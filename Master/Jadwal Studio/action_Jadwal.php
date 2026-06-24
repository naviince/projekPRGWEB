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

if ($id <= 0 && $aksi !== 'generate_7hari') {
    header("Location: list.php?status_sukses=error&message=ID+tidak+valid");
    exit();
}

// =====================================================
// TOGGLE STATUS (Aktif/Nonaktif)
// =====================================================
if ($aksi === 'toggle_status') {
    $new_status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

    if (!in_array($new_status, [0, 1])) {
        header("Location: list.php?status_sukses=error&message=Status+tidak+valid");
        exit();
    }

    // Cek jadwal exists dan tidak expired
    $cek_sql = "SELECT ID_Jadwal, Tanggal_Jadwal, Status_Jadwal FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);

    if ($cek_stmt === false || !sqlsrv_has_rows($cek_stmt)) {
        header("Location: list.php?status_sukses=error&message=Jadwal+tidak+ditemukan");
        exit();
    }

    $current = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

    // Cek apakah jadwal sudah expired
    $tgl_jadwal = is_object($current['Tanggal_Jadwal']) ? $current['Tanggal_Jadwal']->format('Y-m-d') : $current['Tanggal_Jadwal'];
    if (strtotime($tgl_jadwal) < strtotime(date('Y-m-d'))) {
        header("Location: list.php?status_sukses=error&message=Jadwal+sudah+expired+tidak+bisa+diubah");
        exit();
    }

    // Cek apakah jadwal sudah booked
    if ($current['Status_Jadwal'] == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal+sudah+dipesan+tidak+bisa+diubah");
        exit();
    }

    $update_sql = "UPDATE Jadwal_Studio SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $update_stmt = sqlsrv_query($conn, $update_sql, [$new_status, $nama_admin, $id]);

    if ($update_stmt) {
        header("Location: list.php?status_sukses=toggle_status");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+ubah+status");
        exit();
    }
}

// =====================================================
// SOFT DELETE
// =====================================================
elseif ($aksi === 'soft_delete') {
    $cek_sql = "SELECT ID_Jadwal, Status_Jadwal FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);

    if ($cek_stmt === false || !sqlsrv_has_rows($cek_stmt)) {
        header("Location: list.php?status_sukses=error&message=Jadwal+tidak+ditemukan");
        exit();
    }

    $current = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

    if ($current['Status_Jadwal'] == 1) {
        header("Location: list.php?status_sukses=error&message=Jadwal+sudah+dipesan+tidak+bisa+dihapus");
        exit();
    }

    $delete_sql = "UPDATE Jadwal_Studio SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Jadwal = ? AND Is_Deleted = 0";
    $delete_stmt = sqlsrv_query($conn, $delete_sql, [$nama_admin, $id]);

    if ($delete_stmt) {
        header("Location: list.php?status_sukses=soft_delete");
        exit();
    } else {
        header("Location: list.php?status_sukses=error&message=Gagal+hapus+jadwal");
        exit();
    }
}

// =====================================================
// GENERATE JADWAL 7 HARI KE DEPAN
// =====================================================
elseif ($aksi === 'generate_7hari') {
    $generated = 0;
    $errors = [];

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

            // Generate slot dari 08:00 sampai 20:00
            $jam_mulai = new DateTime('08:00');
            $jam_selesai_hari = new DateTime('20:00');

            while ($jam_mulai < $jam_selesai_hari) {
                $jam_mulai_str = $jam_mulai->format('H:i:s');
                $jam_selesai = clone $jam_mulai;
                $jam_selesai->modify("+{$durasi} minutes");

                if ($jam_selesai > $jam_selesai_hari) {
                    break;
                }

                $jam_selesai_str = $jam_selesai->format('H:i:s');

                // Cek bentrok
                $cek_bentrok = sqlsrv_query($conn, "
                    SELECT ID_Jadwal FROM Jadwal_Studio 
                    WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Status = 1 AND Is_Deleted = 0
                    AND (
                        (CAST(? AS TIME) >= Jam_Mulai AND CAST(? AS TIME) < Jam_Selesai) OR
                        (CAST(? AS TIME) > Jam_Mulai AND CAST(? AS TIME) <= Jam_Selesai) OR
                        (CAST(? AS TIME) <= Jam_Mulai AND CAST(? AS TIME) >= Jam_Selesai)
                    )
                ", [$id_ruangan, $tanggal_str, $jam_mulai_str, $jam_mulai_str, $jam_selesai_str, $jam_selesai_str, $jam_mulai_str, $jam_selesai_str]);

                $bentrok = false;
                if ($cek_bentrok && sqlsrv_has_rows($cek_bentrok)) {
                    $bentrok = true;
                }

                if (!$bentrok) {
                    $insert_sql = "INSERT INTO Jadwal_Studio 
                                   (ID_Ruangan, ID_Paket, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, 
                                    Keterangan, Status_Jadwal, Status, Created_By, Created_Date) 
                                   VALUES (?, ?, ?, ?, ?, ?, 0, 1, ?, GETDATE())";

                    $keterangan = "Slot " . $combo['Nama_Paket'] . " - " . $combo['Nama_Ruangan'];

                    $insert_stmt = sqlsrv_query($conn, $insert_sql, [
                        $id_ruangan, $id_paket, $tanggal_str, $jam_mulai_str, $jam_selesai_str,
                        $keterangan, $nama_admin
                    ]);

                    if ($insert_stmt) {
                        $generated++;
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