<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES CUSTOMER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// Definisi Fallback SVG Avatar
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// Ambil Profil Customer
$q_profile = sqlsrv_query($conn, "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?", array($id_customer));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_customer = $d_profile['nama_pelanggan'] ?? 'Customer';
$username_customer = $d_profile['username_pelanggan'] ?? 'customer';
$email_customer = $d_profile['email_pelanggan'] ?? 'customer@email.com';
$foto_customer = $d_profile['foto_profil'] ?? 'default.jpg';

$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// INISIALISASI VARIABLE
// =====================================================
$error_profile = "";
$success_profile = false;

// =====================================================
// PROSES PEMBARUAN PROFIL CUSTOMER
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
        $sandi_final = $d_profile['password_pelanggan']; 
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
            $sql_cek = "SELECT Email_Pelanggan, Username_Pelanggan, No_Hp FROM Pelanggan WHERE (Email_Pelanggan = ? OR Username_Pelanggan = ? OR No_Hp = ?) AND ID_Pelanggan != ?";
            $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_input, $username_input, $no_hp_input, $id_customer));

            if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
                while ($row_cek = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC)) {
                    $row_cek = array_change_key_case($row_cek, CASE_LOWER);
                    if (strtolower($row_cek['email_pelanggan']) == strtolower($email_input)) { $error_profile = "Email sudah digunakan!"; } 
                    if (strtolower($row_cek['username_pelanggan']) == strtolower($username_input)) { $error_profile = "Username sudah digunakan!"; }
                    if ($row_cek['no_hp'] == $no_hp_input) { $error_profile = "Nomor telepon sudah digunakan!"; }
                }
            }
        }

        if ($error_profile == "") {
            $foto_baru = $foto_customer;
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
                    $foto_baru = "customer_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/pelanggan/";
                    
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    
                    if (move_uploaded_file($file_tmp, $target_dir . $foto_baru)) {
                        if ($foto_customer != 'default.jpg' && file_exists($target_dir . $foto_customer)) { unlink($target_dir . $foto_customer); }
                    } else {
                        $error_profile = "Gagal mengunggah foto profil!";
                    }
                }
            }
            
            if ($error_profile == "") {
                $sql_upd = "UPDATE Pelanggan SET Nama_Pelanggan = ?, Username_Pelanggan = ?, Email_Pelanggan = ?, Password_Pelanggan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
                $stmt_upd = sqlsrv_query($conn, $sql_upd, array($nama_input, $username_input, $email_input, $sandi_final, $no_hp_input, $alamat_input, $foto_baru, $username_customer, $id_customer));
                
                if ($stmt_upd) {
                    $success_profile = true;
                    $nama_customer = $nama_input;
                    $username_customer = $username_input;
                    $email_customer = $email_input;
                    $foto_customer = $foto_baru;
                    $foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_customer)) 
                        ? "../../assets/img/pelanggan/" . $foto_customer 
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
// QUERY STATISTIK DASHBOARD CUSTOMER
// =====================================================

// 1. Total Booking Saya
$q_my_booking = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ?", array($id_customer));
$d_my_booking = sqlsrv_fetch_array($q_my_booking, SQLSRV_FETCH_ASSOC);
$my_booking = $d_my_booking['total'] ?? 0;

// 2. Booking Menunggu DP
$q_wait_dp = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ? AND Status = 0", array($id_customer));
$d_wait_dp = sqlsrv_fetch_array($q_wait_dp, SQLSRV_FETCH_ASSOC);
$wait_dp = $d_wait_dp['total'] ?? 0;

// 3. Booking Aktif (DP Terverifikasi + Menunggu Pelunasan)
$q_booking_aktif = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ? AND Status IN (1, 2)", array($id_customer));
$d_booking_aktif = sqlsrv_fetch_array($q_booking_aktif, SQLSRV_FETCH_ASSOC);
$booking_aktif = $d_booking_aktif['total'] ?? 0;

// 4. Booking Lunas (bisa download hasil)
$q_booking_lunas = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ? AND Status = 3", array($id_customer));
$d_booking_lunas = sqlsrv_fetch_array($q_booking_lunas, SQLSRV_FETCH_ASSOC);
$booking_lunas = $d_booking_lunas['total'] ?? 0;

