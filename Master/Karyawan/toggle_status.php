<?php
session_start();
include '../../koneksi.php'; 

// --- PROTEKSI KEAMANAN HAK AKSES BERLAPIS ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$q_profile = sqlsrv_query($conn, "SELECT Username_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$username_owner = $d_profile['Username_Karyawan'] ?? 'owner';

if (isset($_GET['id'])) {
    $id_toggle = $_GET['id'];
    
    // Proteksi: Larang mengubah status akun sendiri demi keamanan
    if ($id_toggle == $id_owner) {
        header("Location: index.php");
        exit();
    }
    
    // Ambil status saat ini
    $sql_status = "SELECT Status FROM Karyawan WHERE ID_Karyawan = ?";
    $stmt_status = sqlsrv_query($conn, $sql_status, array($id_toggle));
    $row_status = sqlsrv_fetch_array($stmt_status, SQLSRV_FETCH_ASSOC);
    
    if ($row_status) {
        $status_baru = ($row_status['Status'] == 1) ? 0 : 1; // Tukar status (1 -> 0, atau 0 -> 1)
        
        // Update ke database Karyawan
        $sql_upd = "UPDATE Karyawan SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
        $stmt_upd = sqlsrv_query($conn, $sql_upd, array($status_baru, $username_owner, $id_toggle));
        
        if ($stmt_upd) {
            header("Location: index.php?status_sukses=status_ubah");
            exit();
        } else {
            die(print_r(sqlsrv_errors(), true));
        }
    } else {
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>