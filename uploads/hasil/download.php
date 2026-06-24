<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("HTTP/1.1 403 Forbidden");
    exit("Akses ditolak. Silakan login terlebih dahulu.");
}

// Validasi parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Parameter file tidak valid.");
}

$file_name = basename($_GET['file']);
$upload_dir = '../../uploads/hasil/';
$file_path = $upload_dir . $file_name;

// Validasi: cek path traversal
$real_upload_dir = realpath($upload_dir);
$real_file_path = realpath($file_path);

if ($real_file_path === false || strpos($real_file_path, $real_upload_dir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit("Akses file ditolak.");
}

// Cek keberadaan file
if (!file_exists($file_path) || !is_file($file_path)) {
    header("HTTP/1.1 404 Not Found");
    exit("File tidak ditemukan.");
}

// Jika role Fotografer, cek apakah file milik sesi yang diassign ke fotografer tersebut
if ($_SESSION['role'] == 'Fotografer') {
    $id_fotografer = $_SESSION['id_user'];
    $q_check = sqlsrv_query($conn, "
        SELECT COUNT(*) AS total FROM Sesi_Foto 
        WHERE File_Hasil = ? AND ID_Karyawan = ? AND Status = 1
    ", array($file_name, $id_fotografer));
    $d_check = sqlsrv_fetch_array($q_check, SQLSRV_FETCH_ASSOC);
    if ($d_check['total'] == 0) {
        header("HTTP/1.1 403 Forbidden");
        exit("Anda tidak memiliki akses ke file ini.");
    }
}

// Jika role Customer, cek apakah file milik order customer tersebut
if ($_SESSION['role'] == 'Customer') {
    $id_pelanggan = $_SESSION['id_user'];
    $q_check = sqlsrv_query($conn, "
        SELECT COUNT(*) AS total 
        FROM Sesi_Foto S
        JOIN [Order] O ON S.ID_Order = O.ID_Order
        WHERE S.File_Hasil = ? AND O.ID_Pelanggan = ? AND S.Status = 1
    ", array($file_name, $id_pelanggan));
    $d_check = sqlsrv_fetch_array($q_check, SQLSRV_FETCH_ASSOC);
    if ($d_check['total'] == 0) {
        header("HTTP/1.1 403 Forbidden");
        exit("Anda tidak memiliki akses ke file ini.");
    }
}

// Set header untuk download
$file_size = filesize($file_path);
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Tentukan MIME type
$mime_types = [
    'zip' => 'application/zip',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'rar' => 'application/x-rar-compressed',
    'pdf' => 'application/pdf'
];

$mime_type = $mime_types[$file_ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($file_path);
exit;
?>