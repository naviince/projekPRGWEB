<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil ---
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
if ($q_profile === false) {
    die("Error query Profil: " . print_r(sqlsrv_errors(), true));
}
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// AMBIL ID PAKET DARI URL (WAJIB ADA)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket'])) {
    header("Location: ../../index.php?error=pilih_paket_dulu");
    exit();
}

$id_paket = (int)$_GET['id_paket'];

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

// Path foto paket
$foto_paket = ($d_paket['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../../../assets/img/paket/" . $d_paket['Foto_Paket'])) 
    ? "../../../../assets/img/paket/" . $d_paket['Foto_Paket'] 
    : "../../../../assets/img/paket/default_paket.jpg";

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');

// =====================================================
// AMBIL RUANGAN YANG TERHUBUNG DENGAN PAKET INI
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT r.ID_Ruangan, r.Nama_Ruangan, r.Deskripsi, r.Foto_Ruangan
     FROM Ruangan r
     INNER JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
     WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0
     ORDER BY r.Nama_Ruangan ASC", 
    array($id_paket)
);
if ($q_ruangan === false) {
    die("Error query Ruangan: " . print_r(sqlsrv_errors(), true));
}

$ruangan_list = [];
while ($row = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)) {
    $ruangan_list[] = $row;
}

// =====================================================
// AMBIL PROPERTI UNTUK SETIAP RUANGAN (langsung via Properti.ID_Ruangan)
// =====================================================
$properti_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_properti = sqlsrv_query($conn, 
        "SELECT Nama_Properti, Kategori_Properti 
         FROM Properti 
         WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0
         ORDER BY Kategori_Properti, Nama_Properti",
        array($id_ruangan)
    );
    if ($q_properti === false) {
        die("Error query Properti: " . print_r(sqlsrv_errors(), true));
    }
    $properti_list = [];
    while ($prop = sqlsrv_fetch_array($q_properti, SQLSRV_FETCH_ASSOC)) {
        $properti_list[] = $prop;
    }
    $properti_map[$id_ruangan] = $properti_list;
}

// =====================================================
// AMBIL TEMA FOTO YANG TERHUBUNG DENGAN SETIAP RUANGAN (via Ruangan_Tema)
// =====================================================
$tema_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_tema = sqlsrv_query($conn, 
        "SELECT t.ID_Tema, t.Nama_Tema, t.Kategori_Tema, t.Foto_Tema
         FROM Tema_Foto t
         INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
         WHERE rt.ID_Ruangan = ? AND t.Status = 1 AND t.Is_Deleted = 0
         ORDER BY t.Nama_Tema",
        array($id_ruangan)
    );
    if ($q_tema === false) {
        die("Error query Tema: " . print_r(sqlsrv_errors(), true));
    }
    $tema_list = [];
    while ($tema = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
        $tema_list[] = $tema;
    }
    $tema_map[$id_ruangan] = $tema_list;
}

