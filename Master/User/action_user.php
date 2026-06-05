<?php
session_start();
include '../../koneksi.php';

$type = $_GET['type'];
$id   = $_GET['id'];

if ($type == 'soft') {
    // Logika Soft Delete: Balikkan status Active <-> Inactive
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($newStatus, $id));
    header("Location: list.php?msg=status_changed");

} elseif ($type == 'hard') {
    // Validasi Akurat: Cek apakah ID yang dihapus adalah diri sendiri
    if ($id == $_SESSION['id_user']) {
        echo "<script>alert('Error: Anda tidak bisa menghapus diri sendiri!'); window.location='list.php';</script>";
        exit();
    }

    // Logika Hard Delete: Hapus dari database (Hapus profil dulu agar tidak error Foreign Key)
    sqlsrv_query($conn, "DELETE FROM Pelanggan WHERE ID_User = ?", array($id));
    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
    
    $sql = "DELETE FROM Users WHERE ID_User = ?";
    sqlsrv_query($conn, $sql, array($id));
    header("Location: list.php?msg=permanently_deleted");
}
?>