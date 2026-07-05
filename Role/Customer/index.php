<?php
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS & ASSET DEFAULT
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_DATA_AKTIF', 1);

// Menambahkan deklarasi variabel default_svg_avatar untuk mengatasi bug Undefined Variable
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// PROSES UPDATE PROFIL / KATA SANDI
// =====================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];
    
    // 1. UPDATE DETAIL PROFIL
    if ($action_type == 'update_profile') {
        $nama = trim($_POST['nama_pelanggan']);
        $email = trim($_POST['email_pelanggan']);
        
        // Membersihkan spasi pada nomor HP untuk mematuhi CHK_Pelanggan_NoHp di database
        $no_hp = str_replace(' ', '', trim($_POST['no_hp']));
        $alamat = trim($_POST['alamat']);
        $jk = $_POST['jenis_kelamin'];
        $tgl_lahir = $_POST['tanggal_lahir'];
        
        // Validasi format nomor handphone sesuai CHK_Pelanggan_NoHp di SQL Server
        if (substr($no_hp, 0, 3) !== '+62') {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Nomor HP harus diawali dengan +62.'];
        } else if (strlen($no_hp) < 12 || strlen($no_hp) > 16) {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Panjang nomor HP harus antara 12 sampai 16 karakter.'];
        } else if (!preg_match('/^\+62[0-9]+$/', $no_hp)) {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Nomor HP hanya boleh berisi angka setelah tanda plus (+).'];
        } else {
            // Proses unggah foto profil jika ada
            $foto_nama_baru = null;
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
                $file_tmp = $_FILES['foto_profil']['tmp_name'];
                $file_name = $_FILES['foto_profil']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $foto_nama_baru = "pelanggan_" . $id_customer . "_" . time() . "." . $file_ext;
                    $upload_dir = "../../assets/img/pelanggan/";
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($file_tmp, $upload_dir . $foto_nama_baru)) {
                        // Hapus file foto lama jika bukan default
                        $q_old_photo = sqlsrv_query($conn, "SELECT Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ?", array($id_customer));
                        $d_old_photo = sqlsrv_fetch_array($q_old_photo, SQLSRV_FETCH_ASSOC);
                        if ($d_old_photo && $d_old_photo['Foto_Profil'] != 'default.jpg' && file_exists($upload_dir . $d_old_photo['Foto_Profil'])) {
                            unlink($upload_dir . $d_old_photo['Foto_Profil']);
                        }
                    } else {
                        $foto_nama_baru = null;
                    }
                }
            }
            
            // Penyusunan query update database
            if ($foto_nama_baru) {
                $sql_up = "UPDATE Pelanggan SET Nama_Pelanggan = ?, Email_Pelanggan = ?, No_Hp = ?, Alamat = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Foto_Profil = ?, Modified_By = 'customer', Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
                $params_up = array($nama, $email, $no_hp, $alamat, $jk, $tgl_lahir, $foto_nama_baru, $id_customer);
            } else {
                $sql_up = "UPDATE Pelanggan SET Nama_Pelanggan = ?, Email_Pelanggan = ?, No_Hp = ?, Alamat = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Modified_By = 'customer', Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
                $params_up = array($nama, $email, $no_hp, $alamat, $jk, $tgl_lahir, $id_customer);
            }
            
            $stmt_up = sqlsrv_query($conn, $sql_up, $params_up);
            if ($stmt_up) {
                $_SESSION['profile_msg'] = ['type' => 'success', 'title' => 'Berhasil', 'text' => 'Data profil Anda berhasil diperbarui!'];
            } else {
                $errors = sqlsrv_errors();
                $is_duplicate = false;
                if ($errors != null) {
                    foreach($errors as $error) {
                        if (strpos($error['message'], 'UNIQUE') !== false || strpos($error['message'], 'duplicate') !== false) {
                            $is_duplicate = true;
                            break;
                        }
                    }
                }
                if ($is_duplicate) {
                    $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Email Sudah Terdaftar', 'text' => 'Email tersebut sudah digunakan oleh akun lain.'];
                } else {
                    $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Gagal', 'text' => 'Gagal memperbarui profil. Mohon periksa kembali inputan Anda.'];
                }
            }
        }
        header("Location: index.php");
        exit();
    }
    
    // 2. UPDATE KATA SANDI
    if ($action_type == 'update_password') {
        $pass_lama = $_POST['pass_lama'];
        $pass_baru = $_POST['pass_baru'];
        $pass_konfirmasi = $_POST['pass_konfirmasi'];
        
        $q_pass = sqlsrv_query($conn, "SELECT Password_Pelanggan FROM Pelanggan WHERE ID_Pelanggan = ?", array($id_customer));
        $d_pass = sqlsrv_fetch_array($q_pass, SQLSRV_FETCH_ASSOC);
        
        if ($d_pass) {
            if ($pass_lama !== $d_pass['Password_Pelanggan']) {
                $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Verifikasi Gagal', 'text' => 'Kata sandi lama yang Anda masukkan salah.'];
            } else if ($pass_baru !== $pass_konfirmasi) {
                $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Gagal', 'text' => 'Konfirmasi kata sandi baru tidak cocok.'];
            } else {
                // Validasi kekuatan kata sandi agar lolos constraint CHK_Pelanggan_Password di SQL Server
                $len = strlen($pass_baru);
                $has_letter = preg_match("/[A-Za-z]/", $pass_baru);
                $has_digit = preg_match("/[0-9]/", $pass_baru);
                $has_special = preg_match("/[^A-Za-z0-9]/", $pass_baru);
                
                if ($len < 8 || !$has_letter || !$has_digit || !$has_special) {
                    $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Sandi Lemah', 'text' => 'Kata sandi baru minimal 8 karakter (kombinasi huruf, angka, simbol)!'];
                } else {
                    $sql_up_pass = "UPDATE Pelanggan SET Password_Pelanggan = ?, Modified_By = 'customer', Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
                    $stmt_up_pass = sqlsrv_query($conn, $sql_up_pass, array($pass_baru, $id_customer));
                    if ($stmt_up_pass) {
                        $_SESSION['profile_msg'] = ['type' => 'success', 'title' => 'Berhasil', 'text' => 'Kata sandi Anda berhasil diperbarui!'];
                    } else {
                        $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Gagal', 'text' => 'Gagal memperbarui kata sandi pada database.'];
                    }
                }
            }
        }
        header("Location: index.php");
        exit();
    }
}

