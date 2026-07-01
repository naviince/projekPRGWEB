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
// PROTEKSI
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php"); exit();
}
$id_owner = $_SESSION['id_user'];
$username_session = $_SESSION['username'] ?? 'system';

$q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = safe_sqlsrv_fetch($q_profile);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';

// =====================================================
// FUNGSI VALIDASI
// =====================================================
function validateEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false && strpos($email, '.') !== false; }
function validatePassword($pass) { return strlen($pass) >= 8 && preg_match('/[A-Za-z]/', $pass) && preg_match('/[0-9]/', $pass) && preg_match('/[^A-Za-z0-9]/', $pass); }
function validatePhone($hp) { return preg_match('/^\+62[0-9]{9,13}$/', $hp); } // +62 + 9-13 digit = 12-16 total
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
// PROSES TAMBAH
// =====================================================
$error_crud = "";
$error_fields = array(); // Track which fields have errors

if (isset($_POST['tambah_karyawan'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_crud = "Token keamanan tidak valid! Silakan refresh halaman.";
    } else {
        $nik = sanitizeInput($_POST['nik'] ?? ''); $nama = sanitizeInput($_POST['nama'] ?? ''); $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? ''); $pass = $_POST['password'] ?? ''; $pass_confirm = $_POST['password_confirm'] ?? '';
        $jk = $_POST['jenis_kelamin'] ?? ''; $dob = trim($_POST['tanggal_lahir'] ?? ''); $role = $_POST['role_karyawan'] ?? '';
        $hp_raw = trim($_POST['no_hp'] ?? ''); $hp = '+62' . $hp_raw; // Tambah +62 prefix
        $alamat = sanitizeInput($_POST['alamat'] ?? '');
        $umur = !empty($dob) && validateTanggal($dob) ? hitungUmur($dob) : 0;

        $errors = array();

        // Validasi per field dengan pesan spesifik
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

        // Cek duplikat menggunakan Stored Procedure sp_CekDuplikatKaryawan
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

        // Cek Owner hanya 1
        if (empty($errors) && $role === 'Owner') {
            $owner_count = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0");
            if ($owner_count > 0) { $errors[] = "Sudah ada Owner dalam sistem! Hanya boleh 1 Owner."; $error_fields['role_karyawan'] = true; }
        }

        // Upload foto
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
                    // Cek apakah GD library tersedia
                    if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
                        // Resize dengan GD
                        list($width, $height) = $img_info; $new_width = 300; $new_height = 300;
                        $thumb = imagecreatetruecolor($new_width, $new_height);
                        if ($mime_type == 'image/png') { $source = imagecreatefrompng($file_tmp); imagealphablending($thumb, false); imagesavealpha($thumb, true); }
                        else $source = imagecreatefromjpeg($file_tmp);
                        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        if ($mime_type == 'image/png') imagepng($thumb, $upload_path, 8); else imagejpeg($thumb, $upload_path, 85);
                        imagedestroy($thumb); imagedestroy($source);
                    } else {
                        // Fallback: upload langsung tanpa resize
                        if (!move_uploaded_file($file_tmp, $upload_path)) {
                            $errors[] = "Gagal mengupload foto!";
                            $foto_profil = 'default.jpg';
                        }
                    }
                }
            }
        }

        // AKSI SIMPAN DENGAN STORED PROCEDURE sp_InsertKaryawan
        if (empty($errors)) {
            $pass_hash = password_hash($pass, PASSWORD_BCRYPT);
            
            // Format pemanggilan Stored Procedure SQL Server
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

// Helper untuk mengecek apakah field punya error
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
            --p-pink: #d83f67; --d-pink: #c73165; --s-pink: #fff5f6;
            --text-dark: #1e1e24; --text-muted: #718096; --border-color: #f1f5f9;
            --sidebar-bg: #ffffff; --body-bg: #fafbfc; --transition: all 0.3s ease;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 6px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; color: #4a5568; font-weight: 600; text-decoration: none; border-radius: 10px; font-size: 0.88rem; transition: var(--transition); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--s-pink); color: var(--p-pink); }
        .submenu { list-style: none; padding-left: 16px; margin-top: 4px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 7px 16px; color: #718096; font-weight: 500; font-size: 0.82rem; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(216,63,103,0.03); }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: var(--transition); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216,63,103,0.2); }

        /* MAIN */
        .main-content { margin-left: 260px; padding: 30px 35px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }

        /* BREADCRUMB */
        .breadcrumb-custom { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 6px; }
        .breadcrumb-custom a { color: var(--p-pink); text-decoration: none; font-weight: 600; }
        .breadcrumb-custom .current { color: var(--text-dark); font-weight: 700; }

        /* BUTTONS */
        .btn-reg-header { background: var(--p-pink) !important; color: #ffffff !important; border-radius: 12px !important; padding: 10px 24px !important; font-weight: 700 !important; border: none !important; box-shadow: 0 4px 12px rgba(216,63,103,0.2) !important; transition: var(--transition) !important; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; text-decoration: none; }
        .btn-reg-header:hover { background: var(--d-pink) !important; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(216,63,103,0.3) !important; }
        .btn-outline-pink { background: #ffffff; color: var(--p-pink); border: 1px solid var(--p-pink); border-radius: 12px; padding: 10px 24px; font-weight: 700; font-size: 0.85rem; transition: var(--transition); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-outline-pink:hover { background: var(--s-pink); transform: translateY(-2px); }

        /* LANDSCAPE */
        .landscape-wrapper { display: flex; gap: 25px; align-items: flex-start; }
        .landscape-left { width: 280px; flex-shrink: 0; }
        .landscape-right { flex: 1; min-width: 0; }

        /* PREVIEW CARD */
        .preview-card { background: #ffffff; border-radius: 20px; border: 1px solid var(--border-color); padding: 30px; text-align: center; position: sticky; top: 30px; }
        .preview-avatar { width: 120px; height: 120px; border-radius: 50%; background: var(--s-pink); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: var(--p-pink); font-size: 3rem; overflow: hidden; border: 4px solid #ffffff; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .preview-name { font-weight: 800; font-size: 1.1rem; color: var(--text-dark); margin-bottom: 4px; }
        .preview-role { display: inline-block; padding: 4px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; margin-bottom: 20px; }
        .preview-info { text-align: left; margin-top: 20px; }
        .preview-info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color); font-size: 0.82rem; }
        .preview-info-item:last-child { border-bottom: none; }
        .preview-info-label { color: var(--text-muted); font-weight: 500; }
        .preview-info-value { color: var(--text-dark); font-weight: 700; }
        .foto-upload-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: 1.5px dashed var(--p-pink); color: var(--p-pink); font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: var(--transition); background: var(--s-pink); margin-top: 12px; }
        .foto-upload-btn:hover { background: var(--p-pink); color: #ffffff; }

        /* FORM */
        .form-card { background: #ffffff; border-radius: 20px; border: 1px solid var(--border-color); padding: 30px; }
        .form-section-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid var(--border-color); }
        .form-label { font-weight: 700; font-size: 0.78rem; color: var(--text-dark); margin-bottom: 5px; display: block; }
        .required-star { color: #ef4444; margin-left: 2px; }
        .form-control, .form-select { border-radius: 10px; border: 1.5px solid var(--border-color); padding: 10px 14px; font-size: 0.85rem; font-weight: 500; transition: var(--transition); background: #ffffff; }
        .form-control:focus, .form-select:focus { border-color: var(--p-pink); box-shadow: 0 0 0 3px rgba(216,63,103,0.08); outline: none; }
        .form-control.is-valid { border-color: #10b981; }
        .form-control.is-invalid { border-color: #ef4444; }
        .form-control.has-error { border-color: #ef4444; background-color: #fef2f2; }

        /* PASSWORD WITH EYE ICON */
        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right: 40px; }
        .password-toggle {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--text-muted); cursor: pointer;
            font-size: 1rem; padding: 4px; transition: var(--transition);
        }
        .password-toggle:hover { color: var(--p-pink); }

        /* RADIO BUTTON */
        .radio-group { display: flex; gap: 12px; }
        .radio-option {
            flex: 1; display: flex; align-items: center; gap: 8px; padding: 12px 16px;
            border-radius: 12px; border: 1.5px solid var(--border-color); cursor: pointer;
            transition: var(--transition); background: #ffffff;
        }
        .radio-option:hover { border-color: var(--p-pink); background: var(--s-pink); }
        .radio-option.active { border-color: var(--p-pink); background: var(--s-pink); }
        .radio-option input[type="radio"] { display: none; }
        .radio-option .radio-icon { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; transition: var(--transition); }
        .radio-option.active .radio-icon { border-color: var(--p-pink); background: var(--p-pink); }
        .radio-option .radio-icon::after { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #ffffff; opacity: 0; transition: var(--transition); }
        .radio-option.active .radio-icon::after { opacity: 1; }
        .radio-option .radio-text { font-weight: 600; font-size: 0.85rem; color: var(--text-dark); }
        .radio-option.active .radio-text { color: var(--p-pink); }

        /* INPUT GROUP */
        .input-group-text { background: var(--s-pink); border: 1.5px solid var(--border-color); border-right: none; border-radius: 10px 0 0 10px; color: var(--p-pink); font-weight: 600; font-size: 0.82rem; }
        .input-group .form-control { border-left: none; border-radius: 0 10px 10px 0; }

        /* PASSWORD STRENGTH */
        .password-strength { height: 3px; border-radius: 2px; margin-top: 6px; transition: var(--transition); }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        .hint-text { font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; }

        /* ERROR MESSAGE */
        .field-error { font-size: 0.75rem; color: #ef4444; margin-top: 4px; display: none; }
        .form-control.has-error ~ .field-error { display: block; }

        /* RESPONSIVE */
        @media (max-width: 1200px) { .landscape-wrapper { flex-direction: column; } .landscape-left { width: 100%; position: static; } }
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
                    <i class="bi bi-chevron-up small" style="transform: rotate(180deg);"></i>
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
                    <i class="bi bi-chevron-down small"></i>
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
    <div><button onclick="confirmLogout(event)" class="btn btn-logout"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom">
        <a href="index.php">Kelola Karyawan</a>
        <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
        <span class="current">Tambah Karyawan</span>
    </div>

    <!-- HEADER -->
    <div class="dashboard-header">
        <div>
            <h4 class="fw-bold mb-1">Tambah Karyawan Baru</h4>
            <p class="text-muted small mb-0" style="font-size: 0.82rem;">Daftarkan staf baru ke sistem SpotLight Studio</p>
        </div>
        <a href="index.php" class="btn-outline-pink"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <!-- LANDSCAPE LAYOUT -->
    <div class="landscape-wrapper">

        <!-- LEFT PANEL - PREVIEW -->
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
                    <i class="bi bi-camera"></i> Upload Foto
                </label>
            </div>
        </div>

        <!-- RIGHT PANEL - FORM -->
        <div class="landscape-right">
            <form method="POST" enctype="multipart/form-data" id="formTambah" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="file" name="foto_profil" id="inputFoto" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="previewFoto(this)">

                <div class="form-card">
                    <!-- DATA PRIBADI -->
                    <div class="form-section-title"><i class="bi bi-person me-2"></i>Data Pribadi</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">NIK<span class="required-star">*</span></label>
                            <input type="text" name="nik" id="inputNIK" class="form-control <?= hasError('nik', $error_fields) ? 'has-error' : '' ?>" placeholder="3175091234567890" value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" maxlength="16" required>
                            <div class="hint-text">16 digit angka (format Indonesia)</div>
                            <div class="field-error">NIK tidak valid</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama Lengkap<span class="required-star">*</span></label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= hasError('nama', $error_fields) ? 'has-error' : '' ?>" placeholder="Nama lengkap" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir<span class="required-star">*</span></label>
                            <input type="date" name="tanggal_lahir" id="inputDOB" class="form-control <?= hasError('tanggal_lahir', $error_fields) ? 'has-error' : '' ?>" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>" required>
                            <div class="hint-text">Format: YYYY-MM-DD (contoh: 2005-12-01)</div>
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
                            <label class="form-label">Alamat<span class="required-star">*</span></label>
                            <input type="text" name="alamat" id="inputAlamat" class="form-control <?= hasError('alamat', $error_fields) ? 'has-error' : '' ?>" placeholder="Alamat domisili lengkap" value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- AKUN -->
                    <div class="form-section-title"><i class="bi bi-shield-lock me-2"></i>Akun Sistem</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Username<span class="required-star">*</span></label>
                            <input type="text" name="username" id="inputUsername" class="form-control <?= hasError('username', $error_fields) ? 'has-error' : '' ?>" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            <div class="hint-text">Huruf, angka, underscore</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email<span class="required-star">*</span></label>
                            <input type="email" name="email" id="inputEmail" class="form-control <?= hasError('email', $error_fields) ? 'has-error' : '' ?>" placeholder="nama@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peran (Role)<span class="required-star">*</span></label>
                            <select name="role_karyawan" id="inputRole" class="form-select <?= hasError('role_karyawan', $error_fields) ? 'has-error' : '' ?>" required>
                                <option value="" disabled <?= empty($_POST['role_karyawan']) ? 'selected' : '' ?>>Pilih peran</option>
                                <option value="Admin" <?= ($_POST['role_karyawan'] ?? '') == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Fotografer" <?= ($_POST['role_karyawan'] ?? '') == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                                <option value="Owner" <?= ($_POST['role_karyawan'] ?? '') == 'Owner' ? 'selected' : '' ?>>Owner</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password<span class="required-star">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="inputPassword" class="form-control <?= hasError('password', $error_fields) ? 'has-error' : '' ?>" placeholder="Minimal 8 karakter" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('inputPassword', this)" title="Lihat password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="hint-text">Minimal 8 karakter, huruf + angka + simbol</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Password<span class="required-star">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control <?= hasError('password_confirm', $error_fields) ? 'has-error' : '' ?>" placeholder="Ulangi password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('inputPasswordConfirm', this)" title="Lihat password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="field-error" id="passwordMatchError">Password tidak cocok!</div>
                        </div>
                    </div>

                    <!-- KONTAK -->
                    <div class="form-section-title"><i class="bi bi-telephone me-2"></i>Kontak</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" name="no_hp" id="inputHP" class="form-control <?= hasError('no_hp', $error_fields) ? 'has-error' : '' ?>" placeholder="87871438459" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                            </div>
                            <div class="hint-text">Isi angka saja tanpa +62 (9-13 digit)</div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex gap-3 justify-content-end pt-3" style="border-top: 1px solid var(--border-color);">
                        <a href="index.php" class="btn-outline-pink">Batal</a>
                        <button type="submit" name="tambah_karyawan" class="btn-reg-header"><i class="bi bi-check-lg"></i> Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    // SUBMENU
    document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.dataset.target);
            const isShown = target.classList.contains('show');
            document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
            if (!isShown) target.classList.add('show');
        });
    });

    // TOGGLE PASSWORD VISIBILITY
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

    // RADIO BUTTON SELECT
    function selectRadio(label, hiddenId, value) {
        document.querySelectorAll('.radio-option').forEach(el => el.classList.remove('active'));
        label.classList.add('active');
        label.querySelector('input[type="radio"]').checked = true;
        document.getElementById(hiddenId).value = value;
        updatePreview();
    }

    // PREVIEW FOTO
    function previewFoto(input) {
        const avatar = document.getElementById('previewAvatar');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) { avatar.innerHTML = '<img src="' + e.target.result + '">'; };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // UPDATE PREVIEW REAL-TIME
    function updatePreview() {
        document.getElementById('previewNama').textContent = document.getElementById('inputNama').value || 'Nama Karyawan';
        const role = document.getElementById('inputRole').value;
        const roleEl = document.getElementById('previewRole');
        if (role) { roleEl.textContent = role; }
        else { roleEl.textContent = 'Pilih Peran'; }
        const roleColors = { 'Admin': ['#eff6ff', '#2563eb'], 'Fotografer': ['var(--s-pink)', 'var(--p-pink)'], 'Owner': ['#f5f3ff', '#8b5cf6'] };
        if (roleColors[role]) { roleEl.style.background = roleColors[role][0]; roleEl.style.color = roleColors[role][1]; }
        document.getElementById('previewNIK').textContent = document.getElementById('inputNIK').value || '-';
        const jk = document.querySelector('input[name="jenis_kelamin"]:checked');
        document.getElementById('previewJK').textContent = jk ? jk.value : '-';
        const hp = document.getElementById('inputHP').value;
        document.getElementById('previewHP').textContent = hp ? '+62' + hp : '-';
        document.getElementById('previewEmail').textContent = document.getElementById('inputEmail').value || '-';
    }

    // PREVIEW UMUR
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

    // ATTACH LISTENERS
    ['inputNama', 'inputNIK', 'inputRole', 'inputHP', 'inputEmail'].forEach(id => {
        document.getElementById(id).addEventListener('input', updatePreview);
    });
    document.querySelectorAll('input[name="jenis_kelamin"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    document.getElementById('inputDOB').addEventListener('change', updateUmur);

    // VALIDASI REAL-TIME
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
    document.getElementById('inputPassword').addEventListener('input', function() {
        const val = this.value; let strength = 0;
        if (val.length >= 8) strength++; if (/[A-Za-z]/.test(val) && /[0-9]/.test(val)) strength++; if (/[^A-Za-z0-9]/.test(val)) strength++;
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

    // FORM SUBMIT
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        const pass = document.getElementById('inputPassword').value;
        const confirm = document.getElementById('inputPasswordConfirm').value;
        const dob = document.getElementById('inputDOB').value;
        if (pass !== confirm) { e.preventDefault(); Swal.fire({ icon: 'error', title: 'Password Tidak Cocok!', text: 'Password dan konfirmasi harus sama.', confirmButtonColor: '#d83f67' }); return false; }
        if (dob) { const birth = new Date(dob); const today = new Date(); let age = today.getFullYear() - birth.getFullYear(); const m = today.getMonth() - birth.getMonth(); if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--; if (age < 17 || age > 60) { e.preventDefault(); Swal.fire({ icon: 'error', title: 'Umur Tidak Valid!', text: 'Umur harus 17-60 tahun. Saat ini: ' + age + ' tahun.', confirmButtonColor: '#d83f67' }); return false; }}
        return true;
    });

    function confirmLogout(e) { e.preventDefault(); Swal.fire({ title: 'Keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = '../../logout.php'; }); }
    function confirmLandingPage(e) { e.preventDefault(); Swal.fire({ title: 'Kembali?', icon: 'info', showCancelButton: true, confirmButtonColor: '#d83f67', cancelButtonColor: '#718096', confirmButtonText: 'Ya', cancelButtonText: 'Batal' }).then(r => { if (r.isConfirmed) window.location = '../../index.php'; }); }
</script>

<?php if ($error_crud != ""): ?>
<script>Swal.fire({ icon: 'error', title: 'Gagal Menyimpan!', html: '<?= str_replace("\n", "<br>", addslashes($error_crud)) ?>', confirmButtonColor: '#d83f67' });</script>
<?php endif; ?>
</body>
</html>