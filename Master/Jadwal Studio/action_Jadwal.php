<?php
ob_start();
session_start();
include '../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

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

if ($id <= 0 && $aksi !== 'generate_7hari') {
    header("Location: list.php?status_sukses=error&message=Parameter tidak valid");
    exit();
}

// =====================================================
// AMBIL DATA JADWAL (jika aksi individu)
// =====================================================
$current = null;
if ($id > 0) {
    $current = safe_sqlsrv_fetch($conn, 
        "SELECT ID_Jadwal, Tanggal_Jadwal, Jam_Mulai, Status_Jadwal, Status, Is_Deleted FROM Jadwal_Studio WHERE ID_Jadwal = ?", 
        [$id]
    );

    if (!$current) {
        header("Location: list.php?status_sukses=error&message=Jadwal tidak ditemukan");
        exit();
    }

    if ($current['Is_Deleted'] == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah dihapus");
        exit();
    }
}

// =====================================================
// 1. TOGGLE STATUS (Aktif / Nonaktif)
// =====================================================
if ($aksi == 'toggle_status') {
    $current_status = (int)($current['Status'] ?? 1);
    $new_status = $current_status === 1 ? 0 : 1;

    // *Penyesuaian WIB: Memastikan jadwal belum kedaluwarsa secara tanggal dan jam
    $tgl_jadwal = is_object($current['Tanggal_Jadwal']) ? $current['Tanggal_Jadwal']->format('Y-m-d') : $current['Tanggal_Jadwal'];
    $jam_mulai_check = is_object($current['Jam_Mulai']) ? $current['Jam_Mulai']->format('H:i:s') : $current['Jam_Mulai'];
    $today_now = date('Y-m-d');
    $time_now = date('H:i:s');

    $is_expired = false;
    if ($tgl_jadwal < $today_now) {
        $is_expired = true;
    } elseif ($tgl_jadwal == $today_now && $jam_mulai_check < $time_now) {
        $is_expired = true;
    }

    if ($is_expired) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah expired tidak bisa diubah");
        exit();
    }

    // Cek apakah jadwal sudah booked
    if ($current['Status_Jadwal'] == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah dipesan tidak bisa diubah");
        exit();
    }

    $update_sql = "UPDATE Jadwal_Studio SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $update_stmt = sqlsrv_query($conn, $update_sql, [$new_status, $nama_admin, $id]);

    if ($update_stmt) {
        header("Location: list.php?status_sukses=toggle_status");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal ubah status");
        exit();
    }
}

// =====================================================
// 2. SOFT DELETE (Menerapkan Stored Procedure sp_DeleteJadwalStudio)
// =====================================================
elseif ($aksi === 'soft_delete') {
    if ($current['Status_Jadwal'] == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal sudah dipesan tidak bisa dihapus");
        exit();
    }

    // Menggunakan Stored Procedure sp_DeleteJadwalStudio untuk menghapus secara aman
    $delete_sql = "EXEC sp_DeleteJadwalStudio ?, ?";
    $delete_stmt = sqlsrv_query($conn, $delete_sql, [$id, $nama_admin]);

    if ($delete_stmt) {
        // Otomatis menonaktifkan status aktif data untuk sinkronisasi list data
        sqlsrv_query($conn, "UPDATE Jadwal_Studio SET Status = 0 WHERE ID_Jadwal = ?", [$id]);
        header("Location: list.php?status_sukses=soft_delete");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal hapus jadwal");
        exit();
    }
}

