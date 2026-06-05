<?php
session_start();
include '../../koneksi.php';

// Proteksi
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'];
$id   = $_GET['id'];

// VALIDASI AKURAT: Admin tidak boleh menonaktifkan/menghapus dirinya sendiri
if ($id == $_SESSION['id_user']) {
    echo "<script>alert('Error: Anda tidak bisa mengubah status atau menghapus akun anda sendiri demi keamanan sistem!'); window.location='list.php';</script>";
    exit();
}

if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($newStatus, $id));
    header("Location: list.php?msg=status_updated");

} elseif ($type == 'hard') {
    // 1. Hapus Detail (Karyawan) dulu agar tidak error Foreign Key
    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
    
    // 2. Hapus User
    $sql = "DELETE FROM Users WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($id));
    header("Location: list.php?msg=deleted");
}
?>