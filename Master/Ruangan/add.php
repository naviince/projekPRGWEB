<?php
ob_start();
session_start();
include '../../koneksi.php';

// =====================================================
// CSRF TOKEN HANDLING
// =====================================================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS - SAFE SQLSRV ANTI-CRASH
// =====================================================
if (!function_exists('safe_sqlsrv_query')) {
    function safe_sqlsrv_query($conn, $sql, $params = array()) {
        $query = sqlsrv_query($conn, $sql, $params);
        if ($query === false) {
            error_log("SQLSRV Error: " . print_r(sqlsrv_errors(), true));
            return false;
        }
        return $query;
    }
}

if (!function_exists('safe_sqlsrv_fetch')) {
    function safe_sqlsrv_fetch($query) {
        if (!$query) return false;
        return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    }
}

if (!function_exists('safe_sqlsrv_count')) {
    function safe_sqlsrv_count($conn, $sql, $params = array()) {
        $query = safe_sqlsrv_query($conn, $sql, $params);
        if (!$query) return 0;
        $row = safe_sqlsrv_fetch($query);
        return $row ? ($row['total'] ?? 0) : 0;
    }
}

// Ambil Profil Admin untuk Sidebar & Header
$q_admin = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ? AND Is_Deleted = 0", [$id_admin]);
$d_admin = safe_sqlsrv_fetch($q_admin);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// *Penyesuaian: Lokasi direktori foto Karyawan diperbaiki dari pelanggan menjadi karyawan
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// Ambil semua paket foto yang aktif (Status dihilangkan dari kueri sesuai instruksi)
$sql_paket = "SELECT ID_Paket, Nama_Paket, Harga_Paket, Kapasitas_Orang, Foto_Paket, Durasi_Waktu 
              FROM Paket_Foto 
              WHERE Is_Deleted = 0 
              ORDER BY Harga_Paket ASC";
$query_paket = safe_sqlsrv_query($conn, $sql_paket);
$daftar_paket = [];
if ($query_paket) {
    while ($row = safe_sqlsrv_fetch($query_paket)) {
        $daftar_paket[] = $row;
    }
}

