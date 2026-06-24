<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

$id_paket = isset($_GET['id_paket']) ? (int)$_GET['id_paket'] : 0;

if ($id_paket <= 0) {
    echo json_encode([]);
    exit();
}

// Ambil ruangan yang valid untuk paket ini (via Paket_Ruangan junction)
$sql = "SELECT r.ID_Ruangan, r.Nama_Ruangan 
        FROM Ruangan r
        INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
        WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0
        ORDER BY r.Nama_Ruangan";

$stmt = sqlsrv_query($conn, $sql, [$id_paket]);
$ruangan = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ruangan[] = $row;
    }
}

echo json_encode($ruangan);
?>