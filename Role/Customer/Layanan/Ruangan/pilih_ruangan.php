<?php
session_start();
// Path dari Role/Customer/Layanan/Ruangan/ ke root projekPRGWEB/ = ../../../../koneksi.php
include '../../../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL ID PAKET DARI STEP 1 (wajib ada)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket'])) {
    header("Location: ../Paket/pilih_paket.php");
    exit();
}

$id_paket = (int)$_GET['id_paket'];

// =====================================================
// AMBIL DATA PAKET (untuk tampilan header)
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT Nama_Paket, Harga_Paket, Durasi_Waktu, Kapasitas_Orang, Deskripsi, Foto_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_paket]
);
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_paket);

if (!$d_paket) {
    header("Location: ../Paket/pilih_paket.php?error=paket_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA RUANGAN YANG TERVALIDASI VIA PAKET_RUANGAN
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT 
        r.ID_Ruangan,
        r.Nama_Ruangan,
        r.Kapasitas_Ruangan,
        r.Deskripsi,
        r.Foto_Ruangan
     FROM Ruangan r
     INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
     WHERE pr.ID_Paket = ?
       AND r.Status = 1
       AND r.Is_Deleted = 0
     ORDER BY r.Nama_Ruangan ASC", 
    [$id_paket]
);

$ruangan_list = [];
$ruangan_ids = [];
while ($row = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)) {
    $ruangan_list[] = $row;
    $ruangan_ids[] = $row['ID_Ruangan'];
}
sqlsrv_free_stmt($q_ruangan);

// =====================================================
// AMBIL PROPERTI UNTUK SETIAP RUANGAN (untuk modal)
// =====================================================
$properti_per_ruangan = [];

if (!empty($ruangan_ids)) {
    $placeholders = implode(',', array_fill(0, count($ruangan_ids), '?'));
    $q_properti = sqlsrv_query($conn, 
        "SELECT ID_Ruangan, Nama_Properti, Kategori_Properti, Foto_Properti 
         FROM Properti 
         WHERE ID_Ruangan IN ($placeholders) AND Status = 1 AND Is_Deleted = 0
         ORDER BY ID_Ruangan, Kategori_Properti, Nama_Properti", 
        $ruangan_ids
    );
    while ($p = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
        $properti_per_ruangan[$p['ID_Ruangan']][] = $p;
    }
    sqlsrv_free_stmt($q_properti);
}

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$q_customer = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_customer]
);
$d_customer = sqlsrv_fetch_array($q_customer, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_customer);

