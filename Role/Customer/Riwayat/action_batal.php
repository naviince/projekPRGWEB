<?php
session_start();
include '../../../koneksi.php';

header('Content-Type: application/json');

// Proteksi - SAMA PERSIS DENGAN INDEX.PHP
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id_customer = $_SESSION['id_user'];

if (!isset($_GET['id_order'])) {
    echo json_encode(['success' => false, 'message' => 'ID Order tidak ada']);
    exit();
}

$id_order = intval($_GET['id_order']);

// Cek order milik customer dan status = Menunggu DP (0)
$cek = sqlsrv_query($conn, "
    SELECT Status_Order, ID_Jadwal FROM [Order] 
    WHERE ID_Order = ? AND ID_Pelanggan = ? AND Is_Deleted = 0
", array($id_order, $id_customer));

if (!$cek || !sqlsrv_has_rows($cek)) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan']);
    exit();
}

$row = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
if ($row['Status_Order'] != 0) {
    echo json_encode(['success' => false, 'message' => 'Hanya order yang menunggu DP yang bisa dibatalkan']);
    exit();
}

$id_jadwal = $row['ID_Jadwal'];

// Update order status = Dibatalkan (4)
$update_order = sqlsrv_query($conn, "
    UPDATE [Order] 
    SET Status_Order = 4, Modified_By = ?, Modified_Date = GETDATE()
    WHERE ID_Order = ? AND ID_Pelanggan = ?
", array($id_customer, $id_order, $id_customer));

if (!$update_order) {
    echo json_encode(['success' => false, 'message' => 'Gagal membatalkan order']);
    exit();
}

// Kalau ada jadwal, kembalikan status jadwal jadi tersedia (0)
if (!empty($id_jadwal)) {
    sqlsrv_query($conn, "
        UPDATE Jadwal_Studio 
        SET Status_Jadwal = 0, Modified_Date = GETDATE()
        WHERE ID_Jadwal = ?
    ", array($id_jadwal));
}

// Soft delete pembayaran DP yang menunggu (kalau ada)
sqlsrv_query($conn, "
    UPDATE Pembayaran 
    SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE()
    WHERE ID_Order = ? AND Tipe_Pembayaran = 'DP' AND Status_Pembayaran = 0
", array($id_customer, $id_order));

echo json_encode(['success' => true, 'message' => 'Order berhasil dibatalkan']);
?>