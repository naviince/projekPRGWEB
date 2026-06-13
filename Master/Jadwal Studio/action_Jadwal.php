<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Ambil Nama Admin dari Session untuk Audit Trail
$admin_name = $_SESSION['nama'] ?? 'Admin';

// Ambil parameter aksi dari URL
$aksi = $_GET['aksi'] ?? '';

// =====================================================
// PROSES HAPUS DATA (SOFT DELETE)
// =====================================================
if ($aksi == 'delete') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        // Kita gunakan SOFT DELETE (Is_Deleted = 1) sesuai struktur tabel Anda
        $sql = "UPDATE Jadwal_Studio SET 
                Is_Deleted = 1, 
                Deleted_By = ?, 
                Deleted_Date = GETDATE() 
                WHERE ID_Jadwal = ?";
        
        $params = [$admin_name, $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            // REDIRECT kembali ke list dengan pesan sukses
            header("Location: list.php?status_sukses=hapus");
            exit();
        } else {
            // Jika gagal query
            die(print_r(sqlsrv_errors(), true));
        }
    } else {
        header("Location: list.php?status_sukses=error&message=ID tidak valid");
        exit();
    }
}

// =====================================================
// JIKA AKSES LANGSUNG TANPA AKSI
// =====================================================
header("Location: list.php");
exit();
?>