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
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL DATA DARI URL (MENDUKUNG MULTI-SLOT JADWAL)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) ||
    !isset($_GET['id_tema']) || empty($_GET['id_tema']) ||
    !isset($_GET['id_jadwal']) || empty($_GET['id_jadwal'])) {
    header("Location: ../../index.php?error=data_tidak_lengkap");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema = (int)$_GET['id_tema'];
$id_jadwal_raw = trim($_GET['id_jadwal']);

// Tangkap opsi tipe pembayaran dari konfirmasi.php (DP atau Lunas)
$tipe_pembayaran_opt = isset($_GET['tipe_pembayaran']) ? trim($_GET['tipe_pembayaran']) : 'DP';
if ($tipe_pembayaran_opt !== 'DP' && $tipe_pembayaran_opt !== 'Lunas') {
    $tipe_pembayaran_opt = 'DP';
}

// Mengurai multi-slot ID jadwal ke dalam array sanitasi
$id_jadwal_arr = array_map('intval', explode(',', $id_jadwal_raw));
$jumlah_slot = count($id_jadwal_arr);
$placeholders_cek = implode(',', array_fill(0, $jumlah_slot, '?'));

// =====================================================
// AMBIL DATA PAKET (untuk harga)
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT Nama_Paket, Harga_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", 
    array($id_paket, STATUS_DATA_AKTIF)
);
if ($q_paket === false) {
    die("Error query Paket: " . print_r(sqlsrv_errors(), true));
}
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

$harga_paket = (float)$d_paket['Harga_Paket'];

// =====================================================
// VALIDASI JADWAL-JADWAL: MASIH TERSEDIA? (SINKRONISASI BANYAK JADWAL)
// =====================================================
$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal, ID_Ruangan, Status_Jadwal 
     FROM Jadwal_Studio 
     WHERE ID_Jadwal IN ($placeholders_cek) AND Status = 1 AND Is_Deleted = 0", 
    $id_jadwal_arr
);
if ($q_jadwal === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}

$schedules_found = [];
while ($row_j = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
    $schedules_found[] = $row_j;
}

if (count($schedules_found) !== $jumlah_slot) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_ditemukan");
    exit();
}

// Validasi masing-masing jadwal fisik ruangan dan ketersediaan
foreach ($schedules_found as $row_j) {
    if ((int)$row_j['ID_Ruangan'] !== $id_ruangan) {
        header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_valid");
        exit();
    }
    if ((int)$row_j['Status_Jadwal'] !== STATUS_JADWAL_TERSEDIA) {
        header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_sudah_dibooking");
        exit();
    }
}

// =====================================================
// VALIDASI RELASI PAKET-RUANGAN & RUANGAN-TEMA (SINKRONISASI JUNCTION)
// =====================================================
$q_validasi_pr = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", 
    array($id_paket, $id_ruangan)
);
if ($q_validasi_pr === false) {
    die("Error query Validasi PR: " . print_r(sqlsrv_errors(), true));
}
$d_validasi_pr = sqlsrv_fetch_array($q_validasi_pr, SQLSRV_FETCH_ASSOC);
if ($d_validasi_pr['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

$q_validasi_rt = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ? AND ID_Tema = ?", 
    array($id_ruangan, $id_tema)
);
if ($q_validasi_rt === false) {
    die("Error query Validasi RT: " . print_r(sqlsrv_errors(), true));
}
$d_validasi_rt = sqlsrv_fetch_array($q_validasi_rt, SQLSRV_FETCH_ASSOC);
if ($d_validasi_rt['total'] == 0) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// CEK: CUSTOMER SUDAH PUNYA ORDER AKTIF DI JADWAL INI? (MENDUKUNG MULTI-SLOT)
// =====================================================
$sql_cek_customer = "SELECT COUNT(*) as total 
                     FROM [Order] o
                     JOIN Order_Jadwal oj ON o.ID_Order = oj.ID_Order
                     WHERE o.ID_Pelanggan = ? 
                       AND oj.ID_Jadwal IN ($placeholders_cek)
                       AND o.Status = 1 
                       AND o.Status_Order NOT IN (?)";

$params_cek_customer = array_merge([$id_customer], $id_jadwal_arr, [STATUS_ORDER_DIBATALKAN]);
$q_cek_customer = sqlsrv_query($conn, $sql_cek_customer, $params_cek_customer);
if ($q_cek_customer === false) {
    die("Error cek customer: " . print_r(sqlsrv_errors(), true));
}
$d_cek_customer = sqlsrv_fetch_array($q_cek_customer, SQLSRV_FETCH_ASSOC);
if (($d_cek_customer['total'] ?? 0) > 0) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=anda_sudah_booking_jadwal_ini");
    exit();
}

