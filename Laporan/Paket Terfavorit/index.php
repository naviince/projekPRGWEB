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
// QUERY STATISTIK UTAMA
// =====================================================

// 1. Total Seluruh Booking Aktif (Bukan Dibatalkan) dalam Periode
$sql_total_booking = "SELECT COUNT(*) AS total FROM [Order] WHERE Status = 1 AND Status_Order <> 4 AND CAST(Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_total = sqlsrv_query($conn, $sql_total_booking, [$tgl_mulai, $tgl_selesai]);
$row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
$total_seluruh_booking = $row_total['total'] ?? 0;

// 2. Paket Paling Terfavorit (Berdasarkan Jumlah Booking)
$sql_fav = "SELECT TOP 1 pk.Nama_Paket, COUNT(o.ID_Order) AS jumlah 
            FROM Paket_Foto pk 
            INNER JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
            WHERE o.Status = 1 AND o.Status_Order <> 4 AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
            GROUP BY pk.Nama_Paket 
            ORDER BY jumlah DESC";
$stmt_fav = sqlsrv_query($conn, $sql_fav, [$tgl_mulai, $tgl_selesai]);
$row_fav = sqlsrv_fetch_array($stmt_fav, SQLSRV_FETCH_ASSOC);
$paket_terfavorit = $row_fav['Nama_Paket'] ?? 'Belum ada data';
$jumlah_booking_fav = $row_fav['jumlah'] ?? 0;

// 3. Estimasi Total Omzet dari Penjualan Paket Aktif dalam Periode
$sql_omzet = "SELECT SUM(Total_Paket) AS total FROM [Order] WHERE Status = 1 AND Status_Order <> 4 AND CAST(Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_omzet = sqlsrv_query($conn, $sql_omzet, [$tgl_mulai, $tgl_selesai]);
$row_omzet = sqlsrv_fetch_array($stmt_omzet, SQLSRV_FETCH_ASSOC);
$total_omzet_paket = $row_omzet['total'] ?? 0;

// 4. Rata-rata Rating Tertinggi Paket Foto
$sql_rating = "SELECT TOP 1 pk.Nama_Paket, AVG(CAST(o.Rating AS DECIMAL(3,2))) AS rata_rating 
               FROM Paket_Foto pk 
               INNER JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
               WHERE o.Status = 1 AND o.Rating IS NOT NULL AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
               GROUP BY pk.Nama_Paket 
               ORDER BY rata_rating DESC";
$stmt_rating = sqlsrv_query($conn, $sql_rating, [$tgl_mulai, $tgl_selesai]);
$row_rating = sqlsrv_fetch_array($stmt_rating, SQLSRV_FETCH_ASSOC);
$paket_rating_tertinggi = $row_rating['Nama_Paket'] ?? 'Belum dinilai';
$nilai_rating_tertinggi = isset($row_rating['rata_rating']) ? number_format((float)$row_rating['rata_rating'], 1) : '-';

// =====================================================
// QUERY DETAIL LAPORAN PAKET TERFAVORIT
// =====================================================
$sql_laporan = "SELECT 
                    pk.ID_Paket,
                    pk.Nama_Paket,
                    pk.Harga_Paket,
                    pk.Durasi_Waktu,
                    pk.Kapasitas_Orang,
                    COUNT(o.ID_Order) AS Jumlah_Booking,
                    SUM(CASE WHEN o.Status_Order <> 4 THEN o.Total_Paket ELSE 0 END) AS Estimasi_Omzet,
                    AVG(CAST(o.Rating AS DECIMAL(3,2))) AS Rata_Rata_Rating,
                    COUNT(CASE WHEN o.Status_Order = 4 THEN 1 END) AS Jumlah_Dibatalkan
                FROM Paket_Foto pk
                LEFT JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
                    AND o.Status = 1 
                    AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
                WHERE pk.Status = 1 AND pk.Is_Deleted = 0
                GROUP BY pk.ID_Paket, pk.Nama_Paket, pk.Harga_Paket, pk.Durasi_Waktu, pk.Kapasitas_Orang
                ORDER BY Jumlah_Booking DESC, Estimasi_Omzet DESC";

