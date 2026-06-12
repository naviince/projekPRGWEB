<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

// =====================================================
// AMBIL ID RUANGAN
// =====================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID ruangan tidak valid");
    exit();
}

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// AMBIL DATA RUANGAN
// =====================================================
$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT r.*, 
        (SELECT COUNT(*) FROM Paket_Ruangan pr WHERE pr.ID_Ruangan = r.ID_Ruangan) as total_paket,
        (SELECT COUNT(*) FROM Properti p WHERE p.ID_Ruangan = r.ID_Ruangan AND p.Status = 1 AND p.Is_Deleted = 0) as total_properti,
        (SELECT COUNT(*) FROM Ruangan_Tema rt WHERE rt.ID_Ruangan = r.ID_Ruangan) as total_tema
    FROM Ruangan r 
    WHERE r.ID_Ruangan = ? AND r.Is_Deleted = 0", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan");
    exit();
}

// =====================================================
// AMBIL PAKET TERHUBUNG
// =====================================================
$paket_terhubung = safe_sqlsrv_fetch_all($conn,
    "SELECT p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Kapasitas_Orang, p.Durasi_Waktu, p.Foto_Paket, p.Deskripsi as Deskripsi_Paket
    FROM Paket_Ruangan pr
    JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket
    WHERE pr.ID_Ruangan = ? AND p.Status = 1 AND p.Is_Deleted = 0
    ORDER BY p.Harga_Paket ASC",
    [$id]
);

// =====================================================
// AMBIL PROPERTI
// =====================================================
$properti_list = safe_sqlsrv_fetch_all($conn,
    "SELECT * FROM Properti 
    WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0
    ORDER BY Nama_Properti ASC",
    [$id]
);

// =====================================================
// AMBIL TEMA
// =====================================================
$tema_list = safe_sqlsrv_fetch_all($conn,
    "SELECT t.* FROM Ruangan_Tema rt
    JOIN Tema_Foto t ON rt.ID_Tema = t.ID_Tema
    WHERE rt.ID_Ruangan = ? AND t.Status = 1 AND t.Is_Deleted = 0
    ORDER BY t.Nama_Tema ASC",
    [$id]
);

// =====================================================
// AMBIL STATISTIK ORDER
// =====================================================
$order_stats = safe_sqlsrv_fetch($conn,
    "SELECT 
        COUNT(*) as total_order,
        SUM(CASE WHEN Status_Order = 0 THEN 1 ELSE 0 END) as menunggu_dp,
        SUM(CASE WHEN Status_Order = 1 THEN 1 ELSE 0 END) as dp_verified,
        SUM(CASE WHEN Status_Order = 2 THEN 1 ELSE 0 END) as selesai_foto,
        SUM(CASE WHEN Status_Order = 3 THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN Status_Order = 4 THEN 1 ELSE 0 END) as dibatalkan
    FROM [Order] 
    WHERE ID_Ruangan = ? AND Status = 1",
    [$id]
) ?? ['total_order' => 0, 'menunggu_dp' => 0, 'dp_verified' => 0, 'selesai_foto' => 0, 'lunas' => 0, 'dibatalkan' => 0];