// =====================================================
// SIMPAN ORDER KE DATABASE (DENGAN TRANSACTION)
// =====================================================
// BEGIN TRANSACTION
if (!sqlsrv_begin_transaction($conn)) {
    die("Error begin transaction: " . print_r(sqlsrv_errors(), true));
}

try {
    // Hitung total harga paket dasar secara kumulatif dikalikan jumlah sesi yang dibooking
    $total_paket_final = $harga_paket * $jumlah_slot;

    // Insert order utama (Kolom ID_Jadwal ditiadakan dari kueri tabel Order)
    $sql_insert_order = "INSERT INTO [Order] (ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, Total_Paket, Total_Barang_Cetak, Status_Order, Status, Created_By)
                         OUTPUT INSERTED.ID_Order
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params_insert_order = [
        $id_customer,
        $id_paket,
        $id_ruangan,
        $id_tema,
        $total_paket_final,
        0, // Total_Barang_Cetak
        STATUS_ORDER_MENUNGGU_DP,
        STATUS_DATA_AKTIF,
        'customer'
    ];
    $q_insert_order = sqlsrv_query($conn, $sql_insert_order, $params_insert_order);

    if ($q_insert_order === false) {
        throw new Exception("Error insert order utama: " . print_r(sqlsrv_errors(), true));
    }

    // Ambil ID_Order yang baru dibuat
    $d_new_order = sqlsrv_fetch_array($q_insert_order, SQLSRV_FETCH_ASSOC);
    $id_order = (int)$d_new_order['ID_Order'];
    sqlsrv_free_stmt($q_insert_order);

    if (empty($id_order) || $id_order === 0) {
        throw new Exception("Error: ID_Order kosong. Order tidak berhasil dibuat.");
    }

    // Hubungkan setiap jadwal studio yang dipesan ke tabel junction Order_Jadwal
    foreach ($id_jadwal_arr as $js_id) {
        $sql_oj = "INSERT INTO Order_Jadwal (ID_Order, ID_Jadwal) VALUES (?, ?)";
        $q_oj = sqlsrv_query($conn, $sql_oj, [$id_order, $js_id]);
        if ($q_oj === false) {
            throw new Exception("Error insert hubungan Order_Jadwal: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($q_oj);
    }

    // =====================================================
    // SINKRONISASI: PROSES SIMPAN KERANJANG BARANG CETAK KE DATABASE
    // =====================================================
    if (isset($_SESSION['booking_cart_cetak']) && !empty($_SESSION['booking_cart_cetak'])) {
        // 1. Buat data transaksi Penjualan utama
        $q_penjualan = sqlsrv_query($conn, 
            "INSERT INTO Penjualan (ID_Order, ID_Karyawan_Admin, Tanggal_Penjualan, Total_Penjualan, Status_Penjualan, Status, Created_By)
             OUTPUT INSERTED.ID_Penjualan
             VALUES (?, NULL, GETDATE(), 0, 0, 1, 'customer')",
            array($id_order)
        );
        
        if ($q_penjualan === false) {
            throw new Exception("Error insert data Penjualan: " . print_r(sqlsrv_errors(), true));
        }
        
        $d_penjualan = sqlsrv_fetch_array($q_penjualan, SQLSRV_FETCH_ASSOC);
        $id_penjualan = (int)$d_penjualan['ID_Penjualan'];
        sqlsrv_free_stmt($q_penjualan);
        
        // 2. Simpan setiap rincian barang cetak yang dibeli pelanggan
        foreach ($_SESSION['booking_cart_cetak'] as $id_brg => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                // Ambil harga ter-update dari database master
                $q_price = sqlsrv_query($conn, 
                    "SELECT Harga_Barang FROM Barang_Cetak WHERE ID_Barang = ? AND Status = 1 AND Is_Deleted = 0", 
                    array($id_brg)
                );
                if ($q_price === false) {
                    throw new Exception("Error query harga barang: " . print_r(sqlsrv_errors(), true));
                }
                $d_price = sqlsrv_fetch_array($q_price, SQLSRV_FETCH_ASSOC);
                $harga_satuan = (float)($d_price['Harga_Barang'] ?? 0);
                sqlsrv_free_stmt($q_price);
                
                // Masukkan ke detail penjualan (Otomatis memicu DB Trigger tr_DetailPenjualan_Insert)
                $q_insert_detail = sqlsrv_query($conn, 
                    "INSERT INTO Detail_Penjualan_Barang_Cetak (ID_Penjualan, ID_Barang, Jumlah, Harga_Satuan)
                     VALUES (?, ?, ?, ?)",
                    array($id_penjualan, $id_brg, $qty, $harga_satuan)
                );
                
                if ($q_insert_detail === false) {
                    throw new Exception("Error insert detail penjualan: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($q_insert_detail);
            }
        }
        
        // Bersihkan data keranjang cetak di sesi
        unset($_SESSION['booking_cart_cetak']);
    }

    // Update seluruh status jadwal terpilih menjadi booked di tabel master Jadwal_Studio
    foreach ($id_jadwal_arr as $js_id) {
        $q_update_jadwal = sqlsrv_query($conn, 
            "UPDATE Jadwal_Studio SET Status_Jadwal = ? WHERE ID_Jadwal = ?",
            array(STATUS_JADWAL_BOOKED, $js_id)
        );

        if ($q_update_jadwal === false) {
            throw new Exception("Error update status booked jadwal ID {$js_id}: " . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($q_update_jadwal);
    }

    // Bersihkan sesi keranjang jadwal karena kueri pemesanan sukses dijalankan
    unset($_SESSION['booking_cart_jadwal']);

    // COMMIT TRANSACTION JIKA SEMUA KUERI BERHASIL TANPA ERROR
    sqlsrv_commit($conn);

} catch (Exception $e) {
    // ROLLBACK TRANSACTION JIKA TERJADI ERROR DI SALAH SATU PROSES
    sqlsrv_rollback($conn);
    die($e->getMessage());
}

// =====================================================
// AMBIL ULANG HARGA FINAL SETELAH TERPOTONG DISKON CETAK 5%
// =====================================================
$q_get_total = sqlsrv_query($conn, "SELECT Total_Harga, Total_Barang_Cetak FROM [Order] WHERE ID_Order = ?", array($id_order));
$d_total = sqlsrv_fetch_array($q_get_total, SQLSRV_FETCH_ASSOC);
$total_harga_db = (float)($d_total['Total_Harga'] ?? $total_paket_final);
$total_cetak_db = (float)($d_total['Total_Barang_Cetak'] ?? 0);
sqlsrv_free_stmt($q_get_total);

// Potongan harga spesial 5% khusus produk cetak
$diskon_cetak_db = 0;
if ($total_cetak_db > 0) {
    $diskon_cetak_db = $total_cetak_db * 0.05; // 5% [2]
}
$total_harga_sebenarnya = $total_paket_final + ($total_cetak_db - $diskon_cetak_db);

// =====================================================
// SIMPAN DATA KEUANGAN KE SESSION SINKRON (untuk halaman pembayaran)
// =====================================================
$_SESSION['order_id'] = $id_order;
$_SESSION['order_harga'] = $total_harga_sebenarnya;
$_SESSION['order_tipe_bayar'] = $tipe_pembayaran_opt;

if ($tipe_pembayaran_opt === 'Lunas') {
    $_SESSION['order_dp'] = $total_harga_sebenarnya; // Kewajiban bayar penuh (100%)
    $_SESSION['order_sisa'] = 0;
} else {
    $_SESSION['order_dp'] = $total_harga_sebenarnya * 0.65; // Kewajiban bayar uang muka (65%)
    $_SESSION['order_sisa'] = $total_harga_sebenarnya * 0.35;
}

// =====================================================
// REDIRECT KE HALAMAN PEMBAYARAN DP DENGAN URL ORDER TERBARU
// =====================================================
header("Location: ../../Booking/Pembayaran/pembayaran_dp.php?id_order=$id_order&success=order_berhasil");
exit();
?>