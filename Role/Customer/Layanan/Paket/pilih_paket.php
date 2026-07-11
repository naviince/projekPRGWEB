<?php
session_start();
include '../../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat) agar deteksi jam lampau akurat
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

// =====================================================
// INISIALISASI VARIABEL WAKTU & JAM SEKARANG (SINKRON WIB)
// =====================================================
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$current_time = date('H:i:s');

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

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil ---
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

// =====================================================
// AMBIL ID PAKET DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];

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

// Path foto paket
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : null;

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');

// Ambil semua paket foto terhubung saat ini
$q_ruangan = sqlsrv_query($conn, 
    "SELECT r.ID_Ruangan, r.Nama_Ruangan, r.Deskripsi, r.Foto_Ruangan
     FROM Ruangan r
     INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
     WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0
     ORDER BY r.Nama_Ruangan ASC", 
    array($id_paket)
);
if ($q_ruangan === false) {
    die("Error query Ruangan: " . print_r(sqlsrv_errors(), true));
}

$ruangan_list = [];
while ($row = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)) {
    $ruangan_list[] = $row;
}

// =====================================================
// AMBIL PROPERTI UNTUK SETIAP RUANGAN
// =====================================================
$properti_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_properti = sqlsrv_query($conn, 
        "SELECT Nama_Properti, Kategori_Properti 
         FROM Properti 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0
         ORDER BY Kategori_Properti, Nama_Properti",
        array($id_ruangan)
    );
    if ($q_properti === false) {
        die("Error query Properti: " . print_r(sqlsrv_errors(), true));
    }
    $properti_list = [];
    while ($prop = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
        $properti_list[] = $prop;
    }
    $properti_map[$id_ruangan] = $properti_list;
}

// =====================================================
// AMBIL TEMA FOTO YANG TERHUBUNG DENGAN SETIAP RUANGAN
// =====================================================
$tema_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_tema = sqlsrv_query($conn, 
        "SELECT t.ID_Tema, t.Nama_Tema, t.Kategori_Tema, t.Foto_Tema
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
    while ($tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
        $tema_list[] = $tema;
    }
    $tema_map[$id_ruangan] = $tema_list;
}

// =====================================================
// AMBIL JADWAL TERSEDIA (FILTER JAM EXPIRED, LIBUR, & MAINTENANCE)
// =====================================================
$q_ruangan_count = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ?", 
    array($id_paket)
);
$d_ruangan_count = sqlsrv_fetch_array($q_ruangan_count, SQLSRV_FETCH_ASSOC);
$jumlah_ruangan = $d_ruangan_count['total'] ?? 0;

