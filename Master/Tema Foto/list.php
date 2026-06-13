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

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV
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
$admin_data = safe_sqlsrv_fetch($conn,
    "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0",
    [$id_admin]
);

$nama_admin    = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin    = $admin_data['Foto_Profil']   ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin
    : $default_svg_avatar;

// =====================================================
// FILTER & PAGINATION
// =====================================================
$search    = trim($_GET['search']    ?? '');
$filter_kat = trim($_GET['kategori'] ?? '');
$filter_st  = $_GET['status'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 8;
$offset     = ($page - 1) * $per_page;

// =====================================================
// QUERY DATA TEMA FOTO
// =====================================================
$where_parts = ["t.Is_Deleted = 0"];
$params      = [];

if ($search !== '') {
    $where_parts[] = "(t.Nama_Tema LIKE ? OR t.Kategori_Tema LIKE ? OR t.Deskripsi LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filter_kat !== '') {
    $where_parts[] = "t.Kategori_Tema = ?";
    $params[] = $filter_kat;
}
if ($filter_st !== '') {
    $where_parts[] = "t.Status = ?";
    $params[] = (int)$filter_st;
}

$where_sql = implode(' AND ', $where_parts);

// Total count
$count_sql = "SELECT COUNT(*) AS total FROM Tema_Foto t WHERE {$where_sql}";
$count_row = safe_sqlsrv_fetch($conn, $count_sql, $params);
$total_data = $count_row['total'] ?? 0;
$total_page = max(1, ceil($total_data / $per_page));

// Main query — jumlah ruangan terhubung via subquery
$sql_tema = "SELECT 
    t.ID_Tema, t.Nama_Tema, t.Kategori_Tema, t.Deskripsi, t.Foto_Tema,
    t.Status, t.Created_Date,
    (SELECT COUNT(*) FROM Ruangan_Tema rt WHERE rt.ID_Tema = t.ID_Tema) AS Jumlah_Ruangan,
    (SELECT COUNT(*) FROM [Order] o WHERE o.ID_Tema = t.ID_Tema AND o.Status = 1 AND o.Status_Order <> 4) AS Jumlah_Order_Aktif
FROM Tema_Foto t
WHERE {$where_sql}
ORDER BY t.Created_Date DESC
OFFSET {$offset} ROWS FETCH NEXT {$per_page} ROWS ONLY";

$daftar_tema = safe_sqlsrv_fetch_all($conn, $sql_tema, $params);

// =====================================================
// STATISTIK RINGKAS
// =====================================================
$stat_total   = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) AS n FROM Tema_Foto WHERE Is_Deleted = 0");
$stat_aktif   = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) AS n FROM Tema_Foto WHERE Is_Deleted = 0 AND Status = 1");
$stat_nonaktif = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) AS n FROM Tema_Foto WHERE Is_Deleted = 0 AND Status = 0");

$daftar_kategori = ['Casual', 'Formal', 'Vintage', 'Modern', 'Outdoor', 'Wisuda', 'Pre-Wedding', 'Lainnya'];

