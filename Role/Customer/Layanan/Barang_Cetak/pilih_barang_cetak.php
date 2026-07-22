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

// --- Fungsi Pembantu Format Tanggal Indonesia ---
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
// AMBIL ID PARAMETER DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) ||
    !isset($_GET['id_tema']) || empty($_GET['id_tema'])) {
    header("Location: ../../index.php?error=lengkapi_langkah_sebelumnya");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema = (int)$_GET['id_tema'];

// =====================================================
// KONTROLLER CART (KERANJANG BARANG CETAK)
// =====================================================
if (!isset($_SESSION['booking_cart_cetak'])) {
    $_SESSION['booking_cart_cetak'] = []; // Array bentuk [ID_Barang => Kuantitas]
}

// Proses jika tombol "Lanjut" (POST) ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_cetak') {
    $_SESSION['booking_cart_cetak'] = [];
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id_brg => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $_SESSION['booking_cart_cetak'][(int)$id_brg] = $qty;
            }
        }
    }
    // Lanjut ke Langkah 5: Pilih Jadwal
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema");
    exit();
}

// Proses jika tombol "Lewati" (GET) ditekan
if (isset($_GET['action']) && $_GET['action'] === 'lewati') {
    $_SESSION['booking_cart_cetak'] = []; // Kosongkan keranjang cetak
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema");
    exit();
}

// =====================================================
// AMBIL DATA TRANSAKSI SEBELUMNYA
// =====================================================
// 1. Paket Foto
$q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Kapasitas_Orang FROM Paket_Foto WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", array($id_paket, STATUS_DATA_AKTIF));
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

// 2. Ruangan
$q_ruangan = sqlsrv_query($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", array($id_ruangan));
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);

// 3. Tema Foto
$q_tema = sqlsrv_query($conn, "SELECT ID_Tema, Nama_Tema FROM Tema_Foto WHERE ID_Tema = ? AND Status = 1 AND Is_Deleted = 0", array($id_tema));
$d_tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC);

if (!$d_paket || !$d_ruangan || !$d_tema) {
    header("Location: ../../index.php?error=data_transaksi_tidak_valid");
    exit();
}

// =====================================================
// AMBIL DAFTAR BARANG CETAK YANG AKTIF & READY STOK
// =====================================================
$q_barang = sqlsrv_query($conn, 
    "SELECT ID_Barang, Nama_Barang, Deskripsi, Harga_Barang, Stok_Barang, Foto_Barang 
     FROM Barang_Cetak 
     WHERE Status = 1 AND Is_Deleted = 0 AND Stok_Barang > 0 
     ORDER BY Nama_Barang ASC"
);
if ($q_barang === false) {
    die("Error query Barang Cetak: " . print_r(sqlsrv_errors(), true));
}

$barang_list = [];
while ($row = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)) {
    $barang_list[] = $row;
}

// =====================================================
// AMBIL PROFIL CUSTOMER LENGKAP SINKRON
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil, Username_Pelanggan, Email_Pelanggan, No_Hp, Alamat, Jenis_Kelamin, Tanggal_Lahir FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
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