// --- Ambil Detail Profil Pelanggan ---
$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Username_Pelanggan, Email_Pelanggan, No_Hp, Alamat, Jenis_Kelamin, Tanggal_Lahir, Foto_Profil 
     FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
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
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// SINKRONISASI DATABSE: HANYA TAMPILKAN PAKET YANG LENGKAP
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Durasi_Waktu, p.Kapasitas_Orang, p.Deskripsi, p.Foto_Paket,
            COUNT(DISTINCT r.ID_Ruangan) as jumlah_ruangan
     FROM Paket_Foto p
     INNER JOIN Paket_Ruangan pr ON p.ID_Paket = pr.ID_Paket
     INNER JOIN Ruangan r ON pr.ID_Ruangan = r.ID_Ruangan
     WHERE p.Is_Deleted = 0 
       AND p.Status = ? 
       AND r.Is_Deleted = 0 
       AND r.Status = 1
       -- Filter Keamanan: Memastikan ruangan yang terhubung memiliki minimal 1 tema foto aktif agar user tidak stuck
       AND r.ID_Ruangan IN (
           SELECT DISTINCT rt.ID_Ruangan 
           FROM Ruangan_Tema rt
           INNER JOIN Tema_Foto t ON rt.ID_Tema = t.ID_Tema
           WHERE t.Is_Deleted = 0 AND t.Status = 1
       )
     GROUP BY p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Durasi_Waktu, p.Kapasitas_Orang, p.Deskripsi, p.Foto_Paket
     ORDER BY p.Harga_Paket ASC",
    array(STATUS_DATA_AKTIF)
);

