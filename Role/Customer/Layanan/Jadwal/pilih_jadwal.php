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
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);

if (!$d_paket) {
    header("Location: ../../index.php?error=paket_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA RUANGAN
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT ID_Ruangan, Nama_Ruangan, Kapasitas_Ruangan, Deskripsi, Foto_Ruangan 
     FROM Ruangan 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    array($id_ruangan)
);
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);

if (!$d_ruangan) {
    header("Location: ../Paket/detail_paket.php?id_paket=$id_paket&error=ruangan_tidak_ditemukan");
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
$d_validasi = sqlsrv_fetch_array($q_validasi, SQLSRV_FETCH_ASSOC);

if ($d_validasi['total'] == 0) {
    header("Location: ../Paket/detail_paket.php?id_paket=$id_paket&error=ruangan_tidak_valid");
    exit();
}

// =====================================================
// VALIDASI: TEMA HARUS TERHUBUNG DENGAN RUANGAN
// =====================================================
$q_validasi_tema = sqlsrv_query($conn, 
    "SELECT COUNT(*) as total FROM Ruangan_Tema WHERE ID_Ruangan = ? AND ID_Tema = ?", 
    array($id_ruangan, $id_tema)
);
$d_validasi_tema = sqlsrv_fetch_array($q_validasi_tema, SQLSRV_FETCH_ASSOC);

if ($d_validasi_tema['total'] == 0) {
    header("Location: ../Tema/pilih_tema.php?id_paket=$id_paket&id_ruangan=$id_ruangan&error=tema_tidak_valid");
    exit();
}

// =====================================================
// GENERATE JADWAL SLOT (7 HARI KE DEPAN, JAM 08:00-20:00)
// =====================================================
$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$today = new DateTime();
$slots_per_hari = [];

// Ambil semua jadwal yang sudah dibooking untuk ruangan ini (7 hari ke depan)
$seven_days_later = (new DateTime())->modify('+6 days')->format('Y-m-d');
$today_str = $today->format('Y-m-d');

$q_booked = sqlsrv_query($conn, 
    "SELECT 
        CAST(j.Tanggal_Jadwal AS DATE) as tanggal,
        j.Jam_Mulai,
        j.Jam_Selesai
     FROM [Order] o
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE o.ID_Ruangan = ? 
       AND o.Status = 1 
       AND o.Status_Order NOT IN (4)
       AND j.Tanggal_Jadwal BETWEEN ? AND ?
       AND j.Is_Deleted = 0",
    array($id_ruangan, $today_str, $seven_days_later)
);

$booked_slots = [];
while ($b = sqlsrv_fetch_array($q_booked, SQLSRV_FETCH_ASSOC)) {
    $tgl = $b['tanggal']->format('Y-m-d');
    $jam_mulai = $b['Jam_Mulai']->format('H:i');
    $booked_slots[$tgl][$jam_mulai] = true;
}

// Generate slot untuk 7 hari ke depan
for ($i = 0; $i < 7; $i++) {
    $tanggal = (new DateTime())->modify("+$i days");
    $tgl_str = $tanggal->format('Y-m-d');
    $hari_nama = $hari_indo[$tanggal->format('w')];
    $tgl_format = $tanggal->format('d') . ' ' . $bulan_indo[(int)$tanggal->format('n') - 1] . ' ' . $tanggal->format('Y');
    $is_today = ($i == 0);

    $slots = [];
    for ($jam = 8; $jam < 20; $jam++) {
        $jam_mulai = sprintf("%02d:00", $jam);
        $jam_selesai = sprintf("%02d:00", $jam + 1);

        // Cek apakah slot sudah lewat (hari ini + jam lewat)
        $slot_datetime = strtotime($tgl_str . ' ' . $jam_mulai);
        $is_expired = $slot_datetime < time();

        // Cek apakah slot sudah booked
        $is_booked = isset($booked_slots[$tgl_str][$jam_mulai]);

        $slots[] = [
            'jam_mulai' => $jam_mulai,
            'jam_selesai' => $jam_selesai,
            'is_booked' => $is_booked,
            'is_expired' => $is_expired,
            'is_available' => !$is_booked && !$is_expired
        ];
    }

    $slots_per_hari[] = [
        'tanggal' => $tgl_str,
        'hari' => $hari_nama,
        'tgl_format' => $tgl_format,
        'is_today' => $is_today,
        'slots' => $slots
    ];
}

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

$harga_format = number_format($d_paket['Harga_Paket'], 0, ',', '.');
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS (SAMA PERSIS) ===== */
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

        /* ===== RINGKASAN BOOKING (STICKY SIDEBAR) ===== */
        .booking-summary {
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .summary-item:last-child { border-bottom: none; }
        .summary-icon {
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
        .summary-icon.completed { background: #d1fae5; color: #059669; }
        .summary-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .summary-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .summary-harga {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #f1f5f9;
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
            transition: all 0.3s;
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

        /* Slot Expired */
        .slot-jam.expired {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .slot-jam.expired .slot-durasi { color: #cbd5e1; }
        .slot-jam.expired .slot-waktu { 
            color: #94a3b8; 
            text-decoration: line-through;
        }
        .slot-jam.expired .slot-status { color: #94a3b8; }

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
        .legend-box.expired { 
            background: #f8fafc; 
            border-color: #e2e8f0; 
            opacity: 0.6;
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
        }
        @media (max-width: 576px) {
            .slot-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS (SAMA PERSIS) -->
    <nav class="top-navbar">
        <a href="../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Paket/detail_paket.php" class="nav-link-item active">Booking Baru</a>
            <a href="../../Booking/Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="../Paket/detail_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="location.href='#'">
        </div>
    </nav>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <div class="breadcrumb-inner">
            <a href="../../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Paket/detail_paket.php?id_paket=<?= $id_paket ?>"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="../Tema/pilih_tema.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>"><?= htmlspecialchars($d_tema['Nama_Tema']) ?></a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pilih Jadwal</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PROGRESS BAR -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Pilih Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Detail Paket</div>
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
            <div class="progress-step active">
                <div class="progress-step-circle">5</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
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
                        <div class="jadwal-subtitle">Jam operasional: 08:00 - 20:00 WIB</div>
                    </div>
                    <div class="jadwal-badge">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>
                    </div>
                </div>

                <?php foreach ($slots_per_hari as $hari_data): ?>
                    <div class="tanggal-section">
                        <div class="tanggal-header">
                            <span class="tanggal-hari"><?= $hari_data['hari'] ?></span>
                            <span class="tanggal-tanggal"><?= $hari_data['tgl_format'] ?></span>
                            <?php if ($hari_data['is_today']): ?>
                                <span class="tanggal-today">Hari Ini</span>
                            <?php endif; ?>
                        </div>
                        <div class="slot-grid">
                            <?php foreach ($hari_data['slots'] as $slot): 
                                if ($slot['is_booked']):
                                    $class = 'slot-jam booked';
                                    $status_text = 'Booked';
                                    $onclick = '';
                                elseif ($slot['is_expired']):
                                    $class = 'slot-jam expired';
                                    $status_text = 'Lewat';
                                    $onclick = '';
                                else:
                                    $class = 'slot-jam tersedia';
                                    $status_text = 'Rp ' . $harga_format;
                                    $onclick = 'onclick="pilihJadwal(\'' . $hari_data['tanggal'] . '\', \'' . $slot['jam_mulai'] . '\', \'' . $slot['jam_selesai'] . '\', \'' . $hari_data['hari'] . '\', \'' . $hari_data['tgl_format'] . '\')"';
                                endif;
                            ?>
                                <div class="<?= $class ?>" <?= $onclick ?>>
                                    <div class="slot-durasi"><?= $d_paket['Durasi_Waktu'] ?> Menit</div>
                                    <div class="slot-waktu"><?= $slot['jam_mulai'] ?> - <?= $slot['jam_selesai'] ?></div>
                                    <div class="slot-status"><?= $status_text ?></div>
                                </div>
                            <?php endforeach; ?>
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
                        <div class="legend-box expired"></div>
                        <span>Sudah Lewat</span>
                    </div>
                </div>
            </div>

            <!-- Right: Sidebar Ringkasan -->
            <div class="booking-summary">
                <div class="summary-card">
                    <div class="summary-title">Ringkasan Booking</div>
                    <div class="summary-item">
                        <div class="summary-icon completed"><i class="bi bi-check-lg"></i></div>
                        <div>
                            <div class="summary-text">Paket</div>
                            <div class="summary-sub"><?= htmlspecialchars($d_paket['Nama_Paket']) ?></div>
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
                    <div class="summary-item">
                        <div class="summary-icon"><i class="bi bi-calendar"></i></div>
                        <div>
                            <div class="summary-text">Jadwal</div>
                            <div class="summary-sub">Belum dipilih</div>
                        </div>
                    </div>
                    <div class="summary-harga">Rp <?= $harga_format ?></div>
                </div>
            </div>
        </div>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function pilihJadwal(tanggal, jamMulai, jamSelesai, hari, tglFormat) {
            Swal.fire({
                title: 'Konfirmasi Jadwal',
                html: '<div style="text-align:left">' +
                      '<p><strong>Hari:</strong> ' + hari + ', ' + tglFormat + '</p>' +
                      '<p><strong>Jam:</strong> ' + jamMulai + ' - ' + jamSelesai + '</p>' +
                      '<p><strong>Ruangan:</strong> <?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>\</p>' +
                      '<p><strong>Tema:</strong> <?= htmlspecialchars($d_tema['Nama_Tema']) ?>\</p>' +
                      '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pilih Jadwal Ini',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim ke proses_order.php
                    window.location.href = 'proses_order.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=<?= $id_tema ?>&tanggal=' + tanggal + '&jam_mulai=' + jamMulai + '&jam_selesai=' + jamSelesai;
                }
            });
        }
    </script>
</body>
</html>