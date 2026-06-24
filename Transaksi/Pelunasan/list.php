<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$default_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) ? "../../assets/img/pelanggan/" . $foto_admin : $default_svg;

$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$tab_filter = isset($_GET['tab']) ? trim($_GET['tab']) : "semua";

// Statistik
$q_stats = "SELECT COUNT(*) as total, SUM(CASE WHEN Status_Pembayaran = 0 THEN 1 ELSE 0 END) as menunggu, SUM(CASE WHEN Status_Pembayaran = 1 THEN 1 ELSE 0 END) as valid, SUM(CASE WHEN Status_Pembayaran = 2 THEN 1 ELSE 0 END) as ditolak FROM Pembayaran WHERE Status = 1 AND Tipe_Pembayaran = 'Pelunasan'";
$stmt_stats = sqlsrv_query($conn, $q_stats);
$stats = ['total'=>0,'menunggu'=>0,'valid'=>0,'ditolak'=>0];
if ($stmt_stats !== false) {
    $row = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    if ($row) $stats = $row;
}

// Query list
$conditions = ["p.Status = 1", "p.Tipe_Pembayaran = 'Pelunasan'"];
$params = [];
if ($tab_filter === 'menunggu') {
    $conditions[] = "p.Status_Pembayaran = 0";
} elseif ($tab_filter === 'valid') {
    $conditions[] = "p.Status_Pembayaran = 1";
} elseif ($tab_filter === 'ditolak') {
    $conditions[] = "p.Status_Pembayaran = 2";
}
if (!empty($cari)) {
    $conditions[] = "(pl.Nama_Pelanggan LIKE ? OR CAST(p.ID_Pembayaran AS VARCHAR) LIKE ? OR CAST(o.ID_Order AS VARCHAR) LIKE ?)";
    $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
}
$where = implode(" AND ", $conditions);

