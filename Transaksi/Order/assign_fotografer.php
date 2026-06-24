<?php
session_start();
include '../../koneksi.php';

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
// CEK ORDER EXISTS DAN STATUS = 1 (DP TERVERIFIKASI)
// =====================================================
$q_check = sqlsrv_query($conn, 
    "SELECT o.ID_Order, o.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
     FROM [Order] o
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE o.ID_Order = ? AND o.Status_Order = 1 AND o.Status = 1",
    [$id_order]
);

if ($q_check === false) {
    $errors = sqlsrv_errors();
    $err_msg = $errors ? $errors[0]['message'] : 'Query gagal';
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek order: " . $err_msg));
    exit();
}

$order = sqlsrv_fetch_array($q_check, SQLSRV_FETCH_ASSOC);
if (!$order) {
    header("Location: list.php?status=error&msg=" . urlencode("Order tidak ditemukan atau sudah diproses. Pastikan order sudah DP terverifikasi."));
    exit();
}

$tanggal_jadwal = $order['Tanggal_Jadwal'];
$jam_mulai = $order['Jam_Mulai'];
$jam_selesai = $order['Jam_Selesai'];

// Format tanggal untuk SQL Server (DATE type)
if (is_object($tanggal_jadwal) && method_exists($tanggal_jadwal, 'format')) {
    $tanggal_str = $tanggal_jadwal->format('Y-m-d');
} elseif (is_string($tanggal_jadwal)) {
    $tanggal_str = date('Y-m-d', strtotime($tanggal_jadwal));
} else {
    $tanggal_str = date('Y-m-d');
}

// Format jam untuk SQL Server (TIME type)
if (is_object($jam_mulai) && method_exists($jam_mulai, 'format')) {
    $jam_mulai_str = $jam_mulai->format('H:i:s');
} elseif (is_string($jam_mulai)) {
    $jam_mulai_str = substr($jam_mulai, 0, 8); // HH:MM:SS
} else {
    $jam_mulai_str = '00:00:00';
}

// =====================================================
// CEK FOTOGRAFER EXISTS
// =====================================================
$q_fotografer = sqlsrv_query($conn, 
    "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan 
     WHERE ID_Karyawan = ? AND Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0",
    [$id_fotografer]
);

if ($q_fotografer === false) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek data fotografer."));
    exit();
}

$fg_data = sqlsrv_fetch_array($q_fotografer, SQLSRV_FETCH_ASSOC);
if (!$fg_data) {
    header("Location: list.php?status=error&msg=" . urlencode("Fotografer tidak ditemukan atau tidak aktif."));
    exit();
}

// =====================================================
// CEK JADWAL BENTROK FOTOGRAFER
// =====================================================
// Cek apakah fotografer sudah punya sesi di tanggal & jam yang sama
$q_bentrok = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total 
     FROM Sesi_Foto sf
     INNER JOIN [Order] o ON sf.ID_Order = o.ID_Order
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE sf.ID_Karyawan = ? 
       AND sf.Status = 1 
       AND sf.Status_Sesi <> 2
       AND CAST(j.Tanggal_Jadwal AS DATE) = CAST(? AS DATE)
       AND j.Jam_Mulai = CAST(? AS TIME)
       AND o.ID_Order <> ?",
    [$id_fotografer, $tanggal_str, $jam_mulai_str, $id_order]
);

if ($q_bentrok === false) {
    $errors = sqlsrv_errors();
    $err_msg = $errors ? $errors[0]['message'] : 'Query gagal';
    header("Location: list.php?status=error&msg=" . urlencode("Gagal cek jadwal bentrok: " . $err_msg));
    exit();
}

$d_bentrok = sqlsrv_fetch_array($q_bentrok, SQLSRV_FETCH_ASSOC);
if ($d_bentrok && $d_bentrok['total'] > 0) {
    header("Location: list.php?status=error&msg=" . urlencode("Fotografer " . $fg_data['Nama_Karyawan'] . " sudah memiliki sesi di jadwal yang sama (" . $tanggal_str . " " . $jam_mulai_str . "). Pilih fotografer lain."));
    exit();
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

// =====================================================
// BEGIN TRANSACTION
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: list.php?status=error&msg=" . urlencode("Gagal memulai transaksi database."));
    exit();
}

try {
    $username = $_SESSION['username'] ?? 'admin';

    if ($existing_sesi) {
        // Update fotografer yang sudah ada
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
            throw new Exception("Gagal insert sesi foto: " . ($errors ? $errors[0]['message'] : 'Unknown error'));
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