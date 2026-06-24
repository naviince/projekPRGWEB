<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL ID DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) ||
    !isset($_GET['id_tema']) || empty($_GET['id_tema']) ||
    !isset($_GET['id_jadwal']) || empty($_GET['id_jadwal'])) {
    header("Location: ../../index.php?error=data_tidak_lengkap");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema = (int)$_GET['id_tema'];
$id_jadwal = (int)$_GET['id_jadwal'];

// =====================================================
// AMBIL DATA PAKET
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    array($id_paket, STATUS_DATA_AKTIF)
);
if ($q_paket === false) {
    die("Error query Paket: " . print_r(sqlsrv_errors(), true));
}
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA RUANGAN
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi, Foto_Ruangan 
     FROM Ruangan 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
if ($q_ruangan === false) {
    die("Error query Ruangan: " . print_r(sqlsrv_errors(), true));
}
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);

if (!$d_ruangan) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA TEMA
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT ID_Tema, Nama_Tema, Kategori_Tema, Deskripsi, Foto_Tema 
     FROM Tema_Foto 
     WHERE ID_Tema = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_tema)
);
if ($q_tema === false) {
    die("Error query Tema: " . print_r(sqlsrv_errors(), true));
}
$d_tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC);

if (!$d_tema) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA JADWAL
// =====================================================
$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal, ID_Ruangan, ID_Paket, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal
     FROM Jadwal_Studio 
     WHERE ID_Jadwal = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_jadwal)
);
if ($q_jadwal === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}
$d_jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC);

if (!$d_jadwal) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_ditemukan");
    exit();
}

// Validasi: jadwal harus untuk ruangan dan paket yang dipilih
if ((int)$d_jadwal['ID_Ruangan'] !== $id_ruangan || (int)$d_jadwal['ID_Paket'] !== $id_paket) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_valid");
    exit();
}

// Validasi: jadwal harus tersedia
if ((int)$d_jadwal['Status_Jadwal'] !== STATUS_JADWAL_TERSEDIA) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_sudah_dibooking");
    exit();
}

// Format tanggal dan jam jadwal
$tgl_obj = $d_jadwal['Tanggal_Jadwal'];
if (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) {
    $tgl_str = $tgl_obj->format('Y-m-d');
    $hari_idx = (int)$tgl_obj->format('w');
    $tgl_num = $tgl_obj->format('d');
    $bln_idx = (int)$tgl_obj->format('n') - 1;
    $thn = $tgl_obj->format('Y');
} else {
    $tgl_str = $tgl_obj;
    $ts = strtotime($tgl_str);
    $hari_idx = date('w', $ts);
    $tgl_num = date('d', $ts);
    $bln_idx = (int)date('n', $ts) - 1;
    $thn = date('Y', $ts);
}

$jam_mulai_obj = $d_jadwal['Jam_Mulai'];
if (is_object($jam_mulai_obj) && method_exists($jam_mulai_obj, 'format')) {
    $jam_mulai_str = $jam_mulai_obj->format('H:i');
} else {
    $jam_mulai_str = substr($jam_mulai_obj, 0, 5);
}

$jam_selesai_obj = $d_jadwal['Jam_Selesai'];
if (is_object($jam_selesai_obj) && method_exists($jam_selesai_obj, 'format')) {
    $jam_selesai_str = $jam_selesai_obj->format('H:i');
} else {
    $jam_selesai_str = substr($jam_selesai_obj, 0, 5);
}

$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$jadwal_hari = $hari_indo[$hari_idx];
$jadwal_tgl_format = $tgl_num . ' ' . $bulan_indo[$bln_idx] . ' ' . $thn;

