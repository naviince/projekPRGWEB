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
define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);
define('STATUS_DATA_AKTIF', 1);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);

// =====================================================
// DEFINISI FALLBACK AVATAR SVG
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Fungsi Pembantu Format Tanggal Indonesia ---
if (!function_exists('fmtTgl')) {
    function fmtTgl($date_str) {
        if (empty($date_str)) return '-';
        $timestamp = strtotime($date_str);
        if (!$timestamp) return $date_str;

        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $d = date('j', $timestamp);
        $m = (int)date('n', $timestamp);
        $y = date('Y', $timestamp);

        return "$d " . ($months[$m] ?? '') . " $y";
    }
}

// =====================================================
// AMBIL ID ORDER DARI URL
// =====================================================
if (!isset($_GET['id_order']) || empty($_GET['id_order'])) {
    header("Location: ../../index.php?error=order_tidak_ditemukan");
    exit();
}

$id_order = (int)$_GET['id_order'];

// =====================================================
// AMBIL DATA ORDER + PELANGGAN + PAKET (SINKRON MULTI-SLOT)
// =====================================================
$q_order = sqlsrv_query($conn, 
    "SELECT o.ID_Order, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, o.Status_Order, o.Tanggal_Booking,
            o.ID_Paket, o.ID_Ruangan, o.ID_Tema,
            p.Nama_Pelanggan, p.No_Hp, p.Email_Pelanggan,
            pk.Nama_Paket, pk.Harga_Paket, pk.Durasi_Waktu, pk.Kapasitas_Orang, pk.Foto_Paket,
            r.Nama_Ruangan, r.Deskripsi as Deskripsi_Ruangan, r.Foto_Ruangan,
            t.Nama_Tema, t.Kategori_Tema, t.Deskripsi as Deskripsi_Tema, t.Foto_Tema
     FROM [Order] o
     INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
     INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
     INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
     INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
     WHERE o.ID_Order = ? AND o.ID_Pelanggan = ? AND o.Status = 1",
    array($id_order, $id_customer)
);

if ($q_order === false) {
    die("Error query order: " . print_r(sqlsrv_errors(), true));
}

$d_order = sqlsrv_fetch_array($q_order, SQLSRV_FETCH_ASSOC);

if (!$d_order) {
    header("Location: ../../index.php?error=order_tidak_valid");
    exit();
}

$id_paket = (int)$d_order['ID_Paket'];
$id_ruangan = (int)$d_order['ID_Ruangan'];
$id_tema = (int)$d_order['ID_Tema'];

// Validasi: hanya order dengan Status_Order = 0 (Menunggu DP) yang boleh mengakses halaman pembayaran awal ini
if ((int)$d_order['Status_Order'] !== STATUS_ORDER_MENUNGGU_DP) {
    header("Location: ../../index.php?error=order_sudah_diproses");
    exit();
}

// =====================================================
// AMBIL DATA JADWAL DARI TABEL JUNCTION Order_Jadwal (SINKRON MULTI-SLOT JADWAL)
// =====================================================
$q_jadwal_order = sqlsrv_query($conn,
    "SELECT j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
     FROM Order_Jadwal oj
     INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
     WHERE oj.ID_Order = ? AND j.Status = 1 AND j.Is_Deleted = 0",
    array($id_order)
);
if ($q_jadwal_order === false) {
    die("Error query Jadwal Order: " . print_r(sqlsrv_errors(), true));
}

$jadwal_order_list = [];
$id_jadwal_arr = [];
while ($row_j = sqlsrv_fetch_array($q_jadwal_order, SQLSRV_FETCH_ASSOC)) {
    $jadwal_order_list[] = $row_j;
    $id_jadwal_arr[] = (int)$row_j['ID_Jadwal'];
}
$jumlah_slot = count($jadwal_order_list);

// Menghasilkan format tampilan string untuk multi-slot jadwal
$jadwal_text_arr = [];
foreach ($jadwal_order_list as $row_j) {
    $tgl_obj = $row_j['Tanggal_Jadwal'];
    if (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) {
        $tgl_str_temp = $tgl_obj->format('d M Y');
    } else {
        $tgl_str_temp = date('d M Y', strtotime($tgl_obj));
    }

    $jam_mulai_obj = $row_j['Jam_Mulai'];
    $jam_mulai_str_temp = is_object($jam_mulai_obj) && method_exists($jam_mulai_obj, 'format') ? $jam_mulai_obj->format('H:i') : substr($jam_mulai_obj, 0, 5);

    $jam_selesai_obj = $row_j['Jam_Selesai'];
    $jam_selesai_str_temp = is_object($jam_selesai_obj) && method_exists($jam_selesai_obj, 'format') ? $jam_selesai_obj->format('H:i') : substr($jam_selesai_obj, 0, 5);

    $jadwal_text_arr[] = $tgl_str_temp . ' | ' . $jam_mulai_str_temp . ' - ' . $jam_selesai_str_temp . ' WIB';
}
$jadwal_display_str = implode('<br>', $jadwal_text_arr);

// =====================================================
// AMBIL LIST BARANG CETAK YANG DIPESAN DARI DATABASE (SINKRON DETAIL)
// =====================================================
$q_cetak_items = sqlsrv_query($conn, 
    "SELECT bc.Nama_Barang, dp.Jumlah, dp.Harga_Satuan, dp.Subtotal
     FROM Detail_Penjualan_Barang_Cetak dp
     INNER JOIN Penjualan p ON dp.ID_Penjualan = p.ID_Penjualan
     INNER JOIN Barang_Cetak bc ON dp.ID_Barang = bc.ID_Barang
     WHERE p.ID_Order = ? AND p.Status = 1",
    array($id_order)
);

$extra_cetak_html = '';
$total_cetak_harga = 0;
if ($q_cetak_items !== false) {
    while ($item = sqlsrv_fetch_array($q_cetak_items, SQLSRV_FETCH_ASSOC)) {
        $qty = (int)$item['Jumlah'];
        $subtotal = (float)$item['Subtotal'];
        $total_cetak_harga += $subtotal;
        $extra_cetak_html .= '
            <div class="extra-goods-item">
                <span>' . htmlspecialchars($item['Nama_Barang']) . ' (x' . $qty . ')</span>
                <span>Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
            </div>
        ';
    }
}

// =====================================================
// CEK APAKAH SUDAH ADA PEMBAYARAN DP
// =====================================================
$q_cek_dp = sqlsrv_query($conn, 
    "SELECT ID_Pembayaran, Status_Pembayaran FROM Pembayaran 
     WHERE ID_Order = ? AND Tipe_Pembayaran = 'DP' AND Status = 1",
    array($id_order)
);
$d_cek_dp = sqlsrv_fetch_array($q_cek_dp, SQLSRV_FETCH_ASSOC);

$sudah_upload = false;
$status_pembayaran = null;
if ($d_cek_dp) {
    $sudah_upload = true;
    $status_pembayaran = (int)$d_cek_dp['Status_Pembayaran'];
}

// =====================================================
// RECALCULATE BELANJAAN BARANG CETAK & HITUNG DISKON SINKRON
// =====================================================
$harga_paket_total = (float)$d_order['Total_Paket'];
$total_cetak_harga = (float)$d_order['Total_Barang_Cetak'];

