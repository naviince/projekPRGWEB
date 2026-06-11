<?php
ob_start();
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// --- AMBIL PARAMETER ---
$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// --- CEK DATA EXIST ---
$sql_cek = "SELECT * FROM Paket_Foto WHERE ID_Paket = ? AND Is_Deleted = 0";
$stmt_cek = sqlsrv_query($conn, $sql_cek, [$id]);
$data = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php?status_sukses=error&message=Paket tidak ditemukan");
    exit();
}

// =====================================================
// PROSES TOGGLE STATUS (Soft Delete)
// Status: 1 = Aktif, 0 = Nonaktif (INT, bukan string)
// =====================================================
if ($aksi == 'toggle_status') {
    $new_status = $data['Status'] == 1 ? 0 : 1;
    $status_text = $new_status == 1 ? 'diaktifkan' : 'dinonaktifkan';

    sqlsrv_query($conn, "BEGIN TRAN");

    $sql = "UPDATE Paket_Foto SET 
        Status = ?,
        Modified_By = ?,
        Modified_Date = GETDATE()
        WHERE ID_Paket = ?";

    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_query($conn, "COMMIT");
        header("Location: list.php?status_sukses=toggle_status&message=Paket berhasil " . $status_text);
        exit();
    } else {
        sqlsrv_query($conn, "ROLLBACK");
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status paket");
        exit();
    }
}

// =====================================================
// PROSES HARD DELETE (Hapus Permanen)
// Cek relasi dulu, kalau ada → error (pakai soft delete)
// Kalau tidak ada → Is_Deleted = 1 (soft delete logis)
// =====================================================
if ($aksi == 'hard_delete') {
    $relasi_found = false;
    $relasi_msg = "";

    // Cek relasi di tabel Order (booking)
    $sql_relasi1 = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ?";
    $stmt_relasi1 = sqlsrv_query($conn, $sql_relasi1, [$id]);
    if ($stmt_relasi1) {
        $relasi1 = sqlsrv_fetch_array($stmt_relasi1, SQLSRV_FETCH_ASSOC);
        if ($relasi1 && $relasi1['total'] > 0) {
            $relasi_found = true;
            $relasi_msg = "Paket sudah pernah dipesan " . $relasi1['total'] . " kali. Gunakan fitur Nonaktifkan saja agar riwayat transaksi tetap aman.";
        }
    }

    // Cek relasi di tabel Pembayaran
    if (!$relasi_found) {
        $sql_relasi2 = "SELECT COUNT(*) as total FROM Pembayaran WHERE ID_Paket = ?";
        $stmt_relasi2 = sqlsrv_query($conn, $sql_relasi2, [$id]);
        if ($stmt_relasi2) {
            $relasi2 = sqlsrv_fetch_array($stmt_relasi2, SQLSRV_FETCH_ASSOC);
            if ($relasi2 && $relasi2['total'] > 0) {
                $relasi_found = true;
                $relasi_msg = "Paket sudah ada " . $relasi2['total'] . " pembayaran. Gunakan fitur Nonaktifkan saja.";
            }
        }
    }

    // Cek relasi di tabel Sesi_Foto
    if (!$relasi_found) {
        $sql_relasi3 = "SELECT COUNT(*) as total FROM Sesi_Foto WHERE ID_Paket = ?";
        $stmt_relasi3 = sqlsrv_query($conn, $sql_relasi3, [$id]);
        if ($stmt_relasi3) {
            $relasi3 = sqlsrv_fetch_array($stmt_relasi3, SQLSRV_FETCH_ASSOC);
            if ($relasi3 && $relasi3['total'] > 0) {
                $relasi_found = true;
                $relasi_msg = "Paket sudah ada " . $relasi3['total'] . " sesi foto. Gunakan fitur Nonaktifkan saja.";
            }
        }
    }

    // Cek relasi di tabel Penjualan
    if (!$relasi_found) {
        $sql_relasi4 = "SELECT COUNT(*) as total FROM Penjualan WHERE ID_Paket = ?";
        $stmt_relasi4 = sqlsrv_query($conn, $sql_relasi4, [$id]);
        if ($stmt_relasi4) {
            $relasi4 = sqlsrv_fetch_array($stmt_relasi4, SQLSRV_FETCH_ASSOC);
            if ($relasi4 && $relasi4['total'] > 0) {
                $relasi_found = true;
                $relasi_msg = "Paket sudah ada " . $relasi4['total'] . " penjualan. Gunakan fitur Nonaktifkan saja.";
            }
        }
    }

    // Kalau ada relasi → error, pakai soft delete
    if ($relasi_found) {
        header("Location: list.php?status_sukses=error&message=" . urlencode($relasi_msg));
        exit();
    }

    // Kalau tidak ada relasi → hapus foto + soft delete (Is_Deleted = 1)
    $foto_paket = $data['Foto_Paket'] ?? 'default_paket.jpg';
    $upload_dir = "../../assets/img/paket/";

    // Hapus foto dari server (jika bukan default)
    if ($foto_paket != 'default_paket.jpg' && file_exists($upload_dir . $foto_paket)) {
        unlink($upload_dir . $foto_paket);
    }

    // Soft delete: update Is_Deleted = 1 (lebih aman daripada DELETE permanen)
    sqlsrv_query($conn, "BEGIN TRAN");

    $sql = "UPDATE Paket_Foto SET 
        Is_Deleted = 1,
        Status = 0,
        Deleted_By = ?,
        Deleted_Date = GETDATE()
        WHERE ID_Paket = ?";

    $params = [$nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_query($conn, "COMMIT");
        header("Location: list.php?status_sukses=hard_delete&message=Paket berhasil dihapus permanen");
        exit();
    } else {
        sqlsrv_query($conn, "ROLLBACK");
        header("Location: list.php?status_sukses=error&message=Gagal menghapus paket");
        exit();
    }
}

// Kalau aksi tidak dikenal
header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();
?>