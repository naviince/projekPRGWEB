<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'];

// Definisi Fallback SVG Avatar
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// Ambil Profil Admin
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_admin = $d_profile['nama_karyawan'] ?? 'Admin';
$username_admin = $d_profile['username_karyawan'] ?? 'admin';
$email_admin = $d_profile['email_karyawan'] ?? 'admin@spotlight.com';
$foto_admin = $d_profile['foto_profil'] ?? 'default.jpg';

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
    ? "../../assets/img/pelanggan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// INISIALISASI VARIABLE
// =====================================================
$error_profile = "";
$success_profile = false;

// =====================================================
// PROSES PEMBARUAN PROFIL ADMIN
// =====================================================
if (isset($_POST['update_profil'])) {
    $nama_input     = trim($_POST['nama']);
    $username_input = trim($_POST['username']);
    $email_input    = trim($_POST['email']);
    $no_hp_input    = trim($_POST['no_hp']);
    $alamat_input   = trim($_POST['alamat']);
    $pass_baru      = $_POST['password'];
    $confirm_pass   = $_POST['confirm_password'];
    
    $hp_bersih_input = str_replace(['+', ' '], '', $no_hp_input);

    // Validasi
    if (empty($nama_input) || !preg_match("/^[a-zA-Z ]*$/", $nama_input)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf!";
    } elseif (empty($username_input) || !preg_match("/^[a-zA-Z0-9_]*$/", $username_input)) {
        $error_profile = "Nama pengguna tidak valid!";
    } elseif (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error_profile = "Email tidak valid!";
    } elseif (empty($no_hp_input) || !ctype_digit($hp_bersih_input) || strlen($hp_bersih_input) < 10) {
        $error_profile = "Nomor telepon tidak valid!";
    } elseif (empty($alamat_input) || strlen($alamat_input) < 10) {
        $error_profile = "Alamat lengkap minimal harus 10 karakter!";
    } else {
        $sandi_final = $d_profile['password_karyawan']; 
        if (!empty($pass_baru)) {
            if (strlen($pass_baru) < 8 || !preg_match("/[A-Za-z]/", $pass_baru) || !preg_match("/[0-9]/", $pass_baru) || !preg_match("/[^A-Za-z0-9]/", $pass_baru)) {
                $error_profile = "Sandi baru minimal 8 karakter (kombinasi huruf, angka, simbol)!";
            } elseif ($pass_baru !== $confirm_pass) {
                $error_profile = "Konfirmasi kata sandi tidak cocok!";
            } else {
                $sandi_final = $pass_baru; 
            }
        }

        if ($error_profile == "") {
            $sql_cek = "SELECT Email_Karyawan, Username_Karyawan, No_Hp FROM Karyawan WHERE (Email_Karyawan = ? OR Username_Karyawan = ? OR No_Hp = ?) AND ID_Karyawan != ?";
            $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_input, $username_input, $no_hp_input, $id_admin));

            if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
                while ($row_cek = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC)) {
                    $row_cek = array_change_key_case($row_cek, CASE_LOWER);
                    if (strtolower($row_cek['email_karyawan']) == strtolower($email_input)) { $error_profile = "Email sudah digunakan!"; } 
                    if (strtolower($row_cek['username_karyawan']) == strtolower($username_input)) { $error_profile = "Username sudah digunakan!"; }
                    if ($row_cek['no_hp'] == $no_hp_input) { $error_profile = "Nomor telepon sudah digunakan!"; }
                }
            }
        }

        if ($error_profile == "") {
            $foto_baru = $foto_admin;
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['foto_profil']['name'];
                $file_size = $_FILES['foto_profil']['size'];
                $file_tmp  = $_FILES['foto_profil']['tmp_name'];
                $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    $error_profile = "Format foto profil harus JPG, JPEG, atau PNG!";
                } elseif ($file_size > 2097152) { 
                    $error_profile = "Ukuran foto profil maksimal 2MB!";
                } else {
                    $foto_baru = "admin_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/pelanggan/";
                    
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    
                    if (move_uploaded_file($file_tmp, $target_dir . $foto_baru)) {
                        if ($foto_admin != 'default.jpg' && file_exists($target_dir . $foto_admin)) { unlink($target_dir . $foto_admin); }
                    } else {
                        $error_profile = "Gagal mengunggah foto profil!";
                    }
                }
            }
            
            if ($error_profile == "") {
                $sql_upd = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $stmt_upd = sqlsrv_query($conn, $sql_upd, array($nama_input, $username_input, $email_input, $sandi_final, $no_hp_input, $alamat_input, $foto_baru, $username_admin, $id_admin));
                
                if ($stmt_upd) {
                    $success_profile = true;
                    $nama_admin = $nama_input;
                    $username_admin = $username_input;
                    $email_admin = $email_input;
                    $foto_admin = $foto_baru;
                    $foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
                        ? "../../assets/img/pelanggan/" . $foto_admin 
                        : $default_svg_avatar;
                    $d_profile['no_hp'] = $no_hp_input;
                    $d_profile['alamat'] = $alamat_input;
                } else {
                    $error_profile = "Gagal memperbarui data di database!";
                }
            }
        }
    }
}

