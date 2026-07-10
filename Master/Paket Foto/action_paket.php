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

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID+tidak+valid");
    exit();
}

// =====================================================
// HELPER FUNCTIONS - UNTUK MENJAGA INTEGRITAS RELASI
// =====================================================

// Cek apakah paket masih memiliki transaksi order di sistem
function hasOrder($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ? AND Status = 1";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true; // Anggap ada order jika query gagal demi keamanan
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

// Cek apakah paket terikat dengan ruangan aktif (Mencegah Database FK Constraint Violation)
function hasRuangan($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total FROM Ruangan WHERE ID_Paket = ? AND Is_Deleted = 0";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

// Cek apakah paket terikat dengan jadwal studio aktif melalui Ruangan
function hasJadwal($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total 
            FROM Jadwal_Studio js
            JOIN Ruangan r ON js.ID_Ruangan = r.ID_Ruangan
            WHERE r.ID_Paket = ? AND js.Is_Deleted = 0";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

// Mengambil nama file gambar paket sebelum dihapus dari server
function getFotoPaket($conn, $id) {
    $sql = "SELECT Foto_Paket FROM Paket_Foto WHERE ID_Paket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt === false) return 'default_paket.jpg';
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row['Foto_Paket'] ?? 'default_paket.jpg';
}

// =====================================================
// PROSES EKSEKUSI AKSI
// =====================================================
switch ($aksi) {

    // -------------------------------------------------
    // TOGGLE STATUS: Mengubah Status Aktif / Nonaktif
    // -------------------------------------------------
    case 'toggle_status':
        $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
        if ($status !== 0 && $status !== 1) {
            $status = 1;
        }

        $sql = "UPDATE Paket_Foto SET 
                Status = ?, 
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Paket = ?";

        $stmt = sqlsrv_query($conn, $sql, [$status, $nama_admin, $id]);

        if ($stmt) {
            header("Location: list.php?status_sukses=toggle_status");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+mengubah+status+paket");
        }
        exit();
        break;

    // -------------------------------------------------
    // SOFT DELETE: Mengarsipkan Paket (Stored Procedure)
    // -------------------------------------------------
    case 'soft_delete':
        // Cek data apakah sudah diarsipkan sebelumnya
        $cek_sql = "SELECT Is_Deleted FROM Paket_Foto WHERE ID_Paket = ?";
        $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);
        $data = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

        if ($data && $data['Is_Deleted'] == 1) {
            header("Location: list.php?status_sukses=error&message=Paket+sudah+berada+dalam+arsip!");
            exit();
        }

        // Jalankan Stored Procedure sp_DeletePaketFoto
        $sql = "{CALL sp_DeletePaketFoto(?, ?)}";
        $stmt = sqlsrv_query($conn, $sql, [$id, $nama_admin]);

        if ($stmt) {
            header("Location: list.php?status_sukses=soft_delete");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+mengarsipkan+paket");
        }
        exit();
        break;

    // -------------------------------------------------
    // RESTORE: Memulihkan Paket dari Arsip
    // -------------------------------------------------
    case 'restore':
        $cek_sql = "SELECT Is_Deleted FROM Paket_Foto WHERE ID_Paket = ?";
        $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);
        $data = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

        if (!$data || $data['Is_Deleted'] == 0) {
            header("Location: list.php?status_sukses=error&message=Paket+tidak+sedang+diarsipkan!");
            exit();
        }

        // Mengembalikan Is_Deleted = 0
        $sql = "UPDATE Paket_Foto SET 
                Is_Deleted = 0, 
                Deleted_By = NULL, 
                Deleted_Date = NULL,
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Paket = ?";

        $stmt = sqlsrv_query($conn, $sql, [$nama_admin, $id]);

        if ($stmt) {
            header("Location: list.php?status_sukses=restore");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+memulihkan+paket");
        }
        exit();
        break;

    // -------------------------------------------------
    // HARD DELETE: Menghapus Fisik Data Permanen
    // -------------------------------------------------
    case 'hard_delete':
        // Cek dependensi relasi agar database tidak crash
        if (hasOrder($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+memiliki+riwayat+transaksi+booking+pelanggan.");
            exit();
        }

        if (hasRuangan($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+terikat+dengan+Ruangan+Studio.");
            exit();
        }

        if (hasJadwal($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+terikat+dengan+Jadwal+Studio.");
            exit();
        }

        // Ambil nama file foto lama untuk dihapus dari server
        $foto_name = getFotoPaket($conn, $id);
        if ($foto_name != 'default_paket.jpg') {
            $foto_path = "../../assets/img/paket/" . $foto_name;
            if (file_exists($foto_path)) {
                @unlink($foto_path);
            }
        }

        // Eksekusi Hard Delete secara permanen
        $sql_delete = "DELETE FROM Paket_Foto WHERE ID_Paket = ?";
        $stmt_delete = sqlsrv_query($conn, $sql_delete, [$id]);

        if ($stmt_delete) {
            header("Location: list.php?status_sukses=hard_delete");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+menghapus+paket+permanen");
        }
        exit();
        break;

    default:
        header("Location: list.php?status_sukses=error&message=Aksi+tidak+valid");
        exit();
        break;
}
?>