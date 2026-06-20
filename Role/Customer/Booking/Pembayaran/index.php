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
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL ID_ORDER DARI URL ATAU SESSION
// =====================================================
$id_order = isset($_GET['id_order']) ? (int)$_GET['id_order'] : (int)($_SESSION['order_id'] ?? 0);

if ($id_order <= 0) {
    header("Location: ../../../../index.php?error=order_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA ORDER
// =====================================================
// HANYA kolom yang ADA di tabel Order:
// ID_Order, ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, ID_Jadwal, Total_Harga, Status_Order, Status
$q_order = sqlsrv_query($conn, 
    "SELECT 
        o.ID_Order, o.Status_Order,
        p.ID_Paket, p.Nama_Paket, p.Durasi_Waktu, p.Harga_Paket, p.Foto_Paket,
        r.ID_Ruangan, r.Nama_Ruangan, r.Foto_Ruangan,
        t.ID_Tema, t.Nama_Tema, t.Kategori_Tema,
        j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
     FROM [Order] o
     INNER JOIN Paket_Foto p ON o.ID_Paket = p.ID_Paket
     INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
     INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
     INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
     WHERE o.ID_Order = ? AND o.ID_Pelanggan = ? AND o.Status = 1",
    array($id_order, $id_customer)
);

if ($q_order === false) {
    die('Error query order: ' . print_r(sqlsrv_errors(), true));
}

$d_order = sqlsrv_fetch_array($q_order, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_order);

if (!$d_order) {
    header("Location: ../../index.php?error=order_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);

if ($q_profile === false) {
    die('Error query profile: ' . print_r(sqlsrv_errors(), true));
}

$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_profile);

$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// Format data
$harga_paket = $d_order['Harga_Paket'];
$dp_amount = $harga_paket * 0.65;
$sisa_amount = $harga_paket - $dp_amount;

$harga_format = number_format($harga_paket, 0, ',', '.');
$dp_format = number_format($dp_amount, 0, ',', '.');
$sisa_format = number_format($sisa_amount, 0, ',', '.');

$tanggal_jadwal = $d_order['Tanggal_Jadwal'];
if ($tanggal_jadwal instanceof DateTime) {
    $tanggal_jadwal = $tanggal_jadwal->format('Y-m-d');
} else {
    $tanggal_jadwal = (string)$tanggal_jadwal;
}

$jam_mulai = $d_order['Jam_Mulai'];
if ($jam_mulai instanceof DateTime) {
    $jam_mulai = $jam_mulai->format('H:i');
} else {
    $jam_mulai = (string)$jam_mulai;
}

$jam_selesai = $d_order['Jam_Selesai'];
if ($jam_selesai instanceof DateTime) {
    $jam_selesai = $jam_selesai->format('H:i');
} else {
    $jam_selesai = (string)$jam_selesai;
}

$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$dt = new DateTime($tanggal_jadwal);
$hari_nama = $hari_indo[$dt->format('w')];
$tgl_format = $dt->format('d') . ' ' . $bulan_indo[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');

$status_text = '';
$status_class = '';
$status_icon = '';

switch ($d_order['Status_Order']) {
    case STATUS_ORDER_MENUNGGU_DP:
        $status_text = 'Menunggu Pembayaran DP';
        $status_class = 'status-menunggu';
        $status_icon = 'bi-hourglass-split';
        break;
    case STATUS_ORDER_DP_TERVERIFIKASI:
        $status_text = 'DP Terverifikasi';
        $status_class = 'status-dp';
        $status_icon = 'bi-check-circle-fill';
        break;
    case STATUS_ORDER_LUNAS:
        $status_text = 'Lunas';
        $status_class = 'status-lunas';
        $status_icon = 'bi-check-circle-fill';
        break;
    case STATUS_ORDER_DIBATALKAN:
        $status_text = 'Dibatalkan';
        $status_class = 'status-batal';
        $status_icon = 'bi-x-circle-fill';
        break;
    default:
        $status_text = 'Menunggu';
        $status_class = 'status-menunggu';
        $status_icon = 'bi-hourglass-split';
}

// Countdown pakai waktu sekarang + 24 jam (karena tidak ada Created_At)
$deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
$deadline_epoch = strtotime($deadline) * 1000;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran DP - SpotLight Studio</title>
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
        .breadcrumb-inner .separator { color: #cbd5e1; }
        .breadcrumb-inner .current { color: var(--p-pink); font-weight: 700; }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== STATUS BADGE ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.9rem;
        }
        .status-menunggu {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fef3c7;
        }
        .status-dp {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-lunas {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .status-batal {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* ===== ORDER DETAIL CARD ===== */
        .order-section {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 40px;
            margin-bottom: 40px;
        }
        .order-main {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .order-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .order-title i { color: var(--p-pink); }
        .order-nomor {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 4px;
        }

        /* ===== DETAIL ITEMS ===== */
        .detail-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .detail-item:last-child { border-bottom: none; }
        .detail-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .detail-content {
            flex: 1;
        }
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .detail-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== PAYMENT CARD ===== */
        .payment-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .payment-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 0.95rem;
        }
        .payment-label {
            color: var(--text-muted);
            font-weight: 600;
        }
        .payment-value {
            color: var(--text-dark);
            font-weight: 700;
        }
        .payment-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 12px 0;
        }
        .payment-total {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .payment-dp {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--p-pink);
        }
        .payment-sisa {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* ===== COUNTDOWN TIMER ===== */
        .countdown-box {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
        }
        .countdown-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #d97706;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .countdown-time {
            font-size: 1.8rem;
            font-weight: 900;
            color: #d97706;
            font-family: 'Courier New', monospace;
        }
        .countdown-expired {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-color: #fca5a5;
        }
        .countdown-expired .countdown-label,
        .countdown-expired .countdown-time {
            color: #dc2626;
        }

        /* ===== TOMBOL BAYAR ===== */
        .btn-bayar {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 16px;
            font-weight: 800;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 16px;
        }
        .btn-bayar:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(216, 63, 103, 0.3);
            color: #ffffff;
        }
        .btn-bayar:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-kembali {
            width: 100%;
            padding: 14px;
            background: #f8fafc;
            color: var(--text-muted);
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-top: 12px;
        }
        .btn-kembali:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        /* ===== METODE PEMBAYARAN ===== */
        .payment-methods {
            margin-top: 20px;
        }
        .payment-method-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .payment-method-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .payment-method-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method-item:hover, .payment-method-item.active {
            border-color: var(--p-pink);
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .payment-method-item i { font-size: 1.2rem; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .order-section { grid-template-columns: 1fr; }
            .payment-card { position: static; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .breadcrumb-bar { padding: 16px 20px; }
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
            <a href="../../Layanan/Paket/detail_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="../../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
        </div>
        <div class="nav-right">
            <a href="../../Layanan/Paket/detail_paket.php" class="nav-btn-booking">
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
            <a href="../../Layanan/Paket/detail_paket.php?id_paket=<?= $d_order['ID_Paket'] ?>">Booking</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pembayaran DP</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <div class="order-section">
            <!-- Left: Detail Order -->
            <div class="order-main">
                <div class="order-header">
                    <div>
                        <div class="order-title">
                            <i class="bi bi-receipt-fill"></i>
                            Detail Order
                        </div>
                        <div class="order-nomor">Nomor Order: #<?= $d_order['ID_Order'] ?></div>
                    </div>
                    <div class="status-badge <?= $status_class ?>">
                        <i class="bi <?= $status_icon ?>"></i>
                        <?= $status_text ?>
                    </div>
                </div>

                <!-- Detail Items -->
                <div class="detail-item">
                    <div class="detail-icon"><i class="bi bi-camera-fill"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Paket Foto</div>
                        <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Paket']) ?></div>
                        <div class="detail-sub"><?= $d_order['Durasi_Waktu'] ?> menit</div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="bi bi-door-open-fill"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Ruangan Studio</div>
                        <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Ruangan']) ?></div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="bi bi-palette-fill"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Tema Foto</div>
                        <div class="detail-value"><?= htmlspecialchars($d_order['Nama_Tema']) ?></div>
                        <div class="detail-sub"><?= htmlspecialchars($d_order['Kategori_Tema'] ?? 'Umum') ?></div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="bi bi-calendar-event-fill"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Tanggal & Jam</div>
                        <div class="detail-value"><?= $hari_nama ?>, <?= $tgl_format ?></div>
                        <div class="detail-sub"><?= $jam_mulai ?> - <?= $jam_selesai ?> WIB</div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Total Harga</div>
                        <div class="detail-value" style="color: var(--p-pink); font-size: 1.2rem;">Rp <?= $harga_format ?></div>
                    </div>
                </div>
            </div>

            <!-- Right: Payment Card -->
            <div class="payment-card">
                <div class="payment-title">Ringkasan Pembayaran</div>

                <!-- Countdown Timer -->
                <?php if ($d_order['Status_Order'] == STATUS_ORDER_MENUNGGU_DP): ?>
                <div class="countdown-box" id="countdownBox">
                    <div class="countdown-label"><i class="bi bi-clock-fill me-1"></i> Bayar DP Sebelum</div>
                    <div class="countdown-time" id="countdownTimer">--:--:--</div>
                </div>
                <?php endif; ?>

                <!-- Rincian Harga -->
                <div class="payment-row">
                    <span class="payment-label">Total Harga</span>
                    <span class="payment-value">Rp <?= $harga_format ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">DP (65%)</span>
                    <span class="payment-value payment-dp">Rp <?= $dp_format ?></span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Sisa Pelunasan</span>
                    <span class="payment-value payment-sisa">Rp <?= $sisa_format ?></span>
                </div>
                <div class="payment-divider"></div>
                <div class="payment-row">
                    <span class="payment-label">Yang Harus Dibayar Sekarang</span>
                    <span class="payment-value payment-total">Rp <?= $dp_format ?></span>
                </div>

                <!-- Metode Pembayaran -->
                <?php if ($d_order['Status_Order'] == STATUS_ORDER_MENUNGGU_DP): ?>
                <div class="payment-methods">
                    <div class="payment-method-title">Metode Pembayaran</div>
                    <div class="payment-method-list">
                        <div class="payment-method-item active" onclick="selectMethod(this)">
                            <i class="bi bi-bank"></i> Transfer Bank
                        </div>
                        <div class="payment-method-item" onclick="selectMethod(this)">
                            <i class="bi bi-wallet"></i> E-Wallet
                        </div>
                        <div class="payment-method-item" onclick="selectMethod(this)">
                            <i class="bi bi-upc-scan"></i> QRIS
                        </div>
                    </div>
                </div>

                <!-- Tombol Bayar -->
                <button class="btn-bayar" onclick="bayarDP()">
                    <i class="bi bi-credit-card-fill"></i>
                    Bayar DP Sekarang
                </button>
                <a href="../../index.php" class="btn-kembali">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Dashboard
                </a>
                <?php else: ?>
                <div class="alert alert-success" style="border-radius: 14px; margin-top: 16px;">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Pembayaran sudah selesai!</strong>
                </div>
                <a href="../../index.php" class="btn-kembali">
                    <i class="bi bi-house-fill"></i>
                    Kembali ke Dashboard
                </a>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script src="../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown Timer
        const deadline = <?= $deadline_epoch ?>;
        const countdownBox = document.getElementById('countdownBox');
        const countdownTimer = document.getElementById('countdownTimer');

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadline - now;

            if (distance <= 0) {
                if (countdownBox) {
                    countdownBox.classList.add('countdown-expired');
                    countdownBox.querySelector('.countdown-label').innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Waktu Habis';
                }
                if (countdownTimer) countdownTimer.innerHTML = '00:00:00';

                const btnBayar = document.querySelector('.btn-bayar');
                if (btnBayar) {
                    btnBayar.disabled = true;
                    btnBayar.innerHTML = '<i class="bi bi-lock-fill"></i> Waktu Pembayaran Habis';
                }
                return;
            }

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (countdownTimer) {
                countdownTimer.innerHTML = 
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
            }
        }

        if (countdownTimer) {
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        function selectMethod(el) {
            document.querySelectorAll('.payment-method-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');
        }

        function bayarDP() {
            Swal.fire({
                title: 'Konfirmasi Pembayaran',
                text: 'Anda akan membayar DP sebesar Rp <?= $dp_format ?>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Bayar Sekarang',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'proses_pembayaran.php?id_order=<?= $id_order ?>';
                }
            });
        }
    </script>
</body>
</html>