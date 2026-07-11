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

// =====================================================
// DEFINISI FALLBACK AVATAR SVG (Penyelesaian Error)
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
// AMBIL ID DARI URL DENGAN DUKUNGAN MULTI-SLOT JADWAL
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

// Mengurai multi-slot ID jadwal ke dalam array sanitasi
$id_jadwal_arr = array_map('intval', explode(',', $id_jadwal_raw));
$placeholders_jadwal = implode(',', array_fill(0, count($id_jadwal_arr), '?'));

// =====================================================
// AMBIL DATA PAKET
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket 
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

// =====================================================
// AMBIL DATA RUANGAN
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi, Foto_Ruangan 
     FROM Ruangan 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
if ($q_ruangan === false) {
    die("Error query Ruangan: " . print_r(sqlsrv_errors(), true));
}
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);

if (!$d_ruangan) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA TEMA
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT ID_Tema, Nama_Tema, Kategori_Tema, Deskripsi, Foto_Tema 
     FROM Tema_Foto 
     WHERE ID_Tema = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_tema)
);
if ($q_tema === false) {
    die("Error query Tema: " . print_r(sqlsrv_errors(), true));
}
$d_tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC);

if (!$d_tema) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA JADWAL (MENDUKUNG MULTI-SLOT)
// =====================================================
$q_jadwal = sqlsrv_query($conn, 
    "SELECT ID_Jadwal, ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal
     FROM Jadwal_Studio 
     WHERE ID_Jadwal IN ($placeholders_jadwal) AND Status = 1 AND Is_Deleted = 0", 
    $id_jadwal_arr
);
if ($q_jadwal === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}

$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$jadwal_list = [];
while ($row_jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
    // Validasi keabsahan ruangan
    if ((int)$row_jadwal['ID_Ruangan'] !== $id_ruangan) {
        header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_valid");
        exit();
    }

    $tgl_obj = $row_jadwal['Tanggal_Jadwal'];
    if (is_object($tgl_obj) && method_exists($tgl_obj, 'format')) {
        $tgl_str = $tgl_obj->format('Y-m-d');
        $hari_idx = (int)$tgl_obj->format('w');
        $tgl_num = $tgl_obj->format('d');
        $bln_idx = (int)$tgl_obj->format('n') - 1;
        $thn = $tgl_obj->format('Y');
    } else {
        $tgl_str = $tgl_obj;
        $ts = strtotime($tgl_str);
        $hari_idx = date('w', $ts);
        $tgl_num = date('d', $ts);
        $bln_idx = (int)date('n', $ts) - 1;
        $thn = date('Y', $ts);
    }

    $jam_mulai_obj = $row_jadwal['Jam_Mulai'];
    $jam_mulai_str = is_object($jam_mulai_obj) && method_exists($jam_mulai_obj, 'format') ? $jam_mulai_obj->format('H:i') : substr($jam_mulai_obj, 0, 5);

    $jam_selesai_obj = $row_jadwal['Jam_Selesai'];
    $jam_selesai_str = is_object($jam_selesai_obj) && method_exists($jam_selesai_obj, 'format') ? $jam_selesai_obj->format('H:i') : substr($jam_selesai_obj, 0, 5);

    $jadwal_list[] = [
        'id' => (int)$row_jadwal['ID_Jadwal'],
        'hari' => $hari_indo[$hari_idx],
        'tanggal_format' => $tgl_num . ' ' . $bulan_indo[$bln_idx] . ' ' . $thn,
        'waktu_format' => $jam_mulai_str . ' - ' . $jam_selesai_str
    ];
}

if (empty($jadwal_list)) {
    header("Location: ../Jadwal/pilih_jadwal.php?id_paket=$id_paket&id_ruangan=$id_ruangan&id_tema=$id_tema&error=jadwal_tidak_ditemukan");
    exit();
}

