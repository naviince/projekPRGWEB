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
$max_total_size = 300 * 1024 * 1024; // 300 MB TOTAL per sesi (bukan per-file)
$allowed_extensions_image = ['jpg', 'jpeg', 'png'];
$allowed_extensions_archive = ['zip', 'rar'];
$allowed_extensions = array_merge($allowed_extensions_image, $allowed_extensions_archive);
$allowed_mime_types = [
    'image/jpeg', 'image/png',
    'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed'
];

// =====================================================
// STATUS_ORDER (LUNAS) TERKAIT SESI -- DIPAKAI DI SEMUA HANDLER
// =====================================================
function getStatusOrderSesi($conn, $id_sesi, $id_fotografer) {
    $q = sqlsrv_query($conn, "
        SELECT O.Status_Order, S.ID_Order
        FROM Sesi_Foto S
        JOIN [Order] O ON S.ID_Order = O.ID_Order
        WHERE S.ID_Sesi_Foto = ? AND S.ID_Karyawan = ? AND S.Status = 1
    ", array($id_sesi, $id_fotografer));
    if (!$q) return null;
    return sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
}

// Total ukuran file aktif yang sudah ada di sesi ini
function getTotalUkuranSesi($conn, $id_sesi) {
    $q = sqlsrv_query($conn, "
        SELECT ISNULL(SUM(Ukuran_Bytes), 0) AS total
        FROM Hasil_Foto WHERE ID_Sesi_Foto = ? AND Status = 1
    ", array($id_sesi));
    $d = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
    return (int)($d['total'] ?? 0);
}

// =====================================================
// HANDLE AJAX UPLOAD (MULTI-FILE, FETCH API)
// =====================================================
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && isset($_POST['ajax_upload'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $response['message'] = 'ID Sesi tidak valid!';
        echo json_encode($response); exit();
    }
    $id_sesi = intval($_GET['id']);

    $sesi_info = getStatusOrderSesi($conn, $id_sesi, $id_fotografer);
    if (!$sesi_info) {
        $response['message'] = 'Sesi tidak ditemukan atau Anda tidak berhak mengaksesnya.';
        echo json_encode($response); exit();
    }

    // =====================================================
    // SYARAT WAJIB: ORDER HARUS LUNAS (Status_Order = 3)
    // Order yang baru DP terverifikasi (1) atau sesi selesai tapi
    // masih Menunggu Pelunasan (2) TIDAK boleh upload hasil. Ini
    // mencegah hasil foto "bocor" ke customer yang belum bayar lunas.
    // =====================================================
    if ((int)$sesi_info['Status_Order'] !== 3) {
        $pesan_status = ((int)$sesi_info['Status_Order'] === 2)
            ? 'Customer belum melunasi pembayaran (masih Menunggu Pelunasan). Upload hasil foto hanya bisa dilakukan setelah status order LUNAS.'
            : 'Order ini belum berstatus Lunas. Upload hasil foto tidak diperbolehkan.';
        $response['message'] = $pesan_status;
        echo json_encode($response); exit();
    }

    if (empty($_FILES['file_hasil']) || empty($_FILES['file_hasil']['name'][0])) {
        $response['message'] = 'Silakan pilih minimal 1 file untuk diupload!';
        echo json_encode($response); exit();
    }

    $files = $_FILES['file_hasil'];
    $jumlah_file = count($files['name']);

    // Hitung total ukuran file BARU yang mau diupload
    $total_ukuran_baru = 0;
    for ($i = 0; $i < $jumlah_file; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $total_ukuran_baru += $files['size'][$i];
        }
    }

    $total_ukuran_existing = getTotalUkuranSesi($conn, $id_sesi);
    $total_setelah_upload = $total_ukuran_existing + $total_ukuran_baru;

    // =====================================================
    // VALIDASI TOTAL UKURAN (bukan per-file, tapi akumulasi semua
    // file dalam 1 sesi -- konsisten sama permintaan bisnis: fotografer
    // bebas upload banyak foto asal totalnya gak lewat kuota per sesi)
    // =====================================================
    if ($total_setelah_upload > $max_total_size) {
        $sisa_mb = round(($max_total_size - $total_ukuran_existing) / 1024 / 1024, 1);
        $sisa_mb = max(0, $sisa_mb);
        $response['message'] = "Total ukuran file untuk sesi ini melebihi batas 300 MB. Sisa kuota Anda: {$sisa_mb} MB. Kurangi jumlah/ukuran file, atau kompres jadi ZIP.";
        echo json_encode($response); exit();
    }

    // Validasi tiap file dulu (ekstensi + MIME) SEBELUM ada yang dipindah,
    // biar gak ada file "nyangkut" setengah kalau salah satu file invalid.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $jumlah_file; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $response['message'] = "File '{$files['name'][$i]}' gagal diupload (error code: {$files['error'][$i]}).";
            echo json_encode($response); finfo_close($finfo); exit();
        }
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            $response['message'] = "Format file '{$files['name'][$i]}' tidak didukung. Format yang diizinkan: JPG, JPEG, PNG, ZIP, RAR.";
            echo json_encode($response); finfo_close($finfo); exit();
        }
        $mime = finfo_file($finfo, $files['tmp_name'][$i]);
        $mime_valid = false;
        foreach ($allowed_mime_types as $allowed) {
            if (strpos($mime, $allowed) !== false || strpos($allowed, $mime) !== false) { $mime_valid = true; break; }
        }
        if (!$mime_valid) {
            $response['message'] = "Tipe file '{$files['name'][$i]}' tidak valid.";
            echo json_encode($response); finfo_close($finfo); exit();
        }
    }
    finfo_close($finfo);

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $response['message'] = 'Gagal membuat folder upload. Periksa permission folder.';
            echo json_encode($response); exit();
        }
    }

    $id_order = $sesi_info['ID_Order'];
    $q_max_urutan = sqlsrv_query($conn, "SELECT ISNULL(MAX(Urutan), 0) AS m FROM Hasil_Foto WHERE ID_Sesi_Foto = ? AND Status = 1", array($id_sesi));
    $d_max_urutan = sqlsrv_fetch_array($q_max_urutan, SQLSRV_FETCH_ASSOC);
    $urutan_next = (int)($d_max_urutan['m'] ?? 0) + 1;

    sqlsrv_begin_transaction($conn);
    $moved_paths = [];
    try {
        $berhasil_count = 0;
        for ($i = 0; $i < $jumlah_file; $i++) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $tipe_file = in_array($ext, $allowed_extensions_archive) ? 'archive' : 'image';
            $new_file_name = "hasil_order{$id_order}_" . time() . '_' . uniqid() . ".{$ext}";
            $target_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                throw new Exception("Gagal memindahkan file '{$files['name'][$i]}' ke server.");
            }
            $moved_paths[] = $target_path;

            $q_insert = sqlsrv_query($conn, "
                INSERT INTO Hasil_Foto (ID_Sesi_Foto, Nama_File, Tipe_File, Ukuran_Bytes, Urutan, Created_By, Created_Date)
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())
            ", array($id_sesi, $new_file_name, $tipe_file, $files['size'][$i], $urutan_next, $username_fotografer));

            if (!$q_insert) {
                $errors = sqlsrv_errors();
                throw new Exception("Gagal simpan data file '{$files['name'][$i]}': " . ($errors ? $errors[0]['message'] : 'Unknown error'));
            }
            $urutan_next++;
            $berhasil_count++;
        }

        // Tandai Sesi_Foto sudah pernah diupload (dipakai badge "Sudah
        // Upload" di halaman lain -- Tanggal_Upload_Hasil = kapan upload
        // TERAKHIR terjadi untuk sesi ini)
        sqlsrv_query($conn, "UPDATE Sesi_Foto SET Tanggal_Upload_Hasil = GETDATE(), Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = ?", array($username_fotografer, $id_sesi));

        sqlsrv_commit($conn);
        $response['success'] = true;
        $response['message'] = "{$berhasil_count} file berhasil diupload!";
        $response['redirect'] = '../../Sesi/RiwayatUpload/index.php?uploaded=1';
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        foreach ($moved_paths as $p) { if (file_exists($p)) unlink($p); }
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// =====================================================
// HANDLE HAPUS 1 FILE HASIL (AJAX)
// =====================================================
$is_ajax_delete = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
                  isset($_POST['ajax_hapus']);

