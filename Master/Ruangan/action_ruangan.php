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
    
    $sql = "UPDATE Ruangan SET Status = ? WHERE ID_Ruangan = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    }

// 2. LOGIKA HARD DELETE (Hapus Permanen)
} elseif ($type == 'hard') {
    // Validasi Akurat: Cek apakah ruangan sudah pernah digunakan dalam booking
    $sql_cek = "SELECT COUNT(*) as total FROM Orders WHERE ID_Ruangan = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($id));
    $check = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($check['total'] > 0) {
        // Jika sudah ada di transaksi, tidak boleh dihapus permanen (Integritas Data)
        echo "<script>alert('Gagal! Ruangan ini sudah memiliki riwayat booking. Gunakan Nonaktifkan saja.'); window.location='list.php';</script>";
    } else {
        // Ambil nama file foto untuk dihapus dari server
        $q_foto = sqlsrv_query($conn, "SELECT Foto_Ruangan FROM Ruangan WHERE ID_Ruangan = ?", array($id));
        $f = sqlsrv_fetch_array($q_foto, SQLSRV_FETCH_ASSOC);
        if (!empty($f['Foto_Ruangan']) && $f['Foto_Ruangan'] != 'default_ruangan.jpg') {
            $file_path = "../../assets/img/ruangan/" . $f['Foto_Ruangan'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = "DELETE FROM Ruangan WHERE ID_Ruangan = ?";
        sqlsrv_query($conn, $sql, array($id));
        header("Location: list.php?msg=deleted");
    }
}
?>
