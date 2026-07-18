<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// AMBIL DAFTAR RUANGAN (UNTUK CHECKBOX)
// *Penyesuaian: Menggunakan Deskripsi, Kapasitas_Ruangan dihapus sesuai skema DB baru
// =====================================================
$daftar_ruangan = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi, Foto_Ruangan 
     FROM Ruangan 
     WHERE Status = 1 AND Is_Deleted = 0 
     ORDER BY Nama_Ruangan ASC"
);

// =====================================================
// KATEGORI TEMA (DAFTAR TETAP)
// =====================================================
$daftar_kategori = ['Casual', 'Formal', 'Vintage', 'Modern', 'Outdoor', 'Wisuda', 'Pre-Wedding', 'Lainnya'];

// =====================================================
// PROSES SIMPAN DATA
// =====================================================
$error = "";
$success = false;

if (isset($_POST['simpan'])) {
    $nama       = trim($_POST['nama_tema'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $status     = (int)($_POST['status'] ?? 1);
    $ruangan_terpilih = $_POST['ruangan'] ?? []; // Array ID_Ruangan

    // --- VALIDASI SERVER-SIDE (KUAT) ---
    if (empty($nama)) {
        $error = "Nama tema foto wajib diisi!";
    } elseif (strlen($nama) > 100) {
        $error = "Nama tema foto maksimal 100 karakter!";
    } elseif (empty($kategori)) {
        $error = "Kategori tema wajib dipilih!";
    } elseif (strlen($kategori) > 50) {
        $error = "Kategori maksimal 50 karakter!";
    } elseif (strlen($deskripsi) > 255) {
        $error = "Deskripsi maksimal 255 karakter!";
    } elseif (empty($ruangan_terpilih)) {
        $error = "Pilih minimal 1 ruangan yang bisa menggunakan tema ini!";
    } else {
        // --- CEK DUPLIKAT NAMA ---
        $cek_dup = safe_sqlsrv_fetch($conn, 
            "SELECT COUNT(*) as total FROM Tema_Foto WHERE Nama_Tema = ? AND Is_Deleted = 0", 
            [$nama]
        );
        if (($cek_dup['total'] ?? 0) > 0) {
            $error = "Nama tema foto '{$nama}' sudah ada! Gunakan nama lain.";
        } else {
            // --- VALIDASI UPLOAD GAMBAR (OPSIONAL) ---
            $foto_name = $_FILES['foto']['name'] ?? '';
            $foto_tmp  = $_FILES['foto']['tmp_name'] ?? '';
            $foto_size = $_FILES['foto']['size'] ?? 0;
            $foto_error = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;

            $new_filename = 'default_tema.jpg';
            $upload_path = '';

            if ($foto_error == UPLOAD_ERR_NO_FILE) {
                $new_filename = 'default_tema.jpg';
            } elseif ($foto_error != UPLOAD_ERR_OK) {
                $error = "Terjadi kesalahan saat upload foto. Coba lagi.";
            } else {
                $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    $error = "Format gambar harus JPG, JPEG, PNG, atau WEBP!";
                } elseif ($foto_size > 2097152) {
                    $error = "Ukuran gambar maksimal 2MB!";
                } else {
                    $new_filename = "tema_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                    $upload_dir   = "../../assets/img/tema/";

                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $upload_path = $upload_dir . $new_filename;

                    $check = getimagesize($foto_tmp);
                    if ($check === false) {
                        $error = "File yang diupload bukan gambar valid!";
                    } elseif (!move_uploaded_file($foto_tmp, $upload_path)) {
                        $error = "Gagal memindahkan file ke server.";
                    }
                }
            }

            if ($error == "") {
                // --- BEGIN TRANSACTION ---
                sqlsrv_begin_transaction($conn);

                try {
                    // 1. INSERT TEMA_FOTO MENGGUNAKAN STORED PROCEDURE (sp_InsertTemaFoto)
                    $sql_tema = "EXEC sp_InsertTemaFoto ?, ?, ?, ?, ?";
                    $params_tema = [
                        $nama, 
                        $kategori, 
                        empty($deskripsi) ? null : $deskripsi, 
                        $new_filename, 
                        $nama_admin
                    ];
                    $stmt_tema = sqlsrv_query($conn, $sql_tema, $params_tema);

                    if ($stmt_tema === false) {
                        $sql_errors = sqlsrv_errors();
                        $error_msg = "Gagal menyimpan tema foto ke database.";
                        if (!empty($sql_errors)) {
                            foreach ($sql_errors as $err) {
                                $error_msg .= " [SQL State: " . ($err['SQLSTATE'] ?? 'N/A') . ", Code: " . ($err['code'] ?? 'N/A') . ", Message: " . ($err['message'] ?? 'Terjadi kesalahan') . "]";
                            }
                        }
                        throw new Exception($error_msg);
                    }

                    // Ambil ID Baru yang di-return dari Stored Procedure
                    $row_tema = sqlsrv_fetch_array($stmt_tema, SQLSRV_FETCH_ASSOC);
                    $id_tema_baru = $row_tema['ID_Tema'] ?? null;
                    sqlsrv_free_stmt($stmt_tema);

                    if (!$id_tema_baru) {
                        throw new Exception("Gagal mendapatkan ID Tema baru dari database.");
                    }

                    // 2. INSERT RUANGAN_TEMA MENGGUNAKAN STORED PROCEDURE (sp_InsertRuanganTema)
                    foreach ($ruangan_terpilih as $id_ruangan) {
                        $id_ruangan = (int)$id_ruangan;
                        $sql_junction = "EXEC sp_InsertRuanganTema ?, ?";
                        $stmt_junction = sqlsrv_query($conn, $sql_junction, [$id_ruangan, $id_tema_baru]);
                        if ($stmt_junction === false) {
                            throw new Exception("Gagal menghubungkan ke ruangan ID {$id_ruangan}.");
                        }
                        sqlsrv_free_stmt($stmt_junction);
                    }

                    // COMMIT
                    sqlsrv_commit($conn);
                    $success = true;

                } catch (Exception $e) {
                    // ROLLBACK
                    sqlsrv_rollback($conn);
                    if (!empty($upload_path) && file_exists($upload_path)) unlink($upload_path);
                    $error = $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Tema Foto – SpotLight Studio</title>
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
        textarea.form-control-custom { min-height: 120px; resize: vertical; }

        .input-hint {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 600;
            margin-top: 6px; display: flex; align-items: center; gap: 4px;
        }

        /* FILE UPLOAD */
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

        /* RUANGAN CHECKBOX GRID */
        .ruangan-section {
            background: #f8fafc; border-radius: 16px;
            padding: 24px; border: 2px solid #e2e8f0;
        }
        .ruangan-section-title {
            font-weight: 800; font-size: 0.85rem;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-dark); margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .ruangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        .ruangan-checkbox-item {
            position: relative;
            background: #ffffff; border: 2px solid #e2e8f0;
            border-radius: 14px; padding: 16px;
            cursor: pointer; transition: var(--transition-3d);
            display: flex; align-items: center; gap: 12px;
        }
        .ruangan-checkbox-item:hover { border-color: var(--p-pink); }
        .ruangan-checkbox-item.selected {
            border-color: var(--p-pink); background: var(--s-pink);
            box-shadow: 0 4px 12px rgba(213, 61, 102, 0.1);
        }
        .ruangan-checkbox-item input[type="checkbox"] {
            width: 20px; height: 20px; accent-color: var(--p-pink);
            cursor: pointer; flex-shrink: 0;
        }
        .ruangan-checkbox-item .ruangan-info { flex: 1; min-width: 0; }
        .ruangan-checkbox-item .ruangan-nama {
            font-weight: 700; font-size: 0.85rem; color: var(--text-dark);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ruangan-checkbox-item .ruangan-kapasitas {
            font-size: 0.75rem; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ruangan-checkbox-item .ruangan-check-icon {
            display: none; color: var(--p-pink); font-size: 1.2rem;
        }
        .ruangan-checkbox-item.selected .ruangan-check-icon { display: block; }

        .ruangan-empty {
            text-align: center; padding: 30px;
            color: var(--text-muted); font-size: 0.85rem;
        }

        /* STATUS TOGGLE */
        .status-toggle-group {
            display: flex; gap: 12px; margin-top: 8px;
        }
        .status-option {
            flex: 1; padding: 14px 16px; border-radius: 14px;
            border: 2px solid #e2e8f0; cursor: pointer;
            text-align: center; transition: var(--transition-3d);
            background: #ffffff;
        }
        .status-option:hover { border-color: var(--p-pink); }
        .status-option.active {
            border-color: var(--p-pink); background: var(--s-pink);
        }
        .status-option input { display: none; }
        .status-option .status-icon { font-size: 1.3rem; margin-bottom: 4px; }
        .status-option .status-label { font-weight: 700; font-size: 0.85rem; }
        .status-option .status-desc { font-size: 0.7rem; color: var(--text-muted); }

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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

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
            .ruangan-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
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
            .file-upload-zone {
                padding: 24px 16px;
            }
            .file-upload-zone i {
                font-size: 2rem;
            }
            .file-upload-zone p {
                font-size: 0.85rem;
            }
            .ruangan-grid { grid-template-columns: 1fr; }
            .ruangan-checkbox-item { padding: 14px; }
            .ruangan-section { padding: 18px 14px; }
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
            .ruangan-checkbox-item { padding: 12px; }
            .ruangan-section { padding: 16px 12px; }
            .ruangan-section-title { font-size: 0.8rem; }
            .btn-submit, .btn-batal {
                padding: 12px 16px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
        }

        @media (max-width: 375px) {
            .dashboard-header h3 { font-size: 0.95rem; }
            .form-card-body { padding: 18px 12px; }
            .btn-submit, .btn-batal {
                padding: 10px 14px;
                font-size: 0.8rem;
            }
            .ruangan-checkbox-item { padding: 10px; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar">
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
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
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
                        <span><i class="bi bi-house-door-fill me-2"></i> Beranda</span>
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
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Tambah Tema Foto</h3>
                    <p class="text-muted small mb-0">Tambah tema foto baru dan tentukan ruangan yang bisa menggunakannya.</p>
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
            <a href="./list.php">Tema Foto</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Tambah Tema Foto</span>
        </div>

        <!-- FORM CARD -->
        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-palette-fill me-2"></i>Form Tema Foto Baru</h4>
                <p>Lengkapi informasi tema dan pilih ruangan yang bisa menggunakannya.</p>
            </div>
            <div class="form-card-body">

                <?php if ($error != ""): ?>
                    <div class="alert-custom">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="formTema">
                    <div class="row">
                        <!-- Nama Tema -->
                        <div class="col-md-8 mb-4">
                            <label class="form-label">Nama Tema Foto <span class="required">*</span></label>
                            <input type="text" name="nama_tema" class="form-control-custom" required 
                                   maxlength="100" placeholder="Contoh: Vintage Retro"
                                   value="<?= htmlspecialchars($_POST['nama_tema'] ?? '') ?>">
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Maksimal 100 karakter, nama harus unik
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Kategori <span class="required">*</span></label>
                            <select name="kategori" class="form-select-custom" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($daftar_kategori as $kat): 
                                    $sel = (isset($_POST['kategori']) && $_POST['kategori'] == $kat) ? 'selected' : '';
                                ?>
                                    <option value="<?= $kat ?>" <?= $sel ?>><?= $kat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="mb-4">
                        <label class="form-label">Deskripsi Tema</label>
                        <textarea name="deskripsi" class="form-control-custom" 
                                  maxlength="255" placeholder="Deskripsikan konsep dan suasana tema ini (opsional)..."><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Maksimal 255 karakter, akan ditampilkan ke pelanggan
                        </div>
                    </div>

                    <!-- Foto Tema -->
                    <div class="mb-4">
                        <label class="form-label">Foto Tema</label>
                        <div class="file-upload-zone" id="dropzone" onclick="document.getElementById('foto-input').click()">
                            <input type="file" name="foto" id="foto-input" 
                                   accept="image/jpeg,image/jpg,image/png,image/webp" onchange="handleFileSelect(event)">
                            <i class="bi bi-camera-fill" id="upload-icon"></i>
                            <p id="upload-text">Klik atau seret foto ke sini (opsional)</p>
                            <small>JPG, JPEG, PNG, WEBP — Maksimal 2MB</small>
                        </div>
                        <div id="preview-container">
                            <img id="preview-img" src="" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview(event)" title="Hapus foto">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Jika tidak diisi, akan menggunakan foto default
                        </div>
                    </div>

                    <!-- PILIH RUANGAN -->
                    <div class="mb-4">
                        <label class="form-label">Pilih Ruangan <span class="required">*</span></label>
                        <div class="input-hint mb-3">
                            <i class="bi bi-info-circle"></i> Pilih minimal 1 ruangan yang bisa menggunakan tema ini
                        </div>

                        <div class="ruangan-section">
                            <div class="ruangan-section-title">
                                <i class="bi bi-door-open-fill text-danger"></i>
                                Ruangan Tersedia
                            </div>

                            <?php if (!empty($daftar_ruangan)): ?>
                                <div class="ruangan-grid">
                                    <?php foreach ($daftar_ruangan as $ruangan): 
                                        $is_checked = (isset($_POST['ruangan']) && in_array($ruangan['ID_Ruangan'], $_POST['ruangan'])) ? 'checked' : '';
                                        $is_selected = $is_checked ? 'selected' : '';
                                    ?>
                                        <div class="ruangan-checkbox-item <?= $is_selected ?>" onclick="toggleRuangan(this)">
                                            <input type="checkbox" name="ruangan[]" value="<?= $ruangan['ID_Ruangan'] ?>" 
                                                   <?= $is_checked ?> onchange="updateRuanganCount()">
                                            <div class="ruangan-info">
                                                <div class="ruangan-nama"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                                                <div class="ruangan-kapasitas"><?= htmlspecialchars($ruangan['Deskripsi'] ?? 'Tidak ada deskripsi ruangan') ?></div>
                                            </div>
                                            <i class="bi bi-check-circle-fill ruangan-check-icon"></i>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="ruangan-empty">
                                    <i class="bi bi-exclamation-circle fs-1 mb-2 d-block" style="color: #cbd5e1;"></i>
                                    <p>Belum ada ruangan aktif. <a href="../Ruangan/add.php" style="color: var(--p-pink);">Tambah ruangan dulu</a>.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="input-hint mt-2" id="ruangan-count-hint">
                            <i class="bi bi-check-circle"></i> <span id="ruangan-count">0</span> ruangan terpilih
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="simpan" class="btn-submit">
                            <i class="bi bi-check2-circle"></i> Simpan Tema Foto
                        </button>
                        <a href="list.php" class="btn-batal">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                    </div>
                </form>

            </div>
        </div>

    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // =====================================================
        // MOBILE SIDEBAR TOGGLE
        // =====================================================
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const isOpen = sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        // Auto-close sidebar when clicking nav links on mobile
        document.querySelectorAll('.sidebar .nav-link-custom, .sidebar .submenu-link, .sidebar .btn-logout').forEach(el => {
            el.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar.classList.contains('mobile-open')) {
                        toggleSidebar();
                    }
                }
            });
        });

        // Handle resize: if going back to desktop, reset mobile sidebar state
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.querySelector('.sidebar-overlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

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

        // Status Toggle
        function selectStatus(el, val) {
            document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
            el.classList.add('active');
            el.querySelector('input').checked = true;
        }

        // Ruangan Checkbox Toggle
        function toggleRuangan(el) {
            const checkbox = el.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            if (checkbox.checked) {
                el.classList.add('selected');
            } else {
                el.classList.remove('selected');
            }
            updateRuanganCount();
        }

        function updateRuanganCount() {
            const checked = document.querySelectorAll('input[name="ruangan[]"]:checked').length;
            document.getElementById('ruangan-count').textContent = checked;
            const hint = document.getElementById('ruangan-count-hint');
            if (checked === 0) {
                hint.style.color = '#dc2626';
                hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Pilih minimal 1 ruangan!';
            } else {
                hint.style.color = '#059669';
                hint.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + checked + ' ruangan terpilih';
            }
        }

        // File Upload & Preview
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
            const input = document.getElementById('foto-input');
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
                document.getElementById('foto-input').files = files;
                handleFileSelect({ target: { files: files } });
            }
        });

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }

        // Konfirmasi Beranda
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            document.getElementById('live-clock').innerText = 
                `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();

        // Form validation
        document.getElementById('formTema').addEventListener('submit', function(e) {
            const ruanganChecked = document.querySelectorAll('input[name="ruangan[]"]:checked').length;
            if (ruanganChecked === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning', title: 'Ruangan Belum Dipilih',
                    text: 'Pilih minimal 1 ruangan yang bisa menggunakan tema ini!',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
        });

        // Init ruangan count
        updateRuanganCount();
    </script>

    <?php if ($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!',
            text: 'Tema foto baru telah ditambahkan dan terhubung ke ruangan.',
            confirmButtonColor: '#D53D66'
        }).then(() => window.location = 'list.php?status_sukses=tambah');
    </script>
    <?php endif; ?>
</body>
</html>