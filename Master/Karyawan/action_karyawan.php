<?php
session_start();
include '../../koneksi.php';

// =====================================================
// PROTEKSI KEAMANAN HAK AKSES BERLAPIS
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$username_session = $_SESSION['username'] ?? 'system';

// =====================================================
// HELPER FUNCTIONS - SAFE SQLSRV
// =====================================================
function safe_sqlsrv_query($conn, $sql, $params = array()) {
    $query = sqlsrv_query($conn, $sql, $params);
    if ($query === false) {
        error_log("SQLSRV Error: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    return $query;
}

function safe_sqlsrv_fetch($query) {
    if (!$query) return false;
    return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
}

function safe_sqlsrv_count($conn, $sql, $params = array()) {
    $query = safe_sqlsrv_query($conn, $sql, $params);
    if (!$query) return 0;
    $row = safe_sqlsrv_fetch($query);
    return $row ? ($row['total'] ?? 0) : 0;
}

// =====================================================
// CEK RELASI KARYAWAN (SEBELUM HARD DELETE)
// =====================================================
function cekRelasiKaryawan($conn, $id_karyawan) {
    $relasi = array();

    // Cek Sesi_Foto
    $count = safe_sqlsrv_count($conn, 
        "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1", 
        array($id_karyawan));
    if ($count > 0) $relasi[] = "Sesi_Foto ($count data)";

    // Cek Pembayaran (sebagai verifikator)
    $count = safe_sqlsrv_count($conn, 
        "SELECT COUNT(*) AS total FROM Pembayaran WHERE ID_Karyawan_Verifikator = ? AND Status = 1", 
        array($id_karyawan));
    if ($count > 0) $relasi[] = "Pembayaran ($count data)";

    // Cek Penjualan (sebagai admin)
    $count = safe_sqlsrv_count($conn, 
        "SELECT COUNT(*) AS total FROM Penjualan WHERE ID_Karyawan_Admin = ? AND Status = 1", 
        array($id_karyawan));
    if ($count > 0) $relasi[] = "Penjualan ($count data)";

    return $relasi;
}

// =====================================================
// GET PARAMETERS
// =====================================================
$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

// Validasi ID
if ($id <= 0) {
    header("Location: index.php?status_sukses=error_general");
    exit();
}

// Proteksi: Owner tidak bisa hapus diri sendiri
if ($id == $id_owner && in_array($aksi, ['soft_delete', 'hard_delete'])) {
    header("Location: index.php?status_sukses=error_self");
    exit();
}

// =====================================================
// HANDLE AKSI
// =====================================================
switch ($aksi) {

    // ============================================
    // TOGGLE STATUS (Aktif/Nonaktif)
    // ============================================
    case 'toggle_status':
        $new_status = ($status == 1) ? 1 : 0;

        $sql = "UPDATE Karyawan SET 
                Status = ?, 
                Modified_By = ?, 
                Modified_Date = GETDATE() 
                WHERE ID_Karyawan = ? AND Is_Deleted = 0";

        $query = safe_sqlsrv_query($conn, $sql, array($new_status, $username_session, $id));

        if ($query) {
            header("Location: index.php?status_sukses=toggle_status");
        } else {
            header("Location: index.php?status_sukses=error_general");
        }
        exit();
        break;

    // ============================================
    // SOFT DELETE (Arsipkan)
    // ============================================
    case 'soft_delete':
        // Cek apakah data sudah dihapus
        $cek = safe_sqlsrv_query($conn, "SELECT Is_Deleted FROM Karyawan WHERE ID_Karyawan = ?", array($id));
        $data = safe_sqlsrv_fetch($cek);

        if ($data && $data['Is_Deleted'] == 1) {
            header("Location: index.php?status_sukses=error_general");
            exit();
        }

        // Soft delete: set Is_Deleted=1, Status=0, Deleted_By, Deleted_Date
        $sql = "UPDATE Karyawan SET 
                Is_Deleted = 1, 
                Status = 0, 
                Deleted_By = ?, 
                Deleted_Date = GETDATE(),
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Karyawan = ?";

        $query = safe_sqlsrv_query($conn, $sql, array($username_session, $username_session, $id));

        if ($query) {
            header("Location: index.php?tab=dihapus&status_sukses=soft_delete");
        } else {
            header("Location: index.php?status_sukses=error_general");
        }
        exit();
        break;

    // ============================================
    // RESTORE (Pulihkan dari Soft Delete)
    // ============================================
    case 'restore':
        // Cek apakah data memang dihapus
        $cek = safe_sqlsrv_query($conn, "SELECT Is_Deleted FROM Karyawan WHERE ID_Karyawan = ?", array($id));
        $data = safe_sqlsrv_fetch($cek);

        if (!$data || $data['Is_Deleted'] == 0) {
            header("Location: index.php?status_sukses=error_general");
            exit();
        }

        // Restore: set Is_Deleted=0, Status=1, hapus Deleted_By & Deleted_Date
        $sql = "UPDATE Karyawan SET 
                Is_Deleted = 0, 
                Status = 1, 
                Deleted_By = NULL, 
                Deleted_Date = NULL,
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Karyawan = ?";

        $query = safe_sqlsrv_query($conn, $sql, array($username_session, $id));

        if ($query) {
            header("Location: index.php?tab=aktif&status_sukses=restore");
        } else {
            header("Location: index.php?status_sukses=error_general");
        }
        exit();
        break;

    // ============================================
    // HARD DELETE (Hapus Permanen)
    // ============================================
    case 'hard_delete':
        // Cek relasi transaksi terlebih dahulu
        $relasi = cekRelasiKaryawan($conn, $id);

        if (!empty($relasi)) {
            // Ada relasi, tidak bisa hapus
            header("Location: index.php?tab=dihapus&status_sukses=error_relasi");
            exit();
        }

        // Hapus foto profil jika bukan default
        $cek_foto = safe_sqlsrv_query($conn, "SELECT Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", array($id));
        $data_foto = safe_sqlsrv_fetch($cek_foto);

        if ($data_foto && $data_foto['Foto_Profil'] != 'default.jpg') {
            $foto_path = "../../assets/img/karyawan/" . $data_foto['Foto_Profil'];
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

        // Hard delete permanen
        $sql = "DELETE FROM Karyawan WHERE ID_Karyawan = ?";
        $query = safe_sqlsrv_query($conn, $sql, array($id));

        if ($query) {
            header("Location: index.php?tab=dihapus&status_sukses=hard_delete");
        } else {
            header("Location: index.php?tab=dihapus&status_sukses=error_general");
        }
        exit();
        break;

    // ============================================
    // DEFAULT: AKSI TIDAK VALID
    // ============================================
    default:
        header("Location: index.php?status_sukses=error_general");
        exit();
        break;
}
?>