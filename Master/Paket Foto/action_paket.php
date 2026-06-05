<?php
session_start();
include '../../koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'];
$id   = $_GET['id'];

// 1. LOGIKA SOFT DELETE (Ganti Status Aktif/Nonaktif)
if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Aktif') ? 'Nonaktif' : 'Aktif';
    
    $sql = "UPDATE Paket_Foto SET Status = ? WHERE ID_Paket = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    }

// 2. LOGIKA HARD DELETE (Hapus Permanen)
} elseif ($type == 'hard') {
    // Validasi Akurat: Cek apakah paket sudah pernah dipesan pelanggan
    $sql_cek = "SELECT COUNT(*) as total FROM Orders WHERE ID_Paket = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($id));
    $check = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($check['total'] > 0) {
        // Jika sudah ada di transaksi, tidak boleh dihapus permanen (Integritas Data)
        echo "<script>alert('Gagal! Paket ini sudah memiliki riwayat transaksi. Gunakan Soft Delete saja.'); window.location='list.php';</script>";
    } else {
        // Ambil nama file foto untuk dihapus dari server (Efisien penyimpanan)
        $q_foto = sqlsrv_query($conn, "SELECT Foto_Paket FROM Paket_Foto WHERE ID_Paket = ?", array($id));
        $f = sqlsrv_fetch_array($q_foto, SQLSRV_FETCH_ASSOC);
        if($f['Foto_Paket'] != 'default_paket.jpg') {
            unlink("../../assets/img/paket/" . $f['Foto_Paket']);
        }

        $sql = "DELETE FROM Paket_Foto WHERE ID_Paket = ?";
        sqlsrv_query($conn, $sql, array($id));
        header("Location: list.php?msg=deleted");
    }
}
?>