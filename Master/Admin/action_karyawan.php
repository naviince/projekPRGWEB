<?php
session_start();
include '../../koneksi.php';

// Proteksi: Hanya Admin yang bisa akses
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'];
$id   = $_GET['id'];

// VALIDASI AKURAT: Karyawan tidak boleh menonaktifkan/menghapus dirinya sendiri
if ($id == $_SESSION['id_user']) {
    echo "<script>alert('Error: Anda tidak bisa mengubah status atau menghapus profil anda sendiri demi keamanan sistem!'); window.location='list.php';</script>";
    exit();
}

if ($type == 'soft') {
    // Logika Soft Delete: Update Status di tabel Users (Parent)
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($newStatus, $id));
    header("Location: list.php?msg=status_updated");

} elseif ($type == 'hard') {
    // Logika Hard Delete (Efisien & Menjaga Integritas Data)
    // 1. Hapus Profil di tabel Karyawan (Child) terlebih dahulu
    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
    
    // 2. Hapus Akun di tabel Users (Parent)
    $sql = "DELETE FROM Users WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($id));
    header("Location: list.php?msg=deleted");
}
?>