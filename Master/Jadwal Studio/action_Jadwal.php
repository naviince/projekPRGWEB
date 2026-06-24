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

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID+tidak+valid");
    exit();
}

// =====================================================
// TOGGLE SOFT DELETE (Aktif/Nonaktif via Is_Deleted)
// =====================================================
if ($aksi === 'toggle_soft_delete') {
    $new_active = isset($_GET['active']) ? (int)$_GET['active'] : 0;

    // Validate: active can only be 0 or 1
    if (!in_array($new_active, [0, 1])) {
        header("Location: list.php?status_sukses=error&message=Status+tidak+valid");
        exit();
    }

    // Check if jadwal exists
    $cek_sql = "SELECT ID_Jadwal, Is_Deleted, Status, Status_Jadwal FROM Jadwal_Studio WHERE ID_Jadwal = ?";
    $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);

    if ($cek_stmt === false || !sqlsrv_has_rows($cek_stmt)) {
        header("Location: list.php?status_sukses=error&message=Jadwal+tidak+ditemukan");
        exit();
    }

    $current = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($cek_stmt);

    // If trying to activate (set Is_Deleted=0, Status=1)
    if ($new_active === 1) {
        // Check for overlap with existing active schedules
        // Get the schedule details first
        $detail_sql = "SELECT ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai 
                       FROM Jadwal_Studio WHERE ID_Jadwal = ?";
        $detail_stmt = sqlsrv_query($conn, $detail_sql, [$id]);
        $detail = sqlsrv_fetch_array($detail_stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($detail_stmt);

        if ($detail) {
            $tanggal = $detail['Tanggal_Jadwal'];
            if ($tanggal instanceof DateTime) {
                $tanggal = $tanggal->format('Y-m-d');
            }
            $jam_mulai = $detail['Jam_Mulai'];
            if ($jam_mulai instanceof DateTime) {
                $jam_mulai = $jam_mulai->format('H:i:s');
            }
            $jam_selesai = $detail['Jam_Selesai'];
            if ($jam_selesai instanceof DateTime) {
                $jam_selesai = $jam_selesai->format('H:i:s');
            }

            // Check overlap with other active schedules in same room
            $overlap_sql = "SELECT COUNT(*) as total FROM Jadwal_Studio 
                            WHERE ID_Ruangan = ? 
                              AND Tanggal_Jadwal = ?
                              AND ID_Jadwal <> ?
                              AND Is_Deleted = 0 
                              AND Status = 1
                              AND (
                                  (Jam_Mulai < ? AND Jam_Selesai > ?) OR
                                  (Jam_Mulai >= ? AND Jam_Mulai < ?) OR
                                  (Jam_Selesai > ? AND Jam_Selesai <= ?)
                              )";
            $overlap_stmt = sqlsrv_query($conn, $overlap_sql, [
                $detail['ID_Ruangan'], $tanggal, $id,
                $jam_selesai, $jam_mulai,
                $jam_mulai, $jam_selesai,
                $jam_mulai, $jam_selesai
            ]);

            if ($overlap_stmt !== false) {
                $overlap_row = sqlsrv_fetch_array($overlap_stmt, SQLSRV_FETCH_ASSOC);
                if (($overlap_row['total'] ?? 0) > 0) {
                    sqlsrv_free_stmt($overlap_stmt);
                    header("Location: list.php?status_sukses=error&message=Jadwal+bertabrakan+dengan+slot+lain");
                    exit();
                }
                sqlsrv_free_stmt($overlap_stmt);
            }
        }

        // Activate: set Is_Deleted=0, Status=1
        $update_sql = "UPDATE Jadwal_Studio 
                       SET Is_Deleted = 0, 
                           Status = 1,
                           Modified_By = ?, 
                           Modified_Date = GETDATE() 
                       WHERE ID_Jadwal = ?";
        $params = [$nama_admin, $id];
    } else {
        // Deactivate (soft delete): set Is_Deleted=1, Status=0
        // But first check if it's currently booked (Status_Jadwal = 1)
        if ((int)$current['Status_Jadwal'] === 1) {
            header("Location: list.php?status_sukses=error&message=Jadwal+sudah+dibooking+tidak+bisa+nonaktifkan");
            exit();
        }

        $update_sql = "UPDATE Jadwal_Studio 
                       SET Is_Deleted = 1, 
                           Status = 0,
                           Deleted_By = ?, 
                           Deleted_Date = GETDATE() 
                       WHERE ID_Jadwal = ?";
        $params = [$nama_admin, $id];
    }

    $update_stmt = sqlsrv_query($conn, $update_sql, $params);

    if ($update_stmt) {
        sqlsrv_free_stmt($update_stmt);
        header("Location: list.php?status_sukses=toggle_soft_delete");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+ubah+status");
        exit();
    }
}

// =====================================================
// HARD DELETE (Permanent - only if already soft deleted)
// =====================================================
elseif ($aksi === 'hard_delete') {
    // Only allow hard delete if already soft deleted
    $cek_sql = "SELECT Is_Deleted, Status_Jadwal FROM Jadwal_Studio WHERE ID_Jadwal = ?";
    $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);

    if ($cek_stmt === false || !sqlsrv_has_rows($cek_stmt)) {
        header("Location: list.php?status_sukses=error&message=Jadwal+tidak+ditemukan");
        exit();
    }

    $current = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($cek_stmt);

    // Prevent hard delete if not soft deleted first
    if ($current['Is_Deleted'] == 0) {
        header("Location: list.php?status_sukses=error&message=Nonaktifkan+terlebih+dahulu+sebelum+hapus+permanen");
        exit();
    }

    // Prevent hard delete if booked
    if ((int)$current['Status_Jadwal'] === 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal+sudah+dibooking+tidak+bisa+dihapus");
        exit();
    }

    // Check if referenced by Order table
    $ref_sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Jadwal = ? AND Status = 1 AND Status_Order <> 4";
    $ref_stmt = sqlsrv_query($conn, $ref_sql, [$id]);

    if ($ref_stmt !== false) {
        $ref_row = sqlsrv_fetch_array($ref_stmt, SQLSRV_FETCH_ASSOC);
        if (($ref_row['total'] ?? 0) > 0) {
            sqlsrv_free_stmt($ref_stmt);
            header("Location: list.php?status_sukses=error&message=Jadwal+masih+terhubung+dengan+order");
            exit();
        }
        sqlsrv_free_stmt($ref_stmt);
    }

    // Hard delete
    $delete_sql = "DELETE FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 1";
    $delete_stmt = sqlsrv_query($conn, $delete_sql, [$id]);

    if ($delete_stmt) {
        sqlsrv_free_stmt($delete_stmt);
        header("Location: list.php?status_sukses=hard_delete");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+hapus+permanen");
        exit();
    }
}

// Invalid action
else {
    header("Location: list.php?status_sukses=error&message=Aksi+tidak+valid");
    exit();
}
?>