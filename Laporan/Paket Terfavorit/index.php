<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

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

// =====================================================
// PARAMETER & VALIDASI
// =====================================================
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'bulan';
if (!in_array($mode, ['bulan', 'tahun', 'custom'])) $mode = 'bulan';

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulan = max(1, min(12, $bulan));
$tahun = max(2020, min(2030, $tahun));

if ($mode == 'bulan') {
    $tgl_mulai = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    $tgl_selesai = date('Y-m-t', strtotime("$tahun-$bulan-01"));
    $label_periode = date('F Y', strtotime("$tahun-$bulan-01"));
} elseif ($mode == 'tahun') {
    $tgl_mulai = "$tahun-01-01";
    $tgl_selesai = "$tahun-12-31";
    $label_periode = "Tahun $tahun";
} else {
    $tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_mulai)) $tgl_mulai = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_selesai)) $tgl_selesai = date('Y-m-d');
    if ($tgl_mulai > $tgl_selesai) { $tmp = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $tmp; }
    $label_periode = date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai));
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!preg_match('/^[a-zA-Z0-9\s\-\+\#\.\@]*$/', $search)) { $search = ''; }

$allowed_sort = ['booking_desc','booking_asc','nama_asc','nama_desc','harga_desc','harga_asc','rating_desc','rating_asc','batal_desc','batal_asc'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'booking_desc';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$limit = 10;
$offset = ($page - 1) * $limit;

// =====================================================
// QUERY DATA
// =====================================================
$stmt_summary = sqlsrv_query($conn, "EXEC sp_LaporanPaketTerfavoritSummary ?, ?", array($tgl_mulai, $tgl_selesai));
$summary = ($stmt_summary) ? sqlsrv_fetch_array($stmt_summary, SQLSRV_FETCH_ASSOC) : null;

$stmt_detail = sqlsrv_query($conn, "EXEC sp_LaporanPaketTerfavoritDetail ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search, $sort, $offset, $limit));

$data_paket = [];
$total_records = 0;
if ($stmt_detail) {
    while ($row = sqlsrv_fetch_array($stmt_detail, SQLSRV_FETCH_ASSOC)) {
        $data_paket[] = $row;
        $total_records = (int)$row['Total_Records'];
    }
}

if ($total_records == 0) {
    $stmt_count = sqlsrv_query($conn, "EXEC sp_LaporanPaketTerfavoritCount ?, ?, ?", 
        array($tgl_mulai, $tgl_selesai, $search));
    if ($stmt_count) {
        $row_count = sqlsrv_fetch_array($stmt_count, SQLSRV_FETCH_ASSOC);
        $total_records = (int)($row_count['TotalRecords'] ?? 0);
    }
}

$total_pages = max(1, ceil($total_records / $limit));
if ($page > $total_pages) $page = $total_pages;

$total_seluruh_booking = $summary['Total_Booking'] ?? 0;

// Build query string for pagination/links
$q_params = http_build_query([
    'mode' => $mode, 'bulan' => $bulan, 'tahun' => $tahun,
    'tgl_mulai' => $tgl_mulai, 'tgl_selesai' => $tgl_selesai,
    'search' => $search, 'sort' => $sort
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Paket Terfavorit - SpotLight Studio</title>
<link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
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

/* Toggle Periode */
.periode-toggle{display:inline-flex;background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:4px;gap:4px;}
.periode-btn{padding:8px 18px;border-radius:10px;font-weight:700;font-size:0.85rem;border:none;background:transparent;color:#718096;cursor:pointer;transition:0.3s;}
.periode-btn.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;box-shadow:0 4px 10px rgba(216,63,103,0.2);}
.periode-btn:hover:not(.active){color:var(--p-pink);background:#fff5f6;}
.periode-nav{display:inline-flex;align-items:center;gap:8px;margin-left:12px;}
.periode-nav button{width:32px;height:32px;border-radius:8px;border:2px solid #e2e8f0;background:#fff;color:var(--p-pink);font-weight:700;cursor:pointer;transition:0.3s;display:inline-flex;align-items:center;justify-content:center;}
.periode-nav button:hover{border-color:var(--p-pink);background:var(--s-pink);}
.periode-label{font-weight:800;font-size:1rem;color:var(--text-dark);min-width:140px;text-align:center;}

/* Search */
.search-box{position:relative;max-width:320px;}
.search-box input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:10px 36px 10px 14px;font-weight:600;font-size:0.9rem;transition:0.3s;}
.search-box input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.search-box .search-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#94a3b8;}
.search-box .clear-search{position:absolute;right:36px;top:50%;transform:translateY(-50%);color:#94a3b8;cursor:pointer;font-size:1.1rem;display:none;}
.search-box .clear-search.show{display:block;}

/* Cards */
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
.stat-icon-yellow{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

.summary-card{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:22px;padding:24px;color:#fff;margin-bottom:25px;}
.summary-title{font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:0.9;margin-bottom:8px;}
.summary-value{font-size:2rem;font-weight:800;margin-bottom:4px;}
.summary-subtitle{font-size:0.85rem;opacity:0.8;font-weight:600;}

.filter-card{background:#fff;border-radius:22px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 8px 24px rgba(216,63,103,0.03);padding:24px;margin-bottom:25px;}
.filter-row{display:flex;align-items:end;gap:16px;flex-wrap:wrap;}
.btn-filter{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;padding:12px 24px;border-radius:14px;font-weight:800;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;}
.btn-filter:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(216,63,103,0.25);}
.btn-export-pdf{background:#fff;border:2px solid #fee2e2;color:#dc2626;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-pdf:hover{background:#dc2626;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(220,38,38,0.2);}
.btn-export-excel{background:#fff;border:2px solid #d1fae5;color:#059669;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-excel:hover{background:#059669;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(5,150,105,0.2);}
.btn-preview{background:#fff;border:2px solid #dbeafe;color:#2563eb;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-preview:hover{background:#2563eb;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(37,99,235,0.2);}

.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;text-align:center;width:60px;}
.data-table thead th:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;font-weight:600;}
.data-table tbody td:first-child{padding-left:24px;text-align:center;}
.data-table tbody td:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}

.td-rank{font-size:1.1rem;font-weight:800;}
.rank-1{background:linear-gradient(135deg,#ffd700,#ffed4a);color:#744210;padding:4px 10px;border-radius:10px;}
.rank-2{background:linear-gradient(135deg,#c0c0c0,#e2e8f0);color:#1a202c;padding:4px 10px;border-radius:10px;}
.rank-3{background:linear-gradient(135deg,#cd7f32,#f6ad55);color:#744210;padding:4px 10px;border-radius:10px;}
.rank-other{color:var(--p-pink);}
.td-paket-nama{font-weight:800;font-size:0.95rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;}
.td-jumlah{font-weight:800;font-size:1rem;color:var(--p-pink);}
.rating-star{color:#eab308;margin-right:4px;}

.empty-state{text-align:center;padding:50px 20px;}
.empty-state i{font-size:3rem;color:#cbd5e1;margin-bottom:15px;display:block;}
.empty-state p{font-weight:700;color:#94a3b8;margin-bottom:5px;}
.empty-state small{color:#cbd5e1;font-weight:600;}

/* Pagination */
.pagination-wrapper{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:25px;}
.pagination-btn{min-width:36px;height:36px;border-radius:10px;border:2px solid #e2e8f0;background:#fff;color:#4a5568;font-weight:700;font-size:0.85rem;cursor:pointer;transition:0.3s;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;}
.pagination-btn:hover{border-color:var(--p-pink);color:var(--p-pink);background:var(--s-pink);}
.pagination-btn.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);box-shadow:0 4px 10px rgba(216,63,103,0.2);}
.pagination-btn.disabled{opacity:0.4;cursor:not-allowed;pointer-events:none;}
.pagination-info{font-size:0.8rem;color:#718096;font-weight:600;}

/* Modal Preview */
.modal-content{border-radius:22px;border:none;box-shadow:0 25px 50px rgba(0,0,0,0.15);}
.modal-header{border-bottom:2px solid #f1f5f9;padding:20px 24px;}
.modal-title{font-weight:800;color:var(--text-dark);}
.modal-body{padding:24px;}
.preview-kop{display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:24px;padding-bottom:20px;border-bottom:2px solid #f1f5f9;}
.preview-kop img{height:60px;}
.preview-kop-text{text-align:center;}
.preview-kop-text h4{font-weight:800;color:var(--p-pink);margin-bottom:2px;}
.preview-kop-text p{margin:0;font-size:0.85rem;color:#718096;}
.preview-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.preview-summary-item{background:#f8fafc;border-radius:12px;padding:14px;text-align:center;}
.preview-summary-item .val{font-size:1.3rem;font-weight:800;color:var(--p-pink);}
.preview-summary-item .lbl{font-size:0.7rem;color:#718096;font-weight:700;text-transform:uppercase;}
.preview-table{width:100%;border-collapse:collapse;font-size:0.85rem;}
.preview-table th{background:var(--p-pink);color:#fff;padding:10px;font-weight:700;text-align:left;}
.preview-table td{padding:10px;border-bottom:1px solid #e2e8f0;}
.preview-table tr:nth-child(even){background:#f8fafc;}
.preview-ttd{margin-top:30px;display:flex;justify-content:flex-end;}
.preview-ttd-box{text-align:center;width:200px;}
.preview-ttd-box .tgl{font-size:0.8rem;color:#718096;margin-bottom:50px;}
.preview-ttd-box .jabatan{font-size:0.8rem;color:#718096;margin-bottom:4px;}
.preview-ttd-box .nama{font-weight:800;border-top:2px solid var(--text-dark);padding-top:4px;}

@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
@media(max-width:992px){.main-content{margin-left:0;padding:20px;}.sidebar{transform:translateX(-100%);}}
@media(max-width:768px){.preview-summary{grid-template-columns:repeat(2,1fr);}}
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
<li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
<li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
<li><a href="index.php" class="submenu-link active"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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
<h3 class="fw-bold mb-1">Laporan Paket Terfavorit</h3>
<p class="text-muted small mb-0">Analisis best seller paket foto, popularitas, rating & kontribusi booking.</p>
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
<div class="summary-title">Paket Best Seller Saat Ini</div>
<div class="summary-value"><?= htmlspecialchars($summary['Best_Seller'] ?? 'Belum ada data') ?></div>
<div class="summary-subtitle">Periode <?= $label_periode ?> &bull; Dipesan sebanyak <?= $summary['Best_Seller_Booking'] ?? 0 ?> kali</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">Total Booking Terverifikasi</div>
<div class="summary-value" style="font-size:1.6rem;"><?= $summary['Total_Booking'] ?? 0 ?> Sesi</div>
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
<div class="stat-title">Total Paket Aktif</div>
<div class="stat-val"><?= $summary['Total_Paket_Aktif'] ?? 0 ?> Paket</div>
<div class="stat-subtitle">Tersedia untuk dipesan</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-green"><i class="bi bi-calendar-check-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Total Booking</div>
<div class="stat-val"><?= $summary['Total_Booking'] ?? 0 ?> Sesi</div>
<div class="stat-subtitle">Semua paket aktif</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-blue"><i class="bi bi-trophy-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Best Seller</div>
<div class="stat-val"><?= htmlspecialchars($summary['Best_Seller'] ?? '-') ?></div>
<div class="stat-subtitle"><?= $summary['Best_Seller_Booking'] ?? 0 ?> Booking</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-yellow"><i class="bi bi-hand-thumbs-up-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Rating Tertinggi</div>
<div class="stat-val"><?= htmlspecialchars($summary['Rating_Tertinggi'] ?? '-') ?></div>
<div class="stat-subtitle">Avg: <?= isset($summary['Rating_Nilai']) && $summary['Rating_Nilai'] > 0 ? number_format((float)$summary['Rating_Nilai'], 1) : '-' ?> <i class="bi bi-star-fill text-warning"></i></div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- FILTER & EXPORT -->
<div class="filter-card fade-in-up">
<div class="filter-row" style="justify-content:space-between;flex-wrap:wrap;gap:16px;">
<div class="d-flex align-items-center flex-wrap gap-3">
<!-- Toggle Periode -->
<div class="periode-toggle">
<button type="button" class="periode-btn <?= $mode=='bulan'?'active':'' ?>" onclick="setMode('bulan')">Bulanan</button>
<button type="button" class="periode-btn <?= $mode=='tahun'?'active':'' ?>" onclick="setMode('tahun')">Tahunan</button>
<button type="button" class="periode-btn <?= $mode=='custom'?'active':'' ?>" onclick="setMode('custom')">Custom</button>
</div>
<!-- Navigasi -->
<?php if ($mode != 'custom'): ?>
<div class="periode-nav">
<button onclick="navigate(-1)"><i class="bi bi-chevron-left"></i></button>
<span class="periode-label"><?= $label_periode ?></span>
<button onclick="navigate(1)"><i class="bi bi-chevron-right"></i></button>
</div>
<?php endif; ?>
<!-- Search -->
<div class="search-box">
<input type="text" id="searchInput" placeholder="Cari nama / ID paket..." value="<?= htmlspecialchars($search) ?>" onkeypress="if(event.key==='Enter')applySearch()">
<i class="bi bi-search search-icon"></i>
<i class="bi bi-x-circle-fill clear-search <?= $search?'show':'' ?>" onclick="clearSearch()"></i>
</div>
</div>
<div class="d-flex align-items-center gap-2 flex-wrap">
<button type="button" class="btn-filter" data-bs-toggle="modal" data-bs-target="#modalFilter"><i class="bi bi-funnel-fill"></i> Filter</button>
<button type="button" class="btn-preview" data-bs-toggle="modal" data-bs-target="#modalPreview"><i class="bi bi-eye-fill"></i> Lihat Laporan</button>
<a href="export_pdf.php?<?= $q_params ?>" class="btn-export-pdf"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>
<a href="export_excel.php?<?= $q_params ?>" class="btn-export-excel"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</a>
</div>
</div>
</div>

<!-- TABEL DATA -->
<div class="card-3d fade-in-up" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead>
<tr>
<th>Rank</th>
<th>ID Paket</th>
<th>Nama Paket</th>
<th>Durasi</th>
<th>Kapasitas</th>
<th>Harga</th>
<th>Jumlah Booking</th>
<th>Kontribusi</th>
<th>Rating</th>
<th>Jumlah Batal</th>
</tr>
</thead>
<tbody>
<?php
if (count($data_paket) > 0):
$rank = $offset + 1;
foreach ($data_paket as $row):
    $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
    $rating_val = isset($row['Rata_Rata_Rating']) && $row['Rata_Rata_Rating'] !== null ? number_format((float)$row['Rata_Rata_Rating'], 1) : null;
    $rank_class = '';
    $rank_label = '#' . $rank;
    if ($rank == 1) { $rank_class = 'rank-1'; $rank_label = '🥇 #1'; }
    elseif ($rank == 2) { $rank_class = 'rank-2'; $rank_label = '🥈 #2'; }
    elseif ($rank == 3) { $rank_class = 'rank-3'; $rank_label = '🥉 #3'; }
?>
<tr>
<td><div class="td-rank <?= $rank_class ?>"><?= $rank_label ?></div></td>
<td align="center"><span class="badge bg-light text-dark border" style="font-size:0.75rem;">PKT-<?= str_pad($row['ID_Paket'], 3, '0', STR_PAD_LEFT) ?></span></td>
<td><div class="td-paket-nama"><?= htmlspecialchars($row['Nama_Paket']) ?></div></td>
<td><div class="td-detail"><i class="bi bi-clock me-1"></i> <?= $row['Durasi_Waktu'] ?> Menit</div></td>
<td><div class="td-detail"><i class="bi bi-people-fill me-1"></i> Max <?= $row['Kapasitas_Orang'] ?> Orang</div></td>
<td><div class="td-detail">Rp <?= number_format((float)$row['Harga_Paket'], 0, ',', '.') ?></div></td>
<td><div class="td-jumlah"><?= $row['Jumlah_Booking'] ?> Sesi</div></td>
<td>
    <div class="d-flex align-items-center gap-2">
        <div class="progress" style="width: 60px; height: 6px;">
            <div class="progress-bar" role="progressbar" style="width: <?= min($kontribusi, 100) ?>%; background-color: var(--p-pink);" aria-valuenow="<?= $kontribusi ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <span class="td-detail fw-bold"><?= number_format($kontribusi, 1) ?>%</span>
    </div>
</td>
<td>
    <div class="td-detail fw-bold">
        <?php if ($rating_val): ?>
            <i class="bi bi-star-fill rating-star"></i><?= $rating_val ?>
        <?php else: ?>
            <span class="text-muted" style="font-size:0.75rem;">Belum ada</span>
        <?php endif; ?>
    </div>
</td>
<td>
    <span class="badge rounded-pill text-danger bg-light" style="font-size:0.75rem; font-weight:700;">
        <?= $row['Jumlah_Batal'] ?> Sesi
    </span>
</td>
</tr>
<?php $rank++; endforeach; else: ?>
<tr>
<td colspan="10">
<div class="empty-state">
<i class="bi bi-inbox"></i>
<p>Tidak ada data paket foto.</p>
<small>Tidak ada booking terverifikasi pada periode ini.</small>
</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination-wrapper">
<?php
$prev_disabled = $page <= 1 ? 'disabled' : '';
$next_disabled = $page >= $total_pages ? 'disabled' : '';
$prev_page = $page - 1;
$next_page = $page + 1;
?>
<a href="?<?= http_build_query(array_merge($_GET, ['page' => $prev_page])) ?>" class="pagination-btn <?= $prev_disabled ?>"><i class="bi bi-chevron-left"></i></a>

<?php for ($i = 1; $i <= $total_pages; $i++): 
    if ($i == 1 || $i == $total_pages || abs($i - $page) <= 1): 
        $active = $i == $page ? 'active' : '';
?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="pagination-btn <?= $active ?>"><?= $i ?></a>
<?php elseif (abs($i - $page) == 2): ?>
    <span class="pagination-info">...</span>
<?php endif; endfor; ?>

<a href="?<?= http_build_query(array_merge($_GET, ['page' => $next_page])) ?>" class="pagination-btn <?= $next_disabled ?>"><i class="bi bi-chevron-right"></i></a>
<span class="pagination-info"><?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?></span>
</div>
<?php endif; ?>
</div>

</div>

<!-- MODAL FILTER -->
<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter & Urutkan</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<form id="formFilter" method="GET" action="">
<input type="hidden" name="mode" value="<?= $mode ?>">
<input type="hidden" name="bulan" value="<?= $bulan ?>">
<input type="hidden" name="tahun" value="<?= $tahun ?>">
<input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

<div class="mb-3">
<label class="filter-label">Urut Berdasarkan</label>
<select name="sort" class="form-select" style="border-radius:12px;border:2px solid #e2e8f0;padding:10px 14px;font-weight:600;">
<option value="booking_desc" <?= $sort=='booking_desc'?'selected':'' ?>>Jumlah Booking Terbanyak</option>
<option value="booking_asc" <?= $sort=='booking_asc'?'selected':'' ?>>Jumlah Booking Tersedikit</option>
<option value="nama_asc" <?= $sort=='nama_asc'?'selected':'' ?>>Nama Paket (A-Z)</option>
<option value="nama_desc" <?= $sort=='nama_desc'?'selected':'' ?>>Nama Paket (Z-A)</option>
<option value="harga_desc" <?= $sort=='harga_desc'?'selected':'' ?>>Harga Tertinggi</option>
<option value="harga_asc" <?= $sort=='harga_asc'?'selected':'' ?>>Harga Terendah</option>
<option value="rating_desc" <?= $sort=='rating_desc'?'selected':'' ?>>Rating Tertinggi</option>
<option value="rating_asc" <?= $sort=='rating_asc'?'selected':'' ?>>Rating Terendah</option>
<option value="batal_desc" <?= $sort=='batal_desc'?'selected':'' ?>>Jumlah Batal Terbanyak</option>
<option value="batal_asc" <?= $sort=='batal_asc'?'selected':'' ?>>Jumlah Batal Tersedikit</option>
</select>
</div>

<div class="mb-3">
<label class="filter-label">Tanggal Mulai</label>
<input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>" style="border-radius:12px;border:2px solid #e2e8f0;padding:10px 14px;font-weight:600;">
</div>
<div class="mb-3">
<label class="filter-label">Tanggal Selesai</label>
<input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>" style="border-radius:12px;border:2px solid #e2e8f0;padding:10px 14px;font-weight:600;">
</div>
<div class="d-flex gap-2">
<button type="button" class="btn btn-outline-secondary w-100" style="border-radius:12px;font-weight:700;" onclick="resetFilter()">Reset</button>
<button type="submit" class="btn-filter w-100" style="justify-content:center;">Terapkan</button>
</div>
</form>
</div>
</div>
</div>
</div>

<!-- MODAL PREVIEW -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-eye-fill me-2 text-primary"></i>Preview Laporan</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div class="preview-kop">
<img src="../../assets/img/logo.png" onerror="this.src=''" alt="Logo">
<div class="preview-kop-text">
<h4>SpotLight Photo Studio</h4>
<p>Laporan Paket Terfavorit &mdash; Best Seller</p>
<p>Periode: <?= $label_periode ?></p>
</div>
</div>

<div class="preview-summary">
<div class="preview-summary-item">
<div class="val"><?= $summary['Total_Paket_Aktif'] ?? 0 ?></div>
<div class="lbl">Paket Aktif</div>
</div>
<div class="preview-summary-item">
<div class="val"><?= $summary['Total_Booking'] ?? 0 ?></div>
<div class="lbl">Total Booking</div>
</div>
<div class="preview-summary-item">
<div class="val"><?= htmlspecialchars($summary['Best_Seller'] ?? '-') ?></div>
<div class="lbl">Best Seller</div>
</div>
<div class="preview-summary-item">
<div class="val"><?= isset($summary['Rating_Nilai']) && $summary['Rating_Nilai'] > 0 ? number_format((float)$summary['Rating_Nilai'], 1) : '-' ?></div>
<div class="lbl">Rating Tertinggi</div>
</div>
</div>

<table class="preview-table">
<thead>
<tr>
<th>Rank</th>
<th>Nama Paket</th>
<th>Durasi</th>
<th>Kapasitas</th>
<th>Harga</th>
<th>Booking</th>
<th>Kontribusi</th>
<th>Rating</th>
<th>Batal</th>
</tr>
</thead>
<tbody>
<?php
$prank = 1;
foreach ($data_paket as $prow):
    $pkontribusi = $total_seluruh_booking > 0 ? ($prow['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
    $prating = isset($prow['Rata_Rata_Rating']) && $prow['Rata_Rata_Rating'] !== null ? number_format((float)$prow['Rata_Rata_Rating'], 1) : '-';
?>
<tr>
<td>#<?= $prank++ ?></td>
<td><?= htmlspecialchars($prow['Nama_Paket']) ?></td>
<td><?= $prow['Durasi_Waktu'] ?> Menit</td>
<td>Max <?= $prow['Kapasitas_Orang'] ?> Orang</td>
<td>Rp <?= number_format((float)$prow['Harga_Paket'], 0, ',', '.') ?></td>
<td><?= $prow['Jumlah_Booking'] ?> Sesi</td>
<td><?= number_format($pkontribusi, 1) ?>%</td>
<td><?= $prating ?></td>
<td><?= $prow['Jumlah_Batal'] ?> Sesi</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="preview-ttd">
<div class="preview-ttd-box">
<div class="tgl">Bekasi, <?= date('d F Y') ?></div>
<div class="jabatan">Owner SpotLight Studio</div>
<div class="nama"><?= htmlspecialchars($nama_owner) ?></div>
</div>
</div>
</div>
<div class="modal-footer" style="border-top:2px solid #f1f5f9;">
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:12px;font-weight:700;">Tutup</button>
<button type="button" class="btn-filter" onclick="window.print()"><i class="bi bi-printer-fill me-2"></i>Cetak</button>
</div>
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
        if(result.isConfirmed) {
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
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kembali',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = '../../index.php';
        }
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

function setMode(m) {
    const params = new URLSearchParams(window.location.search);
    params.set('mode', m);
    if (m === 'bulan') {
        params.set('bulan', '<?= date('n') ?>');
        params.set('tahun', '<?= date('Y') ?>');
    } else if (m === 'tahun') {
        params.set('tahun', '<?= date('Y') ?>');
    }
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function navigate(dir) {
    const params = new URLSearchParams(window.location.search);
    let mode = params.get('mode') || 'bulan';
    let bulan = parseInt(params.get('bulan') || '<?= date('n') ?>');
    let tahun = parseInt(params.get('tahun') || '<?= date('Y') ?>');
    if (mode === 'bulan') {
        bulan += dir;
        if (bulan > 12) { bulan = 1; tahun++; }
        if (bulan < 1) { bulan = 12; tahun--; }
        params.set('bulan', bulan);
        params.set('tahun', tahun);
    } else if (mode === 'tahun') {
        tahun += dir;
        params.set('tahun', tahun);
    }
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function applySearch() {
    const val = document.getElementById('searchInput').value;
    const params = new URLSearchParams(window.location.search);
    params.set('search', val);
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    const params = new URLSearchParams(window.location.search);
    params.delete('search');
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function resetFilter() {
    document.getElementById('formFilter').reset();
    const params = new URLSearchParams();
    params.set('mode', '<?= $mode ?>');
    params.set('bulan', '<?= date('n') ?>');
    params.set('tahun', '<?= date('Y') ?>');
    window.location.href = '?' + params.toString();
}
</script>
</body>
</html>