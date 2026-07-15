<?php
session_start();
include '../../../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// =====================================================
// PROTEKSI HALAMAN - HANYA CUSTOMER YANG LOGIN
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit();
}

$id_customer = $_SESSION['id_user'] ?? $_SESSION['id_pelanggan'] ?? null;
if (!$id_customer) {
    echo json_encode(['success' => false, 'message' => 'ID pelanggan tidak ditemukan dalam sesi.']);
    exit();
}

// =====================================================
// KONSTANTA STATUS - SINKRON DENGAN DATABASE
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI_FOTO', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// =====================================================
// VALIDASI INPUT
// =====================================================
if (!isset($_GET['id_order'])) {
    echo json_encode(['success' => false, 'message' => 'ID Order tidak ditemukan.']);
    exit();
}

$id_order = intval($_GET['id_order']);

if ($id_order <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Order tidak valid.']);
    exit();
}

// =====================================================
// CEK KEPEMILIKAN ORDER & STATUS
// =====================================================
$cek_sql = "
    SELECT 
        o.Status_Order,
        o.Total_Paket,
        o.Total_Barang_Cetak,
        p.Nama_Pelanggan
    FROM [Order] o
    INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
    WHERE o.ID_Order = ? 
      AND o.ID_Pelanggan = ? 
      AND o.Status = 1
";

$cek_stmt = sqlsrv_query($conn, $cek_sql, [$id_order, $id_customer]);

if ($cek_stmt === false) {
    $errors = sqlsrv_errors();
    $error_msg = 'Terjadi kesalahan database saat memverifikasi order.';
    if ($errors != null) {
        foreach ($errors as $error) {
            $error_msg .= ' [' . $error['message'] . ']';
        }
    }
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit();
}

if (!sqlsrv_has_rows($cek_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan atau bukan milik Anda.']);
    exit();
}

$row = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

// =====================================================
// VALIDASI STATUS ORDER - HANYA BISA DIBATALKAN JIKA MENUNGGU DP
// =====================================================
if ($row['Status_Order'] != STATUS_ORDER_MENUNGGU_DP) {
    $status_labels = [
        STATUS_ORDER_DP_TERVERIFIKASI => 'DP Terverifikasi',
        STATUS_ORDER_SELESAI_FOTO => 'Selesai Foto',
        STATUS_ORDER_LUNAS => 'Lunas',
        STATUS_ORDER_DIBATALKAN => 'Sudah Dibatalkan'
    ];
    $current_status = $status_labels[$row['Status_Order']] ?? 'Unknown';

    echo json_encode([
        'success' => false, 
        'message' => 'Order tidak dapat dibatalkan karena statusnya sudah "' . $current_status . '".'
    ]);
    exit();
}

// =====================================================
// TARIK SEMUA JADWAL TERKAIT ORDER (MULTI-JADWAL)
// =====================================================
$jadwal_list = [];
$jadwal_sql = "
    SELECT oj.ID_Jadwal
    FROM Order_Jadwal oj
    INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
    WHERE oj.ID_Order = ? AND j.Status = 1 AND j.Is_Deleted = 0
";

$jadwal_stmt = sqlsrv_query($conn, $jadwal_sql, [$id_order]);
if ($jadwal_stmt !== false) {
    while ($j = sqlsrv_fetch_array($jadwal_stmt, SQLSRV_FETCH_ASSOC)) {
        $jadwal_list[] = $j['ID_Jadwal'];
    }
}

// =====================================================
// PROSES PEMBATALAN — PAKAI STORED PROCEDURE sp_BatalkanOrderBooking
// UNTUK BAGIAN ORDER + JADWAL (satu sumber logika sama kayak
// SP yang dipakai admin di sp_BatalkanOrderBooking versi database).
// Bagian Pembayaran & Penjualan tetap ditangani di PHP karena
// belum ada SP khusus untuk itu di database.
// =====================================================
$username_cust = $_SESSION['username'] ?? 'customer';

sqlsrv_begin_transaction($conn);
try {
    // 1 & 2. Panggil SP: update Status_Order = Dibatalkan (4) + reset semua
    // Jadwal_Studio terkait ke Tersedia (0), lewat Order_Jadwal junction.
    $q_sp = sqlsrv_query($conn, "{CALL sp_BatalkanOrderBooking (?, ?)}", [
        $id_order,
        $username_cust
    ]);

    if ($q_sp === false) {
        $errors = sqlsrv_errors();
        $err_msg = $errors ? $errors[0]['message'] : 'Gagal menjalankan prosedur pembatalan order.';
        throw new Exception($err_msg);
    }

    // 3. Soft delete pembayaran DP yang masih menunggu verifikasi (kalau ada)
    $update_pembayaran_sql = "
        UPDATE Pembayaran 
        SET 
            Status = 0, 
            Modified_By = ?, 
            Modified_Date = GETDATE()
        WHERE ID_Order = ? 
          AND Tipe_Pembayaran = 'DP' 
          AND Status_Pembayaran = ?
          AND Status = 1
    ";

    $update_pembayaran_stmt = sqlsrv_query($conn, $update_pembayaran_sql, [
        $username_cust,
        $id_order,
        STATUS_PEMBAYARAN_MENUNGGU
    ]);

    if ($update_pembayaran_stmt === false) {
        throw new Exception('Gagal memperbarui status pembayaran.');
    }

    // 4. Soft delete penjualan barang cetak terkait (kalau ada)
    $update_penjualan_sql = "
        UPDATE Penjualan 
        SET 
            Status = 0, 
            Modified_By = ?, 
            Modified_Date = GETDATE()
        WHERE ID_Order = ? 
          AND Status = 1
    ";

    $update_penjualan_stmt = sqlsrv_query($conn, $update_penjualan_sql, [
        $username_cust,
        $id_order
    ]);

    // Commit transaksi
    sqlsrv_commit($conn);

    // Siapkan response detail
    $total_jadwal = count($jadwal_list);

    echo json_encode([
        'success' => true, 
        'message' => 'Order berhasil dibatalkan. ' . 
                     ($total_jadwal > 0 ? $total_jadwal . ' slot jadwal telah dilepas kembali.' : ''),
        'data' => [
            'id_order' => $id_order,
            'status_baru' => 'Dibatalkan',
            'jadwal_dilepas' => $total_jadwal,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal membatalkan order: ' . $e->getMessage()]);
    exit();
}
?>