<?php
session_start();
include '../../../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// =====================================================
// PROTEKSI HALAMAN - HANYA CUSTOMER YANG LOGIN
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit();
}

$id_pelanggan = $_SESSION['id_user'] ?? $_SESSION['id_pelanggan'] ?? null;
if (!$id_pelanggan) {
    echo json_encode(['success' => false, 'message' => 'ID pelanggan tidak ditemukan dalam sesi.']);
    exit();
}

// =====================================================
// KONSTANTA STATUS - SINKRON DENGAN DATABASE
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI_FOTO', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

// =====================================================
// VALIDASI INPUT
// =====================================================
if (!isset($_POST['id_order']) || !isset($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. ID Order dan Rating wajib diisi.']);
    exit();
}

$id_order = intval($_POST['id_order']);
$rating = intval($_POST['rating']);
$review = isset($_POST['review']) ? trim($_POST['review']) : '';

// Validasi ID Order
if ($id_order <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Order tidak valid.']);
    exit();
}

// Validasi rating 1-5
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating harus antara 1 sampai 5 bintang.']);
    exit();
}

// Validasi review length (max 255 sesuai database)
if (strlen($review) > 255) {
    echo json_encode(['success' => false, 'message' => 'Review maksimal 255 karakter.']);
    exit();
}

// =====================================================
// CEK KEPEMILIKAN ORDER & STATUS
// =====================================================
$cek_sql = "
    SELECT 
        o.Status_Order,
        o.Rating as Existing_Rating,
        p.Nama_Pelanggan
    FROM [Order] o
    INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
    WHERE o.ID_Order = ? 
      AND o.ID_Pelanggan = ? 
      AND o.Status = 1
";

$cek_stmt = sqlsrv_query($conn, $cek_sql, [$id_order, $id_pelanggan]);

if ($cek_stmt === false) {
    $errors = sqlsrv_errors();
    $error_msg = 'Terjadi kesalahan database saat memverifikasi order.';
    if ($errors != null) {
        foreach ($errors as $error) {
            $error_msg .= ' [' . $error['message'] . ']';
        }
    }
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit();
}

if (!sqlsrv_has_rows($cek_stmt)) {
    echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan atau bukan milik Anda.']);
    exit();
}

$row = sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC);

// Validasi status order harus LUNAS (3)
if ($row['Status_Order'] != STATUS_ORDER_LUNAS) {
    $status_labels = [
        STATUS_ORDER_MENUNGGU_DP => 'Menunggu DP',
        STATUS_ORDER_DP_TERVERIFIKASI => 'DP Terverifikasi',
        STATUS_ORDER_SELESAI_FOTO => 'Selesai Foto',
        STATUS_ORDER_DIBATALKAN => 'Dibatalkan'
    ];
    $current_status = $status_labels[$row['Status_Order']] ?? 'Unknown';
    echo json_encode([
        'success' => false, 
        'message' => 'Hanya order yang sudah lunas yang dapat diberi rating. Status order Anda saat ini: ' . $current_status . '.'
    ]);
    exit();
}

// Cek apakah sudah pernah rating
if (!empty($row['Existing_Rating'])) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah pernah memberi rating untuk order ini.']);
    exit();
}

// =====================================================
// UPDATE RATING & REVIEW KE DATABASE
// =====================================================
$username_cust = $_SESSION['username'] ?? 'customer';

sqlsrv_begin_transaction($conn);
try {
    $update_sql = "
        UPDATE [Order] 
        SET 
            Rating = ?, 
            Review = ?, 
            Modified_By = ?, 
            Modified_Date = GETDATE()
        WHERE ID_Order = ? AND ID_Pelanggan = ? AND Status = 1
    ";

    $update_stmt = sqlsrv_query($conn, $update_sql, [
        $rating, 
        $review, 
        $username_cust, 
        $id_order, 
        $id_pelanggan
    ]);

    if ($update_stmt === false) {
        throw new Exception('Gagal menyimpan rating ke database.');
    }

    // Verifikasi update berhasil (cek rows affected)
    $rows_affected = sqlsrv_rows_affected($update_stmt);
    if ($rows_affected === 0) {
        throw new Exception('Tidak ada data yang diperbarui. Order mungkin sudah tidak aktif.');
    }

    sqlsrv_commit($conn);

    // Siapkan response dengan data rating
    $rating_labels = [
        1 => 'Sangat Buruk',
        2 => 'Buruk', 
        3 => 'Cukup',
        4 => 'Bagus',
        5 => 'Sangat Bagus'
    ];

    echo json_encode([
        'success' => true, 
        'message' => 'Terima kasih! Rating dan review Anda berhasil disimpan.',
        'data' => [
            'id_order' => $id_order,
            'rating' => $rating,
            'rating_label' => $rating_labels[$rating] ?? '',
            'review' => $review,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
    exit();
}
?>