$jadwal_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_jadwal = sqlsrv_query($conn, 
        "SELECT TOP 3 j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
         FROM Jadwal_Studio j
         WHERE j.ID_Ruangan = ? 
           -- SINKRONISASI MANY-TO-MANY: Saring slot ketersediaan berdasarkan durasi paket foto terpilih
           AND DATEDIFF(MINUTE, j.Jam_Mulai, j.Jam_Selesai) = ?
           AND j.Tanggal_Jadwal IN (?, ?)
           -- VALIDASI JAM EXPIRED: Jika tanggal hari ini, jam mulai wajib lebih besar dari jam sekarang
           AND (j.Tanggal_Jadwal > ? OR (j.Tanggal_Jadwal = ? AND j.Jam_Mulai > ?))
           AND j.Status_Jadwal = ? 
           AND j.Status = ? 
           AND j.Is_Deleted = 0
           -- VALIDASI KETERANGAN: Menyaring slot libur / perawatan secara otomatis
           AND j.Keterangan NOT LIKE '%libur%'
           AND j.Keterangan NOT LIKE '%maintenance%'
         ORDER BY j.Tanggal_Jadwal, j.Jam_Mulai",
        array($id_ruangan, $d_paket['Durasi_Waktu'], $today, $tomorrow, $today, $today, $current_time, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
    );
    if ($q_jadwal === false) {
        die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
    }
    $jadwal_list = [];
    while ($jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
        $jam_mulai = $jadwal['Jam_Mulai'];
        $jam_selesai = $jadwal['Jam_Selesai'];
        $tanggal = $jadwal['Tanggal_Jadwal'];

        if (is_object($jam_mulai) && method_exists($jam_mulai, 'format')) {
            $jam_mulai_str = $jam_mulai->format('H:i');
        } else {
            $jam_mulai_str = is_string($jam_mulai) ? substr($jam_mulai, 0, 5) : '-';
        }
        if (is_object($jam_selesai) && method_exists($jam_selesai, 'format')) {
            $jam_selesai_str = $jam_selesai->format('H:i');
        } else {
            $jam_selesai_str = is_string($jam_selesai) ? substr($jam_selesai, 0, 5) : '-';
        }
        if (is_object($tanggal) && method_exists($tanggal, 'format')) {
            $tgl_str = $tanggal->format('d M');
        } else {
            $tgl_str = $tanggal;
        }

        $jadwal['Jam_Mulai_Str'] = $jam_mulai_str;
        $jadwal['Jam_Selesai_Str'] = $jam_selesai_str;
        $jadwal['Tanggal_Str'] = $tgl_str;
        $jadwal_list[] = $jadwal;
    }
    $jadwal_map[$id_ruangan] = $jadwal_list;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($d_paket['Nama_Paket']) ?> - SpotLight Studio</title>
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
            letter-spacing: -1px;
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

        /* ===== DETAIL SECTION ===== */
        .detail-section {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            margin-bottom: 40px;
        }
        .detail-left {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
        }
        .detail-left:hover {
            box-shadow: var(--shadow-card);
        }

        /* DESIGN PREMIUM GAMBAR HERO PAKET */
        .package-banner-container {
            position: relative;
            height: 340px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            border-bottom: 4px solid var(--light-pink);
        }
        .package-banner-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .package-banner-container:hover .package-banner-img {
            transform: scale(1.05);
        }
        .package-banner-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            color: var(--p-pink);
            font-size: 5rem;
        }
        .package-banner-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.45));
            z-index: 1;
        }
        .package-banner-badge {
            position: absolute;
            top: 20px; left: 20px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 800;
            z-index: 2;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .detail-body { padding: 30px; }
        .detail-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            color: var(--p-pink);
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 12px;
            border: 2px solid var(--light-pink);
        }
        .detail-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 12px;
        }
        .detail-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .detail-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .detail-meta-item i { color: var(--p-pink); font-size: 1.1rem; }
        .detail-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 30px 0 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-section-title i { color: var(--p-pink); }
        .detail-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.8;
        }
        .detail-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .detail-info-list li {
            padding: 14px 0;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 600;
            transition: var(--transition-smooth);
        }
        .detail-info-list li:hover {
            transform: translateX(4px);
        }
        .detail-info-list li:last-child { border-bottom: none; }
        .detail-info-list li i {
            color: var(--p-pink);
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .detail-info-list li span {
            color: var(--text-muted);
            font-weight: 500;
            margin-left: auto;
        }

        /* ===== SIDEBAR HARGA ===== */
        .detail-sidebar {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .price-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 28px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            transition: var(--transition-smooth);
            margin-bottom: 20px;
        }
        .price-card:hover {
            box-shadow: 0 16px 48px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .price-card a { text-decoration: none; }
        .price-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .price-value {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-bottom: 4px;
        }
        .price-unit {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 24px;
        }
        .btn-cek {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3);
        }
        .btn-cek:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 32px rgba(216, 63, 103, 0.35);
            color: #ffffff;
        }
        .benefit-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
        }
        .benefit-card:hover {
            box-shadow: var(--shadow-card);
        }
        .benefit-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .benefit-title i { color: var(--p-pink); }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            padding: 8px;
            border-radius: var(--radius-md);
            transition: var(--transition-smooth);
        }
        .benefit-item:hover {
            background: var(--s-pink);
            transform: translateX(4px);
        }
        .benefit-item:last-child { margin-bottom: 0; }
        .benefit-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: var(--transition-bounce);
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.1);
        }
        .benefit-item:hover .benefit-icon {
            transform: scale(1.15) rotate(5deg);
        }
        .benefit-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.5;
        }

        /* ===== RUANGAN SECTION ===== */
        .ruangan-section {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 32px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
            margin-bottom: 40px;
        }
        .ruangan-section:hover {
            box-shadow: var(--shadow-card);
        }
        .ruangan-section-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ruangan-section-title i { color: var(--p-pink); font-size: 1.4rem; }
        .ruangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .ruangan-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: var(--transition-bounce);
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .ruangan-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: var(--light-pink);
        }
        .ruangan-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .ruangan-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .ruangan-card:hover .ruangan-img { transform: scale(1.1); }
        .ruangan-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
        }
        .ruangan-badge {
            position: absolute;
            top: 12px; left: 12px;
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
        .ruangan-body { padding: 20px; }
        .ruangan-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .ruangan-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== PROPERTI & TEMA TAGS ===== */
        .ruangan-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .tag-properti {
            background: #f0fdf4;
            color: #059669;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-tema {
            background: #eff6ff;
            color: #2563eb;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-jadwal {
            background: #fefce8;
            color: #ca8a04;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-more {
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .ruangan-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .ruangan-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 8px;
            transition: var(--transition-smooth);
        }
        .ruangan-meta-item:hover {
            background: var(--s-pink);
            transform: translateY(-2px);
        }
        .ruangan-meta-item i { color: var(--p-pink); }
        .ruangan-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .ruangan-harga-wrapper { display: flex; flex-direction: column; }
        .ruangan-harga {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .ruangan-harga-satuan {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .ruangan-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.25);
        }
        .ruangan-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.35);
            color: #fff;
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

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .detail-section { grid-template-columns: 1fr 360px; }
        }
        @media (max-width: 992px) {
            .detail-section { grid-template-columns: 1fr; }
            .detail-sidebar { position: static; margin-top: 24px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 14px 20px; }
            .breadcrumb-bar { padding: 14px 20px; }
            .ruangan-grid { grid-template-columns: 1fr; }
            .progress-container { padding: 20px 16px; }
            .progress-line { width: 30px; margin: 0 4px; }
        }
        @media (max-width: 768px) {
            .progress-step-label { display: none; }
            .progress-line { width: 16px; }
            .package-banner-container { height: 240px; }
        }
        @media (max-width: 480px) {
            .detail-body { padding: 20px; }
            .price-card { padding: 20px; }
            .ruangan-section { padding: 20px; }
            .summary-card { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memproses...</div>
    </div>

    <!-- NAVBAR ATAS SINKRON -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-btn-booking">
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
            <a href="../../index.php">Paket Foto</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON (Langkah 1 Active) -->
        <div class="progress-container">
            <div class="progress-step active">
                <div class="progress-step-circle">1</div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
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
                <div class="progress-step-label">Bayar DP</div>
            </div>
        </div>

        <!-- DETAIL SECTION -->
        <div class="detail-section">
            <!-- Left: Info -->
            <div class="detail-left">
                <!-- DESIGNED HERO BANNER PAKET FOTO -->
                <div class="package-banner-container">
                    <?php if ($foto_paket): ?>
                        <img src="<?= $foto_paket ?>" class="package-banner-img" alt="<?= htmlspecialchars($d_paket['Nama_Paket']) ?>">
                    <?php else: ?>
                        <div class="package-banner-placeholder">
                            <i class="bi bi-camera-fill"></i>
                        </div>
                    <?php endif; ?>
                    <div class="package-banner-overlay"></div>
                    <div class="package-banner-badge"><i class="bi bi-stars"></i> Paket Populer</div>
                </div>

                <div class="detail-body">
                    <div class="detail-badge"><i class="bi bi-stars"></i> Detail Paket Pilihan</div>
                    <h1 class="detail-title"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></h1>
                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>SpotLight Studio Utama, Cikarang ASTRA</span>
                        </div>
                        <div class="detail-meta-item">
                            <i class="bi bi-shield-check"></i>
                            <span>Jaminan Sesi Terjadwal</span>
                        </div>
                    </div>

                    <h2 class="detail-section-title"><i class="bi bi-file-text-fill"></i> Deskripsi Paket</h2>
                    <p class="detail-desc">
                        <?= htmlspecialchars($d_paket['Deskripsi'] ?? 'Paket foto ' . $d_paket['Nama_Paket'] . ' dengan kualitas terbaik untuk kenangan Anda. Dilengkapi dengan fotografer profesional, peralatan studio lengkap, dan hasil foto berkualitas tinggi.') ?>
                    </p>

                    <h2 class="detail-section-title"><i class="bi bi-info-circle-fill"></i> Ketentuan Paket</h2>
                    <ul class="detail-info-list">
                        <li><i class="bi bi-stopwatch"></i> Durasi Sesi <span><?= $d_paket['Durasi_Waktu'] ?> menit</span></li>
                        <li><i class="bi bi-people-fill"></i> Kapasitas <span><?= $d_paket['Kapasitas_Orang'] ?> orang</span></li>
                        <li><i class="bi bi-door-open-fill"></i> Ruangan Tersedia <span><?= $jumlah_ruangan ?> studio</span></li>
                        <li><i class="bi bi-image-fill"></i> File Hasil <span>Softcopy + Edit</span></li>
                        <li><i class="bi bi-cash-stack"></i> Pembayaran <span>DP 65% + Pelunasan</span></li>
                    </ul>
                </div>
            </div>

            <!-- Right: Sidebar (STICKY) -->
            <div class="detail-sidebar">
                <div class="price-card">
                    <div class="price-label">Mulai dari</div>
                    <div class="price-value">Rp <?= $harga_format ?></div>
                    <div class="price-unit">Per Sesi</div>
                    <a href="#ruangan-section" class="btn-cek">
                        <i class="bi bi-door-open-fill"></i>
                        Pilih Ruangan
                    </a>
                </div>
                <div class="benefit-card">
                    <div class="benefit-title"><i class="bi bi-gift-fill"></i> Booking di SpotLight lebih untung!</div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-credit-card"></i></div>
                        <div class="benefit-text">Pembayaran DP hanya 65%</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-gift"></i></div>
                        <div class="benefit-text">Promo & voucher menarik</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="benefit-text">Jadwal terjamin & tidak bentrok</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RUANGAN SECTION -->
        <div class="ruangan-section" id="ruangan-section">
            <div class="ruangan-section-title">
                <i class="bi bi-door-open-fill"></i>
                Pilih Ruangan untuk Paket <?= htmlspecialchars($d_paket['Nama_Paket']) ?>
            </div>
            <div class="ruangan-grid">
                <?php foreach ($ruangan_list as $ruangan): 
                    $id_ruangan = $ruangan['ID_Ruangan'];
                    $foto_ruangan = ($ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'])) 
                        ? "../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'] 
                        : null;

                    $properti_list = $properti_map[$id_ruangan] ?? [];
                    $tema_list = $tema_map[$id_ruangan] ?? [];
                    $jadwal_list = $jadwal_map[$id_ruangan] ?? [];
                ?>
                    <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="ruangan-card">
                        <div class="ruangan-img-wrapper">
                            <?php if ($foto_ruangan): ?>
                                <img src="<?= $foto_ruangan ?>" class="ruangan-img" alt="<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>">
                            <?php else: ?>
                                <div class="ruangan-img-placeholder"><i class="bi bi-door-open-fill"></i></div>
                            <?php endif; ?>
                            <div class="ruangan-badge"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                        </div>
                        <div class="ruangan-body">
                            <div class="ruangan-nama"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                            <div class="ruangan-desc"><?= htmlspecialchars($ruangan['Deskripsi'] ?? 'Studio dengan fasilitas lengkap untuk sesi foto terbaik.') ?></div>

                            <!-- Properti Tags -->
                            <?php if (!empty($properti_list)): ?>
                            <div class="ruangan-tags">
                                <?php 
                                $count = 0;
                                foreach ($properti_list as $prop): 
                                    if ($count++ >= 3) break;
                                ?>
                                        <span class="tag-properti"><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($prop['Nama_Properti']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($properti_list) > 3): ?>
                                    <span class="tag-more">+<?= count($properti_list) - 3 ?> properti</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Tema Tags -->
                            <?php if (!empty($tema_list)): ?>
                            <div class="ruangan-tags">
                                <?php 
                                $count = 0;
                                foreach ($tema_list as $tema): 
                                    if ($count++ >= 2) break;
                                ?>
                                        <span class="tag-tema"><i class="bi bi-palette me-1"></i><?= htmlspecialchars($tema['Nama_Tema']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($tema_list) > 2): ?>
                                    <span class="tag-more">+<?= count($tema_list) - 2 ?> tema</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Jadwal Tags -->
                            <?php if (!empty($jadwal_list)): ?>
                            <div class="ruangan-tags">
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <span class="tag-jadwal"><i class="bi bi-clock-fill me-1"></i><?= $jadwal['Tanggal_Str'] ?> (<?= $jadwal['Jam_Mulai_Str'] ?> WIB)</span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="ruangan-meta">
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-people"></i> Max <?= $d_paket['Kapasitas_Orang'] ?> orang
                                </div>
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-box-seam"></i> <?= count($properti_list) ?> properti
                                </div>
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-palette"></i> <?= count($tema_list) ?> tema
                                </div>
                            </div>
                            <div class="ruangan-footer">
                                <div class="ruangan-harga-wrapper">
                                </div>
                                <span class="ruangan-btn">Pilih <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($ruangan_list)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>Tidak Ada Ruangan Tersedia</h3>
                        <p>Maaf, belum ada ruangan yang tersedia untuk paket ini.<br>Silakan hubungi admin untuk informasi lebih lanjut.</p>
                    </div>
                <?php endif; ?>
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
    </script>
</body>
</html>