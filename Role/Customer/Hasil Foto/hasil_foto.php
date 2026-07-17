<?php
session_start();
// PERBAIKAN PATH: koneksi naik 3 tingkat
include '../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    // PERBAIKAN PATH: redirect naik 3 tingkat ke login.php di root
    header("Location: ../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil ---
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
// PERBAIKAN PATH: letak foto profil naik 3 tingkat ke root
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// QUERY: Hasil Foto yang bisa diakses customer
// =====================================================
$q_hasil = sqlsrv_query($conn, "{CALL sp_ReadHasilFotoRingkasanCustomer(?)}", array($id_customer));

// =====================================================
// QUERY: Sesi yang sudah ada hasil foto tapi order belum Lunas
// =====================================================
$q_menunggu = sqlsrv_query($conn, "{CALL sp_HitungHasilFotoMenungguCustomer(?)}", array($id_customer));
$d_menunggu = $q_menunggu ? sqlsrv_fetch_array($q_menunggu, SQLSRV_FETCH_ASSOC) : null;
$total_menunggu = $d_menunggu['Total_Menunggu'] ?? 0;

function formatUkuran($bytes) {
    if ($bytes <= 0) return '0 KB';
    $units = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

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
    <title>Hasil Foto Saya - SpotLight Studio</title>
    <!-- PERBAIKAN PATH: pemanggilan aset CSS naik 3 tingkat -->
    <link href="../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --success: #059669;
            --warning: #d97706;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS ===== */
        .top-navbar {
            background: #ffffff;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 30px rgba(0,0,0,0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        .nav-link-item {
            color: #4a5568;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--transition-smooth);
            padding: 8px 0;
            position: relative;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--p-pink);
            border-radius: 3px;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.25);
        }
        .nav-btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.35);
            color: #fff;
        }
        .nav-avatar-wrapper {
            position: relative;
        }
        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .nav-avatar:hover {
            transform: scale(1.05);
            border-color: var(--p-pink);
        }
        .nav-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            padding: 12px;
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid #f1f5f9;
        }
        .nav-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 0;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== MAIN CONTAINER ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== HERO ===== */
        .hero-hasil {
            background: linear-gradient(135deg, var(--p-pink) 0%, var(--d-pink) 50%, #b82e52 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(216, 63, 103, 0.15);
        }
        .hero-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 8px;
        }
        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* ===== WAITING CARD ===== */
        .waiting-card {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fcd34d;
            border-radius: 20px;
            padding: 24px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
        }
        .waiting-icon {
            width: 50px;
            height: 50px;
            background: #f59e0b;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .waiting-card h4 {
            font-weight: 800;
            color: #92400e;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .waiting-card p {
            color: #b45309;
            font-size: 0.85rem;
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* ===== INFO ALERT ===== */
        .info-alert {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 32px;
            border: 1px solid #bfdbfe;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .info-alert i {
            font-size: 1.4rem;
            color: #1e40af;
        }
        .info-alert-text {
            font-size: 0.85rem;
            color: #1e40af;
            font-weight: 500;
            line-height: 1.5;
        }

        /* ===== HASIL GRID ===== */
        .hasil-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 28px;
        }
        .hasil-card {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: var(--transition-smooth);
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }
        .hasil-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.1);
            border-color: var(--light-pink);
        }
        .hasil-header {
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            padding: 24px;
            border-bottom: 1px solid #f3f4f6;
        }
        .hasil-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: var(--success);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 12px;
            border: 1px solid #a7f3d0;
        }
        .hasil-paket {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .hasil-ruangan {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hasil-body {
            padding: 20px 24px;
        }
        .hasil-info {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .hasil-info:last-child {
            border-bottom: none;
        }
        .hasil-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hasil-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .hasil-footer {
            padding: 0 24px 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* === TOMBOL UTAMA: BUKA GALERI (Dark Style) === */
        .btn-gallery-trigger {
            background: #1e1e24;
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition-smooth);
            cursor: pointer;
        }
        .btn-gallery-trigger:hover {
            background: #32323d;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        /* === TOMBOL BARU: DOWNLOAD ZIP (Elegant Pink Gradient) === */
        .btn-download {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff !important;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 800;
            text-decoration: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15);
        }
        .btn-download:hover {
            background: linear-gradient(135deg, #e0456f, var(--p-pink));
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
        }

        /* ===== CUSTOM LIGHTBOX SLIDER (MODAL) ===== */
        .lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .lightbox-modal.show {
            display: flex;
            opacity: 1;
        }
        .lightbox-container {
            position: relative;
            width: 90%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
            align-items: center;
            user-select: none;
        }
        .lightbox-img-wrapper {
            position: relative;
            max-height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border-radius: 12px;
            background: #000;
        }
        .lightbox-img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            display: block;
            transition: transform 0.25s ease-out;
        }
        /* Navigation Controls */
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.25rem;
            transition: var(--transition-smooth);
            z-index: 2010;
        }
        .lightbox-nav:hover {
            background: var(--p-pink);
            border-color: var(--p-pink);
            transform: translateY(-50%) scale(1.1);
        }
        .lightbox-prev { left: -70px; }
        .lightbox-next { right: -70px; }
        
        .lightbox-close {
            position: absolute;
            top: -50px;
            right: 0;
            color: #ffffff;
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
        }
        .lightbox-close:hover {
            color: var(--p-pink);
        }
        .lightbox-info {
            width: 100%;
            text-align: center;
            margin-top: 15px;
            color: #ffffff;
        }
        .lightbox-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: #f1f5f9;
        }
        .lightbox-counter {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .lightbox-download-single {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #ffffff;
            background: rgba(216, 63, 103, 0.2);
            border: 1px solid rgba(216, 63, 103, 0.4);
            padding: 6px 14px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 10px;
            transition: var(--transition-smooth);
        }
        .lightbox-download-single:hover {
            background: var(--p-pink);
            color: #ffffff;
            border-color: var(--p-pink);
        }

        /* ===== EMPTY STATE ===== */
        .empty-hasil {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-hasil i {
            font-size: 5rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-hasil h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-hasil p {
            color: var(--text-muted);
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto;
        }
        .empty-hasil .btn-action {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
        }
        .empty-hasil .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1100px) {
            .lightbox-prev { left: 10px; }
            .lightbox-next { right: 10px; }
            .lightbox-close { right: 10px; }
        }
        @media (max-width: 992px) {
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .nav-menu-center { display: none; }
            .hero-hasil { padding: 30px 20px; }
            .hasil-grid { grid-template-columns: 1fr; }
            .waiting-card { flex-direction: column; text-align: center; }
            .info-alert { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="../../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="hasil_foto.php" class="nav-link-item active">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                        <i class="bi bi-house-door"></i> Kembali ke Beranda
                    </a>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item logout" onclick="confirmLogout()">
                        <i class="bi bi-box-arrow-right"></i> Keluar Sistem
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- HERO -->
        <div class="hero-hasil">
            <div class="hero-title"><i class="bi bi-images me-2"></i>Hasil Foto Saya</div>
            <div class="hero-subtitle">Unduh dokumentasi hasil pemotretan dari setiap sesi pemotretan Anda</div>
        </div>

        <?php if ($total_menunggu > 0): ?>
        <!-- INFO: Menunggu Pelunasan -->
        <div class="waiting-card">
            <div class="waiting-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <h4>Ada <?= (int)$total_menunggu ?> Hasil Foto Menunggu Pelunasan</h4>
                <p>Proses unggah hasil foto oleh fotografer telah selesai. Namun berkas belum dapat diakses sepenuhnya karena status administrasi order Anda masih menunggu pelunasan. Silakan selesaikan pelunasan pembayaran Anda terlebih dahulu.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- INFO ALERT -->
        <div class="info-alert">
            <i class="bi bi-info-circle-fill"></i>
            <div class="info-alert-text">
                Semua hasil foto di bawah ini siap diunduh secara instan. Anda dapat melihat, menggeser (*slide*) gambar, mengunduh satuan melalui tombol di dalam galeri, atau mengunduh seluruh file sekaligus dalam kemasan file ZIP.
            </div>
        </div>

        <!-- HASIL GRID -->
        <div class="hasil-grid">
            <?php
            $has_data = false;
            if ($q_hasil && sqlsrv_has_rows($q_hasil)):
                $has_data = true;
                while ($row = sqlsrv_fetch_array($q_hasil, SQLSRV_FETCH_ASSOC)):
                    $safe_id_order = htmlspecialchars($row['ID_Order'], ENT_QUOTES, 'UTF-8');
            ?>
                <div class="hasil-card">
                    <div class="hasil-header">
                        <div class="hasil-badge">
                            <i class="bi bi-check-circle-fill"></i> Lunas &amp; Siap Diambil
                        </div>
                        <div class="hasil-paket"><?= htmlspecialchars($row['Nama_Paket'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="hasil-ruangan"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($row['Nama_Ruangan'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="hasil-body">
                        <div class="hasil-info">
                            <span class="hasil-label">ID Order</span>
                            <span class="hasil-value">#<?= $safe_id_order ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Tanggal Sesi</span>
                            <span class="hasil-value"><?= formatTanggal($row['Tanggal_Jadwal']) ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Jam Sesi</span>
                            <span class="hasil-value"><?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Durasi</span>
                            <span class="hasil-value"><?= (int)$row['Durasi_Waktu'] ?> menit</span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Total Sesi</span>
                            <span class="hasil-value" style="color: var(--p-pink);">Rp<?= number_format($row['Total_Harga'], 0, ',', '.') ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Jumlah Berkas</span>
                            <span class="hasil-value" style="color: var(--p-pink);"><i class="bi bi-images me-1"></i><?= (int)$row['Total_Foto'] ?> foto (<?= formatUkuran($row['Total_Ukuran']) ?>)</span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Tanggal Upload</span>
                            <span class="hasil-value"><?= formatTanggal($row['Tanggal_Upload_Hasil']) ?> <?= formatWaktu($row['Tanggal_Upload_Hasil']) ?> WIB</span>
                        </div>
                    </div>
                    <div class="hasil-footer">
                        <!-- Tombol Buka Galeri (Sleek Dark Style) -->
                        <button type="button" class="btn-gallery-trigger" onclick="lihatGaleriFoto(<?= $safe_id_order ?>)">
                            <i class="bi bi-images"></i> Buka Galeri Foto
                        </button>
                        <!-- Tombol Download ZIP (Sleek Pink Gradient Style) -->
                        <a href="download_zip.php?id_order=<?= $safe_id_order ?>" class="btn-download">
                            <i class="bi bi-file-earmark-zip"></i> Download Semua (ZIP)
                        </a>
                    </div>
                </div>
            <?php 
                endwhile;
            endif;
            ?>
        </div>

        <?php if (!$has_data): ?>
        <!-- EMPTY STATE -->
        <div class="empty-hasil">
            <i class="bi bi-images"></i>
            <h3>Belum Ada Hasil Foto</h3>
            <p>Sesi foto Anda belum memiliki berkas unggahan hasil yang siap diakses. Sesi dokumentasi akan tampil di sini secara otomatis setelah pelunasan selesai divalidasi admin.</p>
            <a href="../Layanan/Paket/pilih_paket.php" class="btn-action">
                <i class="bi bi-calendar-plus"></i> Mulai Booking Baru
            </a>
        </div>
        <?php endif; ?>

    </main>

    <!-- ===== LIGHTBOX INTERAKTIF SLIDER MODAL ===== -->
    <div class="lightbox-modal" id="lightboxModal">
        <div class="lightbox-container">
            <div class="lightbox-close" onclick="closeLightbox()">
                <i class="bi bi-x-circle-fill"></i> Tutup
            </div>
            
            <!-- Tombol Navigasi Kiri -->
            <button class="lightbox-nav lightbox-prev" onclick="prevImage()" aria-label="Foto Sebelumnya">
                <i class="bi bi-chevron-left"></i>
            </button>
            
            <div class="lightbox-img-wrapper" id="lightboxTouchArea">
                <img id="lightboxImg" class="lightbox-img" src="" alt="Hasil Foto SpotLight Studio">
            </div>
            
            <!-- Tombol Navigasi Kanan -->
            <button class="lightbox-nav lightbox-next" onclick="nextImage()" aria-label="Foto Selanjutnya">
                <i class="bi bi-chevron-right"></i>
            </button>
            
            <div class="lightbox-info">
                <div class="lightbox-title" id="lightboxCaption">-</div>
                <div class="lightbox-counter" id="lightboxCounter">0 dari 0</div>
                <a href="#" id="lightboxDownloadBtn" download class="lightbox-download-single">
                    <i class="bi bi-cloud-arrow-down-fill"></i> Download Gambar Ini
                </a>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN PATH: Mengambil bootstrap bundle dari assets -->
    <script src="../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables untuk menyimpan array file gambar yang sedang aktif dibuka
        let activeGallery = [];
        let currentIndex = 0;

        // Toggle dropdown menu profile
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }
        
        // Menutup dropdown jika klik di luar area profil
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan kembali menuju halaman publik SpotLight Studio.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../index.php';
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin mengakhiri sesi aktif akun Anda?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../logout.php';
                }
            });
        }

        // =====================================================
        // AJAX: AMBIL DATA HASIL FOTO & TAMPILKAN DAFTAR PREVIEW
        // =====================================================
        function lihatGaleriFoto(idOrder) {
            Swal.fire({
                title: 'Mempersiapkan Galeri...',
                html: 'Mohon tunggu sebentar...',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false
            });

            fetch('ajax_hasil_foto.php?id_order=' + idOrder, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => {
                    if (!res.ok) throw new Error('Koneksi bermasalah');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) {
                        Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: data.message, confirmButtonColor: '#d83f67' });
                        return;
                    }

                    // Saring hanya file dengan tipe 'image'
                    activeGallery = data.files.filter(f => f.tipe === 'image');
                    
                    if (activeGallery.length === 0) {
                        Swal.fire({ 
                            icon: 'info', 
                            title: 'Galeri Kosong', 
                            text: 'Belum ada berkas foto berformat gambar yang dapat ditampilkan.', 
                            confirmButtonColor: '#d83f67' 
                        });
                        return;
                    }

                    // Tampilkan Grid Mini menggunakan SweetAlert2 untuk pemilihan awal gambar [1]
                    const gridHtml = activeGallery.map((f, idx) => {
                        return `<div style="cursor:pointer; overflow:hidden; border-radius:12px; position:relative; aspect-ratio:1/1;" onclick="startLightbox(${idx})">
                                    <img src="${f.url}" alt="${f.nama}" style="width:100%; height:100%; object-fit:cover; border:2px solid transparent; border-radius:12px; transition: var(--transition-smooth);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                </div>`;
                    }).join('');

                    Swal.fire({
                        title: 'Galeri Hasil Foto (' + activeGallery.length + ' file)',
                        html: `<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:5px 0;">${gridHtml}</div>
                              <p style="font-size:0.75rem; color:#718096; margin-top:15px; margin-bottom:0;">Klik pada salah satu foto untuk melihat layar penuh, lalu geser kiri atau kanan.</p>`,
                        width: 'min(620px, 94vw)',
                        confirmButtonColor: '#d83f67',
                        confirmButtonText: 'Tutup Galeri'
                    });
                })
                .catch(err => {
                    Swal.fire({ icon: 'error', title: 'Gagal Memuat', text: 'Koneksi gagal atau sesi terputus. Silakan muat ulang halaman.', confirmButtonColor: '#d83f67' });
                });
        }

        // =====================================================
        // LIGHTBOX CONTROLLER (GESER GAMBAR KANAN KIRI)
        // =====================================================
        function startLightbox(index) {
            // Tutup Sweetalert Grid Mini
            Swal.close();
            
            // Atur index gambar saat ini
            currentIndex = index;
            updateLightboxContent();

            // Tampilkan Modal Lightbox dengan animasi transisi
            const modal = document.getElementById('lightboxModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);

            // Pasang Event Keyboard & Gestur Swipe
            document.addEventListener('keydown', handleKeyPress);
            initSwipeGestures();
        }

        function updateLightboxContent() {
            if (activeGallery.length === 0) return;
            
            const fileData = activeGallery[currentIndex];
            const imgEl = document.getElementById('lightboxImg');
            const captionEl = document.getElementById('lightboxCaption');
            const counterEl = document.getElementById('lightboxCounter');
            const downloadBtn = document.getElementById('lightboxDownloadBtn');

            // Set sumber gambar dan konten metadata pendukung
            imgEl.style.transform = 'scale(0.95)';
            imgEl.style.opacity = '0.7';

            setTimeout(() => {
                imgEl.src = fileData.url;
                imgEl.alt = fileData.nama;
                captionEl.textContent = fileData.nama;
                counterEl.textContent = `${currentIndex + 1} dari ${activeGallery.length}`;
                downloadBtn.href = fileData.url;
                
                imgEl.style.transform = 'scale(1)';
                imgEl.style.opacity = '1';
            }, 150);
        }

        function prevImage() {
            if (activeGallery.length <= 1) return;
            currentIndex = (currentIndex === 0) ? activeGallery.length - 1 : currentIndex - 1;
            updateLightboxContent();
        }

        function nextImage() {
            if (activeGallery.length <= 1) return;
            currentIndex = (currentIndex === activeGallery.length - 1) ? 0 : currentIndex + 1;
            updateLightboxContent();
        }

        function closeLightbox() {
            const modal = document.getElementById('lightboxModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);

            // Lepas event listener untuk menghemat memori
            document.removeEventListener('keydown', handleKeyPress);
        }

        // Handler untuk Keyboard Navigasi
        function handleKeyPress(e) {
            if (e.key === 'ArrowRight') {
                nextImage();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
            } else if (e.key === 'Escape') {
                closeLightbox();
            }
        }

        // Deteksi Gestur Swipe (Layar Sentuh Handphone/Mobile)
        let touchStartX = 0;
        let touchEndX = 0;

        function initSwipeGestures() {
            const touchArea = document.getElementById('lightboxTouchArea');
            if(!touchArea) return;

            touchArea.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            touchArea.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipeGesture();
            }, { passive: true });
        }

        function handleSwipeGesture() {
            const threshold = 50; // Sensitivitas geseran minimal (pixel)
            if (touchEndX < touchStartX - threshold) {
                nextImage(); // Swipe ke kiri -> Foto berikutnya
            }
            if (touchEndX > touchStartX + threshold) {
                prevImage(); // Swipe ke kanan -> Foto sebelumnya
            }
        }
    </script>
</body>
</html>