<?php
session_start();
include '../../koneksi.php';

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

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
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
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// --- Paket Foto ---
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Harga_Paket, Durasi_Waktu, Kapasitas_Orang, Deskripsi, Foto_Paket 
     FROM Paket_Foto WHERE Is_Deleted = 0 AND Status = ? ORDER BY Harga_Paket ASC",
    array(STATUS_DATA_AKTIF)
);

// --- Jadwal Hari Ini ---
$today = date('Y-m-d');
$q_jadwal = sqlsrv_query($conn, 
    "SELECT TOP 4 j.ID_Jadwal, r.Nama_Ruangan, j.Jam_Mulai, j.Jam_Selesai, j.Keterangan
     FROM Jadwal_Studio j
     INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
     WHERE j.Tanggal_Jadwal = ? AND j.Status_Jadwal = ? AND j.Status = ? AND j.Is_Deleted = 0
     ORDER BY j.Jam_Mulai ASC",
    array($today, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
);

// --- Barang Cetak Populer ---
$q_barang = sqlsrv_query($conn, 
    "SELECT TOP 4 ID_Barang, Nama_Barang, Harga_Barang, Foto_Barang 
     FROM Barang_Cetak WHERE Is_Deleted = 0 AND Status = ? AND Stok_Barang > 0
     ORDER BY Stok_Barang DESC",
    array(STATUS_DATA_AKTIF)
);

// --- Stats ---
$q_stats = sqlsrv_query($conn, 
    "SELECT 
        (SELECT COUNT(*) FROM [Order] WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order != ?) as total_booking,
        (SELECT COUNT(*) FROM [Order] WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order = ?) as menunggu_dp",
    array($id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_DIBATALKAN, $id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_MENUNGGU_DP)
);
$d_stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpotLight Studio - Booking Studio Foto Online</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
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

        /* ===== HERO BANNER ===== */
        .hero-banner {
            background: linear-gradient(135deg, var(--p-pink) 0%, var(--d-pink) 50%, #b82e52 100%);
            padding: 80px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .hero-content {
            position: relative;
            z-index: 2;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 16px;
            text-shadow: 0 4px 20px rgba(0,0,0,0.15);
            letter-spacing: -1px;
        }
        .hero-subtitle {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 32px;
            font-weight: 500;
        }
        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            color: var(--p-pink);
            padding: 16px 40px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hero-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            color: var(--d-pink);
        }

        /* ===== SEARCH BAR ===== */
        .search-section {
            background: #ffffff;
            padding: 30px 40px;
            border-bottom: 1px solid #eef2f6;
        }
        .search-bar {
            display: flex;
            gap: 16px;
            align-items: center;
            max-width: 900px;
            margin: 0 auto;
        }
        .search-input-group {
            flex: 1;
            position: relative;
        }
        .search-input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .search-input {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border: 2px solid #eef2f6;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            transition: all 0.3s;
            background: #f8fafc;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.1);
        }
        .search-select {
            padding: 14px 18px;
            border: 2px solid #eef2f6;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 160px;
        }
        .search-select:focus {
            outline: none;
            border-color: var(--p-pink);
            background: #ffffff;
        }
        .search-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 14px 32px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .section-title span {
            color: var(--p-pink);
        }
        .section-count {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .section-count strong {
            color: var(--text-dark);
        }

        /* ===== PAKET GRID ===== */
        .paket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 50px;
        }
        .paket-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .paket-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .paket-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        .paket-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .paket-card:hover .paket-img {
            transform: scale(1.1);
        }
        .paket-badge {
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
        .paket-body {
            padding: 20px;
        }
        .paket-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .paket-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .paket-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }
        .paket-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .paket-meta-item i { color: var(--p-pink); }
        .paket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .paket-harga {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .paket-btn {
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
        .paket-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }

        /* ===== INFO SECTION (Jadwal + Barang) ===== */
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 40px;
        }
        .info-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #f1f5f9;
        }
        .info-card-title {
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-card-title i { color: var(--p-pink); }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
        }
        .info-text {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .info-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .info-btn {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 6px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .info-btn:hover {
            background: var(--p-pink);
            color: #fff;
        }

        /* ===== QUICK STATS BAR ===== */
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .stats-bar::-webkit-scrollbar { height: 4px; }
        .stats-bar::-webkit-scrollbar-thumb { background: var(--p-pink); border-radius: 4px; }
        .stat-chip {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            padding: 12px 20px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: max-content;
            transition: all 0.3s;
        }
        .stat-chip:hover {
            border-color: var(--light-pink);
            transform: translateY(-2px);
        }
        .stat-chip-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
        }
        .stat-chip-text {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-dark);
        }
        .stat-chip-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .hero-title { font-size: 2rem; }
            .search-bar { flex-wrap: wrap; }
            .info-section { grid-template-columns: 1fr; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="index.php" class="nav-link-item active">Dashboard</a>
            <a href="Layanan/Paket/detail_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="Booking/Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="Layanan/Paket/detail_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="location.href='#'">
        </div>
    </nav>

    <!-- HERO BANNER -->
    <section class="hero-banner">
        <div class="hero-content">
            <h1 class="hero-title">BOOKING STUDIO FOTO TERBAIK</h1>
            <p class="hero-subtitle">Pesan sesi foto profesional dengan mudah, cepat, dan terjangkau</p>
            <a href="Layanan/Paket/detail_paket.php" class="hero-btn">
                <i class="bi bi-calendar-plus-fill"></i>
                Booking Sekarang
            </a>
        </div>
    </section>

    <!-- SEARCH BAR -->
    <section class="search-section">
        <div class="search-bar">
            <div class="search-input-group">
                <i class="bi bi-search"></i>
                <input type="text" class="search-input" placeholder="Cari nama paket foto...">
            </div>
            <select class="search-select">
                <option value="">Semua Kategori</option>
                <option value="personal">Personal</option>
                <option value="couple">Couple</option>
                <option value="family">Family</option>
                <option value="wisuda">Wisuda</option>
                <option value="corporate">Corporate</option>
            </select>
            <select class="search-select">
                <option value="">Semua Durasi</option>
                <option value="30">30 Menit</option>
                <option value="60">60 Menit</option>
                <option value="90">90 Menit</option>
                <option value="120">120 Menit</option>
            </select>
            <button class="search-btn">
                <i class="bi bi-search me-2"></i>Cari Paket
            </button>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- QUICK STATS -->
        <div class="stats-bar">
            <div class="stat-chip">
                <div class="stat-chip-icon"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="stat-chip-text"><?= $d_stats['total_booking'] ?? 0 ?> Booking</div>
                    <div class="stat-chip-sub">Total pemesanan</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #fffbeb; color: #d97706;"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-chip-text"><?= $d_stats['menunggu_dp'] ?? 0 ?> Menunggu</div>
                    <div class="stat-chip-sub">Segera bayar DP</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #ecfdf5; color: #059669;"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-chip-text">Lunas</div>
                    <div class="stat-chip-sub">Booking selesai</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-camera-fill"></i></div>
                <div>
                    <div class="stat-chip-text">Studio Aktif</div>
                    <div class="stat-chip-sub">5 ruangan tersedia</div>
                </div>
            </div>
        </div>

        <!-- PAKET FOTO -->
        <div class="section-header">
            <div class="section-title">
                <i class="bi bi-fire text-danger me-2"></i>
                Paket Foto <span>Populer</span>
            </div>
            <div class="section-count">
                Menampilkan <strong><?= sqlsrv_num_rows($q_paket) ?></strong> paket tersedia
            </div>
        </div>

        <div class="paket-grid">
            <?php
            if ($q_paket && sqlsrv_has_rows($q_paket)):
                while ($row = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)):
                    $foto_paket = ($row['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $row['Foto_Paket'])) 
                        ? "../../assets/img/paket/" . $row['Foto_Paket'] 
                        : "../../assets/img/paket/default_paket.jpg";
                    $harga = number_format($row['Harga_Paket'], 0, ',', '.');
            ?>
                <!-- ===== FIX: LINK KE DETAIL PAKET (BUKAN pilih_paket.php) ===== -->
                <a href="Layanan/Paket/detail_paket.php?id_paket=<?= $row['ID_Paket'] ?>" class="paket-card">
                    <div class="paket-img-wrapper">
                        <img src="<?= $foto_paket ?>" class="paket-img" alt="<?= htmlspecialchars($row['Nama_Paket']) ?>">
                        <div class="paket-badge"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                    </div>
                    <div class="paket-body">
                        <div class="paket-nama"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                        <div class="paket-desc"><?= htmlspecialchars($row['Deskripsi'] ?? 'Paket foto ' . $row['Nama_Paket'] . ' untuk sesi foto terbaik Anda.') ?></div>
                        <div class="paket-meta">
                            <div class="paket-meta-item">
                                <i class="bi bi-clock"></i> <?= $row['Durasi_Waktu'] ?> menit
                            </div>
                            <div class="paket-meta-item">
                                <i class="bi bi-people"></i> Max <?= $row['Kapasitas_Orang'] ?> orang
                            </div>
                        </div>
                        <div class="paket-footer">
                            <div class="paket-harga">Rp<?= $harga ?></div>
                            <span class="paket-btn">Pilih <i class="bi bi-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </a>
            <?php 
                endwhile; 
            else:
            ?>
                <div class="text-center py-5" style="grid-column: 1 / -1;">
                    <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1;"></i>
                    <p class="text-muted">Belum ada paket foto tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- INFO SECTION: Jadwal + Barang -->
        <div class="info-section">
            <!-- Jadwal Hari Ini -->
            <div class="info-card">
                <div class="info-card-title">
                    <i class="bi bi-calendar-day-fill"></i>
                    Jadwal Tersedia Hari Ini
                </div>
                <?php
                if ($q_jadwal && sqlsrv_has_rows($q_jadwal)):
                    while ($row = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)):
                ?>
                    <div class="info-item">
                        <div class="info-item-left">
                            <div class="info-icon"><i class="bi bi-clock-fill"></i></div>
                            <div>
                                <div class="info-text"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                <div class="info-sub"><?= $row['Jam_Mulai']->format('H:i') ?> - <?= $row['Jam_Selesai']->format('H:i') ?></div>
                            </div>
                        </div>
                        <a href="Layanan/Paket/detail_paket.php" class="info-btn">Booking</a>
                    </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x fs-2 mb-2" style="color: #cbd5e1;"></i>
                        <p class="text-muted small">Tidak ada jadwal tersedia hari ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Barang Cetak -->
            <div class="info-card">
                <div class="info-card-title">
                    <i class="bi bi-bag-heart-fill"></i>
                    Barang Cetak Populer
                </div>
                <?php
                if ($q_barang && sqlsrv_has_rows($q_barang)):
                    while ($row = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)):
                        $harga_barang = number_format($row['Harga_Barang'], 0, ',', '.');
                ?>
                    <div class="info-item">
                        <div class="info-item-left">
                            <div class="info-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-printer-fill"></i></div>
                            <div>
                                <div class="info-text"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                                <div class="info-sub">Rp<?= $harga_barang ?></div>
                            </div>
                        </div>
                        <a href="Cetak/Katalog/index.php" class="info-btn">Lihat</a>
                    </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-2 mb-2" style="color: #cbd5e1;"></i>
                        <p class="text-muted small">Belum ada barang cetak tersedia.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Keluar?',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../logout.php';
            });
        }
    </script>
</body>
</html>