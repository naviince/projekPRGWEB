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
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("[safe_sqlsrv_fetch] SQL Error: " . ($errors ? json_encode($errors) : 'Unknown error'));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("[safe_sqlsrv_count] SQL Error: " . ($errors ? json_encode($errors) : 'Unknown error'));
        return 0;
    }
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
// AMBIL DATA RUANGAN
// =====================================================
$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Foto_Ruangan, Status, Is_Deleted FROM Ruangan WHERE ID_Ruangan = ?", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan");
    exit();
}

// =====================================================
// 1. TOGGLE STATUS (Aktif/Nonaktif)
// =====================================================
if ($aksi == 'toggle_status') {
    
    // Kalau sudah soft delete, tidak bisa toggle
    if ($ruangan['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus, restore dulu untuk mengubah status");
        exit();
    }

    $current_status = (int)($ruangan['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

    // Cek order aktif sebelum nonaktifkan
    if ($new_status === 0) {
        $cek_order = safe_sqlsrv_count($conn,
            "SELECT COUNT(*) as total FROM [Order] 
             WHERE ID_Ruangan = ? AND Status = 1 AND Status_Order IN (0, 1, 2)",
            [$id]
        );
        if ($cek_order > 0) {
            header("Location: list.php?status_sukses=error&message=Ruangan tidak bisa dinonaktifkan karena masih ada {$cek_order} order aktif");
            exit();
        }
    }

    $sql = "UPDATE Ruangan SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Ruangan = ?";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status: " . json_encode(sqlsrv_errors()));
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $status_text = $new_status === 1 ? 'diaktifkan' : 'dinonaktifkan';
    header("Location: list.php?status_sukses=toggle_status&message=Ruangan berhasil {$status_text}");
    exit();
}

// =====================================================
// 2. SOFT DELETE (Hapus - bisa di-restore)
// =====================================================
if ($aksi == 'soft_delete') {
    
    // Kalau sudah soft delete, kasih tau user
    if ($ruangan['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus sebelumnya");
        exit();
    }

    $error_relasi = [];

    // Cek order aktif (Status_Order: 0=Menunggu DP, 1=DP Terverifikasi, 2=Sesi Foto)
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] 
         WHERE ID_Ruangan = ? AND Status = 1 AND Status_Order IN (0, 1, 2)",
        [$id]
    );
    if ($cek_order > 0) {
        $error_relasi[] = "{$cek_order} order aktif (menunggu DP / DP terverifikasi / sesi foto)";
    }

    // Cek jadwal TERPESAN (Status_Jadwal=2) di masa depan
    $cek_jadwal = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Jadwal_Studio 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0 
         AND Tanggal_Jadwal >= CAST(GETDATE() AS DATE) 
         AND Status_Jadwal = 2",
        [$id]
    );
    if ($cek_jadwal > 0) {
        $error_relasi[] = "{$cek_jadwal} jadwal terpesan di masa depan";
    }

    // Cek properti aktif
    $cek_properti = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Properti 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0",
        [$id]
    );
    if ($cek_properti > 0) {
        $error_relasi[] = "{$cek_properti} properti aktif";
    }

    // Jika ada relasi aktif
    if (!empty($error_relasi)) {
        $error_msg = "Ruangan tidak bisa dihapus karena masih memiliki: " . implode(", ", $error_relasi) . ". Selesaikan atau batalkan terlebih dahulu.";
        header("Location: list.php?status_sukses=error&message=" . urlencode($error_msg));
        exit();
    }

    // Soft delete: hanya flag Is_Deleted = 1
    // JANGAN hapus relasi, JANGAN hapus foto
    $sql_soft = "UPDATE Ruangan SET 
        Is_Deleted = 1, 
        Status = 0, 
        Deleted_By = ?, 
        Deleted_Date = GETDATE() 
        WHERE ID_Ruangan = ?";
    $stmt = sqlsrv_query($conn, $sql_soft, [$nama_admin, $id]);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal menghapus ruangan: " . json_encode(sqlsrv_errors()));
        exit();
    }
    sqlsrv_free_stmt($stmt);

    header("Location: list.php?status_sukses=soft_delete&message=Ruangan berhasil dihapus (bisa dikembalikan)");
    exit();
}