$nama_customer = $d_customer['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_customer['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// Path foto paket
$path_foto_paket = "../../../../assets/img/paket/" . ($d_paket['Foto_Paket'] ?? 'default_paket.jpg');
$foto_paket_src = (file_exists($path_foto_paket) && $d_paket['Foto_Paket'] != 'default_paket.jpg') 
    ? $path_foto_paket 
    : "../../../../assets/img/paket/default_paket.jpg";

$harga_format = number_format($d_paket['Harga_Paket'] ?? 0, 0, ',', '.');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Ruangan - SpotLight Studio</title>

    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --sidebar-bg: #ffffff;
            --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex; flex-direction: column;
            justify-content: space-between; padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem;
            color: var(--p-pink); text-decoration: none;
            letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700;
            text-decoration: none; border-radius: 12px; font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px;
            color: #718096; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink); background-color: rgba(216, 63, 103, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216, 63, 103, 0.2); }

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 35px;
        }
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* PROGRESS BAR */
        .booking-progress {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 40px; gap: 0;
        }
        .progress-step {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .progress-step-circle {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem; transition: var(--transition-3d);
            border: 3px solid #e2e8f0; background: #ffffff; color: #94a3b8;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink); color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .progress-step.completed .progress-step-circle {
            background: #059669; border-color: #059669; color: #ffffff;
        }
        .progress-step-label {
            font-size: 0.75rem; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 60px; height: 3px; background: #e2e8f0;
            margin: 0 10px; margin-bottom: 24px;
        }
        .progress-line.completed { background: #059669; }

        /* PAKET INFO CARD */
        .paket-info-card {
            background: linear-gradient(135deg, #fff, var(--s-pink));
            border: 1px solid var(--light-pink);
            border-radius: 20px; padding: 20px 25px;
            display: flex; align-items: center; gap: 20px;
            margin-bottom: 30px;
        }
        .paket-info-img {
            width: 80px; height: 80px; object-fit: cover;
            border-radius: 16px; border: 2px solid var(--light-pink);
        }
        .paket-info-detail h4 {
            font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;
        }
        .paket-info-detail .paket-harga {
            color: var(--p-pink); font-weight: 800; font-size: 1.2rem;
        }
        .paket-info-detail .paket-meta {
            color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;
        }

        /* RUANGAN GRID */
        .ruangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .ruangan-card {
            background: #ffffff; border-radius: 24px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d); overflow: hidden;
            cursor: pointer; position: relative;
        }
        .ruangan-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 22px 45px rgba(216, 63, 103, 0.14);
            border-color: var(--p-pink);
        }
        .ruangan-card-img {
            width: 100%; height: 200px; object-fit: cover;
            border-bottom: 1px solid var(--light-pink);
        }
        .ruangan-card-body { padding: 24px; }
        .ruangan-card-badge {
            display: inline-block; padding: 4px 12px;
            background: var(--s-pink); color: var(--p-pink);
            border-radius: 50px; font-size: 0.7rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;
        }
        .ruangan-card-title {
            font-size: 1.2rem; font-weight: 800; color: var(--text-dark);
            margin-bottom: 8px; line-height: 1.3;
        }
        .ruangan-card-desc {
            font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;
            line-height: 1.5; min-height: 40px;
        }
        .ruangan-card-meta {
            display: flex; gap: 16px; margin-bottom: 20px;
        }
        .ruangan-card-meta-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.8rem; color: #4a5568; font-weight: 600;
        }
        .ruangan-card-meta-item i { color: var(--p-pink); font-size: 1rem; }
        .btn-pilih-ruangan {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            font-weight: 800; font-size: 0.9rem;
            transition: var(--transition-3d); display: flex;
            align-items: center; justify-content: center; gap: 8px;
        }
        .btn-pilih-ruangan:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* MODAL PROPERTI */
        .modal-properti .modal-content {
            border: none; border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .modal-properti .modal-header {
            border: none; padding: 24px 24px 16px;
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
        }
        .modal-properti .modal-body { padding: 0 24px 24px; }
        .modal-ruangan-img {
            width: 100%; height: 180px; object-fit: cover;
            border-radius: 16px; margin-bottom: 20px;
        }
        .properti-section-title {
            font-size: 0.75rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;
        }
        .properti-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px; margin-bottom: 24px;
        }
        .properti-item {
            background: #f8fafc; border-radius: 12px; padding: 12px;
            text-align: center; border: 1px solid #eef2f6;
            transition: var(--transition-3d);
        }
        .properti-item:hover {
            transform: translateY(-2px); border-color: var(--p-pink);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.08);
        }
        .properti-item i {
            font-size: 1.5rem; color: var(--p-pink); margin-bottom: 6px;
        }
        .properti-item-name {
            font-size: 0.7rem; font-weight: 700; color: var(--text-dark);
            line-height: 1.3;
        }
        .properti-item-kategori {
            font-size: 0.6rem; color: var(--text-muted); font-weight: 600;
        }
        .btn-lanjut-tema {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            font-weight: 800; font-size: 0.95rem;
            transition: var(--transition-3d);
        }
        .btn-lanjut-tema:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 20px; }
        .empty-state h4 { font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
            .ruangan-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Studio Foto</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLayanan">
                        <span><i class="bi bi-camera-fill me-2"></i> Layanan Studio</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuLayanan">
                        <ul class="list-unstyled">
                            <li><a href="../Paket/pilih_paket.php" class="submenu-link"><i class="bi bi-collection-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="pilih_ruangan.php" class="submenu-link active"><i class="bi bi-door-open-fill me-2"></i>Ruangan Studio</a></li>
                            <li><a href="../Tema/pilih_tema.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal/pilih_jadwal.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Tersedia</a></li>
                            <li><a href="../Portofolio/index.php" class="submenu-link"><i class="bi bi-images me-2"></i>Portofolio</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuBooking">
                        <span><i class="bi bi-calendar-check-fill me-2"></i> Booking Saya</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuBooking">
                        <ul class="list-unstyled">
                            <li><a href="../../../../Booking/Baru/index.php" class="submenu-link"><i class="bi bi-plus-circle-fill me-2"></i>Booking Baru</a></li>
                            <li><a href="../../../../Booking/Riwayat/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Booking</a></li>
                            <li><a href="../../../../Booking/Pembayaran/index.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Pembayaran</a></li>
                            <li><a href="../../../../Booking/Hasil/index.php" class="submenu-link"><i class="bi bi-download me-2"></i>Download Hasil</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuCetak">
                        <span><i class="bi bi-printer-fill me-2"></i> Barang Cetak</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuCetak">
                        <ul class="list-unstyled">
                            <li><a href="../../../../Cetak/Katalog/index.php" class="submenu-link"><i class="bi bi-shop me-2"></i>Katalog Barang</a></li>
                            <li><a href="../../../../Cetak/Pesanan/index.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Pesanan Saya</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i> Beranda</span>
                    </a>
                </li>
            </ul>
        </div>
        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Pilih Ruangan Studio</h3>
                <p class="text-muted small mb-0">Langkah 2 dari 4 - Pilih ruangan yang sesuai dengan paket <strong><?= htmlspecialchars($d_paket['Nama_Paket']) ?></strong>.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-person-circle me-1 text-danger"></i> <?= htmlspecialchars($nama_customer) ?>
                </span>
                <div class="profile-header-btn shadow-sm" title="Profil Anda">
                    <img src="<?= $foto_customer_src ?>" alt="Profil">
                </div>
            </div>
        </div>

        <!-- PROGRESS BAR -->
        <div class="booking-progress">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">2</div>
                <div class="progress-step-label">Ruangan</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
        </div>

        <!-- PAKET INFO -->
        <div class="paket-info-card">
            <img src="<?= $foto_paket_src ?>" class="paket-info-img" alt="<?= htmlspecialchars($d_paket['Nama_Paket']) ?>">
            <div class="paket-info-detail">
                <h4><?= htmlspecialchars($d_paket['Nama_Paket']) ?></h4>
                <div class="paket-harga">Rp <?= $harga_format ?></div>
                <div class="paket-meta">
                    <i class="bi bi-clock me-1"></i> <?= $d_paket['Durasi_Waktu'] ?> menit &nbsp;|&nbsp;
                    <i class="bi bi-people me-1"></i> Max <?= $d_paket['Kapasitas_Orang'] ?> orang
                </div>
            </div>
        </div>

        <!-- RUANGAN GRID -->
        <div class="ruangan-grid">
            <?php
            if (!empty($ruangan_list)):
                foreach($ruangan_list as $row):
                    $path_img = "../../../../assets/img/ruangan/" . ($row['Foto_Ruangan'] ?? '');
                    $img_src = (!empty($row['Foto_Ruangan']) && file_exists($path_img))
                        ? $path_img 
                        : "../../../../assets/img/ruangan/default_ruangan.jpg";
                    
                    $properti_list = $properti_per_ruangan[$row['ID_Ruangan']] ?? [];
            ?>
                <div class="ruangan-card animate-fade-in-up" onclick="bukaModalRuangan(<?= $row['ID_Ruangan'] ?>)">
                    <img src="<?= $img_src ?>" class="ruangan-card-img" alt="<?= htmlspecialchars($row['Nama_Ruangan']) ?>">
                    <div class="ruangan-card-body">
                        <div class="ruangan-card-badge">Ruangan Studio</div>
                        <div class="ruangan-card-title"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                        <div class="ruangan-card-desc"><?= htmlspecialchars($row['Deskripsi'] ?? 'Ruangan studio dengan fasilitas lengkap untuk sesi foto Anda.') ?></div>
                        <div class="ruangan-card-meta">
                            <div class="ruangan-card-meta-item">
                                <i class="bi bi-people-fill"></i>
                                <?= $row['Kapasitas_Ruangan'] ?> orang
                            </div>
                            <div class="ruangan-card-meta-item">
                                <i class="bi bi-box-seam-fill"></i>
                                <?= count($properti_list) ?> properti
                            </div>
                        </div>
                        <button class="btn-pilih-ruangan" id="btn-ruangan-<?= $row['ID_Ruangan'] ?>">
                            <i class="bi bi-eye-fill"></i>
                            Lihat Detail & Pilih
                        </button>
                    </div>
                </div>

                <!-- MODAL PROPERTI RUANGAN -->
                <div class="modal fade modal-properti" id="modalRuangan<?= $row['ID_Ruangan'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="fw-bold mb-0"><i class="bi bi-door-open-fill text-danger me-2"></i><?= htmlspecialchars($row['Nama_Ruangan']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <img src="<?= $img_src ?>" class="modal-ruangan-img" alt="<?= htmlspecialchars($row['Nama_Ruangan']) ?>">
                                
                                <p class="text-muted mb-4"><?= htmlspecialchars($row['Deskripsi'] ?? 'Ruangan studio dengan fasilitas lengkap untuk sesi foto Anda.') ?></p>
                                
                                <!-- PROPERTI TERSEDIA -->
                                <div class="properti-section-title">
                                    <i class="bi bi-box-seam-fill me-2"></i>
                                    Properti Tersedia (<?= count($properti_list) ?>)
                                </div>
                                
                                <?php if (!empty($properti_list)): ?>
                                    <div class="properti-grid">
                                        <?php foreach ($properti_list as $p): 
                                            $icon_map = [
                                                'Mebel' => 'bi-chair',
                                                'Lampu' => 'bi-lightbulb',
                                                'Dekorasi' => 'bi-stars',
                                                'Kostum' => 'bi-person-badge',
                                                'Latar' => 'bi-image'
                                            ];
                                            $icon = $icon_map[$p['Kategori_Properti']] ?? 'bi-box';
                                        ?>
                                            <div class="properti-item">
                                                <i class="bi <?= $icon ?>"></i>
                                                <div class="properti-item-name"><?= htmlspecialchars($p['Nama_Properti']) ?></div>
                                                <div class="properti-item-kategori"><?= htmlspecialchars($p['Kategori_Properti']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light border-2 border-dashed mb-4" style="border-color: #e2e8f0; border-radius: 14px;">
                                        <i class="bi bi-info-circle-fill me-2 text-info"></i>
                                        <span class="small text-muted">Tidak ada properti khusus di ruangan ini.</span>
                                    </div>
                                <?php endif; ?>

                                <!-- TOMBOL LANJUT KE TEMA -->
                                <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $row['ID_Ruangan'] ?>" 
                                   class="btn-lanjut-tema text-decoration-none d-block text-center">
                                    <i class="bi bi-arrow-right-circle-fill me-2"></i>
                                    Pilih Ruangan Ini & Lanjut ke Tema
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach; 
            else:
            ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="bi bi-door-open"></i>
                    <h4>Tidak Ada Ruangan Tersedia</h4>
                    <p>Maaf, tidak ada ruangan yang terhubung dengan paket <strong><?= htmlspecialchars($d_paket['Nama_Paket']) ?></strong>.<br>Silakan kembali dan pilih paket lain.</p>
                    <a href="../Paket/pilih_paket.php" class="btn-pilih-ruangan text-decoration-none d-inline-block mt-3" style="width: auto; padding: 12px 30px;">
                        <i class="bi bi-arrow-left me-2"></i>Kembali ke Pilih Paket
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle Submenu
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
                    if (!isShown) {
                        targetEl.classList.add('show');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
        });

        function bukaModalRuangan(idRuangan) {
            var modal = new bootstrap.Modal(document.getElementById('modalRuangan' + idRuangan));
            modal.show();
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar?',
                text: 'Apakah Anda yakin ingin keluar dari akun?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama.',
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
        }
    </script>
</body>
</html>