$harga_paket_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Barang Cetak (Opsional) - SpotLight Studio</title>
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

        /* ===== SCROLLBAR CUSTOM ===== */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--light-pink); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--p-pink); }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
        }

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

        /* ===== PROFILE MODAL CSS SINKRON ===== */
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
        .img-preview-container:hover .img-preview { transform: scale(1.05); }
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

        /* ===== MAIN SECTION LAYOUT ===== */
        .cetak-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .cetak-main {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 32px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
        }
        /* .cetak-main:hover removed - non-clickable element */
        .cetak-section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cetak-section-title i { color: var(--p-pink); animation: iconFloat 3s ease-in-out infinite; }
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .cetak-section-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 24px;
            font-weight: 600;
        }

        /* ===== CATALOG GRID ===== */
        .cetak-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
        }
        .cetak-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--glass-border);
            transition: var(--transition-bounce);
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .cetak-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: var(--light-pink);
        }
        .cetak-img-wrapper {
            position: relative;
            height: 180px;
            overflow: hidden;
            background: var(--s-pink);
        }
        .cetak-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .cetak-card:hover .cetak-img { transform: scale(1.08); }
        .cetak-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
        }
        .cetak-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .cetak-nama {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .cetak-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }
        .cetak-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            margin-top: auto;
        }
        .cetak-harga {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .stok-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
        }

        /* ===== QTY SELECTOR ===== */
        .qty-container {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 2px;
        }
        .btn-qty {
            border: none;
            background: none;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-weight: 800;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-qty:hover { background: var(--light-pink); color: var(--p-pink); }
        .input-qty {
            width: 38px;
            border: none;
            background: none;
            text-align: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .input-qty:focus { outline: none; }

        /* ===== SIDEBAR RINGKASAN ===== */
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
        }
        /* .summary-card:hover removed - non-clickable element */
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
        .summary-item:hover { transform: translateX(2px); }
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
        .summary-icon:hover { transform: scale(1.15) rotate(5deg); }
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

        /* ===== BELANJAAN EXTRA LIST ===== */
        .extra-goods-list {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px dashed #e2e8f0;
        }
        .extra-goods-title {
            font-size: 0.78rem;
            font-weight: 900;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .extra-goods-title i { color: var(--p-pink); }
        .extra-goods-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            background: #f8fafc;
            transition: var(--transition-smooth);
        }
        .extra-goods-item:hover {
            background: var(--s-pink);
        }
        .extra-goods-item span:last-child {
            color: var(--text-dark);
            font-weight: 800;
        }

        .summary-harga {
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }
        .btn-lanjut {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 16px;
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .btn-lanjut:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: var(--shadow-hover);
            color: #ffffff;
        }
        .btn-lewati {
            width: 100%;
            padding: 14px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 800;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn-lewati:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: translateY(-2px);
        }

        /* ===== EMPTY STATE ===== */
        .empty-cetak {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-cetak i {
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
        .empty-cetak h3 {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        .empty-cetak p {
            color: var(--text-muted);
            font-size: 0.95rem;
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .cetak-section { grid-template-columns: 1fr 340px; gap: 32px; }
        }
        @media (max-width: 992px) {
            .cetak-section { grid-template-columns: 1fr; }
            .booking-summary { position: static; margin-top: 24px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 14px 20px; }
            .breadcrumb-bar { padding: 14px 20px; }
            .cetak-grid { grid-template-columns: repeat(2, 1fr); }
            .progress-container { padding: 20px 16px; }
            .progress-line { width: 30px; margin: 0 4px; }
        }
        @media (max-width: 768px) {
            .cetak-grid { grid-template-columns: 1fr; }
            .progress-step-label { display: none; }
            .progress-line { width: 16px; }
        }
        @media (max-width: 480px) {
            .cetak-grid { gap: 16px; }
            .cetak-main { padding: 20px; }
            .summary-card { padding: 20px; }
        }

        /* ===== USER BADGE ===== */
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            border: 1.5px solid var(--light-pink);
            padding: 6px 14px 6px 6px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--p-pink);
            transition: var(--transition-smooth);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.08);
        }
        .user-badge:hover {
            border-color: var(--p-pink);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.15);
            transform: translateY(-1px);
        }
        .user-badge img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
        }
        .user-badge .badge-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ===== GLASSMORPHISM ENHANCEMENTS ===== */
        .glass-card {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(20px) saturate(1.2);
            -webkit-backdrop-filter: blur(20px) saturate(1.2);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        /* ===== SMOOTH SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, var(--light-pink), var(--p-pink)); border-radius: 10px; }

        /* ===== FLOATING ANIMATION ===== */
        @keyframes gentleFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .float-gentle { animation: gentleFloat 4s ease-in-out infinite; }

        /* ===== ENHANCED RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .cetak-section { grid-template-columns: 1fr 340px; gap: 28px; }
        }
        @media (max-width: 992px) {
            .cetak-section { grid-template-columns: 1fr; }
            .booking-summary { position: static; margin-top: 24px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 24px; }
            .top-navbar { padding: 12px 24px; }
            .breadcrumb-bar { padding: 12px 24px; }
            .cetak-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .progress-container { padding: 20px 16px; }
            .progress-line { width: 28px; margin: 0 4px; }
            .user-badge .badge-name { display: none; }
        }
        @media (max-width: 768px) {
            .cetak-grid { grid-template-columns: 1fr; }
            .progress-step-label { display: none; }
            .progress-line { width: 14px; margin-bottom: 0; }
            .progress-step-circle { width: 40px; height: 40px; font-size: 0.8rem; }
            .cetak-main { padding: 24px; }
            .summary-card { padding: 24px; }
            .cetak-section-title { font-size: 1.15rem; }
            .nav-logo { font-size: 1.4rem; }
            .nav-btn-booking { padding: 8px 16px; font-size: 0.8rem; }
        }
        @media (max-width: 480px) {
            .main-container { padding: 16px; }
            .top-navbar { padding: 10px 16px; }
            .breadcrumb-bar { padding: 10px 16px; }
            .cetak-grid { gap: 14px; }
            .cetak-main { padding: 20px; }
            .summary-card { padding: 20px; }
            .cetak-card { border-radius: 16px; }
            .cetak-img-wrapper { height: 160px; }
            .progress-container { padding: 16px 10px; border-radius: 20px; }
            .progress-step-circle { width: 36px; height: 36px; font-size: 0.75rem; border-width: 2px; }
            .progress-line { width: 10px; height: 3px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS SINKRON -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
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
                <div class="user-badge" onclick="toggleDropdown()">
                    <img src="<?= $foto_customer_src ?>" alt="Profil">
                    <span class="badge-name"><?= htmlspecialchars($nama_customer) ?></span>
                    <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i>
                </div>
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
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pilih Barang Cetak</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON -->
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
            <div class="progress-step active">
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

        <!-- MAIN LAYOUT -->
        <form action="" method="POST">
            <input type="hidden" name="action" value="simpan_cetak">
            
            <div class="cetak-section">
                <!-- Left: Katalog Cetak -->
                <div class="cetak-main">
                    <h2 class="cetak-section-title">
                        <i class="bi bi-printer-fill"></i> Tambah Produk Cetak Foto (Opsional)
                    </h2>
                    <p class="cetak-section-subtitle">Tingkatkan kenangan fisik Anda dengan mencetak foto berkualitas premium. Langkah ini sepenuhnya opsional.</p>
                    
                    <div class="cetak-grid">
                        <?php 
                        if (!empty($barang_list)):
                            foreach ($barang_list as $brg): 
                                $id_brg = $brg['ID_Barang'];
                                $stok = $brg['Stok_Barang'];
                                $foto_path = "../../../../uploads/barang/" . $brg['Foto_Barang'];
                                $foto_src = (!empty($brg['Foto_Barang']) && file_exists($foto_path)) ? $foto_path : null;
                                
                                // Ambil kuantitas yang sudah tersimpan sebelumnya di sesi (jika ada)
                                $qty_sebelumnya = $_SESSION['booking_cart_cetak'][$id_brg] ?? 0;
                        ?>
                            <div class="cetak-card">
                                <div class="cetak-img-wrapper">
                                    <?php if ($foto_src): ?>
                                        <img src="<?= $foto_src ?>" class="cetak-img" alt="<?= htmlspecialchars($brg['Nama_Barang']) ?>">
                                    <?php else: ?>
                                        <div class="cetak-img-placeholder"><i class="bi bi-file-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="cetak-body">
                                    <div class="cetak-nama"><?= htmlspecialchars($brg['Nama_Barang']) ?></div>
                                    <div class="cetak-desc"><?= htmlspecialchars($brg['Deskripsi'] ?? 'Cetak foto premium untuk melengkapi sesi pemotretan Anda.') ?></div>
                                    <div class="cetak-footer">
                                        <div class="cetak-harga-wrapper">
                                            <div class="cetak-harga">Rp <?= number_format($brg['Harga_Barang'], 0, ',', '.') ?></div>
                                            <div class="stok-label">Stok: <?= $stok ?> unit</div>
                                        </div>
                                        
                                        <!-- QTY Selector Interaktif -->
                                        <div class="qty-container">
                                            <button type="button" class="btn-qty" onclick="adjustQty(<?= $id_brg ?>, -1, <?= $stok ?>)">-</button>
                                            <input type="text" name="qty[<?= $id_brg ?>]" id="qty_<?= $id_brg ?>" class="input-qty" 
                                                   value="<?= $qty_sebelumnya ?>" readonly 
                                                   data-nama="<?= htmlspecialchars($brg['Nama_Barang']) ?>" 
                                                   data-harga="<?= $brg['Harga_Barang'] ?>">
                                            <button type="button" class="btn-qty" onclick="adjustQty(<?= $id_brg ?>, 1, <?= $stok ?>)">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <div class="empty-cetak" style="grid-column: 1 / -1;">
                                <i class="bi bi-inbox"></i>
                                <h3>Tidak Ada Katalog Barang Cetak</h3>
                                <p>Maaf, belum ada katalog barang cetak aktif saat ini.<br>Silakan hubungi admin untuk informasi lebih lanjut.</p>
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
                            <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                            <div>
                                <div class="summary-text">Tema</div>
                                <div class="summary-sub"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></div>
                            </div>
                        </div>
                        
                        <!-- BARANG CETAK INDIKATOR DINAMIS SINKRON -->
                        <div class="summary-item" id="summaryCetakItem">
                            <div class="summary-icon" id="summaryCetakIcon"><i class="bi bi-printer"></i></div>
                            <div>
                                <div class="summary-text">Barang Cetak</div>
                                <div class="summary-sub" id="summaryCetakSub">Belum dipilih</div>
                            </div>                    
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-icon"><i class="bi bi-calendar"></i></div>
                            <div>
                                <div class="summary-text">Jadwal</div>
                                <div class="summary-sub">Belum dipilih</div>
                            </div>
                        </div>

                        <!-- LIST BELANJAAN BARANG CETAK TAMBAHAN (DIRECALCULATE VIA JS) -->
                        <div class="extra-goods-list d-none" id="extraGoodsContainer">
                            <div class="extra-goods-title"><i class="bi bi-printer-fill"></i> Ekstra Cetak:</div>
                            <div id="extraGoodsItems"></div>
                        </div>

                        <div class="summary-harga">
                            <div class="d-flex justify-content-between align-items-center">
                                <span style="font-size:0.95rem;color:var(--text-muted);font-weight:700;">Total Harga:</span>
                                <span id="totalHargaLabel">Rp <?= $harga_paket_format ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-lanjut">
                            <i class="bi bi-arrow-right-circle-fill"></i> Lanjut Pilih Jadwal
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </main>

    <!-- =====================================================
    MODAL DETAIL PROFIL & KATA SANDI SINKRON
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
                                        <label class="form-label form-label-custom">Nomor HP</label>
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
                                        <input type="password" name="pass_lama" class="form-control form-control-custom" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Kata Sandi Baru</label>
                                        <input type="password" name="pass_baru" id="pass_baru" class="form-control form-control-custom" oninput="checkPasswordStrength()" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Konfirmasi Kata Sandi Baru</label>
                                        <input type="password" name="pass_konfirmasi" id="pass_konfirmasi" class="form-control form-control-custom" oninput="checkPasswordMatch()" required>
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
                </div>
            </div>
        </div>
    </div>

    <!-- GANTI BAGIAN SCRIPT DI BAWAH INI -->

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const HARGA_PAKET = <?= $d_paket['Harga_Paket'] ?>;

        function adjustQty(idBarang, offset, maxStok) {
            const input = document.getElementById('qty_' + idBarang);
            let currentQty = parseInt(input.value) || 0;
            let newQty = currentQty + offset;

            if (newQty < 0) newQty = 0;
            if (newQty > maxStok) newQty = maxStok;

            input.value = newQty;
            recalculateSummary();
        }

        // Fungsi kalkulasi real-time sekaligus update badge & icon status cetak secara interaktif
        function recalculateSummary() {
            const qtyInputs = document.querySelectorAll('.input-qty');
            let subtotalCetak = 0;
            let itemsHtml = '';

            qtyInputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                if (qty > 0) {
                    const nama = input.getAttribute('data-nama');
                    const harga = parseFloat(input.getAttribute('data-harga')) || 0;
                    const subtotalItem = qty * harga;
                    
                    subtotalCetak += subtotalItem;
                    
                    itemsHtml += `
                        <div class="extra-goods-item">
                            <span>${nama} (x${qty})</span>
                            <span>Rp ${subtotalItem.toLocaleString('id-ID')}</span>
                        </div>
                    `;
                }
            });

            const extraContainer = document.getElementById('extraGoodsContainer');
            const extraItems = document.getElementById('extraGoodsItems');
            const totalLabel = document.getElementById('totalHargaLabel');
            
            // Elemen Status Cetak di Ringkasan Booking
            const summaryIcon = document.getElementById('summaryCetakIcon');
            const summarySub = document.getElementById('summaryCetakSub');

            if (subtotalCetak > 0) {
                extraItems.innerHTML = itemsHtml;
                extraContainer.classList.remove('d-none');
                
                // Set indikator hijau (completed) secara interaktif
                summaryIcon.classList.add('completed');
                summaryIcon.innerHTML = '<i class="bi bi-check-lg"></i>';
                summarySub.innerText = 'Ditambahkan';
            } else {
                extraContainer.classList.add('d-none');
                extraItems.innerHTML = '';
                
                // Kembalikan ke status default (belum dipilih)
                summaryIcon.classList.remove('completed');
                summaryIcon.innerHTML = '<i class="bi bi-printer"></i>';
                summarySub.innerText = 'Belum dipilih';
            }

            const totalAkhir = HARGA_PAKET + subtotalCetak;
            totalLabel.innerText = 'Rp ' + totalAkhir.toLocaleString('id-ID');
        }

        document.addEventListener('DOMContentLoaded', recalculateSummary);

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
                document.getElementById('profilePreview').src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
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
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) { window.location.href = '../../../../logout.php'; }
            });
        }
    </script>
</body>
</html>