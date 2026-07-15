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
// PROSES VERIFIKASI — PAKAI STORED PROCEDURE sp_VerifikasiPembayaran
// SP ini sudah menangani (di level database, satu sumber logika):
//   - Update status Pembayaran (Valid/Ditolak)
//   - Update Status_Order otomatis (DP Terverifikasi kalau diterima)
//   - Kalau DITOLAK: otomatis kembalikan Status_Jadwal ke Tersedia (0)
//     lewat tabel Order_Jadwal -> Jadwal_Studio (sebelumnya versi raw
//     query PHP TIDAK melakukan ini, jadi ada bug: jadwal nyangkut
//     status "Booked" walau DP-nya ditolak. Sekarang konsisten.)
//   - Trigger tr_Log_Pembayaran & tr_Log_Order tetap otomatis jalan
//     dan mencatat audit log, karena trigger berjalan di level tabel.
// =====================================================
$status_verifikasi_sp = ($aksi === 'terima') ? STATUS_PEMBAYARAN_VALID : STATUS_PEMBAYARAN_DITOLAK;

$q_sp = sqlsrv_query($conn, "{CALL sp_VerifikasiPembayaran (?, ?, ?, ?)}", [
    $id_pembayaran,
    $status_verifikasi_sp,
    $id_verifikator,
    'admin_verifikasi'
]);

if ($q_sp === false) {
    $errors = sqlsrv_errors();
    $err_msg = $errors ? $errors[0]['message'] : 'Gagal menjalankan prosedur verifikasi.';
    header("Location: list.php?status=error&msg=" . urlencode($err_msg));
    exit();
}

if ($aksi === 'terima') {
    header("Location: list.php?status=sukses&msg=" . urlencode("Pembayaran DP diterima. Order sekarang masuk ke Booking Customer."));
} else {
    header("Location: list.php?status=sukses&msg=" . urlencode("Pembayaran ditolak. Jadwal dikembalikan ke Tersedia, customer harus upload ulang."));
}
exit();
?>