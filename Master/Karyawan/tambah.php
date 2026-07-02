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

// Ambil Profil Owner untuk Header & Modal Ganti Profil
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
// LOGIKA UPDATE PROFIL OWNER (SINKRON DENGAN DASHBOARD)
// =====================================================
$error_profile = "";
$success_profile = false;
if (isset($_POST['update_profil'])) {
    $nama_post = trim($_POST['nama']);
    $username_post = trim($_POST['username']);
    $email_post = trim($_POST['email']);
    $no_hp_post = trim($_POST['no_hp']);
    $alamat_post = trim($_POST['alamat']);
    $password_post = $_POST['password'];
    $confirm_password_post = $_POST['confirm_password'];

    if (preg_match('/[^a-zA-Z ]/', $nama_post)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf dan spasi!";
    } elseif (preg_match('/[^a-zA-Z0-9_]/', $username_post)) {
        $error_profile = "Username hanya boleh berisi huruf, angka, dan underscore!";
    } else {
        $q_check = safe_sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE (Username_Karyawan = ? OR Email_Karyawan = ?) AND ID_Karyawan != ?", array($username_post, $email_post, $id_owner));
        $d_check = safe_sqlsrv_fetch($q_check);
        if (($d_check['total'] ?? 0) > 0) {
            $error_profile = "Username atau Email sudah terdaftar oleh akun staf lain!";
        } else {
            $sql_up = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, No_Hp = ?, Alamat = ? WHERE ID_Karyawan = ?";
            $params_up = array($nama_post, $username_post, $email_post, $no_hp_post, $alamat_post, $id_owner);
            $res_up = safe_sqlsrv_query($conn, $sql_up, $params_up);

            if ($res_up !== false) {
                if (!empty($password_post)) {
                    if (strlen($password_post) < 8) {
                        $error_profile = "Sandi baru minimal harus 8 karakter!";
                    } elseif ($password_post !== $confirm_password_post) {
                        $error_profile = "Konfirmasi sandi tidak cocok!";
                    } else {
                        $hashed_password = password_hash($password_post, PASSWORD_DEFAULT);
                        safe_sqlsrv_query($conn, "UPDATE Karyawan SET Password_Karyawan = ? WHERE ID_Karyawan = ?", array($hashed_password, $id_owner));
                    }
                }

                if (empty($error_profile) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
                    $file_name = $_FILES['foto_profil']['name'];
                    $file_tmp = $_FILES['foto_profil']['tmp_name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = array('jpg', 'jpeg', 'png');

                    if (in_array($file_ext, $allowed_ext)) {
                        $new_file_name = "owner_" . $id_owner . "_" . time() . "." . $file_ext;
                        $upload_dir = "../../assets/img/karyawan/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        
                        if ($foto_owner != 'default.jpg' && file_exists($upload_dir . $foto_owner)) {
                            @unlink($upload_dir . $foto_owner);
                        }

                        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                            safe_sqlsrv_query($conn, "UPDATE Karyawan SET Foto_Profil = ? WHERE ID_Karyawan = ?", array($new_file_name, $id_owner));
                            $foto_owner = $new_file_name;
                        }
                    }
                }

                if (empty($error_profile)) {
                    $success_profile = true;
                    $q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
                    $d_profile = safe_sqlsrv_fetch($q_profile);
                    if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
                    $nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
                    $username_owner = $d_profile['username_karyawan'] ?? 'owner';
                    $email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
                    $foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';
                    $foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner)) ? "../../assets/img/karyawan/" . $foto_owner : $default_svg_avatar;
                }
            } else {
                $error_profile = "Gagal memperbarui profil di database!";
            }
        }
    }
}

