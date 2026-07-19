<?php
// MUST be first - no whitespace before this!
ob_start();
session_start();
include 'koneksi.php';

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- DEBUG: Uncomment to see errors ---
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Inisialisasi variabel
$error_email_login = "";
$error_nama = $error_username = $error_email_reg = $error_hp = "";
$error_jk = $error_dob = $error_alamat = $error_pass = $error_confirm_pass = $error_foto = "";
$success_register = false;
$registered_email = $registered_password = "";
$panel_aktif = "";
$foto_profil = 'default.jpg';

if (isset($_GET['aksi']) && $_GET['aksi'] == 'daftar') {
    $panel_aktif = "ke-daftar";
}

// Simpan redirect parameter untuk digunakan saat switch panel
$redirect_param = $_GET['redirect'] ?? '';
$id_paket_param = $_GET['id_paket'] ?? '';
$redirect_query = '';
if (!empty($redirect_param) && !empty($id_paket_param)) {
    $redirect_query = '&redirect=' . urlencode($redirect_param) . '&id_paket=' . urlencode($id_paket_param);
}

// =====================================================
// LOGIN MULTI-ROLE
// =====================================================
if (isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_email_login = "Sesi tidak valid.";
    } else {
        $email_login = trim($_POST['email_login'] ?? '');
        $password_login = $_POST['password_login'] ?? '';

        if (empty($email_login) || empty($password_login)) {
            $error_email_login = "Email/username dan kata sandi wajib diisi!";
        } else {
            // CEK PELANGGAN (CUSTOMER)
            $sql = "SELECT * FROM Pelanggan WHERE (LOWER(Email_Pelanggan)=LOWER(?) OR LOWER(Username_Pelanggan)=LOWER(?)) AND Is_Deleted=0 AND Status=1";
            $stmt = sqlsrv_query($conn, $sql, [$email_login, $email_login]);
            $user = ($stmt !== false) ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

            if ($user) {
                $valid = password_verify($password_login, $user['Password_Pelanggan']) || ($password_login === $user['Password_Pelanggan']);
                if ($valid) {
                    $_SESSION = array_merge($_SESSION, [
                        'status' => 'login', 'id_user' => $user['ID_Pelanggan'],
                        'email' => $user['Email_Pelanggan'], 'role' => 'Customer',
                        'nama' => $user['Nama_Pelanggan']
                    ]);
                    session_write_close();

                    // === REDIRECT LOGIC ===
                    $redirect = $_GET['redirect'] ?? '';
                    $id_paket_redirect = $_GET['id_paket'] ?? '';

                    if ($redirect == 'booking' && !empty($id_paket_redirect) && is_numeric($id_paket_redirect)) {
                        header("Location: Transaksi/booking.php?id_paket=" . (int)$id_paket_redirect);
                        exit();
                    }
                    header("Location: Role/Customer/index.php");
                    exit();
                } else {
                    $error_email_login = "Ada yang tidak sesuai nih🤷‍♂️!";
                }
            } else {
                // CEK KARYAWAN
                $sql = "SELECT * FROM Karyawan WHERE (LOWER(Email_Karyawan)=LOWER(?) OR LOWER(Username_Karyawan)=LOWER(?)) AND Is_Deleted=0 AND Status=1";
                $stmt = sqlsrv_query($conn, $sql, [$email_login, $email_login]);
                $user = ($stmt !== false) ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

                if ($user) {
                    $valid = password_verify($password_login, $user['Password_Karyawan']) || ($password_login === $user['Password_Karyawan']);
                    if ($valid) {
                        $role = $user['Role_Karyawan'];
                        $redirect = ['Admin'=>'Role/Admin/index.php', 'Owner'=>'Role/Owner/index.php', 'Fotografer'=>'Role/Fotografer/index.php'];
                        if (isset($redirect[$role])) {
                            $_SESSION = array_merge($_SESSION, [
                                'status' => 'login', 'id_user' => $user['ID_Karyawan'],
                                'email' => $user['Email_Karyawan'], 'role' => $role,
                                'nama' => $user['Nama_Karyawan']
                            ]);
                            session_write_close();
                            header("Location: " . $redirect[$role]);
                            exit();
                        }
                    } else {
                        $error_email_login = "Ada yang tidak sesuai nih🤷‍♂️!";
                    }
                } else {
                    $error_email_login = "Ada yang tidak sesuai nih🤷‍♂️!";
                }
            }
        }
    }
}

