<?php
session_start();

// Set timezone ke Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

$debug_mode = false;

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

if (!file_exists('../../koneksi.php')) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: File koneksi.php tidak ditemukan!</h2></div>');
}
include '../../koneksi.php';

if (!isset($conn) || $conn === false) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: Koneksi database gagal!</h2></div>');
}

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
if ($q_profile === false) {
    $nama_owner = 'Pemilik';
    $foto_owner_src = $default_svg_avatar;
} else {
    $d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
    if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
    $nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
    $foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';
    $foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) 
        ? "../../assets/img/pelanggan/" . $foto_owner 
        : $default_svg_avatar;
}

$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'bulanan';
$allowed_modes = ['bulanan', 'tahunan', 'custom'];
if (!in_array($mode, $allowed_modes)) $mode = 'bulanan';

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2020 || $tahun > 2100) $tahun = (int)date('Y');

if ($mode === 'bulanan') {
    $tgl_mulai = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    $tgl_selesai = date('Y-m-t', strtotime("$tahun-$bulan-01"));
    $periode_label = date('F Y', strtotime("$tahun-$bulan-01"));
    $prev_bulan = $bulan - 1; $prev_tahun = $tahun;
    if ($prev_bulan < 1) { $prev_bulan = 12; $prev_tahun--; }
    $next_bulan = $bulan + 1; $next_tahun = $tahun;
    if ($next_bulan > 12) { $next_bulan = 1; $next_tahun++; }
} elseif ($mode === 'tahunan') {
    $tgl_mulai = "$tahun-01-01";
    $tgl_selesai = "$tahun-12-31";
    $periode_label = "Tahun $tahun";
    $prev_tahun = $tahun - 1;
    $next_tahun = $tahun + 1;
} else {
    $tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
    $d1 = DateTime::createFromFormat('Y-m-d', $tgl_mulai);
    $d2 = DateTime::createFromFormat('Y-m-d', $tgl_selesai);
    if (!$d1 || $d1->format('Y-m-d') !== $tgl_mulai) $tgl_mulai = date('Y-m-01');
    if (!$d2 || $d2->format('Y-m-d') !== $tgl_selesai) $tgl_selesai = date('Y-m-d');
    if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
        $tmp = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $tmp;
    }
    $periode_label = date('d M Y', strtotime($tgl_mulai)) . ' – ' . date('d M Y', strtotime($tgl_selesai));
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_regex = '/^[a-zA-Z0-9\s\-\+\#\.\@]*$/';
$search_error = '';
if ($search !== '' && !preg_match($search_regex, $search)) {
    $search_error = 'Karakter tidak valid. Hanya huruf, angka, spasi, + # - . @ yang diizinkan.';
    $search = '';
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terjual_desc';
$allowed_sorts = ['terjual_desc','terjual_asc','stok_desc','stok_asc','harga_desc','harga_asc','nama_asc','nama_desc','nilai_desc','nilai_asc'];
if (!in_array($sort, $allowed_sorts)) $sort = 'terjual_desc';

$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$allowed_status = ['', 'aman', 'menipis', 'habis'];
if (!in_array($status_filter, $allowed_status)) $status_filter = '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$summary = [
    'Total_Jenis_Barang' => 0,
    'Total_Stok_Menipis' => 0,
    'Total_Stok_Habis' => 0,
    'Total_Nilai_Aset' => 0,
    'Total_Unit_Terjual' => 0,
    'Total_Omzet_Terjual' => 0
];
$q_summary = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangSummary ?, ?", array($tgl_mulai, $tgl_selesai));
if ($q_summary && ($row = sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC))) {
    $summary = $row;
}

$detail_rows = [];
$total_records = 0;

$q_detail = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangDetail ?, ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search ?: null, $status_filter ?: null, $sort, $offset, $limit));

if ($q_detail) {
    while ($row = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        if ($total_records === 0 && isset($row['Total_Records'])) {
            $total_records = (int)$row['Total_Records'];
        }
        $detail_rows[] = $row;
    }
}
if ($total_records === 0 && count($detail_rows) > 0) {
    $total_records = count($detail_rows);
}
$total_pages = max(1, ceil($total_records / $limit));
if ($page > $total_pages) $page = $total_pages;

