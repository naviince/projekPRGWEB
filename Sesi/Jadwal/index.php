<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES FOTOGRAFER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

// Auto-expire sesi Menunggu yang jadwalnya udah lewat -> Dibatalkan (2)
// Wajib dipanggil di sini juga, jangan cuma di dashboard, karena
// fotografer bisa langsung buka halaman ini tanpa lewat dashboard dulu.
sqlsrv_query($conn, "{CALL sp_AutoExpireSesiFoto (?)}", ['system']);

// =====================================================
// QUERY: SEMUA JADWAL SESI FOTOGRAFER
// =====================================================
$q_jadwal = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.Status_Sesi,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        S.File_Hasil,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        Slot.ID_Jadwal,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        SlotCount.Total_Slot,
        O.Keterangan
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        -- Slot jadwal paling awal untuk order ini -- dipakai sebagai
        -- representasi tanggal/jam sesi. Cegah 1 sesi tampil berkali-kali
        -- kalau order-nya multi-slot (pola sama kayak
        -- sp_ReadSesiTerjadwalFotografer / sp_ReadSesiSelesaiFotografer).
        SELECT TOP 1 J.ID_Jadwal, J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        SELECT COUNT(*) AS Total_Slot
        FROM Order_Jadwal OJ2
        JOIN Jadwal_Studio J2 ON OJ2.ID_Jadwal = J2.ID_Jadwal
        WHERE OJ2.ID_Order = O.ID_Order AND J2.Status = 1 AND J2.Is_Deleted = 0
    ) SlotCount
    WHERE S.ID_Karyawan = ? AND S.Status = 1 AND O.Status = 1 
      AND O.Status_Order <> 4
    ORDER BY Slot.Tanggal_Jadwal ASC, Slot.Jam_Mulai ASC
", array($id_fotografer));

// Ambil data untuk kalender (group by tanggal)
$jadwal_by_date = [];
$total_terlewat = 0;
if ($q_jadwal && sqlsrv_has_rows($q_jadwal)) {
    while ($row = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
        // Format tanggal jadi string aman (jaga-jaga kalau driver return string, bukan objek DateTime)
        $tgl_obj = $row['Tanggal_Jadwal'];
        $tgl = (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) ? $tgl_obj->format('Y-m-d') : date('Y-m-d', strtotime($tgl_obj));

        $js_obj = $row['Jam_Selesai'];
        $jam_selesai_raw = (is_object($js_obj) && method_exists($js_obj, 'format')) ? $js_obj->format('H:i:s') : substr((string)$js_obj, 0, 8);

        // =====================================================
        // VALIDASI: TANDAI SESI YANG WAKTUNYA SUDAH LEWAT
        // Sesi dengan Status_Sesi = 0 (Menunggu) yang jam
        // selesainya sudah lewat waktu sekarang -> ditandai
        // "Terlewat" supaya fotografer/admin sadar dan segera
        // update status (mulai sesi / hubungi admin). Data TIDAK
        // dihapus otomatis karena ini bukti kerja fotografer yang
        // harus tetap tercatat untuk akuntabilitas.
        // =====================================================
        $ts_selesai = strtotime($tgl . ' ' . $jam_selesai_raw);
        $row['is_terlewat'] = ((int)$row['Status_Sesi'] === 0 && $ts_selesai !== false && $ts_selesai < time());
        if ($row['is_terlewat']) { $total_terlewat++; }

        if (!isset($jadwal_by_date[$tgl])) {
            $jadwal_by_date[$tgl] = [];
        }
        $jadwal_by_date[$tgl][] = $row;
    }
}

// =====================================================
// AMBIL DATA PROFIL FOTOGRAFER
// =====================================================
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_fotografer));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) {
    $d_profile = array_change_key_case($d_profile, CASE_LOWER);
}

$nama_fotografer = $d_profile['nama_karyawan'] ?? 'Fotografer';
$foto_fotografer = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_fotografer)) 
    ? "../../assets/img/karyawan/" . $foto_fotografer 
    : $default_svg_avatar;

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) $date = new DateTime($date);
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $date->format('d') . ' ' . $bulan[intval($date->format('m')) - 1] . ' ' . $date->format('Y');
}

function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) $time = new DateTime($time);
    return $time->format('H:i');
}

