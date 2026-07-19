<?php
session_start();
include '../../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

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

// --- Fungsi Pembantu Format Tanggal Indonesia (Antisipasi Fatal Error) ---
if (!function_exists('fmtTgl')) {
    function fmtTgl($date_str) {
        if (empty($date_str)) return '-';
        $timestamp = strtotime($date_str);
        if (!$timestamp) return $date_str;
        
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $d = date('j', $timestamp);
        $m = (int)date('n', $timestamp);
        $y = date('Y', $timestamp);
        
        return "$d " . ($months[$m] ?? '') . " $y";
    }
}

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
// AMBIL TEMA YANG TERHUBUNG DENGAN RUANGAN (VIA Ruangan_Tema)
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT 
        t.ID_Tema,
        t.Nama_Tema,
        t.Kategori_Tema,
        t.Deskripsi,
        t.Foto_Tema
     FROM Tema_Foto t
     INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
     WHERE rt.ID_Ruangan = ?
       AND t.Status = 1
       AND t.Is_Deleted = 0
     ORDER BY t.Nama_Tema ASC", 
    array($id_ruangan)
);
if ($q_tema === false) {
    die("Error query Tema: " . print_r(sqlsrv_errors(), true));
}

$tema_list = [];
while ($row = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
    $tema_list[] = $row;
}

// =====================================================
// AMBIL PROFIL CUSTOMER LENGKAP
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil, Username_Pelanggan, Email_Pelanggan, No_Hp, Alamat, Jenis_Kelamin, Tanggal_Lahir FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
if ($q_profile === false) {
    die("Error query Profil: " . print_r(sqlsrv_errors(), true));
}
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);

$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$username_customer = $d_profile['Username_Pelanggan'] ?? '';
$email_customer = $d_profile['Email_Pelanggan'] ?? 'customer@spotlight.com';
$no_hp_customer = $d_profile['No_Hp'] ?? '';
$alamat_customer = $d_profile['Alamat'] ?? '';
$jk_customer = $d_profile['Jenis_Kelamin'] ?? 'Laki-laki';
$tgl_lahir_raw = $d_profile['Tanggal_Lahir'] ?? '';
$tgl_lahir_str = '';
if (is_object($tgl_lahir_raw) && method_exists($tgl_lahir_raw, 'format')) {
    $tgl_lahir_str = $tgl_lahir_raw->format('Y-m-d');
} else if (is_string($tgl_lahir_raw)) {
    $tgl_lahir_str = substr($tgl_lahir_raw, 0, 10);
}

$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// Path foto paket & ruangan
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : null;

