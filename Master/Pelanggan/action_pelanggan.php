<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

$aksi = $_GET['aksi'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID tidak valid!");
    exit();
}

// Helper: cek apakah pelanggan punya order
function hasOrder($conn, $id_pelanggan) {
    $sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Pelanggan = ? AND Status = 1";
    $stmt = sqlsrv_query($conn, $sql, [$id_pelanggan]);
    if ($stmt === false) return true; // Anggap ada transaksi jika query gagal untuk keamanan
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

// Helper: cek apakah pelanggan punya pembayaran
function hasPembayaran($conn, $id_pelanggan) {
    $sql = "SELECT COUNT(*) as total FROM Pembayaran p JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE o.ID_Pelanggan = ? AND p.Status = 1";
    $stmt = sqlsrv_query($conn, $sql, [$id_pelanggan]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

// Helper: dapatkan data pelanggan
function getPelanggan($conn, $id) {
    $sql = "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt === false) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

switch ($aksi) {
    // ============================================
    // SOFT DELETE: Mengarsipkan Pelanggan (Stored Procedure)
    // ============================================
    case 'soft_delete':
        // Menjalankan Stored Procedure sp_DeletePelanggan
        $sql = "{CALL sp_DeletePelanggan(?, ?)}";
        $stmt = sqlsrv_query($conn, $sql, [$id, $nama_admin]);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal mengarsipkan data!';
            header("Location: list.php?status_sukses=error&message=" . urlencode($msg));
            exit();
        }
        // Diarahkan ke tab arsip (dihapus) untuk melihat hasil tindakan
        header("Location: list.php?tab=dihapus&status_sukses=soft_delete");
        exit();

    // ============================================
    // RESTORE: Memulihkan Pelanggan dari Arsip
    // ============================================
    case 'restore':
        $sql = "UPDATE Pelanggan SET Is_Deleted = 0, Status = 1, Deleted_By = NULL, Deleted_Date = NULL, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
        $stmt = sqlsrv_query($conn, $sql, [$nama_admin, $id]);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal memulihkan data!';
            header("Location: list.php?tab=dihapus&status_sukses=error&message=" . urlencode($msg));
            exit();
        }
        // Diarahkan kembali ke daftar aktif
        header("Location: list.php?tab=aktif&status_sukses=restore");
        exit();

    // ============================================
    // HARD DELETE: Menghapus Fisik Data Permanen
    // ============================================
    case 'hard_delete':
        // Cek integritas relasi di database terlebih dahulu
        if (hasOrder($conn, $id)) {
            header("Location: list.php?tab=dihapus&status_sukses=error&message=" . urlencode("Tidak bisa hapus permanen! Pelanggan masih memiliki data Order/Booking."));
            exit();
        }
        if (hasPembayaran($conn, $id)) {
            header("Location: list.php?tab=dihapus&status_sukses=error&message=" . urlencode("Tidak bisa hapus permanen! Pelanggan masih memiliki data Pembayaran."));
            exit();
        }

        // Ambil data foto profil lama untuk dihapus dari server
        $pelanggan = getPelanggan($conn, $id);
        $foto_path = '';
        if ($pelanggan && !empty($pelanggan['Foto_Profil']) && $pelanggan['Foto_Profil'] != 'default.jpg') {
            $foto_path = "../../assets/img/pelanggan/" . $pelanggan['Foto_Profil'];
        }

        $sql = "DELETE FROM Pelanggan WHERE ID_Pelanggan = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal menghapus permanen!';
            header("Location: list.php?tab=dihapus&status_sukses=error&message=" . urlencode($msg));
            exit();
        }

        // Hapus file fisik gambar jika ada
        if (!empty($foto_path) && file_exists($foto_path)) {
            @unlink($foto_path);
        }

        header("Location: list.php?tab=dihapus&status_sukses=hard_delete");
        exit();

    // ============================================
    // TOGGLE STATUS: Mengubah Status Aktif (Stored Procedure)
    // ============================================
    case 'toggle_status':
        $new_status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
        
        // Menjalankan Stored Procedure sp_UpdateStatusPelanggan
        $sql = "{CALL sp_UpdateStatusPelanggan(?, ?, ?)}";
        $stmt = sqlsrv_query($conn, $sql, [$id, $new_status, $nama_admin]);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $msg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Gagal mengubah status!';
            header("Location: list.php?tab=aktif&status_sukses=error&message=" . urlencode($msg));
            exit();
        }
        header("Location: list.php?tab=aktif&status_sukses=toggle_status");
        exit();

    default:
        header("Location: list.php?status_sukses=error&message=Aksi tidak dikenal!");
        exit();
}
?>