// =====================================================
// FUNGSI VALIDASI TAMBAH KARYAWAN
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
function cekDuplikat($conn, $field, $value) { return safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE $field = ? AND Is_Deleted = 0", array($value)); }
function sanitizeInput($input) { return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'); }
function generateCsrfToken() { if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf_token']; }
function verifyCsrfToken($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }

// =====================================================
// PROSES TAMBAH KARYAWAN
// =====================================================
$error_crud = "";
$error_fields = array();

if (isset($_POST['tambah_karyawan'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_crud = "Token keamanan tidak valid! Silakan refresh halaman.";
    } else {
        $nik = sanitizeInput($_POST['nik'] ?? ''); $nama = sanitizeInput($_POST['nama'] ?? ''); $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? ''); $pass = $_POST['password'] ?? ''; $pass_confirm = $_POST['password_confirm'] ?? '';
        $jk = $_POST['jenis_kelamin'] ?? ''; $dob = trim($_POST['tanggal_lahir'] ?? ''); $role = $_POST['role_karyawan'] ?? '';
        $hp_raw = trim($_POST['no_hp'] ?? ''); $hp = '+62' . $hp_raw;
        $alamat = sanitizeInput($_POST['alamat'] ?? '');
        $umur = !empty($dob) && validateTanggal($dob) ? hitungUmur($dob) : 0;

        $errors = array();

        if (empty($nik)) { $errors[] = "NIK wajib diisi!"; $error_fields['nik'] = true; }
        elseif (!validateNIK($nik)) { $errors[] = "NIK harus 16 digit angka!"; $error_fields['nik'] = true; }

        if (empty($nama)) { $errors[] = "Nama wajib diisi!"; $error_fields['nama'] = true; }
        elseif (strlen($nama) < 3) { $errors[] = "Nama minimal 3 karakter!"; $error_fields['nama'] = true; }

        if (empty($username)) { $errors[] = "Username wajib diisi!"; $error_fields['username'] = true; }
        elseif (!validateUsername($username)) { $errors[] = "Username hanya huruf, angka, underscore (3-50 karakter)!"; $error_fields['username'] = true; }

        if (empty($email)) { $errors[] = "Email wajib diisi!"; $error_fields['email'] = true; }
        elseif (!validateEmail($email)) { $errors[] = "Format email tidak valid! Contoh: nama@email.com"; $error_fields['email'] = true; }

        if (empty($pass)) { $errors[] = "Password wajib diisi!"; $error_fields['password'] = true; }
        elseif (!validatePassword($pass)) { $errors[] = "Password minimal 8 karakter, harus mengandung huruf, angka, dan simbol!"; $error_fields['password'] = true; }
        elseif ($pass !== $pass_confirm) { $errors[] = "Password dan konfirmasi password tidak cocok!"; $error_fields['password_confirm'] = true; }

        if (empty($hp_raw)) { $errors[] = "Nomor telepon wajib diisi!"; $error_fields['no_hp'] = true; }
        elseif (!preg_match('/^[0-9]{9,13}$/', $hp_raw)) { $errors[] = "No HP harus 9-13 digit angka (tanpa +62)!"; $error_fields['no_hp'] = true; }
        elseif (!validatePhone($hp)) { $errors[] = "No HP tidak valid! Format: +62[9-13 digit]"; $error_fields['no_hp'] = true; }

        if (empty($dob)) { $errors[] = "Tanggal lahir wajib diisi!"; $error_fields['tanggal_lahir'] = true; }
        elseif (!validateTanggal($dob)) { $errors[] = "Tanggal lahir tidak valid! Format: YYYY-MM-DD, tidak boleh di masa depan."; $error_fields['tanggal_lahir'] = true; }
        elseif ($umur < 17) { $errors[] = "Umur minimal 17 tahun! (Umur saat ini: $umur tahun)"; $error_fields['tanggal_lahir'] = true; }
        elseif ($umur > 60) { $errors[] = "Umur maksimal 60 tahun! (Umur saat ini: $umur tahun)"; $error_fields['tanggal_lahir'] = true; }

        if (empty($jk)) { $errors[] = "Jenis kelamin wajib dipilih!"; $error_fields['jenis_kelamin'] = true; }
        elseif (!in_array($jk, ['Laki-laki','Perempuan'])) { $errors[] = "Jenis kelamin tidak valid!"; $error_fields['jenis_kelamin'] = true; }

        if (empty($role)) { $errors[] = "Peran wajib dipilih!"; $error_fields['role_karyawan'] = true; }
        elseif (!in_array($role, ['Admin','Fotografer','Owner'])) { $errors[] = "Peran tidak valid!"; $error_fields['role_karyawan'] = true; }

        if (empty($alamat)) { $errors[] = "Alamat wajib diisi!"; $error_fields['alamat'] = true; }

        if (empty($errors)) {
            $sql_dup = "{CALL sp_CekDuplikatKaryawan(?, ?, ?, NULL)}";
            $q_dup = safe_sqlsrv_query($conn, $sql_dup, array($nik, $username, $email));
            $d_dup = safe_sqlsrv_fetch($q_dup);
            
            if ($d_dup) {
                if (($d_dup['Duplikat_NIK'] ?? 0) > 0) { $errors[] = "NIK sudah terdaftar!"; $error_fields['nik'] = true; }
                if (($d_dup['Duplikat_Username'] ?? 0) > 0) { $errors[] = "Username sudah digunakan!"; $error_fields['username'] = true; }
                if (($d_dup['Duplikat_Email'] ?? 0) > 0) { $errors[] = "Email sudah terdaftar!"; $error_fields['email'] = true; }
            }
            if (cekDuplikat($conn, 'No_Hp', $hp) > 0) { $errors[] = "Nomor telepon sudah terdaftar!"; $error_fields['no_hp'] = true; }
        }

        if (empty($errors) && $role === 'Owner') {
            $owner_count = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0");
            if ($owner_count > 0) { $errors[] = "Sudah ada Owner dalam sistem! Hanya boleh 1 Owner."; $error_fields['role_karyawan'] = true; }
        }

        $foto_profil = 'default.jpg';
        if (empty($errors) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['foto_profil']['tmp_name']; $file_size = $_FILES['foto_profil']['size']; $file_name = $_FILES['foto_profil']['name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime_type = finfo_file($finfo, $file_tmp); finfo_close($finfo);
            $allowed = array('image/jpeg','image/png','image/jpg');
            if (!in_array($mime_type, $allowed)) { $errors[] = "Format foto harus JPG, JPEG, atau PNG!"; }
            elseif ($file_size > 2 * 1024 * 1024) { $errors[] = "Ukuran foto maksimal 2MB!"; }
            else {
                $img_info = getimagesize($file_tmp);
                if (!$img_info) { $errors[] = "File bukan gambar yang valid!"; }
                else {
                    $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $foto_profil = 'karyawan_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $upload_path = '../../assets/img/karyawan/' . $foto_profil;
                    if (!file_exists('../../assets/img/karyawan/')) mkdir('../../assets/img/karyawan/', 0777, true);
                    
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
                            $errors[] = "Gagal mengupload foto!";
                            $foto_profil = 'default.jpg';
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
            $sql_ins = "{CALL sp_InsertKaryawan(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
            $params = array(
                $nik,
                $nama,
                $username,
                $email,
                $pass_hash,
                $jk,
                $dob,
                $role,
                $hp,
                $alamat,
                $foto_profil,
                $username_session
            );
            
            $stmt_ins = safe_sqlsrv_query($conn, $sql_ins, $params);
            
            if ($stmt_ins) { 
                header("Location: index.php?status_sukses=tambah"); 
                exit(); 
            } else { 
                $errors[] = "Gagal menyimpan data ke database! Silakan periksa kembali kecocokan data Anda."; 
                if ($foto_profil != 'default.jpg') @unlink('../../assets/img/karyawan/' . $foto_profil); 
            }
        }
        if (!empty($errors)) $error_crud = implode("\n", $errors);
    }
}
$csrf_token = generateCsrfToken();

function hasError($field, $error_fields) { return isset($error_fields[$field]) && $error_fields[$field]; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Karyawan – SpotLight Studio</title>
    
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
            border: 2px solid var(--p-pink);
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 750;
            font-size: 0.85rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-outline-pink:hover {
            background: var(--s-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.1);
        }

        /* LANDSCAPE LAYOUT */
        .landscape-wrapper { display: flex; gap: 30px; align-items: flex-start; }
        .landscape-left { width: 300px; flex-shrink: 0; }
        .landscape-right { flex: 1; min-width: 0; }

        /* PREVIEW CARD SINKRON DESAIN 3D */
        .preview-card { 
            background: #ffffff; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 236, 239, 0.8); 
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            padding: 30px; 
            text-align: center; 
            position: sticky; 
            top: 30px; 
            transition: var(--transition-3d);
        }
        .preview-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 35px rgba(216, 63, 103, 0.08);
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
            background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); 
            outline: none; 
        }
        .form-control.has-error, .form-select.has-error { 
            border-color: #ef4444 !important; 
            background-color: #fffbfa !important; 
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

        /* RADIO BUTTON DESIGN */
        .radio-group { display: flex; gap: 12px; }
        .radio-option {
            flex: 1; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 14px 20px;
            border-radius: 14px; 
            border: 2px solid #eef2f6; 
            cursor: pointer;
            transition: var(--transition-3d); 
            background: #f8fafc;
        }
        .radio-option:hover { 
            border-color: var(--p-pink); 
            background: var(--s-pink); 
            transform: translateY(-2px); 
        }
        .radio-option.active { 
            border-color: var(--p-pink); 
            background: var(--s-pink); 
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.05); 
        }
        .radio-option input[type="radio"] { display: none; }
        .radio-option .radio-icon { 
            width: 22px; 
            height: 22px; 
            border-radius: 50%; 
            border: 2px solid #cbd5e1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transition: var(--transition-3d); 
        }
        .radio-option.active .radio-icon { 
            border-color: var(--p-pink); 
            background: var(--p-pink); 
        }
        .radio-option .radio-icon::after { 
            content: ''; 
            width: 8px; 
            height: 8px; 
            border-radius: 50%; 
            background: #ffffff; 
            opacity: 0; 
            transition: var(--transition-3d); 
        }
        .radio-option.active .radio-icon::after { opacity: 1; }
        .radio-option .radio-text { font-weight: 700; font-size: 0.85rem; color: var(--text-dark); }
        .radio-option.active .radio-text { color: var(--p-pink); }

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

        /* FIELD ERROR MSG */
        .field-error { font-size: 0.75rem; color: #ef4444; margin-top: 6px; display: none; font-weight: 700; }
        .form-control.has-error ~ .field-error { display: block; }

        /* MODAL GANTI PROFIL (UNIFIED SINKRON) */
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
        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px;}
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none !important; box-shadow: none !important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        @media (max-width: 1200px) { .landscape-wrapper { flex-direction: column; } .landscape-left { width: 100%; } .preview-card { position: static; } }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Beranda Pemilik</span></a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="../../Role/Owner/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="submenu-link active"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                    </ul>
                </div>
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
            <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
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
        <span class="current">Tambah Karyawan</span>
    </div>

    <!-- HEADER SINKRON DENGAN ELEMEN PORTAL OWNER -->
    <div class="dashboard-header">
        <div>
            <h3 class="fw-bold mb-1">Tambah Karyawan Baru ✦</h3>
            <p class="text-muted small mb-0">Daftarkan staf baru ke sistem operasional SpotLight Studio.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
            </span>
            <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
            </div>
            <a href="index.php" class="btn-outline-pink"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>
    </div>

    <!-- LANDSCAPE LAYOUT -->
    <div class="landscape-wrapper">

        <!-- LEFT PANEL - PREVIEW CARD -->
        <div class="landscape-left">
            <div class="preview-card">
                <div class="preview-avatar" id="previewAvatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="preview-name" id="previewNama">Nama Karyawan</div>
                <span class="preview-role" id="previewRole" style="background: var(--s-pink); color: var(--p-pink);">Pilih Peran</span>

                <div class="preview-info">
                    <div class="preview-info-item"><span class="preview-info-label">NIK</span><span class="preview-info-value" id="previewNIK">-</span></div>
                    <div class="preview-info-item"><span class="preview-info-label">Umur</span><span class="preview-info-value" id="previewUmur">-</span></div>
                    <div class="preview-info-item"><span class="preview-info-label">Jenis Kelamin</span><span class="preview-info-value" id="previewJK">-</span></div>
                    <div class="preview-info-item"><span class="preview-info-label">Telepon</span><span class="preview-info-value" id="previewHP">-</span></div>
                    <div class="preview-info-item"><span class="preview-info-label">Email</span><span class="preview-info-value" id="previewEmail">-</span></div>
                </div>

                <label class="foto-upload-btn" onclick="document.getElementById('inputFoto').click()">
                    <i class="bi bi-camera-fill"></i> Upload Foto Staf
                </label>
            </div>
        </div>

        <!-- RIGHT PANEL - FORM CARD -->
        <div class="landscape-right">
            <form method="POST" enctype="multipart/form-data" id="formTambah" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="file" name="foto_profil" id="inputFoto" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="previewFoto(this)">

                <div class="form-card">
                    <!-- DATA PRIBADI -->
                    <div class="form-section-title"><i class="bi bi-person-fill me-2"></i>Data Pribadi Karyawan</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nomor Induk Kependudukan (NIK)<span class="required-star">*</span></label>
                            <input type="text" name="nik" id="inputNIK" class="form-control <?= hasError('nik', $error_fields) ? 'has-error' : '' ?>" placeholder="3175091234567890" value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" maxlength="16" required>
                            <div class="hint-text">16 digit angka kependudukan</div>
                            <div class="field-error">NIK tidak valid (harus 16 digit angka)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama Lengkap<span class="required-star">*</span></label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= hasError('nama', $error_fields) ? 'has-error' : '' ?>" placeholder="Masukkan nama lengkap staf" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir<span class="required-star">*</span></label>
                            <input type="date" name="tanggal_lahir" id="inputDOB" class="form-control <?= hasError('tanggal_lahir', $error_fields) ? 'has-error' : '' ?>" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>" required>
                            <div class="hint-text">Batas umur mendaftar: 17 - 60 tahun</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin<span class="required-star">*</span></label>
                            <div class="radio-group">
                                <label class="radio-option <?= ($_POST['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'active' : '' ?> <?= hasError('jenis_kelamin', $error_fields) ? 'has-error' : '' ?>" onclick="selectRadio(this, 'inputJK', 'Laki-laki')">
                                    <span class="radio-icon"></span>
                                    <span class="radio-text">Laki-laki</span>
                                    <input type="radio" name="jenis_kelamin" id="jk_laki" value="Laki-laki" <?= ($_POST['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'checked' : '' ?> required>
                                </label>
                                <label class="radio-option <?= ($_POST['jenis_kelamin'] ?? '') == 'Perempuan' ? 'active' : '' ?> <?= hasError('jenis_kelamin', $error_fields) ? 'has-error' : '' ?>" onclick="selectRadio(this, 'inputJK', 'Perempuan')">
                                    <span class="radio-icon"></span>
                                    <span class="radio-text">Perempuan</span>
                                    <input type="radio" name="jenis_kelamin" id="jk_perempuan" value="Perempuan" <?= ($_POST['jenis_kelamin'] ?? '') == 'Perempuan' ? 'checked' : '' ?> required>
                                </label>
                            </div>
                            <input type="hidden" id="inputJK" value="<?= htmlspecialchars($_POST['jenis_kelamin'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat Domisili<span class="required-star">*</span></label>
                            <input type="text" name="alamat" id="inputAlamat" class="form-control <?= hasError('alamat', $error_fields) ? 'has-error' : '' ?>" placeholder="Alamat tinggal lengkap saat ini" value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- AKUN SISTEM -->
                    <div class="form-section-title"><i class="bi bi-shield-lock-fill me-2"></i>Aparatur Akun & Kredensial</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
                            <input type="text" name="username" id="inputUsername" class="form-control <?= hasError('username', $error_fields) ? 'has-error' : '' ?>" placeholder="username_staf" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            <div class="hint-text">Hanya huruf, angka, dan underscore</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                            <input type="email" name="email" id="inputEmail" class="form-control <?= hasError('email', $error_fields) ? 'has-error' : '' ?>" placeholder="staf@spotlight.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peran Kerja (Role)<span class="required-star">*</span></label>
                            <select name="role_karyawan" id="inputRole" class="form-select <?= hasError('role_karyawan', $error_fields) ? 'has-error' : '' ?>" required>
                                <option value="" disabled <?= empty($_POST['role_karyawan']) ? 'selected' : '' ?>>Pilih Peran Kerja</option>
                                <option value="Admin" <?= ($_POST['role_karyawan'] ?? '') == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Fotografer" <?= ($_POST['role_karyawan'] ?? '') == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                                <option value="Owner" <?= ($_POST['role_karyawan'] ?? '') == 'Owner' ? 'selected' : '' ?>>Owner</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kata Sandi<span class="required-star">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="inputPassword" class="form-control <?= hasError('password', $error_fields) ? 'has-error' : '' ?>" placeholder="Masukkan sandi (Min. 8 karakter)" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('inputPassword', this)" title="Lihat password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="hint-text">Harus memuat kombinasi huruf, angka, dan simbol khusus</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Kata Sandi<span class="required-star">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control <?= hasError('password_confirm', $error_fields) ? 'has-error' : '' ?>" placeholder="Ulangi masukan kata sandi" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('inputPasswordConfirm', this)" title="Lihat password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="field-error" id="passwordMatchError">Konfirmasi sandi tidak cocok dengan sandi utama!</div>
                        </div>
                    </div>

                    <!-- KONTAK -->
                    <div class="form-section-title"><i class="bi bi-telephone-fill me-2"></i>Informasi Kontak Handphone</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon Seluler<span class="required-star">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" name="no_hp" id="inputHP" class="form-control <?= hasError('no_hp', $error_fields) ? 'has-error' : '' ?>" placeholder="87871438459" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                            </div>
                            <div class="hint-text">Hanya angka numerik murni tanpa awalan +62 atau 0 (9 - 13 digit)</div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex gap-3 justify-content-end pt-4" style="border-top: 1px solid var(--border-color);">
                        <a href="index.php" class="btn-outline-pink">Batal</a>
                        <button type="submit" name="tambah_karyawan" class="btn-reg-header"><i class="bi bi-check-lg"></i> Simpan Data</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL LIHAT BIODATA OWNER (SINKRON DASHBOARD) -->
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
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= $d_profile['tanggal_lahir'] ? $d_profile['tanggal_lahir']->format('d M Y') : '-' ?></span>
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
        <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius: 14px;">Edit Profil Anda ⚙</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL GANTI PROFIL OWNER (SINKRON DASHBOARD) -->
<div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(216, 63, 103, 0.25); background: rgba(255, 255, 255, 0.95);">
      <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Owner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 pb-4 pt-3">
        <p class="text-muted small mb-4" style="line-height: 1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat. Data yang diubah akan langsung disinkronkan ke seluruh sistem harian SpotLight.</p>
        <form method="POST" enctype="multipart/form-data">
          <div class="text-center mb-4">
            <div class="d-inline-block position-relative">
              <div class="profile-preview-box mx-auto">
                <img id="profile-preview-modal" src="<?= $foto_owner_src ?>" alt="Foto Profil">
              </div>
              <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
              <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom: -10px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 0.75rem; padding: 5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label>
            <input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
            <input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Alamat Email<span class="required-star">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
            <input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: 08xxxxxxxxxx" value="<?= htmlspecialchars($d_profile['no_hp']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Alamat Lengkap<span class="required-star">*</span></label>
            <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize: none;"><?= htmlspecialchars($d_profile['alamat']) ?></textarea>
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

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
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

    // PROFIL OWNER MODAL TRIGGERS
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

    // VISIBILITAS PASSWORD TOGGLE
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
            btn.title = 'Sembunyikan password';
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
            btn.title = 'Lihat password';
        }
    }

    // SELECT RADIO BUTTON GENDER
    function selectRadio(label, hiddenId, value) {
        document.querySelectorAll('.radio-option').forEach(el => el.classList.remove('active'));
        label.classList.add('active');
        label.querySelector('input[type="radio"]').checked = true;
        document.getElementById(hiddenId).value = value;
        updatePreview();
    }

    // PREVIEW FOTO KARYAWAN
    function previewFoto(input) {
        const avatar = document.getElementById('previewAvatar');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) { avatar.innerHTML = '<img src="' + e.target.result + '">'; };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // UPDATE PREVIEW CARD SECARA LIVE
    function updatePreview() {
        document.getElementById('previewNama').textContent = document.getElementById('inputNama').value || 'Nama Karyawan';
        const role = document.getElementById('inputRole').value;
        const roleEl = document.getElementById('previewRole');
        if (role) { roleEl.textContent = role; }
        else { roleEl.textContent = 'Pilih Peran'; }
        
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
        const jk = document.querySelector('input[name="jenis_kelamin"]:checked');
        document.getElementById('previewJK').textContent = jk ? jk.value : '-';
        const hp = document.getElementById('inputHP').value;
        document.getElementById('previewHP').textContent = hp ? '+62' + hp : '-';
        document.getElementById('previewEmail').textContent = document.getElementById('inputEmail').value || '-';
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
    ['inputNama', 'inputNIK', 'inputRole', 'inputHP', 'inputEmail'].forEach(id => {
        document.getElementById(id).addEventListener('input', updatePreview);
    });
    document.querySelectorAll('input[name="jenis_kelamin"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    document.getElementById('inputDOB').addEventListener('change', updateUmur);

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
        bar.className = 'password-strength';
        if (strength === 1) bar.classList.add('strength-weak');
        else if (strength === 2) bar.classList.add('strength-medium');
        else if (strength === 3) bar.classList.add('strength-strong');
        this.classList.toggle('is-valid', strength === 3);
    });
    
    document.getElementById('inputPasswordConfirm').addEventListener('input', function() {
        const match = this.value === document.getElementById('inputPassword').value && this.value !== '';
        this.classList.toggle('is-valid', match);
        this.classList.toggle('is-invalid', this.value.length > 0 && !match);
        document.getElementById('passwordMatchError').style.display = this.value.length > 0 && !match ? 'block' : 'none';
    });
    
    document.getElementById('inputHP').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);
        this.classList.toggle('is-valid', this.value.length >= 9);
    });

    // VALIDASI SUBMIT FORMULIR
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        const pass = document.getElementById('inputPassword').value;
        const confirm = document.getElementById('inputPasswordConfirm').value;
        const dob = document.getElementById('inputDOB').value;
        
        if (pass !== confirm) { 
            e.preventDefault(); 
            Swal.fire({ 
                icon: 'error', 
                title: 'Password Tidak Cocok!', 
                text: 'Kata sandi konfirmasi tidak sesuai dengan kata sandi utama.', 
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

    // LIVE PREVIEW FOTO PROFIL OWNER MODAL
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

    // VALIDASI INPUT TEXT MODAL
    const inputNamaModal = document.getElementById('inputNamaModal');
    if (inputNamaModal) {
        inputNamaModal.addEventListener('input', function() {
            this.value = this.value.replace(/a-zA-Z ]/g, '');
        });
    }

    const inputUsernameModal = document.getElementById('inputUsernameModal');
    if (inputUsernameModal) {
        inputUsernameModal.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    }

    // MASKING TELEPHONE MODAL
    const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62 ';
    function moveCursorToEndModal() { if (inputHPModal.selectionStart < prefix.length) { if (inputHPModal.setSelectionRange) inputHPModal.setSelectionRange(prefix.length, prefix.length); } }
    if (inputHPModal) {
        inputHPModal.addEventListener('mousedown', () => setTimeout(moveCursorToEndModal, 1));
        inputHPModal.addEventListener('focus', moveCursorToEndModal);
        inputHPModal.addEventListener('keyup', moveCursorToEndModal);
        inputHPModal.addEventListener('keydown', function(e) { if (this.selectionStart <= prefix.length && (e.keyCode === 8 || e.keyCode === 46)) { e.preventDefault(); } });
        inputHPModal.addEventListener('input', function() {
            if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
            let digits = this.value.split(prefix)[1].replace(/[^0-9]/g, '');
            if (digits.length > 13) digits = digits.slice(0, 13);
            this.value = prefix + digits;
        });
    }

    // TOGGLE PASSWORD EYE VISIBILITY MODAL
    function setupPasswordToggleModal(buttonId, inputId) {
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
    setupPasswordToggleModal('btnToggleBaru', 'pass_baru_modal');
    setupPasswordToggleModal('btnToggleKonf', 'pass_konf_modal');
</script>

<!-- SWEETALERT NOTIFIKASI ERROR CRUD -->
<?php if ($error_crud != ""): ?>
<script>
    Swal.fire({ 
        icon: 'error', 
        title: 'Gagal Menyimpan! ❌', 
        html: '<?= str_replace("\n", "<br>", addslashes($error_crud)) ?>', 
        confirmButtonColor: '#d83f67' 
    });
</script>
<?php endif; ?>

<!-- SWEETALERT NOTIFIKASI PROFIL OWNER -->
<?php if(isset($success_profile) && $success_profile === true): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Profil Diperbarui! 🎉',
        text: 'Informasi profil Anda berhasil disinkronkan ke seluruh sistem SpotLight.',
        confirmButtonColor: '#d83f67',
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