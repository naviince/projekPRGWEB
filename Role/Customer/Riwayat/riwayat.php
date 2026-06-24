<?php
session_start();
include '../../../koneksi.php';

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

// Ambil data pelanggan
$q_pelanggan = sqlsrv_query($conn, "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?", [$id_pelanggan]);
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
if ($d_pelanggan) { $d_pelanggan = array_change_key_case($d_pelanggan, CASE_LOWER); }
$nama_pelanggan = $d_pelanggan['nama_pelanggan'] ?? 'Pelanggan';
$foto_pelanggan = $d_pelanggan['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_pelanggan_src = ($foto_pelanggan != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_pelanggan)) 
    ? "../../assets/img/pelanggan/" . $foto_pelanggan 
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
// QUERY RIWAYAT ORDER LENGKAP
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

    j.ID_Jadwal,
    j.Tanggal_Jadwal,
    j.Jam_Mulai,
    j.Jam_Selesai,
    j.Keterangan as Jadwal_Keterangan,

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
LEFT JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Sesi_Foto sf ON o.ID_Order = sf.ID_Order
LEFT JOIN Karyawan k ON sf.ID_Karyawan = k.ID_Karyawan
LEFT JOIN Pembayaran dp ON o.ID_Order = dp.ID_Order AND dp.Tipe_Pembayaran = 'DP'
LEFT JOIN Pembayaran pl ON o.ID_Order = pl.ID_Order AND pl.Tipe_Pembayaran = 'Pelunasan'
WHERE o.ID_Pelanggan = ? AND o.Is_Deleted = 0
ORDER BY o.Tanggal_Booking DESC
";

$params = [$id_pelanggan];
$q_riwayat = sqlsrv_query($conn, $sql, $params);

$riwayat_list = [];
if ($q_riwayat !== false) {
    while ($row = sqlsrv_fetch_array($q_riwayat, SQLSRV_FETCH_ASSOC)) {
        $date_fields = ['Tanggal_Booking', 'Tanggal_Jadwal', 'Tanggal_Upload_Hasil', 'Tgl_DP', 'Tgl_Pelunasan'];
        foreach ($date_fields as $field) {
            if (isset($row[$field]) && is_object($row[$field]) && method_exists($row[$field], 'format')) {
                $row[$field] = $row[$field]->format('Y-m-d H:i:s');
            }
        }
        $time_fields = ['Jam_Mulai', 'Jam_Selesai'];
        foreach ($time_fields as $field) {
            if (isset($row[$field]) && is_object($row[$field]) && method_exists($row[$field], 'format')) {
                $row[$field] = $row[$field]->format('H:i');
            } elseif (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = substr($row[$field], 0, 5);
            }
        }
        $riwayat_list[] = $row;
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
            b.Foto_Barang,
            b.Kategori_Barang
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
    $buttons = '';

    switch ($status) {
        case STATUS_ORDER_MENUNGGU_DP:
            $buttons .= '<a href="../Booking/Pembayaran/upload_dp.php?id_order=' . $id_order . '" class="btn-aksi btn-upload"><i class="fas fa-upload"></i> Upload Bukti DP</a>';
            $buttons .= '<a href="javascript:void(0)" onclick="batalkanOrder(' . $id_order . ')" class="btn-aksi btn-batal"><i class="fas fa-times"></i> Batalkan</a>';
            break;

        case STATUS_ORDER_DP_TERVERIFIKASI:
            $buttons .= '<a href="javascript:void(0)" onclick="lihatDetail(' . $id_order . ')" class="btn-aksi btn-detail"><i class="fas fa-info-circle"></i> Lihat Detail</a>';
            if (!empty($item['Nama_Fotografer'])) {
                $buttons .= '<div class="fotografer-info"><i class="fas fa-user-tie"></i> Fotografer: <strong>' . htmlspecialchars($item['Nama_Fotografer']) . '</strong></div>';
            }
            break;

        case STATUS_ORDER_SELESAI_FOTO:
            $buttons .= '<a href="../Booking/Pembayaran/upload_pelunasan.php?id_order=' . $id_order . '" class="btn-aksi btn-upload"><i class="fas fa-upload"></i> Upload Pelunasan</a>';
            if (!empty($item['File_Hasil'])) {
                $buttons .= '<a href="' . htmlspecialchars($item['File_Hasil']) . '" target="_blank" class="btn-aksi btn-preview"><i class="fas fa-images"></i> Lihat Hasil</a>';
            }
            break;

        case STATUS_ORDER_LUNAS:
            if (!empty($item['File_Hasil'])) {
                $buttons .= '<a href="' . htmlspecialchars($item['File_Hasil']) . '" download class="btn-aksi btn-download"><i class="fas fa-download"></i> Download Hasil</a>';
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
            --primary: #D53D66;
            --primary-dark: #B82E52;
            --primary-light: #FFF0F3;
            --secondary: #1e1e24;
            --accent: #FF6694;
            --bg: #f8fafc;
            --white: #FFFFFF;
            --shadow: 0 8px 32px rgba(213, 61, 102, 0.15);
            --shadow-card: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 20px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); min-height: 100vh; }

        .top-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 40px; background: var(--white);
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .nav-logo {
            font-size: 1.8rem; font-weight: 900; color: var(--primary); text-decoration: none;
            letter-spacing: -1.5px;
        }
        .nav-logo span { color: var(--secondary); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center {
            display: flex; gap: 32px; align-items: center;
        }
        .nav-link-item {
            text-decoration: none; color: #4a5568; font-size: 0.9rem; font-weight: 700;
            padding: 8px 0; position: relative; transition: all 0.3s;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--primary);
        }
        .nav-link-item.active::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
            background: var(--primary); border-radius: 3px;
        }
        .nav-right {
            display: flex; align-items: center; gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white); padding: 10px 24px;
            border-radius: 12px; text-decoration: none; font-size: 0.85rem; font-weight: 800;
            display: flex; align-items: center; gap: 6px; transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.25);
        }
        .nav-btn-booking:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(213, 61, 102, 0.35); color: #fff; }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
            cursor: pointer; border: 2px solid var(--primary-light);
        }
        .nav-dropdown {
            display: none; position: absolute; top: 55px; right: 0;
            background: var(--white); border-radius: 16px; box-shadow: var(--shadow);
            min-width: 220px; padding: 12px; border: 1px solid #f1f5f9;
        }
        .nav-dropdown.show { display: block; animation: fadeIn 0.2s ease; }
        .dropdown-header {
            padding: 8px 16px; font-size: 0.95rem; font-weight: 800; color: var(--secondary);
        }
        .dropdown-divider { height: 1px; background: #f1f5f9; margin: 8px 0; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 16px; color: #4a5568; text-decoration: none; font-size: 0.9rem;
            transition: all 0.3s; cursor: pointer; border: none; background: none; width: 100%;
            font-weight: 600; border-radius: 12px;
        }
        .dropdown-item:hover { background: var(--primary-light); color: var(--primary); }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }

        .main-content { padding: 100px 40px 40px; max-width: 1400px; margin: 0 auto; }
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }
        .page-title h1 { color: var(--secondary); font-size: 28px; font-weight: 800; }
        .page-title p { color: #888; font-size: 14px; margin-top: 4px; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow-card); transition: transform 0.3s;
            border-left: 4px solid var(--primary); position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card::before {
            content: ''; position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--primary-light); opacity: 0.5;
        }
        .stat-card .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--secondary); }
        .stat-card .stat-label { font-size: 13px; color: #888; margin-top: 2px; }
        .stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary); }
        .stat-card.total { border-left-color: var(--primary); }
        .stat-card.menunggu .stat-icon { background: #FFF8E1; color: #F9A825; }
        .stat-card.menunggu { border-left-color: #F9A825; }
        .stat-card.proses .stat-icon { background: #E3F2FD; color: #1976D2; }
        .stat-card.proses { border-left-color: #1976D2; }
        .stat-card.selesai .stat-icon { background: #E8F5E9; color: #388E3C; }
        .stat-card.selesai { border-left-color: #388E3C; }
        .stat-card.batal .stat-icon { background: #FFEBEE; color: #D32F2F; }
        .stat-card.batal { border-left-color: #D32F2F; }

        .tabs-container {
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow-card); margin-bottom: 25px; overflow: hidden;
        }
        .tabs-header {
            display: flex; border-bottom: 2px solid #f0f0f0; padding: 0 10px; overflow-x: auto;
        }
        .tab-btn {
            padding: 16px 24px; border: none; background: none;
            color: #888; font-size: 14px; font-weight: 600; cursor: pointer;
            position: relative; transition: all 0.3s; white-space: nowrap;
        }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active { color: var(--primary); }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 3px;
            background: var(--primary); border-radius: 3px 3px 0 0;
        }
        .tab-btn .tab-count {
            display: inline-block; background: var(--primary-light); color: var(--primary);
            padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px;
        }

        .orders-container { display: flex; flex-direction: column; gap: 20px; }
        .order-card {
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow-card); overflow: hidden; transition: all 0.3s;
            border: 1px solid transparent;
        }
        .order-card:hover { box-shadow: var(--shadow); border-color: var(--primary-light); }
        .order-card.batal { opacity: 0.7; }
        .order-card.batal .order-header { background: #FFEBEE; }

        .order-header {
            padding: 16px 24px; background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-id { font-size: 14px; color: #666; }
        .order-id strong { color: var(--primary); font-size: 16px; }
        .order-date { font-size: 13px; color: #888; }
        .order-date i { margin-right: 5px; color: var(--primary); }

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
        .paket-info h3 { color: var(--secondary); font-size: 18px; font-weight: 600; margin-bottom: 6px; }
        .paket-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .paket-meta span {
            background: var(--primary-light); color: var(--primary);
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;
        }
        .paket-price { font-size: 20px; font-weight: 700; color: var(--primary); }

        .detail-section { display: flex; flex-direction: column; gap: 12px; }
        .detail-item {
            display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px;
            background: #FAFAFA; border-radius: 10px;
        }
        .detail-item i {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--primary-light); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0; margin-top: 2px;
        }
        .detail-item .detail-label { font-size: 12px; color: #888; margin-bottom: 2px; }
        .detail-item .detail-value { font-size: 14px; color: var(--secondary); font-weight: 500; }

        .barang-cetak-section {
            margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ddd;
        }
        .barang-cetak-section h4 {
            font-size: 14px; color: var(--secondary); margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .barang-cetak-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;
        }
        .barang-cetak-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; background: #FAFAFA; border-radius: 12px;
            border-left: 3px solid var(--primary);
        }
        .barang-cetak-item img {
            width: 50px; height: 50px; border-radius: 8px; object-fit: cover;
        }
        .barang-cetak-info { flex: 1; }
        .barang-cetak-nama { font-size: 13px; font-weight: 700; color: var(--secondary); }
        .barang-cetak-detail { font-size: 12px; color: #888; margin-top: 2px; }
        .barang-cetak-subtotal { font-size: 14px; font-weight: 800; color: var(--primary); }

        .pembayaran-section {
            margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ddd;
        }
        .pembayaran-section h4 {
            font-size: 14px; color: var(--secondary); margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .pembayaran-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 15px;
        }
        @media (max-width: 768px) { .pembayaran-grid { grid-template-columns: 1fr; } }
        .pembayaran-box {
            background: #FAFAFA; border-radius: 12px; padding: 16px;
            border-left: 3px solid var(--primary);
        }
        .pembayaran-box.pelunasan { border-left-color: #388E3C; }
        .pembayaran-box .pay-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .pembayaran-box .pay-label { font-size: 13px; font-weight: 600; color: var(--secondary); }
        .pembayaran-box .pay-amount { font-size: 16px; font-weight: 700; color: var(--primary); }
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
            font-weight: 600; text-decoration: none; transition: all 0.3s; border: none; cursor: pointer;
        }
        .btn-aksi:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-upload { background: var(--primary); color: var(--white); }
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

        .empty-state {
            text-align: center; padding: 60px 20px;
        }
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 20px; }
        .empty-state h3 { color: var(--secondary); font-size: 20px; margin-bottom: 8px; }
        .empty-state p { color: #888; font-size: 14px; }
        .empty-state .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 20px; padding: 12px 28px; background: var(--primary);
            color: var(--white); border-radius: 12px; text-decoration: none;
            font-weight: 600; font-size: 14px; transition: all 0.3s;
        }
        .empty-state .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }

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
        .modal-content h3 { color: var(--secondary); margin-bottom: 20px; text-align: center; }
        .star-rating {
            display: flex; justify-content: center; gap: 10px; margin-bottom: 20px;
        }
        .star-rating i {
            font-size: 36px; color: #DDD; cursor: pointer; transition: all 0.2s;
        }
        .star-rating i:hover, .star-rating i.active { color: #F9A825; transform: scale(1.1); }
        .modal-content textarea {
            width: 100%; padding: 14px; border: 2px solid #f0f0f0; border-radius: 12px;
            font-size: 14px; resize: vertical; min-height: 100px; margin-bottom: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .modal-content textarea:focus { outline: none; border-color: var(--primary); }
        .modal-actions {
            display: flex; gap: 10px; justify-content: flex-end;
        }
        .modal-actions button {
            padding: 10px 24px; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: none; transition: all 0.3s;
        }
        .modal-actions .btn-batal-modal { background: #f0f0f0; color: #666; }
        .modal-actions .btn-submit { background: var(--primary); color: var(--white); }
        .modal-actions button:hover { transform: translateY(-2px); }

        @media (max-width: 768px) {
            .top-navbar { padding: 12px 20px; }
            .nav-menu-center { display: none; }
            .main-content { padding: 80px 20px 20px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .tabs-header { overflow-x: auto; }
            .order-grid { grid-template-columns: 1fr; }
            .pembayaran-grid { grid-template-columns: 1fr; }
            .barang-cetak-grid { grid-template-columns: 1fr; }
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="../index.php" class="nav-logo">
        SpotLight.<span>StudioFoto</span>
    </a>
    <div class="nav-menu-center">
        <a href="../index.php" class="nav-link-item">Dashboard</a>
        <a href="../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
        <a href="index.php" class="nav-link-item active">Riwayat</a>
        <a href="../Barang/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
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

<div class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-history" style="color:var(--primary);margin-right:10px;"></i>Riwayat Transaksi</h1>
            <p>Kelola dan pantau semua pesanan foto dan barang cetak Anda</p>
        </div>
    </div>

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
                    <p>Anda belum memiliki transaksi. Yuk, pesan paket foto atau barang cetak sekarang!</p>
                    <a href="../Layanan/Paket/pilih_paket.php" class="btn-primary">
                        <i class="fas fa-camera"></i> Pesan Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <?php foreach ($filtered_list as $item): 
                        $is_batal = ($item['Status_Order'] == STATUS_ORDER_DIBATALKAN);
                        $id_order = $item['ID_Order'];
                        $barang_order = $barang_per_order[$id_order] ?? [];
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
                                    $foto_src = file_exists("../../assets/img/paket/" . $foto_paket) 
                                        ? "../../assets/img/paket/" . $foto_paket 
                                        : "../../assets/img/paket/default_paket.jpg";
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
                                    <div class="paket-img" style="background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:2rem;">
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
                                    <?php if (!empty($item['Tanggal_Jadwal'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <div class="detail-label">Tanggal Sesi</div>
                                            <div class="detail-value"><?php echo formatTanggalIndo($item['Tanggal_Jadwal']); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <div class="detail-label">Waktu</div>
                                            <div class="detail-value"><?php echo $item['Jam_Mulai'] ?? '-'; ?> - <?php echo $item['Jam_Selesai'] ?? '-'; ?> WIB</div>
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
                                        <i class="fas fa-shopping-bag" style="background:var(--primary);color:#fff;"></i>
                                        <div>
                                            <div class="detail-label">Total Barang Cetak</div>
                                            <div class="detail-value" style="color:var(--primary);font-weight:800;"><?php echo formatRupiah($item['Total_Barang_Cetak']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($barang_order)): ?>
                            <div class="barang-cetak-section">
                                <h4><i class="fas fa-box-open" style="color:var(--primary);"></i> Detail Barang Cetak</h4>
                                <div class="barang-cetak-grid">
                                    <?php foreach ($barang_order as $b): 
                                        $foto_barang = $b['Foto_Barang'] ?? 'default_barang.jpg';
                                        $foto_barang_src = file_exists("../../assets/img/barang/" . $foto_barang) 
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
                                <h4><i class="fas fa-credit-card" style="color:var(--primary);"></i> Informasi Pembayaran</h4>
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

<script>
let currentOrderId = null;
let selectedRating = 0;

function toggleDropdown() {
    document.getElementById('navDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.nav-avatar-wrapper');
    if (!wrapper.contains(e.target)) {
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
        confirmButtonColor: '#D53D66',
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
        confirmButtonColor: '#D53D66',
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
            confirmButtonColor: '#D53D66'
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
                confirmButtonColor: '#D53D66'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#D53D66'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi kesalahan sistem',
            confirmButtonColor: '#D53D66'
        });
    });
}

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
            fetch('action_batal.php?id_order=' + id_order)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Dibatalkan',
                        text: 'Pesanan berhasil dibatalkan',
                        confirmButtonColor: '#D53D66'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message || 'Tidak dapat membatalkan',
                        confirmButtonColor: '#D53D66'
                    });
                }
            });
        }
    });
}

function lihatDetail(id_order) {
    Swal.fire({
        title: 'Detail Pesanan #' + String(id_order).padStart(4, '0'),
        html: 'Silakan hubungi admin untuk informasi lebih detail.<br>WhatsApp: <a href="https://wa.me/62xxx" target="_blank">Klik di sini</a>',
        icon: 'info',
        confirmButtonColor: '#D53D66'
    });
}

document.getElementById('modalRating').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>

</body>
</html>