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
// SINKRONISASI: CEK ORDER EXISTS DAN STATUS_ORDER = 1 (DP TERVERIFIKASI) ATAU 3 (LUNAS)
// Order boleh masuk assign fotografer selama salah satu dari dua jalur
// pembayaran (DP atau Pelunasan langsung) sudah diverifikasi Admin.
// =====================================================
$q_check_order = sqlsrv_query($conn, 
    "SELECT ID_Order, Status_Order FROM [Order] WHERE ID_Order = ? AND Status_Order IN (1, 3) AND Status = 1",
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
    header("Location: list.php?status=error&msg=" . urlencode("Order tidak ditemukan atau belum bisa diassign. Pastikan pembayaran DP atau Pelunasan sudah diverifikasi Admin."));
    exit();
}
sqlsrv_free_stmt($q_check_order);

// =====================================================
// CEK KRUSIAL: SATU ORDER = SATU SESI FOTO
// Jika order ini SUDAH memiliki sesi foto aktif (Status_Sesi = 0 Menunggu, atau 1 Selesai),
// maka TOLAK assign ulang. Ini mencegah:
// 1. Customer bayar LUNAS langsung → assign fotografer → customer bayar DP lagi → numpuk
// 2. Customer bayar DP → assign → sesi selesai → bayar pelunasan → assign ulang → numpuk
// =====================================================
$q_sesi_aktif = sqlsrv_query($conn, 
    "SELECT ID_Sesi_Foto, Status_Sesi FROM Sesi_Foto 
     WHERE ID_Order = ? AND Status = 1 AND Status_Sesi IN (0, 1)",
    [$id_order]
);

if ($q_sesi_aktif === false) {
    $errors = sqlsrv_errors();
    $err_msg = $errors ? $errors[0]['message'] : 'Query gagal';
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek sesi foto aktif: " . $err_msg));
    exit();
}

$sesi_aktif = sqlsrv_fetch_array($q_sesi_aktif, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_sesi_aktif);

if ($sesi_aktif) {
    $status_text = ((int)$sesi_aktif['Status_Sesi'] === 0) 
        ? "sedang menunggu pelaksanaan" 
        : "sudah selesai";
    header("Location: list.php?status=error&msg=" . urlencode("Order ini sudah memiliki sesi foto yang " . $status_text . ". Satu order hanya boleh memiliki satu sesi foto. Assign ulang tidak diperbolehkan."));
    exit();
}

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
// Formula overlap: Dua interval [A,B) dan [C,D) overlap iff A < D AND C < B
// Ini bulletproof untuk SEMUA kasus: partial overlap, fully contains, exact match
// =====================================================
foreach ($order_schedules as $sched) {
    $tgl_val = $sched['Tanggal_Jadwal_Str'];
    $mulai_val = $sched['Jam_Mulai_Str'];
    $selesai_val = $sched['Jam_Selesai_Str'];

    // Kueri deteksi irisan waktu menggunakan formula overlap standar
    // order_mulai < existing_selesai AND order_selesai > existing_mulai
    $sql_overlap = "
        SELECT COUNT(*) as total 
        FROM Sesi_Foto sf
        INNER JOIN Order_Jadwal oj ON sf.ID_Order = oj.ID_Order
        INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
        WHERE sf.ID_Karyawan = ? 
          AND sf.Status = 1 
          AND sf.Status_Sesi <> 2
          AND CAST(j.Tanggal_Jadwal AS DATE) = CAST(? AS DATE)
          AND CAST(? AS TIME) < j.Jam_Selesai
          AND CAST(? AS TIME) > j.Jam_Mulai
          AND sf.ID_Order <> ?
    ";
    
    $params_overlap = [
        $id_fotografer,
        $tgl_val,
        $mulai_val,   // order_mulai < existing_selesai
        $selesai_val, // order_selesai > existing_mulai
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
        $tgl_indo = date('d-m-Y', strtotime($tgl_val));
        $jam_format = substr($mulai_val, 0, 5) . " - " . substr($selesai_val, 0, 5);
        header("Location: list.php?status=error&msg=" . urlencode("Fotografer " . $fg_data['Nama_Karyawan'] . " sudah memiliki tugas pemotretan aktif yang bentrok pada tanggal " . $tgl_indo . " jam " . $jam_format . " WIB."));
        exit();
    }
    sqlsrv_free_stmt($q_overlap);
}

// =====================================================
// SIMPAN PENUGASAN (DENGAN TRANSACTION)
// HANYA INSERT BARU — TIDAK ADA UPDATE KARENA SATU ORDER = SATU SESI
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal memulai transaksi database."));
    exit();
}

try {
    $username = $_SESSION['username'] ?? 'admin';

    // Insert sesi foto BARU (selalu insert, tidak pernah update)
    // Status_Sesi = 0 (Menunggu) — fotografer belum mulai sesi
    // Waktu_Mulai = NULL — diisi nanti saat fotografer klik "Mulai Sesi"
    $q_insert = sqlsrv_query($conn, 
        "INSERT INTO Sesi_Foto (ID_Order, ID_Karyawan, Waktu_Mulai, Waktu_Selesai, File_Hasil, Tanggal_Upload_Hasil, Status_Sesi, Status, Created_By, Created_Date)
         VALUES (?, ?, NULL, NULL, NULL, NULL, 0, 1, ?, GETDATE())",
        [$id_order, $id_fotografer, $username]
    );
    
    if (!$q_insert) {
        $errors = sqlsrv_errors();
        throw new Exception("Gagal simpan sesi foto baru: " . ($errors ? $errors[0]['message'] : 'Unknown error'));
    }

    sqlsrv_commit($conn);
    header("Location: list.php?status=sukses_assign&msg=" . urlencode("Fotografer " . $fg_data['Nama_Karyawan'] . " berhasil diassign ke order ini."));

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: list.php?status=error&msg=" . urlencode($e->getMessage()));
}
exit();
?>