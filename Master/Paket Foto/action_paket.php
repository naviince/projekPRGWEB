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
// PROSES TOGGLE STATUS (Aktif / Nonaktif)
// Status: 1 = Aktif, 0 = Nonaktif
// =====================================================
if ($aksi == 'toggle_status') {
    $new_status = $data['Status'] == 1 ? 0 : 1;
    $status_text = $new_status == 1 ? 'diaktifkan' : 'dinonaktifkan';

    if (!sqlsrv_begin_transaction($conn)) {
        header("Location: list.php?status_sukses=error&message=Gagal memulai transaksi");
        exit();
    }

    $sql = "UPDATE Paket_Foto SET 
        Status = ?,
        Modified_By = ?,
        Modified_Date = GETDATE()
        WHERE ID_Paket = ?";

    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=toggle_status&message=Paket berhasil " . $status_text);
        exit();
    } else {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status paket");
        exit();
    }
}

// =====================================================
// PROSES HARD DELETE (Hapus Permanen dari Database)
// =====================================================
if ($aksi == 'hard_delete') {
    $relasi_found = false;
    $relasi_msg = "";

    // Cek relasi ke Paket_Ruangan (junction table)
    $sql_relasi1 = "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ?";
    $stmt_relasi1 = sqlsrv_query($conn, $sql_relasi1, [$id]);
    if ($stmt_relasi1) {
        $relasi1 = sqlsrv_fetch_array($stmt_relasi1, SQLSRV_FETCH_ASSOC);
        if ($relasi1 && $relasi1['total'] > 0) {
            $relasi_found = true;
            $relasi_msg = "Paket masih terhubung dengan " . $relasi1['total'] . " ruangan. Hapus relasi di Paket_Ruangan dulu, atau gunakan Nonaktifkan.";
        }
    }

    // Cek relasi ke Order (transaksi)
    if (!$relasi_found) {
        $sql_relasi2 = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ? AND Status = 1 AND Status_Order <> 4";
        $stmt_relasi2 = sqlsrv_query($conn, $sql_relasi2, [$id]);
        if ($stmt_relasi2) {
            $relasi2 = sqlsrv_fetch_array($stmt_relasi2, SQLSRV_FETCH_ASSOC);
            if ($relasi2 && $relasi2['total'] > 0) {
                $relasi_found = true;
                $relasi_msg = "Paket sudah digunakan dalam " . $relasi2['total'] . " order aktif. Gunakan fitur Nonaktifkan saja agar riwayat transaksi tetap aman.";
            }
        }
    }

    // Cek relasi ke Jadwal_Studio (master jadwal)
    if (!$relasi_found) {
        $sql_relasi3 = "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE ID_Paket = ? AND Is_Deleted = 0";
        $stmt_relasi3 = sqlsrv_query($conn, $sql_relasi3, [$id]);
        if ($stmt_relasi3) {
            $relasi3 = sqlsrv_fetch_array($stmt_relasi3, SQLSRV_FETCH_ASSOC);
            if ($relasi3 && $relasi3['total'] > 0) {
                $relasi_found = true;
                $relasi_msg = "Paket masih punya " . $relasi3['total'] . " slot jadwal di Jadwal_Studio. Hapus jadwal dulu, atau gunakan Nonaktifkan.";
            }
        }
    }

    // Kalau ada relasi -> tidak bisa hard delete, arahkan ke nonaktifkan
    if ($relasi_found) {
        header("Location: list.php?status_sukses=error&message=" . urlencode($relasi_msg));
        exit();
    }

    // TRUE HARD DELETE - hapus permanen dari database
    $foto_paket = $data['Foto_Paket'] ?? 'default_paket.jpg';
    $upload_dir = "../../assets/img/paket/";

    // Hapus foto dari server (jika bukan default)
    if ($foto_paket != 'default_paket.jpg' && file_exists($upload_dir . $foto_paket)) {
        unlink($upload_dir . $foto_paket);
    }

    if (!sqlsrv_begin_transaction($conn)) {
        header("Location: list.php?status_sukses=error&message=Gagal memulai transaksi");
        exit();
    }

    $sql = "DELETE FROM Paket_Foto WHERE ID_Paket = ?";
    $params = [$id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=Paket berhasil dihapus PERMANEN dari database");
        exit();
    } else {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=Gagal menghapus paket permanen");
        exit();
    }
}

// Kalau aksi tidak dikenal
header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();
?>