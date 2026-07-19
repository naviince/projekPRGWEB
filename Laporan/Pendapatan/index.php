<?php
session_start();

// =====================================================
// DEBUG MODE - Hapus atau set false di production
// =====================================================
$debug_mode = false;

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_LUNAS', 2);
define('STATUS_ORDER_SELESAI', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// --- PROTEKSI HALAMAN: HANYA OWNER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

// --- INCLUDE KONEKSI DENGAN ERROR HANDLING ---
if (!file_exists('../../koneksi.php')) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: File koneksi.php tidak ditemukan!</h2><p>Path: ../../koneksi.php</p><p>Pastikan file koneksi.php ada di root folder projek.</p></div>');
}
include '../../koneksi.php';

// --- CEK KONEKSI DATABASE ---
if (!isset($conn) || $conn === false) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: Koneksi database gagal!</h2><p>Pastikan SQL Server berjalan dan konfigurasi koneksi benar.</p></div>');
}

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// =====================================================
// FUNGSI HELPER UNTUK QUERY DENGAN ERROR HANDLING
// =====================================================
function executeQuery($conn, $sql, $params = [], $debug = false) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        $errorMsg = "Query Error:<br>";
        if ($errors) {
            foreach ($errors as $error) {
                $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . " | Code: " . $error['code'] . " | Message: " . $error['message'] . "<br>";
            }
        } else {
            $errorMsg .= "Unknown error occurred.";
        }
        if ($debug) {
            $errorMsg .= "<br><strong>SQL:</strong> " . htmlspecialchars($sql) . "<br>";
            $errorMsg .= "<strong>Params:</strong> " . htmlspecialchars(print_r($params, true));
        }
        return ['error' => $errorMsg];
    }
    return ['success' => true, 'stmt' => $stmt];
}

function fetchSingle($conn, $sql, $params = [], $debug = false) {
    $result = executeQuery($conn, $sql, $params, $debug);
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    $row = sqlsrv_fetch_array($result['stmt'], SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($result['stmt']);
    return ['success' => true, 'data' => $row];
}

// =====================================================
// AMBIL DATA PROFIL OWNER
// =====================================================
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
// FILTER TANGGAL
// =====================================================
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// =====================================================
// PAGINATION
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

// =====================================================
// QUERY STATISTIK PENDAPATAN
// =====================================================

// Total Pendapatan Pelunasan
$sql_total = "SELECT SUM(p.Jumlah_Bayar) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?";
$result_total = fetchSingle($conn, $sql_total, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai], $debug_mode);
$total_pendapatan = (!$result_total || isset($result_total['error'])) ? 0 : ($result_total['data']['total'] ?? 0);

// Jumlah Order Lunas
$sql_jumlah = "SELECT COUNT(DISTINCT p.ID_Order) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?";
$result_jumlah = fetchSingle($conn, $sql_jumlah, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai], $debug_mode);
$jumlah_order = (!$result_jumlah || isset($result_jumlah['error'])) ? 0 : ($result_jumlah['data']['total'] ?? 0);

// Rata-rata Pendapatan per Order
$rata_rata = $jumlah_order > 0 ? $total_pendapatan / $jumlah_order : 0;

// Jumlah Pelanggan Unik
$sql_pelanggan = "SELECT COUNT(DISTINCT o.ID_Pelanggan) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?";
$result_pelanggan = fetchSingle($conn, $sql_pelanggan, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai], $debug_mode);
$jumlah_pelanggan = (!$result_pelanggan || isset($result_pelanggan['error'])) ? 0 : ($result_pelanggan['data']['total'] ?? 0);

// Pendapatan Hari Ini
$sql_hari_ini = "SELECT SUM(p.Jumlah_Bayar) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) = CAST(GETDATE() AS DATE)";
$result_hari_ini = fetchSingle($conn, $sql_hari_ini, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI], $debug_mode);
$pendapatan_hari_ini = (!$result_hari_ini || isset($result_hari_ini['error'])) ? 0 : ($result_hari_ini['data']['total'] ?? 0);

// =====================================================
// QUERY DATA DETAIL (Dengan Pagination)
// =====================================================
$sql_count = "SELECT COUNT(*) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?";
$result_count = fetchSingle($conn, $sql_count, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai], $debug_mode);
$total_records = (!$result_count || isset($result_count['error'])) ? 0 : ($result_count['data']['total'] ?? 0);
$total_halaman = ceil($total_records / $limit);

// Query detail dengan JOIN pelanggan dan paket
$sql_detail = "SELECT p.ID_Pembayaran, p.ID_Order, p.Jumlah_Bayar, p.Metode_Pembayaran, p.Tanggal_Upload, p.Bukti_Transfer, p.ID_Karyawan_Verifikator, pl.Nama_Pelanggan, pl.No_Hp, pl.Email_Pelanggan, pk.Nama_Paket, r.Nama_Ruangan, t.Nama_Tema, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, o.Tanggal_Booking, o.Status_Order, k.Nama_Karyawan as Nama_Verifikator FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ? ORDER BY p.Tanggal_Upload DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$result_detail = executeQuery($conn, $sql_detail, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai, $offset, $limit], $debug_mode);
$query = isset($result_detail['stmt']) ? $result_detail['stmt'] : null;
$query_error = isset($result_detail['error']) ? $result_detail['error'] : null;

