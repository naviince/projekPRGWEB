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
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
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
// AMBIL PROFIL ADMIN
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_admin = $d_profile['nama_karyawan'] ?? 'Admin';
$username_admin = $d_profile['username_karyawan'] ?? 'admin';
$email_admin = $d_profile['email_karyawan'] ?? 'admin@spotlight.com';
$foto_admin = $d_profile['foto_profil'] ?? 'default.jpg';

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;

// =====================================================
// AMBIL ID TEMA DARI URL
// =====================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID tema tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA TEMA FOTO LENGKAP
// =====================================================
$tema = safe_sqlsrv_fetch($conn,
    "SELECT * FROM Tema_Foto WHERE ID_Tema = ? AND Is_Deleted = 0",
    [$id]
);

if (!$tema) {
    header("Location: list.php?status_sukses=error&message=Tema foto tidak ditemukan atau sudah dihapus");
    exit();
}

// =====================================================
// AMBIL DAFTAR RUANGAN YANG TERHUBUNG DENGAN TEMA INI
// =====================================================
$daftar_ruangan_terhubung = safe_sqlsrv_fetch_all($conn,
    "SELECT r.ID_Ruangan, r.Nama_Ruangan, r.Deskripsi, r.Foto_Ruangan, r.Status
     FROM Ruangan_Tema rt
     JOIN Ruangan r ON rt.ID_Ruangan = r.ID_Ruangan
     WHERE rt.ID_Tema = ? AND r.Is_Deleted = 0
     ORDER BY r.Nama_Ruangan ASC",
    [$id]
);

// =====================================================
// HITUNG STATISTIK BOOKING TEMA INI (JIKA ADA RELASI ORDER)
// =====================================================
$total_booking = safe_sqlsrv_fetch($conn,
    "SELECT COUNT(*) as total FROM [Order] WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4",
    [$id]
);
$jumlah_booking = $total_booking['total'] ?? 0;

// Foto tema
$path_img = "../../assets/img/tema/" . ($tema['Foto_Tema'] ?? '');
$img_src = (!empty($tema['Foto_Tema']) && file_exists($path_img)) ? $path_img : $default_svg_avatar;