// Notifikasi dari action
$notif_type    = $_GET['status_sukses'] ?? '';
$notif_message = $_GET['message']      ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Tema Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink:      #D53D66;
            --d-pink:      #CA3366;
            --s-pink:      #FFF0F3;
            --light-pink:  #FFE4E9;
            --accent-pink: #E85D84;
            --text-dark:   #1e1e24;
            --text-muted:  #718096;
            --body-bg:     #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px; height: 100vh; background: #fff;
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255,228,233,.8);
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: .85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: .9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: .85rem; text-decoration: none; border-radius: 10px; transition: .3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: .85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,.2); }

        /* ===== MAIN ===== */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; cursor: pointer; transition: var(--transition-3d); background: #fff; }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* ===== BREADCRUMB ===== */
        .breadcrumb-custom { display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: .85rem; font-weight: 600; }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color .2s; }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }

        /* ===== STATS CARDS ===== */
        .stat-card { background: #fff; border-radius: 18px; border: 1px solid rgba(255,228,233,.8); padding: 22px 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 16px rgba(213,61,102,.04); transition: var(--transition-3d); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(213,61,102,.1); }
        .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .stat-icon.pink  { background: var(--s-pink);   color: var(--p-pink); }
        .stat-icon.green { background: #f0fdf4;          color: #16a34a; }
        .stat-icon.gray  { background: #f8fafc;          color: #64748b; }
        .stat-num  { font-size: 1.6rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: .78rem; color: var(--text-muted); font-weight: 600; margin-top: 2px; }

        /* ===== TOOLBAR ===== */
        .toolbar-card { background: #fff; border-radius: 18px; border: 1px solid rgba(255,228,233,.8); padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-input-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #a0aec0; }
        .search-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 12px; padding: 10px 16px 10px 40px; font-size: .85rem; font-weight: 600; color: #1e293b; transition: .3s; }
        .search-input:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213,61,102,.08); }
        .btn-tambah { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 12px; padding: 10px 22px; font-weight: 800; font-size: .85rem; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition-3d); text-decoration: none; white-space: nowrap; }
        .btn-tambah:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,.3); color: #fff; }
        .btn-reset { border: 2px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 10px 16px; font-size: .85rem; font-weight: 700; color: #64748b; transition: .3s; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .btn-reset:hover { border-color: var(--p-pink); color: var(--p-pink); }

        /* Tombol Filter */
        .btn-filter {
            border: 2px solid #e2e8f0; background: #fff; border-radius: 12px;
            padding: 10px 18px; font-size: .85rem; font-weight: 700; color: #475569;
            display: inline-flex; align-items: center; gap: 8px;
            cursor: pointer; transition: var(--transition-3d); white-space: nowrap;
            position: relative;
        }
        .btn-filter:hover { border-color: var(--p-pink); color: var(--p-pink); background: var(--s-pink); }
        .btn-filter.has-filter { border-color: var(--p-pink); color: var(--p-pink); background: var(--s-pink); }
        .filter-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--p-pink); position: absolute; top: -3px; right: -3px;
            display: none;
        }
        .btn-filter.has-filter .filter-dot { display: block; }

        /* Active filter chips */
        .active-filter-chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .filter-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--s-pink); color: var(--p-pink);
            border: 1px solid var(--light-pink); border-radius: 20px;
            padding: 4px 10px; font-size: .72rem; font-weight: 700;
        }
        .filter-chip button { background: none; border: none; color: inherit; padding: 0; cursor: pointer; line-height: 1; font-size: .75rem; }

        /* ===== FILTER MODAL ===== */
        .filter-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.45); z-index: 1050;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .filter-modal-overlay.show { display: flex; }
        .filter-modal {
            background: #fff; border-radius: 24px;
            padding: 0; width: 100%; max-width: 460px;
            box-shadow: 0 24px 60px rgba(0,0,0,.18);
            animation: modalSlideIn .3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }
        .filter-modal-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            padding: 22px 28px; display: flex; align-items: center; justify-content: space-between;
        }
        .filter-modal-header h6 { color: #fff; font-weight: 800; font-size: 1rem; margin: 0; }
        .filter-modal-header p  { color: rgba(255,255,255,.8); font-size: .78rem; margin: 4px 0 0; }
        .filter-modal-close {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,.2); border: none; color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1rem; transition: .2s;
        }
        .filter-modal-close:hover { background: rgba(255,255,255,.35); }
        .filter-modal-body { padding: 28px; }
        .filter-section-label {
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .8px; color: #94a3b8; margin-bottom: 12px;
        }

        /* Chip selector dalam modal */
        .chip-group { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
        .chip-opt {
            padding: 8px 16px; border-radius: 20px; border: 2px solid #e2e8f0;
            background: #fff; font-size: .82rem; font-weight: 700; color: #475569;
            cursor: pointer; transition: var(--transition-3d); user-select: none;
        }
        .chip-opt:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .chip-opt.selected { border-color: var(--p-pink); background: var(--s-pink); color: var(--p-pink); }

        .filter-modal-footer {
            padding: 20px 28px; border-top: 1px solid #f1f5f9;
            display: flex; gap: 10px; justify-content: flex-end;
        }
        .btn-filter-apply {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff; border: none; border-radius: 12px;
            padding: 11px 28px; font-weight: 800; font-size: .875rem;
            cursor: pointer; transition: var(--transition-3d);
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-filter-apply:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,.3); }
        .btn-filter-clear {
            background: #f1f5f9; color: #475569; border: none; border-radius: 12px;
            padding: 11px 20px; font-weight: 700; font-size: .875rem;
            cursor: pointer; transition: .2s;
        }
        .btn-filter-clear:hover { background: #e2e8f0; }

        /* ===== TABLE CARD ===== */
        .table-card { background: #fff; border-radius: 22px; border: 1px solid rgba(255,228,233,.8); box-shadow: 0 8px 24px rgba(213,61,102,.03); overflow: hidden; }
        .table-card-header { padding: 22px 28px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .table-card-header h6 { font-weight: 800; font-size: .95rem; margin: 0; }
        .table-responsive { overflow-x: auto; }

        table.tema-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        table.tema-table thead th { background: #fafbfc; padding: 14px 20px; font-size: .72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        table.tema-table tbody tr { transition: background .2s; }
        table.tema-table tbody tr:hover { background: #fafbfc; }
        table.tema-table tbody td { padding: 16px 20px; border-bottom: 1px solid #f8fafc; font-size: .875rem; vertical-align: middle; }
        table.tema-table tbody tr:last-child td { border-bottom: none; }

        /* Foto thumbnail */
        .tema-thumb { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 2px solid var(--light-pink); flex-shrink: 0; }
        .tema-thumb-placeholder { width: 56px; height: 56px; border-radius: 12px; background: var(--s-pink); display: flex; align-items: center; justify-content: center; color: var(--p-pink); font-size: 1.3rem; flex-shrink: 0; border: 2px solid var(--light-pink); }

        /* Badge */
        .badge-kategori { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; background: var(--s-pink); color: var(--p-pink); }
        .badge-status-aktif    { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: .72rem; font-weight: 700; background: #f0fdf4; color: #16a34a; }
        .badge-status-nonaktif { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: .72rem; font-weight: 700; background: #f8fafc; color: #64748b; }
        .badge-ruangan { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; background: #eff6ff; color: #2563eb; }

        /* Action buttons */
        .action-btns { display: flex; gap: 6px; align-items: center; }
        .btn-action { width: 34px; height: 34px; border-radius: 10px; border: none; display: flex; align-items: center; justify-content: center; font-size: .85rem; transition: var(--transition-3d); cursor: pointer; }
        .btn-action:hover { transform: translateY(-2px); }
        .btn-edit   { background: #eff6ff; color: #2563eb; }
        .btn-edit:hover   { background: #dbeafe; box-shadow: 0 4px 10px rgba(37,99,235,.15); }
        .btn-toggle-on  { background: #fef9c3; color: #ca8a04; }
        .btn-toggle-on:hover  { background: #fef08a; }
        .btn-toggle-off { background: #f0fdf4; color: #16a34a; }
        .btn-toggle-off:hover { background: #dcfce7; }
        .btn-delete { background: #fef2f2; color: #dc2626; }
        .btn-delete:hover { background: #fee2e2; box-shadow: 0 4px 10px rgba(220,38,38,.15); }

        /* Empty state */
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state i { font-size: 3rem; color: #e2e8f0; margin-bottom: 16px; display: block; }
        .empty-state p { color: var(--text-muted); font-size: .9rem; font-weight: 600; margin: 0; }

        /* Pagination */
        .pagination-wrap { padding: 18px 28px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: .8rem; color: var(--text-muted); font-weight: 600; }
        .pagination-btns { display: flex; gap: 6px; }
        .page-btn { width: 36px; height: 36px; border-radius: 10px; border: 2px solid #e2e8f0; background: #fff; font-size: .8rem; font-weight: 700; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: .2s; text-decoration: none; }
        .page-btn:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .page-btn.active { border-color: var(--p-pink); background: var(--p-pink); color: #fff; }
        .page-btn.disabled { opacity: .4; pointer-events: none; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        .fade-in-up { animation: fadeIn .4s ease-out; }

        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../../Role/Admin/index.php" class="nav-link-custom">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="../Pelanggan/list.php"     class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                        <li><a href="../Paket Foto/list.php"   class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                        <li><a href="../Ruangan/list.php"       class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                        <li><a href="../Properti/list.php"      class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                        <li><a href="./list.php"                class="submenu-link active"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                        <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        <li><a href="../Barang Cetak/list.php"  class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
                        <li><a href="../../Transaksi/Order/list.php"       class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                        <li><a href="../../Transaksi/Pembayaran/list.php"  class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                        <li><a href="../../Transaksi/Pembatalan/list.php"  class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                        <li><a href="../../Transaksi/Sesi Foto/list.php"   class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                        <li><a href="../../Transaksi/Penjualan/list.php"   class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
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
            <h3 class="fw-bold mb-1">Tema Foto</h3>
            <p class="text-muted small mb-0">Kelola tema foto dan ruangan yang bisa menggunakannya.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;">
                <i class="bi bi-clock-history me-1 text-danger"></i>
                <span id="live-clock">Memuat waktu...</span>
            </span>
            <div class="profile-header-btn shadow-sm" title="Profil">
                <img src="<?= $foto_admin_src ?>" alt="Admin">
            </div>
        </div>
    </div>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom">
        <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.7rem;color:#cbd5e1;"></i>
        <a href="./list.php">Data Master</a>
        <i class="bi bi-chevron-right" style="font-size:.7rem;color:#cbd5e1;"></i>
        <span class="active">Tema Foto</span>
    </div>

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon pink"><i class="bi bi-palette-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $stat_total['n'] ?? 0 ?></div>
                    <div class="stat-label">Total Tema Foto</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $stat_aktif['n'] ?? 0 ?></div>
                    <div class="stat-label">Tema Aktif</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon gray"><i class="bi bi-dash-circle-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $stat_nonaktif['n'] ?? 0 ?></div>
                    <div class="stat-label">Tema Nonaktif</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar-card fade-in-up">
        <form method="GET" id="filterForm" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <!-- Hidden inputs untuk filter (diisi JS saat apply) -->
            <input type="hidden" name="kategori" id="hidden-kategori" value="<?= htmlspecialchars($filter_kat) ?>">
            <input type="hidden" name="status"   id="hidden-status"   value="<?= htmlspecialchars($filter_st) ?>">

            <!-- Search -->
            <div class="search-input-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="search-input"
                       placeholder="Cari nama tema, deskripsi..."
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>

            <!-- Tombol Filter -->
            <button type="button" id="btnOpenFilter"
                    class="btn-filter <?= ($filter_kat || $filter_st !== '') ? 'has-filter' : '' ?>"
                    onclick="openFilterModal()">
                <i class="bi bi-sliders2"></i> Filter
                <?php
                $total_filter = ($filter_kat ? 1 : 0) + ($filter_st !== '' ? 1 : 0);
                if ($total_filter > 0): ?>
                    <span style="background:var(--p-pink);color:#fff;border-radius:20px;padding:1px 7px;font-size:.7rem;"><?= $total_filter ?></span>
                <?php endif; ?>
                <span class="filter-dot"></span>
            </button>

            <!-- Tombol Cari -->
            <button type="submit" class="btn-tambah" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                <i class="bi bi-search"></i> Cari
            </button>

            <!-- Reset (hanya muncul kalau ada filter aktif) -->
            <?php if ($search || $filter_kat || $filter_st !== ''): ?>
                <a href="list.php" class="btn-reset">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
            <?php endif; ?>

            <div class="ms-auto">
                <a href="add.php" class="btn-tambah"><i class="bi bi-plus-lg"></i> Tambah Tema</a>
            </div>
        </form>

        <!-- Active filter chips (tampil di bawah toolbar kalau ada filter aktif) -->
        <?php if ($filter_kat || $filter_st !== ''): ?>
        <div class="active-filter-chips w-100 mt-2 pt-2" style="border-top:1px solid #f1f5f9;">
            <span style="font-size:.72rem;color:#94a3b8;font-weight:700;">Filter aktif:</span>
            <?php if ($filter_kat): ?>
                <span class="filter-chip">
                    <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($filter_kat) ?>
                    <button onclick="removeFilter('kategori')" title="Hapus filter"><i class="bi bi-x"></i></button>
                </span>
            <?php endif; ?>
            <?php if ($filter_st !== ''): ?>
                <span class="filter-chip">
                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                    <?= $filter_st === '1' ? 'Aktif' : 'Nonaktif' ?>
                    <button onclick="removeFilter('status')" title="Hapus filter"><i class="bi bi-x"></i></button>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== FILTER MODAL ===== -->
    <div class="filter-modal-overlay" id="filterModalOverlay" onclick="closeFilterOnOverlay(event)">
        <div class="filter-modal">
            <div class="filter-modal-header">
                <div>
                    <h6><i class="bi bi-sliders2 me-2"></i>Filter Tema Foto</h6>
                    <p>Pilih kategori dan status yang ingin ditampilkan</p>
                </div>
                <button class="filter-modal-close" onclick="closeFilterModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="filter-modal-body">

                <!-- Kategori -->
                <div class="filter-section-label"><i class="bi bi-tag-fill me-1"></i> Kategori Tema</div>
                <div class="chip-group" id="chipKategori">
                    <?php foreach ($daftar_kategori as $kat): ?>
                        <div class="chip-opt <?= $filter_kat === $kat ? 'selected' : '' ?>"
                             data-value="<?= $kat ?>" onclick="toggleChip(this, 'kategori')">
                            <?= $kat ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Status -->
                <div class="filter-section-label"><i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i> Status</div>
                <div class="chip-group" id="chipStatus">
                    <div class="chip-opt <?= $filter_st === '1' ? 'selected' : '' ?>"
                         data-value="1" onclick="toggleChip(this, 'status')">
                        ✅ Aktif
                    </div>
                    <div class="chip-opt <?= $filter_st === '0' ? 'selected' : '' ?>"
                         data-value="0" onclick="toggleChip(this, 'status')">
                        ⛔ Nonaktif
                    </div>
                </div>

            </div>
            <div class="filter-modal-footer">
                <button class="btn-filter-clear" onclick="clearAllFilter()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                </button>
                <button class="btn-filter-apply" onclick="applyFilter()">
                    <i class="bi bi-check2-circle"></i> Terapkan Filter
                </button>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-card fade-in-up">
        <div class="table-card-header">
            <h6><i class="bi bi-palette-fill me-2 text-danger"></i>Daftar Tema Foto</h6>
            <span class="badge" style="background:var(--s-pink);color:var(--p-pink);font-weight:700;border-radius:20px;padding:6px 14px;">
                <?= $total_data ?> tema
            </span>
        </div>

        <?php if (!empty($daftar_tema)): ?>
        <div class="table-responsive">
            <table class="tema-table">
                <thead>
                    <tr>
                        <th style="width:48px;">#</th>
                        <th style="width:64px;">Foto</th>
                        <th>Nama Tema</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Ruangan</th>
                        <th>Status</th>
                        <th style="width:130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_tema as $idx => $tema): ?>
                    <tr>
                        <td class="text-muted fw-700" style="font-size:.8rem;"><?= $offset + $idx + 1 ?></td>
                        <td>
                            <?php
                            $foto = $tema['Foto_Tema'] ?? '';
                            $foto_path = "../../assets/img/tema/" . $foto;
                            if (!empty($foto) && $foto !== 'default_tema.jpg' && file_exists($foto_path)):
                            ?>
                                <img src="<?= $foto_path ?>" class="tema-thumb" alt="<?= htmlspecialchars($tema['Nama_Tema']) ?>">
                            <?php else: ?>
                                <div class="tema-thumb-placeholder"><i class="bi bi-palette-fill"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700;font-size:.875rem;"><?= htmlspecialchars($tema['Nama_Tema']) ?></div>
                            <?php if ($tema['Jumlah_Order_Aktif'] > 0): ?>
                                <div style="font-size:.72rem;color:#f59e0b;font-weight:600;margin-top:2px;">
                                    <i class="bi bi-lightning-charge-fill"></i> <?= $tema['Jumlah_Order_Aktif'] ?> order aktif
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-kategori"><?= htmlspecialchars($tema['Kategori_Tema'] ?? '-') ?></span></td>
                        <td>
                            <div style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-muted);font-size:.82rem;"
                                 title="<?= htmlspecialchars($tema['Deskripsi'] ?? '') ?>">
                                <?= !empty($tema['Deskripsi']) ? htmlspecialchars($tema['Deskripsi']) : '<span style="color:#cbd5e1;">—</span>' ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge-ruangan">
                                <i class="bi bi-door-open-fill"></i>
                                <?= (int)$tema['Jumlah_Ruangan'] ?> ruangan
                            </span>
                        </td>
                        <td>
                            <?php if ($tema['Status'] == 1): ?>
                                <span class="badge-status-aktif"><i class="bi bi-circle-fill" style="font-size:.5rem;"></i>Aktif</span>
                            <?php else: ?>
                                <span class="badge-status-nonaktif"><i class="bi bi-circle-fill" style="font-size:.5rem;"></i>Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <!-- Edit -->
                                <a href="edit.php?id=<?= $tema['ID_Tema'] ?>" class="btn-action btn-edit" title="Edit Tema">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <!-- Toggle Status -->
                                <?php if ($tema['Status'] == 1): ?>
                                    <button class="btn-action btn-toggle-on"
                                            onclick="confirmToggle(<?= $tema['ID_Tema'] ?>, 'nonaktifkan', '<?= addslashes($tema['Nama_Tema']) ?>')"
                                            title="Nonaktifkan">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-action btn-toggle-off"
                                            onclick="confirmToggle(<?= $tema['ID_Tema'] ?>, 'aktifkan', '<?= addslashes($tema['Nama_Tema']) ?>')"
                                            title="Aktifkan">
                                        <i class="bi bi-toggle-off"></i>
                                    </button>
                                <?php endif; ?>
                                <!-- Hapus -->
                                <button class="btn-action btn-delete"
                                        onclick="confirmDelete(<?= $tema['ID_Tema'] ?>, '<?= addslashes($tema['Nama_Tema']) ?>', <?= (int)$tema['Jumlah_Order_Aktif'] ?>)"
                                        title="Hapus Tema">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php
        $base_url = "list.php?" . http_build_query(array_filter([
            'search'   => $search,
            'kategori' => $filter_kat,
            'status'   => $filter_st,
        ]));
        $from = $offset + 1;
        $to   = min($offset + $per_page, $total_data);
        ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <?= $from ?>–<?= $to ?> dari <?= $total_data ?> tema
            </div>
            <div class="pagination-btns">
                <a href="<?= $base_url ?>&page=<?= max(1, $page - 1) ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $total_page; $p++): ?>
                    <?php if ($p == 1 || $p == $total_page || abs($p - $page) <= 1): ?>
                        <a href="<?= $base_url ?>&page=<?= $p ?>"
                           class="page-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php elseif (abs($p - $page) == 2): ?>
                        <span class="page-btn" style="pointer-events:none;border:none;">…</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="<?= $base_url ?>&page=<?= min($total_page, $page + 1) ?>"
                   class="page-btn <?= $page >= $total_page ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-palette"></i>
            <p>
                <?= ($search || $filter_kat || $filter_st !== '') ? 'Tidak ada tema yang sesuai filter.' : 'Belum ada tema foto. Klik "Tambah Tema" untuk memulai.' ?>
            </p>
            <?php if ($search || $filter_kat || $filter_st !== ''): ?>
                <a href="list.php" class="btn-reset mt-2 d-inline-block"><i class="bi bi-x-circle me-1"></i>Reset Filter</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    // Submenu toggle
    document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const target   = document.querySelector(this.getAttribute('data-target'));
            const chevron  = this.querySelector('.icon-chevron');
            const isShown  = target.classList.contains('show');
            document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.icon-chevron').forEach(ic => ic.style.transform = 'rotate(0deg)');
            if (!isShown) {
                target.classList.add('show');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        });
    });

    // Konfirmasi Toggle Status
    function confirmToggle(id, aksi, nama) {
        const label = aksi === 'aktifkan' ? 'mengaktifkan' : 'menonaktifkan';
        Swal.fire({
            title: aksi === 'aktifkan' ? 'Aktifkan Tema?' : 'Nonaktifkan Tema?',
            html: `Apakah Anda yakin ingin ${label} tema <strong>${nama}</strong>?<br>
                   <small class="text-muted">Tema nonaktif tidak akan muncul ke pelanggan.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#D53D66',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, ' + (aksi === 'aktifkan' ? 'Aktifkan' : 'Nonaktifkan'),
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                window.location.href = `action_tema.php?aksi=toggle_status&id=${id}`;
            }
        });
    }

    // Konfirmasi Hapus
    function confirmDelete(id, nama, orderAktif) {
        if (orderAktif > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Bisa Dihapus',
                html: `Tema <strong>${nama}</strong> tidak bisa dihapus karena masih memiliki <strong>${orderAktif} order aktif</strong>.<br>
                       <small class="text-muted">Nonaktifkan atau selesaikan order terkait terlebih dahulu.</small>`,
                confirmButtonColor: '#D53D66'
            });
            return;
        }
        Swal.fire({
            title: 'Hapus Tema Foto?',
            html: `Apakah Anda yakin ingin menghapus tema <strong>${nama}</strong>?<br>
                   <small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Tindakan ini tidak dapat dibatalkan.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#718096',
            confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                window.location.href = `action_tema.php?aksi=hard_delete&id=${id}`;
            }
        });
    }

    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) window.location.href = '../../logout.php'; });
    }

    function confirmLandingPage(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.',
            icon: 'info', showCancelButton: true,
            confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) window.location.href = '../../index.php'; });
    }

    // ===== FILTER MODAL =====
    const selectedFilter = {
        kategori: '<?= addslashes($filter_kat) ?>',
        status:   '<?= addslashes($filter_st) ?>'
    };

    function openFilterModal() {
        document.getElementById('filterModalOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterModal() {
        document.getElementById('filterModalOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    function closeFilterOnOverlay(e) {
        if (e.target === document.getElementById('filterModalOverlay')) closeFilterModal();
    }

    // Keyboard ESC
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFilterModal(); });

    function toggleChip(el, type) {
        // Single select per group: deselect yang lain dulu
        const group = type === 'kategori' ? 'chipKategori' : 'chipStatus';
        document.querySelectorAll(`#${group} .chip-opt`).forEach(c => c.classList.remove('selected'));
        // Toggle: kalau sudah selected → deselect; kalau belum → select
        const wasSelected = selectedFilter[type] === el.dataset.value;
        if (!wasSelected) {
            el.classList.add('selected');
            selectedFilter[type] = el.dataset.value;
        } else {
            selectedFilter[type] = '';
        }
    }

    function clearAllFilter() {
        selectedFilter.kategori = '';
        selectedFilter.status   = '';
        document.querySelectorAll('.chip-opt').forEach(c => c.classList.remove('selected'));
    }

    function applyFilter() {
        document.getElementById('hidden-kategori').value = selectedFilter.kategori;
        document.getElementById('hidden-status').value   = selectedFilter.status;
        closeFilterModal();
        document.getElementById('filterForm').submit();
    }

    // Hapus filter satu per satu dari chip aktif di toolbar
    function removeFilter(type) {
        if (type === 'kategori') {
            document.getElementById('hidden-kategori').value = '';
        } else {
            document.getElementById('hidden-status').value = '';
        }
        document.getElementById('filterForm').submit();
    }

    // ===== JAM REAL-TIME =====
    function updateLiveClock() {
        const now = new Date();
        const days   = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
        const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
        document.getElementById('live-clock').innerText =
            `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ` +
            `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
    }
    setInterval(updateLiveClock, 1000); updateLiveClock();
</script>

<?php
// SweetAlert notifikasi dari action_tema.php
$notif_map = [
    'tambah'        => ['success', 'Berhasil!',         'Tema foto baru berhasil ditambahkan.'],
    'edit'          => ['success', 'Berhasil Diperbarui!','Data tema foto berhasil diperbarui.'],
    'toggle_status' => ['success', 'Status Diperbarui!', $notif_message ?: 'Status tema foto berhasil diubah.'],
    'hard_delete'   => ['success', 'Berhasil Dihapus!', 'Tema foto berhasil dihapus dari sistem.'],
    'error'         => ['error',   'Terjadi Kesalahan',  $notif_message ?: 'Terjadi kesalahan. Coba lagi.'],
];
if (isset($notif_map[$notif_type])):
    [$icon, $title, $text] = $notif_map[$notif_type];
?>
<script>
    Swal.fire({
        icon:  '<?= $icon ?>',
        title: '<?= $title ?>',
        text:  '<?= addslashes($text) ?>',
        confirmButtonColor: '#D53D66'
    });
</script>
<?php endif; ?>

</body>
</html>