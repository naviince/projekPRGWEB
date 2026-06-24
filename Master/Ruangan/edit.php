<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return [];
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID ruangan tidak valid");
    exit();
}

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

$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT * FROM Ruangan WHERE ID_Ruangan = ? AND Is_Deleted = 0", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan atau sudah dihapus");
    exit();
}

$daftar_paket = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Paket, Nama_Paket, Harga_Paket, Kapasitas_Orang, Foto_Paket, Durasi_Waktu 
     FROM Paket_Foto 
     WHERE Status = 1 AND Is_Deleted = 0 
     ORDER BY Harga_Paket ASC"
);

$paket_terhubung = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Ruangan = ?",
    [$id]
);
$paket_terhubung_ids = array_column($paket_terhubung, 'ID_Paket');

$error = "";
$success = false;
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $nama       = trim($_POST['nama_ruangan'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $status     = (int)($_POST['status'] ?? 1);
    $paket_baru = $_POST['paket'] ?? [];

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

    if (empty($paket_baru)) {
        $field_errors['paket'] = "Pilih minimal 1 paket!";
    }

    if (empty($field_errors)) {
        $cek_dup = safe_sqlsrv_fetch($conn, 
            "SELECT COUNT(*) as total FROM Ruangan WHERE Nama_Ruangan = ? AND ID_Ruangan <> ? AND Is_Deleted = 0", 
            [$nama, $id]
        );
        if (($cek_dup['total'] ?? 0) > 0) {
            $field_errors['nama_ruangan'] = "Nama ini sudah digunakan ruangan lain!";
        }
    }

    $foto_lama = $ruangan['Foto_Ruangan'] ?? '';
    $foto_baru = $foto_lama;
    $upload_path = '';
    $hapus_foto_lama = false;

    if (empty($field_errors) && isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
        $foto_name = $_FILES['foto']['name'];
        $foto_tmp  = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $foto_error = $_FILES['foto']['error'];

        if ($foto_error != UPLOAD_ERR_OK) {
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
                    $upload_dir = "../../assets/img/ruangan/";
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($foto_tmp, $upload_path)) {
                        $foto_baru = $new_filename;
                        $hapus_foto_lama = true;
                    } else {
                        $field_errors['foto'] = "Gagal simpan file ke server!";
                    }
                }
            }
        }
    }

    if (!empty($field_errors)) {
        $error = "Mohon lengkapi semua field yang bertanda merah (*) di bawah!";
    } else {
        $begin_result = sqlsrv_begin_transaction($conn);
        if ($begin_result === false) {
            $error = "Gagal memulai transaksi database.";
            if (!empty($upload_path) && file_exists($upload_path)) unlink($upload_path);
        } else {
            try {
                $sql_update = "UPDATE Ruangan SET 
                    Nama_Ruangan = ?, Deskripsi = ?, 
                    Foto_Ruangan = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() 
                    WHERE ID_Ruangan = ?";
                $params_update = [$nama, $deskripsi, $foto_baru, $status, $nama_admin, $id];
                $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

                if ($stmt_update === false) {
                    throw new Exception("Gagal update ruangan: " . json_encode(sqlsrv_errors()));
                }
                sqlsrv_free_stmt($stmt_update);

                $paket_sekarang = [];
                $sql_now = "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Ruangan = ?";
                $stmt_now = sqlsrv_query($conn, $sql_now, [$id]);
                while ($row = sqlsrv_fetch_array($stmt_now, SQLSRV_FETCH_ASSOC)) {
                    $paket_sekarang[] = $row['ID_Paket'];
                }
                sqlsrv_free_stmt($stmt_now);

                foreach ($paket_baru as $id_paket) {
                    $id_paket = (int)$id_paket;
                    if (!in_array($id_paket, $paket_sekarang)) {
                        $cek_paket = safe_sqlsrv_fetch($conn,
                            "SELECT ID_Paket FROM Paket_Foto WHERE ID_Paket = ? AND Status = 1 AND Is_Deleted = 0",
                            [$id_paket]
                        );
                        if (!$cek_paket) {
                            throw new Exception("Paket ID {$id_paket} tidak valid atau tidak aktif.");
                        }

                        $sql_insert = "INSERT INTO Paket_Ruangan (ID_Paket, ID_Ruangan) VALUES (?, ?)";
                        $stmt_insert = sqlsrv_query($conn, $sql_insert, [$id_paket, $id]);
                        if ($stmt_insert === false) {
                            throw new Exception("Gagal tambah relasi paket ID {$id_paket}: " . json_encode(sqlsrv_errors()));
                        }
                        sqlsrv_free_stmt($stmt_insert);
                    }
                }

                foreach ($paket_sekarang as $id_paket_lama) {
                    if (!in_array($id_paket_lama, $paket_baru)) {
                        $cek_order = safe_sqlsrv_fetch($conn,
                            "SELECT COUNT(*) as total FROM [Order] 
                             WHERE ID_Paket = ? AND ID_Ruangan = ? AND Status = 1 AND Status_Order <> 4",
                            [$id_paket_lama, $id]
                        );

                        if (($cek_order['total'] ?? 0) > 0) {
                            continue;
                        }

                        $sql_delete = "DELETE FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?";
                        $stmt_delete = sqlsrv_query($conn, $sql_delete, [$id_paket_lama, $id]);
                        if ($stmt_delete === false) {
                            throw new Exception("Gagal hapus relasi paket ID {$id_paket_lama}: " . json_encode(sqlsrv_errors()));
                        }
                        sqlsrv_free_stmt($stmt_delete);
                    }
                }

                $commit_result = sqlsrv_commit($conn);
                if ($commit_result === false) {
                    throw new Exception("Gagal commit transaksi: " . json_encode(sqlsrv_errors()));
                }

                if ($hapus_foto_lama && !empty($foto_lama) && $foto_lama != 'default_ruangan.jpg') {
                    $old_path = "../../assets/img/ruangan/" . $foto_lama;
                    if (file_exists($old_path)) unlink($old_path);
                }

                $success = true;

                $ruangan = safe_sqlsrv_fetch($conn, "SELECT * FROM Ruangan WHERE ID_Ruangan = ?", [$id]);
                $paket_terhubung = safe_sqlsrv_fetch_all($conn, "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Ruangan = ?", [$id]);
                $paket_terhubung_ids = array_column($paket_terhubung, 'ID_Paket');

            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                if (!empty($upload_path) && file_exists($upload_path)) unlink($upload_path);
                $error = $e->getMessage();
            }
        }
    }
}

