<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
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

// =====================================================
// AMBIL DETAIL SESI FOTO - DENGAN ERROR CHECKING
// =====================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id_sesi = intval($_GET['id']);

$sql_detail = "SELECT 
    S.ID_Sesi_Foto,
    S.ID_Order,
    S.ID_Karyawan,
    S.Waktu_Mulai,
    S.Waktu_Selesai,
    S.File_Hasil,
    S.Tanggal_Upload_Hasil,
    S.Status_Sesi,
    S.Created_Date,
    S.Modified_Date,
    S.Modified_By,
    P.ID_Pelanggan,
    P.Nama_Pelanggan,
    P.Email_Pelanggan,
    P.No_Hp AS NoHp_Pelanggan,
    P.Alamat AS Alamat_Pelanggan,
    PK.ID_Paket,
    PK.Nama_Paket,
    PK.Durasi_Waktu,
    PK.Harga_Paket,
    PK.Kapasitas_Orang,
    R.ID_Ruangan,
    R.Nama_Ruangan,
    T.ID_Tema,
    T.Nama_Tema,
    J.Tanggal_Jadwal,
    J.Jam_Mulai,
    J.Jam_Selesai,
    K.Nama_Karyawan AS Nama_Fotografer,
    K.Username_Karyawan AS Username_Fotografer,
    K.Email_Karyawan AS Email_Fotografer,
    K.No_Hp AS NoHp_Fotografer,
    O.Status_Order,
    O.Keterangan AS Keterangan_Order,
    O.Total_Paket,
    O.Total_Barang_Cetak,
    O.Total_Harga,
    O.Rating,
    O.Review
FROM Sesi_Foto S
JOIN [Order] O ON S.ID_Order = O.ID_Order
JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
JOIN Tema_Foto T ON O.ID_Tema = T.ID_Tema
JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
LEFT JOIN Karyawan K ON S.ID_Karyawan = K.ID_Karyawan
WHERE S.ID_Sesi_Foto = ? AND S.Status = 1";

$stmt = sqlsrv_query($conn, $sql_detail, array($id_sesi));

