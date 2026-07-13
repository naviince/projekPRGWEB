<?php
session_start();
include '../../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat)
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
// HANDLE UPLOAD PEMBAYARAN (AJAX) - SELARAS LOGIKA REFERENSI DP
// =====================================================
if (isset($_POST['action_upload_pembayaran'])) {
    header('Content-Type: application/json');
    
    $id_order = intval($_POST['id_order']);
    $tipe_pembayaran = $_POST['tipe_pembayaran']; // 'DP' atau 'Pelunasan'
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);
    
    if (empty($metode_pembayaran) || $jumlah_bayar <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data input tidak valid.']);
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
    
    // Sesuaikan direktori upload ke assets/img/bukti/
    $upload_dir = '../../../assets/img/bukti/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Penamaan file diselaraskan dengan pola referensi
    $prefix = ($tipe_pembayaran === 'DP') ? 'bukti_dp_' : 'bukti_pelunasan_';
    $new_file_name = $prefix . $id_order . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $username_cust = $_SESSION['username'] ?? 'customer';
        
        // Memulai transaksi kueri agar penyimpanan data aman dari resiko kehilangan data
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

// Ambil data pelanggan (Path diselaraskan naik 3 tingkat ke direktori root agar avatar terbaca aman)
$q_pelanggan = sqlsrv_query($conn, "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?", [$id_pelanggan]);
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
if ($d_pelanggan) { $d_pelanggan = array_change_key_case($d_pelanggan, CASE_LOWER); }
$nama_pelanggan = $d_pelanggan['nama_pelanggan'] ?? 'Pelanggan';
$foto_pelanggan = $d_pelanggan['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_pelanggan_src = ($foto_pelanggan != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_pelanggan)) 
    ? "../../../assets/img/pelanggan/" . $foto_pelanggan 
    : $default_svg_avatar;

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI_FOTO', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_TERVERIFIKASI', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

define('STATUS_SESI_BELUM', 0);
define('STATUS_SESI_SELESAI', 1);

define('STATUS_PENJUALAN_PROSES', 0);
define('STATUS_PENJUALAN_SELESAI', 1);

// =====================================================
// QUERY RIWAYAT ORDER LENGKAP (REVISI SINKRONISASI RELASI MULTI JADWAL)
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

    pl.ID_Pembayaran as ID_Pelunasan,
    pl.Jumlah_Bayar as Jumlah_Pelunasan,
    pl.Status_Pembayaran as Status_Pelunasan,
    pl.Tanggal_Upload as Tgl_Pelunasan,
    pl.Bukti_Transfer as Bukti_Pelunasan

FROM [Order] o
LEFT JOIN Paket_Foto p ON o.ID_Paket = p.ID_Paket
LEFT JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
LEFT JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
LEFT JOIN Sesi_Foto sf ON o.ID_Order = sf.ID_Order
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
// TARIK MULTI-SLOT JADWAL STUDIO PER ORDER SECARA AKURAT
// =====================================================
$jadwal_per_order = [];
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
            j.Keterangan
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
            
            // Format Objek Tanggal dan Jam
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

            $jadwal_per_order[$id_order][] = $j;
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
// HITUNG STATISTIK
// =====================================================
$stats = [
    'total' => 0,
    'menunggu_dp' => 0,
    'dp_verified' => 0,
    'selesai_foto' => 0,
    'lunas' => 0,
    'dibatalkan' => 0
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
}

// =====================================================
// FILTER TAB
// =====================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'semua';
$filtered_list = [];
foreach ($riwayat_list as $item) {
    if ($tab === 'semua') {
        $filtered_list[] = $item;
    } elseif ($tab === 'menunggu' && $item['Status_Order'] == STATUS_ORDER_MENUNGGU_DP) {
        $filtered_list[] = $item;
    } elseif ($tab === 'proses' && in_array($item['Status_Order'], [STATUS_ORDER_DP_TERVERIFIKASI, STATUS_ORDER_SELESAI_FOTO])) {
        $filtered_list[] = $item;
    } elseif ($tab === 'selesai' && $item['Status_Order'] == STATUS_ORDER_LUNAS) {
        $filtered_list[] = $item;
    } elseif ($tab === 'batal' && $item['Status_Order'] == STATUS_ORDER_DIBATALKAN) {
        $filtered_list[] = $item;
    }
}

// =====================================================
// FUNGSI HELPER
// =====================================================
function getStatusBadge($status) {
    switch ($status) {
        case STATUS_ORDER_MENUNGGU_DP:
            return '<span class="badge badge-menunggu"><i class="fas fa-clock"></i> Menunggu Pembayaran DP</span>';
        case STATUS_ORDER_DP_TERVERIFIKASI:
            return '<span class="badge badge-dp"><i class="fas fa-check-circle"></i> DP Terverifikasi</span>';
        case STATUS_ORDER_SELESAI_FOTO:
            return '<span class="badge badge-foto"><i class="fas fa-camera"></i> Selesai Pemotretan</span>';
        case STATUS_ORDER_LUNAS:
            return '<span class="badge badge-lunas"><i class="fas fa-check-double"></i> Lunas & Selesai</span>';
        case STATUS_ORDER_DIBATALKAN:
            return '<span class="badge badge-batal"><i class="fas fa-times-circle"></i> Dibatalkan</span>';
        default:
            return '<span class="badge badge-menunggu">Unknown</span>';
    }
}

function getStatusPembayaranBadge($status) {
    switch ($status) {
        case STATUS_PEMBAYARAN_MENUNGGU:
            return '<span class="badge-pay badge-pay-wait"><i class="fas fa-hourglass-half"></i> Menunggu</span>';
        case STATUS_PEMBAYARAN_TERVERIFIKASI:
            return '<span class="badge-pay badge-pay-verified"><i class="fas fa-check"></i> Terverifikasi</span>';
        case STATUS_PEMBAYARAN_DITOLAK:
            return '<span class="badge-pay badge-pay-rejected"><i class="fas fa-times"></i> Ditolak</span>';
        default:
            return '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
    }
}

function getStatusPenjualanBadge($status) {
    switch ($status) {
        case STATUS_PENJUALAN_PROSES:
            return '<span class="badge-pay badge-pay-wait"><i class="fas fa-box"></i> Proses</span>';
        case STATUS_PENJUALAN_SELESAI:
            return '<span class="badge-pay badge-pay-verified"><i class="fas fa-check"></i> Selesai</span>';
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

function getAksiButtons($item) {
    $status = $item['Status_Order'];
    $id_order = $item['ID_Order'];
    $has_file = !empty($item['File_Hasil']);
    $id_sesi = $item['ID_Sesi_Foto'] ?? 0;
    $buttons = '';

    // Perhitungan total harga setelah diskon produk cetak 5% agar presisi
    $total_paket = (float)($item['Total_Paket'] ?? 0);
    $total_cetak = (float)($item['Total_Barang_Cetak'] ?? 0);
    $diskon_cetak = $total_cetak > 0 ? $total_cetak * 0.05 : 0;
    $total_harga_diskon = $total_paket + ($total_cetak - $diskon_cetak);

    switch ($status) {
        case STATUS_ORDER_MENUNGGU_DP:
            $dp_amount = $total_harga_diskon * 0.65;
            $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'DP\', ' . $dp_amount . ')" class="btn-aksi btn-upload"><i class="fas fa-upload"></i> Upload Bukti DP</button>';
            $buttons .= '<a href="javascript:void(0)" onclick="batalkanOrder(' . $id_order . ')" class="btn-aksi btn-batal"><i class="fas fa-times"></i> Batalkan</a>';
            break;

        case STATUS_ORDER_DP_TERVERIFIKASI:
            $buttons .= '<a href="javascript:void(0)" onclick="lihatDetail(' . $id_order . ')" class="btn-aksi btn-detail"><i class="fas fa-info-circle"></i> Lihat Detail</a>';
            if (!empty($item['Nama_Fotografer'])) {
                $buttons .= '<div class="fotografer-info"><i class="fas fa-user-tie"></i> Fotografer: <strong>' . htmlspecialchars($item['Nama_Fotografer']) . '</strong></div>';
            }
            break;

        case STATUS_ORDER_SELESAI_FOTO:
            $remaining_amount = $total_harga_diskon - ($item['Jumlah_DP'] ?? 0);
            $buttons .= '<button onclick="bukaModalPembayaran(' . $id_order . ', \'Pelunasan\', ' . $remaining_amount . ')" class="btn-aksi btn-upload" style="background:#388E3C;color:#fff;"><i class="fas fa-upload"></i> Upload Pelunasan</button>';
            if ($has_file && $id_sesi > 0) {
                $buttons .= '<a href="../../../assets/img/bukti/' . rawurlencode($item['File_Hasil']) . '" class="btn-aksi btn-preview" download><i class="fas fa-images"></i> Lihat Preview Hasil</a>';
            }
            break;

        case STATUS_ORDER_LUNAS:
            if ($has_file && $id_sesi > 0) {
                $buttons .= '<a href="../../../uploads/hasil/' . rawurlencode($item['File_Hasil']) . '" class="btn-aksi btn-download" download><i class="fas fa-download"></i> Download Hasil</a>';
            }
            if (empty($item['Rating'])) {
                $buttons .= '<a href="javascript:void(0)" onclick="bukaRating(' . $id_order . ')" class="btn-aksi btn-rating"><i class="fas fa-star"></i> Beri Rating</a>';
            } else {
                $buttons .= '<div class="rating-display">';
                for ($i = 1; $i <= 5; $i++) {
                    $buttons .= ($i <= $item['Rating']) ? '<i class="fas fa-star star-filled"></i>' : '<i class="far fa-star star-empty"></i>';
                }
                $buttons .= '</div>';
                if (!empty($item['Review'])) {
                    $buttons .= '<div class="review-text">"' . htmlspecialchars($item['Review']) . '"</div>';
                }
            }
            break;

        case STATUS_ORDER_DIBATALKAN:
            $buttons .= '<span class="text-batal"><i class="fas fa-ban"></i> Order ini telah dibatalkan</span>';
            break;
    }

    return $buttons;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - SpotLight Studio</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
            --primary: #D53D66;
            --primary-dark: #B82E52;
            --primary-light: #FFF0F3;
            --secondary: #1e1e24;
            --accent: #FF6694;
            --bg: #f8fafc;
            --white: #FFFFFF;
            --shadow: 0 8px 32px rgba(213, 61, 102, 0.15);
            --radius: 20px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); min-height: 100vh; }

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

        /* ===== MAIN CONTENT ===== */
        .main-content { padding: 40px; max-width: 1400px; margin: 0 auto; }
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 35px;
        }
        .page-title h1 { color: var(--secondary); font-size: 28px; font-weight: 800; }
        .page-title p { color: #888; font-size: 14px; margin-top: 4px; }

        /* ===== STATS GRID SINKRON ===== */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: var(--glass-bg); border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow-soft); transition: var(--transition-smooth);
            border-left: 4px solid var(--p-pink); position: relative; overflow: hidden;
            backdrop-filter: blur(16px); border-top: 1px solid var(--glass-border);
            border-right: 1px solid var(--glass-border); border-bottom: 1px solid var(--glass-border);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-card); }
        .stat-card::before {
            content: ''; position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--light-pink); opacity: 0.5;
        }
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--secondary); }
        .stat-card .stat-label { font-size: 13px; color: #888; margin-top: 2px; font-weight:700; }
        .stat-card.total .stat-icon { background: var(--primary-light); color: var(--p-pink); }
        .stat-card.total { border-left-color: var(--p-pink); }
        .stat-card.menunggu .stat-icon { background: #FFF8E1; color: #F9A825; }
        .stat-card.menunggu { border-left-color: #F9A825; }
        .stat-card.proses .stat-icon { background: #E3F2FD; color: #1976D2; }
        .stat-card.proses { border-left-color: #1976D2; }
        .stat-card.selesai .stat-icon { background: #E8F5E9; color: #388E3C; }
        .stat-card.selesai { border-left-color: #388E3C; }
        .stat-card.batal .stat-icon { background: #FFEBEE; color: #D32F2F; }
        .stat-card.batal { border-left-color: #D32F2F; }

        /* ===== TABS CONTAINER SINKRON ===== */
        .tabs-container {
            background: var(--glass-bg); border-radius: var(--radius);
            box-shadow: var(--shadow-soft); margin-bottom: 25px; overflow: hidden;
            backdrop-filter: blur(16px); border: 1px solid var(--glass-border);
        }
        .tabs-header {
            display: flex; border-bottom: 2px solid #f1f5f9; padding: 0 10px; overflow-x: auto;
        }
        .tabs-header::-webkit-scrollbar { display: none; }
        .tab-btn {
            padding: 16px 24px; border: none; background: none;
            color: #888; font-size: 14px; font-weight: 800; cursor: pointer;
            position: relative; transition: var(--transition-smooth); white-space: nowrap;
        }
        .tab-btn:hover { color: var(--p-pink); }
        .tab-btn.active { color: var(--p-pink); }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 3px;
            background: var(--p-pink); border-radius: 3px 3px 0 0;
        }
        .tab-btn .tab-count {
            display: inline-block; background: var(--primary-light); color: var(--p-pink);
            padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px;
            font-weight: 800;
        }

        /* ===== CARDS SINKRON ===== */
        .orders-container { display: flex; flex-direction: column; gap: 20px; }
        .order-card {
            background: var(--glass-bg); border-radius: var(--radius);
            box-shadow: var(--shadow-soft); overflow: hidden; transition: var(--transition-smooth);
            border: 1px solid var(--glass-border); backdrop-filter: blur(16px);
        }
        .order-card:hover { box-shadow: var(--shadow-card); border-color: var(--light-pink); transform: translateY(-2px); }
        .order-card.batal { opacity: 0.75; }
        .order-card.batal .order-header { background: #FFEBEE; }

        .order-header {
            padding: 16px 24px; background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-id { font-size: 14px; color: #666; }
        .order-id strong { color: var(--p-pink); font-size: 16px; }
        .order-date { font-size: 13px; color: #888; }
        .order-date i { margin-right: 5px; color: var(--p-pink); }

        .order-body { padding: 24px; }
        .order-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 30px;
        }
        @media (max-width: 768px) { .order-grid { grid-template-columns: 1fr; } }

        .paket-section { display: flex; gap: 16px; }
        .paket-img {
            width: 100px; height: 100px; border-radius: 12px; object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .paket-info h3 { color: var(--secondary); font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        .paket-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .paket-meta span {
            background: var(--primary-light); color: var(--p-pink);
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
        }
        .paket-price { font-size: 20px; font-weight: 800; color: var(--p-pink); }

        .detail-section { display: flex; flex-direction: column; gap: 12px; }
        .detail-item {
            display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px;
            background: #FAFAFA; border-radius: 10px;
        }
        .detail-item i {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--primary-light); color: var(--p-pink);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0; margin-top: 2px;
        }
        .detail-item .detail-label { font-size: 12px; color: #888; margin-bottom: 2px; font-weight: 700; }
        .detail-item .detail-value { font-size: 14px; color: var(--secondary); font-weight: 500; }

        /* ===== DETAIL BARANG CETAK ===== */
        .barang-cetak-section {
            margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ddd;
        }
        .barang-cetak-section h4 {
            font-size: 14px; color: var(--secondary); margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px; font-weight: 800;
        }
        .barang-cetak-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;
        }
        .barang-cetak-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; background: #FAFAFA; border-radius: 12px;
            border-left: 3px solid var(--p-pink);
        }
        .barang-cetak-item img {
            width: 50px; height: 50px; border-radius: 8px; object-fit: cover;
        }
        .barang-cetak-info { flex: 1; }
        .barang-cetak-nama { font-size: 13px; font-weight: 700; color: var(--secondary); }
        .barang-cetak-detail { font-size: 12px; color: #888; margin-top: 2px; }
        .barang-cetak-subtotal { font-size: 14px; font-weight: 800; color: var(--p-pink); }

        .pembayaran-section {
            margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ddd;
        }
        .pembayaran-section h4 {
            font-size: 14px; color: var(--secondary); margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px; font-weight: 800;
        }
        .pembayaran-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
        }
        @media (max-width: 768px) { .pembayaran-grid { grid-template-columns: 1fr; } }
        .pembayaran-box {
            background: #FAFAFA; border-radius: 12px; padding: 16px;
            border-left: 3px solid var(--p-pink);
        }
        .pembayaran-box.pelunasan { border-left-color: #388E3C; }
        .pembayaran-box .pay-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .pembayaran-box .pay-label { font-size: 13px; font-weight: 600; color: var(--secondary); }
        .pembayaran-box .pay-amount { font-size: 16px; font-weight: 700; color: var(--p-pink); }
        .pembayaran-box.pelunasan .pay-amount { color: #388E3C; }
        .pembayaran-box .pay-detail { font-size: 12px; color: #888; margin-top: 4px; }

        .order-aksi {
            padding: 16px 24px; background: #FAFAFA;
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
            border-top: 1px solid #f0f0f0;
        }
        .btn-aksi {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 10px; font-size: 13px;
            font-weight: 600; text-decoration: none; transition: var(--transition-smooth); border: none; cursor: pointer;
        }
        .btn-aksi:hover { transform: translateY(-2px); box-shadow: var(--shadow-card); }
        .btn-upload { background: var(--p-pink); color: var(--white); }
        .btn-batal { background: #FFEBEE; color: #D32F2F; }
        .btn-detail { background: #E3F2FD; color: #1976D2; }
        .btn-preview { background: #F3E5F5; color: #7B1FA2; }
        .btn-download { background: #E8F5E9; color: #388E3C; }
        .btn-rating { background: #FFF8E1; color: #F9A825; }
        .btn-rating:hover { background: #F9A825; color: var(--white); }

        .fotografer-info {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px; background: #E3F2FD; border-radius: 10px;
            font-size: 13px; color: #1976D2;
        }
        .text-batal { color: #D32F2F; font-size: 13px; font-weight: 500; }

        .rating-display { display: flex; gap: 4px; margin-top: 8px; }
        .star-filled { color: #F9A825; font-size: 18px; }
        .star-empty { color: #DDD; font-size: 18px; }
        .review-text {
            margin-top: 8px; padding: 10px 14px; background: #FFF8E1;
            border-radius: 10px; font-size: 13px; color: #666; font-style: italic;
        }

        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .badge-menunggu { background: #FFF8E1; color: #F9A825; }
        .badge-dp { background: #E3F2FD; color: #1976D2; }
        .badge-foto { background: #F3E5F5; color: #7B1FA2; }
        .badge-lunas { background: #E8F5E9; color: #388E3C; }
        .badge-batal { background: #FFEBEE; color: #D32F2F; }

        .badge-pay {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600;
        }
        .badge-pay-wait { background: #FFF8E1; color: #F9A825; }
        .badge-pay-verified { background: #E8F5E9; color: #388E3C; }
        .badge-pay-rejected { background: #FFEBEE; color: #D32F2F; }

        /* ===== FILE UPLOAD SINKRON ===== */
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius-md);
            padding: 30px;
            text-align: center;
            transition: var(--transition-smooth);
            cursor: pointer;
            background: #f8fafc;
        }
        .file-upload-area:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .file-upload-area.has-file {
            border-color: var(--success);
            background: #ecfdf5;
        }
        .file-upload-icon { font-size: 2.5rem; color: #94a3b8; margin-bottom: 12px; }
        .file-upload-text { font-size: 0.9rem; font-weight: 700; color: var(--text-muted); }
        .file-upload-note { font-size: 0.75rem; color: #94a3b8; margin-top: 8px; }

        .empty-state {
            text-align: center; padding: 60px 20px;
        }
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 20px; }
        .empty-state h3 { color: var(--secondary); font-size: 20px; margin-bottom: 8px; }
        .empty-state p { color: #888; font-size: 14px; }
        .empty-state .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 20px; padding: 12px 28px; background: var(--p-pink);
            color: var(--white); border-radius: 12px; text-decoration: none;
            font-weight: 800; font-size: 14px; transition: var(--transition-smooth);
        }
        .empty-state .btn-primary:hover { background: var(--d-pink); transform: translateY(-2px); }

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: var(--white); border-radius: var(--radius); padding: 30px;
            width: 90%; max-width: 450px; box-shadow: var(--shadow); animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-content h3 { color: var(--secondary); margin-bottom: 20px; text-align: center; font-weight:800; }
        .star-rating {
            display: flex; justify-content: center; gap: 10px; margin-bottom: 20px;
        }
        .star-rating i {
            font-size: 36px; color: #DDD; cursor: pointer; transition: all 0.2s;
        }
        .star-rating i.active { color: #F9A825; transform: scale(1.1); }
        .modal-content textarea {
            width: 100%; padding: 14px; border: 2px solid #f0f0f0; border-radius: 12px;
            font-size: 14px; resize: vertical; min-height: 100px; margin-bottom: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .modal-content textarea:focus { outline: none; border-color: var(--p-pink); }
        .modal-actions {
            display: flex; gap: 10px; justify-content: flex-end;
        }
        .modal-actions button {
            padding: 10px 24px; border-radius: 10px; font-size: 14px; font-weight: 700;
            cursor: pointer; border: none; transition: all 0.3s;
        }
        .modal-actions .btn-batal-modal { background: #f0f0f0; color: #666; }
        .modal-actions .btn-submit { background: var(--p-pink); color: var(--white); }
        .modal-actions button:hover { transform: translateY(-2px); }

        @media (max-width: 768px) {
            .top-navbar { padding: 12px 20px; }
            .nav-menu-center { display: none; }
            .main-content { padding: 80px 20px 20px; }
            .page-header { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="top-navbar">
    <a href="../index.php" class="nav-logo">
        SpotLight.<span>StudioFoto</span>
    </a>
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
                <a href="../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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

<div class="main-content" style="padding-top: 100px;">
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-history" style="color:var(--p-pink);margin-right:10px;"></i>Riwayat Transaksi</h1>
            <p>Kelola dan pantau semua pesanan foto dan barang cetak Anda</p>
        </div>
    </div>

    <!-- STATS GRID SINKRON -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Pesanan</div>
        </div>
        <div class="stat-card menunggu">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo $stats['menunggu_dp']; ?></div>
            <div class="stat-label">Menunggu DP</div>
        </div>
        <div class="stat-card proses">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="stat-value"><?php echo $stats['dp_verified'] + $stats['selesai_foto']; ?></div>
            <div class="stat-label">Dalam Proses</div>
        </div>
        <div class="stat-card selesai">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?php echo $stats['lunas']; ?></div>
            <div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card batal">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?php echo $stats['dibatalkan']; ?></div>
            <div class="stat-label">Dibatalkan</div>
        </div>
    </div>

    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn <?php echo $tab=='semua'?'active':''; ?>" onclick="location.href='?tab=semua'">
                Semua <span class="tab-count"><?php echo $stats['total']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='menunggu'?'active':''; ?>" onclick="location.href='?tab=menunggu'">
                Menunggu <span class="tab-count"><?php echo $stats['menunggu_dp']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='proses'?'active':''; ?>" onclick="location.href='?tab=proses'">
                Proses <span class="tab-count"><?php echo $stats['dp_verified'] + $stats['selesai_foto']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='selesai'?'active':''; ?>" onclick="location.href='?tab=selesai'">
                Selesai <span class="tab-count"><?php echo $stats['lunas']; ?></span>
            </button>
            <button class="tab-btn <?php echo $tab=='batal'?'active':''; ?>" onclick="location.href='?tab=batal'">
                Dibatalkan <span class="tab-count"><?php echo $stats['dibatalkan']; ?></span>
            </button>
        </div>

        <div style="padding: 24px;">
            <?php if (empty($filtered_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Belum Ada Riwayat</h3>
                    <p>Anda belum memiliki transaksi pada kategori ini.</p>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <?php foreach ($filtered_list as $item): 
                        $is_batal = ($item['Status_Order'] == STATUS_ORDER_DIBATALKAN);
                        $id_order = $item['ID_Order'];
                        $barang_order = $barang_per_order[$id_order] ?? [];
                        $order_schedules = $jadwal_per_order[$id_order] ?? [];
                    ?>
                    <div class="order-card <?php echo $is_batal ? 'batal' : ''; ?>">
                        <div class="order-header">
                            <div class="order-id">
                                <strong>#ORDER-<?php echo str_pad($item['ID_Order'], 4, '0', STR_PAD_LEFT); ?></strong>
                            </div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <div class="order-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo formatTanggalIndo($item['Tanggal_Booking']); ?>
                                </div>
                                <?php echo getStatusBadge($item['Status_Order']); ?>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-grid">
                                <?php if (!empty($item['Nama_Paket'])): ?>
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
                                            <span><i class="fas fa-clock" style="margin-right:4px;"></i><?php echo $item['Durasi_Waktu'] ?? 0; ?> menit</span>
                                            <span><i class="fas fa-users" style="margin-right:4px;"></i><?php echo $item['Kapasitas_Orang'] ?? 0; ?> orang</span>
                                            <span><i class="fas fa-door-open" style="margin-right:4px;"></i><?php echo htmlspecialchars($item['Nama_Ruangan'] ?? '-'); ?></span>
                                            <?php if (!empty($item['Nama_Tema'])): ?>
                                            <span><i class="fas fa-palette" style="margin-right:4px;"></i><?php echo htmlspecialchars($item['Nama_Tema']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="paket-price"><?php echo formatRupiah($item['Total_Paket'] ?? 0); ?></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="paket-section">
                                    <div class="paket-img" style="background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--p-pink);font-size:2rem;">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <div class="paket-info">
                                        <h3>Order Barang Cetak</h3>
                                        <div class="paket-meta">
                                            <span><i class="fas fa-box" style="margin-right:4px;"></i>Barang Cetak</span>
                                        </div>
                                        <div class="paket-price"><?php echo formatRupiah($item['Total_Barang_Cetak'] ?? 0); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="detail-section">
                                    <!-- ITERASI DINAMIS MULTI-SLOT JADWAL SINKRON DATABASE -->
                                    <?php if (!empty($order_schedules)): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <div class="detail-label">Tanggal Sesi (<?php echo count($order_schedules); ?> Slot)</div>
                                            <div class="detail-value">
                                                <?php 
                                                $formatted_schedules = [];
                                                foreach ($order_schedules as $os) {
                                                    $formatted_schedules[] = formatTanggalIndo($os['Tanggal_Jadwal']) . ' | ' . $os['Jam_Mulai'] . ' - ' . $os['Jam_Selesai'] . ' WIB';
                                                }
                                                echo implode('<br>', $formatted_schedules);
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['Nama_Fotografer'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-user-tie"></i>
                                        <div>
                                            <div class="detail-label">Fotografer</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($item['Nama_Fotografer']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['Total_Barang_Cetak']) && $item['Total_Barang_Cetak'] > 0): ?>
                                    <div class="detail-item" style="background:var(--primary-light);">
                                        <i class="fas fa-shopping-bag" style="background:var(--p-pink);color:#fff;"></i>
                                        <div>
                                            <div class="detail-label">Total Barang Cetak</div>
                                            <div class="detail-value" style="color:var(--p-pink);font-weight:800;"><?php echo formatRupiah($item['Total_Barang_Cetak']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($barang_order)): ?>
                            <div class="barang-cetak-section">
                                <h4><i class="fas fa-box-open" style="color:var(--p-pink);"></i> Detail Barang Cetak</h4>
                                <div class="barang-cetak-grid">
                                    <?php foreach ($barang_order as $b): 
                                        $foto_barang = $b['Foto_Barang'] ?? 'default_barang.jpg';
                                        $foto_barang_src = file_exists("../../../assets/img/barang/" . $foto_barang) 
                                            ? "../../assets/img/barang/" . $foto_barang 
                                            : "../../assets/img/barang/default_barang.jpg";
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
                                    <span style="font-size:13px;color:#888;">Status Penjualan: </span>
                                    <?php echo getStatusPenjualanBadge($barang_order[0]['Status_Penjualan'] ?? null); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="pembayaran-section">
                                <h4><i class="fas fa-credit-card" style="color:var(--p-pink);"></i> Informasi Pembayaran</h4>
                                <div class="pembayaran-grid">
                                    <div class="pembayaran-box">
                                        <div class="pay-header">
                                            <span class="pay-label"><i class="fas fa-hand-holding-usd" style="margin-right:6px;"></i>DP (Uang Muka)</span>
                                            <?php 
                                            if (!empty($item['Status_DP'])) {
                                                echo getStatusPembayaranBadge($item['Status_DP']);
                                            } else {
                                                echo '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="pay-amount">
                                            <?php echo !empty($item['Jumlah_DP']) ? formatRupiah($item['Jumlah_DP']) : '-'; ?>
                                        </div>
                                        <?php if (!empty($item['Tgl_DP'])): ?>
                                        <div class="pay-detail">
                                            <i class="fas fa-calendar" style="color:#888;margin-right:4px;"></i>
                                            <?php echo formatTanggalIndo($item['Tgl_DP']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pembayaran-box pelunasan">
                                        <div class="pay-header">
                                            <span class="pay-label"><i class="fas fa-money-bill-wave" style="margin-right:6px;"></i>Pelunasan</span>
                                            <?php 
                                            if (!empty($item['Status_Pelunasan'])) {
                                                echo getStatusPembayaranBadge($item['Status_Pelunasan']);
                                            } else {
                                                echo '<span class="badge-pay badge-pay-wait">Belum Bayar</span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="pay-amount">
                                            <?php echo !empty($item['Jumlah_Pelunasan']) ? formatRupiah($item['Jumlah_Pelunasan']) : '-'; ?>
                                        </div>
                                        <?php if (!empty($item['Tgl_Pelunasan'])): ?>
                                        <div class="pay-detail">
                                            <i class="fas fa-calendar" style="color:#888;margin-right:4px;"></i>
                                            <?php echo formatTanggalIndo($item['Tgl_Pelunasan']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-aksi">
                            <?php echo getAksiButtons($item); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL RATING & REVIEW -->
<div class="modal-overlay" id="modalRating">
    <div class="modal-content">
        <h3><i class="fas fa-star" style="color:#F9A825;margin-right:8px;"></i>Beri Rating & Review</h3>
        <div class="star-rating" id="starContainer">
            <i class="fas fa-star" data-value="1"></i>
            <i class="fas fa-star" data-value="2"></i>
            <i class="fas fa-star" data-value="3"></i>
            <i class="fas fa-star" data-value="4"></i>
            <i class="fas fa-star" data-value="5"></i>
        </div>
        <textarea id="reviewText" placeholder="Ceritakan pengalaman Anda... (opsional)"></textarea>
        <div class="modal-actions">
            <button class="btn-batal-modal" onclick="tutupModal()">Batal</button>
            <button class="btn-submit" onclick="submitRating()">Kirim</button>
        </div>
    </div>
</div>

<!-- MODAL UPLOAD PEMBAYARAN (DP / PELUNASAN) -->
<div class="modal-overlay" id="modalPembayaran">
    <div class="modal-content" style="max-width: 500px;">
        <h3><i class="fas fa-credit-card" style="color:var(--p-pink);margin-right:8px;"></i>Upload Pembayaran</h3>
        <form id="formPembayaran" enctype="multipart/form-data">
            <input type="hidden" name="action_upload_pembayaran" value="1">
            <input type="hidden" name="id_order" id="payOrderId">
            <input type="hidden" name="tipe_pembayaran" id="payTipe">
            
            <div class="mb-3 text-start">
                <label class="form-label d-block fw-bold mb-1" style="font-size: 14px; text-align: left;">Jenis Pembayaran</label>
                <input type="text" id="payTipeLabel" class="form-control w-100 p-2" style="border: 2px solid #f0f0f0; border-radius: 12px; background: #fafafa; font-weight:700;" readonly>
            </div>
            
            <div class="mb-3 text-start">
                <label class="form-label d-block fw-bold mb-1" style="font-size: 14px; text-align: left;">Jumlah yang Harus Dibayar</label>
                <input type="text" id="payJumlahLabel" class="form-control w-100 p-2" style="border: 2px solid #f0f0f0; border-radius: 12px; background: #fafafa; font-weight:700; color:var(--p-pink);" readonly>
                <input type="hidden" name="jumlah_bayar" id="payJumlah">
            </div>
            
            <div class="mb-3 text-start">
                <label class="form-label d-block fw-bold mb-1" style="font-size: 14px; text-align: left;">Metode Pembayaran</label>
                <select name="metode_pembayaran" id="payMetode" class="form-select w-100" required>
                    <option value="">-- Pilih Metode Pembayaran --</option>
                    <option value="Transfer Bank BCA">Transfer Bank BCA (123-456-7890 a/n SpotLight Studio)</option>
                    <option value="Transfer Bank BNI">Transfer Bank BNI (098-765-4321 a/n SpotLight Studio)</option>
                    <option value="Transfer Bank Mandiri">Transfer Bank Mandiri (112-233-4455 a/n SpotLight Studio)</option>
                    <option value="QRIS">QRIS SpotLight Studio (E-Wallet)</option>
                </select>
            </div>
            
            <div class="mb-4 text-start">
                <label class="form-label d-block fw-bold mb-1" style="font-size: 14px; text-align: left;">Unggah Bukti Transfer</label>
                <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('payBukti').click()">
                    <div class="file-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div class="file-upload-text" id="fileText">Klik untuk upload bukti transfer</div>
                    <div class="file-upload-note">Format: JPG, PNG, PDF (Max 5MB)</div>
                </div>
                <input type="file" name="bukti_transfer" id="payBukti" style="display: none;" accept=".jpg,.jpeg,.png,.pdf" required onchange="handleFileSelect(this)">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-batal-modal" onclick="tutupModalPembayaran()">Batal</button>
                <button type="submit" class="btn-submit" style="background:var(--p-pink);">Kirim Bukti</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentOrderId = null;
let selectedRating = 0;

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
            window.location.href = '../../index.php';
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
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#888',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../../logout.php';
        }
    });
}

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
            s.classList.add('active');
            s.classList.remove('far');
            s.classList.add('fas');
        } else {
            s.classList.remove('active');
            s.classList.remove('fas');
            s.classList.add('far');
        }
    });
}

function bukaRating(id_order) {
    currentOrderId = id_order;
    selectedRating = 0;
    updateStars(0);
    document.getElementById('reviewText').value = '';
    document.getElementById('modalRating').classList.add('active');
}

function tutupModal() {
    document.getElementById('modalRating').classList.remove('active');
    currentOrderId = null;
    selectedRating = 0;
}

function submitRating() {
    if (selectedRating === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Pilih Rating',
            text: 'Silakan pilih minimal 1 bintang!',
            confirmButtonColor: '#d83f67'
        });
        return;
    }

    const review = document.getElementById('reviewText').value;

    fetch('action_rating.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_order=' + currentOrderId + '&rating=' + selectedRating + '&review=' + encodeURIComponent(review)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Terima kasih atas rating dan review Anda!',
                confirmButtonColor: '#d83f67'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#d83f67'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi kesalahan sistem',
            confirmButtonColor: '#d83f67'
        });
    });
}

// =====================================================
// FUNGSI MODAL PEMBAYARAN (AJAX) - SINKRON DESAIN PREMIUM
// =====================================================
function bukaModalPembayaran(id_order, tipe, jumlah) {
    document.getElementById('payOrderId').value = id_order;
    document.getElementById('payTipe').value = tipe;
    
    document.getElementById('payTipeLabel').value = tipe === 'DP' ? 'Uang Muka (DP 65%)' : 'Pelunasan Sesi Foto';
    
    const formatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    });
    
    document.getElementById('payJumlahLabel').value = formatter.format(jumlah);
    document.getElementById('payJumlah').value = jumlah;
    
    document.getElementById('payMetode').value = '';
    document.getElementById('payBukti').value = '';
    
    const area = document.getElementById('fileUploadArea');
    const text = document.getElementById('fileText');
    area.classList.remove('has-file');
    text.innerHTML = 'Klik untuk upload bukti transfer';
    
    const oldPreview = document.getElementById('previewBukti');
    if (oldPreview) oldPreview.remove();
    
    document.getElementById('modalPembayaran').classList.add('active');
}

function tutupModalPembayaran() {
    document.getElementById('modalPembayaran').classList.remove('active');
}

function handleFileSelect(input) {
    const area = document.getElementById('fileUploadArea');
    const text = document.getElementById('fileText');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File terlalu besar',
                text: 'Ukuran file maksimal 5MB.',
                confirmButtonColor: '#d83f67'
            });
            input.value = '';
            area.classList.remove('has-file');
            text.innerHTML = 'Klik untuk upload bukti transfer';
            
            const oldPreview = document.getElementById('previewBukti');
            if (oldPreview) oldPreview.remove();
            return;
        }

        area.classList.add('has-file');
        text.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + file.name;

        // Preview gambar
        const reader = new FileReader();
        reader.onload = function(e) {
            const oldPreview = document.getElementById('previewBukti');
            if (oldPreview) oldPreview.remove();

            const previewDiv = document.createElement('div');
            previewDiv.id = 'previewBukti';
            previewDiv.style.cssText = 'margin-top:16px;text-align:center;';
            previewDiv.innerHTML = '<div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);margin-bottom:8px;"><i class="fas fa-eye me-1"></i> Preview Bukti Transfer</div><img src="' + e.target.result + '" style="max-width:100%;max-height:180px;border-radius:12px;border:2px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,0.08);" alt="Preview">';
            area.parentNode.appendChild(previewDiv);
        };
        reader.readAsDataURL(file);
    }
}

document.getElementById('formPembayaran').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Kirim Bukti Pembayaran?',
        text: 'Pastikan bukti transfer dan metode pembayaran yang Anda pilih sudah benar.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kirim',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Mengirim Bukti...',
                html: 'Mohon tunggu sebentar.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
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

function batalkanOrder(id_order) {
    Swal.fire({
        title: 'Batalkan Pesanan?',
        text: 'Apakah Anda yakin ingin membatalkan pesanan ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D32F2F',
        cancelButtonColor: '#888',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tidak'
    }).then((result) => {
        if (result.isConfirmed) {
            // Perbaikan pemanggilan aksi batal diselaraskan ke action_batal.php (atau pembatalan terpadu)
            fetch('../Pembayaran/proses_batal_order.php?id_order=' + id_order + '&redirect=riwayat')
            .then(r => {
                // Diperbaiki untuk menangani redirect langsung yang aman
                if (r.redirected) {
                    window.location.href = r.url;
                } else {
                    return r.text();
                }
            })
            .then(data => {
                // Skenario penanganan sukses
                Swal.fire({
                    icon: 'success',
                    title: 'Dibatalkan',
                    text: 'Pesanan berhasil dibatalkan',
                    confirmButtonColor: '#d83f67'
                }).then(() => location.reload());
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal membatalkan pesanan',
                    confirmButtonColor: '#d83f67'
                });
            });
        }
    });
}

function lihatDetail(id_order) {
    Swal.fire({
        title: 'Detail Sesi Pesanan #' + String(id_order).padStart(4, '0'),
        html: 'Sesi foto Anda sudah terverifikasi dan dijadwalkan.<br>Silakan hubungi admin via WhatsApp untuk koordinasi properti/tambahan: <br><a href="https://wa.me/6287871438459" target="_blank" class="btn btn-success mt-2" style="background:#25D366; border:none; color:white; padding: 10px 20px; border-radius: 12px; font-weight:700;"><i class="fab fa-whatsapp"></i> Hubungi Admin</a>',
        icon: 'info',
        confirmButtonColor: '#d83f67'
    });
}

document.getElementById('modalRating').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});

document.getElementById('modalPembayaran').addEventListener('click', function(e) {
    if (e.target === this) tutupModalPembayaran();
});
</script>

</body>
</html>