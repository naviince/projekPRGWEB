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

// =====================================================
// VALIDASI INPUT
// =====================================================
if (!isset($_POST['id_barang']) || empty($_POST['id_barang'])) {
    echo json_encode(['success' => false, 'message' => 'ID barang tidak valid']);
    exit();
}

$id_barang = (int)$_POST['id_barang'];
$hapus = isset($_POST['hapus']) && $_POST['hapus'] == '1';

// =====================================================
// CEK BARANG DI DATABASE
// =====================================================
$q_barang = sqlsrv_query($conn, 
    "SELECT ID_Barang, Nama_Barang, Harga_Barang, Stok_Barang 
     FROM Barang_Cetak WHERE ID_Barang = ? AND Is_Deleted = 0 AND Status = ?",
    array($id_barang, STATUS_DATA_AKTIF)
);

if ($q_barang === false) {
    echo json_encode(['success' => false, 'message' => 'Error database']);
    exit();
}

$d_barang = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC);

if (!$d_barang) {
    echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
    exit();
}

$harga = (float)$d_barang['Harga_Barang'];
$stok = (int)$d_barang['Stok_Barang'];

// =====================================================
// PROSES HAPUS
// =====================================================
if ($hapus) {
    if (isset($_SESSION['keranjang_barang'][$id_barang])) {
        unset($_SESSION['keranjang_barang'][$id_barang]);
    }

    // Hitung ulang total
    $total_item = 0;
    $total_harga = 0;
    foreach ($_SESSION['keranjang_barang'] as $item) {
        $total_item += (int)$item['jumlah'];
        $total_harga += (int)$item['jumlah'] * (float)$item['harga'];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Barang berhasil dihapus',
        'total_item' => $total_item,
        'total' => $total_harga,
        'total_format' => number_format($total_harga, 0, ',', '.')
    ]);
    exit();
}

// =====================================================
// PROSES UPDATE QTY
// =====================================================
if (!isset($_POST['jumlah']) || empty($_POST['jumlah'])) {
    echo json_encode(['success' => false, 'message' => 'Jumlah tidak valid']);
    exit();
}

$jumlah = (int)$_POST['jumlah'];

if ($jumlah < 1) {
    echo json_encode(['success' => false, 'message' => 'Jumlah minimal 1']);
    exit();
}

if ($jumlah > $stok) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak cukup. Tersisa: ' . $stok]);
    exit();
}

// Update session
if (!isset($_SESSION['keranjang_barang'])) {
    $_SESSION['keranjang_barang'] = [];
}

$_SESSION['keranjang_barang'][$id_barang]['jumlah'] = $jumlah;
$_SESSION['keranjang_barang'][$id_barang]['harga'] = $harga;

// Hitung subtotal barang ini
$subtotal = $jumlah * $harga;

// Hitung ulang total semua
$total_item = 0;
$total_harga = 0;
foreach ($_SESSION['keranjang_barang'] as $item) {
    $total_item += (int)$item['jumlah'];
    $total_harga += (int)$item['jumlah'] * (float)$item['harga'];
}

echo json_encode([
    'success' => true,
    'message' => 'Jumlah berhasil diupdate',
    'subtotal' => $subtotal,
    'subtotal_format' => number_format($subtotal, 0, ',', '.'),
    'total_item' => $total_item,
    'total' => $total_harga,
    'total_format' => number_format($total_harga, 0, ',', '.')
]);
?>