function getStatusBadge($status, $is_terlewat = false) {
    if ($is_terlewat) return '<span class="badge-status" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Terlewat</span>';
    if ($status == 0) return '<span class="badge-status badge-terjadwal">Menunggu</span>';
    if ($status == 1) return '<span class="badge-status badge-selesai">Selesai</span>';
    return '<span class="badge-status badge-batal">Dibatalkan</span>';
}

function getStatusIcon($status) {
    if ($status == 0) return 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;';
    if ($status == 1) return 'background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;';
    return 'background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626;';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Sesi Foto – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; 
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); padding: 25px; position: relative; overflow: hidden; }
        .card-3d::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--p-pink), var(--accent-pink)); opacity: 0; transition: opacity 0.3s ease; }
        .card-3d:hover { transform: translateY(-4px); box-shadow: 0 22px 45px rgba(213, 61, 102, 0.1); border-color: var(--p-pink); }
        .card-3d:hover::before { opacity: 1; }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .content-title { font-weight: 700; font-size: 1.1rem; color: var(--text-dark); }
        .sesi-item { display: flex; align-items: center; gap: 14px; padding: 16px; background: linear-gradient(135deg, #ffffff, #FFF0F3); border-radius: 16px; margin-bottom: 12px; transition: var(--transition-3d); border: 2px solid transparent; cursor: pointer; }
        .sesi-item:hover { transform: translateX(6px); border-color: var(--p-pink); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.1); }
        .sesi-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .sesi-time { font-size: 0.85rem; font-weight: 700; color: var(--p-pink); }
        .sesi-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
        .sesi-info { font-size: 0.8rem; color: var(--text-muted); }
        .badge-status { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
        .badge-terjadwal { background: #fffbeb; color: #d97706; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }
        .btn-action { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 10px; padding: 8px 16px; font-weight: 700; font-size: 0.8rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.25); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg, #059669, #047857); }
        .btn-action-success:hover { box-shadow: 0 6px 15px rgba(5, 150, 105, 0.25); }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-muted); }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); }
        .date-header { background: linear-gradient(135deg, var(--s-pink), #ffffff); border-radius: 14px; padding: 12px 20px; margin-bottom: 16px; border-left: 4px solid var(--p-pink); }
        .date-header-text { font-weight: 800; font-size: 0.95rem; color: var(--p-pink); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
        
        /* Modal custom styles */
        .modal-content { border: none; }
        .modal-backdrop.show { opacity: 0.5; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Fotografer</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Fotografer/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuJadwal"><span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span><i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i></a>
                    <div class="submenu show" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuUpload"><span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
                    <div class="submenu" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="../Upload/index.php" class="submenu-link"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="../RiwayatUpload/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button></div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Jadwal Saya</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Jadwal Sesi Foto</h3>
                <p class="text-muted small mb-0">Semua jadwal sesi foto yang diassign ke Anda.</p>
            </div>
        </div>

        <div class="card-3d animate-fade-in">
            <div class="content-header">
                <h5 class="content-title"><i class="bi bi-calendar-week-fill text-danger me-2"></i>Daftar Jadwal</h5>
                <div class="d-flex gap-2">
                    <?php if ($total_terlewat > 0): ?>
                        <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:700;border-radius:8px;font-size:0.75rem;border:1px solid #fde68a;"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $total_terlewat ?> Terlewat</span>
                    <?php endif; ?>
                    <span class="badge" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem;"><?= count($jadwal_by_date) ?> Hari</span>
                </div>
            </div>

            <?php if (!empty($jadwal_by_date)): ?>
                <?php foreach ($jadwal_by_date as $tgl => $sesi_list): ?>
                    <div class="date-header">
                        <span class="date-header-text"><i class="bi bi-calendar-event me-2"></i><?= formatTanggal(new DateTime($tgl)) ?></span>
                        <span class="float-end" style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;"><?= count($sesi_list) ?> Sesi</span>
                    </div>
                    <?php foreach ($sesi_list as $row): ?>
                        <div class="sesi-item" 
                             data-id="<?= $row['ID_Sesi_Foto'] ?>"
                             data-pelanggan="<?= htmlspecialchars($row['Nama_Pelanggan']) ?>"
                             data-paket="<?= htmlspecialchars($row['Nama_Paket']) ?>"
                             data-ruangan="<?= htmlspecialchars($row['Nama_Ruangan']) ?>"
                             data-tanggal="<?= formatTanggal(new DateTime($tgl)) ?>"
                             data-jam-mulai="<?= formatWaktu($row['Jam_Mulai']) ?>"
                             data-jam-selesai="<?= formatWaktu($row['Jam_Selesai']) ?>"
                             data-durasi="<?= $row['Durasi_Waktu'] ?>"
                             data-status="<?= $row['Status_Sesi'] ?>"
                             data-terlewat="<?= $row['is_terlewat'] ? '1' : '0' ?>"
                             data-keterangan="<?= htmlspecialchars($row['Keterangan'] ?? '') ?>"
                             data-file-hasil="<?= !empty($row['File_Hasil']) ? '1' : '0' ?>"
                             data-id-order="<?= $row['ID_Order'] ?>">
                            <div class="sesi-icon" style="<?= $row['is_terlewat'] ? 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #b45309;' : getStatusIcon($row['Status_Sesi']) ?>">
                                <i class="bi <?= $row['is_terlewat'] ? 'bi-exclamation-triangle-fill' : 'bi-camera-fill' ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="sesi-time">
                                            <i class="bi bi-clock-fill me-1"></i><?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?>
                                            <span class="ms-2">(<?= $row['Durasi_Waktu'] ?> menit)</span>
                                        </div>
                                        <div class="sesi-title"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                        <div class="sesi-info">
                                            <?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?>
                                        </div>
                                        <?php if ($row['is_terlewat']): ?>
                                            <div class="sesi-info mt-1" style="color:#b45309;font-weight:700;"><i class="bi bi-clock-history me-1"></i>Waktu sesi sudah lewat dan belum dimulai. Segera update status atau hubungi admin.</div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['Keterangan'])): ?>
                                            <div class="sesi-info mt-1"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['Keterangan']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?= getStatusBadge($row['Status_Sesi'], $row['is_terlewat']) ?>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-detail-modal" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none; border: none;"
                                                    onclick="event.stopPropagation(); openDetailModal(this);">
                                                <i class="bi bi-eye"></i> Detail
                                            </button>
                                            <?php if ($row['Status_Sesi'] == 0 && $row['is_terlewat']): ?>
                                                <a href="../Proses/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-sm" style="padding: 5px 10px; font-size: 0.75rem; background:#fef3c7;color:#b45309;border:1px solid #fde68a;">
                                                    <i class="bi bi-exclamation-triangle"></i> Mulai (Terlambat)
                                                </a>
                                            <?php elseif ($row['Status_Sesi'] == 0): ?>
                                                <a href="../Proses/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-action-success btn-sm" style="padding: 5px 10px; font-size: 0.75rem;">
                                                    <i class="bi bi-play-fill"></i> Mulai
                                                </a>
                                            <?php elseif ($row['Status_Sesi'] == 1 && empty($row['File_Hasil'])): ?>
                                                <a href="../Upload/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-sm" style="padding: 5px 10px; font-size: 0.75rem;">
                                                    <i class="bi bi-cloud-upload"></i> Upload
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="bi bi-calendar-x fs-1" style="color: #94a3b8;"></i>
                    </div>
                    <h6 class="fw-bold text-muted">Tidak Ada Jadwal</h6>
                    <p class="text-muted" style="font-size: 0.85rem;">Belum ada sesi foto yang diassign ke Anda.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 22px 45px rgba(213, 61, 102, 0.15);">
                <div class="modal-header" style="border-bottom: 1px solid var(--light-pink); padding: 20px 24px;">
                    <h5 class="modal-title fw-bold" style="color: var(--p-pink);"><i class="bi bi-eye me-2"></i>Detail Sesi Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div class="text-center mb-4">
                        <div id="modalStatusIcon" style="width: 70px; height: 70px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 12px;">
                            <i class="bi bi-camera-fill"></i>
                        </div>
                        <div id="modalStatusBadge" class="badge-status mb-2"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="small fw-bold text-muted mb-1">Nama Pelanggan</label>
                            <div class="p-2 rounded-3" style="background: var(--s-pink);">
                                <span id="modalPelanggan" class="fw-bold" style="color: var(--text-dark);"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Tanggal</label>
                            <div class="p-2 rounded-3" style="background: #f8fafc;">
                                <span id="modalTanggal" class="fw-semibold" style="color: var(--text-dark);"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Waktu</label>
                            <div class="p-2 rounded-3" style="background: #f8fafc;">
                                <span id="modalWaktu" class="fw-semibold" style="color: var(--text-dark);"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Paket</label>
                            <div class="p-2 rounded-3" style="background: #f8fafc;">
                                <span id="modalPaket" class="fw-semibold" style="color: var(--text-dark);"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Ruangan</label>
                            <div class="p-2 rounded-3" style="background: #f8fafc;">
                                <span id="modalRuangan" class="fw-semibold" style="color: var(--text-dark);"></span>
                            </div>
                        </div>
                        <div class="col-12" id="modalKeteranganWrap" style="display: none;">
                            <label class="small fw-bold text-muted mb-1">Keterangan</label>
                            <div class="p-2 rounded-3" style="background: #fffbeb; border: 1px solid #fde68a;">
                                <span id="modalKeterangan" class="fw-semibold" style="color: #b45309;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--light-pink); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 700; font-size: 0.8rem;">Tutup</button>
                    <a id="modalProsesLink" href="#" class="btn btn-action" style="border-radius: 10px; font-weight: 700; font-size: 0.8rem; display: none;">
                        <i class="bi bi-play-fill"></i> Proses Sesi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');
                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.icon-chevron').forEach(icon => icon.style.transform = 'rotate(0deg)');
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }
        
        // Detail Modal Function
        function openDetailModal(btn) {
            const item = btn.closest('.sesi-item');
            const data = item.dataset;
            
            document.getElementById('modalPelanggan').textContent = data.pelanggan;
            document.getElementById('modalTanggal').textContent = data.tanggal;
            document.getElementById('modalWaktu').textContent = data.jamMulai + ' - ' + data.jamSelesai + ' (' + data.durasi + ' menit)';
            document.getElementById('modalPaket').textContent = data.paket;
            document.getElementById('modalRuangan').textContent = data.ruangan;
            
            // Status badge
            const statusBadge = document.getElementById('modalStatusBadge');
            const statusIcon = document.getElementById('modalStatusIcon');
            
            if (data.terlewat === '1') {
                statusBadge.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Terlewat';
                statusBadge.className = 'badge-status mb-2';
                statusBadge.style.cssText = 'background:#fef3c7;color:#b45309;border:1px solid #fde68a;';
                statusIcon.style.cssText = 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #b45309;';
                statusIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
            } else if (data.status === '0') {
                statusBadge.innerHTML = 'Menunggu';
                statusBadge.className = 'badge-status badge-terjadwal mb-2';
                statusBadge.style.cssText = '';
                statusIcon.style.cssText = 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;';
                statusIcon.innerHTML = '<i class="bi bi-camera-fill"></i>';
            } else if (data.status === '1') {
                statusBadge.innerHTML = 'Selesai';
                statusBadge.className = 'badge-status badge-selesai mb-2';
                statusBadge.style.cssText = '';
                statusIcon.style.cssText = 'background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;';
                statusIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            } else {
                statusBadge.innerHTML = 'Dibatalkan';
                statusBadge.className = 'badge-status badge-batal mb-2';
                statusBadge.style.cssText = '';
                statusIcon.style.cssText = 'background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626;';
                statusIcon.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
            }
            
            // Keterangan
            const keteranganWrap = document.getElementById('modalKeteranganWrap');
            if (data.keterangan && data.keterangan.trim() !== '') {
                document.getElementById('modalKeterangan').textContent = data.keterangan;
                keteranganWrap.style.display = 'block';
            } else {
                keteranganWrap.style.display = 'none';
            }
            
            // Proses / Upload link
            const prosesLink = document.getElementById('modalProsesLink');
            if (data.status === '0') {
                prosesLink.href = '../Proses/index.php?id=' + data.id;
                prosesLink.innerHTML = '<i class="bi bi-play-fill"></i> Proses Sesi';
                prosesLink.style.display = 'inline-flex';
            } else if (data.status === '1' && data.fileHasil === '0') {
                prosesLink.href = '../Upload/index.php?id=' + data.id;
                prosesLink.innerHTML = '<i class="bi bi-cloud-upload"></i> Upload Hasil';
                prosesLink.style.display = 'inline-flex';
            } else {
                prosesLink.style.display = 'none';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    </script>
</body>
</html>