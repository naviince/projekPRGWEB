<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { exit(); }

$type = $_GET['type'] ?? '';
$id   = $_GET['id']   ?? '';

if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Aktif') ? 'Nonaktif' : 'Aktif';
    $res = sqlsrv_query($conn, "UPDATE Tema_Foto SET Status = ? WHERE ID_Tema = ?", array($newStatus, $id));
    if ($res) { header("Location: list.php?msg=status_updated"); }

} elseif ($type == 'hard') {
    $stmt_cek = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM [Order] WHERE ID_Tema = ?", array($id));
    $check    = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($check['total'] > 0) {
        echo "<script>alert('Gagal! Tema ini sudah memiliki riwayat booking. Gunakan Nonaktifkan saja.'); window.location='list.php';</script>";
    } else {
        $q_foto = sqlsrv_query($conn, "SELECT Foto_Tema FROM Tema_Foto WHERE ID_Tema = ?", array($id));
        $f      = sqlsrv_fetch_array($q_foto, SQLSRV_FETCH_ASSOC);
        if (!empty($f['Foto_Tema']) && $f['Foto_Tema'] != 'default_tema.jpg') {
            $fp = "../../assets/img/tema/" . $f['Foto_Tema'];
            if (file_exists($fp)) unlink($fp);
        }
        sqlsrv_query($conn, "DELETE FROM Tema_Foto WHERE ID_Tema = ?", array($id));
        header("Location: list.php?msg=deleted");
    }
}
?>