// Format tanggal dibuat/diubah jika ada kolomnya
function formatTanggalDetail($val) {
    if (empty($val)) return '-';
    if ($val instanceof DateTime) return $val->format('d M Y, H:i') . ' WIB';
    $ts = strtotime($val);
    return $ts ? date('d M Y, H:i', $ts) . ' WIB' : '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tema Foto – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
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
            margin-bottom: 25px;
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

        /* HERO CARD - FOTO + INFO UTAMA */
        .card-3d {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
        }

        .detail-hero {
            display: flex; gap: 30px; padding: 30px;
        }
        .detail-hero-img {
            width: 280px; height: 280px; flex-shrink: 0;
            border-radius: 20px; object-fit: cover;
            border: 3px solid var(--light-pink);
            box-shadow: 0 12px 30px rgba(213, 61, 102, 0.1);
        }
        .detail-hero-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .detail-hero-title { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); margin-bottom: 10px; }
        .detail-hero-badges { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .detail-hero-desc { font-size: 0.9rem; color: #64748b; line-height: 1.7; margin-bottom: auto; }

        .badge-kategori {
            font-size: 0.75rem; font-weight: 700; padding: 7px 16px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #FFF0F3, #FFE4E9);
            color: var(--p-pink); border: 1px solid var(--light-pink);
        }
        .badge-status {
            font-size: 0.75rem; font-weight: 700; padding: 7px 16px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-aktif { background: #ecfdf5; color: #059669; }
        .badge-nonaktif { background: #fef2f2; color: #dc2626; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-aktif .badge-dot { background: #059669; }
        .badge-nonaktif .badge-dot { background: #dc2626; }

        .detail-hero-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-edit-tema {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 12px 26px; font-weight: 800; font-size: 0.88rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-edit-tema:hover {
            transform: translateY(-3px); color: #ffffff;
            box-shadow: 0 10px 25px rgba(213, 61, 102, 0.3);
        }
        .btn-kembali {
            background: #f1f5f9; color: #475569; border: none;
            border-radius: 14px; padding: 12px 26px;
            font-weight: 700; font-size: 0.88rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-kembali:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-3px); }

        /* SECTION TITLE */
        .section-title {
            font-size: 0.85rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.8px; color: var(--text-dark);
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 18px;
        }
        .section-title i { color: var(--p-pink); }

        /* STAT MINI CARDS */
        .mini-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .mini-stat-card {
            background: #ffffff; border-radius: 18px; padding: 20px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 6px 18px rgba(213, 61, 102, 0.03);
            display: flex; align-items: center; gap: 14px;
        }
        .mini-stat-icon {
            width: 46px; height: 46px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .mini-stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .mini-stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .mini-stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .mini-stat-val { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); line-height: 1.2; }
        .mini-stat-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; }

        /* RUANGAN TERHUBUNG LIST */
        .ruangan-card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .ruangan-item-card {
            background: #f8fafc; border-radius: 16px; padding: 16px;
            border: 2px solid #f1f5f9; display: flex; align-items: center; gap: 14px;
            transition: var(--transition-3d);
        }
        .ruangan-item-card:hover { border-color: var(--light-pink); background: #ffffff; transform: translateY(-2px); }
        .ruangan-item-img {
            width: 56px; height: 56px; border-radius: 12px; object-fit: cover;
            border: 2px solid var(--light-pink); flex-shrink: 0;
        }
        .ruangan-item-info { min-width: 0; flex: 1; }
        .ruangan-item-nama { font-weight: 700; font-size: 0.88rem; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ruangan-item-desc { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ruangan-item-status {
            font-size: 0.62rem; font-weight: 700; padding: 3px 10px; border-radius: 50px;
            display: inline-block; margin-top: 4px;
        }
        .ruangan-status-aktif { background: #ecfdf5; color: #059669; }
        .ruangan-status-nonaktif { background: #fef2f2; color: #dc2626; }

        .empty-state {
            text-align: center; padding: 40px 20px; color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: 12px; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        /* RESPONSIVE */
        .mobile-menu-btn {
            display: none; width: 44px; height: 44px; border-radius: 12px;
            background: #ffffff; border: 2px solid var(--light-pink); color: var(--p-pink);
            align-items: center; justify-content: center; font-size: 1.4rem; cursor: pointer;
            transition: var(--transition-3d); flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mobile-menu-btn:hover { background: var(--s-pink); transform: scale(1.05); }

        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(30, 30, 36, 0.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
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
            .dashboard-header { flex-wrap: wrap; gap: 12px; }
            .detail-hero { flex-direction: column; }
            .detail-hero-img { width: 100%; height: 220px; }
            .mini-stat-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 576px) {
            .main-content { padding: 14px; }
            .detail-hero { padding: 20px; }
            .detail-hero-title { font-size: 1.25rem; }
            .detail-hero-actions { flex-direction: column; }
            .btn-edit-tema, .btn-kembali { width: 100%; justify-content: center; }
            .ruangan-card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Administrator</span>
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
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
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
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
                            <li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
                            <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i>Beranda</span>
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
        <div class="dashboard-header fade-in-up">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Detail Tema Foto</h3>
                    <p class="text-muted small mb-0">Informasi lengkap tema foto dan ruangan yang terhubung.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
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
            <a href="./list.php">Tema Foto</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Detail: <?= htmlspecialchars($tema['Nama_Tema']) ?></span>
        </div>

        <!-- HERO CARD -->
        <div class="card-3d mb-4 fade-in-up">
            <div class="detail-hero">
                <img src="<?= $img_src ?>" class="detail-hero-img" alt="<?= htmlspecialchars($tema['Nama_Tema']) ?>">
                <div class="detail-hero-body">
                    <div>
                        <div class="detail-hero-title"><?= htmlspecialchars($tema['Nama_Tema']) ?></div>
                        <div class="detail-hero-badges">
                            <span class="badge-kategori">
                                <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($tema['Kategori_Tema'] ?? '-') ?>
                            </span>
                            <span class="badge-status <?= $tema['Status'] == 1 ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                <span class="badge-dot"></span>
                                <?= $tema['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>
                        <div class="detail-hero-desc">
                            <?= !empty($tema['Deskripsi']) ? nl2br(htmlspecialchars($tema['Deskripsi'])) : '<span class="text-muted fst-italic">Belum ada deskripsi untuk tema ini.</span>' ?>
                        </div>
                    </div>
                    <div class="detail-hero-actions">
                        <a href="edit.php?id=<?= $tema['ID_Tema'] ?>" class="btn-edit-tema">
                            <i class="bi bi-pencil-fill"></i> Edit Tema Foto
                        </a>
                        <a href="list.php" class="btn-kembali">
                            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- MINI STATS -->
        <div class="mini-stat-grid">
            <div class="mini-stat-card">
                <div class="mini-stat-icon mini-stat-icon-blue"><i class="bi bi-door-open-fill"></i></div>
                <div>
                    <div class="mini-stat-val"><?= count($daftar_ruangan_terhubung) ?> Ruangan</div>
                    <div class="mini-stat-label">Terhubung ke Tema Ini</div>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="mini-stat-icon mini-stat-icon-green"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="mini-stat-val"><?= $jumlah_booking ?> Booking</div>
                    <div class="mini-stat-label">Pernah Menggunakan Tema</div>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="mini-stat-icon mini-stat-icon-pink"><i class="bi bi-hash"></i></div>
                <div>
                    <div class="mini-stat-val">#<?= $tema['ID_Tema'] ?></div>
                    <div class="mini-stat-label">ID Tema Foto</div>
                </div>
            </div>
        </div>

        <!-- RUANGAN TERHUBUNG -->
        <div class="card-3d mb-4 fade-in-up" style="padding: 28px;">
            <div class="section-title">
                <i class="bi bi-door-open-fill"></i> Ruangan yang Menggunakan Tema Ini
            </div>

            <?php if (!empty($daftar_ruangan_terhubung)): ?>
                <div class="ruangan-card-grid">
                    <?php foreach ($daftar_ruangan_terhubung as $r):
                        $path_r = "../../assets/img/ruangan/" . ($r['Foto_Ruangan'] ?? '');
                        $img_r_src = (!empty($r['Foto_Ruangan']) && file_exists($path_r)) ? $path_r : $default_svg_avatar;
                    ?>
                        <a href="../Ruangan/edit.php?id=<?= $r['ID_Ruangan'] ?>" class="ruangan-item-card text-decoration-none">
                            <img src="<?= $img_r_src ?>" class="ruangan-item-img" alt="<?= htmlspecialchars($r['Nama_Ruangan']) ?>">
                            <div class="ruangan-item-info">
                                <div class="ruangan-item-nama"><?= htmlspecialchars($r['Nama_Ruangan']) ?></div>
                                <div class="ruangan-item-desc"><?= htmlspecialchars($r['Deskripsi'] ?? '-') ?></div>
                                <span class="ruangan-item-status <?= $r['Status'] == 1 ? 'ruangan-status-aktif' : 'ruangan-status-nonaktif' ?>">
                                    <?= $r['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-door-closed"></i>
                    <p class="fw-bold mb-1">Belum ada ruangan yang terhubung</p>
                    <p class="small mb-0">Tema ini belum dikaitkan ke ruangan manapun. Atur relasi lewat menu <a href="../Ruangan/list.php" style="color: var(--p-pink);">Ruangan</a>.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);border-radius:50%;overflow:hidden;">
                            <img src="<?= $foto_admin_src ?>" alt="Foto Profil" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5>
                        <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Administrator</span>
                    </div>
                    <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                        <div class="row g-3">
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">NIK</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></span></div>
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nama Pengguna</small><span class="fw-bold text-dark" style="font-size:0.85rem;">@<?= htmlspecialchars($username_admin) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($email_admin) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
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

        // Modal Biodata
        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata'));
            modalBiodata.show();
        }

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>
</body>
</html>