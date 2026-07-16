<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES FOTOGRAFER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];
$username_fotografer = $_SESSION['username'] ?? 'fotografer';

// =====================================================
// KONFIGURASI UPLOAD
// =====================================================
$upload_dir = '../../uploads/hasil/';
$max_file_size = 100 * 1024 * 1024; // 100 MB
$allowed_extensions = ['zip', 'jpg', 'jpeg', 'png', 'rar', 'pdf'];
$allowed_mime_types = [
    'application/zip',
    'application/x-zip-compressed',
    'application/x-rar-compressed',
    'image/jpeg',
    'image/png',
    'application/pdf'
];

// =====================================================
// HANDLE AJAX UPLOAD (Fetch API)
// =====================================================
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $response['message'] = 'ID Sesi tidak valid!';
        echo json_encode($response);
        exit();
    }

    $id_sesi = intval($_GET['id']);

    // Validasi sesi
    $q_sesi = sqlsrv_query($conn, "
        SELECT S.ID_Sesi_Foto, S.ID_Order, S.File_Hasil, S.Status_Sesi
        FROM Sesi_Foto S
        JOIN [Order] O ON S.ID_Order = O.ID_Order
        WHERE S.ID_Sesi_Foto = ? AND S.ID_Karyawan = ? AND S.Status = 1
    ", array($id_sesi, $id_fotografer));

    if (!$q_sesi || !sqlsrv_has_rows($q_sesi)) {
        $response['message'] = 'Sesi tidak ditemukan atau Anda tidak berhak mengaksesnya.';
        echo json_encode($response);
        exit();
    }

    $sesi_data = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);
    if ($sesi_data['Status_Sesi'] != 1) {
        $response['message'] = 'Sesi belum selesai. Hanya sesi selesai yang bisa diupload.';
        echo json_encode($response);
        exit();
    }

    if (!isset($_FILES['file_hasil']) || $_FILES['file_hasil']['error'] === UPLOAD_ERR_NO_FILE) {
        $response['message'] = 'Silakan pilih file untuk diupload!';
        echo json_encode($response);
        exit();
    }

    $file = $_FILES['file_hasil'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    if ($file_error !== UPLOAD_ERR_OK) {
        $response['message'] = 'Terjadi kesalahan saat upload file. Error code: ' . $file_error;
        echo json_encode($response);
        exit();
    }

    if ($file_size > $max_file_size) {
        $response['message'] = 'Ukuran file terlalu besar! Maksimal 100 MB.';
        echo json_encode($response);
        exit();
    }

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        $response['message'] = 'Format file tidak didukung! Format yang diizinkan: ZIP, JPG, JPEG, PNG, RAR, PDF.';
        echo json_encode($response);
        exit();
    }

    // Validasi MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    $mime_valid = false;
    foreach ($allowed_mime_types as $allowed) {
        if (strpos($mime_type, $allowed) !== false || strpos($allowed, $mime_type) !== false) {
            $mime_valid = true;
            break;
        }
    }
    if (!$mime_valid && in_array($file_ext, $allowed_extensions)) {
        $mime_valid = true;
    }

    if (!$mime_valid) {
        $response['message'] = 'Tipe file tidak valid!';
        echo json_encode($response);
        exit();
    }

    // Buat direktori upload jika belum ada
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $response['message'] = 'Gagal membuat folder upload. Periksa permission folder.';
            echo json_encode($response);
            exit();
        }
    }

    // Generate nama file unik
    $id_order = $sesi_data['ID_Order'];
    $timestamp = time();
    $uniqid = uniqid();
    $new_file_name = "hasil_order{$id_order}_{$timestamp}_{$uniqid}.{$file_ext}";
    $target_path = $upload_dir . $new_file_name;

    // =====================================================
    // TRANSACTION (UPLOAD BERHASIL & UPDATE STATUS ORDER)
    // =====================================================
    sqlsrv_begin_transaction($conn);

    try {
        // Hapus file lama jika ada
        if (!empty($sesi_data['File_Hasil'])) {
            $old_file = $upload_dir . $sesi_data['File_Hasil'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Pindahkan file ke server
        if (move_uploaded_file($file_tmp, $target_path)) {
            
            // 1. Update data di tabel Sesi_Foto
            $update_sql = "UPDATE Sesi_Foto SET 
                File_Hasil = ?, 
                Tanggal_Upload_Hasil = GETDATE(),
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Sesi_Foto = ? AND Status = 1";

            $update_stmt = sqlsrv_query($conn, $update_sql, array(
                $new_file_name,
                $username_fotografer,
                $id_sesi
            ));

            // CATATAN: Status_Order SENGAJA TIDAK diubah di sini. Status_Order
            // sudah ditentukan dengan benar oleh sp_SelesaiSesiFoto saat sesi
            // ditandai selesai (2=Menunggu Pelunasan, atau 3=Lunas kalau order
            // sudah dibayar lunas sekaligus di awal). Upload hasil foto adalah
            // urusan pengiriman file, bukan bagian dari alur status pembayaran
            // -- mengubahnya di sini akan menimpa/merusak status yang sudah benar.

            // Commit jika query berhasil dijalankan
            if ($update_stmt) {
                sqlsrv_commit($conn);
                $response['success'] = true;
                $response['message'] = 'File hasil foto berhasil diupload!';
                $response['redirect'] = '../../Sesi/RiwayatUpload/index.php?uploaded=1';
            } else {
                $errors = sqlsrv_errors();
                $response['message'] = 'Gagal memperbarui database: ' . ($errors[0]['message'] ?? 'Unknown error');
                sqlsrv_rollback($conn);
                if (file_exists($target_path)) {
                    unlink($target_path);
                }
            }
        } else {
            $response['message'] = 'Gagal memindahkan file ke server! Periksa permission folder uploads/hasil/.';
            sqlsrv_rollback($conn);
        }
    } catch (Exception $e) {
        $response['message'] = 'Terjadi kesalahan: ' . $e->getMessage();
        sqlsrv_rollback($conn);
        if (file_exists($target_path)) {
            unlink($target_path);
        }
    }

    echo json_encode($response);
    exit();
}

// =====================================================
// HANDLE HAPUS FILE (AJAX)
// =====================================================
$is_ajax_delete = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
                  isset($_POST['ajax_hapus']);

if ($is_ajax_delete) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $response['message'] = 'ID Sesi tidak valid!';
        echo json_encode($response);
        exit();
    }

    $id_sesi = intval($_GET['id']);

    // Mengambil File_Hasil sekaligus ID_Order untuk mengembalikan status order
    $q_sesi = sqlsrv_query($conn, "
        SELECT S.File_Hasil, S.ID_Order FROM Sesi_Foto S
        JOIN [Order] O ON S.ID_Order = O.ID_Order
        WHERE S.ID_Sesi_Foto = ? AND S.ID_Karyawan = ? AND S.Status = 1
    ", array($id_sesi, $id_fotografer));

    if (!$q_sesi || !sqlsrv_has_rows($q_sesi)) {
        $response['message'] = 'Sesi tidak ditemukan.';
        echo json_encode($response);
        exit();
    }

    $sesi_data_del = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);

    if (empty($sesi_data_del['File_Hasil'])) {
        $response['message'] = 'Tidak ada file yang bisa dihapus.';
        echo json_encode($response);
        exit();
    }

    $id_order = $sesi_data_del['ID_Order'];

    // Mulai proses transaksi hapus
    sqlsrv_begin_transaction($conn);

    try {
        $file_path = $upload_dir . $sesi_data_del['File_Hasil'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Reset data File_Hasil di tabel Sesi_Foto
        $delete_sql = "UPDATE Sesi_Foto SET 
            File_Hasil = NULL, 
            Tanggal_Upload_Hasil = NULL,
            Modified_By = ?,
            Modified_Date = GETDATE()
            WHERE ID_Sesi_Foto = ? AND Status = 1";

        $delete_stmt = sqlsrv_query($conn, $delete_sql, array($username_fotografer, $id_sesi));

        // CATATAN: Status_Order SENGAJA TIDAK diubah di sini, dengan alasan
        // yang sama seperti di handler upload -- menghapus file hasil foto
        // adalah urusan pengiriman file, bukan bagian dari alur status
        // pembayaran/sesi. Status_Order tetap seperti yang sudah ditentukan
        // sp_SelesaiSesiFoto (2=Menunggu Pelunasan atau 3=Lunas).

        if ($delete_stmt) {
            sqlsrv_commit($conn);
            $response['success'] = true;
            $response['message'] = 'File berhasil dihapus!';
            $response['redirect'] = '../../Sesi/Upload/index.php?deleted=1';
        } else {
            $errors = sqlsrv_errors();
            $response['message'] = 'Gagal menghapus dari database: ' . ($errors[0]['message'] ?? 'Unknown error');
            sqlsrv_rollback($conn);
        }
    } catch (Exception $e) {
        $response['message'] = 'Terjadi kesalahan: ' . $e->getMessage();
        sqlsrv_rollback($conn);
    }

    echo json_encode($response);
    exit();
}

