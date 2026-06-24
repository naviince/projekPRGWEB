<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$username_admin = $_SESSION['username'] ?? 'admin';

// =====================================================
// PROSES SOFT CANCEL SESI FOTO
// =====================================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id_sesi = intval($_GET['id']);

// Cek apakah sesi ada dan masih aktif + status menunggu
$cek_sql = "SELECT S.Status_Sesi, S.ID_Order, O.Status_Order 
            FROM Sesi_Foto S 
            JOIN [Order] O ON S.ID_Order = O.ID_Order 
            WHERE S.ID_Sesi_Foto = ? AND S.Status = 1";
$cek_stmt = sqlsrv_query($conn, $cek_sql, array($id_sesi));

if (!$cek_stmt || !sqlsrv_has_rows($cek_stmt)) {
    header("Location: list.php?error=notfound");
    exit();
}

$cek_data = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

// Validasi: hanya bisa cancel jika status sesi = 0 (Menunggu)
if ($cek_data['Status_Sesi'] != 0) {
    header("Location: list.php?error=cannotcancel");
    exit();
}

// Validasi: hanya bisa cancel jika order belum selesai (Status_Order < 3)
if ($cek_data['Status_Order'] >= 3) {
    header("Location: list.php?error=ordercompleted");
    exit();
}

// Lakukan soft cancel: update Status_Sesi = 2 (Dibatalkan)
$cancel_sql = "UPDATE Sesi_Foto SET 
    Status_Sesi = 2, 
    Modified_By = ?, 
    Modified_Date = GETDATE() 
    WHERE ID_Sesi_Foto = ? AND Status = 1 AND Status_Sesi = 0";

$cancel_stmt = sqlsrv_query($conn, $cancel_sql, array($username_admin, $id_sesi));

if ($cancel_stmt && sqlsrv_rows_affected($cancel_stmt) > 0) {
    // Update jadwal studio menjadi tersedia kembali (Status_Jadwal = 0)
    $jadwal_sql = "UPDATE Jadwal_Studio SET Status_Jadwal = 0 
                   WHERE ID_Jadwal = (SELECT ID_Jadwal FROM [Order] WHERE ID_Order = ?)";
    sqlsrv_query($conn, $jadwal_sql, array($cek_data['ID_Order']));

    header("Location: list.php?success=cancelled");
    exit();
} else {
    header("Location: list.php?error=failed");
    exit();
}
?>