<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// CEK KERANJANG
// =====================================================
$keranjang = $_SESSION['keranjang_barang'] ?? [];

if (empty($keranjang)) {
    header("Location: keranjang.php?error=keranjang_kosong");
    exit();
}

// =====================================================
// CEK BARANG VALID & STOK MASIH CUKUP
// =====================================================
$barang_valid = [];
$total_barang_cetak = 0;

foreach ($keranjang as $id_barang => $item) {
    $q_barang = sqlsrv_query($conn, 
        "SELECT ID_Barang, Nama_Barang, Harga_Barang, Stok_Barang 
         FROM Barang_Cetak WHERE ID_Barang = ? AND Is_Deleted = 0 AND Status = ?",
        array((int)$id_barang, STATUS_DATA_AKTIF)
    );

    if ($q_barang === false) continue;

    $d_barang = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC);
    if (!$d_barang) continue;

    $stok = (int)$d_barang['Stok_Barang'];
    $jumlah = (int)$item['jumlah'];
    $harga = (float)$d_barang['Harga_Barang'];

    if ($jumlah > $stok) {
        header("Location: keranjang.php?error=stok_tidak_cukup&barang=" . urlencode($d_barang['Nama_Barang']));
        exit();
    }

    $subtotal = $jumlah * $harga;
    $total_barang_cetak += $subtotal;

    $barang_valid[] = [
        'id_barang' => (int)$id_barang,
        'jumlah' => $jumlah,
        'harga' => $harga,
        'subtotal' => $subtotal
    ];
}

if (empty($barang_valid)) {
    header("Location: keranjang.php?error=barang_tidak_valid");
    exit();
}

// =====================================================
// CEK APAKAH CUSTOMER PUNYA ORDER AKTIF
// Jika punya, tambahkan barang ke order yang sudah ada
// Jika tidak, buat order baru
// =====================================================
$q_order_aktif = sqlsrv_query($conn, 
    "SELECT TOP 1 ID_Order FROM [Order] 
     WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order = ?
     ORDER BY ID_Order DESC",
    array($id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_MENUNGGU_DP)
);

$d_order = sqlsrv_fetch_array($q_order_aktif, SQLSRV_FETCH_ASSOC);

sqlsrv_begin_transaction($conn);

try {
    if ($d_order) {
        // Update order yang sudah ada
        $id_order = $d_order['ID_Order'];

        // Update Total_Barang_Cetak
        $q_update = sqlsrv_query($conn, 
            "UPDATE [Order] SET Total_Barang_Cetak = Total_Barang_Cetak + ?, Modified_Date = GETDATE() WHERE ID_Order = ?",
            array($total_barang_cetak, $id_order)
        );

        if ($q_update === false) {
            throw new Exception("Gagal update order: " . print_r(sqlsrv_errors(), true));
        }
    } else {
        // Buat order baru (tanpa paket/ruangan/tema/jadwal)
        // Ini adalah order barang cetak standalone
        $q_insert_order = sqlsrv_query($conn, 
            "INSERT INTO [Order] (ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, ID_Jadwal, Tanggal_Booking, Total_Paket, Total_Barang_Cetak, Status_Order, Status, Created_By) 
             OUTPUT INSERTED.ID_Order
             VALUES (?, NULL, NULL, NULL, NULL, GETDATE(), 0, ?, ?, ?, ?)",
            array($id_customer, $total_barang_cetak, STATUS_ORDER_MENUNGGU_DP, STATUS_DATA_AKTIF, 'customer')
        );

        if ($q_insert_order === false) {
            throw new Exception("Gagal insert order: " . print_r(sqlsrv_errors(), true));
        }

        $d_insert = sqlsrv_fetch_array($q_insert_order, SQLSRV_FETCH_ASSOC);
        $id_order = $d_insert['ID_Order'];
    }

    // =====================================================
    // INSERT KE PENJUALAN (header)
    // =====================================================
    $q_penjualan = sqlsrv_query($conn, 
        "INSERT INTO Penjualan (ID_Order, ID_Karyawan_Admin, Tanggal_Penjualan, Total_Penjualan, Status_Penjualan, Status, Created_By)
         OUTPUT INSERTED.ID_Penjualan
         VALUES (?, NULL, GETDATE(), ?, 0, ?, ?)",
        array($id_order, $total_barang_cetak, STATUS_DATA_AKTIF, 'customer')
    );

    if ($q_penjualan === false) {
        throw new Exception("Gagal insert penjualan: " . print_r(sqlsrv_errors(), true));
    }

    $d_penjualan = sqlsrv_fetch_array($q_penjualan, SQLSRV_FETCH_ASSOC);
    $id_penjualan = $d_penjualan['ID_Penjualan'];

    // =====================================================
    // INSERT DETAIL PENJUALAN & KURANGI STOK
    // =====================================================
    foreach ($barang_valid as $barang) {
        // Insert detail
        $q_detail = sqlsrv_query($conn, 
            "INSERT INTO Detail_Penjualan_Barang_Cetak (ID_Penjualan, ID_Barang, Jumlah, Harga_Satuan)
             VALUES (?, ?, ?, ?)",
            array($id_penjualan, $barang['id_barang'], $barang['jumlah'], $barang['harga'])
        );

        if ($q_detail === false) {
            throw new Exception("Gagal insert detail: " . print_r(sqlsrv_errors(), true));
        }

        // Kurangi stok
        $q_stok = sqlsrv_query($conn, 
            "UPDATE Barang_Cetak SET Stok_Barang = Stok_Barang - ? WHERE ID_Barang = ?",
            array($barang['jumlah'], $barang['id_barang'])
        );

        if ($q_stok === false) {
            throw new Exception("Gagal update stok: " . print_r(sqlsrv_errors(), true));
        }
    }

    // Commit transaction
    sqlsrv_commit($conn);

    // Clear keranjang
    unset($_SESSION['keranjang_barang']);

    // Redirect ke halaman pembayaran DP
    header("Location: ../../../Booking/Pembayaran/upload_dp.php?id_order=" . $id_order . "&tipe=barang_cetak");
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    header("Location: keranjang.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>