// =====================================================
// AMBIL JADWAL TERDEKAT
// =====================================================
$jadwal_terdekat = safe_sqlsrv_fetch_all($conn,
    "SELECT TOP 5 js.*, r.Nama_Ruangan 
    FROM Jadwal_Studio js
    JOIN Ruangan r ON js.ID_Ruangan = r.ID_Ruangan
    WHERE js.ID_Ruangan = ? AND js.Status = 1 AND js.Is_Deleted = 0
        AND js.Tanggal_Jadwal >= CAST(GETDATE() AS DATE)
    ORDER BY js.Tanggal_Jadwal ASC, js.Jam_Mulai ASC",
    [$id]
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Ruangan – <?= htmlspecialchars($ruangan['Nama_Ruangan']) ?> – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66;
            --d-pink: #CA3366;
            --s-pink: #FFF0F3;
            --light-pink: #FFE4E9;
            --accent-pink: #E85D84;
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
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
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
            color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        /* MAIN CONTENT */
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
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* BREADCRUMB */
        .breadcrumb-custom {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 25px; font-size: 0.85rem; font-weight: 600;
        }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }
        .breadcrumb-custom i { color: #cbd5e1; font-size: 0.7rem; }

        /* DETAIL CARD */
        .detail-card {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            overflow: hidden; margin-bottom: 24px;
        }
        .detail-card-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            padding: 30px 40px; color: #ffffff;
            display: flex; align-items: center; gap: 20px;
        }
        .detail-foto {
            width: 120px; height: 120px; border-radius: 20px;
            object-fit: cover; border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .detail-title { font-weight: 800; font-size: 1.6rem; margin-bottom: 4px; }
        .detail-subtitle { opacity: 0.85; font-size: 0.9rem; }
        .detail-card-body { padding: 30px 40px; }

        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .info-item {
            background: #f8fafc; border-radius: 16px;
            padding: 20px; border: 2px solid #e2e8f0;
            transition: var(--transition-3d);
        }
        .info-item:hover {
            border-color: var(--p-pink); transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.08);
        }
        .info-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 12px;
        }
        .info-icon-pink { background: var(--s-pink); color: var(--p-pink); }
        .info-icon-green { background: #ecfdf5; color: #059669; }
        .info-icon-blue { background: #eff6ff; color: #2563eb; }
        .info-icon-orange { background: #fff7ed; color: #ea580c; }
        .info-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
        .info-value { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); }
        .info-desc { font-size: 0.8rem; color: #a0aec0; font-weight: 600; margin-top: 4px; }

        /* SECTION TITLE */
        .section-title {
            font-weight: 800; font-size: 1rem;
            color: var(--text-dark); margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; letter-spacing: 0.8px;
        }
        .section-title i { color: var(--p-pink); font-size: 1.2rem; }

        /* PAKET CARD */
        .paket-card {
            background: #ffffff; border-radius: 16px;
            border: 2px solid #e2e8f0; padding: 20px;
            transition: var(--transition-3d); display: flex;
            align-items: center; gap: 16px;
        }
        .paket-card:hover {
            border-color: var(--p-pink); transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.08);
        }
        .paket-img {
            width: 60px; height: 60px; border-radius: 14px;
            object-fit: cover; border: 2px solid var(--light-pink);
        }
        .paket-info { flex: 1; }
        .paket-nama { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
        .paket-harga { font-size: 0.9rem; color: var(--p-pink); font-weight: 800; }
        .paket-meta { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }

        /* PROPERTI & TEMA LIST */
        .item-list {
            display: flex; flex-wrap: wrap; gap: 10px;
        }
        .item-badge {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 10px 16px; font-weight: 600; font-size: 0.85rem;
            color: #4a5568; display: flex; align-items: center; gap: 8px;
            transition: var(--transition-3d);
        }
        .item-badge:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .item-badge i { font-size: 1rem; }

        /* JADWAL TABLE */
        .jadwal-table {
            width: 100%; border-collapse: separate; border-spacing: 0;
        }
        .jadwal-table th {
            background: #f8fafc; padding: 12px 16px;
            font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
            color: #94a3b8; border-bottom: 2px solid #e2e8f0; text-align: left;
        }
        .jadwal-table td {
            padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem; font-weight: 600; color: #4a5568;
        }
        .jadwal-status {
            font-size: 0.7rem; font-weight: 700; padding: 4px 12px;
            border-radius: 50px; display: inline-block;
        }
        .status-tersedia { background: #ecfdf5; color: #059669; }
        .status-terpesan { background: #fef3c7; color: #d97706; }
        .status-selesai { background: #e0e7ff; color: #4f46e5; }

        /* BUTTONS */
        .btn-edit {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 12px 24px; font-weight: 800; font-size: 0.9rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-edit:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3); color: #ffffff; }
        .btn-kembali {
            background: #f1f5f9; color: #475569; border: none;
            border-radius: 14px; padding: 12px 24px;
            font-weight: 800; font-size: 0.9rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-kembali:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-3px); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Admin</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                            <li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                            <li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                            <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span>
                    </a>
                </li>
            </ul>
        </div>
        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Detail Ruangan</h3>
                <p class="text-muted small mb-0">Informasi lengkap ruangan studio dan relasinya.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- BREADCRUMB -->
        <div class="breadcrumb-custom">
            <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Data Master</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Ruangan</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Detail</span>
        </div>

        <!-- DETAIL CARD -->
        <div class="detail-card fade-in-up">
            <div class="detail-card-header">
                <?php 
                $path_foto = "../../assets/img/ruangan/" . ($ruangan['Foto_Ruangan'] ?? '');
                $foto_src = (!empty($ruangan['Foto_Ruangan']) && file_exists($path_foto)) ? $path_foto : $default_svg_avatar;
                ?>
                <img src="<?= $foto_src ?>" class="detail-foto" alt="<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>">
                <div>
                    <div class="detail-title"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                    <div class="detail-subtitle">
                        <i class="bi bi-people-fill me-1"></i> Kapasitas <?= $ruangan['Kapasitas_Ruangan'] ?> orang • 
                        <span class="badge <?= $ruangan['Status'] == 1 ? 'bg-success' : 'bg-danger' ?>" style="font-size: 0.75rem;">
                            <?= $ruangan['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="detail-card-body">

                <!-- DESKRIPSI -->
                <p class="text-muted mb-4" style="font-size: 0.9rem; line-height: 1.7;">
                    <?= htmlspecialchars($ruangan['Deskripsi'] ?? 'Tidak ada deskripsi.') ?>
                </p>

                <!-- INFO GRID -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon info-icon-pink"><i class="bi bi-camera-fill"></i></div>
                        <div class="info-label">Paket Terhubung</div>
                        <div class="info-value"><?= $ruangan['total_paket'] ?? 0 ?></div>
                        <div class="info-desc">Paket foto</div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon info-icon-green"><i class="bi bi-box-seam-fill"></i></div>
                        <div class="info-label">Properti</div>
                        <div class="info-value"><?= $ruangan['total_properti'] ?? 0 ?></div>
                        <div class="info-desc">Item properti</div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon info-icon-blue"><i class="bi bi-palette-fill"></i></div>
                        <div class="info-label">Tema</div>
                        <div class="info-value"><?= $ruangan['total_tema'] ?? 0 ?></div>
                        <div class="info-desc">Tema foto</div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon info-icon-orange"><i class="bi bi-calendar-check-fill"></i></div>
                        <div class="info-label">Total Order</div>
                        <div class="info-value"><?= $order_stats['total_order'] ?? 0 ?></div>
                        <div class="info-desc">Booking</div>
                    </div>
                </div>

                <!-- PAKET TERHUBUNG -->
                <div class="mb-4">
                    <div class="section-title"><i class="bi bi-camera-fill"></i> Paket Foto Terhubung</div>
                    <?php if (!empty($paket_terhubung)): ?>
                        <div class="row g-3">
                            <?php foreach ($paket_terhubung as $paket): 
                                $path_paket = "../../assets/img/paket/" . ($paket['Foto_Paket'] ?? '');
                                $img_paket = (!empty($paket['Foto_Paket']) && file_exists($path_paket)) ? $path_paket : $default_svg_avatar;
                            ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="paket-card">
                                        <img src="<?= $img_paket ?>" class="paket-img" alt="<?= htmlspecialchars($paket['Nama_Paket']) ?>">
                                        <div class="paket-info">
                                            <div class="paket-nama"><?= htmlspecialchars($paket['Nama_Paket']) ?></div>
                                            <div class="paket-harga">Rp <?= number_format($paket['Harga_Paket'], 0, ',', '.') ?></div>
                                            <div class="paket-meta"><?= $paket['Kapasitas_Orang'] ?> orang • <?= $paket['Durasi_Waktu'] ?> menit</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 16px;">
                            <i class="bi bi-exclamation-circle me-2 text-muted"></i> Belum ada paket foto terhubung
                        </div>
                    <?php endif; ?>
                </div>

                <!-- PROPERTI -->
                <div class="mb-4">
                    <div class="section-title"><i class="bi bi-box-seam-fill"></i> Properti</div>
                    <?php if (!empty($properti_list)): ?>
                        <div class="item-list">
                            <?php foreach ($properti_list as $properti): ?>
                                <div class="item-badge">
                                    <i class="bi bi-box"></i>
                                    <?= htmlspecialchars($properti['Nama_Properti']) ?>
                                    <span class="text-muted" style="font-size: 0.7rem;">(<?= htmlspecialchars($properti['Kategori_Properti'] ?? '-') ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 16px;">
                            <i class="bi bi-exclamation-circle me-2 text-muted"></i> Belum ada properti
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TEMA -->
                <div class="mb-4">
                    <div class="section-title"><i class="bi bi-palette-fill"></i> Tema Foto</div>
                    <?php if (!empty($tema_list)): ?>
                        <div class="item-list">
                            <?php foreach ($tema_list as $tema): ?>
                                <div class="item-badge">
                                    <i class="bi bi-palette"></i>
                                    <?= htmlspecialchars($tema['Nama_Tema']) ?>
                                    <span class="text-muted" style="font-size: 0.7rem;">(<?= htmlspecialchars($tema['Kategori_Tema'] ?? '-') ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 16px;">
                            <i class="bi bi-exclamation-circle me-2 text-muted"></i> Belum ada tema foto
                        </div>
                    <?php endif; ?>
                </div>

                <!-- JADWAL TERDEKAT -->
                <div class="mb-4">
                    <div class="section-title"><i class="bi bi-calendar-week-fill"></i> Jadwal Terdekat</div>
                    <?php if (!empty($jadwal_terdekat)): ?>
                        <div class="table-responsive">
                            <table class="jadwal-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th>Keterangan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jadwal_terdekat as $jadwal): 
                                        $status_jadwal = $jadwal['Status_Jadwal'] ?? 0;
                                        $badge_class = $status_jadwal == 0 ? 'status-tersedia' : ($status_jadwal == 1 ? 'status-terpesan' : 'status-selesai');
                                        $text_jadwal = $status_jadwal == 0 ? 'Tersedia' : ($status_jadwal == 1 ? 'Terpesan' : 'Selesai');

                                        // Handle DateTime object dari SQL Server
                                        $tgl_jadwal = $jadwal['Tanggal_Jadwal'];
                                        if (is_object($tgl_jadwal) && method_exists($tgl_jadwal, 'format')) {
                                            $tgl_str = $tgl_jadwal->format('d M Y');
                                        } else {
                                            $tgl_str = date('d M Y', strtotime($tgl_jadwal));
                                        }

                                        // Handle TIME type dari SQL Server
                                        $jam_mulai = $jadwal['Jam_Mulai'];
                                        $jam_selesai = $jadwal['Jam_Selesai'];
                                        if (is_object($jam_mulai) && method_exists($jam_mulai, 'format')) {
                                            $jam_mulai_str = $jam_mulai->format('H:i');
                                            $jam_selesai_str = $jam_selesai->format('H:i');
                                        } else {
                                            $jam_mulai_str = substr($jam_mulai, 0, 5);
                                            $jam_selesai_str = substr($jam_selesai, 0, 5);
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $tgl_str ?></td>
                                            <td><?= $jam_mulai_str ?> - <?= $jam_selesai_str ?></td>
                                            <td><?= htmlspecialchars($jadwal['Keterangan'] ?? '-') ?></td>
                                            <td><span class="jadwal-status <?= $badge_class ?>"><?= $text_jadwal ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 16px;">
                            <i class="bi bi-exclamation-circle me-2 text-muted"></i> Tidak ada jadwal mendatang
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ORDER STATS -->
                <div class="mb-4">
                    <div class="section-title"><i class="bi bi-graph-up"></i> Statistik Order</div>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="info-item" style="text-align: center;">
                                <div class="info-label">Menunggu DP</div>
                                <div class="info-value" style="color: #d97706;"><?= $order_stats['menunggu_dp'] ?? 0 ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="info-item" style="text-align: center;">
                                <div class="info-label">DP Terverifikasi</div>
                                <div class="info-value" style="color: #2563eb;"><?= $order_stats['dp_verified'] ?? 0 ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="info-item" style="text-align: center;">
                                <div class="info-label">Selesai Foto</div>
                                <div class="info-value" style="color: #7c3aed;"><?= $order_stats['selesai_foto'] ?? 0 ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="info-item" style="text-align: center;">
                                <div class="info-label">Lunas</div>
                                <div class="info-value" style="color: #059669;"><?= $order_stats['lunas'] ?? 0 ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BUTTONS -->
                <div class="d-flex gap-3 mt-4">
                    <a href="edit.php?id=<?= $id ?>" class="btn-edit">
                        <i class="bi bi-pencil-square"></i> Edit Ruangan
                    </a>
                    <a href="list.php" class="btn-kembali">
                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                    </a>
                </div>

            </div>
        </div>

    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

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

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            document.getElementById('live-clock').innerText = 
                `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();
    </script>
</body>
</html>