// =====================================================
// AMBIL JADWAL TERSEDIA HARI INI & BESOK UNTUK SETIAP RUANGAN
// Status_Jadwal = 0 = Tersedia
// =====================================================
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$jadwal_map = [];
foreach ($ruangan_list as $ruangan) {
    $id_ruangan = $ruangan['ID_Ruangan'];
    $q_jadwal = sqlsrv_query($conn, 
        "SELECT TOP 3 j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
         FROM Jadwal_Studio j
         WHERE j.ID_Ruangan = ? AND j.ID_Paket = ? 
           AND j.Tanggal_Jadwal IN (?, ?)
           AND j.Status_Jadwal = ? AND j.Status = ? AND j.Is_Deleted = 0
         ORDER BY j.Tanggal_Jadwal, j.Jam_Mulai",
        array($id_ruangan, $id_paket, $today, $tomorrow, STATUS_JADWAL_TERSEDIA, STATUS_DATA_AKTIF)
    );
    if ($q_jadwal === false) {
        die("Error query Jadwal: " . print_r(sqlsrv_errors(), true));
    }
    $jadwal_list = [];
    while ($jadwal = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
        $jam_mulai = $jadwal['Jam_Mulai'];
        $jam_selesai = $jadwal['Jam_Selesai'];
        $tanggal = $jadwal['Tanggal_Jadwal'];

        if (is_object($jam_mulai) && method_exists($jam_mulai, 'format')) {
            $jam_mulai_str = $jam_mulai->format('H:i');
        } else {
            $jam_mulai_str = is_string($jam_mulai) ? substr($jam_mulai, 0, 5) : '-';
        }
        if (is_object($jam_selesai) && method_exists($jam_selesai, 'format')) {
            $jam_selesai_str = $jam_selesai->format('H:i');
        } else {
            $jam_selesai_str = is_string($jam_selesai) ? substr($jam_selesai, 0, 5) : '-';
        }
        if (is_object($tanggal) && method_exists($tanggal, 'format')) {
            $tgl_str = $tanggal->format('d M');
        } else {
            $tgl_str = $tanggal;
        }

        $jadwal['Jam_Mulai_Str'] = $jam_mulai_str;
        $jadwal['Jam_Selesai_Str'] = $jam_selesai_str;
        $jadwal['Tanggal_Str'] = $tgl_str;
        $jadwal_list[] = $jadwal;
    }
    $jadwal_map[$id_ruangan] = $jadwal_list;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($d_paket['Nama_Paket']) ?> - SpotLight Studio</title>
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

        /* ===== PROGRESS BAR ===== */
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

        /* ===== DETAIL SECTION ===== */
        .detail-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .detail-left {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .detail-foto {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .detail-body { padding: 30px; }
        .detail-badge {
            display: inline-block;
            padding: 6px 16px;
            background: var(--s-pink);
            color: var(--p-pink);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .detail-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 12px;
        }
        .detail-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .detail-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .detail-meta-item i { color: var(--p-pink); font-size: 1.1rem; }
        .detail-section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 30px 0 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-section-title i { color: var(--p-pink); }
        .detail-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.8;
        }
        .detail-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .detail-info-list li {
            padding: 14px 0;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 600;
        }
        .detail-info-list li:last-child { border-bottom: none; }
        .detail-info-list li i {
            color: var(--p-pink);
            font-size: 1.2rem;
            width: 24px;
        }
        .detail-info-list li span {
            color: var(--text-muted);
            font-weight: 500;
            margin-left: auto;
        }

        /* ===== SIDEBAR HARGA ===== */
        .detail-sidebar {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .price-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .price-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .price-value {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-bottom: 4px;
        }
        .price-unit {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 24px;
        }
        .btn-cek {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-cek:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(216, 63, 103, 0.3);
            color: #ffffff;
        }
        .benefit-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
        }
        .benefit-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }
        .benefit-item:last-child { margin-bottom: 0; }
        .benefit-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .benefit-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.5;
        }

        /* ===== RUANGAN SECTION ===== */
        .ruangan-section {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            margin-bottom: 40px;
        }
        .ruangan-section-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ruangan-section-title i { color: var(--p-pink); }
        .ruangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        .ruangan-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .ruangan-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .ruangan-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
        }
        .ruangan-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        .ruangan-card:hover .ruangan-img { transform: scale(1.1); }
        .ruangan-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 3rem;
        }
        .ruangan-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ruangan-body { padding: 20px; }
        .ruangan-nama {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .ruangan-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== PROPERTI & TEMA TAGS ===== */
        .ruangan-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .tag-properti {
            background: #f0fdf4;
            color: #059669;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-tema {
            background: #eff6ff;
            color: #2563eb;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-jadwal {
            background: #fefce8;
            color: #ca8a04;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-more {
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .ruangan-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .ruangan-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 8px;
        }
        .ruangan-meta-item i { color: var(--p-pink); }
        .ruangan-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .ruangan-harga-wrapper { display: flex; flex-direction: column; }
        .ruangan-harga {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .ruangan-harga-satuan {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .ruangan-btn {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ruangan-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .detail-section { grid-template-columns: 1fr; }
            .detail-sidebar { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .ruangan-grid { grid-template-columns: 1fr; }
            .progress-line { width: 30px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-link-item active">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="pilih_paket.php?id_paket=<?= $id_paket ?>" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
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

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../../index.php">Paket Foto</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR -->
        <div class="progress-container">
            <div class="progress-step active">
                <div class="progress-step-circle">1</div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">2</div>
                <div class="progress-step-label">Pilih Ruangan</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Pilih Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Pilih Jadwal</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Konfirmasi</div>
            </div>
        </div>

        <!-- DETAIL SECTION -->
        <div class="detail-section">
            <!-- Left: Info -->
            <div class="detail-left">
                <img src="<?= $foto_paket ?>" class="detail-foto" alt="<?= htmlspecialchars($d_paket['Nama_Paket']) ?>">
                <div class="detail-body">
                    <div class="detail-badge">Paket Foto</div>
                    <h1 class="detail-title"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></h1>
                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <i class="bi bi-star-fill"></i>
                            <span>4.8</span> (120 review)
                        </div>
                        <div class="detail-meta-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>SpotLight Studio, Cikarang</span>
                        </div>
                        <div class="detail-meta-item">
                            <i class="bi bi-camera-fill"></i>
                            <span>Studio Foto Profesional</span>
                        </div>
                    </div>

                    <h2 class="detail-section-title"><i class="bi bi-file-text-fill"></i> Deskripsi</h2>
                    <p class="detail-desc">
                        <?= htmlspecialchars($d_paket['Deskripsi'] ?? 'Paket foto ' . $d_paket['Nama_Paket'] . ' dengan kualitas terbaik untuk kenangan Anda. Dilengkapi dengan fotografer profesional, peralatan studio lengkap, dan hasil foto berkualitas tinggi.') ?>
                    </p>

                    <h2 class="detail-section-title"><i class="bi bi-info-circle-fill"></i> Informasi Paket</h2>
                    <ul class="detail-info-list">
                        <li><i class="bi bi-stopwatch"></i> Durasi Sesi <span><?= $d_paket['Durasi_Waktu'] ?> menit</span></li>
                        <li><i class="bi bi-people-fill"></i> Kapasitas <span><?= $d_paket['Kapasitas_Orang'] ?> orang</span></li>
                        <li><i class="bi bi-door-open-fill"></i> Ruangan Tersedia <span><?= count($ruangan_list) ?> studio</span></li>
                        <li><i class="bi bi-image-fill"></i> File Hasil <span>Softcopy + Edit</span></li>
                        <li><i class="bi bi-cash-stack"></i> Pembayaran <span>DP 65% + Pelunasan</span></li>
                    </ul>
                </div>
            </div>

            <!-- Right: Sidebar (STICKY) -->
            <div class="detail-sidebar">
                <div class="price-card">
                    <div class="price-label">Mulai dari</div>
                    <div class="price-value">Rp <?= $harga_format ?></div>
                    <div class="price-unit">Per Sesi</div>
                    <a href="#ruangan-section" class="btn-cek" onclick="event.preventDefault(); document.querySelector('#ruangan-section').scrollIntoView({ behavior: 'smooth' });">
                        <i class="bi bi-calendar-check-fill"></i>
                        Lihat Ruangan Tersedia
                    </a>
                </div>
                <div class="benefit-card">
                    <div class="benefit-title">Booking di SpotLight lebih untung!</div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-credit-card"></i></div>
                        <div class="benefit-text">Pembayaran DP hanya 65%</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="benefit-text">Reschedule jadwal H-3</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-gift"></i></div>
                        <div class="benefit-text">Promo & voucher menarik</div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="benefit-text">Jadwal terjamin & tidak bentrok</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RUANGAN SECTION -->
        <div class="ruangan-section" id="ruangan-section">
            <div class="ruangan-section-title">
                <i class="bi bi-door-open-fill"></i>
                Pilih Ruangan untuk Paket <?= htmlspecialchars($d_paket['Nama_Paket']) ?>
            </div>
            <div class="ruangan-grid">
                <?php foreach ($ruangan_list as $ruangan): 
                    $id_ruangan = $ruangan['ID_Ruangan'];
                    $foto_ruangan = ($ruangan['Foto_Ruangan'] != 'default_ruangan.jpg' && file_exists("../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'])) 
                        ? "../../../../assets/img/ruangan/" . $ruangan['Foto_Ruangan'] 
                        : null;

                    $properti_list = $properti_map[$id_ruangan] ?? [];
                    $tema_list = $tema_map[$id_ruangan] ?? [];
                    $jadwal_list = $jadwal_map[$id_ruangan] ?? [];
                ?>
                    <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>" class="ruangan-card">
                        <div class="ruangan-img-wrapper">
                            <?php if ($foto_ruangan): ?>
                                <img src="<?= $foto_ruangan ?>" class="ruangan-img" alt="<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>">
                            <?php else: ?>
                                <div class="ruangan-img-placeholder"><i class="bi bi-door-open-fill"></i></div>
                            <?php endif; ?>
                            <div class="ruangan-badge"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                        </div>
                        <div class="ruangan-body">
                            <div class="ruangan-nama"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div>
                            <div class="ruangan-desc"><?= htmlspecialchars($ruangan['Deskripsi'] ?? 'Studio dengan fasilitas lengkap untuk sesi foto terbaik.') ?></div>

                            <!-- Properti Tags -->
                            <?php if (!empty($properti_list)): ?>
                            <div class="ruangan-tags">
                                <?php 
                                $count = 0;
                                foreach ($properti_list as $prop): 
                                    if ($count++ >= 3) break;
                                ?>
                                    <span class="tag-properti"><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($prop['Nama_Properti']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($properti_list) > 3): ?>
                                    <span class="tag-more">+<?= count($properti_list) - 3 ?> properti</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Tema Tags -->
                            <?php if (!empty($tema_list)): ?>
                            <div class="ruangan-tags">
                                <?php 
                                $count = 0;
                                foreach ($tema_list as $tema): 
                                    if ($count++ >= 2) break;
                                ?>
                                    <span class="tag-tema"><i class="bi bi-palette me-1"></i><?= htmlspecialchars($tema['Nama_Tema']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($tema_list) > 2): ?>
                                    <span class="tag-more">+<?= count($tema_list) - 2 ?> tema</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Jadwal Tags -->
                            <?php if (!empty($jadwal_list)): ?>
                            <div class="ruangan-tags">
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <span class="tag-jadwal"><i class="bi bi-clock me-1"></i><?= $jadwal['Tanggal_Str'] ?> <?= $jadwal['Jam_Mulai_Str'] ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="ruangan-meta">
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-people"></i> Max <?= $d_paket['Kapasitas_Orang'] ?> orang
                                </div>
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-box-seam"></i> <?= count($properti_list) ?> properti
                                </div>
                                <div class="ruangan-meta-item">
                                    <i class="bi bi-palette"></i> <?= count($tema_list) ?> tema
                                </div>
                            </div>
                            <div class="ruangan-footer">
                                <div class="ruangan-harga-wrapper">
                                    <div class="ruangan-harga">Rp <?= $harga_format ?></div>
                                    <div class="ruangan-harga-satuan">/ sesi</div>
                                </div>
                                <span class="ruangan-btn">Pilih <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($ruangan_list)): ?>
                    <div class="text-center py-5" style="grid-column: 1 / -1;">
                        <i class="bi bi-inbox fs-1 mb-3" style="color: #cbd5e1;"></i>
                        <p class="text-muted">Tidak ada ruangan tersedia untuk paket ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
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
    </script>
</body>
</html>