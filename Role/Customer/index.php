<?php
session_start();
include '../../koneksi.php';

define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_DATA_AKTIF', 1);

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    if ($action_type == 'update_profile') {
        $nama = trim($_POST['nama_pelanggan']);
        $email = trim($_POST['email_pelanggan']);
        $no_hp = str_replace(' ', '', trim($_POST['no_hp']));
        $alamat = trim($_POST['alamat']);
        $jk = $_POST['jenis_kelamin'];
        $tgl_lahir = $_POST['tanggal_lahir'];

        if (substr($no_hp, 0, 3) !== '+62') {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Nomor HP harus diawali dengan +62.'];
        } else if (strlen($no_hp) < 12 || strlen($no_hp) > 16) {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Panjang nomor HP harus antara 12 sampai 16 karakter.'];
        } else if (!preg_match('/^\+62[0-9]+$/', $no_hp)) {
            $_SESSION['profile_msg'] = ['type' => 'error', 'title' => 'Format Salah', 'text' => 'Nomor HP hanya boleh berisi angka setelah tanda plus (+).'];
        } else {
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
// QUERY PAKET FOTO - DIPERBAIKI SESUAI DATABASE
// =====================================================
// Validasi logika nyata:
// 1. Paket aktif (Status=1, Is_Deleted=0)
// 2. Paket punya ruangan via Paket_Ruangan (junction table)
// 3. Ruangan aktif (Status=1, Is_Deleted=0)
// 4. Ruangan punya tema via Ruangan_Tema
// 5. Tema aktif (Status=1, Is_Deleted=0)
// 6. Ruangan punya jadwal tersedia di masa depan

$sql_paket = "SELECT 
    p.ID_Paket, 
    p.Nama_Paket, 
    p.Harga_Paket, 
    p.Durasi_Waktu, 
    p.Kapasitas_Orang, 
    p.Deskripsi, 
    p.Foto_Paket,
    COUNT(DISTINCT pr.ID_Ruangan) as jumlah_ruangan,
    COUNT(DISTINCT j.ID_Jadwal) as jumlah_jadwal_tersedia
FROM Paket_Foto p
INNER JOIN Paket_Ruangan pr ON p.ID_Paket = pr.ID_Paket
INNER JOIN Ruangan r ON pr.ID_Ruangan = r.ID_Ruangan AND r.Status = 1 AND r.Is_Deleted = 0
INNER JOIN Ruangan_Tema rt ON r.ID_Ruangan = rt.ID_Ruangan
INNER JOIN Tema_Foto t ON rt.ID_Tema = t.ID_Tema AND t.Status = 1 AND t.Is_Deleted = 0
LEFT JOIN Jadwal_Studio j ON r.ID_Ruangan = j.ID_Ruangan 
    AND j.Status_Jadwal = 0 
    AND j.Status = 1 
    AND j.Is_Deleted = 0
    AND j.Tanggal_Jadwal >= CAST(GETDATE() AS DATE)
WHERE p.Is_Deleted = 0 
  AND p.Status = ?
GROUP BY p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Durasi_Waktu, p.Kapasitas_Orang, p.Deskripsi, p.Foto_Paket
HAVING COUNT(DISTINCT pr.ID_Ruangan) > 0
   AND COUNT(DISTINCT j.ID_Jadwal) > 0
ORDER BY p.Harga_Paket ASC";

$q_paket = sqlsrv_query($conn, $sql_paket, array(STATUS_DATA_AKTIF));

$jumlah_paket = 0;
if ($q_paket) {
    $q_count = sqlsrv_query($conn, $sql_paket, array(STATUS_DATA_AKTIF));
    while (sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC)) {
        $jumlah_paket++;
    }
}

$today = date('Y-m-d');
$q_jadwal = sqlsrv_query($conn, 
    "SELECT TOP 4 j.ID_Jadwal, r.Nama_Ruangan, j.Jam_Mulai, j.Jam_Selesai, j.Keterangan
     FROM Jadwal_Studio j
     INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
     WHERE j.Tanggal_Jadwal = ? AND j.Status_Jadwal = ? AND j.Status = ? AND j.Is_Deleted = 0
     ORDER BY j.Jam_Mulai ASC",
    array($today, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
);

$q_barang = sqlsrv_query($conn, 
    "SELECT TOP 10 ID_Barang, Nama_Barang, Harga_Barang, Foto_Barang 
     FROM Barang_Cetak WHERE Is_Deleted = 0 AND Status = ? AND Stok_Barang > 0
     ORDER BY Stok_Barang DESC",
    array(STATUS_DATA_AKTIF)
);

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
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }
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
        .nav-link-item:hover, .nav-link-item.active { color: var(--p-pink); }
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
        .nav-avatar-wrapper { position: relative; }
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
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
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
        .hero-content { position: relative; z-index: 2; }
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
        .step-number-active { animation: pulse 2s infinite; }
        .step-number.inactive {
            background: #e2e8f0;
            color: #94a3b8;
        }
        .step-text {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-dark);
        }
        .step-text.inactive { color: #94a3b8; }
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
        .section-title span { color: var(--p-pink); }
        .section-count {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .section-count strong { color: var(--text-dark); }

        /* ===== PAKET SCROLL HORIZONTAL ===== */
        .paket-scroll-wrapper {
            position: relative;
            margin-bottom: 50px;
        }
        .paket-scroll-container {
            display: flex;
            gap: 24px;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            padding: 10px 4px 20px 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--light-pink) transparent;
        }
        .paket-scroll-container::-webkit-scrollbar {
            height: 8px;
        }
        .paket-scroll-container::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 10px;
        }
        .paket-scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--light-pink);
            border-radius: 10px;
        }
        .paket-scroll-container::-webkit-scrollbar-thumb:hover {
            background-color: var(--p-pink);
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
            min-width: 300px;
            max-width: 300px;
            flex-shrink: 0;
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
        .paket-card:hover .paket-img { transform: scale(1.1); }
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
        .paket-body { padding: 20px; }
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
        .scroll-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--p-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 10;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        .scroll-nav-btn:hover {
            background: var(--p-pink);
            color: #ffffff;
            border-color: var(--p-pink);
        }
        .scroll-nav-btn.left { left: -22px; }
        .scroll-nav-btn.right { right: -22px; }
        .scroll-nav-btn.hidden { display: none; }

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
        .barang-cetak-scroll-container {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 8px;
        }
        .barang-cetak-scroll-container::-webkit-scrollbar {
            width: 6px;
        }
        .barang-cetak-scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--light-pink);
            border-radius: 10px;
        }
        .barang-cetak-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }
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
        .pwd-requirement.valid { color: #059669; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }


        /* ========== RESPONSIVE BREAKPOINTS ========== */
        /* Tablet & Mobile (max-width: 991.98px) */
        @media (max-width: 991.98px) {
            .top-navbar {
                padding: 12px 16px;
            }
            .nav-logo {
                font-size: 1.4rem;
            }
            .nav-logo span {
                font-size: 0.75rem;
            }
            .nav-menu-center {
                display: none;
            }
            .nav-btn-booking {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
            .nav-avatar {
                width: 36px;
                height: 36px;
            }
            .hero-banner {
                padding: 40px 20px;
            }
            .hero-title {
                font-size: 1.8rem;
            }
            .hero-subtitle {
                font-size: 0.9rem;
            }
            .hero-btn {
                padding: 12px 28px;
                font-size: 0.9rem;
            }
            .booking-steps {
                display: none;
            }
            .main-container {
                padding: 20px 16px;
            }
            .stats-bar {
                gap: 10px;
            }
            .stat-chip {
                padding: 10px 14px;
            }
            .stat-chip-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            .stat-chip-text {
                font-size: 0.8rem;
            }
            .stat-chip-sub {
                font-size: 0.7rem;
            }
            .section-title {
                font-size: 1.1rem;
            }
            .paket-card {
                min-width: 260px;
                max-width: 260px;
            }
            .paket-img-wrapper {
                height: 180px;
            }
            .paket-nama {
                font-size: 1rem;
            }
            .paket-harga {
                font-size: 1.1rem;
            }
            .paket-btn {
                padding: 10px 20px;
                font-size: 0.8rem;
            }
            .scroll-nav-btn {
                display: none !important;
            }
            .info-section {
                grid-template-columns: 1fr;
            }
            .info-card {
                padding: 20px;
            }
            .modal-dialog.modal-lg {
                max-width: 95%;
                margin: 10px auto;
            }
            .modal-body-custom {
                padding: 16px;
            }
            .img-preview-container {
                width: 90px;
                height: 90px;
            }
            .form-control-custom {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            .nav-dropdown {
                right: -10px;
                min-width: 200px;
            }
        }

        /* Mobile Landscape & Small Tablets (max-width: 767.98px) */
        @media (max-width: 767.98px) {
            .top-navbar {
                padding: 10px 12px;
            }
            .nav-logo {
                font-size: 1.2rem;
            }
            .nav-logo span {
                display: none;
            }
            .nav-right {
                gap: 10px;
            }
            .nav-btn-booking {
                padding: 8px 12px;
                font-size: 0.75rem;
            }
            .nav-btn-booking i {
                display: none;
            }
            .hero-banner {
                padding: 30px 16px;
            }
            .hero-title {
                font-size: 1.5rem;
                letter-spacing: -0.5px;
            }
            .hero-subtitle {
                font-size: 0.85rem;
                margin-bottom: 20px;
            }
            .hero-btn {
                padding: 10px 24px;
                font-size: 0.85rem;
            }
            .main-container {
                padding: 16px 12px;
            }
            .stats-bar {
                gap: 8px;
                padding-bottom: 6px;
            }
            .stat-chip {
                padding: 8px 12px;
                border-radius: 12px;
            }
            .stat-chip-icon {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
                border-radius: 8px;
            }
            .stat-chip-text {
                font-size: 0.75rem;
            }
            .stat-chip-sub {
                font-size: 0.65rem;
            }
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 16px;
            }
            .section-title {
                font-size: 1rem;
            }
            .section-title i {
                font-size: 1.1rem;
            }
            .d-flex.flex-wrap.justify-content-between.align-items-center.gap-3 {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .d-flex.flex-wrap.justify-content-between.align-items-center.gap-3 .position-relative {
                max-width: 100% !important;
                min-width: 100% !important;
            }
            .d-flex.flex-wrap.justify-content-between.align-items-center.gap-3 > div:last-child {
                min-width: 100% !important;
            }
            #sortPaket {
                width: 100%;
            }
            .paket-scroll-wrapper {
                margin-bottom: 30px;
            }
            .paket-card {
                min-width: 240px;
                max-width: 240px;
                border-radius: 16px;
            }
            .paket-img-wrapper {
                height: 160px;
            }
            .paket-badge-durasi,
            .paket-badge-kapasitas {
                padding: 4px 10px;
                font-size: 0.7rem;
            }
            .paket-body {
                padding: 14px;
            }
            .paket-nama {
                font-size: 0.95rem;
            }
            .paket-desc {
                font-size: 0.8rem;
                margin-bottom: 12px;
            }
            .paket-meta {
                gap: 8px;
                margin-bottom: 12px;
            }
            .paket-meta-item {
                padding: 4px 8px;
                font-size: 0.75rem;
            }
            .paket-footer {
                padding-top: 12px;
            }
            .paket-harga {
                font-size: 1rem;
            }
            .paket-harga-satuan {
                font-size: 0.7rem;
            }
            .paket-btn {
                padding: 8px 16px;
                font-size: 0.8rem;
                border-radius: 10px;
            }
            .info-section {
                gap: 16px;
                margin-top: 24px;
            }
            .info-card {
                padding: 16px;
                border-radius: 16px;
            }
            .info-card-title {
                font-size: 1rem;
                margin-bottom: 12px;
            }
            .info-item {
                padding: 10px 0;
            }
            .info-icon {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            .info-text {
                font-size: 0.85rem;
            }
            .info-sub {
                font-size: 0.75rem;
            }
            .info-btn {
                padding: 5px 12px;
                font-size: 0.7rem;
            }
            .barang-cetak-scroll-container {
                max-height: 200px;
            }
            .modal-content-custom {
                border-radius: 20px;
            }
            .modal-header-custom {
                padding: 16px;
            }
            .modal-header-custom h5 {
                font-size: 1rem;
            }
            .modal-body-custom {
                padding: 16px;
            }
            .profile-nav-tabs .nav-link {
                padding: 8px 14px;
                font-size: 0.85rem;
            }
            .img-preview-container {
                width: 80px;
                height: 80px;
            }
            .btn-upload-trigger {
                width: 28px;
                height: 28px;
            }
            .form-label-custom {
                font-size: 0.8rem;
            }
            .form-control-custom {
                padding: 8px 12px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            .pwd-requirement {
                font-size: 0.7rem;
            }
            .modal-footer-custom {
                padding: 12px 16px;
                flex-direction: column-reverse;
                gap: 8px;
            }
            .modal-footer-custom .btn {
                width: 100%;
                padding: 10px;
            }
            .nav-dropdown {
                right: -5px;
                min-width: 180px;
                border-radius: 12px;
                padding: 8px;
            }
            .dropdown-header {
                font-size: 0.9rem;
                padding: 6px 12px;
            }
            .dropdown-item {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
        }

        /* Small Mobile (max-width: 575.98px) */
        @media (max-width: 575.98px) {
            .top-navbar {
                padding: 8px 10px;
            }
            .nav-logo {
                font-size: 1.1rem;
            }
            .nav-btn-booking {
                padding: 6px 10px;
                font-size: 0.7rem;
                border-radius: 8px;
            }
            .nav-avatar {
                width: 32px;
                height: 32px;
            }
            .hero-banner {
                padding: 24px 12px;
            }
            .hero-title {
                font-size: 1.3rem;
            }
            .hero-subtitle {
                font-size: 0.8rem;
            }
            .hero-btn {
                padding: 10px 20px;
                font-size: 0.8rem;
            }
            .main-container {
                padding: 12px 10px;
            }
            .stats-bar {
                gap: 6px;
            }
            .stat-chip {
                padding: 6px 10px;
                border-radius: 10px;
            }
            .stat-chip-icon {
                width: 24px;
                height: 24px;
                font-size: 0.7rem;
            }
            .stat-chip-text {
                font-size: 0.7rem;
            }
            .stat-chip-sub {
                font-size: 0.6rem;
            }
            .paket-card {
                min-width: 220px;
                max-width: 220px;
            }
            .paket-img-wrapper {
                height: 140px;
            }
            .paket-badge-durasi,
            .paket-badge-kapasitas {
                padding: 3px 8px;
                font-size: 0.65rem;
            }
            .paket-body {
                padding: 12px;
            }
            .paket-nama {
                font-size: 0.9rem;
            }
            .paket-desc {
                font-size: 0.75rem;
                -webkit-line-clamp: 2;
            }
            .paket-meta {
                gap: 6px;
                margin-bottom: 10px;
            }
            .paket-meta-item {
                padding: 3px 6px;
                font-size: 0.7rem;
                border-radius: 6px;
            }
            .paket-footer {
                padding-top: 10px;
            }
            .paket-harga {
                font-size: 0.9rem;
            }
            .paket-btn {
                padding: 6px 12px;
                font-size: 0.75rem;
            }
            .info-card {
                padding: 14px;
                border-radius: 14px;
            }
            .info-card-title {
                font-size: 0.95rem;
            }
            .info-item {
                padding: 8px 0;
            }
            .info-icon {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }
            .info-text {
                font-size: 0.8rem;
            }
            .info-sub {
                font-size: 0.7rem;
            }
            .info-btn {
                padding: 4px 10px;
                font-size: 0.65rem;
            }
            .section-count {
                font-size: 0.75rem;
            }
            .modal-dialog {
                margin: 5px;
            }
            .modal-content-custom {
                border-radius: 16px;
            }
            .modal-header-custom {
                padding: 14px;
            }
            .modal-body-custom {
                padding: 14px;
            }
            .profile-nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                scrollbar-width: none;
            }
            .profile-nav-tabs::-webkit-scrollbar {
                display: none;
            }
            .profile-nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            .row.g-3 > [class*="col-"] {
                padding-left: 8px;
                padding-right: 8px;
            }
            .form-control-custom {
                padding: 8px 10px;
                font-size: 0.8rem;
            }
            .img-preview-container {
                width: 70px;
                height: 70px;
            }
            .nav-dropdown {
                right: 0;
                min-width: 170px;
                border-radius: 10px;
            }
            .dropdown-item {
                padding: 8px 10px;
                font-size: 0.8rem;
            }
        }

        /* Extra Small Mobile (max-width: 359.98px) */
        @media (max-width: 359.98px) {
            .nav-logo {
                font-size: 1rem;
            }
            .nav-btn-booking {
                padding: 5px 8px;
                font-size: 0.65rem;
            }
            .hero-title {
                font-size: 1.1rem;
            }
            .hero-subtitle {
                font-size: 0.75rem;
            }
            .hero-btn {
                padding: 8px 16px;
                font-size: 0.75rem;
            }
            .paket-card {
                min-width: 200px;
                max-width: 200px;
            }
            .paket-img-wrapper {
                height: 120px;
            }
            .paket-nama {
                font-size: 0.85rem;
            }
            .paket-desc {
                font-size: 0.7rem;
            }
            .paket-meta-item {
                font-size: 0.65rem;
            }
            .paket-harga {
                font-size: 0.85rem;
            }
            .paket-btn {
                padding: 5px 10px;
                font-size: 0.7rem;
            }
            .stat-chip-text {
                font-size: 0.65rem;
            }
            .info-text {
                font-size: 0.75rem;
            }
        }

        /* Large Screens (min-width: 1200px) */
        @media (min-width: 1200px) {
            .main-container {
                max-width: 1320px;
            }
            .paket-card {
                min-width: 320px;
                max-width: 320px;
            }
            .paket-img-wrapper {
                height: 240px;
            }
        }

        /* Touch Device Optimizations */
        @media (hover: none) and (pointer: coarse) {
            .paket-card:hover {
                transform: none;
            }
            .paket-card:active {
                transform: scale(0.98);
            }
            .stat-chip:hover {
                transform: none;
            }
            .info-btn:hover {
                background: var(--s-pink);
                color: var(--p-pink);
            }
            .nav-avatar:hover {
                transform: none;
            }
            .hero-btn:hover {
                transform: none;
            }
            .nav-btn-booking:hover {
                transform: none;
            }
        }

        /* Reduced Motion Preference */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            html {
                scroll-behavior: auto;
            }
        }

    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="index.php" class="nav-link-item active">Dashboard</a>
            <a href="#section-paket" class="nav-link-item" id="navBookingBaru">Booking Baru</a>
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

    <!-- ALUR BOOKING STEPS -->
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
            <div class="step-item">
                <div class="step-number inactive">7</div>
                <div class="step-text inactive">Pembayaran</div>
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
                    <div class="stat-chip-sub">Segera Melakukan Pembayaran</div>
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

        <!-- PAKET FOTO - SCROLL HORIZONTAL DENGAN FILTER -->
        <div class="d-flex flex-column gap-3 mb-4" id="section-paket" style="scroll-margin-top: 100px;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="section-title mb-0">
                    <i class="bi bi-fire text-danger me-2"></i>
                    Paket Foto <span>Populer</span>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2" style="flex: 1; max-width: 600px; justify-content: flex-end;">
                    <div class="position-relative" style="flex: 1; min-width: 180px; max-width: 300px;">
                        <i class="bi bi-search position-absolute text-muted" style="left: 14px; top: 50%; transform: translateY(-50%);"></i>
                        <input type="text" id="searchPaket" class="form-control form-control-custom ps-5" placeholder="Cari nama paket..." onkeyup="filterPaketGrid()">
                    </div>
                    <div style="min-width: 150px;">
                        <select id="sortPaket" class="form-select form-control-custom" onchange="filterPaketGrid()">
                            <option value="all">Urutkan Harga</option>
                            <option value="murah">Termurah</option>
                            <option value="mahal">Termahal</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="section-count text-muted" style="font-size: 0.85rem; font-weight: 600;">
                    Harga <strong>per sesi</strong> &bull; <span id="active-paket-count"><?= $jumlah_paket ?></span> paket tersedia
                </div>
            </div>
        </div>

        <!-- PAKET SCROLL HORIZONTAL -->
        <div class="paket-scroll-wrapper">
            <button class="scroll-nav-btn left" id="scrollLeft" onclick="scrollPaket('left')" title="Geser ke kiri">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="paket-scroll-container" id="paketContainer">
                <?php
                $ada_paket = false;
                if ($q_paket):
                    while ($row = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)):
                        $ada_paket = true;
                        $foto_paket = ($row['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $row['Foto_Paket'])) 
                            ? "../../assets/img/paket/" . $row['Foto_Paket'] 
                            : null;
                        $harga = number_format($row['Harga_Paket'], 0, ',', '.');
                        $jumlah_ruangan = $row['jumlah_ruangan'] ?? 0;
                        $jumlah_jadwal = $row['jumlah_jadwal_tersedia'] ?? 0;
                ?>
                    <a href="Layanan/Paket/pilih_paket.php?id_paket=<?= $row['ID_Paket'] ?>" 
                       class="paket-card" 
                       data-nama="<?= strtolower(htmlspecialchars($row['Nama_Paket'])) ?>" 
                       data-harga="<?= (int)$row['Harga_Paket'] ?>">
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
                                    <i class="bi bi-calendar-check"></i> <?= $jumlah_jadwal ?> Jadwal
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
                endif;

                if (!$ada_paket):
                ?>
                    <div class="text-center py-5" style="min-width: 100%; flex-shrink: 0;">
                        <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1; display: block;"></i>
                        <p class="text-muted">Belum ada paket foto yang memenuhi kriteria tersedia saat ini.</p>
                        <small class="text-muted d-block mt-2">Pastikan paket memiliki ruangan, tema, dan jadwal yang aktif.</small>
                    </div>
                <?php endif; ?>
            </div>

            <button class="scroll-nav-btn right" id="scrollRight" onclick="scrollPaket('right')" title="Geser ke kanan">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <!-- INFO SECTION: Jadwal + Barang Cetak -->
        <div class="info-section">
            <!-- Jadwal Hari Ini -->
            <div class="info-card">
                <div class="info-card-title">
                    <i class="bi bi-calendar-day-fill"></i>
                    Jadwal Tersedia Hari Ini
                </div>
                <?php
                $ada_jadwal = false;
                if ($q_jadwal):
                    while ($row = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)):
                        $ada_jadwal = true;
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
                <?php endwhile; endif; 
                if (!$ada_jadwal):
                ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x fs-2 mb-2" style="color: #cbd5e1; display: block;"></i>
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
                <div class="barang-cetak-scroll-container">
                    <?php
                    $ada_barang = false;
                    if ($q_barang):
                        while ($row = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)):
                            $ada_barang = true;
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
                        </div>
                    <?php 
                        endwhile; 
                    endif;
                    if (!$ada_barang):
                    ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-2 mb-2" style="color: #cbd5e1; display: block;"></i>
                            <p class="text-muted small">Belum ada barang cetak tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <!-- MODAL DETAIL PROFIL & KATA SANDI -->
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

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
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

        // FUNGSI SCROLL PAKET KIRI/KANAN
        function scrollPaket(direction) {
            const container = document.getElementById('paketContainer');
            const scrollAmount = 320;
            if (direction === 'left') {
                container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            } else {
                container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            }
        }


        // ===== INTERSECTION OBSERVER UNTUK NAVBAR ACTIVE =====
        document.addEventListener("DOMContentLoaded", function() {
            const dashboardLink = document.querySelector('a[href="index.php"]');
            const bookingLink = document.getElementById('navBookingBaru');
            const paketSection = document.getElementById('section-paket');

            if (paketSection && bookingLink && dashboardLink) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            // Section paket terlihat → Booking Baru aktif
                            dashboardLink.classList.remove('active');
                            bookingLink.classList.add('active');
                        } else {
                            // Section paket tidak terlihat → Dashboard aktif
                            bookingLink.classList.remove('active');
                            dashboardLink.classList.add('active');
                        }
                    });
                }, {
                    threshold: 0.3,  // Aktif ketika 30% section terlihat
                    rootMargin: '-80px 0px -50% 0px'  // Offset untuk navbar sticky
                });

                observer.observe(paketSection);
            }
        });
        // FUNGSI FILTER PAKET - PENCARIAN & PENGURUTAN REALTIME
        function filterPaketGrid() {
            const searchVal = document.getElementById('searchPaket').value.toLowerCase().trim();
            const sortVal = document.getElementById('sortPaket').value;
            const container = document.getElementById('paketContainer');
            const cards = Array.from(container.querySelectorAll('.paket-card'));

            let visibleCount = 0;

            // 1. Filter Berdasarkan Pencarian
            cards.forEach(card => {
                const name = card.getAttribute('data-nama');
                if (name && name.includes(searchVal)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // 2. Sort Berdasarkan Urutan Harga
            if (sortVal === 'murah') {
                cards.sort((a, b) => parseInt(a.getAttribute('data-harga')) - parseInt(b.getAttribute('data-harga')));
            } else if (sortVal === 'mahal') {
                cards.sort((a, b) => parseInt(b.getAttribute('data-harga')) - parseInt(a.getAttribute('data-harga')));
            }

            // Kembalikan elemen kartu yang telah diurutkan ke dalam kontainer
            cards.forEach(card => container.appendChild(card));

            // Perbarui indikator jumlah data paket yang aktif
            document.getElementById('active-paket-count').innerText = visibleCount;

            // Tampilkan / Sembunyikan pesan jika tidak ada data yang cocok
            let noDataMsg = container.querySelector('.no-data-card');
            if (visibleCount === 0 && cards.length > 0) {
                if (!noDataMsg) {
                    noDataMsg = document.createElement('div');
                    noDataMsg.className = 'text-center py-5 no-data-card';
                    noDataMsg.style.cssText = 'min-width: 100%; flex-shrink: 0;';
                    noDataMsg.innerHTML = `
                        <i class="bi bi-search fs-1 mb-3" style="color: #cbd5e1; display: block;"></i>
                        <p class="text-muted">Paket "${searchVal}" tidak ditemukan.</p>
                    `;
                    container.appendChild(noDataMsg);
                } else {
                    noDataMsg.querySelector('p').innerText = `Paket "${searchVal}" tidak ditemukan.`;
                    noDataMsg.style.display = 'block';
                }
            } else {
                if (noDataMsg) {
                    noDataMsg.style.display = 'none';
                }
            }
        }

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
            const clockEl = document.getElementById('live-clock');
            if (clockEl) {
                clockEl.innerText = 
                    days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + 
                    String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0') + ' WIB';
            }
        }
        const clockEl = document.getElementById('live-clock');
        if (clockEl) {
            setInterval(updateLiveClock, 1000);
            updateLiveClock();
        }

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