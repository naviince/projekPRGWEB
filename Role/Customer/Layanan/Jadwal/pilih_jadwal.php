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
define('STATUS_JADWAL_MAINTENANCE', 2);
define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

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
// AMBIL ID PAKET, ID RUANGAN, ID TEMA DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) ||
    !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan']) ||
    !isset($_GET['id_tema']) || empty($_GET['id_tema'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];
$id_tema = (int)$_GET['id_tema'];

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
// VALIDASI: RUANGAN HARUS TERHUBUNG DENGAN PAKET
// =====================================================
$q_validasi = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", 
    array($id_paket, $id_ruangan)
);
if ($q_validasi === false) {
    die("Error query Validasi Paket-Ruangan: " . print_r(sqlsrv_errors(), true));
}
$d_validasi = sqlsrv_fetch_array($q_validasi, SQLSRV_FETCH_ASSOC);

if ($d_validasi['total'] == 0) {
    header("Location: ../Paket/pilih_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI: TEMA HARUS TERHUBUNG DENGAN RUANGAN
// =====================================================
$q_validasi_tema = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ? AND ID_Tema = ?", 
    array($id_ruangan, $id_tema)
);
if ($q_validasi_tema === false) {
    die("Error query Validasi Ruangan-Tema: " . print_r(sqlsrv_errors(), true));
}
$d_validasi_tema = sqlsrv_fetch_array($q_validasi_tema, SQLSRV_FETCH_ASSOC);

if ($d_validasi_tema['total'] == 0) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// AMBIL JADWAL DARI MASTER Jadwal_Studio (FILTER TANGGAL EXPIRED)
// =====================================================
$today = date('Y-m-d');
$now_time = date('H:i:s');

// Parameter tanggal dari URL (untuk navigasi 7 hari)
$selected_date = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : $today;
if (strtotime($selected_date) < strtotime($today)) {
    $selected_date = $today;
}

// Hitung range tanggal (7 hari dari selected_date)
$date_start = new DateTime($selected_date);
$date_end = clone $date_start;
$date_end->modify('+6 days');
$date_start_str = $date_start->format('Y-m-d');
$date_end_str = $date_end->format('Y-m-d');

$q_jadwal = sqlsrv_query($conn, 
    "SELECT 
        j.ID_Jadwal,
        j.Tanggal_Jadwal,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Keterangan,
        j.Status_Jadwal,
        j.Status
     FROM Jadwal_Studio j
     WHERE j.ID_Ruangan = ?
       AND j.ID_Paket = ?
       AND j.Tanggal_Jadwal BETWEEN ? AND ?
       AND j.Status = ?
       AND j.Is_Deleted = 0
     ORDER BY j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC",
    array($id_ruangan, $id_paket, $date_start_str, $date_end_str, STATUS_DATA_AKTIF)
);
if ($q_jadwal === false) {
    die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
}

// =====================================================
// CEK JADWAL YANG SUDAH DIBOOKING (dari tabel [Order])
// =====================================================
$q_booked = sqlsrv_query($conn, 
    "SELECT DISTINCT o.ID_Jadwal
     FROM [Order] o
     WHERE o.ID_Ruangan = ?
       AND o.ID_Paket = ?
       AND o.ID_Jadwal IS NOT NULL
       AND o.Status = 1
       AND o.Status_Order NOT IN (?, ?)",
    array($id_ruangan, $id_paket, STATUS_ORDER_DIBATALKAN, STATUS_ORDER_SELESAI)
);
if ($q_booked === false) {
    die("Error query Booked: " . print_r(sqlsrv_errors(), true));
}

$booked_ids = [];
while ($b = sqlsrv_fetch_array($q_booked, SQLSRV_FETCH_ASSOC)) {
    $booked_ids[] = (int)$b['ID_Jadwal'];
}

// =====================================================
// PROSES DATA JADWAL
// =====================================================
$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$jadwal_per_hari = [];

while ($j = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
    // Format tanggal
    $tgl_obj = $j['Tanggal_Jadwal'];
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

    // Format jam
    $jam_mulai_obj = $j['Jam_Mulai'];
    if (is_object($jam_mulai_obj) && method_exists($jam_mulai_obj, 'format')) {
        $jam_mulai_str = $jam_mulai_obj->format('H:i');
    } else {
        $jam_mulai_str = substr($jam_mulai_obj, 0, 5);
    }

    $jam_selesai_obj = $j['Jam_Selesai'];
    if (is_object($jam_selesai_obj) && method_exists($jam_selesai_obj, 'format')) {
        $jam_selesai_str = $jam_selesai_obj->format('H:i');
    } else {
        $jam_selesai_str = substr($jam_selesai_obj, 0, 5);
    }

    // CEK VALIDASI EXPIRED AKURAT: Tanggal kemarin atau jam saat ini sudah lewat
    $is_expired = false;
    if ($tgl_str < $today) {
        $is_expired = true;
    } elseif ($tgl_str == $today) {
        $slot_time = strtotime($tgl_str . ' ' . $jam_mulai_str . ':00');
        $current_time = time();
        if ($slot_time < $current_time) {
            $is_expired = true;
        }
    }

    // Cek apakah slot sudah dibooking (berdasarkan ID_Jadwal)
    $is_booked = in_array((int)$j['ID_Jadwal'], $booked_ids);

    // Cek apakah jadwal maintenance/libur dari master
    $is_maintenance = ($j['Status_Jadwal'] == STATUS_JADWAL_MAINTENANCE);
    $is_libur = (stripos($j['Keterangan'] ?? '', 'libur') !== false);

    // Status akhir: tersedia / booked / expired / libur / maintenance
    if ($is_expired) {
        $status = 'expired';
    } elseif ($is_booked || $j['Status_Jadwal'] == STATUS_JADWAL_BOOKED) {
        $status = 'booked';
    } elseif ($is_libur || $is_maintenance) {
        $status = 'libur';
    } else {
        $status = 'tersedia';
    }

    // Group by tanggal
    if (!isset($jadwal_per_hari[$tgl_str])) {
        $jadwal_per_hari[$tgl_str] = [
            'tanggal' => $tgl_str,
            'hari' => $hari_indo[$hari_idx],
            'tgl_format' => $tgl_num . ' ' . $bulan_indo[$bln_idx] . ' ' . $thn,
            'is_today' => ($tgl_str == $today),
            'slots' => []
        ];
    }

    $jadwal_per_hari[$tgl_str]['slots'][] = [
        'id_jadwal' => (int)$j['ID_Jadwal'],
        'jam_mulai' => $jam_mulai_str,
        'jam_selesai' => $jam_selesai_str,
        'keterangan' => $j['Keterangan'] ?? '',
        'status' => $status
    ];
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

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');

// =====================================================
// RECALCULATE BELANJAAN BARANG CETAK DARI SESI (REALTIME SIDEBAR)
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
                        <div class="extra-goods-item" style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                            <span>' . htmlspecialchars($item['Nama_Barang']) . ' (x' . $qty . ')</span>
                            <span style="color: var(--text-dark); font-weight: 700;">Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
                        </div>
                    ';
                }
            }
        }
    }
}

