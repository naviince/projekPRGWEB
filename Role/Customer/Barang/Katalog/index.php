<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

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
// FILTER & SEARCH
// =====================================================
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : "";

// =====================================================
// QUERY BARANG CETAK (Hanya aktif & stok > 0)
// =====================================================
$sql = "
    SELECT 
        ID_Barang,
        Nama_Barang,
        Deskripsi,
        Harga_Barang,
        Stok_Barang,
        Stok_Minimum,
        Foto_Barang,
        Kategori_Barang
    FROM Barang_Cetak
    WHERE Is_Deleted = 0 AND Status = ? AND Stok_Barang > 0
";
$params = array(STATUS_DATA_AKTIF);

if (!empty($cari)) {
    $sql .= " AND (Nama_Barang LIKE ? OR Deskripsi LIKE ?)";
    $params[] = "%" . $cari . "%";
    $params[] = "%" . $cari . "%";
}

if (!empty($kategori)) {
    $sql .= " AND Kategori_Barang = ?";
    $params[] = $kategori;
}

$sql .= " ORDER BY 
    CASE WHEN Stok_Barang <= Stok_Minimum THEN 0 ELSE 1 END ASC,
    Nama_Barang ASC";

$q_barang = sqlsrv_query($conn, $sql, $params);

// =====================================================
// QUERY KATEGORI (distinct)
// =====================================================
$q_kategori = sqlsrv_query($conn, 
    "SELECT DISTINCT Kategori_Barang FROM Barang_Cetak WHERE Is_Deleted = 0 AND Status = ? AND Stok_Barang > 0 ORDER BY Kategori_Barang",
    array(STATUS_DATA_AKTIF)
);
$kategori_list = [];
if ($q_kategori !== false) {
    while ($k = sqlsrv_fetch_array($q_kategori, SQLSRV_FETCH_ASSOC)) {
        $kategori_list[] = $k['Kategori_Barang'];
    }
}

// =====================================================
// HITUNG KERANJANG (barang di session)
// =====================================================
$jumlah_keranjang = 0;
if (isset($_SESSION['keranjang_barang']) && is_array($_SESSION['keranjang_barang'])) {
    foreach ($_SESSION['keranjang_barang'] as $item) {
        $jumlah_keranjang += (int)$item['jumlah'];
    }
}

// =====================================================
// PROMO: Barang dengan stok banyak = diskon 10%
// =====================================================
function cekPromo($stok, $stok_minimum) {
    // Promo: stok > 2x minimum = diskon 10%
    if ($stok > ($stok_minimum * 2)) {
        return 10; // 10% diskon
    }
    return 0;
}

