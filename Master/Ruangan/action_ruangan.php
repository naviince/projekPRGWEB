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
// AMBIL DATA RUANGAN (untuk nama & cek exist)
// =====================================================
$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Ruangan, Foto_Ruangan, Status, Is_Deleted FROM Ruangan WHERE ID_Ruangan = ?", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan");
    exit();
}

if ($ruangan['Is_Deleted'] == 1) {
    header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus");
    exit();
}

// =====================================================
// 1. TOGGLE STATUS (Soft Delete / Aktifkan)
// =====================================================
if ($aksi == 'toggle_status') {
    $current_status = (int)($ruangan['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

    $sql = "UPDATE Ruangan SET 
        Status = ?, 
        Modified_By = ?, 
        Modified_Date = GETDATE() 
        WHERE ID_Ruangan = ?";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status ruangan");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $status_text = $new_status === 1 ? 'diaktifkan' : 'dinonaktifkan';
    header("Location: list.php?status_sukses=toggle_status&message=Ruangan berhasil {$status_text}");
    exit();
}

// =====================================================
// 2. HARD DELETE (Soft Delete - Is_Deleted = 1)
// =====================================================
if ($aksi == 'hard_delete') {
    // --- CEK RELASI YANG MASIH AKTIF ---
    $error_relasi = [];

    // 1. Cek Order aktif
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] 
         WHERE ID_Ruangan = ? AND Status = 1 AND Status_Order <> 4",
        [$id]
    );
    if ($cek_order > 0) {
        $error_relasi[] = "{$cek_order} order aktif";
    }

    // 2. Cek Jadwal mendatang
    $cek_jadwal = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Jadwal_Studio 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0 
         AND Tanggal_Jadwal >= CAST(GETDATE() AS DATE) 
         AND Status_Jadwal <> 2",
        [$id]
    );
    if ($cek_jadwal > 0) {
        $error_relasi[] = "{$cek_jadwal} jadwal mendatang";
    }

    // 3. Cek Properti aktif
    $cek_properti = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Properti 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0",
        [$id]
    );
    if ($cek_properti > 0) {
        $error_relasi[] = "{$cek_properti} properti aktif";
    }

    // 4. Cek Tema terhubung
    $cek_tema = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ?",
        [$id]
    );
    if ($cek_tema > 0) {
        $error_relasi[] = "{$cek_tema} tema terhubung";
    }

    // 5. Cek Paket terhubung
    $cek_paket = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Ruangan = ?",
        [$id]
    );
    if ($cek_paket > 0) {
        $error_relasi[] = "{$cek_paket} paket terhubung";
    }

    // --- JIKA ADA RELASI AKTIF ---
    if (!empty($error_relasi)) {
        $error_msg = "Ruangan tidak bisa dihapus karena masih memiliki: " . implode(", ", $error_relasi) . ". Nonaktifkan terlebih dahulu atau hapus relasinya.";
        header("Location: list.php?status_sukses=error&message=" . urlencode($error_msg));
        exit();
    }

    // --- SOFT DELETE (Is_Deleted = 1) ---
    sqlsrv_begin_transaction($conn);

    try {
        // 1. Hapus relasi Paket_Ruangan (tidak ada order, jadi aman)
        $sql_del_paket = "DELETE FROM Paket_Ruangan WHERE ID_Ruangan = ?";
        $stmt1 = sqlsrv_query($conn, $sql_del_paket, [$id]);
        if ($stmt1 === false) throw new Exception("Gagal hapus relasi paket");
        sqlsrv_free_stmt($stmt1);

        // 2. Hapus relasi Ruangan_Tema
        $sql_del_tema = "DELETE FROM Ruangan_Tema WHERE ID_Ruangan = ?";
        $stmt2 = sqlsrv_query($conn, $sql_del_tema, [$id]);
        if ($stmt2 === false) throw new Exception("Gagal hapus relasi tema");
        sqlsrv_free_stmt($stmt2);

        // 3. Soft delete Ruangan (Is_Deleted = 1)
        $sql_soft = "UPDATE Ruangan SET 
            Is_Deleted = 1, 
            Status = 0, 
            Deleted_By = ?, 
            Deleted_Date = GETDATE() 
            WHERE ID_Ruangan = ?";
        $stmt3 = sqlsrv_query($conn, $sql_soft, [$nama_admin, $id]);
        if ($stmt3 === false) throw new Exception("Gagal soft delete ruangan");
        sqlsrv_free_stmt($stmt3);

        // 4. Hapus foto dari server (opsional, tapi direkomendasikan)
        $foto = $ruangan['Foto_Ruangan'] ?? '';
        if (!empty($foto) && $foto != 'default_ruangan.jpg' && $foto != 'default.jpg') {
            $foto_path = "../../assets/img/ruangan/" . $foto;
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

        sqlsrv_commit($conn);
        header("Location: list.php?status_sukses=hard_delete&message=Ruangan berhasil dihapus");
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