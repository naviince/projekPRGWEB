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

$aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || empty($aksi)) {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// Ambil data ruangan (Status dihilangkan dari kueri filter)
$ruangan = safe_sqlsrv_fetch($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Foto_Ruangan, Is_Deleted FROM Ruangan WHERE ID_Ruangan = ?", 
    [$id]
);

if (!$ruangan) {
    header("Location: list.php?status_sukses=error&message=Ruangan tidak ditemukan");
    exit();
}

// =====================================================
// HELPER FUNCTIONS - SAFE SQLSRV
// =====================================================
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

// =====================================================
// PROSES EKSEKUSI AKSI (ARSIP, PULIHKAN, HAPUS PERMANEN)
// =====================================================
switch ($aksi) {

    // -------------------------------------------------
    // SOFT DELETE: Mengarsipkan Ruangan (Stored Procedure sp_DeleteRuangan)
    // -------------------------------------------------
    case 'soft_delete':
        if ($ruangan['Is_Deleted'] == 1) {
            header("Location: list.php?status_sukses=error&message=Ruangan sudah dihapus sebelumnya");
            exit();
        }

        $error_relasi = [];

        // Cek order aktif terkait ruangan ini
        $cek_order = safe_sqlsrv_count($conn,
            "SELECT COUNT(*) as total FROM [Order] 
             WHERE ID_Ruangan = ? AND Status = 1 AND Status_Order IN (0, 1, 2)",
            [$id]
        );
        if ($cek_order > 0) {
            $error_relasi[] = "{$cek_order} order aktif";
        }

        // Cek jadwal booked di masa depan terkait ruangan ini
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

        // Cek properti aktif terkait ruangan ini
        $cek_properti = safe_sqlsrv_count($conn,
            "SELECT COUNT(*) as total FROM Properti 
             WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0",
            [$id]
        );
        if ($cek_properti > 0) {
            $error_relasi[] = "{$cek_properti} properti aktif";
        }

        if (!empty($error_relasi)) {
            $error_msg = "Ruangan tidak bisa diarsipkan karena masih memiliki: " . implode(", ", $error_relasi);
            header("Location: list.php?status_sukses=error&message=" . urlencode($error_msg));
            exit();
        }

        // Panggil Stored Procedure sp_DeleteRuangan
        $sql_soft = "{CALL sp_DeleteRuangan(?, ?)}";
        $stmt = sqlsrv_query($conn, $sql_soft, [$id, $nama_admin]);

        if ($stmt === false) {
            header("Location: list.php?status_sukses=error&message=Gagal mengarsipkan ruangan");
            exit();
        }
        sqlsrv_free_stmt($stmt);

        // Diarahkan langsung ke tab terhapus (terhapus=1) agar user dapat melihat hasil tindakan arsip
        header("Location: list.php?terhapus=1&status_sukses=soft_delete");
        exit();
        break;

    // -------------------------------------------------
    // RESTORE: Memulihkan Ruangan dari Arsip
    // -------------------------------------------------
    case 'restore':
        if ($ruangan['Is_Deleted'] == 0) {
            header("Location: list.php?status_sukses=error&message=Ruangan masih aktif");
            exit();
        }

        $sql_restore = "UPDATE Ruangan SET 
            Is_Deleted = 0, 
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

        // Diarahkan kembali ke daftar aktif (terhapus=0)
        header("Location: list.php?terhapus=0&status_sukses=restore");
        exit();
        break;

    // -------------------------------------------------
    // HARD DELETE: Menghapus Fisik Ruangan Permanen
    // -------------------------------------------------
    case 'hard_delete':
        if ($ruangan['Is_Deleted'] == 0) {
            header("Location: list.php?status_sukses=error&message=Ruangan harus dihapus dulu sebelum hapus permanen");
            exit();
        }

        // Verifikasi relasi transaksi sebelum benar-benar dihapus secara fisik
        $cek_order = safe_sqlsrv_count($conn,
            "SELECT COUNT(*) as total FROM [Order] WHERE ID_Ruangan = ? AND Status = 1",
            [$id]
        );
        if ($cek_order > 0) {
            header("Location: list.php?terhapus=1&status_sukses=error&message=Gagal Hapus! Masih ada {$cek_order} riwayat order terkait ruangan ini.");
            exit();
        }

        $begin_result = sqlsrv_begin_transaction($conn);
        if ($begin_result === false) {
            header("Location: list.php?terhapus=1&status_sukses=error&message=Gagal memulai transaksi database");
            exit();
        }

        try {
            // Hapus relasi Paket_Ruangan
            $sql1 = "DELETE FROM Paket_Ruangan WHERE ID_Ruangan = ?";
            $stmt1 = sqlsrv_query($conn, $sql1, [$id]);
            if ($stmt1 === false) throw new Exception("Gagal menghapus relasi paket");
            sqlsrv_free_stmt($stmt1);

            // Hapus relasi Ruangan_Tema
            $sql2 = "DELETE FROM Ruangan_Tema WHERE ID_Ruangan = ?";
            $stmt2 = sqlsrv_query($conn, $sql2, [$id]);
            if ($stmt2 === false) throw new Exception("Gagal menghapus relasi tema");
            sqlsrv_free_stmt($stmt2);

            // Soft delete properti pendukung ruangan ini
            $sql3 = "UPDATE Properti SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Ruangan = ?";
            $stmt3 = sqlsrv_query($conn, $sql3, [$nama_admin, $id]);
            if ($stmt3 === false) throw new Exception("Gagal mengarsipkan properti terkait");
            sqlsrv_free_stmt($stmt3);

            // Hapus jadwal studio yang tidak aktif/kosong
            $sql4 = "DELETE FROM Jadwal_Studio WHERE ID_Ruangan = ? AND Status_Jadwal <> 1";
            $stmt4 = sqlsrv_query($conn, $sql4, [$id]);
            if ($stmt4 === false) throw new Exception("Gagal menghapus jadwal");
            sqlsrv_free_stmt($stmt4);

            // Hapus fisik Ruangan dari database
            $sql5 = "DELETE FROM Ruangan WHERE ID_Ruangan = ?";
            $stmt5 = sqlsrv_query($conn, $sql5, [$id]);
            if ($stmt5 === false) throw new Exception("Gagal menghapus data ruangan");
            sqlsrv_free_stmt($stmt5);

            // Hapus file gambar dari server
            $foto = $ruangan['Foto_Ruangan'] ?? '';
            if (!empty($foto) && $foto != 'default_ruangan.jpg' && $foto != 'default.jpg') {
                $foto_path = "../../assets/img/ruangan/" . $foto;
                if (file_exists($foto_path)) {
                    @unlink($foto_path);
                }
            }

            $commit_result = sqlsrv_commit($conn);
            if ($commit_result === false) throw new Exception("Gagal menyimpan transaksi database");

            header("Location: list.php?terhapus=1&status_sukses=hard_delete");
            exit();

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            header("Location: list.php?terhapus=1&status_sukses=error&message=" . urlencode($e->getMessage()));
            exit();
        }
        break;

    default:
        // Aksi toggle_status ditiadakan demi keamanan operasional data master ruangan
        header("Location: list.php?status_sukses=error&message=Aksi tidak valid atau dinonaktifkan");
        exit();
        break;
}
?>