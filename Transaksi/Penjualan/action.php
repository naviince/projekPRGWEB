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
    header("Location: list.php?status_sukses=error&message=" . urlencode("ID tidak valid"));
    exit();
}

// =====================================================
// CEK DATA PENJUALAN EXIST (termasuk yang sudah soft-delete, supaya aksi
// 'restore' tetap bisa menemukan datanya untuk validasi lanjutan di SP)
// =====================================================
$cek = sqlsrv_query($conn, "SELECT ID_Penjualan FROM Penjualan WHERE ID_Penjualan = ?", [$id]);
$data = $cek ? sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) : null;

if (!$data) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("Data penjualan tidak ditemukan"));
    exit();
}

// =====================================================
// PROSES AKSI -- semua aksi sekarang lewat Stored Procedure yang
// memvalidasi state (Status/Status_Penjualan) DAN menyesuaikan stok
// barang secara konsisten dalam 1 transaksi (lihat SpotLight.sql:
// sp_TandaiSelesaiPenjualan, sp_SoftDeletePenjualan, sp_RestorePenjualan).
// Validasi state yang sebelumnya ada di PHP sudah dipindah + diperkuat
// di level database, supaya tidak bisa dilewati lewat jalur lain.
// =====================================================
switch ($aksi) {

    // -------------------------------------------------
    // UPDATE STATUS: Proses (0) -> Selesai (1)
    // -------------------------------------------------
    case 'update_status':
        $stmt = sqlsrv_query($conn, "{CALL sp_TandaiSelesaiPenjualan(?, ?, ?)}", [$id, $id_admin, $username_admin]);

        if ($stmt) {
            header("Location: list.php?status_sukses=update_status");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = $errors[0]['message'] ?? 'Gagal update status';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();

    // -------------------------------------------------
    // SOFT DELETE: Status = 0 (+ kembalikan stok barang)
    // -------------------------------------------------
    case 'soft_delete':
        $stmt = sqlsrv_query($conn, "{CALL sp_SoftDeletePenjualan(?, ?)}", [$id, $username_admin]);

        if ($stmt) {
            header("Location: list.php?status_sukses=soft_delete");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = $errors[0]['message'] ?? 'Gagal menghapus data';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();

    // -------------------------------------------------
    // RESTORE: Status = 1 (+ validasi & potong ulang stok barang)
    // -------------------------------------------------
    case 'restore':
        $stmt = sqlsrv_query($conn, "{CALL sp_RestorePenjualan(?, ?)}", [$id, $username_admin]);

        if ($stmt) {
            header("Location: list.php?status_sukses=restore");
        } else {
            $errors = sqlsrv_errors();
            $err_msg = $errors[0]['message'] ?? 'Gagal memulihkan data';
            header("Location: list.php?status_sukses=error&message=" . urlencode($err_msg));
        }
        exit();

    // -------------------------------------------------
    // DEFAULT: Aksi tidak dikenal
    // -------------------------------------------------
    default:
        header("Location: list.php?status_sukses=error&message=" . urlencode("Aksi tidak dikenal"));
        exit();
}
?>