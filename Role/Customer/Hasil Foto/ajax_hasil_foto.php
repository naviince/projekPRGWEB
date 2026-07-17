<?php
session_start();
include '../../../koneksi.php';

header('Content-Type: application/json');

// --- PROTEKSI: HANYA CUSTOMER LOGIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid, silakan login ulang.']);
    exit();
}

$id_customer = $_SESSION['id_user'];
$id_order = (int)($_GET['id_order'] ?? 0);

if ($id_order <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Order tidak valid.']);
    exit();
}

// sp_ReadHasilFotoByOrder SUDAH memvalidasi kepemilikan (ID_Pelanggan) di
// dalam SP itu sendiri -- kalau order bukan milik customer ini, result set
// akan kosong (bukan error), jadi tidak membocorkan info order orang lain.
$q = sqlsrv_query($conn, "{CALL sp_ReadHasilFotoByOrder(?, ?)}", array($id_order, $id_customer));

if ($q === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data hasil foto.']);
    exit();
}

$files = [];
while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    $files[] = [
        'nama'  => $row['Nama_File'],
        'tipe'  => $row['Tipe_File'], // 'image' | 'archive'
        'ukuran' => (int)$row['Ukuran_Bytes'],
        'url'   => '../../../uploads/hasil/' . rawurlencode($row['Nama_File']),
    ];
}

if (empty($files)) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan, bukan milik Anda, atau belum ada hasil foto.']);
    exit();
}

echo json_encode(['success' => true, 'files' => $files]);
exit();