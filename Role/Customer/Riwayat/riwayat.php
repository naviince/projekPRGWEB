<?php
session_start();
include '../../../koneksi.php';

// Atur zona waktu ke WIB
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// PROTEKSI HALAMAN - HANYA CUSTOMER
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_pelanggan = $_SESSION['id_user'] ?? $_SESSION['id_pelanggan'] ?? null;
if (!$id_pelanggan) {
    header("Location: ../../login.php");
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

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

define('STATUS_SESI_BELUM', 0);
define('STATUS_SESI_SELESAI', 1);

define('STATUS_PENJUALAN_PROSES', 0);
define('STATUS_PENJUALAN_SELESAI', 1);

define('STATUS_DATA_AKTIF', 1);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// =====================================================
// HANDLE UPLOAD PEMBAYARAN (AJAX) - POPUP SINKRON
// =====================================================
if (isset($_POST['action_upload_pembayaran'])) {
    header('Content-Type: application/json');

    $id_order = intval($_POST['id_order']);
    $tipe_pembayaran = $_POST['tipe_pembayaran'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);

    if (empty($metode_pembayaran) || $jumlah_bayar <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data input tidak valid.']);
        exit();
    }

    if (!in_array($tipe_pembayaran, ['DP', 'Pelunasan'])) {
        echo json_encode(['success' => false, 'message' => 'Tipe pembayaran tidak valid.']);
        exit();
    }

    // -----------------------------------------------------
    // VALIDASI KEPEMILIKAN + TAHAP ORDER YANG SESUAI
    // Mencegah: upload DP dobel saat masih menunggu verifikasi,
    // upload Pelunasan sebelum sesi foto selesai, atau upload
    // untuk order yang bukan milik user / sudah dibatalkan.
    // -----------------------------------------------------
    $cek_order_sql = "SELECT Status_Order FROM [Order] WHERE ID_Order = ? AND ID_Pelanggan = ? AND Status = 1";
    $cek_order_stmt = sqlsrv_query($conn, $cek_order_sql, [$id_order, $id_pelanggan]);
    $order_row = $cek_order_stmt ? sqlsrv_fetch_array($cek_order_stmt, SQLSRV_FETCH_ASSOC) : null;

    if (!$order_row) {
        echo json_encode(['success' => false, 'message' => 'Order tidak ditemukan atau bukan milik Anda.']);
        exit();
    }

    if ($tipe_pembayaran === 'DP' && $order_row['Status_Order'] != STATUS_ORDER_MENUNGGU_DP) {
        echo json_encode(['success' => false, 'message' => 'Order ini sudah tidak dalam tahap menunggu DP.']);
        exit();
    }
    if ($tipe_pembayaran === 'Pelunasan') {
        $is_pelunasan_lanjutan = ($order_row['Status_Order'] == STATUS_ORDER_SELESAI_FOTO);

        // Order masih di tahap "Menunggu DP" TAPI belum pernah punya baris DP
        // sama sekali -> ini pembayaran lunas sekaligus di awal (bukan pelunasan
        // lanjutan setelah sesi foto), jadi tetap diizinkan.
        $is_pelunasan_lunas_sekaligus = false;
        if ($order_row['Status_Order'] == STATUS_ORDER_MENUNGGU_DP) {
            $cek_dp_sql = "SELECT COUNT(*) AS total FROM Pembayaran WHERE ID_Order = ? AND Tipe_Pembayaran = 'DP' AND Status = 1";
            $cek_dp_stmt = sqlsrv_query($conn, $cek_dp_sql, [$id_order]);
            $cek_dp_row = $cek_dp_stmt ? sqlsrv_fetch_array($cek_dp_stmt, SQLSRV_FETCH_ASSOC) : null;
            $is_pelunasan_lunas_sekaligus = !$cek_dp_row || $cek_dp_row['total'] == 0;
        }

        if (!$is_pelunasan_lanjutan && !$is_pelunasan_lunas_sekaligus) {
            echo json_encode(['success' => false, 'message' => 'Pelunasan hanya bisa diunggah setelah sesi foto selesai, atau sebagai pembayaran lunas sekaligus sebelum sesi berlangsung.']);
            exit();
        }
    }

    // Cegah upload dobel selagi bukti sebelumnya masih menunggu verifikasi admin
    $cek_pending_sql = "SELECT COUNT(*) AS total FROM Pembayaran 
        WHERE ID_Order = ? AND Tipe_Pembayaran = ? AND Status_Pembayaran = ? AND Status = 1";
    $cek_pending_stmt = sqlsrv_query($conn, $cek_pending_sql, [$id_order, $tipe_pembayaran, STATUS_PEMBAYARAN_MENUNGGU]);
    $pending_row = $cek_pending_stmt ? sqlsrv_fetch_array($cek_pending_stmt, SQLSRV_FETCH_ASSOC) : null;
    if ($pending_row && $pending_row['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Anda masih memiliki bukti ' . $tipe_pembayaran . ' yang sedang menunggu verifikasi admin. Mohon tunggu sampai diproses.']);
        exit();
    }

    if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'Bukti transfer wajib diunggah.']);
        exit();
    }

    $file = $_FILES['bukti_transfer'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($file_ext, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'Format file bukti transfer harus JPG, JPEG, PNG, atau PDF.']);
        exit();
    }

    $upload_dir = '../../../assets/img/bukti/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $prefix = ($tipe_pembayaran === 'DP') ? 'bukti_dp_' : 'bukti_pelunasan_';
    $new_file_name = $prefix . $id_order . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $username_cust = $_SESSION['username'] ?? 'customer';

        sqlsrv_begin_transaction($conn);
        try {
            $sql_insert = "INSERT INTO Pembayaran (
                ID_Order, Tipe_Pembayaran, Metode_Pembayaran, Jumlah_Bayar, Bukti_Transfer, 
                Tanggal_Upload, Status_Pembayaran, Status, Created_By, Created_Date
            ) VALUES (?, ?, ?, ?, ?, GETDATE(), 0, 1, ?, GETDATE())";

            $stmt_insert = sqlsrv_query($conn, $sql_insert, [
                $id_order, $tipe_pembayaran, $metode_pembayaran, $jumlah_bayar, $new_file_name, $username_cust
            ]);

            if (!$stmt_insert) {
                throw new Exception('Gagal menyimpan bukti pembayaran ke database.');
            }

            sqlsrv_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Bukti pembayaran ' . $tipe_pembayaran . ' berhasil diunggah dan menunggu verifikasi admin.']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            if (file_exists($target_path)) {
                unlink($target_path);
            }
            echo json_encode(['success' => false, 'message' => 'Gagal memproses transaksi bukti transfer: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file bukti transfer ke server.']);
    }
    exit();
}

// =====================================================
// AMBIL DATA PELANGGAN
// =====================================================
$q_pelanggan = sqlsrv_query($conn, "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?", [$id_pelanggan]);
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
if ($d_pelanggan) { $d_pelanggan = array_change_key_case($d_pelanggan, CASE_LOWER); }
$nama_pelanggan = $d_pelanggan['nama_pelanggan'] ?? 'Pelanggan';
$foto_pelanggan = $d_pelanggan['foto_profil'] ?? 'default.jpg';

$foto_pelanggan_src = ($foto_pelanggan != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_pelanggan)) 
    ? "../../../assets/img/pelanggan/" . $foto_pelanggan 
    : $default_svg_avatar;

// =====================================================
// QUERY RIWAYAT ORDER LENGKAP - SINKRON MULTI JADWAL
// =====================================================
$sql = "
SELECT 
    o.ID_Order,
    o.Tanggal_Booking,
    o.Status_Order,
    o.Total_Harga,
    o.Total_Paket,
    o.Total_Barang_Cetak,
    o.Rating,
    o.Review,
    o.Keterangan,

    p.ID_Paket,
    p.Nama_Paket,
    p.Durasi_Waktu,
    p.Harga_Paket,
    p.Kapasitas_Orang,
    p.Foto_Paket,

    r.ID_Ruangan,
    r.Nama_Ruangan,

    t.ID_Tema,
    t.Nama_Tema,

    k.ID_Karyawan as ID_Fotografer,
    k.Nama_Karyawan as Nama_Fotografer,
    k.Foto_Profil as Foto_Fotografer,

    sf.ID_Sesi_Foto,
    sf.Status_Sesi,
    sf.File_Hasil,
    sf.Tanggal_Upload_Hasil,

    dp.ID_Pembayaran as ID_DP,
    dp.Jumlah_Bayar as Jumlah_DP,
    dp.Status_Pembayaran as Status_DP,
    dp.Tanggal_Upload as Tgl_DP,
    dp.Bukti_Transfer as Bukti_DP,
    dp.Metode_Pembayaran as Metode_DP,

    pl.ID_Pembayaran as ID_Pelunasan,
    pl.Jumlah_Bayar as Jumlah_Pelunasan,
    pl.Status_Pembayaran as Status_Pelunasan,
    pl.Tanggal_Upload as Tgl_Pelunasan,
    pl.Bukti_Transfer as Bukti_Pelunasan,
    pl.Metode_Pembayaran as Metode_Pelunasan

FROM [Order] o
LEFT JOIN Paket_Foto p ON o.ID_Paket = p.ID_Paket
LEFT JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
LEFT JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
LEFT JOIN Sesi_Foto sf ON o.ID_Order = sf.ID_Order AND sf.Status = 1
LEFT JOIN Karyawan k ON sf.ID_Karyawan = k.ID_Karyawan
LEFT JOIN Pembayaran dp ON o.ID_Order = dp.ID_Order AND dp.Tipe_Pembayaran = 'DP' AND dp.Status = 1
LEFT JOIN Pembayaran pl ON o.ID_Order = pl.ID_Order AND pl.Tipe_Pembayaran = 'Pelunasan' AND pl.Status = 1
WHERE o.ID_Pelanggan = ? AND o.Status = 1
ORDER BY o.Tanggal_Booking DESC
";

$params = [$id_pelanggan];
$q_riwayat = sqlsrv_query($conn, $sql, $params);

$riwayat_list = [];
if ($q_riwayat !== false) {
    while ($row = sqlsrv_fetch_array($q_riwayat, SQLSRV_FETCH_ASSOC)) {
        $date_fields = ['Tanggal_Booking', 'Tanggal_Upload_Hasil', 'Tgl_DP', 'Tgl_Pelunasan'];
        foreach ($date_fields as $field) {
            if (isset($row[$field]) && is_object($row[$field]) && method_exists($row[$field], 'format')) {
                $row[$field] = $row[$field]->format('Y-m-d H:i:s');
            }
        }
        $riwayat_list[] = $row;
    }
}

// =====================================================
// TARIK MULTI-SLOT JADWAL STUDIO PER ORDER
// =====================================================
$jadwal_per_order = [];
$jadwal_expired = []; // Track expired jadwal per order
$jadwal_mendatang = []; // Track upcoming jadwal per order

if (!empty($riwayat_list)) {
    $order_ids = array_column($riwayat_list, 'ID_Order');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $sql_jadwal = "
        SELECT 
            oj.ID_Order,
            j.ID_Jadwal,
            j.Tanggal_Jadwal,
            j.Jam_Mulai,
            j.Jam_Selesai,
            j.Keterangan,
            j.Status_Jadwal
        FROM Order_Jadwal oj
        INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
        WHERE oj.ID_Order IN ($placeholders) AND j.Status = 1 AND j.Is_Deleted = 0
        ORDER BY oj.ID_Order, j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC
    ";

    $q_jadwal = sqlsrv_query($conn, $sql_jadwal, $order_ids);
    if ($q_jadwal !== false) {
        while ($j = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
            $id_order = $j['ID_Order'];
            if (!isset($jadwal_per_order[$id_order])) {
                $jadwal_per_order[$id_order] = [];
            }

            if (isset($j['Tanggal_Jadwal']) && is_object($j['Tanggal_Jadwal']) && method_exists($j['Tanggal_Jadwal'], 'format')) {
                $j['Tanggal_Jadwal'] = $j['Tanggal_Jadwal']->format('Y-m-d');
            }
            if (isset($j['Jam_Mulai']) && is_object($j['Jam_Mulai']) && method_exists($j['Jam_Mulai'], 'format')) {
                $j['Jam_Mulai'] = $j['Jam_Mulai']->format('H:i');
            } elseif (isset($j['Jam_Mulai'])) {
                $j['Jam_Mulai'] = substr($j['Jam_Mulai'], 0, 5);
            }
            if (isset($j['Jam_Selesai']) && is_object($j['Jam_Selesai']) && method_exists($j['Jam_Selesai'], 'format')) {
                $j['Jam_Selesai'] = $j['Jam_Selesai']->format('H:i');
            } elseif (isset($j['Jam_Selesai'])) {
                $j['Jam_Selesai'] = substr($j['Jam_Selesai'], 0, 5);
            }

            // Cek expired: bandingkan Tanggal_Jadwal + Jam_Selesai dengan sekarang
            $jadwal_datetime = strtotime($j['Tanggal_Jadwal'] . ' ' . $j['Jam_Selesai']);
            $j['Is_Expired'] = ($jadwal_datetime < time());
            $j['Expired_DateTime'] = $j['Tanggal_Jadwal'] . ' ' . $j['Jam_Selesai'];

            $jadwal_per_order[$id_order][] = $j;
        }
    }

    // Tentukan status expired per order
    foreach ($jadwal_per_order as $oid => $schedules) {
        $all_expired = true;
        $has_upcoming = false;
        foreach ($schedules as $sched) {
            if (!$sched['Is_Expired']) {
                $all_expired = false;
                $has_upcoming = true;
            }
        }
        $jadwal_expired[$oid] = $all_expired;
        $jadwal_mendatang[$oid] = $has_upcoming;
    }
}

