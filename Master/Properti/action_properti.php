<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row;
}

function safe_sqlsrv_query($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    return $stmt; // bisa false
}

// =====================================================
// AMBIL DATA ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch(
    $conn,
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0",
    [$id_admin]
);
$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// Ambil data properti (untuk cek exist + nama file)
$properti = safe_sqlsrv_fetch(
    $conn,
    "SELECT ID_Properti, Foto_Properti, Status, Is_Deleted FROM Properti WHERE ID_Properti = ?",
    [$id]
);

if (!$properti) {
    header("Location: list.php?status_sukses=error&message=Properti tidak ditemukan");
    exit();
}

if ((int)$properti['Is_Deleted'] === 1) {
    header("Location: list.php?status_sukses=error&message=Properti sudah dihapus");
    exit();
}

// =====================================================
// 1) TOGGLE STATUS
// =====================================================
if ($aksi === 'toggle_status') {
    $status = isset($_GET['status']) ? (int)$_GET['status'] : (int)($properti['Status'] ?? 1);
    $status = ($status === 1) ? 1 : 0;

    $sql = "UPDATE Properti SET 
            Status = ?,
            Modified_By = ?,
            Modified_Date = GETDATE()
            WHERE ID_Properti = ?";

    $res = safe_sqlsrv_query($conn, $sql, [$status, $nama_admin, $id]);
    if ($res === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status properti");
        exit();
    }

    header("Location: list.php?status_sukses=toggle_status");
    exit();
}

// =====================================================
// 2) HARD DELETE (soft delete Is_Deleted=1)
// =====================================================
if ($aksi === 'hard_delete') {
    $foto = $properti['Foto_Properti'] ?? '';

    sqlsrv_begin_transaction($conn);
    try {
        $sql = "UPDATE Properti SET 
                Is_Deleted = 1,
                Status = 0,
                Deleted_By = ?,
                Deleted_Date = GETDATE()
                WHERE ID_Properti = ?";

        $res = safe_sqlsrv_query($conn, $sql, [$nama_admin, $id]);
        if ($res === false) {
            throw new Exception('Gagal menghapus properti');
        }

        sqlsrv_commit($conn);

        // hapus file dari server (jika bukan default)
        if (!empty($foto) && $foto !== 'default_properti.jpg') {
            $path_foto = "../../assets/img/properti/" . $foto;
            if (file_exists($path_foto)) {
                unlink($path_foto);
            }
        }

        header("Location: list.php?status_sukses=hard_delete");
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// AKSI TIDAK VALID
header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();
?>

