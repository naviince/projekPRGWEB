<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

// HANDLE NOTIFIKASI DARI REDIRECT
$upload_success = false;
$delete_success = false;
$error_msg = '';
if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') { $upload_success = true; }
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') { $delete_success = true; }
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'noid': $error_msg = 'ID Sesi tidak valid!'; break;
        case 'notfound': $error_msg = 'Sesi tidak ditemukan atau Anda tidak berhak mengaksesnya.'; break;
        case 'notcompleted': $error_msg = 'Sesi belum selesai. Hanya sesi selesai yang bisa diupload.'; break;
        default: $error_msg = 'Terjadi kesalahan.';
    }
}

$q_list = sqlsrv_query($conn, "{CALL sp_ReadListSesiBelumUploadFotografer(?)}", array($id_fotografer));

$q_stats = sqlsrv_query($conn, "SELECT COUNT(*) AS total_selesai, SUM(CASE WHEN File_Hasil IS NOT NULL THEN 1 ELSE 0 END) AS sudah_upload, SUM(CASE WHEN File_Hasil IS NULL THEN 1 ELSE 0 END) AS belum_upload FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 1", array($id_fotografer));
$stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) { $date = new DateTime($date); }
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $date->format('d') . ' ' . $bulan[intval($date->format('m')) - 1] . ' ' . $date->format('Y');
}
function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) { $time = new DateTime($time); }
    return $time->format('H:i');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upload Hasil Foto – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9;
            --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --sidebar-width: 260px; --sidebar-collapsed: -260px; --header-height: 70px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg);
            color: var(--text-dark); overflow-x: hidden; margin: 0; padding: 0;
            -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 24px 16px; z-index: 1040;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            overflow-y: auto; scrollbar-width: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar-brand { font-weight: 800; font-size: 1.4rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 32px; display: block; padding: 0 4px; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.8rem; font-weight: 600; display: block; margin-top: 2px; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 16px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 6px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; color: #4a5568; font-weight: 700; text-decoration: none;
            border-radius: 12px; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .nav-link-custom i.me-2 { flex-shrink: 0; }
        .submenu { list-style: none; padding-left: 16px; margin-top: 4px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 7px 14px; color: #718096; font-weight: 600; font-size: 0.82rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,0.03); padding-left: 18px; }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800;
            font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }

        /* MOBILE HEADER */
        .mobile-header {
            display: none; position: fixed; top: 0; left: 0; right: 0;
            height: var(--header-height); background: var(--sidebar-bg);
            border-bottom: 1px solid rgba(255,228,233,0.8); z-index: 1030;
            padding: 0 16px; align-items: center; justify-content: space-between;
        }
        .mobile-brand { font-weight: 800; font-size: 1.2rem; color: var(--p-pink); text-decoration: none; }
        .mobile-brand span { font-size: 0.7rem; color: var(--text-dark); display: block; font-weight: 600; }
        .btn-toggle-sidebar {
            width: 42px; height: 42px; border-radius: 12px; border: none;
            background: var(--s-pink); color: var(--p-pink); font-size: 1.3rem;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            transition: var(--transition-3d); flex-shrink: 0;
        }
        .btn-toggle-sidebar:hover { background: var(--p-pink); color: #fff; }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1035;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-width); padding: 32px 28px; min-height: 100vh; transition: margin-left 0.35s ease; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
        .page-header h3 { font-size: 1.5rem; margin-bottom: 4px; }
        .page-header p { font-size: 0.85rem; }

        /* BREADCRUMB */
        .breadcrumb { font-size: 0.8rem; margin-bottom: 4px; }
        .breadcrumb-item a { color: var(--p-pink); text-decoration: none; font-weight: 600; }
        .breadcrumb-item.active { color: var(--text-muted); font-weight: 600; }

        /* CONTENT CARD - FLAT, TANPA SHADOW HOVER */
        .content-card {
            background: #ffffff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8);
            box-shadow: 0 6px 20px rgba(213,61,102,0.03); padding: 22px;
        }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; }
        .content-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
        .content-badge { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; padding: 5px 12px; border-radius: 8px; font-size: 0.72rem; font-weight: 700; }

        /* STAT CARDS - FLAT, TANPA SHADOW HOVER */
        .stat-card {
            background: #ffffff; border-radius: 16px; padding: 18px;
            border: 1px solid rgba(255,228,233,0.8);
            box-shadow: 0 4px 12px rgba(213,61,102,0.03);
        }
        .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-bottom: 12px;
        }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); }
        .stat-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }

        /* SESI ITEM - TANPA SHADOW HOVER */
        .sesi-item {
            display: flex; align-items: center; gap: 14px; padding: 14px;
            background: linear-gradient(135deg, #ffffff, #FFF0F3);
            border-radius: 16px; margin-bottom: 10px;
            transition: var(--transition-3d); border: 2px solid transparent;
        }
        .sesi-item:hover { transform: translateX(4px); border-color: var(--p-pink); }
        .sesi-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .sesi-time { font-size: 0.82rem; font-weight: 700; color: var(--p-pink); }
        .sesi-title { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .sesi-info { font-size: 0.78rem; color: var(--text-muted); }

        .badge-status { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-belum { background: #fffbeb; color: #d97706; }

        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; border-radius: 10px; padding: 6px 14px; font-weight: 700;
            font-size: 0.78rem; transition: var(--transition-3d); text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.25); color: #ffffff; }
        .btn-action-secondary {
            background: #f1f5f9; color: var(--text-muted); border: none; border-radius: 10px;
            padding: 6px 14px; font-weight: 700; font-size: 0.78rem; transition: var(--transition-3d);
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); transform: translateY(-2px); }

        /* ALERT INFO */
        .alert-info-custom {
            background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 14px;
            border: none; padding: 14px 18px;
        }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }

        /* RESPONSIVE */
        @media (min-width: 1400px) { .main-content { padding: 40px 36px; } }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(var(--sidebar-collapsed)); box-shadow: 4px 0 20px rgba(0,0,0,0.08); }
            .sidebar.open { transform: translateX(0); }
            .mobile-header { display: flex; }
            .main-content { margin-left: 0; padding: 90px 16px 24px; }
            .page-header h3 { font-size: 1.2rem; }
            .page-header p { font-size: 0.8rem; }
            .content-card { padding: 18px; }
        }
        @media (max-width: 767.98px) {
            .main-content { padding: 85px 12px 20px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .sesi-item { padding: 12px; gap: 10px; }
            .sesi-icon { width: 38px; height: 38px; font-size: 1rem; border-radius: 10px; }
            .sesi-title { font-size: 0.85rem; }
            .sesi-time { font-size: 0.78rem; }
            .sesi-info { font-size: 0.72rem; }
            .content-title { font-size: 0.9rem; }
            .btn-action, .btn-action-secondary { padding: 5px 10px; font-size: 0.72rem; }
            .stat-value { font-size: 1.3rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
        }
        @media (max-width: 575.98px) {
            .main-content { padding: 80px 10px 16px; }
            .mobile-header { height: 64px; padding: 0 12px; }
            .mobile-brand { font-size: 1.1rem; }
            .btn-toggle-sidebar { width: 38px; height: 38px; font-size: 1.1rem; }
            .page-header h3 { font-size: 1.1rem; }
            .content-card { padding: 16px; border-radius: 16px; }
            .stat-card { padding: 14px; border-radius: 14px; }
            .stat-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
            .stat-value { font-size: 1.15rem; }
            .stat-label { font-size: 0.72rem; }
        }
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar { padding: 16px 12px; }
            .sidebar-brand { font-size: 1.1rem; margin-bottom: 16px; }
            .nav-link-custom { padding: 8px 12px; font-size: 0.8rem; }
            .mobile-header { height: 52px; }
            .main-content { padding-top: 68px; }
        }
    </style>
</head>
<body>

    <!-- MOBILE HEADER -->
    <div class="mobile-header">
        <button class="btn-toggle-sidebar" onclick="toggleSidebar()" aria-label="Toggle Menu"><i class="bi bi-list"></i></button>
        <a href="../../index.php" class="mobile-brand">SpotLight.<span>Panel Fotografer</span></a>
        <div style="width: 42px;"></div> <!-- spacer agar brand tetap center -->
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<span>Panel Fotografer</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Fotografer/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuJadwal">
                        <span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span><i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="../../Sesi/Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../../Sesi/Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../../Sesi/Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuUpload">
                        <span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span><i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="../../Sesi/RiwayatUpload/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
            </ul>
        </div>
        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="page-header animate-fade-in">
            <div style="min-width: 0;">
                <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php">Dashboard</a></li><li class="breadcrumb-item active">Upload Foto</li></ol></nav>
                <h3 class="fw-bold mb-0">Upload Hasil Foto</h3>
                <p class="text-muted small mb-0">Pilih sesi foto yang sudah selesai untuk upload hasil.</p>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4 animate-fade-in delay-1">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb;"><i class="bi bi-camera-fill"></i></div>
                    <div class="stat-value"><?= $stats['total_selesai'] ?? 0 ?></div>
                    <div class="stat-label">Total Sesi Selesai</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value"><?= $stats['belum_upload'] ?? 0 ?></div>
                    <div class="stat-label">Menunggu Upload</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-value"><?= $stats['sudah_upload'] ?? 0 ?></div>
                    <div class="stat-label">Sudah Diupload</div>
                </div>
            </div>
        </div>

        <!-- LIST SESI -->
        <div class="content-card animate-fade-in delay-2">
            <div class="content-header">
                <h5 class="content-title"><i class="bi bi-list-check text-danger me-2"></i>Daftar Sesi Siap Upload</h5>
                <span class="content-badge">Belum Upload</span>
            </div>

            <div class="alert alert-info-custom mb-4">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                    <div style="font-size: 0.85rem; color: #1e40af;">
                        <strong>Halaman ini hanya menampilkan sesi yang BELUM diupload.</strong>
                        Sesi yang sudah diupload dapat dilihat di menu <a href="../../Sesi/RiwayatUpload/index.php" style="color: var(--p-pink); font-weight: 700;">Riwayat Upload</a>.
                    </div>
                </div>
            </div>

            <?php
            $has_data = false;
            if ($q_list && sqlsrv_has_rows($q_list)):
                $has_data = true;
                while ($row = sqlsrv_fetch_array($q_list, SQLSRV_FETCH_ASSOC)):
            ?>
                <div class="sesi-item">
                    <div class="sesi-icon" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;"><i class="bi bi-camera-fill"></i></div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div style="min-width: 0;">
                                <div class="sesi-title text-truncate"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                <div class="sesi-info"><?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                <div class="sesi-time mt-1"><i class="bi bi-calendar-event me-1"></i><?= formatTanggal($row['Tanggal_Jadwal']) ?> • <?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?></div>
                            </div>
                            <span class="badge-status badge-belum">Belum Upload</span>
                        </div>
                    </div>
                    <a href="../../Role/Fotografer/upload_hasil.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action"><i class="bi bi-cloud-upload"></i> Upload</a>
                </div>
            <?php endwhile; endif; ?>

            <?php if (!$has_data): ?>
                <div class="text-center py-5">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="bi bi-check-circle fs-1" style="color: #059669;"></i>
                    </div>
                    <h6 class="fw-bold text-muted">Semua Sesi Sudah Diupload!</h6>
                    <p class="text-muted" style="font-size: 0.8rem;">Tidak ada sesi foto yang menunggu upload. Semua hasil foto sudah tersimpan.</p>
                    <a href="../../Sesi/RiwayatUpload/index.php" class="btn-action mt-2"><i class="bi bi-clock-history"></i> Lihat Riwayat Upload</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        document.querySelectorAll('.submenu-link, .nav-link-custom:not(.btn-toggle-submenu)').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) { const sidebar = document.getElementById('sidebar'); if (sidebar.classList.contains('open')) toggleSidebar(); }
            });
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
        <?php if ($upload_success): ?>Swal.fire({ icon: 'success', title: 'Upload Berhasil!', text: 'File hasil foto berhasil diupload dan tersimpan.', confirmButtonColor: '#D53D66', confirmButtonText: 'Oke' });<?php endif; ?>
        <?php if ($delete_success): ?>Swal.fire({ icon: 'success', title: 'File Dihapus!', text: 'File hasil foto berhasil dihapus dari sistem.', confirmButtonColor: '#D53D66', confirmButtonText: 'Oke' });<?php endif; ?>
        <?php if (!empty($error_msg)): ?>Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan!', text: '<?= addslashes($error_msg) ?>', confirmButtonColor: '#D53D66', confirmButtonText: 'Oke' });<?php endif; ?>
    </script>
</body>
</html>