function hitungHargaPromo($harga, $diskon_persen) {
    return $harga * (1 - ($diskon_persen / 100));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Barang Cetak - SpotLight Studio</title>
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
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS (SAMA PERSIS DENGAN CUSTOMER INDEX) ===== */
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
        .nav-avatar-wrapper {
            position: relative;
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
        .nav-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
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
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
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

        /* ===== KERANJANG BADGE ===== */
        .keranjang-btn {
            position: relative;
            background: var(--s-pink);
            color: var(--p-pink);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--light-pink);
        }
        .keranjang-btn:hover {
            background: var(--p-pink);
            color: #fff;
            transform: translateY(-2px);
        }
        .keranjang-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--danger);
            color: #fff;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== HERO SECTION ===== */
        .hero-katalog {
            background: linear-gradient(135deg, var(--p-pink) 0%, var(--d-pink) 50%, #b82e52 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        .hero-katalog::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            border-radius: 50%;
        }
        .hero-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 8px;
            position: relative;
        }
        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
            position: relative;
        }

        /* ===== SEARCH & FILTER BAR ===== */
        .filter-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
            transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.1);
        }
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .kategori-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .kategori-btn {
            padding: 10px 20px;
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #4a5568;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }
        .kategori-btn:hover, .kategori-btn.active {
            background: var(--p-pink);
            color: #ffffff;
            border-color: var(--p-pink);
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.2);
        }

        /* ===== BARANG GRID ===== */
        .barang-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .barang-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        .barang-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .barang-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .barang-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .barang-card:hover .barang-img {
            transform: scale(1.1);
        }
        .barang-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
        }
        .promo-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 2;
        }
        .stok-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 2;
        }
        .stok-badge.menipis {
            background: #fef2f2;
            color: var(--danger);
        }
        .stok-badge.tersedia {
            color: var(--success);
        }
        .barang-body {
            padding: 20px;
        }
        .barang-kategori {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .barang-nama {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .barang-deskripsi {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .barang-harga-wrapper {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .barang-harga {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .barang-harga-coret {
            font-size: 0.95rem;
            color: #94a3b8;
            text-decoration: line-through;
            font-weight: 600;
        }
        .barang-harga-promo {
            background: #fef2f2;
            color: var(--danger);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .barang-footer {
            display: flex;
            gap: 10px;
        }
        .btn-tambah {
            flex: 1;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-tambah:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
        }
        .btn-tambah:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-detail {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #4a5568;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.1rem;
        }
        .btn-detail:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        /* ===== EMPTY STATE ===== */
        .empty-katalog {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-katalog i {
            font-size: 5rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-katalog h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-katalog p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .nav-menu-center { display: none; }
            .hero-katalog { padding: 30px 20px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { min-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="index.php" class="nav-link-item active">Barang Cetak</a>
            <a href="../../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>        
        </div>
                <div class="nav-right">
            <a href="keranjang.php" class="keranjang-btn" title="Keranjang Belanja">
                <i class="bi bi-cart-fill" style="font-size:1.2rem;"></i>
                <?php if ($jumlah_keranjang > 0): ?>
                <span class="keranjang-badge"><?= $jumlah_keranjang ?></span>
                <?php endif; ?>
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                    <a href="../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                    <a href="../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">                        <i class="bi bi-house-door"></i> Kembali ke Beranda
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
        <div class="hero-katalog">
            <div class="hero-title"><i class="bi bi-bag-heart-fill me-2"></i>Katalog Barang Cetak</div>
            <div class="hero-subtitle">Pilih barang cetak tambahan untuk melengkapi koleksi foto Anda</div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" action="" class="search-box" style="margin:0;">
                <i class="bi bi-search"></i>
                <input type="text" name="cari" placeholder="Cari barang cetak..." value="<?= htmlspecialchars($cari) ?>" onkeyup="if(event.key==='Enter')this.form.submit()">
            </form>
            <div class="kategori-filter">
                <a href="index.php" class="kategori-btn <?= empty($kategori) ? 'active' : '' ?>">Semua</a>
                <?php foreach ($kategori_list as $kat): ?>
                <a href="?kategori=<?= urlencode($kat) ?>" class="kategori-btn <?= $kategori === $kat ? 'active' : '' ?>"><?= htmlspecialchars($kat) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- BARANG GRID -->
        <div class="barang-grid">
            <?php
            if ($q_barang && sqlsrv_has_rows($q_barang)):
                while ($row = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)):
                    $foto_barang = ($row['Foto_Barang'] != 'default_barang.jpg' && file_exists("../../../../assets/img/barang/" . $row['Foto_Barang'])) 
                        ? "../../../../assets/img/barang/" . $row['Foto_Barang'] 
                        : null;

                    $diskon = cekPromo((int)$row['Stok_Barang'], (int)$row['Stok_Minimum']);
                    $harga_asli = (float)$row['Harga_Barang'];
                    $harga_promo = hitungHargaPromo($harga_asli, $diskon);
                    $harga_display = $diskon > 0 ? $harga_promo : $harga_asli;
                    $harga_format = number_format($harga_display, 0, ',', '.');
                    $harga_asli_format = number_format($harga_asli, 0, ',', '.');
                    $stok = (int)$row['Stok_Barang'];
                    $stok_min = (int)$row['Stok_Minimum'];
                    $is_menipis = ($stok <= $stok_min);
            ?>
                <div class="barang-card">
                    <div class="barang-img-wrapper">
                        <?php if ($foto_barang): ?>
                            <img src="<?= $foto_barang ?>" class="barang-img" alt="<?= htmlspecialchars($row['Nama_Barang']) ?>">
                        <?php else: ?>
                            <div class="barang-img-placeholder">
                                <i class="bi bi-printer-fill"></i>
                            </div>
                        <?php endif; ?>

                        <?php if ($diskon > 0): ?>
                        <div class="promo-badge"><i class="bi bi-tag-fill me-1"></i>Diskon <?= $diskon ?>%</div>
                        <?php endif; ?>

                        <div class="stok-badge <?= $is_menipis ? 'menipis' : 'tersedia' ?>">
                            <i class="bi bi-box-seam-fill me-1"></i>Stok: <?= $stok ?>
                        </div>
                    </div>
                    <div class="barang-body">
                        <div class="barang-kategori"><?= htmlspecialchars($row['Kategori_Barang']) ?></div>
                        <div class="barang-nama"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                        <div class="barang-deskripsi"><?= htmlspecialchars($row['Deskripsi'] ?? 'Barang cetak berkualitas untuk melengkapi koleksi foto Anda.') ?></div>
                        <div class="barang-harga-wrapper">
                            <div class="barang-harga">Rp<?= $harga_format ?></div>
                            <?php if ($diskon > 0): ?>
                            <div class="barang-harga-coret">Rp<?= $harga_asli_format ?></div>
                            <div class="barang-harga-promo">-<?= $diskon ?>%</div>
                            <?php endif; ?>
                        </div>
                        <div class="barang-footer">
                            <button class="btn-tambah" onclick="tambahKeKeranjang(<?= (int)$row['ID_Barang'] ?>, '<?= htmlspecialchars(addslashes($row['Nama_Barang'])) ?>', <?= $harga_display ?>, <?= $stok ?>)" <?= $stok <= 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-cart-plus-fill"></i> <?= $stok > 0 ? 'Tambah' : 'Habis' ?>
                            </button>
                            <button class="btn-detail" onclick="lihatDetail(<?= (int)$row['ID_Barang'] ?>, '<?= htmlspecialchars(addslashes($row['Nama_Barang'])) ?>', '<?= htmlspecialchars(addslashes($row['Deskripsi'] ?? '')) ?>', <?= $harga_display ?>, <?= $stok ?>, <?= $diskon ?>)">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile; 
            else:
            ?>
                <div class="empty-katalog" style="grid-column: 1 / -1;">
                    <i class="bi bi-inbox"></i>
                    <h3>Barang Tidak Ditemukan</h3>
                    <p>Maaf, tidak ada barang cetak yang sesuai dengan pencarian Anda.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan meninggalkan halaman customer dan kembali ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
<<<<<<< Updated upstream
<<<<<<< HEAD
                    window.location.href = '../../../index.php';
=======
                    window.location.href = '../../index.php';
>>>>>>> 0abd9d4d5c2874abb677ffcabe7bc8ac4c06b8c9
=======
                    window.location.href = '../../index.php';
>>>>>>> Stashed changes
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        // Tambah ke keranjang
        function tambahKeKeranjang(idBarang, namaBarang, harga, stok) {
            if (stok <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Stok Habis',
                    text: 'Maaf, stok barang ini sudah habis.',
                    confirmButtonColor: '#d83f67'
                });
                return;
            }

            Swal.fire({
                title: 'Tambah ke Keranjang?',
                html: '<div style="text-align:left">' +
                      '<p><strong>' + namaBarang + '</strong></p>' +
                      '<p>Harga: <strong style="color:#d83f67">Rp ' + harga.toLocaleString('id-ID') + '</strong></p>' +
                      '<p>Stok tersisa: <strong>' + stok + '</strong></p>' +
                      '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Tambah',
                cancelButtonText: 'Batal',
                input: 'number',
                inputLabel: 'Jumlah:',
                inputValue: 1,
                inputAttributes: {
                    min: 1,
                    max: stok,
                    step: 1
                },
                preConfirm: (jumlah) => {
                    if (!jumlah || jumlah < 1) {
                        Swal.showValidationMessage('Jumlah minimal 1');
                        return false;
                    }
                    if (jumlah > stok) {
                        Swal.showValidationMessage('Jumlah melebihi stok (' + stok + ')');
                        return false;
                    }
                    return jumlah;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const jumlah = result.value;
                    // Kirim ke server via AJAX
                    fetch('tambah_ke_keranjang.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id_barang=' + idBarang + '&jumlah=' + jumlah + '&harga=' + harga
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: namaBarang + ' ditambahkan ke keranjang (' + jumlah + ' item)',
                                confirmButtonColor: '#d83f67',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: data.message || 'Terjadi kesalahan',
                                confirmButtonColor: '#d83f67'
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan sistem',
                            confirmButtonColor: '#d83f67'
                        });
                    });
                }
            });
        }

        // Lihat detail barang
        function lihatDetail(idBarang, nama, deskripsi, harga, stok, diskon) {
            let hargaText = 'Rp ' + harga.toLocaleString('id-ID');
            if (diskon > 0) {
                const hargaAsli = Math.round(harga / (1 - diskon/100));
                hargaText = '<span style="text-decoration:line-through;color:#94a3b8">Rp ' + hargaAsli.toLocaleString('id-ID') + '</span> ' +
                           '<span style="color:#d83f67;font-weight:900">Rp ' + harga.toLocaleString('id-ID') + '</span>' +
                           '<span style="background:#fef2f2;color:#dc2626;padding:4px 10px;border-radius:8px;font-size:0.75rem;margin-left:8px">-' + diskon + '%</span>';
            }

            Swal.fire({
                title: nama,
                html: '<div style="text-align:left">' +
                      '<p style="color:#718096;margin-bottom:12px;">' + (deskripsi || 'Barang cetak berkualitas') + '</p>' +
                      '<hr style="border-color:#f1f5f9;margin:12px 0">' +
                      '<p><strong>Harga:</strong> ' + hargaText + '</p>' +
                      '<p><strong>Stok:</strong> ' + stok + ' item</p>' +
                      '</div>',
                icon: 'info',
                confirmButtonColor: '#d83f67',
                confirmButtonText: 'Tutup'
            });
        }
    </script>
</body>
</html>