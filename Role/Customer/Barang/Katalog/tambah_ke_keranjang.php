<?php
session_start();
include '../../../../koneksi.php';

header('Content-Type: application/json');

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// VALIDASI INPUT
// =====================================================
if (!isset($_POST['id_barang']) || empty($_POST['id_barang']) ||
    !isset($_POST['jumlah']) || empty($_POST['jumlah']) ||
    !isset($_POST['harga']) || empty($_POST['harga'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

$id_barang = (int)$_POST['id_barang'];
$jumlah = (int)$_POST['jumlah'];
$harga = (float)$_POST['harga'];

if ($id_barang <= 0 || $jumlah <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit();
}

// =====================================================
// CEK BARANG DI DATABASE (validasi stok)
// =====================================================
$q_barang = sqlsrv_query($conn, 
    "SELECT ID_Barang, Nama_Barang, Harga_Barang, Stok_Barang, Status, Is_Deleted 
     FROM Barang_Cetak WHERE ID_Barang = ?",
    array($id_barang)
);

if ($q_barang === false) {
    echo json_encode(['success' => false, 'message' => 'Error database: ' . print_r(sqlsrv_errors(), true)]);
    exit();
}

$d_barang = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC);

if (!$d_barang) {
    echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
    exit();
}

if ($d_barang['Is_Deleted'] == 1 || $d_barang['Status'] != STATUS_DATA_AKTIF) {
    echo json_encode(['success' => false, 'message' => 'Barang tidak tersedia']);
    exit();
}

$stok_tersedia = (int)$d_barang['Stok_Barang'];

// =====================================================
// HITUNG TOTAL YANG SUDAH DI KERANJANG
// =====================================================
$jumlah_di_keranjang = 0;
if (isset($_SESSION['keranjang_barang'][$id_barang])) {
    $jumlah_di_keranjang = (int)$_SESSION['keranjang_barang'][$id_barang]['jumlah'];
}

$total_yang_diminta = $jumlah_di_keranjang + $jumlah;

if ($total_yang_diminta > $stok_tersedia) {
    $sisa = $stok_tersedia - $jumlah_di_keranjang;
    echo json_encode([
        'success' => false, 
        'message' => 'Stok tidak cukup. Stok tersisa: ' . $stok_tersedia . ', sudah di keranjang: ' . $jumlah_di_keranjang . ', bisa ditambah: ' . max(0, $sisa)
    ]);
    exit();
}

// =====================================================
// TAMBAH KE SESSION KERANJANG
// =====================================================
if (!isset($_SESSION['keranjang_barang'])) {
    $_SESSION['keranjang_barang'] = [];
}

if (isset($_SESSION['keranjang_barang'][$id_barang])) {
    $_SESSION['keranjang_barang'][$id_barang]['jumlah'] += $jumlah;
} else {
    $_SESSION['keranjang_barang'][$id_barang] = [
        'id_barang' => $id_barang,
        'jumlah' => $jumlah,
        'harga' => $harga,
        'tanggal_tambah' => date('Y-m-d H:i:s')
    ];
}

// Hitung total item
$total_item = 0;
foreach ($_SESSION['keranjang_barang'] as $item) {
    $total_item += (int)$item['jumlah'];
}

echo json_encode([
    'success' => true,
    'message' => 'Barang berhasil ditambahkan ke keranjang',
    'total_item' => $total_item,
    'nama_barang' => $d_barang['Nama_Barang']
]);
?>