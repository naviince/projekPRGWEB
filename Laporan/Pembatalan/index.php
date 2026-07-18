<?php
session_start();

// Set timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI_SESI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

// --- PROTEKSI HALAMAN: HANYA OWNER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

include '../../koneksi.php';

if (!isset($conn) || $conn === false) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: Koneksi database gagal!</h2></div>');
}

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// =====================================================
// AMBIL DATA PROFIL OWNER
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$nama_owner = 'Pemilik';
$foto_owner_src = $default_svg_avatar;
if ($q_profile !== false) {
    $d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
    if ($d_profile) {
        $d_profile = array_change_key_case($d_profile, CASE_LOWER);
        $nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
        $foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';
        $foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner))
            ? "../../assets/img/karyawan/" . $foto_owner
            : $default_svg_avatar;
    }
}

// =====================================================
// MODE LAPORAN: BULANAN / TAHUNAN / CUSTOM
// =====================================================
$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['bulanan', 'tahunan', 'custom']) ? $_GET['mode'] : 'bulanan';

// Navigasi Bulanan
$bulan_aktif = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun_bulan = isset($_GET['tahun_bulan']) ? (int)$_GET['tahun_bulan'] : (int)date('Y');

// Navigasi Tahunan
$tahun_aktif = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Sort & Search (tervalidasi)
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['terbaru','terlama','nama_asc','nama_desc','harga_tertinggi','harga_terendah','jadwal_terdekat','jadwal_terjauh']) ? $_GET['sort'] : 'terbaru';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_escaped = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

// Validasi search
if ($search !== '' && !preg_match('/^[a-zA-Z0-9\s\+\#\-\.\@]+$/u', $search)) {
    $search = '';
    $search_escaped = '';
}

// Filter Alasan Batal
$alasan_filter = isset($_GET['alasan']) && in_array($_GET['alasan'], ['belum_bayar_dp','dp_ditolak','dibatalkan_pelanggan','dibatalkan_sistem']) ? $_GET['alasan'] : '';

// Nama bulan Indonesia
$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// =====================================================
// FILTER TANGGAL
// =====================================================
if ($mode === 'custom') {
    $tgl_mulai = isset($_GET['tgl_mulai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
} elseif ($mode === 'tahunan') {
    $tgl_mulai = "$tahun_aktif-01-01";
    $tgl_selesai = "$tahun_aktif-12-31";
} else {
    $tgl_mulai = date('Y-m-01', strtotime("$tahun_bulan-$bulan_aktif-01"));
    $tgl_selesai = date('Y-m-t', strtotime("$tahun_bulan-$bulan_aktif-01"));
}

if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
    [$tgl_mulai, $tgl_selesai] = [$tgl_selesai, $tgl_mulai];
}

// =====================================================
// BUILD BASE URL PARAMS
// =====================================================
$url_params = '';
if ($mode == 'bulanan') {
    $url_params = "mode=bulanan&bulan=$bulan_aktif&tahun_bulan=$tahun_bulan";
} elseif ($mode == 'tahunan') {
    $url_params = "mode=tahunan&tahun=$tahun_aktif";
} else {
    $url_params = "mode=custom&tgl_mulai=$tgl_mulai&tgl_selesai=$tgl_selesai";
}
if ($search !== '') $url_params .= '&search=' . urlencode($search);
if ($sort != 'terbaru') $url_params .= '&sort=' . $sort;
if ($alasan_filter !== '') $url_params .= '&alasan=' . $alasan_filter;

// =====================================================
// PAGINATION
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

// =====================================================
// SUMMARY (via SP)
// =====================================================
$q_summary = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanSummary (?, ?)}", array($tgl_mulai, $tgl_selesai));
$summary = [];
if ($q_summary) {
    while ($r = sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC)) {
        $summary[] = $r;
    }
}

$total_batal            = $summary[0]['Total_Batal'] ?? 0;
$total_belum_bayar_dp   = $summary[1]['Total_BelumBayarDP'] ?? 0;
$total_dp_ditolak       = $summary[2]['Total_DPDitolak'] ?? 0;
$total_dibatalkan_plg   = $summary[3]['Total_DibatalkanPelanggan'] ?? 0;
$total_dibatalkan_sistem= $summary[4]['Total_DibatalkanSistem'] ?? 0;

