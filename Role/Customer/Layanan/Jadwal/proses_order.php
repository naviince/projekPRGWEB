<?php
session_start();
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
define('STATUS_JADWAL_BOOKED', 1);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL DATA DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) ||
    !isset($_GET['id_tema']) || empty($_GET['id_tema']) ||
    !isset($_GET['tanggal']) || empty($_GET['tanggal']) ||
    !isset($_GET['jam_mulai']) || empty($_GET['jam_mulai']) ||
    !isset($_GET['jam_selesai']) || empty($_GET['jam_selesai'])) {

    header("Location: ../../index.php?error=data_tidak_lengkap");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema = (int)$_GET['id_tema'];
$tanggal = $_GET['tanggal'];
$jam_mulai = $_GET['jam_mulai'];
$jam_selesai = $_GET['jam_selesai'];

// =====================================================
// VALIDASI TANGGAL & JAM FORMAT
// =====================================================
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=tanggal_invalid");
    exit();
}

// =====================================================
// AMBIL DATA PAKET (untuk harga & durasi)
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT Nama_Paket, Durasi_Waktu, Harga_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    array($id_paket, STATUS_DATA_AKTIF)
);

if ($q_paket === false) {
    die('Error query paket: ' . print_r(sqlsrv_errors(), true));
}

$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_paket);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

$harga_paket = $d_paket['Harga_Paket'];
$dp_amount = $harga_paket * 0.65; // DP 65%
$sisa_amount = $harga_paket - $dp_amount;

// =====================================================
// CEK DUPLIKAT: JADWAL SUDAH DIBOOKING ORANG LAIN?
// =====================================================
$q_cek = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total 
     FROM [Order] o
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE o.ID_Ruangan = ? 
       AND j.Tanggal_Jadwal = ?
       AND j.Jam_Mulai = ?
       AND j.Jam_Selesai = ?
       AND o.Status = 1 
       AND o.Status_Order NOT IN (?)
       AND j.Is_Deleted = 0",
    array($id_ruangan, $tanggal, $jam_mulai, $jam_selesai, STATUS_ORDER_DIBATALKAN)
);

if ($q_cek === false) {
    die('Error cek duplikat: ' . print_r(sqlsrv_errors(), true));
}

$d_cek = sqlsrv_fetch_array($q_cek, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_cek);

if ($d_cek['total'] > 0) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=slot_sudah_dibooking");
    exit();
}

// =====================================================
// CEK DUPLIKAT: CUSTOMER SUDAH BOOKING JADWAL INI?
// =====================================================
$q_cek_customer = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total 
     FROM [Order] o
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE o.ID_Pelanggan = ?
       AND j.Tanggal_Jadwal = ?
       AND j.Jam_Mulai = ?
       AND j.Jam_Selesai = ?
       AND o.Status = 1 
       AND o.Status_Order NOT IN (?)
       AND j.Is_Deleted = 0",
    array($id_customer, $tanggal, $jam_mulai, $jam_selesai, STATUS_ORDER_DIBATALKAN)
);

if ($q_cek_customer === false) {
    die('Error cek customer: ' . print_r(sqlsrv_errors(), true));
}

$d_cek_customer = sqlsrv_fetch_array($q_cek_customer, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_cek_customer);

if ($d_cek_customer['total'] > 0) {
    header("Location: pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=anda_sudah_booking");
    exit();
}

// =====================================================
// CARI ATAU BUAT JADWAL DI Jadwal_Studio
// =====================================================
$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal FROM Jadwal_Studio 
     WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Jam_Mulai = ? AND Jam_Selesai = ? AND Is_Deleted = 0",
    array($id_ruangan, $tanggal, $jam_mulai, $jam_selesai)
);

if ($q_jadwal === false) {
    die('Error cari jadwal: ' . print_r(sqlsrv_errors(), true));
}

$d_jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_jadwal);

