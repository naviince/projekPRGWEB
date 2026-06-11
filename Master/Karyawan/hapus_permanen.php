<?php
session_start();
include '../../koneksi.php'; 

// --- PROTEKSI KEAMANAN HAK AKSES BERLAPIS ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_hapus = $_GET['id'];
    
    // Melakukan Hard Delete: Menghapus baris data selamanya dari database SQL Server [1]
    $sql_hard = "DELETE FROM Karyawan WHERE ID_Karyawan = ?";
    $stmt_hard = sqlsrv_query($conn, $sql_hard, array($id_hapus));
    
    if ($stmt_hard) {
        header("Location: index.php?status_sukses=permanen");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    header("Location: index.php");
    exit();
}
?>