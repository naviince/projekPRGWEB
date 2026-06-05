<?php
session_start();
include '../../koneksi.php';

// Proteksi
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'];
$id   = $_GET['id'];

// LOGIKA 1: SOFT DELETE (Toggle Status)
if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    }

// LOGIKA 2: HARD DELETE (Hapus Permanen)
} elseif ($type == 'hard') {
    // Gunakan Transaction agar sinkronisasi Pelanggan & Users terjamin
    sqlsrv_begin_transaction($conn);

    // A. Hapus Profil Pelanggan (Child)
    $sql1 = "DELETE FROM Pelanggan WHERE ID_User = ?";
    $res1 = sqlsrv_query($conn, $sql1, array($id));

    // B. Hapus Akun User (Parent)
    $sql2 = "DELETE FROM Users WHERE ID_User = ?";
    $res2 = sqlsrv_query($conn, $sql2, array($id));

    if ($res1 && $res2) {
        sqlsrv_commit($conn); // Simpan perubahan jika keduanya sukses
        header("Location: list.php?msg=deleted");
    } else {
        sqlsrv_rollback($conn); // Batalkan semua jika gagal (misal: ada relasi booking)
        echo "<script>alert('Gagal menghapus! Pelanggan ini sudah memiliki data transaksi booking.'); window.location='list.php';</script>";
    }
}
?>