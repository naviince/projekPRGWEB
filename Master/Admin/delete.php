<?php
session_start();
include '../../koneksi.php'; // Path diperbaiki

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Validasi: Jangan biarkan admin menghapus dirinya sendiri yang sedang login
    if ($id == $_SESSION['id_user']) {
        echo "<script>alert('Anda tidak bisa menghapus akun anda sendiri!'); window.location='list.php';</script>";
        exit();
    }

    // 1. Hapus data di tabel Karyawan terlebih dahulu (Child Table)
    $sql_karyawan = "DELETE FROM Karyawan WHERE ID_User = ?";
    sqlsrv_query($conn, $sql_karyawan, array($id));

    // 2. Hapus data di tabel Users (Parent Table)
    $sql_user = "DELETE FROM Users WHERE ID_User = ?";
    $res = sqlsrv_query($conn, $sql_user, array($id));

    if ($res) {
        header("Location: list.php?msg=deleted");
    } else {
        // Jika gagal karena ada transaksi, tampilkan pesan error akurat
        echo "<script>alert('Gagal menghapus! Admin ini sudah memiliki history transaksi.'); window.location='list.php';</script>";
    }
} else {
    header("Location: list.php");
}
?>