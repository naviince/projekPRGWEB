<?php
session_start();
include '../../../koneksi.php';

$id = $_GET['id'];

// Cari tahu role-nya dulu untuk hapus detail
$q = sqlsrv_query($conn, "SELECT Role_User FROM Users WHERE ID_User = ?", array($id));
$u = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if ($u['Role_User'] == 'Customer') {
    sqlsrv_query($conn, "DELETE FROM Pelanggan WHERE ID_User = ?", array($id));
} else {
    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
}

// Terakhir hapus User
sqlsrv_query($conn, "DELETE FROM Users WHERE ID_User = ?", array($id));

header("Location: list.php");
?>