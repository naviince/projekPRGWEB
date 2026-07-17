<?php
session_start();
include '../../../koneksi.php';

// --- PROTEKSI: HANYA CUSTOMER LOGIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];
$id_order = (int)($_GET['id_order'] ?? 0);

if ($id_order <= 0) {
    http_response_code(400);
    die('ID Order tidak valid.');
}

// Validasi kepemilikan + ambil daftar file lewat SP yang sama dipakai galeri
// (sp_ReadHasilFotoByOrder sudah cek ID_Pelanggan di dalamnya)
$q = sqlsrv_query($conn, "{CALL sp_ReadHasilFotoByOrder(?, ?)}", array($id_order, $id_customer));
if ($q === false) {
    http_response_code(500);
    die('Gagal mengambil data hasil foto.');
}

$files = [];
while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    $files[] = $row['Nama_File'];
}

if (empty($files)) {
    http_response_code(404);
    die('Order tidak ditemukan, bukan milik Anda, atau belum ada hasil foto.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Fitur download ZIP tidak tersedia di server ini (ekstensi PHP Zip belum aktif). Silakan hubungi admin, atau download foto satu per satu lewat galeri.');
}

$upload_dir = '../../../uploads/hasil/';
$zip_name = 'HasilFoto_Order' . $id_order . '_' . date('Ymd_His') . '.zip';
$zip_temp_path = sys_get_temp_dir() . '/' . uniqid('hasilfoto_') . '.zip';

$zip = new ZipArchive();
if ($zip->open($zip_temp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Gagal membuat file ZIP.');
}

$jumlah_ditambahkan = 0;
foreach ($files as $nama_file) {
    $path_fisik = $upload_dir . $nama_file;
    if (file_exists($path_fisik)) {
        // Nama di dalam ZIP dibuat berurutan biar rapi buat customer,
        // bukan nama file internal server yang panjang/acak
        $ext = pathinfo($nama_file, PATHINFO_EXTENSION);
        $nama_di_zip = 'Foto_' . str_pad($jumlah_ditambahkan + 1, 3, '0', STR_PAD_LEFT) . '.' . $ext;
        $zip->addFile(realpath($path_fisik), $nama_di_zip);
        $jumlah_ditambahkan++;
    }
}
$zip->close();

if ($jumlah_ditambahkan === 0) {
    unlink($zip_temp_path);
    http_response_code(404);
    die('File fisik hasil foto tidak ditemukan di server. Silakan hubungi admin.');
}

// Stream ZIP ke browser lalu hapus file sementara
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_name . '"');
header('Content-Length: ' . filesize($zip_temp_path));
header('Cache-Control: no-cache, must-revalidate');
readfile($zip_temp_path);
unlink($zip_temp_path);
exit();