<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// Ambil profil admin secara dinamis untuk konsistensi pencatatan audit log
$nama_admin = 'Administrator';
if ($id_admin) {
    $q_profile = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
    if ($q_profile && sqlsrv_has_rows($q_profile)) {
        $d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
        $nama_admin = $d_profile['Nama_Karyawan'] ?? $_SESSION['nama'] ?? 'Administrator';
    }
}

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

// =====================================================
// AMBIL PARAMETER
// =====================================================
$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA TEMA (untuk nama & cek exist)
// =====================================================
$tema = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Tema, Foto_Tema, Status, Is_Deleted FROM Tema_Foto WHERE ID_Tema = ?", 
    [$id]
);

if (!$tema) {
    header("Location: list.php?status_sukses=error&message=Tema foto tidak ditemukan");
    exit();
}

if ($tema['Is_Deleted'] == 1) {
    header("Location: list.php?status_sukses=error&message=Tema foto sudah dihapus");
    exit();
}

// =====================================================
// 1. TOGGLE STATUS (Aktif / Nonaktif)
// =====================================================
if ($aksi == 'toggle_status') {
    $current_status = (int)($tema['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

    // Menggunakan Direct SQL UPDATE agar aman dari stored procedure sp_UpdateStatusTemaFoto yang tidak ada di DB
    $sql = "UPDATE Tema_Foto SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Tema = ?";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status tema foto");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $status_text = $new_status === 1 ? 'diaktifkan' : 'dinonaktifkan';
    header("Location: list.php?status_sukses=toggle_status&message=Tema foto berhasil {$status_text}");
    exit();
}

// =====================================================
// 2. HARD DELETE (Menerapkan Stored Procedure sp_DeleteTemaFoto)
// =====================================================
if ($aksi == 'hard_delete') {
    // --- CEK RELASI YANG MASIH AKTIF ---
    // 1. Cek Order aktif yang menggunakan tema ini (Satu-satunya penghalang wajib)
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] 
         WHERE ID_Tema = ? AND Status = 1 AND Status_Order <> 4",
        [$id]
    );

    // --- JIKA ADA ORDER AKTIF PELANGGAN -> BLOKIR PENGHAPUSAN ---
    if ($cek_order > 0) {
        $error_msg = "Gagal Hapus! Tema foto ini tidak bisa dihapus karena masih memiliki {$cek_order} order aktif pelanggan.";
        header("Location: list.php?status_sukses=error&message=" . urlencode($error_msg));
        exit();
    }

    // --- JIKA TIDAK ADA ORDER AKTIF -> JALANKAN PROSES HAPUS SECARA AMAN ---
    sqlsrv_begin_transaction($conn);

    try {
        // 1. Bersihkan dahulu relasi ruangan di tabel Ruangan_Tema (aman karena tidak ada order aktif)
        $sql_del_ruangan = "DELETE FROM Ruangan_Tema WHERE ID_Tema = ?";
        $stmt1 = sqlsrv_query($conn, $sql_del_ruangan, [$id]);
        if ($stmt1 === false) throw new Exception("Gagal hapus relasi ruangan");
        sqlsrv_free_stmt($stmt1);

        // 2. Tandai hapus tema menggunakan Stored Procedure resmi (sp_DeleteTemaFoto)
        $sql_soft = "EXEC sp_DeleteTemaFoto ?, ?";
        $stmt2 = sqlsrv_query($conn, $sql_soft, [$id, $nama_admin]);
        if ($stmt2 === false) throw new Exception("Gagal soft delete tema foto");
        sqlsrv_free_stmt($stmt2);

        // 3. Bersihkan file foto fisik di penyimpanan server jika bukan gambar default
        $foto = $tema['Foto_Tema'] ?? '';
        if (!empty($foto) && $foto != 'default_tema.jpg' && $foto != 'default.jpg') {
            $foto_path = "../../assets/img/tema/" . $foto;
            if (file_exists($foto_path)) {
                @unlink($foto_path);
            }
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=Tema foto berhasil dihapus");
        exit();

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        header("Location: list.php?status_sukses=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// =====================================================
// AKSI TIDAK VALID
// =====================================================
header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();