// =====================================================
// 3. GENERATE JADWAL 7 HARI KE DEPAN
// =====================================================
elseif ($aksi === 'generate_7hari') {
    $generated = 0;

    // Ambil semua paket dan ruangan valid dari Paket_Ruangan
    $q_valid = sqlsrv_query($conn, "
        SELECT pr.ID_Paket, pr.ID_Ruangan, p.Durasi_Waktu, p.Nama_Paket, r.Nama_Ruangan
        FROM Paket_Ruangan pr
        INNER JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket
        INNER JOIN Ruangan r ON pr.ID_Ruangan = r.ID_Ruangan
        WHERE p.Status = 1 AND p.Is_Deleted = 0 AND r.Status = 1 AND r.Is_Deleted = 0
    ");

    if ($q_valid === false) {
        header("Location: list.php?status_sukses=error&message=Gagal+ambil+data+paket+ruangan");
        exit();
    }

    $valid_combinations = [];
    while ($row = sqlsrv_fetch_array($q_valid, SQLSRV_FETCH_ASSOC)) {
        $valid_combinations[] = $row;
    }

    if (empty($valid_combinations)) {
        header("Location: list.php?status_sukses=error&message=Tidak+ada+kombinasi+paket+ruangan+yang+valid");
        exit();
    }

    // Generate untuk 7 hari ke depan
    $start_date = new DateTime();
    $start_date->setTime(0, 0, 0);

    for ($day = 0; $day < 7; $day++) {
        $current_date = clone $start_date;
        $current_date->modify("+{$day} days");
        $tanggal_str = $current_date->format('Y-m-d');

        foreach ($valid_combinations as $combo) {
            $id_paket = $combo['ID_Paket'];
            $id_ruangan = $combo['ID_Ruangan'];
            $durasi = $combo['Durasi_Waktu'];

            // Generate slot dari 08:00 sampai 20:00 WIB
            $jam_mulai = new DateTime('08:00');
            $jam_selesai_hari = new DateTime('20:00');

            while ($jam_mulai < $jam_selesai_hari) {
                $jam_mulai_str = $jam_mulai->format('H:i');
                $jam_selesai = clone $jam_mulai;
                $jam_selesai->modify("+{$durasi} minutes");

                if ($jam_selesai > $jam_selesai_hari) {
                    break;
                }

                $jam_selesai_str = $jam_selesai->format('H:i');

                // *Penyesuaian Kueri Bentrok Akurat (Hanya Memerlukan 4 Parameter)
                $cek_bentrok = sqlsrv_query($conn, "
                    SELECT ID_Jadwal FROM Jadwal_Studio 
                    WHERE ID_Ruangan = ? 
                      AND Tanggal_Jadwal = ? 
                      AND Status = 1 
                      AND Is_Deleted = 0
                      AND CAST(? AS TIME) < Jam_Selesai 
                      AND Jam_Mulai < CAST(? AS TIME)
                ", [$id_ruangan, $tanggal_str, $jam_mulai_str, $jam_selesai_str]);

                $bentrok = false;
                if ($cek_bentrok && sqlsrv_has_rows($cek_bentrok)) {
                    $bentrok = true;
                }

                if (!$bentrok) {
                    // Menyimpan data jadwal baru menggunakan Stored Procedure sp_InsertJadwalStudio
                    $insert_sql = "EXEC sp_InsertJadwalStudio ?, ?, ?, ?, ?, ?, ?";
                    $keterangan = "Slot " . $combo['Nama_Paket'] . " - " . $combo['Nama_Ruangan'];

                    $insert_stmt = sqlsrv_query($conn, $insert_sql, [
                        $id_ruangan, $id_paket, $tanggal_str, $jam_mulai_str, $jam_selesai_str,
                        $keterangan, $nama_admin
                    ]);

                    if ($insert_stmt) {
                        $row_new = sqlsrv_fetch_array($insert_stmt, SQLSRV_FETCH_ASSOC);
                        $id_jadwal_baru = $row_new['ID_Jadwal'] ?? null;
                        sqlsrv_free_stmt($insert_stmt);

                        if ($id_jadwal_baru) {
                            // Update Status_Jadwal ke 0 (Tersedia) agar langsung dapat dipesan
                            sqlsrv_query($conn, "UPDATE Jadwal_Studio SET Status_Jadwal = 0 WHERE ID_Jadwal = ?", [$id_jadwal_baru]);
                            $generated++;
                        }
                    }
                }

                $jam_mulai = clone $jam_selesai;
            }
        }
    }

    if ($generated > 0) {
        header("Location: list.php?status_sukses=generate&message=" . urlencode("Berhasil generate {$generated} jadwal untuk 7 hari ke depan!"));
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=" . urlencode("Tidak ada jadwal baru yang bisa digenerate. Semua slot sudah terisi."));
        exit();
    }
}

// Invalid action
else {
    header("Location: list.php?status_sukses=error&message=Aksi+tidak+valid");
    exit();
}
?>