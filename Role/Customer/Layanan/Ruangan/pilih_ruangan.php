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
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL ID PAKET & ID RUANGAN DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];

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
// AMBIL DATA RUANGAN YANG DIPILIH
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
// VALIDASI: RUANGAN HARUS TERHUBUNG DENGAN PAKET
// =====================================================
$q_validasi = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", 
    array($id_paket, $id_ruangan)
);
if ($q_validasi === false) {
    die("Error query Validasi: " . print_r(sqlsrv_errors(), true));
}
$d_validasi = sqlsrv_fetch_array($q_validasi, SQLSRV_FETCH_ASSOC);

if ($d_validasi['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

// =====================================================
// AMBIL PROPERTI RUANGAN (langsung via Properti.ID_Ruangan)
// =====================================================
$q_properti = sqlsrv_query($conn, 
    "SELECT Nama_Properti, Kategori_Properti, Foto_Properti 
     FROM Properti 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0
     ORDER BY Kategori_Properti, Nama_Properti", 
    array($id_ruangan)
);
if ($q_properti === false) {
    die("Error query Properti: " . print_r(sqlsrv_errors(), true));
}

$properti_list = [];
while ($p = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
    $properti_list[] = $p;
}

// =====================================================
// AMBIL TEMA FOTO YANG TERSEDIA DI RUANGAN INI (via Ruangan_Tema)
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT t.ID_Tema, t.Nama_Tema, t.Deskripsi, t.Foto_Tema 
     FROM Tema_Foto t
     INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
     WHERE rt.ID_Ruangan = ? AND t.Status = 1 AND t.Is_Deleted = 0
     ORDER BY t.Nama_Tema", 
    array($id_ruangan)
);
if ($q_tema === false) {
    die("Error query Tema: " . print_r(sqlsrv_errors(), true));
}

$tema_list = [];
while ($t = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
    $tema_list[] = $t;
}

// =====================================================
// AMBIL JADWAL TERSEDIA DI RUANGAN INI (7 hari ke depan)
// Status_Jadwal = 0 = Tersedia
// =====================================================
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+7 days'));

$q_jadwal_preview = sqlsrv_query($conn, 
    "SELECT TOP 5 j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai, j.Keterangan
     FROM Jadwal_Studio j
     WHERE j.ID_Ruangan = ? AND j.ID_Paket = ?
       AND j.Tanggal_Jadwal >= ? 
       AND j.Tanggal_Jadwal <= ?
       AND j.Status_Jadwal = ?
       AND j.Status = ?
       AND j.Is_Deleted = 0
     ORDER BY j.Tanggal_Jadwal, j.Jam_Mulai ASC", 
    array($id_ruangan, $id_paket, $today, $next_week, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
);
if ($q_jadwal_preview === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}

$jadwal_list = [];
while ($j = sqlsrv_fetch_array($q_jadwal_preview, SQLSRV_FETCH_ASSOC)) {
    $jadwal_list[] = $j;
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

// Path foto
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : "../../../../assets/img/paket/default_paket.jpg";

$foto_ruangan = ($d_ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'])) 
    ? "../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'] 
    : "../../../../assets/img/ruangan/default_ruangan.jpg";

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');

// Icon mapping properti
$icon_map = [
    'Mebel' => 'bi-chair',
    'Lampu' => 'bi-lightbulb',
    'Dekorasi' => 'bi-stars',
    'Kostum' => 'bi-person-badge',
    'Latar' => 'bi-image',
    'Aksesoris' => 'bi-gem',
    'Properti' => 'bi-box',
    'Background' => 'bi-image-alt',
    'Lighting' => 'bi-lightbulb-fill',
    'Furniture' => 'bi-sofa',
    'Properti Foto' => 'bi-camera',
    'Default' => 'bi-box-seam'
];

// Helper format DateTime
function formatDateTimeSafe($val) {
    if (is_object($val) && method_exists($val, 'format')) {
        return $val->format('Y-m-d');
    }
    return is_string($val) ? $val : '-';
}

function formatTimeSafe($val) {
    if (is_object($val) && method_exists($val, 'format')) {
        return $val->format('H:i');
    }
    return is_string($val) ? substr($val, 0, 5) : '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?> - SpotLight Studio</title>
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
            background: #059669;
            border-color: #059669;
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
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            margin-bottom: 24px;
        }
        .progress-line.completed { background: #059669; }

        /* ===== RINGKASAN BOOKING (STICKY SIDEBAR) ===== */
        .booking-summary {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .summary-item:last-child { border-bottom: none; }
        .summary-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .summary-icon.completed { background: #d1fae5; color: #059669; }
        .summary-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .summary-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .summary-harga {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #f1f5f9;
        }
        .btn-lanjut {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 16px;
        }
        .btn-lanjut:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(216, 63, 103, 0.3);
            color: #ffffff;
        }

        /* ===== DETAIL RUANGAN ===== */
        .ruangan-detail-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .ruangan-main {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .ruangan-foto {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .ruangan-body {
            padding: 30px;
        }
        .ruangan-badge {
            display: inline-block;
            padding: 6px 16px;
            background: var(--s-pink);
            color: var(--p-pink);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .ruangan-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 12px;
        }
        .ruangan-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .ruangan-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .ruangan-meta-item i { color: var(--p-pink); font-size: 1.1rem; }
        .ruangan-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.8;
            margin-bottom: 30px;
        }

        /* ===== PROPERTI SECTION ===== */
        .properti-section {
            margin-top: 30px;
        }
        .properti-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .properti-section-title i { color: var(--p-pink); }
        .properti-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
        }
        .properti-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .properti-card:hover {
            border-color: var(--p-pink);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.1);
        }
        .properti-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1.5rem;
            margin: 0 auto 12px;
        }
        .properti-nama {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .properti-kategori {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== TEMA SECTION ===== */
        .tema-section {
            margin-top: 30px;
        }
        .tema-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .tema-section-title i { color: var(--p-pink); }
        .tema-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        .tema-card {
            background: #f8fafc;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .tema-card:hover {
            border-color: var(--p-pink);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.1);
        }
        .tema-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .tema-img-placeholder {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .tema-body {
            padding: 16px;
        }
        .tema-nama {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .tema-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== JADWAL PREVIEW SECTION ===== */
        .jadwal-section {
            margin-top: 30px;
        }
        .jadwal-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .jadwal-section-title i { color: var(--p-pink); }
        .jadwal-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .jadwal-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s;
        }
        .jadwal-item:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .jadwal-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .jadwal-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1.1rem;
        }
        .jadwal-info {
            display: flex;
            flex-direction: column;
        }
        .jadwal-tanggal {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .jadwal-waktu {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .jadwal-status {
            background: #d1fae5;
            color: #059669;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .ruangan-detail-section { grid-template-columns: 1fr; }
            .booking-summary { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
            .properti-grid { grid-template-columns: repeat(2, 1fr); }
            .tema-grid { grid-template-columns: repeat(2, 1fr); }
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
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
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
            <span class="current"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></span>
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
            <div class="progress-step active">
                <div class="progress-step-circle">2</div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
        </div>

        <!-- RUANGAN DETAIL + SIDEBAR -->
        <div class="ruangan-detail-section">
            <!-- Left: Detail Ruangan -->
            <div class="ruangan-main">
                <img src="<?= $foto_ruangan ?>" class="ruangan-foto" alt="<?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>">
                <div class="ruangan-body">
                    <div class="ruangan-badge">Ruangan Studio</div>
                    <h1 class="ruangan-title"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></h1>
                    <div class="ruangan-meta">
                        <div class="ruangan-meta-item">
                            <i class="bi bi-people-fill"></i>
                            Kapasitas <?= $d_paket['Kapasitas_Orang'] ?> orang
                        </div>
                        <div class="ruangan-meta-item">
                            <i class="bi bi-box-seam-fill"></i>
                            <?= count($properti_list) ?> Properti
                        </div>
                        <div class="ruangan-meta-item">
                            <i class="bi bi-palette-fill"></i>
                            <?= count($tema_list) ?> Tema
                        </div>
                        <div class="ruangan-meta-item">
                            <i class="bi bi-clock-fill"></i>
                            <?= $d_paket['Durasi_Waktu'] ?> menit/sesi
                        </div>
                    </div>
                    <p class="ruangan-desc">
                        <?= htmlspecialchars($d_ruangan['Deskripsi'] ?? 'Ruangan studio dengan fasilitas lengkap untuk sesi foto terbaik Anda. Dilengkapi dengan peralatan profesional dan dekorasi yang dapat disesuaikan dengan tema pilihan Anda.') ?>
                    </p>

                    <!-- PROPERTI TERSEDIA -->
                    <div class="properti-section">
                        <div class="properti-section-title">
                            <i class="bi bi-box-seam-fill"></i>
                            Properti Tersedia (<?= count($properti_list) ?>)
                        </div>
                        <?php if (!empty($properti_list)): ?>
                            <div class="properti-grid">
                                <?php foreach ($properti_list as $p): 
                                    $icon = $icon_map[$p['Kategori_Properti']] ?? 'bi-box-seam';
                                ?>
                                    <div class="properti-card">
                                        <div class="properti-icon">
                                            <i class="bi <?= $icon ?>"></i>
                                        </div>
                                        <div class="properti-nama"><?= htmlspecialchars($p['Nama_Properti']) ?></div>
                                        <div class="properti-kategori"><?= htmlspecialchars($p['Kategori_Properti']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 14px;">
                                <i class="bi bi-info-circle-fill me-2 text-info"></i>
                                <span class="text-muted">Tidak ada properti khusus di ruangan ini.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TEMA FOTO TERSEDIA -->
                    <div class="tema-section">
                        <div class="tema-section-title">
                            <i class="bi bi-palette-fill"></i>
                            Tema Foto Tersedia (<?= count($tema_list) ?>)
                        </div>
                        <?php if (!empty($tema_list)): ?>
                            <div class="tema-grid">
                                <?php foreach ($tema_list as $t): 
                                    $foto_tema = ($t['Foto_Tema'] && file_exists("../../../../assets/img/tema/" . $t['Foto_Tema'])) 
                                        ? "../../../../assets/img/tema/" . $t['Foto_Tema'] 
                                        : null;
                                ?>
                                    <div class="tema-card">
                                        <?php if ($foto_tema): ?>
                                            <img src="<?= $foto_tema ?>" class="tema-img" alt="<?= htmlspecialchars($t['Nama_Tema']) ?>">
                                        <?php else: ?>
                                            <div class="tema-img-placeholder">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="tema-body">
                                            <div class="tema-nama"><?= htmlspecialchars($t['Nama_Tema']) ?></div>
                                            <div class="tema-desc"><?= htmlspecialchars($t['Deskripsi'] ?? 'Tema foto untuk sesi Anda.') ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 14px;">
                                <i class="bi bi-info-circle-fill me-2 text-info"></i>
                                <span class="text-muted">Tidak ada tema foto tersedia di ruangan ini.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- JADWAL PREVIEW -->
                    <div class="jadwal-section">
                        <div class="jadwal-section-title">
                            <i class="bi bi-calendar-week-fill"></i>
                            Jadwal Tersedia (7 Hari ke Depan)
                        </div>
                        <?php if (!empty($jadwal_list)): ?>
                            <div class="jadwal-list">
                                <?php foreach ($jadwal_list as $j): 
                                    $tgl_str = formatDateTimeSafe($j['Tanggal_Jadwal']);
                                    $jam_mulai = formatTimeSafe($j['Jam_Mulai']);
                                    $jam_selesai = formatTimeSafe($j['Jam_Selesai']);
                                    $hari = date('l', strtotime($tgl_str));
                                    $hari_id = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'][$hari] ?? $hari;
                                ?>
                                    <div class="jadwal-item">
                                        <div class="jadwal-left">
                                            <div class="jadwal-icon"><i class="bi bi-clock"></i></div>
                                            <div class="jadwal-info">
                                                <div class="jadwal-tanggal"><?= $hari_id ?>, <?= date('d M Y', strtotime($tgl_str)) ?></div>
                                                <div class="jadwal-waktu"><?= $jam_mulai ?> - <?= $jam_selesai ?></div>
                                            </div>
                                        </div>
                                        <div class="jadwal-status">Tersedia</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border-2 border-dashed" style="border-color: #e2e8f0; border-radius: 14px;">
                                <i class="bi bi-info-circle-fill me-2 text-info"></i>
                                <span class="text-muted">Tidak ada jadwal tersedia untuk ruangan ini dalam 7 hari ke depan.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Sidebar Ringkasan -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title">Ringkasan Booking</div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Paket</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_paket['Nama_Paket']) ?> (<?= $d_paket['Durasi_Waktu'] ?> menit)</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Ruangan</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-palette"></i></div>
                        <div>
                            <div class="summary-text">Tema</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-calendar"></i></div>
                        <div>
                            <div class="summary-text">Jadwal</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>
                    <div class="summary-harga">Rp <?= $harga_format ?> <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">/ sesi</span></div>
                    <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="btn-lanjut">
                        <i class="bi bi-arrow-right-circle-fill"></i>
                        Lanjut ke Pilih Tema
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
    </script>
</body>
</html>