// Inisialisasi variabel paket agar terhindar dari uninitialized error saat GET request
$paket_terpilih = [];
$error = "";
$success = false;
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    // === VALIDASI CSRF ===
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token keamanan tidak valid. Silakan refresh halaman.";
    } else {
        // Regenerate token setelah digunakan (one-time use)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Hanya proses jika tidak ada error CSRF
    if (empty($error)) {
        $nama           = trim($_POST['nama_ruangan'] ?? '');
    $deskripsi      = trim($_POST['deskripsi'] ?? '');
    $paket_terpilih = $_POST['paket'] ?? [];

    if (empty($nama)) {
        $field_errors['nama_ruangan'] = "Nama ruangan wajib diisi!";
    } elseif (strlen($nama) > 100) {
        $field_errors['nama_ruangan'] = "Maksimal 100 karakter!";
    }

    if (empty($deskripsi)) {
        $field_errors['deskripsi'] = "Deskripsi wajib diisi!";
    } elseif (strlen($deskripsi) > 255) {
        $field_errors['deskripsi'] = "Maksimal 255 karakter!";
    }

    // Memilih satu atau lebih paket foto diperbolehkan sesuai skema many-to-many
    if (empty($paket_terpilih)) {
        $field_errors['paket'] = "Pilih minimal 1 paket foto!";
    }

    // Cek Duplikat Nama Ruangan (Diubah ke SQL Langsung karena sp_CekDuplikatRuangan tidak tersedia)
    if (empty($field_errors)) {
        $sql_dup = "SELECT COUNT(*) AS total FROM Ruangan WHERE Nama_Ruangan = ? AND Is_Deleted = 0";
        $stmt_dup = safe_sqlsrv_query($conn, $sql_dup, [$nama]);
        $cek_dup = safe_sqlsrv_fetch($stmt_dup);
        
        if (($cek_dup['total'] ?? 0) > 0) {
            $error = "Nama ruangan '{$nama}' sudah ada! Gunakan nama lain.";
        } else {
            // --- VALIDASI UPLOAD GAMBAR (OPSIONAL) ---
            $foto_name = $_FILES['foto']['name'] ?? '';
            $foto_tmp  = $_FILES['foto']['tmp_name'] ?? '';
            $foto_size = $_FILES['foto']['size'] ?? 0;
            $foto_error = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;

            $new_filename = 'default_ruangan.jpg';
            $upload_path = '';

            if ($foto_error == UPLOAD_ERR_NO_FILE) {
                $new_filename = 'default_ruangan.jpg';
            } elseif ($foto_error != UPLOAD_ERR_OK) {
                $field_errors['foto'] = "Upload gagal (Error: {$foto_error})";
            } else {
                $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    $field_errors['foto'] = "Format harus JPG/PNG/WEBP!";
                } elseif ($foto_size > 2097152) {
                    $field_errors['foto'] = "Ukuran maksimal 2MB!";
                } else {
                    $check = getimagesize($foto_tmp);
                    if ($check === false) {
                        $field_errors['foto'] = "File bukan gambar valid!";
                    } else {
                        $new_filename = "ruangan_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                        $upload_dir   = "../../assets/img/ruangan/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $upload_path = $upload_dir . $new_filename;

                        if (!move_uploaded_file($foto_tmp, $upload_path)) {
                            $field_errors['foto'] = "Gagal simpan file ke server!";
                            $upload_path = null;
                        }
                    }
                }
            }
        }
    }

    } // Tutup if (empty($error)) dari validasi CSRF

    if (!empty($field_errors)) {
        $error = "Mohon lengkapi semua field yang bertanda merah (*) di bawah!";
    } else {
        if (sqlsrv_begin_transaction($conn) === false) {
            $error = "Gagal memulai transaksi database.";
            if ($upload_path && file_exists($upload_path)) @unlink($upload_path);
        } else {
            try {
                // Simpan Ruangan Baru via Stored Procedure sp_InsertRuangan
                // Format Parameter: @Nama, @Deskripsi, @Foto, @CreatedBy
                $sql_ruangan = "{CALL sp_InsertRuangan(?, ?, ?, ?)}";
                $params_ruangan = [
                    $nama, 
                    !empty($deskripsi) ? $deskripsi : null, 
                    $new_filename, 
                    $nama_admin
                ];
                $stmt_ruangan = sqlsrv_query($conn, $sql_ruangan, $params_ruangan);

                if ($stmt_ruangan === false) {
                    throw new Exception("Gagal menyimpan data ruangan: " . json_encode(sqlsrv_errors()));
                }

                // Skip result status INSERT untuk membaca output SELECT SCOPE_IDENTITY()
                sqlsrv_next_result($stmt_ruangan);

                $row_ruangan = safe_sqlsrv_fetch($stmt_ruangan);
                $id_ruangan_baru = $row_ruangan['ID_Ruangan'] ?? null;

                if (!$id_ruangan_baru) {
                    throw new Exception("Gagal mengambil ID Ruangan baru dari database.");
                }

                // Simpan Relasi Paket Ruangan ke Tabel Junction Paket_Ruangan via sp_InsertPaketRuangan
                foreach ($paket_terpilih as $id_paket) {
                    $id_paket = (int)$id_paket;
                    
                    // Validasi paket harus aktif dan terdaftar di database
                    $cek_paket_sql = "SELECT ID_Paket FROM Paket_Foto WHERE ID_Paket = ? AND Is_Deleted = 0";
                    $cek_paket_stmt = safe_sqlsrv_query($conn, $cek_paket_sql, [$id_paket]);
                    $cek_paket = safe_sqlsrv_fetch($cek_paket_stmt);
                    
                    if (!$cek_paket) {
                        throw new Exception("Paket ID {$id_paket} tidak valid.");
                    }

                    $sql_junction = "{CALL sp_InsertPaketRuangan(?, ?)}";
                    $stmt_junction = sqlsrv_query($conn, $sql_junction, [$id_paket, $id_ruangan_baru]);
                    if ($stmt_junction === false) {
                        throw new Exception("Gagal menghubungkan Paket ke Ruangan: " . json_encode(sqlsrv_errors()));
                    }
                }

                if (sqlsrv_commit($conn) === false) {
                    throw new Exception("Gagal melakukan commit transaksi database.");
                }
                // === PRG PATTERN: Redirect ke list dengan flag sukses ===
                header("Location: list.php?status_sukses=tambah");
                exit();

            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                if ($upload_path && file_exists($upload_path)) @unlink($upload_path);
                $error = $e->getmessage();
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
    <title>Tambah Ruangan – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --error-red: #dc2626; --error-bg: #fef2f2;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff; }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
        .form-card { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); overflow: hidden; }
        .form-card-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); padding: 30px 40px; color: #ffffff; }
        .form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
        .form-card-body { padding: 40px; }
        .info-card { background: linear-gradient(135deg, #FFF0F3, #FFF8F0); border-radius: 16px; padding: 16px 20px; margin-bottom: 25px; border: 1px solid rgba(255, 228, 233, 0.8); display: flex; align-items: center; gap: 12px; }
        .info-card i { font-size: 1.5rem; color: var(--p-pink); }
        .info-card .info-text { font-size: 0.85rem; color: #4a5568; font-weight: 600; line-height: 1.5; }
        .info-card .info-text strong { color: var(--p-pink); }
        .form-label { font-weight: 700; font-size: 0.75rem; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .form-label .required { color: var(--error-red); margin-left: 2px; font-size: 0.9rem; }
        .form-label .badge-wajib { background: var(--error-red); color: #fff; font-size: 0.6rem; padding: 2px 8px; border-radius: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-custom { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
        .form-control-custom:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
        .form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
        textarea.form-control-custom { min-height: 120px; resize: vertical; }
        .form-control-custom.is-error { border-color: var(--error-red) !important; background-color: var(--error-bg) !important; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.08) !important; }
        .input-hint { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 4px; }
        .field-error-msg { display: none; font-size: 0.8rem; color: var(--error-red); font-weight: 700; margin-top: 6px; align-items: center; gap: 4px; }
        .field-error-msg.show { display: flex; }
        
        /* FILE SELECT COMPONENT */
        .file-upload-zone { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 30px; text-align: center; transition: var(--transition-3d); cursor: pointer; background: #f8fafc; }
        .file-upload-zone:hover, .file-upload-zone.dragover { border-color: var(--p-pink); background: var(--s-pink); }
        .file-upload-zone.is-error { border-color: var(--error-red) !important; background-color: var(--error-bg) !important; }
        .file-upload-zone i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px; display: block; }
        .file-upload-zone p { font-size: 0.9rem; color: #64748b; font-weight: 600; margin: 0; }
        .file-upload-zone small { font-size: 0.75rem; color: #94a3b8; }
        .file-upload-zone input[type="file"] { display: none; }
        #preview-container { display: none; margin-top: 16px; position: relative; border-radius: 14px; overflow: hidden; border: 2px solid var(--light-pink); }
        #preview-container img { width: 100%; max-height: 250px; object-fit: cover; display: block; }
        #preview-container .remove-preview { position: absolute; top: 10px; right: 10px; background: rgba(220, 38, 38, 0.9); color: #fff; border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; transition: all 0.2s; }
        #preview-container .remove-preview:hover { background: #dc2626; transform: scale(1.1); }
        
        /* CARD INTERACTIVE GRID */
        .paket-section { background: #f8fafc; border-radius: 16px; padding: 24px; border: 2px solid #e2e8f0; transition: var(--transition-3d); }
        .paket-section.is-error { border-color: var(--error-red) !important; background-color: var(--error-bg) !important; }
        .paket-section-title { font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-dark); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .paket-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .paket-checkbox-item { position: relative; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px; padding: 16px; cursor: pointer; transition: var(--transition-3d); display: flex; align-items: center; gap: 12px; }
        .paket-checkbox-item:hover { border-color: var(--p-pink); }
        .paket-checkbox-item.selected { border-color: var(--p-pink); background: var(--s-pink); box-shadow: 0 4px 12px rgba(213, 61, 102, 0.1); }
        .paket-checkbox-item input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--p-pink); cursor: pointer; flex-shrink: 0; }
        .paket-checkbox-item .paket-info { flex: 1; min-width: 0; }
        .paket-checkbox-item .paket-nama { font-weight: 700; font-size: 0.85rem; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .paket-checkbox-item .paket-harga { font-size: 0.75rem; color: var(--p-pink); font-weight: 700; }
        .paket-checkbox-item .paket-kapasitas { font-size: 0.7rem; color: var(--text-muted); }
        .paket-checkbox-item .paket-durasi { font-size: 0.7rem; color: #94a3b8; font-weight: 600; }
        .paket-checkbox-item .paket-check-icon { display: none; color: var(--p-pink); font-size: 1.2rem; }
        .paket-checkbox-item.selected .paket-check-icon { display: block; }
        .paket-empty { text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.85rem; }
        .paket-section-error { display: none; font-size: 0.8rem; color: var(--error-red); font-weight: 700; margin-top: 8px; align-items: center; gap: 4px; }
        .paket-section-error.show { display: flex; }
        
        /* SUBMIT & CANCEL BUTTONS */
        .btn-submit { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35); color: #ffffff; }
        .btn-batal { background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-batal:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-3px); }
        
        .alert-custom { background: #fef2f2; border: none; border-left: 4px solid #dc2626; border-radius: 12px; color: #991b1b; font-size: 0.85rem; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .alert-custom i { font-size: 1.1rem; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; }
        .loading-overlay.active { display: flex; }
        .loading-spinner { width: 50px; height: 50px; border: 4px solid var(--light-pink); border-top-color: var(--p-pink); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { margin-top: 16px; font-weight: 700; color: var(--p-pink); }
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
            .paket-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
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
            .paket-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
            .paket-checkbox-item { padding: 12px; }
            .paket-checkbox-item img { width: 40px !important; height: 40px !important; }
            .btn-group-bottom {
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
            .form-control-custom {
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
            .paket-grid { grid-template-columns: 1fr; }
            .paket-checkbox-item { padding: 14px; }
            .paket-checkbox-item img { width: 50px !important; height: 50px !important; }
            .info-card {
                padding: 14px 16px;
                font-size: 0.8rem;
                gap: 10px;
            }
            .info-card i { font-size: 1.3rem; }
            .alert-custom {
                padding: 12px 14px;
                font-size: 0.8rem;
            }
            .btn-submit, .btn-batal {
                padding: 12px 20px;
                font-size: 0.9rem;
                border-radius: 12px;
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
            .form-control-custom {
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
            .paket-checkbox-item { padding: 12px; }
            .paket-checkbox-item img { width: 40px !important; height: 40px !important; }
            .paket-checkbox-item .paket-nama { font-size: 0.8rem; }
            .paket-checkbox-item .paket-harga { font-size: 0.78rem; }
            .btn-submit, .btn-batal {
                padding: 12px 16px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            .loading-text { font-size: 0.9rem; }
            .loading-spinner { width: 40px; height: 40px; }
        }

        @media (max-width: 375px) {
            .dashboard-header h3 { font-size: 0.95rem; }
            .form-card-body { padding: 18px 12px; }
            .btn-submit, .btn-batal {
                padding: 10px 14px;
                font-size: 0.8rem;
            }
            .paket-checkbox-item { padding: 10px; }
            .paket-checkbox-item img { width: 36px !important; height: 36px !important; }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Menyimpan ruangan...</div>
    </div>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg)"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
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
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Tambah Ruangan</h3>
                    <p class="text-muted small mb-0">Tambah ruangan studio baru dan tentukan paket foto yang bisa menggunakannya.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
            </div>
        </div>

        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-door-open-fill me-2"></i>Form Ruangan Baru</h4>
                <p>Lengkapi informasi ruangan dan pilih paket foto yang bisa menggunakannya.</p>
            </div>
            <div class="form-card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert-custom" id="alertError"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $error ?></span></div>
                <?php endif; ?>

                <div class="info-card">
                    <i class="bi bi-info-circle-fill"></i>
                    <div class="info-text">
                        <strong>Perhatian:</strong> Kapasitas ruangan diatur oleh <strong>Paket Foto</strong> yang dipilih. Setiap paket memiliki kapasitas dan durasi berbeda.
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="formRuangan" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label"><i class="bi bi-type"></i> Nama Ruangan <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                            <input type="text" name="nama_ruangan" id="nama_ruangan" class="form-control-custom <?= isset($field_errors['nama_ruangan']) ? 'is-error' : '' ?>" required maxlength="100" placeholder="Contoh: Studio A Minimalis" value="<?= htmlspecialchars($_POST['nama_ruangan'] ?? '') ?>">
                            <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 100 karakter, nama harus unik</div>
                            <div class="field-error-msg <?= isset($field_errors['nama_ruangan']) ? 'show' : '' ?>" id="error-nama_ruangan"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['nama_ruangan'] ?? '' ?></span></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-card-text"></i> Deskripsi Ruangan <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control-custom <?= isset($field_errors['deskripsi']) ? 'is-error' : '' ?>" required maxlength="255" placeholder="Deskripsikan suasana, konsep, dan keunggulan ruangan ini..."><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                        <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 255 karakter, akan ditampilkan ke pelanggan</div>
                        <div class="field-error-msg <?= isset($field_errors['deskripsi']) ? 'show' : '' ?>" id="error-deskripsi"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['deskripsi'] ?? '' ?></span></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-image"></i> Foto Ruangan <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                        <div class="file-upload-zone <?= isset($field_errors['foto']) ? 'is-error' : '' ?>" id="uploadArea" onclick="document.getElementById('foto-input').click()">
                            <input type="file" name="foto" id="foto-input" required accept="image/jpeg,image/jpg,image/png,image/webp" onchange="handleFileSelect(event)">
                            <i class="bi bi-camera-fill" id="upload-icon"></i>
                            <p id="upload-text">Klik atau seret foto ke sini</p>
                            <small>JPG, JPEG, PNG, WEBP — Maksimal 2MB</small>
                        </div>
                        <div id="preview-container">
                            <img id="preview-img" src="" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview(event)" title="Hapus foto"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="field-error-msg <?= isset($field_errors['foto']) ? 'show' : '' ?>" id="error-foto"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['foto'] ?? '' ?></span></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-camera"></i> Pilih Paket Foto <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                        <div class="input-hint mb-3"><i class="bi bi-info-circle"></i> Pilih paket-paket foto yang bisa menggunakan ruangan ini</div>
                        <div class="paket-section <?= isset($field_errors['paket']) ? 'is-error' : '' ?>" id="paket-section">
                            <div class="paket-section-title">
                                <i class="bi bi-stars"></i> Daftar Paket Foto Aktif
                            </div>
                            
                            <!-- INTERACTIVE CARD-BASED SELECTION GRID -->
                            <div style="max-height: 250px; overflow-y: auto; padding: 5px;">
                                <div class="paket-grid">
                                    <?php if (!empty($daftar_paket)): foreach ($daftar_paket as $pkt): 
                                        $foto_p = $pkt['Foto_Paket'] ?? 'default_paket.jpg';
                                        $foto_p_src = ($foto_p != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $foto_p)) ? "../../assets/img/paket/" . $foto_p : $default_svg_avatar;
                                        $is_checked = in_array($pkt['ID_Paket'], $paket_terpilih) ? 'checked' : '';
                                        $is_selected = $is_checked ? 'selected' : '';
                                    ?>
                                        <div class="paket-checkbox-item <?= $is_selected ?>">
                                            <input type="checkbox" name="paket[]" value="<?= $pkt['ID_Paket'] ?>" <?= $is_checked ?> onchange="updatePaketCount()">
                                            <div class="d-flex align-items-center gap-3 w-100">
                                                <img src="<?= $foto_p_src ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                                                <div class="paket-info flex-grow-1" style="min-width: 0;">
                                                    <div class="paket-nama fw-bold text-dark" style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($pkt['Nama_Paket']) ?></div>
                                                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size: 0.75rem; margin-top: 2px;">
                                                        <span class="text-muted"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($pkt['Durasi_Waktu']) ?> Menit</span>
                                                        <span class="text-muted">•</span>
                                                        <span class="text-muted"><i class="bi bi-people me-1"></i><?= htmlspecialchars($pkt['Kapasitas_Orang']) ?> Orang</span>
                                                    </div>
                                                    <div class="paket-harga fw-bold text-danger mt-1" style="font-size: 0.85rem; color: var(--p-pink) !important;">Rp <?= number_format($pkt['Harga_Paket'], 0, ',', '.') ?></div>
                                                </div>
                                                <i class="bi bi-check-circle-fill paket-check-icon fs-4 text-danger ms-auto"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="paket-empty w-100 py-4"><i class="bi bi-exclamation-circle fs-1 mb-2 d-block" style="color: #cbd5e1;"></i><p>Belum ada paket foto aktif. <a href="../Paket Foto/add.php" style="color: var(--p-pink);">Tambah paket foto dulu</a>.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="paket-section-error <?= isset($field_errors['paket']) ? 'show' : '' ?>" id="error-paket"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['paket'] ?? '' ?></span></div>
                        <div class="input-hint mt-2" id="paket-count-hint"><i class="bi bi-check-circle"></i> <span id="paket-count">0</span> paket terpilih</div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="btn-group-bottom" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; padding-top: 25px; border-top: 2px solid #f1f5f9;">
                        <a href="list.php" class="btn-batal"><i class="bi bi-arrow-left"></i> Batal</a>
                        <button type="submit" name="simpan" class="btn-submit"><i class="bi bi-check-circle-fill"></i> Simpan Ruangan</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- MODAL PROFILE BIODATA -->
    <div class="modal fade" id="modalBiodataAdmin" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #fff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center"><h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-badge-fill text-danger me-2"></i>Profil Anda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box" style="width: 100px; height: 100px; border: 3px solid var(--s-pink); margin: 0 auto; border-radius: 50%; overflow: hidden;"><img src="<?= $foto_admin_src ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;"></div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5><span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Admin</span>
            </div>
            <div class="card-3d p-3 border-0 mb-3" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-12"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email Karyawan</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_admin['email_karyawan'] ?? 'admin@spotlight.com') ?></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Hak Akses Sistem</small><span class="fw-bold text-dark" style="font-size: 0.85rem;">Administrator (Admin)</span></div>
              </div>
            </div>
            <button class="btn btn-reg-header shadow-sm py-3 mt-0 w-100" data-bs-dismiss="modal" style="border-radius: 14px !important; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; font-weight: 700;">Tutup</button>
          </div>
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
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });

        // Toggle class selected on table row when checkbox checked (MUTIPLE CHECKBOXES ALLOWED)
        document.querySelectorAll('.paket-checkbox-item input[type="checkbox"]').forEach(chk => {
            chk.addEventListener('change', function(e) {
                e.stopPropagation();
                if (this.checked) {
                    this.closest('.paket-checkbox-item').classList.add('selected');
                } else {
                    this.closest('.paket-checkbox-item').classList.remove('selected');
                }
                updatePaketCount();
                clearFieldError('paket');
            });
        });

        // Parent div click handler to check the checkbox (MUTIPLE CHECKBOXES ALLOWED)
        document.querySelectorAll('.paket-checkbox-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.tagName === 'INPUT') return;
                
                const checkbox = this.querySelector('input[type="checkbox"]');
                const wasChecked = checkbox.checked;
                
                checkbox.checked = !wasChecked;
                if (checkbox.checked) {
                    this.classList.add('selected');
                } else {
                    this.classList.remove('selected');
                }
                updatePaketCount();
                clearFieldError('paket');
            });
        });

        function updatePaketCount() {
            const checked = document.querySelectorAll('input[name="paket[]"]:checked').length;
            document.getElementById('paket-count').textContent = checked;
            const hint = document.getElementById('paket-count-hint');
            if (checked === 0) {
                hint.style.color = '#dc2626';
                hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Pilih minimal 1 paket foto!';
            } else {
                hint.style.color = '#059669';
                hint.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + checked + ' paket terpilih';
            }
        }

        // Preview Foto
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('preview-container');
            const previewImg = document.getElementById('preview-img');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');

            if (file) {
                if (file.size > 2097152) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ukuran Terlalu Besar',
                        text: 'Ukuran gambar maksimal 2MB.',
                        confirmButtonColor: '#D53D66'
                    });
                    event.target.value = '';
                    return;
                }

                const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format Tidak Valid',
                        text: 'Format gambar harus JPG, JPEG, PNG, atau WEBP.',
                        confirmButtonColor: '#D53D66'
                    });
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                    uploadIcon.style.display = 'none';
                    uploadText.textContent = file.name;
                };
                reader.readAsDataURL(file);
                clearFieldError('foto');
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
            uploadText.innerHTML = '<span>Klik atau seret foto ke sini</span>';
            document.getElementById('uploadArea').classList.remove('has-image');
        }

        // Drag & Drop
        const uploadArea = document.getElementById('uploadArea');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.style.borderColor = 'var(--p-pink)';
            uploadArea.style.background = 'var(--s-pink)';
        }

        function unhighlight(e) {
            uploadArea.style.borderColor = '#e2e8f0';
            uploadArea.style.background = '#f8fafc';
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                document.getElementById('foto-input').files = files;
                handleFileSelect({ target: { files: files } });
            }
        }

        // Clear Errors
        function clearFieldError(fieldName) {
            const field = document.getElementById(fieldName);
            const errorMsg = document.getElementById('error-' + fieldName);
            if (field) field.classList.remove('is-error');
            if (errorMsg) errorMsg.classList.remove('show');
            if (fieldName === 'paket') {
                const section = document.getElementById('paket-section');
                if (section) section.classList.remove('is-error');
                const errSec = document.getElementById('error-paket');
                if (errSec) errSec.classList.remove('show');
            }
            if (fieldName === 'foto') {
                const zone = document.getElementById('uploadArea');
                if (zone) zone.classList.remove('is-error');
            }
        }

        document.getElementById('nama_ruangan').addEventListener('input', function() {
            if (this.value.trim()) clearFieldError('nama_ruangan');
        });
        document.getElementById('deskripsi').addEventListener('input', function() {
            if (this.value.trim()) clearFieldError('deskripsi');
        });

        // Form Submit (VALIDATION REVISED FOR MANY-TO-MANY MODE)
        document.getElementById('formRuangan').addEventListener('submit', function(e) {
            const paketChecked = document.querySelectorAll('input[name="paket[]"]:checked').length;
            if (paketChecked === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Paket Belum Dipilih',
                    text: 'Pilih minimal 1 paket foto yang bisa menggunakan ruangan ini!',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
            document.getElementById('loadingOverlay').classList.add('active');
            return true;
        });

        // Modal Biodata
        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalBiodataAdmin'));
            modalBiodata.show();
        }

        // Logout & Beranda
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar?',
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

        // Live Clock
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
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        updatePaketCount();

        <?php if (!empty($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan!',
                html: '<?= addslashes($error) ?>',
                confirmButtonColor: '#D53D66'
            });
        <?php endif; ?>


    </script>
</body>
</html>