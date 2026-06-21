<?php
ob_start();
session_start();
include '../../koneksi.php';

define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);
define('STATUS_JADWAL_MAINTENANCE', 2);
define('STATUS_DATA_AKTIF', 1);

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_fetch] SQL Error: " . json_encode(sqlsrv_errors()));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_count] SQL Error: " . json_encode(sqlsrv_errors()));
        return 0;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

function safe_sqlsrv_execute($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_execute] SQL Error: " . json_encode(sqlsrv_errors()));
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("Parameter tidak valid"));
    exit();
}

$jadwal = safe_sqlsrv_fetch($conn,
    "SELECT j.*, r.Nama_Ruangan FROM Jadwal_Studio j INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan WHERE j.ID_Jadwal = ?",
    [$id]
);

if (!$jadwal) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal tidak ditemukan"));
    exit();
}

// 1. TOGGLE STATUS (Tersedia <-> Maintenance)
if ($aksi == 'toggle_status') {
    $current_status = (int)($jadwal['Status_Jadwal'] ?? STATUS_JADWAL_TERSEDIA);
    if ($current_status == STATUS_JADWAL_BOOKED) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal sedang booked, tidak bisa diubah status. Selesaikan order terlebih dahulu."));
        exit();
    }
    $new_status = ($current_status == STATUS_JADWAL_TERSEDIA) ? STATUS_JADWAL_MAINTENANCE : STATUS_JADWAL_TERSEDIA;
    $sql = "UPDATE Jadwal_Studio SET Status_Jadwal = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
    $params = [$new_status, $nama_admin, $id];
    if (!safe_sqlsrv_execute($conn, $sql, $params)) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Gagal mengubah status jadwal"));
        exit();
    }
    $status_label = ($new_status == STATUS_JADWAL_TERSEDIA) ? 'tersedia' : 'maintenance';
    header("Location: list.php?status_sukses=toggle_status&message=" . urlencode("Jadwal berhasil diubah menjadi {$status_label}"));
    exit();
}

// 2. SOFT DELETE
if ($aksi == 'soft_delete') {
    if ($jadwal['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal sudah dihapus sebelumnya"));
        exit();
    }
    if ((int)$jadwal['Status_Jadwal'] == STATUS_JADWAL_BOOKED) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal sedang booked, tidak bisa dihapus. Selesaikan order terlebih dahulu."));
        exit();
    }
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] WHERE ID_Jadwal = ? AND Status = ? AND Status_Order NOT IN (3, 4)",
        [$id, STATUS_DATA_AKTIF]
    );
    if ($cek_order > 0) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal masih terhubung dengan {$cek_order} order aktif"));
        exit();
    }
    $sql = "UPDATE Jadwal_Studio SET Is_Deleted = 1, Status = 0, Status_Jadwal = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Jadwal = ?";
    if (!safe_sqlsrv_execute($conn, $sql, [$nama_admin, $id])) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Gagal menghapus jadwal"));
        exit();
    }
    header("Location: list.php?status_sukses=soft_delete&message=" . urlencode("Jadwal berhasil dihapus (bisa dikembalikan)"));
    exit();
}

// 3. RESTORE
if ($aksi == 'restore') {
    if ($jadwal['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal masih aktif, tidak perlu di-restore"));
        exit();
    }
    $sql = "UPDATE Jadwal_Studio SET Is_Deleted = 0, Status = 1, Status_Jadwal = 0, Modified_By = ?, Modified_Date = GETDATE(), Deleted_By = NULL, Deleted_Date = NULL WHERE ID_Jadwal = ?";
    if (!safe_sqlsrv_execute($conn, $sql, [$nama_admin, $id])) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Gagal mengembalikan jadwal"));
        exit();
    }
    header("Location: list.php?status_sukses=restore&message=" . urlencode("Jadwal berhasil dikembalikan"));
    exit();
}

// 4. HARD DELETE
if ($aksi == 'hard_delete') {
    if ($jadwal['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal harus dihapus terlebih dahulu sebelum dihapus permanen"));
        exit();
    }
    $cek_order = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM [Order] WHERE ID_Jadwal = ? AND Status = ?", [$id, STATUS_DATA_AKTIF]);
    if ($cek_order > 0) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Masih ada {$cek_order} order terkait, tidak bisa hapus permanen"));
        exit();
    }
    $begin_result = sqlsrv_begin_transaction($conn);
    if ($begin_result === false) {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Gagal memulai transaksi"));
        exit();
    }
    try {
        $sql = "DELETE FROM Jadwal_Studio WHERE ID_Jadwal = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt === false) throw new Exception("Gagal hapus jadwal: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt);
        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=" . urlencode("Jadwal berhasil dihapus permanen"));
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: list.php?status_sukses=error&message=" . urlencode("Aksi tidak valid"));
exit();
?>