if ($is_ajax_delete) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $id_sesi = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $id_hasil_foto = isset($_POST['id_hasil_foto']) ? intval($_POST['id_hasil_foto']) : 0;

    if ($id_sesi <= 0 || $id_hasil_foto <= 0) {
        $response['message'] = 'Data tidak valid.';
        echo json_encode($response); exit();
    }

    $sesi_info = getStatusOrderSesi($conn, $id_sesi, $id_fotografer);
    if (!$sesi_info) {
        $response['message'] = 'Sesi tidak ditemukan atau Anda tidak berhak mengaksesnya.';
        echo json_encode($response); exit();
    }

    $q_file = sqlsrv_query($conn, "SELECT Nama_File FROM Hasil_Foto WHERE ID_Hasil_Foto = ? AND ID_Sesi_Foto = ? AND Status = 1", array($id_hasil_foto, $id_sesi));
    $d_file = $q_file ? sqlsrv_fetch_array($q_file, SQLSRV_FETCH_ASSOC) : null;
    if (!$d_file) {
        $response['message'] = 'File tidak ditemukan.';
        echo json_encode($response); exit();
    }

    sqlsrv_begin_transaction($conn);
    try {
        $file_path = $upload_dir . $d_file['Nama_File'];

        $q_del = sqlsrv_query($conn, "UPDATE Hasil_Foto SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Hasil_Foto = ?", array($username_fotografer, $id_hasil_foto));
        if (!$q_del) {
            $errors = sqlsrv_errors();
            throw new Exception('Gagal menghapus dari database: ' . ($errors ? $errors[0]['message'] : 'Unknown error'));
        }

        if (file_exists($file_path)) unlink($file_path);

        sqlsrv_commit($conn);
        $response['success'] = true;
        $response['message'] = 'File berhasil dihapus!';
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// =====================================================
// AMBIL DATA SESI FOTO (untuk tampilan halaman)
// =====================================================
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

// Ambil Status_Order buat tau apakah boleh upload (harus Lunas)
$sesi_info_order = getStatusOrderSesi($conn, $id_sesi, $id_fotografer);
$status_order = (int)($sesi_info_order['Status_Order'] ?? 0);
$boleh_upload = ($status_order === 3);

// =====================================================
// AMBIL SEMUA FILE HASIL YANG SUDAH ADA UNTUK SESI INI
// =====================================================
$daftar_file = [];
$total_ukuran_terpakai = 0;
$q_files = sqlsrv_query($conn, "
    SELECT ID_Hasil_Foto, Nama_File, Tipe_File, Ukuran_Bytes, Created_Date
    FROM Hasil_Foto
    WHERE ID_Sesi_Foto = ? AND Status = 1
    ORDER BY Urutan ASC, Created_Date ASC
", array($id_sesi));
if ($q_files) {
    while ($f = sqlsrv_fetch_array($q_files, SQLSRV_FETCH_ASSOC)) {
        $daftar_file[] = $f;
        $total_ukuran_terpakai += (int)$f['Ukuran_Bytes'];
    }
}
$jumlah_foto = count(array_filter($daftar_file, fn($f) => $f['Tipe_File'] === 'image'));
$jumlah_arsip = count(array_filter($daftar_file, fn($f) => $f['Tipe_File'] === 'archive'));

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

$foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_fotografer))
    ? "../../assets/img/karyawan/" . $foto_fotografer
    : $default_svg_avatar;

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) $date = new DateTime($date);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $date->format('d') . ' ' . $bulan[intval($date->format('m')) - 1] . ' ' . $date->format('Y');
}
function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) $time = new DateTime($time);
    return $time->format('H:i');
}
function formatUkuran($bytes) {
    if ($bytes <= 0) return '0 KB';
    $units = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
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
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); padding: 25px; position: relative; overflow: hidden; }
        .card-3d::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--p-pink), var(--accent-pink)); opacity: 0; transition: opacity 0.3s ease; }
        .card-3d:hover { transform: translateY(-4px); box-shadow: 0 22px 45px rgba(213, 61, 102, 0.1); border-color: var(--p-pink); }
        .card-3d:hover::before { opacity: 1; }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .content-title { font-weight: 700; font-size: 1.1rem; color: var(--text-dark); }
        .info-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
        .info-value { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); text-align: right; }
        .upload-zone { border: 2px dashed var(--light-pink); border-radius: 20px; padding: 40px; text-align: center; background: linear-gradient(135deg, #ffffff, var(--s-pink)); transition: var(--transition-3d); cursor: pointer; }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--p-pink); background: linear-gradient(135deg, #ffffff, #FFE4E9); transform: scale(1.01); }
        .upload-zone i { font-size: 3rem; color: var(--p-pink); margin-bottom: 15px; }
        .upload-zone-text { font-weight: 700; color: var(--text-dark); margin-bottom: 5px; }
        .upload-zone-sub { font-size: 0.8rem; color: var(--text-muted); }
        .btn-action { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 12px; padding: 12px 24px; font-weight: 800; font-size: 0.9rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg, #059669, #047857); }
        .btn-action-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-muted); }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .badge-status { padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-upload { background: #dbeafe; color: #2563eb; }
        .badge-locked { background: #fef3c7; color: #b45309; }
        .progress-wrapper { display: none; margin-top: 20px; }
        .progress-wrapper.show { display: block; }
        .progress { height: 10px; border-radius: 10px; background: #f1f5f9; overflow: hidden; }
        .progress-bar { background: linear-gradient(90deg, var(--p-pink), var(--accent-pink)); border-radius: 10px; transition: width 0.3s ease; }
        .error-alert { border-radius: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 16px 20px; margin-bottom: 20px; }
        .quota-bar-wrapper { margin-bottom: 20px; }
        .quota-bar { height: 8px; border-radius: 8px; background: #f1f5f9; overflow: hidden; }
        .quota-bar-fill { height: 100%; background: linear-gradient(90deg, var(--p-pink), var(--accent-pink)); border-radius: 8px; transition: width 0.4s ease; }
        .quota-bar-fill.warning { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .quota-bar-fill.danger { background: linear-gradient(90deg, #dc2626, #b91c1c); }

        /* GALERI FOTO */
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .gallery-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1/1; cursor: pointer; border: 1px solid #f1f5f9; background: #f8fafc; }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .gallery-item:hover img { transform: scale(1.08); }
        .gallery-item-archive { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; color: var(--p-pink); background: var(--s-pink); }
        .gallery-item-archive i { font-size: 1.8rem; }
        .gallery-item-archive span { font-size: 0.6rem; font-weight: 700; text-align: center; padding: 0 4px; word-break: break-all; }
        .gallery-item-del { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(220,38,38,0.9); color: #fff; border: none; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; opacity: 0; transition: opacity 0.2s; }
        .gallery-item:hover .gallery-item-del { opacity: 1; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Fotografer</span>
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

        <?php if (!$boleh_upload): ?>
        <div class="error-alert mb-4 animate-fade-in">
            <i class="bi bi-lock-fill me-2"></i>
            <strong>Upload terkunci.</strong>
            <?php if ($status_order === 2): ?>
                Customer belum melunasi pembayaran (masih <strong>Menunggu Pelunasan</strong>). Upload hasil foto baru bisa dilakukan setelah status order <strong>LUNAS</strong> dan diverifikasi Admin.
            <?php else: ?>
                Order ini belum berstatus Lunas.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- INFO SESI -->
            <div class="col-lg-5 animate-fade-in">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-info-circle-fill text-danger me-2"></i>Detail Sesi</h5>
                        <span class="badge-status <?= $boleh_upload ? 'badge-selesai' : 'badge-locked' ?>"><?= $boleh_upload ? 'Lunas' : 'Terkunci' ?></span>
                    </div>
                    <div class="info-item"><span class="info-label">ID Sesi</span><span class="info-value">#<?= $sesi_data['id_sesi_foto'] ?></span></div>
                    <div class="info-item"><span class="info-label">ID Order</span><span class="info-value">#<?= $sesi_data['id_order'] ?></span></div>
                    <div class="info-item"><span class="info-label">Nama Pelanggan</span><span class="info-value"><?= htmlspecialchars($sesi_data['nama_pelanggan']) ?></span></div>
                    <div class="info-item"><span class="info-label">Email Pelanggan</span><span class="info-value"><?= htmlspecialchars($sesi_data['email_pelanggan']) ?></span></div>
                    <div class="info-item"><span class="info-label">Paket Foto</span><span class="info-value"><?= htmlspecialchars($sesi_data['nama_paket']) ?> (<?= $sesi_data['durasi_waktu'] ?> menit)</span></div>
                    <div class="info-item"><span class="info-label">Ruangan</span><span class="info-value"><?= htmlspecialchars($sesi_data['nama_ruangan']) ?></span></div>
                    <div class="info-item"><span class="info-label">Tanggal Sesi</span><span class="info-value"><?= formatTanggal($sesi_data['tanggal_jadwal']) ?></span></div>
                    <div class="info-item"><span class="info-label">Jam Sesi</span><span class="info-value"><?= formatWaktu($sesi_data['jam_mulai']) ?> - <?= formatWaktu($sesi_data['jam_selesai']) ?></span></div>
                    <?php if (!empty($sesi_data['keterangan_order'])): ?>
                    <div class="info-item"><span class="info-label">Keterangan</span><span class="info-value"><?= htmlspecialchars($sesi_data['keterangan_order']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UPLOAD AREA -->
            <div class="col-lg-7 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-cloud-upload-fill text-danger me-2"></i>Hasil Foto</h5>
                        <span class="badge-status <?= count($daftar_file) > 0 ? 'badge-upload' : 'badge-selesai' ?>">
                            <?= $jumlah_foto ?> Foto<?= $jumlah_arsip > 0 ? ' + ' . $jumlah_arsip . ' Arsip' : '' ?>
                        </span>
                    </div>

                    <!-- KUOTA TOTAL UKURAN -->
                    <div class="quota-bar-wrapper">
                        <?php
                            $persen_pakai = min(100, round(($total_ukuran_terpakai / $max_total_size) * 100, 1));
                            $kelas_bar = $persen_pakai >= 90 ? 'danger' : ($persen_pakai >= 70 ? 'warning' : '');
                        ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">Kuota Terpakai</span>
                            <span style="font-size:0.75rem;font-weight:700;color:var(--p-pink);"><?= formatUkuran($total_ukuran_terpakai) ?> / <?= formatUkuran($max_total_size) ?></span>
                        </div>
                        <div class="quota-bar"><div class="quota-bar-fill <?= $kelas_bar ?>" style="width: <?= $persen_pakai ?>%"></div></div>
                    </div>

                    <!-- GALERI FILE YANG SUDAH DIUPLOAD -->
                    <?php if (!empty($daftar_file)): ?>
                    <div class="gallery-grid" id="galleryGrid">
                        <?php foreach ($daftar_file as $f): ?>
                            <?php if ($f['Tipe_File'] === 'image'): ?>
                            <div class="gallery-item" onclick="bukaPopupFoto('../../uploads/hasil/<?= rawurlencode($f['Nama_File']) ?>', '<?= htmlspecialchars(addslashes($f['Nama_File'])) ?>')">
                                <img src="../../uploads/hasil/<?= rawurlencode($f['Nama_File']) ?>" alt="Hasil Foto" loading="lazy">
                                <?php if ($boleh_upload): ?>
                                <button type="button" class="gallery-item-del" onclick="event.stopPropagation(); hapusFile(<?= $f['ID_Hasil_Foto'] ?>, '<?= htmlspecialchars(addslashes($f['Nama_File'])) ?>')" title="Hapus"><i class="bi bi-x-lg"></i></button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="gallery-item gallery-item-archive" onclick="window.open('../../uploads/hasil/<?= rawurlencode($f['Nama_File']) ?>', '_blank')">
                                <i class="bi bi-file-earmark-zip-fill"></i>
                                <span><?= htmlspecialchars($f['Nama_File']) ?></span>
                                <?php if ($boleh_upload): ?>
                                <button type="button" class="gallery-item-del" onclick="event.stopPropagation(); hapusFile(<?= $f['ID_Hasil_Foto'] ?>, '<?= htmlspecialchars(addslashes($f['Nama_File'])) ?>')" title="Hapus"><i class="bi bi-x-lg"></i></button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($boleh_upload): ?>
                    <!-- FORM UPLOAD MULTI-FILE -->
                    <form id="formUpload" enctype="multipart/form-data">
                        <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click();" id="dropZone">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <div class="upload-zone-text">Klik atau seret banyak file sekaligus ke sini</div>
                            <div class="upload-zone-sub">Format: JPG, JPEG, PNG (foto), ZIP, RAR (arsip borongan) &bull; Total kuota 300 MB / sesi</div>
                            <input type="file" name="file_hasil[]" id="fileInput" class="d-none" accept=".jpg,.jpeg,.png,.zip,.rar" multiple>
                        </div>

                        <div id="fileSelectedList" class="mb-3"></div>

                        <div class="progress-wrapper" id="progressWrapper">
                            <div class="d-flex justify-content-between mb-1">
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Mengupload...</span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--p-pink);" id="progressText">0%</span>
                            </div>
                            <div class="progress"><div class="progress-bar" id="progressBar" style="width: 0%"></div></div>
                        </div>

                        <button type="button" class="btn-action w-100" id="btnUpload" onclick="submitUpload()">
                            <i class="bi bi-cloud-upload"></i> Upload File Terpilih
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- PETUNJUK -->
                    <div class="mt-4 p-3" style="background: #f8fafc; border-radius: 14px;">
                        <h6 class="fw-bold mb-2" style="font-size: 0.8rem; color: var(--text-muted);">
                            <i class="bi bi-lightbulb-fill text-warning me-1"></i> Petunjuk Upload
                        </h6>
                        <ul class="mb-0 ps-3" style="font-size: 0.75rem; color: var(--text-muted);">
                            <li>Bisa pilih <strong>banyak foto sekaligus</strong>, atau upload <strong>1 file ZIP/RAR</strong> untuk borongan.</li>
                            <li>Total ukuran semua file dalam 1 sesi maksimal <strong>300 MB</strong> (bukan per-file).</li>
                            <li>Upload hasil foto hanya bisa dilakukan setelah order berstatus <strong>Lunas</strong>.</li>
                            <li>Customer akan melihat foto dalam bentuk galeri, bisa klik untuk memperbesar.</li>
                            <li>Pastikan kualitas foto sudah di-edit sebelum diupload.</li>
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
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });

        // =====================================================
        // POPUP LIHAT FOTO (pakai SweetAlert2 imageUrl -- lightbox instan)
        // =====================================================
        function bukaPopupFoto(src, nama) {
            Swal.fire({
                imageUrl: src,
                imageAlt: nama,
                width: 'min(90vw, 700px)',
                showCloseButton: true,
                showConfirmButton: false,
                title: nama,
                background: '#fff'
            });
        }

        // =====================================================
        // MULTI-FILE INPUT HANDLER
        // =====================================================
        const MAX_TOTAL_SIZE = <?= $max_total_size ?>;
        const TOTAL_TERPAKAI = <?= $total_ukuran_terpakai ?>;
        const fileInput = document.getElementById('fileInput');
        const fileSelectedList = document.getElementById('fileSelectedList');
        const dropZone = document.getElementById('dropZone');
        let selectedFiles = [];

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function renderSelectedFiles() {
            if (!fileSelectedList) return;
            fileSelectedList.innerHTML = '';
            if (selectedFiles.length === 0) return;

            let totalBaru = selectedFiles.reduce((sum, f) => sum + f.size, 0);
            let sisaKuota = MAX_TOTAL_SIZE - TOTAL_TERPAKAI;
            let overQuota = totalBaru > sisaKuota;

            const summary = document.createElement('div');
            summary.className = 'p-2 mb-2';
            summary.style.cssText = 'font-size:0.8rem;font-weight:700;border-radius:10px;padding:10px 14px;' +
                (overQuota ? 'background:#fef2f2;color:#dc2626;' : 'background:#f0fdf4;color:#166534;');
            summary.innerHTML = selectedFiles.length + ' file dipilih (' + formatFileSize(totalBaru) + ')' +
                (overQuota ? ' — melebihi sisa kuota ' + formatFileSize(Math.max(0, sisaKuota)) + '!' : '');
            fileSelectedList.appendChild(summary);

            selectedFiles.forEach((f, idx) => {
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center justify-content-between p-2 mb-1';
                row.style.cssText = 'background:#f8fafc;border-radius:10px;font-size:0.8rem;';
                row.innerHTML = '<span><i class="bi bi-file-earmark-check text-success me-1"></i>' + f.name + ' <span class="text-muted">(' + formatFileSize(f.size) + ')</span></span>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeSelectedFile(' + idx + ')"><i class="bi bi-x-lg"></i></button>';
                fileSelectedList.appendChild(row);
            });
        }

        function syncFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        function removeSelectedFile(idx) {
            selectedFiles.splice(idx, 1);
            syncFileInput();
            renderSelectedFiles();
        }

        function handleFilesSelect(fileList) {
            for (const f of fileList) selectedFiles.push(f);
            syncFileInput();
            renderSelectedFiles();
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) { handleFilesSelect(e.target.files); });
        }

        if (dropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
            });
            ['dragenter', 'dragover'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover')));
            ['dragleave', 'drop'].forEach(eventName => dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover')));
            dropZone.addEventListener('drop', function(e) { handleFilesSelect(e.dataTransfer.files); });
        }

        // =====================================================
        // AJAX UPLOAD MULTI-FILE
        // =====================================================
        const progressWrapper = document.getElementById('progressWrapper');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const btnUpload = document.getElementById('btnUpload');

        function submitUpload() {
            if (selectedFiles.length === 0) {
                Swal.fire({ icon: 'warning', title: 'File Belum Dipilih', text: 'Silakan pilih minimal 1 file.', confirmButtonColor: '#D53D66' });
                return;
            }

            const totalBaru = selectedFiles.reduce((sum, f) => sum + f.size, 0);
            const sisaKuota = MAX_TOTAL_SIZE - TOTAL_TERPAKAI;
            if (totalBaru > sisaKuota) {
                Swal.fire({ icon: 'error', title: 'Kuota Terlampaui', text: 'Total ukuran file yang dipilih melebihi sisa kuota ' + formatFileSize(Math.max(0, sisaKuota)) + '.', confirmButtonColor: '#D53D66' });
                return;
            }

            const allowedExt = ['jpg', 'jpeg', 'png', 'zip', 'rar'];
            for (const f of selectedFiles) {
                const ext = f.name.split('.').pop().toLowerCase();
                if (!allowedExt.includes(ext)) {
                    Swal.fire({ icon: 'error', title: 'Format Tidak Didukung', text: "File '" + f.name + "' formatnya tidak didukung.", confirmButtonColor: '#D53D66' });
                    return;
                }
            }

            progressWrapper.classList.add('show');
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';

            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress >= 90) { progress = 90; clearInterval(progressInterval); }
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
            }, 200);

            const formData = new FormData();
            selectedFiles.forEach(f => formData.append('file_hasil[]', f));
            formData.append('ajax_upload', '1');

            fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    progressText.textContent = '100%';

                    if (data.success) {
                        Swal.fire({
                            icon: 'success', title: 'Upload Berhasil!', text: data.message,
                            confirmButtonColor: '#D53D66', confirmButtonText: 'Lihat Riwayat'
                        }).then(() => { window.location.href = data.redirect; });
                    } else {
                        btnUpload.disabled = false;
                        btnUpload.innerHTML = '<i class="bi bi-cloud-upload"></i> Upload File Terpilih';
                        progressWrapper.classList.remove('show');
                        progressBar.style.width = '0%'; progressText.textContent = '0%';
                        Swal.fire({ icon: 'error', title: 'Upload Gagal!', text: data.message, confirmButtonColor: '#D53D66' });
                    }
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    btnUpload.disabled = false;
                    btnUpload.innerHTML = '<i class="bi bi-cloud-upload"></i> Upload File Terpilih';
                    progressWrapper.classList.remove('show');
                    progressBar.style.width = '0%'; progressText.textContent = '0%';
                    Swal.fire({ icon: 'error', title: 'Upload Gagal!', text: 'Terjadi kesalahan saat mengupload file.', confirmButtonColor: '#D53D66' });
                    console.error('Upload error:', error);
                });
        }

        // =====================================================
        // HAPUS 1 FILE
        // =====================================================
        function hapusFile(idHasilFoto, namaFile) {
            Swal.fire({
                title: 'Hapus File?',
                text: "'" + namaFile + "' akan dihapus permanen dari sistem. Lanjutkan?",
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#dc2626', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('ajax_hapus', '1');
                    formData.append('id_hasil_foto', idHasilFoto);
                    fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'File Dihapus!', confirmButtonColor: '#D53D66' }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal Menghapus!', text: data.message, confirmButtonColor: '#D53D66' });
                            }
                        })
                        .catch(() => Swal.fire({ icon: 'error', title: 'Gagal Menghapus!', text: 'Terjadi kesalahan.', confirmButtonColor: '#D53D66' }));
                }
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' })
                .then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' })
                .then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }
    </script>
</body>
</html>