// =====================================================
// COUNT TOTAL RECORDS (via SP)
// =====================================================
$q_count = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanCount (?, ?, ?, ?)}", array($tgl_mulai, $tgl_selesai, $search, $alasan_filter));
$total_records = 0;
if ($q_count && ($r = sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC))) {
    $total_records = (int)($r['TotalRecords'] ?? 0);
}
$total_halaman = ceil($total_records / $limit);
if ($halaman > $total_halaman && $total_halaman > 0) $halaman = $total_halaman;
$offset = ($halaman - 1) * $limit;

// =====================================================
// DETAIL DATA (via SP dengan pagination)
// =====================================================
$q_detail = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanDetail (?, ?, ?, ?, ?, ?, ?)}", 
    array($tgl_mulai, $tgl_selesai, $search, $alasan_filter, $sort, $offset, $limit));
$detail_rows = [];
if ($q_detail) {
    while ($r = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        $detail_rows[] = $r;
    }
}

// =====================================================
// DATA PREVIEW (SEMUA baris tanpa pagination)
// =====================================================
$q_preview = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanDetail (?, ?, ?, ?, ?, 0, 1000000)}", 
    array($tgl_mulai, $tgl_selesai, $search, $alasan_filter, $sort));
$preview_rows = [];
if ($q_preview) {
    while ($r = sqlsrv_fetch_array($q_preview, SQLSRV_FETCH_ASSOC)) {
        $preview_rows[] = $r;
    }
}

function formatTanggal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y H:i');
    }
    return date('d M Y H:i', strtotime($dateObj));
}
function formatTanggalSingkat($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y');
    }
    return date('d M Y', strtotime($dateObj));
}

