<?php
session_start();
include '../../../koneksi.php';

header('Content-Type: application/json');

// Proteksi
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id_pelanggan = $_SESSION['id_user'] ?? $_SESSION['id_pelanggan'] ?? null;
if (!$id_pelanggan) {
    echo json_encode(['success' => false, 'message' => 'Session invalid']);
    exit();
}

// Validasi input
if (!isset($_POST['id_order']) || !isset($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

$id_order = intval($_POST['id_order']);
$rating = intval($_POST['rating']);
$review = isset($_POST['review']) ? trim($_POST['review']) : '';

// Validasi rating 1-5
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating harus 1-5']);
    exit();
}

// Cek order milik pelanggan ini dan status = Lunas (3)
$cek = sqlsrv_query($conn, "
    SELECT Status_Order FROM [Order] 
    WHERE ID_Order = ? AND ID_Pelanggan = ? AND Is_Deleted = 0
", [$id_order, $id_pelanggan]);

if (!$cek || !sqlsrv_has_rows($cek)) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']);
    exit();
}

$row = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
if ($row['Status_Order'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Hanya order yang sudah lunas yang bisa di-rating']);
    exit();
}

// Update rating & review
$update = sqlsrv_query($conn, "
    UPDATE [Order] 
    SET Rating = ?, Review = ?, Modified_By = ?, Modified_Date = GETDATE()
    WHERE ID_Order = ? AND ID_Pelanggan = ?
", [$rating, $review, $id_pelanggan, $id_order, $id_pelanggan]);

if ($update) {
    echo json_encode(['success' => true, 'message' => 'Rating berhasil disimpan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan rating']);
}
?>