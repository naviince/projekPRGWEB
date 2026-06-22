<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID+tidak+valid");
    exit();
}

// =====================================================
// TOGGLE STATUS (Soft Delete / Aktif-Nonaktif)
// =====================================================
if ($aksi === 'toggle_status') {
    $new_status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

    // Validate status value
    if (!in_array($new_status, [0, 1])) {
        header("Location: list.php?status_sukses=error&message=Status+tidak+valid");
        exit();
    }

    // Check if paket exists and is not hard deleted
    $cek_sql = "SELECT ID_Paket, Nama_Paket, Status FROM Paket_Foto WHERE ID_Paket = ? AND Is_Deleted = 0";
    $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);

    if ($cek_stmt === false || !sqlsrv_has_rows($cek_stmt)) {
        header("Location: list.php?status_sukses=error&message=Paket+tidak+ditemukan");
        exit();
    }

    $current = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($cek_stmt);

    // Update status
    $update_sql = "UPDATE Paket_Foto 
                   SET Status = ?, 
                       Modified_By = ?, 
                       Modified_Date = GETDATE() 
                   WHERE ID_Paket = ? AND Is_Deleted = 0";

    $update_stmt = sqlsrv_query($conn, $update_sql, [$new_status, $nama_admin, $id]);

    if ($update_stmt) {
        sqlsrv_free_stmt($update_stmt);
        header("Location: list.php?status_sukses=toggle_status");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+ubah+status");
        exit();
    }
}

// =====================================================
// HARD DELETE (Permanent Delete)
// =====================================================
elseif ($aksi === 'hard_delete') {
    // Check if paket has any orders (to prevent deleting used packages)
    $cek_order_sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ? AND Status = 1 AND Status_Order <> 4";
    $cek_order_stmt = sqlsrv_query($conn, $cek_order_sql, [$id]);

    $has_orders = false;
    if ($cek_order_stmt !== false) {
        $order_row = sqlsrv_fetch_array($cek_order_stmt, SQLSRV_FETCH_ASSOC);
        $has_orders = ($order_row['total'] ?? 0) > 0;
        sqlsrv_free_stmt($cek_order_stmt);
    }

    if ($has_orders) {
        header("Location: list.php?status_sukses=error&message=Paket+masih+memiliki+order+aktif");
        exit();
    }

    // Soft delete approach (mark as deleted, don't actually remove)
    // This preserves referential integrity with Jadwal_Studio and Order tables
    $delete_sql = "UPDATE Paket_Foto 
                   SET Is_Deleted = 1, 
                       Status = 0,
                       Deleted_By = ?, 
                       Deleted_Date = GETDATE() 
                   WHERE ID_Paket = ? AND Is_Deleted = 0";

    $delete_stmt = sqlsrv_query($conn, $delete_sql, [$nama_admin, $id]);

    if ($delete_stmt) {
        sqlsrv_free_stmt($delete_stmt);
        header("Location: list.php?status_sukses=hard_delete");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+hapus+paket");
        exit();
    }
}

// Invalid action
else {
    header("Location: list.php?status_sukses=error&message=Aksi+tidak+valid");
    exit();
}
?>