// --- Jadwal Hari Ini (SAFE DateTime) ---
$today = date('Y-m-d');
$q_jadwal = sqlsrv_query($conn, 
    "SELECT TOP 4 j.ID_Jadwal, r.Nama_Ruangan, j.Jam_Mulai, j.Jam_Selesai, j.Keterangan
     FROM Jadwal_Studio j
     INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
     WHERE j.Tanggal_Jadwal = ? AND j.Status_Jadwal = ? AND j.Status = ? AND j.Is_Deleted = 0
     ORDER BY j.Jam_Mulai ASC",
    array($today, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
);

// --- Barang Cetak Populer ---
$q_barang = sqlsrv_query($conn, 
    "SELECT TOP 4 ID_Barang, Nama_Barang, Harga_Barang, Foto_Barang 
     FROM Barang_Cetak WHERE Is_Deleted = 0 AND Status = ? AND Stok_Barang > 0
     ORDER BY Stok_Barang DESC",
    array(STATUS_DATA_AKTIF)
);

// --- Stats ---
$q_stats = sqlsrv_query($conn, 
    "SELECT 
        (SELECT COUNT(*) FROM [Order] WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order != ?) as total_booking,
        (SELECT COUNT(*) FROM [Order] WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order = ?) as menunggu_dp
    FROM Pelanggan WHERE ID_Pelanggan = ?",
    array($id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_DIBATALKAN, $id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_MENUNGGU_DP, $id_customer)
);
$d_stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

function fmtTgl($d) {
    if (empty($d)) return '-';
    return (is_object($d) && method_exists($d, 'format')) ? $d->format('d M Y') : date('d M Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpotLight Studio - Booking Studio Foto Online</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
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

        html {
            scroll-behavior: smooth;
        }

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
            letter-spacing: -1px;
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
            box-shadow: 0 4px 15px rgba(216, 63, 102, 0.25);
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
            border: 2px solid #light-pink;
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

        /* ===== HERO BANNER ===== */
        .hero-banner {
            background: linear-gradient(135deg, var(--p-pink) 0%, var(--d-pink) 50%, #b82e52 100%);
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .hero-content {
            position: relative;
            z-index: 2;
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 12px;
            text-shadow: 0 4px 20px rgba(0,0,0,0.15);
            letter-spacing: -1px;
        }
        .hero-subtitle {
            font-size: 1rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 28px;
            font-weight: 500;
        }
        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
            color: var(--p-pink);
            padding: 14px 36px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hero-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            color: var(--d-pink);
        }

        /* ===== ALUR BOOKING STEPS (6 LANGKAH REVISI BARU!) ===== */
        .booking-steps {
            background: #ffffff;
            padding: 30px 40px;
            border-bottom: 1px solid #eef2f6;
        }
        .steps-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            gap: 0;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 15px;
            position: relative;
        }
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -15px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 2px;
            background: linear-gradient(90deg, var(--light-pink), #e2e8f0);
        }
        .step-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(216, 63, 103, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(216, 63, 103, 0); }
            100% { box-shadow: 0 0 0 0 rgba(216, 63, 103, 0); }
        }
        .step-number-active {
            animation: pulse 2s infinite;
        }

        .step-number.inactive {
            background: #e2e8f0;
            color: #94a3b8;
        }
        .step-text {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-dark);
        }
        .step-text.inactive {
            color: #94a3b8;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .section-title span {
            color: var(--p-pink);
        }
        .section-count {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .section-count strong {
            color: var(--text-dark);
        }

        /* ===== PAKET GRID ===== */
        .paket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 50px;
        }
        .paket-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }
        .paket-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .paket-img-wrapper {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .paket-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .paket-card:hover .paket-img {
            transform: scale(1.1);
        }
        .paket-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
        }
        .paket-badge-durasi {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--p-pink);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .paket-badge-kapasitas {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(30,30,36,0.8);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #ffffff;
        }
        .paket-body {
            padding: 20px;
        }
        .paket-nama {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .paket-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .paket-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .paket-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 8px;
        }
        .paket-meta-item i { color: var(--p-pink); }
        .paket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .paket-harga-wrapper {
            display: flex;
            flex-direction: column;
        }
        .paket-harga {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .paket-harga-satuan {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .paket-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .paket-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }

        /* ===== INFO SECTION ===== */
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 40px;
        }
        .info-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #f1f5f9;
        }
        .info-card-title {
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-card-title i { color: var(--p-pink); }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
        }
        .info-text {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .info-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .info-btn {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 6px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .info-btn:hover {
            background: var(--p-pink);
            color: #fff;
        }

        /* ===== QUICK STATS BAR ===== */
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .stats-bar::-webkit-scrollbar { height: 4px; }
        .stats-bar::-webkit-scrollbar-thumb { background: var(--p-pink); border-radius: 4px; }
        .stat-chip {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            padding: 12px 20px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: max-content;
            transition: all 0.3s;
        }
        .stat-chip:hover {
            border-color: var(--light-pink);
            transform: translateY(-2px);
        }
        .stat-chip-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
        }
        .stat-chip-text {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-dark);
        }
        .stat-chip-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ===== MODAL STYLE (PINK ACCENT) ===== */
        .modal-content-custom {
            border-radius: 24px;
            border: none;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .modal-header-custom {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border-bottom: none;
            padding: 24px;
        }
        .modal-body-custom {
            padding: 24px;
            background-color: #ffffff;
        }
        .modal-footer-custom {
            border-top: 1px solid #f1f5f9;
            padding: 18px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .profile-nav-tabs .nav-link {
            border: none;
            color: var(--text-muted);
            font-weight: 700;
            padding: 12px 20px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .profile-nav-tabs .nav-link.active {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .form-control-custom {
            border-radius: 12px;
            border: 1.5px solid #cbd5e1;
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .form-control-custom:focus {
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px var(--light-pink);
        }
        .form-label-custom {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .img-preview-container {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 20px auto;
        }
        .img-preview {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light-pink);
        }
        .btn-upload-trigger {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--p-pink);
            color: #ffffff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        .btn-upload-trigger:hover {
            background: var(--d-pink);
            transform: scale(1.1);
        }
        .pwd-requirement {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }
        .pwd-requirement.valid {
            color: #059669;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .hero-title { font-size: 2rem; }
            .info-section { grid-template-columns: 1fr; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .booking-steps { display: none; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS (Penyempurnaan: Navigasi Menu Barang Cetak Dihapus Sesuai Instruksi) -->
    <nav class="top-navbar">
        <a href="index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="index.php" class="nav-link-item active">Dashboard</a>
            <a href="#section-paket" class="nav-link-item">Booking Baru</a>
            <a href="Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="#section-paket" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    
                    <!-- MENU: Akses Detil Profil -->
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalProfil">
                        <i class="bi bi-person-circle"></i> Profil Saya
                    </button>
                    
                    <a href="../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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

    <!-- HERO BANNER -->
    <section class="hero-banner">
        <div class="hero-content">
            <h1 class="hero-title">BOOKING STUDIO FOTO TERBAIK</h1>
            <p class="hero-subtitle">Pesan sesi foto profesional dengan mudah, cepat, dan terjangkau</p>
            <a href="#section-paket" class="hero-btn">
                <i class="bi bi-calendar-plus-fill"></i>
                Booking Sekarang
            </a>
        </div>
    </section>

    <!-- ALUR BOOKING STEPS (6 LANGKAH REVISI BARU!) -->
    <section class="booking-steps">
        <div class="steps-container">
            <div class="step-item">
                <div class="step-number step-number-active">1</div>
                <div class="step-text" style="color: var(--p-pink);">Pilih Paket</div>
            </div>
            <div class="step-item">
                <div class="step-number inactive">2</div>
                <div class="step-text inactive">Pilih Ruangan</div>
            </div>
            <div class="step-item">
                <div class="step-number inactive">3</div>
                <div class="step-text inactive">Pilih Tema</div>
            </div>
            <div class="step-item">
                <div class="step-number inactive">4</div>
                <div class="step-text inactive">Pilih Barang Cetak</div>
            </div>
            <div class="step-item">
                <div class="step-number inactive">5</div>
                <div class="step-text inactive">Pilih Jadwal</div>
            </div>
            <div class="step-item">
                <div class="step-number inactive">6</div>
                <div class="step-text inactive">Konfirmasi</div>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- QUICK STATS -->
        <div class="stats-bar">
            <div class="stat-chip">
                <div class="stat-chip-icon"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="stat-chip-text"><?= $d_stats['total_booking'] ?? 0 ?> Booking</div>
                    <div class="stat-chip-sub">Total pemesanan</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #fffbeb; color: #d97706;"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-chip-text"><?= $d_stats['menunggu_dp'] ?? 0 ?> Menunggu</div>
                    <div class="stat-chip-sub">Segera bayar DP</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #ecfdf5; color: #059669;"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-chip-text">Lunas</div>
                    <div class="stat-chip-sub">Booking selesai</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-camera-fill"></i></div>
                <div>
                    <div class="stat-chip-text">Studio Aktif</div>
                    <div class="stat-chip-sub">5 ruangan tersedia</div>
                </div>
            </div>
        </div>

        <!-- PAKET FOTO -->
        <div class="section-header" id="section-paket" style="scroll-margin-top: 100px;">
            <div class="section-title">
                <i class="bi bi-fire text-danger me-2"></i>
                Paket Foto <span>Populer</span>
            </div>
            <div class="section-count">
                Harga <strong>per sesi</strong> • <?= sqlsrv_num_rows($q_paket) ?> paket tersedia
            </div>
        </div>

        <div class="paket-grid">
            <?php
            if ($q_paket && sqlsrv_has_rows($q_paket)):
                while ($row = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)):
                    $foto_paket = ($row['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $row['Foto_Paket'])) 
                        ? "../../assets/img/paket/" . $row['Foto_Paket'] 
                        : null;
                    $harga = number_format($row['Harga_Paket'], 0, ',', '.');
                    $jumlah_ruangan = $row['jumlah_ruangan'] ?? 0;
            ?>
                <a href="Layanan/Paket/pilih_paket.php?id_paket=<?= $row['ID_Paket'] ?>" class="paket-card">
                    <div class="paket-img-wrapper">
                        <?php if ($foto_paket): ?>
                            <img src="<?= $foto_paket ?>" class="paket-img" alt="<?= htmlspecialchars($row['Nama_Paket']) ?>">
                        <?php else: ?>
                            <div class="paket-img-placeholder">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                        <?php endif; ?>
                        <div class="paket-badge-durasi">
                            <i class="bi bi-clock me-1"></i><?= $row['Durasi_Waktu'] ?> menit
                        </div>
                        <div class="paket-badge-kapasitas">
                            <i class="bi bi-people me-1"></i>Max <?= $row['Kapasitas_Orang'] ?> orang
                        </div>
                    </div>
                    <div class="paket-body">
                        <div class="paket-nama"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                        <div class="paket-desc"><?= htmlspecialchars($row['Deskripsi'] ?? 'Paket foto ' . $row['Nama_Paket'] . ' untuk sesi foto terbaik Anda.') ?></div>
                        <div class="paket-meta">
                            <div class="paket-meta-item">
                                <i class="bi bi-door-open"></i> <?= $jumlah_ruangan ?> Ruangan
                            </div>
                            <div class="paket-meta-item">
                                <i class="bi bi-star-fill"></i> Paket Populer
                            </div>
                        </div>
                        <div class="paket-footer">
                            <div class="paket-harga-wrapper">
                                <div class="paket-harga">Rp<?= $harga ?></div>
                                <div class="paket-harga-satuan">/ sesi</div>
                            </div>
                            <span class="paket-btn">Pilih <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            <?php 
                endwhile; 
            else:
            ?>
                <div class="text-center py-5" style="grid-column: 1 / -1;">
                    <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1;"></i>
                    <p class="text-muted">Belum ada paket foto tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- INFO SECTION: Jadwal + Barang -->
        <div class="info-section">
            <!-- Jadwal Hari Ini -->
            <div class="info-card">
                <div class="info-card-title">
                    <i class="bi bi-calendar-day-fill"></i>
                    Jadwal Tersedia Hari Ini
                </div>
                <?php
                if ($q_jadwal && sqlsrv_has_rows($q_jadwal)):
                    while ($row = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)):
                        $jam_mulai = $row['Jam_Mulai'];
                        $jam_selesai = $row['Jam_Selesai'];
                        $jam_mulai_str = (is_object($jam_mulai) && method_exists($jam_mulai, 'format')) ? $jam_mulai->format('H:i') : (is_string($jam_mulai) ? substr($jam_mulai, 0, 5) : '-');
                        $jam_selesai_str = (is_object($jam_selesai) && method_exists($jam_selesai, 'format')) ? $jam_selesai->format('H:i') : (is_string($jam_selesai) ? substr($jam_selesai, 0, 5) : '-');
                ?>
                    <div class="info-item">
                        <div class="info-item-left">
                            <div class="info-icon"><i class="bi bi-clock-fill"></i></div>
                            <div>
                                <div class="info-text"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                <div class="info-sub"><?= $jam_mulai_str ?> - <?= $jam_selesai_str ?></div>
                            </div>
                        </div>
                        <a href="#section-paket" class="info-btn">Booking</a>
                    </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x fs-2 mb-2" style="color: #cbd5e1;"></i>
                        <p class="text-muted small">Tidak ada jadwal tersedia hari ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Barang Cetak -->
            <div class="info-card">
                <div class="info-card-title">
                    <i class="bi bi-bag-heart-fill"></i>
                    Barang Cetak Populer
                </div>
                <?php
                if ($q_barang && sqlsrv_has_rows($q_barang)):
                    while ($row = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)):
                        $harga_barang = number_format($row['Harga_Barang'], 0, ',', '.');
                ?>
                    <div class="info-item">
                        <div class="info-item-left">
                            <div class="info-icon" style="background: #dbeafe; color: #2563eb;"><i class="bi bi-printer-fill"></i></div>
                            <div>
                                <div class="info-text"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                                <div class="info-sub">Rp<?= $harga_barang ?></div>
                            </div>
                        </div>
                        <a href="Barang/Katalog/index.php" class="info-btn">Lihat</a>
                    </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-2 mb-2" style="color: #cbd5e1;"></i>
                        <p class="text-muted small">Belum ada barang cetak tersedia.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- =====================================================
    MODAL DETAIL PROFIL & KATA SANDI (BARU & MODEREN)
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
                
                <!-- Tab Menu Navigasi Moderen -->
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
                            <form action="index.php" method="POST" enctype="multipart/form-data">
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
                            <form action="index.php" method="POST" id="formPassword">
                                <input type="hidden" name="action_type" value="update_password">
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label form-label-custom">Kata Sandi Saat Ini</label>
                                        <input type="password" name="pass_lama" class="form-control form-control-custom" placeholder="Masukkan kata sandi lama Anda" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Kata Sandi Baru</label>
                                        <input type="password" name="pass_baru" id="pass_baru" class="form-control form-control-custom" placeholder="Masukkan kata sandi baru" oninput="checkPasswordStrength()" required>
                                        
                                        <!-- Indikator Validasi Kekuatan Kata Sandi Realtime -->
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_SESSION['profile_msg'])): ?>
                Swal.fire({
                    title: "<?= $_SESSION['profile_msg']['title'] ?>",
                    text: "<?= $_SESSION['profile_msg']['text'] ?>",
                    icon: "<?= $_SESSION['profile_msg']['type'] ?>",
                    confirmButtonColor: "#d83f67"
                });
                <?php unset($_SESSION['profile_msg']); ?>
            <?php endif; ?>
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

        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }
        
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan meninggalkan halaman customer dan kembali ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
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
                    window.location.href = '../../logout.php';
                }
            });
        }

        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata'));
            modalBiodata.show();
        }

        function bukaModalEditDariBiodata() {
            var modalBiodata = bootstrap.Modal.getInstance(document.getElementById('modalLihatBiodata'));
            if(modalBiodata) modalBiodata.hide();
            setTimeout(function() {
                var modalProfil = new bootstrap.Modal(document.getElementById('modalProfil'));
                modalProfil.show();
            }, 400);
        }

        function previewFile() {}

        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            document.getElementById('live-clock').innerText = 
                days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + 
                String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0') + ' WIB';
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                const errorType = urlParams.get('error');
                let message = "Terjadi kesalahan pada proses navigasi.";
                let title = "Navigasi Ditangguhkan";
                let icon = "warning";
                
                if (errorType === 'pilih_paket_dulu') {
                    title = "Pilih Paket Terlebih Dahulu";
                    message = "Sistem mengarahkan Anda kembali ke dasbor. Silakan pilih salah satu Paket Foto Populer di bawah ini untuk memulai langkah pemesanan.";
                    icon = "info";
                }

                Swal.fire({
                    title: title,
                    text: message,
                    icon: icon,
                    confirmButtonColor: "#d83f67",
                    confirmButtonText: "Pilih Paket Sekarang"
                }).then(() => {
                    const targetElement = document.getElementById('section-paket');
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                });

                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>