// =====================================================
// AUTO-BATALKAN ORDER YANG KADALUARSA & BELUM DP
// - Order dengan status "Menunggu DP" (0) yang seluruh
//   jadwalnya sudah lewat otomatis dipindah ke "Dibatalkan" (4).
// - Order yang sudah DP/lunas TIDAK disentuh otomatis,
//   karena ada uang yang harus diverifikasi/direfund manual oleh admin.
// - Ini menggantikan konsep "hapus permanen": data order tetap
//   tersimpan sebagai riwayat (Status tetap 1), tapi statusnya
//   berubah jadi Dibatalkan sehingga otomatis pindah ke tab Batal
//   dan tidak lagi dianggap pesanan aktif.
// =====================================================
$auto_cancel_ids = [];
foreach ($riwayat_list as $item) {
    $oid = $item['ID_Order'];
    if (
        $item['Status_Order'] == STATUS_ORDER_MENUNGGU_DP &&
        isset($jadwal_expired[$oid]) && $jadwal_expired[$oid] === true
    ) {
        $auto_cancel_ids[] = $oid;
    }
}

if (!empty($auto_cancel_ids)) {
    $ph = implode(',', array_fill(0, count($auto_cancel_ids), '?'));
    $sql_auto_cancel = "UPDATE [Order] 
        SET Status_Order = " . STATUS_ORDER_DIBATALKAN . ", 
            Keterangan = 'Dibatalkan otomatis oleh sistem: jadwal kadaluarsa tanpa pembayaran DP',
            Modified_By = 'system_auto', 
            Modified_Date = GETDATE()
        WHERE ID_Order IN ($ph) AND Status_Order = " . STATUS_ORDER_MENUNGGU_DP;
    $stmt_auto_cancel = sqlsrv_query($conn, $sql_auto_cancel, $auto_cancel_ids);

    if ($stmt_auto_cancel !== false) {
        // Bersihkan juga bukti DP yang mungkin sudah terlanjur diupload
        // customer tapi belum sempat diverifikasi admin saat order ini
        // ikut auto-dibatalkan (mencegah data pembayaran "nyangkut").
        $sql_bersih_pembayaran = "UPDATE Pembayaran 
            SET Status = 0, Modified_By = 'system_auto', Modified_Date = GETDATE()
            WHERE ID_Order IN ($ph) AND Tipe_Pembayaran = 'DP' AND Status_Pembayaran = 0 AND Status = 1";
        sqlsrv_query($conn, $sql_bersih_pembayaran, $auto_cancel_ids);

        // Sinkronkan array di memori supaya tampilan langsung update
        // tanpa perlu reload halaman kedua kalinya.
        foreach ($riwayat_list as &$item_ref) {
            if (in_array($item_ref['ID_Order'], $auto_cancel_ids)) {
                $item_ref['Status_Order'] = STATUS_ORDER_DIBATALKAN;
                $item_ref['Keterangan'] = 'Dibatalkan otomatis oleh sistem: jadwal kadaluarsa tanpa pembayaran DP';
            }
        }
        unset($item_ref);
        // Order yang sudah otomatis dibatalkan bukan lagi "expired" (sudah final di tab Batal)
        foreach ($auto_cancel_ids as $aid) {
            $jadwal_expired[$aid] = false;
        }
    }
}

// =====================================================
// AMBIL BARANG CETAK PER ORDER
// =====================================================
$barang_per_order = [];
if (!empty($riwayat_list)) {
    $order_ids = array_column($riwayat_list, 'ID_Order');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $sql_barang = "
        SELECT 
            pen.ID_Order,
            pen.ID_Penjualan,
            pen.Status_Penjualan,
            pen.Total_Penjualan,
            d.ID_Barang,
            d.Jumlah,
            d.Harga_Satuan,
            d.Subtotal,
            b.Nama_Barang,
            b.Foto_Barang
        FROM Penjualan pen
        INNER JOIN Detail_Penjualan_Barang_Cetak d ON pen.ID_Penjualan = d.ID_Penjualan
        INNER JOIN Barang_Cetak b ON d.ID_Barang = b.ID_Barang
        WHERE pen.ID_Order IN ($placeholders) AND pen.Status = 1
        ORDER BY pen.ID_Order, d.ID_Detail
    ";

    $q_barang = sqlsrv_query($conn, $sql_barang, $order_ids);
    if ($q_barang !== false) {
        while ($b = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC)) {
            $id_order = $b['ID_Order'];
            if (!isset($barang_per_order[$id_order])) {
                $barang_per_order[$id_order] = [];
            }
            $barang_per_order[$id_order][] = $b;
        }
    }
}

// =====================================================
// AMBIL REKENING UNTUK POPUP PEMBAYARAN
// =====================================================
$rekening_list = [
    ['nama_bank' => 'Bank BCA', 'no_rekening' => '123-456-7890', 'atas_nama' => 'SpotLight Studio Foto'],
    ['nama_bank' => 'Bank BNI', 'no_rekening' => '098-765-4321', 'atas_nama' => 'SpotLight Studio Foto'],
    ['nama_bank' => 'Bank Mandiri', 'no_rekening' => '112-233-4455', 'atas_nama' => 'SpotLight Studio Foto']
];

// =====================================================
// HITUNG STATISTIK
// =====================================================
$stats = [
    'total' => 0,
    'menunggu_dp' => 0,
    'dp_verified' => 0,
    'selesai_foto' => 0,
    'lunas' => 0,
    'dibatalkan' => 0,
    'expired' => 0
];

foreach ($riwayat_list as $item) {
    $stats['total']++;
    switch ($item['Status_Order']) {
        case STATUS_ORDER_MENUNGGU_DP: $stats['menunggu_dp']++; break;
        case STATUS_ORDER_DP_TERVERIFIKASI: $stats['dp_verified']++; break;
        case STATUS_ORDER_SELESAI_FOTO: $stats['selesai_foto']++; break;
        case STATUS_ORDER_LUNAS: $stats['lunas']++; break;
        case STATUS_ORDER_DIBATALKAN: $stats['dibatalkan']++; break;
    }
    // Cek expired
    $oid = $item['ID_Order'];
    if (isset($jadwal_expired[$oid]) && $jadwal_expired[$oid] && $item['Status_Order'] != STATUS_ORDER_LUNAS && $item['Status_Order'] != STATUS_ORDER_DIBATALKAN) {
        $stats['expired']++;
    }
}

// =====================================================
// FILTER TAB
// =====================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'semua';
$filtered_list = [];
foreach ($riwayat_list as $item) {
    $oid = $item['ID_Order'];
    $is_expired = isset($jadwal_expired[$oid]) && $jadwal_expired[$oid] && $item['Status_Order'] != STATUS_ORDER_LUNAS && $item['Status_Order'] != STATUS_ORDER_DIBATALKAN;

    if ($tab === 'semua') {
        $filtered_list[] = $item;
    } elseif ($tab === 'menunggu' && $item['Status_Order'] == STATUS_ORDER_MENUNGGU_DP && !$is_expired) {
        $filtered_list[] = $item;
    } elseif ($tab === 'proses' && in_array($item['Status_Order'], [STATUS_ORDER_DP_TERVERIFIKASI, STATUS_ORDER_SELESAI_FOTO]) && !$is_expired) {
        $filtered_list[] = $item;
    } elseif ($tab === 'selesai' && $item['Status_Order'] == STATUS_ORDER_LUNAS) {
        $filtered_list[] = $item;
    } elseif ($tab === 'batal' && $item['Status_Order'] == STATUS_ORDER_DIBATALKAN) {
        $filtered_list[] = $item;
    } elseif ($tab === 'expired' && $is_expired) {
        $filtered_list[] = $item;
    }
}

// =====================================================
// FUNGSI HELPER
// =====================================================
// -----------------------------------------------------
// DETEKSI PEMBAYARAN LANGSUNG LUNAS (SEKALIGUS)
// Sistem hanya mengenal 2 tahap pembayaran di tabel Pembayaran
// (DP lalu Pelunasan). Saat customer memilih "Bayar Langsung Lunas"
// di halaman booking, seluruh nominal (100%) tetap tercatat sebagai
// baris Tipe_Pembayaran = 'DP'. Fungsi ini mendeteksi kondisi tsb
// dengan membandingkan Jumlah_DP terhadap total harga order, supaya
// tampilan tidak keliru menganggapnya sebagai DP parsial (65%) yang
// masih menyisakan tahap Pelunasan terpisah.
// -----------------------------------------------------
function hitungTotalHargaDiskon($item) {
    $total_paket = (float)($item['Total_Paket'] ?? 0);
    $total_cetak = (float)($item['Total_Barang_Cetak'] ?? 0);
    $diskon_cetak = $total_cetak > 0 ? $total_cetak * 0.05 : 0;
    return $total_paket + ($total_cetak - $diskon_cetak);
}

// Kembalikan 'DP', 'Pelunasan', atau null -- menandakan DI KOLOM MANA data
// pembayaran lunas-sekaligus itu sebenarnya tersimpan. Dua pola bisa terjadi:
//  - Pola BARU (setelah proses_pembayaran.php diperbaiki): pembayaran lunas
//    langsung tercatat sebagai Tipe_Pembayaran='Pelunasan', TANPA baris DP
//    sama sekali (order tidak pernah melalui tahap DP terpisah).
//  - Pola LAMA (data historis sebelum perbaikan): pembayaran lunas sempat
//    tercatat sebagai Tipe_Pembayaran='DP' dengan nominal 100% dari total,
//    dan tidak ada baris Pelunasan menyusul.
function sumberLunasSekaligus($item) {
    $jumlah_dp = (float)($item['Jumlah_DP'] ?? 0);
    $jumlah_pelunasan = (float)($item['Jumlah_Pelunasan'] ?? 0);
    $total_harga_diskon = hitungTotalHargaDiskon($item);

    if ($total_harga_diskon <= 0) {
        return null;
    }

    // Toleransi pembulatan bersifat RELATIF (0.5% dari total harga), dengan batas
    // bawah Rp1 dan batas atas Rp100, supaya tetap akurat baik untuk order kecil
    // (ratusan ribu) maupun order besar (jutaan) tanpa gampang salah deteksi.
    $toleransi = max(1, min(100, $total_harga_diskon * 0.005));

    if ($jumlah_dp <= 0 && $jumlah_pelunasan > 0 && $jumlah_pelunasan >= ($total_harga_diskon - $toleransi)) {
        return 'Pelunasan';
    }
    if ($jumlah_dp > 0 && $jumlah_pelunasan <= 0 && $jumlah_dp >= ($total_harga_diskon - $toleransi)) {
        return 'DP';
    }
    return null;
}

function isBayarLunasSekaligus($item) {
    return sumberLunasSekaligus($item) !== null;
}

function getStatusBadge($status, $is_expired = false, $is_lunas_sekaligus = false) {
    if ($is_expired && $status != STATUS_ORDER_LUNAS && $status != STATUS_ORDER_DIBATALKAN) {
        return '<span class="badge badge-expired"><i class="bi bi-clock-history"></i> Kadaluarsa</span>';
    }
    switch ($status) {
        case STATUS_ORDER_MENUNGGU_DP:
            if ($is_lunas_sekaligus) {
                return '<span class="badge badge-dp"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi Pembayaran (Lunas)</span>';
            }
            return '<span class="badge badge-menunggu"><i class="bi bi-hourglass-split"></i> Menunggu DP</span>';
        case STATUS_ORDER_DP_TERVERIFIKASI:
            if ($is_lunas_sekaligus) {
                return '<span class="badge badge-dp"><i class="bi bi-check-circle-fill"></i> Lunas - Menunggu Sesi</span>';
            }
            return '<span class="badge badge-dp"><i class="bi bi-check-circle-fill"></i> DP Terverifikasi</span>';
        case STATUS_ORDER_SELESAI_FOTO:
            return '<span class="badge badge-foto"><i class="bi bi-camera-fill"></i> Selesai Foto</span>';
        case STATUS_ORDER_LUNAS:
            return '<span class="badge badge-lunas"><i class="bi bi-check2-all"></i> Lunas & Selesai</span>';
        case STATUS_ORDER_DIBATALKAN:
            return '<span class="badge badge-batal"><i class="bi bi-x-circle-fill"></i> Dibatalkan</span>';
        default:
            return '<span class="badge badge-menunggu">Unknown</span>';
    }
}

function getStatusPembayaranBadge($status) {
    switch ($status) {
        case STATUS_PEMBAYARAN_MENUNGGU:
            return '<span class="badge-pay badge-pay-wait"><i class="bi bi-hourglass-split"></i> Menunggu</span>';
        case STATUS_PEMBAYARAN_VALID:
            return '<span class="badge-pay badge-pay-verified"><i class="bi bi-check-lg"></i> Terverifikasi</span>';
        case STATUS_PEMBAYARAN_DITOLAK:
            return '<span class="badge-pay badge-pay-rejected"><i class="bi bi-x-lg"></i> Ditolak</span>';
        default:
            return '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
    }
}

