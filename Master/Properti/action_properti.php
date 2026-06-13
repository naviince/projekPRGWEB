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
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
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

// =====================================================
// AMBIL PARAMETER
// =====================================================
$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA PROPERTI (untuk nama & cek exist)
// =====================================================
$properti = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Properti, Foto_Properti, Status, Is_Deleted FROM Properti WHERE ID_Properti = ?", 
    [$id]
);

if (!$properti) {
    header("Location: list.php?status_sukses=error&message=Properti tidak ditemukan");
    exit();
}

if ($properti['Is_Deleted'] == 1) {
    header("Location: list.php?status_sukses=error&message=Properti sudah dihapus");
    exit();
}

// =====================================================
// 1. TOGGLE STATUS (Aktif / Nonaktif)
// =====================================================
if ($aksi == 'toggle_status') {
    $current_status = (int)($properti['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

    $sql = "UPDATE Properti SET 
        Status = ?, 
        Modified_By = ?, 
        Modified_Date = GETDATE() 
        WHERE ID_Properti = ?";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status properti");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $status_text = $new_status === 1 ? 'diaktifkan' : 'dinonaktifkan';
    header("Location: list.php?status_sukses=toggle_status&message=Properti berhasil {$status_text}");
    exit();
}

// =====================================================
// 2. HARD DELETE (Soft Delete - Is_Deleted = 1)
// =====================================================
if ($aksi == 'hard_delete') {
    // --- SOFT DELETE (Is_Deleted = 1) ---
    sqlsrv_begin_transaction($conn);

    try {
        // 1. Soft delete Properti (Is_Deleted = 1)
        $sql_soft = "UPDATE Properti SET 
            Is_Deleted = 1, 
            Status = 0, 
            Deleted_By = ?, 
            Deleted_Date = GETDATE() 
            WHERE ID_Properti = ?";
        $stmt_soft = sqlsrv_query($conn, $sql_soft, [$nama_admin, $id]);
        if ($stmt_soft === false) throw new Exception("Gagal soft delete properti");
        sqlsrv_free_stmt($stmt_soft);

        // 2. Hapus foto dari server (opsional, tapi direkomendasikan)
        $foto = $properti['Foto_Properti'] ?? '';
        if (!empty($foto) && $foto != 'default_properti.jpg' && $foto != 'default.jpg') {
            $foto_path = "../../assets/img/properti/" . $foto;
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=Properti berhasil dihapus");
        exit();

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// =====================================================
// AKSI TIDAK VALID
// =====================================================
header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();