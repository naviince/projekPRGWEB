<?php
session_start();
// Path dari Role/Customer/Layanan/Jadwal/ ke root projekPRGWEB/ = ../../../../koneksi.php
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_TERPESAN', 1);

define('STATUS_DATA_AKTIF', 1);

// =====================================================
// PROTEKSI HALAMAN
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_pelanggan = $_SESSION['id_user'];

if (!is_numeric($id_pelanggan) || $id_pelanggan <= 0) {
    header("Location: ../../../../login.php?error=invalid_session");
    exit();
}

// =====================================================
// AMBIL PARAMETER DARI URL (WAJIB ADA SEMUA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) || 
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) || 
    !isset($_GET['id_tema']) || empty($_GET['id_tema']) || 
    !isset($_GET['id_jadwal']) || empty($_GET['id_jadwal'])) {
    
    header("Location: ../Paket/pilih_paket.php?error=data_tidak_lengkap");
    exit();
}

$id_paket   = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema    = (int)$_GET['id_tema'];
$id_jadwal  = (int)$_GET['id_jadwal'];

// =====================================================
// VALIDASI 1: CEK PAKET AKTIF
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Harga_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    [$id_paket, STATUS_DATA_AKTIF]
);
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_paket);

if (!$d_paket) {
    header("Location: ../Paket/pilih_paket.php?error=paket_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI 2: CEK RUANGAN AKTIF & TERHUBUNG DENGAN PAKET
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT r.ID_Ruangan, r.Nama_Ruangan 
     FROM Ruangan r
     INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
     WHERE r.ID_Ruangan = ? 
       AND pr.ID_Paket = ?
       AND r.Status = ? 
       AND r.Is_Deleted = 0", 
    [$id_ruangan, $id_paket, STATUS_DATA_AKTIF]
);
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_ruangan);

if (!$d_ruangan) {
    header("Location: ../Ruangan/pilih_ruangan.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI 3: CEK TEMA AKTIF & TERHUBUNG DENGAN RUANGAN
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT t.ID_Tema, t.Nama_Tema 
     FROM Tema_Foto t
     INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
     WHERE t.ID_Tema = ? 
       AND rt.ID_Ruangan = ?
       AND t.Status = ? 
       AND t.Is_Deleted = 0", 
    [$id_tema, $id_ruangan, STATUS_DATA_AKTIF]
);
$d_tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_tema);

if (!$d_tema) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI 4: CEK JADWAL VALID & TERSEDIA
// =====================================================
$today = date('Y-m-d');

$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai 
     FROM Jadwal_Studio 
     WHERE ID_Jadwal = ? 
       AND ID_Ruangan = ?
       AND Status_Jadwal = ?
       AND Status = ?
       AND Is_Deleted = 0
       AND Tanggal_Jadwal >= ?", 
    [$id_jadwal, $id_ruangan, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF, $today]
);
$d_jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_jadwal);

if (!$d_jadwal) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_tersedia");
    exit();
}

// =====================================================
// VALIDASI 5: CEK JADWAL BELUM DIBOOKING ORDER AKTIF
// =====================================================
$q_cek_booking = sqlsrv_query($conn, 
    "SELECT ID_Order FROM [Order] 
     WHERE ID_Jadwal = ? 
       AND Status = ? 
       AND Status_Order <> ?", 
    [$id_jadwal, STATUS_DATA_AKTIF, STATUS_ORDER_DIBATALKAN]
);
$ada_booking = sqlsrv_fetch_array($q_cek_booking, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_cek_booking);

if ($ada_booking) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_sudah_dibooking");
    exit();
}

// =====================================================
// VALIDASI 6: CEK PELANGGAN AKTIF
// =====================================================
$q_pelanggan = sqlsrv_query($conn, 
    "SELECT ID_Pelanggan FROM Pelanggan 
     WHERE ID_Pelanggan = ? AND Status = ? AND Is_Deleted = 0", 
    [$id_pelanggan, STATUS_DATA_AKTIF]
);
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_pelanggan);

if (!$d_pelanggan) {
    header("Location: ../../../../login.php?error=akun_tidak_aktif");
    exit();
}

// =====================================================
// SIMPAN ORDER KE DATABASE
// =====================================================
$total_paket = $d_paket['Harga_Paket'];
$tanggal_booking = date('Y-m-d H:i:s');

$sql_insert = "INSERT INTO [Order] (
    ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, ID_Jadwal,
    Tanggal_Booking, Total_Paket, Total_Barang_Cetak,
    Status_Order, Status, Created_By, Created_Date
) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, GETDATE())";

$params_insert = [
    $id_pelanggan, $id_paket, $id_ruangan, $id_tema, $id_jadwal,
    $tanggal_booking, $total_paket,
    STATUS_ORDER_MENUNGGU_DP, STATUS_DATA_AKTIF, 'customer'
];

$stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

if ($stmt_insert === false) {
    $errors = sqlsrv_errors();
    error_log("[SpotLight] Gagal simpan order: " . print_r($errors, true));
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=gagal_simpan");
    exit();
}

// Ambil ID_Order yang baru dibuat
$q_last_id = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS last_id");
$d_last_id = sqlsrv_fetch_array($q_last_id, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_last_id);
$id_order = $d_last_id['last_id'] ?? 0;

if ($id_order <= 0) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=gagal_ambil_id");
    exit();
}

// =====================================================
// UPDATE STATUS JADWAL MENJADI TERPESAN
// =====================================================
$sql_update_jadwal = "UPDATE Jadwal_Studio SET 
    Status_Jadwal = ?, 
    Modified_By = ?, 
    Modified_Date = GETDATE() 
    WHERE ID_Jadwal = ?";

$stmt_update = sqlsrv_query($conn, $sql_update_jadwal, [
    STATUS_JADWAL_TERPESAN, 'customer', $id_jadwal
]);

if ($stmt_update === false) {
    error_log("[SpotLight] Gagal update status jadwal ID: $id_jadwal");
}

// =====================================================
// REDIRECT KE HALAMAN PEMBAYARAN DP
// =====================================================
header("Location: ../../Booking/Pembayaran/index.php?id_order=$id_order&status=berhasil");
exit();
?>