// =====================================================
// AMBIL DATA SESI FOTO (untuk tampilan halaman)
// =====================================================
$error = "";
$sesi_data = null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../Sesi/Upload/index.php?error=noid");
    exit();
}

$id_sesi = intval($_GET['id']);

$q_sesi = sqlsrv_query($conn, "{CALL sp_ReadDetailSesiHasilFotografer(?, ?)}", array($id_sesi, $id_fotografer));

if (!$q_sesi) {
    $errors = sqlsrv_errors();
    die("Query error: " . ($errors[0]['message'] ?? 'Unknown database error'));
}
if (!sqlsrv_has_rows($q_sesi)) {
    header("Location: ../../Sesi/Upload/index.php?error=notfound");
    exit();
}

$sesi_data = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);
if ($sesi_data) {
    $sesi_data = array_change_key_case($sesi_data, CASE_LOWER);
}

// Validasi: hanya bisa upload jika Status_Sesi = 1 (Selesai)
if ($sesi_data['status_sesi'] != 1) {
    header("Location: ../../Sesi/Upload/index.php?error=notcompleted");
    exit();
}

// =====================================================
// AMBIL DATA PROFIL FOTOGRAFER
// =====================================================
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_fotografer));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) {
    $d_profile = array_change_key_case($d_profile, CASE_LOWER);
}

