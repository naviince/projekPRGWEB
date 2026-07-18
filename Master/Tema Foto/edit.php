<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin   = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS
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
// AMBIL ID DARI URL
// =====================================================
$id_tema = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_tema <= 0) {
    header("Location: list.php?status_sukses=error&message=ID tema tidak valid");
    exit();
}

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn,
    "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0",
    [$id_admin]
);
$nama_admin    = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin    = $admin_data['Foto_Profil']   ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin
    : $default_svg_avatar;

// =====================================================
// AMBIL DATA TEMA YANG AKAN DIEDIT
// =====================================================
$tema = safe_sqlsrv_fetch($conn,
    "SELECT ID_Tema, Nama_Tema, Kategori_Tema, Deskripsi, Foto_Tema, Status 
     FROM Tema_Foto 
     WHERE ID_Tema = ? AND Is_Deleted = 0",
    [$id_tema]
);

if (!$tema) {
    header("Location: list.php?status_sukses=error&message=Tema foto tidak ditemukan atau sudah dihapus");
    exit();
}

// Ruangan yang sudah terhubung dengan tema ini
$ruangan_terhubung_raw = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan FROM Ruangan_Tema WHERE ID_Tema = ?",
    [$id_tema]
);
$ruangan_terhubung = array_column($ruangan_terhubung_raw, 'ID_Ruangan');

// Semua ruangan aktif
$daftar_ruangan = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi 
     FROM Ruangan 
     WHERE Status = 1 AND Is_Deleted = 0 
     ORDER BY Nama_Ruangan ASC"
);

$daftar_kategori = ['Casual', 'Formal', 'Vintage', 'Modern', 'Outdoor', 'Wisuda', 'Pre-Wedding', 'Lainnya'];

// =====================================================
// PROSES SIMPAN EDIT
// =====================================================
$error   = "";
$success = false;