// =====================================================
// VALIDASI RELASI
// =====================================================
$q_validasi_pr = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", 
    array($id_paket, $id_ruangan)
);
if ($q_validasi_pr === false) {
    die("Error query Validasi PR: " . print_r(sqlsrv_errors(), true));
}
$d_validasi_pr = sqlsrv_fetch_array($q_validasi_pr, SQLSRV_FETCH_ASSOC);
if ($d_validasi_pr['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

$q_validasi_rt = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ? AND ID_Tema = ?", 
    array($id_ruangan, $id_tema)
);
if ($q_validasi_rt === false) {
    die("Error query Validasi RT: " . print_r(sqlsrv_errors(), true));
}
$d_validasi_rt = sqlsrv_fetch_array($q_validasi_rt, SQLSRV_FETCH_ASSOC);
if ($d_validasi_rt['total'] == 0) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
if ($q_profile === false) {
    die("Error query Profil: " . print_r(sqlsrv_errors(), true));
}
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// HITUNG HARGA
// =====================================================
$harga_paket = (float)$d_paket['Harga_Paket'];
$dp_amount = $harga_paket * 0.65; // DP 65%
$sisa_amount = $harga_paket - $dp_amount;

$harga_format = number_format($harga_paket, 0, ',', '.');
$dp_format = number_format($dp_amount, 0, ',', '.');
$sisa_format = number_format($sisa_amount, 0, ',', '.');

// =====================================================
// AMBIL PROPERTI RUANGAN (untuk ditampilkan)
// =====================================================
$q_properti = sqlsrv_query($conn, 
    "SELECT Nama_Properti, Kategori_Properti 
     FROM Properti 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
$properti_list = [];
if ($q_properti !== false) {
    while ($p = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
        $properti_list[] = $p;
    }
}

// =====================================================
// CEK APAKAH JADWAL MASIH TERSEDIA (REAL-TIME)
// =====================================================
$q_cek_jadwal = sqlsrv_query($conn, 
    "SELECT Status_Jadwal FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_jadwal)
);
if ($q_cek_jadwal === false) {
    die("Error cek jadwal: " . print_r(sqlsrv_errors(), true));
}
$d_cek_jadwal = sqlsrv_fetch_array($q_cek_jadwal, SQLSRV_FETCH_ASSOC);
$jadwal_masih_tersedia = ($d_cek_jadwal && (int)$d_cek_jadwal['Status_Jadwal'] === STATUS_JADWAL_TERSEDIA);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Booking - SpotLight Studio</title>
    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --success: #059669;
            --warning: #d97706;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS ===== */
        .top-navbar {
            background: #ffffff;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        .nav-link-item {
            color: #4a5568;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 8px 0;
            position: relative;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--p-pink);
            border-radius: 3px;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.25);
        }
        .nav-btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.35);
            color: #fff;
        }
        .nav-avatar-wrapper {
            position: relative;
        }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: all 0.3s;
        }
        .nav-avatar:hover {
            transform: scale(1.1);
            border-color: var(--p-pink);
        }
        .nav-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 12px;
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid #f1f5f9;
        }
        .nav-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 0;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== BREADCRUMB BAR ===== */
        .breadcrumb-bar {
            background: #ffffff;
            padding: 16px 40px;
            border-bottom: 1px solid #f1f5f9;
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
        }
        .breadcrumb-inner a:hover { color: var(--p-pink); }
        .breadcrumb-inner .separator {
            color: #cbd5e1;
        }
        .breadcrumb-inner .current {
            color: var(--p-pink);
            font-weight: 700;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== PROGRESS BAR ===== */
        .progress-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 30px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .progress-step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            border: 3px solid #e2e8f0;
            background: #ffffff;
            color: #94a3b8;
            transition: all 0.3s;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .progress-step.completed .progress-step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #ffffff;
        }
        .progress-step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: var(--success); }
        .progress-line {
            width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            margin-bottom: 24px;
        }
        .progress-line.completed { background: var(--success); }

        /* ===== KONFIRMASI SECTION ===== */
        .konfirmasi-section {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 40px;
            margin-bottom: 40px;
        }

        /* ===== DETAIL CARD ===== */
        .detail-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }
        .detail-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-title i { color: var(--p-pink); }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 16px;
            background: var(--s-pink);
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .detail-item:hover {
            transform: translateX(4px);
        }
        .detail-item:last-child { margin-bottom: 0; }
        .detail-img {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .detail-info { flex: 1; }
        .detail-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .detail-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== JADWAL CARD ===== */
        .jadwal-card {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 24px;
            padding: 24px;
            color: #ffffff;
            margin-bottom: 20px;
        }
        .jadwal-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .jadwal-card-main {
            font-size: 1.4rem;
            font-weight: 900;
            margin-bottom: 4px;
        }
        .jadwal-card-sub {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .jadwal-card-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 24px;
            top: 24px;
        }

        /* ===== PROPERTI CARD ===== */
        .properti-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }
        .properti-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .properti-tag {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* ===== RINGKASAN HARGA ===== */
        .harga-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .harga-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .harga-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .harga-row:last-child { border-bottom: none; }
        .harga-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .harga-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .harga-value.total {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .harga-divider {
            height: 2px;
            background: #f1f5f9;
            margin: 16px 0;
        }
        .harga-dp-info {
            background: var(--s-pink);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            border: 2px dashed var(--light-pink);
        }
        .harga-dp-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--p-pink);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .harga-dp-amount {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .harga-dp-note {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* ===== BUTTONS ===== */
        .btn-group-konfirmasi {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-konfirmasi {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 16px 24px;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
            text-decoration: none;
        }
        .btn-konfirmasi:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(216, 63, 103, 0.4);
            color: #ffffff;
        }
        .btn-konfirmasi:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        .btn-kembali {
            background: #ffffff;
            color: var(--text-muted);
            border: 2px solid #e2e8f0;
            padding: 14px 24px;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-kembali:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        /* ===== WARNING BOX ===== */
        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .warning-box i {
            font-size: 1.5rem;
            color: #d97706;
            flex-shrink: 0;
        }
        .warning-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: #92400e;
        }

        /* ===== INFO BOX ===== */
        .info-box {
            background: var(--s-pink);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .info-box i {
            font-size: 1.5rem;
            color: var(--p-pink);
            flex-shrink: 0;
            margin-top: 2px;
        }
        .info-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.6;
        }
        .info-text strong { color: var(--p-pink); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .konfirmasi-section { grid-template-columns: 1fr; }
            .harga-card { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Booking/Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                        <i class="bi bi-house-door"></i> Kembali ke Beranda
                    </a>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item logout" onclick="confirmLogout()">
                        <i class="bi bi-box-arrow-right"></i> Keluar Sistem
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>">Jadwal</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Konfirmasi</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR: 1.Paket -> 2.Ruangan -> 3.Tema -> 4.Jadwal -> 5.Konfirmasi -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Jadwal</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
        </div>

        <?php if (!$jadwal_masih_tersedia): ?>
        <!-- WARNING: Jadwal sudah tidak tersedia -->
        <div class="warning-box">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div class="warning-text">
                Maaf, jadwal yang Anda pilih sudah tidak tersedia. Mungkin sudah dibooking oleh pelanggan lain. Silakan pilih jadwal lain.
            </div>
        </div>
        <?php endif; ?>

        <!-- INFO BOX -->
        <div class="info-box">
            <i class="bi bi-info-circle-fill"></i>
            <div class="info-text">
                Silakan periksa kembali detail booking Anda. Setelah menekan <strong>"Buat Order & Bayar DP"</strong>, jadwal akan terblokir dan Anda wajib membayar DP sebesar <strong>65%</strong> dari total harga. Order yang tidak dibayar dalam waktu 24 jam akan otomatis dibatalkan.
            </div>
        </div>

        <!-- KONFIRMASI SECTION -->
        <div class="konfirmasi-section">
            <!-- Left: Detail Booking -->
            <div>
                <!-- Paket -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-box-seam-fill"></i> Paket Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/paket/<?= htmlspecialchars($d_paket['Foto_Paket']) ?>" class="detail-img" alt="Paket">
                        <div class="detail-info">
                            <div class="detail-label">Paket Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></div>
                            <div class="detail-sub"><?= (int)$d_paket['Durasi_Waktu'] ?> menit &bull; Max <?= (int)$d_paket['Kapasitas_Orang'] ?> orang</div>
                        </div>
                    </div>
                </div>

                <!-- Ruangan -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-door-open-fill"></i> Ruangan</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/ruangan/<?= htmlspecialchars($d_ruangan['Foto_Ruangan']) ?>" class="detail-img" alt="Ruangan">
                        <div class="detail-info">
                            <div class="detail-label">Ruangan Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_ruangan['Deskripsi']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tema -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-image-fill"></i> Tema Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/tema/<?= htmlspecialchars($d_tema['Foto_Tema']) ?>" class="detail-img" alt="Tema">
                        <div class="detail-info">
                            <div class="detail-label">Tema Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_tema['Kategori_Tema']) ?> &bull; <?= htmlspecialchars($d_tema['Deskripsi']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Jadwal -->
                <div class="detail-card" style="position:relative; overflow:hidden;">
                    <div class="detail-title"><i class="bi bi-calendar-check-fill"></i> Jadwal Sesi</div>
                    <div class="jadwal-card">
                        <div class="jadwal-card-title"><i class="bi bi-clock"></i> Jadwal Tersedia</div>
                        <div class="jadwal-card-main"><?= htmlspecialchars($jadwal_hari) ?>, <?= htmlspecialchars($jadwal_tgl_format) ?></div>
                        <div class="jadwal-card-sub"><i class="bi bi-clock-fill"></i> <?= htmlspecialchars($jam_mulai_str) ?> - <?= htmlspecialchars($jam_selesai_str) ?> WIB</div>
                    </div>
                </div>

                <!-- Properti -->
                <?php if (!empty($properti_list)): ?>
                <div class="properti-card">
                    <div class="detail-title"><i class="bi bi-stars"></i> Properti Tersedia</div>
                    <div class="detail-sub" style="margin-bottom:8px;">Fasilitas yang tersedia di ruangan ini:</div>
                    <div class="properti-tags">
                        <?php foreach ($properti_list as $prop): ?>
                        <span class="properti-tag">
                            <i class="bi bi-check-circle-fill" style="font-size:0.7rem;margin-right:4px;"></i>
                            <?= htmlspecialchars($prop['Nama_Properti']) ?> (<?= htmlspecialchars($prop['Kategori_Properti']) ?>)
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Ringkasan Harga -->
            <div class="harga-card">
                <div class="harga-title"><i class="bi bi-receipt"></i> Ringkasan Pembayaran</div>

                <div class="harga-row">
                    <span class="harga-label">Harga Paket</span>
                    <span class="harga-value">Rp <?= $harga_format ?></span>
                </div>
                <div class="harga-row">
                    <span class="harga-label">Biaya Ruangan</span>
                    <span class="harga-value" style="color:var(--success);">Gratis</span>
                </div>
                <div class="harga-row">
                    <span class="harga-label">Biaya Tema</span>
                    <span class="harga-value" style="color:var(--success);">Gratis</span>
                </div>
                <div class="harga-row">
                    <span class="harga-label">Biaya Properti</span>
                    <span class="harga-value" style="color:var(--success);">Gratis</span>
                </div>

                <div class="harga-divider"></div>

                <div class="harga-row">
                    <span class="harga-label" style="font-weight:800;">Total Harga</span>
                    <span class="harga-value total">Rp <?= $harga_format ?></span>
                </div>

                <div class="harga-dp-info">
                    <div class="harga-dp-title"><i class="bi bi-cash-stack"></i> Pembayaran DP (65%)</div>
                    <div class="harga-dp-amount">Rp <?= $dp_format ?></div>
                    <div class="harga-dp-note">
                        Sisa pembayaran <strong>Rp <?= $sisa_format ?></strong> dibayar setelah sesi foto selesai.
                    </div>
                </div>

                <div class="btn-group-konfirmasi">
                    <?php if ($jadwal_masih_tersedia): ?>
                    <button class="btn-konfirmasi" onclick="konfirmasiBooking()">
                        <i class="bi bi-check2-circle"></i> Buat Order & Bayar DP
                    </button>
                    <?php else: ?>
                    <button class="btn-konfirmasi" disabled>
                        <i class="bi bi-x-circle"></i> Jadwal Tidak Tersedia
                    </button>
                    <?php endif; ?>
                    <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>" class="btn-kembali">
                        <i class="bi bi-arrow-left"></i> Kembali ke Jadwal
                    </a>
                </div>
            </div>
        </div>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan meninggalkan halaman customer dan kembali ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../index.php';
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        function konfirmasiBooking() {
            Swal.fire({
                title: 'Konfirmasi Booking?',
                html: '<div style="text-align:left;font-size:0.95rem;">' +
                      '<p>Anda akan membuat order dengan detail:</p>' +
                      '<ul style="margin-top:8px;padding-left:20px;">' +
                      '<li><strong>Paket:</strong> <?= json_encode(htmlspecialchars($d_paket['Nama_Paket'])) ?></li>' +
                      '<li><strong>Ruangan:</strong> <?= json_encode(htmlspecialchars($d_ruangan['Nama_Ruangan'])) ?></li>' +
                      '<li><strong>Tema:</strong> <?= json_encode(htmlspecialchars($d_tema['Nama_Tema'])) ?></li>' +
                      '<li><strong>Jadwal:</strong> <?= json_encode(htmlspecialchars($jadwal_hari . ', ' . $jadwal_tgl_format . ' | ' . $jam_mulai_str . ' - ' . $jam_selesai_str)) ?></li>' +
                      '</ul>' +
                      '<p style="margin-top:12px;color:#d83f67;font-weight:700;">Total: Rp <?= $harga_format ?></p>' +
                      '<p style="margin-top:4px;color:#718096;font-size:0.85rem;">DP yang harus dibayar: Rp <?= $dp_format ?></p>' +
                      '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Buat Order',
                cancelButtonText: 'Batal',
                width: 480
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim ke proses_order.php
                    window.location.href = 'proses_order.php?id_paket=<?= (int)$id_paket ?>&id_ruangan=<?= (int)$id_ruangan ?>&id_tema=<?= (int)$id_tema ?>&id_jadwal=<?= (int)$id_jadwal ?>';
                }
            });
        }
    </script>
</body>
</html>