$total_biaya_akhir = $d_paket['Harga_Paket'] + $total_cetak_harga;
$total_biaya_akhir_format = number_format($total_biaya_akhir, 0, ',', '.');

// Hitung jumlah slot tersedia hari ini
$total_tersedia = 0;
foreach ($jadwal_per_hari as $hari) {
    foreach ($hari['slots'] as $slot) {
        if ($slot['status'] == 'tersedia') {
            $total_tersedia++;
        }
    }
}

// Data untuk JS (escape dengan json_encode)
$ruangan_nama_js = htmlspecialchars($d_ruangan['Nama_Ruangan'], ENT_QUOTES, 'UTF-8');
$tema_nama_js = htmlspecialchars($d_tema['Nama_Tema'], ENT_QUOTES, 'UTF-8');
$paket_nama_js = htmlspecialchars($d_paket['Nama_Paket'], ENT_QUOTES, 'UTF-8');
$durasi_js = (int)$d_paket['Durasi_Waktu'];

// Navigation dates
$prev_date = clone $date_start;
$prev_date->modify('-7 days');
$next_date = clone $date_start;
$next_date->modify('+7 days');

// Generate 7 day tabs
$date_tabs = [];
$temp_date = clone $date_start;
for ($i = 0; $i < 7; $i++) {
    $ts = strtotime($temp_date->format('Y-m-d'));
    $date_tabs[] = [
        'date' => $temp_date->format('Y-m-d'),
        'hari' => $hari_indo[date('w', $ts)],
        'tgl' => date('d', $ts),
        'bln' => $bulan_indo[(int)date('n', $ts) - 1],
        'is_today' => ($temp_date->format('Y-m-d') == $today),
        'is_selected' => ($temp_date->format('Y-m-d') == $selected_date)
    ];
    $temp_date->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Jadwal - SpotLight Studio</title>
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
        .nav-avatar-wrapper {
            position: relative;
        }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: all 0.3s;
        }
        .nav-avatar:hover {
            transform: scale(1.1);
            border-color: var(--p-pink);
        }
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
        .nav-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
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
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 0;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
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
        .breadcrumb-inner .separator {
            color: #cbd5e1;
        }
        .breadcrumb-inner .current {
            color: var(--p-pink);
            font-weight: 700;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
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
            background: #059669;
            border-color: #059669;
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
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 60px;
            height: 3px;
            background: #e2e8f0;
            margin: 0 10px;
            margin-bottom: 24px;
        }
        .progress-line.completed { background: #059669; }

        /* ===== DATE NAVIGATION ===== */
        .date-nav-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 30px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .date-nav-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .date-nav-title i { color: var(--p-pink); font-size: 1.3rem; }
        .date-tabs-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .date-tabs-wrapper::-webkit-scrollbar { display: none; }
        .date-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 12px 20px;
            border-radius: 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.3s;
            min-width: 80px;
            cursor: pointer;
        }
        .date-tab:hover {
            border-color: var(--light-pink);
            background: var(--s-pink);
        }
        .date-tab.active {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .date-tab:hover .tab-hari,
        .date-tab:hover .tab-tgl,
        .date-tab:hover .tab-bln { color: var(--p-pink); }
        .date-tab.active .tab-hari,
        .date-tab.active .tab-tgl,
        .date-tab.active .tab-bln { color: #ffffff !important; }
        
        .date-tab .tab-hari {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .date-tab .tab-tgl {
            font-size: 1.2rem;
            font-weight: 800;
        }
        .date-tab .tab-bln {
            font-size: 0.7rem;
            font-weight: 600;
            opacity: 0.8;
        }
        .date-tab.today {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .date-tab.today.active {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
        }
        .date-nav-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a5568;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            flex-shrink: 0;
        }
        .date-nav-btn:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .date-nav-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ===== JADWAL SECTION ===== */
        .jadwal-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .jadwal-main {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
        }
        .jadwal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .jadwal-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .jadwal-title i { color: var(--p-pink); }
        .jadwal-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .jadwal-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--s-pink);
            color: var(--p-pink);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .jadwal-badge i { font-size: 1.1rem; }

        /* ===== TANGGAL SECTION ===== */
        .tanggal-section {
            margin-bottom: 32px;
        }
        .tanggal-section:last-child { margin-bottom: 0; }
        .tanggal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .tanggal-hari {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .tanggal-tanggal {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .tanggal-today {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .tanggal-libur {
            background: linear-gradient(135deg, #ea580c, #d97706);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        /* ===== SLOT GRID ===== */
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .slot-jam {
            padding: 16px 12px;
            border-radius: 16px;
            text-align: center;
            transition: var(--transition-3d);
            border: 2px solid;
            cursor: pointer;
        }
        .slot-jam .slot-durasi {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .slot-jam .slot-waktu {
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        .slot-jam .slot-status {
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Slot Tersedia */
        .slot-jam.tersedia {
            background: #ffffff;
            border-color: var(--light-pink);
            color: var(--text-dark);
        }
        .slot-jam.tersedia:hover {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink);
            color: #ffffff;
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.2);
        }
        .slot-jam.tersedia:hover .slot-durasi,
        .slot-jam.tersedia:hover .slot-status {
            color: rgba(255,255,255,0.9);
        }
        .slot-jam.tersedia .slot-durasi { color: var(--p-pink); }
        .slot-jam.tersedia .slot-waktu { color: var(--text-dark); }
        .slot-jam.tersedia:hover .slot-waktu { color: #ffffff; }
        .slot-jam.tersedia .slot-status { color: var(--p-pink); }
        .slot-jam.tersedia:hover .slot-status { color: #ffffff; }

        /* Slot Booked */
        .slot-jam.booked {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .slot-jam.booked .slot-durasi { color: #cbd5e1; }
        .slot-jam.booked .slot-waktu { 
            color: #94a3b8; 
            text-decoration: line-through;
        }
        .slot-jam.booked .slot-status { 
            color: #94a3b8; 
            text-transform: uppercase;
        }

        /* Slot Libur */
        .slot-jam.libur {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #ea580c;
            cursor: not-allowed;
            opacity: 0.8;
        }
        .slot-jam.libur .slot-durasi { color: #f59e0b; }
        .slot-jam.libur .slot-waktu { 
            color: #ea580c; 
            text-decoration: line-through;
        }
        .slot-jam.libur .slot-status { 
            color: #ea580c; 
            text-transform: uppercase;
            font-weight: 800;
        }

        /* ===== LEGEND ===== */
        .slot-legend {
            display: flex;
            gap: 24px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid;
        }
        .legend-box.tersedia { 
            background: #ffffff; 
            border-color: var(--light-pink); 
        }
        .legend-box.booked { 
            background: #f8fafc; 
            border-color: #e2e8f0; 
        }
        .legend-box.libur { 
            background: #fff7ed; 
            border-color: #fed7aa; 
        }

        /* ===== EMPTY STATE ===== */
        .empty-jadwal {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-jadwal i {
            font-size: 4rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-jadwal h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-jadwal p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            gap: 16px;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--light-pink);
            border-top-color: var(--p-pink);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .loading-text {
            font-size: 1rem;
            font-weight: 700;
            color: var(--p-pink);
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== EXTRA BELANJAAN BARANG CETAK ===== */
        .extra-goods-list {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
        }
        .extra-goods-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .extra-goods-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .extra-goods-item span:last-child {
            color: var(--text-dark);
            font-weight: 700;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .jadwal-section { grid-template-columns: 1fr; }
            .booking-summary { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
            .slot-grid { grid-template-columns: repeat(3, 1fr); }
            .date-nav-container { padding: 20px; }
            .date-tab { min-width: 70px; padding: 10px 14px; }
        }
        @media (max-width: 576px) {
            .slot-grid { grid-template-columns: repeat(2, 1fr); }
            .date-tabs-wrapper { gap: 6px; }
            .date-tab { min-width: 60px; padding: 8px 10px; }
            .date-tab .tab-tgl { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <!-- LOADING OVERLAY -->
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
            <a href="../Barang_Cetak/pilih_barang_cetak.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_barang_cetak['Nama_Barang_Cetak']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pilih Jadwal</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR SINKRON (Langkah 5 Active, 1 s.d 4 Completed) -->
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
            <div class="progress-step active">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Pilih Jadwal</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">6</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">7</div>
                <div class="progress-step-label">Bayar DP</div>
            </div>
        </div>

        <!-- DATE NAVIGATION -->
        <div class="date-nav-container">
            <div class="date-nav-title">
                <i class="bi bi-calendar-week-fill"></i>
                Pilih Tanggal
            </div>
            <a href="?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&tanggal=<?= $prev_date->format('Y-m-d') ?>" 
               class="date-nav-btn <?= ($prev_date->format('Y-m-d') < $today) ? 'disabled' : '' ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="date-tabs-wrapper">
                <?php foreach ($date_tabs as $tab): ?>
                    <a href="?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&tanggal=<?= $tab['date'] ?>" 
                       class="date-tab <?= $tab['is_selected'] ? 'active' : '' ?> <?= $tab['is_today'] ? 'today' : '' ?>">
                        <span class="tab-hari"><?= $tab['hari'] ?></span>
                        <span class="tab-tgl"><?= $tab['tgl'] ?></span>
                        <span class="tab-bln"><?= $tab['bln'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&tanggal=<?= $next_date->format('Y-m-d') ?>" 
               class="date-nav-btn">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <!-- JADWAL SECTION + SIDEBAR -->
        <div class="jadwal-section">
            <!-- Left: Grid Slot Jam -->
            <div class="jadwal-main">
                <div class="jadwal-header">
                    <div>
                        <div class="jadwal-title">
                            <i class="bi bi-calendar-week-fill"></i>
                            Pilih Jadwal Sesi Foto
                        </div>
                        <div class="jadwal-subtitle"><?= $total_tersedia ?> jadwal tersedia untuk <?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                    </div>
                    <div class="jadwal-badge">
                        <i class="bi bi-clock-fill"></i>
                        <?= (int)$d_paket['Durasi_Waktu'] ?> Menit / Sesi
                    </div>
                </div>

                <?php if (empty($jadwal_per_hari)): ?>
                    <div class="empty-jadwal">
                        <i class="bi bi-calendar-x"></i>
                        <h3>Tidak Ada Jadwal Tersedia</h3>
                        <p>Maaf, belum ada jadwal yang tersedia untuk ruangan dan paket ini.<br>Silakan hubungi admin untuk informasi lebih lanjut.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($jadwal_per_hari as $hari_data): 
                        // Filter: hanya tampilkan slot yang tidak expired (hari lewat / jam lewat otomatis tersembunyi)
                        $visible_slots = array_filter($hari_data['slots'], function($s) {
                            return $s['status'] != 'expired';
                        });
                        if (empty($visible_slots)) continue;

                        // Cek apakah semua slot libur
                        $all_libur = true;
                        foreach ($visible_slots as $slot) {
                            if ($slot['status'] != 'libur') {
                                $all_libur = false;
                                break;
                            }
                        }
                    ?>
                        <div class="tanggal-section">
                            <div class="tanggal-header">
                                <span class="tanggal-hari"><?= $hari_data['hari'] ?></span>
                                <span class="tanggal-tanggal"><?= $hari_data['tgl_format'] ?></span>
                                <?php if ($hari_data['is_today']): ?>
                                    <span class="tanggal-today">Hari Ini</span>
                                <?php endif; ?>
                                <?php if ($all_libur): ?>
                                    <span class="tanggal-libur">Libur</span>
                                <?php endif; ?>
                            </div>
                            <div class="slot-grid">
                                <?php foreach ($visible_slots as $slot): 
                                    if ($slot['status'] == 'booked'):
                                ?>
                                    <div class="slot-jam booked">
                                        <div class="slot-durasi"><?= (int)$d_paket['Durasi_Waktu'] ?> Menit</div>
                                        <div class="slot-waktu"><?= htmlspecialchars($slot['jam_mulai']) ?> - <?= htmlspecialchars($slot['jam_selesai']) ?></div>
                                        <div class="slot-status">Booked</div>
                                    </div>
                                <?php elseif ($slot['status'] == 'libur'): ?>
                                    <div class="slot-jam libur">
                                        <div class="slot-durasi"><?= (int)$d_paket['Durasi_Waktu'] ?> Menit</div>
                                        <div class="slot-waktu"><?= htmlspecialchars($slot['jam_mulai']) ?> - <?= htmlspecialchars($slot['jam_selesai']) ?></div>
                                        <div class="slot-status">Libur</div>
                                    </div>
                                <?php else: 
                                    // Build data-attributes for JS (safe, no inline onclick)
                                    $slot_data = json_encode([
                                        'id' => $slot['id_jadwal'],
                                        'tanggal' => $hari_data['tanggal'],
                                        'jam_mulai' => $slot['jam_mulai'],
                                        'jam_selesai' => $slot['jam_selesai'],
                                        'hari' => $hari_data['hari'],
                                        'tgl_format' => $hari_data['tgl_format']
                                    ], JSON_UNESCAPED_UNICODE);
                                ?>
                                    <div class="slot-jam tersedia" data-slot='<?= $slot_data ?>'>
                                        <div class="slot-durasi"><?= (int)$d_paket['Durasi_Waktu'] ?> Menit</div>
                                        <div class="slot-waktu"><?= htmlspecialchars($slot['jam_mulai']) ?> - <?= htmlspecialchars($slot['jam_selesai']) ?></div>
                                        <div class="slot-status">Rp <?= $harga_format ?></div>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- LEGEND -->
                    <div class="slot-legend">
                        <div class="legend-item">
                            <div class="legend-box tersedia"></div>
                            <span>Tersedia</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box booked"></div>
                            <span>Sudah Dibooking</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-box libur"></div>
                            <span>Libur</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Sidebar Ringkasan SINKRON -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title">Ringkasan Booking</div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Paket</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_paket['Nama_Paket']) ?> (<?= (int)$d_paket['Durasi_Waktu'] ?> menit)</div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Ruangan</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></div>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Tema</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></div>
                        </div>
                    </div>
                    
                    <!-- BARANG CETAK BADGE SINKRON SUNTUK DETAIL -->
                    <div class="summary-item" id="summaryCetakItem">
                        <div class="summary-icon" id="summaryCetakIcon"><i class="bi bi-printer"></i></div>
                        <div>
                            <div class="summary-text">Barang Cetak</div>
                            <div class="summary-sub" id="summaryCetakSub">Belum dipilih</div>
                        </div>                    
                    </div>
                    
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-calendar"></i></div>
                        <div>
                            <div class="summary-text">Jadwal</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>

                    <!-- LIST PRODUK CETAK JIKA DIPILIH -->
                    <div class="extra-goods-list d-none" id="extraGoodsContainer">
                        <div class="extra-goods-title"><i class="bi bi-printer-fill me-1"></i>Ekstra Cetak:</div>
                        <div id="extraGoodsItems"><?= $extra_cetak_html ?></div>
                    </div>

                    <div class="summary-harga">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-size:0.95rem;color:var(--text-muted);font-weight:700;">Total Harga:</span>
                            <span id="totalHargaLabel">Rp <?= $total_biaya_akhir_format ?></span>
                        </div>
                    </div>
                </div>
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
        const totalHargaAkhirFormat = <?= json_encode(number_format($total_biaya_akhir, 0, ',', '.')) ?>;
        const idPaket = <?= json_encode((int)$id_paket) ?>;
        const idRuangan = <?= json_encode((int)$id_ruangan) ?>;
        const idTema = <?= json_encode((int)$id_tema) ?>;

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('show');
        }

        // Fungsi show loading
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('show');
        }

        document.querySelectorAll('.slot-jam.tersedia').forEach(function(el) {
            el.addEventListener('click', function() {
                const slotData = this.getAttribute('data-slot');
                let slot = null;

                try {
                    slot = JSON.parse(slotData);
                } catch (e) {
                    console.error('Error parsing slot data:', e);
                    hideLoading();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Data jadwal tidak valid. Silakan refresh halaman.',
                        confirmButtonColor: '#d83f67'
                    });
                    return;
                }

                if (!slot || !slot.id) {
                    hideLoading();
                    return;
                }

                pilihJadwal(slot.id, slot.tanggal, slot.jam_mulai, slot.jam_selesai, slot.hari, slot.tgl_format);
            });
        });

        function pilihJadwal(idJadwal, tanggal, jamMulai, jamSelesai, hari, tglFormat) {
            Swal.fire({
                title: 'Konfirmasi Jadwal',
                html: '<div style="text-align:left">' +
                      '<p><strong>Hari:</strong> ' + hari + ', ' + tglFormat + '</p>' +
                      '<p><strong>Jam:</strong> ' + jamMulai + ' - ' + jamSelesai + ' WIB</p>' +
                      '<p><strong>Ruangan:</strong> ' + ruanganNama + '</p>' +
                      '<p><strong>Tema:</strong> ' + temaNama + '</p>' +
                      '<p><strong>Paket:</strong> ' + paketNama + ' (' + durasi + ' menit)</p>' +
                      '<hr style="margin:12px 0;border-color:#f1f5f9">' +
                      '<p style="font-size:1.1rem;font-weight:800;color:#d83f67">Total Tagihan: Rp ' + totalHargaAkhirFormat + '</p>' +
                      '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pilih Jadwal Ini',
                cancelButtonText: 'Batal',
                allowOutsideClick: true,
                allowEscapeKey: true,
                showCloseButton: true
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    window.location.href = '../Konfirmasi/konfirmasi.php?id_paket=' + idPaket + 
                                          '&id_ruangan=' + idRuangan + 
                                          '&id_tema=' + idTema + 
                                          '&id_jadwal=' + idJadwal;
                }
            }).catch((error) => {
                console.error('Swal error:', error);
                hideLoading();
            });
        }

        window.addEventListener('load', function() {
            hideLoading();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoading();
            }
        });

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById('profilePreview').src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        // =====================================================
        // SINKRONISASI BADGE BARANG CETAK PADA LOAD JADWAL
        // =====================================================
        document.addEventListener('DOMContentLoaded', function() {
            const totalCetak = <?= (int)$total_cetak_harga ?>;
            const summaryIcon = document.getElementById('summaryCetakIcon');
            const summarySub = document.getElementById('summaryCetakSub');
            const extraContainer = document.getElementById('extraGoodsContainer');

            if (totalCetak > 0) {
                if (summaryIcon) {
                    summaryIcon.classList.add('completed');
                    summaryIcon.innerHTML = '<i class="bi bi-check-lg"></i>';
                }
                if (summarySub) {
                    summarySub.innerText = 'Ditambahkan';
                }
                if (extraContainer) {
                    extraContainer.classList.remove('d-none');
                }
            } else {
                if (summaryIcon) {
                    summaryIcon.classList.remove('completed');
                    summaryIcon.innerHTML = '<i class="bi bi-printer"></i>';
                }
                if (summarySub) {
                    summarySub.innerText = 'Dilewati';
                }
                if (extraContainer) {
                    extraContainer.classList.add('d-none');
                }
            }
        });
    </script>
</body>
</html>