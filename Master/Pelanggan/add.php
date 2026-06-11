<?php
ob_start();
session_start();
include '../../koneksi.php';

<<<<<<< HEAD
// Helper: Format tanggal ke bahasa Indonesia
function formatTanggalIndo($dateStr) {
    if (empty($dateStr)) return '';
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $parts = explode('-', $dateStr);
    if (count($parts) == 3) {
        return $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
    }
    return $dateStr;
}

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
=======
// Proteksi Halaman: Hanya Admin yang bisa menambah pelanggan
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
    header("Location: ../../login.php");
    exit();
}

<<<<<<< HEAD
$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// --- INISIALISASI VARIABEL ---
$error_nama = $error_email = $error_username = $error_hp = $error_jk = $error_dob = $error_alamat = $error_password = $error_foto = $error_general = "";
=======
// 1. Inisialisasi variabel error
$error_nama = ""; $error_email = ""; $error_hp = ""; $error_alamat = ""; $error_pass = ""; $error_general = "";
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
$success = false;
$old_values = [];

// --- PROSES SIMPAN ---
if (isset($_POST['simpan'])) {
<<<<<<< HEAD
    $nama       = trim($_POST['nama'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $hp_raw     = trim($_POST['no_hp'] ?? '');
    $jk         = $_POST['jenis_kelamin'] ?? '';
    $dob        = $_POST['tanggal_lahir'] ?? '';
    $alamat     = trim($_POST['alamat'] ?? '');

    $old_values = compact('nama', 'username', 'email', 'hp_raw', 'jk', 'dob', 'alamat');

    // === VALIDASI NAMA ===
    if (empty($nama)) {
        $error_nama = "Nama lengkap wajib diisi!";
    } elseif (strlen($nama) < 3) {
        $error_nama = "Nama minimal 3 karakter!";
    } elseif (!preg_match("/^[a-zA-Z\s]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf dan spasi!";
    }

    // === VALIDASI USERNAME ===
    if (empty($username)) {
        $error_username = "Username wajib diisi!";
    } elseif (strlen($username) < 5) {
        $error_username = "Username minimal 5 karakter!";
    } elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) {
        $error_username = "Username hanya boleh huruf, angka, dan underscore!";
    } elseif (strlen($username) > 20) {
        $error_username = "Username maksimal 20 karakter!";
=======
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $hp     = trim($_POST['no_hp']); 
    $alamat = trim($_POST['alamat']);

    // --- VALIDASI SERVER SIDE ---
    
    // Validasi Nama
    if (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    }

    // Validasi Nomor HP
    $hp_clean = str_replace(['+', ' '], '', $hp); 
    if (!ctype_digit($hp_clean)) {
        $error_hp = "Nomor Telepon harus berupa angka!";
    } elseif (strlen($hp_clean) < 11 || strlen($hp_clean) > 14) {
        $error_hp = "Nomor Telepon tidak valid (Harus 11-14 digit termasuk 62)!";
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
    }

    // === VALIDASI EMAIL ===
    if (empty($email)) {
        $error_email = "Email wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    } elseif (strlen($email) > 100) {
        $error_email = "Email terlalu panjang (maks 100 karakter)!";
    }

<<<<<<< HEAD
    // === VALIDASI PASSWORD ===
    if (empty($password)) {
        $error_password = "Kata sandi wajib diisi!";
    } elseif (strlen($password) < 8) {
        $error_password = "Kata sandi minimal 8 karakter!";
    } elseif (strlen($password) > 50) {
        $error_password = "Kata sandi terlalu panjang!";
    }

    // === VALIDASI NO HP ===
    $hp_digits = preg_replace('/[^0-9]/', '', $hp_raw);
    if (strpos($hp_raw, '+62') === 0 || strpos($hp_raw, '62') === 0) {
        $hp_digits = preg_replace('/^62/', '', $hp_digits);
    }
    $hp_clean = '62' . $hp_digits;

    if (empty($hp_raw)) {
        $error_hp = "Nomor telepon wajib diisi!";
    } elseif (!ctype_digit($hp_digits)) {
        $error_hp = "Nomor telepon hanya boleh berisi angka!";
    } elseif (strlen($hp_digits) < 10) {
        $error_hp = "Nomor telepon minimal 10 digit (setelah +62)!";
    } elseif (strlen($hp_digits) > 13) {
        $error_hp = "Nomor telepon maksimal 13 digit (setelah +62)!";
    } elseif (!preg_match("/^8[1-9][0-9]{8,11}$/", $hp_digits)) {
        $error_hp = "Nomor telepon tidak valid. Contoh: 81234567890";
    }
=======
    // Validasi Alamat
    if (strlen($alamat) < 10) {
        $error_alamat = "Mohon isi alamat lengkap (Min. 10 Karakter)!";
    }

    // --- VALIDASI KATA SANDI (HURUF, ANGKA, SIMBOL) ---
    if (strlen($pass) < 8) {
        $error_pass = "Kata sandi minimal 8 karakter!";
    } elseif (!preg_match("/[a-zA-Z]/", $pass)) {
        $error_pass = "Kata sandi wajib mengandung huruf!";
    } elseif (!preg_match("/[0-9]/", $pass)) {
        $error_pass = "Kata sandi wajib mengandung angka!";
    } elseif (!preg_match("/[^a-zA-Z0-9]/", $pass)) {
        $error_pass = "Kata sandi wajib mengandung simbol (contoh: @,#,$,!,%)!";
    }

    // 2. Jika validasi lolos, cek duplikasi email & simpan
    if ($error_nama == "" && $error_hp == "" && $error_email == "" && $error_alamat == "" && $error_pass == "") {
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c

    // === VALIDASI JENIS KELAMIN ===
    if (empty($jk)) {
        $error_jk = "Jenis kelamin wajib dipilih!";
    } elseif (!in_array($jk, ['Laki-laki', 'Perempuan'])) {
        $error_jk = "Pilihan jenis kelamin tidak valid!";
    }

    // === VALIDASI TANGGAL LAHIR ===
    if (empty($dob)) {
        $error_dob = "Tanggal lahir wajib diisi!";
    } else {
        $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dob_date || $dob_date->format('Y-m-d') !== $dob) {
            $error_dob = "Format tanggal lahir tidak valid!";
        } else {
<<<<<<< HEAD
            $today = new DateTime();
            $age = $today->diff($dob_date)->y;
            if ($dob_date > $today) {
                $error_dob = "Tanggal lahir tidak boleh di masa depan!";
            } elseif ($age < 13) {
                $error_dob = "Pelanggan minimal berusia 13 tahun!";
            } elseif ($age > 100) {
                $error_dob = "Umur tidak valid (maksimal 100 tahun)!";
            }
        }
    }
=======
            sqlsrv_begin_transaction($conn);
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c

    // === VALIDASI ALAMAT ===
    if (empty($alamat)) {
        $error_alamat = "Alamat wajib diisi!";
    } elseif (strlen($alamat) < 10) {
        $error_alamat = "Alamat terlalu singkat (minimal 10 karakter)!";
    } elseif (strlen($alamat) > 255) {
        $error_alamat = "Alamat terlalu panjang (maksimal 255 karakter)!";
    }

    // === CEK DUPLIKAT DI DATABASE ===
    if (empty($error_nama) && empty($error_username) && empty($error_email) && empty($error_hp)) {
        $cek_user = sqlsrv_query($conn, "SELECT ID_Pelanggan FROM Pelanggan WHERE Username_Pelanggan = ?", [$username]);
        if (sqlsrv_has_rows($cek_user)) {
            $error_username = "Username sudah digunakan oleh pelanggan lain!";
        }

        $cek_email = sqlsrv_query($conn, "SELECT ID_Pelanggan FROM Pelanggan WHERE Email_Pelanggan = ?", [$email]);
        if (sqlsrv_has_rows($cek_email)) {
            $error_email = "Email sudah terdaftar di sistem!";
        }

<<<<<<< HEAD
        $cek_hp = sqlsrv_query($conn, "SELECT ID_Pelanggan FROM Pelanggan WHERE No_Hp = ?", [$hp_clean]);
        if (sqlsrv_has_rows($cek_hp)) {
            $error_hp = "Nomor telepon sudah terdaftar di sistem!";
        }
    }

    // === PROSES UPLOAD FOTO ===
    $foto_name = 'default.jpg';
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $file_size = $_FILES['foto']['size'];

        if (!in_array($file_ext, $allowed)) {
            $error_foto = "Format foto harus JPG, JPEG, atau PNG!";
        } elseif ($file_size > 2 * 1024 * 1024) {
            $error_foto = "Ukuran foto maksimal 2MB!";
        } else {
            $foto_name = uniqid('pelanggan_') . '.' . $file_ext;
            $upload_path = "../../assets/img/pelanggan/" . $foto_name;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                $error_foto = "Gagal mengupload foto. Coba lagi!";
                $foto_name = 'default.jpg';
            }
        }
    }

    // === INSERT KE DATABASE ===
    if (empty($error_nama) && empty($error_username) && empty($error_email) && empty($error_password) && 
        empty($error_hp) && empty($error_jk) && empty($error_dob) && empty($error_alamat) && empty($error_foto)) {

        sqlsrv_begin_transaction($conn);

        try {
            $sql = "INSERT INTO Pelanggan (
                Username_Pelanggan, Password_Pelanggan, Nama_Pelanggan, 
                Email_Pelanggan, No_Hp, Jenis_Kelamin, Tanggal_Lahir, 
                Alamat, Foto_Profil, Status, Is_Deleted,
                Created_By, Created_Date, Modified_By, Modified_Date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, GETDATE(), ?, GETDATE())";

            $params = [
                $username,
                $password,
                $nama,
                $email,
                $hp_clean,
                $jk,
                $dob,
                $alamat,
                $foto_name,
                $nama_admin,
                $nama_admin
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                sqlsrv_commit($conn);
                $success = true;
                $old_values = [];
            } else {
                sqlsrv_rollback($conn);
                $error_general = "Gagal menyimpan data. Silakan coba lagi!";
                if ($foto_name != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_name)) {
                    unlink("../../assets/img/pelanggan/" . $foto_name);
                }
            }
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $error_general = "Terjadi kesalahan sistem: " . $e->getMessage();
            if ($foto_name != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_name)) {
                unlink("../../assets/img/pelanggan/" . $foto_name);
=======
                if ($stmt2) {
                    sqlsrv_commit($conn); 
                    $success = true;
                } else {
                    sqlsrv_rollback($conn);
                    $error_general = "Gagal menyimpan biodata pelanggan.";
                }
            } else {
                sqlsrv_rollback($conn);
                $error_general = "Kesalahan sistem saat membuat akun.";
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
            }
        }
    }
}
<<<<<<< HEAD