// =====================================================
// QUERY STATISTIK DASHBOARD ADMIN
// =====================================================

// 1. Total Pelanggan Aktif
$q_pelanggan = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Pelanggan WHERE Is_Deleted = 0");
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
$total_pelanggan = $d_pelanggan['total'] ?? 0;

// 2. Total Booking Hari Ini
$q_booking_today = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)");
$d_booking_today = sqlsrv_fetch_array($q_booking_today, SQLSRV_FETCH_ASSOC);
$booking_today = $d_booking_today['total'] ?? 0;

// 3. Menunggu Verifikasi DP
$q_wait_dp = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Pembayaran WHERE Status = 0 AND Tipe_Pembayaran = 'DP'");
$d_wait_dp = sqlsrv_fetch_array($q_wait_dp, SQLSRV_FETCH_ASSOC);
$wait_dp = $d_wait_dp['total'] ?? 0;

// 4. Menunggu Verifikasi Pelunasan
$q_wait_lunas = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Pembayaran WHERE Status = 0 AND Tipe_Pembayaran = 'Pelunasan'");
$d_wait_lunas = sqlsrv_fetch_array($q_wait_lunas, SQLSRV_FETCH_ASSOC);
$wait_lunas = $d_wait_lunas['total'] ?? 0;

// 5. Total Sesi Foto Terjadwal
$q_sesi_terjadwal = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE Status = 0");
$d_sesi_terjadwal = sqlsrv_fetch_array($q_sesi_terjadwal, SQLSRV_FETCH_ASSOC);
$sesi_terjadwal = $d_sesi_terjadwal['total'] ?? 0;

// 6. Stok Barang Menipis
$q_stok_menipis = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Barang_Cetak WHERE Stok_Barang <= Stok_Minimum AND Is_Deleted = 0");
$d_stok_menipis = sqlsrv_fetch_array($q_stok_menipis, SQLSRV_FETCH_ASSOC);
$stok_menipis = $d_stok_menipis['total'] ?? 0;

// 7. Total Paket Foto Aktif
$q_paket = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Paket_Foto WHERE Is_Deleted = 0 AND Status = 1");
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);
$total_paket = $d_paket['total'] ?? 0;

// 8. Total Ruangan Tersedia
$q_ruangan = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Ruangan WHERE Is_Deleted = 0 AND Status = 1");
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);
$total_ruangan = $d_ruangan['total'] ?? 0;

// =====================================================
// QUERY CHART DATA
// =====================================================

