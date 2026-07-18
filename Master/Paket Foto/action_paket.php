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

if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID+tidak+valid");
    exit();
}

// =====================================================
// HELPER FUNCTIONS - UNTUK MENJAGA INTEGRITAS RELASI
// =====================================================

function hasOrder($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total FROM [Order] WHERE ID_Paket = ? AND Status = 1";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

function hasRuangan($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total FROM Ruangan WHERE ID_Paket = ? AND Is_Deleted = 0";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

function hasJadwal($conn, $id_paket) {
    $sql = "SELECT COUNT(*) as total 
            FROM Jadwal_Studio js
            JOIN Ruangan r ON js.ID_Ruangan = r.ID_Ruangan
            WHERE r.ID_Paket = ? AND js.Is_Deleted = 0";
    $stmt = sqlsrv_query($conn, $sql, [$id_paket]);
    if ($stmt === false) return true;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return ($row['total'] ?? 0) > 0;
}

function getFotoPaket($conn, $id) {
    $sql = "SELECT Foto_Paket FROM Paket_Foto WHERE ID_Paket = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt === false) return 'default_paket.jpg';
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row['Foto_Paket'] ?? 'default_paket.jpg';
}

// =====================================================
// PROSES EKSEKUSI AKSI
// =====================================================
switch ($aksi) {

    case 'toggle_status':
        $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
        if ($status !== 0 && $status !== 1) {
            $status = 1;
        }

        $sql = "UPDATE Paket_Foto SET 
                Status = ?, 
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Paket = ?";

        $stmt = sqlsrv_query($conn, $sql, [$status, $nama_admin, $id]);

        if ($stmt) {
            header("Location: list.php?status_sukses=toggle_status");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+mengubah+status+paket");
        }
        exit();
        break;

    case 'soft_delete':
        $cek_sql = "SELECT Is_Deleted FROM Paket_Foto WHERE ID_Paket = ?";
        $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);
        $data = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

        if ($data && $data['Is_Deleted'] == 1) {
            header("Location: list.php?status_sukses=error&message=Paket+sudah+berada+dalam+arsip!");
            exit();
        }

        $sql = "{CALL sp_DeletePaketFoto(?, ?)}";
        $stmt = sqlsrv_query($conn, $sql, [$id, $nama_admin]);

        if ($stmt) {
            header("Location: list.php?status_sukses=soft_delete");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+mengarsipkan+paket");
        }
        exit();
        break;

    case 'restore':
        $cek_sql = "SELECT Is_Deleted FROM Paket_Foto WHERE ID_Paket = ?";
        $cek_stmt = sqlsrv_query($conn, $cek_sql, [$id]);
        $data = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

        if (!$data || $data['Is_Deleted'] == 0) {
            header("Location: list.php?status_sukses=error&message=Paket+tidak+sedang+diarsipkan!");
            exit();
        }

        $sql = "UPDATE Paket_Foto SET 
                Is_Deleted = 0, 
                Deleted_By = NULL, 
                Deleted_Date = NULL,
                Modified_By = ?,
                Modified_Date = GETDATE()
                WHERE ID_Paket = ?";

        $stmt = sqlsrv_query($conn, $sql, [$nama_admin, $id]);

        if ($stmt) {
            header("Location: list.php?status_sukses=restore");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+memulihkan+paket");
        }
        exit();
        break;

    case 'hard_delete':
        if (hasOrder($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+memiliki+riwayat+transaksi+booking+pelanggan.");
            exit();
        }

        if (hasRuangan($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+terikat+dengan+Ruangan+Studio.");
            exit();
        }

        if (hasJadwal($conn, $id)) {
            header("Location: list.php?status_sukses=error&message=Gagal+Hapus!+Paket+ini+terikat+dengan+Jadwal+Studio.");
            exit();
        }

        $foto_name = getFotoPaket($conn, $id);
        if ($foto_name != 'default_paket.jpg') {
            $foto_path = "../../assets/img/paket/" . $foto_name;
            if (file_exists($foto_path)) {
                @unlink($foto_path);
            }
        }

        $sql_delete = "DELETE FROM Paket_Foto WHERE ID_Paket = ?";
        $stmt_delete = sqlsrv_query($conn, $sql_delete, [$id]);

        if ($stmt_delete) {
            header("Location: list.php?status_sukses=hard_delete");
        } else {
            header("Location: list.php?status_sukses=error&message=Gagal+menghapus+paket+permanen");
        }
        exit();
        break;

    default:
        if (!headers_sent()) {
            header("Location: list.php?status_sukses=error&message=Aksi+tidak+valid");
            exit();
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting - SpotLight Studio</title>
    <meta http-equiv="refresh" content="0;url=list.php?status_sukses=error&message=Aksi+tidak+valid">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .box {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 90%;
            width: 400px;
            border: 1px solid #FFE4E9;
        }
        .box h1 { color: #D53D66; font-size: 1.5rem; margin-bottom: 12px; }
        .box p { color: #718096; margin-bottom: 20px; line-height: 1.5; font-size: 0.95rem; }
        .box a {
            display: inline-block;
            background: linear-gradient(135deg, #D53D66, #CA3366);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 14px;
            transition: all 0.3s ease;
        }
        .box a:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,0.3); }
        @media (max-width: 576px) {
            .box { padding: 30px 20px; border-radius: 20px; }
            .box h1 { font-size: 1.25rem; }
            .box p { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>SpotLight Studio</h1>
        <p>Memproses aksi, silakan tunggu...</p>
        <a href="list.php?status_sukses=error&message=Aksi+tidak+valid">Klik di sini jika tidak dialihkan</a>
    </div>
</body>
</html>