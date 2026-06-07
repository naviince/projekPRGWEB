<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI AKSES: Hanya Admin yang boleh mengeksekusi aksi ini
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    exit("Akses Ditolak");
}

// 2. VALIDASI INPUT: Pastikan ID dan Tipe Aksi ada
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header("Location: list.php");
    exit();
}

$id = $_GET['id'];
$type = $_GET['type'];

// 3. VALIDASI KEAMANAN (SELF-DESTRUCT GUARD)
// Admin tidak boleh menghapus atau menonaktifkan dirinya sendiri agar sistem tidak terkunci
if ($id == $_SESSION['id_user']) {
    echo "<script>
            alert('Aksi Ditolak! Anda tidak diizinkan untuk menghapus atau menonaktifkan akun yang sedang digunakan demi keamanan sistem.'); 
            window.location='list.php';
          </script>";
    exit();
}

// --- EKSEKUSI LOGIKA ---

// AKSI A: SOFT DELETE (Toggle Status Active/Inactive)
if ($type == 'soft') {
    $currentStatus = $_GET['status'];
    $newStatus = ($currentStatus == 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE Users SET Status_User = ? WHERE ID_User = ?";
    $res = sqlsrv_query($conn, $sql, array($newStatus, $id));
    
    if ($res) {
        header("Location: list.php?msg=status_updated");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }

// AKSI B: HARD DELETE (Hapus Permanen dari Database)
} elseif ($type == 'hard') {
    
    // MENGGUNAKAN TRANSACTION (Akurat: Menjaga integritas data berelasi)
    sqlsrv_begin_transaction($conn);

    // 1. Hapus Profil di tabel Karyawan (Child)
    $sql_karyawan = "DELETE FROM Karyawan WHERE ID_User = ?";
    $stmt1 = sqlsrv_query($conn, $sql_karyawan, array($id));

    // 2. Hapus Akun di tabel Users (Parent)
    $sql_user = "DELETE FROM Users WHERE ID_User = ?";
    $stmt2 = sqlsrv_query($conn, $sql_user, array($id));

    // 4. VALIDASI TRANSAKSI: Cek apakah ada riwayat transaksi (Foreign Key Check)
    if ($stmt1 && $stmt2) {
        // Jika keduanya sukses dihapus
        sqlsrv_commit($conn);
        header("Location: list.php?msg=deleted");
    } else {
        // Jika gagal (biasanya karena karyawan sudah punya riwayat sesi foto/transaksi)
        sqlsrv_rollback($conn);
        echo "<script>
                alert('Gagal Hapus Permanen! Karyawan ini sudah memiliki riwayat tugas atau transaksi dalam sistem. Gunakan fitur Nonaktifkan (Soft Delete) saja.'); 
                window.location='list.php';
              </script>";
    }
}
?>