$preview_rows = [];
$q_preview = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangDetail ?, ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search ?: null, $status_filter ?: null, $sort, 0, 1000000));
if ($q_preview) {
    while ($row = sqlsrv_fetch_array($q_preview, SQLSRV_FETCH_ASSOC)) {
        $preview_rows[] = $row;
    }
}

$export_params = http_build_query([
    'tgl_mulai' => $tgl_mulai,
    'tgl_selesai' => $tgl_selesai,
    'search' => $search,
    'sort' => $sort,
    'status_filter' => $status_filter,
    'mode' => $mode,
    'bulan' => $bulan,
    'tahun' => $tahun
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Stok Barang Cetak - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#d83f67;--d-pink:#c73165;--s-pink:#fff5f6;--light-pink:#ffe4e9;--accent-pink:#ff6694;--text-dark:#1e1e24;--text-muted:#718096;--body-bg:#f8fafc;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--body-bg);color:var(--text-dark);}
.sidebar{width:260px;height:100vh;background:#fff;position:fixed;top:0;left:0;border-right:1px solid rgba(255,236,239,0.8);display:flex;flex-direction:column;justify-content:space-between;padding:30px 20px;z-index:100;}
.sidebar-brand{font-weight:800;font-size:1.5rem;color:var(--p-pink);text-decoration:none;letter-spacing:-1px;margin-bottom:40px;display:block;}
.sidebar-brand span{color:var(--text-dark);font-size:0.85rem;font-weight:600;}
.sidebar-menu-wrapper{flex-grow:1;overflow-y:auto;margin-bottom:20px;scrollbar-width:none;}
.sidebar-menu-wrapper::-webkit-scrollbar{display:none;}
.nav-menu{list-style:none;padding:0;margin:0;}
.nav-item{margin-bottom:8px;}
.nav-link-custom{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;color:#4a5568;font-weight:700;text-decoration:none;border-radius:12px;font-size:0.9rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
.nav-link-custom:hover,.nav-link-custom.active{background:var(--light-pink);color:var(--p-pink);transform:translateX(4px);}
.submenu{list-style:none;padding-left:20px;margin-top:5px;display:none;}
.submenu.show{display:block!important;}
.submenu-link{display:flex;align-items:center;padding:8px 18px;color:#718096;font-weight:600;font-size:0.85rem;text-decoration:none;border-radius:10px;transition:0.3s;}
.submenu-link:hover,.submenu-link.active{color:var(--p-pink);background:rgba(216,63,103,0.03);padding-left:22px;}
.btn-logout{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;width:100%;padding:12px;border-radius:12px;font-weight:800;font-size:0.85rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
.btn-logout:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(216,63,103,0.2);}
.main-content{margin-left:260px;padding:40px;min-height:100vh;}
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px;}
.profile-header-btn{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #fff;cursor:pointer;transition:all 0.4s;background:#fff;}
.profile-header-btn:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 20px rgba(216,63,103,0.15);border-color:var(--p-pink);}
.profile-header-btn img{width:100%;height:100%;object-fit:cover;}
.summary-card{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:22px;padding:24px;color:#fff;margin-bottom:25px;}
.summary-title{font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:0.9;margin-bottom:8px;}
.summary-value{font-size:2rem;font-weight:800;margin-bottom:4px;}
.summary-subtitle{font-size:0.85rem;opacity:0.8;font-weight:600;}
.stats-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:10px;margin-bottom:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.stats-scroll-wrapper::-webkit-scrollbar{height:6px;}
.stats-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.stats-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.stats-row{display:flex;gap:16px;min-width:max-content;}
.stat-card-item{min-width:220px;max-width:280px;flex:0 0 auto;}
.card-3d{background:#fff;border-radius:22px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 8px 24px rgba(216,63,103,0.03);transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);padding:20px;height:100%;position:relative;overflow:hidden;}
.card-3d::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,var(--p-pink),var(--accent-pink));opacity:0;transition:opacity 0.3s ease;}
.card-3d:hover{transform:translateY(-8px) scale(1.01);box-shadow:0 22px 45px rgba(216,63,103,0.14);border-color:var(--p-pink);}
.card-3d:hover::before{opacity:1;}
.stat-card{display:flex;align-items:center;gap:14px;}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:all 0.4s;flex-shrink:0;}
.card-3d:hover .stat-icon{transform:scale(1.1) rotate(5deg);}
.stat-icon-pink{background:linear-gradient(135deg,#fff5f6,#ffe4e9);color:var(--p-pink);}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;}
.stat-icon-blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.filter-card{background:#fff;border-radius:22px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 8px 24px rgba(216,63,103,0.03);padding:24px;margin-bottom:25px;}
.mode-toggle{display:inline-flex;background:#f1f5f9;border-radius:12px;padding:4px;gap:4px;margin-bottom:16px;}
.mode-btn{padding:8px 18px;border-radius:10px;font-size:0.85rem;font-weight:700;color:#718096;text-decoration:none;transition:all 0.3s;border:none;background:transparent;cursor:pointer;}
.mode-btn.active{background:#fff;color:var(--p-pink);box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.mode-btn:hover:not(.active){color:var(--text-dark);}
.search-wrapper{position:relative;flex:1;max-width:400px;}
.search-input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px 12px 44px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.search-input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;}
.search-clear{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.1rem;display:none;}
.search-clear.show{display:block;}
.search-error{color:#dc2626;font-size:0.75rem;font-weight:600;margin-top:6px;display:none;}
.search-error.show{display:block;}
.btn-filter-open{background:#fff;border:2px solid #e2e8f0;color:#4a5568;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;}
.btn-filter-open:hover{border-color:var(--p-pink);color:var(--p-pink);}
.btn-filter-open i{color:var(--p-pink);}
.btn-preview{background:#fff;border:2px solid var(--light-pink);color:var(--p-pink);padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-preview:hover{background:var(--s-pink);transform:translateY(-2px);}
.btn-export-pdf{background:#fff;border:2px solid #fee2e2;color:#dc2626;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-pdf:hover{background:#dc2626;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(220,38,38,0.2);}
.btn-export-excel{background:#fff;border:2px solid #d1fae5;color:#059669;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-excel:hover{background:#059669;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(5,150,105,0.2);}
.periode-nav{display:flex;align-items:center;gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;}
.periode-btn{width:36px;height:36px;border-radius:50%;border:1px solid #e2e8f0;background:#fff;color:#4a5568;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s;text-decoration:none;}
.periode-btn:hover{background:var(--s-pink);border-color:var(--p-pink);color:var(--p-pink);}
.periode-label{display:flex;align-items:center;gap:8px;background:#fff5f6;padding:8px 18px;border-radius:12px;font-weight:700;font-size:0.9rem;color:var(--p-pink);}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;}
.data-table thead th:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;font-weight:600;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}
.td-no{font-weight:800;font-size:0.9rem;color:#94a3b8;width:50px;}
.td-barang-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-barang-nama{font-weight:800;font-size:0.95rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;}
.td-stok{font-weight:800;font-size:1rem;color:var(--p-pink);}
.td-omzet{font-weight:800;font-size:1rem;color:#059669;}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.badge-aman{background:#ecfdf5;color:#059669;}
.badge-tipis{background:#fffbeb;color:#d97706;}
.badge-habis{background:#fee2e2;color:#dc2626;}
.pagination-wrapper{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px;}
.page-btn{padding:8px 16px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#4a5568;font-weight:700;font-size:0.85rem;text-decoration:none;transition:all 0.3s;}
.page-btn:hover{background:var(--s-pink);border-color:var(--p-pink);color:var(--p-pink);}
.page-btn.disabled{opacity:0.4;pointer-events:none;}
.page-btn.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);}
.page-info{font-size:0.85rem;color:#718096;font-weight:600;}
.empty-state{text-align:center;padding:50px 20px;}
.empty-state i{font-size:3rem;color:#cbd5e1;margin-bottom:15px;display:block;}
.empty-state p{font-weight:700;color:#94a3b8;margin-bottom:5px;}
.empty-state small{color:#cbd5e1;font-weight:600;}
.modal-content{border-radius:22px;border:none;box-shadow:0 25px 60px rgba(0,0,0,0.15);}
.modal-header{border-bottom:1px solid #f1f5f9;padding:20px 24px;}
.modal-title{font-weight:800;font-size:1.1rem;color:var(--text-dark);}
.modal-body{padding:24px;}
.modal-footer{border-top:1px solid #f1f5f9;padding:16px 24px;}
.btn-close-modal{background:#f1f5f9;border:none;padding:10px 20px;border-radius:12px;font-weight:700;font-size:0.85rem;color:#4a5568;cursor:pointer;transition:0.3s;}
.btn-close-modal:hover{background:#e2e8f0;}
.btn-apply-filter{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;padding:10px 24px;border-radius:12px;font-weight:800;font-size:0.85rem;cursor:pointer;transition:all 0.4s;}
.btn-apply-filter:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(216,63,103,0.25);}
.filter-modal-label{font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px;display:block;}
.filter-modal-select,.filter-modal-input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.filter-modal-select:focus,.filter-modal-input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.preview-kop{display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--p-pink);}
.preview-kop img{height:60px;width:auto;}
.preview-kop-text{text-align:left;}
.preview-kop-text h4{font-weight:800;color:var(--p-pink);margin:0;font-size:1.3rem;}
.preview-kop-text p{margin:0;font-size:0.85rem;color:#718096;font-weight:600;}
.preview-summary{display:flex;gap:16px;margin-bottom:20px;}
.preview-summary-item{flex:1;background:#f8fafc;border-radius:12px;padding:12px;text-align:center;}
.preview-summary-item .val{font-weight:800;font-size:1.1rem;color:var(--p-pink);}
.preview-summary-item .lbl{font-size:0.7rem;color:#718096;font-weight:700;text-transform:uppercase;}
.preview-table{width:100%;border-collapse:collapse;font-size:0.8rem;}
.preview-table th,.preview-table td{padding:10px;border:1px solid #e2e8f0;text-align:left;}
.preview-table th{background:#f8fafc;font-weight:800;color:#94a3b8;font-size:0.7rem;text-transform:uppercase;}
.preview-table td{font-weight:600;color:var(--text-dark);}
.preview-table tr:nth-child(even){background:#fff8f0;}
.preview-ttd{margin-top:30px;display:flex;justify-content:flex-end;}
.preview-ttd-box{text-align:center;width:200px;}
.preview-ttd-box .ttd-tanggal{font-size:0.8rem;color:#718096;margin-bottom:40px;}
.preview-ttd-box .ttd-jabatan{font-weight:800;font-size:0.9rem;color:var(--text-dark);}
.preview-ttd-box .ttd-nama{font-weight:700;font-size:0.85rem;color:var(--p-pink);margin-top:4px;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
@media(max-width:992px){.main-content{margin-left:0;padding:20px;}.sidebar{transform:translateX(-100%);}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="sidebar-menu-wrapper">
<a href="../../Role/Owner/index.php" class="sidebar-brand">SpotLight.<br><span>Beranda Pemilik</span></a>
<ul class="nav-menu">
<li class="nav-item"><a href="../../Role/Owner/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
<li class="nav-item">
    <a href="../../Master/Karyawan/index.php" class="nav-link-custom">
        <span><i class="bi bi-person-badge-fill me-2"></i> Kelola Karyawan</span>
    </a>
</li>
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLaporan"><span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuLaporan">
<ul class="list-unstyled">
<li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
<li><a href="index.php" class="submenu-link active"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
<li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
<li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
</ul>
</div>
</li>
<li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
</ul>
</div>
<div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
<div class="dashboard-header">
<div>
<h3 class="fw-bold mb-1">Laporan Stok Barang Cetak</h3>
<p class="text-muted small mb-0">Menganalisis sisa persediaan, melacak unit barang yang terjual, serta menghitung total aset barang.</p>
</div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_owner_src ?>" alt="Owner Profil"></div>
</div>
</div>

<!-- SUMMARY CARD -->
<div class="summary-card fade-in-up">
<div class="row align-items-center">
<div class="col-lg-8">
<div class="summary-title">Total Nilai Aset Persediaan Saat Ini</div>
<div class="summary-value">Rp <?= number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.') ?></div>
<div class="summary-subtitle">Tersebar di <?= $summary['Total_Jenis_Barang'] ?? 0 ?> jenis barang cetak aktif &bull; Stok menipis: <?= ($summary['Total_Stok_Menipis'] ?? 0) + ($summary['Total_Stok_Habis'] ?? 0) ?> item</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">Unit Terjual Periode Ini</div>
<div class="summary-value" style="font-size:1.5rem;"><?= number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.') ?> Unit</div>
<div class="summary-subtitle">Omzet Rp <?= number_format((float)($summary['Total_Omzet_Terjual'] ?? 0), 0, ',', '.') ?></div>
</div>
</div>
</div>

<!-- STAT CARDS -->
<div class="stats-scroll-wrapper fade-in-up">
<div class="stats-row">
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-box-seam-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Jenis Barang</div>
<div class="stat-val"><?= $summary['Total_Jenis_Barang'] ?? 0 ?> Produk</div>
<div class="stat-subtitle">Katalog barang cetak aktif</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-green"><i class="bi bi-cart-check-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Unit Terjual</div>
<div class="stat-val"><?= number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.') ?> Unit</div>
<div class="stat-subtitle">Periode <?= $periode_label ?></div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-orange"><i class="bi bi-exclamation-triangle-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Stok Menipis</div>
<div class="stat-val"><?= ($summary['Total_Stok_Menipis'] ?? 0) + ($summary['Total_Stok_Habis'] ?? 0) ?> Item</div>
<div class="stat-subtitle">Perlu restock segera</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-blue"><i class="bi bi-shield-lock-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Total Nilai Aset</div>
<div class="stat-val">Rp <?= number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.') ?></div>
<div class="stat-subtitle">Berdasarkan stok saat ini</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- FILTER & ACTION CARD -->
<div class="filter-card fade-in-up">
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
<div class="mode-toggle">
<a href="?mode=bulanan&bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>" class="mode-btn <?= $mode=='bulanan'?'active':'' ?>">Bulanan</a>
<a href="?mode=tahunan&tahun=<?= date('Y') ?>" class="mode-btn <?= $mode=='tahunan'?'active':'' ?>">Tahunan</a>
<a href="?mode=custom" class="mode-btn <?= $mode=='custom'?'active':'' ?>">Custom</a>
</div>
<div class="search-wrapper">
<i class="bi bi-search search-icon"></i>
<input type="text" id="searchInput" class="search-input" placeholder="Cari nama barang atau ID..." value="<?= htmlspecialchars($search) ?>" maxlength="50">
<button type="button" id="clearSearch" class="search-clear <?= $search?'show':'' ?>"><i class="bi bi-x-circle-fill"></i></button>
</div>
<div class="search-error <?= $search_error?'show':'' ?>" id="searchError"><?= $search_error ?></div>
<button type="button" class="btn-filter-open" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="bi bi-funnel-fill"></i> Filter</button>
</div>
<div class="d-flex flex-wrap align-items-center gap-3">
<button type="button" class="btn-preview" data-bs-toggle="modal" data-bs-target="#previewModal"><i class="bi bi-eye-fill"></i> Lihat Laporan</button>
<a href="export_pdf.php?<?= $export_params ?>" class="btn-export-pdf"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>
<a href="export_excel.php?<?= $export_params ?>" class="btn-export-excel"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</a>
</div>
<div class="periode-nav">
<?php if ($mode === 'bulanan'): ?>
<a href="?mode=bulanan&bulan=<?= $prev_bulan ?>&tahun=<?= $prev_tahun ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&status_filter=<?= $status_filter ?>" class="periode-btn"><i class="bi bi-chevron-left"></i></a>
<div class="periode-label"><i class="bi bi-calendar3"></i> <?= $periode_label ?></div>
<a href="?mode=bulanan&bulan=<?= $next_bulan ?>&tahun=<?= $next_tahun ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&status_filter=<?= $status_filter ?>" class="periode-btn"><i class="bi bi-chevron-right"></i></a>
<?php elseif ($mode === 'tahunan'): ?>
<a href="?mode=tahunan&tahun=<?= $prev_tahun ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&status_filter=<?= $status_filter ?>" class="periode-btn"><i class="bi bi-chevron-left"></i></a>
<div class="periode-label"><i class="bi bi-calendar3"></i> <?= $periode_label ?></div>
<a href="?mode=tahunan&tahun=<?= $next_tahun ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&status_filter=<?= $status_filter ?>" class="periode-btn"><i class="bi bi-chevron-right"></i></a>
<?php else: ?>
<div class="periode-label"><i class="bi bi-calendar3"></i> <?= $periode_label ?></div>
<?php endif; ?>
</div>
</div>

<!-- TABEL DATA -->
<div class="card-3d" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead>
<tr>
<th style="width:50px;">No</th>
<th>ID Barang</th>
<th>Nama Barang</th>
<th>Harga Jual</th>
<th>Stok Saat Ini</th>
<th>Stok Minimum</th>
<th>Unit Terjual</th>
<th>Status Persediaan</th>
<th>Nilai Persediaan</th>
<th class="text-end">Omzet Terjual</th>
</tr>
</thead>
<tbody>
<?php if (count($detail_rows) > 0): ?>
<?php foreach ($detail_rows as $idx => $row): 
    $stok = (int)$row['Stok_Barang'];
    $min = (int)$row['Stok_Minimum'];
    $nilai_persediaan = $stok * (float)$row['Harga_Barang'];
    if ($stok === 0) { $status_label = "Habis"; $badge_class = "badge-habis"; }
    elseif ($stok <= $min) { $status_label = "Stok Menipis"; $badge_class = "badge-tipis"; }
    else { $status_label = "Stok Aman"; $badge_class = "badge-aman"; }
    $nomor_urut = $offset + $idx + 1;
?>
<tr>
<td><div class="td-no"><?= $nomor_urut ?></div></td>
<td><div class="td-barang-id">#BRG-<?= str_pad((int)$row['ID_Barang'], 3, '0', STR_PAD_LEFT) ?></div></td>
<td><div class="td-barang-nama"><?= htmlspecialchars($row['Nama_Barang']) ?></div></td>
<td><div class="td-detail">Rp <?= number_format((float)$row['Harga_Barang'], 0, ',', '.') ?></div></td>
<td><div class="td-stok"><?= number_format($stok, 0, ',', '.') ?> Unit</div></td>
<td><div class="td-detail"><?= number_format($min, 0, ',', '.') ?> Unit</div></td>
<td><div class="td-detail fw-bold text-dark"><?= number_format((int)$row['Total_Terjual'], 0, ',', '.') ?> Unit</div></td>
<td><span class="badge-status <?= $badge_class ?>"><span class="badge-dot" style="background:currentColor;"></span><?= $status_label ?></span></td>
<td><div class="td-detail fw-bold">Rp <?= number_format($nilai_persediaan, 0, ',', '.') ?></div></td>
<td><div class="td-omzet text-end">Rp <?= number_format((float)$row['Total_Pendapatan'], 0, ',', '.') ?></div></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="10">
<div class="empty-state">
<i class="bi bi-box-seam"></i>
<p>Tidak ada data inventaris barang.</p>
<small><?= $search ? 'Pencarian tidak menemukan hasil untuk "'.htmlspecialchars($search).'".' : 'Data katalog barang cetak tidak ditemukan atau kosong.' ?></small>
</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination-wrapper">
<a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page-1)])) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
<span class="page-info">Halaman <?= $page ?> dari <?= $total_pages ?> (<?= number_format($total_records, 0, ',', '.') ?> data)</span>
<a href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page+1)])) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
</div>
<?php endif; ?>
</div>

</div>

<!-- PREVIEW MODAL -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-eye-fill me-2" style="color:var(--p-pink);"></i>Preview Laporan Stok Barang</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div class="preview-kop">
<img src="../../assets/img/logo.png" alt="SpotLight Studio" onerror="this.style.display='none'">
<div class="preview-kop-text">
<h4>SpotLight Studio</h4>
<p>Laporan Stok Barang Cetak &bull; Periode <?= $periode_label ?></p>
</div>
</div>
<div class="preview-summary">
<div class="preview-summary-item"><div class="val"><?= $summary['Total_Jenis_Barang'] ?? 0 ?></div><div class="lbl">Jenis Barang</div></div>
<div class="preview-summary-item"><div class="val"><?= number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Unit Terjual</div></div>
<div class="preview-summary-item"><div class="val">Rp <?= number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Nilai Aset</div></div>
<div class="preview-summary-item"><div class="val">Rp <?= number_format((float)($summary['Total_Omzet_Terjual'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Omzet Terjual</div></div>
</div>
<div class="table-responsive">
<table class="preview-table">
<thead>
<tr><th>No</th><th>ID</th><th>Nama Barang</th><th>Harga</th><th>Stok</th><th>Min</th><th>Terjual</th><th>Status</th><th>Nilai</th><th>Omzet</th></tr>
</thead>
<tbody>
<?php $no = 1; foreach ($preview_rows as $prow): 
    $pstok = (int)$prow['Stok_Barang']; $pmin = (int)$prow['Stok_Minimum'];
    $pnilai = $pstok * (float)$prow['Harga_Barang'];
    if ($pstok === 0) $pstatus = 'Habis'; elseif ($pstok <= $pmin) $pstatus = 'Menipis'; else $pstatus = 'Aman';
?>
<tr>
<td><?= $no++ ?></td>
<td>#BRG-<?= str_pad((int)$prow['ID_Barang'], 3, '0', STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($prow['Nama_Barang']) ?></td>
<td>Rp <?= number_format((float)$prow['Harga_Barang'], 0, ',', '.') ?></td>
<td><?= number_format($pstok, 0, ',', '.') ?></td>
<td><?= number_format($pmin, 0, ',', '.') ?></td>
<td><?= number_format((int)$prow['Total_Terjual'], 0, ',', '.') ?></td>
<td><?= $pstatus ?></td>
<td>Rp <?= number_format($pnilai, 0, ',', '.') ?></td>
<td>Rp <?= number_format((float)$prow['Total_Pendapatan'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
<?php if (count($preview_rows) === 0): ?><tr><td colspan="10" style="text-align:center;color:#94a3b8;">Tidak ada data.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<div class="preview-ttd">
<div class="preview-ttd-box">
<div class="ttd-tanggal">tanggal <?= date('d M Y') ?><br>Approval</div>
<div style="border-top:1px solid #1e1e24;padding-top:8px;margin-top:40px;">
<div class="ttd-jabatan">Owner</div>
<div class="ttd-nama"><?= htmlspecialchars($nama_owner) ?></div>
</div>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn-close-modal" data-bs-dismiss="modal">Tutup</button>
<button type="button" class="btn-apply-filter" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Cetak</button>
</div>
</div>
</div>
</div>

<!-- FILTER MODAL -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-funnel-fill me-2" style="color:var(--p-pink);"></i>Filter Laporan</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form method="GET" action="" id="filterForm">
<div class="modal-body">
<input type="hidden" name="mode" value="custom">
<div class="mb-3">
<label class="filter-modal-label">Urut Berdasarkan</label>
<select name="sort" class="filter-modal-select">
<option value="terjual_desc" <?= $sort=='terjual_desc'?'selected':'' ?>>Unit Terjual (Terbanyak)</option>
<option value="terjual_asc" <?= $sort=='terjual_asc'?'selected':'' ?>>Unit Terjual (Tersedikit)</option>
<option value="stok_desc" <?= $sort=='stok_desc'?'selected':'' ?>>Stok (Terbanyak)</option>
<option value="stok_asc" <?= $sort=='stok_asc'?'selected':'' ?>>Stok (Tersedikit)</option>
<option value="harga_desc" <?= $sort=='harga_desc'?'selected':'' ?>>Harga (Tertinggi)</option>
<option value="harga_asc" <?= $sort=='harga_asc'?'selected':'' ?>>Harga (Terendah)</option>
<option value="nama_asc" <?= $sort=='nama_asc'?'selected':'' ?>>Nama Barang (A-Z)</option>
<option value="nama_desc" <?= $sort=='nama_desc'?'selected':'' ?>>Nama Barang (Z-A)</option>
<option value="nilai_desc" <?= $sort=='nilai_desc'?'selected':'' ?>>Nilai Persediaan (Tertinggi)</option>
<option value="nilai_asc" <?= $sort=='nilai_asc'?'selected':'' ?>>Nilai Persediaan (Terendah)</option>
</select>
</div>
<div class="mb-3">
<label class="filter-modal-label">Status Persediaan</label>
<select name="status_filter" class="filter-modal-select">
<option value="" <?= $status_filter==''?'selected':'' ?>>Semua Status</option>
<option value="aman" <?= $status_filter=='aman'?'selected':'' ?>>Stok Aman</option>
<option value="menipis" <?= $status_filter=='menipis'?'selected':'' ?>>Stok Menipis</option>
<option value="habis" <?= $status_filter=='habis'?'selected':'' ?>>Stok Habis</option>
</select>
</div>
<div class="row">
<div class="col-6 mb-3">
<label class="filter-modal-label">Tanggal Dari</label>
<input type="date" name="tgl_mulai" class="filter-modal-input" value="<?= $tgl_mulai ?>" required>
</div>
<div class="col-6 mb-3">
<label class="filter-modal-label">Tanggal Sampai</label>
<input type="date" name="tgl_selesai" class="filter-modal-input" value="<?= $tgl_selesai ?>" required>
</div>
</div>
</div>
<div class="modal-footer">
<a href="index.php?mode=bulanan" class="btn-close-modal" style="text-decoration:none;">Reset</a>
<button type="submit" class="btn-apply-filter"><i class="bi bi-check-lg me-1"></i> Terapkan</button>
</div>
</form>
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
        if(targetEl) {
            const isShown = targetEl.classList.contains('show');
            document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.icon-chevron').forEach(icon => icon.style.transform = 'rotate(0deg)');
            if(!isShown) {
                targetEl.classList.add('show');
                if(chevron) chevron.style.transform = 'rotate(180deg)';
            }
        }
    });
});

const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
const searchError = document.getElementById('searchError');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearSearch.classList.toggle('show', this.value.length > 0);
    searchError.classList.remove('show');
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const val = this.value.trim();
        const regex = /^[a-zA-Z0-9\s\-\+\#\.\@]*$/;
        if (val && !regex.test(val)) {
            searchError.textContent = 'Karakter tidak valid. Hanya huruf, angka, spasi, + # - . @ yang diizinkan.';
            searchError.classList.add('show');
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('search', val);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }, 600);
});

clearSearch.addEventListener('click', function() {
    searchInput.value = '';
    clearSearch.classList.remove('show');
    searchError.classList.remove('show');
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    url.searchParams.delete('page');
    window.location.href = url.toString();
});

function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Keluar Sistem?',
        text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) window.location.href = '../../logout.php';
    });
}

function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Kembali ke Beranda?',
        text: 'Anda akan dialihkan ke halaman utama publik.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kembali',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) window.location.href = '../../index.php';
    });
}

function bukaModalBiodata() {
    Swal.fire({
        title: '<?= htmlspecialchars($nama_owner) ?>',
        text: 'Owner - SpotLight Studio',
        icon: 'info',
        confirmButtonColor: '#d83f67'
    });
}

function updateLiveClock() {
    const now = new Date();
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('live-clock').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;
}
setInterval(updateLiveClock, 1000);
updateLiveClock();
</script>
</body>
</html>