// =====================================================
// REGISTER PELANGGAN
// =====================================================
if (isset($_POST['register'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_email_reg = "Sesi tidak valid.";
    } else {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $hp_raw = trim($_POST['no_hp'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? '';
        $dob = $_POST['tanggal_lahir'] ?? '';
        $alamat = trim($_POST['alamat'] ?? '');
        $pass = $_POST['password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $panel_aktif = "ke-daftar";

        // VALIDASI NAMA
        if (empty($nama) || strlen($nama) < 3) $error_nama = "Nama min 3 karakter!";
        elseif (!preg_match("/^[a-zA-Z\s]+$/", $nama)) $error_nama = "Hanya huruf dan spasi!";

        // VALIDASI USERNAME
        if (empty($username) || strlen($username) < 5) $error_username = "Username min 5 karakter!";
        elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) $error_username = "Hanya huruf, angka, underscore!";
        elseif (strlen($username) > 50) $error_username = "Max 50 karakter!";
        else {
            $cek = sqlsrv_query($conn, "SELECT 1 FROM Pelanggan WHERE LOWER(Username_Pelanggan)=LOWER(?) AND Is_Deleted = 0", [$username]);
            if ($cek && sqlsrv_has_rows($cek)) $error_username = "Username sudah digunakan!";
        }

        // VALIDASI EMAIL (Sesuai CHK_Pelanggan_Email)
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error_email_reg = "Email tidak valid!";
        elseif (strlen($email) > 100) $error_email_reg = "Email max 100 karakter!";
        else {
            $cek = sqlsrv_query($conn, "SELECT 1 FROM Pelanggan WHERE LOWER(Email_Pelanggan)=LOWER(?) AND Is_Deleted = 0", [$email]);
            if ($cek && sqlsrv_has_rows($cek)) $error_email_reg = "Email sudah terdaftar!";
        }

        // VALIDASI NO HP (Sesuai CHK_Pelanggan_NoHp: +62 + 9-13 digit)
        $hp_digits = preg_replace('/[^0-9]/', '', $hp_raw);
        $hp_clean = '+62' . $hp_digits;
        if (empty($hp_raw)) $error_hp = "No HP wajib diisi!";
        elseif (strlen($hp_digits) < 9) $error_hp = "Min 9 digit!";
        elseif (strlen($hp_digits) > 12) $error_hp = "Max 12 digit setelah +62!";
        elseif (!preg_match("/^8[1-9][0-9]{7,11}$/", $hp_digits)) $error_hp = "Format: 81234567890";
        else {
            $cek = sqlsrv_query($conn, "SELECT 1 FROM Pelanggan WHERE No_Hp=? AND Is_Deleted = 0", [$hp_clean]);
            if ($cek && sqlsrv_has_rows($cek)) $error_hp = "No HP sudah terdaftar!";
        }

        // VALIDASI JENIS KELAMIN (Sesuai CHK_Pelanggan_JK)
        if (empty($jk) || !in_array($jk, ['Laki-laki', 'Perempuan'])) $error_jk = "Pilih jenis kelamin!";

        // VALIDASI TANGGAL LAHIR
        if (empty($dob)) $error_dob = "Tanggal lahir wajib diisi!";
        else {
            $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dob_date || $dob_date->format('Y-m-d') !== $dob) $error_dob = "Format tidak valid!";
            else {
                $age = (new DateTime())->diff($dob_date)->y;
                if ($dob_date > new DateTime()) $error_dob = "Tidak boleh masa depan!";
                elseif ($age < 13) $error_dob = "Min 13 tahun!";
                elseif ($age > 100) $error_dob = "Umur tidak valid!";
            }
        }

        // VALIDASI ALAMAT
        if (empty($alamat) || strlen($alamat) < 10) $error_alamat = "Alamat min 10 karakter!";
        elseif (strlen($alamat) > 255) $error_alamat = "Alamat max 255 karakter!";

        // VALIDASI KATA SANDI (Sesuai CHK_Pelanggan_Password)
        if (strlen($pass) < 8 || !preg_match("/[A-Za-z]/", $pass) || !preg_match("/[0-9]/", $pass) || !preg_match("/[^A-Za-z0-9]/", $pass))
            $error_pass = "Min 8: huruf+angka+simbol!";
        if ($pass !== $confirm_pass) $error_confirm_pass = "Kata sandi tidak cocok!";

        // VALIDASI & UNGGAH FOTO PROFIL
        $foto_name = isset($_POST['existing_foto_profil']) ? trim($_POST['existing_foto_profil']) : 'default.jpg';
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'])) $error_foto = "Format: JPG/JPEG/PNG!";
            elseif ($_FILES['foto_profil']['size'] > 2*1024*1024) $error_foto = "Max 2MB!";
            else {
                $foto_name = "pelanggan_" . time() . "_" . uniqid() . "." . $ext;
                $dir = "assets/img/pelanggan/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                if (extension_loaded('gd')) {
                    list($w, $h) = getimagesize($_FILES['foto_profil']['tmp_name']);
                    $thumb = imagecreatetruecolor(300, 300);
                    $src = ($ext == 'png') ? imagecreatefrompng($_FILES['foto_profil']['tmp_name']) : imagecreatefromjpeg($_FILES['foto_profil']['tmp_name']);
                    imagecopyresampled($thumb, $src, 0, 0, 0, 0, 300, 300, $w, $h);
                    ($ext == 'png') ? imagepng($thumb, $dir . $foto_name, 8) : imagejpeg($thumb, $dir . $foto_name, 85);
                    imagedestroy($thumb); imagedestroy($src);
                } else {
                    move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dir . $foto_name);
                }
                $foto_profil = $foto_name;
            }
        }

        // INSERT KE DATABASE MENGGUNAKAN STORED PROCEDURE (sp_InsertPelanggan)
        if (empty($error_nama) && empty($error_username) && empty($error_email_reg) && empty($error_hp) &&
            empty($error_jk) && empty($error_dob) && empty($error_alamat) && empty($error_pass) &&
            empty($error_confirm_pass) && empty($error_foto)) {

            // Enkripsi kata sandi (Akan menghasilkan hash 60 karakter yang memenuhi CHK_Pelanggan_Password)
            $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
            
            // Format panggilan Stored Procedure SQL Server
            $sql = "{CALL sp_InsertPelanggan(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
            $params = [$nama, $username, $email, $pass_hash, $jk, $dob, $hp_clean, $alamat, $foto_name, 'system'];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                $success_register = true;
                $registered_email = $email;
                $registered_password = $pass;

                // === AUTO-LOGIN SETELAH REGISTER + REDIRECT ===
                $sql_new = "SELECT * FROM Pelanggan WHERE Email_Pelanggan = ? AND Is_Deleted = 0 AND Status = 1";
                $stmt_new = sqlsrv_query($conn, $sql_new, [$email]);
                $user_new = ($stmt_new !== false) ? sqlsrv_fetch_array($stmt_new, SQLSRV_FETCH_ASSOC) : null;

                if ($user_new) {
                    $_SESSION = array_merge($_SESSION, [
                        'status' => 'login',
                        'id_user' => $user_new['ID_Pelanggan'],
                        'email' => $user_new['Email_Pelanggan'],
                        'role' => 'Customer',
                        'nama' => $user_new['Nama_Pelanggan']
                    ]);

                    $redirect_reg = $_GET['redirect'] ?? '';
                    $id_paket_reg = $_GET['id_paket'] ?? '';

                    if ($redirect_reg == 'booking' && !empty($id_paket_reg) && is_numeric($id_paket_reg)) {
                        header("Location: Transaksi/booking.php?id_paket=" . (int)$id_paket_reg);
                        exit();
                    }
                }
            } else {
                $err = sqlsrv_errors();
                $error_email_reg = "Gagal mendaftarkan akun: " . ($err[0]['message'] ?? 'Kesalahan tidak diketahui');
            }
        }
    }
}

