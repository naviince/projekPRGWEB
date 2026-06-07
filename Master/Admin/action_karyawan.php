<?php
session_start();
include '../../koneksi.php';

// Proteksi: Hanya Admin yang bisa akses
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit("Akses Ditolak"); }

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
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    }

} elseif ($type == 'hard') {
    // LOGIKA HARD DELETE DENGAN TRANSACTION (Sangat Aman & Akurat)
    sqlsrv_begin_transaction($conn);

    // 1. Hapus Profil di tabel Karyawan (Child)
    $sql1 = "DELETE FROM Karyawan WHERE ID_User = ?";
    $stmt1 = sqlsrv_query($conn, $sql1, array($id));

    // 2. Hapus Akun di tabel Users (Parent)
    $sql2 = "DELETE FROM Users WHERE ID_User = ?";
    $stmt2 = sqlsrv_query($conn, $sql2, array($id));

    // Validasi: Jika dua-duanya berhasil, baru simpan ke database
    if ($stmt1 && $stmt2) {
        sqlsrv_commit($conn);
        header("Location: list.php?msg=deleted");
    } else {
        // Jika gagal (biasanya karena ada relasi transaksi sesi foto), batalkan semua
        sqlsrv_rollback($conn);
        echo "<script>alert('Gagal Hapus Permanen! Karyawan ini kemungkinan besar memiliki riwayat transaksi/tugas di tabel lain. Gunakan Soft Delete saja.'); window.location='list.php';</script>";
    }
}
?>