function formatTanggal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y H:i');
    }
    return date('d M Y H:i', strtotime($dateObj));
}

function getStatusOrderLabel($s) {
    $l = [0=>['Menunggu DP','#d97706'], 1=>['DP Terverifikasi','#059669'], 2=>['Lunas','#2563eb'], 3=>['Selesai','#7c3aed'], 4=>['Dibatalkan','#dc2626']];
    return $l[$s] ?? ['Unknown','#718096'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pendapatan - SpotLight Studio</title>
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
.stat-icon-purple{background:linear-gradient(135deg,#f5f3ff,#ede9fe);color:#7c3aed;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.filter-card{background:#fff;border-radius:22px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 8px 24px rgba(216,63,103,0.03);padding:24px;margin-bottom:25px;}
.filter-row{display:flex;align-items:end;gap:16px;flex-wrap:wrap;}
.filter-group{flex:1;min-width:200px;}
.filter-label{font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px;display:block;}
.filter-input{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.filter-input:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(216,63,103,0.08);}
.btn-filter{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;padding:12px 24px;border-radius:14px;font-weight:800;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;}
.btn-filter:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(216,63,103,0.25);}
.btn-export-pdf{background:#fff;border:2px solid #fee2e2;color:#dc2626;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-pdf:hover{background:#dc2626;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(220,38,38,0.2);}
.btn-export-excel{background:#fff;border:2px solid #d1fae5;color:#059669;padding:12px 20px;border-radius:14px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;display:inline-flex;align-items:center;gap:8px;text-decoration:none;}
.btn-export-excel:hover{background:#059669;color:#fff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(5,150,105,0.2);}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:1200px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;}
.data-table thead th:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}
.td-pembayaran-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-jumlah{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-detail{font-size:0.8rem;color:#718096;font-weight:600;}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.badge-lunas{background:#ecfdf5;color:#059669;}
.badge-selesai{background:#dbeafe;color:#2563eb;}
.bukti-thumb{width:50px;height:50px;border-radius:10px;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;transition:all 0.3s;}
.bukti-thumb:hover{transform:scale(1.05);border-color:var(--p-pink);}
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:30px;padding:20px 24px;background:#fff;border-radius:20px;border:1px solid rgba(255,236,239,0.8);box-shadow:0 4px 15px rgba(216,63,103,0.04);}
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
.error-box{background:#fef2f2;border:2px solid #fecaca;border-radius:16px;padding:20px;margin-bottom:20px;color:#dc2626;}
.error-box h4{color:#dc2626;font-weight:800;margin-bottom:10px;}
.empty-state{text-align:center;padding:50px 20px;}
.empty-state i{font-size:3rem;color:#cbd5e1;margin-bottom:15px;display:block;}
.empty-state p{font-weight:700;color:#94a3b8;margin-bottom:5px;}
.empty-state small{color:#cbd5e1;font-weight:600;}
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
        <span>
            <i class="bi bi-person-badge-fill me-2"></i>
            Kelola Karyawan
        </span>
    </a>
</li>
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLaporan"><span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuLaporan">
<ul class="list-unstyled">
<li><a href="index.php" class="submenu-link active"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
<li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
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
<h3 class="fw-bold mb-1">Laporan Pendapatan</h3>
<p class="text-muted small mb-0">Pantau arus kas masuk dari pembayaran pelunasan customer.</p>
</div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_owner_src ?>" alt="Owner Profil"></div>
</div>
</div>

<?php if ($query_error): ?>
<!-- TAMPILKAN ERROR JIKA ADA -->
<div class="error-box fade-in-up">
<h4><i class="bi bi-exclamation-triangle-fill me-2"></i>Terjadi Error pada Query Database</h4>
<div><?= $query_error ?></div>
<p class="mt-2 mb-0" style="font-size:0.85rem;"><strong>Saran:</strong> Pastikan semua tabel dan kolom sudah benar. Jika error berlanjut, hubungi administrator database.</p>
</div>
<?php endif; ?>

<!-- SUMMARY CARD -->
<div class="summary-card fade-in-up">
<div class="row align-items-center">
<div class="col-lg-8">
<div class="summary-title">Total Pendapatan Pelunasan</div>
<div class="summary-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
<div class="summary-subtitle">Periode <?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?> &bull; <?= $jumlah_order ?> Order Lunas &bull; <?= $jumlah_pelanggan ?> Pelanggan</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">Pendapatan Hari Ini</div>
<div class="summary-value" style="font-size:1.5rem;">Rp <?= number_format($pendapatan_hari_ini, 0, ',', '.') ?></div>
</div>
</div>
</div>

<!-- STAT CARDS -->
<div class="stats-scroll-wrapper fade-in-up">
<div class="stats-row">
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-cash-coin"></i></div>
<div class="stat-content">
<div class="stat-title">Total Pendapatan</div>
<div class="stat-val">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
<div class="stat-subtitle">Dari pelunasan valid</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Order Lunas</div>
<div class="stat-val"><?= $jumlah_order ?> Order</div>
<div class="stat-subtitle">Sudah pelunasan</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-blue"><i class="bi bi-people-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Pelanggan</div>
<div class="stat-val"><?= $jumlah_pelanggan ?> Orang</div>
<div class="stat-subtitle">Pelunasan unik</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-purple"><i class="bi bi-calculator-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Rata-rata/Order</div>
<div class="stat-val">Rp <?= number_format($rata_rata, 0, ',', '.') ?></div>
<div class="stat-subtitle">Per order lunas</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-orange"><i class="bi bi-calendar-check-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Hari Ini</div>
<div class="stat-val">Rp <?= number_format($pendapatan_hari_ini, 0, ',', '.') ?></div>
<div class="stat-subtitle"><?= date('d M Y') ?></div>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- FILTER & EXPORT -->
<div class="filter-card fade-in-up">
<form method="GET" action="" class="filter-row">
<div class="filter-group">
<label class="filter-label">Tanggal Mulai</label>
<input type="date" name="tgl_mulai" class="filter-input" value="<?= $tgl_mulai ?>" required>
</div>
<div class="filter-group">
<label class="filter-label">Tanggal Selesai</label>
<input type="date" name="tgl_selesai" class="filter-input" value="<?= $tgl_selesai ?>" required>
</div>
<button type="submit" class="btn-filter"><i class="bi bi-funnel-fill"></i> Terapkan Filter</button>
<a href="export_pdf.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" class="btn-export-pdf" target="_blank"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>
<a href="export_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" class="btn-export-excel"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</a>
</form>
</div>

<!-- TABEL DATA -->
<div class="card-3d" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead>
<tr>
<th>No</th>
<th>No. Pembayaran</th>
<th>No. Order</th>
<th>Customer</th>
<th>Paket</th>
<th>Ruangan</th>
<th>Tema</th>
<th>Metode</th>
<th>Jumlah</th>
<th>Tanggal Pelunasan</th>
<th>Status</th>
<th>Verifikator</th>
</tr>
</thead>
<tbody>
<?php
if($query && sqlsrv_has_rows($query)):
$no = $offset + 1;
while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
    $statusInfo = getStatusOrderLabel((int)$row['Status_Order']);
?>
<tr class="fade-in-up">
<td><?= $no++ ?></td>
<td><div class="td-pembayaran-id">#<?= str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT) ?></div></td>
<td><div class="td-detail">#ORD-<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></div></td>
<td>
<div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
<div class="td-customer-contact"><?= htmlspecialchars($row['No_Hp']) ?></div>
</td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Paket']) ?></div></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Tema']) ?></div></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Metode_Pembayaran']) ?></div></td>
<td><div class="td-jumlah">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></div></td>
<td><div class="td-detail"><?= formatTanggal($row['Tanggal_Upload']) ?></div></td>
<td><span class="badge-status <?= (int)$row['Status_Order'] === 2 ? 'badge-lunas' : 'badge-selesai' ?>"><span class="badge-dot" style="background:<?= $statusInfo[1] ?>"></span><?= $statusInfo[0] ?></span></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Verifikator'] ?? 'System') ?></div></td>
</tr>
<?php endwhile; else: ?>
<tr>
<td colspan="12">
<div class="empty-state">
<i class="bi bi-inbox"></i>
<p>Tidak ada data pendapatan pelunasan.</p>
<small>Belum ada pembayaran pelunasan pada periode ini.</small>
</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($total_halaman > 1): ?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> data</div>
<nav class="pagination-nav">
<?php if($halaman > 1): ?>
<a class="page-link-pag" href="?halaman=<?= $halaman-1 ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a>
<?php else: ?>
<span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
<?php endif; ?>

<?php 
$start_page = max(1, $halaman - 2);
$end_page = min($total_halaman, $halaman + 2);
if($start_page > 1) {
    echo '<a class="page-link-pag" href="?halaman=1&tgl_mulai='.$tgl_mulai.'&tgl_selesai='.$tgl_selesai.'">1</a>';
    if($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
}
for($i = $start_page; $i <= $end_page; $i++): 
?>
<a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="?halaman=<?= $i ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>"><?= $i ?></a>
<?php endfor; 
if($end_page < $total_halaman) {
    if($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
    echo '<a class="page-link-pag" href="?halaman='.$total_halaman.'&tgl_mulai='.$tgl_mulai.'&tgl_selesai='.$tgl_selesai.'">'.$total_halaman.'</a>';
}
?>

<?php if($halaman < $total_halaman): ?>
<a class="page-link-pag" href="?halaman=<?= $halaman+1 ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a>
<?php else: ?>
<span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span>
<?php endif; ?>
</nav>
</div>
<?php elseif($total_records > 0): ?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> data</div>
</div>
<?php endif; ?>
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
</script>
</body>
</html>