$foto_existing = "../../assets/img/ruangan/" . ($ruangan['Foto_Ruangan'] ?? 'default_ruangan.jpg');
$foto_existing_src = file_exists($foto_existing) ? $foto_existing : $default_svg_avatar;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ruangan – SpotLight Studio</title>
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
        .current-foto-box { border-radius: 16px; overflow: hidden; border: 2px solid var(--light-pink); margin-bottom: 16px; position: relative; }
        .current-foto-box img { width: 100%; max-height: 200px; object-fit: cover; display: block; }
        .current-foto-label { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.6)); color: #fff; padding: 12px 16px; font-size: 0.8rem; font-weight: 700; }
        .file-upload-zone { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 24px; text-align: center; transition: var(--transition-3d); cursor: pointer; background: #f8fafc; }
        .file-upload-zone:hover, .file-upload-zone.dragover { border-color: var(--p-pink); background: var(--s-pink); }
        .file-upload-zone.is-error { border-color: var(--error-red) !important; background-color: var(--error-bg) !important; }
        .file-upload-zone i { font-size: 2rem; color: #cbd5e1; margin-bottom: 8px; display: block; }
        .file-upload-zone p { font-size: 0.85rem; color: #64748b; font-weight: 600; margin: 0; }
        .file-upload-zone small { font-size: 0.7rem; color: #94a3b8; }
        .file-upload-zone input[type="file"] { display: none; }
        #preview-container { display: none; margin-top: 16px; position: relative; border-radius: 14px; overflow: hidden; border: 2px solid var(--light-pink); }
        #preview-container img { width: 100%; max-height: 200px; object-fit: cover; display: block; }
        #preview-container .remove-preview { position: absolute; top: 10px; right: 10px; background: rgba(220, 38, 38, 0.9); color: #fff; border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; transition: all 0.2s; }
        #preview-container .remove-preview:hover { background: #dc2626; transform: scale(1.1); }
        .paket-section { background: #f8fafc; border-radius: 16px; padding: 24px; border: 2px solid #e2e8f0; transition: var(--transition-3d); }
        .paket-section.is-error { border-color: var(--error-red) !important; background-color: var(--error-bg) !important; }
        .paket-section-title { font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-dark); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .paket-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
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
        .paket-checkbox-item.has-order { opacity: 0.7; background: #f8fafc; border-color: #e2e8f0; cursor: not-allowed; }
        .paket-checkbox-item.has-order:hover { border-color: #e2e8f0; transform: none; }
        .paket-checkbox-item.has-order.selected { border-color: #059669; background: #ecfdf5; }
        .order-badge { font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 50px; background: #fef3c7; color: #d97706; margin-left: 6px; }
        .lock-icon { color: #dc2626; font-size: 1rem; margin-left: 8px; }
        .paket-empty { text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.85rem; }
        .paket-section-error { display: none; font-size: 0.8rem; color: var(--error-red); font-weight: 700; margin-top: 8px; align-items: center; gap: 4px; }
        .paket-section-error.show { display: flex; }
        .status-toggle-group { display: flex; gap: 12px; margin-top: 8px; }
        .status-option { flex: 1; padding: 14px 16px; border-radius: 14px; border: 2px solid #e2e8f0; cursor: pointer; text-align: center; transition: var(--transition-3d); background: #ffffff; }
        .status-option:hover { border-color: var(--p-pink); }
        .status-option.active { border-color: var(--p-pink); background: var(--s-pink); }
        .status-option input { display: none; }
        .status-option .status-icon { font-size: 1.3rem; margin-bottom: 4px; }
        .status-option .status-label { font-weight: 700; font-size: 0.85rem; }
        .status-option .status-desc { font-size: 0.7rem; color: var(--text-muted); }
        .btn-submit { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; }
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
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Menyimpan perubahan...</div>
    </div>

    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
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
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Edit Ruangan</h3>
                <p class="text-muted small mb-0">Perbarui data ruangan dan relasi paket foto.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
            </div>
        </div>

        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-pencil-square me-2"></i>Edit Ruangan: <?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></h4>
                <p>Perbarui informasi ruangan dan relasi paket foto.</p>
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
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label"><i class="bi bi-type"></i> Nama Ruangan <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                            <input type="text" name="nama_ruangan" id="nama_ruangan" class="form-control-custom <?= isset($field_errors['nama_ruangan']) ? 'is-error' : '' ?>" required maxlength="100" placeholder="Contoh: Studio A Minimalis" value="<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>">
                            <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 100 karakter, nama harus unik</div>
                            <div class="field-error-msg <?= isset($field_errors['nama_ruangan']) ? 'show' : '' ?>" id="error-nama_ruangan"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['nama_ruangan'] ?? '' ?></span></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-card-text"></i> Deskripsi Ruangan <span class="required">*</span><span class="badge-wajib">Wajib</span></label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control-custom <?= isset($field_errors['deskripsi']) ? 'is-error' : '' ?>" required maxlength="255" placeholder="Deskripsikan suasana, konsep, dan keunggulan ruangan ini..."><?= htmlspecialchars($ruangan['Deskripsi'] ?? '') ?></textarea>
                        <div class="input-hint"><i class="bi bi-info-circle"></i> Maksimal 255 karakter, akan ditampilkan ke pelanggan</div>
                        <div class="field-error-msg <?= isset($field_errors['deskripsi']) ? 'show' : '' ?>" id="error-deskripsi"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['deskripsi'] ?? '' ?></span></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-image"></i> Foto Ruangan <span class="badge-wajib" style="background: #718096;">Opsional</span></label>
                        <?php if (file_exists($foto_existing) && $ruangan['Foto_Ruangan'] != 'default_ruangan.jpg'): ?>
                            <div class="current-foto-box">
                                <img src="<?= $foto_existing_src ?>" alt="Foto Saat Ini">
                                <div class="current-foto-label"><i class="bi bi-image me-1"></i> Foto saat ini</div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px;"><i class="bi bi-image me-2 text-muted"></i> Belum ada foto ruangan</div>
                        <?php endif; ?>
                        <div class="file-upload-zone <?= isset($field_errors['foto']) ? 'is-error' : '' ?>" id="dropzone" onclick="document.getElementById('foto-input').click()">
                            <input type="file" name="foto" id="foto-input" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="handleFileSelect(event)">
                            <i class="bi bi-camera-fill" id="upload-icon"></i>
                            <p id="upload-text">Klik untuk ganti foto (opsional)</p>
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
                        <div class="input-hint mb-3"><i class="bi bi-info-circle"></i> Pilih minimal 1 paket foto yang bisa menggunakan ruangan ini</div>
                        <div class="paket-section <?= isset($field_errors['paket']) ? 'is-error' : '' ?>" id="paket-section">
                            <div class="paket-section-title"><i class="bi bi-camera-fill text-danger"></i> Paket Foto Tersedia</div>
                            <?php if (!empty($daftar_paket)): ?>
                                <div class="paket-grid">
                                    <?php foreach ($daftar_paket as $paket): 
                                        $is_checked = in_array($paket['ID_Paket'], $paket_terhubung_ids) ? 'checked' : '';
                                        $is_selected = $is_checked ? 'selected' : '';
                                        $cek_order_paket = safe_sqlsrv_count($conn,
                                            "SELECT COUNT(*) as total FROM [Order] 
                                             WHERE ID_Paket = ? AND ID_Ruangan = ? AND Status = 1 AND Status_Order <> 4",
                                            [$paket['ID_Paket'], $id]
                                        );
                                        $has_order = ($cek_order_paket > 0);
                                        $disabled = $has_order ? 'disabled' : '';
                                        $order_badge = $has_order ? '<span class="order-badge">' . $cek_order_paket . ' order</span>' : '';
                                    ?>
                                        <div class="paket-checkbox-item <?= $is_selected ?> <?= $has_order ? 'has-order' : '' ?>" onclick="<?= $has_order ? '' : 'togglePaket(this)' ?>" title="<?= $has_order ? 'Tidak bisa dihapus: ' . $cek_order_paket . ' order aktif' : 'Klik untuk pilih' ?>">
                                            <input type="checkbox" name="paket[]" value="<?= $paket['ID_Paket'] ?>" <?= $is_checked ?> <?= $disabled ?> onchange="updatePaketCount()">
                                            <div class="paket-info">
                                                <div class="paket-nama"><?= htmlspecialchars($paket['Nama_Paket']) ?> <?= $order_badge ?></div>
                                                <div class="paket-harga">Rp <?= number_format($paket['Harga_Paket'], 0, ',', '.') ?></div>
                                                <div class="paket-kapasitas"><?= $paket['Kapasitas_Orang'] ?> orang</div>
                                                <div class="paket-durasi"><?= $paket['Durasi_Waktu'] ?> menit</div>
                                            </div>
                                            <i class="bi bi-check-circle-fill paket-check-icon"></i>
                                            <?php if ($has_order): ?><i class="bi bi-lock-fill lock-icon" title="Tidak bisa dihapus karena ada order aktif"></i><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="paket-empty"><i class="bi bi-exclamation-circle fs-1 mb-2 d-block" style="color: #cbd5e1;"></i><p>Belum ada paket foto aktif. <a href="../Paket Foto/add.php" style="color: var(--p-pink);">Tambah paket foto dulu</a>.</p></div>
                            <?php endif; ?>
                        </div>
                        <div class="paket-section-error <?= isset($field_errors['paket']) ? 'show' : '' ?>" id="error-paket"><i class="bi bi-exclamation-circle-fill"></i><span><?= $field_errors['paket'] ?? '' ?></span></div>
                        <div class="input-hint mt-2" id="paket-count-hint"><i class="bi bi-check-circle"></i> <span id="paket-count"><?= count($paket_terhubung_ids) ?></span> paket terpilih</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-toggle-on"></i> Status Ruangan</label>
                        <div class="status-toggle-group">
                            <label class="status-option <?= ($ruangan['Status'] == 1) ? 'active' : '' ?>" onclick="selectStatus(this, 1)">
                                <input type="radio" name="status" value="1" <?= ($ruangan['Status'] == 1) ? 'checked' : '' ?>><div class="status-icon">✅</div><div class="status-label">Aktif</div><div class="status-desc">Bisa dipesan</div>
                            </label>
                            <label class="status-option <?= ($ruangan['Status'] == 0) ? 'active' : '' ?>" onclick="selectStatus(this, 0)">
                                <input type="radio" name="status" value="0" <?= ($ruangan['Status'] == 0) ? 'checked' : '' ?>><div class="status-icon">⛔</div><div class="status-label">Nonaktif</div><div class="status-desc">Sementara tutup</div>
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="update" class="btn-submit" id="btnUpdate"><i class="bi bi-check2-all"></i> Simpan Perubahan</button>
                        <a href="list.php" class="btn-batal"><i class="bi bi-x-circle"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
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
        function selectStatus(el, val) { document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active')); el.classList.add('active'); el.querySelector('input').checked = true; }
        function togglePaket(el) { const checkbox = el.querySelector('input[type="checkbox"]'); checkbox.checked = !checkbox.checked; if (checkbox.checked) el.classList.add('selected'); else el.classList.remove('selected'); updatePaketCount(); clearFieldError('paket'); }
        function updatePaketCount() { const checked = document.querySelectorAll('input[name="paket[]"]:checked').length; document.getElementById('paket-count').textContent = checked; const hint = document.getElementById('paket-count-hint'); if (checked === 0) { hint.style.color = '#dc2626'; hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Pilih minimal 1 paket!'; } else { hint.style.color = '#059669'; hint.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + checked + ' paket terpilih'; } }
        function handleFileSelect(event) { const file = event.target.files[0]; const previewContainer = document.getElementById('preview-container'); const previewImg = document.getElementById('preview-img'); const uploadIcon = document.getElementById('upload-icon'); const uploadText = document.getElementById('upload-text'); if (file) { if (file.size > 2097152) { Swal.fire({ icon: 'error', title: 'Ukuran Terlalu Besar', text: 'Ukuran gambar maksimal 2MB.', confirmButtonColor: '#D53D66' }); event.target.value = ''; return; } const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']; if (!allowed.includes(file.type)) { Swal.fire({ icon: 'error', title: 'Format Tidak Valid', text: 'Format gambar harus JPG, JPEG, PNG, atau WEBP.', confirmButtonColor: '#D53D66' }); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = function(e) { previewImg.src = e.target.result; previewContainer.style.display = 'block'; uploadIcon.style.display = 'none'; uploadText.textContent = 'Foto baru: ' + file.name; }; reader.readAsDataURL(file); clearFieldError('foto'); } }
        function removePreview(e) { e.stopPropagation(); const input = document.getElementById('foto-input'); const previewContainer = document.getElementById('preview-container'); const uploadIcon = document.getElementById('upload-icon'); const uploadText = document.getElementById('upload-text'); input.value = ''; previewContainer.style.display = 'none'; uploadIcon.style.display = 'block'; uploadText.textContent = 'Klik untuk ganti foto (opsional)'; }
        const dropzone = document.getElementById('dropzone');
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); const files = e.dataTransfer.files; if (files.length > 0) { document.getElementById('foto-input').files = files; handleFileSelect({ target: { files: files } }); } });
        function confirmLogout(e) { e.preventDefault(); Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; }); }
        function confirmLandingPage(e) { e.preventDefault(); Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; }); }
        function updateLiveClock() { const now = new Date(); const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"]; const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"]; document.getElementById('live-clock').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`; }
        setInterval(updateLiveClock, 1000); updateLiveClock();
        function clearFieldError(fieldName) { const field = document.getElementById(fieldName); const errorMsg = document.getElementById('error-' + fieldName); if (field) field.classList.remove('is-error'); if (errorMsg) errorMsg.classList.remove('show'); if (fieldName === 'paket') { const section = document.getElementById('paket-section'); if (section) section.classList.remove('is-error'); } if (fieldName === 'foto') { const dropzone = document.getElementById('dropzone'); if (dropzone) dropzone.classList.remove('is-error'); } }
        document.getElementById('nama_ruangan').addEventListener('input', function() { if (this.value.trim()) clearFieldError('nama_ruangan'); });
        document.getElementById('deskripsi').addEventListener('input', function() { if (this.value.trim()) clearFieldError('deskripsi'); });
        document.getElementById('formRuangan').addEventListener('submit', function(e) { const paketChecked = document.querySelectorAll('input[name="paket[]"]:checked').length; if (paketChecked === 0) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Paket Belum Dipilih', text: 'Pilih minimal 1 paket foto yang bisa menggunakan ruangan ini!', confirmButtonColor: '#D53D66' }); return false; } document.getElementById('loadingOverlay').classList.add('active'); return true; });
        updatePaketCount();
        <?php if (!empty($error)): ?> Swal.fire({ icon: 'error', title: 'Gagal Menyimpan!', html: '<?= addslashes($error) ?>', confirmButtonColor: '#D53D66' }); <?php endif; ?>
        <?php if ($success): ?> Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data ruangan dan relasi paket foto telah diperbarui.', confirmButtonColor: '#D53D66' }).then(() => { window.location.href = 'list.php?status_sukses=edit'; }); <?php endif; ?>
    </script>
</body>
</html>