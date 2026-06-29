<?php
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// --- PROTEKSI HALAMAN: HANYA ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['aksi']) || empty($_GET['aksi'])) {
    header("Location: list.php?status=error&msg=Data tidak valid");
    exit();
}

$id_pembayaran = (int)$_GET['id'];
$aksi = $_GET['aksi'];

if (!in_array($aksi, ['terima', 'tolak'])) {
    header("Location: list.php?status=error&msg=Aksi tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA PEMBAYARAN
// =====================================================
$q_pembayaran = sqlsrv_query($conn, 
    "SELECT p.ID_Pembayaran, p.ID_Order, p.Tipe_Pembayaran, p.Status_Pembayaran, o.Status_Order
     FROM Pembayaran p
     INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
     WHERE p.ID_Pembayaran = ? AND p.Status = 1",
    [$id_pembayaran]
);
$d_pembayaran = sqlsrv_fetch_array($q_pembayaran, SQLSRV_FETCH_ASSOC);

if (!$d_pembayaran) {
    header("Location: list.php?status=error&msg=Pembayaran tidak ditemukan");
    exit();
}

if ((int)$d_pembayaran['Status_Pembayaran'] !== STATUS_PEMBAYARAN_MENUNGGU) {
    header("Location: list.php?status=error&msg=Pembayaran sudah diverifikasi sebelumnya");
    exit();
}

$id_order = (int)$d_pembayaran['ID_Order'];
$tipe_pembayaran = $d_pembayaran['Tipe_Pembayaran'];

// =====================================================
// PROSES VERIFIKASI
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=Gagal memulai transaction");
    exit();
}

try {
    if ($aksi === 'terima') {
        // Update pembayaran jadi VALID
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ?",
            [STATUS_PEMBAYARAN_VALID, $_SESSION['id_user'] ?? $_SESSION['id_karyawan'], $id_pembayaran]
        );
        if (!$q_update) {
            throw new Exception("Gagal update pembayaran: " . print_r(sqlsrv_errors(), true));
        }

        // Kalau DP valid, update order jadi DP Terverifikasi
        if ($tipe_pembayaran === 'DP') {
            $q_order = sqlsrv_query($conn, 
                "UPDATE [Order] SET Status_Order = ? WHERE ID_Order = ?",
                [STATUS_ORDER_DP_TERVERIFIKASI, $id_order]
            );
            if (!$q_order) {
                throw new Exception("Gagal update order: " . print_r(sqlsrv_errors(), true));
            }
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=Pembayaran DP diterima. Order sekarang masuk ke Booking Customer.");

    } else { // tolak
        // Update pembayaran jadi DITOLAK
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ?",
            [STATUS_PEMBAYARAN_DITOLAK, $_SESSION['id_user'] ?? $_SESSION['id_karyawan'], $id_pembayaran]
        );
        if (!$q_update) {
            throw new Exception("Gagal update pembayaran: " . print_r(sqlsrv_errors(), true));
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=Pembayaran ditolak. Customer harus upload ulang.");
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: list.php?status=error&msg=" . urlencode($e->getMessage()));
}
exit();<?php
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_LUNAS', 3); // SINKRONISASI: Status Lunas & Selesai

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// --- PROTEKSI HALAMAN: HANYA ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['aksi']) || empty($_GET['aksi'])) {
    header("Location: list.php?status=error&msg=Data tidak valid");
    exit();
}

$id_pembayaran = (int)$_GET['id'];
$aksi = $_GET['aksi'];

if (!in_array($aksi, ['terima', 'tolak'])) {
    header("Location: list.php?status=error&msg=Aksi tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA PEMBAYARAN
// =====================================================
$q_pembayaran = sqlsrv_query($conn, 
    "SELECT p.ID_Pembayaran, p.ID_Order, p.Tipe_Pembayaran, p.Status_Pembayaran, o.Status_Order
     FROM Pembayaran p
     INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
     WHERE p.ID_Pembayaran = ? AND p.Status = 1",
    [$id_pembayaran]
);
$d_pembayaran = sqlsrv_fetch_array($q_pembayaran, SQLSRV_FETCH_ASSOC);

if (!$d_pembayaran) {
    header("Location: list.php?status=error&msg=Pembayaran tidak ditemukan");
    exit();
}

if ((int)$d_pembayaran['Status_Pembayaran'] !== STATUS_PEMBAYARAN_MENUNGGU) {
    header("Location: list.php?status=error&msg=Pembayaran sudah diverifikasi sebelumnya");
    exit();
}

$id_order = (int)$d_pembayaran['ID_Order'];
$tipe_pembayaran = $d_pembayaran['Tipe_Pembayaran'];

// =====================================================
// PROSES VERIFIKASI (MENDUKUNG DP DAN PELUNASAN SECARA SINKRON)
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=Gagal memulai transaction");
    exit();
}

try {
    if ($aksi === 'terima') {
        // 1. Update pembayaran jadi VALID (Bisa DP maupun Pelunasan)
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ?",
            [STATUS_PEMBAYARAN_VALID, $_SESSION['id_user'] ?? $_SESSION['id_karyawan'], $id_pembayaran]
        );
        if (!$q_update) {
            throw new Exception("Gagal update pembayaran: " . print_r(sqlsrv_errors(), true));
        }

        // 2. Cabang logika berdasarkan tipe pembayaran yang diterima
        if ($tipe_pembayaran === 'DP') {
            // Kalau DP valid, update order jadi DP Terverifikasi (1)
            $q_order = sqlsrv_query($conn, 
                "UPDATE [Order] SET Status_Order = ? WHERE ID_Order = ?",
                [STATUS_ORDER_DP_TERVERIFIKASI, $id_order]
            );
            if (!$q_order) {
                throw new Exception("Gagal update order DP: " . print_r(sqlsrv_errors(), true));
            }
            $msg = "Pembayaran DP diterima. Order sekarang masuk ke Booking Customer.";
        } 
        elseif ($tipe_pembayaran === 'Pelunasan') {
            // Kalau Pelunasan valid, update order jadi Lunas & Selesai (3)
            $q_order = sqlsrv_query($conn, 
                "UPDATE [Order] SET Status_Order = ? WHERE ID_Order = ?",
                [STATUS_ORDER_LUNAS, $id_order]
            );
            if (!$q_order) {
                throw new Exception("Gagal update order Pelunasan: " . print_r(sqlsrv_errors(), true));
            }
            $msg = "Pembayaran Pelunasan diterima. Order sekarang LUNAS dan customer dapat mengunduh hasil foto.";
        } else {
            throw new Exception("Tipe pembayaran tidak dikenal.");
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=" . urlencode($msg));

    } else { // aksi === 'tolak'
        // 1. Update pembayaran jadi DITOLAK (Bisa DP maupun Pelunasan)
        $q_update = sqlsrv_query($conn, 
            "UPDATE Pembayaran 
             SET Status_Pembayaran = ?, ID_Karyawan_Verifikator = ?, Modified_Date = GETDATE()
             WHERE ID_Pembayaran = ?",
            [STATUS_PEMBAYARAN_DITOLAK, $_SESSION['id_user'] ?? $_SESSION['id_karyawan'], $id_pembayaran]
        );
        if (!$q_update) {
            throw new Exception("Gagal menolak pembayaran: " . print_r(sqlsrv_errors(), true));
        }

        // Tentukan pesan penolakan berdasarkan tipe pembayaran
        if ($tipe_pembayaran === 'DP') {
            $msg = "Pembayaran DP ditolak. Customer harus upload ulang.";
        } else {
            $msg = "Pembayaran Pelunasan ditolak. Customer harus upload ulang bukti pelunasan.";
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status=sukses&msg=" . urlencode($msg));
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: list.php?status=error&msg=" . urlencode($e->getMessage()));
}
exit();
?>
?>