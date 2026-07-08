<?php
session_start();

// =====================================================
// DEBUG MODE - Set false di production
// =====================================================
$debug_mode = false;

// --- PROTEKSI HALAMAN: HANYA OWNER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

// --- INCLUDE KONEKSI ---
if (!file_exists('../../koneksi.php')) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: File koneksi.php tidak ditemukan!</h2></div>');
}
include '../../koneksi.php';

// --- CEK KONEKSI DATABASE ---
if (!isset($conn) || $conn === false) {
    die('<div style="padding:20px;font-family:Arial;"><h2 style="color:#d83f67;">Error: Koneksi database gagal!</h2></div>');
}

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

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
// QUERY STATISTIK PEMBATALAN & REJEKSI DP
// =====================================================

// 1. Total Sesi yang Batal (Status Order Dibatalkan ATAU Pembayaran DP Ditolak)
$sql_total_batal = "SELECT COUNT(DISTINCT o.ID_Order) AS total 
                    FROM [Order] o 
                    LEFT JOIN Pembayaran p ON o.ID_Order = p.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1 
                    WHERE o.Status = 1 AND (o.Status_Order = 4 OR p.Status_Pembayaran = 2)
                      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_total = sqlsrv_query($conn, $sql_total_batal, [$tgl_mulai, $tgl_selesai]);
$row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
$total_batal = $row_total['total'] ?? 0;

// 2. Jumlah Order dengan Status_Order = 4 (Explicitly Canceled)
$sql_order_cancel = "SELECT COUNT(*) AS total FROM [Order] WHERE Status = 1 AND Status_Order = 4 AND CAST(Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_cancel = sqlsrv_query($conn, $sql_order_cancel, [$tgl_mulai, $tgl_selesai]);
$row_cancel = sqlsrv_fetch_array($stmt_cancel, SQLSRV_FETCH_ASSOC);
$total_order_cancel = $row_cancel['total'] ?? 0;

// 3. Jumlah Pembayaran DP yang Ditolak (Gagal Booking karena bukti transfer palsu/salah)
$sql_dp_ditolak = "SELECT COUNT(*) AS total FROM Pembayaran WHERE Status = 1 AND Tipe_Pembayaran = 'DP' AND Status_Pembayaran = 2 AND CAST(Tanggal_Upload AS DATE) BETWEEN ? AND ?";
$stmt_ditolak = sqlsrv_query($conn, $sql_dp_ditolak, [$tgl_mulai, $tgl_selesai]);
$row_ditolak = sqlsrv_fetch_array($stmt_ditolak, SQLSRV_FETCH_ASSOC);
$total_dp_ditolak = $row_ditolak['total'] ?? 0;

// 4. Estimasi Nilai Kerugian (Revenue Lost) dari Paket yang Batal/Ditolak
$sql_rugi = "SELECT SUM(o.Total_Harga) AS total 
             FROM [Order] o 
             LEFT JOIN Pembayaran p ON o.ID_Order = p.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1 
             WHERE o.Status = 1 AND (o.Status_Order = 4 OR p.Status_Pembayaran = 2)
               AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_rugi = sqlsrv_query($conn, $sql_rugi, [$tgl_mulai, $tgl_selesai]);
$row_rugi = sqlsrv_fetch_array($stmt_rugi, SQLSRV_FETCH_ASSOC);
$total_kerugian = $row_rugi['total'] ?? 0;

// =====================================================
// QUERY DETAIL DATA PEMBATALAN (Dengan referensi Pembayaran)
// =====================================================
$sql_detail = "SELECT 
                    o.ID_Order,
                    o.Tanggal_Booking,
                    o.Total_Harga,
                    o.Status_Order,
                    o.Keterangan AS Keterangan_Order,
                    p.Nama_Pelanggan,
                    p.No_Hp,
                    pk.Nama_Paket,
                    r.Nama_Ruangan,
                    t.Nama_Tema,
                    j.Tanggal_Jadwal,
                    j.Jam_Mulai,
                    j.Jam_Selesai,
                    pb.ID_Pembayaran,
                    pb.Jumlah_Bayar,
                    pb.Metode_Pembayaran,
                    pb.Bukti_Transfer,
                    pb.Status_Pembayaran,
                    pb.Tanggal_Upload,
                    k.Nama_Karyawan AS Nama_Verifikator
                FROM [Order] o
                INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
                INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
                INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
                INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
                INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
                LEFT JOIN Pembayaran pb ON o.ID_Order = pb.ID_Order AND pb.Tipe_Pembayaran = 'DP' AND pb.Status = 1
                LEFT JOIN Karyawan k ON pb.ID_Karyawan_Verifikator = k.ID_Karyawan
                WHERE o.Status = 1 AND (o.Status_Order = 4 OR pb.Status_Pembayaran = 2)
                  AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
                ORDER BY o.Tanggal_Booking DESC";

$query_detail = sqlsrv_query($conn, $sql_detail, [$tgl_mulai, $tgl_selesai]);

