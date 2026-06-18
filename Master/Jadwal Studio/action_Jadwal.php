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
// HELPER FUNCTIONS - Safe SQLSRV
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
// AMBIL PARAMETER AKSI
// =====================================================
$aksi = $_GET['aksi'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID jadwal tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA JADWAL UNTUK VALIDASI STATUS
// =====================================================
$data_jadwal = safe_sqlsrv_fetch($conn,
    "SELECT Status_Jadwal, Is_Deleted, Status FROM Jadwal_Studio WHERE ID_Jadwal = ?",
    [$id]
);

if (!$data_jadwal) {
    header("Location: list.php?status_sukses=error&message=Data jadwal tidak ditemukan");
    exit();
}

$status_jadwal = (int)($data_jadwal['Status_Jadwal'] ?? 0);
$is_deleted = (int)($data_jadwal['Is_Deleted'] ?? 0);
$status_data = (int)($data_jadwal['Status'] ?? 1);

// =====================================================
// 1. TOGGLE STATUS (Aktif / Nonaktif)
// =====================================================
if ($aksi == 'toggle_status') {
    // Jadwal yang sudah di-soft-delete tidak boleh toggle status
    if ($is_deleted == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah dihapus, tidak bisa ubah status");
        exit();
    }

    // Jadwal yang sudah terpesan atau selesai tidak boleh dinonaktifkan
    // Karena akan merusak alur order customer
    if ($status_data == 1 && $status_jadwal >= 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah " . ($status_jadwal == 1 ? "terpesan" : "selesai") . ", tidak bisa diubah status");
        exit();
    }

    $new_status = $status_data === 1 ? 0 : 1;
    $status_text = $new_status === 1 ? 'Aktif' : 'Nonaktif';

    $sql = "UPDATE Jadwal_Studio SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $params = [$new_status, $nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=toggle_status&message=Status jadwal diubah ke " . $status_text);
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal mengubah status jadwal");
        exit();
    }
}

// =====================================================
// 2. SOFT DELETE (Is_Deleted = 1)
// =====================================================
if ($aksi == 'soft_delete') {
    // --- VALIDASI 1: Jadwal sudah di-soft-delete? ---
    if ($is_deleted == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah dihapus sebelumnya");
        exit();
    }

    // --- VALIDASI 2: Jadwal sudah terpesan? (Status_Jadwal = 1) ---
    // Kalau terpesan, TIDAK BOLEH dihapus karena akan merusak order customer
    if ($status_jadwal == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena sudah terpesan oleh customer");
        exit();
    }

    // --- VALIDASI 3: Jadwal sedang berlangsung? (Status_Jadwal = 1 dari sisi sesi) ---
    // Sebenarnya Status_Jadwal = 1 sudah cukup, tapi double-check dengan sesi foto
    $cek_sesi_berlangsung = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Sesi_Foto sf
         INNER JOIN [Order] o ON sf.ID_Order = o.ID_Order
         WHERE o.ID_Jadwal = ? AND sf.Status = 1 AND sf.Status_Sesi = 1 AND o.Status = 1",
        [$id]
    );

    if ($cek_sesi_berlangsung > 0) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena sedang ada sesi foto yang berlangsung");
        exit();
    }

    // --- VALIDASI 4: Cek apakah ada order (APAPUN STATUSNYA) yang mereferensi jadwal ini ---
    // Termasuk order yang dibatalkan (Status_Order = 4) karena untuk audit trail
    // Jadwal yang pernah dipakai tidak boleh dihapus
    $cek_order_all = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] WHERE ID_Jadwal = ? AND Status = 1",
        [$id]
    );

    if ($cek_order_all > 0) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena pernah digunakan dalam " . $cek_order_all . " order (termasuk yang sudah selesai/dibatalkan)");
        exit();
    }

    // --- VALIDASI 5: Cek apakah ada sesi foto (APAPUN STATUSNYA) ---
    $cek_sesi_all = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Sesi_Foto sf
         INNER JOIN [Order] o ON sf.ID_Order = o.ID_Order
         WHERE o.ID_Jadwal = ? AND sf.Status = 1",
        [$id]
    );

    if ($cek_sesi_all > 0) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak bisa dihapus karena memiliki riwayat sesi foto");
        exit();
    }

    // --- SOFT DELETE: Is_Deleted = 1, Status tetap apa adanya ---
    // Status tidak diubah ke 0, biarkan apa adanya untuk konsistensi data
    $sql = "UPDATE Jadwal_Studio SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $params = [$nama_admin, $id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=soft_delete&message=Jadwal berhasil dihapus (soft delete)");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal menghapus jadwal");
        exit();
    }
}

// =====================================================
// 3. HARD DELETE (Hanya kalau sudah soft delete dulu)
// =====================================================
if ($aksi == 'hard_delete') {
    // --- VALIDASI 1: Cek apakah sudah soft delete dulu ---
    if ($is_deleted != 1) {
        header("Location: list.php?status_sukses=error&message=Hard delete hanya bisa dilakukan setelah soft delete");
        exit();
    }

    // --- VALIDASI 2: Cek apakah ada order (APAPUN STATUS) yang mereferensi ---
    // Ini untuk mencegah FK constraint error saat DELETE
    $cek_order_fk = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM [Order] WHERE ID_Jadwal = ?",
        [$id]
    );

    if ($cek_order_fk > 0) {
        header("Location: list.php?status_sukses=error&message=Hard delete gagal: jadwal masih memiliki " . $cek_order_fk . " riwayat order. Data tidak bisa dihapus permanen untuk menjaga integritas database.");
        exit();
    }

    // --- VALIDASI 3: Cek apakah ada sesi foto yang mereferensi ---
    $cek_sesi_fk = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Sesi_Foto sf
         INNER JOIN [Order] o ON sf.ID_Order = o.ID_Order
         WHERE o.ID_Jadwal = ?",
        [$id]
    );

    if ($cek_sesi_fk > 0) {
        header("Location: list.php?status_sukses=error&message=Hard delete gagal: jadwal masih memiliki riwayat sesi foto");
        exit();
    }

    // --- VALIDASI 4: Cek apakah ada pembayaran yang mereferensi ---
    $cek_pembayaran_fk = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Pembayaran p
         INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
         WHERE o.ID_Jadwal = ?",
        [$id]
    );

    if ($cek_pembayaran_fk > 0) {
        header("Location: list.php?status_sukses=error&message=Hard delete gagal: jadwal masih memiliki riwayat pembayaran");
        exit();
    }

    // --- VALIDASI 5: Cek apakah ada penjualan yang mereferensi ---
    $cek_penjualan_fk = safe_sqlsrv_count($conn,
        "SELECT COUNT(*) as total FROM Penjualan pen
         INNER JOIN [Order] o ON pen.ID_Order = o.ID_Order
         WHERE o.ID_Jadwal = ?",
        [$id]
    );

    if ($cek_penjualan_fk > 0) {
        header("Location: list.php?status_sukses=error&message=Hard delete gagal: jadwal masih memiliki riwayat penjualan");
        exit();
    }

    // --- HARD DELETE PERMANEN ---
    $sql = "DELETE FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 1";
    $params = [$id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
        header("Location: list.php?status_sukses=hard_delete&message=Jadwal berhasil dihapus permanen");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal hard delete jadwal");
        exit();
    }
}

// =====================================================
// JIKA AKSES LANGSUNG TANPA AKSI VALID
// =====================================================
header("Location: list.php");
exit();
?>