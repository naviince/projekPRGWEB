<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
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
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// AMBIL ID PAKET DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];

// =====================================================
// AMBIL DATA PAKET
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    array($id_paket, STATUS_DATA_AKTIF)
);
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

// Path foto paket
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : "../../../../assets/img/paket/default_paket.jpg";

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');

// =====================================================
// AMBIL RUANGAN YANG TERHUBUNG DENGAN PAKET INI
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT 
        r.ID_Ruangan, r.Nama_Ruangan, r.Kapasitas_Ruangan, r.Deskripsi, r.Foto_Ruangan,
        (SELECT COUNT(*) FROM Properti p WHERE p.ID_Ruangan = r.ID_Ruangan AND p.Status = 1 AND p.Is_Deleted = 0) as total_properti
     FROM Ruangan r
     INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
     WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0
     ORDER BY r.Nama_Ruangan ASC", 
    array($id_paket)
);

$ruangan_list = [];
while ($row = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)) {
    $ruangan_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($d_paket['Nama_Paket']) ?> - SpotLight Studio</title>
    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS (SAMA PERSIS DENGAN INDEX.PHP) ===== */
        .top-navbar {
            background: #ffffff;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
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
            transition: all 0.3s;
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
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.25);
        }
        .nav-btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.35);
            color: #fff;
        }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: all 0.3s;
        }
        .nav-avatar:hover {
            transform: scale(1.1);
            border-color: var(--p-pink);
        }

        /* ===== BREADCRUMB BAR ===== */
        .breadcrumb-bar {
            background: #ffffff;
            padding: 16px 40px;
            border-bottom: 1px solid #f1f5f9;
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
        }
        .breadcrumb-inner a:hover { color: var(--p-pink); }
        .breadcrumb-inner .separator {
            color: #cbd5e1;
        }
        .breadcrumb-inner .current {
            color: var(--p-pink);
            font-weight: 700;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== PROGRESS BAR ===== */
        .progress-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 30px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .progress-step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            border: 3px solid #e2e8f0;
            background: #ffffff;
            color: #94a3b8;
            transition: all 0.3s;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .progress-step.completed .progress-step-circle {
            background: #059669;
            border-color: #059669;
            color: #ffffff;
        }
        .progress-step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            margin-bottom: 24px;
        }
        .progress-line.completed { background: #059669; }

        /* ===== DETAIL SECTION (2 KOLOM) ===== */
        .detail-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }

        /* Left: Foto + Info */
        .detail-left {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .detail-foto {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .detail-body {
            padding: 30px;
        }
        .detail-badge {
            display: inline-block;
            padding: 6px 16px;
            background: var(--s-pink);
            color: var(--p-pink);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .detail-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 12px;
        }
        .detail-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .detail-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .detail-meta-item i { color: var(--p-pink); font-size: 1.1rem; }
        .detail-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 30px 0 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-section-title i { color: var(--p-pink); }
        .detail-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.8;
        }
        .detail-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .detail-info-list li {
            padding: 14px 0;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 600;
        }
        .detail-info-list li:last-child { border-bottom: none; }
        .detail-info-list li i {
            color: var(--p-pink);
            font-size: 1.2rem;
            width: 24px;
        }
        .detail-info-list li span {
            color: var(--text-muted);
            font-weight: 500;
            margin-left: auto;
        }

        /* Right: Sidebar Harga (STICKY) */
        .detail-sidebar {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .price-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .price-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .price-value {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-bottom: 4px;
        }
        .price-unit {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 24px;
        }
        .btn-cek {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-cek:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(216, 63, 103, 0.3);
            color: #ffffff;
        }
        .benefit-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
        }
        .benefit-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }
        .benefit-item:last-child { margin-bottom: 0; }
        .benefit-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .benefit-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.5;
        }

        /* ===== RUANGAN SECTION (SAMA STYLE CARD DENGAN INDEX.PHP) ===== */
        .ruangan-section {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            margin-bottom: 40px;
        }
        .ruangan-section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ruangan-section-title i { color: var(--p-pink); }
        .ruangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }
        .ruangan-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .ruangan-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .ruangan-img-wrapper {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        .ruangan-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .ruangan-card:hover .ruangan-img {
            transform: scale(1.1);
        }
        .ruangan-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ruangan-body {
            padding: 20px;
        }
        .ruangan-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .ruangan-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .ruangan-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }
        .ruangan-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .ruangan-meta-item i { color: var(--p-pink); }
        .ruangan-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .ruangan-harga {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .ruangan-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .ruangan-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .detail-section { grid-template-columns: 1fr; }
            .detail-sidebar { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
            .ruangan-grid { grid-template-columns: 1fr; }
            .progress-line { width: 30px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS (SAMA PERSIS DENGAN INDEX.PHP) -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="detail_paket.php" class="nav-link-item active">Booking Baru</a>
            <a href="../../Booking/Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="detail_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="location.href='#'">
        </div>
    </nav>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="detail_paket.php">Paket Foto</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">2</div>
                <div class="progress-step-label">Detail Paket</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
        </div>

        <!-- DETAIL SECTION (FOTO + INFO + SIDEBAR HARGA) -->
        <div class="detail-section">
            <!-- Left: Info -->
            <div class="detail-left">
                <img src="<?= $foto_paket ?>" class="detail-foto" alt="<?= htmlspecialchars($d_paket['Nama_Paket']) ?>">
                <div class="detail-body">
                    <div class="detail-badge">Paket Foto</div>
                    <h1 class="detail-title"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></h1>
                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <i class="bi bi-star-fill"></i>
                            <span>4.8</span> (120 review)
                        </div>
                        <div class="detail-meta-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>SpotLight Studio, Cikarang</span>
                        </div>
                        <div class="detail-meta-item">
                            <i class="bi bi-camera-fill"></i>
                            <span>Studio Foto Profesional</span>
                        </div>
                    </div>

                    <h2 class="detail-section-title"><i class="bi bi-file-text-fill"></i> Deskripsi</h2>
                    <p class="detail-desc">
                        <?= htmlspecialchars($d_paket['Deskripsi'] ?? 'Paket foto ' . $d_paket['Nama_Paket'] . ' dengan kualitas terbaik untuk kenangan Anda. Dilengkapi dengan fotografer profesional, peralatan studio lengkap, dan hasil foto berkualitas tinggi.') ?>
                    </p>

                    <h2 class="detail-section-title"><i class="bi bi-info-circle-fill"></i> Informasi Paket</h2>
                    <ul class="detail-info-list">
                        <li>
                            <i class="bi bi-stopwatch"></i>
                            Durasi Sesi
                            <span><?= $d_paket['Durasi_Waktu'] ?> menit</span>
                        </li>
                        <li>
                            <i class="bi bi-people-fill"></i>
                            Kapasitas
                            <span><?= $d_paket['Kapasitas_Orang'] ?> orang</span>
                        </li>
                        <li>
                            <i class="bi bi-door-open-fill"></i>
                            Ruangan Tersedia
                            <span><?= count($ruangan_list) ?> studio</span>
                        </li>
                        <li>
                            <i class="bi bi-image-fill"></i>
                            File Hasil
                            <span>Softcopy + Edit</span>
                        </li>
                        <li>
                            <i class="bi bi-cash-stack"></i>
                            Pembayaran
                            <span>DP 65% + Pelunasan</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right: Sidebar (STICKY) -->
            <div class="detail-sidebar">
                <div class="price-card">
                    <div class="price-label">Mulai dari</div>
                    <div class="price-value">Rp <?= $harga_format ?></div>
                    <div class="price-unit">Per Sesi</div>
                    <a href="#ruangan-section" class="btn-cek" onclick="event.preventDefault(); document.querySelector('#ruangan-section').scrollIntoView({ behavior: 'smooth' });">
                        <i class="bi bi-calendar-check-fill"></i>
                        Lihat Ruangan Tersedia
                    </a>
                </div>
                <div class="benefit-card">
                    <div class="benefit-title">Booking di SpotLight lebih untung!</div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-credit-card"></i></div>
                        <div class="benefit-text">Pembayaran DP hanya 65%</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="benefit-text">Reschedule jadwal H-3</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-gift"></i></div>
                        <div class="benefit-text">Promo & voucher menarik</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="benefit-text">Jadwal terjamin & tidak bentrok</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RUANGAN SECTION (SAMA STYLE CARD DENGAN INDEX.PHP) -->
        <div class="ruangan-section" id="ruangan-section">
            <div class="ruangan-section-title">
                <i class="bi bi-door-open-fill"></i>
                Pilih Ruangan untuk Paket <?= htmlspecialchars($d_paket['Nama_Paket']) ?>
            </div>
            <div class="ruangan-grid">
                <?php foreach ($ruangan_list as $ruangan): 
                    $foto_ruangan = ($ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'])) 
                        ? "../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'] 
                        : "../../../../assets/img/ruangan/default_ruangan.jpg";
                ?>
                    <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $ruangan['ID_Ruangan'] ?>" class="ruangan-card">
                        <div class="ruangan-img-wrapper">
                            <img src="<?= $foto_ruangan ?>" class="ruangan-img" alt="<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>">
                            <div class="ruangan-badge"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                        </div>
                        <div class="ruangan-body">
                            <div class="ruangan-nama"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                            <div class="ruangan-desc"><?= htmlspecialchars($ruangan['Deskripsi'] ?? 'Studio dengan fasilitas lengkap untuk sesi foto terbaik.') ?></div>
                            <div class="ruangan-meta">
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-people"></i> <?= $ruangan['Kapasitas_Ruangan'] ?> orang
                                </div>
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-box-seam"></i> <?= $ruangan['total_properti'] ?> properti
                                </div>
                            </div>
                            <div class="ruangan-footer">
                                <div class="ruangan-harga">Rp <?= $harga_format ?></div>
                                <span class="ruangan-btn">Pilih <i class="bi bi-arrow-right ms-1"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($ruangan_list)): ?>
                    <div class="text-center py-5" style="grid-column: 1 / -1;">
                        <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1;"></i>
                        <p class="text-muted">Tidak ada ruangan tersedia untuk paket ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>