// =====================================================
// 3. RESTORE (Kembalikan data yang dihapus)
// =====================================================
if ($aksi == 'restore') {
    
    // Hanya bisa restore kalau sudah soft delete
    if ($ruangan['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=Ruangan masih aktif, tidak perlu di-restore");
        exit();
    }

    $sql_restore = "UPDATE Ruangan SET 
        Is_Deleted = 0, 
        Status = 1, 
        Modified_By = ?, 
        Modified_Date = GETDATE(),
        Deleted_By = NULL,
        Deleted_Date = NULL
        WHERE ID_Ruangan = ?";
    $stmt = sqlsrv_query($conn, $sql_restore, [$nama_admin, $id]);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal mengembalikan ruangan: " . json_encode(sqlsrv_errors()));
        exit();
    }
    sqlsrv_free_stmt($stmt);

    header("Location: list.php?status_sukses=restore&message=Ruangan berhasil dikembalikan");
    exit();
}

// =====================================================
// 4. HARD DELETE (Hapus permanen - hanya untuk sudah soft delete)
// =====================================================
if ($aksi == 'hard_delete') {
    
    // Hanya bisa hard delete kalau sudah soft delete
    if ($ruangan['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=Ruangan harus dihapus terlebih dahulu sebelum dihapus permanen");
        exit();
    }

    // Double check: masih ada order?
    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] WHERE ID_Ruangan = ? AND Status = 1",
        [$id]
    );
    if ($cek_order > 0) {
        header("Location: list.php?status_sukses=error&message=Masih ada {$cek_order} order terkait, tidak bisa hapus permanen");
        exit();
    }

    // Begin transaction
    $begin_result = sqlsrv_begin_transaction($conn);
    if ($begin_result === false) {
        header("Location: list.php?status_sukses=error&message=Gagal memulai transaksi");
        exit();
    }

    try {
        // 1. Hapus relasi Paket_Ruangan
        $sql1 = "DELETE FROM Paket_Ruangan WHERE ID_Ruangan = ?";
        $stmt1 = sqlsrv_query($conn, $sql1, [$id]);
        if ($stmt1 === false) throw new Exception("Gagal hapus relasi paket: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt1);

        // 2. Hapus relasi Ruangan_Tema
        $sql2 = "DELETE FROM Ruangan_Tema WHERE ID_Ruangan = ?";
        $stmt2 = sqlsrv_query($conn, $sql2, [$id]);
        if ($stmt2 === false) throw new Exception("Gagal hapus relasi tema: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt2);

        // 3. Lepas properti dari ruangan (ID_Ruangan = NULL)
        $sql3 = "UPDATE Properti SET ID_Ruangan = NULL, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Ruangan = ?";
        $stmt3 = sqlsrv_query($conn, $sql3, [$nama_admin, $id]);
        if ($stmt3 === false) throw new Exception("Gagal update properti: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt3);

        // 4. Hapus jadwal yang belum terpesan
        $sql4 = "DELETE FROM Jadwal_Studio WHERE ID_Ruangan = ? AND Status_Jadwal <> 2";
        $stmt4 = sqlsrv_query($conn, $sql4, [$id]);
        if ($stmt4 === false) throw new Exception("Gagal hapus jadwal: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt4);

        // 5. Hard delete ruangan
        $sql5 = "DELETE FROM Ruangan WHERE ID_Ruangan = ?";
        $stmt5 = sqlsrv_query($conn, $sql5, [$id]);
        if ($stmt5 === false) throw new Exception("Gagal hapus ruangan: " . json_encode(sqlsrv_errors()));
        sqlsrv_free_stmt($stmt5);

        // 6. Hapus foto dari server
        $foto = $ruangan['Foto_Ruangan'] ?? '';
        if (!empty($foto) && $foto != 'default_ruangan.jpg' && $foto != 'default.jpg') {
            $foto_path = "../../assets/img/ruangan/" . $foto;
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

        // Commit
        $commit_result = sqlsrv_commit($conn);
        if ($commit_result === false) throw new Exception("Gagal commit transaksi");

        header("Location: list.php?status_sukses=hard_delete&message=Ruangan berhasil dihapus permanen");
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