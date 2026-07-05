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
// AMBIL DATA DARI URL (WAJIB ADA)
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
$id_jadwal = (int)$_GET['id_jadwal'];

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
// VALIDASI JADWAL: MASIH TERSEDIA?
// =====================================================
$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal, ID_Ruangan, ID_Paket, Status_Jadwal 
     FROM Jadwal_Studio 
     WHERE ID_Jadwal = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_jadwal)
);
if ($q_jadwal === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}
$d_jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC);

if (!$d_jadwal) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_ditemukan");
    exit();
}

// Validasi: jadwal harus untuk ruangan dan paket yang benar
if ((int)$d_jadwal['ID_Ruangan'] !== $id_ruangan || (int)$d_jadwal['ID_Paket'] !== $id_paket) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_valid");
    exit();
}

// Validasi: jadwal harus tersedia
if ((int)$d_jadwal['Status_Jadwal'] !== STATUS_JADWAL_TERSEDIA) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_sudah_dibooking");
    exit();
}

// =====================================================
// VALIDASI RELASI PAKET-RUANGAN & RUANGAN-TEMA
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
// CEK: CUSTOMER SUDAH PUNYA ORDER AKTIF DI JADWAL INI?
// =====================================================
$q_cek_customer = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM [Order] 
     WHERE ID_Pelanggan = ? AND ID_Jadwal = ? 
       AND Status = 1 AND Status_Order NOT IN (?)",
    array($id_customer, $id_jadwal, STATUS_ORDER_DIBATALKAN)
);
if ($q_cek_customer === false) {
    die("Error cek customer: " . print_r(sqlsrv_errors(), true));
}
$d_cek_customer = sqlsrv_fetch_array($q_cek_customer, SQLSRV_FETCH_ASSOC);
if ($d_cek_customer['total'] > 0) {
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
    // Insert order utama
    $q_insert_order = sqlsrv_query($conn, 
        "INSERT INTO [Order] (ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, ID_Jadwal, Total_Paket, Total_Barang_Cetak, Status_Order, Status, Created_By)
         OUTPUT INSERTED.ID_Order
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(
            $id_customer,
            $id_paket,
            $id_ruangan,
            $id_tema,
            $id_jadwal,
            $harga_paket,   // Total_Paket = harga paket dasar
            0,              // Total_Barang_Cetak diisi 0 dulu, akan diperbarui otomatis oleh DB trigger setelah detail barang dimasukkan
            STATUS_ORDER_MENUNGGU_DP,
            STATUS_DATA_AKTIF,
            'customer'
        )
    );

    if ($q_insert_order === false) {
        throw new Exception("Error insert order: " . print_r(sqlsrv_errors(), true));
    }

    // Ambil ID_Order yang baru dibuat
    $d_new_order = sqlsrv_fetch_array($q_insert_order, SQLSRV_FETCH_ASSOC);
    $id_order = (int)$d_new_order['ID_Order'];
    sqlsrv_free_stmt($q_insert_order);

    if (empty($id_order) || $id_order === 0) {
        throw new Exception("Error: ID_Order kosong. Order tidak berhasil dibuat.");
    }

    // =====================================================
    // SINKRONISASI: PROSES SIMPAN KERANJANG BARANG CETAK KE DATABASE [2]
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
                
                // Masukkan ke detail penjualan (Akan otomatis memicu DB Trigger tr_DetailPenjualan_Insert untuk mengurangi stok dan menghitung total harga di tabel Order)
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
        
        // Bersihkan data keranjang cetak di sesi karena sudah sukses terekam permanen di database
        unset($_SESSION['booking_cart_cetak']);
    }

    // Update jadwal menjadi booked
    $q_update_jadwal = sqlsrv_query($conn, 
        "UPDATE Jadwal_Studio SET Status_Jadwal = ? WHERE ID_Jadwal = ?",
        array(STATUS_JADWAL_BOOKED, $id_jadwal)
    );

    if ($q_update_jadwal === false) {
        throw new Exception("Error update jadwal: " . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($q_update_jadwal);

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
$total_harga_db = (float)($d_total['Total_Harga'] ?? $harga_paket);
$total_cetak_db = (float)($d_total['Total_Barang_Cetak'] ?? 0);
sqlsrv_free_stmt($q_get_total);

// Potongan harga spesial 5% khusus produk cetak
$diskon_cetak_db = 0;
if ($total_cetak_db > 0) {
    $diskon_cetak_db = $total_cetak_db * 0.05; // 5% [2]
}
$total_harga_sebenarnya = $harga_paket + ($total_cetak_db - $diskon_cetak_db);

// =====================================================
// SIMPAN KE SESSION SINKRON (untuk halaman pembayaran)
// =====================================================
$_SESSION['order_id'] = $id_order;
$_SESSION['order_harga'] = $total_harga_sebenarnya;
$_SESSION['order_dp'] = $total_harga_sebenarnya * 0.65;
$_SESSION['order_sisa'] = $total_harga_sebenarnya * 0.35;

// =====================================================
// REDIRECT KE HALAMAN PEMBAYARAN DP DENGAN URL ORDER TERBARU
// =====================================================
header("Location: ../../Booking/Pembayaran/pembayaran_dp.php?id_order=$id_order&success=order_berhasil");
exit();
?>