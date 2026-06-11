<?php
session_start();
include '../../koneksi.php'; 

// --- PROTEKSI KEAMANAN HAK AKSES BERLAPIS ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
$username_owner = $d_profile['username_karyawan'] ?? 'owner';

if (isset($_GET['id'])) {
    $id_hapus = $_GET['id'];
    
    // Melakukan Soft Delete: Is_Deleted diubah menjadi 1 dan mencatat siapa yang menghapusnya [1]
    $sql_soft = "UPDATE Karyawan SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Karyawan = ?";
    $stmt_soft = sqlsrv_query($conn, $sql_soft, array($username_owner, $id_hapus));
    
    if ($stmt_soft) {
        header("Location: index.php?status_sukses=lembut");
        exit();
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    header("Location: index.php");
    exit();
}
?>