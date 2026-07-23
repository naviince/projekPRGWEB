<?php
session_start();
include '../../koneksi.php';

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_query($conn, $sql, $params = array()) {
    $query = sqlsrv_query($conn, $sql, $params);
    if ($query === false) { error_log("SQLSRV Error: " . print_r(sqlsrv_errors(), true)); return false; }
    return $query;
}
function safe_sqlsrv_fetch($query) { if (!$query) return false; return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC); }
function safe_sqlsrv_count($conn, $sql, $params = array()) {
    $query = safe_sqlsrv_query($conn, $sql, $params);
    if (!$query) return 0; $row = safe_sqlsrv_fetch($query); return $row ? ($row['total'] ?? 0) : 0;
}

// =====================================================
// PROTEKSI KEAMANAN HAK AKSES
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php"); exit();
}
$id_owner = $_SESSION['id_user'];
$username_session = $_SESSION['username'] ?? 'system';

// Ambil Profil Owner untuk Header
$q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = safe_sqlsrv_fetch($q_profile);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

if ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/karyawan/" . $foto_owner;
} elseif ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/pelanggan/" . $foto_owner;
} else {
    $foto_owner_src = $default_svg_avatar;
}

// =====================================================
// AMBIL DATA KARYAWAN YANG AKAN DI-EDIT
// =====================================================
if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }
$id_edit = (int)$_GET['id'];
if ($id_edit <= 0) { header("Location: index.php"); exit(); }

$sql_fetch = "SELECT * FROM Karyawan WHERE ID_Karyawan = ?";
$stmt_fetch = safe_sqlsrv_query($conn, $sql_fetch, array($id_edit));
$data_karyawan = safe_sqlsrv_fetch($stmt_fetch);

if (!$data_karyawan) { header("Location: index.php"); exit(); }

if (is_object($data_karyawan['Tanggal_Lahir'])) {
    $data_karyawan['Tanggal_Lahir'] = $data_karyawan['Tanggal_Lahir']->format('Y-m-d');
}