function formatTanggal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y H:i');
    }
    return date('d M Y H:i', strtotime($dateObj));
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
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
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
.data-table thead th:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;font-weight: 600;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}
.td-order-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;}
.td-detail{font-size:0.8rem;color:#718096;}
.td-jumlah{font-weight:800;font-size:1rem;color:var(--p-pink);}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.badge-batal{background:#fee2e2;color:#dc2626;}
.badge-ditolak{background:#fffbeb;color:#d97706;}
.bukti-thumb{width:50px;height:50px;border-radius:10px;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;transition:all 0.3s;}
.bukti-thumb:hover{transform:scale(1.05);border-color:var(--p-pink);}
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
<a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
<div class="submenu" id="submenuMaster">
<ul class="list-unstyled">
<li><a href="../../Master/Karyawan/index.php" class="submenu-link"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
</ul>
</div>
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
<p class="text-muted small mb-0">Menganalisis pesanan yang batal akibat pembatalan sepihak atau penolakan bukti pembayaran DP.</p>
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
<div class="summary-title">Total Potensi Omzet yang Batal</div>
<div class="summary-value">Rp <?= number_format($total_kerugian, 0, ',', '.') ?></div>
<div class="summary-subtitle">Periode <?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?> &bull; <?= $total_batal ?> Total Kasus Booking Gagal</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">DP Ditolak Admin</div>
<div class="summary-value" style="font-size:1.5rem;"><?= $total_dp_ditolak ?> Transaksi</div>
</div>
</div>
</div>

<!-- STAT CARDS -->
<div class="stats-scroll-wrapper fade-in-up">
<div class="stats-row">
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-red"><i class="bi bi-calendar-x-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Total Kasus Batal</div>
<div class="stat-val"><?= $total_batal ?> Sesi</div>
<div class="stat-subtitle">Sesi gagal terlaksana</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-x-circle-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Order Dibatalkan</div>
<div class="stat-val"><?= $total_order_cancel ?> Sesi</div>
<div class="stat-subtitle">Status Order: 4</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-orange"><i class="bi bi-exclamation-octagon-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Bukti DP Ditolak</div>
<div class="stat-val"><?= $total_dp_ditolak ?> Transaksi</div>
<div class="stat-subtitle">Pembayaran Ditolak: 2</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-cash-stack"></i></div>
<div class="stat-content">
<div class="stat-title">Potensi Omzet Hilang</div>
<div class="stat-val">Rp <?= number_format($total_kerugian, 0, ',', '.') ?></div>
<div class="stat-subtitle">Dari nominal tagihan</div>
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
<th>No. Order</th>
<th>Tanggal Booking</th>
<th>Customer</th>
<th>Paket & Sesi</th>
<th>Jadwal Terpilih</th>
<th>DP Upload</th>
<th>Bukti</th>
<th>Status / Alasan</th>
<th>Verifikator</th>
<th class="text-end">Potensi Omzet</th>
</tr>
</thead>
<tbody>
<?php
if ($query_detail && sqlsrv_has_rows($query_detail)):
while ($row = sqlsrv_fetch_array($query_detail, SQLSRV_FETCH_ASSOC)):
    $jam_mulai = (is_object($row['Jam_Mulai']) && method_exists($row['Jam_Mulai'], 'format')) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
    $jam_selesai = (is_object($row['Jam_Selesai']) && method_exists($row['Jam_Selesai'], 'format')) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
    
    // Klasifikasi Alasan Batal
    if ((int)$row['Status_Order'] === 4) {
        $badgeClass = "badge-batal";
        $statusText = "Order Dibatalkan";
    } else {
        $badgeClass = "badge-ditolak";
        $statusText = "Bukti DP Ditolak";
    }
?>
<tr>
<td><div class="td-order-id">#ORD-<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></div></td>
<td><div class="td-detail"><?= formatTanggal($row['Tanggal_Booking']) ?></div></td>
<td>
    <div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
    <div class="td-customer-contact"><?= htmlspecialchars($row['No_Hp']) ?></div>
</td>
<td>
    <div class="td-customer"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
    <div class="td-detail"><?= htmlspecialchars($row['Nama_Ruangan']) ?> &bull; <?= htmlspecialchars($row['Nama_Tema']) ?></div>
</td>
<td>
    <div class="td-customer"><?= (is_object($row['Tanggal_Jadwal']) && method_exists($row['Tanggal_Jadwal'], 'format')) ? $row['Tanggal_Jadwal']->format('d M Y') : $row['Tanggal_Jadwal'] ?></div>
    <div class="td-detail"><?= $jam_mulai ?> - <?= $jam_selesai ?></div>
</td>
<td>
    <?php if (isset($row['ID_Pembayaran'])): ?>
        <div class="td-customer">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></div>
        <div class="td-detail"><?= htmlspecialchars($row['Metode_Pembayaran']) ?></div>
    <?php else: ?>
        <span class="text-muted" style="font-size:0.75rem;">Belum bayar DP</span>
    <?php endif; ?>
</td>
<td>
    <?php if (!empty($row['Bukti_Transfer'])): ?>
        <img src="../../assets/img/bukti/<?= htmlspecialchars($row['Bukti_Transfer']) ?>" class="bukti-thumb" onclick="window.open(this.src,'_blank')" title="Klik untuk memperbesar">
    <?php else: ?>
        <span class="text-muted" style="font-size:0.75rem;">Tidak Ada</span>
    <?php endif; ?>
</td>
<td>
    <span class="badge-status <?= $badgeClass ?>"><span class="badge-dot" style="background: currentColor;"></span><?= $statusText ?></span>
    <?php if (!empty($row['Keterangan_Order'])): ?>
        <div class="td-detail" style="font-size:0.72rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['Keterangan_Order']) ?>">Ket: <?= htmlspecialchars($row['Keterangan_Order']) ?></div>
    <?php endif; ?>
</td>
<td><div class="td-detail"><?= htmlspecialchars($row['Nama_Verifikator'] ?? '-') ?></div></td>
<td><div class="td-jumlah text-end">Rp <?= number_format((float)$row['Total_Harga'], 0, ',', '.') ?></div></td>
</tr>
<?php endwhile; else: ?>
<tr>
<td colspan="10">
<div class="empty-state">
<i class="bi bi-calendar-x"></i>
<p>Tidak ada data pembatalan.</p>
<small>Tidak ada transaksi order yang dibatalkan atau ditolak bukti pembayarannya.</small>
</div>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
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
</script>
</body>
</html>