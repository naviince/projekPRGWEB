<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

$q_selesai = sqlsrv_query($conn, "{CALL sp_ReadSesiSelesaiFotografer(?)}", array($id_fotografer));

$sesi_selesai = [];
if ($q_selesai) {
    while ($row = sqlsrv_fetch_array($q_selesai, SQLSRV_FETCH_ASSOC)) {
        $sesi_selesai[] = $row;
    }
}
$has_data = !empty($sesi_selesai);

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Selesai – SpotLight Studio</title>
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
        
        /* card-3d: elemen non-clickable, hover effect dihapus */
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); padding: 25px; position: relative; overflow: hidden; }
        
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .content-title { font-weight: 700; font-size: 1.1rem; color: var(--text-dark); }
        .sesi-item { display: flex; align-items: center; gap: 14px; padding: 20px; background: linear-gradient(135deg, #ffffff, #ecfdf5); border-radius: 16px; margin-bottom: 12px; transition: var(--transition-3d); border: 2px solid transparent; }
        .sesi-item:hover { transform: translateX(6px); border-color: #059669; box-shadow: 0 8px 20px rgba(5, 150, 105, 0.1); }
        .sesi-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .sesi-time { font-size: 0.9rem; font-weight: 700; color: #059669; }
        .sesi-title { font-weight: 700; font-size: 1rem; color: var(--text-dark); }
        .sesi-info { font-size: 0.85rem; color: var(--text-muted); }
        .badge-status { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
        .badge-upload { background: #dbeafe; color: #2563eb; }
        .badge-belum { background: #fffbeb; color: #d97706; }
        .btn-action { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 10px; padding: 8px 16px; font-weight: 700; font-size: 0.8rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.25); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg, #059669, #047857); }
        .btn-action-success:hover { box-shadow: 0 6px 15px rgba(5, 150, 105, 0.25); }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-muted); }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); }
        .file-info { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; color: #166534; font-weight: 600; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }

        /* Mobile menu & overlay */
        .mobile-menu-btn {
            display: none; width: 44px; height: 44px; border-radius: 12px;
            background: #fff; border: 2px solid var(--light-pink); color: var(--p-pink);
            align-items: center; justify-content: center; font-size: 1.4rem; cursor: pointer;
            transition: var(--transition-3d); flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mobile-menu-btn:hover { background: var(--s-pink); transform: scale(1.05); }
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(30,30,36,0.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 99; opacity: 0; transition: opacity 0.35s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        @media (max-width: 992px) {
            .mobile-menu-btn { display: inline-flex; }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                box-shadow: none;
            }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 10px 0 50px rgba(0,0,0,0.15); }
            .main-content { margin-left: 0; padding: 24px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 18px; }
            .content-header { flex-direction: column; align-items: flex-start; }
            .sesi-item { flex-direction: column; align-items: flex-start; gap: 12px; }
            .sesi-icon { width: 44px; height: 44px; font-size: 1.2rem; }
            .d-flex.justify-content-between.align-items-start { flex-direction: column; gap: 12px; width: 100%; }
            .text-end { text-align: left !important; width: 100%; }
            .mt-3.d-flex.gap-2 { flex-direction: column; }
            .btn-action, .btn-action-success, .btn-action-secondary { width: 100%; justify-content: center; }
        }
        @media (max-width: 576px) {
            .main-content { padding: 14px; }
            .sidebar-brand { font-size: 1.3rem; margin-bottom: 30px; }
            .nav-link-custom { padding: 10px 14px; font-size: 0.85rem; }
            .sesi-item { padding: 14px; }
            .sesi-title { font-size: 0.9rem; }
            .sesi-time { font-size: 0.8rem; }
            .sesi-info { font-size: 0.78rem; }
            h3.fw-bold { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
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
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
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
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                            <li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                            <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Sesi Selesai</li>
                        </ol>
                    </nav>
                    <h3 class="fw-bold mb-0">Sesi Selesai</h3>
                    <p class="text-muted small mb-0">Riwayat sesi foto yang sudah selesai diproses.</p>
                </div>
            </div>
        </div>

        <div class="card-3d animate-fade-in">
            <div class="content-header">
                <h5 class="content-title"><i class="bi bi-check-circle-fill text-success me-2"></i>Daftar Sesi Selesai</h5>
            </div>

            <?php
            if ($has_data):
                foreach ($sesi_selesai as $row):
                    $is_uploaded = !empty($row['File_Hasil']);
            ?>
                <div class="sesi-item"
                     data-id="<?= $row['ID_Sesi_Foto'] ?>"
                     data-pelanggan="<?= htmlspecialchars($row['Nama_Pelanggan']) ?>"
                     data-paket="<?= htmlspecialchars($row['Nama_Paket']) ?>"
                     data-ruangan="<?= htmlspecialchars($row['Nama_Ruangan']) ?>"
                     data-tanggal="<?= htmlspecialchars(formatTanggal($row['Tanggal_Jadwal'])) ?>"
                     data-jam-mulai="<?= formatWaktu($row['Jam_Mulai']) ?>"
                     data-jam-selesai="<?= formatWaktu($row['Jam_Selesai']) ?>"
                     data-durasi="<?= $row['Durasi_Waktu'] ?>"
                     data-slot="<?= (int)($row['Total_Slot'] ?? 1) ?>"
                     data-keterangan="<?= htmlspecialchars($row['Keterangan'] ?? '') ?>"
                     data-mulai="<?= $row['Waktu_Mulai'] ? htmlspecialchars(formatTanggal($row['Waktu_Mulai']).' '.formatWaktu($row['Waktu_Mulai'])) : '-' ?>"
                     data-selesai="<?= $row['Waktu_Selesai'] ? htmlspecialchars(formatTanggal($row['Waktu_Selesai']).' '.formatWaktu($row['Waktu_Selesai'])) : '-' ?>"
                     data-file="<?= htmlspecialchars($row['File_Hasil'] ?? '') ?>">
                    <div class="sesi-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="sesi-time">
                                    <i class="bi bi-calendar-check me-1"></i><?= formatTanggal($row['Tanggal_Jadwal']) ?>
                                    <span class="ms-2"><i class="bi bi-clock me-1"></i><?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?></span>
                                    <?php if (($row['Total_Slot'] ?? 1) > 1): ?>
                                        <span class="ms-2 badge" style="background:#dbeafe;color:#2563eb;font-weight:700;font-size:0.7rem;"><i class="bi bi-collection me-1"></i><?= $row['Total_Slot'] ?> Slot</span>
                                    <?php endif; ?>
                                </div>
                                <div class="sesi-title mt-1"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                <div class="sesi-info">
                                    <?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?> • <?= $row['Durasi_Waktu'] ?> menit
                                </div>
                                <div class="sesi-info mt-1">
                                    <i class="bi bi-camera me-1"></i>
                                    <?= $row['Waktu_Mulai'] ? formatTanggal($row['Waktu_Mulai']).' '.formatWaktu($row['Waktu_Mulai']) : '-' ?>
                                    <span class="mx-1">→</span>
                                    <?= $row['Waktu_Selesai'] ? formatTanggal($row['Waktu_Selesai']).' '.formatWaktu($row['Waktu_Selesai']) : '-' ?>
                                </div>
                                <?php if ($is_uploaded): ?>
                                    <div class="mt-2">
                                        <span class="file-info"><i class="bi bi-file-earmark-check"></i> <?= htmlspecialchars($row['File_Hasil']) ?></span>
                                        <span class="file-info ms-2" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-clock-history"></i> <?= formatTanggal($row['Tanggal_Upload_Hasil']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if ($is_uploaded): ?>
                                    <span class="badge-status badge-upload">Sudah Upload</span>
                                <?php else: ?>
                                    <span class="badge-status badge-belum">Belum Upload</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <?php if (!$is_uploaded): ?>
                                <a href="../Upload/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action">
                                    <i class="bi bi-cloud-upload"></i> Upload Hasil
                                </a>
                            <?php else: ?>
                                <a href="../../uploads/hasil/download.php?file=<?= urlencode($row['File_Hasil']) ?>" class="btn-action btn-action-success">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <a href="../Upload/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-action-secondary">
                                    <i class="bi bi-arrow-repeat"></i> Upload Ulang
                                </a>
                            <?php endif; ?>
                            <a href="../Detail/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-action-secondary" onclick="return openDetailModal(this, event);">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>

            <?php if (!$has_data): ?>
                <div class="text-center py-5">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="bi bi-inbox fs-1" style="color: #94a3b8;"></i>
                    </div>
                    <h6 class="fw-bold text-muted">Belum Ada Sesi Selesai</h6>
                    <p class="text-muted" style="font-size: 0.85rem;">Sesi foto yang sudah selesai akan muncul di sini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== SIDEBAR TOGGLE (MOBILE) =====
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        document.querySelectorAll('.sidebar .nav-link-custom, .sidebar .submenu-link, .sidebar .btn-logout').forEach(el => {
            el.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('mobile-open')) toggleSidebar();
                }
            });
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

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

        function openDetailModal(btn, e) {
            if (e) e.preventDefault();
            const item = btn.closest('.sesi-item');
            const d = item.dataset;

            const slotInfo = parseInt(d.slot, 10) > 1
                ? '<span class="badge" style="background:#dbeafe;color:#2563eb;font-weight:700;">' + d.slot + ' Slot Jadwal</span>'
                : '';

            const keteranganHtml = (d.keterangan && d.keterangan.trim() !== '')
                ? '<hr style="margin:12px 0;border-color:#f1f5f9;">' +
                  '<p style="font-size:0.85rem;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:8px 12px;border-radius:8px;">' +
                  '<i class="bi bi-info-circle me-1"></i>' + d.keterangan + '</p>'
                : '';

            const fileHtml = (d.file && d.file.trim() !== '')
                ? '<p><strong>File Hasil:</strong> ' + d.file + '</p>'
                : '<p style="color:#d97706;"><strong>File Hasil:</strong> Belum diunggah</p>';

            Swal.fire({
                title: 'Detail Sesi Foto',
                html: '<div style="text-align:left;">' +
                      '<p><strong>Pelanggan:</strong> ' + d.pelanggan + '</p>' +
                      '<p><strong>Tanggal Jadwal:</strong> ' + d.tanggal + '</p>' +
                      '<p><strong>Waktu Jadwal:</strong> ' + d.jamMulai + ' - ' + d.jamSelesai + ' (' + d.durasi + ' menit)</p>' +
                      '<p><strong>Paket:</strong> ' + d.paket + '</p>' +
                      '<p><strong>Ruangan:</strong> ' + d.ruangan + '</p>' +
                      (slotInfo ? '<p>' + slotInfo + '</p>' : '') +
                      '<hr style="margin:12px 0;border-color:#f1f5f9;">' +
                      '<p><strong>Sesi Berlangsung:</strong> ' + d.mulai + ' → ' + d.selesai + '</p>' +
                      fileHtml +
                      keteranganHtml +
                      '</div>',
                icon: 'success',
                confirmButtonColor: '#D53D66',
                confirmButtonText: 'Tutup'
            });

            return false;
        }
    </script>
</body>
</html>