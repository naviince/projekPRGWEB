<?php
session_start();
include '../../koneksi.php';

// Atur zona waktu ke WIB
date_default_timezone_set('Asia/Jakarta');

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit();
}

$id_order = (int)($_POST['id_order'] ?? 0);
$id_fotografer = (int)($_POST['id_fotografer'] ?? 0);

if ($id_order <= 0 || $id_fotografer <= 0) {
    header("Location: list.php?status=error&msg=" . urlencode("Data tidak valid. Pilih order dan fotografer dengan benar."));
    exit();
}

// =====================================================
// SINKRONISASI: CEK ORDER EXISTS DAN STATUS_ORDER = 1 (DP TERVERIFIKASI)
// =====================================================
$q_check_order = sqlsrv_query($conn, 
    "SELECT ID_Order FROM [Order] WHERE ID_Order = ? AND Status_Order = 1 AND Status = 1",
    [$id_order]
);

if ($q_check_order === false) {
    $errors = sqlsrv_errors();
    $err_msg = $errors ? $errors[0]['message'] : 'Query gagal';
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek order: " . $err_msg));
    exit();
}

$order = sqlsrv_fetch_array($q_check_order, SQLSRV_FETCH_ASSOC);
if (!$order) {
    header("Location: list.php?status=error&msg=" . urlencode("Order tidak ditemukan atau sudah diproses. Pastikan order sudah DP terverifikasi."));
    exit();
}
sqlsrv_free_stmt($q_check_order);

// =====================================================
// TARIK SELURUH JADWAL SESI FOTO UNTUK ORDER INI (MENDUKUNG MULTI-SLOT JADWAL)
// =====================================================
$q_jadwal = sqlsrv_query($conn,
    "SELECT j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
     FROM Order_Jadwal oj
     INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
     WHERE oj.ID_Order = ? AND j.Status = 1 AND j.Is_Deleted = 0",
    [$id_order]
);
if ($q_jadwal === false) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal mengambil jadwal order dari database."));
    exit();
}

$order_schedules = [];
while ($row_j = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal
    $t_obj = $row_j['Tanggal_Jadwal'];
    if (is_object($t_obj) && method_exists($t_obj, 'format')) {
        $row_j['Tanggal_Jadwal_Str'] = $t_obj->format('Y-m-d');
    } else {
        $row_j['Tanggal_Jadwal_Str'] = date('Y-m-d', strtotime($t_obj));
    }

    // Format jam mulai
    $jm_obj = $row_j['Jam_Mulai'];
    if (is_object($jm_obj) && method_exists($jm_obj, 'format')) {
        $row_j['Jam_Mulai_Str'] = $jm_obj->format('H:i:s');
    } else {
        $row_j['Jam_Mulai_Str'] = substr($jm_obj, 0, 8);
    }

    // Format jam selesai
    $js_obj = $row_j['Jam_Selesai'];
    if (is_object($js_obj) && method_exists($js_obj, 'format')) {
        $row_j['Jam_Selesai_Str'] = $js_obj->format('H:i:s');
    } else {
        $row_j['Jam_Selesai_Str'] = substr($js_obj, 0, 8);
    }

    $order_schedules[] = $row_j;
}
sqlsrv_free_stmt($q_jadwal);

if (empty($order_schedules)) {
    header("Location: list.php?status=error&msg=" . urlencode("Order ini tidak memiliki jadwal sesi foto aktif di database."));
    exit();
}

// =====================================================
// CEK FOTOGRAFER EXISTS & AKTIF
// =====================================================
$q_fotografer = sqlsrv_query($conn, 
    "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan 
     WHERE ID_Karyawan = ? AND Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0",
    [$id_fotografer]
);

if ($q_fotografer === false) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal verifikasi data fotografer."));
    exit();
}

$fg_data = sqlsrv_fetch_array($q_fotografer, SQLSRV_FETCH_ASSOC);
if (!$fg_data) {
    header("Location: list.php?status=error&msg=" . urlencode("Fotografer tidak ditemukan atau sudah tidak aktif."));
    exit();
}
sqlsrv_free_stmt($q_fotografer);

