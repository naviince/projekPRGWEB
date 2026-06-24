<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$username_admin = $_SESSION['username'] ?? 'admin';

// =====================================================
// AMBIL PARAMETER
// =====================================================
$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID tidak valid");
    exit();
}

// =====================================================
// CEK DATA PENJUALAN EXIST
// =====================================================
$cek = sqlsrv_query($conn, "SELECT * FROM Penjualan WHERE ID_Penjualan = ?", [$id]);
$data = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php?status_sukses=error&message=Data penjualan tidak ditemukan");
    exit();
}

// =====================================================
// PROSES AKSI
// =====================================================
switch ($aksi) {

    // -------------------------------------------------
    // UPDATE STATUS: Proses (0) → Selesai (1)
    // -------------------------------------------------
    case 'update_status':
        // Validasi: hanya bisa update kalau masih Proses (0)
        if ($data['Status_Penjualan'] != 0) {
            header("Location: list.php?status_sukses=error&message=Status sudah selesai, tidak bisa diubah");
            exit();
        }

        // Update status + assign admin + modified
        $sql = "UPDATE Penjualan 
                SET Status_Penjualan = 1, 
                    ID_Karyawan_Admin = ?, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE() 
                WHERE ID_Penjualan = ?";
        $params = [$id_admin, $username_admin, $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: list.php?status_sukses=update_status");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal update status';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();
        break;

    // -------------------------------------------------
    // SOFT DELETE: Status = 0
    // -------------------------------------------------
    case 'soft_delete':
        // Validasi: tidak bisa hapus kalau sudah Selesai (1)
        if ($data['Status_Penjualan'] == 1) {
            header("Location: list.php?status_sukses=error&message=Penjualan sudah selesai, tidak bisa dihapus");
            exit();
        }

        $sql = "UPDATE Penjualan 
                SET Status = 0, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE() 
                WHERE ID_Penjualan = ?";
        $params = [$username_admin, $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: list.php?status_sukses=soft_delete");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal menghapus data';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();
        break;

    // -------------------------------------------------
    // RESTORE: Status = 1 (kembalikan dari soft delete)
    // -------------------------------------------------
    case 'restore':
        $sql = "UPDATE Penjualan 
                SET Status = 1, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE() 
                WHERE ID_Penjualan = ?";
        $params = [$username_admin, $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: list.php?status_sukses=restore");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal memulihkan data';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();
        break;

    // -------------------------------------------------
    // DEFAULT: Aksi tidak dikenal
    // -------------------------------------------------
    default:
        header("Location: list.php?status_sukses=error&message=Aksi tidak dikenal");
        exit();
}
?>