// 5. Sesi Foto Selesai
$q_sesi_selesai = sqlsrv_query($conn, "
    SELECT COUNT(*) AS total 
    FROM Sesi_Foto S 
    JOIN [Order] O ON S.ID_Order = O.ID_Order 
    WHERE O.ID_Pelanggan = ? AND S.Status = 1", array($id_customer));
$d_sesi_selesai = sqlsrv_fetch_array($q_sesi_selesai, SQLSRV_FETCH_ASSOC);
$sesi_selesai = $d_sesi_selesai['total'] ?? 0;

// 6. Total Pesanan Barang Cetak
$q_pesanan_cetak = sqlsrv_query($conn, "
    SELECT COUNT(*) AS total 
    FROM Penjualan P 
    JOIN [Order] O ON P.ID_Order = O.ID_Order 
    WHERE O.ID_Pelanggan = ?", array($id_customer));
$d_pesanan_cetak = sqlsrv_fetch_array($q_pesanan_cetak, SQLSRV_FETCH_ASSOC);
$pesanan_cetak = $d_pesanan_cetak['total'] ?? 0;

// 7. Rating yang sudah diberikan
$q_my_rating = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ? AND Rating IS NOT NULL", array($id_customer));
$d_my_rating = sqlsrv_fetch_array($q_my_rating, SQLSRV_FETCH_ASSOC);
$my_rating = $d_my_rating['total'] ?? 0;

// 8. Booking Dibatalkan
$q_booking_batal = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Pelanggan = ? AND Status = 4", array($id_customer));
$d_booking_batal = sqlsrv_fetch_array($q_booking_batal, SQLSRV_FETCH_ASSOC);
$booking_batal = $d_booking_batal['total'] ?? 0;

// =====================================================
// QUERY DATA TAMPILAN
// =====================================================

// Paket Foto Populer (Top 3)
$q_paket_populer = sqlsrv_query($conn, "
    SELECT TOP 3 p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Durasi_Waktu, p.Kapasitas_Orang, p.Foto_Paket,
           COUNT(o.ID_Order) AS total_booking
    FROM Paket_Foto p
    LEFT JOIN [Order] o ON p.ID_Paket = o.ID_Paket
    WHERE p.Is_Deleted = 0 AND p.Status = 1
    GROUP BY p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Durasi_Waktu, p.Kapasitas_Orang, p.Foto_Paket
    ORDER BY total_booking DESC
");

// Booking Saya Terbaru (Top 3)
$q_booking_saya = sqlsrv_query($conn, "
    SELECT TOP 3 
        o.ID_Order,
        pk.Nama_Paket,
        r.Nama_Ruangan,
        o.Tanggal_Booking,
        o.Total_Harga,
        o.Status,
        o.Rating
    FROM [Order] o
    JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
    JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
    WHERE o.ID_Pelanggan = ?
    ORDER BY o.Created_Date DESC
", array($id_customer));

// Jadwal Tersedia Hari Ini
$q_jadwal_hari_ini = sqlsrv_query($conn, "
    SELECT TOP 3 
        j.ID_Jadwal,
        r.Nama_Ruangan,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Keterangan
    FROM Jadwal_Studio j
    JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
    WHERE j.Status = 1 AND j.Tanggal_Jadwal = CAST(GETDATE() AS DATE) AND j.Is_Deleted = 0
    ORDER BY j.Jam_Mulai ASC
");

// Barang Cetak Populer (Top 3)
$q_barang_populer = sqlsrv_query($conn, "
    SELECT TOP 3 b.ID_Barang, b.Nama_Barang, b.Harga_Barang, b.Stok_Barang, b.Foto_Barang
    FROM Barang_Cetak b
    WHERE b.Is_Deleted = 0 AND b.Status = 1 AND b.Stok_Barang > 0
    ORDER BY b.Stok_Barang DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpotLight Studio - Customer Dashboard</title>
    
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 236, 239, 0.8);
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
            background-color: rgba(216, 63, 103, 0.03);
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
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.2);
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
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
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
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
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
            box-shadow: 0 22px 45px rgba(216, 63, 103, 0.14); 
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
        .stat-icon-pink { background: linear-gradient(135deg, #fff5f6, #ffe4e9); color: var(--p-pink); }
        .stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
        .stat-icon-cyan { background: linear-gradient(135deg, #ecfeff, #cffafe); color: #0891b2; }
        .stat-icon-yellow { background: linear-gradient(135deg, #fefce8, #fef9c3); color: #ca8a04; }

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

        /* CONTENT CARDS */
        .content-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d);
            padding: 25px;
            height: 100%;
        }
        .content-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.1);
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .content-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
        }
        .content-badge {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* PAKET CARD */
        .paket-card {
            background: linear-gradient(135deg, #ffffff, #fff5f6);
            border-radius: 16px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            padding: 20px;
            transition: var(--transition-3d);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .paket-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 15px 35px rgba(216, 63, 103, 0.12);
            border-color: var(--p-pink);
        }
        .paket-img {
            width: 100%;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            margin-bottom: 12px;
        }
        .paket-harga {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--p-pink);
        }
        .paket-nama {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .paket-info {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* BOOKING ITEM */
        .booking-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 14px;
            margin-bottom: 10px;
            transition: var(--transition-3d);
            border: 1px solid transparent;
        }
        .booking-item:hover {
            transform: translateX(6px);
            border-color: var(--p-pink);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.08);
        }
        .booking-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* BADGE STATUS */
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-menunggu { background: #fffbeb; color: #d97706; }
        .badge-dp { background: #dbeafe; color: #2563eb; }
        .badge-pelunasan { background: #e0e7ff; color: #4f46e5; }
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
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); outline: none;
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
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }
        .btn-reg { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; 
            border-radius: 16px; padding: 16px; font-weight: 800; border: none; 
            width: 100%; transition: var(--transition-3d); margin-top: 15px; 
            font-size: 15px; box-shadow: 0 10px 25px rgba(216, 63, 103, 0.25); 
        }
        .btn-reg:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 15px 35px rgba(216, 63, 103, 0.35); 
        }
        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 0.8rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #ffffff;
        }

        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px;}
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); }
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
                <span>Studio Foto</span>
            </a>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link-custom active">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                
                <!-- LAYANAN STUDIO -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLayanan">
                        <span><i class="bi bi-camera-fill me-2"></i> Layanan Studio</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuLayanan">
                        <ul class="list-unstyled">
                            <li><a href="../../Layanan/Paket/index.php" class="submenu-link"><i class="bi bi-collection-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../../Layanan/Tema/index.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../../Layanan/Ruangan/index.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan Studio</a></li>
                            <li><a href="../../Layanan/Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Tersedia</a></li>
                            <li><a href="../../Layanan/Portofolio/index.php" class="submenu-link"><i class="bi bi-images me-2"></i>Portofolio</a></li>
                        </ul>
                    </div>
                </li>

                <!-- BOOKING & TRANSAKSI -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuBooking">
                        <span><i class="bi bi-calendar-check-fill me-2"></i> Booking Saya</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuBooking">
                        <ul class="list-unstyled">
                            <li><a href="../../Booking/Baru/index.php" class="submenu-link"><i class="bi bi-plus-circle-fill me-2"></i>Booking Baru</a></li>
                            <li><a href="../../Booking/Riwayat/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Booking</a></li>
                            <li><a href="../../Booking/Pembayaran/index.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Pembayaran</a></li>
                            <li><a href="../../Booking/Hasil/index.php" class="submenu-link"><i class="bi bi-download me-2"></i>Download Hasil</a></li>
                        </ul>
                    </div>
                </li>

                <!-- BARANG CETAK -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuCetak">
                        <span><i class="bi bi-printer-fill me-2"></i> Barang Cetak</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuCetak">
                        <ul class="list-unstyled">
                            <li><a href="../../Cetak/Katalog/index.php" class="submenu-link"><i class="bi bi-shop me-2"></i>Katalog Barang</a></li>
                            <li><a href="../../Cetak/Pesanan/index.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Pesanan Saya</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i> Beranda</span>
                    </a>
                </li>
            </ul>
        </div>

        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- BANNER -->
        <div class="alert border-0 d-flex align-items-center gap-3 mb-4 shadow-sm animate-fade-in" style="border-radius: 16px; background: linear-gradient(135deg, rgba(216, 63, 103, 0.05), rgba(255, 236, 239, 0.15)); border: 1px solid rgba(216, 63, 103, 0.15) !important; padding: 15px 25px;">
            <div class="stat-icon" style="width: 40px; height: 40px; background: var(--p-pink); color: #ffffff; font-size: 1.1rem; border-radius: 10px;">
                <i class="bi bi-camera-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0" style="font-size: 0.9rem; color: var(--p-pink);">Selamat Datang di SpotLight Studio ✦</h6>
                <small class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Pesan studio foto profesional dengan mudah dan cepat.</small>
            </div>
        </div>

        <!-- HEADER -->
        <div class="dashboard-header animate-fade-in delay-1">
            <div>
                <h3 class="fw-bold mb-1">Halo, <?= htmlspecialchars($nama_customer) ?>! 📸</h3>
                <p class="text-muted small mb-0">Lihat paket menarik dan kelola booking foto Anda.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_customer_src ?>" alt="Customer Profil">
                </div>
            </div>
        </div>

        <!-- ============================================================
           STAT CARDS - 8 CARDS SCROLL HORIZONTAL
           ============================================================ -->
        <div class="stats-scroll-wrapper animate-fade-in delay-2">
            <div class="stats-row">
                <!-- Card 1: Total Booking -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-check-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Booking</div>
                                <div class="stat-val"><?= $my_booking ?>x</div>
                                <div class="stat-subtitle">Semua pemesanan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Menunggu DP -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Menunggu DP</div>
                                <div class="stat-val"><?= $wait_dp ?> Booking</div>
                                <div class="stat-subtitle">Segera bayar DP</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Booking Aktif -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-blue"><i class="bi bi-camera-reels-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Booking Aktif</div>
                                <div class="stat-val"><?= $booking_aktif ?> Sesi</div>
                                <div class="stat-subtitle">Siap dipotret</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Lunas (Download) -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Lunas</div>
                                <div class="stat-val"><?= $booking_lunas ?> Order</div>
                                <div class="stat-subtitle">Bisa download hasil</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Sesi Selesai -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-purple"><i class="bi bi-image-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Sesi Selesai</div>
                                <div class="stat-val"><?= $sesi_selesai ?>x</div>
                                <div class="stat-subtitle">Foto sudah diambil</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Pesanan Cetak -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-cyan"><i class="bi bi-printer-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Pesanan Cetak</div>
                                <div class="stat-val"><?= $pesanan_cetak ?> Item</div>
                                <div class="stat-subtitle">Barang cetak dipesan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 7: Rating Diberikan -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-yellow"><i class="bi bi-star-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Rating Saya</div>
                                <div class="stat-val"><?= $my_rating ?>x</div>
                                <div class="stat-subtitle">Review diberikan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 8: Dibatalkan -->
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-red"><i class="bi bi-x-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Dibatalkan</div>
                                <div class="stat-val"><?= $booking_batal ?>x</div>
                                <div class="stat-subtitle">Booking batal</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 2: PAKET POPULER & BOOKING SAYA
           ============================================================ -->
        <div class="row g-4 mb-4">
            <!-- Paket Populer -->
            <div class="col-lg-7 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-fire text-danger me-2"></i>Paket Foto Populer</h5>
                        <a href="../../Layanan/Paket/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Lihat Semua</a>
                    </div>
                    <div class="row g-3">
                        <?php
                        if ($q_paket_populer && sqlsrv_has_rows($q_paket_populer)):
                            while ($row = sqlsrv_fetch_array($q_paket_populer, SQLSRV_FETCH_ASSOC)):
                                $foto_paket = ($row['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $row['Foto_Paket'])) 
                                    ? "../../assets/img/paket/" . $row['Foto_Paket'] 
                                    : $default_svg_avatar;
                        ?>
                            <div class="col-md-4">
                                <a href="../../Booking/Baru/index.php?paket=<?= $row['ID_Paket'] ?>" class="paket-card">
                                    <img src="<?= $foto_paket ?>" alt="<?= htmlspecialchars($row['Nama_Paket']) ?>" class="paket-img">
                                    <div class="paket-nama"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                    <div class="paket-harga">Rp<?= number_format($row['Harga_Paket'], 0, ',', '.') ?></div>
                                    <div class="paket-info">
                                        <i class="bi bi-clock me-1"></i><?= $row['Durasi_Waktu'] ?> menit | 
                                        <i class="bi bi-people me-1"></i><?= $row['Kapasitas_Orang'] ?> orang
                                    </div>
                                </a>
                            </div>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <div class="col-12 text-center py-4">
                                <i class="bi bi-inbox fs-1 mb-2" style="color: #cbd5e1;"></i>
                                <p class="text-muted">Belum ada paket tersedia.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Booking Saya Terbaru -->
            <div class="col-lg-5 animate-fade-in delay-2">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-calendar-event-fill text-danger me-2"></i>Booking Terbaru</h5>
                        <a href="../../Booking/Riwayat/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Riwayat</a>
                    </div>
                    <?php
                    if ($q_booking_saya && sqlsrv_has_rows($q_booking_saya)):
                        while ($row = sqlsrv_fetch_array($q_booking_saya, SQLSRV_FETCH_ASSOC)):
                            $status_class = '';
                            $status_text = '';
                            $icon_bg = '';
                            switch ($row['Status']) {
                                case 0: $status_class = 'badge-menunggu'; $status_text = 'Menunggu DP'; $icon_bg = 'background: #fffbeb; color: #d97706;'; break;
                                case 1: $status_class = 'badge-dp'; $status_text = 'DP Terverifikasi'; $icon_bg = 'background: #dbeafe; color: #2563eb;'; break;
                                case 2: $status_class = 'badge-pelunasan'; $status_text = 'Menunggu Pelunasan'; $icon_bg = 'background: #e0e7ff; color: #4f46e5;'; break;
                                case 3: $status_class = 'badge-lunas'; $status_text = 'Lunas'; $icon_bg = 'background: #ecfdf5; color: #059669;'; break;
                                case 4: $status_class = 'badge-batal'; $status_text = 'Dibatalkan'; $icon_bg = 'background: #fef2f2; color: #dc2626;'; break;
                            }
                    ?>
                        <div class="booking-item">
                            <div class="booking-icon" style="<?= $icon_bg ?>">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($row['Nama_Ruangan']) ?> • <?= $row['Tanggal_Booking']->format('d M Y') ?></div>
                                    </div>
                                    <span class="badge-status <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="fw-bold" style="font-size: 0.85rem; color: var(--p-pink);">Rp<?= number_format($row['Total_Harga'], 0, ',', '.') ?></span>
                                    <?php if ($row['Status'] == 0): ?>
                                        <a href="../../Booking/Pembayaran/index.php?id=<?= $row['ID_Order'] ?>" class="btn-action" style="padding: 5px 12px; font-size: 0.7rem;">
                                            <i class="bi bi-credit-card"></i> Bayar DP
                                        </a>
                                    <?php elseif ($row['Status'] == 2): ?>
                                        <a href="../../Booking/Pembayaran/index.php?id=<?= $row['ID_Order'] ?>" class="btn-action" style="padding: 5px 12px; font-size: 0.7rem;">
                                            <i class="bi bi-cash-stack"></i> Pelunasan
                                        </a>
                                    <?php elseif ($row['Status'] == 3 && empty($row['Rating'])): ?>
                                        <a href="../../Booking/Rating/index.php?id=<?= $row['ID_Order'] ?>" class="btn-action" style="padding: 5px 12px; font-size: 0.7rem;">
                                            <i class="bi bi-star-fill"></i> Rating
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 mb-2" style="color: #cbd5e1;"></i>
                            <p class="text-muted">Belum ada booking. <a href="../../Booking/Baru/index.php" style="color: var(--p-pink); font-weight: 700;">Booking sekarang!</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 3: JADWAL HARI INI & BARANG CETAK
           ============================================================ -->
        <div class="row g-4 mb-4">
            <!-- Jadwal Tersedia Hari Ini -->
            <div class="col-lg-6 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-calendar-day-fill text-danger me-2"></i>Jadwal Tersedia Hari Ini</h5>
                        <a href="../../Layanan/Jadwal/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Lihat Semua</a>
                    </div>
                    <?php
                    if ($q_jadwal_hari_ini && sqlsrv_has_rows($q_jadwal_hari_ini)):
                        while ($row = sqlsrv_fetch_array($q_jadwal_hari_ini, SQLSRV_FETCH_ASSOC)):
                    ?>
                        <div class="booking-item">
                            <div class="booking-icon" style="background: linear-gradient(135deg, #fff5f6, #ffe4e9); color: var(--p-pink);">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                            <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= $row['Keterangan'] ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--p-pink);"><?= $row['Jam_Mulai']->format('H:i') ?> - <?= $row['Jam_Selesai']->format('H:i') ?></div>
                                    <a href="../../Booking/Baru/index.php?jadwal=<?= $row['ID_Jadwal'] ?>" class="btn-action" style="padding: 4px 10px; font-size: 0.7rem; margin-top: 4px;">
                                        <i class="bi bi-plus-lg"></i> Booking
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 mb-2" style="color: #cbd5e1;"></i>
                            <p class="text-muted">Tidak ada jadwal tersedia hari ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barang Cetak Populer -->
            <div class="col-lg-6 animate-fade-in delay-2">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-bag-heart-fill text-danger me-2"></i>Barang Cetak Populer</h5>
                        <a href="../../Cetak/Katalog/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Katalog</a>
                    </div>
                    <div class="row g-3">
                        <?php
                        if ($q_barang_populer && sqlsrv_has_rows($q_barang_populer)):
                            while ($row = sqlsrv_fetch_array($q_barang_populer, SQLSRV_FETCH_ASSOC)):
                        ?>
                            <div class="col-md-4">
                                <a href="../../Cetak/Katalog/index.php?id=<?= $row['ID_Barang'] ?>" class="paket-card text-center">
                                    <div class="stat-icon stat-icon-pink mx-auto mb-2" style="width: 50px; height: 50px;">
                                        <i class="bi bi-printer-fill"></i>
                                    </div>
                                    <div class="paket-nama"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                                    <div class="paket-harga">Rp<?= number_format($row['Harga_Barang'], 0, ',', '.') ?></div>
                                    <div class="paket-info">Stok: <?= $row['Stok_Barang'] ?></div>
                                </a>
                            </div>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <div class="col-12 text-center py-4">
                                <i class="bi bi-inbox fs-1 mb-2" style="color: #cbd5e1;"></i>
                                <p class="text-muted">Belum ada barang cetak tersedia.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
           BARIS 4: QUICK ACTION
           ============================================================ -->
        <div class="row g-4">
            <div class="col-12 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Aksi Cepat</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Booking/Baru/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-pink mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-calendar-plus-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Booking Baru</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Pesan sesi foto</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Booking/Pembayaran/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-green mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-credit-card-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Bayar Booking</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Upload bukti transfer</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Booking/Hasil/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-blue mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-download"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Download Hasil</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Foto sesi selesai</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="../../Cetak/Katalog/index.php" class="text-decoration-none">
                                <div class="card-3d text-center p-3">
                                    <div class="stat-icon stat-icon-purple mx-auto mb-2" style="width: 50px; height: 50px;"><i class="bi bi-bag-plus-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.9rem; color: var(--text-dark);">Pesan Cetak</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Cetak foto & bingkai</div>
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
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Saya</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
                <img src="<?= $foto_customer_src ?>" alt="Foto Profil">
              </div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_customer) ?></h5>
              <span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Customer</span>
            </div>
            <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Username</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;">@<?= htmlspecialchars($username_customer) ?></span>
                </div>
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_customer) ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">No. HP</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span>
                </div>
              </div>
            </div>
            <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius: 14px;">Edit Profil Saya ⚙</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL GANTI PROFIL -->
    <div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(216, 63, 103, 0.25); background: rgba(255, 255, 255, 0.95);">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Edit Profil</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <form method="POST" enctype="multipart/form-data">
              <div class="text-center mb-4">
                <div class="d-inline-block position-relative">
                  <div class="profile-preview-box mx-auto">
                    <img id="profile-preview-modal" src="<?= $foto_customer_src ?>" alt="Foto Profil">
                  </div>
                  <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                  <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom: -10px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 0.75rem; padding: 5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Nama Lengkap<span class="required-star">*</span></label>
                <input type="text" name="nama" id="inputNamaModal" class="form-control" value="<?= htmlspecialchars($nama_customer) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Username<span class="required-star">*</span></label>
                <input type="text" name="username" id="inputUsernameModal" class="form-control" value="<?= htmlspecialchars($username_customer) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Email<span class="required-star">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email_customer) ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">No. HP<span class="required-star">*</span></label>
                <input type="text" name="no_hp" id="inputHPModal" class="form-control" value="<?= htmlspecialchars($d_profile['no_hp'] ?? '') ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Alamat<span class="required-star">*</span></label>
                <textarea name="alamat" class="form-control" rows="2" required style="resize: none;"><?= htmlspecialchars($d_profile['alamat'] ?? '') ?></textarea>
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
                text: 'Anda akan dialihkan ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
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
                title: 'Keluar? ❌',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
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

    <!-- SweetAlert Notifikasi -->
    <?php if(isset($success_profile) && $success_profile === true): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Profil Diperbarui! 🎉',
            text: 'Informasi profil Anda berhasil diperbarui.',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Selesai'
        });
    </script>
    <?php endif; ?>

    <?php if(isset($error_profile) && $error_profile !== ""): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal! ❌',
            text: '<?= $error_profile ?>',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Periksa Kembali'
        }).then(() => {
            var modalGanti = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
            modalGanti.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>