if ($stmt === false || !sqlsrv_has_rows($stmt)) {
    header("Location: list.php");
    exit();
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Format data
$tanggal_jadwal = (is_object($row['Tanggal_Jadwal']) && method_exists($row['Tanggal_Jadwal'], 'format')) ? $row['Tanggal_Jadwal']->format('d M Y') : ($row['Tanggal_Jadwal'] ? date('d M Y', strtotime($row['Tanggal_Jadwal'])) : '-');
$jam_mulai = (is_object($row['Jam_Mulai']) && method_exists($row['Jam_Mulai'], 'format')) ? $row['Jam_Mulai']->format('H:i') : (is_string($row['Jam_Mulai']) ? substr($row['Jam_Mulai'], 0, 5) : '-');
$jam_selesai = (is_object($row['Jam_Selesai']) && method_exists($row['Jam_Selesai'], 'format')) ? $row['Jam_Selesai']->format('H:i') : (is_string($row['Jam_Selesai']) ? substr($row['Jam_Selesai'], 0, 5) : '-');
$waktu_mulai = (is_object($row['Waktu_Mulai']) && method_exists($row['Waktu_Mulai'], 'format')) ? $row['Waktu_Mulai']->format('d M Y H:i') : ($row['Waktu_Mulai'] ? date('d M Y H:i', strtotime($row['Waktu_Mulai'])) : '-');
$waktu_selesai = (is_object($row['Waktu_Selesai']) && method_exists($row['Waktu_Selesai'], 'format')) ? $row['Waktu_Selesai']->format('d M Y H:i') : ($row['Waktu_Selesai'] ? date('d M Y H:i', strtotime($row['Waktu_Selesai'])) : '-');
$tanggal_upload = (is_object($row['Tanggal_Upload_Hasil']) && method_exists($row['Tanggal_Upload_Hasil'], 'format')) ? $row['Tanggal_Upload_Hasil']->format('d M Y H:i') : ($row['Tanggal_Upload_Hasil'] ? date('d M Y H:i', strtotime($row['Tanggal_Upload_Hasil'])) : '-');
$created_date = (is_object($row['Created_Date']) && method_exists($row['Created_Date'], 'format')) ? $row['Created_Date']->format('d M Y H:i') : ($row['Created_Date'] ? date('d M Y H:i', strtotime($row['Created_Date'])) : '-');
$modified_date = (is_object($row['Modified_Date']) && method_exists($row['Modified_Date'], 'format')) ? $row['Modified_Date']->format('d M Y H:i') : ($row['Modified_Date'] ? date('d M Y H:i', strtotime($row['Modified_Date'])) : '-');

$has_fotografer = !empty($row['ID_Karyawan']);
$has_file = !empty($row['File_Hasil']);

// Status labels
function getStatusLabel($status) {
    switch ($status) {
        case 0: return ['Menunggu', 'badge-menunggu', 'bi-hourglass-split', 'Sesi belum dimulai. Fotografer perlu di-assign dan memulai pemotretan.'];
        case 1: return ['Selesai', 'badge-selesai', 'bi-check-circle-fill', 'Sesi foto telah selesai. Hasil foto sudah tersedia.'];
        case 2: return ['Dibatalkan', 'badge-batal', 'bi-x-octagon-fill', 'Sesi foto telah dibatalkan.'];
        default: return ['Unknown', 'badge-secondary', 'bi-question-circle', ''];
    }
}

function getStatusOrderLabel($status) {
    switch ($status) {
        case 0: return ['Menunggu Pembayaran', '#d97706', '#fffbeb', 'bi-hourglass-split'];
        case 1: return ['DP Terverifikasi', '#2563eb', '#dbeafe', 'bi-check-circle-fill'];
        case 2: return ['Lunas', '#7c3aed', '#ede9fe', 'bi-cash-stack'];
        case 3: return ['Selesai', '#059669', '#ecfdf5', 'bi-camera-fill'];
        case 4: return ['Dibatalkan', '#dc2626', '#fef2f2', 'bi-x-circle-fill'];
        default: return ['Unknown', '#718096', '#f1f5f9', 'bi-question-circle'];
    }
}

$status_info = getStatusLabel($row['Status_Sesi']);
$order_status = getStatusOrderLabel($row['Status_Order']);

// Ambil riwayat pembayaran - DENGAN ERROR CHECKING
$pembayaran_list = array();
$q_pembayaran = sqlsrv_query($conn, "
    SELECT Tipe_Pembayaran, Metode_Pembayaran, Jumlah_Bayar, Status_Pembayaran, Tanggal_Upload
    FROM Pembayaran 
    WHERE ID_Order = ? AND Status = 1 
    ORDER BY Tanggal_Upload ASC
", array($row['ID_Order']));

if ($q_pembayaran !== false) {
    while ($p = sqlsrv_fetch_array($q_pembayaran, SQLSRV_FETCH_ASSOC)) {
        $pembayaran_list[] = $p;
    }
}

// Ambil list fotografer - DENGAN ERROR CHECKING
$q_fotografer = sqlsrv_query($conn, 
    "SELECT ID_Karyawan, Nama_Karyawan, Username_Karyawan FROM Karyawan 
     WHERE Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0 
     ORDER BY Nama_Karyawan ASC"
);
$fotografer_list = array();
if ($q_fotografer !== false) {
    while ($f = sqlsrv_fetch_array($q_fotografer, SQLSRV_FETCH_ASSOC)) {
        $fotografer_list[] = $f;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Sesi Foto #<?= $id_sesi ?> - SpotLight Studio</title>

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
.card-3d{background:#fff;border-radius:22px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 8px 24px rgba(213,61,102,0.03);transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);padding:25px;height:100%;position:relative;overflow:hidden;}
.card-3d::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,var(--p-pink),#E85D84);opacity:0;transition:opacity 0.3s ease;}
.card-3d:hover{transform:translateY(-4px);box-shadow:0 22px 45px rgba(213,61,102,0.14);border-color:var(--p-pink);}
.card-3d:hover::before{opacity:1;}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:all 0.4s;flex-shrink:0;}
.stat-icon-purple{background:linear-gradient(135deg,#FFF0F3,#FFE4E9);color:#D53D66;}
.stat-icon-blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-icon-pink{background:linear-gradient(135deg,#fdf2f8,#fce7f3);color:#db2777;}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-menunggu{background:#fffbeb;color:#d97706;}
.badge-selesai{background:#ecfdf5;color:#059669;}
.badge-batal{background:#fef2f2;color:#dc2626;}
.badge-belum-assign{background:#dbeafe;color:#2563eb;}
.badge-pembayaran-valid{background:#ecfdf5;color:#059669;}
.badge-pembayaran-tunggu{background:#fffbeb;color:#d97706;}
.badge-pembayaran-tolak{background:#fef2f2;color:#dc2626;}
.btn-action{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;border-radius:10px;padding:8px 16px;font-weight:700;font-size:0.8rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;border:none;}
.btn-action:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(213,61,102,0.25);color:#fff;}
.btn-action-secondary{background:linear-gradient(135deg,#718096,#4a5568);}
.btn-action-secondary:hover{box-shadow:0 6px 15px rgba(113,128,150,0.25);}
.btn-action-success{background:linear-gradient(135deg,#059669,#047857);}
.btn-action-success:hover{box-shadow:0 6px 15px rgba(5,150,105,0.25);}
.btn-action-warning{background:linear-gradient(135deg,#d97706,#b45309);}
.btn-action-warning:hover{box-shadow:0 6px 15px rgba(217,119,6,0.25);}
.btn-action-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-action-danger:hover{box-shadow:0 6px 15px rgba(220,38,38,0.25);}
.btn-action-sm{padding:6px 12px;font-size:0.75rem;}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.info-row:last-child{border-bottom:none;}
.info-label{font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;}
.info-value{font-size:0.85rem;font-weight:700;color:var(--text-dark);text-align:right;}
.timeline-detail{position:relative;padding-left:24px;}
.timeline-detail::before{content:'';position:absolute;left:6px;top:0;bottom:0;width:2px;background:linear-gradient(180deg,var(--p-pink),#E85D84);border-radius:2px;}
.timeline-item-detail{position:relative;padding-bottom:16px;}
.timeline-item-detail::before{content:'';position:absolute;left:-22px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--p-pink);border:2px solid #fff;box-shadow:0 0 0 2px var(--p-pink);}
.timeline-item-detail.completed::before{background:#059669;box-shadow:0 0 0 2px #059669;}
.timeline-item-detail.cancelled::before{background:#dc2626;box-shadow:0 0 0 2px #dc2626;}
.file-preview-box{background:linear-gradient(135deg,var(--s-pink),#fff);border-radius:16px;padding:20px;text-align:center;border:2px dashed var(--light-pink);}
.breadcrumb-item a{color:var(--p-pink);font-weight:700;text-decoration:none;}
.breadcrumb-item.active{font-weight:700;color:var(--text-dark);}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;}
.modal-overlay.show{display:flex;}
.modal-content-custom{background:#fff;border-radius:24px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:modalIn 0.3s ease;}
.modal-header-custom{padding:24px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;}
.modal-title-custom{font-size:1.2rem;font-weight:800;color:var(--text-dark);}
.modal-close-custom{background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all 0.3s;}
.modal-close-custom:hover{background:#f1f5f9;color:var(--text-dark);}
.modal-body-custom{padding:24px;}
@keyframes modalIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
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

<!-- TRANSAKSI -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuTransaksi"><span><i class="bi bi-cart-fill me-2"></i> Transaksi</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuTransaksi">
<ul class="list-unstyled">
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Booking/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
</ul>
</div>
</li>

<!-- SESI FOTO (ACTIVE) -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuSesi"><span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuSesi">
<ul class="list-unstyled">
<li><a href="list.php" class="submenu-link active"><i class="bi bi-eye-fill me-2"></i>Lihat Sesi Foto</a></li>
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

<!-- HEADER -->
<div class="dashboard-header">
<div>
    <nav aria-label="breadcrumb" style="margin-bottom:8px;">
        <ol class="breadcrumb mb-0" style="font-size:0.8rem;">
            <li class="breadcrumb-item"><a href="../../Role/Admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="list.php">Sesi Foto</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail #<?= $id_sesi ?></li>
        </ol>
    </nav>
    <h3 class="fw-bold mb-1">Detail Sesi Foto</h3>
    <p class="text-muted small mb-0">Informasi lengkap sesi foto pelanggan.</p>
</div>
<div class="d-flex align-items-center gap-3">
    <a href="list.php" class="btn-action btn-action-secondary btn-action-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
    <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<!-- STATUS BANNER -->
<div class="card-3d mb-4 fade-in-up" style="border-left:4px solid <?= $row['Status_Sesi'] == 1 ? '#059669' : ($row['Status_Sesi'] == 2 ? '#dc2626' : '#d97706') ?>;">
<div class="d-flex justify-content-between align-items-center">
<div class="d-flex align-items-center gap-3">
<div class="stat-icon" style="width:50px;height:50px;font-size:1.5rem;background:<?= $row['Status_Sesi'] == 1 ? 'linear-gradient(135deg,#ecfdf5,#d1fae5)' : ($row['Status_Sesi'] == 2 ? 'linear-gradient(135deg,#fef2f2,#fee2e2)' : 'linear-gradient(135deg,#fffbeb,#fef3c7)') ?>;color:<?= $row['Status_Sesi'] == 1 ? '#059669' : ($row['Status_Sesi'] == 2 ? '#dc2626' : '#d97706') ?>;">
<i class="bi <?= $status_info[2] ?>"></i>
</div>
<div>
<div class="fw-bold" style="font-size:1.1rem;">Sesi Foto #<?= str_pad((int)$id_sesi, 5, '0', STR_PAD_LEFT) ?></div>
<div class="text-muted" style="font-size:0.8rem;"><?= $status_info[3] ?></div>
</div>
</div>
<div class="d-flex gap-2">
<span class="badge-status <?= $status_info[1] ?>"><i class="bi <?= $status_info[2] ?>"></i> <?= $status_info[0] ?></span>
<?php if ($row['Status_Sesi'] == 0): ?>
<?php if (!$has_fotografer): ?>
<button onclick="bukaModalAssign()" class="btn-action btn-action-warning btn-action-sm"><i class="bi bi-person-plus"></i> Assign</button>
<?php endif; ?>
<button onclick="konfirmasiBatal(<?= $id_sesi ?>)" class="btn-action btn-action-danger btn-action-sm"><i class="bi bi-x-circle"></i> Batal</button>
<?php endif; ?>
</div>
</div>
</div>

<div class="row g-4">
<!-- KOLOM KIRI: Info Pelanggan & Order -->
<div class="col-lg-4 fade-in-up">
<!-- Info Pelanggan -->
<div class="card-3d mb-4">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-purple" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-person-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Data Pelanggan</div><div class="text-muted" style="font-size:0.75rem;">Informasi pemesan</div></div>
</div>
<div class="info-row"><span class="info-label">Nama</span><span class="info-value"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></span></div>
<div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($row['Email_Pelanggan']) ?></span></div>
<div class="info-row"><span class="info-label">No. HP</span><span class="info-value"><?= htmlspecialchars($row['NoHp_Pelanggan']) ?></span></div>
<div class="info-row"><span class="info-label">Alamat</span><span class="info-value"><?= htmlspecialchars($row['Alamat_Pelanggan'] ?? '-') ?></span></div>
</div>

<!-- Info Order -->
<div class="card-3d mb-4">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-blue" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-bag-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Data Order</div><div class="text-muted" style="font-size:0.75rem;">Order #<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></div></div>
</div>
<div class="info-row"><span class="info-label">Status Order</span><span class="badge-status" style="background:<?= $order_status[2] ?>;color:<?= $order_status[1] ?>"><span class="badge-dot" style="background:<?= $order_status[1] ?>"></span><?= $order_status[0] ?></span></div>
<div class="info-row"><span class="info-label">Total Paket</span><span class="info-value">Rp <?= number_format((float)$row['Total_Paket'], 0, ',', '.') ?></span></div>
<div class="info-row"><span class="info-label">Total Barang</span><span class="info-value">Rp <?= number_format((float)$row['Total_Barang_Cetak'], 0, ',', '.') ?></span></div>
<div class="info-row"><span class="info-label">Total Harga</span><span class="info-value" style="color:var(--p-pink);font-size:1rem;">Rp <?= number_format((float)$row['Total_Harga'], 0, ',', '.') ?></span></div>
<?php if ($row['Rating']): ?>
<div class="info-row"><span class="info-label">Rating</span><span class="info-value" style="color:#f59e0b;"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= $row['Rating'] ? '-fill' : '' ?>" style="font-size:0.8rem;"></i><?php endfor; ?> (<?= $row['Rating'] ?>/5)</span></div>
<?php endif; ?>
<?php if ($row['Review']): ?>
<div class="mt-2 p-3" style="background:var(--s-pink);border-radius:12px;"><div class="info-label mb-1">Review Pelanggan</div><div style="font-size:0.85rem;font-style:italic;color:var(--text-dark);">"<?= htmlspecialchars($row['Review']) ?>"</div></div>
<?php endif; ?>
</div>

<!-- Pembayaran -->
<div class="card-3d">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-green" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-credit-card-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Riwayat Pembayaran</div><div class="text-muted" style="font-size:0.75rem;"><?= count($pembayaran_list) ?> transaksi</div></div>
</div>
<?php if (count($pembayaran_list) > 0): ?>
<?php foreach ($pembayaran_list as $p):
$badge_class = ($p['Status_Pembayaran'] ?? 0) == 1 ? 'badge-pembayaran-valid' : (($p['Status_Pembayaran'] ?? 0) == 2 ? 'badge-pembayaran-tolak' : 'badge-pembayaran-tunggu');
$status_text = ($p['Status_Pembayaran'] ?? 0) == 1 ? 'Valid' : (($p['Status_Pembayaran'] ?? 0) == 2 ? 'Ditolak' : 'Menunggu');
$tgl_upload = (is_object($p['Tanggal_Upload']) && method_exists($p['Tanggal_Upload'], 'format')) ? $p['Tanggal_Upload']->format('d M Y H:i') : ($p['Tanggal_Upload'] ? date('d M Y H:i', strtotime($p['Tanggal_Upload'])) : '-');
?>
<div class="d-flex justify-content-between align-items-center p-2 mb-2" style="background:#f8fafc;border-radius:10px;">
<div><div class="fw-bold" style="font-size:0.8rem;"><?= htmlspecialchars($p['Tipe_Pembayaran']) ?> &bull; <?= htmlspecialchars($p['Metode_Pembayaran']) ?></div><div class="text-muted" style="font-size:0.72rem;">Rp <?= number_format((float)$p['Jumlah_Bayar'], 0, ',', '.') ?> &bull; <?= $tgl_upload ?></div></div>
<span class="badge-status <?= $badge_class ?>" style="font-size:0.65rem;"><?= $status_text ?></span>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-center py-3 text-muted" style="font-size:0.8rem;"><i class="bi bi-inbox fs-4 mb-2 d-block" style="color:#cbd5e1"></i>Belum ada pembayaran</div>
<?php endif; ?>
</div>
</div>

<!-- KOLOM TENGAH: Detail Paket & Jadwal -->
<div class="col-lg-4 fade-in-up" style="animation-delay:0.1s;">
<!-- Detail Paket -->
<div class="card-3d mb-4">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-purple" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-box-seam-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Detail Paket</div><div class="text-muted" style="font-size:0.75rem;">Informasi pemotretan</div></div>
</div>
<div class="info-row"><span class="info-label">Paket</span><span class="info-value"><?= htmlspecialchars($row['Nama_Paket']) ?></span></div>
<div class="info-row"><span class="info-label">Durasi</span><span class="info-value"><?= $row['Durasi_Waktu'] ?> menit</span></div>
<div class="info-row"><span class="info-label">Harga</span><span class="info-value">Rp <?= number_format((float)$row['Harga_Paket'], 0, ',', '.') ?></span></div>
<div class="info-row"><span class="info-label">Kapasitas</span><span class="info-value"><?= $row['Kapasitas_Orang'] ?> orang</span></div>
<div class="info-row"><span class="info-label">Ruangan</span><span class="info-value"><?= htmlspecialchars($row['Nama_Ruangan']) ?></span></div>
<div class="info-row"><span class="info-label">Tema</span><span class="info-value"><?= htmlspecialchars($row['Nama_Tema']) ?></span></div>
</div>

<!-- Jadwal -->
<div class="card-3d mb-4">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-orange" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-calendar-event-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Jadwal Pemotretan</div><div class="text-muted" style="font-size:0.75rem;">Waktu dan tempat</div></div>
</div>
<div class="info-row"><span class="info-label">Tanggal</span><span class="info-value"><?= $tanggal_jadwal ?></span></div>
<div class="info-row"><span class="info-label">Jam</span><span class="info-value"><?= $jam_mulai ?> - <?= $jam_selesai ?></span></div>
<div class="info-row"><span class="info-label">Waktu Mulai Aktual</span><span class="info-value"><?= $waktu_mulai ?></span></div>
<div class="info-row"><span class="info-label">Waktu Selesai Aktual</span><span class="info-value"><?= $waktu_selesai ?></span></div>
<?php if ($row['Keterangan_Order']): ?>
<div class="mt-2 p-3" style="background:#fffbeb;border-radius:12px;border-left:3px solid #d97706;"><div class="info-label mb-1" style="color:#d97706;">Keterangan</div><div style="font-size:0.8rem;"><?= htmlspecialchars($row['Keterangan_Order']) ?></div></div>
<?php endif; ?>
</div>

<!-- Timeline -->
<div class="card-3d">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-blue" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-clock-history"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Timeline</div><div class="text-muted" style="font-size:0.75rem;">Riwayat aktivitas</div></div>
</div>
<div class="timeline-detail">
<div class="timeline-item-detail <?= $row['Status_Sesi'] != 2 ? 'completed' : '' ?>"><div class="fw-bold" style="font-size:0.85rem;">Sesi Dibuat</div><div class="text-muted" style="font-size:0.75rem;"><?= $created_date ?></div></div>
<?php if ($has_fotografer): ?>
<div class="timeline-item-detail <?= $row['Status_Sesi'] != 2 ? 'completed' : '' ?>"><div class="fw-bold" style="font-size:0.85rem;">Fotografer Di-assign</div><div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($row['Nama_Fotografer']) ?></div></div>
<?php endif; ?>
<?php if ($row['Waktu_Mulai']): ?>
<div class="timeline-item-detail <?= $row['Status_Sesi'] == 1 ? 'completed' : '' ?>"><div class="fw-bold" style="font-size:0.85rem;">Pemotretan Dimulai</div><div class="text-muted" style="font-size:0.75rem;"><?= $waktu_mulai ?></div></div>
<?php endif; ?>
<?php if ($row['Waktu_Selesai']): ?>
<div class="timeline-item-detail <?= $row['Status_Sesi'] == 1 ? 'completed' : '' ?>"><div class="fw-bold" style="font-size:0.85rem;">Pemotretan Selesai</div><div class="text-muted" style="font-size:0.75rem;"><?= $waktu_selesai ?></div></div>
<?php endif; ?>
<?php if ($has_file): ?>
<div class="timeline-item-detail completed"><div class="fw-bold" style="font-size:0.85rem;">Hasil Diupload</div><div class="text-muted" style="font-size:0.75rem;"><?= $tanggal_upload ?></div></div>
<?php endif; ?>
<?php if ($row['Status_Sesi'] == 2): ?>
<div class="timeline-item-detail cancelled"><div class="fw-bold" style="font-size:0.85rem;color:#dc2626;">Sesi Dibatalkan</div><div class="text-muted" style="font-size:0.75rem;">Oleh: <?= htmlspecialchars($row['Modified_By'] ?? 'Admin') ?> &bull; <?= $modified_date ?></div></div>
<?php endif; ?>
</div>
</div>
</div>

<!-- KOLOM KANAN: Fotografer & Hasil Foto -->
<div class="col-lg-4 fade-in-up" style="animation-delay:0.2s;">
<!-- Fotografer -->
<div class="card-3d mb-4">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-purple" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-camera-fill"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Fotografer</div><div class="text-muted" style="font-size:0.75rem;">Penanggung jawab</div></div>
</div>
<?php if ($has_fotografer): ?>
<div class="text-center mb-3">
<div class="mx-auto mb-2" style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--s-pink),var(--light-pink));display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--p-pink);"><i class="bi bi-person-fill"></i></div>
<div class="fw-bold" style="font-size:1rem;"><?= htmlspecialchars($row['Nama_Fotografer']) ?></div>
<div class="text-muted" style="font-size:0.8rem;">@<?= htmlspecialchars($row['Username_Fotografer']) ?></div>
</div>
<div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($row['Email_Fotografer'] ?? '-') ?></span></div>
<div class="info-row"><span class="info-label">No. HP</span><span class="info-value"><?= htmlspecialchars($row['NoHp_Fotografer'] ?? '-') ?></span></div>
<?php else: ?>
<div class="text-center py-4">
<div class="stat-icon stat-icon-blue mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem;"><i class="bi bi-person-x"></i></div>
<div class="fw-bold text-muted mb-1">Belum Ada Fotografer</div>
<div class="text-muted" style="font-size:0.8rem;margin-bottom:15px;">Assign fotografer untuk sesi ini</div>
<button onclick="bukaModalAssign()" class="btn-action btn-action-warning"><i class="bi bi-person-plus"></i> Assign Sekarang</button>
</div>
<?php endif; ?>
</div>

<!-- Hasil Foto -->
<div class="card-3d">
<div class="d-flex align-items-center gap-3 mb-3">
<div class="stat-icon stat-icon-green" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi bi-images"></i></div>
<div><div class="fw-bold" style="font-size:0.9rem;">Hasil Foto</div><div class="text-muted" style="font-size:0.75rem;">File hasil pemotretan</div></div>
</div>
<?php if ($has_file): ?>
<div class="file-preview-box mb-3">
<i class="bi bi-file-earmark-zip" style="font-size:3rem;color:var(--p-pink);"></i>
<div class="fw-bold mt-2" style="font-size:0.9rem;word-break:break-all;"><?= htmlspecialchars($row['File_Hasil']) ?></div>
<div class="text-muted" style="font-size:0.75rem;">Diupload: <?= $tanggal_upload ?></div>
</div>
<a href="../../uploads/sesi_foto/<?= htmlspecialchars($row['File_Hasil']) ?>" target="_blank" class="btn-action btn-action-success w-100 justify-content-center"><i class="bi bi-download"></i> Unduh Hasil Foto</a>
<?php else: ?>
<div class="text-center py-4">
<div class="stat-icon stat-icon-orange mx-auto mb-3" style="width:60px;height:60px;font-size:1.5rem;"><i class="bi bi-image"></i></div>
<div class="fw-bold text-muted mb-1">Belum Ada Hasil</div>
<div class="text-muted" style="font-size:0.8rem;margin-bottom:15px;">
<?php if ($row['Status_Sesi'] == 0): ?>Fotografer akan upload setelah sesi selesai<?php elseif ($row['Status_Sesi'] == 1): ?>Menunggu fotografer mengupload hasil<?php else: ?>Sesi dibatalkan, tidak ada hasil<?php endif; ?>
</div>
</div>
<?php endif; ?>
</div>
</div>
</div>
</div>

<!-- MODAL ASSIGN FOTOGRAFER -->
<div class="modal-overlay" id="modalAssign">
<div class="modal-content-custom" style="max-width:450px;">
<div class="modal-header-custom">
<div class="modal-title-custom"><i class="bi bi-person-plus" style="color:var(--p-pink);margin-right:8px;"></i> Assign Fotografer</div>
<button class="modal-close-custom" onclick="tutupModal('modalAssign')">&times;</button>
</div>
<div class="modal-body-custom">
<p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:20px;">Pilih fotografer untuk menangani sesi foto ini:</p>
<div style="margin-bottom:20px;">
<label style="display:block;font-size:0.9rem;font-weight:700;color:var(--text-dark);margin-bottom:10px;">Fotografer</label>
<select id="selectFotografer" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:14px;font-family:inherit;font-weight:600;font-size:0.9rem;cursor:pointer;">
<option value="">-- Pilih Fotografer --</option>
<?php foreach ($fotografer_list as $fg): ?>
<option value="<?= (int)$fg['ID_Karyawan'] ?>"><?= htmlspecialchars($fg['Nama_Karyawan']) ?> (@<?= htmlspecialchars($fg['Username_Karyawan'] ?? $fg['Nama_Karyawan']) ?>)</option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:20px 24px;display:flex;justify-content:flex-end;gap:12px;">
<button class="btn-action btn-action-secondary btn-action-sm" onclick="tutupModal('modalAssign')">Batal</button>
<button class="btn-action btn-action-sm" onclick="submitAssign()"><i class="bi bi-check-lg me-1"></i> Simpan</button>
</div>
</div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle Submenu
document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)';}}});});

function bukaModal(id){document.getElementById(id).classList.add('show')}
function tutupModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(modal=>{modal.addEventListener('click',function(e){if(e.target===this)tutupModal(this.id);});});

function bukaModalAssign(){document.getElementById('selectFotografer').value='';bukaModal('modalAssign');}

function submitAssign(){
const idFotografer=document.getElementById('selectFotografer').value;
if(!idFotografer){Swal.fire({icon:'warning',title:'Pilih Fotografer!',text:'Silakan pilih fotografer terlebih dahulu.',confirmButtonColor:'#D53D66'});return;}

Swal.fire({title:'Konfirmasi Assign?',text:'Fotografer akan ditugaskan untuk sesi #<?= $id_sesi ?>.',icon:'question',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Assign',cancelButtonText:'Batal'}).then((result)=>{
if(result.isConfirmed){
const formData=new FormData();
formData.append('ajax_assign','1');
formData.append('id_sesi','<?= $id_sesi ?>');
formData.append('id_fotografer',idFotografer);

fetch('list.php',{method:'POST',body:formData})
.then(res=>res.json())
.then(data=>{
if(data.success){
Swal.fire({icon:'success',title:'Berhasil!',text:data.message,confirmButtonColor:'#D53D66',timer:1500,showConfirmButton:false}).then(()=>{tutupModal('modalAssign');location.reload();});
}else{
Swal.fire({icon:'error',title:'Gagal!',text:data.message,confirmButtonColor:'#D53D66'});
}
})
.catch(err=>{Swal.fire({icon:'error',title:'Error!',text:'Terjadi kesalahan koneksi.',confirmButtonColor:'#D53D66'});});
}
});
}

function konfirmasiBatal(idSesi){
Swal.fire({title:'Batalkan Sesi?',text:'Sesi foto akan dibatalkan. Pelanggan akan diberitahu.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#718096',confirmButtonText:'Ya, Batalkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='batal.php?id='+idSesi;}});
}

function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php';}});}
function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php';}});}
function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'});}
function updateLiveClock(){const now=new Date();const days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];document.getElementById('live-clock').innerText=`${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;}
setInterval(updateLiveClock,1000);updateLiveClock();
</script>
</body>
</html>