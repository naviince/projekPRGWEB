<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI AKSES ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// --- VALIDASI PARAMETER ---
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['aksi']) || empty($_GET['aksi'])) {
    header("Location: list.php?status_sukses=error&message=parameter_tidak_valid");
    exit();
}

$id_pelanggan = (int)$_GET['id'];
$aksi = $_GET['aksi'];

// --- CEK DATA PELANGGAN EXIST ---
$cek_sql = "SELECT ID_Pelanggan, Nama_Pelanggan, Status, Is_Deleted FROM Pelanggan WHERE ID_Pelanggan = ?";
$cek_stmt = sqlsrv_query($conn, $cek_sql, [$id_pelanggan]);
$pelanggan = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

if (!$pelanggan) {
    header("Location: list.php?status_sukses=error&message=data_tidak_ditemukan");
    exit();
}

$nama_pelanggan = $pelanggan['Nama_Pelanggan'];

// ============================================================
// SOFT DELETE (Toggle Status: Aktif <-> Nonaktif)
// ============================================================
if ($aksi == 'soft_delete') {

    // Toggle status: 1 (Aktif) -> 0 (Nonaktif), 0 -> 1
    $new_status = ($pelanggan['Status'] == 1) ? 0 : 1;
    $status_text = ($new_status == 1) ? 'Aktif' : 'Nonaktif';

    sqlsrv_begin_transaction($conn);

    try {
        // Update Status, Modified_By, Modified_Date
        $sql = "UPDATE Pelanggan SET 
                Status = ?, 
                Modified_By = ?, 
                Modified_Date = GETDATE() 
                WHERE ID_Pelanggan = ?";

        $stmt = sqlsrv_query($conn, $sql, [$new_status, $nama_admin, $id_pelanggan]);

        if ($stmt) {
            sqlsrv_commit($conn);

            // Redirect dengan pesan sukses
            header("Location: list.php?status_sukses=soft_delete&status=" . $status_text . "&nama=" . urlencode($nama_pelanggan));
            exit();
        } else {
            throw new Exception("Gagal update status");
        }

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// ============================================================
// HARD DELETE (Hapus Permanen)
// ============================================================
elseif ($aksi == 'hard_delete') {

    // --- CEK RELASI DI TABEL LAIN ---
    // Cek apakah pelanggan punya booking/order
    $cek_booking_sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Pelanggan = ?";
    $cek_booking_stmt = sqlsrv_query($conn, $cek_booking_sql, [$id_pelanggan]);
    $booking = sqlsrv_fetch_array($cek_booking_stmt, SQLSRV_FETCH_ASSOC);

    // Cek apakah pelanggan punya transaksi pembayaran
    $cek_pembayaran_sql = "SELECT COUNT(*) as total FROM Pembayaran WHERE ID_Pelanggan = ?";
    $cek_pembayaran_stmt = sqlsrv_query($conn, $cek_pembayaran_sql, [$id_pelanggan]);
    $pembayaran = sqlsrv_fetch_array($cek_pembayaran_stmt, SQLSRV_FETCH_ASSOC);

    // Cek apakah pelanggan punya riwayat sesi foto
    $cek_sesi_sql = "SELECT COUNT(*) as total FROM Sesi_Foto WHERE ID_Pelanggan = ?";
    $cek_sesi_stmt = sqlsrv_query($conn, $cek_sesi_sql, [$id_pelanggan]);
    $sesi = sqlsrv_fetch_array($cek_sesi_stmt, SQLSRV_FETCH_ASSOC);

    // Cek apakah pelanggan punya penjualan barang cetak
    $cek_penjualan_sql = "SELECT COUNT(*) as total FROM Penjualan WHERE ID_Pelanggan = ?";
    $cek_penjualan_stmt = sqlsrv_query($conn, $cek_penjualan_sql, [$id_pelanggan]);
    $penjualan = sqlsrv_fetch_array($cek_penjualan_stmt, SQLSRV_FETCH_ASSOC);

    $total_relasi = ($booking['total'] ?? 0) + ($pembayaran['total'] ?? 0) + 
                    ($sesi['total'] ?? 0) + ($penjualan['total'] ?? 0);

    // --- JIKA PUNYA RELASI, TIDAK BOLEH HARD DELETE ---
    if ($total_relasi > 0) {
        $detail_relasi = [];
        if ($booking['total'] > 0) $detail_relasi[] = $booking['total'] . " booking";
        if ($pembayaran['total'] > 0) $detail_relasi[] = $pembayaran['total'] . " pembayaran";
        if ($sesi['total'] > 0) $detail_relasi[] = $sesi['total'] . " sesi foto";
        if ($penjualan['total'] > 0) $detail_relasi[] = $penjualan['total'] . " penjualan";

        $pesan_relasi = implode(", ", $detail_relasi);

        header("Location: list.php?status_sukses=error&message=" . urlencode(
            "Tidak dapat hapus permanen! Pelanggan '" . $nama_pelanggan . "' memiliki riwayat " . $pesan_relasi . 
            ". Gunakan fitur Nonaktifkan saja."
        ));
        exit();
    }

    // --- JIKA TIDAK PUNYA RELASI, LAKUKAN HARD DELETE ---
    sqlsrv_begin_transaction($conn);

    try {
        // 1. Hapus foto profil jika bukan default
        $foto_sql = "SELECT Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ?";
        $foto_stmt = sqlsrv_query($conn, $foto_sql, [$id_pelanggan]);
        $foto_data = sqlsrv_fetch_array($foto_stmt, SQLSRV_FETCH_ASSOC);

        if ($foto_data && $foto_data['Foto_Profil'] != 'default.jpg') {
            $foto_path = "../../assets/img/pelanggan/" . $foto_data['Foto_Profil'];
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

        // 2. Update Is_Deleted = 1, Deleted_By, Deleted_Date (Soft delete log)
        $soft_delete_sql = "UPDATE Pelanggan SET 
                            Is_Deleted = 1, 
                            Deleted_By = ?, 
                            Deleted_Date = GETDATE() 
                            WHERE ID_Pelanggan = ?";
        $soft_delete_stmt = sqlsrv_query($conn, $soft_delete_sql, [$nama_admin, $id_pelanggan]);

        if (!$soft_delete_stmt) {
            throw new Exception("Gagal soft delete log");
        }

        // 3. Hard delete dari database (opsional - bisa di-comment jika mau soft delete only)
        // Uncomment baris di bawah ini jika ingin benar-benar hapus dari database
        // $hard_delete_sql = "DELETE FROM Pelanggan WHERE ID_Pelanggan = ?";
        // $hard_delete_stmt = sqlsrv_query($conn, $hard_delete_sql, [$id_pelanggan]);
        // if (!$hard_delete_stmt) { throw new Exception("Gagal hard delete"); }

        sqlsrv_commit($conn);

        header("Location: list.php?status_sukses=hard_delete&nama=" . urlencode($nama_pelanggan));
        exit();

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// ============================================================
// TOGGLE STATUS (Aktif/Nonaktif dari list.php)
// ============================================================
elseif ($aksi == 'toggle_status') {

    if (!isset($_GET['status']) || !in_array($_GET['status'], ['0', '1'])) {
        header("Location: list.php?status_sukses=error&message=status_tidak_valid");
        exit();
    }

    $new_status = (int)$_GET['status'];
    $status_text = ($new_status == 1) ? 'Aktif' : 'Nonaktif';

    sqlsrv_begin_transaction($conn);

    try {
        $sql = "UPDATE Pelanggan SET 
                Status = ?, 
                Modified_By = ?, 
                Modified_Date = GETDATE() 
                WHERE ID_Pelanggan = ?";

        $stmt = sqlsrv_query($conn, $sql, [$new_status, $nama_admin, $id_pelanggan]);

        if ($stmt) {
            sqlsrv_commit($conn);
            header("Location: list.php?status_sukses=toggle_status&status=" . $status_text . "&nama=" . urlencode($nama_pelanggan));
            exit();
        } else {
            throw new Exception("Gagal toggle status");
        }

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// ============================================================
// AKSI TIDAK DIKENAL
// ============================================================
else {
    header("Location: list.php?status_sukses=error&message=aksi_tidak_dikenal");
    exit();
}
?>