$query_laporan = sqlsrv_query($conn, $sql_laporan, [$tgl_mulai, $tgl_selesai]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Paket Terfavorit - SpotLight Studio</title>
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
.stat-icon-yellow{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
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
.data-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;text-align:center;width:60px;}
.data-table thead th:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;font-weight:600;}
.data-table tbody td:first-child{padding-left:24px;text-align:center;}
.data-table tbody td:last-child{padding-right:24px;text-align:right;}
.data-table tbody tr:nth-child(even){background-color:#fff8f0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#ffedd5!important;transform:scale(1.002);}
.td-rank{font-size:1.1rem;font-weight:800;color:var(--p-pink);}
.td-paket-nama{font-weight:800;font-size:0.95rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;}
.td-jumlah{font-weight:800;font-size:1rem;color:var(--p-pink);}
.td-omzet{font-weight:800;font-size:1rem;color:#059669;}
.rating-star{color:#eab308;margin-right:4px;}
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
<li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
<li><a href="index.php" class="submenu-link active"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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
<div>
<h3 class="fw-bold mb-1">Laporan Paket Terfavorit</h3>
<p class="text-muted small mb-0">Analisis popularitas, performa rating, serta estimasi omzet per paket foto.</p>
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
<div class="summary-title">Paket Terpopuler Saat Ini</div>
<div class="summary-value"><?= htmlspecialchars($paket_terfavorit) ?></div>
<div class="summary-subtitle">Periode <?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?> &bull; Dipesan sebanyak <?= $jumlah_booking_fav ?> kali</div>
</div>
<div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
<div class="summary-title">Total Pendapatan Layanan Paket</div>
<div class="summary-value" style="font-size:1.6rem;">Rp <?= number_format($total_omzet_paket, 0, ',', '.') ?></div>
</div>
</div>
</div>

<!-- STAT CARDS -->
<div class="stats-scroll-wrapper fade-in-up">
<div class="stats-row">
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-pink"><i class="bi bi-star-fill text-warning"></i></div>
<div class="stat-content">
<div class="stat-title">Paket Terfavorit</div>
<div class="stat-val"><?= htmlspecialchars($paket_terfavorit) ?></div>
<div class="stat-subtitle"><?= $jumlah_booking_fav ?> Booking</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-green"><i class="bi bi-calendar-check-fill"></i></div>
<div class="stat-content">
<div class="stat-title">Total Booking Sesi</div>
<div class="stat-val"><?= $total_seluruh_booking ?> Sesi</div>
<div class="stat-subtitle">Semua paket aktif</div>
</div>
</div>
</div>
</div>
<div class="stat-card-item">
<div class="card-3d">
<div class="stat-card">
<div class="stat-icon stat-icon-blue"><i class="bi bi-cash-coin"></i></div>
<div class="stat-content">
<div class="stat-title">Estimasi Omzet</div>
<div class="stat-val">Rp <?= number_format($total_omzet_paket, 0, ',', '.') ?></div>
<div class="stat-subtitle">Nilai kotor paket</div>
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
<div class="stat-val"><?= htmlspecialchars($paket_rating_tertinggi) ?></div>
<div class="stat-subtitle">Avg Rating: <?= $nilai_rating_tertinggi ?> <i class="bi bi-star-fill text-warning"></i></div>
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
<div class="card-3d fade-in-up" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead>
<tr>
<th>Peringkat</th>
<th>Nama Paket</th>
<th>Durasi Sesi</th>
<th>Kapasitas</th>
<th>Harga Paket</th>
<th>Jumlah Booking</th>
<th>Kontribusi</th>
<th>Rating Rata-rata</th>
<th>Dibatalkan</th>
<th class="text-end">Estimasi Pendapatan</th>
</tr>
</thead>
<tbody>
<?php
if ($query_laporan && sqlsrv_has_rows($query_laporan)):
$rank = 1;
while ($row = sqlsrv_fetch_array($query_laporan, SQLSRV_FETCH_ASSOC)):
    $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
    $rating_val = isset($row['Rata_Rata_Rating']) ? number_format((float)$row['Rata_Rata_Rating'], 1) : null;
?>
<tr>
<td><div class="td-rank">#<?= $rank++ ?></div></td>
<td><div class="td-paket-nama"><?= htmlspecialchars($row['Nama_Paket']) ?></div></td>
<td><div class="td-detail"><i class="bi bi-clock me-1"></i> <?= $row['Durasi_Waktu'] ?> Menit</div></td>
<td><div class="td-detail"><i class="bi bi-people-fill me-1"></i> Max <?= $row['Kapasitas_Orang'] ?> Orang</div></td>
<td><div class="td-detail">Rp <?= number_format((float)$row['Harga_Paket'], 0, ',', '.') ?></div></td>
<td><div class="td-jumlah"><?= $row['Jumlah_Booking'] ?> Sesi</div></td>
<td>
    <div class="d-flex align-items-center gap-2">
        <div class="progress" style="width: 70px; height: 6px;">
            <div class="progress-bar" role="progressbar" style="width: <?= $kontribusi ?>%; background-color: var(--p-pink);" aria-valuenow="<?= $kontribusi ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <span class="td-detail fw-bold"><?= number_format($kontribusi, 1) ?>%</span>
    </div>
</td>
<td>
    <div class="td-detail fw-bold">
        <?php if ($rating_val): ?>
            <i class="bi bi-star-fill rating-star"></i><?= $rating_val ?>
        <?php else: ?>
            <span class="text-muted" style="font-size:0.75rem;">Belum ada ulasan</span>
        <?php endif; ?>
    </div>
</td>
<td>
    <span class="badge rounded-pill text-danger bg-light" style="font-size:0.75rem; font-weight:700;">
        <?= $row['Jumlah_Dibatalkan'] ?> Sesi
    </span>
</td>
<td><div class="td-omzet">Rp <?= number_format((float)$row['Estimasi_Omzet'], 0, ',', '.') ?></div></td>
</tr>
<?php endwhile; else: ?>
<tr>
<td colspan="10">
<div class="empty-state">
<i class="bi bi-inbox"></i>
<p>Tidak ada data penjualan paket foto.</p>
<small>Tidak ada booking terverifikasi pada periode ini.</small>
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