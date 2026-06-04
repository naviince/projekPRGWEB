<?php
session_start();
include '../../koneksi.php';
$id = $_GET['id'];

// Validasi Akurat: Admin tidak bisa hapus dirinya sendiri
if ($id == $_SESSION['id_user']) {
    header("Location: list.php?msg=error_self_delete");
    exit();
}

// Hapus Relasi Profil Dulu
sqlsrv_query($conn, "DELETE FROM Pelanggan WHERE ID_User = ?", array($id));
sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));

// Baru Hapus User
$sql = "DELETE FROM Users WHERE ID_User = ?";
sqlsrv_query($conn, $sql, array($id));

header("Location: list.php?msg=deleted");
?>