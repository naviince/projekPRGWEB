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

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
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
$email_admin = $admin_data['Email_Karyawan'] ?? 'admin@spotlight.com';

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
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

// =====================================================
// QUERY STATISTIK
// =====================================================
$stats = safe_sqlsrv_fetch($conn, 
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) as nonaktif
    FROM Tema_Foto WHERE Is_Deleted = 0"
) ?? ['total' => 0, 'aktif' => 0, 'nonaktif' => 0];

// Tema terpopuler (dari Order)
$top_tema = safe_sqlsrv_fetch($conn,
    "SELECT TOP 1 t.Nama_Tema, COUNT(o.ID_Order) as total_booked 
    FROM Tema_Foto t 
    LEFT JOIN [Order] o ON t.ID_Tema = o.ID_Tema AND o.Status = 1 AND o.Status_Order <> 4
    WHERE t.Is_Deleted = 0 AND t.Status = 1
    GROUP BY t.Nama_Tema 
    ORDER BY total_booked DESC"
);

// =====================================================
// DAFTAR KATEGORI (UNTUK FILTER)
// =====================================================
$daftar_kategori_filter = safe_sqlsrv_fetch_all($conn,
    "SELECT DISTINCT Kategori_Tema FROM Tema_Foto WHERE Is_Deleted = 0 AND Kategori_Tema IS NOT NULL ORDER BY Kategori_Tema ASC"
);

// =====================================================
// QUERY LIST DATA DENGAN FILTER & RELASI
// =====================================================
$conditions = ["t.Is_Deleted = 0"];
$params = [];

if (!empty($cari)) {
    $conditions[] = "(t.Nama_Tema LIKE ? OR t.Deskripsi LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
}
if ($status_filter !== "") {
    $conditions[] = "t.Status = ?";
    $params[] = (int)$status_filter;
}
if ($kategori_filter !== "") {
    $conditions[] = "t.Kategori_Tema = ?";
    $params[] = $kategori_filter;
}

$order_clause = "t.Nama_Tema ASC";
if ($sort == "nama_desc") { $order_clause = "t.Nama_Tema DESC"; }
elseif ($sort == "kategori_asc") { $order_clause = "t.Kategori_Tema ASC"; }
elseif ($sort == "kategori_desc") { $order_clause = "t.Kategori_Tema DESC"; }
elseif ($sort == "ruangan_asc") { $order_clause = "total_ruangan ASC"; }
elseif ($sort == "ruangan_desc") { $order_clause = "total_ruangan DESC"; }

// Hitung total untuk pagination
$count_sql = "SELECT COUNT(*) AS total FROM Tema_Foto t WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $count_sql, $params);
$total_halaman = ceil($total_records / $limit);

// Ambil data dengan relasi (jumlah ruangan terhubung)
$list_sql = "SELECT 
    t.ID_Tema,
    t.Nama_Tema,
    t.Kategori_Tema,
    t.Deskripsi,
    t.Foto_Tema,
    t.Status,
    (SELECT COUNT(*) FROM Ruangan_Tema rt WHERE rt.ID_Tema = t.ID_Tema) as total_ruangan
FROM Tema_Foto t
WHERE " . implode(" AND ", $conditions) . "
ORDER BY " . $order_clause . "
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_list = $params;
$params_list[] = $offset;
$params_list[] = $limit;

$tema_list = safe_sqlsrv_fetch_all($conn, $list_sql, $params_list);

