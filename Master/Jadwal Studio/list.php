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
$tab_filter = isset($_GET['tab']) ? trim($_GET['tab']) : "semua";

// =====================================================
// QUERY STATISTIK
// =====================================================
$q_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN Status_Jadwal = 0 AND Is_Deleted = 0 AND Status = 1 THEN 1 ELSE 0 END) as tersedia,
    SUM(CASE WHEN Status_Jadwal = 1 AND Is_Deleted = 0 AND Status = 1 THEN 1 ELSE 0 END) as booked,
    SUM(CASE WHEN Is_Deleted = 1 OR Status = 0 THEN 1 ELSE 0 END) as terhapus
FROM Jadwal_Studio";
$stmt_stats = sqlsrv_query($conn, $q_stats);
$stats = ['total' => 0, 'tersedia' => 0, 'booked' => 0, 'terhapus' => 0];
if ($stmt_stats !== false) {
    $stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC) ?: $stats;
}

// =====================================================
// QUERY LIST DATA
// =====================================================
$conditions = array();
$params = array();

if ($tab_filter === 'tersedia') {
    $conditions[] = "j.Status_Jadwal = 0 AND j.Is_Deleted = 0 AND j.Status = 1";
} elseif ($tab_filter === 'booked') {
    $conditions[] = "j.Status_Jadwal = 1 AND j.Is_Deleted = 0 AND j.Status = 1";
} elseif ($tab_filter === 'terhapus') {
    $conditions[] = "(j.Is_Deleted = 1 OR j.Status = 0)";
} else {
    $conditions[] = "j.Is_Deleted = 0 AND j.Status = 1";
}

