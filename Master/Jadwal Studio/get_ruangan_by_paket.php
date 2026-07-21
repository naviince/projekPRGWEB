<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

// --- SINKRONISASI KEAMANAN: Proteksi akses API dari pihak luar yang belum login ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    http_response_code(403); // Kirim kode status 403 Forbidden (Akses Ditolak)
    echo json_encode(['error' => 'Akses ditolak. Silakan login terlebih dahulu sebagai Admin.']);
    exit();
}

// --- AMBIL PARAMETER ID PAKET ---
$id_paket = isset($_GET['id_paket']) ? (int)$_GET['id_paket'] : 0;

if ($id_paket <= 0) {
    echo json_encode([]);
    exit();
}

// Ambil ruangan yang aktif dan valid untuk paket ini (via Paket_Ruangan junction)
// Ditambahkan validasi defensive untuk memastikan Paket Foto terkait juga berstatus aktif & belum terhapus
$sql = "SELECT r.ID_Ruangan, r.Nama_Ruangan 
        FROM Ruangan r
        INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
        INNER JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket
        WHERE pr.ID_Paket = ? 
          AND r.Status = 1 AND r.Is_Deleted = 0
          AND p.Status = 1 AND p.Is_Deleted = 0
        ORDER BY r.Nama_Ruangan";

$stmt = sqlsrv_query($conn, $sql, [$id_paket]);
$ruangan = [];

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ruangan[] = $row;
    }
    sqlsrv_free_stmt($stmt);
} else {
    // Log error ke server jika kueri gagal demi kemudahan debugging oleh admin
    error_log("[SpotLight] AJAX get_ruangan_by_paket failed: " . print_r(sqlsrv_errors(), true));
}

// Selalu kembalikan respons berformat JSON yang valid
echo json_encode($ruangan);
?>