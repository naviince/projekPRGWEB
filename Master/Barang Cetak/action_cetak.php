<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$act = $_GET['act'] ?? '';
$admin_name = $_SESSION['nama'] ?? 'Admin'; // Mengambil nama admin dari session

if ($act == 'insert' || $act == 'update') {
    // Ambil data dari POST
    $nama      = trim($_POST['nama']);
    $harga     = $_POST['harga'];
    $stok      = $_POST['stok'];
    $stok_min  = $_POST['stok_min'];
    $deskripsi = trim($_POST['deskripsi']);
    $status    = (int)$_POST['status'];

    // --- PROSES UPLOAD FOTO ---
    $foto_baru = "";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = "../../assets/img/barang/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_baru = "brg_" . time() . "_" . uniqid() . "." . $file_ext;
        
        move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_baru);
    } else {
        // Jika update dan tidak ganti foto, pakai foto lama
        $foto_baru = $_POST['foto_lama'] ?? "";
    }

    if ($act == 'insert') {
        // Query INSERT sesuai screenshot kolom database Anda
        $sql = "INSERT INTO Barang_Cetak (
                    Nama_Barang, Deskripsi, Harga_Barang, Stok_Barang, 
                    Stok_Minimum, Foto_Barang, Status, Is_Deleted, 
                    Created_By, Created_Date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, GETDATE())";
        
        $params = [$nama, $deskripsi, $harga, $stok, $stok_min, $foto_baru, $status, $admin_name];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: list.php?status_sukses=tambah");
        } else {
            die(print_r(sqlsrv_errors(), true));
        }

    } else if ($act == 'update') {
        $id = $_POST['id'];
        // Query UPDATE
        $sql = "UPDATE Barang_Cetak SET 
                    Nama_Barang = ?, Deskripsi = ?, Harga_Barang = ?, 
                    Stok_Barang = ?, Stok_Minimum = ?, Foto_Barang = ?, 
                    Status = ?, Modified_By = ?, Modified_Date = GETDATE() 
                WHERE ID_Barang = ?";
        
        $params = [$nama, $deskripsi, $harga, $stok, $stok_min, $foto_baru, $status, $admin_name, $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            header("Location: list.php?status_sukses=edit");
        } else {
            die(print_r(sqlsrv_errors(), true));
        }
    }

} else if ($act == 'delete') {
    $id = $_GET['id'];
    // Soft Delete (Is_Deleted = 1)
    $sql = "UPDATE Barang_Cetak SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Barang = ?";
    $stmt = sqlsrv_query($conn, $sql, [$admin_name, $id]);

    if ($stmt) {
        header("Location: list.php?status_sukses=hapus");
    } else {
        die(print_r(sqlsrv_errors(), true));
    }
}
?>