// Badge style per alasan batal
function getAlasanBadge($alasan) {
    $map = [
        'Belum Bayar DP'        => ['bg' => '#fef2f2', 'color' => '#dc2626', 'dot' => '#dc2626'],
        'DP Ditolak'            => ['bg' => '#fffbeb', 'color' => '#d97706', 'dot' => '#d97706'],
        'Dibatalkan Pelanggan'  => ['bg' => '#eff6ff', 'color' => '#2563eb', 'dot' => '#2563eb'],
        'Dibatalkan Sistem'     => ['bg' => '#f3f4f6', 'color' => '#4b5563', 'dot' => '#4b5563'],
    ];
    return $map[$alasan] ?? ['bg' => '#f3f4f6', 'color' => '#4b5563', 'dot' => '#4b5563'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pembatalan - SpotLight Studio</title>
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
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px;flex-wrap:wrap;gap:12px;}
.profile-header-btn{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #fff;cursor:pointer;transition:all 0.4s;background:#fff;}
.profile-header-btn:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 20px rgba(216,63,103,0.15);border-color:var(--p-pink);}
.profile-header-btn img{width:100%;height:100%;object-fit:cover;}
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
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-icon-blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;}
.stat-icon-gray{background:linear-gradient(135deg,#f3f4f6,#e5e7eb);color:#4b5563;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mode-toggle-wrapper{display:inline-flex;background:#f1f5f9;border-radius:12px;padding:4px;gap:2px;}
.mode-btn{display:inline-block;padding:8px 18px;border-radius:10px;font-size:0.85rem;font-weight:700;color:#64748b;text-decoration:none;transition:all 0.3s;border:none;background:transparent;cursor:pointer;}
.mode-btn:hover{color:var(--p-pink);}
.mode-btn.active{background:#fff;color:var(--p-pink);box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.search-wrapper{position:relative;min-width:260px;max-width:420px;flex:1;}
.search-box{position:relative;display:flex;align-items:center;}
.search-box i.bi-search{position:absolute;left:14px;color:#94a3b8;font-size:0.9rem;}
.search-input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:10px 36px 10px 40px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.search-input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.search-clear{position:absolute;right:14px;color:#94a3b8;text-decoration:none;font-size:0.9rem;}
.search-clear:hover{color:var(--p-pink);}
.btn-filter-trigger{background:#fff;border:2px solid var(--light-pink);color:var(--p-pink);padding:10px 18px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;}
.btn-filter-trigger:hover{background:var(--p-pink);color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(216,63,103,0.2);}
.period-navigator{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;}
.nav-arrow{width:36px;height:36px;border-radius:50%;background:#fff;border:2px solid #f1f5f9;color:#4a5568;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all 0.3s;font-size:0.9rem;}
.nav-arrow:hover{background:var(--light-pink);border-color:var(--p-pink);color:var(--p-pink);}
.period-label{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.05rem;color:var(--text-dark);background:#fff;padding:10px 24px;border-radius:14px;border:2px solid #f1f5f9;box-shadow:0 2px 8px rgba(0,0,0,0.02);}
.period-label i{color:var(--p-pink);font-size:1.1rem;}
.filter-card{background:#fff;border-radius:22px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 8px 24px rgba(216,63,103,0.03);padding:24px;margin-bottom:25px;}
.filter-label{font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px;display:block;}
.filter-input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.filter-input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.btn-preview{background:#fff;border:2px solid var(--light-pink);color:var(--p-pink);padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-preview:hover{background:var(--p-pink);color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(216,63,103,0.2);}
.btn-export-pdf{background:#fff;border:2px solid #fee2e2;color:#dc2626;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-pdf:hover{background:#dc2626;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(220,38,38,0.2);}
.btn-export-excel{background:#fff;border:2px solid #d1fae5;color:#059669;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-excel:hover{background:#059669;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(5,150,105,0.2);}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;}
.data-table thead th:last-child{padding-right:24px;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}
.td-order-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-detail{font-size:0.8rem;color:#718096;font-weight:600;}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:30px;padding:20px 24px;background:#fff;border-radius:20px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 4px 15px rgba(216,63,103,0.04);flex-wrap:wrap;gap:12px;}
.pagination-info{font-size:0.85rem;color:#718096;font-weight:600;}
.pagination-info span{color:var(--p-pink);font-weight:700;}
.pagination-nav{display:flex;gap:6px;align-items:center;}
.page-link-pag{display:flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 14px;border-radius:12px;background:#fff;border:2px solid #fff5f7;color:#4a5568;font-weight:700;font-size:0.9rem;text-decoration:none;transition:all 0.4s;}
.page-link-pag:hover{background:var(--light-pink);border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px);}
.page-link-pag.active-pag{background:linear-gradient(135deg,var(--p-pink),var(--d-pink))!important;color:#fff!important;border-color:var(--p-pink)!important;box-shadow:0 4px 12px rgba(216,63,103,0.3);}
.page-link-pag.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none;}
.summary-card{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:22px;padding:24px;color:#fff;margin-bottom:25px;}
.summary-title{font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:0.9;margin-bottom:8px;}
.summary-value{font-size:2rem;font-weight:800;margin-bottom:4px;}
.summary-subtitle{font-size:0.85rem;opacity:0.8;font-weight:600;}
.empty-state{text-align:center;padding:50px 20px;}
.empty-state i{font-size:3rem;color:#cbd5e1;margin-bottom:15px;display:block;}
.empty-state p{font-weight:700;color:#94a3b8;margin-bottom:5px;}
.empty-state small{color:#cbd5e1;font-weight:600;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
.kop-surat{border-bottom:3px solid var(--p-pink);margin-bottom:20px;}
.kop-surat h4{margin:0;font-weight:800;color:var(--text-dark);letter-spacing:-0.5px;font-size:1.3rem;}
.kop-surat p{margin:4px 0 0;font-size:0.8rem;color:var(--text-muted);font-weight:600;}
.preview-table{width:100%;border-collapse:collapse;font-size:0.8rem;}
.preview-table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:800;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
.preview-table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap;}
.modal-content{border-radius:24px !important;border:none !important;}
.appearance-none{-webkit-appearance:none;-moz-appearance:none;appearance:none;}
@media(max-width:992px){.main-content{margin-left:0;padding:20px;}.sidebar{transform:translateX(-100%);}}
@media(max-width:768px){.search-wrapper{min-width:100%;}.mode-toggle-wrapper{width:100%;justify-content:center;}}
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
        <span><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</span>
    </a>
</li>
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLaporan"><span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuLaporan">
<ul class="list-unstyled">
<li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
<li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
<li><a href="index.php" class="submenu-link active"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
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
<h3 class="fw-bold mb-1">Laporan Pembatalan</h3>
<p class="text-muted small mb-0">Menganalisis booking yang batal beserta alasan dan keterangannya.</p>
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
<div class="summary-title">Total Booking Batal</div>
<div class="summary-value"><?= $total_batal ?> Booking</div>
<div class="summary-subtitle">Periode <?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?> &bull; <?= $total_belum_bayar_dp ?> Belum Bayar DP &bull; <?= $total_dp_ditolak ?> DP Ditolak</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">Dibatalkan Pelanggan</div>
<div class="summary-value" style="font-size:1.5rem;"><?= $total_dibatalkan_plg ?> Booking</div>
</div>
</div>
</div>

<!-- STAT CARDS -->
<div class="stats-scroll-wrapper fade-in-up">
<div class="stats-row">
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-x-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Total Batal</div>
<div class="stat-val"><?= $total_batal ?> Booking</div>
<div class="stat-subtitle">Seluruh kasus batal</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-red"><i class="bi bi-x-circle-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Belum Bayar DP</div>
<div class="stat-val"><?= $total_belum_bayar_dp ?> Booking</div>
<div class="stat-subtitle">Tidak ada pembayaran DP</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-orange"><i class="bi bi-exclamation-octagon-fill"></i></div>
<div class="stat-content">
<div class="stat-title">DP Ditolak</div>
<div class="stat-val"><?= $total_dp_ditolak ?> Booking</div>
<div class="stat-subtitle">Bukti transfer ditolak</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-blue"><i class="bi bi-person-x-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Dibatalkan Pelanggan</div>
<div class="stat-val"><?= $total_dibatalkan_plg ?> Booking</div>
<div class="stat-subtitle">DP valid tapi dibatalkan</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-gray"><i class="bi bi-shield-exclamation"></i></div>
<div class="stat-content">
<div class="stat-title">Dibatalkan Sistem</div>
<div class="stat-val"><?= $total_dibatalkan_sistem ?> Booking</div>
<div class="stat-subtitle">Alasan sistem/lainnya</div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- FILTER, SEARCH, MODE & NAVIGATION -->
<div class="filter-card fade-in-up">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="mode-toggle-wrapper">
            <a href="?mode=bulanan<?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="mode-btn <?= $mode == 'bulanan' ? 'active' : '' ?>">Bulanan</a>
            <a href="?mode=tahunan<?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="mode-btn <?= $mode == 'tahunan' ? 'active' : '' ?>">Tahunan</a>
            <?php if ($mode == 'custom'): ?>
            <span class="mode-btn active">Custom</span>
            <?php endif; ?>
        </div>
        <form method="GET" action="" class="search-wrapper ms-auto">
            <?php if ($mode == 'bulanan'): ?>
            <input type="hidden" name="mode" value="bulanan">
            <input type="hidden" name="bulan" value="<?= $bulan_aktif ?>">
            <input type="hidden" name="tahun_bulan" value="<?= $tahun_bulan ?>">
            <?php elseif ($mode == 'tahunan'): ?>
            <input type="hidden" name="mode" value="tahunan">
            <input type="hidden" name="tahun" value="<?= $tahun_aktif ?>">
            <?php else: ?>
            <input type="hidden" name="mode" value="custom">
            <input type="hidden" name="tgl_mulai" value="<?= $tgl_mulai ?>">
            <input type="hidden" name="tgl_selesai" value="<?= $tgl_selesai ?>">
            <?php endif; ?>
            <?php if ($sort != 'terbaru'): ?><input type="hidden" name="sort" value="<?= $sort ?>"><?php endif; ?>
            <?php if ($alasan_filter): ?><input type="hidden" name="alasan" value="<?= $alasan_filter ?>"><?php endif; ?>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="search-input" placeholder="Cari customer, no order, no hp, paket..." value="<?= $search_escaped ?>" maxlength="100" pattern="[a-zA-Z0-9\s\+\#\-\.\@]*" title="Hanya huruf, angka, spasi, + # - . @">
                <?php if ($search): ?><a href="?<?= $url_params ?>" class="search-clear" title="Hapus pencarian"><i class="bi bi-x-circle-fill"></i></a><?php endif; ?>
            </div>
        </form>
        <button type="button" class="btn-filter-trigger" data-bs-toggle="modal" data-bs-target="#modalFilter">
            <i class="bi bi-funnel-fill"></i> Filter
        </button>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <button type="button" class="btn-preview" onclick="bukaPreviewLaporan()"><i class="bi bi-eye-fill"></i> Lihat Laporan</button>
        <a href="export_pdf.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>&sort=<?= $sort ?>" class="btn-export-pdf"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>
        <a href="export_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>&sort=<?= $sort ?>" class="btn-export-excel"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</a>
    </div>
    <?php if ($mode == 'bulanan'): ?>
    <div class="period-navigator">
        <a href="?mode=bulanan&bulan=<?= $bulan_aktif == 1 ? 12 : $bulan_aktif - 1 ?>&tahun_bulan=<?= $bulan_aktif == 1 ? $tahun_bulan - 1 : $tahun_bulan ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="nav-arrow" title="Bulan Sebelumnya"><i class="bi bi-chevron-left"></i></a>
        <div class="period-label"><i class="bi bi-calendar3"></i><span><?= $nama_bulan[$bulan_aktif] ?> <?= $tahun_bulan ?></span></div>
        <a href="?mode=bulanan&bulan=<?= $bulan_aktif == 12 ? 1 : $bulan_aktif + 1 ?>&tahun_bulan=<?= $bulan_aktif == 12 ? $tahun_bulan + 1 : $tahun_bulan ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="nav-arrow" title="Bulan Berikutnya"><i class="bi bi-chevron-right"></i></a>
    </div>
    <?php elseif ($mode == 'tahunan'): ?>
    <div class="period-navigator">
        <a href="?mode=tahunan&tahun=<?= $tahun_aktif - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="nav-arrow" title="Tahun Sebelumnya"><i class="bi bi-chevron-left"></i></a>
        <div class="period-label"><i class="bi bi-calendar3"></i><span>Tahun <?= $tahun_aktif ?></span></div>
        <a href="?mode=tahunan&tahun=<?= $tahun_aktif + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $sort != 'terbaru' ? '&sort='.$sort : '' ?><?= $alasan_filter ? '&alasan='.$alasan_filter : '' ?>" class="nav-arrow" title="Tahun Berikutnya"><i class="bi bi-chevron-right"></i></a>
    </div>
    <?php else: ?>
    <div class="period-navigator">
        <div class="period-label"><i class="bi bi-calendar3"></i><span><?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?></span></div>
    </div>
    <?php endif; ?>
</div>

<!-- TABEL DATA -->
<div class="card-3d" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead>
<tr>
<th>No</th>
<th>No. Order</th>
<th>Customer</th>
<th>Paket & Ruangan</th>
<th>Tema</th>
<th>Alasan Batal</th>
<th>Keterangan</th>
<th>Verifikator</th>
<th>Tanggal Booking</th>
</tr>
</thead>
<tbody>
<?php
if(count($detail_rows) > 0):
$no = $offset + 1;
foreach($detail_rows as $row):
    $badge = getAlasanBadge($row['Alasan_Batal']);
?>
<tr class="fade-in-up">
<td><?= $no++ ?></td>
<td><div class="td-order-id">#ORD-<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></div></td>
<td>
<div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
<div class="td-customer-contact"><?= htmlspecialchars($row['No_Hp']) ?></div>
</td>
<td>
<div class="td-customer"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
<div class="td-detail"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
</td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Tema']) ?></div></td>
<td>
<span class="badge-status" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;">
    <span class="badge-dot" style="background:<?= $badge['dot'] ?>"></span><?= htmlspecialchars($row['Alasan_Batal']) ?>
</span>
</td>
<td>
<?php if (!empty($row['Keterangan_Order'])): ?>
<div class="td-detail" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($row['Keterangan_Order']) ?>"><?= htmlspecialchars($row['Keterangan_Order']) ?></div>
<?php else: ?>
<span class="text-muted" style="font-size:0.75rem;">-</span>
<?php endif; ?>
</td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Verifikator'] ?? '-') ?></div></td>
<td><div class="td-detail"><?= formatTanggal($row['Tanggal_Booking']) ?></div></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="9">
<div class="empty-state"><i class="bi bi-inbox"></i><p>Tidak ada data pembatalan.</p><small>Belum ada booking yang dibatalkan pada periode ini.</small></div>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if($total_halaman > 1): ?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> data</div>
<nav class="pagination-nav">
<?php if($halaman > 1): ?><a class="page-link-pag" href="?<?= $url_params ?>&halaman=<?= $halaman-1 ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
<?php
$start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2);
if($start_page > 1) { echo '<a class="page-link-pag" href="?'.$url_params.'&halaman=1">1</a>'; if($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; }
for($i = $start_page; $i <= $end_page; $i++):
?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="?<?= $url_params ?>&halaman=<?= $i ?>"><?= $i ?></a><?php endfor;
if($end_page < $total_halaman) { if($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="?'.$url_params.'&halaman='.$total_halaman.'">'.$total_halaman.'</a>'; }
?>
<?php if($halaman < $total_halaman): ?><a class="page-link-pag" href="?<?= $url_params ?>&halaman=<?= $halaman+1 ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
</nav>
</div>
<?php elseif($total_records > 0): ?>
<div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> data</div></div>
<?php endif; ?>
</div>

</div>

<!-- MODAL FILTER -->
<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
<div class="modal-content shadow-lg">
<div class="modal-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--p-pink),var(--d-pink));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;"><i class="bi bi-funnel-fill"></i></div>
            <h5 class="fw-bold mb-0" style="color:var(--text-dark);">Filter Data</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <form method="GET" action="" id="formFilter">
        <input type="hidden" name="mode" value="custom">
        <?php if ($search): ?><input type="hidden" name="search" value="<?= $search_escaped ?>"><?php endif; ?>
        <div class="mb-3">
            <label class="filter-label">Urut Berdasarkan</label>
            <div class="position-relative">
                <select name="sort" class="filter-input appearance-none">
                    <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Tanggal Booking Terbaru</option>
                    <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Tanggal Booking Terlama</option>
                    <option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama Pelanggan (A-Z)</option>
                    <option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Pelanggan (Z-A)</option>
                    <option value="harga_tertinggi" <?= $sort == 'harga_tertinggi' ? 'selected' : '' ?>>Harga Tertinggi</option>
                    <option value="harga_terendah" <?= $sort == 'harga_terendah' ? 'selected' : '' ?>>Harga Terendah</option>
                    <option value="jadwal_terdekat" <?= $sort == 'jadwal_terdekat' ? 'selected' : '' ?>>Tanggal Terdekat</option>
                    <option value="jadwal_terjauh" <?= $sort == 'jadwal_terjauh' ? 'selected' : '' ?>>Tanggal Terjauh</option>
                </select>
                <i class="bi bi-chevron-down position-absolute" style="right:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
            </div>
        </div>
        <div class="mb-3">
            <label class="filter-label">Alasan Batal</label>
            <div class="position-relative">
                <select name="alasan" class="filter-input appearance-none">
                    <option value="" <?= $alasan_filter == '' ? 'selected' : '' ?>>Semua Alasan</option>
                    <option value="belum_bayar_dp" <?= $alasan_filter == 'belum_bayar_dp' ? 'selected' : '' ?>>Belum Bayar DP</option>
                    <option value="dp_ditolak" <?= $alasan_filter == 'dp_ditolak' ? 'selected' : '' ?>>DP Ditolak</option>
                    <option value="dibatalkan_pelanggan" <?= $alasan_filter == 'dibatalkan_pelanggan' ? 'selected' : '' ?>>Dibatalkan Pelanggan</option>
                    <option value="dibatalkan_sistem" <?= $alasan_filter == 'dibatalkan_sistem' ? 'selected' : '' ?>>Dibatalkan Sistem</option>
                </select>
                <i class="bi bi-chevron-down position-absolute" style="right:14px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;"></i>
            </div>
        </div>
        <div class="mb-3">
            <label class="filter-label">Tanggal Dari</label>
            <input type="date" name="tgl_mulai" class="filter-input" value="<?= $tgl_mulai ?>" required>
        </div>
        <div class="mb-4">
            <label class="filter-label">Tanggal Sampai</label>
            <input type="date" name="tgl_selesai" class="filter-input" value="<?= $tgl_selesai ?>" required>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-light flex-fill fw-bold" style="border-radius:14px;padding:12px;color:#64748b;border:2px solid #f1f5f9;"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
            <button type="submit" class="btn flex-fill fw-bold text-white" style="border-radius:14px;padding:12px;background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border:none;"><i class="bi bi-check2 me-1"></i> Terapkan</button>
        </div>
    </form>
</div>
</div>
</div>
</div>

<!-- PREVIEW LAPORAN -->
<div id="previewLaporanContent" class="d-none">
    <div class="kop-surat" style="display:flex;align-items:center;justify-content:center;gap:16px;padding-bottom:16px;">
        <img src="../../assets/img/logo.png" alt="SpotLight Studio" style="height:60px;width:auto;flex-shrink:0;">
        <div>
            <h4 style="margin:0;font-weight:800;color:var(--text-dark);letter-spacing:-0.5px;font-size:1.3rem;">SpotLight Studio</h4>
            <p style="margin:4px 0 0;font-size:0.8rem;color:var(--text-muted);font-weight:600;">Laporan Pembatalan &bull; Periode <?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?></p>
        </div>
    </div>
    <div class="row mb-3 g-2">
        <div class="col-3"><div style="background:#f8fafc;border-radius:12px;padding:12px;text-align:center;"><div style="font-size:0.68rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Total Batal</div><div style="font-size:1.1rem;font-weight:800;color:var(--p-pink);"><?= $total_batal ?></div></div></div>
        <div class="col-3"><div style="background:#f8fafc;border-radius:12px;padding:12px;text-align:center;"><div style="font-size:0.68rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Belum Bayar DP</div><div style="font-size:1.1rem;font-weight:800;color:#dc2626;"><?= $total_belum_bayar_dp ?></div></div></div>
        <div class="col-3"><div style="background:#f8fafc;border-radius:12px;padding:12px;text-align:center;"><div style="font-size:0.68rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">DP Ditolak</div><div style="font-size:1.1rem;font-weight:800;color:#d97706;"><?= $total_dp_ditolak ?></div></div></div>
        <div class="col-3"><div style="background:#f8fafc;border-radius:12px;padding:12px;text-align:center;"><div style="font-size:0.68rem;color:#94a3b8;font-weight:700;text-transform:uppercase;">Dibatalkan Plg</div><div style="font-size:1.1rem;font-weight:800;color:#2563eb;"><?= $total_dibatalkan_plg ?></div></div></div>
    </div>
    <div style="max-height:340px;overflow:auto;border:1px solid #f1f5f9;border-radius:12px;">
    <table class="preview-table">
        <thead><tr><th>No</th><th>No. Order</th><th>Customer</th><th>Paket</th><th>Ruangan</th><th>Tema</th><th>Alasan Batal</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php if (empty($preview_rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada data pada periode ini.</td></tr>
        <?php else: $pno = 1; foreach ($preview_rows as $pr): $pbadge = getAlasanBadge($pr['Alasan_Batal']); ?>
            <tr>
                <td><?= $pno++ ?></td>
                <td>#ORD-<?= str_pad((int)$pr['ID_Order'], 5, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($pr['Nama_Pelanggan']) ?></td>
                <td><?= htmlspecialchars($pr['Nama_Paket']) ?></td>
                <td><?= htmlspecialchars($pr['Nama_Ruangan']) ?></td>
                <td><?= htmlspecialchars($pr['Nama_Tema']) ?></td>
                <td><span style="font-size:0.75rem;font-weight:700;color:<?= $pbadge['color'] ?>;"><?= htmlspecialchars($pr['Alasan_Batal']) ?></span></td>
                <td><?= htmlspecialchars($pr['Keterangan_Order'] ?? '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:24px;">
        <div style="text-align:center;min-width:180px;">
            <p style="margin:0 0 4px;font-size:0.8rem;color:#4a5568;font-weight:600;">tanggal <?= date('d M Y') ?></p>
            <p style="margin:0 0 24px;font-size:0.8rem;color:#4a5568;font-weight:600;">Approval</p>
            <p style="margin:32px 0 0;font-size:0.8rem;color:#4a5568;font-weight:700;text-decoration:underline;">Owner</p>
            <p style="margin:4px 0 0;font-size:0.8rem;color:#4a5568;font-weight:700;"><?= htmlspecialchars($nama_owner) ?></p>
        </div>
    </div>
    <p class="text-muted mt-2 mb-0" style="font-size:0.75rem;">Total <?= count($preview_rows) ?> booking batal pada periode ini.</p>
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
            if(!isShown) { targetEl.classList.add('show'); if(chevron) chevron.style.transform = 'rotate(180deg)'; }
        }
    });
});
function bukaPreviewLaporan() {
    const content = document.getElementById('previewLaporanContent').innerHTML;
    Swal.fire({ title: false, html: content, width: 'min(90vw, 780px)', showCloseButton: true, showConfirmButton: true, confirmButtonText: '<i class="bi bi-check2"></i> Tutup', confirmButtonColor: '#d83f67', customClass: { popup: 'text-start' } });
}
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if(result.isConfirmed) window.location.href = '../../logout.php'; });
}
function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if(result.isConfirmed) window.location.href = '../../index.php'; });
}
function bukaModalBiodata() {
    Swal.fire({ title: '<?= htmlspecialchars($nama_owner) ?>', text: 'Owner - SpotLight Studio', icon: 'info', confirmButtonColor: '#d83f67' });
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