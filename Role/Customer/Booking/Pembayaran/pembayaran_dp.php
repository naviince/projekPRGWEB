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
// AMBIL DATA ORDER + PELANGGAN + PAKET
// =====================================================
$q_order = sqlsrv_query($conn, 
    "SELECT o.ID_Order, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, o.Status_Order, o.Tanggal_Booking,
            p.Nama_Pelanggan, p.No_Hp, p.Email_Pelanggan,
            pk.Nama_Paket, pk.Harga_Paket,
            r.Nama_Ruangan,
            j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
     FROM [Order] o
     INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
     INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
     INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
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

// Validasi: hanya order dengan Status_Order = 0 yang boleh bayar DP
if ((int)$d_order['Status_Order'] !== STATUS_ORDER_MENUNGGU_DP) {
    header("Location: ../../index.php?error=order_sudah_diproses");
    exit();
}

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
            <div class="info-row" style="border-bottom: 1px dashed #f1f5f9;">
                <span class="info-label">' . htmlspecialchars($item['Nama_Barang']) . ' (x' . $qty . ')</span>
                <span class="info-value">Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
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
$harga_paket = (float)$d_order['Total_Paket'];
$total_cetak_harga = (float)$d_order['Total_Barang_Cetak'];

// Potongan harga spesial 5% khusus produk cetak
$diskon_cetak = 0;
if ($total_cetak_harga > 0) {
    $diskon_cetak = $total_cetak_harga * 0.05; // 5% [2]
}
$total_cetak_setelah_diskon = $total_cetak_harga - $diskon_cetak;

// Hitung rekapitulasi pembayaran total akhir (SINKRON DENGAN KONFIRMASI)
$total_harga_order = $harga_paket + $total_cetak_setelah_diskon;
$dp_amount = $total_harga_order * 0.65; // DP 65% dari total keseluruhan [2]
$sisa_amount = $total_harga_order - $dp_amount;

$harga_paket_format = number_format($harga_paket, 0, ',', '.');
$total_cetak_format = number_format($total_cetak_harga, 0, ',', '.');
$diskon_cetak_format = number_format($diskon_cetak, 0, ',', '.');
$total_harga_format = number_format($total_harga_order, 0, ',', '.');
$dp_format = number_format($dp_amount, 0, ',', '.');
$sisa_format = number_format($sisa_amount, 0, ',', '.');

// =====================================================
// FORMAT JADWAL
// =====================================================
$tgl_obj = $d_order['Tanggal_Jadwal'];
if (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) {
    $tgl_str = $tgl_obj->format('d M Y');
} else {
    $tgl_str = date('d M Y', strtotime($tgl_obj));
}

$jam_mulai_obj = $d_order['Jam_Mulai'];
if (is_object($jam_mulai_obj) && method_exists($jam_mulai_obj, 'format')) {
    $jam_mulai_str = $jam_mulai_obj->format('H:i');
} else {
    $jam_mulai_str = substr($jam_mulai_obj, 0, 5);
}

$jam_selesai_obj = $d_order['Jam_Selesai'];
if (is_object($jam_selesai_obj) && method_exists($jam_selesai_obj, 'format')) {
    $jam_selesai_str = $jam_selesai_obj->format('H:i');
} else {
    $jam_selesai_str = substr($jam_selesai_obj, 0, 5);
}

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
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../assets/img/pelanggan/" . $foto_customer 
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran DP - SpotLight Studio</title>
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
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS ===== */
        .top-navbar {
            background: #ffffff;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        .nav-link-item {
            color: #4a5568;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 8px 0;
            position: relative;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--p-pink);
            border-radius: 3px;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: all 0.3s;
        }
        .nav-avatar:hover { transform: scale(1.1); border-color: var(--p-pink); }
        .nav-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 12px;
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid #f1f5f9;
        }
        .nav-dropdown.show { display: block; animation: fadeIn 0.2s ease; }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover { background: var(--s-pink); color: var(--p-pink); }
        .dropdown-item i { font-size: 1.1rem; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background: #f1f5f9; margin: 8px 0; }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== PROFILE MODAL CSS SINKRON ===== */
        .modal-content-custom { border-radius: 24px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .modal-header-custom { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; padding: 20px 30px; border: none; }
        .modal-body-custom { padding: 30px; }
        .form-control-custom { border-radius: 12px; padding: 12px 16px; border: 1px solid #e2e8f0; font-size: 0.9rem; font-weight: 600; transition: all 0.3s; }
        .form-control-custom:focus { border-color: var(--p-pink); box-shadow: 0 0 0 3px rgba(216, 63, 103, 0.1); }
        .form-label-custom { font-weight: 700; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 8px; }
        .profile-nav-tabs { border: none; gap: 10px; }
        .profile-nav-tabs .nav-link { border: none; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; padding: 10px 20px; border-radius: 12px; transition: all 0.3s; }
        .profile-nav-tabs .nav-link.active { background: var(--light-pink); color: var(--p-pink); }
        .img-preview-container { position: relative; width: 120px; height: 120px; margin: 0 auto 30px; }
        .img-preview { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid var(--light-pink); }
        .btn-upload-trigger { position: absolute; bottom: 0; right: 0; width: 36px; height: 36px; border-radius: 50%; background: var(--p-pink); color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid #ffffff; transition: all 0.3s; }
        .btn-upload-trigger:hover { background: var(--d-pink); transform: scale(1.1); }
        .pwd-requirement { display: block; font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-top: 4px; }
        .pwd-requirement.valid { color: #059669; }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ===== PROGRESS BAR SINKRON ===== */
        .progress-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 30px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            flex-wrap: wrap;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .progress-step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            border: 3px solid #e2e8f0;
            background: #ffffff;
            color: #94a3b8;
            transition: all 0.3s;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .progress-step.completed .progress-step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #ffffff;
        }
        .progress-step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: var(--success); }
        .progress-line {
            width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            margin-bottom: 24px;
        }
        .progress-line.completed { background: var(--success); }

        /* ===== PAYMENT SECTION ===== */
        .payment-section {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }

        /* ===== ORDER INFO CARD ===== */
        .info-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }
        .info-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-title i { color: var(--p-pink); }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
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

        /* ===== REKENING CARD ===== */
        .rekening-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #bae6fd;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        .rekening-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.15);
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
            transition: all 0.3s;
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
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .copy-btn:hover { background: #0369a1; color: #ffffff; border-color: #0369a1; }
        .copy-btn.copied { background: var(--success); color: #ffffff; border-color: var(--success); }

        /* ===== QRIS CARD ===== */
        .qris-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            text-align: center;
            margin-bottom: 20px;
        }
        .qris-title { font-size: 1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 16px; }
        .qris-img { width: 200px; height: 200px; object-fit: contain; border-radius: 16px; border: 2px solid #f1f5f9; margin-bottom: 12px; }
        .qris-note { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

        /* ===== UPLOAD FORM ===== */
        .upload-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .upload-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .dp-amount {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 20px;
            padding: 24px;
            color: #ffffff;
            text-align: center;
            margin-bottom: 24px;
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
        .form-select, .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: all 0.3s;
            background: #ffffff;
        }
        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.08);
        }
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: #f8fafc;
        }
        .file-upload-area:hover { border-color: var(--p-pink); background: var(--s-pink); }
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
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(216, 63, 103, 0.4); }
        .btn-submit:disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        /* ===== STATUS ALERT ===== */
        .alert-status { border-radius: 16px; padding: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }
        .alert-status.menunggu { background: #fffbeb; border: 2px solid #f59e0b; }
        .alert-status.valid { background: #ecfdf5; border: 2px solid #059669; }
        .alert-status.ditolak { background: #fef2f2; border: 2px solid #dc2626; }
        .alert-status i { font-size: 1.5rem; flex-shrink: 0; }
        .alert-status.menunggu i { color: #d97706; }
        .alert-status.valid i { color: #059669; }
        .alert-status.ditolak i { color: #dc2626; }
        .alert-text { font-size: 0.9rem; font-weight: 700; }
        .alert-status.menunggu .alert-text { color: #92400e; }
        .alert-status.valid .alert-text { color: #065f46; }
        .alert-status.ditolak .alert-text { color: #991b1b; }

        /* ===== COUNTDOWN ===== */
        .countdown-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid #f59e0b;
        }
        .countdown-box i { font-size: 1.5rem; color: #d97706; }
        .countdown-text { font-size: 0.9rem; font-weight: 700; color: #92400e; }
        .countdown-timer { font-size: 1.2rem; font-weight: 900; color: #d97706; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .payment-section { grid-template-columns: 1fr; }
            .upload-card { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
        }
    </style>
</head>
<body>

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
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalProfil">
                        <i class="bi bi-person-circle"></i> Profil Saya
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

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON (Langkah 7 Active, 1 s.d 6 Completed) -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Barang Cetak</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Jadwal</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">7</div>
                <div class="progress-step-label">Bayar DP</div>
            </div>
        </div>

        <?php if ($sudah_upload): ?>
            <!-- STATUS PEMBAYARAN -->
            <?php if ($status_pembayaran === STATUS_PEMBAYARAN_MENUNGGU): ?>
                <div class="alert-status menunggu">
                    <i class="bi bi-hourglass-split"></i>
                    <div>
                        <div class="alert-text">Pembayaran DP sedang menunggu verifikasi admin.</div>
                        <div style="font-size: 0.8rem; color: #92400e; margin-top: 4px;">Admin akan memeriksa bukti transfer Anda. Mohon tunggu.</div>
                    </div>
                </div>
            <?php elseif ($status_pembayaran === STATUS_PEMBAYARAN_VALID): ?>
                <div class="alert-status valid">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <div class="alert-text">Pembayaran DP telah diverifikasi!</div>
                        <div style="font-size: 0.8rem; color: #065f46; margin-top: 4px;">Silakan datang ke studio sesuai jadwal yang telah dipilih.</div>
                    </div>
                </div>
            <?php elseif ($status_pembayaran === STATUS_PEMBAYARAN_DITOLAK): ?>
                <div class="alert-status ditolak">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <div class="alert-text">Pembayaran DP ditolak oleh admin.</div>
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
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-3 p-4 mb-4" style="border-radius:24px; background: linear-gradient(135deg, #e6fffa, #d1fae5);">
            <div class="fs-1">🎉</div>
            <div>
                <h5 class="fw-bold text-success mb-1">Selamat! Anda Mendapatkan Diskon 5%!</h5>
                <p class="text-success small mb-0 fw-semibold">Anda mendapatkan diskon potongan harga spesial sebesar <strong>5%</strong> khusus untuk seluruh produk cetak foto Anda.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYMENT SECTION -->
        <div class="payment-section">
            <!-- Left: Info & Rekening -->
            <div>
                <!-- Order Info (SINKRON DENGAN TOTAL HARGA DAN DETAIL BARANG CETAK) -->
                <div class="info-card">
                    <div class="info-title"><i class="bi bi-receipt"></i> Detail Order</div>
                    <div class="info-row">
                        <span class="info-label">No. Order</span>
                        <span class="info-value" style="font-weight: 800;">#<?= str_pad((int)$d_order['ID_Order'], 5, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Paket</span>
                        <span class="info-value"><?= htmlspecialchars($d_order['Nama_Paket']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ruangan</span>
                        <span class="info-value"><?= htmlspecialchars($d_order['Nama_Ruangan']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Jadwal Sesi</span>
                        <span class="info-value"><?= htmlspecialchars($tgl_str) ?> | <?= htmlspecialchars($jam_mulai_str) ?> - <?= htmlspecialchars($jam_selesai_str) ?> WIB</span>
                    </div>
                    
                    <div class="info-row" style="margin-top:12px; padding-top:12px; border-top:1px solid #f8fafc;">
                        <span class="info-label">Harga Paket</span>
                        <span class="info-value">Rp <?= $harga_paket_format ?></span>
                    </div>

                    <!-- Rincian Cetak jika memesan barang cetak -->
                    <?php if ($total_cetak_harga > 0): ?>
                        <div style="margin: 12px 0; padding-top: 12px; border-top: 1px dashed #cbd5e1;">
                            <div style="font-size:0.8rem; font-weight:800; color:var(--text-dark); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">Produk Cetak Tambahan:</div>
                            <?= $extra_cetak_html ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Produk Cetak</span>
                            <span class="info-value">Rp <?= $total_cetak_format ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Diskon Produk Cetak (5%)</span>
                            <span class="info-value" style="color: var(--success); font-weight: 800;">- Rp <?= $diskon_cetak_format ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="info-row" style="margin-top:16px; padding-top:14px; border-top: 2px solid #f1f5f9;">
                        <span class="info-label" style="font-weight: 800;">Total Tagihan</span>
                        <span class="info-value total" style="font-size: 1.3rem; font-weight: 900; color: var(--p-pink);">Rp <?= $total_harga_format ?></span>
                    </div>
                </div>

                <!-- Rekening Bank -->
                <div class="info-card">
                    <div class="info-title"><i class="bi bi-bank"></i> Rekening Pembayaran</div>
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
                <div class="qris-card">
                    <div class="qris-title"><i class="bi bi-qr-code"></i> Pembayaran QRIS</div>
                    <?php 
                    $qris_path = '../../../../assets/img/qris/qris_spotlight.jpg';
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

            <!-- Right: Upload Form (SINKRON DENGAN PERHITUNGAN DP TOTAL KESELURUHAN) -->
            <div class="upload-card">
                <div class="upload-title"><i class="bi bi-upload"></i> Upload Bukti Transfer</div>

                <div class="dp-amount">
                    <div class="dp-amount-label">Nominal DP (65%)</div>
                    <div class="dp-amount-value">Rp <?= $dp_format ?></div>
                    <div class="dp-amount-note">Sisa pembayaran Rp <?= $sisa_format ?> dibayar setelah sesi pemotretan selesai</div>
                </div>

                <?php if (!$sudah_upload || $status_pembayaran === STATUS_PEMBAYARAN_DITOLAK): ?>
                <form id="formPembayaran" method="POST" action="proses_pembayaran.php" enctype="multipart/form-data">
                    <input type="hidden" name="id_order" value="<?= (int)$id_order ?>">

                    <div class="form-group">
                        <label class="form-label">Metode Pembayaran <span>*</span></label>
                        <select name="metode_pembayaran" class="form-select" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="Transfer Bank BCA">Transfer Bank BCA</option>
                            <option value="Transfer Bank BNI">Transfer Bank BNI</option>
                            <option value="Transfer Bank Mandiri">Transfer Bank Mandiri</option>
                            <option value="QRIS">QRIS</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah Transfer <span>*</span></label>
                        <input type="text" name="jumlah_bayar_display" class="form-input" value="Rp <?= $dp_format ?>" readonly>
                        <input type="hidden" name="jumlah_bayar" value="<?= $dp_amount ?>">
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
    MODAL DETAIL PROFIL & KATA SANDI SINKRON SUNTUK DETAIL
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
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-custom">Konfirmasi Kata Sandi Baru</label>
                                        <input type="password" name="pass_konfirmasi" id="pass_konfirmasi" class="form-control form-control-custom" oninput="checkPasswordMatch()" required>
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

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);"><img src="<?= $foto_customer_src ?>" alt="Foto Profil" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_customer) ?></h5>
                        <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Pelanggan</span>
                    </div>
                    <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
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
        // Toggle dropdown
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
                text: 'Anda akan meninggalkan halaman customer.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../../../index.php';
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../../../logout.php';
            });
        }

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
            const file = document.getElementById('inputFile').files[0];
            if (!file) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Bukti Transfer Wajib',
                    text: 'Silakan upload bukti transfer terlebih dahulu.',
                    confirmButtonColor: '#d83f67'
                });
                return;
            }
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';
        });

        // Success/Error Alert
        <?php if (isset($_GET['success']) && $_GET['success'] === 'upload_berhasil'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Upload Berhasil!',
            text: 'Bukti pembayaran Anda telah dikirim. Mohon tunggu verifikasi admin.',
            confirmButtonColor: '#d83f67'
        });
        <?php elseif (isset($_GET['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= htmlspecialchars($_GET['error']) ?>',
            confirmButtonColor: '#d83f67'
        });
        <?php endif; ?>
    </script>
</body>
</html>