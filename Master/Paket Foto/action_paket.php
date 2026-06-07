<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI AKSES (Validasi Matang)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { 
    exit("Akses Ditolak"); 
}

// Cek keberadaan parameter
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$type = $_GET['type'];
$id   = $_GET['id'];

// --- EKSEKUSI LOGIKA ---

// LOGIKA 1: SOFT DELETE (Toggle Status Aktif/Nonaktif)
if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Aktif') ? 'Nonaktif' : 'Aktif';
    
    $sql = "UPDATE Paket_Foto SET Status = ? WHERE ID_Paket = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }

// LOGIKA 2: HARD DELETE (Hapus Permanen)
} elseif ($type == 'hard') {
    
    // VALIDASI AKURAT: Cek relasi ke tabel [Order] agar data tidak pincang
    // Gunakan [Order] dengan kurung siku karena 'Order' adalah kata kunci SQL
    $sql_cek = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($id));

    // Validasi jika tabel [Order] belum dibuat atau query gagal
    if ($stmt_cek === false) {
        // Jika tabel [Order] belum ada (karena masih tahap Master), langsung izinkan hapus
        $has_transaction = 0;
    } else {
        $check = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);
        $has_transaction = $check['total'];
    }

    if ($has_transaction > 0) {
        // JIKA ADA TRANSAKSI: Dilarang Hapus (Logika Bisnis Sempurna)
        echo "<script>
                alert('Gagal Hapus Permanen! Paket ini sudah pernah dipesan oleh pelanggan. Gunakan fitur NONAKTIFKAN (Soft Delete) agar riwayat transaksi tetap aman.'); 
                window.location='list.php';
              </script>";
    } else {
        // JIKA BERSIH: Proses Hapus File & Data
        
        // A. Ambil nama foto untuk dihapus dari folder (Efisien Penyimpanan)
        $q_foto = sqlsrv_query($conn, "SELECT Foto_Paket FROM Paket_Foto WHERE ID_Paket = ?", array($id));
        $f = sqlsrv_fetch_array($q_foto, SQLSRV_FETCH_ASSOC);
        
        if($f && $f['Foto_Paket'] != 'default_paket.jpg') {
            $path = "../../assets/img/paket/" . $f['Foto_Paket'];
            if (file_exists($path)) { unlink($path); }
        }

        // B. Hapus baris dari database
        $sql = "DELETE FROM Paket_Foto WHERE ID_Paket = ?";
        $res = sqlsrv_query($conn, $sql, array($id));

        if ($res) {
            header("Location: list.php?msg=deleted");
        } else {
            die(print_r(sqlsrv_errors(), true));
        }
    }
}