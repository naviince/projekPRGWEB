<?php
session_start();
include '../../koneksi.php';

// =====================================================
// HELPER FUNCTIONS - SAFE SQLSRV ANTI-CRASH
// =====================================================
function safe_sqlsrv_query($conn, $sql, $params = array()) {
    $query = sqlsrv_query($conn, $sql, $params);
    if ($query === false) {
        error_log("SQLSRV Error: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    return $query;
}

function safe_sqlsrv_fetch($query) {
    if (!$query) return false;
    return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
}

function safe_sqlsrv_count($conn, $sql, $params = array()) {
    $query = safe_sqlsrv_query($conn, $sql, $params);
    if (!$query) return 0;
    $row = safe_sqlsrv_fetch($query);
    return $row ? ($row['total'] ?? 0) : 0;
}

// =====================================================
// PROTEKSI KEAMANAN HAK AKSES
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$username_session = $_SESSION['username'] ?? 'system';

// Ambil Profil Owner
$q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = safe_sqlsrv_fetch($q_profile);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner)) ? "../../assets/img/karyawan/" . $foto_owner : $default_svg_avatar;

// =====================================================
// HITUNG UMUR
// =====================================================
function hitungUmur($tanggal_lahir) {
    if (!$tanggal_lahir) return '-';
    if (is_object($tanggal_lahir) && method_exists($tanggal_lahir, 'format')) {
        $tgl = $tanggal_lahir->format('Y-m-d');
    } else { $tgl = $tanggal_lahir; }
    $birthDate = new DateTime($tgl);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y . " tahun";
}

// =====================================================
// TAB FILTER
// =====================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'aktif';