if ($d_jadwal) {
    // Jadwal sudah ada, pakai yang existing
    $id_jadwal = $d_jadwal['ID_Jadwal'];
} else {
    // Buat jadwal baru
    $q_insert_jadwal = sqlsrv_query($conn, 
        "INSERT INTO Jadwal_Studio (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Status_Jadwal, Status, Is_Deleted)
         VALUES (?, ?, ?, ?, ?, ?, 0)",
        array($id_ruangan, $tanggal, $jam_mulai, $jam_selesai, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
    );

    if ($q_insert_jadwal === false) {
        die('Error insert jadwal: ' . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($q_insert_jadwal);

    // Coba ambil ID dengan MAX (lebih aman dari SCOPE_IDENTITY)
    $q_get_id = sqlsrv_query($conn, 
        "SELECT MAX(ID_Jadwal) as ID_Jadwal FROM Jadwal_Studio 
         WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Jam_Mulai = ? AND Jam_Selesai = ?",
        array($id_ruangan, $tanggal, $jam_mulai, $jam_selesai)
    );

    if ($q_get_id === false) {
        die('Error get jadwal ID: ' . print_r(sqlsrv_errors(), true));
    }
    $d_new_jadwal = sqlsrv_fetch_array($q_get_id, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($q_get_id);

    $id_jadwal = $d_new_jadwal['ID_Jadwal'];
}

// =====================================================
// VALIDASI: ID_Jadwal harus punya nilai
// =====================================================
if (empty($id_jadwal) || $id_jadwal === null) {
    die('Error: ID_Jadwal kosong. Jadwal tidak berhasil dibuat.');
}

// =====================================================
// SIMPAN ORDER KE DATABASE
// =====================================================
// Total_Harga adalah computed column - JANGAN di-insert
// ID_Order auto-increment - JANGAN di-insert
$q_insert_order = sqlsrv_query($conn, 
    "INSERT INTO [Order] (ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, ID_Jadwal, Status_Order, Status)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
    array(
        $id_customer,
        $id_paket,
        $id_ruangan,
        $id_tema,
        $id_jadwal,
        STATUS_ORDER_MENUNGGU_DP,
        STATUS_DATA_AKTIF
    )
);

if ($q_insert_order === false) {
    $errors = sqlsrv_errors();
    // Rollback: update jadwal kembali ke tersedia kalau baru dibuat
    sqlsrv_query($conn, "UPDATE Jadwal_Studio SET Status_Jadwal = ? WHERE ID_Jadwal = ?", array(STATUS_JADWAL_TERSEDIA, $id_jadwal));
    die('Error insert order: ' . print_r($errors, true));
}

sqlsrv_free_stmt($q_insert_order);

// Ambil ID_Order yang baru dibuat dengan MAX (lebih aman)
$q_get_order_id = sqlsrv_query($conn, 
    "SELECT MAX(ID_Order) as ID_Order FROM [Order] 
     WHERE ID_Pelanggan = ? AND ID_Paket = ? AND ID_Ruangan = ? AND ID_Tema = ? AND ID_Jadwal = ?",
    array($id_customer, $id_paket, $id_ruangan, $id_tema, $id_jadwal)
);
if ($q_get_order_id === false) {
    die('Error get order ID: ' . print_r(sqlsrv_errors(), true));
}
$d_new_order = sqlsrv_fetch_array($q_get_order_id, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_get_order_id);

$id_order = $d_new_order['ID_Order'];

if (empty($id_order) || $id_order === null) {
    die('Error: ID_Order kosong. Order tidak berhasil dibuat.');
}

// =====================================================
// UPDATE JADWAL MENJADI BOOKED
// =====================================================
$q_update_jadwal = sqlsrv_query($conn, 
    "UPDATE Jadwal_Studio SET Status_Jadwal = ? WHERE ID_Jadwal = ?",
    array(STATUS_JADWAL_BOOKED, $id_jadwal)
);

if ($q_update_jadwal === false) {
    die('Error update jadwal: ' . print_r(sqlsrv_errors(), true));
}
sqlsrv_free_stmt($q_update_jadwal);

// =====================================================
// SIMPAN KE SESSION (untuk halaman pembayaran)
// =====================================================
$_SESSION['order_id'] = $id_order;
$_SESSION['order_harga'] = $harga_paket;
$_SESSION['order_dp'] = $dp_amount;
$_SESSION['order_sisa'] = $sisa_amount;

// =====================================================
// REDIRECT KE HALAMAN PEMBAYARAN
// =====================================================
header("Location: ../../Booking/Pembayaran/index.php?id_order=$id_order&success=order_berhasil");
exit();
?>