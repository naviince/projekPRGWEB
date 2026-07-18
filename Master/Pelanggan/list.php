<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin untuk Sidebar
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$email_admin = $d_admin['email_karyawan'] ?? 'admin@spotlight.com';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
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
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : "aktif";

// =====================================================
// QUERY STATISTIK (4 CARDS)
// =====================================================
$q_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN Status = 1 AND Is_Deleted = 0 THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN Status = 0 AND Is_Deleted = 0 THEN 1 ELSE 0 END) as nonaktif,
    SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END) as dihapus
FROM Pelanggan";
$stmt_stats = sqlsrv_query($conn, $q_stats);
$stats = ['total' => 0, 'aktif' => 0, 'nonaktif' => 0, 'dihapus' => 0];
if ($stmt_stats !== false) {
    $stats_row = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    if ($stats_row) $stats = $stats_row;
}

// =====================================================
// QUERY LIST DATA DENGAN FILTER & TAB
// =====================================================
$conditions = array();
$params = array();

if ($tab === 'aktif') {
    $conditions[] = "Is_Deleted = 0";
} elseif ($tab === 'dihapus') {
    $conditions[] = "Is_Deleted = 1";
}

if (!empty($cari)) {
    $conditions[] = "(Nama_Pelanggan LIKE ? OR Email_Pelanggan LIKE ? OR No_Hp LIKE ? OR Username_Pelanggan LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($status_filter !== "" && $tab !== 'dihapus') {
    $conditions[] = "Status = ?";
    $params[] = (int)$status_filter;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$order_clause = "Nama_Pelanggan ASC";
if ($sort == "nama_desc") { $order_clause = "Nama_Pelanggan DESC"; }
elseif ($sort == "baru") { $order_clause = "Created_Date DESC"; }
elseif ($sort == "lama") { $order_clause = "Created_Date ASC"; }

// Mengambil Total Records
$sql_count = "SELECT COUNT(*) AS total FROM Pelanggan " . $where_clause;
$query_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0;
$total_halaman = 0;
if ($query_count !== false) {
    $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
    $total_records = $row_count['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// FIX ANTI-CRASH: Menyematkan parameter integer $offset dan $limit langsung di dalam query SQL Server
$sql_list = "SELECT *, 
    DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - 
    CASE WHEN MONTH(Tanggal_Lahir) > MONTH(GETDATE()) 
         OR (MONTH(Tanggal_Lahir) = MONTH(GETDATE()) AND DAY(Tanggal_Lahir) > DAY(GETDATE())) 
    THEN 1 ELSE 0 END as Umur 
FROM Pelanggan " . $where_clause . " ORDER BY " . $order_clause . " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

$query = sqlsrv_query($conn, $sql_list, $params);

function safe_has_rows($q) {
    if ($q === false) return false;
    try { return sqlsrv_has_rows($q); } catch (Exception $e) { return false; }
}

function format_date_sqlsrv($date_obj) {
    if ($date_obj instanceof DateTime) return $date_obj->format('d M Y');
    if (is_string($date_obj)) return date('d M Y', strtotime($date_obj));
    return '-';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Pelanggan - SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --zebra-cream: #FFF8F0; --zebra-orange: #FFEDD5;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); overflow-x: hidden; margin: 0; }

        /* SIDEBAR */
        .sidebar { 
            width: 260px; height: 100vh; background: var(--sidebar-bg); 
            position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8); 
            display: flex; flex-direction: column; justify-content: space-between; 
            padding: 30px 20px; z-index: 1000;
            transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
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
            background: var(--light-pink); color: var(--p-pink); transform: translateX(4px); 
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { 
            display: flex; align-items: center; padding: 8px 18px; color: #718096; 
            font-weight: 600; font-size: 0.85rem; text-decoration: none; 
            border-radius: 10px; transition: 0.3s; 
        }
        .submenu-link:hover, .submenu-link.active { 
            color: var(--p-pink); background: rgba(213,61,102,0.03); padding-left: 22px; 
        }
        .btn-logout { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #fff; border: none; width: 100%; padding: 12px; 
            border-radius: 12px; font-weight: 800; font-size: 0.85rem; 
            transition: var(--transition-3d); 
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }

        /* MOBILE HEADER */
        .mobile-header {
            display: none;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: #ffffff;
            border-bottom: 1px solid rgba(255, 228, 233, 0.8);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .mobile-brand {
            font-weight: 800; font-size: 1.2rem; color: var(--p-pink);
            text-decoration: none; letter-spacing: -0.5px;
        }
        .hamburger-btn {
            width: 40px; height: 40px; border-radius: 10px;
            border: none; background: var(--s-pink); color: var(--p-pink);
            font-size: 1.4rem; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s;
        }
        .hamburger-btn:hover { background: var(--light-pink); }
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            z-index: 999; backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show { display: block; }

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 25px; flex-wrap: wrap; gap: 12px;
        }
        .profile-header-btn { 
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden; 
            border: 2px solid #fff; cursor: pointer; transition: var(--transition-3d); background: #fff; 
            flex-shrink: 0;
        }
        .profile-header-btn:hover { 
            transform: scale(1.08) translateY(-2px); 
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink); 
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
        .breadcrumb-custom { 
            font-size: 0.8rem; color: #94a3b8; font-weight: 600; 
            margin-bottom: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .breadcrumb-custom a { color: var(--p-pink); text-decoration: none; }
        .breadcrumb-custom a:hover { text-decoration: underline; }
        .breadcrumb-custom .active { color: #64748b; }

        /* STATS */
        .stats-scroll-wrapper { 
            width: 100%; overflow-x: auto; overflow-y: hidden; 
            padding-bottom: 10px; margin-bottom: 20px; 
            scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; 
        }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 16px; min-width: max-content; }
        .stat-card-item { min-width: 200px; max-width: 260px; flex: 0 0 auto; }
        /* card-3d: base visual only, NO hover lift/shadow because stat cards are NOT clickable */
        .card-3d { 
            background: #fff; border-radius: 22px; 
            border: 1px solid rgba(255,228,233,0.8); 
            box-shadow: 0 8px 24px rgba(213,61,102,0.03); 
            padding: 20px; 
            height: 100%; position: relative; overflow: hidden; 
        }
        .stat-card { display: flex; align-items: center; gap: 14px; }
        .stat-icon { 
            width: 48px; height: 48px; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.4rem; flex-shrink: 0; 
        }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-gray { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #64748b; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }

        /* TABS */
        .tab-filter-wrapper { 
            display: flex; gap: 8px; margin-bottom: 20px; 
            overflow-x: auto; scrollbar-width: none; padding-bottom: 4px;
        }
        .tab-filter-wrapper::-webkit-scrollbar { display: none; }
        .tab-filter-btn { 
            padding: 10px 20px; border-radius: 12px; border: 2px solid #e2e8f0; 
            background: #fff; color: #64748b; font-weight: 700; font-size: 0.85rem; 
            cursor: pointer; transition: var(--transition-3d); 
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px; 
            white-space: nowrap; flex-shrink: 0;
        }
        .tab-filter-btn:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .tab-filter-btn.active { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #fff; border-color: var(--p-pink); 
            box-shadow: 0 4px 15px rgba(213,61,102,0.2); 
        }
        .tab-filter-btn .tab-count { 
            background: rgba(255,255,255,0.2); padding: 2px 8px; 
            border-radius: 20px; font-size: 0.75rem; font-weight: 800; 
        }
        .tab-filter-btn.active .tab-count { background: rgba(255,255,255,0.3); color: #fff; }
        .tab-filter-btn:not(.active) .tab-count { background: #f1f5f9; color: #64748b; }

        /* SEARCH & FILTER BAR */
        .search-filter-bar { 
            display: flex; align-items: center; gap: 12px; 
            margin-bottom: 25px; flex-wrap: wrap; 
        }
        .search-form-flex { 
            display: flex; align-items: center; gap: 10px; 
            flex: 1; min-width: 280px; 
        }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-icon { 
            position: absolute; left: 16px; top: 50%; 
            transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; 
        }
        .search-input-main { 
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; 
            padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem; 
            color: #1e293b; transition: var(--transition-3d); background: #fff; 
        }
        .search-input-main:focus { 
            outline: none; border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); 
        }
        /* FILTER BUTTON: outline style (sync with Properti page) */
        .btn-filter-modal { 
            background: var(--s-pink);
            color: var(--p-pink);
            border: 1.5px solid var(--light-pink);
            border-radius: 14px;
            padding: 12px 24px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: var(--transition-3d);
            white-space: nowrap;
        }
        .btn-filter-modal:hover {
            background: var(--light-pink);
            border-color: var(--p-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(213, 61, 102, 0.2);
        }
        .btn-search-icon { 
            background: #fff; border: 2px solid #e2e8f0; border-radius: 14px; 
            padding: 12px 16px; color: #94a3b8; cursor: pointer; 
            transition: var(--transition-3d); display: flex; align-items: center; justify-content: center; 
        }
        .btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .info-bar { 
            background: linear-gradient(135deg, #ecfdf5, #d1fae5); 
            border-radius: 14px; padding: 14px 20px; 
            display: inline-flex; align-items: center; gap: 10px; 
            font-size: 0.85rem; font-weight: 600; color: #059669; 
            border: 1px solid #a7f3d0; white-space: nowrap;
        }

        /* FILTER MODAL */
        .filter-modal-content { border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
        .filter-modal-header { border: none; padding: 24px 24px 16px; background: #fff; }
        .filter-modal-header h5 { font-size: 1.1rem; color: var(--text-dark); }
        .filter-modal-body { padding: 0 24px 20px; background: #fff; }
        .filter-group { margin-bottom: 20px; }
        .filter-group:last-child { margin-bottom: 0; }
        .filter-label { 
            display: block; font-size: 0.75rem; font-weight: 800; 
            color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; 
        }
        .filter-select { 
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; 
            padding: 14px 18px; font-weight: 600; font-size: 0.9rem; 
            color: #1e293b; background: #fff; cursor: pointer; 
            transition: var(--transition-3d); appearance: none; -webkit-appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); 
            background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; 
        }
        .filter-select:focus { 
            outline: none; border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(213,61,102,0.08); 
        }
        .filter-modal-footer { 
            border: none; padding: 0 24px 24px; background: #fff; display: flex; gap: 12px; 
        }
        .btn-reset-filter { 
            flex: 1; background: #f1f5f9; color: #475569; border: none; 
            border-radius: 14px; padding: 14px 20px; font-weight: 700; font-size: 0.9rem; 
            transition: var(--transition-3d); cursor: pointer; 
        }
        .btn-reset-filter:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-apply-filter { 
            flex: 1; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #fff; border: none; border-radius: 14px; 
            padding: 14px 20px; font-weight: 700; font-size: 0.9rem; 
            transition: var(--transition-3d); cursor: pointer; 
        }
        .btn-apply-filter:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(213,61,102,0.3); }

        /* TABLE */
        .table-scroll-wrapper { 
            width: 100%; overflow-x: auto; overflow-y: hidden; 
            border-radius: 20px; scrollbar-width: thin; 
            scrollbar-color: var(--p-pink) #f1f5f9; 
        }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .data-table { width: 100%; min-width: 1100px; border-collapse: separate; border-spacing: 0; }
        .data-table thead th { 
            background: #fff; padding: 16px 20px; font-size: 0.75rem; 
            font-weight: 800; text-transform: uppercase; letter-spacing: 1px; 
            color: #94a3b8; white-space: nowrap; border: none; 
            border-bottom: 2px solid #f1f5f9; text-align: left; 
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
        .data-table tbody tr:nth-child(even) { background: var(--zebra-cream); }
        .data-table tbody tr:nth-child(odd) { background: #fff; }
        .data-table tbody tr:hover { 
            background: var(--zebra-orange) !important; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.04); z-index: 10; position: relative; 
        }
        .td-no { font-weight: 700; font-size: 0.9rem; color: #94a3b8; width: 50px; }
        .td-nama { width: 250px; min-width: 250px; }
        .td-nama-content { display: flex; align-items: center; gap: 12px; }
        .td-nama-text { 
            font-weight: 700; font-size: 0.9rem; color: var(--text-dark); 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; 
        }
        .td-email { font-size: 0.85rem; color: #64748b; font-weight: 600; width: 220px; }
        .td-hp { font-size: 0.85rem; color: #64748b; font-weight: 600; width: 150px; }
        .td-kelamin { font-size: 0.85rem; color: #64748b; font-weight: 600; width: 100px; }
        .td-umur { font-size: 0.85rem; color: #64748b; font-weight: 600; width: 80px; }
        .td-status { width: 120px; }
        .td-aksi { width: 140px; text-align: center; }
        .profile-table-avatar { 
            width: 40px; height: 40px; border-radius: 50%; overflow: hidden; 
            border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); flex-shrink: 0; 
        }
        .profile-table-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .badge-status { 
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px; 
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; 
        }
        .badge-aktif { background: #ecfdf5; color: #059669; }
        .badge-nonaktif { background: #fef2f2; color: #dc2626; }
        .badge-dihapus { background: #f1f5f9; color: #64748b; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-aktif .badge-dot { background: #059669; }
        .badge-nonaktif .badge-dot { background: #dc2626; }
        .badge-dihapus .badge-dot { background: #94a3b8; }
        .btn-action-circle { 
            width: 34px; height: 34px; border-radius: 50%; 
            display: inline-flex; align-items: center; justify-content: center; 
            transition: var(--transition-3d); border: 1.5px solid #eef2f6; 
            background: #fff; font-size: 0.85rem; text-decoration: none; margin: 0 2px; cursor: pointer; 
        }
        .btn-action-detail { color: #D53D66; border-color: #FFE4E9; }
        .btn-action-detail:hover { background: #D53D66; color: #fff; transform: translateY(-2px); }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #fff; transform: translateY(-2px); }
        .btn-action-restore { color: #059669; border-color: #d1fae5; }
        .btn-action-restore:hover { background: #059669; color: #fff; transform: translateY(-2px); }
        .row-deleted td { opacity: 0.7; }
        .row-deleted .td-nama-text { text-decoration: line-through; color: #94a3b8; }
        .row-deleted .td-email { color: #94a3b8; }
        .row-deleted .td-hp { color: #94a3b8; }

        /* PAGINATION */
        .pagination-wrapper { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-top: 30px; padding: 20px 24px; 
            background: #fff; border-radius: 20px; 
            border: 1px solid rgba(255,228,233,0.8); 
            box-shadow: 0 4px 15px rgba(213,61,102,0.04); 
            flex-wrap: wrap; gap: 12px;
        }
        .pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .page-link-pag { 
            display: flex; align-items: center; justify-content: center; 
            min-width: 40px; height: 40px; padding: 0 14px; 
            border-radius: 12px; background: #fff; border: 2px solid #FFF5F7; 
            color: #4a5568; font-weight: 700; font-size: 0.9rem; 
            text-decoration: none; transition: var(--transition-3d); 
        }
        .page-link-pag:hover { 
            background: var(--light-pink); border-color: var(--p-pink); 
            color: var(--p-pink); transform: translateY(-2px); 
        }
        .page-link-pag.active-pag { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; 
            color: #fff !important; border-color: var(--p-pink) !important; 
            box-shadow: 0 4px 12px rgba(213,61,102,0.3); 
        }
        .page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        /* ================= RESPONSIVE ================= */

        /* Tablet & below */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .mobile-header { display: flex; }
            .main-content { margin-left: 0; padding: 24px; }
            .dashboard-header { margin-top: 0; }
            .dashboard-header h3 { font-size: 1.3rem; }
            .search-filter-bar { flex-direction: column; align-items: stretch; }
            .search-form-flex { min-width: 100%; }
            .info-bar { width: 100%; justify-content: center; }
            .stat-card-item { min-width: 180px; }
            .stat-val { font-size: 1.3rem; }
        }

        /* Small tablet */
        @media (max-width: 768px) {
            .main-content { padding: 20px 16px; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .breadcrumb-custom { font-size: 0.75rem; gap: 6px; }
            .card-3d { border-radius: 18px; padding: 16px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.2rem; }
            .stat-val { font-size: 1.2rem; }
            .stat-title { font-size: 0.65rem; }
            .stat-subtitle { font-size: 0.62rem; }
            .tab-filter-btn { padding: 8px 16px; font-size: 0.8rem; }
            .search-input-main { 
                padding: 12px 14px 12px 40px; 
                font-size: 16px; /* Prevents iOS zoom */
            }
            .btn-filter-modal { padding: 12px 18px; font-size: 0.85rem; }
            .pagination-wrapper { 
                flex-direction: column; align-items: center; 
                text-align: center; padding: 16px; 
            }
            .pagination-info { font-size: 0.8rem; }
            .page-link-pag { min-width: 36px; height: 36px; font-size: 0.85rem; }
            .data-table tbody td { padding: 14px 16px; }
            .td-nama-text { max-width: 140px; }
        }

        /* Mobile phone */
        @media (max-width: 576px) {
            .mobile-header { padding: 12px 16px; }
            .mobile-brand { font-size: 1.1rem; }
            .main-content { padding: 16px 12px; }
            .dashboard-header h3 { font-size: 1.15rem; }
            .dashboard-header p { font-size: 0.8rem; }
            .profile-header-btn { width: 40px; height: 40px; }
            .stats-scroll-wrapper { padding-bottom: 8px; }
            .stat-card-item { min-width: 160px; }
            .card-3d { border-radius: 16px; padding: 14px; }
            .stat-icon { width: 36px; height: 36px; font-size: 1.1rem; }
            .stat-val { font-size: 1.1rem; }
            .tab-filter-wrapper { gap: 6px; }
            .tab-filter-btn { padding: 8px 14px; font-size: 0.75rem; gap: 6px; }
            .tab-filter-btn .tab-count { padding: 1px 6px; font-size: 0.7rem; }
            .search-form-flex { gap: 8px; }
            .search-input-main { 
                border-radius: 12px; 
                font-size: 16px; 
            }
            .btn-filter-modal { border-radius: 12px; padding: 10px 14px; }
            .btn-search-icon { border-radius: 12px; padding: 10px 12px; }
            .info-bar { 
                font-size: 0.8rem; padding: 12px; 
                text-align: center; flex-wrap: wrap; 
            }
            .data-table tbody td { padding: 12px 14px; }
            .td-nama-content { gap: 8px; }
            .profile-table-avatar { width: 32px; height: 32px; }
            .td-nama-text { font-size: 0.85rem; max-width: 120px; }
            .td-email, .td-hp, .td-kelamin, .td-umur { font-size: 0.8rem; }
            .badge-status { font-size: 0.65rem; padding: 4px 10px; }
            .btn-action-circle { width: 30px; height: 30px; font-size: 0.8rem; margin: 0 1px; }
            .pagination-wrapper { margin-top: 20px; }
            .page-link-pag { min-width: 32px; height: 32px; padding: 0 10px; font-size: 0.8rem; border-radius: 10px; }
            .filter-modal-content { border-radius: 20px; margin: 12px; }
            .filter-modal-header { padding: 20px 20px 12px; }
            .filter-modal-body { padding: 0 20px 16px; }
            .filter-modal-footer { padding: 0 20px 20px; flex-direction: column; }
            .btn-reset-filter, .btn-apply-filter { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- MOBILE HEADER -->
    <div class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <a href="../../index.php" class="mobile-brand">SpotLight.</a>
        <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" style="width:36px;height:36px;border-width:1px;">
            <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
        </div>
    </div>

    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i></a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi"><span><i class="bi bi-cart-fill me-2"></i> Transaksi</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <div class="main-content">
        <div class="breadcrumb-custom">
            <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right mx-2" style="font-size: 0.7rem;"></i>
            <a href="#">Data Master</a>
            <i class="bi bi-chevron-right mx-2" style="font-size: 0.7rem;"></i>
            <span class="active">Kelola Pelanggan</span>
        </div>
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Kelola Master Pelanggan</h3>
                <p class="text-muted small mb-0">Pantau dan kelola profil pelanggan terdaftar SpotLight Studio.</p>
            </div>
            <div class="d-flex align-items-center gap-3 d-none d-md-flex">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-people-fill"></i></div><div class="stat-content"><div class="stat-title">Total Pelanggan</div><div class="stat-val"><?= $stats['total'] ?? 0 ?> Orang</div><div class="stat-subtitle">Terdaftar di sistem</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-person-check-fill"></i></div><div class="stat-content"><div class="stat-title">Pelanggan Sesi</div><div class="stat-val"><?= $stats['aktif'] ?? 0 ?> Orang</div><div class="stat-subtitle">Dapat melakukan booking</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-person-x-fill"></i></div><div class="stat-content"><div class="stat-title">Pelanggan Nonaktif</div><div class="stat-val"><?= $stats['nonaktif'] ?? 0 ?> Orang</div><div class="stat-subtitle">Tidak dapat booking</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-gray"><i class="bi bi-archive-fill"></i></div><div class="stat-content"><div class="stat-title">Data Dihapus</div><div class="stat-val"><?= $stats['dihapus'] ?? 0 ?> Orang</div><div class="stat-subtitle">Diarsipkan (soft delete)</div></div></div></div></div>
            </div>
        </div>

        <div class="tab-filter-wrapper">
            <a href="?tab=aktif&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>&status=<?= $status_filter ?>" class="tab-filter-btn <?= $tab == 'aktif' ? 'active' : '' ?>"><i class="bi bi-person-check-fill"></i> Data Aktif <span class="tab-count"><?= ($stats['aktif'] ?? 0) + ($stats['nonaktif'] ?? 0) ?></span></a>
            <a href="?tab=dihapus&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>" class="tab-filter-btn <?= $tab == 'dihapus' ? 'active' : '' ?>"><i class="bi bi-archive-fill"></i> Sudah Dihapus <span class="tab-count"><?= $stats['dihapus'] ?? 0 ?></span></a>
            <a href="?tab=semua&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>&status=<?= $status_filter ?>" class="tab-filter-btn <?= $tab == 'semua' ? 'active' : '' ?>"><i class="bi bi-list-ul"></i> Semua Data <span class="tab-count"><?= $stats['total'] ?? 0 ?></span></a>
        </div>

        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="tab" id="hiddenTab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari nama, email, username, atau nomor HP..." value="<?= htmlspecialchars($cari) ?>"></div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()"><i class="bi bi-funnel-fill me-2"></i>Filter <i class="bi bi-chevron-down ms-2"></i></button>
                <button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
            </form>
            <div class="info-bar"><i class="bi bi-info-circle-fill"></i> Pelanggan mendaftar sendiri via halaman registrasi</div>
        </div>

        <div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content filter-modal-content">
                    <div class="modal-header filter-modal-header"><h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body filter-modal-body">
                        <div class="filter-group"><label class="filter-label">URUT BERDASARKAN</label><select class="filter-select" id="modalSort"><option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option><option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Z - A</option><option value="baru" <?= $sort == 'baru' ? 'selected' : '' ?>>Terbaru</option><option value="lama" <?= $sort == 'lama' ? 'selected' : '' ?>>Terlama</option></select></div>
                        <?php if ($tab !== 'dihapus'): ?>
                        <div class="filter-group"><label class="filter-label">STATUS AKUN</label><select class="filter-select" id="modalStatus"><option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option><option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Nonaktif</option></select></div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer filter-modal-footer"><button type="button" class="btn btn-reset-filter" onclick="resetFilter()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</button><button type="button" class="btn btn-apply-filter" onclick="applyFilter()"><i class="bi bi-check-lg me-2"></i>Terapkan</button></div>
                </div>
            </div>
        </div>

        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead><tr><th>No</th><th>Nama Pelanggan</th><th>Email</th><th>No. HP</th><th>Kelamin</th><th>Umur</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        <?php $no = $offset + 1; if ($query && safe_has_rows($query)): while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): $is_deleted = ($row['Is_Deleted'] ?? 0) == 1; $row_class = $is_deleted ? 'row-deleted' : ''; $foto_pelanggan = $row['Foto_Profil'] ?? 'default.jpg'; $foto_pelanggan_src = ($foto_pelanggan != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_pelanggan)) ? "../../assets/img/pelanggan/" . $foto_pelanggan : $default_svg_avatar; if ($is_deleted) { $badge_status = "badge-dihapus"; $text_status = "Dihapus"; } else { $badge_status = ($row['Status'] == 1) ? "badge-aktif" : "badge-nonaktif"; $text_status = ($row['Status'] == 1) ? "Aktif" : "Nonaktif"; } $umur = $row['Umur'] ?? '-'; ?>
                        <tr class="fade-in-up <?= $row_class ?>">
                            <td class="td-no"><?= $no++ ?></td>
                            <td class="td-nama"><div class="td-nama-content"><div class="profile-table-avatar"><img src="<?= $foto_pelanggan_src ?>" alt="Foto Profil"></div><span class="td-nama-text" title="<?= htmlspecialchars($row['Nama_Pelanggan']) ?>"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></span></div></td>
                            <td class="td-email"><?= htmlspecialchars($row['Email_Pelanggan']) ?></td>
                            <td class="td-hp"><?= htmlspecialchars($row['No_Hp']) ?></td>
                            <td class="td-kelamin"><?= htmlspecialchars($row['Jenis_Kelamin']) ?></td>
                            <td class="td-umur"><?= $umur ?> th</td>
                            <td class="td-status"><span class="badge-status <?= $badge_status ?>"><span class="badge-dot"></span><?= $text_status ?></span></td>
                            <td class="td-aksi">
                                <button class="btn-action-circle btn-action-detail" data-nama="<?= htmlspecialchars($row['Nama_Pelanggan']) ?>" data-email="<?= htmlspecialchars($row['Email_Pelanggan']) ?>" data-username="<?= htmlspecialchars($row['Username_Pelanggan']) ?>" data-hp="<?= htmlspecialchars($row['No_Hp']) ?>" data-jk="<?= htmlspecialchars($row['Jenis_Kelamin']) ?>" data-dob="<?= format_date_sqlsrv($row['Tanggal_Lahir']) ?>" data-umur="<?= $umur ?>" data-alamat="<?= htmlspecialchars($row['Alamat'] ?? '-') ?>" data-status="<?= $text_status ?>" data-foto="<?= $foto_pelanggan_src ?>" data-terdaftar="<?= format_date_sqlsrv($row['Created_Date']) ?>" onclick="bukaModalDetail(this)" title="Lihat Detail"><i class="bi bi-eye"></i></button>
                                <?php if (!$is_deleted): ?>
                                <button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $row['ID_Pelanggan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan']) ?>')" title="Arsipkan (Soft Delete)"><i class="bi bi-archive"></i></button>
                                <?php else: ?>
                                <button class="btn-action-circle btn-action-restore" onclick="restoreData(<?= $row['ID_Pelanggan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan']) ?>')" title="Pulihkan Data"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <button class="btn-action-circle btn-action-delete" onclick="hardDelete(<?= $row['ID_Pelanggan'] ?>, '<?= htmlspecialchars($row['Nama_Pelanggan']) ?>')" title="Hapus Permanen"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i><p class="fw-bold"><?php if ($tab == 'dihapus'): ?>Tidak ada data pelanggan yang diarsipkan.<?php elseif ($tab == 'semua'): ?>Tidak ada data pelanggan di sistem.<?php else: ?>Tidak ada data pelanggan yang sesuai dengan filter.<?php endif; ?></p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> pelanggan</div>
                <nav class="pagination-nav">
                    <?php if ($halaman > 1): ?><a class="page-link-pag" href="list.php?tab=<?= $tab ?>&halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&sort=<?= $sort ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
                    <?php $start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2); if ($start_page > 1) { echo '<a class="page-link-pag" href="list.php?tab=' . $tab . '&halaman=1&cari=' . urlencode($cari) . '&status=' . $status_filter . '&sort=' . $sort . '">1</a>'; if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; } for ($i = $start_page; $i <= $end_page; $i++): ?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?tab=<?= $tab ?>&halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&sort=<?= $sort ?>"><?= $i ?></a><?php endfor; if ($end_page < $total_halaman) { if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="list.php?tab=' . $tab . '&halaman=' . $total_halaman . '&cari=' . urlencode($cari) . '&status=' . $status_filter . '&sort=' . $sort . '">' . $total_halaman . '</a>'; } ?>
                    <?php if ($halaman < $total_halaman): ?><a class="page-link-pag" href="list.php?tab=<?= $tab ?>&halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&sort=<?= $sort ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> pelanggan</div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal fade" id="modalDetailPelanggan" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #fff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center"><h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Detail Data Pelanggan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box" style="width: 100px; height: 100px; border: 3px solid var(--s-pink); margin: 0 auto; border-radius: 50%; overflow: hidden;"><img id="d_foto" src="" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;"></div>
              <h5 class="fw-bold text-dark mt-3 mb-1" id="d_nama"></h5><span class="badge bg-danger px-3 py-1 text-white text-uppercase" id="d_status" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;"></span>
            </div>
            <div class="card-3d p-3 border-0 mb-3" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Username</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_username"></span></div>
                <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_email"></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nomor Telepon</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_hp"></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_jk"></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Umur</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_umur"></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Tanggal Lahir</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_dob"></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Terdaftar</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_terdaftar"></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Domisili</small><span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_alamat"></span></div>
              </div>
            </div>
            <button class="btn btn-reg-header shadow-sm py-3 mt-0 w-100" data-bs-dismiss="modal" style="border-radius: 14px !important; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; font-weight: 700;">Tutup Detail</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PROFILE BIODATA -->
    <div class="modal fade" id="modalBiodataAdmin" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #fff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center"><h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-badge-fill text-danger me-2"></i>Profil Anda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box" style="width: 100px; height: 100px; border: 3px solid var(--s-pink); margin: 0 auto; border-radius: 50%; overflow: hidden;"><img src="<?= $foto_admin_src ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;"></div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5><span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Admin</span>
            </div>
            <div class="card-3d p-3 border-0 mb-3" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-12"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email Karyawan</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_admin) ?></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Hak Akses Sistem</small><span class="fw-bold text-dark" style="font-size: 0.85rem;">Administrator (Admin)</span></div>
              </div>
            </div>
            <button class="btn btn-reg-header shadow-sm py-3 mt-0 w-100" data-bs-dismiss="modal" style="border-radius: 14px !important; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; font-weight: 700;">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

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
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });
        var filterModal;
        function bukaModalFilter() { filterModal = new bootstrap.Modal(document.getElementById('modalFilterData')); filterModal.show(); }
        function applyFilter() {
            const sortVal = document.getElementById('modalSort').value;
            const statusVal = document.getElementById('modalStatus') ? document.getElementById('modalStatus').value : '';
            document.getElementById('hiddenSort').value = sortVal;
            document.getElementById('hiddenStatus').value = statusVal;
            document.getElementById('mainSearchForm').submit();
        }
        function resetFilter() {
            document.getElementById('modalSort').value = 'nama_asc';
            if (document.getElementById('modalStatus')) document.getElementById('modalStatus').value = '';
            document.getElementById('hiddenSort').value = 'nama_asc';
            document.getElementById('hiddenStatus').value = '';
            document.getElementById('mainSearchForm').submit();
        }
        function bukaModalDetail(button) {
            const ds = button.dataset;
            document.getElementById('d_nama').innerText = ds.nama;
            document.getElementById('d_username').innerText = '@' + ds.username;
            document.getElementById('d_email').innerText = ds.email;
            document.getElementById('d_jk').innerText = ds.jk;
            document.getElementById('d_dob').innerText = ds.dob;
            document.getElementById('d_umur').innerText = ds.umur + ' tahun';
            document.getElementById('d_hp').innerText = ds.hp;
            document.getElementById('d_alamat').innerText = ds.alamat;
            document.getElementById('d_status').innerText = ds.status;
            document.getElementById('d_terdaftar').innerText = ds.terdaftar;
            document.getElementById('d_foto').src = ds.foto;
            var modalDetail = new bootstrap.Modal(document.getElementById('modalDetailPelanggan'));
            modalDetail.show();
        }
        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalBiodataAdmin'));
            modalBiodata.show();
        }
        function softDelete(id, nama) {
            Swal.fire({
                title: 'Arsipkan Pelanggan?',
                text: '"' + nama + '" akan diarsipkan (soft delete). Data masih tersimpan tapi tidak aktif.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Arsipkan',
                cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_pelanggan.php?aksi=soft_delete&id=' + id; } });
        }
        function restoreData(id, nama) {
            Swal.fire({
                title: 'Pulihkan Pelanggan?',
                text: '"' + nama + '" akan dikembalikan ke data aktif.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pulihkan',
                cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_pelanggan.php?aksi=restore&id=' + id; } });
        }
        function hardDelete(id, nama) {
            Swal.fire({
                title: 'HAPUS PERMANEN?',
                text: '"' + nama + '" akan dihapus PERMANEN dari database!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus Permanen',
                cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_pelanggan.php?aksi=hard_delete&id=' + id; } });
        }
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = '../../logout.php'; } });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = '../../index.php'; } });
        }
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
            const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            h = h < 10 ? '0'+h : h; m = m < 10 ? '0'+m : m; s = s < 10 ? '0'+s : s;
            document.getElementById('live-clock').innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + h + ':' + m + ':' + s + ' WIB';
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();
    </script>
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = ""; let t_icon = "success"; let t_title = "Berhasil!";
        if ("<?= $_GET['status_sukses'] ?>" == "soft_delete") { msg = "Pelanggan berhasil diarsipkan!"; t_title = "Diarsipkan"; }
        else if ("<?= $_GET['status_sukses'] ?>" == "restore") { msg = "Pelanggan berhasil dipulihkan!"; t_title = "Dipulihkan"; }
        else if ("<?= $_GET['status_sukses'] ?>" == "hard_delete") { msg = "Pelanggan berhasil dihapus permanen!"; t_title = "Hard Delete Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == "error_relasi") { msg = "Tidak bisa hapus! Pelanggan masih memiliki data transaksi order."; t_icon = "error"; t_title = "Gagal!"; }
        else if ("<?= $_GET['status_sukses'] ?>" == "error") { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }
        Swal.fire({ icon: t_icon, title: t_title, text: msg, confirmButtonColor: '#D53D66' });
    </script>
    <?php endif; ?>
</body>
</html>