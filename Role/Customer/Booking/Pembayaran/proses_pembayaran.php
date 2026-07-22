<?php
session_start();
include '../../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat) agar deteksi jam lampau akurat
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

// =====================================================
// VALIDASI: HANYA METHOD POST YANG DITERIMA
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.php?error=metode_tidak_valid");
    exit();
}

$id_customer = $_SESSION['id_user'];
$id_order = (int)($_POST['id_order'] ?? 0);
$metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
$jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);

// =====================================================
// VALIDASI: METODE PEMBAYARAN WAJIB DIPILIH
// =====================================================
if (empty($metode_pembayaran)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&error=metode_pembayaran_wajib_dipilih");
    exit();
}

// =====================================================
// VALIDASI: FILE BUKTI WAJIB DIUPLOAD
// =====================================================
if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_NO_FILE) {
    header("Location: pembayaran_dp.php?id_order=$id_order&error=bukti_transfer_wajib_diupload");
    exit();
}

// =====================================================
// AMBIL TIPE PEMBAYARAN (DP / LUNAS)
// =====================================================
$tipe_bayar_opt = 'DP';
if (isset($_SESSION['order_tipe_bayar']) && $_SESSION['order_id'] == $id_order) {
    $tipe_bayar_opt = $_SESSION['order_tipe_bayar'];
} elseif (isset($_POST['tipe_pembayaran'])) {
    $tipe_bayar_opt = trim($_POST['tipe_pembayaran']) === 'Lunas' ? 'Lunas' : 'DP';
}

// =====================================================
// VALIDASI ORDER
// =====================================================
$q_order = sqlsrv_query($conn, 
    "SELECT ID_Order, Status_Order, Total_Paket, Total_Barang_Cetak FROM [Order] 
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
// TENTUKAN TIPE PEMBAYARAN UNTUK DATABASE
// DP (65%)    -> Tipe_Pembayaran = 'DP'
// Bayar Lunas -> Tipe_Pembayaran = 'Pelunasan'
// =====================================================
$tipe_pembayaran_db = ($tipe_bayar_opt === 'Lunas') ? 'Pelunasan' : 'DP';

// =====================================================
// VALIDASI NOMINAL PEMBAYARAN
// =====================================================
$total_paket = (float)($d_order['Total_Paket'] ?? 0);
$total_cetak = (float)($d_order['Total_Barang_Cetak'] ?? 0);
$diskon_cetak = $total_cetak > 0 ? $total_cetak * 0.05 : 0;
$total_harga_diskon = $total_paket + ($total_cetak - $diskon_cetak);

$nominal_seharusnya = ($tipe_pembayaran_db === 'Pelunasan')
    ? $total_harga_diskon
    : ($total_harga_diskon * 0.65);

// Toleransi pembulatan relatif (0.5% dari total, min Rp1, maks Rp100)
$toleransi = max(1, min(100, $total_harga_diskon * 0.005));

if ($total_harga_diskon <= 0 || abs($jumlah_bayar - $nominal_seharusnya) > $toleransi) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=nominal_tidak_sesuai");
    exit();
}

// =====================================================
// CEK SUDAH ADA PEMBAYARAN
// =====================================================
$q_cek = sqlsrv_query($conn, 
    "SELECT ID_Pembayaran FROM Pembayaran 
     WHERE ID_Order = ? AND Tipe_Pembayaran IN ('DP', 'Pelunasan') AND Status = 1",
    array($id_order)
);
if (sqlsrv_fetch_array($q_cek, SQLSRV_FETCH_ASSOC)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=sudah_upload_dp");
    exit();
}

// =====================================================
// VALIDASI FILE UPLOAD
// =====================================================
$file = $_FILES['bukti_transfer'];

// Validasi error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'gagal_upload_file';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error_msg = 'file_terlalu_besar';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_msg = 'file_upload_tidak_lengkap';
            break;
    }
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=$error_msg");
    exit();
}

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
// VALIDASI MIME TYPE (KEAMANAN TAMBAHAN)
// =====================================================
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

$allowed_mime = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'application/pdf' => ['pdf']
];

$mime_valid = false;
foreach ($allowed_mime as $mime => $exts) {
    if ($mime === $mime_type && in_array($file_ext, $exts)) {
        $mime_valid = true;
        break;
    }
}

if (!$mime_valid) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=format_file_tidak_valid");
    exit();
}

// =====================================================
// UPLOAD FILE
// =====================================================
$upload_dir = '../../../../assets/img/bukti/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Sanitasi nama file
$prefix_file = ($tipe_pembayaran_db === 'Pelunasan') ? 'bukti_lunas_' : 'bukti_dp_';
$safe_id_order = preg_replace('/[^0-9]/', '', (string)$id_order);
$file_baru = $prefix_file . $safe_id_order . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
$target_path = $upload_dir . $file_baru;

if (!move_uploaded_file($file_tmp, $target_path)) {
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=gagal_upload_file");
    exit();
}

// =====================================================
// INSERT KE TABEL PEMBAYARAN (DENGAN TRANSACTION)
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    // Hapus file fisik jika transaction gagal dimulai
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    header("Location: pembayaran_dp.php?id_order=$id_order&tipe_bayar=$tipe_bayar_opt&error=gagal_transaction");
    exit();
}

try {
    $q_insert = sqlsrv_query($conn, 
        "INSERT INTO Pembayaran (ID_Order, Tipe_Pembayaran, Metode_Pembayaran, Jumlah_Bayar, Bukti_Transfer, Tanggal_Upload, Status_Pembayaran, Status, Created_By, Created_Date)
         VALUES (?, ?, ?, ?, ?, GETDATE(), ?, ?, ?, GETDATE())",
        array(
            $id_order,
            $tipe_pembayaran_db,
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