// Potongan harga spesial 5% khusus produk cetak
$diskon_cetak = 0;
if ($total_cetak_harga > 0) {
    $diskon_cetak = $total_cetak_harga * 0.05; // 5%
}
$total_cetak_setelah_diskon = $total_cetak_harga - $diskon_cetak;

// Hitung rekapitulasi pembayaran total akhir (SINKRON DENGAN KONFIRMASI)
$total_harga_order = $harga_paket_total + $total_cetak_setelah_diskon;

// =====================================================
// DETEKSI LOGIKA PILIHAN TIPE PEMBAYARAN (DP / LUNAS) SINKRON
// =====================================================
$tipe_bayar = 'DP'; // Default fallback
if (isset($_SESSION['order_tipe_bayar']) && $_SESSION['order_id'] == $id_order) {
    $tipe_bayar = $_SESSION['order_tipe_bayar'];
} elseif (isset($_GET['tipe_bayar'])) {
    $tipe_bayar = trim($_GET['tipe_bayar']) === 'Lunas' ? 'Lunas' : 'DP';
}

// Perhitungan Keuangan Dinamis sesuai Opsi Pembayaran Pelanggan
if ($tipe_bayar === 'Lunas') {
    $nominal_bayar_sekarang = $total_harga_order;
    $keterangan_pembayaran = "Lunas (100%)";
    $keterangan_bawah = "Bebas Hambatan! Anda telah melunasi seluruh pembayaran di awal.";
    $sisa_amount = 0;
} else {
    $nominal_bayar_sekarang = $total_harga_order * 0.65;
    $keterangan_pembayaran = "DP (65%)";
    $sisa_amount = $total_harga_order - $nominal_bayar_sekarang;
    $keterangan_bawah = "Sisa pembayaran sebesar Rp " . number_format($sisa_amount, 0, ',', '.') . " dibayar setelah sesi pemotretan selesai";
}

$harga_paket_format = number_format($harga_paket_total, 0, ',', '.');
$total_cetak_format = number_format($total_cetak_harga, 0, ',', '.');
$diskon_cetak_format = number_format($diskon_cetak, 0, ',', '.');
$total_harga_format = number_format($total_harga_order, 0, ',', '.');
$nominal_bayar_sekarang_format = number_format($nominal_bayar_sekarang, 0, ',', '.');
$sisa_format = number_format($sisa_amount, 0, ',', '.');

// =====================================================
// REKENING HARDCODE
// =====================================================
$rekening_list = [
    [
        'nama_bank' => 'Bank BCA',
        'no_rekening' => '123-456-7890',
        'atas_nama' => 'SpotLight Studio Foto'
    ],
    [
        'nama_bank' => 'Bank BNI',
        'no_rekening' => '098-765-4321',
        'atas_nama' => 'SpotLight Studio Foto'
    ],
    [
        'nama_bank' => 'Bank Mandiri',
        'no_rekening' => '112-233-4455',
        'atas_nama' => 'SpotLight Studio Foto'
    ]
];

// =====================================================
// AMBIL PROFIL CUSTOMER LENGKAP SINKRON
// =====================================================
$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil, Username_Pelanggan, Email_Pelanggan, No_Hp, Alamat, Jenis_Kelamin, Tanggal_Lahir FROM Pelanggan WHERE ID_Pelanggan = ? AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$username_customer = $d_profile['Username_Pelanggan'] ?? '';
$email_customer = $d_profile['Email_Pelanggan'] ?? 'customer@spotlight.com';
$no_hp_customer = $d_profile['No_Hp'] ?? '';
$alamat_customer = $d_profile['Alamat'] ?? '';
$jk_customer = $d_profile['Jenis_Kelamin'] ?? 'Laki-laki';
$tgl_lahir_raw = $d_profile['Tanggal_Lahir'] ?? '';
$tgl_lahir_str = '';
if (is_object($tgl_lahir_raw) && method_exists($tgl_lahir_raw, 'format')) {
    $tgl_lahir_str = $tgl_lahir_raw->format('Y-m-d');
} else if (is_string($tgl_lahir_raw)) {
    $tgl_lahir_str = substr($tgl_lahir_raw, 0, 10);
}

$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// HITUNG COUNTDOWN 24 JAM
// =====================================================
$tgl_booking = $d_order['Tanggal_Booking'];
if (is_object($tgl_booking) && method_exists($tgl_booking, 'format')) {
    $booking_time = strtotime($tgl_booking->format('Y-m-d H:i:s'));
} else {
    $booking_time = strtotime($tgl_booking);
}
$deadline = $booking_time + (24 * 60 * 60); // 24 jam deadline
$now = time();
$sisa_waktu = $deadline - $now;
if ($sisa_waktu < 0) $sisa_waktu = 0;