// =====================================================
// FUNGSI VALIDASI EDIT KARYAWAN
// =====================================================
function validateEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false && strpos($email, '.') !== false; }
function validatePassword($pass) { return strlen($pass) >= 8 && preg_match('/[A-Za-z]/', $pass) && preg_match('/[0-9]/', $pass) && preg_match('/[^A-Za-z0-9]/', $pass); }
function validatePhone($hp) { return preg_match('/^\+62[0-9]{9,13}$/', $hp); }
function validateNIK($nik) { return preg_match('/^[0-9]{16}$/', $nik); }
function validateUsername($username) { return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username); }
function validateTanggal($tgl) {
    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    return $d && $d->format('Y-m-d') === $tgl && $d <= new DateTime('today') && $d >= new DateTime('1950-01-01');
}
function hitungUmur($tanggal_lahir) { $birthDate = new DateTime($tanggal_lahir); $today = new DateTime('today'); return $birthDate->diff($today)->y; }
function cekDuplikatEdit($conn, $field, $value, $exclude_id) { return safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE $field = ? AND Is_Deleted = 0 AND ID_Karyawan != ?", array($value, $exclude_id)); }
function sanitizeInput($input) { return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'); }
function generateCsrfToken() { if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf_token']; }
function verifyCsrfToken($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }
function hasError($field, $error_fields) { return isset($error_fields[$field]) && $error_fields[$field]; }

// =====================================================
// PROSES PENYIMPANAN PERUBAHAN
// =====================================================
$errors = array();
$error_fields = array();

if (isset($_POST['edit_karyawan'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['global'] = "Token keamanan tidak valid! Silakan refresh halaman.";
    } else {
        $nik = sanitizeInput($_POST['nik'] ?? ''); $nama = sanitizeInput($_POST['nama'] ?? ''); $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? ''); $pass = $_POST['password'] ?? ''; $pass_confirm = $_POST['password_confirm'] ?? '';
        $jk = $_POST['jenis_kelamin'] ?? ''; $dob = trim($_POST['tanggal_lahir'] ?? ''); $role = $_POST['role_karyawan'] ?? '';
        $status = (int)($_POST['status_karyawan'] ?? 1); $hp_raw = trim($_POST['no_hp'] ?? ''); $hp = '+62' . $hp_raw; $alamat = sanitizeInput($_POST['alamat'] ?? '');
        $umur = !empty($dob) && validateTanggal($dob) ? hitungUmur($dob) : 0;

        if ($id_edit === $id_owner) {
            if ($role !== 'Owner') { $errors['role_karyawan'] = "Anda tidak dapat menurunkan peran (role) Owner Anda sendiri!"; $error_fields['role_karyawan'] = true; }
            if ($status !== 1) { $errors['status_karyawan'] = "Anda tidak dapat menonaktifkan akun Owner Anda sendiri!"; $error_fields['status_karyawan'] = true; }
        }

        if (empty($nik)) { $errors['nik'] = "NIK wajib diisi!"; $error_fields['nik'] = true; }
        elseif (!validateNIK($nik)) { $errors['nik'] = "NIK harus berisi tepat 16 digit angka murni!"; $error_fields['nik'] = true; }

        if (empty($nama)) { $errors['nama'] = "Nama wajib diisi!"; $error_fields['nama'] = true; }
        elseif (strlen($nama) < 3) { $errors['nama'] = "Nama minimal 3 karakter huruf!"; $error_fields['nama'] = true; }

        if (empty($username)) { $errors['username'] = "Username wajib diisi!"; $error_fields['username'] = true; }
        elseif (!validateUsername($username)) { $errors['username'] = "Username hanya berupa huruf, angka, dan underscore (3-50 karakter)!"; $error_fields['username'] = true; }

        if (empty($email)) { $errors['email'] = "Email wajib diisi!"; $error_fields['email'] = true; }
        elseif (!validateEmail($email)) { $errors['email'] = "Format alamat email tidak valid!"; $error_fields['email'] = true; }

        if (!empty($pass)) {
            if (!validatePassword($pass)) { $errors['password'] = "Sandi baru harus minimal 8 karakter dengan kombinasi huruf, angka, dan simbol!"; $error_fields['password'] = true; }
            elseif ($pass !== $pass_confirm) { $errors['password_confirm'] = "Konfirmasi sandi baru tidak sesuai dengan sandi baru utama!"; $error_fields['password_confirm'] = true; }
        }

        if (empty($hp_raw)) { $errors['no_hp'] = "Nomor telepon wajib diisi!"; $error_fields['no_hp'] = true; }
        elseif (!preg_match('/^[0-9]{9,13}$/', $hp_raw)) { $errors['no_hp'] = "No HP harus berisi 9-13 digit angka murni!"; $error_fields['no_hp'] = true; }
        elseif (!validatePhone($hp)) { $errors['no_hp'] = "Format No HP tidak valid!"; $error_fields['no_hp'] = true; }

        if (empty($dob)) { $errors['tanggal_lahir'] = "Tanggal lahir wajib diisi!"; $error_fields['tanggal_lahir'] = true; }
        elseif (!validateTanggal($dob)) { $errors['tanggal_lahir'] = "Tanggal lahir tidak valid!"; $error_fields['tanggal_lahir'] = true; }
        elseif ($umur < 17) { $errors['tanggal_lahir'] = "Umur minimal mendaftar adalah 17 tahun! (Saat ini: $umur tahun)"; $error_fields['tanggal_lahir'] = true; }
        elseif ($umur > 60) { $errors['tanggal_lahir'] = "Umur maksimal mendaftar adalah 60 tahun! (Saat ini: $umur tahun)"; $error_fields['tanggal_lahir'] = true; }

        if (empty($jk)) { $errors['jenis_kelamin'] = "Jenis kelamin wajib dipilih!"; $error_fields['jenis_kelamin'] = true; }
        if (empty($role)) { $errors['role_karyawan'] = "Peran kerja wajib dipilih!"; $error_fields['role_karyawan'] = true; }
        if (empty($alamat)) { $errors['alamat'] = "Alamat domisili wajib diisi!"; $error_fields['alamat'] = true; }

        if (empty($errors)) {
            if (cekDuplikatEdit($conn, 'NIK', $nik, $id_edit) > 0) { $errors['nik'] = "NIK sudah terdaftar oleh staf lain!"; $error_fields['nik'] = true; }
            if (safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE LOWER(Username_Karyawan) = LOWER(?) AND Is_Deleted = 0 AND ID_Karyawan != ?", array($username, $id_edit)) > 0) { $errors['username'] = "Username sudah digunakan oleh staf lain!"; $error_fields['username'] = true; }
            if (safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE LOWER(Email_Karyawan) = LOWER(?) AND Is_Deleted = 0 AND ID_Karyawan != ?", array($email, $id_edit)) > 0) { $errors['email'] = "Email sudah terdaftar oleh staf lain!"; $error_fields['email'] = true; }
            if (cekDuplikatEdit($conn, 'No_Hp', $hp, $id_edit) > 0) { $errors['no_hp'] = "Nomor telepon sudah terdaftar oleh staf lain!"; $error_fields['no_hp'] = true; }
        }

        if (empty($errors) && $role === 'Owner' && $data_karyawan['Role_Karyawan'] != 'Owner') {
            $owner_count = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_edit));
            if ($owner_count > 0) { $errors['role_karyawan'] = "Sistem menolak! Hanya boleh ada 1 Owner aktif di sistem."; $error_fields['role_karyawan'] = true; }
        }

        $foto_profil = $data_karyawan['Foto_Profil'] ?? 'default.jpg';
        if (empty($errors) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['foto_profil']['tmp_name']; $file_size = $_FILES['foto_profil']['size']; $file_name = $_FILES['foto_profil']['name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime_type = finfo_file($finfo, $file_tmp); finfo_close($finfo);
            $allowed = array('image/jpeg','image/png','image/jpg');
            if (!in_array($mime_type, $allowed)) { $errors['global'] = "Format foto harus JPG, JPEG, atau PNG!"; }
            elseif ($file_size > 2 * 1024 * 1024) { $errors['global'] = "Ukuran foto maksimal 2MB!"; }
            else {
                $img_info = getimagesize($file_tmp);
                if (!$img_info) { $errors['global'] = "Berkas gambar rusak atau tidak valid!"; }
                else {
                    $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $foto_profil = 'karyawan_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $upload_path = '../../assets/img/karyawan/' . $foto_profil;
                    if (!file_exists('../../assets/img/karyawan/')) mkdir('../../assets/img/karyawan/', 0777, true);

                    $old_foto = $data_karyawan['Foto_Profil'] ?? 'default.jpg';
                    if ($old_foto != 'default.jpg' && file_exists('../../assets/img/karyawan/' . $old_foto)) {
                        @unlink('../../assets/img/karyawan/' . $old_foto);
                    }

                    if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
                        list($width, $height) = $img_info; $new_width = 300; $new_height = 300;
                        $thumb = imagecreatetruecolor($new_width, $new_height);
                        if ($mime_type == 'image/png') { $source = imagecreatefrompng($file_tmp); imagealphablending($thumb, false); imagesavealpha($thumb, true); }
                        else $source = imagecreatefromjpeg($file_tmp);
                        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        if ($mime_type == 'image/png') imagepng($thumb, $upload_path, 8); else imagejpeg($thumb, $upload_path, 85);
                        imagedestroy($thumb); imagedestroy($source);
                    } else {
                        if (!move_uploaded_file($file_tmp, $upload_path)) {
                            $errors['global'] = "Gagal mengupload foto!";
                            $foto_profil = $data_karyawan['Foto_Profil'] ?? 'default.jpg';
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            if (!empty($pass)) {
                $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
                $sql_upd = "UPDATE Karyawan SET NIK = ?, Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Role_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $params = array($nik, $nama, $username, $email, $pass_hash, $jk, $dob, $role, $hp, $alamat, $foto_profil, $status, $username_session, $id_edit);
            } else {
                $sql_upd = "UPDATE Karyawan SET NIK = ?, Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Role_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $params = array($nik, $nama, $username, $email, $jk, $dob, $role, $hp, $alamat, $foto_profil, $status, $username_session, $id_edit);
            }

            $stmt_upd = safe_sqlsrv_query($conn, $sql_upd, $params);
            if ($stmt_upd) { header("Location: index.php?status_sukses=edit"); exit(); }
            else { $errors['global'] = "Gagal memperbarui data di database!"; }
        }
    }
}
$csrf_token = generateCsrfToken();

// Data Binding untuk Formulir
$current_nik = htmlspecialchars($_POST['nik'] ?? $data_karyawan['NIK'] ?? '');
$current_nama = htmlspecialchars($_POST['nama'] ?? $data_karyawan['Nama_Karyawan'] ?? '');
$current_username = htmlspecialchars($_POST['username'] ?? $data_karyawan['Username_Karyawan'] ?? '');
$current_email = htmlspecialchars($_POST['email'] ?? $data_karyawan['Email_Karyawan'] ?? '');
$current_jk = $_POST['jenis_kelamin'] ?? $data_karyawan['Jenis_Kelamin'] ?? '';
$current_dob = htmlspecialchars($_POST['tanggal_lahir'] ?? $data_karyawan['Tanggal_Lahir'] ?? '');
$current_role = $_POST['role_karyawan'] ?? $data_karyawan['Role_Karyawan'] ?? '';
$current_status = (int)($_POST['status_karyawan'] ?? $data_karyawan['Status'] ?? 1);
$current_hp = htmlspecialchars($_POST['no_hp'] ?? '');
if (empty($current_hp) && isset($data_karyawan['No_Hp'])) {
    $current_hp = preg_replace('/^\+62/', '', $data_karyawan['No_Hp']);
}
$current_alamat = htmlspecialchars($_POST['alamat'] ?? $data_karyawan['Alamat'] ?? '');
$current_foto = $data_karyawan['Foto_Profil'] ?? 'default.jpg';

if ($current_foto != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $current_foto)) {
    $foto_src = "../../assets/img/karyawan/" . $current_foto;
} elseif ($current_foto != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $current_foto)) {
    $foto_src = "../../assets/img/pelanggan/" . $current_foto;
} else {
    $foto_src = $default_svg_avatar;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Karyawan – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
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
            --border-color: #f1f5f9;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * { -webkit-tap-highlight-color: transparent; }
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
            border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 1040;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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

        /* SIDEBAR OVERLAY */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(2px);
            z-index: 1035;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* MOBILE HEADER */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 60px;
            background: #fff;
            border-bottom: 1px solid rgba(255,228,233,.8);
            z-index: 1020;
            padding: 0 20px;
            align-items: center;
            justify-content: space-between;
        }
        .mobile-brand {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -0.5px;
        }
        .hamburger-btn {
            width: 40px; height: 40px;
            border-radius: 10px; border: none;
            background: var(--s-pink);
            color: var(--p-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition-3d);
        }
        .hamburger-btn:active { transform: scale(0.92); }

        /* MAIN CONTENT AREA */
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
            flex-wrap: wrap;
            gap: 15px;
        }

        /* TOMBOL AVATAR PROFIL ATAS KANAN */
        .profile-header-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ffffff;
            cursor: pointer;
            transition: var(--transition-3d);
            background: #ffffff;
            flex-shrink: 0;
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

        /* BREADCRUMB */
        .breadcrumb-custom { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.8rem; 
            color: var(--text-muted); 
            margin-bottom: 12px; 
        }
        .breadcrumb-custom a { 
            color: var(--p-pink); 
            text-decoration: none; 
            font-weight: 700; 
            transition: var(--transition-3d);
        }
        .breadcrumb-custom a:hover {
            color: var(--d-pink);
        }
        .breadcrumb-custom .current { 
            color: var(--text-dark); 
            font-weight: 800; 
        }

        /* BUTTONS */
        .btn-reg-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; 
            border-radius: 12px !important;
            padding: 12px 24px !important; 
            font-weight: 800 !important; 
            border: none !important;
            box-shadow: 0 6px 16px rgba(216, 63, 103, 0.25) !important; 
            transition: var(--transition-3d) !important;
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.85rem;
            text-decoration: none;
        }
        .btn-reg-header:hover { 
            transform: translateY(-3px) scale(1.02); 
            box-shadow: 0 10px 22px rgba(216, 63, 103, 0.35) !important; 
        }
        .btn-outline-pink { 
            background: #ffffff; 
            color: var(--p-pink); 
            border: 2px solid var(--light-pink); 
            border-radius: 12px; 
            padding: 12px 24px; 
            font-weight: 700; 
            font-size: 0.85rem; 
            transition: var(--transition-3d); 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
        }
        .btn-outline-pink:hover { 
            background: var(--s-pink); 
            border-color: var(--p-pink);
            transform: translateY(-2px); 
        }

        /* LANDSCAPE LAYOUT */
        .landscape-wrapper { display: flex; gap: 25px; align-items: flex-start; }
        .landscape-left { width: 280px; flex-shrink: 0; }
        .landscape-right { flex: 1; min-width: 0; }

        /* PREVIEW CARD */
        .preview-card { 
            background: #ffffff; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 236, 239, 0.8); 
            padding: 30px; 
            text-align: center; 
            position: sticky; 
            top: 30px; 
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d);
        }
        .preview-avatar { 
            width: 120px; 
            height: 120px; 
            border-radius: 50%; 
            background: var(--s-pink); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 16px; 
            color: var(--p-pink); 
            font-size: 3rem; 
            overflow: hidden; 
            border: 4px solid #ffffff; 
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15); 
            transition: var(--transition-3d);
        }
        .preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .preview-name { font-weight: 800; font-size: 1.15rem; color: var(--text-dark); margin-bottom: 6px; }
        .preview-role { 
            display: inline-block; 
            padding: 5px 16px; 
            border-radius: 50px; 
            font-size: 0.72rem; 
            font-weight: 800; 
            margin-bottom: 20px; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .preview-info { text-align: left; margin-top: 20px; }
        .preview-info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1.5px solid var(--border-color); font-size: 0.82rem; }
        .preview-info-item:last-child { border-bottom: none; }
        .preview-info-label { color: var(--text-muted); font-weight: 700; }
        .preview-info-value { color: var(--text-dark); font-weight: 800; }

        .foto-upload-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            padding: 10px 20px; 
            border-radius: 12px; 
            border: 2px dashed var(--p-pink); 
            color: var(--p-pink); 
            font-weight: 700; 
            font-size: 0.85rem; 
            cursor: pointer; 
            transition: var(--transition-3d); 
            background: var(--s-pink); 
            margin-top: 15px; 
        }
        .foto-upload-btn:hover { 
            background: var(--p-pink); 
            color: #ffffff; 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }

        /* FORM CARD */
        .form-card { 
            background: #ffffff; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 236, 239, 0.8); 
            padding: 35px;
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
        }
        .form-section-title { 
            font-size: 0.75rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 1.2px; 
            color: var(--p-pink); 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid var(--s-pink); 
        }
        .form-label { 
            font-weight: 800; 
            font-size: 11px; 
            color: #8a99a8; 
            text-transform: uppercase; 
            letter-spacing: 1.2px; 
            margin-bottom: 8px; 
            display: block; 
        }
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }

        .form-control, .form-select { 
            border-radius: 14px; 
            padding: 12px 18px; 
            border: 2px solid #eef2f6; 
            background: #f8fafc; 
            font-size: 14px; 
            font-weight: 600; 
            transition: var(--transition-3d); 
            color: var(--text-dark); 
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--p-pink); 
            background-color: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); 
            outline: none; 
        }
        .form-control.has-error, .form-select.has-error { 
            border-color: #ef4444 !important; 
            background-color: #fffbfa !important; 
        }

        /* Penyesuaian Gaya Indikator Dropdown Arrow Kustom (Garis Merah Muda Khas SpotLight) */
        .form-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23d83f67'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") no-repeat right 18px center !important;
            background-size: 14px !important;
            padding-right: 45px !important;
        }

        /* Penyesuaian Placeholder: Font normal (tidak bold) dan berwarna abu-abu redup saat kosong */
        .form-control::placeholder {
            color: #a0aec0 !important;
            font-weight: 500 !important;
        }

        /* Ketika select masih menampilkan opsi placeholder bawaan */
        .form-select:required:invalid {
            color: #a0aec0 !important;
            font-weight: 500 !important;
        }

        /* Mengembalikan ke warna gelap dan font bold ketika nilai valid sudah dipilih */
        .form-select:valid {
            color: var(--text-dark) !important;
            font-weight: 600 !important;
        }

        /* Seluruh opsi di dalam dropdown tetap menggunakan gaya teks tegas */
        .form-select option {
            color: var(--text-dark) !important;
            font-weight: 600 !important;
        }

        /* PASSWORD WRAPPER DENGAN TOMBOL VISIBILITAS SINKRON */
        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right: 45px; }
        .password-toggle {
            position: absolute; 
            right: 15px; 
            top: 50%; 
            transform: translateY(-50%);
            background: none; 
            border: none; 
            color: var(--text-muted); 
            cursor: pointer;
            font-size: 1.1rem; 
            padding: 4px; 
            transition: var(--transition-3d);
            z-index: 5;
        }
        .password-toggle:hover { color: var(--p-pink); }

        /* INPUT GROUP +62 PREFIX */
        .input-group-text { 
            background: var(--s-pink); 
            border: 2px solid #eef2f6; 
            border-right: none; 
            border-radius: 14px 0 0 14px; 
            color: var(--p-pink); 
            font-weight: 700; 
            font-size: 0.85rem; 
            padding: 12px 18px; 
        }
        .input-group .form-control { 
            border-left: none; 
            border-radius: 0 14px 14px 0; 
        }

        /* PASSWORD STRENGTH */
        .password-strength { height: 4px; border-radius: 10px; margin-top: 8px; transition: var(--transition-3d); }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        .hint-text { font-size: 0.72rem; color: var(--text-muted); margin-top: 5px; font-weight: 600; }

        /* FIELD ERROR MSG - INDEPENDENT & NON-DISRUPTIVE */
        .field-error-msg {
            color: #ef4444;
            font-size: 0.72rem;
            font-weight: 700;
            margin-top: 6px;
            display: block;
            animation: fadeInUp 0.3s ease-out;
        }

        /* MODAL GANTI PROFIL */
        .profile-preview-box {
            width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
            border: 2.5px solid #eef2f6; background: #f8fafc; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            transition: var(--transition-3d);
        }
        .profile-preview-box img {
            width: 100%; height: 100%; object-fit: cover;
        }

        /* BUTTON GROUP ACTION */
        .btn-group-action {
            display: flex; gap: 12px; justify-content: flex-end;
            margin-top: 30px; padding-top: 20px;
            border-top: 2px solid var(--s-pink);
        }

        /* ============================================
           RESPONSIVE BREAKPOINTS
           ============================================ */

        /* Tablet & below */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 4px 0 24px rgba(0,0,0,0.08);
            }
            .sidebar.show-mobile {
                transform: translateX(0);
            }
            .mobile-header {
                display: flex;
            }
            .main-content {
                margin-left: 0;
                padding: 80px 20px 30px;
            }
            .dashboard-header {
                margin-bottom: 25px;
            }
            .dashboard-header h3 {
                font-size: 1.25rem;
            }
            .landscape-wrapper { flex-direction: column; }
            .landscape-left { width: 100%; }
            .preview-card { position: static; margin-bottom: 20px; }
            .form-card { padding: 24px; }
        }

        /* Small phones */
        @media (max-width: 575.98px) {
            .main-content {
                padding: 70px 14px 20px;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .dashboard-header > div:last-child {
                width: 100%;
                justify-content: space-between;
            }
            .form-card {
                padding: 20px 16px;
                border-radius: 16px;
            }
            .form-section-title {
                font-size: 0.7rem;
                margin-bottom: 18px;
            }
            .form-control, .form-select {
                padding: 12px 14px;
                font-size: 0.88rem;
                border-radius: 12px;
            }
            .form-label {
                font-size: 10px;
            }
            .preview-card {
                padding: 20px;
                border-radius: 16px;
            }
            .preview-avatar {
                width: 90px;
                height: 90px;
                font-size: 2.2rem;
            }
            .preview-name { font-size: 1rem; }
            .radio-option {
                padding: 10px 12px;
            }
            .radio-option .radio-text {
                font-size: 0.75rem;
            }
            .input-group-text {
                padding: 12px 10px;
                font-size: 0.8rem;
            }
            .password-toggle {
                right: 12px;
            }
            .btn-group-action {
                flex-direction: column;
                gap: 10px;
            }
            .btn-reg-header, .btn-outline-pink {
                width: 100%;
                justify-content: center;
                padding: 14px;
                font-size: 0.9rem;
            }
            .foto-upload-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Extra small */
        @media (max-width: 359.98px) {
            .mobile-header {
                padding: 0 14px;
            }
            .mobile-brand {
                font-size: 1.1rem;
            }
            .form-card {
                padding: 16px 12px;
            }
        }

        /* Large screens */
        @media (min-width: 1400px) {
            .form-card {
                padding: 40px;
            }
        }
    </style>
</head>
<body>

    <!-- MOBILE HEADER -->
    <div class="mobile-header">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <a href="../../index.php" class="mobile-brand">SpotLight.</a>
        <div style="width:40px;"></div>
    </div>

    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Beranda Pemilik</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Owner/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="../../Master/Karyawan/index.php" class="nav-link-custom active">
                        <span>
                            <i class="bi bi-person-badge-fill me-2"></i>
                            Kelola Karyawan
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                        <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuLaporan">
                        <ul class="list-unstyled">
                            <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                            <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                            <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                            <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- BREADCRUMB -->
        <div class="breadcrumb-custom">
            <a href="index.php">Kelola Karyawan</a>
            <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
            <span class="current">Edit Karyawan</span>
        </div>

        <!-- HEADER SINKRON DENGAN ELEMEN PORTAL OWNER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Edit Data Karyawan ✦</h3>
                <p class="text-muted small mb-0">Perbarui data kredensial serta profil milik staf <?= htmlspecialchars($current_nama) ?>.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
                </div>
                <a href="index.php" class="btn-outline-pink d-none d-md-inline-flex"><i class="bi bi-arrow-left"></i> Kembali</a>
            </div>
        </div>

        <!-- LANDSCAPE LAYOUT -->
        <div class="landscape-wrapper">

            <!-- LEFT PANEL - PREVIEW CARD -->
            <div class="landscape-left">
                <div class="preview-card">
                    <div class="preview-avatar" id="previewAvatar">
                        <img src="<?= $foto_src ?>" alt="Foto Profil">
                    </div>
                    <div class="preview-name" id="previewNama"><?= htmlspecialchars($current_nama) ?></div>
                    <span class="preview-role" id="previewRole" style="background: var(--s-pink); color: var(--p-pink);"><?= htmlspecialchars($current_role) ?></span>

                    <div class="preview-info">
                        <div class="preview-info-item"><span class="preview-info-label">NIK</span><span class="preview-info-value" id="previewNIK"><?= htmlspecialchars($current_nik) ?></span></div>
                        <div class="preview-info-item"><span class="preview-info-label">Umur</span><span class="preview-info-value" id="previewUmur"><?= hitungUmur($current_dob) ?></span></div>
                        <div class="preview-info-item"><span class="preview-info-label">Jenis Kelamin</span><span class="preview-info-value" id="previewJK"><?= htmlspecialchars($current_jk) ?></span></div>
                        <div class="preview-info-item"><span class="preview-info-label">Telepon</span><span class="preview-info-value" id="previewHP"><?= htmlspecialchars($data_karyawan['No_Hp'] ?? '-') ?></span></div>
                        <div class="preview-info-item"><span class="preview-info-label">Email</span><span class="preview-info-value" id="previewEmail"><?= htmlspecialchars($current_email) ?></span></div>
                        <div class="preview-info-item"><span class="preview-info-label">Status</span><span class="preview-info-value" id="previewStatus"><?= $current_status == 1 ? 'Aktif' : 'Nonaktif' ?></span></div>
                    </div>

                    <label class="foto-upload-btn" onclick="document.getElementById('inputFoto').click()">
                        <i class="bi bi-camera-fill"></i> Ganti Foto Staf
                    </label>
                </div>
            </div>

            <!-- RIGHT PANEL - FORM CARD -->
            <div class="landscape-right">
                <form method="POST" enctype="multipart/form-data" id="formEdit" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="file" name="foto_profil" id="inputFoto" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="previewFoto(this)">

                    <div class="form-card">
                        <!-- DATA PRIBADI -->
                        <div class="form-section-title"><i class="bi bi-person-fill me-2"></i>Data Pribadi Karyawan</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Nomor Induk Kependudukan (NIK)<span class="required-star">*</span></label>
                                <input type="text" name="nik" id="inputNIK" class="form-control <?= hasError('nik', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: 3175023101060001 (16 digit angka)" value="<?= $current_nik ?>" maxlength="16" required autocomplete="new-nik">
                                <div class="hint-text">16 digit angka kependudukan</div>
                                <?php if (isset($errors['nik'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['nik'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nama Lengkap<span class="required-star">*</span></label>
                                <input type="text" name="nama" id="inputNama" class="form-control <?= hasError('nama', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: Fikri Sunanta (Hanya huruf, Min. 3 karakter)" value="<?= $current_nama ?>" required autocomplete="new-name">
                                <?php if (isset($errors['nama'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['nama'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Lahir<span class="required-star">*</span></label>
                                <input type="date" name="tanggal_lahir" id="inputDOB" class="form-control <?= hasError('tanggal_lahir', $error_fields) ? 'has-error' : '' ?>" value="<?= $current_dob ?>" required>
                                <div class="hint-text">Batas umur mendaftar: 17 - 60 tahun</div>
                                <?php if (isset($errors['tanggal_lahir'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['tanggal_lahir'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Kelamin<span class="required-star">*</span></label>
                                <select name="jenis_kelamin" id="inputJK" class="form-select <?= hasError('jenis_kelamin', $error_fields) ? 'has-error' : '' ?>" required>
                                    <option value="" disabled <?= empty($current_jk) ? 'selected' : '' ?>>Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" <?= $current_jk == 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= $current_jk == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                                <?php if (isset($errors['jenis_kelamin'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['jenis_kelamin'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alamat Domisili<span class="required-star">*</span></label>
                                <input type="text" name="alamat" id="inputAlamat" class="form-control <?= hasError('alamat', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: Jl. Kemang Raya No. 12, Jakarta Selatan" value="<?= $current_alamat ?>" required autocomplete="new-address">
                                <?php if (isset($errors['alamat'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['alamat'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- AKUN SISTEM -->
                        <div class="form-section-title"><i class="bi bi-shield-lock-fill me-2"></i>Aparatur Akun & Kredensial</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
                                <input type="text" name="username" id="inputUsername" class="form-control <?= hasError('username', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: fikri_admin (Huruf, angka & underscore)" value="<?= $current_username ?>" required autocomplete="new-username">
                                <div class="hint-text">Hanya huruf, angka, dan underscore</div>
                                <?php if (isset($errors['username'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['username'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                                <input type="email" name="email" id="inputEmail" class="form-control <?= hasError('email', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: fikri@spotlight.com" value="<?= $current_email ?>" required autocomplete="new-email">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['email'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Peran Kerja (Role)<span class="required-star">*</span></label>
                                <select name="role_karyawan" id="inputRole" class="form-select <?= hasError('role_karyawan', $error_fields) ? 'has-error' : '' ?>" required>
                                    <option value="Admin" <?= $current_role == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="Fotografer" <?= $current_role == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                                    <option value="Owner" <?= $current_role == 'Owner' ? 'selected' : '' ?>>Owner</option>
                                </select>
                                <?php if (isset($errors['role_karyawan'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['role_karyawan'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kata Sandi Baru <span style="color: var(--text-muted); font-weight: 600; text-transform: none; letter-spacing: 0;">(Kosongkan jika tidak diganti)</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="inputPassword" class="form-control <?= hasError('password', $error_fields) ? 'has-error' : '' ?>" placeholder="Min. 8 karakter (Huruf, Angka, Simbol)" autocomplete="new-password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('inputPassword', this)" title="Lihat password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="hint-text">Harus memuat kombinasi huruf, angka, dan simbol khusus</div>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['password'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control <?= hasError('password_confirm', $error_fields) ? 'has-error' : '' ?>" placeholder="Masukkan kembali kata sandi baru" autocomplete="new-password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('inputPasswordConfirm', this)" title="Lihat password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password_confirm'])): ?>
                                    <div class="field-error-msg" id="passwordMatchError"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['password_confirm'] ?></div>
                                <?php else: ?>
                                    <div class="field-error-msg" id="passwordMatchError" style="display:none;"><i class="bi bi-exclamation-circle-fill"></i> Konfirmasi sandi tidak cocok dengan sandi utama!</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- KONTAK & STATUS -->
                        <div class="form-section-title"><i class="bi bi-telephone-fill me-2"></i>Informasi Kontak & Status Operasional</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nomor Telepon Seluler<span class="required-star">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">+62</span>
                                    <input type="text" name="no_hp" id="inputHP" class="form-control <?= hasError('no_hp', $error_fields) ? 'has-error' : '' ?>" placeholder="Contoh: 8123456789 (9-13 digit angka murni)" value="<?= $current_hp ?>" required autocomplete="new-phone">
                                </div>
                                <div class="hint-text">Hanya angka numerik murni tanpa awalan +62 atau 0 (9 - 13 digit)</div>
                                <?php if (isset($errors['no_hp'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['no_hp'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Akun Sistem<span class="required-star">*</span></label>
                                <select name="status_karyawan" id="inputStatus" class="form-select <?= hasError('status_karyawan', $error_fields) ? 'has-error' : '' ?>" required>
                                    <option value="1" <?= $current_status == 1 ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= $current_status == 0 ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                                <?php if (isset($errors['status_karyawan'])): ?>
                                    <div class="field-error-msg"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['status_karyawan'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- BUTTONS -->
                        <div class="btn-group-action">
                            <a href="index.php" class="btn-outline-pink"><i class="bi bi-arrow-left"></i> Batal</a>
                            <button type="submit" name="edit_karyawan" class="btn-reg-header"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BIODATA OWNER (HANYA INFO - EDIT DIHAPUS) -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pemilik</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
                <img src="<?= $foto_owner_src ?>" alt="Foto Profil">
              </div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_owner) ?></h5>
              <span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Owner (Pemilik)</span>
            </div>
            <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc; box-shadow: none;">
              <div class="row g-3">
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['nik']) ?></span>
                </div>
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nama Pengguna</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;">@<?= htmlspecialchars($username_owner) ?></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Email</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_owner) ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin']) ?></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Tanggal Lahir</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;">
                    <?= ($d_profile['tanggal_lahir'] instanceof DateTime) ? $d_profile['tanggal_lahir']->format('d M Y') : ($d_profile['tanggal_lahir'] ?? '-') ?>
                  </span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nomor Telepon</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['no_hp']) ?></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Lengkap</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['alamat']) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('show-mobile');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('show-mobile') ? 'hidden' : '';
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 991) {
                document.getElementById('sidebar').classList.remove('show-mobile');
                document.getElementById('sidebarOverlay').classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // JAM REAL-TIME SINKRON
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();

            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();

            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const timeString = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
            const clockEl = document.getElementById('live-clock');
            if (clockEl) clockEl.innerText = timeString;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        // SUBMENU SINKRON SIDEBAR
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

        // PROFIL OWNER MODAL TRIGGERS (Akses Edit Dihapus, Hanya Lihat Biodata)
        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata'));
            modalBiodata.show();
        }

        // VISIBILITAS PASSWORD TOGGLE
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text'; icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); btn.title = 'Sembunyikan password';
            } else {
                input.type = 'password'; icon.classList.remove('bi-eye-slash'); icon.classList.add('bi-eye'); btn.title = 'Lihat password';
            }
        }

        // PREVIEW FOTO KARYAWAN
        function previewFoto(input) {
            const avatar = document.getElementById('previewAvatar');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { 
                    avatar.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">'; 
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // UPDATE PREVIEW CARD SECARA LIVE
        function updatePreview() {
            document.getElementById('previewNama').textContent = document.getElementById('inputNama').value || 'Nama Karyawan';
            const role = document.getElementById('inputRole').value;
            const roleEl = document.getElementById('previewRole');
            roleEl.textContent = role || 'Pilih Peran';

            const roleColors = { 
                'Admin': ['#eff6ff', '#2563eb'], 
                'Fotografer': ['var(--s-pink)', 'var(--p-pink)'], 
                'Owner': ['#f5f3ff', '#8b5cf6'] 
            };
            if (roleColors[role]) { 
                roleEl.style.background = roleColors[role][0]; 
                roleEl.style.color = roleColors[role][1]; 
            }
            document.getElementById('previewNIK').textContent = document.getElementById('inputNIK').value || '-';
            const jk = document.getElementById('inputJK').value;
            document.getElementById('previewJK').textContent = jk || '-';
            const hp = document.getElementById('inputHP').value;
            document.getElementById('previewHP').textContent = hp ? '+62' + hp : '-';
            document.getElementById('previewEmail').textContent = document.getElementById('inputEmail').value || '-';
            const status = document.getElementById('inputStatus').value;
            document.getElementById('previewStatus').textContent = status == '1' ? 'Aktif' : 'Nonaktif';
        }

        // KALKULASI & PREVIEW UMUR REAL-TIME
        function updateUmur() {
            const dob = document.getElementById('inputDOB').value;
            const preview = document.getElementById('previewUmur');
            if (!dob) { preview.textContent = '-'; return; }
            const birth = new Date(dob); const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            preview.textContent = age + ' tahun';
            preview.style.color = (age >= 17 && age <= 60) ? '#059669' : '#dc2626';
            document.getElementById('inputDOB').classList.toggle('is-valid', age >= 17 && age <= 60);
            document.getElementById('inputDOB').classList.toggle('is-invalid', age < 17 || age > 60);
        }

        // PASANG EVENT LISTENERS UNTUK REAL-TIME INPUTS
        ['inputNama', 'inputNIK', 'inputRole', 'inputHP', 'inputEmail', 'inputJK', 'inputStatus'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updatePreview);
                el.addEventListener('change', updatePreview);
            }
        });
        
        const dobEl = document.getElementById('inputDOB');
        if (dobEl) {
            dobEl.addEventListener('change', updateUmur);
        }

        // MASKING & VALIDASI INPUTS
        document.getElementById('inputNIK').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 16);
            this.classList.toggle('is-valid', /^[0-9]{16}$/.test(this.value));
            this.classList.toggle('is-invalid', this.value.length > 0 && !/^[0-9]{16}$/.test(this.value));
        });
        document.getElementById('inputNama').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z ]/g, '');
            this.classList.toggle('is-valid', this.value.length >= 3);
        });
        document.getElementById('inputUsername').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            this.classList.toggle('is-valid', /^[a-zA-Z0-9_]{3,50}$/.test(this.value));
        });
        document.getElementById('inputEmail').addEventListener('input', function() {
            const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
            this.classList.toggle('is-valid', valid);
            this.classList.toggle('is-invalid', this.value.length > 0 && !valid);
        });

        // KEKUATAN KATA SANDI BAR
        document.getElementById('inputPassword').addEventListener('input', function() {
            const val = this.value; let strength = 0;
            if (val.length >= 8) strength++; 
            if (/[A-Za-z]/.test(val) && /[0-9]/.test(val)) strength++; 
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            const bar = document.getElementById('passwordStrength');
            if (bar) {
                bar.className = 'password-strength';
                if (strength === 1) bar.classList.add('strength-weak');
                else if (strength === 2) bar.classList.add('strength-medium');
                else if (strength === 3) bar.classList.add('strength-strong');
            }
            this.classList.toggle('is-valid', strength === 3 || val.length === 0);
        });

        document.getElementById('inputPasswordConfirm').addEventListener('input', function() {
            const pass = document.getElementById('inputPassword').value;
            const match = this.value === pass && (this.value !== '' || pass === '');
            this.classList.toggle('is-valid', match && pass !== '');
            this.classList.toggle('is-invalid', this.value.length > 0 && !match);
            const err = document.getElementById('passwordMatchError');
            if (err) err.style.display = (this.value.length > 0 && !match) ? 'block' : 'none';
        });

        document.getElementById('inputHP').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);
            this.classList.toggle('is-valid', this.value.length >= 9);
        });

        // VALIDASI SUBMIT FORMULIR
        document.getElementById('formEdit').addEventListener('submit', function(e) {
            const pass = document.getElementById('inputPassword').value;
            const confirm = document.getElementById('inputPasswordConfirm').value;
            const dob = document.getElementById('inputDOB').value;

            if (pass !== '' && pass !== confirm) { 
                e.preventDefault(); 
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Password Tidak Cocok!', 
                    text: 'Kata sandi konfirmasi baru tidak sesuai dengan kata sandi baru utama.', 
                    confirmButtonColor: '#d83f67' 
                }); 
                return false; 
            }

            if (dob) { 
                const birth = new Date(dob); 
                const today = new Date(); 
                let age = today.getFullYear() - birth.getFullYear(); 
                const m = today.getMonth() - birth.getMonth(); 
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--; 

                if (age < 17 || age > 60) { 
                    e.preventDefault(); 
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Umur Tidak Valid!', 
                        text: 'Umur pendaftar staf baru harus berumur antara 17-60 tahun. Saat ini: ' + age + ' tahun.', 
                        confirmButtonColor: '#d83f67' 
                    }); 
                    return false; 
                }
            }
            return true;
        });

        // Jalankan sinkronisasi pratinjau kartu kiri & umur sesaat setelah halaman berhasil dimuat
        document.addEventListener("DOMContentLoaded", function() {
            updatePreview();
            updateUmur();
        });

        function confirmLogout(e) { 
            e.preventDefault(); 
            Swal.fire({ 
                title: 'Keluar Sistem? ❌', 
                text: 'Yakin ingin keluar dari sistem SpotLight Studio?', 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#d83f67', 
                cancelButtonColor: '#718096', 
                confirmButtonText: 'Ya', 
                cancelButtonText: 'Batal' 
            }).then(r => { 
                if (r.isConfirmed) window.location = '../../logout.php'; 
            }); 
        }

        function confirmLandingPage(e) { 
            e.preventDefault(); 
            Swal.fire({ 
                title: 'Kembali ke Beranda? ✦', 
                text: 'Kembali ke halaman utama publik?', 
                icon: 'info', 
                showCancelButton: true, 
                confirmButtonColor: '#d83f67', 
                cancelButtonColor: '#718096', 
                confirmButtonText: 'Ya', 
                cancelButtonText: 'Batal' 
            }).then(r => { 
                if (r.isConfirmed) window.location = '../../index.php'; 
            }); 
        }
    </script>

    <!-- SWEETALERT NOTIFIKASI ERROR CRUD GLOBAL -->
    <?php if (isset($errors['global']) && $errors['global'] != ""): ?>
    <script>
        Swal.fire({ 
            icon: 'error', 
            title: 'Gagal Menyimpan! ❌', 
            html: '<?= addslashes($errors['global']) ?>', 
            confirmButtonColor: '#d83f67' 
        });
    </script>
    <?php endif; ?>

</body>
</html>