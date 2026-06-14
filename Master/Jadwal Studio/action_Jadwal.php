<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

// =====================================================
// AMBIL PARAMETER AKSI
// =====================================================
$aksi = $_GET['aksi'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID jadwal tidak valid");
    exit();
}

// =====================================================
// 1. TOGGLE STATUS (Soft Delete Toggle)
// =====================================================
if ($aksi == 'toggle_status') {
    // Ambil status sekarang
    $data = safe_sqlsrv_fetch($conn, 
        "SELECT Status FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 0", 
        [$id]
    );

    if (!$data) {
        header("Location: list.php?status_sukses=error&message=Data jadwal tidak ditemukan");
        exit();
    }

    $current_status = (int)($data['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;
    $status_text = $new_status === 1 ? 'Aktif' : 'Nonaktif';

    $sql = "UPDATE Jadwal_Studio SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=toggle_status&message=Status jadwal diubah ke " . $status_text);
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status jadwal");
        exit();
    }
}

// =====================================================
// 2. SOFT DELETE (Is_Deleted = 1)
// =====================================================
if ($aksi == 'soft_delete') {
    // Cek relasi: ada order aktif?
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) FROM [Order] WHERE ID_Jadwal = ? AND Status = 1 AND Status_Order <> 4",
        [$id]
    );

    if ($cek_order > 0) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena masih memiliki " . $cek_order . " order aktif");
        exit();
    }

    // Cek relasi: ada sesi foto yang belum selesai?
    $cek_sesi = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) FROM Sesi_Foto WHERE ID_Order IN (SELECT ID_Order FROM [Order] WHERE ID_Jadwal = ?) AND Status = 1 AND Status_Sesi <> 2",
        [$id]
    );

    if ($cek_sesi > 0) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena masih memiliki sesi foto yang aktif");
        exit();
    }

    // Soft delete: Is_Deleted = 1, Status = 0
    $sql = "UPDATE Jadwal_Studio SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Jadwal = ?";
    $params = [$nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=soft_delete&message=Jadwal berhasil dihapus (soft delete)");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal menghapus jadwal");
        exit();
    }
}

// =====================================================
// 3. HARD DELETE (Hanya kalau sudah soft delete dulu)
// =====================================================
if ($aksi == 'hard_delete') {
    // Cek apakah sudah soft delete dulu
    $data = safe_sqlsrv_fetch($conn,
        "SELECT Is_Deleted FROM Jadwal_Studio WHERE ID_Jadwal = ?",
        [$id]
    );

    if (!$data || $data['Is_Deleted'] != 1) {
        header("Location: list.php?status_sukses=error&message=Hard delete hanya bisa dilakukan setelah soft delete");
        exit();
    }

    // Hard delete permanen
    $sql = "DELETE FROM Jadwal_Studio WHERE ID_Jadwal = ?";
    $params = [$id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=hard_delete&message=Jadwal berhasil dihapus permanen");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal hard delete jadwal");
        exit();
    }
}

// =====================================================
// JIKA AKSES LANGSUNG TANPA AKSI VALID
// =====================================================
header("Location: list.php");
exit();
?>