$foto_ruangan = ($d_ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'])) 
    ? "../../../../assets/img/ruangan/" . $d_ruangan['Foto_Ruangan'] 
    : null;

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Tema Foto - SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
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
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-soft: 0 4px 24px rgba(0, 0, 0, 0.06);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 48px rgba(216, 63, 103, 0.18);
            --shadow-glow: 0 0 40px rgba(216, 63, 103, 0.15);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
            --transition-smooth: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* ===== SCROLLBAR CUSTOM ===== */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--light-pink); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--p-pink); }

        /* ===== NAVBAR ATAS ===== */
        .top-navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 14px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
            border-bottom: 1px solid var(--glass-border);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.7rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
            transition: var(--transition-smooth);
        }
        .nav-logo:hover { transform: scale(1.02); }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.85rem; }
        .nav-menu-center {
            display: flex;
            gap: 36px;
            align-items: center;
        }
        .nav-link-item {
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: var(--transition-smooth);
            padding: 8px 4px;
            position: relative;
        }
        .nav-link-item::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 3px;
            transition: var(--transition-smooth);
            transform: translateX(-50%);
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item:hover::after, .nav-link-item.active::after {
            width: 100%;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 22px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-btn-booking:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 28px rgba(216, 63, 103, 0.4);
            color: #fff;
        }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid var(--light-pink);
            cursor: pointer;
            transition: var(--transition-smooth);
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.15);
        }
        .nav-avatar:hover {
            transform: scale(1.12) rotate(3deg);
            border-color: var(--p-pink);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .nav-dropdown {
            position: absolute;
            top: 58px;
            right: -8px;
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card), 0 0 0 1px rgba(0,0,0,0.04);
            padding: 12px;
            min-width: 240px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--glass-border);
            animation: dropdownSlide 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .nav-dropdown.show { display: block; }
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .dropdown-item i { font-size: 1.1rem; width: 22px; text-align: center; }
        .dropdown-divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 8px 0; }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 10px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== PROFILE MODAL CSS ===== */
        .modal-content-custom { 
            border-radius: var(--radius-xl); 
            border: none; 
            overflow: hidden; 
            box-shadow: 0 24px 64px rgba(0,0,0,0.18);
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
        }
        .modal-header-custom { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; 
            padding: 24px 32px; 
            border: none; 
        }
        .modal-body-custom { padding: 32px; }
        .form-control-custom { 
            border-radius: var(--radius-md); 
            padding: 14px 18px; 
            border: 2px solid #e2e8f0; 
            font-size: 0.9rem; 
            font-weight: 600; 
            transition: var(--transition-smooth);
            background: #ffffff;
        }
        .form-control-custom:focus { 
            border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.1); 
            transform: translateY(-1px);
        }
        .form-label-custom { font-weight: 700; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 8px; }
        .profile-nav-tabs { border: none; gap: 12px; }
        .profile-nav-tabs .nav-link { 
            border: none; 
            color: var(--text-muted); 
            font-weight: 700; 
            font-size: 0.9rem; 
            padding: 12px 24px; 
            border-radius: var(--radius-md); 
            transition: var(--transition-smooth); 
        }
        .profile-nav-tabs .nav-link.active { 
            background: linear-gradient(135deg, var(--light-pink), var(--s-pink)); 
            color: var(--p-pink); 
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.15);
        }
        .img-preview-container { position: relative; width: 130px; height: 130px; margin: 0 auto 32px; }
        .img-preview { 
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover; 
            border: 4px solid var(--light-pink); 
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.2);
            transition: var(--transition-smooth);
        }
        .img-preview-container:hover .img-preview { transform: scale(1.02); }
        .btn-upload-trigger { 
            position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; 
            border-radius: 50%; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; border: 4px solid #ffffff; 
            transition: var(--transition-bounce);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.3);
        }
        .btn-upload-trigger:hover { transform: scale(1.15) rotate(10deg); }
        .pwd-requirement { display: block; font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-top: 4px; }
        .pwd-requirement.valid { color: #059669; }

        /* ===== BREADCRUMB BAR ===== */
        .breadcrumb-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            padding: 14px 40px;
            border-bottom: 1px solid var(--glass-border);
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition-smooth);
            padding: 4px 8px;
            border-radius: 8px;
        }
        .breadcrumb-inner a:hover { 
            color: var(--p-pink); 
            background: var(--s-pink);
        }
        .breadcrumb-inner .separator { color: #cbd5e1; font-size: 0.75rem; }
        .breadcrumb-inner .current { 
            color: var(--p-pink); 
            font-weight: 800; 
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            padding: 4px 14px;
            border-radius: 20px;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ===== PROGRESS BAR ===== */
        .progress-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 28px 36px;
            margin-bottom: 32px;
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
            box-shadow: var(--shadow-soft);
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            position: relative;
        }
        .progress-step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            border: 3px solid #e2e8f0;
            background: #ffffff;
            color: #94a3b8;
            transition: var(--transition-bounce);
            position: relative;
            z-index: 2;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: var(--shadow-glow);
            animation: pulseGlow 2s infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(216, 63, 103, 0.3); }
            50% { box-shadow: 0 0 40px rgba(216, 63, 103, 0.5); }
        }
        .progress-step.completed .progress-step-circle {
            background: linear-gradient(135deg, #059669, #10b981);
            border-color: #059669;
            color: #ffffff;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        .progress-step-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            transition: var(--transition-smooth);
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 56px;
            height: 4px;
            background: #e2e8f0;
            margin: 0 8px;
            margin-bottom: 26px;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .progress-line.completed { 
            background: linear-gradient(90deg, #059669, #10b981); 
        }
        .progress-line::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* ===== TEMA SECTION ===== */
        .tema-section {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            margin-bottom: 40px;
        }
        .tema-main {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 32px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
        }
        .tema-main:hover {
            border-color: var(--light-pink);
        }
        .tema-section-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tema-section-title i { color: var(--p-pink); font-size: 1.4rem; }
        .tema-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .tema-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 2px solid transparent;
            transition: var(--transition-bounce);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .tema-card:hover {
            border-color: var(--p-pink);
            transform: translateY(-8px) scale(1.03);
            box-shadow: var(--shadow-hover);
        }
        .tema-img-wrapper {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        .tema-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .tema-card:hover .tema-img { transform: scale(1.1); }
        .tema-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .tema-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .tema-body { padding: 20px; }
        .tema-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .tema-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .tema-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .tema-kategori {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tema-kategori i { color: var(--p-pink); }
        .tema-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.8rem;
            transition: var(--transition-smooth);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.25);
        }
        .tema-card:hover .tema-btn {
            transform: translateX(3px);
            box-shadow: 0 6px 16px rgba(216, 63, 103, 0.3);
        }

        /* ===== SIDEBAR RINGKASAN BOOKING ===== */
        .booking-summary {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 28px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            transition: var(--transition-smooth);
            margin-bottom: 20px;
        }
        .summary-card:hover {
            border-color: var(--light-pink);
        }
        .summary-title {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-title i { color: var(--p-pink); }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
            transition: var(--transition-smooth);
        }
        .summary-item:hover { background: rgba(255,255,255,0.5); }
        .summary-item:last-child { border-bottom: none; }
        .summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: var(--transition-bounce);
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.1);
        }
        .summary-icon.completed { 
            background: linear-gradient(135deg, #d1fae5, #a7f3d0); 
            color: #059669; 
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.15);
        }
        .summary-icon:hover { opacity: 0.9; }
        .summary-text {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .summary-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.4;
        }
        .summary-harga {
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }
        .summary-harga span {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }
        .empty-state i {
            font-size: 5rem;
            color: #e2e8f0;
            margin-bottom: 24px;
            display: inline-block;
            animation: emptyFloat 3s ease-in-out infinite;
        }
        @keyframes emptyFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        .empty-state h3 {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.95rem;
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            gap: 20px;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border: 4px solid var(--light-pink);
            border-top-color: var(--p-pink);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--p-pink);
            letter-spacing: 0.5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }


        /* ========== MOBILE SIDEBAR RESPONSIVE ========== */
        @media (max-width: 991.98px) {
            .mobile-menu-toggle { display: flex; }
            .top-navbar { padding: 12px 16px; }
            .nav-logo { font-size: 1.4rem; }
            .nav-logo span { font-size: 0.75rem; }
            .nav-menu-center { display: none; }
            .nav-btn-booking { padding: 8px 16px; font-size: 0.8rem; }
            .nav-avatar { width: 36px; height: 36px; }
            .nav-dropdown { right: -10px; min-width: 200px; border-radius: 12px; padding: 8px; }
            .dropdown-header { font-size: 0.9rem; padding: 6px 12px; }
            .dropdown-item { padding: 10px 12px; font-size: 0.85rem; }
            .breadcrumb-bar { padding: 12px 16px; }
            .main-container { padding: 20px; }
            .progress-container { padding: 20px 16px; gap: 4px; }
            .progress-line { width: 24px; margin: 0 2px; margin-bottom: 22px; }
            .progress-step-circle { width: 40px; height: 40px; font-size: 0.8rem; }
            .tema-section { grid-template-columns: 1fr; gap: 24px; }
            .booking-summary { position: static; margin-top: 0; }
            .tema-grid { grid-template-columns: repeat(2, 1fr); }
            .tema-main { padding: 24px; }
            .summary-card { padding: 20px; }
        }
        @media (max-width: 767.98px) {
            .top-navbar { padding: 10px 12px; }
            .nav-logo { font-size: 1.2rem; }
            .nav-logo span { display: none; }
            .nav-right { gap: 10px; }
            .nav-btn-booking { padding: 8px 12px; font-size: 0.75rem; }
            .nav-btn-booking i { display: none; }
            .breadcrumb-bar { padding: 10px 12px; }
            .breadcrumb-inner { font-size: 0.8rem; }
            .main-container { padding: 16px 12px; }
            .progress-step-label { display: none; }
            .progress-line { width: 12px; margin-bottom: 18px; }
            .progress-step-circle { width: 36px; height: 36px; font-size: 0.75rem; }
            .progress-container { padding: 16px 8px; }
            .tema-grid { grid-template-columns: 1fr; }
            .tema-main { padding: 20px; }
            .tema-section-title { font-size: 1.1rem; }
            .tema-img-wrapper { height: 200px; }
            .tema-nama { font-size: 1.05rem; }
            .summary-card { padding: 18px; }
            .summary-title { font-size: 1rem; }
            .summary-harga { font-size: 1.2rem; }
            .modal-content-custom { max-width: 95%; margin: 10px auto; }
            .modal-body-custom { padding: 20px; }
            .img-preview-container { width: 100px; height: 100px; }
            .form-control-custom { padding: 10px 14px; font-size: 0.85rem; }
        }
        @media (max-width: 575.98px) {
            .top-navbar { padding: 8px 10px; }
            .nav-logo { font-size: 1.1rem; }
            .nav-btn-booking { padding: 6px 10px; font-size: 0.7rem; border-radius: 8px; }
            .nav-avatar { width: 32px; height: 32px; }
            .breadcrumb-bar { padding: 8px 10px; }
            .main-container { padding: 12px 10px; }
            .progress-container { border-radius: var(--radius-md); padding: 12px 4px; }
            .progress-step-circle { width: 32px; height: 32px; font-size: 0.7rem; border-width: 2px; }
            .progress-line { width: 8px; height: 3px; margin-bottom: 14px; }
            .tema-main { padding: 16px; border-radius: var(--radius-md); }
            .tema-section-title { font-size: 1rem; margin-bottom: 16px; }
            .tema-section-title i { font-size: 1.2rem; }
            .tema-grid { gap: 12px; }
            .tema-card { border-radius: var(--radius-md); }
            .tema-img-wrapper { height: 160px; }
            .tema-body { padding: 14px; }
            .tema-nama { font-size: 1rem; }
            .tema-desc { font-size: 0.8rem; }
            .tema-footer { padding-top: 12px; }
            .tema-btn { padding: 6px 12px; font-size: 0.75rem; }
            .summary-card { padding: 14px; border-radius: var(--radius-md); }
            .summary-item { padding: 8px 0; }
            .summary-icon { width: 34px; height: 34px; font-size: 1rem; }
            .summary-text { font-size: 0.85rem; }
            .summary-sub { font-size: 0.75rem; }
            .summary-harga { font-size: 1.1rem; margin-top: 14px; padding-top: 14px; }
            .empty-state { padding: 40px 16px; }
            .empty-state i { font-size: 3.5rem; }
            .empty-state h3 { font-size: 1.1rem; }
            .modal-content-custom { border-radius: var(--radius-md); }
            .modal-header-custom { padding: 16px 20px; }
            .modal-body-custom { padding: 16px; }
            .img-preview-container { width: 90px; height: 90px; }
            .btn-upload-trigger { width: 32px; height: 32px; }
            .form-control-custom { padding: 8px 12px; font-size: 0.8rem; }
            .profile-nav-tabs .nav-link { padding: 8px 14px; font-size: 0.8rem; }
        }
        @media (max-width: 359.98px) {
            .nav-logo { font-size: 1rem; }
            .nav-btn-booking { padding: 5px 8px; font-size: 0.65rem; }
            .mobile-sidebar { width: 260px; }
            .progress-step-circle { width: 28px; height: 28px; font-size: 0.65rem; }
            .progress-line { width: 6px; }
            .tema-img-wrapper { height: 140px; }
            .tema-nama { font-size: 0.9rem; }
        }

        /* ========== TOUCH DEVICE: DISABLE HOVER ========== */
        @media (hover: none) and (pointer: coarse) {
            .tema-main:hover { box-shadow: var(--shadow-soft); border-color: var(--glass-border); }
            .summary-card:hover { box-shadow: var(--shadow-card); border-color: var(--glass-border); transform: none; }
            .summary-item:hover { background: transparent; }
            .summary-icon:hover { transform: none; opacity: 1; }
            .tema-card:hover { transform: none; box-shadow: 0 2px 12px rgba(0,0,0,0.04); border-color: transparent; }
            .tema-card:hover .tema-img { transform: none; }
            .tema-card:hover .tema-btn { transform: none; }
            .nav-avatar:hover { transform: none; border-color: var(--light-pink); box-shadow: 0 2px 8px rgba(216, 63, 103, 0.15); }
            .nav-btn-booking:hover { transform: none; box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3); }
            .nav-logo:hover { transform: none; }
            .nav-link-item:hover::after { width: 0; }
            .nav-link-item.active::after { width: 100%; }
            .img-preview-container:hover .img-preview { transform: none; }
            .btn-upload-trigger:hover { transform: none; }
        }

        /* ========== REDUCED MOTION ========== */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
            html { scroll-behavior: auto; }
            .mobile-sidebar { transition: none; }
            .progress-line::after { animation: none; }
        }


        /* ===== MOBILE SIDEBAR ===== */
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: #ffffff;
            z-index: 1050;
            transform: translateX(-100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.08);
            border-right: 1px solid rgba(255, 228, 233, 0.8);
        }
        .mobile-sidebar.open { transform: translateX(0); }
        .mobile-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 1045;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-sidebar-overlay.show { display: block; opacity: 1; }
        .mobile-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .mobile-sidebar-brand {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1px;
        }
        .mobile-sidebar-brand span {
            color: var(--text-dark);
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }
        .mobile-sidebar-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--s-pink);
            color: var(--p-pink);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .mobile-sidebar-close:hover {
            background: var(--p-pink);
            color: #fff;
        }
        .mobile-sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            scrollbar-width: none;
        }
        .mobile-sidebar-menu::-webkit-scrollbar { display: none; }
        .mobile-sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #475569;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            margin-bottom: 6px;
            border: none;
            background: none;
            width: 100%;
            cursor: pointer;
            text-align: left;
        }
        .mobile-sidebar-link:hover,
        .mobile-sidebar-link.active {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .mobile-sidebar-link i {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
        }
        .mobile-sidebar-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 12px 0;
        }
        .mobile-sidebar-footer {
            padding: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .mobile-sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--s-pink);
            border-radius: 14px;
        }
        .mobile-sidebar-user img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
        }
        .mobile-sidebar-user-info {
            flex: 1;
            min-width: 0;
        }
        .mobile-sidebar-user-name {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mobile-sidebar-user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .mobile-menu-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            background: var(--s-pink);
            color: var(--p-pink);
            font-size: 1.3rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-smooth);
            margin-right: 10px;
            flex-shrink: 0;
        }
        .mobile-menu-toggle:hover {
            background: var(--p-pink);
            color: #fff;
        }
    </style>