// Ambil profil admin untuk sidebar
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$email_admin = $d_admin['email_karyawan'] ?? 'admin@spotlight.com';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
    ? "../../assets/img/pelanggan/" . $foto_admin 
    : $default_svg_avatar;
?>
=======
?>  
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pelanggan Baru – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem;
            color: var(--p-pink); text-decoration: none;
            letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700;
            text-decoration: none; border-radius: 12px; font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px;
            color: #718096; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }

        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 35px;
        }
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

<<<<<<< HEAD
        /* FORM CARD */
        .form-card {
            background: #ffffff; border-radius: 24px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            padding: 40px; max-width: 900px; margin: 0 auto;
        }

        .form-section-title {
            font-size: 1.1rem; font-weight: 800; color: var(--text-dark);
            margin-bottom: 24px; padding-bottom: 12px;
            border-bottom: 2px solid var(--s-pink);
            display: flex; align-items: center; gap: 10px;
        }

        .form-label-custom {
            font-size: 0.75rem; font-weight: 800;
            color: #94a3b8; text-transform: uppercase;
            letter-spacing: 1.2px; margin-bottom: 8px;
            display: block;
        }

        .form-input-custom {
            width: 100%; border: 2px solid #f1f5f9;
            border-radius: 16px; padding: 14px 18px;
            font-size: 0.9rem; font-weight: 600;
            color: var(--text-dark); background: #f8fafc;
            transition: var(--transition-3d);
        }
        .form-input-custom:focus {
            outline: none; border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }
        .form-input-custom::placeholder { color: #cbd5e1; font-weight: 500; }
        .form-input-custom.is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .form-select-custom {
            width: 100%; border: 2px solid #f1f5f9;
            border-radius: 16px; padding: 14px 18px;
            font-size: 0.9rem; font-weight: 600;
            color: var(--text-dark); background: #f8fafc;
            transition: var(--transition-3d); cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 45px;
        }
        .form-select-custom:focus {
            outline: none; border-color: var(--p-pink);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }

        .form-textarea-custom {
            width: 100%; border: 2px solid #f1f5f9;
            border-radius: 16px; padding: 14px 18px;
            font-size: 0.9rem; font-weight: 600;
            color: var(--text-dark); background: #f8fafc;
            transition: var(--transition-3d); resize: vertical;
            min-height: 100px;
        }
        .form-textarea-custom:focus {
            outline: none; border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }
        .form-textarea-custom::placeholder { color: #cbd5e1; font-weight: 500; }
        .form-textarea-custom.is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .error-text {
            color: #ef4444; font-size: 0.8rem;
            font-weight: 700; margin-top: 6px;
            display: flex; align-items: center; gap: 5px;
        }

        .btn-simpan {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 16px;
            padding: 16px 32px; font-weight: 800; font-size: 1rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; box-shadow: 0 10px 30px rgba(213, 61, 102, 0.25);
        }
        .btn-simpan:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(213, 61, 102, 0.35);
        }

        .btn-batal {
            background: #f1f5f9; color: #475569;
            border: 2px solid #e2e8f0; border-radius: 16px;
            padding: 16px 32px; font-weight: 700; font-size: 1rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-batal:hover {
            background: #e2e8f0; color: #1e293b;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .btn-group-action {
            display: flex; gap: 12px; justify-content: flex-end;
            margin-top: 30px; padding-top: 20px;
            border-top: 2px solid var(--s-pink);
        }

        /* FOTO UPLOAD */
        .foto-upload-wrapper {
            position: relative; width: 120px; height: 120px;
            border-radius: 50%; overflow: hidden;
            border: 3px solid var(--light-pink);
            cursor: pointer; transition: var(--transition-3d);
            margin: 0 auto 20px;
        }
        .foto-upload-wrapper:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .foto-upload-wrapper img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .foto-upload-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(213, 61, 102, 0.7);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: 0.3s; color: #ffffff;
        }
        .foto-upload-wrapper:hover .foto-upload-overlay { opacity: 1; }
        .foto-upload-input { display: none; }

        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute; right: 16px; top: 50%;
            transform: translateY(-50%); cursor: pointer;
            color: #94a3b8; font-size: 1.1rem; transition: 0.3s;
        }
        .toggle-password:hover { color: var(--p-pink); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.6s ease-out; }

        /* Radio Buttons */
        .radio-group {
            display: flex;
            gap: 10px;
        }
        .radio-card {
            flex: 1;
            border: 2px solid #f1f5f9;
            border-radius: 14px;
            padding: 10px 8px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-3d);
            background: #f8fafc;
            position: relative;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .radio-card:hover {
            border-color: var(--light-pink);
            transform: translateY(-2px);
        }
        .radio-card.active {
            border-color: var(--p-pink);
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.1);
        }
        .radio-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .radio-card .radio-icon {
            font-size: 1.3rem;
            margin-bottom: 4px;
            color: #94a3b8;
            transition: 0.3s;
            line-height: 1;
        }
        .radio-card.active .radio-icon {
            color: var(--p-pink);
        }
        .radio-card .radio-text {
            font-weight: 700;
            font-size: 0.75rem;
            color: #64748b;
            transition: 0.3s;
            line-height: 1.2;
        }
        .radio-card.active .radio-text {
            color: var(--p-pink);
        }

        /* Date Picker */
        .date-picker-wrapper {
            position: relative;
            cursor: pointer;
        }
        .date-picker-wrapper .form-input-custom {
            cursor: pointer;
            padding-right: 45px;
        }
        .date-picker-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.2rem;
            transition: 0.3s;
            pointer-events: none;
            z-index: 5;
        }
        .date-picker-wrapper:hover .date-picker-icon {
            color: var(--p-pink);
        }
        #dateInput {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.001;
            cursor: pointer;
            z-index: 100;
            border: none;
            background: transparent;
        }

        /* HP Input with +62 prefix */
        .hp-input-wrapper {
            display: flex;
            align-items: stretch;
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            overflow: hidden;
            background: #f8fafc;
            transition: var(--transition-3d);
        }
        .hp-input-wrapper:focus-within {
            border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }
        .hp-prefix {
            display: flex;
            align-items: center;
            padding: 14px 12px 14px 18px;
            font-weight: 700;
            color: var(--p-pink);
            font-size: 0.9rem;
            background: #f8fafc;
            border-right: 2px solid #f1f5f9;
            white-space: nowrap;
        }
        .hp-input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 14px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
        }
        .hp-input::placeholder {
            color: #cbd5e1;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
=======
.btn-batal:hover { 
    background: #cbd5e1; 
    color: #1e293b; 
    transform: translateY(-5px); 
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
    </style>
</head>
<body>

<<<<<<< HEAD
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Admin</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        </ul>
=======
    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div>
                    <h2 class="fw-bold mb-4" style="font-size: 2.8rem; line-height: 1.1;">Welcome to <br><span style="color: #ffe0ec">SpotLight</span> Family.</h2>
                    <p class="opacity-75">Manajemen data pelanggan membantu studio dalam mengelola riwayat booking dan pengiriman hasil foto cetak secara akurat.</p>
                </div>
                <div class="glass-box">
                    <p class="mb-0 small" style="line-height: 1.7;"><i class="bi bi-info-circle-fill me-2"></i>Sistem secara otomatis membuat akun login dengan role "Customer" setelah data profil disimpan.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Tambah Pelanggan</h3>
                    <p class="text-muted small fw-500">Daftarkan profil customer baru ke dalam sistem.</p>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- NAMA LENGKAP -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Customer</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan nama (Huruf saja)" value="<?= @$_POST['nama'] ?>" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Aktif</label>
                            <input type="email" name="email" class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" placeholder="customer@email.com" value="<?= @$_POST['email'] ?>" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- WHATSAPP & PASSWORD -->
                        <div class="col-md-6 mb-3">
    <label class="form-label">Nomor Telepon</label>
    <input type="text" name="no_hp" id="inputHP" 
           class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" 
           value="<?= isset($_POST['no_hp']) ? $_POST['no_hp'] : '+62 ' ?>" required>
    <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
</div>
                       <div class="col-md-6 mb-3">
    <label class="form-label">Kata Sandi</label>
    <div class="position-relative">
        <!-- Tambahkan class PHP is-invalid di bawah ini -->
        <input type="password" name="password" id="inputPass" 
               class="form-control <?= ($error_pass != '') ? 'is-invalid' : '' ?>" 
               placeholder="••••••••" required>
        <i class="bi bi-eye-slash position-absolute top-50 end-0 translate-middle-y me-3 text-muted" 
           id="togglePassword" 
           style="cursor: pointer; z-index: 10;"></i>
    </div>
     <?php if($error_pass): ?>
        <div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_pass ?></div>
    <?php endif; ?>
</div>
                        <!-- ALAMAT (VALIDASI MIN 10 KARAKTER) -->
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat" class="form-control <?= ($error_alamat != '') ? 'is-invalid' : '' ?>" rows="2" placeholder="Masukkan alamat lengkap (Min. 10 Karakter)..." required><?= @$_POST['alamat'] ?></textarea>
                            <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                        </div>
>>>>>>> 298ddc1654af930b4da20d45cba40f1a517e2d8c
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                            <li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                            <li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                            <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
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

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Tambah Pelanggan Baru 👤</h3>
                <p class="text-muted small mb-0">Daftarkan data pelanggan baru ke dalam sistem SpotLight Studio.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <div class="form-card animate-fade-in">

            <form method="POST" enctype="multipart/form-data" id="formPelanggan">

                <!-- FOTO PROFIL -->
                <div class="text-center mb-4">
                    <div class="foto-upload-wrapper" onclick="document.getElementById('fotoInput').click()">
                        <img id="previewFoto" src="<?= $default_svg_avatar ?>" alt="Preview Foto">
                        <div class="foto-upload-overlay">
                            <i class="bi bi-camera-fill fs-3"></i>
                        </div>
                    </div>
                    <input type="file" name="foto" id="fotoInput" class="foto-upload-input" accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this)">
                    <p class="text-muted small mb-0">Klik untuk upload foto profil</p>
                    <p class="text-muted" style="font-size: 0.7rem;">Format: JPG, JPEG, PNG (Maks. 2MB)</p>
                    <?php if($error_foto): ?><div class="error-text justify-content-center"><i class="bi bi-x-circle-fill"></i> <?= $error_foto ?></div><?php endif; ?>
                </div>

                <!-- INFORMASI AKUN -->
                <div class="form-section-title">
                    <i class="bi bi-shield-lock-fill text-danger"></i>
                    Informasi Akun
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label-custom">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-input-custom <?= $error_username ? 'is-invalid' : '' ?>" 
                               placeholder="Masukkan username (huruf, angka, underscore)" 
                               value="<?= htmlspecialchars($old_values['username'] ?? '') ?>" required>
                        <?php if($error_username): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_username ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Kata Sandi <span class="text-danger">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="inputPassword" class="form-input-custom <?= $error_password ? 'is-invalid' : '' ?>" 
                                   placeholder="Minimal 8 karakter" required>
                            <i class="bi bi-eye-slash toggle-password" onclick="togglePassword()"></i>
                        </div>
                        <?php if($error_password): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_password ?></div><?php endif; ?>
                    </div>
                </div>

                <!-- DATA PRIBADI -->
                <div class="form-section-title">
                    <i class="bi bi-person-fill text-danger"></i>
                    Data Pribadi
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label-custom">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="inputNama" class="form-input-custom <?= $error_nama ? 'is-invalid' : '' ?>" 
                               placeholder="Masukkan nama lengkap sesuai KTP (hanya huruf)" 
                               value="<?= htmlspecialchars($old_values['nama'] ?? '') ?>" required>
                        <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Email Aktif <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-input-custom <?= $error_email ? 'is-invalid' : '' ?>" 
                               placeholder="contoh: pelanggan@email.com" 
                               value="<?= htmlspecialchars($old_values['email'] ?? '') ?>" required>
                        <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Nomor Telepon <span class="text-danger">*</span></label>
                        <div class="hp-input-wrapper">
                            <span class="hp-prefix">+62</span>
                            <input type="text" name="no_hp" id="inputHP" class="hp-input <?= $error_hp ? 'is-invalid' : '' ?>" 
                                   placeholder="81234567890" 
                                   value="<?= htmlspecialchars($old_values['hp_raw'] ?? '') ?>" required>
                        </div>
                        <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                        <small class="text-muted" style="font-size: 0.7rem;">Format: +62 81234567890</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Jenis Kelamin <span class="text-danger">*</span></label>
                        <div class="radio-group">
                            <label class="radio-card <?= ($old_values['jk'] ?? '') == 'Laki-laki' ? 'active' : '' ?>">
                                <input type="radio" name="jenis_kelamin" value="Laki-laki" 
                                       <?= ($old_values['jk'] ?? '') == 'Laki-laki' ? 'checked' : '' ?> required>
                                <div class="radio-icon"><i class="bi bi-gender-male"></i></div>
                                <div class="radio-text">Laki-laki</div>
                            </label>
                            <label class="radio-card <?= ($old_values['jk'] ?? '') == 'Perempuan' ? 'active' : '' ?>">
                                <input type="radio" name="jenis_kelamin" value="Perempuan" 
                                       <?= ($old_values['jk'] ?? '') == 'Perempuan' ? 'checked' : '' ?> required>
                                <div class="radio-icon"><i class="bi bi-gender-female"></i></div>
                                <div class="radio-text">Perempuan</div>
                            </label>
                        </div>
                        <?php if($error_jk): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_jk ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Tanggal Lahir <span class="text-danger">*</span></label>
                        <div class="date-picker-wrapper" id="datePickerWrapper">
                            <input type="text" name="tanggal_lahir_display" id="dateDisplay" class="form-input-custom <?= $error_dob ? 'is-invalid' : '' ?>" 
                                   placeholder="Pilih tanggal lahir" readonly
                                   value="<?= !empty($old_values['dob']) ? formatTanggalIndo($old_values['dob']) : '' ?>">
                            <input type="hidden" name="tanggal_lahir" id="dateValue" value="<?= htmlspecialchars($old_values['dob'] ?? '') ?>">
                            <i class="bi bi-calendar-event date-picker-icon" id="dateIcon"></i>
                            <input type="date" id="dateInput" 
                                   value="<?= htmlspecialchars($old_values['dob'] ?? '') ?>"
                                   max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
                                   onchange="updateDateDisplay(this.value)">
                        </div>
                        <?php if($error_dob): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_dob ?></div><?php endif; ?>
                        <small class="text-muted" style="font-size: 0.7rem;">Minimal berusia 13 tahun</small>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label-custom">Alamat Domisili <span class="text-danger">*</span></label>
                        <textarea name="alamat" class="form-textarea-custom <?= $error_alamat ? 'is-invalid' : '' ?>" 
                                  placeholder="Masukkan alamat lengkap domisili (minimal 10 karakter)" required><?= htmlspecialchars($old_values['alamat'] ?? '') ?></textarea>
                        <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                    </div>
                </div>

                <?php if($error_general): ?>
                <div class="alert alert-danger rounded-3 mb-4 fw-bold" style="border: none; background: #fef2f2; color: #dc2626;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_general ?>
                </div>
                <?php endif; ?>

                <!-- BUTTONS -->
                <div class="btn-group-action">
                    <a href="list.php" class="btn-batal">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" name="simpan" class="btn-simpan">
                        <i class="bi bi-check-lg"></i> Simpan Data
                    </button>
                </div>

            </form>
        </div>

    </div>

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

        // Preview Foto
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewFoto').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle Password
        function togglePassword() {
            const input = document.getElementById('inputPassword');
            const icon = document.querySelector('.toggle-password');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }

        // Real-time Validasi Nama
        document.getElementById('inputNama').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        });

        // Radio button click handler
        document.querySelectorAll('.radio-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.radio-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Real-time Validasi HP
        document.getElementById('inputHP').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 13) this.value = this.value.slice(0, 13);
        });

        // Date Picker - Click handler
        document.getElementById('datePickerWrapper').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var dateInput = document.getElementById('dateInput');
            if (dateInput.showPicker) {
                dateInput.showPicker();
            } else {
                dateInput.click();
            }
        });

        document.getElementById('dateIcon').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var dateInput = document.getElementById('dateInput');
            if (dateInput.showPicker) {
                dateInput.showPicker();
            } else {
                dateInput.click();
            }
        });

        // Date Picker - Update display
        function updateDateDisplay(value) {
            if (!value) return;
            const parts = value.split('-');
            const bulan = {
                '01': 'Januari', '02': 'Februari', '03': 'Maret', '04': 'April',
                '05': 'Mei', '06': 'Juni', '07': 'Juli', '08': 'Agustus',
                '09': 'September', '10': 'Oktober', '11': 'November', '12': 'Desember'
            };
            const display = parts[2] + ' ' + bulan[parts[1]] + ' ' + parts[0];
            document.getElementById('dateDisplay').value = display;
            document.getElementById('dateValue').value = value;
        }

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }

        // =====================================================
        // JAM REAL-TIME - FIX: Pastikan elemen ada sebelum update
        // =====================================================
        function updateLiveClock() {
            var clockEl = document.getElementById('live-clock');
            if (!clockEl) {
                console.log('Elemen live-clock tidak ditemukan');
                return;
            }

            var now = new Date();
            var days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            var months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            var dayName = days[now.getDay()];
            var day = now.getDate();
            var monthName = months[now.getMonth()];
            var year = now.getFullYear();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var seconds = now.getSeconds();

            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            clockEl.innerText = dayName + ', ' + day + ' ' + monthName + ' ' + year + ' - ' + hours + ':' + minutes + ':' + seconds + ' WIB';
        }

        // Jalankan segera dan set interval
        updateLiveClock();
        setInterval(updateLiveClock, 1000);

        // SweetAlert Success
        <?php if($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Data pelanggan baru berhasil ditambahkan ke sistem.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'list.php?status_sukses=tambah';
        });
        <?php endif; ?>
    </script>

</body>
</html>
<?php ob_end_flush(); ?>