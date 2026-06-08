<?php
session_start();
include '../../koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'] ?? '';
$id   = $_GET['id']   ?? '';

// 1. LOGIKA SOFT DELETE (Toggle Status Aktif/Nonaktif)
if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Aktif') ? 'Nonaktif' : 'Aktif';

    $sql = "UPDATE Tema_Foto SET Status = ? WHERE ID_Tema = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));

    if ($res) {
        header("Location: list.php?msg=status_updated");
    }

// 2. LOGIKA HARD DELETE (Hapus Permanen)
} elseif ($type == 'hard') {
    // Validasi: cek apakah tema sudah pernah digunakan dalam booking (Diselaraskan ke [Order])
    $sql_cek  = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Tema = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($id));
    $check    = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($check['total'] > 0) {
        // Sudah ada di transaksi → tidak boleh dihapus permanen
        echo "<script>
            alert('Gagal! Tema ini sudah memiliki riwayat booking. Gunakan Nonaktifkan saja.');
            window.location='list.php';
        </script>";
    } else {
        // Hapus foto dari server
        $q_foto = sqlsrv_query($conn, "SELECT Foto_Tema FROM Tema_Foto WHERE ID_Tema = ?", array($id));
        $f      = sqlsrv_fetch_array($q_foto, SQLSRV_FETCH_ASSOC);

        if (!empty($f['Foto_Tema']) && $f['Foto_Tema'] != 'default_tema.jpg') {
            $file_path = "../../assets/img/tema/" . $f['Foto_Tema'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = "DELETE FROM Tema_Foto WHERE ID_Tema = ?";
        sqlsrv_query($conn, $sql, array($id));
        header("Location: list.php?msg=deleted");
    }
}
?>