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

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

function redirect_with_error($url, $message) {
    header("Location: " . $url . "?status_sukses=error&message=" . urlencode($message));
    exit();
}

function redirect_with_success($url, $status, $message) {
    header("Location: " . $url . "?status_sukses=" . $status . "&message=" . urlencode($message));
    exit();
}

// =====================================================
// AMBIL PARAMETER
// =====================================================
$act = $_GET['act'] ?? '';
$id_barang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_barang <= 0) {
    redirect_with_error('list.php', 'ID barang tidak valid!');
}

// =====================================================
// CEK DATA BARANG ADA
// =====================================================
$data_barang = safe_sqlsrv_fetch($conn, 
    "SELECT * FROM Barang_Cetak WHERE ID_Barang = ?", 
    [$id_barang]
);

if (!$data_barang) {
    redirect_with_error('list.php', 'Data barang tidak ditemukan!');
}

// =====================================================
// CEK RELASI: Detail_Penjualan_Barang_Cetak
// =====================================================
function cek_relasi_penjualan($conn, $id_barang) {
    $count = safe_sqlsrv_count($conn, 
        "SELECT COUNT(*) as total FROM Detail_Penjualan_Barang_Cetak WHERE ID_Barang = ?", 
        [$id_barang]
    );
    return $count;
}

// =====================================================
// CEK RELASI: Penjualan yang belum selesai
// =====================================================
function cek_relasi_penjualan_aktif($conn, $id_barang) {
    $count = safe_sqlsrv_count($conn, 
        "SELECT COUNT(*) as total 
         FROM Detail_Penjualan_Barang_Cetak d
         JOIN Penjualan p ON d.ID_Penjualan = p.ID_Penjualan
         WHERE d.ID_Barang = ? AND p.Status_Penjualan = 0 AND p.Status = 1", 
        [$id_barang]
    );
    return $count;
}

