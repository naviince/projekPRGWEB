<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../Jadwal/index.php");
    exit();
}

$id_sesi = intval($_GET['id']);

$q_sesi = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto, S.ID_Order, S.File_Hasil, S.Tanggal_Upload_Hasil,
        S.Waktu_Mulai, S.Waktu_Selesai, S.Status_Sesi,
        P.Nama_Pelanggan, P.Email_Pelanggan, P.No_Hp,
        PK.Nama_Paket, PK.Durasi_Waktu, PK.Harga_Paket,
        R.Nama_Ruangan,
        J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai,
        O.Keterangan, O.Status_Order
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
    WHERE S.ID_Sesi_Foto = ? AND S.ID_Karyawan = ? AND S.Status = 1
", array($id_sesi, $id_fotografer));

if (!$q_sesi || !sqlsrv_has_rows($q_sesi)) {
    header("Location: ../Jadwal/index.php?error=notfound");
    exit();
}

$sesi = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) $date = new DateTime($date);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $date->format('d').' '.$bulan[intval($date->format('m'))-1].' '.$date->format('Y');
}
function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) $time = new DateTime($time);
    return $time->format('H:i');
}

$status_text = ['Menunggu', 'Selesai', 'Dibatalkan'][$sesi['Status_Sesi']] ?? 'Unknown';
$status_class = ['badge-terjadwal', 'badge-selesai', 'badge-batal'][$sesi['Status_Sesi']] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Sesi – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096; --sidebar-bg: #ffffff; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
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
        .info-item { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
        .info-value { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); text-align: right; }
        .badge-status { padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-terjadwal { background: #fffbeb; color: #d97706; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }
        .btn-action { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 0.85rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.25); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg, #059669, #047857); }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-muted); }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
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
                            <li><a href="../Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
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
                        <li class="breadcrumb-item"><a href="../Jadwal/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Jadwal</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Detail Sesi</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Detail Sesi Foto</h3>
            </div>
            <a href="../Jadwal/index.php" class="btn-action btn-action-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6 animate-fade-in">
                <div class="card-3d">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-info-circle-fill text-danger me-2"></i>Informasi Sesi</h5>
                        <span class="badge-status <?= $status_class ?>"><?= $status_text ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">ID Sesi</span><span class="info-value">#<?= $sesi['ID_Sesi_Foto'] ?></span></div>
                    <div class="info-item"><span class="info-label">ID Order</span><span class="info-value">#<?= $sesi['ID_Order'] ?></span></div>
                    <div class="info-item"><span class="info-label">Status Order</span><span class="info-value"><?= $sesi['Status_Order'] ?></span></div>
                    <div class="info-item"><span class="info-label">Tanggal Jadwal</span><span class="info-value"><?= formatTanggal($sesi['Tanggal_Jadwal']) ?></span></div>
                    <div class="info-item"><span class="info-label">Jam</span><span class="info-value"><?= formatWaktu($sesi['Jam_Mulai']) ?> - <?= formatWaktu($sesi['Jam_Selesai']) ?></span></div>
                    <div class="info-item"><span class="info-label">Waktu Mulai</span><span class="info-value"><?= $sesi['Waktu_Mulai'] ? formatTanggal($sesi['Waktu_Mulai']).' '.formatWaktu($sesi['Waktu_Mulai']) : '-' ?></span></div>
                    <div class="info-item"><span class="info-label">Waktu Selesai</span><span class="info-value"><?= $sesi['Waktu_Selesai'] ? formatTanggal($sesi['Waktu_Selesai']).' '.formatWaktu($sesi['Waktu_Selesai']) : '-' ?></span></div>
                </div>
            </div>
            <div class="col-lg-6 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="card-3d">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-person-fill text-danger me-2"></i>Informasi Pelanggan</h5>
                    </div>
                    <div class="info-item"><span class="info-label">Nama</span><span class="info-value"><?= htmlspecialchars($sesi['Nama_Pelanggan']) ?></span></div>
                    <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($sesi['Email_Pelanggan']) ?></span></div>
                    <div class="info-item"><span class="info-label">No. HP</span><span class="info-value"><?= htmlspecialchars($sesi['No_Hp']) ?></span></div>
                </div>
            </div>
            <div class="col-lg-6 animate-fade-in" style="animation-delay: 0.2s;">
                <div class="card-3d">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-box-fill text-danger me-2"></i>Informasi Paket</h5>
                    </div>
                    <div class="info-item"><span class="info-label">Nama Paket</span><span class="info-value"><?= htmlspecialchars($sesi['Nama_Paket']) ?></span></div>
                    <div class="info-item"><span class="info-label">Durasi</span><span class="info-value"><?= $sesi['Durasi_Waktu'] ?> menit</span></div>
                    <div class="info-item"><span class="info-label">Harga</span><span class="info-value">Rp <?= number_format($sesi['Harga_Paket'], 0, ',', '.') ?></span></div>
                </div>
            </div>
            <div class="col-lg-6 animate-fade-in" style="animation-delay: 0.3s;">
                <div class="card-3d">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-door-open-fill text-danger me-2"></i>Informasi Ruangan</h5>
                    </div>
                    <div class="info-item"><span class="info-label">Nama Ruangan</span><span class="info-value"><?= htmlspecialchars($sesi['Nama_Ruangan']) ?></span></div>
                    <?php if (!empty($sesi['Keterangan'])): ?>
                    <div class="info-item"><span class="info-label">Keterangan</span><span class="info-value"><?= htmlspecialchars($sesi['Keterangan']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($sesi['File_Hasil'])): ?>
            <div class="col-12 animate-fade-in" style="animation-delay: 0.4s;">
                <div class="card-3d">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-file-earmark-check-fill text-success me-2"></i>File Hasil</h5>
                    </div>
                    <div class="d-flex align-items-center gap-3 p-3" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-radius: 14px;">
                        <i class="bi bi-file-earmark-zip text-success fs-1"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars($sesi['File_Hasil']) ?></div>
                            <div class="text-muted" style="font-size: 0.8rem;">Diupload: <?= formatTanggal($sesi['Tanggal_Upload_Hasil']) ?> <?= formatWaktu($sesi['Tanggal_Upload_Hasil']) ?></div>
                        </div>
                        <a href="../../uploads/hasil/download.php?file=<?= urlencode($sesi['File_Hasil']) ?>" class="btn-action btn-action-success"><i class="bi bi-download"></i> Download</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
    </script>
</body>
</html>