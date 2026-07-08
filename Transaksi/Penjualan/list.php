<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin untuk Sidebar
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$email_admin = $d_admin['email_karyawan'] ?? 'admin@spotlight.com';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
    ? "../../assets/img/pelanggan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// PAGINATION & FILTER
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$tanggal_dari = isset($_GET['tanggal_dari']) ? trim($_GET['tanggal_dari']) : "";
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? trim($_GET['tanggal_sampai']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "terbaru";

// =====================================================
// QUERY STATISTIK
// =====================================================
$q_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN Status_Penjualan = 0 AND Status = 1 THEN 1 ELSE 0 END) as proses,
    SUM(CASE WHEN Status_Penjualan = 1 AND Status = 1 THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) as terhapus
FROM Penjualan WHERE 1=1";
$stmt_stats = sqlsrv_query($conn, $q_stats);
$stats = ['total' => 0, 'proses' => 0, 'selesai' => 0, 'terhapus' => 0];
if ($stmt_stats !== false) {
    $stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC) ?: $stats;
}

// Stok menipis alert
$q_stok_alert = sqlsrv_query($conn, 
    "SELECT TOP 5 b.Nama_Barang, b.Stok_Barang, b.Stok_Minimum 
     FROM Barang_Cetak b 
     WHERE b.Stok_Barang <= b.Stok_Minimum AND b.Is_Deleted = 0 AND b.Status = 1
     ORDER BY b.Stok_Barang ASC");
$stok_alert = [];
if ($q_stok_alert !== false) {
    while ($row = sqlsrv_fetch_array($q_stok_alert, SQLSRV_FETCH_ASSOC)) {
        $stok_alert[] = $row;
    }
}

// =====================================================
// QUERY LIST DATA DENGAN FILTER
// =====================================================
$conditions = array("p.Status = 1");
$params = array();

if (!empty($cari)) {
    $conditions[] = "(pl.Nama_Pelanggan LIKE ? OR o.ID_Order LIKE ? OR b.Nama_Barang LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($status_filter !== "" && $status_filter !== "terhapus") {
    $conditions[] = "p.Status_Penjualan = ?";
    $params[] = (int)$status_filter;
}
if ($status_filter === "terhapus") {
    $conditions = array("p.Status = 0");
    $params = array();
}
if (!empty($tanggal_dari)) {
    $conditions[] = "CAST(p.Tanggal_Penjualan AS DATE) >= ?";
    $params[] = $tanggal_dari;
}
if (!empty($tanggal_sampai)) {
    $conditions[] = "CAST(p.Tanggal_Penjualan AS DATE) <= ?";
    $params[] = $tanggal_sampai;
}

$order_clause = "p.Tanggal_Penjualan DESC";
if ($sort == "terlama") { $order_clause = "p.Tanggal_Penjualan ASC"; }
elseif ($sort == "total_tertinggi") { $order_clause = "p.Total_Penjualan DESC"; }
elseif ($sort == "total_terendah") { $order_clause = "p.Total_Penjualan ASC"; }

// Hitung total untuk pagination
$sql_count = "SELECT COUNT(*) AS total FROM Penjualan p 
              LEFT JOIN [Order] o ON p.ID_Order = o.ID_Order 
              LEFT JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan 
              LEFT JOIN Detail_Penjualan_Barang_Cetak d ON p.ID_Penjualan = d.ID_Penjualan 
              LEFT JOIN Barang_Cetak b ON d.ID_Barang = b.ID_Barang 
              WHERE " . implode(" AND ", $conditions);
$query_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0;
$total_halaman = 0;
if ($query_count !== false) {
    $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
    $total_records = $row_count['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// Ambil data dengan JOIN lengkap
$sql_list = "SELECT p.ID_Penjualan, p.ID_Order, p.Tanggal_Penjualan, p.Total_Penjualan, 
                    p.Status_Penjualan, p.Status, p.ID_Karyawan_Admin,
                    pl.Nama_Pelanggan, pl.ID_Pelanggan,
                    COUNT(d.ID_Detail) as jumlah_barang,
                    SUM(d.Jumlah) as total_qty
             FROM Penjualan p 
             LEFT JOIN [Order] o ON p.ID_Order = o.ID_Order 
             LEFT JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan 
             LEFT JOIN Detail_Penjualan_Barang_Cetak d ON p.ID_Penjualan = d.ID_Penjualan 
             WHERE " . implode(" AND ", $conditions) . " 
             GROUP BY p.ID_Penjualan, p.ID_Order, p.Tanggal_Penjualan, p.Total_Penjualan, 
                      p.Status_Penjualan, p.Status, p.ID_Karyawan_Admin,
                      pl.Nama_Pelanggan, pl.ID_Pelanggan
             ORDER BY " . $order_clause . " 
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params_list = $params;
$params_list[] = $offset;
$params_list[] = $limit;

$query = sqlsrv_query($conn, $sql_list, $params_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan Barang Cetak - SpotLight Studio</title>

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
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem; color: var(--p-pink);
            text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block;
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
            background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px);
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
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px;
        }
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* STAT CARDS */
        .stats-scroll-wrapper {
            width: 100%; overflow-x: auto; overflow-y: hidden;
            padding-bottom: 10px; margin-bottom: 20px;
            scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 16px; min-width: max-content; }
        .stat-card-item { min-width: 220px; max-width: 280px; flex: 0 0 auto; }
        .card-3d {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d); padding: 20px;
            height: 100%; position: relative; overflow: hidden;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14); border-color: var(--p-pink);
        }
        .stat-card { display: flex; align-items: center; gap: 14px; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0;
        }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fff7ed, #fed7aa); color: #ea580c; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
        .stat-icon-cyan { background: linear-gradient(135deg, #ecfeff, #cffafe); color: #0891b2; }
        .stat-icon-blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }

        /* STOCK ALERT BANNER */
        .stock-alert-banner {
            background: linear-gradient(135deg, #fef2f2, #fff7ed);
            border: 2px dashed #fca5a5; border-radius: 20px;
            padding: 16px 24px; margin-bottom: 25px;
            display: flex; align-items: center; gap: 16px;
        }
        .stock-alert-icon {
            width: 44px; height: 44px; border-radius: 14px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.3rem; flex-shrink: 0;
        }
        .stock-alert-text { flex: 1; }
        .stock-alert-title { font-size: 0.95rem; font-weight: 800; color: #991b1b; }
        .stock-alert-sub { font-size: 0.8rem; color: #b91c1c; font-weight: 600; }

        /* TAB FILTER */
        .tab-filter-container {
            display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap;
        }
        .tab-filter {
            padding: 10px 24px; border-radius: 50px;
            background: #ffffff; color: #4a5568;
            font-size: 0.85rem; font-weight: 700;
            text-decoration: none; transition: var(--transition-3d);
            border: 2px solid #f1f5f9; cursor: pointer;
        }
        .tab-filter:hover, .tab-filter.active {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff; border-color: var(--p-pink);
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.25);
        }
        .tab-filter .badge-count {
            background: rgba(255,255,255,0.3); color: inherit;
            padding: 2px 10px; border-radius: 50px;
            font-size: 0.75rem; margin-left: 6px;
        }

        /* SEARCH & FILTER */
        .search-filter-bar {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 25px; flex-wrap: wrap;
        }
        .search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 300px; }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; }
        .search-input-main {
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem;
            color: #1e293b; transition: var(--transition-3d); background: #ffffff;
        }
        .search-input-main:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
        .btn-filter-modal {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 12px 24px; font-weight: 700; font-size: 0.9rem;
            display: inline-flex; align-items: center; cursor: pointer;
            transition: var(--transition-3d); white-space: nowrap;
        }
        .btn-filter-modal:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(213, 61, 102, 0.3); }
        .btn-search-icon {
            background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 12px 16px; color: #94a3b8; cursor: pointer; transition: var(--transition-3d);
            display: flex; align-items: center; justify-content: center;
        }
        .btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }

        /* TABEL */
        .table-scroll-wrapper {
            width: 100%; overflow-x: auto; overflow-y: hidden;
            border-radius: 20px; scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .data-table {
            width: 100%; min-width: 950px; border-collapse: separate; border-spacing: 0;
        }
        .data-table thead th {
            background: #ffffff; padding: 16px 20px;
            font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #94a3b8; white-space: nowrap;
            border: none; border-bottom: 2px solid #f1f5f9; text-align: left;
        }
        .data-table thead th:first-child { padding-left: 24px; }
        .data-table thead th:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody td {
            padding: 16px 20px; border: none;
            border-bottom: 1px solid #f1f5f9; vertical-align: middle; white-space: nowrap;
        }
        .data-table tbody td:first-child { padding-left: 24px; }
        .data-table tbody td:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr:nth-child(even) { background-color: #FFF8F0; }
        .data-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .data-table tbody tr:hover { background-color: #FFEDD5 !important; transform: scale(1.002); }

        .td-order-id { font-weight: 800; font-size: 0.9rem; color: var(--p-pink); }
        .td-customer { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .td-tanggal { font-size: 0.8rem; color: #718096; font-weight: 600; }
        .td-total { font-weight: 800; color: var(--p-pink); font-size: 1rem; }
        .td-barang { font-size: 0.8rem; color: #718096; }

        .badge-status-penjualan {
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-proses { background: #fffbeb; color: #d97706; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-terhapus { background: #f3f4f6; color: #6b7280; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-proses .badge-dot { background: #d97706; }
        .badge-selesai .badge-dot { background: #059669; }
        .badge-terhapus .badge-dot { background: #6b7280; }

        .btn-action-circle {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            transition: var(--transition-3d); border: 1.5px solid #eef2f6;
            background: #ffffff; font-size: 0.85rem; text-decoration: none;
            margin: 0 2px; cursor: pointer;
        }
        .btn-action-detail { color: #D53D66; border-color: #FFE4E9; }
        .btn-action-detail:hover { background: #D53D66; color: #ffffff; transform: translateY(-2px); }
        .btn-action-status { color: #059669; border-color: #d1fae5; }
        .btn-action-status:hover { background: #059669; color: #ffffff; transform: translateY(-2px); }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); }
        .btn-action-restore { color: #7c3aed; border-color: #ede9fe; }
        .btn-action-restore:hover { background: #7c3aed; color: #ffffff; transform: translateY(-2px); }

        /* PAGINATION */
        .pagination-wrapper {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 30px; padding: 20px 24px;
            background: #ffffff; border-radius: 20px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.04);
        }
        .pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; }
        .page-link-pag {
            display: flex; align-items: center; justify-content: center;
            min-width: 40px; height: 40px; padding: 0 14px;
            border-radius: 12px; background: #ffffff;
            border: 2px solid #FFF5F7; color: #4a5568;
            font-weight: 700; font-size: 0.9rem; text-decoration: none;
            transition: var(--transition-3d);
        }
        .page-link-pag:hover {
            background: var(--light-pink); border-color: var(--p-pink); color: var(--p-pink);
            transform: translateY(-2px);
        }
        .page-link-pag.active-pag {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; border-color: var(--p-pink) !important;
            box-shadow: 0 4px 12px rgba(213, 61, 102, 0.3);
        }
        .page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

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
                SpotLight.<br><span>Panel Administrator</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../../Master/Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../../Master/Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../../Master/Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../../Master/Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../../Master/Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../../Master/Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../../Master/Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Booking Customer</a></li>
                            <li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
                            <li><a href="list.php" class="submenu-link active"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
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
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Transaksi Penjualan Barang Cetak</h3>
                <p class="text-muted small mb-0">Kelola dan verifikasi penjualan barang cetak dari pelanggan.</p>
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

        <!-- STOCK ALERT BANNER -->
        <?php if (!empty($stok_alert)): ?>
        <div class="stock-alert-banner fade-in-up">
            <div class="stock-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stock-alert-text">
                <div class="stock-alert-title">Peringatan Stok Menipis!</div>
                <div class="stock-alert-sub">
                    <?php foreach ($stok_alert as $idx => $s): ?>
                        <?= htmlspecialchars($s['Nama_Barang']) ?> (Stok: <?= $s['Stok_Barang'] ?>, Min: <?= $s['Stok_Minimum'] ?>)
                        <?= $idx < count($stok_alert) - 1 ? ' | ' : '' ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="../../Master/Barang Cetak/list.php" class="btn btn-sm" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; border-radius: 10px; font-weight: 700; padding: 8px 16px; text-decoration: none;">
                <i class="bi bi-box-seam me-1"></i>Kelola Stok
            </a>
        </div>
        <?php endif; ?>

        <!-- STATISTIK CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-bag-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Penjualan</div>
                                <div class="stat-val"><?= $stats['total'] ?? 0 ?> Order</div>
                                <div class="stat-subtitle">Semua transaksi</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Dalam Proses</div>
                                <div class="stat-val"><?= $stats['proses'] ?? 0 ?> Order</div>
                                <div class="stat-subtitle">Belum selesai</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Selesai</div>
                                <div class="stat-val"><?= $stats['selesai'] ?? 0 ?> Order</div>
                                <div class="stat-subtitle">Sudah diambil</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-red"><i class="bi bi-trash-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Terhapus</div>
                                <div class="stat-val"><?= $stats['terhapus'] ?? 0 ?> Order</div>
                                <div class="stat-subtitle">Soft deleted</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB FILTER -->
        <div class="tab-filter-container">
            <a href="list.php" class="tab-filter <?= $status_filter === '' ? 'active' : '' ?>">
                Semua <span class="badge-count"><?= $stats['total'] ?? 0 ?></span>
            </a>
            <a href="list.php?status=0" class="tab-filter <?= $status_filter === '0' ? 'active' : '' ?>">
                Proses <span class="badge-count"><?= $stats['proses'] ?? 0 ?></span>
            </a>
            <a href="list.php?status=1" class="tab-filter <?= $status_filter === '1' ? 'active' : '' ?>">
                Selesai <span class="badge-count"><?= $stats['selesai'] ?? 0 ?></span>
            </a>
            <a href="list.php?status=terhapus" class="tab-filter <?= $status_filter === 'terhapus' ? 'active' : '' ?>">
                Terhapus <span class="badge-count"><?= $stats['terhapus'] ?? 0 ?></span>
            </a>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="tanggal_dari" id="hiddenTanggalDari" value="<?= htmlspecialchars($tanggal_dari) ?>">
                <input type="hidden" name="tanggal_sampai" id="hiddenTanggalSampai" value="<?= htmlspecialchars($tanggal_sampai) ?>">
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="cari" class="search-input-main" placeholder="Cari nama customer, no. order, atau nama barang..." value="<?= htmlspecialchars($cari) ?>">
                </div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()">
                    <i class="bi bi-funnel-fill me-2"></i>Filter
                    <i class="bi bi-chevron-down ms-2"></i>
                </button>
                <button type="submit" class="btn-search-icon" title="Cari">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>

        <!-- TABEL DATA -->
        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Tanggal Penjualan</th>
                            <th>Barang</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = $offset + 1;
                        if ($query && sqlsrv_has_rows($query)):
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                $tanggal = '';
                                if (isset($row['Tanggal_Penjualan']) && $row['Tanggal_Penjualan'] instanceof DateTime) {
                                    $tanggal = $row['Tanggal_Penjualan']->format('d M Y H:i');
                                }

                                // Status badge
                                if ($row['Status'] == 0) {
                                    $badge_class = "badge-terhapus";
                                    $text_status = "Terhapus";
                                } elseif ($row['Status_Penjualan'] == 0) {
                                    $badge_class = "badge-proses";
                                    $text_status = "Proses";
                                } else {
                                    $badge_class = "badge-selesai";
                                    $text_status = "Selesai";
                                }
                        ?>
                            <tr class="fade-in-up">
                                <td class="td-order-id">#<?= $row['ID_Order'] ?? '-' ?></td>
                                <td>
                                    <div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan'] ?? 'Unknown') ?></div>
                                    <div class="td-tanggal">ID: <?= $row['ID_Penjualan'] ?></div>
                                </td>
                                <td class="td-tanggal"><?= $tanggal ?></td>
                                <td class="td-barang">
                                    <i class="bi bi-box-seam me-1 text-danger"></i>
                                    <?= $row['jumlah_barang'] ?? 0 ?> jenis (<?= $row['total_qty'] ?? 0 ?> qty)
                                </td>
                                <td class="td-total">Rp <?= number_format($row['Total_Penjualan'] ?? 0, 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge-status-penjualan <?= $badge_class ?>">
                                        <span class="badge-dot"></span>
                                        <?= $text_status ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= $row['ID_Penjualan'] ?>" class="btn-action-circle btn-action-detail" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($row['Status'] == 1 && $row['Status_Penjualan'] == 0): ?>
                                    <button class="btn-action-circle btn-action-status" onclick="updateStatus(<?= $row['ID_Penjualan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan'] ?? 'Unknown') ?>')" title="Tandai Selesai">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($row['Status'] == 1): ?>
                                    <button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $row['ID_Penjualan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan'] ?? 'Unknown') ?>')" title="Hapus (Soft Delete)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-action-circle btn-action-restore" onclick="restoreData(<?= $row['ID_Penjualan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan'] ?? 'Unknown') ?>')" title="Pulihkan Data">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i>
                                    <p class="fw-bold">Tidak ada data penjualan yang sesuai.</p>
                                    <p class="text-muted" style="font-size: 0.8rem;">Coba ubah filter atau lakukan pencarian ulang.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> penjualan
                </div>
                <nav class="pagination-nav">
                    <?php 
                    $base_url = "list.php?status=" . urlencode($status_filter) . "&sort=" . urlencode($sort) . "&tanggal_dari=" . urlencode($tanggal_dari) . "&tanggal_sampai=" . urlencode($tanggal_sampai) . "&cari=" . urlencode($cari);
                    if ($halaman > 1): 
                    ?>
                        <a class="page-link-pag" href="<?= $base_url ?>&halaman=<?= $halaman - 1 ?>" title="Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php 
                    $start_page = max(1, $halaman - 2);
                    $end_page = min($total_halaman, $halaman + 2);

                    if ($start_page > 1) {
                        echo '<a class="page-link-pag" href="' . $base_url . '&halaman=1">1</a>';
                        if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="<?= $base_url ?>&halaman=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; 

                    if ($end_page < $total_halaman) {
                        if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
                        echo '<a class="page-link-pag" href="' . $base_url . '&halaman=' . $total_halaman . '">' . $total_halaman . '</a>';
                    }
                    ?>

                    <?php if ($halaman < $total_halaman): ?>
                        <a class="page-link-pag" href="<?= $base_url ?>&halaman=<?= $halaman + 1 ?>" title="Selanjutnya">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> penjualan
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- FILTER MODAL POPUP -->
    <div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
                <div class="modal-header" style="border: none; padding: 24px 24px 16px; background: #ffffff;">
                    <h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 0 24px 20px; background: #ffffff;">
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">URUT BERDASARKAN</label>
                        <select class="form-select" id="modalSort" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Tanggal Terbaru</option>
                            <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Tanggal Terlama</option>
                            <option value="total_tertinggi" <?= $sort == 'total_tertinggi' ? 'selected' : '' ?>>Total Tertinggi</option>
                            <option value="total_terendah" <?= $sort == 'total_terendah' ? 'selected' : '' ?>>Total Terendah</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">TANGGAL DARI</label>
                        <input type="date" class="form-select" id="modalTanggalDari" value="<?= htmlspecialchars($tanggal_dari) ?>" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">TANGGAL SAMPAI</label>
                        <input type="date" class="form-select" id="modalTanggalSampai" value="<?= htmlspecialchars($tanggal_sampai) ?>" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 0 24px 24px; background: #ffffff; display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1; background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="resetFilter()">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                    </button>
                    <button type="button" class="btn btn-danger" style="flex: 1; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="applyFilter()">
                        <i class="bi bi-check-lg me-2"></i>Terapkan
                    </button>
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

        // Filter Modal
        var filterModal;
        function bukaModalFilter() {
            filterModal = new bootstrap.Modal(document.getElementById('modalFilterData'));
            filterModal.show();
        }
        function applyFilter() {
            document.getElementById('hiddenSort').value = document.getElementById('modalSort').value;
            document.getElementById('hiddenTanggalDari').value = document.getElementById('modalTanggalDari').value;
            document.getElementById('hiddenTanggalSampai').value = document.getElementById('modalTanggalSampai').value;
            document.getElementById('mainSearchForm').submit();
        }
        function resetFilter() {
            document.getElementById('modalSort').value = 'terbaru';
            document.getElementById('modalTanggalDari').value = '';
            document.getElementById('modalTanggalSampai').value = '';
            document.getElementById('hiddenSort').value = 'terbaru';
            document.getElementById('hiddenTanggalDari').value = '';
            document.getElementById('hiddenTanggalSampai').value = '';
            document.getElementById('mainSearchForm').submit();
        }

        // Update Status
        function updateStatus(id, nama) {
            Swal.fire({
                title: 'Tandai Selesai?',
                text: 'Penjualan untuk pelanggan "' + nama + '" akan ditandai sebagai SELESAI.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Selesai',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action.php?aksi=update_status&id=' + id;
                }
            });
        }

        // Soft Delete
        function softDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Data?',
                text: 'Penjualan untuk pelanggan "' + nama + '" akan dihapus (soft delete).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action.php?aksi=soft_delete&id=' + id;
                }
            });
        }

        // Restore
        function restoreData(id, nama) {
            Swal.fire({
                title: 'Pulihkan Data?',
                text: 'Penjualan untuk pelanggan "' + nama + '" akan dipulihkan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pulihkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action.php?aksi=restore&id=' + id;
                }
            });
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

    <!-- Notifikasi -->
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = "";
        let t_icon = "success";
        let t_title = "Berhasil!";

        if ("<?= $_GET['status_sukses'] ?>" == 'update_status') { 
            msg = "Status penjualan berhasil diperbarui menjadi SELESAI!"; 
            t_title = "Status Diperbarui"; 
        }
        else if ("<?= $_GET['status_sukses'] ?>" == 'soft_delete') { 
            msg = "Data penjualan berhasil dihapus (soft delete)!"; 
            t_title = "Data Dihapus"; 
        }
        else if ("<?= $_GET['status_sukses'] ?>" == 'restore') { 
            msg = "Data penjualan berhasil dipulihkan!"; 
            t_title = "Data Dipulihkan"; 
        }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { 
            msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; 
            t_icon = "error"; t_title = "Gagal!"; 
        }

        Swal.fire({
            icon: t_icon,
            title: t_title,
            text: msg,
            confirmButtonColor: '#D53D66'
        });
    </script>
    <?php endif; ?>
</body>
</html>