if (!empty($cari)) {
    $conditions[] = "(r.Nama_Ruangan LIKE ? OR p.Nama_Paket LIKE ? OR j.Keterangan LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

$where_clause = implode(" AND ", $conditions);

// Count
$sql_count = "SELECT COUNT(*) AS total FROM Jadwal_Studio j 
              INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan 
              INNER JOIN Paket_Foto p ON j.ID_Paket = p.ID_Paket 
              WHERE " . $where_clause;
$query_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0;
$total_halaman = 0;
if ($query_count !== false) {
    $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
    $total_records = $row_count['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// Select data
$sql_list = "SELECT j.ID_Jadwal, j.ID_Ruangan, j.ID_Paket, j.Tanggal_Jadwal, 
                    j.Jam_Mulai, j.Jam_Selesai, j.Keterangan, j.Status_Jadwal, 
                    j.Status, j.Is_Deleted, j.Created_By, j.Created_Date,
                    r.Nama_Ruangan, p.Nama_Paket, p.Durasi_Waktu,
                    DATEDIFF(MINUTE, j.Jam_Mulai, j.Jam_Selesai) as Durasi_Real
             FROM Jadwal_Studio j
             INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan 
             INNER JOIN Paket_Foto p ON j.ID_Paket = p.ID_Paket 
             WHERE " . $where_clause . "
             ORDER BY j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC
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
    <title>Master Jadwal Studio – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096; --sidebar-bg: #ffffff; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff; }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
        .stats-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 10px; margin-bottom: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 16px; min-width: max-content; }
        .stat-card-item { min-width: 220px; max-width: 280px; flex: 0 0 auto; }
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); padding: 20px; height: 100%; position: relative; overflow: hidden; }
        .card-3d:hover { transform: translateY(-8px) scale(1.01); box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14); border-color: var(--p-pink); }
        .stat-card { display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0; }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-gray { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #64748b; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }
        .tab-filter-bar { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border-radius: 14px; border: 2px solid #e2e8f0; background: #ffffff; color: #4a5568; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .tab-btn:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .tab-btn.active { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border-color: var(--p-pink); box-shadow: 0 4px 12px rgba(213, 61, 102, 0.2); }
        .tab-btn .tab-count { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 50px; font-size: 0.75rem; }
        .search-filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
        .search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 300px; }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; }
        .search-input-main { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
        .search-input-main:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
        .btn-search-icon { background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; color: #94a3b8; cursor: pointer; transition: var(--transition-3d); display: flex; align-items: center; justify-content: center; }
        .btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .btn-reg-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; color: #ffffff !important; border-radius: 14px !important; padding: 12px 28px !important; font-weight: 800 !important; border: none !important; box-shadow: 0 8px 20px rgba(213, 61, 102, 0.25) !important; transition: var(--transition-3d) !important; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-reg-header:hover { background: linear-gradient(135deg, #E85D84, var(--p-pink)) !important; transform: translateY(-4px) scale(1.03) !important; box-shadow: 0 12px 25px rgba(213, 61, 102, 0.4) !important; }
        .table-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; border-radius: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .data-table { width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0; }
        .data-table thead th { background: #ffffff; padding: 16px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; white-space: nowrap; border: none; border-bottom: 2px solid #f1f5f9; text-align: left; }
        .data-table thead th:first-child { padding-left: 24px; }
        .data-table thead th:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody td { padding: 16px 20px; border: none; border-bottom: 1px solid #f1f5f9; vertical-align: middle; white-space: nowrap; }
        .data-table tbody td:first-child { padding-left: 24px; }
        .data-table tbody td:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr:nth-child(even) { background-color: #FFF8F0; }
        .data-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .data-table tbody tr:hover { background-color: #FFEDD5 !important; transform: scale(1.002); }
        .data-table tbody tr.deleted-row { background-color: #fef2f2 !important; opacity: 0.7; }
        .td-ruangan { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .td-paket { font-size: 0.8rem; color: #718096; font-weight: 600; }
        .td-paket i { color: var(--p-pink); }
        .td-tanggal { font-weight: 600; font-size: 0.85rem; color: var(--text-dark); }
        .td-hari { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
        .td-waktu { font-weight: 800; font-size: 0.95rem; color: var(--p-pink); }
        .td-durasi { font-size: 0.8rem; color: #718096; font-weight: 600; }
        .td-keterangan { font-size: 0.8rem; color: #4a5568; max-width: 180px; white-space: normal; font-weight: 600; }
        .badge-status-jadwal { font-size: 0.72rem; font-weight: 700; padding: 6px 14px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-tersedia { background: #ecfdf5; color: #059669; }
        .badge-booked { background: #fef2f2; color: #dc2626; }
        .badge-maintenance { background: #fff7ed; color: #ea580c; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-tersedia .badge-dot { background: #059669; }
        .badge-booked .badge-dot { background: #dc2626; }
        .badge-maintenance .badge-dot { background: #ea580c; }
        .toggle-switch { position: relative; display: inline-block; width: 48px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 26px; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .toggle-switch input:checked + .toggle-slider { background: linear-gradient(135deg, #059669, #10b981); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); }
        .toggle-switch input:not(:checked) + .toggle-slider { background: linear-gradient(135deg, #cbd5e1, #94a3b8); }
        .toggle-switch input:focus + .toggle-slider { box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2); }
        .toggle-switch.deleted input + .toggle-slider { background: linear-gradient(135deg, #ef4444, #dc2626) !important; }
        .toggle-switch.deleted input + .toggle-slider:before { transform: translateX(22px); }
        .btn-action-circle { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: var(--transition-3d); border: 1.5px solid #eef2f6; background: #ffffff; font-size: 0.85rem; text-decoration: none; margin: 0 2px; cursor: pointer; }
        .btn-action-view { color: #D53D66; border-color: #FFE4E9; }
        .btn-action-view:hover { background: #D53D66; color: #ffffff; transform: translateY(-2px); }
        .btn-action-edit { color: var(--p-pink); border-color: #FFE4E9; }
        .btn-action-edit:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); }
        .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding: 20px 24px; background: #ffffff; border-radius: 20px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 4px 15px rgba(213, 61, 102, 0.04); }
        .pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; }
        .page-link-pag { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 14px; border-radius: 12px; background: #ffffff; border: 2px solid #FFF5F7; color: #4a5568; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: var(--transition-3d); }
        .page-link-pag:hover { background: var(--light-pink); border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .page-link-pag.active-pag { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; color: #ffffff !important; border-color: var(--p-pink) !important; box-shadow: 0 4px 12px rgba(213, 61, 102, 0.3); }
        .page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i></a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi"><span><i class="bi bi-cart-fill me-2"></i> Transaksi</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
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
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="dashboard-header" data-aos="fade-up">
            <div><h3 class="fw-bold mb-1">Master Jadwal Studio</h3><p class="text-muted small mb-0">Kelola slot waktu tersedia di setiap ruangan studio.</p></div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
            </div>
        </div>

        <!-- STATISTIK -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-week-fill"></i></div><div class="stat-content"><div class="stat-title">Total Jadwal</div><div class="stat-val"><?= $stats['total'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Aktif di sistem</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Tersedia</div><div class="stat-val"><?= $stats['tersedia'] ?? 0 ?> Slot</div><div class="stat-subtitle">Bisa dipesan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-lock-fill"></i></div><div class="stat-content"><div class="stat-title">Booked</div><div class="stat-val"><?= $stats['booked'] ?? 0 ?> Slot</div><div class="stat-subtitle">Sudah dipesan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-gray"><i class="bi bi-trash-fill"></i></div><div class="stat-content"><div class="stat-title">Terhapus</div><div class="stat-val"><?= $stats['terhapus'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Soft deleted</div></div></div></div></div>
            </div>
        </div>

        <!-- TAB FILTER -->
        <div class="tab-filter-bar">
            <a href="list.php?tab=semua" class="tab-btn <?= $tab_filter === 'semua' ? 'active' : '' ?>"><i class="bi bi-grid-fill"></i> Semua <span class="tab-count"><?= $stats['total'] ?? 0 ?></span></a>
            <a href="list.php?tab=tersedia" class="tab-btn <?= $tab_filter === 'tersedia' ? 'active' : '' ?>"><i class="bi bi-check-circle-fill"></i> Tersedia <span class="tab-count"><?= $stats['tersedia'] ?? 0 ?></span></a>
            <a href="list.php?tab=booked" class="tab-btn <?= $tab_filter === 'booked' ? 'active' : '' ?>"><i class="bi bi-lock-fill"></i> Booked <span class="tab-count"><?= $stats['booked'] ?? 0 ?></span></a>
            <a href="list.php?tab=terhapus" class="tab-btn <?= $tab_filter === 'terhapus' ? 'active' : '' ?>"><i class="bi bi-trash-fill"></i> Terhapus <span class="tab-count"><?= $stats['terhapus'] ?? 0 ?></span></a>
        </div>

        <!-- SEARCH -->
        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter) ?>">
                <div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari keterangan, ruangan, paket..." value="<?= htmlspecialchars($cari) ?>"></div>
                <button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
            </form>
            <a href="add.php" class="btn-reg-header text-decoration-none"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Jadwal</a>
        </div>

        <!-- TABEL -->
        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead><tr><th>No.</th><th>Ruangan & Paket</th><th>Tanggal</th><th>Waktu</th><th>Durasi</th><th>Keterangan</th><th>Status Jadwal</th><th class="text-center">Aktif</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        <?php
                        $no = $offset + 1;
                        if ($query && sqlsrv_has_rows($query)):
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                $tanggal_val = $row['Tanggal_Jadwal'];
                                if (is_object($tanggal_val) && method_exists($tanggal_val, 'format')) {
                                    $tanggal_str = $tanggal_val->format('Y-m-d');
                                    $hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                                    $nama_hari = $hari_indo[(int)$tanggal_val->format('w')];
                                    $tgl_format = $tanggal_val->format('d M Y');
                                } else {
                                    $tanggal_str = $tanggal_val;
                                    $nama_hari = '-';
                                    $tgl_format = $tanggal_val;
                                }
                                $jam_mulai_val = $row['Jam_Mulai'];
                                $jam_selesai_val = $row['Jam_Selesai'];
                                $jam_mulai_str = (is_object($jam_mulai_val) && method_exists($jam_mulai_val, 'format')) ? $jam_mulai_val->format('H:i') : (is_string($jam_mulai_val) ? substr($jam_mulai_val, 0, 5) : '-');
                                $jam_selesai_str = (is_object($jam_selesai_val) && method_exists($jam_selesai_val, 'format')) ? $jam_selesai_val->format('H:i') : (is_string($jam_selesai_val) ? substr($jam_selesai_val, 0, 5) : '-');
                                $status_jadwal = (int)$row['Status_Jadwal'];
                                if ($status_jadwal === 0) { $badge_jadwal = 'badge-tersedia'; $text_jadwal = 'Tersedia'; }
                                elseif ($status_jadwal === 1) { $badge_jadwal = 'badge-booked'; $text_jadwal = 'Booked'; }
                                else { $badge_jadwal = 'badge-maintenance'; $text_jadwal = 'Maintenance'; }
                                $is_active = ($row['Is_Deleted'] == 0 && $row['Status'] == 1);
                                $row_deleted = ($row['Is_Deleted'] == 1);
                        ?>
                            <tr class="fade-in-up <?= $row_deleted ? 'deleted-row' : '' ?>" data-id="<?= $row['ID_Jadwal'] ?>" data-ruangan="<?= htmlspecialchars($row['Nama_Ruangan']) ?>" data-paket="<?= htmlspecialchars($row['Nama_Paket']) ?>" data-tanggal="<?= $tgl_format ?>" data-hari="<?= $nama_hari ?>" data-jam="<?= $jam_mulai_str ?> - <?= $jam_selesai_str ?>" data-durasi="<?= $row['Durasi_Real'] ?? $row['Durasi_Waktu'] ?>" data-keterangan="<?= htmlspecialchars($row['Keterangan'] ?? '-') ?>" data-status-jadwal="<?= $text_jadwal ?>" data-status-data="<?= $is_active ? 'Aktif' : 'Nonaktif' ?>" data-created="<?= htmlspecialchars($row['Created_By'] ?? 'system') ?>" data-created-date="<?= is_object($row['Created_Date']) ? $row['Created_Date']->format('d M Y H:i') : $row['Created_Date'] ?>">
                                <td><?= $no++ ?></td>
                                <td><div class="td-ruangan"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div><div class="td-paket"><i class="bi bi-camera-fill me-1"></i>Paket: <?= htmlspecialchars($row['Nama_Paket']) ?></div></td>
                                <td><div class="td-tanggal"><?= $tgl_format ?></div><div class="td-hari"><?= $nama_hari ?></div></td>
                                <td><div class="td-waktu"><?= $jam_mulai_str ?> – <?= $jam_selesai_str ?></div></td>
                                <td><div class="td-durasi"><?= $row['Durasi_Real'] ?? $row['Durasi_Waktu'] ?> menit</div></td>
                                <td><div class="td-keterangan"><?= htmlspecialchars($row['Keterangan'] ?? '-') ?></div></td>
                                <td><span class="badge-status-jadwal <?= $badge_jadwal ?>"><span class="badge-dot"></span><?= $text_jadwal ?></span></td>
                                <td class="text-center">
                                    <label class="toggle-switch <?= $row_deleted ? 'deleted' : '' ?>" title="<?= $is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' ?>">
                                        <input type="checkbox" <?= $is_active ? 'checked' : '' ?> onchange="toggleSoftDelete(<?= $row['ID_Jadwal'] ?>, <?= $is_active ? 1 : 0 ?>, '<?= htmlspecialchars($row['Nama_Ruangan']) ?> - <?= $jam_mulai_str ?>')">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <button class="btn-action-circle btn-action-view" onclick="bukaDetail(<?= $row['ID_Jadwal'] ?>)" title="Lihat Detail"><i class="bi bi-eye"></i></button>
                                    <a href="edit.php?id=<?= $row['ID_Jadwal'] ?>" class="btn-action-circle btn-action-edit" title="Edit Jadwal"><i class="bi bi-pencil"></i></a>
                                    <button class="btn-action-circle btn-action-delete" onclick="hardDelete(<?= $row['ID_Jadwal'] ?>, '<?= htmlspecialchars($row['Nama_Ruangan']) ?> - <?= $jam_mulai_str ?>')" title="Hapus Permanen"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i><p class="fw-bold">Tidak ada data jadwal studio yang sesuai.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> jadwal</div>
                <nav class="pagination-nav">
                    <?php if ($halaman > 1): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
                    <?php $start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2); if ($start_page > 1) { echo '<a class="page-link-pag" href="list.php?halaman=1&tab=' . $tab_filter . '&cari=' . urlencode($cari) . '">1</a>'; if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; } for ($i = $start_page; $i <= $end_page; $i++): ?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>"><?= $i ?></a><?php endfor; if ($end_page < $total_halaman) { if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&tab=' . $tab_filter . '&cari=' . urlencode($cari) . '">' . $total_halaman . '</a>'; } ?>
                    <?php if ($halaman < $total_halaman): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?><div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> jadwal</div></div><?php endif; ?>
        </div>
    </div>

    <!-- DETAIL MODAL -->
    <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
                <div class="modal-header" style="border: none; padding: 24px 24px 16px; background: linear-gradient(135deg, #FFF0F3, #FFF8F0);"><h5 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2 text-danger"></i>Detail Jadwal</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" style="padding: 20px 24px; background: #ffffff;"><div id="detailContent"></div></div>
                <div class="modal-footer" style="border: none; padding: 0 24px 24px; background: #ffffff;"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 12px 24px; font-weight: 700;"><i class="bi bi-x-lg me-2"></i>Tutup</button></div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
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

        function bukaDetail(id) {
            const row = document.querySelector('tr[data-id="' + id + '"]');
            if (!row) return;
            const ruangan = row.getAttribute('data-ruangan');
            const paket = row.getAttribute('data-paket');
            const tanggal = row.getAttribute('data-tanggal');
            const hari = row.getAttribute('data-hari');
            const jam = row.getAttribute('data-jam');
            const durasi = row.getAttribute('data-durasi');
            const keterangan = row.getAttribute('data-keterangan');
            const statusJadwal = row.getAttribute('data-status-jadwal');
            const statusData = row.getAttribute('data-status-data');
            const created = row.getAttribute('data-created');
            const createdDate = row.getAttribute('data-created-date');
            let statusBadge = '';
            if (statusJadwal === 'Tersedia') { statusBadge = '<span class="badge-status-jadwal badge-tersedia"><span class="badge-dot"></span>Tersedia</span>'; }
            else if (statusJadwal === 'Booked') { statusBadge = '<span class="badge-status-jadwal badge-booked"><span class="badge-dot"></span>Booked</span>'; }
            else { statusBadge = '<span class="badge-status-jadwal badge-maintenance"><span class="badge-dot"></span>Maintenance</span>'; }
            const html = `<div style="display: grid; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Ruangan</div><div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem;">${ruangan}</div></div>
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Paket</div><div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem;">${paket}</div></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Tanggal</div><div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem;">${tanggal}</div><div style="font-size: 0.8rem; color: #94a3b8; font-weight: 600;">${hari}</div></div>
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Waktu</div><div style="font-weight: 700; color: var(--p-pink); font-size: 0.95rem;">${jam}</div><div style="font-size: 0.8rem; color: #94a3b8; font-weight: 600;">${durasi} menit</div></div>
                </div>
                <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Keterangan</div><div style="font-weight: 600; color: var(--text-dark); font-size: 0.9rem;">${keterangan}</div></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Status Jadwal</div><div>${statusBadge}</div></div>
                    <div style="background: #f8fafc; padding: 14px 18px; border-radius: 14px; border: 1px solid #e2e8f0;"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Status Data</div><div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem;">${statusData}</div></div>
                </div>
                <div style="background: linear-gradient(135deg, #FFF0F3, #FFF8F0); padding: 14px 18px; border-radius: 14px; border: 1px solid rgba(255, 228, 233, 0.8);"><div style="font-size: 0.7rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;"><i class="bi bi-database-fill me-1"></i>Audit Trail (Database Only)</div><div style="font-weight: 600; color: var(--text-dark); font-size: 0.85rem;">Created By: <strong>${created}</strong></div><div style="font-weight: 600; color: var(--text-dark); font-size: 0.85rem;">Created Date: <strong>${createdDate}</strong></div></div>
            </div>`;
            document.getElementById('detailContent').innerHTML = html;
            var detailModal = new bootstrap.Modal(document.getElementById('modalDetail'));
            detailModal.show();
        }

        function toggleSoftDelete(id, currentActive, info) {
            const newActive = currentActive === 1 ? 0 : 1;
            const actionText = currentActive === 1 ? 'menonaktifkan' : 'mengaktifkan';
            const confirmText = currentActive === 1 ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan';
            const iconType = currentActive === 1 ? 'warning' : 'question';
            Swal.fire({
                title: 'Ubah Status Jadwal?', text: 'Anda akan ' + actionText + ' jadwal "' + info + '"', icon: iconType,
                showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: confirmText, cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) { window.location.href = 'action_jadwal.php?aksi=toggle_soft_delete&id=' + id + '&active=' + newActive; }
                else { const checkbox = document.querySelector('input[onchange*="' + id + '"]'); if (checkbox) checkbox.checked = !checkbox.checked; }
            });
        }

        function hardDelete(id, info) {
            Swal.fire({
                title: 'HAPUS PERMANEN?', text: 'Jadwal "' + info + '" akan dihapus PERMANEN dari database!',
                icon: 'error', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_jadwal.php?aksi=hard_delete&id=' + id; } });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = '../../logout.php'; } });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info',
                showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) { window.location.href = '../../index.php'; } });
        }
        function bukaModalBiodata() {
            Swal.fire({ title: '<?= htmlspecialchars($nama_admin) ?>', text: 'Administrator - SpotLight Studio', icon: 'info', confirmButtonColor: '#D53D66' });
        }
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
            const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours(); let minutes = now.getMinutes(); let seconds = now.getSeconds();
            hours = hours < 10 ? '0' + hours : hours; minutes = minutes < 10 ? '0' + minutes : minutes; seconds = seconds < 10 ? '0' + seconds : seconds;
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();
    </script>
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = ""; let t_icon = "success"; let t_title = "Berhasil!";
        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Jadwal studio baru berhasil ditambahkan!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data jadwal studio berhasil diperbarui!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'toggle_soft_delete') { msg = "Status jadwal berhasil diubah!"; t_title = "Status Diubah"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'hard_delete') { msg = "Jadwal berhasil dihapus permanen!"; t_title = "Hard Delete Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }
        Swal.fire({ icon: t_icon, title: t_title, text: msg, confirmButtonColor: '#D53D66' });
    </script>
    <?php endif; ?>
</body>
</html>