// Ambil daftar ruangan untuk setiap tema (untuk badge)
$ruangan_per_tema = [];
if (!empty($tema_list)) {
    $tema_ids = array_column($tema_list, 'ID_Tema');
    $placeholders = implode(',', array_fill(0, count($tema_ids), '?'));
    $ruangan_sql = "SELECT rt.ID_Tema, r.Nama_Ruangan 
                  FROM Ruangan_Tema rt 
                  JOIN Ruangan r ON rt.ID_Ruangan = r.ID_Ruangan 
                  WHERE rt.ID_Tema IN ($placeholders) AND r.Status = 1 AND r.Is_Deleted = 0";
    $ruangan_data = safe_sqlsrv_fetch_all($conn, $ruangan_sql, $tema_ids);
    foreach ($ruangan_data as $r) {
        $ruangan_per_tema[$r['ID_Tema']][] = $r['Nama_Ruangan'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tema Foto – SpotLight Studio</title>

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
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14);
            border-color: var(--p-pink);
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
        .stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }

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
        .btn-reg-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; border-radius: 14px !important;
            padding: 12px 28px !important; font-weight: 800 !important;
            border: none !important; box-shadow: 0 8px 20px rgba(213, 61, 102, 0.25) !important;
            transition: var(--transition-3d) !important; display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-reg-header:hover {
            background: linear-gradient(135deg, #E85D84, var(--p-pink)) !important;
            transform: translateY(-4px) scale(1.03) !important;
            box-shadow: 0 12px 25px rgba(213, 61, 102, 0.4) !important;
        }

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
            width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0;
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

        .tema-preview {
            width: 70px; height: 70px; object-fit: cover;
            border-radius: 16px; border: 2px solid var(--light-pink);
            transition: var(--transition-3d); flex-shrink: 0;
        }
        .data-table tbody tr:hover .tema-preview { transform: scale(1.08) rotate(2deg); }

        .td-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .td-deskripsi { font-size: 0.8rem; color: #718096; max-width: 200px; white-space: normal; }
        .td-relasi { font-size: 0.8rem; color: #718096; font-weight: 600; }

        .badge-kategori {
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #FFF0F3, #FFE4E9);
            color: var(--p-pink); border: 1px solid var(--light-pink);
        }

        .badge-ruangan {
            font-size: 0.65rem; font-weight: 700; padding: 3px 10px;
            border-radius: 50px; background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #2563eb; border: 1px solid #dbeafe;
            display: inline-block; margin: 1px;
        }

        .badge-status {
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-aktif { background: #ecfdf5; color: #059669; }
        .badge-nonaktif { background: #fef2f2; color: #dc2626; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-aktif .badge-dot { background: #059669; }
        .badge-nonaktif .badge-dot { background: #dc2626; }

        .btn-action-circle {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            transition: var(--transition-3d); border: 1.5px solid #eef2f6;
            background: #ffffff; font-size: 0.85rem; text-decoration: none;
            margin: 0 2px; cursor: pointer;
        }
        .btn-action-edit { color: var(--p-pink); border-color: #FFE4E9; }
        .btn-action-edit:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); }

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
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Master Tema Foto</h3>
                <p class="text-muted small mb-0">Kelola data tema foto untuk sesi pemotretan pelanggan. Tema terhubung ke ruangan studio.</p>
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

        <!-- STATISTIK CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-palette-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Tema</div>
                                <div class="stat-val"><?= $stats['total'] ?? 0 ?> Tema</div>
                                <div class="stat-subtitle">Tersedia di sistem</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Tema Aktif</div>
                                <div class="stat-val"><?= $stats['aktif'] ?? 0 ?> Tema</div>
                                <div class="stat-subtitle">Tampil ke pelanggan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-x-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Tema Nonaktif</div>
                                <div class="stat-val"><?= $stats['nonaktif'] ?? 0 ?> Tema</div>
                                <div class="stat-subtitle">Disembunyikan sementara</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-blue"><i class="bi bi-award-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Terpopuler</div>
                                <div class="stat-val" style="font-size: 1.1rem;"><?= $top_tema ? htmlspecialchars($top_tema['Nama_Tema']) : '-' ?></div>
                                <div class="stat-subtitle"><?= $top_tema ? ($top_tema['total_booked'] ?? 0) . ' booking' : 'Belum ada data' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="kategori" id="hiddenKategori" value="<?= htmlspecialchars($kategori_filter) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="cari" class="search-input-main" placeholder="Cari nama tema atau deskripsi..." value="<?= htmlspecialchars($cari) ?>">
                </div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()">
                    <i class="bi bi-funnel-fill me-2"></i>Filter
                    <i class="bi bi-chevron-down ms-2"></i>
                </button>
                <button type="submit" class="btn-search-icon" title="Cari">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            <a href="add.php" class="btn-reg-header text-decoration-none">
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Tema Foto
            </a>
        </div>

        <!-- INFO TEXT -->
        <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px; background: #f8fafc;">
            <i class="bi bi-info-circle-fill me-2 text-info"></i>
            <span class="small fw-bold text-muted">
                <strong>Info:</strong> Tema foto akan ditampilkan kepada pelanggan berdasarkan ruangan yang dipilih. 
                Kelola ruangan di menu <a href="../Ruangan/list.php" style="color: var(--p-pink);">Ruangan</a>.
            </span>
        </div>

        <!-- TABEL DATA -->
        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tema Foto</th>
                            <th>Kategori</th>
                            <th>Ruangan Terhubung</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($tema_list)):
                            foreach($tema_list as $row):
                                $path_img = "../../assets/img/tema/" . ($row['Foto_Tema'] ?? '');
                                $img_src = (!empty($row['Foto_Tema']) && file_exists($path_img))
                                    ? $path_img 
                                    : $default_svg_avatar;

                                $badge_status = ($row['Status'] == 1) ? "badge-aktif" : "badge-nonaktif";
                                $text_status = ($row['Status'] == 1) ? "Aktif" : "Nonaktif";

                                $ruangan_list = $ruangan_per_tema[$row['ID_Tema']] ?? [];
                        ?>
                            <tr class="fade-in-up">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= $img_src ?>" class="tema-preview" alt="<?= htmlspecialchars($row['Nama_Tema']) ?>">
                                        <div>
                                            <div class="td-nama"><?= htmlspecialchars($row['Nama_Tema']) ?></div>
                                            <div class="td-deskripsi"><?= htmlspecialchars($row['Deskripsi'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-kategori">
                                        <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($row['Kategori_Tema'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($ruangan_list)): ?>
                                        <?php foreach (array_slice($ruangan_list, 0, 2) as $ruangan): ?>
                                            <span class="badge-ruangan"><?= htmlspecialchars($ruangan) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($ruangan_list) > 2): ?>
                                            <span class="badge-ruangan">+<?= count($ruangan_list) - 2 ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Belum terhubung</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $badge_status ?>">
                                        <span class="badge-dot"></span>
                                        <?= $text_status ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $row['ID_Tema'] ?>" class="btn-action-circle btn-action-edit" title="Edit Tema Foto">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn-action-circle btn-action-delete" onclick="toggleStatus(<?= $row['ID_Tema'] ?>, <?= $row['Status'] ?>, '<?= htmlspecialchars($row['Nama_Tema']) ?>')" title="Toggle Status">
                                        <i class="bi bi-toggle-<?= $row['Status'] == 1 ? 'on' : 'off' ?>"></i>
                                    </button>
                                    <button class="btn-action-circle btn-action-delete" onclick="hardDelete(<?= $row['ID_Tema'] ?>, '<?= htmlspecialchars($row['Nama_Tema']) ?>')" title="Hapus Permanen">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i>
                                    <p class="fw-bold">Tidak ada data tema foto yang sesuai.</p>
                                    <p class="small">Coba ubah filter atau tambah tema foto baru.</p>
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
                    Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> tema foto
                </div>
                <nav class="pagination-nav">
                    <?php 
                    $base_qs = "cari=" . urlencode($cari) . "&status=" . $status_filter . "&kategori=" . urlencode($kategori_filter) . "&sort=" . $sort;
                    ?>
                    <?php if ($halaman > 1): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&<?= $base_qs ?>" title="Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php 
                    $start_page = max(1, $halaman - 2);
                    $end_page = min($total_halaman, $halaman + 2);

                    if ($start_page > 1) {
                        echo '<a class="page-link-pag" href="list.php?halaman=1&' . $base_qs . '">1</a>';
                        if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&<?= $base_qs ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; 

                    if ($end_page < $total_halaman) {
                        if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
                        echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&' . $base_qs . '">' . $total_halaman . '</a>';
                    }
                    ?>

                    <?php if ($halaman < $total_halaman): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&<?= $base_qs ?>" title="Selanjutnya">
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
                    Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> tema foto
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
                            <option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option>
                            <option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Z - A</option>
                            <option value="kategori_asc" <?= $sort == 'kategori_asc' ? 'selected' : '' ?>>Kategori A - Z</option>
                            <option value="kategori_desc" <?= $sort == 'kategori_desc' ? 'selected' : '' ?>>Kategori Z - A</option>
                            <option value="ruangan_asc" <?= $sort == 'ruangan_asc' ? 'selected' : '' ?>>Ruangan Terhubung (Sedikit)</option>
                            <option value="ruangan_desc" <?= $sort == 'ruangan_desc' ? 'selected' : '' ?>>Ruangan Terhubung (Banyak)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">KATEGORI</label>
                        <select class="form-select" id="modalKategori" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="" <?= $kategori_filter === '' ? 'selected' : '' ?>>Semua Kategori</option>
                            <?php foreach ($daftar_kategori_filter as $k): ?>
                                <option value="<?= htmlspecialchars($k['Kategori_Tema']) ?>" <?= $kategori_filter == $k['Kategori_Tema'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['Kategori_Tema']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">STATUS</label>
                        <select class="form-select" id="modalStatus" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
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
            document.getElementById('hiddenKategori').value = document.getElementById('modalKategori').value;
            document.getElementById('hiddenStatus').value = document.getElementById('modalStatus').value;
            document.getElementById('mainSearchForm').submit();
        }
        function resetFilter() {
            document.getElementById('modalSort').value = 'nama_asc';
            document.getElementById('modalKategori').value = '';
            document.getElementById('modalStatus').value = '';
            document.getElementById('hiddenSort').value = 'nama_asc';
            document.getElementById('hiddenKategori').value = '';
            document.getElementById('hiddenStatus').value = '';
            document.getElementById('mainSearchForm').submit();
        }

        // Toggle Status
        function toggleStatus(id, currentStatus, nama) {
            const newStatus = currentStatus === 1 ? 0 : 1;
            const actionText = currentStatus === 1 ? 'menonaktifkan' : 'mengaktifkan';

            Swal.fire({
                title: 'Ubah Status Tema Foto?',
                text: 'Anda akan ' + actionText + ' tema "' + nama + '"',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Ubah',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_tema.php?aksi=toggle_status&id=' + id;
                }
            });
        }

        // Hard Delete
        function hardDelete(id, nama) {
            Swal.fire({
                title: 'HAPUS PERMANEN?',
                text: 'Tema foto "' + nama + '" akan dihapus PERMANEN dari database!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_tema.php?aksi=hard_delete&id=' + id;
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

        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Tema foto baru berhasil ditambahkan!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data tema foto berhasil diperbarui!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'toggle_status') { msg = "Status tema foto berhasil diubah!"; t_title = "Status Diubah"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'hard_delete') { msg = "Tema foto berhasil dihapus permanen!"; t_title = "Hard Delete Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }

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