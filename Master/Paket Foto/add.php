<?php
ob_start();
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// =====================================================
// PROFIL ADMIN (Sinkron penuh dengan index.php)
// =====================================================
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_admin = $d_profile['nama_karyawan'] ?? 'Admin';
$username_admin = $d_profile['username_karyawan'] ?? 'admin';
$email_admin = $d_profile['email_karyawan'] ?? 'admin@spotlight.com';
$foto_admin = $d_profile['foto_profil'] ?? 'default.jpg';

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;

$error_profile = "";
$success_profile = false;

if (isset($_POST['update_profil'])) {
    $nama_input = trim($_POST['nama']);
    $username_input = trim($_POST['username']);
    $email_input = trim($_POST['email']);
    $no_hp_input = str_replace(' ', '', trim($_POST['no_hp']));
    $alamat_input = trim($_POST['alamat']);
    $pass_baru = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $hp_bersih_input = str_replace(['+', ' '], '', $no_hp_input);

    if (empty($nama_input) || !preg_match("/^[a-zA-Z ]*$/", $nama_input)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf!";
    } elseif (empty($username_input) || !preg_match("/^[a-zA-Z0-9_]*$/", $username_input)) {
        $error_profile = "Nama pengguna tidak valid!";
    } elseif (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error_profile = "Email tidak valid!";
    } elseif (empty($no_hp_input) || substr($no_hp_input, 0, 3) !== '+62' || !ctype_digit($hp_bersih_input) || strlen($no_hp_input) < 12 || strlen($no_hp_input) > 16) {
        $error_profile = "Nomor telepon tidak valid! Harus diawali dengan +62, berisi angka, dan panjang total 12-16 karakter.";
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
                $file_tmp = $_FILES['foto_profil']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                if (!in_array($file_ext, $allowed_ext)) {
                    $error_profile = "Format foto profil harus JPG, JPEG, atau PNG!";
                } elseif ($file_size > 2097152) {
                    $error_profile = "Ukuran foto profil maksimal 2MB!";
                } else {
                    $foto_baru = "admin_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/karyawan/";
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
                    $foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
                        ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;
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
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_fetch] SQL Error: " . json_encode(sqlsrv_errors()));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

// =====================================================
// PROSES SUBMIT PAKET
// =====================================================
$errors = [];
$old_values = $_POST ?? [];
$success = false;
$error = ""; // Alert umum (disamakan dengan pola Tema Foto)

if (isset($_POST['simpan'])) {
    $nama_paket = isset($_POST['nama_paket']) ? trim($_POST['nama_paket']) : '';
    $durasi_waktu = isset($_POST['durasi_waktu']) ? (int)$_POST['durasi_waktu'] : 0;
    $harga_paket = isset($_POST['harga_paket']) ? (float)$_POST['harga_paket'] : 0;
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $kapasitas_orang = isset($_POST['kapasitas_orang']) ? (int)$_POST['kapasitas_orang'] : 0;

    if (empty($nama_paket)) {
        $errors['nama_paket'] = "Nama paket wajib diisi!";
    } elseif (strlen($nama_paket) > 100) {
        $errors['nama_paket'] = "Nama paket maksimal 100 karakter!";
    } else {
        $sql_dup = "SELECT COUNT(*) AS Total FROM Paket_Foto WHERE Nama_Paket = ? AND Is_Deleted = 0";
        $stmt_dup = sqlsrv_query($conn, $sql_dup, array($nama_paket));
        if ($stmt_dup !== false) {
            $row_dup = sqlsrv_fetch_array($stmt_dup, SQLSRV_FETCH_ASSOC);
            if ($row_dup && ($row_dup['Total'] ?? 0) > 0) {
                $errors['nama_paket'] = "Nama paket sudah digunakan!";
            }
            sqlsrv_free_stmt($stmt_dup);
        }
    }

    if ($durasi_waktu <= 0) {
        $errors['durasi_waktu'] = "Durasi waktu harus lebih dari 0!";
    } elseif ($durasi_waktu < 10) {
        $errors['durasi_waktu'] = "Durasi waktu minimal 10 menit!";
    }

    if ($harga_paket < 0) {
        $errors['harga_paket'] = "Harga paket tidak boleh negatif!";
    }

    if ($kapasitas_orang <= 0) {
        $errors['kapasitas_orang'] = "Kapasitas orang harus lebih dari 0!";
    }

    $foto_paket = 'default_paket.jpg';
    if (empty($errors) && isset($_FILES['foto_paket']) && $_FILES['foto_paket']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_paket'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            $errors['foto_paket'] = "Format file harus JPG, JPEG, PNG, atau WEBP!";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['foto_paket'] = "Ukuran file maksimal 2MB!";
        } else {
            $upload_dir = '../../assets/img/paket/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $new_filename = 'paket_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $foto_paket = $new_filename;
            } else {
                $errors['foto_paket'] = "Gagal mengupload foto paket!";
            }
        }
    }

    if (empty($errors)) {
        if (!sqlsrv_begin_transaction($conn)) {
            $errors['general'] = "Gagal memulai transaksi database!";
        } else {
            $sql_ins_paket = "{CALL sp_InsertPaketFoto(?, ?, ?, ?, ?, ?, ?)}";
            $params_ins_paket = [
                $nama_paket,
                $durasi_waktu,
                $harga_paket,
                !empty($deskripsi) ? $deskripsi : null,
                $kapasitas_orang,
                $foto_paket,
                $nama_admin
            ];

            $stmt_ins_paket = sqlsrv_query($conn, $sql_ins_paket, $params_ins_paket);

            if ($stmt_ins_paket) {
                sqlsrv_next_result($stmt_ins_paket);

                $row_id = sqlsrv_fetch_array($stmt_ins_paket, SQLSRV_FETCH_ASSOC);
                $new_paket_id = $row_id['ID_Paket'] ?? null;

                if ($new_paket_id) {
                    sqlsrv_commit($conn);
                    $success = true;
                    $old_values = [];
                } else {
                    sqlsrv_rollback($conn);
                    $errors['general'] = "Sistem gagal mengidentifikasi ID paket baru!";
                    if ($foto_paket != 'default_paket.jpg' && file_exists('../../assets/img/paket/' . $foto_paket)) {
                        @unlink('../../assets/img/paket/' . $foto_paket);
                    }
                }
                sqlsrv_free_stmt($stmt_ins_paket);
            } else {
                sqlsrv_rollback($conn);
                $errors['general'] = "Gagal menyimpan paket foto ke dalam database. Silakan periksa kembali data Anda!";
                if ($foto_paket != 'default_paket.jpg' && file_exists('../../assets/img/paket/' . $foto_paket)) {
                    @unlink('../../assets/img/paket/' . $foto_paket);
                }
            }
        }
    }

    // Gabungkan semua error field menjadi satu alert seperti pola Tema Foto
    if (!empty($errors)) {
        $error = reset($errors);
    }
}
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Paket Foto – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
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

        .breadcrumb-custom {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 25px; font-size: 0.85rem; font-weight: 600;
        }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }
        .breadcrumb-custom i { color: #cbd5e1; font-size: 0.7rem; }

        .form-card {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            overflow: hidden;
        }
        .form-card-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            padding: 30px 40px;
            color: #ffffff;
        }
        .form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
        .form-card-body { padding: 40px; }

        .form-label {
            font-weight: 700; font-size: 0.75rem;
            color: var(--text-dark); text-transform: uppercase;
            letter-spacing: 0.8px; margin-bottom: 8px;
            display: block;
        }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control-custom, .form-select-custom {
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 14px 18px; font-weight: 600; font-size: 0.9rem;
            color: #1e293b; transition: var(--transition-3d); background: #ffffff;
        }
        .form-control-custom:focus, .form-select-custom:focus { 
            outline: none; border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); 
        }
        .form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
        .form-control-custom.is-invalid { border-color: #ef4444; background: #fef2f2; }
        textarea.form-control-custom { min-height: 100px; resize: vertical; }

        .input-hint {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 600;
            margin-top: 6px; display: flex; align-items: center; gap: 4px;
        }
        .error-text { color: #ef4444; font-size: 0.8rem; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }

        /* INPUT DENGAN PREFIX (Rp) */
        .input-group-custom { display: flex; align-items: center; gap: 10px; }
        .input-group-custom .input-prefix {
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink)); color: var(--p-pink);
            padding: 14px 16px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; border: 2px solid var(--light-pink);
        }
        .input-group-custom .form-control-custom { flex: 1; }

        /* FILE UPLOAD (drag & drop, disamakan dengan Tema Foto) */
        .file-upload-zone {
            border: 2px dashed #e2e8f0; border-radius: 16px;
            padding: 30px; text-align: center;
            transition: var(--transition-3d); cursor: pointer;
            background: #f8fafc;
        }
        .file-upload-zone:hover, .file-upload-zone.dragover {
            border-color: var(--p-pink); background: var(--s-pink);
        }
        .file-upload-zone i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }
        .file-upload-zone p { font-size: 0.9rem; color: #64748b; font-weight: 600; margin: 0; }
        .file-upload-zone small { font-size: 0.75rem; color: #94a3b8; }
        .file-upload-zone input[type="file"] { display: none; }

        #preview-container {
            display: none; margin-top: 16px;
            position: relative; border-radius: 14px; overflow: hidden;
            border: 2px solid var(--light-pink);
        }
        #preview-container img {
            width: 100%; max-height: 250px; object-fit: cover; display: block;
        }
        #preview-container .remove-preview {
            position: absolute; top: 10px; right: 10px;
            background: rgba(220, 38, 38, 0.9); color: #fff;
            border: none; border-radius: 50%; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.85rem; transition: all 0.2s;
        }
        #preview-container .remove-preview:hover { background: #dc2626; transform: scale(1.1); }

        /* BUTTONS */
        .btn-submit {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 14px 32px; font-weight: 800; font-size: 0.95rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35);
            color: #ffffff;
        }
        .btn-batal {
            background: #f1f5f9; color: #475569; border: none;
            border-radius: 14px; padding: 14px 32px;
            font-weight: 800; font-size: 0.95rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-batal:hover {
            background: #e2e8f0; color: #1e293b;
            transform: translateY(-3px);
        }

        /* ALERT */
        .alert-custom {
            background: #fef2f2; border: none;
            border-left: 4px solid #dc2626; border-radius: 12px;
            color: #991b1b; font-size: 0.85rem; padding: 14px 18px;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .alert-custom i { font-size: 1.1rem; }

        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-grid-3 { grid-template-columns: 1fr; } }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        /* card-3d hanya dipakai di dalam modal biodata (elemen non-klik -> tetap datar) */
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); }

        /* Modal profil (sinkron index.php) */
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-control, .form-select {
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6;
            background: #f8fafc; font-size: 14px; font-weight: 600; transition: var(--transition-3d); color: var(--text-dark);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--p-pink); background: #ffffff;
            transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); outline: none;
        }
        .profile-preview-box {
            width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
            border: 2.5px solid #eef2f6; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition: var(--transition-3d);
        }
        .profile-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .btn-pilih-foto {
            background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink);
            font-weight: 700; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-pilih-foto:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.15); }
        .btn-reg {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 16px;
            padding: 16px; font-weight: 800; border: none; width: 100%; transition: var(--transition-3d);
            margin-top: 15px; font-size: 15px; box-shadow: 0 10px 25px rgba(213, 61, 102, 0.25);
        }
        .btn-reg:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.35); }
        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px; }
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none!important; box-shadow: none!important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #ffffff;
            border: 2px solid var(--light-pink);
            color: var(--p-pink);
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition-3d);
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mobile-menu-btn:hover {
            background: var(--s-pink);
            transform: scale(1.05);
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(30, 30, 36, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99;
            opacity: 0;
            transition: opacity 0.35s ease;
        }
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* =====================================================
           RESPONSIVE ENHANCEMENTS
           ===================================================== */
        @media (max-width: 1199px) {
            .form-card-body { padding: 35px; }
        }

        @media (max-width: 992px) {
            .mobile-menu-btn { display: inline-flex; }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                box-shadow: none;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 10px 0 50px rgba(0,0,0,0.15);
            }
            .main-content { margin-left: 0; padding: 24px; }
            .dashboard-header {
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 28px;
            }
            .dashboard-header h3 { font-size: 1.35rem; }
            .form-card { border-radius: 20px; }
            .form-card-header { padding: 25px 30px; }
            .form-card-header h4 { font-size: 1.2rem; }
            .form-card-body { padding: 30px; }
            .breadcrumb-custom { font-size: 0.8rem; margin-bottom: 20px; }
            .d-flex.gap-3.mt-4 {
                flex-direction: column;
                gap: 10px !important;
            }
            .btn-submit, .btn-batal {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .main-content { padding: 18px; }
            .dashboard-header { margin-bottom: 22px; }
            .dashboard-header h3 { font-size: 1.15rem; }
            .dashboard-header p { font-size: 0.8rem; }
            .form-card { border-radius: 18px; }
            .form-card-header { padding: 22px 24px; }
            .form-card-header h4 { font-size: 1.1rem; }
            .form-card-body { padding: 24px 18px; }
            .form-control-custom, .form-select-custom {
                padding: 12px 14px;
                font-size: 0.85rem;
                border-radius: 12px;
            }
            .form-label { font-size: 0.7rem; }
            .input-hint { font-size: 0.7rem; }
            .form-grid-3 { grid-template-columns: 1fr; gap: 16px; }
            .file-upload-zone {
                padding: 24px 16px;
            }
            .file-upload-zone i {
                font-size: 2rem;
            }
            .file-upload-zone p {
                font-size: 0.85rem;
            }
            .btn-submit, .btn-batal {
                padding: 12px 20px;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            .alert-custom {
                padding: 12px 14px;
                font-size: 0.8rem;
            }
            .breadcrumb-custom {
                flex-wrap: wrap;
                gap: 6px;
                font-size: 0.75rem;
            }
            .profile-header-btn {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 576px) {
            .main-content { padding: 14px; }
            .dashboard-header h3 { font-size: 1.05rem; }
            .form-card { border-radius: 16px; }
            .form-card-header { padding: 18px 20px; }
            .form-card-header h4 { font-size: 1rem; }
            .form-card-body { padding: 20px 14px; }
            .form-control-custom, .form-select-custom {
                padding: 10px 12px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            textarea.form-control-custom { min-height: 80px; }
            .file-upload-zone {
                padding: 20px 14px;
                border-radius: 12px;
            }
            .file-upload-zone i {
                font-size: 1.8rem;
            }
            .file-upload-zone p {
                font-size: 0.8rem;
            }
            .file-upload-zone small {
                font-size: 0.7rem;
            }
            #preview-container img {
                max-height: 180px;
            }
            .btn-submit, .btn-batal {
                padding: 12px 16px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            .modal-dialog { margin: 12px; }
            .modal-content { border-radius: 20px !important; }
            .profile-preview-box { width: 80px; height: 80px; }
            .form-control, .form-select { padding: 10px 14px; font-size: 16px; border-radius: 12px; }
            .btn-reg { padding: 14px; font-size: 14px; }
        }

        @media (max-width: 375px) {
            .dashboard-header h3 { font-size: 0.95rem; }
            .form-card-body { padding: 18px 12px; }
            .btn-submit, .btn-batal {
                padding: 10px 14px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Administrator</span>
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
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
                            <li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
                            <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i>Beranda</span>
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
        <div class="dashboard-header fade-in-up">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Tambah Paket Foto</h3>
                    <p class="text-muted small mb-0">Buat paket layanan foto baru untuk SpotLight Studio.</p>
                </div>
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

        <!-- BREADCRUMB -->
        <div class="breadcrumb-custom">
            <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Data Master</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Paket Foto</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Tambah Paket Foto</span>
        </div>

        <!-- FORM CARD -->
        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-camera-fill me-2"></i>Form Paket Foto Baru</h4>
                <p>Lengkapi informasi paket layanan foto di bawah ini.</p>
            </div>
            <div class="form-card-body">

                <?php if(isset($errors['general']) || $error != ""): ?>
                    <div class="alert-custom">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($errors['general'] ?? $error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="formPaket" enctype="multipart/form-data">

                    <div class="mb-4">
                        <label class="form-label">Nama Paket <span class="required">*</span></label>
                        <input type="text" name="nama_paket" 
                               class="form-control-custom <?= isset($errors['nama_paket']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($old_values['nama_paket'] ?? '') ?>" 
                               placeholder="Contoh: Basic, Couple, Family, Wisuda, Corporate" 
                               maxlength="100" required>
                        <?php if(isset($errors['nama_paket'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['nama_paket'] ?></span>
                        <?php endif; ?>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Nama paket harus unik dan maksimal 100 karakter
                        </div>
                    </div>

                    <div class="form-grid-3 mb-4">
                        <div>
                            <label class="form-label">Durasi Waktu (Menit) <span class="required">*</span></label>
                            <input type="number" name="durasi_waktu"
                                   class="form-control-custom <?= isset($errors['durasi_waktu']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old_values['durasi_waktu'] ?? '') ?>"
                                   placeholder="Contoh: 30, 60, 90, 120" min="10" required>
                            <?php if(isset($errors['durasi_waktu'])): ?>
                                <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['durasi_waktu'] ?></span>
                            <?php endif; ?>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Minimal 10 menit
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Harga Paket (Rp) <span class="required">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-prefix">Rp</span>
                                <input type="number" name="harga_paket" 
                                       class="form-control-custom <?= isset($errors['harga_paket']) ? 'is-invalid' : '' ?>" 
                                       value="<?= htmlspecialchars($old_values['harga_paket'] ?? '') ?>" 
                                       placeholder="250000" min="0" required>
                            </div>
                            <?php if(isset($errors['harga_paket'])): ?>
                                <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['harga_paket'] ?></span>
                            <?php endif; ?>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Biaya utama layanan
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Kapasitas Orang <span class="required">*</span></label>
                            <input type="number" name="kapasitas_orang" 
                                   class="form-control-custom <?= isset($errors['kapasitas_orang']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($old_values['kapasitas_orang'] ?? '') ?>" 
                                   placeholder="Contoh: 2, 5, 8, 20" min="1" required>
                            <?php if(isset($errors['kapasitas_orang'])): ?>
                                <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['kapasitas_orang'] ?></span>
                            <?php endif; ?>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Maksimal orang per sesi
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Deskripsi <span style="color: #94a3b8; font-weight: 500; text-transform: none; letter-spacing: 0;">(opsional)</span></label>
                        <textarea name="deskripsi" 
                                  class="form-control-custom <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>" 
                                  placeholder="Jelaskan detail paket, konsep, atau keunggulan layanan ini..." 
                                  maxlength="255"><?= htmlspecialchars($old_values['deskripsi'] ?? '') ?></textarea>
                        <?php if(isset($errors['deskripsi'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['deskripsi'] ?></span>
                        <?php endif; ?>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Maksimal 255 karakter. Akan ditampilkan di halaman pelanggan
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Foto Paket <span style="color: #94a3b8; font-weight: 500; text-transform: none; letter-spacing: 0;">(opsional)</span></label>
                        <div class="file-upload-zone" id="dropzone" onclick="document.getElementById('fileFoto').click()">
                            <input type="file" name="foto_paket" id="fileFoto" accept="image/jpeg,image/png,image/webp" onchange="handleFileSelect(event)">
                            <i class="bi bi-cloud-arrow-up" id="upload-icon"></i>
                            <p id="upload-text">Klik atau seret foto ke sini (opsional)</p>
                            <small>JPG, JPEG, PNG, WEBP — Maksimal 2MB</small>
                        </div>
                        <div id="preview-container">
                            <img id="preview-img" src="" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview(event)" title="Hapus foto">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <?php if(isset($errors['foto_paket'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['foto_paket'] ?></span>
                        <?php endif; ?>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Jika tidak diisi, akan menggunakan foto default
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="simpan" class="btn-submit">
                            <i class="bi bi-check2-circle"></i> Simpan Paket Foto
                        </button>
                        <a href="list.php" class="btn-batal">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);">
                            <img src="<?= $foto_admin_src ?>" alt="Foto Profil">
                        </div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5>
                        <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Administrator</span>
                    </div>
                    <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                        <div class="row g-3">
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">NIK</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></span></div>
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nama Pengguna</small><span class="fw-bold text-dark" style="font-size:0.85rem;">@<?= htmlspecialchars($username_admin) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($email_admin) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span></div>
                        </div>
                    </div>
                    <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius:14px;">Edit Profil Anda</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GANTI PROFIL -->
    <div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(213,61,102,0.25);background:rgba(255,255,255,0.95);">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <p class="text-muted small mb-4" style="line-height:1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <div class="d-inline-block position-relative">
                                <div class="profile-preview-box mx-auto"><img id="profile-preview-modal" src="<?= $foto_admin_src ?>" alt="Foto Profil"></div>
                                <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                                <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom:-10px;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:0.75rem;padding:5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label><input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label><input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Alamat Email<span class="required-star">*</span></label><input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Nomor Telepon<span class="required-star">*</span></label><input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: +628xxxxxxxxxx" value="<?= htmlspecialchars($d_profile['no_hp'] ?? '') ?>" required></div>
                        <div class="mb-3"><label class="form-label">Alamat Lengkap<span class="required-star">*</span></label><textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize:none;"><?= htmlspecialchars($d_profile['alamat'] ?? '') ?></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Sandi Baru (Opsional)</label><div class="password-group"><input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter"><i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i></div></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Konfirmasi Sandi</label><div class="password-group"><input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru"><i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i></div></div>
                        </div>
                        <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // ===== SIDEBAR TOGGLE (MOBILE) =====
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        document.querySelectorAll('.sidebar .nav-link-custom, .sidebar .submenu-link, .sidebar .btn-logout').forEach(el => {
            el.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('mobile-open')) toggleSidebar();
                }
            });
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // ===== TOGGLE SUBMENU =====
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

        // ===== FILE UPLOAD & PREVIEW (drag & drop) =====
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('preview-container');
            const previewImg = document.getElementById('preview-img');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');

            if (file) {
                if (file.size > 2097152) {
                    Swal.fire({
                        icon: 'error', title: 'Ukuran Terlalu Besar',
                        text: 'Ukuran gambar maksimal 2MB.', confirmButtonColor: '#D53D66'
                    });
                    event.target.value = ''; return;
                }
                const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    Swal.fire({
                        icon: 'error', title: 'Format Tidak Valid',
                        text: 'Format gambar harus JPG, JPEG, PNG, atau WEBP.', confirmButtonColor: '#D53D66'
                    });
                    event.target.value = ''; return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                    uploadIcon.style.display = 'none';
                    uploadText.textContent = file.name;
                };
                reader.readAsDataURL(file);
            }
        }

        function removePreview(e) {
            e.stopPropagation();
            const input = document.getElementById('fileFoto');
            const previewContainer = document.getElementById('preview-container');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');
            input.value = '';
            previewContainer.style.display = 'none';
            uploadIcon.style.display = 'block';
            uploadText.textContent = 'Klik atau seret foto ke sini (opsional)';
        }

        // Drag & Drop
        const dropzone = document.getElementById('dropzone');
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault(); dropzone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('fileFoto').files = files;
                handleFileSelect({ target: { files: files } });
            }
        });

        // ===== VALIDASI FORM SEBELUM SUBMIT =====
        document.getElementById('formPaket').addEventListener('submit', function(e) {
            const nama = document.querySelector('input[name="nama_paket"]').value.trim();
            const durasi = document.querySelector('input[name="durasi_waktu"]').value;
            const harga = document.querySelector('input[name="harga_paket"]').value;
            const kapasitas = document.querySelector('input[name="kapasitas_orang"]').value;

            if (!nama) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Nama Paket Kosong', text: 'Silakan isi nama paket foto.', confirmButtonColor: '#D53D66' });
                return false;
            }
            if (!durasi || durasi < 10) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Durasi Tidak Valid', text: 'Durasi waktu minimal 10 menit.', confirmButtonColor: '#D53D66' });
                return false;
            }
            if (harga === '' || harga < 0) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Harga Tidak Valid', text: 'Harga paket tidak boleh negatif.', confirmButtonColor: '#D53D66' });
                return false;
            }
            if (!kapasitas || kapasitas <= 0) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Kapasitas Tidak Valid', text: 'Kapasitas orang harus lebih dari 0.', confirmButtonColor: '#D53D66' });
                return false;
            }
        });

        // ===== MODAL PROFIL (Biodata + Edit) =====
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

        const inputFotoModal = document.getElementById('inputFotoModal');
        if (inputFotoModal) {
            inputFotoModal.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) { document.getElementById('profile-preview-modal').src = event.target.result; };
                    reader.readAsDataURL(file);
                }
            });
        }

        const inputNamaModal = document.getElementById('inputNamaModal');
        if (inputNamaModal) {
            inputNamaModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z ]/g, ''); });
        }
        const inputUsernameModal = document.getElementById('inputUsernameModal');
        if (inputUsernameModal) {
            inputUsernameModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z0-9_]/g, ''); });
        }

        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('bi-eye');
                    this.classList.toggle('bi-eye-slash');
                });
            }
        }
        setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
        setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');

        const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62';
        if (inputHPModal) {
            inputHPModal.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, ''); }
                let digits = this.value.split(prefix)[1]?.replace(/[^0-9]/g, '') || '';
                if (digits.length > 13) digits = digits.slice(0, 13);
                this.value = prefix + digits;
            });
        }

        // ===== KONFIRMASI LOGOUT / LANDING =====
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
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
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
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }

        // ===== JAM REAL-TIME =====
        function updateLiveClock() {
            var clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            var now = new Date();
            var days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            var months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            var hours = String(now.getHours()).padStart(2, '0');
            var minutes = String(now.getMinutes()).padStart(2, '0');
            var seconds = String(now.getSeconds()).padStart(2, '0');
            clockEl.innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + hours + ':' + minutes + ':' + seconds + ' WIB';
        }
        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Paket foto baru berhasil ditambahkan.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Oke'
        }).then(() => {
            window.location.href = 'list.php?status_sukses=tambah';
        });
    </script>
    <?php endif; ?>

    <?php if(isset($success_profile) && $success_profile === true): ?>
    <script>Swal.fire({icon:'success',title:'Profil Diperbarui!',text:'Informasi profil Anda berhasil disinkronkan.',confirmButtonColor:'#D53D66',confirmButtonText:'Selesai'});</script>
    <?php endif; ?>

    <?php if(isset($error_profile) && $error_profile !== ""): ?>
    <script>Swal.fire({icon:'error',title:'Pembaruan Gagal!',text:'<?= addslashes($error_profile) ?>',confirmButtonColor:'#D53D66',confirmButtonText:'Periksa Kembali'}).then(()=>{var modalGanti=new bootstrap.Modal(document.getElementById('modalGantiProfil'));modalGanti.show();});</script>
    <?php endif; ?>

</body>
</html>
<?php ob_end_flush(); ?>