// =====================================================
// DETEKSI TABRAKAN JADWAL FOTOGRAFER (PREVENT BENTROK MULTI-SLOT SECARA MATEMATIS)
// =====================================================
// Memutar loop kueri untuk memeriksa irisan waktu pada setiap slot jadwal pemesanan ini [2]
foreach ($order_schedules as $sched) {
    $tgl_val = $sched['Tanggal_Jadwal_Str'];
    $mulai_val = $sched['Jam_Mulai_Str'];
    $selesai_val = $sched['Jam_Selesai_Str'];

    // Kueri deteksi irisan waktu (Time Overlap) pada sesi pemotretan aktif milik fotografer terkait [2]
    $sql_overlap = "
        SELECT COUNT(*) as total 
        FROM Sesi_Foto sf
        INNER JOIN Order_Jadwal oj ON sf.ID_Order = oj.ID_Order
        INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
        WHERE sf.ID_Karyawan = ? 
          AND sf.Status = 1 
          AND sf.Status_Sesi <> 2
          AND j.Tanggal_Jadwal = ?
          AND (
              (j.Jam_Mulai >= ? AND j.Jam_Mulai < ?) OR
              (j.Jam_Selesai > ? AND j.Jam_Selesai <= ?) OR
              (? >= j.Jam_Mulai AND ? < j.Jam_Selesai)
          )
          AND sf.ID_Order <> ?
    ";
    
    $params_overlap = [
        $id_fotografer,
        $tgl_val,
        $mulai_val, $selesai_val,
        $mulai_val, $selesai_val,
        $mulai_val, $mulai_val,
        $id_order
    ];
    
    $q_overlap = sqlsrv_query($conn, $sql_overlap, $params_overlap);
    if ($q_overlap === false) {
        $errors = sqlsrv_errors();
        $err_msg = $errors ? $errors[0]['message'] : 'Query gagal';
        header("Location: list.php?status=error&msg=" . urlencode("Gagal melakukan kalkulasi ketersediaan jadwal fotografer: " . $err_msg));
        exit();
    }
    
    $d_overlap = sqlsrv_fetch_array($q_overlap, SQLSRV_FETCH_ASSOC);
    if ($d_overlap && (int)$d_overlap['total'] > 0) {
        // Tampilkan notifikasi penolakan yang presisi dan mudah dipahami
        $tgl_indo = date('d-m-Y', strtotime($tgl_val));
        $jam_format = substr($mulai_val, 0, 5) . " - " . substr($selesai_val, 0, 5);
        header("Location: list.php?status=error&msg=" . urlencode("Fotografer " . $fg_data['Nama_Karyawan'] . " sudah memiliki tugas pemotretan aktif yang bentrok pada tanggal " . $tgl_indo . " jam " . $jam_format . " WIB."));
        exit();
    }
    sqlsrv_free_stmt($q_overlap);
}

// =====================================================
// CEK APAKAH SUDAH ADA SESI UNTUK ORDER INI
// =====================================================
$q_sesi = sqlsrv_query($conn, 
    "SELECT ID_Sesi_Foto FROM Sesi_Foto 
     WHERE ID_Order = ? AND Status = 1 AND Status_Sesi <> 2",
    [$id_order]
);

if ($q_sesi === false) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek sesi foto."));
    exit();
}

$existing_sesi = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_sesi);

// =====================================================
// SIMPAN PENUGASAN (DENGAN TRANSACTION)
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal memulai transaksi database."));
    exit();
}

try {
    $username = $_SESSION['username'] ?? 'admin';

    if ($existing_sesi) {
        // Update fotografer pada sesi foto yang sudah ada
        $q_update = sqlsrv_query($conn, 
            "UPDATE Sesi_Foto 
             SET ID_Karyawan = ?, Modified_By = ?, Modified_Date = GETDATE() 
             WHERE ID_Sesi_Foto = ?",
            [$id_fotografer, $username, $existing_sesi['ID_Sesi_Foto']]
        );
        if (!$q_update) {
            $errors = sqlsrv_errors();
            throw new Exception("Gagal update fotografer: " . ($errors ? $errors[0]['message'] : 'Unknown error'));
        }
    } else {
        // Insert sesi foto baru
        $q_insert = sqlsrv_query($conn, 
            "INSERT INTO Sesi_Foto (ID_Order, ID_Karyawan, Status_Sesi, Status, Created_By, Created_Date)
             VALUES (?, ?, 0, 1, ?, GETDATE())",
            [$id_order, $id_fotografer, $username]
        );
        if (!$q_insert) {
            $errors = sqlsrv_errors();
            throw new Exception("Gagal simpan sesi foto baru: " . ($errors ? $errors[0]['message'] : 'Unknown error'));
        }
    }

    sqlsrv_commit($conn);
    header("Location: list.php?status=sukses_assign&msg=" . urlencode("Fotografer " . $fg_data['Nama_Karyawan'] . " berhasil diassign."));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: list.php?status=error&msg=" . urlencode($e->getMessage()));
}
exit();
?>