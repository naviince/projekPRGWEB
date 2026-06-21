<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
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

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Foto_Ruangan, Status, Is_Deleted FROM Ruangan WHERE ID_Ruangan = ?", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan");
    exit();
}

// 1. TOGGLE STATUS
if ($aksi == 'toggle_status') {
    if ($ruangan['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus, restore dulu");
        exit();
    }

    $current_status = (int)($ruangan['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

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
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    $status_text = $new_status === 1 ? 'diaktifkan' : 'dinonaktifkan';
    header("Location: list.php?status_sukses=toggle_status&message=Ruangan berhasil {$status_text}");
    exit();
}

// 2. SOFT DELETE
if ($aksi == 'soft_delete') {
    if ($ruangan['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus sebelumnya");
        exit();
    }

    $error_relasi = [];

    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] 
         WHERE ID_Ruangan = ? AND Status = 1 AND Status_Order IN (0, 1, 2)",
        [$id]
    );
    if ($cek_order > 0) {
        $error_relasi[] = "{$cek_order} order aktif";
    }

    $cek_jadwal = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Jadwal_Studio 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0 
         AND Tanggal_Jadwal >= CAST(GETDATE() AS DATE) 
         AND Status_Jadwal = 1",
        [$id]
    );
    if ($cek_jadwal > 0) {
        $error_relasi[] = "{$cek_jadwal} jadwal booked di masa depan";
    }

    $cek_properti = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Properti 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0",
        [$id]
    );
    if ($cek_properti > 0) {
        $error_relasi[] = "{$cek_properti} properti aktif";
    }

    if (!empty($error_relasi)) {
        $error_msg = "Ruangan tidak bisa dihapus karena masih memiliki: " . implode(", ", $error_relasi);
        header("Location: list.php?status_sukses=error&message=" . urlencode($error_msg));
        exit();
    }

    $sql_soft = "UPDATE Ruangan SET 
        Is_Deleted = 1, 
        Status = 0, 
        Deleted_By = ?, 
        Deleted_Date = GETDATE() 
        WHERE ID_Ruangan = ?";
    $stmt = sqlsrv_query($conn, $sql_soft, [$nama_admin, $id]);

    if ($stmt === false) {
        header("Location: list.php?status_sukses=error&message=Gagal menghapus ruangan");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    header("Location: list.php?status_sukses=soft_delete&message=Ruangan berhasil dihapus (bisa dikembalikan)");
    exit();
}

// 3. RESTORE
if ($aksi == 'restore') {
    if ($ruangan['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=Ruangan masih aktif");
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
        header("Location: list.php?status_sukses=error&message=Gagal mengembalikan ruangan");
        exit();
    }
    sqlsrv_free_stmt($stmt);

    header("Location: list.php?status_sukses=restore&message=Ruangan berhasil dikembalikan");
    exit();
}

// 4. HARD DELETE
if ($aksi == 'hard_delete') {
    if ($ruangan['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=Ruangan harus dihapus dulu sebelum hapus permanen");
        exit();
    }

    $cek_order = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] WHERE ID_Ruangan = ? AND Status = 1",
        [$id]
    );
    if ($cek_order > 0) {
        header("Location: list.php?status_sukses=error&message=Masih ada {$cek_order} order terkait");
        exit();
    }

    $begin_result = sqlsrv_begin_transaction($conn);
    if ($begin_result === false) {
        header("Location: list.php?status_sukses=error&message=Gagal memulai transaksi");
        exit();
    }

    try {
        $sql1 = "DELETE FROM Paket_Ruangan WHERE ID_Ruangan = ?";
        $stmt1 = sqlsrv_query($conn, $sql1, [$id]);
        if ($stmt1 === false) throw new Exception("Gagal hapus relasi paket");
        sqlsrv_free_stmt($stmt1);

        $sql2 = "DELETE FROM Ruangan_Tema WHERE ID_Ruangan = ?";
        $stmt2 = sqlsrv_query($conn, $sql2, [$id]);
        if ($stmt2 === false) throw new Exception("Gagal hapus relasi tema");
        sqlsrv_free_stmt($stmt2);

        $sql3 = "UPDATE Properti SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Ruangan = ?";
        $stmt3 = sqlsrv_query($conn, $sql3, [$nama_admin, $id]);
        if ($stmt3 === false) throw new Exception("Gagal soft delete properti");
        sqlsrv_free_stmt($stmt3);

        $sql4 = "DELETE FROM Jadwal_Studio WHERE ID_Ruangan = ? AND Status_Jadwal <> 1";
        $stmt4 = sqlsrv_query($conn, $sql4, [$id]);
        if ($stmt4 === false) throw new Exception("Gagal hapus jadwal");
        sqlsrv_free_stmt($stmt4);

        $sql5 = "DELETE FROM Ruangan WHERE ID_Ruangan = ?";
        $stmt5 = sqlsrv_query($conn, $sql5, [$id]);
        if ($stmt5 === false) throw new Exception("Gagal hapus ruangan");
        sqlsrv_free_stmt($stmt5);

        $foto = $ruangan['Foto_Ruangan'] ?? '';
        if (!empty($foto) && $foto != 'default_ruangan.jpg' && $foto != 'default.jpg') {
            $foto_path = "../../assets/img/ruangan/" . $foto;
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }

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

header("Location: list.php?status_sukses=error&message=Aksi tidak valid");
exit();