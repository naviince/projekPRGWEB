<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

$tema = safe_sqlsrv_fetch($conn, "SELECT Nama_Tema, Status, Is_Deleted, Foto_Tema FROM Tema_Foto WHERE ID_Tema = ?", [$id]);

if (!$tema || $tema['Is_Deleted'] == 1) {
    header("Location: list.php?status_sukses=error&message=Tema tidak ditemukan");
    exit();
}

// 1. TOGGLE STATUS
if ($aksi == 'toggle_status') {
    $new_status = ($tema['Status'] == 1) ? 0 : 1;
    $sql = "UPDATE Tema_Foto SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Tema = ?";
    $stmt = sqlsrv_query($conn, $sql, [$new_status, $nama_admin, $id]);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status");
        exit();
    }
    header("Location: list.php?status_sukses=toggle_status&message=Status tema berhasil diperbarui");
    exit();
}

// 2. HARD DELETE (Is_Deleted = 1)
if ($aksi == 'hard_delete') {
    // Cek apakah ada order aktif yang menggunakan tema ini
    $sql_cek = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, [$id]);
    $res_cek = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($res_cek['total'] > 0) {
        header("Location: list.php?status_sukses=error&message=Tema tidak bisa dihapus karena masih digunakan dalam order aktif.");
        exit();
    }

    sqlsrv_begin_transaction($conn);
    try {
        // Hapus relasi di Ruangan_Tema
        $sql_relasi = "DELETE FROM Ruangan_Tema WHERE ID_Tema = ?";
        sqlsrv_query($conn, $sql_relasi, [$id]);

        // Soft delete Tema_Foto
        $sql_del = "UPDATE Tema_Foto SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Tema = ?";
        sqlsrv_query($conn, $sql_del, [$nama_admin, $id]);

        // Hapus foto jika ada
        if (!empty($tema['Foto_Tema']) && $tema['Foto_Tema'] != 'default_tema.jpg') {
            @unlink("../../assets/img/tema/" . $tema['Foto_Tema']);
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=Tema berhasil dihapus");
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=Gagal menghapus tema");
    }
    exit();
}