$sql_count = "SELECT COUNT(*) AS total FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan WHERE $where";
$q_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0; $total_halaman = 0;
if ($q_count !== false) {
    $r = sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC);
    $total_records = $r['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

$sql_list = "SELECT p.ID_Pembayaran, p.ID_Order, p.Tipe_Pembayaran, p.Metode_Pembayaran, p.Jumlah_Bayar, p.Bukti_Transfer, p.Tanggal_Upload, p.Status_Pembayaran, p.ID_Karyawan_Verifikator, pl.Nama_Pelanggan, pl.No_Hp, pl.Email_Pelanggan, o.Status_Order, k.Nama_Karyawan as Nama_Verifikator FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan WHERE $where ORDER BY p.Tanggal_Upload DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$p_list = $params; $p_list[] = $offset; $p_list[] = $limit;
$query = sqlsrv_query($conn, $sql_list, $p_list);

function getStatusPembayaranLabel($s) {
    $l = [0=>['Menunggu','#d97706','#fffbeb','bi-hourglass-split'],1=>['Valid','#059669','#d1fae5','bi-check-circle-fill'],2=>['Ditolak','#dc2626','#fee2e2','bi-x-circle-fill']];
    return $l[$s] ?? ['Unknown','#718096','#f1f5f9','bi-question-circle'];
}
function getStatusOrderLabel($s) {
    $l = [0=>['Menunggu DP','#d97706'],1=>['DP Terverifikasi','#059669'],2=>['Selesai','#2563eb'],3=>['Lunas','#7c3aed'],4=>['Dibatalkan','#dc2626']];
    return $l[$s] ?? ['Unknown','#718096'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Pelunasan - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#D53D66;--d-pink:#CA3366;--s-pink:#FFF0F3;--light-pink:#FFE4E9;--text-dark:#1e1e24;--text-muted:#718096;--body-bg:#f8fafc;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--body-bg);color:var(--text-dark);}
.sidebar{width:260px;height:100vh;background:#fff;position:fixed;top:0;left:0;border-right:1px solid rgba(255,228,233,0.8);display:flex;flex-direction:column;justify-content:space-between;padding:30px 20px;z-index:100;}
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
.submenu-link:hover,.submenu-link.active{color:var(--p-pink);background:rgba(213,61,102,0.03);padding-left:22px;}
.btn-logout{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;width:100%;padding:12px;border-radius:12px;font-weight:800;font-size:0.85rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
.btn-logout:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(213,61,102,0.2);}
.main-content{margin-left:260px;padding:40px;min-height:100vh;}
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px;}
.profile-header-btn{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #fff;cursor:pointer;transition:all 0.4s;background:#fff;}
.profile-header-btn:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 20px rgba(213,61,102,0.15);border-color:var(--p-pink);}
.profile-header-btn img{width:100%;height:100%;object-fit:cover;}
.stats-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:10px;margin-bottom:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.stats-scroll-wrapper::-webkit-scrollbar{height:6px;}
.stats-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.stats-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.stats-row{display:flex;gap:16px;min-width:max-content;}
.stat-card-item{min-width:220px;max-width:280px;flex:0 0 auto;}
.card-3d{background:#fff;border-radius:22px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 8px 24px rgba(213,61,102,0.03);transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);padding:20px;height:100%;position:relative;overflow:hidden;}
.card-3d:hover{transform:translateY(-8px) scale(1.01);box-shadow:0 22px 45px rgba(213,61,102,0.14);border-color:var(--p-pink);}
.stat-card{display:flex;align-items:center;gap:14px;}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:all 0.4s;flex-shrink:0;}
.stat-icon-pink{background:linear-gradient(135deg,#FFF0F3,#FFE4E9);color:#D53D66;}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;}
.tab-filter-bar{display:flex;gap:10px;margin-bottom:25px;flex-wrap:wrap;}
.tab-btn{padding:10px 20px;border-radius:14px;border:2px solid #e2e8f0;background:#fff;color:#4a5568;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
.tab-btn:hover{border-color:var(--p-pink);color:var(--p-pink);}
.tab-btn.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);box-shadow:0 4px 12px rgba(213,61,102,0.2);}
.tab-btn .tab-count{background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:50px;font-size:0.75rem;}
.search-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:25px;flex-wrap:wrap;}
.search-form-flex{display:flex;align-items:center;gap:10px;flex:1;min-width:300px;}
.search-input-wrapper{position:relative;flex:1;}
.search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;z-index:2;}
.search-input-main{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 18px 12px 44px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.search-input-main:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(213,61,102,0.08);}
.btn-search-icon{background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;color:#94a3b8;cursor:pointer;transition:all 0.4s;display:flex;align-items:center;justify-content:center;}
.btn-search-icon:hover{border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px);}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;}
.data-table thead th:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr:nth-child(even){background-color:#FFF8F0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#FFEDD5!important;transform:scale(1.002);}
.td-pembayaran-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-order{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;font-weight:600;}
.td-jumlah{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.btn-action-circle{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;transition:all 0.4s;border:1.5px solid #eef2f6;background:#fff;font-size:0.85rem;text-decoration:none;margin:0 2px;cursor:pointer;}
.btn-action-terima{color:#059669;border-color:#d1fae5;}
.btn-action-terima:hover{background:#059669;color:#fff;transform:translateY(-2px);}
.btn-action-tolak{color:#dc2626;border-color:#fee2e2;}
.btn-action-tolak:hover{background:#dc2626;color:#fff;transform:translateY(-2px);}
.btn-action-view{color:#D53D66;border-color:#FFE4E9;}
.btn-action-view:hover{background:#D53D66;color:#fff;transform:translateY(-2px);}
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:30px;padding:20px 24px;background:#fff;border-radius:20px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 4px 15px rgba(213,61,102,0.04);}
.pagination-info{font-size:0.85rem;color:#718096;font-weight:600;}
.pagination-info span{color:var(--p-pink);font-weight:700;}
.pagination-nav{display:flex;gap:6px;align-items:center;}
.page-link-pag{display:flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 14px;border-radius:12px;background:#fff;border:2px solid #FFF5F7;color:#4a5568;font-weight:700;font-size:0.9rem;text-decoration:none;transition:all 0.4s;}
.page-link-pag:hover{background:var(--light-pink);border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px);}
.page-link-pag.active-pag{background:linear-gradient(135deg,var(--p-pink),var(--d-pink))!important;color:#fff!important;border-color:var(--p-pink)!important;box-shadow:0 4px 12px rgba(213,61,102,0.3);}
.page-link-pag.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
.bukti-thumb{width:60px;height:60px;border-radius:10px;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;transition:all 0.3s;}
.bukti-thumb:hover{transform:scale(1.05);border-color:var(--p-pink);}
@media(max-width:992px){.main-content{margin-left:0;padding:20px;}.sidebar{transform:translateX(-100%);}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="sidebar-menu-wrapper">
<a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
<ul class="nav-menu">
<li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>

<!-- DATA MASTER -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
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

<!-- TRANSAKSI - URUTAN BERURUTAN -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuTransaksi"><span><i class="bi bi-cart-fill me-2"></i> Transaksi</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuTransaksi">
<ul class="list-unstyled">
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="list.php" class="submenu-link active"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
</ul>
</div>
</li>

<!-- SESI FOTO (VIEW ONLY) -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuSesi"><span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
<div class="submenu" id="submenuSesi">
<ul class="list-unstyled">
<li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-eye-fill me-2"></i>Lihat Sesi Foto</a></li>
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
<div class="dashboard-header">
<div><h3 class="fw-bold mb-1">Verifikasi Pelunasan</h3><p class="text-muted small mb-0">Verifikasi bukti transfer pelunasan dari customer.</p></div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<div class="stats-scroll-wrapper animate-fade-in">
<div class="stats-row">
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-cash-stack"></i></div><div class="stat-content"><div class="stat-title">Total Pelunasan</div><div class="stat-val"><?= $stats['total']??0 ?> Pembayaran</div><div class="stat-subtitle">Semua pembayaran pelunasan</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-hourglass-split"></i></div><div class="stat-content"><div class="stat-title">Menunggu</div><div class="stat-val"><?= $stats['menunggu']??0 ?> Pembayaran</div><div class="stat-subtitle">Perlu diverifikasi</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Valid</div><div class="stat-val"><?= $stats['valid']??0 ?> Pembayaran</div><div class="stat-subtitle">Pelunasan diterima</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-x-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Ditolak</div><div class="stat-val"><?= $stats['ditolak']??0 ?> Pembayaran</div><div class="stat-subtitle">Perlu upload ulang</div></div></div></div></div>
</div>
</div>

<div class="tab-filter-bar">
<a href="list.php?tab=semua" class="tab-btn <?= $tab_filter==='semua'?'active':'' ?>"><i class="bi bi-grid-fill"></i> Semua <span class="tab-count"><?= $stats['total']??0 ?></span></a>
<a href="list.php?tab=menunggu" class="tab-btn <?= $tab_filter==='menunggu'?'active':'' ?>"><i class="bi bi-hourglass-split"></i> Menunggu <span class="tab-count"><?= $stats['menunggu']??0 ?></span></a>
<a href="list.php?tab=valid" class="tab-btn <?= $tab_filter==='valid'?'active':'' ?>"><i class="bi bi-check-circle-fill"></i> Valid <span class="tab-count"><?= $stats['valid']??0 ?></span></a>
<a href="list.php?tab=ditolak" class="tab-btn <?= $tab_filter==='ditolak'?'active':'' ?>"><i class="bi bi-x-circle-fill"></i> Ditolak <span class="tab-count"><?= $stats['ditolak']??0 ?></span></a>
</div>

<div class="search-filter-bar">
<form method="GET" class="search-form-flex" id="mainSearchForm">
<input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter) ?>">
<div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari nama customer, no. pembayaran, atau no. order..." value="<?= htmlspecialchars($cari) ?>"></div>
<button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
</form>
</div>

<div class="card-3d mb-4" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead><tr><th>No. Pembayaran</th><th>Customer</th><th>No. Order</th><th>Metode</th><th>Jumlah</th><th>Bukti</th><th>Tanggal Upload</th><th>Status</th><th>Verifikator</th><th class="text-center">Aksi</th></tr></thead>
<tbody>
<?php
if($query&&sqlsrv_has_rows($query)):
while($row=sqlsrv_fetch_array($query,SQLSRV_FETCH_ASSOC)):
$statusInfo=getStatusPembayaranLabel((int)$row['Status_Pembayaran']);
$orderStatusInfo=getStatusOrderLabel((int)$row['Status_Order']);
?>
<tr class="fade-in-up">
<td><div class="td-pembayaran-id">#<?= str_pad((int)$row['ID_Pembayaran'],5,'0',STR_PAD_LEFT) ?></div><div class="td-customer-contact">Order #<?= str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT) ?></div></td>
<td><div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div><div class="td-customer-contact"><?= htmlspecialchars($row['No_Hp']) ?></div></td>
<td><div class="td-order">#<?= str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT) ?></div><div class="td-detail" style="color:<?= $orderStatusInfo[1] ?>"><span class="badge-dot" style="background:<?= $orderStatusInfo[1] ?>"></span><?= $orderStatusInfo[0] ?></div></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Metode_Pembayaran']) ?></div></td>
<td><div class="td-jumlah">Rp <?= number_format((float)$row['Jumlah_Bayar'],0,',','.') ?></div></td>
<td><?php if(!empty($row['Bukti_Transfer'])):?><img src="../../assets/img/bukti/<?= htmlspecialchars($row['Bukti_Transfer']) ?>" class="bukti-thumb" onclick="window.open(this.src,'_blank')" title="Klik untuk memperbesar"><?php else:?><span class="td-detail" style="color:#94a3b8">Tidak ada</span><?php endif;?></td>
<td><div class="td-detail"><?= (is_object($row['Tanggal_Upload'])&&method_exists($row['Tanggal_Upload'],'format'))?$row['Tanggal_Upload']->format('d M Y H:i'):date('d M Y H:i',strtotime($row['Tanggal_Upload'])) ?></div></td>
<td><span class="badge-status" style="background:<?= $statusInfo[2] ?>;color:<?= $statusInfo[1] ?>"><span class="badge-dot" style="background:<?= $statusInfo[1] ?>"></span><?= $statusInfo[0] ?></span></td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Verifikator']??'-') ?></div></td>
<td>
<?php if((int)$row['Status_Pembayaran']===0):?>
<button class="btn-action-circle btn-action-terima" onclick="konfirmasiTerima(<?= (int)$row['ID_Pembayaran'] ?>)" title="Terima Pelunasan"><i class="bi bi-check-lg"></i></button>
<button class="btn-action-circle btn-action-tolak" onclick="konfirmasiTolak(<?= (int)$row['ID_Pembayaran'] ?>)" title="Tolak Pelunasan"><i class="bi bi-x-lg"></i></button>
<?php else:?>
<button class="btn-action-circle btn-action-view" onclick="Swal.fire({title:'Detail Pelunasan',html:'<b>Customer:</b> <?= htmlspecialchars($row['Nama_Pelanggan']) ?><br><b>Jumlah:</b> Rp <?= number_format((float)$row['Jumlah_Bayar'],0,',','.') ?><br><b>Status:</b> <?= $statusInfo[0] ?>',icon:'info',confirmButtonColor:'#D53D66'})" title="Lihat Detail"><i class="bi bi-eye"></i></button>
<?php endif;?>
</td>
</tr>
<?php endwhile;else:?>
<tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color:#cbd5e1"></i><p class="fw-bold">Tidak ada pelunasan yang sesuai.</p><p class="small">Belum ada pembayaran pelunasan masuk.</p></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
<?php if($total_halaman>1):?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset+1 ?></span> - <span><?= min($offset+$limit,$total_records) ?></span> dari <span><?= $total_records ?></span> pembayaran</div>
<nav class="pagination-nav">
<?php if($halaman>1):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman-1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif;?>
<?php $start_page=max(1,$halaman-2);$end_page=min($total_halaman,$halaman+2);if($start_page>1){echo'<a class="page-link-pag" href="list.php?halaman=1&tab='.$tab_filter.'&cari='.urlencode($cari).'">1</a>';if($start_page>2)echo'<span class="page-link-pag disabled">...</span>';}for($i=$start_page;$i<=$end_page;$i++):?><a class="page-link-pag <?= ($halaman==$i)?'active-pag':'' ?>" href="list.php?halaman=<?= $i ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>"><?= $i ?></a><?php endfor;if($end_page<$total_halaman){if($end_page<$total_halaman-1)echo'<span class="page-link-pag disabled">...</span>';echo'<a class="page-link-pag" href="list.php?halaman='.$total_halaman.'&tab='.$tab_filter.'&cari='.urlencode($cari).'">'.$total_halaman.'</a>';}?>
<?php if($halaman<$total_halaman):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman+1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif;?>
</nav>
</div>
<?php elseif($total_records>0):?>
<div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> pembayaran</div></div>
<?php endif;?>
</div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)';}}});});
function konfirmasiTerima(idPembayaran){Swal.fire({title:'Terima Pelunasan?',text:'Apakah Anda yakin ingin MENERIMA pembayaran pelunasan ini? Order akan menjadi LUNAS.',icon:'question',showCancelButton:true,confirmButtonColor:'#059669',cancelButtonColor:'#718096',confirmButtonText:'Ya, Terima',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='verifikasi.php?id='+idPembayaran+'&aksi=terima';}});}
function konfirmasiTolak(idPembayaran){Swal.fire({title:'Tolak Pelunasan?',text:'Apakah Anda yakin ingin MENOLAK pembayaran pelunasan ini? Customer harus upload ulang.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#718096',confirmButtonText:'Ya, Tolak',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='verifikasi.php?id='+idPembayaran+'&aksi=tolak';}});}
function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php';}});}
function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php';}});}
function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'});}
function updateLiveClock(){const now=new Date();const days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];document.getElementById('live-clock').innerText=`${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;}
setInterval(updateLiveClock,1000);updateLiveClock();
<?php if(isset($_GET['status'])):?><?php if($_GET['status']=='sukses'):?>Swal.fire({icon:'success',title:'Berhasil!',text:'<?= htmlspecialchars($_GET['msg']??'Operasi berhasil.') ?>',confirmButtonColor:'#D53D66'});<?php elseif($_GET['status']=='error'):?>Swal.fire({icon:'error',title:'Gagal!',text:'<?= htmlspecialchars($_GET['msg']??'Terjadi kesalahan.') ?>',confirmButtonColor:'#D53D66'});<?php endif;?><?php endif;?>
</script>
</body>
</html>