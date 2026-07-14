<?php
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// --- PROTEKSI HALAMAN: HANYA ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_verifikator = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
if (!$id_verifikator) {
    header("Location: list.php?status=error&msg=" . urlencode("Sesi admin tidak valid, silakan login ulang."));
    exit();
}

// =====================================================
// VALIDASI INPUT
// =====================================================
if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id']) || (int)$_GET['id'] <= 0) {
    header("Location: list.php?status=error&msg=" . urlencode("ID pembayaran tidak valid."));
    exit();
}

if (!isset($_GET['aksi']) || empty($_GET['aksi'])) {
    header("Location: list.php?status=error&msg=" . urlencode("Aksi tidak valid."));
    exit();
}

$id_pembayaran = (int)$_GET['id'];
$aksi = $_GET['aksi'];

if (!in_array($aksi, ['terima', 'tolak'], true)) {
    header("Location: list.php?status=error&msg=" . urlencode("Aksi tidak dikenali."));
    exit();
}

// =====================================================
// AMBIL DATA PEMBAYARAN + VALIDASI KONSISTENSI DATA
// =====================================================
$q_pembayaran = sqlsrv_query($conn, 
    "SELECT p.ID_Pembayaran, p.ID_Order, p.Tipe_Pembayaran, p.Status_Pembayaran, o.Status_Order
     FROM Pembayaran p
     INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
     WHERE p.ID_Pembayaran = ? AND p.Status = 1 AND o.Status = 1",
    [$id_pembayaran]
);

if ($q_pembayaran === false) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal mengambil data pembayaran."));
    exit();
}

$d_pembayaran = sqlsrv_fetch_array($q_pembayaran, SQLSRV_FETCH_ASSOC);

if (!$d_pembayaran) {
    header("Location: list.php?status=error&msg=" . urlencode("Pembayaran tidak ditemukan."));
    exit();
}

// Halaman ini KHUSUS verifikasi DP - tolak kalau tipe-nya bukan DP (mis. Pelunasan)
if ($d_pembayaran['Tipe_Pembayaran'] !== 'DP') {
    header("Location: list.php?status=error&msg=" . urlencode("Pembayaran ini bukan DP dan tidak bisa diverifikasi dari halaman ini."));
    exit();
}

// Order sudah dibatalkan (misalnya auto-expired sistem) -> tidak boleh diverifikasi lagi
if ((int)$d_pembayaran['Status_Order'] === STATUS_ORDER_DIBATALKAN) {
    header("Location: list.php?status=error&msg=" . urlencode("Order sudah dibatalkan (kadaluarsa/dibatalkan customer), pembayaran tidak dapat diproses."));
    exit();
}

if ((int)$d_pembayaran['Status_Pembayaran'] !== STATUS_PEMBAYARAN_MENUNGGU) {
    header("Location: list.php?status=error&msg=" . urlencode("Pembayaran ini sudah diverifikasi sebelumnya."));
    exit();
}

$id_order = (int)$d_pembayaran['ID_Order'];

// =====================================================
// PROSES VERIFIKASI (ATOMIC UPDATE - ANTI RACE CONDITION)
// Update pembayaran menyertakan WHERE Status_Pembayaran = MENUNGGU
// supaya kalau ada 2 admin klik bersamaan, hanya 1 yang berhasil.
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal memulai transaksi."));
    exit();
}

try {
    if ($aksi === 'terima') {
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ? AND Status_Pembayaran = ?",
            [STATUS_PEMBAYARAN_VALID, $id_verifikator, $id_pembayaran, STATUS_PEMBAYARAN_MENUNGGU]
        );
        if ($q_update === false) {
            throw new Exception("Gagal update pembayaran.");
        }
        if (sqlsrv_rows_affected($q_update) === 0) {
            throw new Exception("Pembayaran sudah diproses admin lain sebelum Anda.");
        }

        // Kalau DP valid, update order jadi DP Terverifikasi (hanya jika masih Menunggu DP)
        $q_order = sqlsrv_query($conn, 
            "UPDATE [Order] SET Status_Order = ?, Modified_By = ?, Modified_Date = GETDATE() 
             WHERE ID_Order = ? AND Status_Order = ?",
            [STATUS_ORDER_DP_TERVERIFIKASI, 'admin_verifikasi', $id_order, STATUS_ORDER_MENUNGGU_DP]
        );
        if ($q_order === false) {
            throw new Exception("Gagal update status order.");
        }
        if (sqlsrv_rows_affected($q_order) === 0) {
            throw new Exception("Status order sudah berubah (kemungkinan dibatalkan/kadaluarsa), verifikasi dibatalkan.");
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=" . urlencode("Pembayaran DP diterima. Order sekarang masuk ke Booking Customer."));

    } else { // tolak
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ? AND Status_Pembayaran = ?",
            [STATUS_PEMBAYARAN_DITOLAK, $id_verifikator, $id_pembayaran, STATUS_PEMBAYARAN_MENUNGGU]
        );
        if ($q_update === false) {
            throw new Exception("Gagal update pembayaran.");
        }
        if (sqlsrv_rows_affected($q_update) === 0) {
            throw new Exception("Pembayaran sudah diproses admin lain sebelum Anda.");
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=" . urlencode("Pembayaran ditolak. Customer harus upload ulang."));
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: list.php?status=error&msg=" . urlencode($e->getMessage()));
}
exit();
?>