// =====================================================
// PROSES AKSI
// =====================================================
switch ($act) {

    // ============================================
    // AKSI 1: TOGGLE STATUS (0 ↔ 1)
    // ============================================
    case 'toggle_status':
        $status_baru = ($data_barang['Status'] == 1) ? 0 : 1;
        $status_text = ($status_baru == 1) ? 'Aktif' : 'Nonaktif';

        sqlsrv_begin_transaction($conn);
        try {
            $sql = "UPDATE Barang_Cetak SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Barang = ?";
            $stmt = sqlsrv_query($conn, $sql, [$status_baru, $nama_admin, $id_barang]);

            if ($stmt === false) {
                throw new Exception('Gagal toggle status: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_commit($conn);

            redirect_with_success('list.php', 'toggle', 'Status barang "' . $data_barang['Nama_Barang'] . '" diubah ke ' . $status_text);

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            redirect_with_error('list.php', 'Gagal mengubah status: ' . $e->getMessage());
        }
        break;

    // ============================================
    // AKSI 2: SOFT DELETE (Menggunakan sp_DeleteBarangCetak)
    // ============================================
    case 'soft_delete':
        // Cek apakah sudah soft delete
        if ($data_barang['Is_Deleted'] == 1) {
            redirect_with_error('list.php', 'Barang "' . $data_barang['Nama_Barang'] . '" sudah dihapus!');
        }

        // Cek relasi: ada di Detail_Penjualan?
        $jumlah_relasi = cek_relasi_penjualan($conn, $id_barang);
        if ($jumlah_relasi > 0) {
            redirect_with_error('list.php', 
                'Barang "' . $data_barang['Nama_Barang'] . '" tidak bisa dihapus karena masih memiliki ' . $jumlah_relasi . ' transaksi penjualan!');
        }

        // Cek relasi: ada penjualan aktif?
        $jumlah_aktif = cek_relasi_penjualan_aktif($conn, $id_barang);
        if ($jumlah_aktif > 0) {
            redirect_with_error('list.php', 
                'Barang "' . $data_barang['Nama_Barang'] . '" tidak bisa dihapus karena masih memiliki ' . $jumlah_aktif . ' penjualan yang belum selesai!');
        }

        sqlsrv_begin_transaction($conn);
        try {
            // Memanggil Stored Procedure sp_DeleteBarangCetak untuk soft-delete aman
            $sql = "EXEC sp_DeleteBarangCetak ?, ?";
            $stmt = sqlsrv_query($conn, $sql, [$id_barang, $nama_admin]);

            if ($stmt === false) {
                throw new Exception('Gagal soft delete: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt);

            // Sesuaikan Status = 0 untuk sinkronisasi logika data nonaktif
            sqlsrv_query($conn, "UPDATE Barang_Cetak SET Status = 0 WHERE ID_Barang = ?", [$id_barang]);

            sqlsrv_commit($conn);

            redirect_with_success('list.php', 'hapus', 'Barang "' . $data_barang['Nama_Barang'] . '" berhasil dihapus (soft delete)');

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            redirect_with_error('list.php', 'Gagal menghapus: ' . $e->getMessage());
        }
        break;

    // ============================================
    // AKSI 3: HARD DELETE (DELETE PERMANEN)
    // ============================================
    case 'hard_delete':
        // Cek apakah sudah soft delete dulu
        if ($data_barang['Is_Deleted'] != 1) {
            redirect_with_error('list.php', 
                'Barang "' . $data_barang['Nama_Barang'] . '" harus dihapus (soft delete) terlebih dahulu sebelum hapus permanen!');
        }

        // Cek relasi: ada di Detail_Penjualan?
        $jumlah_relasi = cek_relasi_penjualan($conn, $id_barang);
        if ($jumlah_relasi > 0) {
            redirect_with_error('list.php', 
                'Barang "' . $data_barang['Nama_Barang'] . '" tidak bisa dihapus permanen karena masih memiliki ' . $jumlah_relasi . ' transaksi penjualan!');
        }

        sqlsrv_begin_transaction($conn);
        try {
            // Hapus file foto kalau ada
            $upload_dir = '../../uploads/barang/';
            $foto_file = $data_barang['Foto_Barang'];
            if (!empty($foto_file) && $foto_file != 'default_barang.jpg' && file_exists($upload_dir . $foto_file)) {
                unlink($upload_dir . $foto_file);
            }

            // DELETE permanen
            $sql = "DELETE FROM Barang_Cetak WHERE ID_Barang = ?";
            $stmt = sqlsrv_query($conn, $sql, [$id_barang]);

            if ($stmt === false) {
                throw new Exception('Gagal hard delete: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_commit($conn);

            redirect_with_success('list.php', 'hard_delete', 'Barang "' . $data_barang['Nama_Barang'] . '" berhasil dihapus permanen');

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            redirect_with_error('list.php', 'Gagal menghapus permanen: ' . $e->getMessage());
        }
        break;

    // ============================================
    // AKSI 4: RESTORE (Is_Deleted = 0)
    // ============================================
    case 'restore':
        // Cek apakah sudah soft delete
        if ($data_barang['Is_Deleted'] != 1) {
            redirect_with_error('list.php', 'Barang "' . $data_barang['Nama_Barang'] . '" tidak dalam status dihapus!');
        }

        sqlsrv_begin_transaction($conn);
        try {
            $sql = "UPDATE Barang_Cetak SET 
                        Is_Deleted = 0, 
                        Status = 1, 
                        Modified_By = ?, 
                        Modified_Date = GETDATE() 
                    WHERE ID_Barang = ?";
            $stmt = sqlsrv_query($conn, $sql, [$nama_admin, $id_barang]);

            if ($stmt === false) {
                throw new Exception('Gagal restore: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_commit($conn);

            redirect_with_success('list.php', 'restore', 'Barang "' . $data_barang['Nama_Barang'] . '" berhasil dipulihkan');

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            redirect_with_error('list.php', 'Gagal memulihkan: ' . $e->getMessage());
        }
        break;

    // ============================================
    // DEFAULT: AKSI TIDAK VALID
    // ============================================
    default:
        redirect_with_error('list.php', 'Aksi tidak valid! Gunakan: toggle_status, soft_delete, hard_delete, atau restore.');
        break;
}
?>