// =====================================================
// AMBIL DATA PROPERTI SINKRON DENGAN RUANGAN
// =====================================================
$properti_list = [];
$q_properti = sqlsrv_query($conn, 
    "SELECT Nama_Properti, Kategori_Properti 
     FROM Properti 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
if ($q_properti !== false) {
    while ($row_prop = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
        $properti_list[] = $row_prop;
    }
}

// Variable JS aman
$ruangan_nama_js = htmlspecialchars($d_order['Nama_Ruangan'], ENT_QUOTES, 'UTF-8');
$tema_nama_js = htmlspecialchars($d_order['Nama_Tema'], ENT_QUOTES, 'UTF-8');
$paket_nama_js = htmlspecialchars($d_order['Nama_Paket'], ENT_QUOTES, 'UTF-8');
$durasi_js = (int)$d_order['Durasi_Waktu'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran <?= $tipe_bayar ?> - SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
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
            padding: 10px 22px;
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
        .dropdown-divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 8px 0; }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 10px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== BREADCRUMB BAR SINKRON ===== */
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

        /* ===== PROFILE MODAL CSS SINKRON ===== */
        .modal-content-custom { 
            border-radius: var(--radius-xl); 
            border: none; 
            overflow: hidden; 
            box-shadow: 0 24px 64px rgba(0,0,0,0.18);
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
        }
        .modal-header-custom { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; 
            padding: 24px 32px; 
            border: none; 
        }
        .modal-body-custom { padding: 32px; }
        .form-control-custom { 
            border-radius: var(--radius-md); 
            padding: 14px 18px; 
            border: 2px solid #e2e8f0; 
            font-size: 0.9rem; 
            font-weight: 600; 
            transition: var(--transition-smooth);
            background: #ffffff;
        }
        .form-control-custom:focus { 
            border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.1); 
            transform: translateY(-1px);
        }
        .form-label-custom { font-weight: 700; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 8px; }
        .profile-nav-tabs { border: none; gap: 12px; }
        .profile-nav-tabs .nav-link { 
            border: none; 
            color: var(--text-muted); 
            font-weight: 700; 
            font-size: 0.9rem; 
            padding: 12px 24px; 
            border-radius: var(--radius-md); 
            transition: var(--transition-smooth); 
        }
        .profile-nav-tabs .nav-link.active { 
            background: linear-gradient(135deg, var(--light-pink), var(--s-pink)); 
            color: var(--p-pink); 
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.15);
        }
        .img-preview-container { position: relative; width: 130px; height: 130px; margin: 0 auto 32px; }
        .img-preview { 
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover; 
            border: 4px solid var(--light-pink); 
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.2);
            transition: var(--transition-smooth);
        }
        .img-preview-container:hover .img-preview { transform: scale(1.05); }
        .btn-upload-trigger { 
            position: absolute; bottom: 0; right: 0; width: 40px; height: 40px; 
            border-radius: 50%; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; display: flex; align-items: center; justify-content: center; 
            cursor: pointer; border: 4px solid #ffffff; 
            transition: var(--transition-bounce);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.3);
        }
        .btn-upload-trigger:hover { transform: scale(1.15) rotate(10deg); }
        .pwd-requirement { display: block; font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-top: 4px; }
        .pwd-requirement.valid { color: #059669; }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ===== BACK BUTTON SINKRON ===== */
        .back-nav-container {
            margin-bottom: 20px;
        }
        .btn-back-step {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            cursor: pointer;
        }
        .btn-back-step:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
            transform: translateX(-4px);
        }

        /* ===== PROGRESS BAR SINKRON ===== */
        .progress-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 28px 36px;
            margin-bottom: 32px;
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
            box-shadow: var(--shadow-soft);
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            position: relative;
        }
        .progress-step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            border: 3px solid #e2e8f0;
            background: #ffffff;
            color: #94a3b8;
            transition: var(--transition-bounce);
            position: relative;
            z-index: 2;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: var(--shadow-glow);
            animation: pulseGlow 2s infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(216, 63, 103, 0.3); }
            50% { box-shadow: 0 0 40px rgba(216, 63, 103, 0.5); }
        }
        .progress-step.completed .progress-step-circle {
            background: linear-gradient(135deg, #059669, #10b981);
            border-color: #059669;
            color: #ffffff;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        .progress-step-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            transition: var(--transition-smooth);
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 56px;
            height: 4px;
            background: #e2e8f0;
            margin: 0 8px;
            margin-bottom: 26px;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .progress-line.completed { 
            background: linear-gradient(90deg, #059669, #10b981); 
        }
        .progress-line::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* ===== PROGRESS BAR CLICKABLE SINKRON ===== */
        .progress-step-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            transition: var(--transition-smooth);
            cursor: default;
        }
        .progress-step-wrapper.clickable {
            cursor: pointer;
        }
        .progress-step-wrapper.clickable:hover .progress-step-circle {
            transform: scale(1.1);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        .progress-step-wrapper.clickable:hover .progress-step-label {
            color: #059669;
        }

        /* ===== PAYMENT SECTION SINKRON ===== */
        .payment-section {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 32px;
            margin-bottom: 40px;
        }

        /* ===== DETAIL CARD SINKRON ===== */
        .detail-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 32px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            margin-bottom: 20px;
            transition: var(--transition-smooth);
        }
        .detail-card:hover {
            box-shadow: var(--shadow-card);
        }
        .detail-title {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        .detail-title i { 
            color: var(--p-pink); 
            font-size: 1.3rem;
            animation: iconFloat 3s ease-in-out infinite;
        }
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            margin-bottom: 12px;
            transition: var(--transition-smooth);
            border: 1px solid transparent;
        }
        .detail-item:hover { 
            transform: translateX(6px); 
            border-color: var(--light-pink);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.08);
        }
        .detail-item:last-child { margin-bottom: 0; }
        .detail-img {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-md);
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transition: var(--transition-smooth);
        }
        .detail-item:hover .detail-img {
            transform: scale(1.08) rotate(2deg);
        }
        .detail-info { flex: 1; }
        .detail-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text-dark);
            line-height: 1.3;
        }
        .detail-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 2px;
        }

        /* ===== INFO ROW SINKRON ===== */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
            transition: var(--transition-smooth);
        }
        .info-row:hover { transform: translateX(4px); }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .info-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .info-value.total {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
        }

        /* ===== JADWAL CARD SINKRON ===== */
        .jadwal-card {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: var(--radius-xl);
            padding: 24px;
            color: #ffffff;
            margin-bottom: 14px;
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.25);
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }
        .jadwal-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .jadwal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(216, 63, 103, 0.35);
        }
        .jadwal-card-title {
            font-size: 0.78rem;
            font-weight: 800;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .jadwal-card-main {
            font-size: 1.35rem;
            font-weight: 900;
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }
        .jadwal-card-sub {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ===== PROPERTI CARD SINKRON ===== */
        .properti-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 28px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            margin-bottom: 20px;
            transition: var(--transition-smooth);
        }
        .properti-card:hover {
            box-shadow: var(--shadow-card);
        }
        .properti-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .properti-tag {
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            color: var(--p-pink);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1.5px solid var(--light-pink);
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .properti-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.12);
            border-color: var(--p-pink);
        }

        /* ===== REKENING CARD ===== */
        .rekening-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid #bae6fd;
            margin-bottom: 16px;
            transition: var(--transition-smooth);
        }
        .rekening-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .rekening-bank {
            font-size: 0.85rem;
            font-weight: 800;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .rekening-no {
            font-size: 1.3rem;
            font-weight: 900;
            color: #0c4a6e;
            letter-spacing: 1px;
            margin-bottom: 4px;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .rekening-no:hover { color: var(--p-pink); }
        .rekening-an {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
        }
        .copy-btn {
            background: #ffffff;
            border: 2px solid #bae6fd;
            color: #0369a1;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-smooth);
            margin-top: 10px;
        }
        .copy-btn:hover { background: #0369a1; color: #ffffff; border-color: #0369a1; }
        .copy-btn.copied { background: var(--success); color: #ffffff; border-color: var(--success); }

        /* ===== QRIS CARD ===== */
        .qris-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 30px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-soft);
            text-align: center;
            margin-bottom: 20px;
            transition: var(--transition-smooth);
        }
        .qris-card:hover { box-shadow: var(--shadow-card); }
        .qris-title { font-size: 1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 16px; }
        .qris-img { width: 200px; height: 200px; object-fit: contain; border-radius: var(--radius-md); border: 2px solid #f1f5f9; margin-bottom: 12px; }
        .qris-note { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

        /* ===== UPLOAD FORM SINKRON ===== */
        .upload-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 28px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-card);
            position: sticky;
            top: 90px;
            height: fit-content;
            transition: var(--transition-smooth);
        }
        .upload-card:hover { box-shadow: 0 16px 48px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .upload-title {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .upload-title i { color: var(--p-pink); }
        .dp-amount {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: var(--radius-xl);
            padding: 24px;
            color: #ffffff;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.25);
        }
        .dp-amount-label {
            font-size: 0.85rem;
            font-weight: 700;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .dp-amount-value { font-size: 2.2rem; font-weight: 900; }
        .dp-amount-note { font-size: 0.8rem; font-weight: 600; opacity: 0.8; margin-top: 8px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; }
        .form-label span { color: var(--danger); }
        .form-select {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-md);
        font-family: inherit;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-dark);
        transition: var(--transition-smooth);
        background: #ffffff;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23718096' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 16px;
        padding-right: 48px;
    }
    .form-select:focus {
        outline: none;
        border-color: var(--p-pink);
        box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.08);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23d83f67' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    }

    .form-input {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-md);
        font-family: inherit;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-dark);
        transition: var(--transition-smooth);
        background: #ffffff;
    }
    .form-input:focus {
        outline: none;
        border-color: var(--p-pink);
        box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.08);
    }
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            transition: var(--transition-smooth);
            cursor: pointer;
            background: #f8fafc;
        }
        .file-upload-area:hover { border-color: var(--p-pink); background: var(--s-pink); transform: translateY(-2px); }
        .file-upload-area.has-file { border-color: var(--success); background: #ecfdf5; }
        .file-upload-icon { font-size: 2.5rem; color: #94a3b8; margin-bottom: 12px; }
        .file-upload-text { font-size: 0.9rem; font-weight: 700; color: var(--text-muted); }
        .file-upload-note { font-size: 0.75rem; color: #94a3b8; margin-top: 8px; }
        #previewBukti { animation: fadeIn 0.3s ease; }
        .btn-submit {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 16px 24px;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: var(--transition-smooth);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
        }
        .btn-submit:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 35px rgba(216, 63, 103, 0.4); }
        .btn-submit:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        /* ===== STATUS ALERT SINKRON ===== */
        .alert-status { 
            border-radius: var(--radius-lg); 
            padding: 20px; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 16px; 
            box-shadow: var(--shadow-soft);
            animation: fadeSlideUp 0.5s ease forwards;
        }
        .alert-status.menunggu { background: #fffbeb; border: 2px solid #f59e0b; }
        .alert-status.valid { background: #ecfdf5; border: 2px solid #059669; }
        .alert-status.ditolak { background: #fef2f2; border: 2px solid #dc2626; }
        .alert-status i { font-size: 1.5rem; flex-shrink: 0; }
        .alert-status.menunggu i { color: #d97706; animation: warningPulse 2s infinite; }
        .alert-status.valid i { color: #059669; }
        .alert-status.ditolak i { color: #dc2626; }
        .alert-text { font-size: 0.9rem; font-weight: 700; }
        .alert-status.menunggu .alert-text { color: #92400e; }
        .alert-status.valid .alert-text { color: #065f46; }
        .alert-status.ditolak .alert-text { color: #991b1b; }

        /* ===== COUNTDOWN SINKRON ===== */
        .countdown-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid #f59e0b;
            box-shadow: var(--shadow-soft);
            animation: fadeSlideUp 0.5s ease forwards;
        }
        .countdown-box i { font-size: 1.5rem; color: #d97706; animation: warningPulse 2s infinite; }
        @keyframes warningPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .countdown-text { font-size: 0.9rem; font-weight: 700; color: #92400e; }
        .countdown-timer { font-size: 1.2rem; font-weight: 900; color: #d97706; }

        /* ===== DISKON ALERT SINKRON ===== */
        .diskon-alert {
            background: linear-gradient(135deg, #e6fffa, #d1fae5);
            border-radius: var(--radius-xl);
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 2px solid #a7f3d0;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.1);
            animation: fadeSlideUp 0.5s ease forwards;
            animation-delay: 0.15s;
            opacity: 0;
        }
        .diskon-alert .diskon-icon {
            font-size: 2.5rem;
            flex-shrink: 0;
            animation: celebrateBounce 1.5s ease-in-out infinite;
        }
        @keyframes celebrateBounce {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50% { transform: translateY(-8px) rotate(5deg); }
        }
        .diskon-alert h5 {
            font-weight: 900;
            color: #059669;
            margin-bottom: 4px;
            font-size: 1.05rem;
        }
        .diskon-alert p {
            color: #059669;
            font-size: 0.88rem;
            font-weight: 600;
            margin: 0;
        }

        /* ===== EXTRA GOODS LIST SINKRON ===== */
        .extra-goods-list {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px dashed #e2e8f0;
        }
        .extra-goods-title {
            font-size: 0.78rem;
            font-weight: 900;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .extra-goods-title i { color: var(--p-pink); }
        .extra-goods-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            background: #f8fafc;
            transition: var(--transition-smooth);
        }
        .extra-goods-item:hover {
            background: var(--s-pink);
            transform: translateX(4px);
        }
        .extra-goods-item span:last-child {
            color: var(--text-dark);
            font-weight: 800;
        }

        /* ===== ANIMATIONS SINKRON ===== */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== LOADING OVERLAY SINKRON ===== */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            gap: 20px;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border: 4px solid var(--light-pink);
            border-top-color: var(--p-pink);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--p-pink);
            letter-spacing: 0.5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== RESPONSIVE SINKRON ===== */
        @media (max-width: 1200px) {
            .payment-section { grid-template-columns: 1fr 380px; }
        }
        @media (max-width: 992px) {
            .payment-section { grid-template-columns: 1fr; }
            .upload-card { position: static; margin-top: 24px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 14px 20px; }
            .breadcrumb-bar { padding: 14px 20px; }
            .progress-container { padding: 20px 16px; }
            .progress-line { width: 30px; margin: 0 4px; }
        }
        @media (max-width: 768px) {
            .progress-step-label { display: none; }
            .progress-line { width: 16px; }
            .detail-card { padding: 24px; }
            .jadwal-card { padding: 20px; }
            .upload-card { padding: 24px; }
        }
        @media (max-width: 480px) {
            .detail-card { padding: 20px; }
            .properti-card { padding: 20px; }
            .upload-card { padding: 20px; }
            .btn-submit { padding: 14px 20px; font-size: 0.9rem; }
            .diskon-alert { flex-direction: column; text-align: center; padding: 20px; }
            .diskon-alert .diskon-icon { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <!-- LOADING OVERLAY SINKRON -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memproses...</div>
    </div>

    <!-- NAVBAR ATAS SINKRON -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalProfil">
                        <i class="bi bi-person-circle"></i> Profil Saya
                    </button>
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalLihatBiodata">
                        <i class="bi bi-person-vcard"></i> Lihat Biodata
                    </button>
                    <a href="../../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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

    <!-- BREADCRUMB SINKRON -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_order['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_order['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_order['Nama_Tema']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>">Jadwal</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Konfirmasi/konfirmasi.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&id_jadwal=<?= implode(',', $id_jadwal_arr) ?>">Konfirmasi</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pembayaran <?= $tipe_bayar ?></span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- BACK BUTTON DENGAN LOGIKA CANCEL ORDER -->
        <div class="back-nav-container">
            <button type="button" class="btn-back-step" onclick="confirmCancelAndBack()">
                <i class="bi bi-arrow-left"></i> Kembali ke Konfirmasi
            </button>
        </div>

        <!-- PROGRESS BAR SINKRON (Langkah 7 Pembayaran Active, 1 s.d 6 Completed, Clickable) -->
        <div class="progress-container">
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Pilih Paket</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Pilih Ruangan</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Pilih Tema</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <a href="../Barang_Cetak/pilih_barang_cetak.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Pilih Barang Cetak</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Jadwal</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <a href="../Konfirmasi/konfirmasi.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&id_jadwal=<?= implode(',', $id_jadwal_arr) ?>" class="progress-step-wrapper clickable">
                <div class="progress-step completed">
                    <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="progress-step-label">Konfirmasi</div>
                </div>
            </a>
            <div class="progress-line completed"></div>
            <div class="progress-step-wrapper">
                <div class="progress-step active">
                    <div class="progress-step-circle">7</div>
                    <div class="progress-step-label">Pembayaran <?= $tipe_bayar ?></div>
                </div>
            </div>
        </div>

        <?php if ($sudah_upload): ?>
            <!-- STATUS PEMBAYARAN -->
            <?php if ($status_pembayaran === STATUS_PEMBAYARAN_MENUNGGU): ?>
                <div class="alert-status menunggu">
                    <i class="bi bi-hourglass-split"></i>
                    <div>
                        <div class="alert-text">Pembayaran <?= $tipe_bayar ?> sedang menunggu verifikasi admin.</div>
                        <div style="font-size: 0.8rem; color: #92400e; margin-top: 4px;">Admin akan memeriksa bukti transfer Anda. Mohon tunggu.</div>
                    </div>
                </div>
            <?php elseif ($status_pembayaran === STATUS_PEMBAYARAN_VALID): ?>
                <div class="alert-status valid">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <div class="alert-text">Pembayaran <?= $tipe_bayar ?> telah diverifikasi!</div>
                        <div style="font-size: 0.8rem; color: #065f46; margin-top: 4px;">Silakan datang ke studio sesuai jadwal yang telah dipilih.</div>
                    </div>
                </div>
            <?php elseif ($status_pembayaran === STATUS_PEMBAYARAN_DITOLAK): ?>
                <div class="alert-status ditolak">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <div class="alert-text">Pembayaran <?= $tipe_bayar ?> ditolak oleh admin.</div>
                        <div style="font-size: 0.8rem; color: #991b1b; margin-top: 4px;">Silakan upload bukti transfer yang valid.</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- COUNTDOWN -->
            <div class="countdown-box">
                <i class="bi bi-clock-history"></i>
                <div>
                    <div class="countdown-text">Sisa waktu pembayaran:</div>
                    <div class="countdown-timer" id="countdown">--:--:--</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- BANNER DISKON PROSES (DITAMPILKAN JIKA MEMBELI BARANG CETAK) -->
        <?php if ($total_cetak_harga > 0): ?>
        <div class="diskon-alert">
            <div class="diskon-icon">🎉</div>
            <div>
                <h5>Selamat! Anda Mendapatkan Diskon 5%!</h5>
                <p>Anda mendapatkan diskon potongan harga spesial sebesar <strong>5%</strong> khusus untuk seluruh produk cetak foto Anda.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYMENT SECTION -->
        <div class="payment-section">
            <!-- Left: Info & Rekening -->
            <div>
                <!-- Paket Detail Card (SINKRON DENGAN KONFIRMASI) -->
                <div class="detail-card" style="animation: fadeSlideUp 0.5s ease forwards; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-box-seam-fill"></i> Paket Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/paket/<?= htmlspecialchars($d_order['Foto_Paket'] ?? 'default_paket.jpg') ?>" class="detail-img" alt="Paket">
                        <div class="detail-info">
                            <div class="detail-label">Paket Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Paket']) ?></div>
                            <div class="detail-sub"><?= (int)$d_order['Durasi_Waktu'] ?> menit &bull; Max <?= (int)$d_order['Kapasitas_Orang'] ?> orang</div>
                        </div>
                    </div>
                </div>

                <!-- Ruangan Detail Card (SINKRON) -->
                <div class="detail-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.1s; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-door-open-fill"></i> Ruangan</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/ruangan/<?= htmlspecialchars($d_order['Foto_Ruangan'] ?? 'default_ruangan.jpg') ?>" class="detail-img" alt="Ruangan">
                        <div class="detail-info">
                            <div class="detail-label">Ruangan Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Ruangan']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_order['Deskripsi_Ruangan']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tema Detail Card (SINKRON) -->
                <div class="detail-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.2s; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-image-fill"></i> Tema Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/tema/<?= htmlspecialchars($d_order['Foto_Tema'] ?? 'default_tema.jpg') ?>" class="detail-img" alt="Tema">
                        <div class="detail-info">
                            <div class="detail-label">Tema Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Tema']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_order['Kategori_Tema']) ?> &bull; <?= htmlspecialchars($d_order['Deskripsi_Tema']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Jadwal Card (Iterasi dinamis multi-slot, SINKRON) -->
                <div class="detail-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.3s; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-calendar-check-fill"></i> Jadwal Sesi (<?= $jumlah_slot ?> Slot)</div>
                    <?php 
                    $hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                    $bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                    foreach ($jadwal_order_list as $index => $row_j): 
                        $tgl_obj = $row_j['Tanggal_Jadwal'];
                        if (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) {
                            $hari_idx = (int)$tgl_obj->format('w');
                            $tgl_num = $tgl_obj->format('d');
                            $bln_idx = (int)$tgl_obj->format('n') - 1;
                            $thn = $tgl_obj->format('Y');
                            $jam_mulai = $row_j['Jam_Mulai']->format('H:i');
                            $jam_selesai = $row_j['Jam_Selesai']->format('H:i');
                        } else {
                            $ts = strtotime($tgl_obj);
                            $hari_idx = date('w', $ts);
                            $tgl_num = date('d', $ts);
                            $bln_idx = (int)date('n', $ts) - 1;
                            $thn = date('Y', $ts);
                            $jam_mulai = is_string($row_j['Jam_Mulai']) ? substr($row_j['Jam_Mulai'], 0, 5) : $row_j['Jam_Mulai']->format('H:i');
                            $jam_selesai = is_string($row_j['Jam_Selesai']) ? substr($row_j['Jam_Selesai'], 0, 5) : $row_j['Jam_Selesai']->format('H:i');
                        }
                    ?>
                        <div class="jadwal-card" style="<?= $index > 0 ? 'margin-top: 14px;' : '' ?>">
                            <div class="jadwal-card-title"><i class="bi bi-clock"></i> Jadwal Terpilih (Slot <?= $index + 1 ?>)</div>
                            <div class="jadwal-card-main"><?= $hari_indo[$hari_idx] ?>, <?= $tgl_num ?> <?= $bulan_indo[$bln_idx] ?> <?= $thn ?></div>
                            <div class="jadwal-card-sub"><i class="bi bi-clock-fill"></i> <?= $jam_mulai ?> - <?= $jam_selesai ?> WIB</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Properti Card (SINKRON) -->
                <?php if (!empty($properti_list)): ?>
                <div class="properti-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.4s; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-stars"></i> Properti Tersedia</div>
                    <div class="detail-sub" style="margin-bottom:8px;">Fasilitas yang tersedia di ruangan ini:</div>
                    <div class="properti-tags">
                        <?php foreach ($properti_list as $prop): ?>
                        <span class="properti-tag">
                            <i class="bi bi-check-circle-fill" style="font-size:0.7rem;"></i>
                            <?= htmlspecialchars($prop['Nama_Properti']) ?> (<?= htmlspecialchars($prop['Kategori_Properti']) ?>)
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rekening Bank -->
                <div class="detail-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.5s; opacity: 0;">
                    <div class="detail-title"><i class="bi bi-bank"></i> Rekening Pembayaran</div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px; font-weight: 600;">Silakan transfer ke salah satu rekening berikut:</p>

                    <?php if (!empty($rekening_list)): ?>
                    <?php foreach ($rekening_list as $idx => $rek): ?>
                    <div class="rekening-card">
                        <div class="rekening-bank"><?= htmlspecialchars($rek['nama_bank']) ?></div>
                        <div class="rekening-no" onclick="copyRekening('<?= htmlspecialchars($rek['no_rekening']) ?>', this)" id="rek-<?= $idx ?>">
                            <?= htmlspecialchars($rek['no_rekening']) ?> <i class="bi bi-copy" style="font-size: 0.8rem; margin-left: 8px;"></i>
                        </div>
                        <div class="rekening-an">a.n. <?= htmlspecialchars($rek['atas_nama']) ?></div>
                        <button class="copy-btn" onclick="copyRekening('<?= htmlspecialchars($rek['no_rekening']) ?>', document.getElementById('rek-<?= $idx ?>'))">
                            <i class="bi bi-clipboard"></i> Salin Nomor
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- QRIS -->
                <div class="qris-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.6s; opacity: 0;">
                    <div class="qris-title"><i class="bi bi-qr-code"></i> Pembayaran QRIS</div>
                    <?php 
                    $qris_path = '../../../../assets/img/qris/qris_spotlight.jpeg';
                    if (file_exists($qris_path)): 
                    ?>
                        <img src="<?= $qris_path ?>" alt="QRIS" class="qris-img">
                    <?php else: ?>
                        <div style="padding:40px 20px;background:linear-gradient(135deg,#f8fafc,#fff5f6);border-radius:16px;border:2px dashed var(--light-pink);text-align:center;margin-bottom:16px;">
                            <i class="bi bi-qr-code-scan" style="font-size:4rem;color:var(--p-pink);margin-bottom:16px;display:block;"></i>
                            <div style="font-weight:800;font-size:1.1rem;color:var(--text-dark);margin-bottom:8px;">QRIS SpotLight Studio</div>
                            <div style="font-size:0.9rem;color:var(--text-muted);font-weight:600;">Scan QRIS ini untuk pembayaran</div>
                        </div>
                    <?php endif; ?>
                    <div class="qris-note">Scan QRIS di atas untuk pembayaran</div>
                    <div class="qris-note" style="margin-top: 4px; font-weight: 700; color: var(--p-pink);">a.n. SpotLight Studio Foto</div>
                </div>
            </div>

            <!-- Right: Upload Form (SINKRON DENGAN PERHITUNGAN DP / LUNAS SECARA REAL-TIME) -->
            <div class="upload-card" style="animation: fadeSlideUp 0.5s ease forwards; animation-delay: 0.2s; opacity: 0;">
                <div class="upload-title"><i class="bi bi-receipt"></i> Ringkasan Pembayaran</div>

                <div class="info-row">
                    <span class="info-label">No. Booking</span>
                    <span class="info-value" style="font-weight: 800;">#<?= str_pad((int)$d_order['ID_Order'], 5, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Harga Paket (<?= $jumlah_slot ?> Sesi)</span>
                    <span class="info-value">Rp <?= $harga_paket_format ?></span>
                </div>

                <!-- Detail Cetak jika ada -->
                <?php if ($total_cetak_harga > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Total Produk Cetak</span>
                        <span class="info-value">Rp <?= $total_cetak_format ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Diskon Produk Cetak (5%)</span>
                        <span class="info-value" style="font-weight: 800; color: var(--success);">- Rp <?= $diskon_cetak_format ?></span>
                    </div>
                <?php endif; ?>

                <div class="info-row">
                    <span class="info-label">Biaya Ruangan</span>
                    <span class="info-value" style="color:var(--success);">Gratis</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Biaya Tema</span>
                    <span class="info-value" style="color:var(--success);">Gratis</span>
                </div>

                <!-- LIST PRODUK CETAK DI DETAIL HARGA -->
                <?php if ($total_cetak_harga > 0): ?>
                    <div class="extra-goods-list">
                        <div class="extra-goods-title"><i class="bi bi-printer-fill"></i> Rincian Cetak:</div>
                        <?= $extra_cetak_html ?>
                    </div>
                <?php endif; ?>

                <div style="height: 2px; background: linear-gradient(90deg, transparent, #f1f5f9, transparent); margin: 16px 0;"></div>

                <div class="info-row" style="border-bottom: none;">
                    <span class="info-label" style="font-weight: 800;">Total Tagihan</span>
                    <span class="info-value total">Rp <?= $total_harga_format ?></span>
                </div>

                <div class="dp-amount">
                    <div class="dp-amount-label">Nominal Pembayaran <?= $keterangan_pembayaran ?></div>
                    <div class="dp-amount-value">Rp <?= $nominal_bayar_sekarang_format ?></div>
                    <div class="dp-amount-note"><?= $keterangan_bawah ?></div>
                </div>

                <?php if (!$sudah_upload || $status_pembayaran === STATUS_PEMBAYARAN_DITOLAK): ?>
                <form id="formPembayaran" method="POST" action="proses_pembayaran.php" enctype="multipart/form-data">
                    <input type="hidden" name="id_order" value="<?= (int)$id_order ?>">
                    <!-- SINKRONISASI TIPE PEMBAYARAN KE DB -->
                    <input type="hidden" name="tipe_pembayaran" value="<?= $tipe_bayar === 'Lunas' ? 'Lunas' : 'DP' ?>">

                    <div class="form-group">
                    <label class="form-label">Metode Pembayaran <span>*</span></label>
                    <select name="metode_pembayaran" id="metodePembayaran" class="form-select" required>
                        <option value="">-- Pilih Metode --</option>
                        <option value="Transfer Bank BCA">Transfer Bank BCA</option>
                        <option value="Transfer Bank BNI">Transfer Bank BNI</option>
                        <option value="Transfer Bank Mandiri">Transfer Bank Mandiri</option>
                        <option value="QRIS">QRIS</option>
                    </select>
                    <small id="errorMetode" style="color:#dc2626;font-size:0.8rem;font-weight:600;display:none;margin-top:6px;">
                        <i class="bi bi-exclamation-circle-fill"></i> Silakan pilih metode pembayaran terlebih dahulu
                    </small>
                </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah Transfer <span>*</span></label>
                        <input type="text" name="jumlah_bayar_display" class="form-input" value="Rp <?= $nominal_bayar_sekarang_format ?>" readonly>
                        <input type="hidden" name="jumlah_bayar" value="<?= $nominal_bayar_sekarang ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bukti Transfer <span>*</span></label>
                        <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('inputFile').click()">
                            <div class="file-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="file-upload-text" id="fileText">Klik untuk upload bukti transfer</div>
                            <div class="file-upload-note">Format: JPG, PNG, PDF (Max 5MB)</div>
                        </div>
                        <input type="file" name="bukti_transfer" id="inputFile" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required onchange="handleFileSelect(this)">
                    </div>

                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="bi bi-check2-circle"></i> Konfirmasi Pembayaran
                    </button>
                </form>
                <?php else: ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="bi bi-check-circle-fill" style="font-size: 3rem; color: var(--success); margin-bottom: 16px;"></i>
                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-dark); margin-bottom: 8px;">Pembayaran sudah dikirim</div>
                    <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Mohon tunggu verifikasi dari admin.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- =====================================================
    MODAL DETAIL PROFIL & KATA SANDI SINKRON
    ===================================================== -->
    <div class="modal fade" id="modalProfil" data-bs-backdrop="static" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold" id="modalProfilLabel">
                        <i class="bi bi-person-fill-gear me-2"></i> Pengaturan Profil Pelanggan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="bg-light px-4 pt-3">
                    <ul class="nav profile-nav-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="detail-tab" data-bs-toggle="tab" data-bs-target="#tab-detail" type="button" role="tab" aria-controls="tab-detail" aria-selected="true">
                                <i class="bi bi-person-badge-fill me-1"></i> Detail Profil
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#tab-password" type="button" role="tab" aria-controls="tab-password" aria-selected="false">
                                <i class="bi bi-shield-lock-fill me-1"></i> Ubah Kata Sandi
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body modal-body-custom">
                    <div class="tab-content" id="profileTabsContent">

                        <!-- TAB 1: DETAIL PROFIL -->
                        <div class="tab-pane fade show active" id="tab-detail" role="tabpanel" aria-labelledby="detail-tab">
                            <form action="../../index.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action_type" value="update_profile">

                                <div class="img-preview-container">
                                    <img src="<?= $foto_customer_src ?>" id="profilePreview" class="img-preview" alt="Foto Profil">
                                    <label for="foto_profil" class="btn-upload-trigger" title="Ubah Foto">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                    <input type="file" id="foto_profil" name="foto_profil" accept="image/png, image/jpeg, image/jpg" style="display: none;" onchange="previewImage(event)">
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Username</label>
                                        <input type="text" class="form-control form-control-custom bg-light" value="<?= htmlspecialchars($username_customer) ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Nama Lengkap</label>
                                        <input type="text" name="nama_pelanggan" class="form-control form-control-custom" value="<?= htmlspecialchars($nama_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Email</label>
                                        <input type="email" name="email_pelanggan" class="form-control form-control-custom" value="<?= htmlspecialchars($email_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Nomor HP</label>
                                        <input type="text" name="no_hp" id="inputHPModal" class="form-control form-control-custom" value="<?= htmlspecialchars($no_hp_customer) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Jenis Kelamin</label>
                                        <select name="jenis_kelamin" class="form-select form-control-custom">
                                            <option value="Laki-laki" <?= ($jk_customer == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                            <option value="Perempuan" <?= ($jk_customer == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Tanggal Lahir</label>
                                        <input type="date" name="tanggal_lahir" class="form-control form-control-custom" value="<?= $tgl_lahir_str ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label form-label-custom">Alamat Lengkap</label>
                                        <textarea name="alamat" rows="2" class="form-control form-control-custom" required><?= htmlspecialchars($alamat_customer) ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer-custom mt-4 px-0 pb-0">
                                    <button type="button" class="btn btn-secondary px-4" style="border-radius:12px; font-weight:700;" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn text-white px-4" style="background: var(--p-pink); border-radius:12px; font-weight:700;">Simpan Profil</button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: UBAH KATA SANDI -->
                        <div class="tab-pane fade" id="tab-password" role="tabpanel" aria-labelledby="password-tab">
                            <form action="../../index.php" method="POST" id="formPassword">
                                <input type="hidden" name="action_type" value="update_password">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label form-label-custom">Kata Sandi Saat Ini</label>
                                        <input type="password" name="pass_lama" class="form-control form-control-custom" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Kata Sandi Baru</label>
                                        <input type="password" name="pass_baru" id="pass_baru" class="form-control form-control-custom" oninput="checkPasswordStrength()" required>
                                        <small class="pwd-requirement" id="pwdLength">Minimal 8 karakter</small>
                                        <small class="pwd-requirement" id="pwdUpper">Minimal 1 huruf besar</small>
                                        <small class="pwd-requirement" id="pwdNumber">Minimal 1 angka</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Konfirmasi Kata Sandi Baru</label>
                                        <input type="password" name="pass_konfirmasi" id="pass_konfirmasi" class="form-control form-control-custom" oninput="checkPasswordMatch()" required>
                                        <small class="pwd-requirement" id="pwdMatch">Kata sandi tidak cocok</small>
                                    </div>
                                </div>
                                <div class="modal-footer-custom mt-4 px-0 pb-0">
                                    <button type="button" class="btn btn-secondary px-4" style="border-radius:12px; font-weight:700;" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" id="btnSubmitPassword" class="btn text-white px-4" style="background: var(--p-pink); border-radius:12px; font-weight:700;" disabled>Perbarui Sandi</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BIODATA SINKRON -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);border-radius:50%;overflow:hidden;">
                            <img src="<?= $foto_customer_src ?>" alt="Foto Profil" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_customer) ?></h5>
                        <span class="badge px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;background: linear-gradient(135deg, var(--p-pink), var(--d-pink));">Pelanggan</span>
                    </div>
                    <div class="p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                        <div class="row g-3" style="font-size: 0.85rem; font-weight:600;">
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Username</small><span class="fw-bold text-dark">@<?= htmlspecialchars($username_customer) ?></span></div>
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Tanggal Lahir</small><span class="fw-bold text-dark"><?= fmtTgl($tgl_lahir_str) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small><span class="fw-bold text-dark"><?= htmlspecialchars($email_customer) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark"><?= htmlspecialchars($jk_customer) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small><span class="fw-bold text-dark"><?= htmlspecialchars($no_hp_customer) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small><span class="fw-bold text-dark"><?= htmlspecialchars($alamat_customer) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
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
                text: 'Anda akan meninggalkan halaman customer dan kembali ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../index.php';
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        // =====================================================
        // LOGIKA KEMBALI KE KONFIRMASI = CANCEL ORDER
        // =====================================================
        function confirmCancelAndBack() {
            Swal.fire({
                title: 'Batalkan Order?',
                html: '<div style="text-align:left;">' +
                      '<p>Anda akan kembali ke halaman konfirmasi. Order ini akan <strong style="color:#dc2626;">dibatalkan otomatis</strong> dan slot jadwal akan dilepas.</p>' +
                      '<p style="margin-top:8px;color:#718096;font-size:0.9rem;">Jika ingin booking lagi, Anda harus mengulang dari awal.</p>' +
                      '</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Batalkan Order',
                cancelButtonText: 'Tetap di Halaman Ini',
                width: 480
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    // Redirect ke proses cancel order, lalu kembali ke konfirmasi
                    window.location.href = 'cancel_order.php?id_order=<?= (int)$id_order ?>&redirect=konfirmasi';
                }
            });
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('show');
        }

        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('show');
        }

        window.addEventListener('load', function() {
            hideLoading();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoading();
            }
        });

        // Copy rekening
        function copyRekening(noRek, el) {
            navigator.clipboard.writeText(noRek).then(() => {
                const btn = el.closest('.rekening-card').querySelector('.copy-btn');
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-clipboard"></i> Salin Nomor';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }

        // File upload dengan preview
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
                    text.innerHTML = '<i class="bi bi-cloud-arrow-up"></i> Klik untuk upload bukti transfer';

                    const oldPreview = document.getElementById('previewBukti');
                    if (oldPreview) oldPreview.remove();
                    return;
                }

                area.classList.add('has-file');
                text.innerHTML = '<i class="bi bi-check-circle-fill" style="color: var(--success);"></i> ' + file.name;

                // Preview gambar
                const reader = new FileReader();
                reader.onload = function(e) {
                    const oldPreview = document.getElementById('previewBukti');
                    if (oldPreview) oldPreview.remove();

                    const previewDiv = document.createElement('div');
                    previewDiv.id = 'previewBukti';
                    previewDiv.style.cssText = 'margin-top:16px;text-align:center;';
                    previewDiv.innerHTML = '<div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);margin-bottom:8px;"><i class="bi bi-eye me-1"></i> Preview Bukti Transfer</div><img src="' + e.target.result + '" style="max-width:100%;max-height:250px;border-radius:12px;border:2px solid #e2e8f0;box-shadow:0 4px 12px rgba(0,0,0,0.08);" alt="Preview">';
                    area.parentNode.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            }
        }

        // Countdown timer
        <?php if (!$sudah_upload): ?>
        function updateCountdown() {
            const sisa = <?= $sisa_waktu ?>;
            const now = Math.floor(Date.now() / 1000);
            const deadline = now + sisa;

            function tick() {
                const current = Math.floor(Date.now() / 1000);
                const diff = deadline - current;
                if (diff <= 0) {
                    document.getElementById('countdown').innerText = 'Waktu habis!';
                    document.getElementById('btnSubmit').disabled = true;
                    return;
                }
                const hours = Math.floor(diff / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                const seconds = (diff % 60).toString().padStart(2, '0');
                document.getElementById('countdown').innerText = hours + ':' + minutes + ':' + seconds;
            }
            tick();
            setInterval(tick, 1000);
        }
        updateCountdown();
        <?php endif; ?>

        // Form submit
        document.getElementById('formPembayaran')?.addEventListener('submit', function(e) {
            const metode = document.getElementById('metodePembayaran');
            const file = document.getElementById('inputFile').files[0];
            const errorMetode = document.getElementById('errorMetode');
            let hasError = false;

            // Reset error
            errorMetode.style.display = 'none';
            metode.style.borderColor = '#e2e8f0';

            // Validasi: Metode pembayaran harus dipilih
            if (!metode.value) {
                e.preventDefault();
                metode.style.borderColor = '#dc2626';
                metode.focus();
                errorMetode.style.display = 'block';
                hasError = true;
            }

            // Validasi: Bukti transfer wajib
            if (!file) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Bukti Transfer Wajib',
                    text: 'Silakan upload bukti transfer terlebih dahulu.',
                    confirmButtonColor: '#d83f67'
                });
                hasError = true;
            }

            if (hasError) return;

            showLoading();
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';
        });

        // Visual feedback saat metode pembayaran dipilih
document.getElementById('metodePembayaran')?.addEventListener('change', function() {
    const errorMetode = document.getElementById('errorMetode');
    if (this.value) {
        this.style.borderColor = 'var(--p-pink)';
        this.style.boxShadow = '0 0 0 4px rgba(216, 63, 103, 0.1)';
        errorMetode.style.display = 'none';
    } else {
        this.style.borderColor = '#e2e8f0';
        this.style.boxShadow = 'none';
    }
});

// Form submit dengan validasi metode pembayaran
document.getElementById('formPembayaran')?.addEventListener('submit', function(e) {
    const metode = document.getElementById('metodePembayaran');
    const file = document.getElementById('inputFile').files[0];
    const errorMetode = document.getElementById('errorMetode');
    let hasError = false;

    // Reset error
    errorMetode.style.display = 'none';
    metode.style.borderColor = '#e2e8f0';

    // Validasi: Metode pembayaran harus dipilih
    if (!metode.value) {
        e.preventDefault();
        metode.style.borderColor = '#dc2626';
        metode.focus();
        errorMetode.style.display = 'block';
        hasError = true;
    }

    // Validasi: Bukti transfer wajib
    if (!file) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Bukti Transfer Wajib',
            text: 'Silakan upload bukti transfer terlebih dahulu.',
            confirmButtonColor: '#d83f67'
        });
        hasError = true;
    }

    if (hasError) return;

    showLoading();
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';
});

        // Preview image untuk modal profil
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById('profilePreview').src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        // Password strength checker
        function checkPasswordStrength() {
            const pass = document.getElementById('pass_baru').value;
            const lengthReq = document.getElementById('pwdLength');
            const upperReq = document.getElementById('pwdUpper');
            const numberReq = document.getElementById('pwdNumber');

            if (pass.length >= 8) {
                lengthReq.classList.add('valid');
                lengthReq.innerHTML = '<i class="bi bi-check-circle-fill"></i> Minimal 8 karakter';
            } else {
                lengthReq.classList.remove('valid');
                lengthReq.innerHTML = 'Minimal 8 karakter';
            }

            if (/[A-Z]/.test(pass)) {
                upperReq.classList.add('valid');
                upperReq.innerHTML = '<i class="bi bi-check-circle-fill"></i> Minimal 1 huruf besar';
            } else {
                upperReq.classList.remove('valid');
                upperReq.innerHTML = 'Minimal 1 huruf besar';
            }

            if (/[0-9]/.test(pass)) {
                numberReq.classList.add('valid');
                numberReq.innerHTML = '<i class="bi bi-check-circle-fill"></i> Minimal 1 angka';
            } else {
                numberReq.classList.remove('valid');
                numberReq.innerHTML = 'Minimal 1 angka';
            }

            checkPasswordMatch();
        }

        // Password match checker
        function checkPasswordMatch() {
            const pass = document.getElementById('pass_baru').value;
            const confirm = document.getElementById('pass_konfirmasi').value;
            const matchReq = document.getElementById('pwdMatch');
            const btnSubmit = document.getElementById('btnSubmitPassword');

            const isLengthValid = pass.length >= 8;
            const isUpperValid = /[A-Z]/.test(pass);
            const isNumberValid = /[0-9]/.test(pass);
            const isMatch = pass === confirm && confirm !== '';

            if (isMatch) {
                matchReq.classList.add('valid');
                matchReq.innerHTML = '<i class="bi bi-check-circle-fill"></i> Kata sandi cocok';
            } else {
                matchReq.classList.remove('valid');
                matchReq.innerHTML = 'Kata sandi tidak cocok';
            }

            btnSubmit.disabled = !(isLengthValid && isUpperValid && isNumberValid && isMatch);
        }

        // Success/Error Alert
        <?php if (isset($_GET['success']) && $_GET['success'] === 'upload_berhasil'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Upload Berhasil!',
            text: 'Bukti pembayaran Anda telah dikirim. Mohon tunggu verifikasi admin.',
            confirmButtonColor: '#d83f67'
        });
        <?php elseif (isset($_GET['error'])): 
            $error_msg = htmlspecialchars($_GET['error']);
            $error_title = 'Gagal!';
            $error_text = $error_msg;
            
            switch ($error_msg) {
                case 'metode_pembayaran_wajib_dipilih':
                    $error_title = 'Metode Pembayaran Belum Dipilih';
                    $error_text = 'Silakan pilih metode pembayaran terlebih dahulu sebelum mengupload bukti transfer.';
                    break;
                case 'bukti_transfer_wajib_diupload':
                    $error_title = 'Bukti Transfer Wajib';
                    $error_text = 'Silakan upload bukti transfer terlebih dahulu.';
                    break;
                case 'nominal_tidak_sesuai':
                    $error_title = 'Nominal Tidak Sesuai';
                    $error_text = 'Nominal pembayaran tidak sesuai dengan yang seharusnya.';
                    break;
                case 'sudah_upload_dp':
                    $error_title = 'Sudah Upload';
                    $error_text = 'Anda sudah pernah upload bukti pembayaran untuk order ini.';
                    break;
                case 'format_file_tidak_valid':
                    $error_title = 'Format File Tidak Valid';
                    $error_text = 'Format file harus JPG, JPEG, PNG, atau PDF.';
                    break;
                case 'file_terlalu_besar':
                    $error_title = 'File Terlalu Besar';
                    $error_text = 'Ukuran file maksimal 5MB.';
                    break;
            }
        ?>
        Swal.fire({
            icon: 'error',
            title: '<?= $error_title ?>',
            text: '<?= $error_text ?>',
            confirmButtonColor: '#d83f67'
        });
        <?php endif; ?>
    </script>
</body>
</html>