function getStatusPenjualanBadge($status) {
    switch ($status) {
        case STATUS_PENJUALAN_PROSES:
            return '<span class="badge-pay badge-pay-wait"><i class="bi bi-box"></i> Proses</span>';
        case STATUS_PENJUALAN_SELESAI:
            return '<span class="badge-pay badge-pay-verified"><i class="bi bi-check-lg"></i> Selesai</span>';
        default:
            return '<span class="badge-pay badge-pay-wait">-</span>';
    }
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $date = new DateTime($tanggal);
    $b = $date->format('m');
    return $date->format('d') . ' ' . $bulan[$b] . ' ' . $date->format('Y');
}

function formatJam($jam) {
    if (empty($jam)) return '-';
    if (is_object($jam) && method_exists($jam, 'format')) {
        return $jam->format('H:i');
    }
    return substr($jam, 0, 5);
}

function getAksiButtons($item, $jadwal_expired_map, $jadwal_map) {
    $status = $item['Status_Order'];
    $id_order = $item['ID_Order'];
    $has_file = !empty($item['File_Hasil']);
    $id_sesi = $item['ID_Sesi_Foto'] ?? 0;
    $buttons = '';

    $oid = $item['ID_Order'];
    $is_expired = isset($jadwal_expired_map[$oid]) && $jadwal_expired_map[$oid] && $status != STATUS_ORDER_LUNAS && $status != STATUS_ORDER_DIBATALKAN;

    // Perhitungan total harga setelah diskon produk cetak 5%
    $total_harga_diskon = hitungTotalHargaDiskon($item);
    // Deteksi apakah customer memilih "Bayar Langsung Lunas" saat booking
    // (nominal DP yang tercatat sudah menutupi 100% total harga)
    $is_lunas_sekaligus = isBayarLunasSekaligus($item);

    // Jika expired, hanya tampilkan badge expired
    if ($is_expired) {
        $buttons .= '<span class="btn-aksi btn-expired"><i class="bi bi-clock-history"></i> Jadwal Kadaluarsa</span>';
        return $buttons;
    }

    switch ($status) {
        case STATUS_ORDER_MENUNGGU_DP:
            $dp_amount = $total_harga_diskon * 0.65;
            $sumber_lunas = sumberLunasSekaligus($item); // 'DP' | 'Pelunasan' | null

            if ($sumber_lunas === 'Pelunasan') {
                // Pola BARU: pembayaran lunas sekaligus tersimpan di kolom Pelunasan
                $status_bayar = $item['Status_Pelunasan'] ?? null;

                if ($status_bayar === STATUS_PEMBAYARAN_MENUNGGU) {
                    $buttons .= '<span class="btn-aksi btn-pending"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi Admin (Pembayaran Lunas)</span>';
                } elseif ($status_bayar === STATUS_PEMBAYARAN_DITOLAK) {
                    $buttons .= '<div class="notice-ditolak"><i class="bi bi-exclamation-triangle"></i> Bukti pembayaran lunas sebelumnya ditolak admin. Silakan upload ulang.</div>';
                    $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'Pelunasan\', ' . $total_harga_diskon . ', ' . $total_harga_diskon . ')" class="btn-aksi btn-upload"><i class="bi bi-upload"></i> Upload Ulang Bukti Pembayaran</button>';
                }

                if ($status_bayar !== STATUS_PEMBAYARAN_MENUNGGU) {
                    $buttons .= '<a href="javascript:void(0)" onclick="batalkanOrder(' . $id_order . ')" class="btn-aksi btn-batal"><i class="bi bi-x-lg"></i> Batalkan</a>';
                }
            } else {
                // Pola normal (DP 65%) atau pola lama lunas-sekaligus (data historis di kolom DP)
                $status_dp = $item['Status_DP'] ?? null;

                if ($status_dp === STATUS_PEMBAYARAN_MENUNGGU) {
                    // Sudah upload, tinggal nunggu admin verifikasi -> tombol upload dinonaktifkan
                    if ($sumber_lunas === 'DP') {
                        $buttons .= '<span class="btn-aksi btn-pending"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi Admin (Pembayaran Lunas)</span>';
                    } else {
                        $buttons .= '<span class="btn-aksi btn-pending"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi Admin</span>';
                    }
                } elseif ($status_dp === STATUS_PEMBAYARAN_DITOLAK) {
                    $buttons .= '<div class="notice-ditolak"><i class="bi bi-exclamation-triangle"></i> Bukti pembayaran sebelumnya ditolak admin. Silakan upload ulang.</div>';
                    $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'DP\', ' . $dp_amount . ', ' . $total_harga_diskon . ')" class="btn-aksi btn-upload"><i class="bi bi-upload"></i> Upload Ulang Bukti DP</button>';
                } else {
                    $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'DP\', ' . $dp_amount . ', ' . $total_harga_diskon . ')" class="btn-aksi btn-upload"><i class="bi bi-upload"></i> Upload Bukti DP</button>';
                }

                // Batalkan tetap tersedia selama belum ada bukti DP yang menunggu verifikasi
                if ($status_dp !== STATUS_PEMBAYARAN_MENUNGGU) {
                    $buttons .= '<a href="javascript:void(0)" onclick="batalkanOrder(' . $id_order . ')" class="btn-aksi btn-batal"><i class="bi bi-x-lg"></i> Batalkan</a>';
                }
            }
            break;

        case STATUS_ORDER_DP_TERVERIFIKASI:
            $buttons .= '<a href="javascript:void(0)" onclick="lihatDetail(' . $id_order . ')" class="btn-aksi btn-detail"><i class="bi bi-info-circle"></i> Lihat Detail</a>';
            if (!empty($item['Nama_Fotografer'])) {
                $buttons .= '<div class="fotografer-info"><i class="bi bi-person-badge"></i> Fotografer: <strong>' . htmlspecialchars($item['Nama_Fotografer']) . '</strong></div>';
            }
            break;

        case STATUS_ORDER_SELESAI_FOTO:
            $remaining_amount = $total_harga_diskon - ($item['Jumlah_DP'] ?? 0);
            $status_pelunasan = $item['Status_Pelunasan'] ?? null;

            if ($is_lunas_sekaligus) {
                // Sudah dibayar lunas sejak awal, tidak perlu upload pelunasan lagi
                $buttons .= '<span class="btn-aksi btn-pending" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check2-all"></i> Sudah Lunas - Menunggu Admin Selesaikan Pesanan</span>';
            } elseif ($status_pelunasan === STATUS_PEMBAYARAN_MENUNGGU) {
                $buttons .= '<span class="btn-aksi btn-pending"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi Admin</span>';
            } else {
                if ($status_pelunasan === STATUS_PEMBAYARAN_DITOLAK) {
                    $buttons .= '<div class="notice-ditolak"><i class="bi bi-exclamation-triangle"></i> Bukti pelunasan sebelumnya ditolak admin. Silakan upload ulang.</div>';
                }
                $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'Pelunasan\', ' . $remaining_amount . ', ' . $total_harga_diskon . ')" class="btn-aksi btn-upload" style="background:linear-gradient(135deg, #059669, #10b981);color:#fff;"><i class="bi bi-upload"></i> Upload Pelunasan</button>';
            }
            if ($has_file && $id_sesi > 0) {
                $buttons .= '<a href="../../../assets/img/bukti/' . rawurlencode($item['File_Hasil']) . '" class="btn-aksi btn-preview" download><i class="bi bi-image"></i> Preview Hasil</a>';
            }
            break;

        case STATUS_ORDER_LUNAS:
            if ($has_file && $id_sesi > 0) {
                $buttons .= '<a href="../../../uploads/hasil/' . rawurlencode($item['File_Hasil']) . '" class="btn-aksi btn-download" download><i class="bi bi-download"></i> Download Hasil</a>';
            }
            if (empty($item['Rating'])) {
                $buttons .= '<a href="javascript:void(0)" onclick="bukaRating(' . $id_order . ')" class="btn-aksi btn-rating"><i class="bi bi-star-fill"></i> Beri Rating</a>';
            } else {
                $buttons .= '<div class="rating-display">';
                for ($i = 1; $i <= 5; $i++) {
                    $buttons .= ($i <= $item['Rating']) ? '<i class="bi bi-star-fill star-filled"></i>' : '<i class="bi bi-star star-empty"></i>';
                }
                $buttons .= '</div>';
                if (!empty($item['Review'])) {
                    $buttons .= '<div class="review-text">"' . htmlspecialchars($item['Review']) . '"</div>';
                }
            }
            break;

        case STATUS_ORDER_DIBATALKAN:
            $buttons .= '<span class="text-batal"><i class="bi bi-ban"></i> Order ini telah dibatalkan</span>';
            break;
    }

    return $buttons;
}

