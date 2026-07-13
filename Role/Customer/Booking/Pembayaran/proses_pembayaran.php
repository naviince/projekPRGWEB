<?php
session_start();
include '../../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat) agar deteksi jam lampau akurat
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pembayaran_dp.php?error=metode_tidak_valid");
    exit();
}

$id_customer = $_SESSION['id_user'];
$id_order = (int)($_POST['id_order'] ?? 0);
$metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
$jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);

// Ambil pilihan tipe pembayaran (DP / Lunas) untuk melestarikan status halaman pembayaran_dp.php setelah redirect
$tipe_bayar_opt = 'DP';
if (isset($_SESSION['order_tipe_bayar']) && $_SESSION['order_id'] == $id_order) {
    $tipe_bayar_opt = $_SESSION['order_tipe_bayar'];
} elseif (isset($_POST['tipe_bayar'])) {
    $tipe_bayar_opt = trim($_POST['tipe_bayar']) === 'Lunas' ? 'Lunas' : 'DP';
}

if ($id_order <= 0 || empty($metode_pembayaran) || $jumlah_bayar <= 0) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=data_tidak_lengkap");
    exit();
}

// =====================================================
// VALIDASI ORDER
// =====================================================
$q_order = sqlsrv_query($conn, 
    "SELECT ID_Order, Status_Order FROM [Order] 
     WHERE ID_Order = ? AND ID_Pelanggan = ? AND Status = 1",
    array($id_order, $id_customer)
);
$d_order = sqlsrv_fetch_array($q_order, SQLSRV_FETCH_ASSOC);

if (!$d_order) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=order_tidak_valid");
    exit();
}

if ((int)$d_order['Status_Order'] !== STATUS_ORDER_MENUNGGU_DP) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=order_sudah_diproses");
    exit();
}

// =====================================================
// CEK SUDAH ADA PEMBAYARAN DP
// =====================================================
$q_cek = sqlsrv_query($conn, 
    "SELECT ID_Pembayaran FROM Pembayaran 
     WHERE ID_Order = ? AND Tipe_Pembayaran = 'DP' AND Status = 1",
    array($id_order)
);
if (sqlsrv_fetch_array($q_cek, SQLSRV_FETCH_ASSOC)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=sudah_upload_dp");
    exit();
}

// =====================================================
// VALIDASI FILE UPLOAD
// =====================================================
if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=file_wajib_diupload");
    exit();
}

$file = $_FILES['bukti_transfer'];
$file_name = $file['name'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file_ext, $allowed_ext)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=format_file_tidak_valid");
    exit();
}

if ($file_size > $max_size) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=file_terlalu_besar");
    exit();
}

// =====================================================
// UPLOAD FILE
// =====================================================
$upload_dir = '../../../../assets/img/bukti/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_baru = 'bukti_dp_' . $id_order . '_' . time() . '_' . uniqid() . '.' . $file_ext;
$target_path = $upload_dir . $file_baru;

if (!move_uploaded_file($file_tmp, $target_path)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=gagal_upload_file");
    exit();
}

// =====================================================
// INSERT KE TABEL PEMBAYARAN (DENGAN TRANSACTION)
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=gagal_transaction");
    exit();
}

try {
    $q_insert = sqlsrv_query($conn, 
        "INSERT INTO Pembayaran (ID_Order, Tipe_Pembayaran, Metode_Pembayaran, Jumlah_Bayar, Bukti_Transfer, Tanggal_Upload, Status_Pembayaran, Status, Created_By, Created_Date)
         VALUES (?, 'DP', ?, ?, ?, GETDATE(), ?, ?, ?, GETDATE())",
        array(
            $id_order,
            $metode_pembayaran,
            $jumlah_bayar,
            $file_baru,
            STATUS_PEMBAYARAN_MENUNGGU,
            STATUS_DATA_AKTIF,
            $_SESSION['username'] ?? 'customer'
        )
    );

    if (!$q_insert) {
        throw new Exception("Gagal insert pembayaran: " . print_r(sqlsrv_errors(), true));
    }

    sqlsrv_commit($conn);
    // Redirect sukses dengan melestarikan tipe pembayaran pilihan
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&success=upload_berhasil");

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    // Hapus file fisik yang terlanjur diunggah demi mencegah sampah file di storage server
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=" . urlencode($e->getMessage()));
}
exit();
?>