<?php
session_start();
include '../../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat) agar deteksi jam lampau akurat
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// Ambil parameter order dan redirect
$id_order = isset($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : 'index';

if ($id_order <= 0) {
    header("Location: ../../index.php?error=order_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI KEPEMILIKAN ORDER
// =====================================================
$q_check = sqlsrv_query($conn, 
    "SELECT ID_Order, ID_Paket, ID_Ruangan, ID_Tema, Status_Order FROM [Order] 
     WHERE ID_Order = ? AND ID_Pelanggan = ? AND Status = 1",
    array($id_order, $id_customer)
);
$d_check = sqlsrv_fetch_array($q_check, SQLSRV_FETCH_ASSOC);

if (!$d_check) {
    header("Location: ../../index.php?error=order_tidak_ditemukan");
    exit();
}

// Hanya bisa cancel jika status masih Menunggu DP (0)
if ((int)$d_check['Status_Order'] !== 0) {
    header("Location: ../../index.php?error=order_tidak_bisa_dibatalkan");
    exit();
}

$id_paket = (int)$d_check['ID_Paket'];
$id_ruangan = (int)$d_check['ID_Ruangan'];
$id_tema = (int)$d_check['ID_Tema'];

// =====================================================
// AMBIL ID JADWAL YANG TERKAIT DENGAN ORDER (SEBELUM DIHAPUS)
// =====================================================
$q_jadwal = sqlsrv_query($conn,
    "SELECT ID_Jadwal FROM Order_Jadwal WHERE ID_Order = ?",
    array($id_order)
);

$id_jadwal_list = [];
while ($row = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
    $id_jadwal_list[] = (int)$row['ID_Jadwal'];
}

// =====================================================
// TRANSACTION: CANCEL ORDER + LEPAS JADWAL (SINKRON DATABASE)
// =====================================================
if (!sqlsrv_begin_transaction($conn)) {
    die("Error begin transaction: " . print_r(sqlsrv_errors(), true));
}

try {
    // 1. Update status order menjadi Dibatalkan & Soft Delete (Status = 0)
    // Penyelarasan kolom: Menggunakan Modified_By dan Modified_Date (Bukan Updated_At yang tidak ada di skema database!)
    $q_update_order = sqlsrv_query($conn,
        "UPDATE [Order] 
         SET Status_Order = ?, 
             Status = 0, 
             Modified_By = 'customer', 
             Modified_Date = GETDATE() 
         WHERE ID_Order = ?",
        array(STATUS_ORDER_DIBATALKAN, $id_order)
    );

    if ($q_update_order === false) {
        throw new Exception("Gagal update status order utama");
    }
    sqlsrv_free_stmt($q_update_order);

    // 2. Lepas semua jadwal yang terkait (ubah status jadwal menjadi tersedia di master Jadwal_Studio)
    if (!empty($id_jadwal_list)) {
        $placeholders = implode(',', array_fill(0, count($id_jadwal_list), '?'));
        $q_release_jadwal = sqlsrv_query($conn,
            "UPDATE Jadwal_Studio 
             SET Status_Jadwal = ?, 
                 Modified_By = 'customer', 
                 Modified_Date = GETDATE() 
             WHERE ID_Jadwal IN ($placeholders)",
            array_merge([STATUS_JADWAL_TERSEDIA], $id_jadwal_list)
        );

        if ($q_release_jadwal === false) {
            throw new Exception("Gagal melepas status jadwal studio");
        }
        sqlsrv_free_stmt($q_release_jadwal);
    }

    // 3. Hapus relasi Order_Jadwal
    $q_delete_relasi = sqlsrv_query($conn,
        "DELETE FROM Order_Jadwal WHERE ID_Order = ?",
        array($id_order)
    );

    if ($q_delete_relasi === false) {
        throw new Exception("Gagal menghapus hubungan Order_Jadwal");
    }
    sqlsrv_free_stmt($q_delete_relasi);

    // 4. Update status pembayaran terkait (soft delete)
    $q_update_payment = sqlsrv_query($conn,
        "UPDATE Pembayaran 
         SET Status = 0, 
             Modified_By = 'customer', 
             Modified_Date = GETDATE() 
         WHERE ID_Order = ?",
        array($id_order)
    );

    if ($q_update_payment === false) {
        throw new Exception("Gagal update status pembayaran terkait");
    }
    sqlsrv_free_stmt($q_update_payment);

    // COMMIT SEMUA TRANSAKSI JIKA BERHASIL TANPA ERROR
    sqlsrv_commit($conn);

    // 5. Bersihkan session booking
    if (isset($_SESSION['order_tipe_bayar'])) {
        unset($_SESSION['order_tipe_bayar']);
    }
    if (isset($_SESSION['order_id'])) {
        unset($_SESSION['order_id']);
    }
    if (isset($_SESSION['booking_cart_cetak'])) {
        unset($_SESSION['booking_cart_cetak']);
    }

} catch (Exception $e) {
    // ROLLBACK TRANSAKSI JIKA SALAH SATU PROSES GAGAL
    sqlsrv_rollback($conn);
    header("Location: pembayaran_dp.php?id_order=" . $id_order . "&error=gagal_membatalkan_order");
    exit();
}

// Redirect sesuai parameter
if ($redirect === 'konfirmasi') {
    // Kembali ke halaman konfirmasi dengan parameter yang sama
    $id_jadwal_str = implode(',', $id_jadwal_list);
    // Jalur diperbaiki naik 2 tingkat agar terhindar dari error 404
    header("Location: ../../Layanan/Konfirmasi/konfirmasi.php?id_paket=" . $id_paket . "&id_ruangan=" . $id_ruangan . "&id_tema=" . $id_tema . "&id_jadwal=" . $id_jadwal_str . "&info=order_dibatalkan");
    exit();
} else {
    header("Location: ../../index.php?success=order_dibatalkan");
    exit();
}
?>