// Collect errors for display
$all_errors = [];
foreach (['nama'=>$error_nama, 'username'=>$error_username, 'email'=>$error_email_reg, 'hp'=>$error_hp,
          'jk'=>$error_jk, 'dob'=>$error_dob, 'alamat'=>$error_alamat, 'pass'=>$error_pass, 
          'confirm'=>$error_confirm_pass, 'foto'=>$error_foto] as $key => $val) {
    if ($val) $all_errors[] = ucfirst($key) . ": " . $val;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pintu Masuk & Daftar Akun - SpotLight Studio</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --glass: rgba(255, 255, 255, 0.96);
            --transition-soft: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #d83f67 100%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        /* Floating particles background */
        .particles {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            width: 10px; height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Main Card */
        .main-card {
            position: relative;
            width: 100%;
            max-width: 1100px;
            min-height: 700px;
            background: var(--glass);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            z-index: 10;
            display: flex;
            transition: var(--transition-soft);
        }

        /* Left Panel - Visual */
        .visual-panel {
            width: 45%;
            background: linear-gradient(180deg, #d83f67 0%, #c73165 50%, #1a1a2e 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 50px;
            color: white;
            transition: var(--transition-soft);
        }

        .visual-panel::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: pulse 4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .visual-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .visual-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
            animation: fadeInUp 0.8s ease-out;
        }

        .visual-title {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .visual-text {
            font-size: 1rem;
            line-height: 1.8;
            opacity: 0.9;
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .visual-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition-soft);
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }
        .visual-btn:hover {
            background: white;
            color: var(--p-pink);
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        /* Floating icons */
        .float-icon {
            position: absolute;
            font-size: 2rem;
            opacity: 0.15;
            animation: floatIcon 6s ease-in-out infinite;
        }
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        /* Right Panel - Forms */
        .forms-panel {
            width: 55%;
            position: relative;
            overflow: hidden;
        }

        .form-container {
            position: relative;
            width: 100%;
            min-height: 100%;
            padding: 50px;
            overflow-y: auto;
            transition: var(--transition-soft);
            opacity: 0;
            transform: translateX(50px);
            pointer-events: none;
            display: none;
        }

        .form-container.active {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
            display: block;
        }

        .form-container.hidden {
            opacity: 0;
            transform: translateX(-50px);
            pointer-events: none;
            display: none;
        }

        /* Logo */
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }
        .logo-icon {
            width: 50px; height: 50px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
        }
        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .logo-text span { color: var(--p-pink); }

        /* Title */
        .form-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .form-subtitle {
            color: #8a99a8;
            font-size: 0.95rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        /* Input Groups */
        .input-group-custom {
            margin-bottom: 20px;
        }
        .input-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #8a99a8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .input-label .required { color: #ef4444; margin-left: 2px; }

        .input-field {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #eef2f6;
            border-radius: 16px;
            background: #f8fafc;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-dark);
            transition: var(--transition-soft);
            outline: none;
        }
        .input-field:focus {
            border-color: var(--p-pink);
            background: white;
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.1), 0 8px 25px rgba(216, 63, 103, 0.1);
            transform: translateY(-2px);
        }
        .input-field.is-invalid {
            border-color: #ef4444;
            background: #fff1f2;
        }
        .input-field::placeholder { color: #cbd5e1; }

        /* Password Group */
        .password-wrap {
            position: relative;
        }
        .password-wrap .input-field {
            padding-right: 50px;
        }
        .toggle-eye {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 1.2rem;
            transition: 0.3s;
        }
        .toggle-eye:hover { color: var(--p-pink); }

        /* Radio Buttons */
        .radio-group-fix {
            display: flex;
            gap: 10px;
            width: 100%;
        }
        .radio-fix {
            flex: 1;
            cursor: pointer;
            margin: 0;
        }
        .radio-fix input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .radio-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 8px;
            background: #f8fafc;
            border: 2px solid #eef2f6;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            transition: var(--transition-soft);
            text-align: center;
            min-height: 42px;
        }
        .radio-box .radio-icon {
            font-size: 1rem;
            flex-shrink: 0;
        }
        .radio-box .radio-text {
            white-space: nowrap;
        }
        .radio-fix:hover .radio-box {
            border-color: #ffd1dc;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.08);
        }
        .radio-fix input:checked + .radio-box {
            background: linear-gradient(135deg, #fff5f6, #ffe4e9);
            border-color: var(--p-pink);
            color: var(--p-pink);
            box-shadow: 0 6px 20px rgba(216, 63, 103, 0.12);
            transform: translateY(-1px);
        }
        .radio-fix input:checked + .radio-box .radio-icon {
            color: var(--p-pink);
        }

        /* File Upload */
        .upload-area {
            border: 2px dashed #eef2f6;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            transition: var(--transition-soft);
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: var(--p-pink);
            background: #fff5f6;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.1);
        }
        .upload-preview {
            width: 80px; height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .upload-text {
            font-size: 0.85rem;
            color: #8a99a8;
            font-weight: 500;
        }
        .upload-text span { color: var(--p-pink); font-weight: 700; }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-soft);
            box-shadow: 0 10px 30px rgba(216, 63, 103, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-submit:hover::after {
            left: 100%;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(216, 63, 103, 0.4);
        }
        .btn-submit:active {
            transform: translateY(-1px);
        }

        /* Loading spinner */
        .btn-submit.loading {
            pointer-events: none;
        }
        .btn-submit.loading .btn-text {
            opacity: 0;
        }
        .btn-submit.loading::before {
            content: '';
            position: absolute;
            width: 24px; height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Link styles */
        .link-pink {
            color: var(--p-pink);
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            border-bottom: 2px solid transparent;
        }
        .link-pink:hover {
            border-bottom-color: var(--p-pink);
        }

        /* Error text */
        .error-msg {
            color: #ef4444;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Back to home */
        .btn-home {
            position: absolute;
            top: 25px;
            right: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #eef2f6;
            border-radius: 50px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-soft);
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .btn-home:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.15);
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scrollbar */
        .form-container::-webkit-scrollbar {
            width: 6px;
        }
        .form-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .form-container::-webkit-scrollbar-thumb {
            background: #eef2f6;
            border-radius: 10px;
        }
        .form-container::-webkit-scrollbar-thumb:hover {
            background: var(--p-pink);
        }
    </style>
</head>
<body>

    <!-- Particles Background -->
    <div class="particles" id="particles"></div>

    <!-- Main Card -->
    <div class="main-card" id="mainCard">

        <!-- Visual Panel (Left) -->
        <div class="visual-panel" id="visualPanel">
            <div class="float-icon" style="top: 10%; left: 15%; animation-delay: 0s;">📸</div>
            <div class="float-icon" style="top: 20%; right: 20%; animation-delay: 1s;">✨</div>
            <div class="float-icon" style="bottom: 25%; left: 20%; animation-delay: 2s;">🎨</div>
            <div class="float-icon" style="bottom: 15%; right: 15%; animation-delay: 3s;">💖</div>

            <div class="visual-content" id="visualContent">
                <div class="visual-badge" id="visualBadge">
                    <i class="bi bi-stars"></i> Studio Terpopuler di Cikarang
                </div>
                <h1 class="visual-title" id="visualTitle">
                    Yuk, Bergabung<br>Bersama Kami! 🌟
                </h1>
                <p class="visual-text" id="visualText">
                    Daftar sekarang dan nikmati kemudahan booking studio, pilih tema foto estetik favorit, serta pantau jadwal Anda dengan praktis! ✨📸
                </p>
                <button class="visual-btn" id="visualBtn" onclick="switchPanel()">
                    <i class="bi bi-person-plus" id="btnIcon"></i>
                    <span id="btnText">Daftar Baru</span>
                </button>
            </div>
        </div>

        <!-- Forms Panel (Right) -->
        <div class="forms-panel">
            <a href="index.php" class="btn-home">
                <i class="bi bi-house-door"></i> Beranda
            </a>

            <!-- LOGIN FORM -->
            <div class="form-container active" id="loginForm">
                <div class="logo-area">
                    <div class="logo-icon"><i class="bi bi-camera-fill"></i></div>
                    <div class="logo-text">Spot<span>Light</span></div>
                </div>

                <h2 class="form-title">Halo Kak, Selamat Datang! 👋✨</h2>
                <p class="form-subtitle">Masukkan akun terdaftar Anda untuk melanjutkan kisah indah bersama SpotLight Studio! 📸💖</p>

                <form method="POST" id="formLogin" action="login.php<?= !empty($redirect_query) ? '?' . ltrim($redirect_query, '&') : '' ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="input-group-custom">
                        <label class="input-label">Email / Nama Pengguna <span class="required">*</span></label>
                        <input type="text" name="email_login" class="input-field" placeholder="nama@email.com atau username" value="<?= htmlspecialchars(@$_POST['email_login'] ?? '') ?>" required>
                    </div>

                    <div class="input-group-custom">
                        <label class="input-label">Kata Sandi <span class="required">*</span></label>
                        <div class="password-wrap">
                            <input type="password" name="password_login" id="password_login" class="input-field" placeholder="Masukkan kata sandi" required>
                            <i class="bi bi-eye-slash toggle-eye" onclick="togglePassword('password_login', this)"></i>
                        </div>
                    </div>

                    <div style="text-align: right; margin-bottom: 25px;">
                        <a href="lupa_password.php" class="link-pink">Lupa kata sandi? 🔐</a>
                    </div>

                    <button type="submit" name="login" class="btn-submit" id="btnLogin" onclick="showLoading(this)">
                        <span class="btn-text">Masuk Sekarang <i class="bi bi-arrow-right"></i></span>
                    </button>
                </form>
            </div>

            <!-- REGISTER FORM -->
            <div class="form-container hidden" id="registerForm">
                <div class="logo-area">
                    <div class="logo-icon"><i class="bi bi-camera-fill"></i></div>
                    <div class="logo-text">Spot<span>Light</span></div>
                </div>

                <h2 class="form-title">Mari Mulai Kisah Indah! 🌟📷</h2>
                <p class="form-subtitle">Ciptakan akun dalam sekejap untuk akses penuh ke seluruh layanan studio terbaik kami! ✨🎈</p>

                <form method="POST" enctype="multipart/form-data" id="formRegister" action="login.php?aksi=daftar<?= $redirect_query ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="existing_foto_profil" id="existingFoto" value="<?= htmlspecialchars($foto_profil ?? 'default.jpg') ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama" id="inputNama" class="input-field <?= $error_nama ? 'is-invalid' : '' ?>" placeholder="Nama Lengkap Anda" value="<?= htmlspecialchars(@$_POST['nama'] ?? '') ?>" required>
                                <?php if($error_nama): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Username <span class="required">*</span></label>
                                <input type="text" name="username" id="inputUsername" class="input-field <?= $error_username ? 'is-invalid' : '' ?>" placeholder="huruf_angka_123" value="<?= htmlspecialchars(@$_POST['username'] ?? '') ?>" required>
                                <?php if($error_username): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_username ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="input-field <?= $error_email_reg ? 'is-invalid' : '' ?>" placeholder="nama@email.com" value="<?= htmlspecialchars(@$_POST['email'] ?? '') ?>" required>
                                <?php if($error_email_reg): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_email_reg ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">No. Telepon <span class="required">*</span></label>
                                <div class="d-flex align-items-stretch" style="border: 2px solid #eef2f6; border-radius: 16px; overflow: hidden; background: #f8fafc; transition: all 0.3s ease;">
                                    <span style="display: flex; align-items: center; padding: 14px 16px; font-weight: 700; color: #d83f67; font-size: 0.9rem; border-right: 2px solid #eef2f6; background: #f8fafc; white-space: nowrap;">+62</span>
                                    <input type="text" name="no_hp" id="inputHP" class="flex-grow-1" style="border: none; background: transparent; padding: 14px 18px; font-size: 0.95rem; font-weight: 500; color: #1e1e24; outline: none; min-width: 0;" placeholder="81234567890" value="<?= htmlspecialchars(@$_POST['no_hp'] ?? '') ?>" required>
                                </div>
                                <?php if($error_hp): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                                <small style="color: #94a3b8; font-size: 0.75rem; display: block; margin-top: 6px;">Format: 81234567890 (tanpa angka 0 di depan) 📱</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Jenis Kelamin <span class="required">*</span></label>
                                <div class="radio-group-fix">
                                    <label class="radio-fix" for="jk_laki">
                                        <input type="radio" name="jenis_kelamin" id="jk_laki" value="Laki-laki" <?= (@$_POST['jenis_kelamin'] == 'Laki-laki') ? 'checked' : '' ?> required>
                                        <span class="radio-box">
                                            <i class="bi bi-gender-male radio-icon"></i>
                                            <span class="radio-text">Laki-laki</span>
                                        </span>
                                    </label>
                                    <label class="radio-fix" for="jk_perempuan">
                                        <input type="radio" name="jenis_kelamin" id="jk_perempuan" value="Perempuan" <?= (@$_POST['jenis_kelamin'] == 'Perempuan') ? 'checked' : '' ?> required>
                                        <span class="radio-box">
                                            <i class="bi bi-gender-female radio-icon"></i>
                                            <span class="radio-text">Perempuan</span>
                                        </span>
                                    </label>
                                </div>
                                <?php if($error_jk): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_jk ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Tanggal Lahir <span class="required">*</span></label>
                                <div class="date-wrap">
                                    <input type="date" name="tanggal_lahir" id="inputDOB" class="input-field <?= $error_dob ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars(@$_POST['tanggal_lahir'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-13 years')) ?>" required>
                                    <i class="bi bi-calendar-event date-icon"></i>
                                </div>
                                <?php if($error_dob): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_dob ?></div><?php endif; ?>
                                <small style="color: #94a3b8; font-size: 0.75rem; display: block; margin-top: 6px;">Format: dd/mm/yyyy • Minimal 13 tahun ya Kak! 🎂</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Kata Sandi <span class="required">*</span></label>
                                <div class="password-wrap">
                                    <input type="password" name="password" id="password" class="input-field <?= $error_pass ? 'is-invalid' : '' ?>" placeholder="Min 8: huruf+angka+simbol" required>
                                    <i class="bi bi-eye-slash toggle-eye" onclick="togglePassword('password', this)"></i>
                                </div>
                                <?php if($error_pass): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_pass ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom">
                                <label class="input-label">Verifikasi Sandi <span class="required">*</span></label>
                                <div class="password-wrap">
                                    <input type="password" name="confirm_password" id="confirm_password" class="input-field <?= $error_confirm_pass ? 'is-invalid' : '' ?>" placeholder="Ulangi kata sandi" required>
                                    <i class="bi bi-eye-slash toggle-eye" onclick="togglePassword('confirm_password', this)"></i>
                                </div>
                                <?php if($error_confirm_pass): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_confirm_pass ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="input-group-custom">
                        <label class="input-label">Alamat Lengkap <span class="required">*</span></label>
                        <input type="text" name="alamat" class="input-field <?= $error_alamat ? 'is-invalid' : '' ?>" placeholder="Alamat lengkap domisili (min 10 karakter)" value="<?= htmlspecialchars(@$_POST['alamat'] ?? '') ?>" required>
                        <?php if($error_alamat): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="input-group-custom">
                                <label class="input-label">Foto Profil (Opsional)</label>
                                <div class="upload-area" onclick="document.getElementById('inputFoto').click()">
                                    <?php
                                    $default_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
                                    ?>
                                    <img id="previewFoto" src="<?= (isset($foto_profil) && $foto_profil != 'default.jpg') ? 'assets/img/pelanggan/' . htmlspecialchars($foto_profil) : $default_svg ?>" class="upload-preview" alt="Preview" onerror="this.src='<?= $default_svg ?>'">
                                    <div class="upload-text">
                                        <span>Klik untuk upload</span><br>
                                        JPG, JPEG, PNG (Max 2MB)
                                    </div>
                                </div>
                                <input type="file" name="foto_profil" id="inputFoto" class="d-none" accept=".jpg,.jpeg,.png" onchange="previewImage(this)">
                                <?php if($error_foto): ?><div class="error-msg"><i class="bi bi-x-circle-fill"></i> <?= $error_foto ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn-submit" id="btnRegister" onclick="showLoading(this)">
                        <span class="btn-text">Daftar Akun Sekarang ✨</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create particles
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (10 + Math.random() * 10) + 's';
            particle.style.width = particle.style.height = (5 + Math.random() * 15) + 'px';
            particlesContainer.appendChild(particle);
        }

        // Panel switch
        let isRegister = false;
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const visualTitle = document.getElementById('visualTitle');
        const visualText = document.getElementById('visualText');
        const visualBtn = document.getElementById('visualBtn');
        const btnText = document.getElementById('btnText');
        const visualBadge = document.getElementById('visualBadge');

        const visualData = {
            login: {
                badge: '<i class="bi bi-stars"></i> Studio Terpopuler di Cikarang',
                title: 'Yuk, Bergabung<br>Bersama Kami! 🌟',
                text: 'Daftar sekarang dan nikmati kemudahan booking studio, pilih tema foto estetik favorit, serta pantau jadwal Anda dengan praktis! ✨📸',
                btnIcon: 'bi-person-plus',
                btnText: 'Daftar Baru'
            },
            register: {
                badge: '<i class="bi bi-camera-fill"></i> SPOTLIGHT STUDIO FOTO',
                title: 'Pintu Masuk<br>Kehangatan Studio! 👋💖',
                text: 'Selamat datang kembali, Sahabat SpotLight! Masuk untuk mengelola pesanan, pelunasan, atau download hasil jepretan terbaik Anda! 📸🎈',
                btnIcon: 'bi-box-arrow-in-right',
                btnText: 'Masuk Akun'
            }
        };

        function switchPanel(forceToLogin = false) {
            if (forceToLogin) {
                isRegister = true;
            }

            isRegister = !isRegister;
            const data = isRegister ? visualData.register : visualData.login;

            visualBadge.style.opacity = '0';
            visualTitle.style.opacity = '0';
            visualText.style.opacity = '0';
            visualBtn.style.opacity = '0';

            setTimeout(() => {
                visualBadge.innerHTML = data.badge;
                visualTitle.innerHTML = data.title;
                visualText.innerHTML = data.text;
                const icon = document.getElementById('btnIcon');
                const text = document.getElementById('btnText');
                if (icon) icon.className = `bi ${data.btnIcon}`;
                if (text) text.textContent = data.btnText;

                visualBadge.style.opacity = '1';
                visualTitle.style.opacity = '1';
                visualText.style.opacity = '1';
                visualBtn.style.opacity = '1';
            }, 300);

            if (isRegister) {
                loginForm.classList.remove('active');
                loginForm.classList.add('hidden');
                setTimeout(() => {
                    registerForm.classList.remove('hidden');
                    registerForm.classList.add('active');
                }, 300);
            } else {
                registerForm.classList.remove('active');
                registerForm.classList.add('hidden');
                setTimeout(() => {
                    loginForm.classList.remove('hidden');
                    loginForm.classList.add('active');
                }, 300);
            }
        }

        <?php if($panel_aktif == "ke-daftar"): ?>
        switchPanel();
        <?php endif; ?>

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewFoto').src = e.target.result;
                    document.getElementById('existingFoto').value = 'new_upload';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.getElementById('inputNama')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        });

        document.getElementById('inputUsername')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });

        document.getElementById('inputHP')?.addEventListener('input', function() {
            let val = this.value.replace(/[^0-9]/g, '').substring(0, 13);
            if (val.startsWith('0')) {
                val = val.substring(1);
                this.parentElement.style.borderColor = '#f59e0b';
                setTimeout(() => { this.parentElement.style.borderColor = '#eef2f6'; }, 800);
            }
            this.value = val;
        });

        function showLoading(btn) {
            btn.classList.add('loading');
            setTimeout(() => {
                btn.classList.remove('loading');
            }, 3000);
        }

        <?php if($success_register && !isset($_SESSION['status'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Yeay, Akun Berhasil Dibuat! 🎉✨',
            html: 'Selamat datang di keluarga SpotLight!<br>Akan otomatis ke halaman masuk dalam <b>3</b> detik... 📸💖',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Masuk Sekarang 🚀',
            backdrop: 'rgba(216, 63, 103, 0.2)',
            timer: 3000,
            timerProgressBar: true,
            allowOutsideClick: false,
            willClose: () => {
                if (!isRegister) {
                    switchPanel();
                }
            }
        }).then((result) => {
            const emailField = document.querySelector('input[name="email_login"]');
            const passField = document.querySelector('input[name="password_login"]');
            if (emailField) emailField.value = '<?= htmlspecialchars($registered_email ?? '') ?>';
            if (passField) passField.value = '<?= htmlspecialchars($registered_password ?? '') ?>';
            switchPanel(true);
        });
        <?php endif; ?>

        <?php if(!empty($all_errors)): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Ups, Ada yang Perlu Dicek! 😅',
            html: '<div style="text-align:left; font-size:0.9rem;">' +
                  '<?php foreach($all_errors as $err): ?>' +
                  '<div style="margin-bottom:8px; display:flex; align-items:start; gap:8px;">' +
                  '<span style="color:#ef4444; font-size:1.1rem;">⚠️</span>' +
                  '<span><?= addslashes($err) ?></span>' +
                  '</div>' +
                  '<?php endforeach; ?>' +
                  '</div>' +
                  '<div style="margin-top:15px; padding-top:15px; border-top:1px solid #eee; color:#8a99a8; font-size:0.85rem;">' +
                  '💡 <b>Tips:</b> Pastikan semua data terisi dengan benar ya Kak!' +
                  '</div>',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Oke, Dicek Lagi! 🔍',
            backdrop: 'rgba(216, 63, 103, 0.2)',
            width: '480px'
        });
        <?php endif; ?>

        <?php if($error_email_login): ?>
        Swal.fire({
            icon: 'error',
            title: 'Ups, Gagal Masuk! 😢',
            text: '<?= addslashes($error_email_login) ?>',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Coba Lagi 💪',
            backdrop: 'rgba(216, 63, 103, 0.2)'
        });
        <?php endif; ?>
    </script>
</body>  
</html>