$nama_fotografer = $d_profile['nama_karyawan'] ?? 'Fotografer';
$foto_fotografer = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_fotografer)) 
    ? "../../assets/img/pelanggan/" . $foto_fotografer 
    : $default_svg_avatar;

// Format tanggal
function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $date->format('d') . ' ' . $bulan[intval($date->format('m')) - 1] . ' ' . $date->format('Y');
}

function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) {
        $time = new DateTime($time);
    }
    return $time->format('H:i');
}

// Tentukan tipe file untuk preview
$file_ext_preview = '';
if (!empty($sesi_data['file_hasil'])) {
    $file_ext_preview = strtolower(pathinfo($sesi_data['file_hasil'], PATHINFO_EXTENSION));
}
$is_image_preview = in_array($file_ext_preview, ['jpg', 'jpeg', 'png']);
$is_zip_preview = in_array($file_ext_preview, ['zip', 'rar']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Hasil Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
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
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
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
        .submenu.show { display: block !important; }
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
            background-color: rgba(213, 61, 102, 0.03);
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
            box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }

        /* CARDS */
        .card-3d {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d);
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        .card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .card-3d:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.1); 
            border-color: var(--p-pink);
        }
        .card-3d:hover::before { opacity: 1; }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .content-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        /* INFO ITEM */
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .info-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
        }

        /* UPLOAD ZONE */
        .upload-zone {
            border: 2px dashed var(--light-pink);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff, var(--s-pink));
            transition: var(--transition-3d);
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--p-pink);
            background: linear-gradient(135deg, #ffffff, #FFE4E9);
            transform: scale(1.01);
        }
        .upload-zone i {
            font-size: 3rem;
            color: var(--p-pink);
            margin-bottom: 15px;
        }
        .upload-zone-text {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        .upload-zone-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* FILE PREVIEW */
        .file-preview-area {
            margin-bottom: 20px;
        }
        .file-preview-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-radius: 16px;
            border: 2px solid #a7f3d0;
            margin-bottom: 12px;
        }
        .file-preview-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: #059669;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .file-preview-info { flex: 1; }
        .file-preview-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            word-break: break-all;
        }
        .file-preview-meta {
            font-size: 0.75rem;
            color: #059669;
            font-weight: 600;
        }

        /* PREVIEW GAMBAR */
        .image-preview-wrapper {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid var(--light-pink);
            background: #f8fafc;
            margin-bottom: 16px;
            display: none;
        }
        .image-preview-wrapper.show { display: block; }
        .image-preview-wrapper img {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            display: block;
        }
        .image-preview-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
        }
        .image-preview-overlay button {
            background: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .image-preview-overlay button:hover {
            background: rgba(185, 28, 28, 1);
        }

        /* BUTTONS */
        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 800;
            font-size: 0.9rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3);
            color: #ffffff;
        }
        .btn-action-success {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .btn-action-success:hover {
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
        }
        .btn-action-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-action-danger:hover {
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
        }
        .btn-action-secondary {
            background: #f1f5f9;
            color: var(--text-muted);
        }
        .btn-action-secondary:hover {
            background: #e2e8f0;
            color: var(--text-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* BADGE */
        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-upload { background: #dbeafe; color: #2563eb; }

        /* PROGRESS BAR */
        .progress-wrapper {
            display: none;
            margin-top: 20px;
        }
        .progress-wrapper.show { display: block; }
        .progress {
            height: 10px;
            border-radius: 10px;
            background: #f1f5f9;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* ERROR ALERT */
        .error-alert {
            border-radius: 14px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        /* ANIMATION */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Panel Fotografer</span>
            </a>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuJadwal">
                        <span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="../../Sesi/Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../../Sesi/Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../../Sesi/Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuUpload">
                        <span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span>
                        <i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="../../Sesi/Upload/index.php" class="submenu-link"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="../../Sesi/RiwayatUpload/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
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
                <i class="bi bi-box-arrow-right me-2"></i> Keluar
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../../Sesi/Upload/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Upload</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Upload Hasil</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Upload Hasil Foto</h3>
                <p class="text-muted small mb-0">Upload hasil pemotretan untuk customer.</p>
            </div>
            <a href="../../Sesi/Upload/index.php" class="btn-action btn-action-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="row g-4">
            <!-- INFO SESI -->
            <div class="col-lg-5 animate-fade-in">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-info-circle-fill text-danger me-2"></i>Detail Sesi</h5>
                        <span class="badge-status badge-selesai">Selesai</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">ID Sesi</span>
                        <span class="info-value">#<?= $sesi_data['id_sesi_foto'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID Order</span>
                        <span class="info-value">#<?= $sesi_data['id_order'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nama Pelanggan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_pelanggan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Pelanggan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['email_pelanggan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Paket Foto</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_paket']) ?> (<?= $sesi_data['durasi_waktu'] ?> menit)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ruangan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_ruangan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal Sesi</span>
                        <span class="info-value"><?= formatTanggal($sesi_data['tanggal_jadwal']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jam Sesi</span>
                        <span class="info-value"><?= formatWaktu($sesi_data['jam_mulai']) ?> - <?= formatWaktu($sesi_data['jam_selesai']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Waktu Mulai</span>
                        <span class="info-value"><?= $sesi_data['waktu_mulai'] ? formatTanggal($sesi_data['waktu_mulai']) . ' ' . formatWaktu($sesi_data['waktu_mulai']) : '-' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Waktu Selesai</span>
                        <span class="info-value"><?= $sesi_data['waktu_selesai'] ? formatTanggal($sesi_data['waktu_selesai']) . ' ' . formatWaktu($sesi_data['waktu_selesai']) : '-' ?></span>
                    </div>
                    <?php if (!empty($sesi_data['keterangan_order'])): ?>
                    <div class="info-item">
                        <span class="info-label">Keterangan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['keterangan_order']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UPLOAD AREA -->
            <div class="col-lg-7 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-cloud-upload-fill text-danger me-2"></i>Upload File Hasil</h5>
                        <?php if (!empty($sesi_data['file_hasil'])): ?>
                            <span class="badge-status badge-upload">Sudah Upload</span>
                        <?php else: ?>
                            <span class="badge-status badge-selesai">Belum Upload</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($sesi_data['file_hasil'])): ?>
                        <!-- FILE SUDAH ADA -->
                        <div class="file-preview-area">
                            <!-- PREVIEW GAMBAR JIKA FILE ADALAH GAMBAR -->
                            <?php if ($is_image_preview): ?>
                            <div class="image-preview-wrapper show" id="existingImagePreview">
                                <img src="../../uploads/hasil/<?= rawurlencode($sesi_data['file_hasil']) ?>" alt="Preview Hasil Foto" id="existingPreviewImg">
                            </div>
                            <?php endif; ?>

                            <div class="file-preview-card">
                                <div class="file-preview-icon">
                                    <?php if ($is_image_preview): ?>
                                        <i class="bi bi-image"></i>
                                    <?php elseif ($is_zip_preview): ?>
                                        <i class="bi bi-file-earmark-zip"></i>
                                    <?php else: ?>
                                        <i class="bi bi-file-earmark"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="file-preview-info">
                                    <div class="file-preview-name"><?= htmlspecialchars($sesi_data['file_hasil']) ?></div>
                                    <div class="file-preview-meta">
                                        <i class="bi bi-clock-history me-1"></i>
                                        Diupload: <?= $sesi_data['tanggal_upload_hasil'] ? formatTanggal($sesi_data['tanggal_upload_hasil']) . ' ' . formatWaktu($sesi_data['tanggal_upload_hasil']) : '-' ?>
                                    </div>
                                </div>
                                <a href="../../uploads/hasil/<?= rawurlencode($sesi_data['file_hasil']) ?>" 
                                   class="btn-action btn-action-success" 
                                   download 
                                   title="Download File">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>

                        <div class="alert border-0 mb-4" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-radius: 14px;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-warning"></i>
                                <span style="font-size: 0.85rem; font-weight: 600; color: #92400e;">
                                    File sudah pernah diupload. Upload ulang akan menimpa file lama.
                                    <br><small class="text-muted">File hanya akan tersedia untuk customer setelah pelunasan terverifikasi oleh admin.</small>
                                </span>
                            </div>
                        </div>

                        <!-- FORM UPLOAD ULANG -->
                        <form id="formUpload" enctype="multipart/form-data">
                            <!-- PREVIEW GAMBAR BARU -->
                            <div class="image-preview-wrapper" id="newImagePreview">
                                <img src="" alt="Preview File Baru" id="newPreviewImg">
                                <div class="image-preview-overlay">
                                    <button type="button" onclick="clearFileSelection()">
                                        <i class="bi bi-x-lg"></i> Hapus
                                    </button>
                                </div>
                            </div>

                            <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click();" id="dropZone">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <div class="upload-zone-text">Klik atau seret file ke sini</div>
                                <div class="upload-zone-sub">Format: ZIP, JPG, JPEG, PNG, RAR, PDF (Max 100 MB)</div>
                                <input type="file" name="file_hasil" id="fileInput" class="d-none" 
                                       accept=".zip,.jpg,.jpeg,.png,.rar,.pdf" required>
                            </div>

                            <div id="fileSelected" class="d-none mb-3">
                                <div class="d-flex align-items-center gap-2 p-3" style="background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                    <i class="bi bi-file-earmark-check text-success fs-4"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.85rem; color: #166534;" id="selectedFileName">-</div>
                                        <div style="font-size: 0.75rem; color: #22c55e;" id="selectedFileSize">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-wrapper" id="progressWrapper">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Mengupload...</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--p-pink);" id="progressText">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn-action flex-fill" id="btnUpload" onclick="submitUpload()">
                                    <i class="bi bi-cloud-upload"></i> Upload Ulang
                                </button>
                                <button type="button" class="btn-action btn-action-danger" onclick="confirmHapus()">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- FILE BELUM ADA -->
                        <form id="formUpload" enctype="multipart/form-data">
                            <!-- PREVIEW GAMBAR BARU -->
                            <div class="image-preview-wrapper" id="newImagePreview">
                                <img src="" alt="Preview File Baru" id="newPreviewImg">
                                <div class="image-preview-overlay">
                                    <button type="button" onclick="clearFileSelection()">
                                        <i class="bi bi-x-lg"></i> Hapus
                                    </button>
                                </div>
                            </div>

                            <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click();" id="dropZone">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <div class="upload-zone-text">Klik atau seret file ke sini</div>
                                <div class="upload-zone-sub">Format: ZIP, JPG, JPEG, PNG, RAR, PDF (Max 100 MB)</div>
                                <input type="file" name="file_hasil" id="fileInput" class="d-none" 
                                       accept=".zip,.jpg,.jpeg,.png,.rar,.pdf" required>
                            </div>

                            <div id="fileSelected" class="d-none mb-3">
                                <div class="d-flex align-items-center gap-2 p-3" style="background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                    <i class="bi bi-file-earmark-check text-success fs-4"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.85rem; color: #166534;" id="selectedFileName">-</div>
                                        <div style="font-size: 0.75rem; color: #22c55e;" id="selectedFileSize">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-wrapper" id="progressWrapper">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Mengupload...</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--p-pink);" id="progressText">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                                </div>
                            </div>

                            <button type="button" class="btn-action w-100" id="btnUpload" onclick="submitUpload()">
                                <i class="bi bi-cloud-upload"></i> Upload Hasil Foto
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- PETUNJUK -->
                    <div class="mt-4 p-3" style="background: #f8fafc; border-radius: 14px;">
                        <h6 class="fw-bold mb-2" style="font-size: 0.8rem; color: var(--text-muted);">
                            <i class="bi bi-lightbulb-fill text-warning me-1"></i> Petunjuk Upload
                        </h6>
                        <ul class="mb-0 ps-3" style="font-size: 0.75rem; color: var(--text-muted);">
                            <li>Gunakan format <strong>ZIP</strong> untuk mengupload banyak foto sekaligus.</li>
                            <li>Pastikan file tidak melebihi <strong>100 MB</strong>.</li>
                            <li>Beri nama file yang jelas (contoh: hasil_foto_nama_customer.zip).</li>
                            <li>Pastikan kualitas foto sudah di-edit sebelum diupload.</li>
                            <li>File akan tersedia untuk customer setelah <strong>pelunasan terverifikasi</strong> oleh admin.</li>
                        </ul>
                    </div>
                </div>
            </div>
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

        // =====================================================
        // FILE INPUT HANDLER + PREVIEW GAMBAR
        // =====================================================
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const selectedFileName = document.getElementById('selectedFileName');
        const selectedFileSize = document.getElementById('selectedFileSize');
        const dropZone = document.getElementById('dropZone');
        const newImagePreview = document.getElementById('newImagePreview');
        const newPreviewImg = document.getElementById('newPreviewImg');
        const existingImagePreview = document.getElementById('existingImagePreview');

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function isImageFile(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png'].includes(ext);
        }

        function handleFileSelect(file) {
            if (!file) return;

            selectedFileName.textContent = file.name;
            selectedFileSize.textContent = formatFileSize(file.size);
            fileSelected.classList.remove('d-none');

            // PREVIEW GAMBAR JIKA FILE ADALAH GAMBAR
            if (isImageFile(file.name)) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    newPreviewImg.src = e.target.result;
                    newImagePreview.classList.add('show');
                    // Sembunyikan preview lama jika ada
                    if (existingImagePreview) {
                        existingImagePreview.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            } else {
                // Bukan gambar, sembunyikan preview gambar
                newImagePreview.classList.remove('show');
                newPreviewImg.src = '';
            }
        }

        function clearFileSelection() {
            fileInput.value = '';
            fileSelected.classList.add('d-none');
            selectedFileName.textContent = '-';
            selectedFileSize.textContent = '-';
            newImagePreview.classList.remove('show');
            newPreviewImg.src = '';
            // Tampilkan kembali preview lama jika ada
            if (existingImagePreview) {
                existingImagePreview.style.display = 'block';
            }
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                handleFileSelect(file);
            });
        }

        // Drag & Drop
        if (dropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

            dropZone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect(files[0]);
                }
            });
        }

        // =====================================================
        // AJAX UPLOAD DENGAN FETCH API
        // =====================================================
        const progressWrapper = document.getElementById('progressWrapper');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const btnUpload = document.getElementById('btnUpload');

        function submitUpload() {
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Belum Dipilih',
                    text: 'Silakan pilih file terlebih dahulu.',
                    confirmButtonColor: '#D53D66'
                });
                return;
            }

            const file = fileInput.files[0];
            const maxSize = 100 * 1024 * 1024; // 100 MB
            if (file.size > maxSize) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ukuran File Terlalu Besar',
                    text: 'Maksimal ukuran file adalah 100 MB.',
                    confirmButtonColor: '#D53D66'
                });
                return;
            }

            // Validasi ekstensi
            const allowedExt = ['zip', 'jpg', 'jpeg', 'png', 'rar', 'pdf'];
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedExt.includes(ext)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Format Tidak Didukung',
                    text: 'Format yang diizinkan: ZIP, JPG, JPEG, PNG, RAR, PDF.',
                    confirmButtonColor: '#D53D66'
                });
                return;
            }

            // Tampilkan progress bar
            progressWrapper.classList.add('show');
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';

            // Simulasi progress bar
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress >= 90) {
                    progress = 90;
                    clearInterval(progressInterval);
                }
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
            }, 200);

            // Kirim via Fetch API
            const formData = new FormData();
            formData.append('file_hasil', file);
            formData.append('ajax_upload', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressText.textContent = '100%';

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Berhasil!',
                        html: '<div style="text-align:left"><p>File hasil foto berhasil diupload dan tersimpan di sistem.</p><hr style="border-color:#f1f5f9;margin:10px 0"><p style="color:#718096;font-size:0.85rem"><i class="bi bi-info-circle-fill text-warning me-1"></i> File akan tersedia untuk customer setelah <strong style="color:#D53D66">pelunasan terverifikasi</strong> oleh admin.</p></div>',
                        confirmButtonColor: '#D53D66',
                        confirmButtonText: 'Lihat Riwayat'
                    }).then(() => {
                        window.location.href = data.redirect;
                    });
                } else {
                    btnUpload.disabled = false;
                    btnUpload.innerHTML = '<i class="bi bi-cloud-upload"></i> Upload Hasil Foto';
                    progressWrapper.classList.remove('show');
                    progressBar.style.width = '0%';
                    progressText.textContent = '0%';

                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Gagal!',
                        text: data.message,
                        confirmButtonColor: '#D53D66'
                    });
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-cloud-upload"></i> Upload Hasil Foto';
                progressWrapper.classList.remove('show');
                progressBar.style.width = '0%';
                progressText.textContent = '0%';

                Swal.fire({
                    icon: 'error',
                    title: 'Upload Gagal!',
                    text: 'Terjadi kesalahan saat mengupload file. Silakan coba lagi.',
                    confirmButtonColor: '#D53D66'
                });
                console.error('Upload error:', error);
            });
        }

        // =====================================================
        // AJAX HAPUS FILE
        // =====================================================
        function confirmHapus() {
            Swal.fire({
                title: 'Hapus File?',
                text: 'File hasil foto akan dihapus permanen dari sistem, dan status order akan diturunkan kembali. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax_hapus', '1');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'File Dihapus!',
                                text: data.message,
                                confirmButtonColor: '#D53D66',
                                confirmButtonText: 'Oke'
                            }).then(() => {
                                window.location.href = data.redirect;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Menghapus!',
                                text: data.message,
                                confirmButtonColor: '#D53D66'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus!',
                            text: 'Terjadi kesalahan saat menghapus file.',
                            confirmButtonColor: '#D53D66'
                        });
                        console.error('Delete error:', error);
                    });
                }
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar?',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../logout.php';
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../index.php';
            });
        }
    </script>
</body>
</html>