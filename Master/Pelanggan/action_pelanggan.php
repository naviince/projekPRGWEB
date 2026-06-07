<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI AKSES: Hanya Admin yang boleh mengeksekusi penghapusan/perubahan status
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { 
    exit("Akses ditolak. Otoritas tidak mencukupi."); 
}

// 2. VALIDASI PARAMETER (Akurat & Efisien)
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header("Location: list.php");
    exit();
}

$type = $_GET['type'];
$id   = $_GET['id'];

// --- EKSEKUSI LOGIKA ---

// LOGIKA 1: SOFT DELETE (Mengubah Status Akses Pelanggan)
if ($type == 'soft') {
    if (!isset($_GET['status'])) { header("Location: list.php"); exit(); }
    
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    // Update status di tabel Users (Parent)
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }

// LOGIKA 2: HARD DELETE (Hapus Permanen dari Database)
} elseif ($type == 'hard') {
    
    // MENGGUNAKAN TRANSACTION (Keamanan Data Level Tinggi)
    sqlsrv_begin_transaction($conn);

    // A. Hapus profil Pelanggan (Child)
    $sql1 = "DELETE FROM Pelanggan WHERE ID_User = ?";
    $stmt1 = sqlsrv_query($conn, $sql1, array($id));

    // B. Hapus akun User (Parent)
    $sql2 = "DELETE FROM Users WHERE ID_User = ?";
    $stmt2 = sqlsrv_query($conn, $sql2, array($id));

    // VALIDASI HASIL: Jika kedua tabel berhasil dihapus
    if ($stmt1 && $stmt2) {
        sqlsrv_commit($conn); // Simpan perubahan permanen
        header("Location: list.php?msg=deleted");
    } else {
        // Jika gagal (Misal: Pelanggan sudah punya riwayat Booking/Transaksi)
        sqlsrv_rollback($conn); // Batalkan semua agar database tidak rusak
        
        // Memberikan notifikasi yang logis kepada Admin
        echo "<script>
            alert('Gagal Hapus Permanen! Pelanggan ini tidak bisa dihapus karena sudah memiliki riwayat booking atau transaksi di sistem Spotlight. Silakan gunakan fitur NONAKTIFKAN saja.'); 
            window.location='list.php';
        </script>";
    }
} else {
    // Jika tipe aksi tidak dikenal
    header("Location: list.php");
}
?>