// =====================================================
// VALIDASI RELASI SINKRON
// =====================================================
$q_validasi = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", array($id_paket, $id_ruangan));
$d_validasi = sqlsrv_fetch_array($q_validasi, SQLSRV_FETCH_ASSOC);
if ($d_validasi['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

$q_validasi_tema = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ? AND ID_Tema = ?", array($id_ruangan, $id_tema));
$d_validasi_tema = sqlsrv_fetch_array($q_validasi_tema, SQLSRV_FETCH_ASSOC);
if ($d_validasi_tema['total'] == 0) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// AMBIL PROFIL CUSTOMER LENGKAP SINKRON
// =====================================================
$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil, Username_Pelanggan, Email_Pelanggan, No_Hp, Alamat, Jenis_Kelamin, Tanggal_Lahir FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
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
// RECALCULATE BELANJAAN BARANG CETAK & HITUNG DISKON SINKRON
// =====================================================
$extra_cetak_html = '';
$total_cetak_harga = 0;
if (isset($_SESSION['booking_cart_cetak']) && !empty($_SESSION['booking_cart_cetak'])) {
    $cart_ids = array_keys($_SESSION['booking_cart_cetak']);
    if (!empty($cart_ids)) {
        $cart_ids_str = implode(',', array_map('intval', $cart_ids));
        $q_cart_items = sqlsrv_query($conn, "SELECT ID_Barang, Nama_Barang, Harga_Barang FROM Barang_Cetak WHERE ID_Barang IN ($cart_ids_str)");
        if ($q_cart_items !== false) {
            while ($item = sqlsrv_fetch_array($q_cart_items, SQLSRV_FETCH_ASSOC)) {
                $id_brg = $item['ID_Barang'];
                $qty = $_SESSION['booking_cart_cetak'][$id_brg] ?? 0;
                if ($qty > 0) {
                    $subtotal = $qty * $item['Harga_Barang'];
                    $total_cetak_harga += $subtotal;
                    $extra_cetak_html .= '
                        <div class="extra-goods-item" style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                            <span>' . htmlspecialchars($item['Nama_Barang']) . ' (x' . $qty . ')</span>
                            <span style="color: var(--text-dark); font-weight: 700;">Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
                        </div>
                    ';
                }
            }
        }
    }
}

// Potongan harga spesial 5% khusus produk cetak
$diskon_cetak = 0;
if ($total_cetak_harga > 0) {
    $diskon_cetak = $total_cetak_harga * 0.05; // 5% [2]
}
$total_cetak_setelah_diskon = $total_cetak_harga - $diskon_cetak;

// Hitung rekapitulasi pembayaran total akhir (Mendukung Multi-Slot Jadwal secara proporsional)
$harga_paket = (float)$d_paket['Harga_Paket'];
$jumlah_slot = count($id_jadwal_arr);
$total_harga_paket = $harga_paket * $jumlah_slot;

$total_harga_semua = $total_harga_paket + $total_cetak_setelah_diskon;
$dp_amount = $total_harga_semua * 0.65; // DP 65% dari total keseluruhan
$sisa_amount = $total_harga_semua - $dp_amount;

$harga_paket_format = number_format($harga_paket, 0, ',', '.');
$total_harga_paket_format = number_format($total_harga_paket, 0, ',', '.');
$total_cetak_format = number_format($total_cetak_harga, 0, ',', '.');
$diskon_cetak_format = number_format($diskon_cetak, 0, ',', '.');
$total_format = number_format($total_harga_semua, 0, ',', '.');
$dp_format = number_format($dp_amount, 0, ',', '.');
$sisa_format = number_format($sisa_amount, 0, ',', '.');

// Cek ketersediaan seluruh slot secara real-time
$q_cek_jadwal = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE ID_Jadwal IN ($placeholders_jadwal) AND Status = 1 AND Is_Deleted = 0 AND Status_Jadwal = 0", $id_jadwal_arr);
$d_cek_jadwal = sqlsrv_fetch_array($q_cek_jadwal, SQLSRV_FETCH_ASSOC);
$jadwal_masih_tersedia = ($d_cek_jadwal && (int)$d_cek_jadwal['total'] === $jumlah_slot);

// Variable JS aman
$ruangan_nama_js = htmlspecialchars($d_ruangan['Nama_Ruangan'], ENT_QUOTES, 'UTF-8');
$tema_nama_js = htmlspecialchars($d_tema['Nama_Tema'], ENT_QUOTES, 'UTF-8');
$paket_nama_js = htmlspecialchars($d_paket['Nama_Paket'], ENT_QUOTES, 'UTF-8');
$durasi_js = (int)$d_paket['Durasi_Waktu'];
$jadwal_info_ringkas = $jumlah_slot . ' Sesi Terjadwal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Booking - SpotLight Studio</title>
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
            --transition-smooth: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.25);
        }
        .nav-btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.35);
            color: #fff;
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

        /* ===== BREADCRUMB BAR ===== */
        .breadcrumb-bar {
            background: #ffffff;
            padding: 16px 40px;
            border-bottom: 1px solid #f1f5f9;
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
        }
        .breadcrumb-inner a:hover { color: var(--p-pink); }
        .breadcrumb-inner .separator { color: #cbd5e1; }
        .breadcrumb-inner .current { color: var(--p-pink); font-weight: 700; }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== PROGRESS BAR SINKRON (Langkah 6 Konfirmasi Active) ===== */
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

        /* ===== KONFIRMASI SECTION ===== */
        .konfirmasi-section {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 40px;
            margin-bottom: 40px;
        }

        /* ===== DETAIL CARD ===== */
        .detail-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }
        .detail-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-title i { color: var(--p-pink); }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 16px;
            background: var(--s-pink);
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .detail-item:hover { transform: translateX(4px); }
        .detail-item:last-child { margin-bottom: 0; }
        .detail-img {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .detail-info { flex: 1; }
        .detail-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .detail-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== JADWAL CARD ===== */
        .jadwal-card {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 24px;
            padding: 24px;
            color: #ffffff;
            margin-bottom: 20px;
        }
        .jadwal-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .jadwal-card-main {
            font-size: 1.4rem;
            font-weight: 900;
            margin-bottom: 4px;
        }
        .jadwal-card-sub {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* ===== PROPERTI CARD ===== */
        .properti-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
            margin-bottom: 20px;
        }
        .properti-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .properti-tag {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* ===== HARGA SIDEBAR SINKRON ===== */
        .booking-summary {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        .summary-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .summary-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .summary-value.total {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .summary-divider {
            height: 2px;
            background: #f1f5f9;
            margin: 16px 0;
        }

        /* ===== METODE BAYAR SELECTOR ===== */
        .summary-dp-info {
            background: var(--s-pink);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            border: 2px dashed var(--light-pink);
        }
        .summary-dp-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--p-pink);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-dp-amount {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .summary-dp-note {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 12px;
            line-height: 1.5;
        }

        .payment-option-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .payment-option-card:hover {
            border-color: var(--p-pink);
        }
        .payment-option-card.active {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .option-label {
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--text-dark);
        }
        .option-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== WARNING BOX ===== */
        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .warning-box i { font-size: 1.5rem; color: #d97706; flex-shrink: 0; }
        .warning-text { font-size: 0.9rem; font-weight: 700; color: #92400e; }

        /* ===== INFO BOX ===== */
        .info-box {
            background: var(--s-pink);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .info-box i { font-size: 1.5rem; color: var(--p-pink); flex-shrink: 0; margin-top: 2px; }
        .info-text { font-size: 0.9rem; font-weight: 600; color: var(--text-dark); line-height: 1.6; }
        .info-text strong { color: var(--p-pink); }

        /* ===== BUTTONS ===== */
        .btn-group-konfirmasi {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-konfirmasi {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 16px 24px;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
            text-decoration: none;
        }
        .btn-konfirmasi:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(216, 63, 103, 0.4);
            color: #ffffff;
        }
        .btn-konfirmasi:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        .btn-kembali {
            background: #ffffff;
            color: var(--text-muted);
            border: 2px solid #e2e8f0;
            padding: 14px 24px;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-kembali:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .konfirmasi-section { grid-template-columns: 1fr; }
            .harga-card { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
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
            <a href="../Paket/pilih_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>">Jadwal</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Konfirmasi</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON (Langkah 6 Konfirmasi Active, 1 s.d 5 Completed) -->
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
            <div class="progress-step active">
                <div class="progress-step-circle">6</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">7</div>
                <div class="progress-step-label">Bayar DP</div>
            </div>
        </div>

        <?php if (!$jadwal_masih_tersedia): ?>
        <!-- WARNING: Jadwal sudah tidak tersedia -->
        <div class="warning-box">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div class="warning-text">
                Maaf, jadwal yang Anda pilih sudah tidak tersedia. Mungkin sudah dibooking oleh pelanggan lain. Silakan pilih jadwal lain.
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

        <!-- INFO BOX -->
        <div class="info-box">
            <i class="bi bi-info-circle-fill"></i>
            <div class="info-text">
                Silakan periksa kembali detail booking Anda. Pilih opsi pembayaran yang Anda inginkan (Bayar DP atau Bayar Lunas), kemudian tekan tombol <strong>"Konfirmasi Sesi & Bayar"</strong> untuk melanjutkan ke langkah pembayaran.
            </div>
        </div>

        <!-- KONFIRMASI SECTION -->
        <div class="konfirmasi-section">
            <!-- Left: Detail Booking -->
            <div>
                <!-- Paket -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-box-seam-fill"></i> Paket Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/paket/<?= htmlspecialchars($d_paket['Foto_Paket'] ?? 'default_paket.jpg') ?>" class="detail-img" alt="Paket">
                        <div class="detail-info">
                            <div class="detail-label">Paket Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></div>
                            <div class="detail-sub"><?= (int)$d_paket['Durasi_Waktu'] ?> menit &bull; Max <?= (int)$d_paket['Kapasitas_Orang'] ?> orang</div>
                        </div>
                    </div>
                </div>

                <!-- Ruangan -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-door-open-fill"></i> Ruangan</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/ruangan/<?= htmlspecialchars($d_ruangan['Foto_Ruangan'] ?? 'default_ruangan.jpg') ?>" class="detail-img" alt="Ruangan">
                        <div class="detail-info">
                            <div class="detail-label">Ruangan Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_ruangan['Deskripsi']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tema -->
                <div class="detail-card">
                    <div class="detail-title"><i class="bi bi-image-fill"></i> Tema Foto</div>
                    <div class="detail-item">
                        <img src="../../../../assets/img/tema/<?= htmlspecialchars($d_tema['Foto_Tema'] ?? 'default_tema.jpg') ?>" class="detail-img" alt="Tema">
                        <div class="detail-info">
                            <div class="detail-label">Tema Dipilih</div>
                            <div class="detail-value"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></div>
                            <div class="detail-sub"><?= htmlspecialchars($d_tema['Kategori_Tema']) ?> &bull; <?= htmlspecialchars($d_tema['Deskripsi']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Jadwal -->
                <div class="detail-card" style="position:relative; overflow:hidden;">
                    <div class="detail-title"><i class="bi bi-calendar-check-fill"></i> Jadwal Sesi</div>
                    <div class="jadwal-card">
                        <div class="jadwal-card-title"><i class="bi bi-clock"></i> Jadwal Terpilih</div>
                        <div class="jadwal-card-main"><?= htmlspecialchars($jadwal_hari) ?>, <?= htmlspecialchars($jadwal_tgl_format) ?></div>
                        <div class="jadwal-card-sub"><i class="bi bi-clock-fill"></i> <?= htmlspecialchars($jam_mulai_str) ?> - <?= htmlspecialchars($jam_selesai_str) ?> WIB</div>
                    </div>
                </div>

                <!-- Properti -->
                <?php if (!empty($properti_list)): ?>
                <div class="properti-card">
                    <div class="detail-title"><i class="bi bi-stars"></i> Properti Tersedia</div>
                    <div class="detail-sub" style="margin-bottom:8px;">Fasilitas yang tersedia di ruangan ini:</div>
                    <div class="properti-tags">
                        <?php foreach ($properti_list as $prop): ?>
                        <span class="properti-tag">
                            <i class="bi bi-check-circle-fill" style="font-size:0.7rem;margin-right:4px;"></i>
                            <?= htmlspecialchars($prop['Nama_Properti']) ?> (<?= htmlspecialchars($prop['Kategori_Properti']) ?>)
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Ringkasan Harga (SINKRON DENGAN PRODUK CETAK) -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title">
                        <i class="bi bi-receipt"></i> Ringkasan Pembayaran
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Harga Paket</span>
                        <span class="summary-value">Rp <?= $harga_format ?></span>
                    </div>

                    <!-- Detail Cetak jika ada -->
                    <?php if ($total_cetak_harga > 0): ?>
                        <div class="summary-row">
                            <span class="summary-label">Total Produk Cetak</span>
                            <span class="summary-value">Rp <?= $total_cetak_format ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Diskon Produk Cetak (5%)</span>
                            <span class="summary-value" style="font-weight: 800; color: var(--success);">- Rp <?= $diskon_cetak_format ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span class="summary-label">Biaya Ruangan</span>
                        <span class="summary-value" style="color:var(--success);">Gratis</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Biaya Tema</span>
                        <span class="summary-value" style="color:var(--success);">Gratis</span>
                    </div>

                    <!-- LIST PRODUK CETAK DI DETAIL HARGA -->
                    <?php if ($total_cetak_harga > 0): ?>
                        <div class="extra-goods-list">
                            <div class="extra-goods-title"><i class="bi bi-printer-fill me-1"></i>Rincian Cetak:</div>
                            <?= $extra_cetak_html ?>
                        </div>
                    <?php endif; ?>

                    <div class="summary-divider"></div>

                    <div class="summary-row" style="border-bottom: none;">
                        <span class="summary-label" style="font-weight: 800;">Total Tagihan</span>
                        <span class="summary-value total">Rp <?= $total_format ?></span>
                    </div>

                    <!-- INTERAKTIF PAYMENT OPTION SELECTOR (PENGENDALI DUA METODE BAYAR) -->
                    <div class="summary-dp-info">
                        <div class="summary-dp-title"><i class="bi bi-wallet2"></i> Pilih Metode Pembayaran</div>
                        
                        <!-- Opsi 1: Bayar DP (65%) -->
                        <div class="payment-option-card active mb-2" onclick="selectPaymentOption('DP')">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="payment_type" value="DP" checked style="accent-color: var(--p-pink); width:18px; height:18px;">
                                <div style="flex: 1; min-width: 0;">
                                    <div class="option-label">Bayar DP (65%)</div>
                                    <div class="option-desc">Bayar Rp <?= $dp_format ?> sekarang</div>
                                </div>
                            </div>
                        </div>

                        <!-- Opsi 2: Bayar Lunas (100%) -->
                        <div class="payment-option-card" onclick="selectPaymentOption('Lunas')">
                            <div class="d-flex align-items-center gap-3">
                                <input type="radio" name="payment_type" value="Lunas" style="accent-color: var(--p-pink); width:18px; height:18px;">
                                <div style="flex: 1; min-width: 0;">
                                    <div class="option-label">Bayar Lunas (100%)</div>
                                    <div class="option-desc">Bayar Rp <?= $total_format ?> sekarang</div>
                                </div>
                            </div>
                        </div>

                        <!-- Keterangan sisa tagihan yang dinamis sesuai opsi bayar -->
                        <div class="summary-dp-note" id="paymentNote">
                            Sisa pembayaran sebesar <strong>Rp <?= $sisa_format ?></strong> dapat dilunasi setelah sesi pemotretan Anda selesai di studio.
                        </div>
                    </div>

                    <div class="btn-group-konfirmasi">
                        <?php if ($jadwal_masih_tersedia): ?>
                        <button class="btn-konfirmasi" onclick="konfirmasiBooking()">
                            <i class="bi bi-check2-circle"></i> Konfirmasi Sesi & Bayar
                        </button>
                        <?php else: ?>
                        <button class="btn-konfirmasi" disabled>
                            <i class="bi bi-x-circle"></i> Jadwal Tidak Tersedia
                        </button>
                        <?php endif; ?>
                        <a href="../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>" class="btn-kembali">
                            <i class="bi bi-arrow-left"></i> Kembali ke Jadwal
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- =====================================================
    MODAL DETAIL PROFIL & KATA SANDI
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

        const ruanganNama = <?= json_encode($ruangan_nama_js) ?>;
        const temaNama = <?= json_encode($tema_nama_js) ?>;
        const paketNama = <?= json_encode($paket_nama_js) ?>;
        const durasi = <?= json_encode($durasi_js) ?>;
        const hargaFormat = <?= json_encode($harga_format) ?>;
        const totalHargaAkhirFormat = <?= json_encode($total_format) ?>;
        const idPaket = <?= json_encode((int)$id_paket) ?>;
        const idRuangan = <?= json_encode((int)$id_ruangan) ?>;
        const idTema = <?= json_encode((int)$id_tema) ?>;

        // =====================================================
        // KONTROLLER INTERAKTIF OPSI PEMBAYARAN (DP / LUNAS)
        // =====================================================
        let selectedPaymentType = 'DP';

        function selectPaymentOption(type) {
            // Hapus status active dari semua kartu opsi bayar
            document.querySelectorAll('.payment-option-card').forEach(card => card.classList.remove('active'));
            
            // Set input radio target ke checked dan aktifkan visual kartu
            const radioButton = document.querySelector(`input[name="payment_type"][value="${type}"]`);
            if (radioButton) {
                radioButton.checked = true;
                radioButton.closest('.payment-option-card').classList.add('active');
            }
            
            selectedPaymentType = type;

            // Perbarui teks keterangan sisa pembayaran di bawah kartu secara dinamis
            const noteContainer = document.getElementById('paymentNote');
            if (type === 'Lunas') {
                noteContainer.innerHTML = '<span class="text-success"><i class="bi bi-shield-check-fill me-1"></i> <strong>Bebas Hambatan!</strong> Anda telah memilih untuk melunasi seluruh tagihan sekarang. Tidak ada biaya tambahan di studio.</span>';
            } else {
                noteContainer.innerHTML = 'Sisa pembayaran sebesar <strong>Rp <?= $sisa_format ?></strong> dapat dilunasi setelah sesi pemotretan Anda selesai di studio.';
            }
        }

        function konfirmasiBooking() {
            Swal.fire({
                title: 'Konfirmasi Booking?',
                html: '<div style="text-align:left;font-size:0.95rem;">' +
                      '<p>Anda akan membuat order dengan detail:</p>' +
                      '<ul style="margin-top:8px;padding-left:20px;">' +
                      '<li><strong>Paket:</strong> ' + paketNama + '</li>' +
                      '<li><strong>Ruangan:</strong> ' + ruanganNama + '</li>' +
                      '<li><strong>Tema:</strong> ' + temaNama + '</li>' +
                      '<li><strong>Jadwal:</strong> <?= json_encode(htmlspecialchars($jadwal_hari . ', ' . $jadwal_tgl_format . ' | ' . $jam_mulai_str . ' - ' . $jam_selesai_str)) ?> WIB</li>' +
                      '</ul>' +
                      '<p style="margin-top:12px;color:#d83f67;font-weight:700;margin-bottom:2px;">Metode Pembayaran: ' + (selectedPaymentType === 'DP' ? 'Bayar DP (65%)' : 'Bayar Lunas (100%)') + '</p>' +
                      '<p style="font-size:0.85rem;color:#718096;">Tagihan Sekarang: ' + (selectedPaymentType === 'DP' ? 'Rp <?= $dp_format ?>' : 'Rp ' + totalHargaAkhirFormat) + '</p>' +
                      '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Buat Order',
                cancelButtonText: 'Batal',
                width: 480
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim ke proses_order.php beserta tipe pembayaran pilihan
                    window.location.href = 'proses_order.php?id_paket=' + idPaket + '&id_ruangan=' + idRuangan + '&id_tema=' + idTema + '&id_jadwal=<?= (int)$id_jadwal ?>&tipe_pembayaran=' + selectedPaymentType;
                }
            });
        }

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById('profilePreview').src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</body>
</html>