// Status Booking untuk Chart
$q_status_booking = sqlsrv_query($conn, "
    SELECT 
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS menunggu_dp,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS dp_verified,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) AS tunggu_pelunasan,
        SUM(CASE WHEN Status = 3 THEN 1 ELSE 0 END) AS lunas,
        SUM(CASE WHEN Status = 4 THEN 1 ELSE 0 END) AS dibatalkan
    FROM [Order]
");
$d_status_booking = sqlsrv_fetch_array($q_status_booking, SQLSRV_FETCH_ASSOC);

// Booking per Bulan (6 bulan terakhir)
$q_booking_bulan = sqlsrv_query($conn, "
    SELECT 
        MONTH(Tanggal_Booking) AS bulan,
        COUNT(*) AS total
    FROM [Order]
    WHERE Tanggal_Booking >= DATEADD(MONTH, -5, GETDATE())
    GROUP BY MONTH(Tanggal_Booking)
    ORDER BY MONTH(Tanggal_Booking)
");
$booking_bulan_data = array_fill(0, 12, 0);
while ($row = sqlsrv_fetch_array($q_booking_bulan, SQLSRV_FETCH_ASSOC)) {
    $booking_bulan_data[$row['bulan'] - 1] = $row['total'];
}

// Pembayaran per Status
$q_pembayaran_status = sqlsrv_query($conn, "
    SELECT 
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS menunggu,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS valid,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) AS tidak_valid
    FROM Pembayaran
");
$d_pembayaran_status = sqlsrv_fetch_array($q_pembayaran_status, SQLSRV_FETCH_ASSOC);

// Aktivitas Terkini - Pembayaran Menunggu Verifikasi
$q_aktivitas = sqlsrv_query($conn, "
    SELECT TOP 5 
        p.ID_Pembayaran,
        pl.Nama_Pelanggan,
        p.Tipe_Pembayaran,
        p.Jumlah_Bayar,
        p.Tanggal_Upload,
        p.Status
    FROM Pembayaran p
    JOIN [Order] o ON p.ID_Order = o.ID_Order
    JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
    WHERE p.Status = 0
    ORDER BY p.Tanggal_Upload DESC
");

// Stok Alert Detail
$q_stok_alert = sqlsrv_query($conn, "
    SELECT TOP 3 Nama_Barang, Stok_Barang, Stok_Minimum 
    FROM Barang_Cetak 
    WHERE Stok_Barang <= Stok_Minimum AND Is_Deleted = 0
    ORDER BY Stok_Barang ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin – SpotLight Studio</title>
    
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root { 
            --p-pink: #D53D66; 
            --d-pink: #CA3366; 
            --s-pink: #FFF0F3; 
            --light-pink: #FFE4E9;
            --accent-pink: #E85D84;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --sidebar-bg: #ffffff;
            --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR STYLING */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1px;
            margin-bottom: 40px;
            display: block;
        }
        .sidebar-brand span {
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .sidebar-menu-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .sidebar-menu-wrapper::-webkit-scrollbar {
            display: none;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            margin-bottom: 8px;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            color: #4a5568;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu {
            list-style: none;
            padding-left: 20px;
            margin-top: 5px;
            display: none;
            transition: var(--transition-3d);
        }
        .submenu.show {
            display: block !important;
        }
        .submenu-link {
            display: flex;
            align-items: center;
            padding: 8px 18px;
            color: #718096;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink);
            background-color: rgba(213, 61, 102, 0.03);
            padding-left: 22px;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        /* PROFILE HEADER */
        .profile-header-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ffffff;
            cursor: pointer;
            transition: var(--transition-3d);
            background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* SCROLL WRAPPER */
        .stats-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 10px;
            margin-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .stats-scroll-wrapper::-webkit-scrollbar {
            height: 6px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 10px;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            min-width: max-content;
        }

        .stat-card-item {
            min-width: 220px;
            max-width: 280px;
            flex: 0 0 auto;
        }

        /* CARD 3D */
        .card-3d {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d);
            padding: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14); 
            border-color: var(--p-pink);
        }
        .card-3d:hover::before {
            opacity: 1;
        }

        /* STAT CARDS */
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: var(--transition-3d);
            flex-shrink: 0;
        }
        .card-3d:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
        .stat-icon-cyan { background: linear-gradient(135deg, #ecfeff, #cffafe); color: #0891b2; }
        .stat-icon-pink { background: linear-gradient(135deg, #fdf2f8, #fce7f3); color: #db2777; }

        .stat-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .stat-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-title {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-subtitle {
            font-size: 0.68rem;
            color: #a0aec0;
            font-weight: 600;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* CHART CARDS */
        .chart-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d);
            padding: 25px;
            height: 100%;
        }
        .chart-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(213, 61, 102, 0.1);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
        }
        .chart-badge {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* ALERT PANEL */
        .alert-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(238, 242, 246, 0.8);
        }
        .alert-item:last-child {
            border-bottom: none;
        }
        .alert-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .alert-icon-warning { background: #fffbeb; color: #d97706; }
        .alert-icon-danger { background: #fef2f2; color: #dc2626; }
        .alert-icon-info { background: #FFF0F3; color: #D53D66; }

        /* TABLE */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table-custom thead th {
            color: var(--text-muted);
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 15px;
            border: none;
            background: transparent;
        }
        .table-custom tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: var(--transition-3d);
            border-radius: 12px;
        }
        .table-custom tbody tr:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.08);
        }
        .table-custom td {
            padding: 14px 15px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            vertical-align: middle;
        }
        .table-custom td:first-child {
            border-radius: 12px 0 0 12px;
        }
        .table-custom td:last-child {
            border-radius: 0 12px 12px 0;
        }

        /* BADGE STATUS */
        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-menunggu { background: #fffbeb; color: #d97706; }
        .badge-dp { background: #FFE4E9; color: #D53D66; }
        .badge-pelunasan { background: #FFD6E0; color: #D53D66; }
        .badge-lunas { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }

        /* MODAL */
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .form-control, .form-select { 
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; 
            background: #f8fafc; font-size: 14px; font-weight: 600; 
            transition: var(--transition-3d); color: var(--text-dark); 
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--p-pink); background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); outline: none;
        }
        .profile-preview-box {
            width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
            border: 2.5px solid #eef2f6; background: #f8fafc; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            transition: var(--transition-3d);
        }
        .profile-preview-box img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .btn-pilih-foto {
            background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink);
            font-weight: 700; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-pilih-foto:hover {
            background: var(--p-pink); color: #ffffff; transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(213, 61, 102, 0.15);
        }
        .btn-reg { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; 
            border-radius: 16px; padding: 16px; font-weight: 800; border: none; 
            width: 100%; transition: var(--transition-3d); margin-top: 15px; 
            font-size: 15px; box-shadow: 0 10px 25px rgba(213, 61, 102, 0.25); 
        }
        .btn-reg:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 15px 35px rgba(213, 61, 102, 0.35); 
        }

        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px;}
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none !important; box-shadow: none !important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        /* ANIMATION */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* SCROLLBAR */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 10px;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .main-content { padding: 20px; }
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 15px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Panel Administrator</span>
            </a>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link-custom active">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                
                <!-- DATA MASTER -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../../Master/Pelanggan/list.php" class="submenu-link"><i class="bi bi-person-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../../Master/Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../../Master/Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../../Master/Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../../Master/Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../../Master/Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                            <li><a href="../../Master/Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        </ul>
                    </div>
                </li>

                <!-- TRANSAKSI -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Order/index.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
                            <li><a href="../../Transaksi/Pembayaran/index.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                            <li><a href="../../Transaksi/Penjualan/index.php" class="submenu-link"><i class="bi bi-shop-fill me-2"></i>Penjualan Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>

                <!-- SESI FOTO -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuSesi">
                        <span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuSesi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Sesi Foto/index.php" class="submenu-link"><i class="bi bi-upload-fill me-2"></i>Upload Hasil Foto</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span>
                    </a>
                </li>
            </ul>
        </div>

        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- BANNER -->
        <div class="alert border-0 d-flex align-items-center gap-3 mb-4 shadow-sm animate-fade-in" style="border-radius: 16px; background: linear-gradient(135deg, rgba(213, 61, 102, 0.05), rgba(255, 228, 233, 0.15)); border: 1px solid rgba(213, 61, 102, 0.15) !important; padding: 15px 25px;">
            <div class="stat-icon" style="width: 40px; height: 40px; background: var(--p-pink); color: #ffffff; font-size: 1.1rem; border-radius: 10px;">
                <i class="bi bi-broadcast"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0" style="font-size: 0.9rem; color: var(--p-pink);">Siaran Sistem SpotLight ✦</h6>
                <small class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Dashboard Admin - Kelola operasional studio dan verifikasi transaksi.</small>
            </div>
        </div>

        <!-- HEADER -->
        <div class="dashboard-header animate-fade-in delay-1">
            <div>
                <h3 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($nama_admin) ?>! 👋</h3>
                <p class="text-muted small mb-0">Kelola data master, verifikasi pembayaran, dan pantau operasional studio.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- ============================================================
           STAT CARDS - 8 CARDS SCROLL HORIZONTAL
           ============================================================ -->
        <div class="stats-scroll-wrapper animate-fade-in delay-2">
            <div class="stats-row">
                <!-- Card 1: Pelanggan Aktif -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-person-fill-check"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Pelanggan Aktif</div>
                                <div class="stat-val"><?= $total_pelanggan ?> Akun</div>
                                <div class="stat-subtitle">Terdaftar di sistem</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Booking Hari Ini -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-calendar-plus-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Booking Hari Ini</div>
                                <div class="stat-val"><?= $booking_today ?> Order</div>
                                <div class="stat-subtitle">Masuk hari ini</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Menunggu Verifikasi DP -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Verifikasi DP</div>
                                <div class="stat-val"><?= $wait_dp ?> Pembayaran</div>
                                <div class="stat-subtitle">Menunggu konfirmasi</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Menunggu Verifikasi Pelunasan -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-cyan"><i class="bi bi-hourglass-bottom"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Verifikasi Pelunasan</div>
                                <div class="stat-val"><?= $wait_lunas ?> Pembayaran</div>
                                <div class="stat-subtitle">Menunggu konfirmasi</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Sesi Terjadwal -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-purple"><i class="bi bi-camera-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Sesi Terjadwal</div>
                                <div class="stat-val"><?= $sesi_terjadwal ?> Sesi</div>
                                <div class="stat-subtitle">Belum diproses</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Stok Menipis -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-red"><i class="bi bi-box-seam-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Stok Menipis</div>
                                <div class="stat-val"><?= $stok_menipis ?> Barang</div>
                                <div class="stat-subtitle">Di bawah minimum</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 7: Paket Aktif -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-camera-reels-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Paket Aktif</div>
                                <div class="stat-val"><?= $total_paket ?> Paket</div>
                                <div class="stat-subtitle">Tersedia untuk booking</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 8: Ruangan Tersedia -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-door-open-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Ruangan Tersedia</div>
                                <div class="stat-val"><?= $total_ruangan ?> Ruangan</div>
                                <div class="stat-subtitle">Siap digunakan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 2: ALERT & CHART BOOKING
           ============================================================ -->
        <div class="row g-4 mb-4">
            <!-- Alert Panel -->
            <div class="col-lg-4 animate-fade-in delay-1">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Peringatan Sistem</h5>
                    </div>
                    
                    <?php 
                    $has_alert = false;
                    if ($stok_menipis > 0 && $q_stok_alert && sqlsrv_has_rows($q_stok_alert)): 
                        $has_alert = true;
                        while ($row = sqlsrv_fetch_array($q_stok_alert, SQLSRV_FETCH_ASSOC)): 
                    ?>
                        <div class="alert-item">
                            <div class="alert-icon alert-icon-danger"><i class="bi bi-box-seam"></i></div>
                            <div>
                                <div class="fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">Stok: <?= $row['Stok_Barang'] ?> (Min: <?= $row['Stok_Minimum'] ?>)</div>
                            </div>
                        </div>
                    <?php endwhile; endif; ?>
                    
                    <?php if ($wait_dp > 0): $has_alert = true; ?>
                        <div class="alert-item">
                            <div class="alert-icon alert-icon-warning"><i class="bi bi-credit-card"></i></div>
                            <div>
                                <div class="fw-bold" style="font-size: 0.85rem;"><?= $wait_dp ?> Pembayaran DP</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Menunggu verifikasi Admin</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($wait_lunas > 0): $has_alert = true; ?>
                        <div class="alert-item">
                            <div class="alert-icon alert-icon-info"><i class="bi bi-cash-stack"></i></div>
                            <div>
                                <div class="fw-bold" style="font-size: 0.85rem;"><?= $wait_lunas ?> Pelunasan</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Menunggu verifikasi Admin</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$has_alert): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill fs-1 mb-2" style="color: #059669;"></i>
                            <p class="text-muted fw-bold mb-0" style="font-size: 0.85rem;">Tidak ada peringatan</p>
                            <p class="text-muted" style="font-size: 0.75rem;">Semua sistem berjalan normal</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart Booking Bulanan -->
            <div class="col-lg-8 animate-fade-in delay-2">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-graph-up-arrow text-danger me-2"></i>Tren Booking Bulanan</h5>
                        <span class="chart-badge">Tahun <?= date('Y') ?></span>
                    </div>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="chartBooking"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 3: STATUS BOOKING & PEMBAYARAN
           ============================================================ -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6 animate-fade-in delay-1">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-bar-chart-fill text-danger me-2"></i>Distribusi Status Booking</h5>
                    </div>
                    <div style="height: 280px; width: 100%;">
                        <canvas id="chartStatusBooking"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 animate-fade-in delay-2">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-pie-chart-fill text-danger me-2"></i>Status Pembayaran</h5>
                    </div>
                    <div style="height: 280px; width: 100%; display: flex; align-items: center; justify-content: center;">
                        <canvas id="chartPembayaran"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 4: AKTIVITAS VERIFIKASI TERKINI
           ============================================================ -->
        <div class="row g-4 mb-4">
            <div class="col-12 animate-fade-in delay-1">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-activity text-danger me-2"></i>Pembayaran Menunggu Verifikasi</h5>
                        <a href="../../Transaksi/Pembayaran/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Verifikasi Semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Tipe</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal Upload</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($q_aktivitas && sqlsrv_has_rows($q_aktivitas)):
                                    while ($row = sqlsrv_fetch_array($q_aktivitas, SQLSRV_FETCH_ASSOC)):
                                        $tipe_badge = $row['Tipe_Pembayaran'] == 'DP' ? 'badge-dp' : 'badge-pelunasan';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="profile-table-avatar" style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden;">
                                                    <img src="<?= $default_svg_avatar ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <span><?= htmlspecialchars($row['Nama_Pelanggan']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge-status <?= $tipe_badge ?>"><?= $row['Tipe_Pembayaran'] ?></span></td>
                                        <td class="fw-bold">Rp<?= number_format($row['Jumlah_Bayar'], 0, ',', '.') ?></td>
                                        <td><?= $row['Tanggal_Upload']->format('d M Y H:i') ?></td>
                                        <td><span class="badge-status badge-menunggu">Menunggu</span></td>
                                        <td>
                                            <a href="../../Transaksi/Pembayaran/verifikasi.php?id=<?= $row['ID_Pembayaran'] ?>" class="btn btn-sm" style="background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border-radius: 8px; font-weight: 700; font-size: 0.75rem; text-decoration: none; padding: 6px 12px;">
                                                <i class="bi bi-check-lg me-1"></i>Verifikasi
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-check-circle-fill fs-1 mb-2" style="color: #059669;"></i>
                                            <p class="fw-bold mb-0">Tidak ada pembayaran menunggu</p>
                                            <p class="text-muted" style="font-size: 0.75rem;">Semua pembayaran sudah diverifikasi</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 5: QUICK ACCESS MENU
           ============================================================ -->
        <div class="row g-4">
            <div class="col-12 animate-fade-in delay-1">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Akses Cepat</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Master/Pelanggan/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-pink mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-person-fill-add"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Tambah Pelanggan</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Master/Paket Foto/tambah.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-pink mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-camera-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Tambah Paket</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Master/Jadwal Studio/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-purple mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-calendar-plus-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Atur Jadwal</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Master/Barang Cetak/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-green mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-box-seam-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Kelola Stok</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
                <img src="<?= $foto_admin_src ?>" alt="Foto Profil">
              </div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5>
              <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Administrator</span>
            </div>
            <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></span>
                </div>
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nama Pengguna</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;">@<?= htmlspecialchars($username_admin) ?></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Email</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_admin) ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nomor Telepon</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Lengkap</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span>
                </div>
              </div>
            </div>
            <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius: 14px;">Edit Profil Anda ⚙</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL GANTI PROFIL -->
    <div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(213, 61, 102, 0.25); background: rgba(255, 255, 255, 0.95);">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <p class="text-muted small mb-4" style="line-height: 1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat.</p>
            <form method="POST" enctype="multipart/form-data">
              <div class="text-center mb-4">
                <div class="d-inline-block position-relative">
                  <div class="profile-preview-box mx-auto">
                    <img id="profile-preview-modal" src="<?= $foto_admin_src ?>" alt="Foto Profil">
                  </div>
                  <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                  <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom: -10px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 0.75rem; padding: 5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label>
                <input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_admin) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
                <input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_admin) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_admin) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
                <input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: 08xxxxxxxxxx" value="<?= htmlspecialchars($d_profile['no_hp'] ?? '') ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Alamat Lengkap<span class="required-star">*</span></label>
                <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize: none;"><?= htmlspecialchars($d_profile['alamat'] ?? '') ?></textarea>
              </div>

              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label">Sandi Baru (Opsional)</label>
                      <div class="password-group">
                          <input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter">
                          <i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i>
                      </div>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label">Konfirmasi Sandi</label>
                      <div class="password-group">
                          <input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru">
                          <i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i>
                      </div>
                  </div>
              </div>

              <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan ✨</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Script JS -->
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle Submenu
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');
                
                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.icon-chevron').forEach(icon => icon.style.transform = 'rotate(0deg)');
                    
                    if (!isShown) {
                        targetEl.classList.add('show');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
        });

        function bukaModalProfil() {
            var modalProfil = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
            modalProfil.show();
        }

        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata'));
            modalBiodata.show();
        }

        function bukaModalEditDariBiodata() {
            var modalBiodata = bootstrap.Modal.getInstance(document.getElementById('modalLihatBiodata'));
            if (modalBiodata) modalBiodata.hide();
            setTimeout(bukaModalProfil, 400);
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda? ✦',
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../index.php';
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem? ❌',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../logout.php';
            });
        }

        // Foto Preview
        const inputFotoModal = document.getElementById('inputFotoModal');
        if (inputFotoModal) {
            inputFotoModal.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.getElementById('profile-preview-modal').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Validasi Input
        const inputNamaModal = document.getElementById('inputNamaModal');
        if (inputNamaModal) {
            inputNamaModal.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z ]/g, '');
            });
        }

        const inputUsernameModal = document.getElementById('inputUsernameModal');
        if (inputUsernameModal) {
            inputUsernameModal.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            });
        }

        // Toggle Password
        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function () {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('bi-eye'); this.classList.toggle('bi-eye-slash');
                });
            }
        }
        setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
        setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');

        // Masking Telepon
        const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62 ';
        if (inputHPModal) {
            inputHPModal.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
                let digits = this.value.split(prefix)[1]?.replace(/[^0-9]/g, '') || '';
                if (digits.length > 13) digits = digits.slice(0, 13);
                this.value = prefix + digits;
            });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            
            let hours = now.getHours().toString().padStart(2, '0');
            let minutes = now.getMinutes().toString().padStart(2, '0');
            let seconds = now.getSeconds().toString().padStart(2, '0');
            
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>

    <!-- GRAFIK CHART.JS -->
    <script>
        // 1. Chart Booking Bulanan
        const ctxBooking = document.getElementById('chartBooking').getContext('2d');
        const gradientPink = ctxBooking.createLinearGradient(0, 0, 0, 300);
        gradientPink.addColorStop(0, 'rgba(213, 61, 102, 0.45)');
        gradientPink.addColorStop(1, 'rgba(255, 240, 243, 0.05)');

        new Chart(ctxBooking, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [{
                    label: 'Booking',
                    data: <?= json_encode($booking_bulan_data) ?>,
                    borderColor: '#D53D66',
                    borderWidth: 4,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#D53D66',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    fill: true,
                    backgroundColor: gradientPink,
                    tension: 0.4 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(30, 30, 36, 0.9)',
                        titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                        padding: 12,
                        cornerRadius: 10
                    }
                },
                scales: {
                    y: { grid: { color: 'rgba(255, 228, 233, 0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } }
                }
            }
        });

        // 2. Chart Status Booking
        const ctxStatusBooking = document.getElementById('chartStatusBooking').getContext('2d');
        new Chart(ctxStatusBooking, {
            type: 'bar',
            data: {
                labels: ['Menunggu DP', 'DP Terverifikasi', 'Menunggu Pelunasan', 'Lunas', 'Dibatalkan'],
                datasets: [{
                    label: 'Jumlah',
                    data: [
                        <?= $d_status_booking['menunggu_dp'] ?? 0 ?>,
                        <?= $d_status_booking['dp_verified'] ?? 0 ?>,
                        <?= $d_status_booking['tunggu_pelunasan'] ?? 0 ?>,
                        <?= $d_status_booking['lunas'] ?? 0 ?>,
                        <?= $d_status_booking['dibatalkan'] ?? 0 ?>
                    ],
                    backgroundColor: ['#fbbf24', '#E85D84', '#E85D84', '#10b981', '#ef4444'],
                    borderRadius: 10,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255, 228, 233, 0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans' } } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } } }
                }
            }
        });

        // 3. Chart Pembayaran Status
        const ctxPembayaran = document.getElementById('chartPembayaran').getContext('2d');
        new Chart(ctxPembayaran, {
            type: 'doughnut',
            data: {
                labels: ['Menunggu', 'Valid', 'Tidak Valid'],
                datasets: [{
                    data: [
                        <?= $d_pembayaran_status['menunggu'] ?? 0 ?>,
                        <?= $d_pembayaran_status['valid'] ?? 0 ?>,
                        <?= $d_pembayaran_status['tidak_valid'] ?? 0 ?>
                    ],
                    backgroundColor: ['#fbbf24', '#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', weight: '600' }, padding: 20 }
                    }
                },
                cutout: '65%'
            }
        });
    </script>

    <!-- SweetAlert Notifikasi -->
    <?php if(isset($success_profile) && $success_profile === true): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Profil Diperbarui! 🎉',
            text: 'Informasi profil Anda berhasil disinkronkan.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Selesai'
        });
    </script>
    <?php endif; ?>

    <?php if(isset($error_profile) && $error_profile !== ""): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Pembaruan Gagal! ❌',
            text: '<?= $error_profile ?>',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Periksa Kembali'
        }).then(() => {
            var modalGanti = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
            modalGanti.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>