// =====================================================
// STATISTIK
// =====================================================
$stats = array();
$stats['total'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['admin'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Admin' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['fotografer'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['owner'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['aktif'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['nonaktif'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Status = 0 AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['dihapus'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Is_Deleted = 1 AND ID_Karyawan != ?", array($id_owner));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Karyawan – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --border-color: #f1f5f9;
            --sidebar-bg: #ffffff;
            --body-bg: #fafbfc;
            --transition: all 0.3s ease;
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
            position: fixed; top: 0; left: 0; border-right: 1px solid var(--border-color);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 6px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; color: #4a5568; font-weight: 600; text-decoration: none;
            border-radius: 10px; font-size: 0.88rem; transition: var(--transition);
        }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--s-pink); color: var(--p-pink); }
        .submenu { list-style: none; padding-left: 16px; margin-top: 4px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 7px 16px; color: #718096; font-weight: 500; font-size: 0.82rem; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(216, 63, 103, 0.03); }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: var(--transition);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216,63,103,0.2); }

        /* MAIN */
        .main-content { margin-left: 260px; padding: 35px 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }

        /* BREADCRUMB */
        .breadcrumb-custom { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 6px; }
        .breadcrumb-custom a { color: var(--p-pink); text-decoration: none; font-weight: 600; }
        .breadcrumb-custom .current { color: var(--text-dark); font-weight: 700; }

        /* LIVE CLOCK */
        .live-clock { display: flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 8px 14px; font-weight: 700; font-size: 0.8rem; color: var(--p-pink); }

        /* BUTTONS */
        .btn-reg-header {
            background: var(--p-pink) !important; color: #ffffff !important; border-radius: 12px !important;
            padding: 10px 24px !important; font-weight: 700 !important; border: none !important;
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.2) !important; transition: var(--transition) !important;
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem;
        }
        .btn-reg-header:hover { background: var(--d-pink) !important; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(216, 63, 103, 0.3) !important; }

        /* STAT CARDS */
        .stats-scroll-wrapper { display: flex; gap: 12px; overflow-x: auto; padding: 5px 2px 15px 2px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 5px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--p-pink); border-radius: 10px; }
        .stat-card-mini {
            min-width: 160px; flex-shrink: 0; background: #ffffff; border-radius: 14px;
            padding: 16px 20px; border: 1px solid var(--border-color); transition: var(--transition);
            display: flex; align-items: center; gap: 12px;
        }
        .stat-card-mini:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); border-color: rgba(216,63,103,0.15); }
        .stat-icon-mini { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .stat-info-mini h5 { font-size: 1.2rem; font-weight: 800; margin-bottom: 0; color: var(--text-dark); }
        .stat-info-mini small { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }

        /* TAB FILTER */
        .tab-filter-wrapper { display: flex; gap: 6px; margin-bottom: 20px; }
        .tab-filter-btn {
            padding: 8px 20px; border-radius: 10px; border: 1px solid var(--border-color);
            font-weight: 600; font-size: 0.82rem; cursor: pointer; transition: var(--transition);
            background: #ffffff; color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 6px;
        }
        .tab-filter-btn:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .tab-filter-btn.active { background: var(--p-pink); color: #ffffff; border-color: var(--p-pink); box-shadow: 0 2px 8px rgba(216,63,103,0.2); }
        .tab-filter-btn .badge-count { background: rgba(0,0,0,0.08); padding: 1px 7px; border-radius: 50px; font-size: 0.65rem; font-weight: 700; }
        .tab-filter-btn.active .badge-count { background: rgba(255,255,255,0.25); }

        /* SEARCH & FILTER */
        .search-filter-wrapper { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        .search-input { border-radius: 12px; border: 1px solid var(--border-color); padding: 10px 16px; font-weight: 500; font-size: 0.85rem; max-width: 320px; }
        .search-input:focus { border-color: var(--p-pink); box-shadow: 0 0 0 3px rgba(216,63,103,0.08); }
        .btn-filter-toggle {
            background: #ffffff; border: 1px solid var(--border-color); border-radius: 12px;
            padding: 10px 18px; font-weight: 600; color: var(--text-dark); font-size: 0.82rem;
            transition: var(--transition); display: flex; align-items: center; gap: 6px;
        }
        .btn-filter-toggle:hover { border-color: var(--p-pink); color: var(--p-pink); }

        /* TABLE - ZEBRA STRIPING */
        .table-wrapper { background: #ffffff; border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            padding: 14px 16px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); background: #fafbfc;
            border-bottom: 1px solid var(--border-color); white-space: nowrap; text-align: left;
        }
        .data-table thead th:first-child { padding-left: 24px; }
        .data-table thead th:last-child { padding-right: 24px; text-align: center; }

        .data-table tbody tr { transition: var(--transition); border-bottom: 1px solid var(--border-color); }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background-color: #fff8f9 !important; }

        /* ZEBRA STRIPING */
        .data-table tbody tr:nth-child(even) { background-color: #fafbfc; }
        .data-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .data-table tbody tr:hover:nth-child(even),
        .data-table tbody tr:hover:nth-child(odd) { background-color: #fff8f9 !important; }

        /* DELETED ROW */
        .data-table tbody tr.row-deleted { background-color: #fef2f2 !important; opacity: 0.75; }
        .data-table tbody tr.row-deleted:hover { background-color: #fee2e2 !important; }

        .data-table tbody td { padding: 14px 16px; vertical-align: middle; white-space: nowrap; font-size: 0.85rem; }
        .data-table tbody td:first-child { padding-left: 24px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; }
        .data-table tbody td:last-child { padding-right: 24px; text-align: center; }

        /* AVATAR */
        .avatar-default {
            width: 36px; height: 36px; border-radius: 10px; background: var(--s-pink);
            display: flex; align-items: center; justify-content: center; color: var(--p-pink);
            font-size: 1.1rem; flex-shrink: 0;
        }
        .avatar-default img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }

        /* NAMA */
        .nama-karyawan { font-weight: 700; color: var(--text-dark); font-size: 0.88rem; }
        .username-karyawan { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }

        /* ROLE BADGE */
        .badge-role {
            font-size: 0.7rem; font-weight: 700; padding: 4px 12px; border-radius: 50px;
            display: inline-block; white-space: nowrap;
        }
        .badge-role-admin { background: #eff6ff; color: #2563eb; }
        .badge-role-foto { background: var(--s-pink); color: var(--p-pink); }
        .badge-role-owner { background: #f5f3ff; color: #8b5cf6; }

        /* STATUS DOT */
        .status-dot { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; }
        .status-dot .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .status-dot .dot.aktif { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); }
        .status-dot .dot.nonaktif { background: #cbd5e1; }
        .status-dot .text-aktif { color: #10b981; }
        .status-dot .text-nonaktif { color: var(--text-muted); }

        /* PERSIS SEPERTI GAMBAR: BULAT DENGAN OUTLINE PINK MUDA */
        .btn-aksi {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: 1px solid var(--light-pink); /* Outline pink muda */
            background: #ffffff;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            margin: 0 4px;
        }
        .btn-aksi:hover {
            transform: translateY(-2px);
            background-color: var(--s-pink);
            box-shadow: 0 4px 10px rgba(216, 63, 103, 0.12);
        }
        
        /* Ikon berwarna merah muda */
        .btn-aksi-edit { color: var(--p-pink); }
        .btn-aksi-toggle { color: var(--p-pink); }
        .btn-aksi-delete { color: var(--p-pink); }
        
        /* Warna alternatif untuk aksi pemulihan & hapus permanen data arsip */
        .btn-aksi-restore { color: #059669; border-color: #d1fae5; }
        .btn-aksi-restore:hover { background-color: #ecfdf5; }
        .btn-aksi-hard { color: #dc2626; border-color: #fee2e2; }
        .btn-aksi-hard:hover { background-color: #fef2f2; }

        /* PAGINATION */
        .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 16px 24px; }
        .pagination-info { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 4px; align-items: center; }
        .page-link-pag {
            display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
            padding: 0 12px; border-radius: 10px; background: transparent; border: 1px solid transparent;
            color: var(--text-muted); font-weight: 600; font-size: 0.85rem; text-decoration: none; transition: var(--transition);
        }
        .page-link-pag:hover { background: var(--s-pink); color: var(--p-pink); }
        .page-link-pag.active-pag { background: var(--p-pink); color: #ffffff; }
        .page-link-pag.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        /* MODAL */
        .modal-content { border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.1); }
        .modal-header { border-bottom: 1px solid var(--border-color); padding: 20px 24px; }
        .modal-body { padding: 20px 24px; }
        .modal-footer { border-top: 1px solid var(--border-color); padding: 16px 24px; }

        @media (max-width: 1200px) { .main-content { padding: 20px; } }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Beranda Pemilik</span></a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="../../Role/Owner/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-up small" style="transform: rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="submenu-link active"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                    <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="submenu" id="submenuLaporan">
                    <ul class="list-unstyled">
                        <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                        <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                        <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                        <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
        </ul>
    </div>
    <div><button onclick="confirmLogout(event)" class="btn btn-logout"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom">
        <a href="../../Role/Owner/index.php">Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
        <span class="current">Kelola Karyawan</span>
    </div>

    <!-- HEADER -->
    <div class="dashboard-header">
        <div>
            <h4 class="fw-bold mb-1">Kelola Karyawan</h4>
            <p class="text-muted small mb-0" style="font-size: 0.82rem;">Kelola staf SpotLight Studio</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="live-clock"><i class="bi bi-clock"></i><span id="liveClock">--:--</span></div>
            <a href="tambah.php" class="btn btn-reg-header"><i class="bi bi-plus-lg"></i> Tambah</a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-scroll-wrapper mb-3">
        <div class="stat-card-mini" style="border-left: 3px solid var(--p-pink);">
            <div class="stat-icon-mini" style="background: var(--s-pink); color: var(--p-pink);"><i class="bi bi-people"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['total'] ?></h5><small>Total Staf</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid #2563eb;">
            <div class="stat-icon-mini" style="background: #eff6ff; color: #2563eb;"><i class="bi bi-person-workspace"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['admin'] ?></h5><small>Admin</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid var(--p-pink);">
            <div class="stat-icon-mini" style="background: var(--s-pink); color: var(--p-pink);"><i class="bi bi-camera"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['fotografer'] ?></h5><small>Fotografer</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid #8b5cf6;">
            <div class="stat-icon-mini" style="background: #f5f3ff; color: #8b5cf6;"><i class="bi bi-person-check"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['owner'] ?></h5><small>Owner</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid #10b981;">
            <div class="stat-icon-mini" style="background: #ecfdf5; color: #10b981;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['aktif'] ?></h5><small>Aktif</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid #d97706;">
            <div class="stat-icon-mini" style="background: #fffbeb; color: #d97706;"><i class="bi bi-x-circle"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['nonaktif'] ?></h5><small>Nonaktif</small></div>
        </div>
        <div class="stat-card-mini" style="border-left: 3px solid #dc2626;">
            <div class="stat-icon-mini" style="background: #fef2f2; color: #dc2626;"><i class="bi bi-trash"></i></div>
            <div class="stat-info-mini"><h5><?= $stats['dihapus'] ?></h5><small>Dihapus</small></div>
        </div>
    </div>

    <!-- TAB FILTER -->
    <div class="tab-filter-wrapper">
        <a href="?tab=aktif<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'aktif' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Aktif <span class="badge-count"><?= $stats['total'] ?></span>
        </a>
        <a href="?tab=dihapus<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'dihapus' ? 'active' : '' ?>">
            <i class="bi bi-trash"></i> Dihapus <span class="badge-count"><?= $stats['dihapus'] ?></span>
        </a>
        <a href="?tab=semua<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'semua' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> Semua <span class="badge-count"><?= $stats['total'] + $stats['dihapus'] ?></span>
        </a>
    </div>

    <!-- SEARCH -->
    <div class="search-filter-wrapper">
        <form method="GET" class="d-flex gap-2 align-items-center" style="flex: 1;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="cari" class="form-control search-input" placeholder="Cari nama, NIK, atau email..." value="<?= htmlspecialchars(@$_GET['cari']) ?>">
            <button type="submit" class="btn btn-reg-header" style="padding: 10px 18px !important; font-size: 0.8rem !important;"><i class="bi bi-search"></i></button>
        </form>
        <button type="button" class="btn-filter-toggle" data-bs-toggle="modal" data-bs-target="#modalFilter"><i class="bi bi-funnel"></i> Filter</button>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 120px;">NIK</th>
                    <th>Nama</th>
                    <th style="width: 80px;">Umur</th>
                    <th style="width: 140px;">Telepon</th>
                    <th style="width: 100px;">Kelamin</th>
                    <th style="width: 100px;">Peran</th>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 180px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php
// =====================================================
// QUERY DATA
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

$conditions = array("ID_Karyawan != ?");
$params = array($id_owner);

if ($tab == 'aktif') { $conditions[] = "Is_Deleted = 0"; }
elseif ($tab == 'dihapus') { $conditions[] = "Is_Deleted = 1"; }

if (!empty($cari)) {
    $conditions[] = "(Nama_Karyawan LIKE ? OR NIK LIKE ? OR Email_Karyawan LIKE ? OR Username_Karyawan LIKE ?)";
    $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
}
if ($status_filter !== "") { $conditions[] = "Status = ?"; $params[] = (int)$status_filter; }
if (!empty($role_filter)) { $conditions[] = "Role_Karyawan = ?"; $params[] = $role_filter; }

$order_clause = "Nama_Karyawan ASC";
if ($sort == "nama_desc") { $order_clause = "Nama_Karyawan DESC"; }
elseif ($sort == "umur_muda") { $order_clause = "Tanggal_Lahir DESC"; }
elseif ($sort == "umur_tua") { $order_clause = "Tanggal_Lahir ASC"; }
elseif ($sort == "baru") { $order_clause = "Created_Date DESC"; }
elseif ($sort == "lama") { $order_clause = "Created_Date ASC"; }

$sql_count = "SELECT COUNT(*) AS total FROM Karyawan WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $sql_count, $params);
$total_halaman = ceil($total_records / $limit);

// FIX ANTI-CRASH: Menyematkan parameter integer $offset dan $limit langsung di dalam query SQL Server
$sql_list = "SELECT * FROM Karyawan WHERE " . implode(" AND ", $conditions) . " ORDER BY " . $order_clause . " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
$query_list = safe_sqlsrv_query($conn, $sql_list, $params);

$no = $offset + 1;
if ($query_list && sqlsrv_has_rows($query_list)):
    while($row = sqlsrv_fetch_array($query_list, SQLSRV_FETCH_ASSOC)):
        $umur = hitungUmur($row['Tanggal_Lahir'] ?? null);
        $isDeleted = ($row['Is_Deleted'] == 1);
        $isOwnerSelf = ($row['ID_Karyawan'] == $id_owner);
        $roleClass = "badge-role-" . ($row['Role_Karyawan'] == 'Fotografer' ? 'foto' : strtolower($row['Role_Karyawan']));
?>
                <tr class="<?= $isDeleted ? 'row-deleted' : '' ?>">
                    <td><?= $no++ ?></td>
                    <td style="font-weight: 600; color: var(--text-muted);"><?= htmlspecialchars($row['NIK']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-3" style="cursor: pointer;" onclick="bukaDetailRow(this)"
                                data-nama="<?= htmlspecialchars($row['Nama_Karyawan']) ?>"
                                data-nik="<?= htmlspecialchars($row['NIK']) ?>"
                                data-role="<?= htmlspecialchars($row['Role_Karyawan']) ?>"
                                data-umur="<?= $umur ?>"
                                data-hp="<?= htmlspecialchars($row['No_Hp']) ?>"
                                data-jk="<?= htmlspecialchars($row['Jenis_Kelamin']) ?>"
                                data-email="<?= htmlspecialchars($row['Email_Karyawan']) ?>"
                                data-alamat="<?= htmlspecialchars($row['Alamat'] ?? '-') ?>"
                                data-status="<?= $row['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?>">
                            <div class="avatar-default">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <div class="nama-karyawan"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div>
                                <div class="username-karyawan">@<?= htmlspecialchars($row['Username_Karyawan']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= $umur ?></td>
                    <td><?= htmlspecialchars($row['No_Hp']) ?></td>
                    <td><?= htmlspecialchars($row['Jenis_Kelamin']) ?></td>
                    <td><span class="badge-role <?= $roleClass ?>"><?= htmlspecialchars($row['Role_Karyawan']) ?></span></td>
                    <td>
                        <?php if (!$isDeleted): ?>
                        <div class="status-dot">
                            <span class="dot <?= $row['Status'] == 1 ? 'aktif' : 'nonaktif' ?>"></span>
                            <span class="<?= $row['Status'] == 1 ? 'text-aktif' : 'text-nonaktif' ?>"><?= $row['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?></span>
                        </div>
                        <?php else: ?>
                        <span style="font-size: 0.75rem; color: #dc2626; font-weight: 700;">DIHAPUS</span>
                        <?php endif; ?>
                    </td>
                                        <td style="text-align: center;">
                        <!-- ========================================== -->
                        <!-- AKSI: 3 TOMBOL BULAT (PERSIS GAMBAR REFERENSI PAKET FOTO) -->
                        <!-- ========================================== -->
                        <?php if (!$isDeleted): ?>
                            <!-- === DATA AKTIF === -->

                            <!-- TOMBOL 1: EDIT -->
                            <a href="edit.php?id=<?= $row['ID_Karyawan'] ?>" class="btn-aksi btn-aksi-edit" title="Edit Karyawan">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <!-- TOMBOL 2: SOFT DELETE (ARSIPKAN) -->
                            <button class="btn-aksi btn-aksi-toggle" onclick="confirmSoftDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Arsipkan Karyawan">
                                <i class="bi bi-toggle2-on" style="font-size: 1.25rem;"></i>
                            </button>

                            <!-- TOMBOL 3: HARD DELETE -->
                            <?php if (!$isOwnerSelf): ?>
                                <button class="btn-aksi btn-aksi-delete" onclick="confirmHardDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-aksi btn-aksi-delete" style="opacity: 0.35; cursor: not-allowed;" disabled title="Tidak bisa hapus akun sendiri"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- === DATA TERARSIP === -->

                            <!-- TOMBOL 1: EDIT (DISABLED) -->
                            <button class="btn-aksi btn-aksi-edit" style="opacity: 0.35; cursor: not-allowed;" disabled title="Pulihkan terlebih dahulu untuk mengedit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- TOMBOL 2: RESTORE (PULIHKAN) -->
                            <button class="btn-aksi btn-aksi-toggle" onclick="confirmRestore(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Pulihkan Karyawan">
                                <i class="bi bi-toggle2-off" style="font-size: 1.25rem; opacity: 0.6;"></i>
                            </button>

                            <!-- TOMBOL 3: HARD DELETE -->
                            <?php if (!$isOwnerSelf): ?>
                                <button class="btn-aksi btn-aksi-hard" onclick="confirmHardDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-aksi btn-aksi-hard" style="opacity: 0.35; cursor: not-allowed;" disabled title="Tidak bisa hapus akun sendiri"><i class="bi bi-trash-fill"></i></button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <!-- ========================================== -->
                    </td>
                </tr>
<?php endwhile; else: ?>
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 2rem; color: #e2e8f0;"></i>
                        <p class="text-muted mt-2" style="font-size: 0.85rem;">Tidak ada data karyawan</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_halaman > 1 || $total_records > 0): ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data</div>
        <nav class="pagination-nav">
            <?php if ($halaman > 1): ?>
                <a class="page-link-pag" href="?tab=<?= $tab ?>&halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>

            <?php for ($i = 1; $i <= $total_halaman; $i++): ?>
                <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="?tab=<?= $tab ?>&halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($halaman < $total_halaman): ?>
                <a class="page-link-pag" href="?tab=<?= $tab ?>&halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL FILTER -->
<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="fw-bold mb-0"><i class="bi bi-funnel text-danger me-2"></i>Filter Data</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="GET" id="formModalFilter">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 0.8rem;">Urutkan</label>
                        <select name="sort" class="form-select" style="font-size: 0.85rem;">
                            <option value="nama_asc" <?= ($sort == 'nama_asc') ? 'selected' : '' ?>>Nama A-Z</option>
                            <option value="nama_desc" <?= ($sort == 'nama_desc') ? 'selected' : '' ?>>Nama Z-A</option>
                            <option value="umur_muda" <?= ($sort == 'umur_muda') ? 'selected' : '' ?>>Umur Termuda</option>
                            <option value="umur_tua" <?= ($sort == 'umur_tua') ? 'selected' : '' ?>>Umur Tertua</option>
                            <option value="baru" <?= ($sort == 'baru') ? 'selected' : '' ?>>Terbaru</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 0.8rem;">Status Akun</label>
                        <select name="status" class="form-select" style="font-size: 0.85rem;">
                            <option value="" <?= ($status_filter == '') ? 'selected' : '' ?>>Semua</option>
                            <option value="1" <?= ($status_filter == '1') ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= ($status_filter == '0') ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 0.8rem;">Peran</label>
                        <select name="role" class="form-select" style="font-size: 0.85rem;">
                            <option value="" <?= ($role_filter == '') ? 'selected' : '' ?>>Semua</option>
                            <option value="Admin" <?= ($role_filter == 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Fotografer" <?= ($role_filter == 'Fotografer') ? 'selected' : '' ?>>Fotografer</option>
                            <option value="Owner" <?= ($role_filter == 'Owner') ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-reg-header w-100" style="font-size: 0.85rem !important;">Terapkan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="fw-bold mb-0"><i class="bi bi-person-vcard text-danger me-2"></i>Detail Karyawan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar-default" style="width: 60px; height: 60px; margin: 0 auto; font-size: 1.5rem;"><i class="bi bi-person-fill"></i></div>
                    <h5 class="fw-bold mt-2 mb-1" id="d_nama" style="font-size: 1.1rem;"></h5>
                    <span class="badge-role" id="d_role"></span>
                </div>
                <div style="background: #fafbfc; border-radius: 12px; padding: 16px;">
                    <div class="row g-2">
                        <div class="col-6"><small class="text-muted" style="font-size: 0.7rem;">NIK</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_nik"></div></div>
                        <div class="col-6"><small class="text-muted" style="font-size: 0.7rem;">Umur</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_umur"></div></div>
                        <div class="col-12"><hr style="margin: 8px 0; opacity: 0.1;"></div>
                        <div class="col-6"><small class="text-muted" style="font-size: 0.7rem;">Kelamin</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_jk"></div></div>
                        <div class="col-6"><small class="text-muted" style="font-size: 0.7rem;">Telepon</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_hp"></div></div>
                        <div class="col-12"><hr style="margin: 8px 0; opacity: 0.1;"></div>
                        <div class="col-12"><small class="text-muted" style="font-size: 0.7rem;">Email</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_email"></div></div>
                        <div class="col-12"><small class="text-muted" style="font-size: 0.7rem;">Alamat</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_alamat"></div></div>
                        <div class="col-12"><hr style="margin: 8px 0; opacity: 0.1;"></div>
                        <div class="col-12"><small class="text-muted" style="font-size: 0.7rem;">Status</small><div class="fw-bold" style="font-size: 0.85rem;" id="d_status"></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-reg-header w-100" data-bs-dismiss="modal" style="font-size: 0.85rem !important;">Tutup</button></div>
        </div>
    </div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    // LIVE CLOCK
    function updateClock() { document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}); }
    setInterval(updateClock, 1000); updateClock();

    // SUBMENU
    document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.dataset.target);
            const isShown = target.classList.contains('show');
            document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
            if (!isShown) target.classList.add('show');
        });
    });

    // DETAIL MODAL (Dapat dipicu saat klik foto/nama baris)
    function bukaDetailRow(element) {
        const d = element.dataset;
        document.getElementById('d_nama').textContent = d.nama;
        document.getElementById('d_nik').textContent = d.nik;
        document.getElementById('d_role').textContent = d.role;
        document.getElementById('d_role').className = 'badge-role badge-role-' + (d.role === 'Fotografer' ? 'foto' : d.role.toLowerCase());
        document.getElementById('d_umur').textContent = d.umur;
        document.getElementById('d_jk').textContent = d.jk;
        document.getElementById('d_hp').textContent = d.hp;
        document.getElementById('d_email').textContent = d.email;
        document.getElementById('d_alamat').textContent = d.alamat;
        document.getElementById('d_status').textContent = d.status;
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // SWEETALERT FUNCTIONS & ACTIONS
    function confirmSoftDelete(id, nama) {
        Swal.fire({ title: 'Arsipkan Karyawan?', text: '"' + nama + '" akan diarsipkan dan dinonaktifkan. Bisa dipulihkan kapan saja.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Arsipkan', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=soft_delete&id=' + id; });
    }
    
    function confirmRestore(id, nama) {
        Swal.fire({ title: 'Pulihkan Data?', text: '"' + nama + '" akan dikembalikan ke daftar aktif dengan status diaktifkan kembali.', icon: 'info', showCancelButton: true, confirmButtonColor: '#059669', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Pulihkan', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=restore&id=' + id; });
    }
    
    function confirmHardDelete(id, nama) {
        Swal.fire({ title: 'Hapus Permanen?', text: '"' + nama + '" akan dihapus PERMANEN! Tindakan ini tidak bisa dibatalkan.', icon: 'error', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Hapus Permanen', cancelButtonText: 'Batal', input: 'text', inputPlaceholder: 'Ketik "HAPUS" untuk konfirmasi', inputValidator: v => v !== 'HAPUS' ? 'Ketik "HAPUS" untuk mengonfirmasi!' : null }).then(r => { if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=hard_delete&id=' + id; });
    }
    
    function confirmLogout(e) { e.preventDefault(); Swal.fire({ title: 'Keluar?', text: 'Yakin ingin keluar dari sistem?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = '../../logout.php'; }); }
    
    function confirmLandingPage(e) { e.preventDefault(); Swal.fire({ title: 'Kembali?', text: 'Ke halaman utama publik?', icon: 'info', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = '../../index.php'; }); }
</script>

<?php if(isset($_GET['status_sukses'])): ?>
<script>
    const s = "<?= $_GET['status_sukses'] ?>";
    let msg = "", t = "success", title = "Berhasil!";
    if (s === 'tambah') msg = "Karyawan baru berhasil didaftarkan!";
    else if (s === 'edit') msg = "Data berhasil diperbarui!";
    else if (s === 'soft_delete') { msg = "Karyawan berhasil diarsipkan."; title = "Diarsipkan!"; }
    else if (s === 'restore') { msg = "Karyawan berhasil dipulihkan!"; title = "Dipulihkan!"; }
    else if (s === 'hard_delete') { msg = "Karyawan dihapus permanen."; title = "Dihapus!"; t = "warning"; }
    else if (s === 'error_relasi') { msg = "Tidak bisa hapus! Masih ada data transaksi."; title = "Gagal!"; t = "error"; }
    else if (s === 'error_self') { msg = "Anda tidak bisa mengarsipkan atau menghapus akun sendiri!"; title = "Ditolak!"; t = "error"; }
    else if (s === 'error_general') { msg = "Terjadi kesalahan. Silakan coba lagi."; title = "Error!"; t = "error"; }
    Swal.fire({ icon: t, title: title, text: msg, confirmButtonColor: '#d83f67' });
</script>
<?php endif; ?>
</body>
</html>