</head>
<body>

    <!-- MOBILE SIDEBAR OVERLAY -->
    <div class="mobile-sidebar-overlay" id="mobileSidebarOverlay" onclick="toggleMobileSidebar()"></div>

    <!-- MOBILE SIDEBAR -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <a href="../../index.php" class="mobile-sidebar-brand">SpotLight.<span>StudioFoto</span></a>
            <button class="mobile-sidebar-close" onclick="toggleMobileSidebar()" aria-label="Tutup menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mobile-sidebar-menu">
            <a href="../../index.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="../Paket/pilih_paket.php" class="mobile-sidebar-link active" onclick="toggleMobileSidebar()">
                <i class="bi bi-calendar-plus-fill"></i> Booking Baru
            </a>
            <a href="../../Riwayat/riwayat.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-clock-history"></i> Riwayat
            </a>
            <a href="../../Hasil Foto/hasil_foto.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-images"></i> Hasil Foto
            </a>
            <div class="mobile-sidebar-divider"></div>
            <a href="../../../../index.php" class="mobile-sidebar-link" onclick="return confirmLandingPage(event)">
                <i class="bi bi-house-door"></i> Beranda
            </a>
            <button class="mobile-sidebar-link text-danger" onclick="confirmLogout(); toggleMobileSidebar();">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </button>
        </div>
        <div class="mobile-sidebar-footer">
            <div class="mobile-sidebar-user">
                <img src="<?= $foto_customer_src ?>" alt="Profil">
                <div class="mobile-sidebar-user-info">
                    <div class="mobile-sidebar-user-name"><?= htmlspecialchars($nama_customer) ?></div>
                    <div class="mobile-sidebar-user-role">Pelanggan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memproses...</div>
    </div>

    <!-- NAVBAR ATAS SINKRON -->
    <nav class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="mobile-menu-toggle d-lg-none" onclick="toggleMobileSidebar()" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
            <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        </div>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>
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
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalProfil">
                        <i class="bi bi-person-circle"></i> Profil Saya
                    </button>
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

    <!-- BREADCRUMB SINKRON -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pilih Tema</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON (Langkah 1-2 Completed, 3 Active) -->
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
            <div class="progress-step active">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Pilih Barang Cetak</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Pilih Jadwal</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">6</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">7</div>
                <div class="progress-step-label">Pembayaran</div>
            </div>
        </div>

        <!-- TEMA SECTION + SIDEBAR -->
        <div class="tema-section">
            <!-- Left: Grid Tema -->
            <div class="tema-main">
                <div class="tema-section-title">
                    <i class="bi bi-palette-fill"></i>
                    Pilih Tema untuk <?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>
                </div>
                <div class="tema-grid">
                    <?php foreach ($tema_list as $tema): 
                        $foto_tema = ($tema['Foto_Tema'] != 'default_tema.jpg' && file_exists("../../../../assets/img/tema/" . $tema['Foto_Tema'])) 
                            ? "../../../../assets/img/tema/" . $tema['Foto_Tema'] 
                            : null;
                    ?>
                        <div class="tema-card" onclick="konfirmasiTema(<?= $tema['ID_Tema'] ?>, '<?= htmlspecialchars(addslashes($tema['Nama_Tema'])) ?>')">
                            <div class="tema-img-wrapper">
                                <?php if ($foto_tema): ?>
                                    <img src="<?= $foto_tema ?>" class="tema-img" alt="<?= htmlspecialchars($tema['Nama_Tema']) ?>">
                                <?php else: ?>
                                    <div class="tema-img-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="tema-badge"><?= htmlspecialchars($tema['Kategori_Tema'] ?? 'Tema') ?></div>
                            </div>
                            <div class="tema-body">
                                <div class="tema-nama"><?= htmlspecialchars($tema['Nama_Tema']) ?></div>
                                <div class="tema-desc"><?= htmlspecialchars($tema['Deskripsi'] ?? 'Tema foto ' . $tema['Nama_Tema'] . ' untuk sesi foto terbaik Anda.') ?></div>
                                <div class="tema-footer">
                                    <div class="tema-kategori">
                                        <i class="bi bi-tag-fill"></i>
                                        <?= htmlspecialchars($tema['Kategori_Tema'] ?? 'Umum') ?>
                                    </div>
                                    <span class="tema-btn">Pilih <i class="bi bi-arrow-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($tema_list)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h3>Tidak Ada Tema Tersedia</h3>
                            <p>Maaf, belum ada tema yang tersedia untuk ruangan ini.<br>Silakan hubungi admin untuk informasi lebih lanjut.</p>
                            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="tema-btn text-decoration-none d-inline-block mt-3" style="padding: 12px 30px;">
                                <i class="bi bi-arrow-left me-2"></i>Kembali ke Ruangan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Sidebar Ringkasan SINKRON -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title"><i class="bi bi-receipt"></i> Ringkasan Booking</div>
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
                        <div class="summary-icon"><i class="bi bi-printer"></i></div>
                        <div>
                            <div class="summary-text">Barang Cetak</div>
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
                    <div class="summary-harga">
                        Rp <?= $harga_format ?> <span>/ sesi</span>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- =====================================================
    MODAL DETAIL PROFIL & KATA SANDI
    ===================================================== -->
    <div class="modal fade" id="modalProfil" data-bs-backdrop="static" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold" id="modalProfilLabel">
                        <i class="bi bi-person-fill-gear me-2"></i> Pengaturan Profil Pelanggan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="bg-light px-4 pt-3">
                    <ul class="nav profile-nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="detail-tab" data-bs-toggle="tab" data-bs-target="#tab-detail" type="button" role="tab" aria-controls="tab-detail" aria-selected="true">
                                <i class="bi bi-person-badge-fill me-1"></i> Detail Profil
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#tab-password" type="button" role="tab" aria-controls="tab-password" aria-selected="false">
                                <i class="bi bi-shield-lock-fill me-1"></i> Ubah Kata Sandi
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body modal-body-custom">
                    <div class="tab-content" id="profileTabsContent">
                        
                        <!-- TAB 1: DETAIL PROFIL -->
                        <div class="tab-pane fade show active" id="tab-detail" role="tabpanel" aria-labelledby="detail-tab">
                            <form action="../../index.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action_type" value="update_profile">
                                
                                <div class="img-preview-container">
                                    <img src="<?= $foto_customer_src ?>" id="profilePreview" class="img-preview" alt="Foto Profil">
                                    <label for="foto_profil" class="btn-upload-trigger" title="Ubah Foto">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                    <input type="file" id="foto_profil" name="foto_profil" accept="image/png, image/jpeg, image/jpg" style="display: none;" onchange="previewImage(event)">
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Username</label>
                                        <input type="text" class="form-control form-control-custom bg-light" value="<?= htmlspecialchars($username_customer) ?>" disabled>
                                        <small class="text-muted">Username tidak dapat diubah.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Nama Lengkap</label>
                                        <input type="text" name="nama_pelanggan" class="form-control form-control-custom" value="<?= htmlspecialchars($nama_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Email</label>
                                        <input type="email" name="email_pelanggan" class="form-control form-control-custom" value="<?= htmlspecialchars($email_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Nomor HP (Contoh: +628211...)</label>
                                        <input type="text" name="no_hp" id="inputHPModal" class="form-control form-control-custom" value="<?= htmlspecialchars($no_hp_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Jenis Kelamin</label>
                                        <select name="jenis_kelamin" class="form-select form-control-custom">
                                            <option value="Laki-laki" <?= ($jk_customer == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                            <option value="Perempuan" <?= ($jk_customer == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Tanggal Lahir</label>
                                        <input type="date" name="tanggal_lahir" class="form-control form-control-custom" value="<?= $tgl_lahir_str ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label form-label-custom">Alamat Lengkap</label>
                                        <textarea name="alamat" rows="2" class="form-control form-control-custom" required><?= htmlspecialchars($alamat_customer) ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer-custom mt-4 px-0 pb-0">
                                    <button type="button" class="btn btn-secondary px-4" style="border-radius:12px; font-weight:700;" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn text-white px-4" style="background: var(--p-pink); border-radius:12px; font-weight:700;">Simpan Profil</button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: UBAH KATA SANDI -->
                        <div class="tab-pane fade" id="tab-password" role="tabpanel" aria-labelledby="password-tab">
                            <form action="../../index.php" method="POST" id="formPassword">
                                <input type="hidden" name="action_type" value="update_password">
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label form-label-custom">Kata Sandi Saat Ini</label>
                                        <input type="password" name="pass_lama" class="form-control form-control-custom" placeholder="Masukkan kata sandi lama Anda" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Kata Sandi Baru</label>
                                        <input type="password" name="pass_baru" id="pass_baru" class="form-control form-control-custom" placeholder="Masukkan kata sandi baru" oninput="checkPasswordStrength()" required>
                                        
                                        <div class="mt-2">
                                            <span class="pwd-requirement" id="req-len">
                                                <i class="bi bi-x-circle-fill text-danger" id="icon-len"></i> Minimal 8 Karakter
                                            </span>
                                            <span class="pwd-requirement" id="req-char">
                                                <i class="bi bi-x-circle-fill text-danger" id="icon-char"></i> Mengandung Huruf & Angka
                                            </span>
                                            <span class="pwd-requirement" id="req-spec">
                                                <i class="bi bi-x-circle-fill text-danger" id="icon-spec"></i> Mengandung Karakter Spesial (e.g. !@#$)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Konfirmasi Kata Sandi Baru</label>
                                        <input type="password" name="pass_konfirmasi" id="pass_konfirmasi" class="form-control form-control-custom" placeholder="Ulangi kata sandi baru" oninput="checkPasswordMatch()" required>
                                        <div class="mt-2">
                                            <span class="pwd-requirement" id="req-match">
                                                <i class="bi bi-x-circle-fill text-danger" id="icon-match"></i> Kesesuaian Kata Sandi
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer-custom mt-4 px-0 pb-0">
                                    <button type="button" class="btn btn-secondary px-4" style="border-radius:12px; font-weight:700;" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" id="btnSubmitPassword" class="btn text-white px-4" style="background: var(--p-pink); border-radius:12px; font-weight:700;" disabled>Perbarui Sandi</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);"><img src="<?= $foto_customer_src ?>" alt="Foto Profil" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_customer) ?></h5>
                        <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Pelanggan</span>
                    </div>
                    <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                        <div class="row g-3" style="font-size: 0.85rem; font-weight:600;">
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Username</small><span class="fw-bold text-dark">@<?= htmlspecialchars($username_customer) ?></span></div>
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Tanggal Lahir</small><span class="fw-bold text-dark"><?= fmtTgl($tgl_lahir_str) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small><span class="fw-bold text-dark"><?= htmlspecialchars($email_customer) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark"><?= htmlspecialchars($jk_customer) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small><span class="fw-bold text-dark"><?= htmlspecialchars($no_hp_customer) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small><span class="fw-bold text-dark"><?= htmlspecialchars($alamat_customer) ?></span></div>
                        </div>
                    </div>
                    <button class="btn text-white py-3 w-100" style="background: var(--p-pink); font-weight:700; border-radius:14px;" onclick="bukaModalEditDariBiodata()">Edit Profil Anda</button>
                </div>
            </div>
        </div>
    </div>

    
    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>

// FUNGSI TOGGLE MOBILE SIDEBAR
function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('mobileSidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}


        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('profilePreview');
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        function checkPasswordStrength() {
            const password = document.getElementById('pass_baru').value;
            const isLenValid = password.length >= 8;
            updateIndicator('req-len', 'icon-len', isLenValid);

            const hasLetter = /[A-Za-z]/.test(password);
            const hasDigit = /[0-9]/.test(password);
            const isCharValid = hasLetter && hasDigit;
            updateIndicator('req-char', 'icon-char', isCharValid);

            const isSpecValid = /[^A-Za-z0-9]/.test(password);
            updateIndicator('req-spec', 'icon-spec', isSpecValid);

            checkPasswordMatch();
        }

        function checkPasswordMatch() {
            const password = document.getElementById('pass_baru').value;
            const confirmPassword = document.getElementById('pass_konfirmasi').value;

            const isMatch = password === confirmPassword && confirmPassword.length > 0;
            updateIndicator('req-match', 'icon-match', isMatch);

            const isLenValid = password.length >= 8;
            const hasLetter = /[A-Za-z]/.test(password);
            const hasDigit = /[0-9]/.test(password);
            const isSpecValid = /[^A-Za-z0-9]/.test(password);

            const btnSubmit = document.getElementById('btnSubmitPassword');
            if (isLenValid && hasLetter && hasDigit && isSpecValid && isMatch) {
                btnSubmit.removeAttribute('disabled');
            } else {
                btnSubmit.setAttribute('disabled', 'true');
            }
        }

        function updateIndicator(elementId, iconId, isValid) {
            const element = document.getElementById(elementId);
            const icon = document.getElementById(iconId);

            if (isValid) {
                element.classList.add('valid');
                icon.className = 'bi bi-check-circle-fill text-success';
            } else {
                element.classList.remove('valid');
                icon.className = 'bi bi-x-circle-fill text-danger';
            }
        }

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

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('show');
        }

        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('show');
        }

        window.addEventListener('load', function() {
            hideLoading();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoading();
            }
        });

        function konfirmasiTema(idTema, namaTema) {
            Swal.fire({
                title: 'Pilih Tema Ini?',
                text: 'Anda akan memilih tema "' + namaTema + '"',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pilih',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    window.location.href = '../Barang_Cetak/pilih_barang_cetak.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=' + idTema;
                }
            });
        }
    </script>
</body>
</html>