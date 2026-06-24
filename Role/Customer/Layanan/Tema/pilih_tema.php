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

// =====================================================
// AMBIL ID PAKET & ID RUANGAN DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];

// =====================================================
// AMBIL DATA PAKET
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    array($id_paket, STATUS_DATA_AKTIF)
);
if ($q_paket === false) {
    die("Error query Paket: " . print_r(sqlsrv_errors(), true));
}
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA RUANGAN
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi, Foto_Ruangan 
     FROM Ruangan 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
if ($q_ruangan === false) {
    die("Error query Ruangan: " . print_r(sqlsrv_errors(), true));
}
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);

if (!$d_ruangan) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_ditemukan");
    exit();
}

// =====================================================
// VALIDASI: RUANGAN HARUS TERHUBUNG DENGAN PAKET
// =====================================================
$q_validasi = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", 
    array($id_paket, $id_ruangan)
);
if ($q_validasi === false) {
    die("Error query Validasi: " . print_r(sqlsrv_errors(), true));
}
$d_validasi = sqlsrv_fetch_array($q_validasi, SQLSRV_FETCH_ASSOC);

if ($d_validasi['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

// =====================================================
// AMBIL TEMA YANG TERHUBUNG DENGAN RUANGAN (VIA Ruangan_Tema)
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT 
        t.ID_Tema,
        t.Nama_Tema,
        t.Kategori_Tema,
        t.Deskripsi,
        t.Foto_Tema
     FROM Tema_Foto t
     INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
     WHERE rt.ID_Ruangan = ?
       AND t.Status = 1
       AND t.Is_Deleted = 0
     ORDER BY t.Nama_Tema ASC", 
    array($id_ruangan)
);
if ($q_tema === false) {
    die("Error query Tema: " . print_r(sqlsrv_errors(), true));
}

$tema_list = [];
while ($row = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
    $tema_list[] = $row;
}

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
if ($q_profile === false) {
    die("Error query Profil: " . print_r(sqlsrv_errors(), true));
}
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// Path foto
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : "../../../../assets/img/paket/default_paket.jpg";

$foto_ruangan = ($d_ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'])) 
    ? "../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'] 
    : "../../../../assets/img/ruangan/default_ruangan.jpg";

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Tema Foto - SpotLight Studio</title>
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

        /* ===== RINGKASAN BOOKING (STICKY SIDEBAR) ===== */
        .booking-summary {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .summary-item:last-child { border-bottom: none; }
        .summary-icon {
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
        .summary-icon.completed { background: #d1fae5; color: #059669; }
        .summary-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .summary-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .summary-harga {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #f1f5f9;
        }

        /* ===== TEMA SECTION ===== */
        .tema-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .tema-main {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
        }
        .tema-section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tema-section-title i { color: var(--p-pink); }
        .tema-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }
        .tema-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }
        .tema-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .tema-img-wrapper {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        .tema-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .tema-card:hover .tema-img {
            transform: scale(1.1);
        }
        .tema-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .tema-badge {
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
        .tema-body {
            padding: 20px;
        }
        .tema-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .tema-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .tema-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .tema-kategori {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tema-kategori i { color: var(--p-pink); }
        .tema-btn {
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
        .tema-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .tema-section { grid-template-columns: 1fr; }
            .booking-summary { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
            .tema-grid { grid-template-columns: 1fr; }
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
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pilih Tema</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR: 1.Paket -> 2.Ruangan -> 3.Tema -> 4.Jadwal -> 5.Konfirmasi -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
        </div>

        <!-- TEMA SECTION + SIDEBAR -->
        <div class="tema-section">
            <!-- Left: Grid Tema -->
            <div class="tema-main">
                <div class="tema-section-title">
                    <i class="bi bi-palette-fill"></i>
                    Pilih Tema untuk <?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>
                </div>
                <div class="tema-grid">
                    <?php foreach ($tema_list as $tema): 
                        $foto_tema = ($tema['Foto_Tema'] != 'default_tema.jpg' && file_exists("../../../../assets/img/tema/" . $tema['Foto_Tema'])) 
                            ? "../../../../assets/img/tema/" . $tema['Foto_Tema'] 
                            : null;
                    ?>
                        <div class="tema-card" onclick="konfirmasiTema(<?= $tema['ID_Tema'] ?>, '<?= htmlspecialchars(addslashes($tema['Nama_Tema'])) ?>')">
                            <div class="tema-img-wrapper">
                                <?php if ($foto_tema): ?>
                                    <img src="<?= $foto_tema ?>" class="tema-img" alt="<?= htmlspecialchars($tema['Nama_Tema']) ?>">
                                <?php else: ?>
                                    <div class="tema-img-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="tema-badge"><?= htmlspecialchars($tema['Kategori_Tema'] ?? 'Tema') ?></div>
                            </div>
                            <div class="tema-body">
                                <div class="tema-nama"><?= htmlspecialchars($tema['Nama_Tema']) ?></div>
                                <div class="tema-desc"><?= htmlspecialchars($tema['Deskripsi'] ?? 'Tema foto ' . $tema['Nama_Tema'] . ' untuk sesi foto terbaik Anda.') ?></div>
                                <div class="tema-footer">
                                    <div class="tema-kategori">
                                        <i class="bi bi-tag-fill"></i>
                                        <?= htmlspecialchars($tema['Kategori_Tema'] ?? 'Umum') ?>
                                    </div>
                                    <span class="tema-btn">Pilih <i class="bi bi-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($tema_list)): ?>
                        <div class="text-center py-5" style="grid-column: 1 / -1;">
                            <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1;"></i>
                            <p class="text-muted">Tidak ada tema tersedia untuk ruangan ini.</p>
                            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="tema-btn text-decoration-none d-inline-block mt-3" style="padding: 12px 30px;">
                                <i class="bi bi-arrow-left me-2"></i>Kembali ke Ruangan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Sidebar Ringkasan -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title">Ringkasan Booking</div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Paket</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_paket['Nama_Paket']) ?> (<?= $d_paket['Durasi_Waktu'] ?> menit)</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Ruangan</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-palette"></i></div>
                        <div>
                            <div class="summary-text">Tema</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-calendar"></i></div>
                        <div>
                            <div class="summary-text">Jadwal</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>
                    <div class="summary-harga">Rp <?= $harga_format ?> <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">/ sesi</span></div>
                </div>
            </div>
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
                    window.location.href = '../../../../index.php';
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

        function konfirmasiTema(idTema, namaTema) {
            Swal.fire({
                title: 'Pilih Tema Ini?',
                text: 'Anda akan memilih tema "' + namaTema + '"',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pilih',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=' + idTema;
                }
            });
        }
    </script>
</body>
</html>