if (isset($_POST['simpan'])) {
    $nama              = trim($_POST['nama_tema']  ?? '');
    $kategori          = trim($_POST['kategori']   ?? '');
    $deskripsi         = trim($_POST['deskripsi']  ?? '');
    $status            = (int)($_POST['status']    ?? 1);
    $ruangan_terpilih  = $_POST['ruangan']         ?? [];

    // --- VALIDASI SERVER-SIDE ---
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
        // Cek duplikat nama (kecuali record ini sendiri)
        $cek_dup = safe_sqlsrv_fetch($conn,
            "SELECT COUNT(*) as total FROM Tema_Foto WHERE Nama_Tema = ? AND Is_Deleted = 0 AND ID_Tema <> ?",
            [$nama, $id_tema]
        );
        if (($cek_dup['total'] ?? 0) > 0) {
            $error = "Nama tema foto '{$nama}' sudah ada! Gunakan nama lain.";
        } else {
            // --- VALIDASI UPLOAD GAMBAR (OPSIONAL) ---
            $foto_name = $_FILES['foto']['name'] ?? '';
            $foto_tmp  = $_FILES['foto']['tmp_name'] ?? '';
            $foto_size = $_FILES['foto']['size'] ?? 0;
            $foto_error = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;

            $foto_lama      = $tema['Foto_Tema'] ?? 'default_tema.jpg';
            $new_filename   = $foto_lama;
            $upload_path    = '';

            if ($foto_error == UPLOAD_ERR_NO_FILE) {
                $new_filename = $foto_lama;
            } elseif ($foto_error != UPLOAD_ERR_OK) {
                $error = "Terjadi kesalahan saat upload foto. Coba lagi.";
            } else {
                $ext     = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    $error = "Format gambar harus JPG, JPEG, PNG, atau WEBP!";
                } elseif ($foto_size > 2097152) {
                    $error = "Ukuran gambar maksimal 2MB!";
                } else {
                    $check = getimagesize($foto_tmp);
                    if ($check === false) {
                        $error = "File yang diupload bukan gambar valid!";
                    } else {
                        $new_filename = "tema_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                        $upload_dir   = "../../assets/img/tema/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $upload_path  = $upload_dir . $new_filename;

                        if (!move_uploaded_file($foto_tmp, $upload_path)) {
                            $error = "Gagal memindahkan file ke server.";
                        }
                    }
                }
            }

            if ($error == "") {
                $cek_order_aktif = safe_sqlsrv_fetch($conn,
                    "SELECT COUNT(*) AS total FROM [Order] 
                     WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4",
                    [$id_tema]
                );
                $ada_order_aktif = (($cek_order_aktif['total'] ?? 0) > 0);

                sqlsrv_begin_transaction($conn);

                try {
                    $sql_update = "EXEC sp_UpdateTemaFoto ?, ?, ?, ?, ?, ?, ?";
                    $params_update = [
                        $id_tema,
                        $nama,
                        $kategori,
                        empty($deskripsi) ? null : $deskripsi,
                        $new_filename,
                        $status,
                        $nama_admin
                    ];
                    $stmt_update   = sqlsrv_query($conn, $sql_update, $params_update);
                    if ($stmt_update === false) {
                        throw new Exception("Gagal memperbarui data tema foto.");
                    }
                    sqlsrv_free_stmt($stmt_update);

                    if ($ada_order_aktif) {
                        $ruangan_order_aktif_raw = safe_sqlsrv_fetch_all($conn,
                            "SELECT DISTINCT ID_Ruangan FROM [Order] 
                             WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4",
                            [$id_tema]
                        );
                        $ruangan_order_aktif = array_column($ruangan_order_aktif_raw, 'ID_Ruangan');

                        foreach ($ruangan_order_aktif as $r_aktif) {
                            if (!in_array($r_aktif, $ruangan_terpilih)) {
                                throw new Exception("Ruangan yang digunakan oleh order aktif tidak boleh dihapus dari tema ini.");
                            }
                        }
                    }

                    $stmt_del = sqlsrv_query($conn, "DELETE FROM Ruangan_Tema WHERE ID_Tema = ?", [$id_tema]);
                    if ($stmt_del === false) throw new Exception("Gagal memperbarui relasi ruangan.");
                    sqlsrv_free_stmt($stmt_del);

                    foreach ($ruangan_terpilih as $id_ruangan) {
                        $id_ruangan    = (int)$id_ruangan;
                        $stmt_junction = sqlsrv_query($conn,
                            "EXEC sp_InsertRuanganTema ?, ?",
                            [$id_ruangan, $id_tema]
                        );
                        if ($stmt_junction === false) {
                            throw new Exception("Gagal menghubungkan ke ruangan ID {$id_ruangan}.");
                        }
                        sqlsrv_free_stmt($stmt_junction);
                    }

                    if (!empty($upload_path) && !empty($foto_lama) && $foto_lama !== 'default_tema.jpg') {
                        $old_path = "../../assets/img/tema/" . $foto_lama;
                        if (file_exists($old_path)) unlink($old_path);
                    }

                    sqlsrv_commit($conn);
                    $success = true;

                } catch (Exception $e) {
                    sqlsrv_rollback($conn);
                    if (!empty($upload_path) && file_exists($upload_path)) unlink($upload_path);
                    $error = $e->getMessage();
                }
            }
        }
    }

    if ($error != "") {
        $tema['Nama_Tema']     = $_POST['nama_tema'] ?? $tema['Nama_Tema'];
        $tema['Kategori_Tema'] = $_POST['kategori']  ?? $tema['Kategori_Tema'];
        $tema['Deskripsi']     = $_POST['deskripsi'] ?? $tema['Deskripsi'];
        $tema['Status']        = (int)($_POST['status'] ?? $tema['Status']);
        $ruangan_terhubung     = array_map('intval', $_POST['ruangan'] ?? []);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Tema Foto – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink:      #D53D66;
            --d-pink:      #CA3366;
            --s-pink:      #FFF0F3;
            --light-pink:  #FFE4E9;
            --text-dark:   #1e1e24;
            --text-muted:  #718096;
            --body-bg:     #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * { -webkit-tap-highlight-color: transparent; }

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
            background: #fff; 
            position: fixed; 
            top: 0; 
            left: 0; 
            border-right: 1px solid rgba(255,228,233,.8); 
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
            font-size: .85rem; 
            font-weight: 600; 
        }
        .sidebar-menu-wrapper { 
            flex-grow: 1; 
            overflow-y: auto; 
            margin-bottom: 20px; 
            scrollbar-width: none; 
        }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 12px 18px; 
            color: #4a5568; 
            font-weight: 700; 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: .9rem; 
            transition: var(--transition-3d); 
        }
        .nav-link-custom:hover, .nav-link-custom.active { 
            background-color: var(--light-pink); 
            color: var(--p-pink); 
            transform: translateX(4px); 
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { 
            display: flex; 
            align-items: center; 
            padding: 8px 18px; 
            color: #718096; 
            font-weight: 600; 
            font-size: .85rem; 
            text-decoration: none; 
            border-radius: 10px; 
            transition: .3s; 
        }
        .submenu-link:hover, .submenu-link.active { 
            color: var(--p-pink); 
            background-color: rgba(213,61,102,.03); 
            padding-left: 22px; 
        }
        .btn-logout { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #fff; 
            border: none; 
            width: 100%; 
            padding: 12px; 
            border-radius: 12px; 
            font-weight: 800; 
            font-size: .85rem; 
            transition: var(--transition-3d); 
        }
        .btn-logout:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 15px rgba(213,61,102,.2); 
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

        /* MOBILE HEADER / HAMBURGER */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
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
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
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

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        
        .dashboard-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 35px; 
            flex-wrap: wrap;
            gap: 15px;
        }
        .profile-header-btn { 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            overflow: hidden; 
            border: 2px solid #fff; 
            cursor: pointer; 
            transition: var(--transition-3d); 
            background: #fff; 
            flex-shrink: 0;
        }
        .profile-header-btn:hover { 
            transform: scale(1.08) translateY(-2px); 
            box-shadow: 0 8px 20px rgba(213,61,102,.15); 
            border-color: var(--p-pink); 
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        .breadcrumb-custom { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            margin-bottom: 25px; 
            font-size: .85rem; 
            font-weight: 600; 
            flex-wrap: wrap;
        }
        .breadcrumb-custom a { 
            color: var(--text-muted); 
            text-decoration: none; 
            transition: color .2s; 
        }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }

        /* FORM CARD */
        .form-card { 
            background: #fff; 
            border-radius: 22px; 
            border: 1px solid rgba(255,228,233,.8); 
            box-shadow: 0 8px 24px rgba(213,61,102,.03); 
            overflow: hidden; 
        }
        .form-card-header { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            padding: 30px 40px; 
            color: #fff; 
        }
        .form-card-header h4 { 
            font-weight: 800; 
            font-size: 1.4rem; 
            margin-bottom: 4px; 
        }
        .form-card-header p { 
            opacity: .85; 
            font-size: .85rem; 
            margin: 0; 
        }
        .form-card-body { padding: 40px; }

        .form-label { 
            font-weight: 700; 
            font-size: .75rem; 
            color: var(--text-dark); 
            text-transform: uppercase; 
            letter-spacing: .8px; 
            margin-bottom: 8px; 
        }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control-custom, .form-select-custom { 
            width: 100%; 
            border: 2px solid #e2e8f0; 
            border-radius: 14px; 
            padding: 14px 18px; 
            font-weight: 600; 
            font-size: .9rem; 
            color: #1e293b; 
            transition: var(--transition-3d); 
            background: #fff; 
        }
        .form-control-custom:focus, .form-select-custom:focus { 
            outline: none; 
            border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(213,61,102,.08); 
        }
        .form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
        textarea.form-control-custom { min-height: 120px; resize: vertical; }
        .input-hint { 
            font-size: .75rem; 
            color: var(--text-muted); 
            font-weight: 600; 
            margin-top: 6px; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
        }

        /* CURRENT FOTO */
        .current-foto-box { 
            border: 2px solid var(--light-pink); 
            border-radius: 16px; 
            padding: 16px; 
            background: var(--s-pink); 
            display: flex; 
            align-items: center; 
            gap: 16px; 
            margin-bottom: 12px; 
        }
        .current-foto-box img { 
            width: 80px; 
            height: 80px; 
            border-radius: 12px; 
            object-fit: cover; 
            border: 2px solid #fff; 
            box-shadow: 0 4px 12px rgba(0,0,0,.08); 
            flex-shrink: 0;
        }
        .current-foto-box .placeholder-icon { 
            width: 80px; 
            height: 80px; 
            border-radius: 12px; 
            background: #fff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--p-pink); 
            font-size: 2rem; 
            border: 2px dashed var(--light-pink); 
            flex-shrink: 0;
        }
        .current-foto-info p { margin: 0; font-weight: 700; font-size: .85rem; }
        .current-foto-info small { color: var(--text-muted); font-size: .75rem; }

        /* FILE UPLOAD */
        .file-upload-zone { 
            border: 2px dashed #e2e8f0; 
            border-radius: 16px; 
            padding: 26px; 
            text-align: center; 
            transition: var(--transition-3d); 
            cursor: pointer; 
            background: #f8fafc; 
        }
        .file-upload-zone:hover, .file-upload-zone.dragover { 
            border-color: var(--p-pink); 
            background: var(--s-pink); 
        }
        .file-upload-zone i { 
            font-size: 2rem; 
            color: #cbd5e1; 
            margin-bottom: 10px; 
            display: block; 
        }
        .file-upload-zone p { 
            font-size: .875rem; 
            color: #64748b; 
            font-weight: 600; 
            margin: 0; 
        }
        .file-upload-zone small { font-size: .72rem; color: #94a3b8; }
        .file-upload-zone input[type="file"] { display: none; }

        #preview-container { 
            display: none; 
            margin-top: 12px; 
            position: relative; 
            border-radius: 14px; 
            overflow: hidden; 
            border: 2px solid var(--light-pink); 
        }
        #preview-container img { 
            width: 100%; 
            max-height: 220px; 
            object-fit: cover; 
            display: block; 
        }
        #preview-container .remove-preview { 
            position: absolute; 
            top: 8px; 
            right: 8px; 
            background: rgba(220,38,38,.9); 
            color: #fff; 
            border: none; 
            border-radius: 50%; 
            width: 30px; 
            height: 30px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            font-size: .8rem; 
            transition: .2s; 
        }
        #preview-container .remove-preview:hover { 
            background: #dc2626; 
            transform: scale(1.1); 
        }

        /* RUANGAN CHECKBOX */
        .ruangan-section { 
            background: #f8fafc; 
            border-radius: 16px; 
            padding: 24px; 
            border: 2px solid #e2e8f0; 
        }
        .ruangan-section-title { 
            font-weight: 800; 
            font-size: .85rem; 
            text-transform: uppercase; 
            letter-spacing: .8px; 
            color: var(--text-dark); 
            margin-bottom: 16px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        .ruangan-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 12px; 
        }
        .ruangan-checkbox-item { 
            position: relative; 
            background: #fff; 
            border: 2px solid #e2e8f0; 
            border-radius: 14px; 
            padding: 16px; 
            cursor: pointer; 
            transition: var(--transition-3d); 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        .ruangan-checkbox-item:hover { border-color: var(--p-pink); }
        .ruangan-checkbox-item.selected { 
            border-color: var(--p-pink); 
            background: var(--s-pink); 
            box-shadow: 0 4px 12px rgba(213,61,102,.1); 
        }
        .ruangan-checkbox-item input[type="checkbox"] { 
            width: 20px; 
            height: 20px; 
            accent-color: var(--p-pink); 
            cursor: pointer; 
            flex-shrink: 0; 
        }
        .ruangan-checkbox-item .ruangan-info { flex: 1; min-width: 0; }
        .ruangan-checkbox-item .ruangan-nama { 
            font-weight: 700; 
            font-size: .85rem; 
            color: var(--text-dark); 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .ruangan-checkbox-item .ruangan-kapasitas { 
            font-size: .72rem; 
            color: var(--text-muted); 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .ruangan-checkbox-item .ruangan-check-icon { 
            display: none; 
            color: var(--p-pink); 
            font-size: 1.2rem; 
        }
        .ruangan-checkbox-item.selected .ruangan-check-icon { display: block; }
        .ruangan-empty { 
            text-align: center; 
            padding: 30px; 
            color: var(--text-muted); 
            font-size: .85rem; 
        }

        /* STATUS TOGGLE */
        .status-toggle-group { 
            display: flex; 
            gap: 12px; 
            margin-top: 8px; 
        }
        .status-option { 
            flex: 1; 
            padding: 14px 16px; 
            border-radius: 14px; 
            border: 2px solid #e2e8f0; 
            cursor: pointer; 
            text-align: center; 
            transition: var(--transition-3d); 
            background: #fff; 
        }
        .status-option:hover { border-color: var(--p-pink); }
        .status-option.active { 
            border-color: var(--p-pink); 
            background: var(--s-pink); 
        }
        .status-option input { display: none; }
        .status-option .status-icon { font-size: 1.3rem; margin-bottom: 4px; }
        .status-option .status-label { font-weight: 700; font-size: .85rem; }
        .status-option .status-desc { font-size: .7rem; color: var(--text-muted); }

        /* BUTTONS */
        .btn-submit { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #fff; 
            border: none; 
            border-radius: 14px; 
            padding: 14px 32px; 
            font-weight: 800; 
            font-size: .95rem; 
            transition: var(--transition-3d); 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
        }
        .btn-submit:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 12px 28px rgba(213,61,102,.35); 
            color: #fff; 
        }
        .btn-batal { 
            background: #f1f5f9; 
            color: #475569; 
            border: none; 
            border-radius: 14px; 
            padding: 14px 32px; 
            font-weight: 800; 
            font-size: .95rem; 
            transition: var(--transition-3d); 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none; 
        }
        .btn-batal:hover { 
            background: #e2e8f0; 
            color: #1e293b; 
            transform: translateY(-3px); 
        }

        /* ALERT */
        .alert-custom { 
            background: #fef2f2; 
            border: none; 
            border-left: 4px solid #dc2626; 
            border-radius: 12px; 
            color: #991b1b; 
            font-size: .85rem; 
            padding: 14px 18px; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }

        /* INFO BADGE */
        .info-badge { 
            background: #eff6ff; 
            border-radius: 12px; 
            padding: 12px 16px; 
            font-size: .8rem; 
            color: #1d4ed8; 
            font-weight: 600; 
            display: flex; 
            align-items: flex-start; 
            gap: 8px; 
            margin-bottom: 20px; 
            border: 1px solid #bfdbfe; 
        }

        /* ANIMATIONS */
        @keyframes fadeIn { 
            from { opacity:0; transform:translateY(-10px); } 
            to { opacity:1; transform:translateY(0); } 
        }
        .fade-in-up { animation: fadeIn .5s ease-out; }

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
            .form-card-header {
                padding: 24px;
            }
            .form-card-body {
                padding: 24px;
            }
            .form-card-header h4 {
                font-size: 1.15rem;
            }
            .breadcrumb-custom {
                font-size: .75rem;
                margin-bottom: 18px;
            }
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
                border-radius: 16px;
            }
            .form-card-header {
                padding: 20px;
            }
            .form-card-header h4 {
                font-size: 1.1rem;
            }
            .form-card-header p {
                font-size: .8rem;
            }
            .form-card-body {
                padding: 20px 16px;
            }
            .form-control-custom, .form-select-custom {
                padding: 12px 14px;
                font-size: .88rem;
                border-radius: 12px;
            }
            .form-label {
                font-size: .7rem;
            }
            
            /* Current foto stack vertically */
            .current-foto-box {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
            .current-foto-box img,
            .current-foto-box .placeholder-icon {
                width: 64px;
                height: 64px;
            }

            /* Ruangan grid smaller */
            .ruangan-grid {
                grid-template-columns: 1fr;
            }
            .ruangan-section {
                padding: 16px;
            }
            .ruangan-checkbox-item {
                padding: 12px;
            }

            /* Status toggle stack */
            .status-toggle-group {
                flex-direction: column;
            }
            .status-option {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 12px;
                text-align: left;
                padding: 12px 14px;
            }
            .status-option .status-icon {
                margin-bottom: 0;
                font-size: 1.1rem;
            }
            .status-option .status-label {
                font-size: .8rem;
            }
            .status-option .status-desc {
                display: none;
            }

            /* Buttons full width stack */
            .d-flex.gap-3.mt-4 {
                flex-direction: column;
                gap: 10px !important;
            }
            .btn-submit, .btn-batal {
                width: 100%;
                justify-content: center;
                padding: 13px;
                font-size: .9rem;
            }

            /* File upload zone */
            .file-upload-zone {
                padding: 20px 14px;
            }
            .file-upload-zone i {
                font-size: 1.6rem;
            }
            .file-upload-zone p {
                font-size: .8rem;
            }

            /* Alert & info badge */
            .alert-custom, .info-badge {
                font-size: .78rem;
                padding: 12px 14px;
            }
            .info-badge {
                flex-direction: column;
                gap: 6px;
            }

            /* Breadcrumb hide chevrons on very small screens if needed */
            .breadcrumb-custom .bi-chevron-right {
                display: none;
            }
            .breadcrumb-custom {
                gap: 4px;
            }
            .breadcrumb-custom a, .breadcrumb-custom .active {
                font-size: .7rem;
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
            .form-card-body {
                padding: 16px 12px;
            }
        }

        /* Large screens optimization */
        @media (min-width: 1400px) {
            .ruangan-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
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
    <div style="width:40px;"></div> <!-- spacer for balance -->
</div>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../../Role/Admin/index.php" class="nav-link-custom">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="../Pelanggan/list.php"     class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                        <li><a href="../Paket Foto/list.php"   class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                        <li><a href="../Ruangan/list.php"       class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                        <li><a href="../Properti/list.php"      class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                        <li><a href="./list.php"                class="submenu-link active"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                        <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        <li><a href="../Barang Cetak/list.php"  class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
    <div class="dashboard-header">
        <div>
            <h3 class="fw-bold mb-1">Edit Tema Foto</h3>
            <p class="text-muted small mb-0">Perbarui informasi tema dan ruangan yang bisa menggunakannya.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;">
                <i class="bi bi-clock-history me-1 text-danger"></i>
                <span id="live-clock">Memuat waktu...</span>
            </span>
            <div class="profile-header-btn shadow-sm" title="Profil">
                <img src="<?= $foto_admin_src ?>" alt="Admin">
            </div>
        </div>
    </div>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom">
        <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size:.7rem;color:#cbd5e1;"></i>
        <a href="./list.php">Data Master</a>
        <i class="bi bi-chevron-right" style="font-size:.7rem;color:#cbd5e1;"></i>
        <a href="./list.php">Tema Foto</a>
        <i class="bi bi-chevron-right" style="font-size:.7rem;color:#cbd5e1;"></i>
        <span class="active">Edit: <?= htmlspecialchars($tema['Nama_Tema']) ?></span>
    </div>

    <!-- FORM CARD -->
    <div class="form-card fade-in-up">
        <div class="form-card-header">
            <h4><i class="bi bi-pencil-square me-2"></i>Edit Tema Foto</h4>
            <p>Perbarui data tema foto. Kosongkan field foto jika tidak ingin mengganti gambar.</p>
        </div>
        <div class="form-card-body">

            <?php if ($error != ""): ?>
            <div class="alert-custom">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Info order aktif -->
            <?php
            $cek_order_aktif_info = safe_sqlsrv_fetch($conn,
                "SELECT COUNT(*) AS total FROM [Order] WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4",
                [$id_tema]
            );
            $jml_order_aktif = (int)($cek_order_aktif_info['total'] ?? 0);
            if ($jml_order_aktif > 0):
            ?>
            <div class="info-badge">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <div>
                    Tema ini memiliki <strong><?= $jml_order_aktif ?> order aktif</strong>.
                    Ruangan yang sedang digunakan order aktif <strong>tidak boleh dihapus</strong> dari daftar ruangan tema.
                    Perubahan nama, kategori, deskripsi, foto, dan status tetap bisa dilakukan.
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formEditTema">
                <div class="row">
                    <!-- Nama Tema -->
                    <div class="col-12 col-md-8 mb-4">
                        <label class="form-label">Nama Tema Foto <span class="required">*</span></label>
                        <input type="text" name="nama_tema" class="form-control-custom" required
                               maxlength="100" placeholder="Contoh: Vintage Retro"
                               value="<?= htmlspecialchars($tema['Nama_Tema']) ?>">
                        <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 100 karakter, nama harus unik</div>
                    </div>
                    <!-- Kategori -->
                    <div class="col-12 col-md-4 mb-4">
                        <label class="form-label">Kategori <span class="required">*</span></label>
                        <select name="kategori" class="form-select-custom" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($daftar_kategori as $kat): ?>
                                <option value="<?= $kat ?>" <?= ($tema['Kategori_Tema'] == $kat) ? 'selected' : '' ?>><?= $kat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="mb-4">
                    <label class="form-label">Deskripsi Tema</label>
                    <textarea name="deskripsi" class="form-control-custom"
                              maxlength="255" placeholder="Deskripsikan konsep dan suasana tema ini (opsional)..."><?= htmlspecialchars($tema['Deskripsi'] ?? '') ?></textarea>
                    <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 255 karakter</div>
                </div>

                <!-- Foto Tema -->
                <div class="mb-4">
                    <label class="form-label">Foto Tema</label>

                    <!-- Foto saat ini -->
                    <?php
                    $foto_cur   = $tema['Foto_Tema'] ?? '';
                    $foto_path  = "../../assets/img/tema/" . $foto_cur;
                    $ada_foto   = !empty($foto_cur) && $foto_cur !== 'default_tema.jpg' && file_exists($foto_path);
                    ?>
                    <div class="current-foto-box">
                        <?php if ($ada_foto): ?>
                            <img src="<?= $foto_path ?>" alt="Foto saat ini">
                        <?php else: ?>
                            <div class="placeholder-icon"><i class="bi bi-palette-fill"></i></div>
                        <?php endif; ?>
                        <div class="current-foto-info">
                            <p>Foto <?= $ada_foto ? 'saat ini' : 'default' ?></p>
                            <small><?= $ada_foto ? htmlspecialchars($foto_cur) : 'Belum ada foto khusus' ?></small><br>
                            <small class="text-muted">Upload foto baru di bawah untuk menggantinya.</small>
                        </div>
                    </div>

                    <div class="file-upload-zone" id="dropzone" onclick="document.getElementById('foto-input').click()">
                        <input type="file" name="foto" id="foto-input"
                               accept="image/jpeg,image/jpg,image/png,image/webp"
                               onchange="handleFileSelect(event)">
                        <i class="bi bi-arrow-up-circle-fill" id="upload-icon"></i>
                        <p id="upload-text">Klik atau seret foto baru ke sini (opsional)</p>
                        <small>JPG, JPEG, PNG, WEBP — Maksimal 2MB</small>
                    </div>
                    <div id="preview-container">
                        <img id="preview-img" src="" alt="Preview">
                        <button type="button" class="remove-preview" onclick="removePreview(event)" title="Hapus pilihan foto">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="input-hint mt-2">
                        <i class="bi bi-info-circle"></i> Biarkan kosong jika tidak ingin mengganti foto
                    </div>
                </div>

                <!-- PILIH RUANGAN -->
                <div class="mb-4">
                    <label class="form-label">Ruangan yang Bisa Menggunakan Tema Ini <span class="required">*</span></label>
                    <div class="input-hint mb-3">
                        <i class="bi bi-info-circle"></i> Pilih minimal 1 ruangan
                    </div>

                    <div class="ruangan-section">
                        <div class="ruangan-section-title">
                            <i class="bi bi-door-open-fill text-danger"></i> Ruangan Tersedia
                        </div>
                        <?php if (!empty($daftar_ruangan)): ?>
                            <div class="ruangan-grid">
                                <?php foreach ($daftar_ruangan as $ruangan):
                                    $is_checked  = in_array($ruangan['ID_Ruangan'], $ruangan_terhubung);
                                    $is_selected = $is_checked ? 'selected' : '';
                                ?>
                                    <div class="ruangan-checkbox-item <?= $is_selected ?>" onclick="toggleRuangan(this)">
                                        <input type="checkbox" name="ruangan[]"
                                               value="<?= $ruangan['ID_Ruangan'] ?>"
                                               <?= $is_checked ? 'checked' : '' ?>
                                               onchange="updateRuanganCount()">
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
                                <i class="bi bi-exclamation-circle fs-1 mb-2 d-block" style="color:#cbd5e1;"></i>
                                <p>Belum ada ruangan aktif. <a href="../Ruangan/add.php" style="color:var(--p-pink);">Tambah ruangan dulu</a>.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="input-hint mt-2" id="ruangan-count-hint">
                        <i class="bi bi-check-circle"></i>
                        <span id="ruangan-count">0</span> ruangan terpilih
                    </div>
                </div>

                <!-- Buttons -->
                <div class="d-flex gap-3 mt-4">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="bi bi-check2-circle"></i> Simpan Perubahan
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
    // Toggle Sidebar Mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show-mobile');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('show-mobile') ? 'hidden' : '';
    }

    // Close sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 991) {
            document.getElementById('sidebar').classList.remove('show-mobile');
            document.getElementById('sidebarOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Toggle Submenu
    document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const target  = document.querySelector(this.getAttribute('data-target'));
            const chevron = this.querySelector('.icon-chevron');
            const isShown = target.classList.contains('show');
            document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.icon-chevron').forEach(ic => ic.style.transform = 'rotate(0deg)');
            if (!isShown) {
                target.classList.add('show');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        });
    });

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
            hint.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${checked} ruangan terpilih`;
        }
    }

    // File Upload & Preview
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (file.size > 2097152) {
            Swal.fire({ icon: 'error', title: 'Ukuran Terlalu Besar', text: 'Ukuran gambar maksimal 2MB.', confirmButtonColor: '#D53D66' });
            event.target.value = ''; return;
        }
        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            Swal.fire({ icon: 'error', title: 'Format Tidak Valid', text: 'Format gambar harus JPG, JPEG, PNG, atau WEBP.', confirmButtonColor: '#D53D66' });
            event.target.value = ''; return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('preview-container').style.display = 'block';
            document.getElementById('upload-icon').style.display = 'none';
            document.getElementById('upload-text').textContent = file.name;
        };
        reader.readAsDataURL(file);
    }

    function removePreview(e) {
        e.stopPropagation();
        document.getElementById('foto-input').value = '';
        document.getElementById('preview-container').style.display = 'none';
        document.getElementById('upload-icon').style.display = 'block';
        document.getElementById('upload-text').textContent = 'Klik atau seret foto baru ke sini (opsional)';
    }

    // Drag & Drop
    const dz = document.getElementById('dropzone');
    dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('foto-input').files = files;
            handleFileSelect({ target: { files } });
        }
    });

    // Form Validation
    document.getElementById('formEditTema').addEventListener('submit', function(e) {
        const checked = document.querySelectorAll('input[name="ruangan[]"]:checked').length;
        if (checked === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning', title: 'Ruangan Belum Dipilih',
                text: 'Pilih minimal 1 ruangan yang bisa menggunakan tema ini!',
                confirmButtonColor: '#D53D66'
            });
        }
    });

    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' })
        .then(r => { if (r.isConfirmed) window.location.href = '../../logout.php'; });
    }

    function confirmLandingPage(e) {
        e.preventDefault();
        Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' })
        .then(r => { if (r.isConfirmed) window.location.href = '../../index.php'; });
    }

    // Jam Real-Time
    function updateLiveClock() {
        const now = new Date();
        const days   = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
        const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
        document.getElementById('live-clock').innerText =
            `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ` +
            `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
    }
    setInterval(updateLiveClock, 1000); updateLiveClock();

    // Init count
    updateRuanganCount();
</script>

<?php if ($success): ?>
<script>
    Swal.fire({
        icon: 'success', title: 'Berhasil Diperbarui!',
        text: 'Data tema foto telah berhasil disimpan.',
        confirmButtonColor: '#D53D66'
    }).then(() => window.location = 'list.php?status_sukses=edit');
</script>
<?php endif; ?>

</body>
</html>