// Data untuk JS popup pembayaran
$order_data_js = [];
foreach ($riwayat_list as $item) {
    $oid = $item['ID_Order'];
    $total_paket = (float)($item['Total_Paket'] ?? 0);
    $total_cetak = (float)($item['Total_Barang_Cetak'] ?? 0);
    $diskon_cetak = $total_cetak > 0 ? $total_cetak * 0.05 : 0;
    $total_harga = $total_paket + ($total_cetak - $diskon_cetak);

    $schedules = $jadwal_per_order[$oid] ?? [];
    $jadwal_str = [];
    foreach ($schedules as $s) {
        $jadwal_str[] = formatTanggalIndo($s['Tanggal_Jadwal']) . ' | ' . $s['Jam_Mulai'] . ' - ' . $s['Jam_Selesai'] . ' WIB';
    }

    $order_data_js[$oid] = [
        'nama_paket' => $item['Nama_Paket'] ?? 'Paket Tidak Ditemukan',
        'nama_ruangan' => $item['Nama_Ruangan'] ?? '-',
        'nama_tema' => $item['Nama_Tema'] ?? '-',
        'total_paket' => $total_paket,
        'total_cetak' => $total_cetak,
        'diskon_cetak' => $diskon_cetak,
        'total_harga' => $total_harga,
        'jadwal' => $jadwal_str,
        'durasi' => $item['Durasi_Waktu'] ?? 0,
        'kapasitas' => $item['Kapasitas_Orang'] ?? 0,
        'foto_paket' => $item['Foto_Paket'] ?? 'default_paket.jpg',
        'foto_ruangan' => $item['Foto_Ruangan'] ?? 'default_ruangan.jpg',
        'foto_tema' => $item['Foto_Tema'] ?? 'default_tema.jpg'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-soft: 0 4px 24px rgba(0, 0, 0, 0.06);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 48px rgba(216, 63, 103, 0.18);
            --shadow-glow: 0 0 40px rgba(216, 63, 103, 0.15);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
            --transition-smooth: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--light-pink); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--p-pink); }

        /* ===== NAVBAR ATAS SINKRON ===== */
        .top-navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 14px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
            border-bottom: 1px solid var(--glass-border);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.7rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
            transition: var(--transition-smooth);
        }
        .nav-logo:hover { transform: scale(1.02); }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.85rem; }
        .nav-menu-center {
            display: flex;
            gap: 36px;
            align-items: center;
        }
        .nav-link-item {
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: var(--transition-smooth);
            padding: 8px 4px;
            position: relative;
        }
        .nav-link-item::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 3px;
            transition: var(--transition-smooth);
            transform: translateX(-50%);
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item:hover::after, .nav-link-item.active::after {
            width: 100%;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-btn-booking:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 28px rgba(216, 63, 103, 0.4);
            color: #fff;
        }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid var(--light-pink);
            cursor: pointer;
            transition: var(--transition-smooth);
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.15);
        }
        .nav-avatar:hover {
            transform: scale(1.12) rotate(3deg);
            border-color: var(--p-pink);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .nav-dropdown {
            position: absolute;
            top: 58px;
            right: -8px;
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card), 0 0 0 1px rgba(0,0,0,0.04);
            padding: 12px;
            min-width: 240px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--glass-border);
            animation: dropdownSlide 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .nav-dropdown.show { display: block; }
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .dropdown-item i { font-size: 1.1rem; width: 22px; text-align: center; }
        .dropdown-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 8px 0;
        }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 10px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== BREADCRUMB BAR ===== */
        .breadcrumb-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            padding: 14px 40px;
            border-bottom: 1px solid var(--glass-border);
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition-smooth);
            padding: 4px 8px;
            border-radius: 8px;
        }
        .breadcrumb-inner a:hover { 
            color: var(--p-pink); 
            background: var(--s-pink);
        }
        .breadcrumb-inner .separator { color: #cbd5e1; font-size: 0.75rem; }
        .breadcrumb-inner .current { 
            color: var(--p-pink); 
            font-weight: 800; 
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            padding: 4px 14px;
            border-radius: 20px;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 32px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title h1 {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title h1 i { color: var(--p-pink); font-size: 1.5rem; }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ===== STATS GRID SINKRON ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-soft);
            transition: var(--transition-smooth);
            border-left: 4px solid var(--p-pink);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--glass-border);
            border-right: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
            animation: fadeSlideUp 0.5s ease forwards;
            opacity: 0;
        }
        .stat-card:nth-child(1) { animation-delay: 0s; }
        .stat-card:nth-child(2) { animation-delay: 0.05s; }
        .stat-card:nth-child(3) { animation-delay: 0.1s; }
        .stat-card:nth-child(4) { animation-delay: 0.15s; }
        .stat-card:nth-child(5) { animation-delay: 0.2s; }
        .stat-card:nth-child(6) { animation-delay: 0.25s; }
        .stat-card:hover { border-color: var(--light-pink); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--light-pink);
            opacity: 0.5;
        }
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 900; color: var(--text-dark); letter-spacing: -1px; }
        .stat-card .stat-label { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; font-weight: 700; }
        .stat-card.total .stat-icon { background: var(--s-pink); color: var(--p-pink); }
        .stat-card.total { border-left-color: var(--p-pink); }
        .stat-card.menunggu .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-card.menunggu { border-left-color: #d97706; }
        .stat-card.proses .stat-icon { background: #eff6ff; color: #2563eb; }
        .stat-card.proses { border-left-color: #2563eb; }
        .stat-card.selesai .stat-icon { background: #ecfdf5; color: #059669; }
        .stat-card.selesai { border-left-color: #059669; }
        .stat-card.batal .stat-icon { background: #fef2f2; color: #dc2626; }
        .stat-card.batal { border-left-color: #dc2626; }
        .stat-card.expired .stat-icon { background: #f3f4f6; color: #6b7280; }
        .stat-card.expired { border-left-color: #6b7280; }

        /* ===== TABS CONTAINER SINKRON ===== */
        .tabs-container {
            background: var(--glass-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            margin-bottom: 24px;
            overflow: hidden;
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            animation: fadeSlideUp 0.5s ease forwards;
            animation-delay: 0.1s;
            opacity: 0;
        }
        .tabs-header {
            display: flex;
            border-bottom: 2px solid #f1f5f9;
            padding: 0 10px;
            overflow-x: auto;
        }
        .tabs-header::-webkit-scrollbar { display: none; }
        .tab-btn {
            padding: 16px 22px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            position: relative;
            transition: var(--transition-smooth);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { color: var(--p-pink); }
        .tab-btn.active { color: var(--p-pink); }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 3px 3px 0 0;
        }
        .tab-btn .tab-count {
            display: inline-flex;
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 800;
            min-width: 22px;
            justify-content: center;
        }
        .tab-btn.active .tab-count {
            background: var(--p-pink);
            color: #fff;
        }

        /* ===== NOTIFIKASI SUKSES SELESAI ===== */
        .notif-selesai {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 2px solid #a7f3d0;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.1);
            animation: fadeSlideUp 0.5s ease forwards, celebrateBounce 2s ease-in-out infinite;
        }
        .notif-selesai i { font-size: 1.5rem; color: var(--success); }
        .notif-selesai-text { font-size: 0.9rem; font-weight: 700; color: #065f46; }
        .notif-selesai-sub { font-size: 0.8rem; color: #059669; font-weight: 600; margin-top: 2px; }

        /* ===== ORDER CARDS SINKRON ===== */
        .orders-container { display: flex; flex-direction: column; gap: 20px; }
        .order-card {
            background: var(--glass-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            transition: var(--transition-smooth);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(16px);
            animation: fadeSlideUp 0.5s ease forwards;
            opacity: 0;
        }
        .order-card:hover { 
            box-shadow: var(--shadow-card); 
            border-color: var(--light-pink); 
            transform: translateY(-3px);
        }
        .order-card.batal { opacity: 0.7; }
        .order-card.batal .order-header { background: linear-gradient(135deg, #fef2f2, #fff5f5); }
        .order-card.expired { opacity: 0.85; }
        .order-card.expired .order-header { background: linear-gradient(135deg, #f3f4f6, #f9fafb); }
        .order-card.expired { border-left: 4px solid #6b7280; }
        .order-card.lunas { border-left: 4px solid var(--success); }

        .order-header {
            padding: 18px 24px;
            background: linear-gradient(135deg, #fafafa 0%, #f8fafc 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-id { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .order-id strong { color: var(--p-pink); font-size: 1rem; font-weight: 900; }
        .order-date { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .order-date i { color: var(--p-pink); }

        .order-body { padding: 24px; }
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        @media (max-width: 992px) { .order-grid { grid-template-columns: 1fr; } }

        .paket-section { display: flex; gap: 16px; }
        .paket-img {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-md);
            object-fit: cover;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border: 3px solid #ffffff;
            transition: var(--transition-smooth);
        }
        .paket-section:hover .paket-img { transform: scale(1.03); }
        .paket-info h3 { color: var(--text-dark); font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; }
        .paket-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .paket-meta span {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .paket-price { font-size: 1.3rem; font-weight: 900; color: var(--p-pink); letter-spacing: -0.5px; }

        .detail-section { display: flex; flex-direction: column; gap: 10px; }
        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #fafafa, #ffffff);
            border-radius: var(--radius-md);
            transition: var(--transition-smooth);
            border: 1px solid transparent;
        }
        .detail-item:hover {
            background: linear-gradient(135deg, #ffffff, #fafafa);
        }
        .detail-item i {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            color: var(--p-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
            transition: var(--transition-smooth);
        }
        .detail-item:hover i { background: var(--p-pink); color: #fff; }
        .detail-item .detail-label { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 2px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-item .detail-value { font-size: 0.9rem; color: var(--text-dark); font-weight: 700; }

        /* ===== EXPIRED BADGE ===== */
        .expired-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            color: #6b7280;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-top: 8px;
            border: 1px solid #d1d5db;
            animation: warningPulse 2s infinite;
        }
        .expired-badge i { font-size: 0.85rem; }

        /* ===== BARANG CETAK SECTION ===== */
        .barang-cetak-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e2e8f0;
        }
        .barang-cetak-section h4 {
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .barang-cetak-section h4 i { color: var(--p-pink); }
        .barang-cetak-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        .barang-cetak-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: linear-gradient(135deg, #fafafa, #ffffff);
            border-radius: var(--radius-md);
            border-left: 3px solid var(--p-pink);
            transition: var(--transition-smooth);
        }
        .barang-cetak-item:hover { background: #ffffff; }
        .barang-cetak-item img {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .barang-cetak-info { flex: 1; }
        .barang-cetak-nama { font-size: 0.82rem; font-weight: 800; color: var(--text-dark); }
        .barang-cetak-detail { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; font-weight: 600; }
        .barang-cetak-subtotal { font-size: 0.9rem; font-weight: 900; color: var(--p-pink); }

        /* ===== PEMBAYARAN SECTION ===== */
        .pembayaran-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e2e8f0;
        }
        .pembayaran-section h4 {
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pembayaran-section h4 i { color: var(--p-pink); }
        .pembayaran-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 768px) { .pembayaran-grid { grid-template-columns: 1fr; } }
        .pembayaran-box {
            background: linear-gradient(135deg, #fafafa, #ffffff);
            border-radius: var(--radius-md);
            padding: 18px;
            border-left: 3px solid var(--p-pink);
            transition: var(--transition-smooth);
        }
        .pembayaran-box:hover { background: #ffffff; }
        .pembayaran-box.pelunasan { border-left-color: var(--success); }
        .pembayaran-box .pay-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .pembayaran-box .pay-label { font-size: 0.82rem; font-weight: 800; color: var(--text-dark); }
        .pembayaran-box .pay-amount { font-size: 1.1rem; font-weight: 900; color: var(--p-pink); }
        .pembayaran-box.pelunasan .pay-amount { color: var(--success); }
        .pembayaran-box .pay-detail { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-weight: 600; }
        .pembayaran-box .pay-metode { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 4px; }

        /* ===== ORDER AKSI ===== */
        .order-aksi {
            padding: 16px 24px;
            background: linear-gradient(135deg, #fafafa, #f8fafc);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            border-top: 1px solid #f1f5f9;
        }
        .btn-aksi {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
            transition: var(--transition-smooth);
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .btn-aksi:hover { transform: translateY(-2px); box-shadow: var(--shadow-card); }
        .btn-upload {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
        }
        .btn-upload:hover { box-shadow: 0 6px 20px rgba(216, 63, 103, 0.3); }
        .btn-batal { background: #fef2f2; color: #dc2626; }
        .btn-batal:hover { background: #dc2626; color: #fff; }
        .btn-detail { background: #eff6ff; color: #2563eb; }
        .btn-detail:hover { background: #2563eb; color: #fff; }
        .btn-preview { background: #faf5ff; color: #7c3aed; }
        .btn-preview:hover { background: #7c3aed; color: #fff; }
        .btn-download { background: #ecfdf5; color: #059669; }
        .btn-download:hover { background: #059669; color: #fff; }
        .btn-rating { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
        .btn-rating:hover { background: #d97706; color: #fff; }
        .btn-expired { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
        .btn-expired:hover { transform: none; box-shadow: none; }
        .btn-pending { background: #fef3c7; color: #92400e; cursor: default; border: 1px solid #fde68a; }
        .btn-pending:hover { transform: none; box-shadow: none; }
        .notice-ditolak { width: 100%; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }

        .fotografer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #eff6ff;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            color: #2563eb;
            font-weight: 700;
        }
        .text-batal { color: #dc2626; font-size: 0.85rem; font-weight: 700; }

        .rating-display { display: flex; gap: 4px; }
        .star-filled { color: #f59e0b; font-size: 1.1rem; }
        .star-empty { color: #e2e8f0; font-size: 1.1rem; }
        .review-text {
            margin-top: 8px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            color: #92400e;
            font-weight: 600;
            font-style: italic;
            border-left: 3px solid #f59e0b;
        }

        /* ===== BADGES ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .badge-menunggu { background: #fffbeb; color: #d97706; }
        .badge-dp { background: #eff6ff; color: #2563eb; }
        .badge-foto { background: #faf5ff; color: #7c3aed; }
        .badge-lunas { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }
        .badge-expired { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        .badge-pay {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .badge-pay-wait { background: #fffbeb; color: #d97706; }
        .badge-pay-verified { background: #ecfdf5; color: #059669; }
        .badge-pay-rejected { background: #fef2f2; color: #dc2626; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeSlideUp 0.5s ease forwards;
        }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 20px; display: block; }
        .empty-state h3 { color: var(--text-dark); font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        .empty-state .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.85rem;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .empty-state .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(216, 63, 103, 0.35); }

        /* ===== MODAL POPUP PEMBAYARAN SINKRON DENGAN pembayaran_dp.php ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #ffffff;
            border-radius: var(--radius-xl);
            padding: 0;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
            animation: modalIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header-popup {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .modal-header-popup h3 {
            font-size: 1.2rem;
            font-weight: 900;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        .modal-close-btn:hover { background: rgba(255,255,255,0.4); transform: rotate(90deg); }
        .modal-body-popup { padding: 32px; }
        .modal-payment-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 28px;
        }
        @media (max-width: 992px) { .modal-payment-grid { grid-template-columns: 1fr; } }

        /* Detail Card dalam Modal */
        .modal-detail-card {
            background: linear-gradient(135deg, #fafafa, #f8fafc);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #f1f5f9;
        }
        .modal-detail-title {
            font-size: 0.85rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .modal-detail-title i { color: var(--p-pink); }
        .modal-detail-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px;
            background: #fff;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .modal-detail-item:last-child { margin-bottom: 0; }
        .modal-detail-img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .modal-detail-info { flex: 1; }
        .modal-detail-label { font-size: 0.7rem; font-weight: 800; color: var(--p-pink); text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-detail-value { font-size: 0.95rem; font-weight: 800; color: var(--text-dark); }
        .modal-detail-sub { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

        /* Jadwal Card dalam Modal */
        .modal-jadwal-card {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: var(--radius-lg);
            padding: 18px;
            color: #fff;
            margin-bottom: 12px;
            position: relative;
            overflow: hidden;
        }
        .modal-jadwal-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .modal-jadwal-title { font-size: 0.75rem; font-weight: 800; opacity: 0.85; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .modal-jadwal-main { font-size: 1.1rem; font-weight: 900; margin-bottom: 4px; }
        .modal-jadwal-sub { font-size: 0.9rem; font-weight: 600; opacity: 0.9; display: flex; align-items: center; gap: 6px; }

        /* Rekening Card dalam Modal */
        .modal-rekening-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: var(--radius-md);
            padding: 18px;
            border: 1px solid #bae6fd;
            margin-bottom: 12px;
            transition: var(--transition-smooth);
        }
        .modal-rekening-card:hover { background: linear-gradient(135deg, #e0f2fe, #f0f9ff); }
        .modal-rekening-bank { font-size: 0.78rem; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .modal-rekening-no { font-size: 1.1rem; font-weight: 900; color: #0c4a6e; letter-spacing: 1px; cursor: pointer; transition: var(--transition-smooth); }
        .modal-rekening-no:hover { color: var(--p-pink); }
        .modal-rekening-an { font-size: 0.8rem; color: #64748b; font-weight: 600; }
        .modal-copy-btn {
            background: #fff;
            border: 2px solid #bae6fd;
            color: #0369a1;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: var(--transition-smooth);
            margin-top: 8px;
        }
        .modal-copy-btn:hover { background: #0369a1; color: #fff; border-color: #0369a1; }
        .modal-copy-btn.copied { background: var(--success); color: #fff; border-color: var(--success); }

        /* QRIS Card dalam Modal */
        .modal-qris-card {
            background: linear-gradient(135deg, #fafafa, #f8fafc);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid #f1f5f9;
            text-align: center;
        }
        .modal-qris-title { font-size: 0.9rem; font-weight: 800; color: var(--text-dark); margin-bottom: 12px; }
        .modal-qris-img { width: 160px; height: 160px; object-fit: contain; border-radius: var(--radius-md); border: 2px solid #f1f5f9; margin-bottom: 10px; }
        .modal-qris-note { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

        /* Upload Card dalam Modal */
        .modal-upload-card {
            background: linear-gradient(135deg, #fafafa, #f8fafc);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid #f1f5f9;
            height: fit-content;
        }
        .modal-upload-title {
            font-size: 1rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-upload-title i { color: var(--p-pink); }
        .modal-dp-amount {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: var(--radius-lg);
            padding: 20px;
            color: #fff;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.25);
        }
        .modal-dp-label { font-size: 0.78rem; font-weight: 800; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
        .modal-dp-value { font-size: 1.8rem; font-weight: 900; }
        .modal-dp-note { font-size: 0.75rem; font-weight: 600; opacity: 0.8; margin-top: 6px; }
        .modal-info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-info-row:last-child { border-bottom: none; }
        .modal-info-label { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        .modal-info-value { font-size: 0.88rem; font-weight: 800; color: var(--text-dark); }
        .modal-info-value.total { font-size: 1.1rem; color: var(--p-pink); }
        .modal-info-value.discount { color: var(--success); }

        .modal-file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            transition: var(--transition-smooth);
            cursor: pointer;
            background: #f8fafc;
            margin-bottom: 16px;
        }
        .modal-file-upload-area:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
            transform: translateY(-2px);
        }
        .modal-file-upload-area.has-file {
            border-color: var(--success);
            background: #ecfdf5;
        }
        .modal-file-upload-icon { font-size: 2rem; color: #94a3b8; margin-bottom: 8px; }
        .modal-file-upload-text { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); }
        .modal-file-upload-note { font-size: 0.72rem; color: #94a3b8; margin-top: 6px; }
        .modal-btn-submit {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            border: none;
            padding: 14px 24px;
            border-radius: var(--radius-lg);
            font-size: 0.95rem;
            font-weight: 900;
            cursor: pointer;
            transition: var(--transition-smooth);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 20px rgba(216, 63, 103, 0.25);
        }
        .modal-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(216, 63, 103, 0.35); }
        .modal-btn-submit:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }
        .modal-form-group { margin-bottom: 16px; }
        .modal-form-label { display: block; font-size: 0.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .modal-form-label span { color: var(--danger); }
        .modal-form-select, .modal-form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-dark);
            transition: var(--transition-smooth);
            background: #fff;
        }
        .modal-form-select:focus, .modal-form-input:focus {
            outline: none;
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.08);
        }

        /* ===== MODAL RATING ===== */
        .modal-rating-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-rating-overlay.active { display: flex; }
        .modal-rating-content {
            background: #fff;
            border-radius: var(--radius-xl);
            padding: 32px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-hover);
            animation: modalIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
        }
        .modal-rating-content h3 {
            color: var(--text-dark);
            margin-bottom: 24px;
            font-weight: 900;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .modal-rating-content h3 i { color: #f59e0b; }
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .star-rating i {
            font-size: 2.5rem;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .star-rating i.active { color: #f59e0b; transform: scale(1.15); }
        .star-rating i:hover { transform: scale(1.2); }
        .modal-rating-content textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
        }
        .modal-rating-content textarea:focus { outline: none; border-color: var(--p-pink); }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-actions button {
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            border: none;
            transition: var(--transition-smooth);
        }
        .modal-actions .btn-batal-modal { background: #f1f5f9; color: #64748b; }
        .modal-actions .btn-batal-modal:hover { background: #e2e8f0; }
        .modal-actions .btn-submit-rating { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; }
        .modal-actions .btn-submit-rating:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(216, 63, 103, 0.25); }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes celebrateBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        @keyframes warningPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .top-navbar { padding: 14px 20px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .breadcrumb-bar { padding: 14px 20px; }
            .modal-content { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .order-grid { grid-template-columns: 1fr; }
            .pembayaran-grid { grid-template-columns: 1fr; }
            .barang-cetak-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .modal-body-popup { padding: 20px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .order-header { flex-direction: column; align-items: flex-start; }
            .paket-section { flex-direction: column; }
            .btn-aksi { width: 100%; justify-content: center; }
        }

        /* ===== MOBILE SIDEBAR ===== */
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: #ffffff;
            z-index: 1050;
            transform: translateX(-100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.08);
            border-right: 1px solid rgba(255, 228, 233, 0.8);
        }
        .mobile-sidebar.open { transform: translateX(0); }
        .mobile-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 1045;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-sidebar-overlay.show { display: block; opacity: 1; }
        .mobile-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .mobile-sidebar-brand {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1px;
        }
        .mobile-sidebar-brand span {
            color: var(--text-dark);
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }
        .mobile-sidebar-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--s-pink);
            color: var(--p-pink);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .mobile-sidebar-close:hover {
            background: var(--p-pink);
            color: #fff;
        }
        .mobile-sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            scrollbar-width: none;
        }
        .mobile-sidebar-menu::-webkit-scrollbar { display: none; }
        .mobile-sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #475569;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            margin-bottom: 6px;
            border: none;
            background: none;
            width: 100%;
            cursor: pointer;
            text-align: left;
        }
        .mobile-sidebar-link:hover,
        .mobile-sidebar-link.active {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .mobile-sidebar-link i {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
        }
        .mobile-sidebar-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 12px 0;
        }
        .mobile-sidebar-footer {
            padding: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .mobile-sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--s-pink);
            border-radius: 14px;
        }
        .mobile-sidebar-user img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
        }
        .mobile-sidebar-user-info {
            flex: 1;
            min-width: 0;
        }
        .mobile-sidebar-user-name {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mobile-sidebar-user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .mobile-menu-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            background: var(--s-pink);
            color: var(--p-pink);
            font-size: 1.3rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-smooth);
            margin-right: 10px;
            flex-shrink: 0;
        }
        .mobile-menu-toggle:hover {
            background: var(--p-pink);
            color: #fff;
        }

        /* ========== MOBILE SIDEBAR RESPONSIVE ========== */
        @media (max-width: 991.98px) {
            .mobile-menu-toggle { display: flex; }
            .top-navbar { padding: 12px 16px; }
            .nav-logo { font-size: 1.4rem; }
            .nav-logo span { font-size: 0.75rem; }
            .nav-menu-center { display: none; }
            .nav-btn-booking { padding: 8px 16px; font-size: 0.8rem; }
            .nav-avatar { width: 36px; height: 36px; }
            .nav-dropdown { right: -10px; min-width: 200px; border-radius: 12px; padding: 8px; }
            .dropdown-header { font-size: 0.9rem; padding: 6px 12px; }
            .dropdown-item { padding: 10px 12px; font-size: 0.85rem; }
        }
        @media (max-width: 767.98px) {
            .top-navbar { padding: 10px 12px; }
            .nav-logo { font-size: 1.2rem; }
            .nav-logo span { display: none; }
            .nav-right { gap: 10px; }
            .nav-btn-booking { padding: 8px 12px; font-size: 0.75rem; }
            .nav-btn-booking i { display: none; }
            .breadcrumb-bar { padding: 10px 16px; }
            .breadcrumb-inner { font-size: 0.8rem; }
            .main-container { padding: 16px 12px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .page-title h1 { font-size: 1.4rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
            .stat-card .stat-value { font-size: 1.3rem; }
            .tabs-header { padding: 0 6px; }
            .tab-btn { padding: 12px 14px; font-size: 0.8rem; gap: 6px; }
            .order-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .order-body { padding: 16px; }
            .order-grid { grid-template-columns: 1fr; gap: 20px; }
            .paket-section { flex-direction: column; }
            .paket-img { width: 80px; height: 80px; }
            .paket-price { font-size: 1.1rem; }
            .detail-item { padding: 10px 12px; }
            .barang-cetak-grid { grid-template-columns: 1fr; }
            .pembayaran-grid { grid-template-columns: 1fr; }
            .order-aksi { flex-direction: column; }
            .btn-aksi { width: 100%; justify-content: center; padding: 10px 16px; }
            .modal-content { max-width: 95%; margin: 10px auto; }
            .modal-body-popup { padding: 20px; }
            .modal-payment-grid { grid-template-columns: 1fr; }
            .modal-dp-value { font-size: 1.4rem; }
            .modal-header-popup { padding: 16px 20px; }
            .modal-header-popup h3 { font-size: 1rem; }
            .modal-detail-img { width: 48px; height: 48px; }
            .modal-qris-img { width: 120px; height: 120px; }
            .modal-rating-content { padding: 24px; max-width: 95%; }
            .star-rating i { font-size: 2rem; }
        }
        @media (max-width: 575.98px) {
            .top-navbar { padding: 8px 10px; }
            .nav-logo { font-size: 1.1rem; }
            .nav-btn-booking { padding: 6px 10px; font-size: 0.7rem; border-radius: 8px; }
            .nav-avatar { width: 32px; height: 32px; }
            .breadcrumb-bar { padding: 8px 12px; }
            .main-container { padding: 12px 10px; }
            .page-title h1 { font-size: 1.2rem; }
            .page-title h1 i { font-size: 1.2rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 12px; border-radius: var(--radius-md); }
            .tab-btn { padding: 10px 12px; font-size: 0.75rem; white-space: nowrap; }
            .tab-btn .tab-count { padding: 1px 6px; font-size: 0.65rem; min-width: 18px; }
            .tabs-header { scrollbar-width: none; }
            .tabs-header::-webkit-scrollbar { display: none; }
            .order-card { border-radius: var(--radius-md); }
            .order-header { padding: 14px 16px; }
            .order-body { padding: 14px; }
            .paket-img { width: 70px; height: 70px; border-radius: 10px; }
            .paket-info h3 { font-size: 1rem; }
            .paket-meta span { padding: 4px 10px; font-size: 0.7rem; }
            .detail-item i { width: 32px; height: 32px; font-size: 0.8rem; }
            .detail-item .detail-value { font-size: 0.85rem; }
            .barang-cetak-item { padding: 10px; }
            .barang-cetak-item img { width: 40px; height: 40px; }
            .pembayaran-box { padding: 14px; }
            .pembayaran-box .pay-amount { font-size: 1rem; }
            .empty-state { padding: 40px 16px; }
            .empty-state i { font-size: 3rem; }
            .modal-content { border-radius: var(--radius-md); }
            .modal-body-popup { padding: 16px; }
            .modal-upload-card { padding: 16px; }
            .modal-dp-amount { padding: 14px; }
            .modal-dp-value { font-size: 1.2rem; }
            .modal-form-select, .modal-form-input { padding: 10px 12px; }
            .modal-btn-submit { padding: 12px 16px; }
            .modal-actions { flex-direction: column-reverse; }
            .modal-actions button { width: 100%; padding: 10px; }
            .modal-rating-content { padding: 20px; }
            .modal-rating-content h3 { font-size: 1rem; }
            .star-rating { gap: 6px; }
            .star-rating i { font-size: 1.8rem; }
        }
        @media (max-width: 359.98px) {
            .nav-logo { font-size: 1rem; }
            .nav-btn-booking { padding: 5px 8px; font-size: 0.65rem; }
            .mobile-sidebar { width: 260px; }
            .page-title h1 { font-size: 1.1rem; }
            .stat-card .stat-value { font-size: 1.1rem; }
        }

        /* ========== TOUCH DEVICE: DISABLE HOVER ========== */
        @media (hover: none) and (pointer: coarse) {
            .stat-card:hover { transform: none; box-shadow: var(--shadow-soft); border-color: transparent; }
            .order-card:hover { transform: none; box-shadow: var(--shadow-soft); border-color: var(--glass-border); }
            .tab-btn:hover { color: var(--text-muted); }
            .tab-btn.active:hover { color: var(--p-pink); }
            .btn-aksi:hover { transform: none; }
            .nav-avatar:hover { transform: none; border-color: var(--light-pink); box-shadow: 0 2px 8px rgba(216, 63, 103, 0.15); }
            .nav-btn-booking:hover { transform: none; box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3); }
            .nav-link-item:hover::after { width: 0; }
            .nav-link-item.active::after { width: 100%; }
            .paket-section:hover .paket-img { transform: none; }
            .detail-item:hover { background: linear-gradient(135deg, #fafafa, #ffffff); }
            .detail-item:hover i { transform: none; }
        }

        /* ========== REDUCED MOTION ========== */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
            html { scroll-behavior: auto; }
            .mobile-sidebar { transition: none; }
        }
    </style>
</head>
<body>

    <!-- MOBILE SIDEBAR OVERLAY -->
    <div class="mobile-sidebar-overlay" id="mobileSidebarOverlay" onclick="toggleMobileSidebar()"></div>

    <!-- MOBILE SIDEBAR -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <a href="../index.php" class="mobile-sidebar-brand">SpotLight.<span>StudioFoto</span></a>
            <button class="mobile-sidebar-close" onclick="toggleMobileSidebar()" aria-label="Tutup menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="mobile-sidebar-menu">
            <a href="../index.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="../Layanan/Paket/pilih_paket.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-calendar-plus-fill"></i> Booking Baru
            </a>
            <a href="riwayat.php" class="mobile-sidebar-link active" onclick="toggleMobileSidebar()">
                <i class="bi bi-clock-history"></i> Riwayat
            </a>
            <a href="../Hasil Foto/hasil_foto.php" class="mobile-sidebar-link" onclick="toggleMobileSidebar()">
                <i class="bi bi-images"></i> Hasil Foto
            </a>
            <div class="mobile-sidebar-divider"></div>
            <a href="../../index.php" class="mobile-sidebar-link" onclick="return confirmLandingPage(event)">
                <i class="bi bi-house-door"></i> Beranda
            </a>
            <button class="mobile-sidebar-link text-danger" onclick="confirmLogout(); toggleMobileSidebar();">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </button>
        </div>
        <div class="mobile-sidebar-footer">
            <div class="mobile-sidebar-user">
                <img src="<?php echo $foto_pelanggan_src; ?>" alt="Profil">
                <div class="mobile-sidebar-user-info">
                    <div class="mobile-sidebar-user-name"><?php echo htmlspecialchars($nama_pelanggan); ?></div>
                    <div class="mobile-sidebar-user-role">Pelanggan</div>
                </div>
            </div>
        </div>
    </div>

<!-- NAVBAR ATAS SINKRON -->
<nav class="top-navbar">
    <div class="d-flex align-items-center">
        <button class="mobile-menu-toggle d-lg-none" onclick="toggleMobileSidebar()" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>
        <a href="../index.php" class="nav-logo">
        SpotLight.<span>StudioFoto</span>
    </a>
    </div>
    <div class="nav-menu-center">
        <a href="../index.php" class="nav-link-item">Dashboard</a>
        <a href="../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
        <a href="riwayat.php" class="nav-link-item active">Riwayat</a>
        <a href="../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>
    </div>
    <div class="nav-right">
        <a href="../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
            <i class="bi bi-plus-lg"></i> Booking
        </a>
        <div class="nav-avatar-wrapper">
            <img src="<?php echo $foto_pelanggan_src; ?>" alt="Profil" class="nav-avatar" onclick="toggleDropdown()">
            <div class="nav-dropdown" id="navDropdown">
                <div class="dropdown-header">Halo, <?php echo htmlspecialchars($nama_pelanggan); ?></div>
                <div class="dropdown-divider"></div>
                <a href="../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                    <i class="bi bi-house-door"></i> Kembali ke Beranda
                </a>
                <div class="dropdown-divider"></div>
                <button class="dropdown-item logout" onclick="confirmLogout()">
                    <i class="bi bi-box-arrow-right"></i> Keluar Sistem
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- BREADCRUMB -->
<div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
        <a href="../index.php">Home</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current"><i class="bi bi-clock-history"></i> Riwayat Transaksi</span>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-container">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-title">
            <h1><i class="bi bi-clock-history"></i> Riwayat Transaksi</h1>
            <p>Kelola dan pantau semua pesanan foto dan barang cetak Anda</p>
        </div>
    </div>

    <!-- NOTIFIKASI SUKSES SELESAI (tampil jika ada order yang baru lunas) -->
    <?php 
    $has_new_complete = false;
    foreach ($riwayat_list as $item) {
        if ($item['Status_Order'] == STATUS_ORDER_LUNAS && !empty($item['Rating'])) {
            $has_new_complete = true;
            break;
        }
    }
    if ($has_new_complete): 
    ?>
    <div class="notif-selesai">
        <i class="bi bi-trophy-fill"></i>
        <div>
            <div class="notif-selesai-text"><i class="bi bi-check-circle-fill" style="color:var(--success);"></i> Selamat! Pesanan Anda telah selesai dengan sempurna</div>
            <div class="notif-selesai-sub">Terima kasih telah menggunakan layanan SpotLight Studio. Jangan lupa beri rating!</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- STATS GRID SINKRON -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="bi bi-bag-check-fill"></i></div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Pesanan</div>
        </div>
        <div class="stat-card menunggu">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?php echo $stats['menunggu_dp']; ?></div>
            <div class="stat-label">Menunggu DP</div>
        </div>
        <div class="stat-card proses">
            <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
            <div class="stat-value"><?php echo $stats['dp_verified'] + $stats['selesai_foto']; ?></div>
            <div class="stat-label">Dalam Proses</div>
        </div>
        <div class="stat-card selesai">
            <div class="stat-icon"><i class="bi bi-check2-all"></i></div>
            <div class="stat-value"><?php echo $stats['lunas']; ?></div>
            <div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card batal">
            <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?php echo $stats['dibatalkan']; ?></div>
            <div class="stat-label">Dibatalkan</div>
        </div>
        <div class="stat-card expired">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value"><?php echo $stats['expired']; ?></div>
            <div class="stat-label">Kadaluarsa</div>
        </div>
    </div>

    <!-- TABS CONTAINER -->
    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn <?php echo $tab=='semua'?'active':''; ?>" onclick="location.href='?tab=semua'">
                <i class="bi bi-grid"></i> Semua <span class="tab-count"><?php echo $stats['total']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='menunggu'?'active':''; ?>" onclick="location.href='?tab=menunggu'">
                <i class="bi bi-hourglass-split"></i> Menunggu <span class="tab-count"><?php echo $stats['menunggu_dp']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='proses'?'active':''; ?>" onclick="location.href='?tab=proses'">
                <i class="bi bi-arrow-repeat"></i> Proses <span class="tab-count"><?php echo $stats['dp_verified'] + $stats['selesai_foto']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='selesai'?'active':''; ?>" onclick="location.href='?tab=selesai'">
                <i class="bi bi-check2-all"></i> Selesai <span class="tab-count"><?php echo $stats['lunas']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='batal'?'active':''; ?>" onclick="location.href='?tab=batal'">
                <i class="bi bi-x-circle"></i> Dibatalkan <span class="tab-count"><?php echo $stats['dibatalkan']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='expired'?'active':''; ?>" onclick="location.href='?tab=expired'">
                <i class="bi bi-clock-history"></i> Kadaluarsa <span class="tab-count"><?php echo $stats['expired']; ?></span>
            </button>
        </div>

        <div style="padding: 24px;">
            <?php if (empty($filtered_list)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>Belum Ada Riwayat</h3>
                    <p>Anda belum memiliki transaksi pada kategori ini.</p>
                    <a href="../Layanan/Paket/pilih_paket.php" class="btn-primary">
                        <i class="bi bi-plus-lg"></i> Booking Baru
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <?php 
                    $card_delay = 0;
                    foreach ($filtered_list as $item): 
                        $is_batal = ($item['Status_Order'] == STATUS_ORDER_DIBATALKAN);
                        $id_order = $item['ID_Order'];
                        $barang_order = $barang_per_order[$id_order] ?? [];
                        $order_schedules = $jadwal_per_order[$id_order] ?? [];
                        $is_expired = isset($jadwal_expired[$id_order]) && $jadwal_expired[$id_order] && $item['Status_Order'] != STATUS_ORDER_LUNAS && $item['Status_Order'] != STATUS_ORDER_DIBATALKAN;
                        $card_class = $is_batal ? 'batal' : ($is_expired ? 'expired' : ($item['Status_Order'] == STATUS_ORDER_LUNAS ? 'lunas' : ''));
                        $is_lunas_sekaligus = isBayarLunasSekaligus($item);
                        $sumber_lunas = sumberLunasSekaligus($item); // 'DP' | 'Pelunasan' | null
                        $card_delay += 0.05;
                    ?>
                    <div class="order-card <?php echo $card_class; ?>" style="animation-delay: <?php echo $card_delay; ?>s;">
                        <div class="order-header">
                            <div class="order-id">
                                <strong>#ORDER-<?php echo str_pad($item['ID_Order'], 4, '0', STR_PAD_LEFT); ?></strong>
                            </div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <div class="order-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo formatTanggalIndo($item['Tanggal_Booking']); ?>
                                </div>
                                <?php echo getStatusBadge($item['Status_Order'], $is_expired, $is_lunas_sekaligus); ?>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-grid">
                                <!-- KIRI: Paket Info -->
                                <div class="paket-section">
                                    <?php 
                                    $foto_paket = $item['Foto_Paket'] ?? 'default_paket.jpg';
                                    $foto_src = file_exists("../../../assets/img/paket/" . $foto_paket) 
                                        ? "../../../assets/img/paket/" . $foto_paket 
                                        : "../../../assets/img/paket/default_paket.jpg";
                                    ?>
                                    <img src="<?php echo $foto_src; ?>" alt="Paket" class="paket-img">
                                    <div class="paket-info">
                                        <h3><?php echo htmlspecialchars($item['Nama_Paket'] ?? 'Paket Tidak Ditemukan'); ?></h3>
                                        <div class="paket-meta">
                                            <span><i class="bi bi-clock"></i><?php echo $item['Durasi_Waktu'] ?? 0; ?> menit</span>
                                            <span><i class="bi bi-people"></i><?php echo $item['Kapasitas_Orang'] ?? 0; ?> orang</span>
                                            <span><i class="bi bi-door-open"></i><?php echo htmlspecialchars($item['Nama_Ruangan'] ?? '-'); ?></span>
                                            <?php if (!empty($item['Nama_Tema'])): ?>
                                            <span><i class="bi bi-palette"></i><?php echo htmlspecialchars($item['Nama_Tema']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="paket-price"><?php echo formatRupiah($item['Total_Paket'] ?? 0); ?></div>
                                        <?php if ($is_expired): ?>
                                        <div class="expired-badge">
                                            <i class="bi bi-clock-history"></i> Jadwal sudah lewat — tidak dapat diproses
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- KANAN: Detail Info -->
                                <div class="detail-section">
                                    <!-- Multi-Slot Jadwal -->
                                    <?php if (!empty($order_schedules)): ?>
                                    <div class="detail-item">
                                        <i class="bi bi-calendar-check"></i>
                                        <div>
                                            <div class="detail-label">Tanggal Sesi (<?php echo count($order_schedules); ?> Slot)</div>
                                            <div class="detail-value">
                                                <?php 
                                                foreach ($order_schedules as $idx => $os) {
                                                    $expired_class = $os['Is_Expired'] ? ' style="color:#dc2626;text-decoration:line-through;"' : '';
                                                    echo '<div' . $expired_class . '>' . formatTanggalIndo($os['Tanggal_Jadwal']) . ' | ' . $os['Jam_Mulai'] . ' - ' . $os['Jam_Selesai'] . ' WIB' . ($os['Is_Expired'] ? ' <span style="color:#dc2626;font-size:0.75rem;"><i class="bi bi-clock-history"></i> Lewat</span>' : '') . '</div>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['Nama_Fotografer'])): ?>
                                    <div class="detail-item">
                                        <i class="bi bi-person-badge"></i>
                                        <div>
                                            <div class="detail-label">Fotografer</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($item['Nama_Fotografer']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['Total_Barang_Cetak']) && $item['Total_Barang_Cetak'] > 0): ?>
                                    <div class="detail-item" style="background: linear-gradient(135deg, var(--s-pink), #ffffff);">
                                        <i class="bi bi-bag" style="background: var(--p-pink); color: #fff;"></i>
                                        <div>
                                            <div class="detail-label">Total Barang Cetak</div>
                                            <div class="detail-value" style="color:var(--p-pink);font-weight:900;"><?php echo formatRupiah($item['Total_Barang_Cetak']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- BARANG CETAK -->
                            <?php if (!empty($barang_order)): ?>
                            <div class="barang-cetak-section">
                                <h4><i class="bi bi-box-seam"></i> Detail Barang Cetak</h4>
                                <div class="barang-cetak-grid">
                                    <?php foreach ($barang_order as $b): 
                                        $foto_barang = $b['Foto_Barang'] ?? 'default_barang.jpg';
                                        $foto_barang_src = file_exists("../../../assets/img/barang/" . $foto_barang) 
                                            ? "../../../assets/img/barang/" . $foto_barang 
                                            : "../../../assets/img/barang/default_barang.jpg";
                                    ?>
                                    <div class="barang-cetak-item">
                                        <img src="<?php echo $foto_barang_src; ?>" alt="<?php echo htmlspecialchars($b['Nama_Barang']); ?>">
                                        <div class="barang-cetak-info">
                                            <div class="barang-cetak-nama"><?php echo htmlspecialchars($b['Nama_Barang']); ?></div>
                                            <div class="barang-cetak-detail"><?php echo $b['Jumlah']; ?> x <?php echo formatRupiah($b['Harga_Satuan']); ?></div>
                                        </div>
                                        <div class="barang-cetak-subtotal"><?php echo formatRupiah($b['Subtotal']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top:12px;text-align:right;">
                                    <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">Status Penjualan: </span>
                                    <?php echo getStatusPenjualanBadge($barang_order[0]['Status_Penjualan'] ?? null); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- PEMBAYARAN -->
                            <div class="pembayaran-section">
                                <h4><i class="bi bi-credit-card-2-front"></i> Informasi Pembayaran</h4>
                                <?php if ($is_lunas_sekaligus): ?>
                                <div style="margin-bottom:10px;padding:8px 12px;background:#ecfdf5;color:#059669;border-radius:8px;font-size:0.82rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
                                    <i class="bi bi-info-circle"></i> Dibayar Lunas Sekaligus (tidak ada tahap Pelunasan terpisah)
                                </div>
                                <?php endif; ?>
                                <div class="pembayaran-grid">
                                    <!-- DP Box -->
                                    <div class="pembayaran-box">
                                        <div class="pay-header">
                                            <span class="pay-label"><i class="bi bi-cash-coin" style="margin-right:6px;"></i><?php echo ($sumber_lunas === 'DP') ? 'Pembayaran (Lunas)' : 'DP (Uang Muka)'; ?></span>
                                            <?php
                                            if ($sumber_lunas === 'Pelunasan') {
                                                // Lunas sekaligus tercatat di kolom Pelunasan -> DP memang tidak dipakai
                                                echo '<span class="badge-pay badge-pay-verified"><i class="bi bi-check-lg"></i> Tidak Diperlukan</span>';
                                            } elseif (!empty($item['Status_DP']) || !empty($item['Jumlah_DP'])) {
                                                echo getStatusPembayaranBadge($item['Status_DP'] ?? null);
                                            } else {
                                                echo '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="pay-amount">
                                            <?php
                                            if ($sumber_lunas === 'Pelunasan') {
                                                echo '<span style="font-size:0.8rem;font-weight:600;color:var(--text-muted);">Sudah dibayar lunas via Pelunasan</span>';
                                            } else {
                                                echo !empty($item['Jumlah_DP']) ? formatRupiah($item['Jumlah_DP']) : '-';
                                            }
                                            ?>
                                        </div>
                                        <?php if ($sumber_lunas !== 'Pelunasan' && !empty($item['Metode_DP'])): ?>
                                        <div class="pay-metode"><i class="bi bi-bank"></i> <?php echo htmlspecialchars($item['Metode_DP']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($sumber_lunas !== 'Pelunasan' && !empty($item['Tgl_DP'])): ?>
                                        <div class="pay-detail">
                                            <i class="bi bi-calendar3" style="color:var(--text-muted);margin-right:4px;"></i>
                                            <?php echo formatTanggalIndo($item['Tgl_DP']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Pelunasan Box -->
                                    <div class="pembayaran-box pelunasan">
                                        <div class="pay-header">
                                            <span class="pay-label"><i class="bi bi-cash-stack" style="margin-right:6px;"></i><?php echo ($sumber_lunas === 'Pelunasan') ? 'Pembayaran (Lunas)' : 'Pelunasan'; ?></span>
                                            <?php
                                            if ($sumber_lunas === 'DP') {
                                                // Lunas sekaligus tercatat di kolom DP (data historis) -> Pelunasan memang tidak dipakai
                                                echo '<span class="badge-pay badge-pay-verified"><i class="bi bi-check-lg"></i> Tidak Diperlukan</span>';
                                            } elseif (!empty($item['Status_Pelunasan']) || !empty($item['Jumlah_Pelunasan'])) {
                                                echo getStatusPembayaranBadge($item['Status_Pelunasan'] ?? null);
                                            } else {
                                                echo '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="pay-amount">
                                            <?php
                                            if ($sumber_lunas === 'DP') {
                                                echo '<span style="font-size:0.8rem;font-weight:600;color:var(--text-muted);">Sudah termasuk pembayaran lunas</span>';
                                            } else {
                                                echo !empty($item['Jumlah_Pelunasan']) ? formatRupiah($item['Jumlah_Pelunasan']) : '-';
                                            }
                                            ?>
                                        </div>
                                        <?php if ($sumber_lunas !== 'DP' && !empty($item['Metode_Pelunasan'])): ?>
                                        <div class="pay-metode"><i class="bi bi-bank"></i> <?php echo htmlspecialchars($item['Metode_Pelunasan']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($sumber_lunas !== 'DP' && !empty($item['Tgl_Pelunasan'])): ?>
                                        <div class="pay-detail">
                                            <i class="bi bi-calendar3" style="color:var(--text-muted);margin-right:4px;"></i>
                                            <?php echo formatTanggalIndo($item['Tgl_Pelunasan']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AKSI BUTTONS -->
                        <div class="order-aksi">
                            <?php echo getAksiButtons($item, $jadwal_expired, $jadwal_per_order); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =====================================================
MODAL POPUP PEMBAYARAN SINKRON DENGAN pembayaran_dp.php
===================================================== -->
<div class="modal-overlay" id="modalPembayaran">
    <div class="modal-content">
        <div class="modal-header-popup">
            <h3><i class="bi bi-receipt"></i> Pembayaran</h3>
            <button class="modal-close-btn" onclick="tutupModalPembayaran()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body-popup">
            <div class="modal-payment-grid">
                <!-- LEFT: Detail Order -->
                <div>
                    <!-- Paket Detail -->
                    <div class="modal-detail-card">
                        <div class="modal-detail-title"><i class="bi bi-box-seam-fill"></i> Paket Foto</div>
                        <div class="modal-detail-item" id="modalPaketItem">
                            <img src="../../../assets/img/paket/default_paket.jpg" class="modal-detail-img" id="modalPaketImg" alt="Paket">
                            <div class="modal-detail-info">
                                <div class="modal-detail-label">Paket Dipilih</div>
                                <div class="modal-detail-value" id="modalPaketNama">-</div>
                                <div class="modal-detail-sub" id="modalPaketSub">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Ruangan Detail -->
                    <div class="modal-detail-card">
                        <div class="modal-detail-title"><i class="bi bi-door-open-fill"></i> Ruangan</div>
                        <div class="modal-detail-item">
                            <img src="../../../assets/img/ruangan/default_ruangan.jpg" class="modal-detail-img" id="modalRuanganImg" alt="Ruangan">
                            <div class="modal-detail-info">
                                <div class="modal-detail-label">Ruangan Dipilih</div>
                                <div class="modal-detail-value" id="modalRuanganNama">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tema Detail -->
                    <div class="modal-detail-card">
                        <div class="modal-detail-title"><i class="bi bi-image-fill"></i> Tema Foto</div>
                        <div class="modal-detail-item">
                            <img src="../../../assets/img/tema/default_tema.jpg" class="modal-detail-img" id="modalTemaImg" alt="Tema">
                            <div class="modal-detail-info">
                                <div class="modal-detail-label">Tema Dipilih</div>
                                <div class="modal-detail-value" id="modalTemaNama">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Jadwal -->
                    <div class="modal-detail-card">
                        <div class="modal-detail-title"><i class="bi bi-calendar-check-fill"></i> Jadwal Sesi</div>
                        <div id="modalJadwalContainer"></div>
                    </div>

                    <!-- Rekening Bank -->
                    <div class="modal-detail-card">
                        <div class="modal-detail-title"><i class="bi bi-bank"></i> Rekening Pembayaran</div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 14px; font-weight: 600;">Silakan transfer ke salah satu rekening berikut:</p>
                        <?php foreach ($rekening_list as $idx => $rek): ?>
                        <div class="modal-rekening-card">
                            <div class="modal-rekening-bank"><?php echo htmlspecialchars($rek['nama_bank']); ?></div>
                            <div class="modal-rekening-no" onclick="copyRekeningPopup('<?php echo htmlspecialchars($rek['no_rekening']); ?>', this)">
                                <?php echo htmlspecialchars($rek['no_rekening']); ?> <i class="bi bi-copy" style="font-size: 0.7rem; margin-left: 6px;"></i>
                            </div>
                            <div class="modal-rekening-an">a.n. <?php echo htmlspecialchars($rek['atas_nama']); ?></div>
                            <button class="modal-copy-btn" onclick="copyRekeningPopup('<?php echo htmlspecialchars($rek['no_rekening']); ?>', this)">
                                <i class="bi bi-clipboard"></i> Salin Nomor
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- QRIS -->
                    <div class="modal-qris-card">
                        <div class="modal-qris-title"><i class="bi bi-qr-code"></i> Pembayaran QRIS</div>
                        <?php 
                        $qris_path = '../../../assets/img/qris/qris_spotlight.jpeg';
                        if (file_exists($qris_path)): 
                        ?>
                            <img src="<?php echo $qris_path; ?>" alt="QRIS" class="modal-qris-img">
                        <?php else: ?>
                            <div style="padding:30px 20px;background:linear-gradient(135deg,#f8fafc,#fff5f6);border-radius:16px;border:2px dashed var(--light-pink);text-align:center;margin-bottom:12px;">
                                <i class="bi bi-qr-code-scan" style="font-size:3rem;color:var(--p-pink);margin-bottom:12px;display:block;"></i>
                                <div style="font-weight:800;font-size:1rem;color:var(--text-dark);margin-bottom:6px;">QRIS SpotLight Studio</div>
                                <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Scan QRIS ini untuk pembayaran</div>
                            </div>
                        <?php endif; ?>
                        <div class="modal-qris-note">Scan QRIS di atas untuk pembayaran</div>
                        <div class="modal-qris-note" style="margin-top: 4px; font-weight: 800; color: var(--p-pink);">a.n. SpotLight Studio Foto</div>
                    </div>
                </div>

                <!-- RIGHT: Upload Form -->
                <div class="modal-upload-card">
                    <div class="modal-upload-title"><i class="bi bi-receipt"></i> Ringkasan Pembayaran</div>

                    <div class="modal-info-row">
                        <span class="modal-info-label">No. Booking</span>
                        <span class="modal-info-value" id="modalOrderId" style="font-weight: 900;">#0000</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Harga Paket</span>
                        <span class="modal-info-value" id="modalHargaPaket">Rp 0</span>
                    </div>
                    <div class="modal-info-row" id="modalRowCetak" style="display:none;">
                        <span class="modal-info-label">Total Produk Cetak</span>
                        <span class="modal-info-value" id="modalTotalCetak">Rp 0</span>
                    </div>
                    <div class="modal-info-row" id="modalRowDiskon" style="display:none;">
                        <span class="modal-info-label">Diskon Cetak (5%)</span>
                        <span class="modal-info-value discount" id="modalDiskonCetak">- Rp 0</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Biaya Ruangan</span>
                        <span class="modal-info-value" style="color:var(--success);">Gratis</span>
                    </div>
                    <div class="modal-info-row">
                        <span class="modal-info-label">Biaya Tema</span>
                        <span class="modal-info-value" style="color:var(--success);">Gratis</span>
                    </div>
                    <div style="height: 2px; background: linear-gradient(90deg, transparent, #f1f5f9, transparent); margin: 12px 0;"></div>
                    <div class="modal-info-row" style="border-bottom: none;">
                        <span class="modal-info-label" style="font-weight: 900;">Total Tagihan</span>
                        <span class="modal-info-value total" id="modalTotalHarga">Rp 0</span>
                    </div>

                    <div class="modal-dp-amount">
                        <div class="modal-dp-label" id="modalDpLabel">Nominal Pembayaran</div>
                        <div class="modal-dp-value" id="modalDpValue">Rp 0</div>
                        <div class="modal-dp-note" id="modalDpNote">-</div>
                    </div>

                    <form id="formPembayaranPopup" enctype="multipart/form-data">
                        <input type="hidden" name="action_upload_pembayaran" value="1">
                        <input type="hidden" name="id_order" id="payOrderId">
                        <input type="hidden" name="tipe_pembayaran" id="payTipe">
                        <input type="hidden" name="jumlah_bayar" id="payJumlah">

                        <div class="modal-form-group">
                            <label class="modal-form-label">Metode Pembayaran <span>*</span></label>
                            <select name="metode_pembayaran" id="payMetode" class="modal-form-select" required>
                                <option value="">-- Pilih Metode --</option>
                                <option value="Transfer Bank BCA">Transfer Bank BCA (123-456-7890 a/n SpotLight Studio)</option>
                                <option value="Transfer Bank BNI">Transfer Bank BNI (098-765-4321 a/n SpotLight Studio)</option>
                                <option value="Transfer Bank Mandiri">Transfer Bank Mandiri (112-233-4455 a/n SpotLight Studio)</option>
                                <option value="QRIS">QRIS SpotLight Studio (E-Wallet)</option>
                            </select>
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-form-label">Bukti Transfer <span>*</span></label>
                            <div class="modal-file-upload-area" id="fileUploadAreaPopup" onclick="document.getElementById('payBuktiPopup').click()">
                                <div class="modal-file-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="modal-file-upload-text" id="fileTextPopup">Klik untuk upload bukti transfer</div>
                                <div class="modal-file-upload-note">Format: JPG, PNG, PDF (Max 5MB)</div>
                            </div>
                            <input type="file" name="bukti_transfer" id="payBuktiPopup" style="display: none;" accept=".jpg,.jpeg,.png,.pdf" required onchange="handleFileSelectPopup(this)">
                        </div>

                        <button type="submit" class="modal-btn-submit" id="btnSubmitPopup">
                            <i class="bi bi-check2-circle"></i> Konfirmasi Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================
MODAL RATING & REVIEW
===================================================== -->
<div class="modal-rating-overlay" id="modalRating">
    <div class="modal-rating-content">
        <h3><i class="bi bi-star-fill"></i> Beri Rating & Review</h3>
        <div class="star-rating" id="starContainer">
            <i class="bi bi-star" data-value="1"></i>
            <i class="bi bi-star" data-value="2"></i>
            <i class="bi bi-star" data-value="3"></i>
            <i class="bi bi-star" data-value="4"></i>
            <i class="bi bi-star" data-value="5"></i>
        </div>
        <textarea id="reviewText" placeholder="Ceritakan pengalaman Anda... (opsional)"></textarea>
        <div class="modal-actions">
            <button class="btn-batal-modal" onclick="tutupModalRating()">Batal</button>
            <button class="btn-submit-rating" onclick="submitRating()">Kirim</button>
        </div>
    </div>
</div>

<script src="../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>

// FUNGSI TOGGLE MOBILE SIDEBAR
function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('mobileSidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}


// =====================================================
// DATA ORDER UNTUK POPUP (dari PHP)
// =====================================================
const orderData = <?php echo json_encode($order_data_js); ?>;

let currentOrderId = null;
let selectedRating = 0;

// =====================================================
// NAVBAR DROPDOWN
// =====================================================
function toggleDropdown() {
    document.getElementById('navDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.nav-avatar-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        document.getElementById('navDropdown').classList.remove('show');
    }
});

function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Kembali ke Beranda?',
        text: 'Anda akan meninggalkan halaman ini.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kembali',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../../../index.php';
        }
    });
    return false;
}

function confirmLogout() {
    Swal.fire({
        title: 'Keluar?',
        text: 'Apakah Anda yakin ingin keluar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../../../logout.php';
        }
    });
}

// =====================================================
// MODAL PEMBAYARAN POPUP - SINKRON DENGAN pembayaran_dp.php
// =====================================================
function bukaModalPembayaran(id_order, tipe, jumlah, total_harga) {
    currentOrderId = id_order;
    const data = orderData[id_order] || {};

    document.getElementById('payOrderId').value = id_order;
    document.getElementById('payTipe').value = tipe;
    document.getElementById('payJumlah').value = jumlah;

    // Update order ID
    document.getElementById('modalOrderId').textContent = '#' + String(id_order).padStart(4, '0');

    // Update paket info
    document.getElementById('modalPaketNama').textContent = data.nama_paket || '-';
    document.getElementById('modalPaketSub').textContent = (data.durasi || 0) + ' menit • Max ' + (data.kapasitas || 0) + ' orang';
    document.getElementById('modalPaketImg').src = '../../../assets/img/paket/' + (data.foto_paket || 'default_paket.jpg');

    // Update ruangan
    document.getElementById('modalRuanganNama').textContent = data.nama_ruangan || '-';
    document.getElementById('modalRuanganImg').src = '../../../assets/img/ruangan/' + (data.foto_ruangan || 'default_ruangan.jpg');

    // Update tema
    document.getElementById('modalTemaNama').textContent = data.nama_tema || '-';
    document.getElementById('modalTemaImg').src = '../../../assets/img/tema/' + (data.foto_tema || 'default_tema.jpg');

    // Update jadwal
    const jadwalContainer = document.getElementById('modalJadwalContainer');
    jadwalContainer.innerHTML = '';
    if (data.jadwal && data.jadwal.length > 0) {
        data.jadwal.forEach((j, idx) => {
            const div = document.createElement('div');
            div.className = 'modal-jadwal-card';
            div.innerHTML = `
                <div class="modal-jadwal-title"><i class="bi bi-clock"></i> Jadwal Terpilih (Slot ${idx + 1})</div>
                <div class="modal-jadwal-main">${j.split(' | ')[0]}</div>
                <div class="modal-jadwal-sub"><i class="bi bi-clock-fill"></i> ${j.split(' | ')[1] || ''}</div>
            `;
            jadwalContainer.appendChild(div);
        });
    }

    // Update ringkasan harga
    const formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });

    document.getElementById('modalHargaPaket').textContent = formatter.format(data.total_paket || 0);

    if ((data.total_cetak || 0) > 0) {
        document.getElementById('modalRowCetak').style.display = 'flex';
        document.getElementById('modalRowDiskon').style.display = 'flex';
        document.getElementById('modalTotalCetak').textContent = formatter.format(data.total_cetak || 0);
        document.getElementById('modalDiskonCetak').textContent = '- ' + formatter.format(data.diskon_cetak || 0);
    } else {
        document.getElementById('modalRowCetak').style.display = 'none';
        document.getElementById('modalRowDiskon').style.display = 'none';
    }

    document.getElementById('modalTotalHarga').textContent = formatter.format(data.total_harga || 0);

    // Update DP amount
    document.getElementById('modalDpLabel').textContent = tipe === 'DP' ? 'Nominal Pembayaran DP (65%)' : 'Nominal Pelunasan';
    document.getElementById('modalDpValue').textContent = formatter.format(jumlah);

    if (tipe === 'DP') {
        const sisa = (data.total_harga || 0) - jumlah;
        document.getElementById('modalDpNote').textContent = 'Sisa pembayaran ' + formatter.format(sisa) + ' dibayar setelah sesi selesai';
    } else {
        document.getElementById('modalDpNote').textContent = 'Pembayaran akhir untuk menyelesaikan order';
    }

    // Reset form
    document.getElementById('payMetode').value = '';
    document.getElementById('payBuktiPopup').value = '';
    const area = document.getElementById('fileUploadAreaPopup');
    const text = document.getElementById('fileTextPopup');
    area.classList.remove('has-file');
    text.innerHTML = '<i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#94a3b8;display:block;margin-bottom:8px;"></i> Klik untuk upload bukti transfer';

    const oldPreview = document.getElementById('previewBuktiPopup');
    if (oldPreview) oldPreview.remove();

    document.getElementById('modalPembayaran').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function tutupModalPembayaran() {
    document.getElementById('modalPembayaran').classList.remove('active');
    document.body.style.overflow = '';
    currentOrderId = null;
}

// Tutup modal dengan klik di luar
 document.getElementById('modalPembayaran').addEventListener('click', function(e) {
    if (e.target === this) tutupModalPembayaran();
});

// Copy rekening dalam popup
function copyRekeningPopup(noRek, el) {
    navigator.clipboard.writeText(noRek).then(() => {
        let btn = el;
        if (!el.classList.contains('modal-copy-btn')) {
            btn = el.closest('.modal-rekening-card').querySelector('.modal-copy-btn');
        }
        if (btn) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }

        tampilkanToast({
            icon: 'success',
            title: 'Tersalin!',
            text: 'Nomor rekening berhasil disalin ke clipboard.'
        });
    });
}

// File upload popup
function handleFileSelectPopup(input) {
    const area = document.getElementById('fileUploadAreaPopup');
    const text = document.getElementById('fileTextPopup');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File terlalu besar',
                text: 'Ukuran file maksimal 5MB.',
                confirmButtonColor: '#d83f67'
            });
            input.value = '';
            area.classList.remove('has-file');
            text.innerHTML = '<i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#94a3b8;display:block;margin-bottom:8px;"></i> Klik untuk upload bukti transfer';

            const oldPreview = document.getElementById('previewBuktiPopup');
            if (oldPreview) oldPreview.remove();
            return;
        }

        area.classList.add('has-file');
        text.innerHTML = '<i class="bi bi-check-circle-fill" style="color: var(--success); font-size: 2rem; display: block; margin-bottom: 8px;"></i> ' + file.name;

        const reader = new FileReader();
        reader.onload = function(e) {
            const oldPreview = document.getElementById('previewBuktiPopup');
            if (oldPreview) oldPreview.remove();

            const previewDiv = document.createElement('div');
            previewDiv.id = 'previewBuktiPopup';
            previewDiv.style.cssText = 'margin-top:12px;text-align:center;';
            previewDiv.innerHTML = '<div style="font-size:0.75rem;font-weight:800;color:var(--text-muted);margin-bottom:6px;"><i class="bi bi-eye me-1"></i> Preview</div><img src="' + e.target.result + '" style="max-width:100%;max-height:160px;border-radius:12px;border:2px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,0.08);" alt="Preview">';
            area.parentNode.appendChild(previewDiv);
        };
        reader.readAsDataURL(file);
    }
}

// Form submit popup
 document.getElementById('formPembayaranPopup').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    Swal.fire({
        title: 'Kirim Bukti Pembayaran?',
        text: 'Pastikan bukti transfer dan metode pembayaran sudah benar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kirim',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Mengirim...',
                html: 'Mohon tunggu sebentar.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('riwayat.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#d83f67'
                    }).then(() => {
                        tutupModalPembayaran();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#d83f67'
                    });
                }
            })
            .catch(err => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan sistem saat mengunggah bukti.',
                    confirmButtonColor: '#d83f67'
                });
            });
        }
    });
});

// =====================================================
// ANTI-NUMPUK NOTIFIKASI
// Semua Swal.fire pop-up harus lewat helper ini supaya
// notifikasi lama otomatis ditutup dulu sebelum yang baru muncul.
// =====================================================
function tampilkanNotif(opsi) {
    Swal.close(); // tutup notif yang sedang tampil (kalau ada) biar tidak numpuk
    return Swal.fire(opsi);
}

// Toast khusus (pojok kanan atas, auto-hilang) juga anti-numpuk
function tampilkanToast(opsi) {
    Swal.close();
    return Swal.fire(Object.assign({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    }, opsi));
}

// Kunci proses supaya tombol tidak bisa diklik berkali-kali
// selagi masih menunggu response server (penyebab utama notif numpuk).
const uiLock = { batal: false, rating: false, upload: false };

// =====================================================
// MODAL RATING
// =====================================================
const stars = document.querySelectorAll('#starContainer i');
stars.forEach(star => {
    star.addEventListener('click', function() {
        selectedRating = parseInt(this.dataset.value);
        updateStars(selectedRating);
    });
    star.addEventListener('mouseenter', function() {
        updateStars(parseInt(this.dataset.value));
    });
});
 document.getElementById('starContainer').addEventListener('mouseleave', function() {
    updateStars(selectedRating);
});

function updateStars(rating) {
    stars.forEach(s => {
        const val = parseInt(s.dataset.value);
        if (val <= rating) {
            s.classList.remove('bi-star');
            s.classList.add('bi-star-fill', 'active');
        } else {
            s.classList.remove('bi-star-fill', 'active');
            s.classList.add('bi-star');
        }
    });
}

function bukaRating(id_order) {
    currentOrderId = id_order;
    selectedRating = 0;
    updateStars(0);
    document.getElementById('reviewText').value = '';
    document.getElementById('modalRating').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function tutupModalRating() {
    document.getElementById('modalRating').classList.remove('active');
    document.body.style.overflow = '';
    currentOrderId = null;
    selectedRating = 0;
}

 document.getElementById('modalRating').addEventListener('click', function(e) {
    if (e.target === this) tutupModalRating();
});

function submitRating() {
    if (selectedRating === 0) {
        tampilkanNotif({
            icon: 'warning',
            title: 'Pilih Rating',
            text: 'Silakan pilih minimal 1 bintang!',
            confirmButtonColor: '#d83f67'
        });
        return;
    }

    if (uiLock.rating) return; // cegah double submit -> notif numpuk
    uiLock.rating = true;

    const review = document.getElementById('reviewText').value;

    fetch('action_rating.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_order=' + currentOrderId + '&rating=' + selectedRating + '&review=' + encodeURIComponent(review)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            tampilkanNotif({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Terima kasih atas rating dan review Anda!',
                confirmButtonColor: '#d83f67'
            }).then(() => {
                location.reload();
            });
        } else {
            uiLock.rating = false;
            tampilkanNotif({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#d83f67'
            });
        }
    })
    .catch(err => {
        uiLock.rating = false;
        tampilkanNotif({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi kesalahan sistem',
            confirmButtonColor: '#d83f67'
        });
    });
}

// =====================================================
// BATALKAN ORDER
// =====================================================
function batalkanOrder(id_order) {
    if (uiLock.batal) return; // cegah dialog konfirmasi terbuka berkali-kali

    tampilkanNotif({
        title: 'Batalkan Pesanan?',
        text: 'Apakah Anda yakin ingin membatalkan pesanan ini? Slot jadwal akan dilepas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tidak'
    }).then((result) => {
        if (result.isConfirmed) {
            uiLock.batal = true;

            tampilkanNotif({
                title: 'Membatalkan...',
                html: 'Mohon tunggu...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('action_batal.php?id_order=' + id_order)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    tampilkanNotif({
                        icon: 'success',
                        title: 'Dibatalkan',
                        text: data.message || 'Pesanan berhasil dibatalkan',
                        confirmButtonColor: '#d83f67'
                    }).then(() => location.reload());
                } else {
                    uiLock.batal = false;
                    tampilkanNotif({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message || 'Gagal membatalkan pesanan',
                        confirmButtonColor: '#d83f67'
                    });
                }
            })
            .catch(err => {
                uiLock.batal = false;
                tampilkanNotif({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal membatalkan pesanan',
                    confirmButtonColor: '#d83f67'
                });
            });
        }
    });
}

// =====================================================
// LIHAT DETAIL (untuk status DP Terverifikasi)
// =====================================================
function lihatDetail(id_order) {
    const data = orderData[id_order] || {};
    Swal.fire({
        title: 'Detail Sesi Pesanan #' + String(id_order).padStart(4, '0'),
        html: '<div style="text-align:left;">' +
              '<p><strong>Paket:</strong> ' + (data.nama_paket || '-') + '</p>' +
              '<p><strong>Ruangan:</strong> ' + (data.nama_ruangan || '-') + '</p>' +
              '<p><strong>Tema:</strong> ' + (data.nama_tema || '-') + '</p>' +
              '<p><strong>Total:</strong> Rp ' + (data.total_harga || 0).toLocaleString('id-ID') + '</p>' +
              '<hr style="margin:12px 0;border-color:#f1f5f9;">' +
              '<p style="font-size:0.85rem;color:#718096;">Sesi foto sudah terverifikasi dan dijadwalkan.</p>' +
              '<p style="font-size:0.85rem;color:#718096;">Silakan hubungi admin untuk koordinasi:</p>' +
              '</div>',
        icon: 'info',
        confirmButtonColor: '#d83f67',
        confirmButtonText: 'Tutup',
        showDenyButton: true,
        denyButtonText: '<i class="bi bi-whatsapp"></i> Hubungi Admin',
        denyButtonColor: '#25D366'
    }).then((result) => {
        if (result.isDenied) {
            window.open('https://wa.me/6287871438459', '_blank');
        }
    });
}

// =====================================================
// KEYBOARD SHORTCUT (Escape tutup modal)
// =====================================================
 document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupModalPembayaran();
        tutupModalRating();
    }
});
</script>

</body>
</html>