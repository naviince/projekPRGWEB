<?php
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_DATA_AKTIF', 1);
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_LUNAS', 3);

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil Customer ---
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$q_profile = sqlsrv_query($conn,
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?",
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_customer))
    ? "../../assets/img/pelanggan/" . $foto_customer
    : $default_svg_avatar;

// --- Ambil semua sesi foto milik customer ini ---
$sql_sesi = "SELECT 
                s.ID_Sesi_Foto, s.Status_Sesi, s.File_Hasil, 
                s.Waktu_Mulai, s.Waktu_Selesai, s.Tanggal_Upload_Hasil,
                o.ID_Order, o.Status_Order,
                pkt.Nama_Paket,
                r.Nama_Ruangan,
                k.Nama_Karyawan
             FROM Sesi_Foto s
             JOIN [Order] o       ON s.ID_Order     = o.ID_Order
             JOIN Paket_Foto pkt  ON o.ID_Paket     = pkt.ID_Paket
             JOIN Ruangan r       ON o.ID_Ruangan   = r.ID_Ruangan
             JOIN Karyawan k      ON s.ID_Karyawan  = k.ID_Karyawan
             WHERE o.ID_Pelanggan = ? AND s.Status = 1
             ORDER BY s.Waktu_Mulai DESC";
$q_sesi = sqlsrv_query($conn, $sql_sesi, array($id_customer));

// --- Statistik ---
$q_stats = sqlsrv_query($conn,
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.Status_Sesi = 0 THEN 1 ELSE 0 END) as terjadwal,
        SUM(CASE WHEN s.Status_Sesi = 1 THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN s.Status_Sesi = 2 THEN 1 ELSE 0 END) as selesai
     FROM Sesi_Foto s
     JOIN [Order] o ON s.ID_Order = o.ID_Order
     WHERE o.ID_Pelanggan = ? AND s.Status = 1",
    array($id_customer)
);
$stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

// --- Fetch semua data ke array (karena sqlsrv tidak bisa di-rewind) ---
$sesi_list = [];
while ($row = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC)) {
    $sesi_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Foto Saya – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #d83f67; --d-pink: #c73165; --s-pink: #fff5f6;
            --light-pink: #ffe4e9; --text-dark: #1e1e24; --text-muted: #718096;
            --body-bg: #f8fafc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); }

        /* NAVBAR */
        .top-navbar { background: #fff; padding: 16px 40px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 20px rgba(0,0,0,0.06); }
        .nav-logo { font-weight: 900; font-size: 1.8rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1.5px; }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center { display: flex; gap: 32px; align-items: center; }
        .nav-link-item { color: #4a5568; text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: all 0.3s; padding: 8px 0; position: relative; }
        .nav-link-item:hover, .nav-link-item.active { color: var(--p-pink); }
        .nav-link-item.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: var(--p-pink); border-radius: 3px; }
        .nav-right { display: flex; align-items: center; gap: 16px; }
        .nav-btn-booking { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; padding: 10px 24px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 15px rgba(216,63,103,0.25); }
        .nav-btn-booking:hover { transform: translateY(-2px); color: #fff; }
        .nav-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--light-pink); cursor: pointer; transition: 0.3s; }
        .nav-avatar:hover { transform: scale(1.1); border-color: var(--p-pink); }

        /* PAGE HEADER */
        .page-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); padding: 50px 40px; position: relative; overflow: hidden; }
        .page-header::before { content: ''; position: absolute; top: -40%; right: -10%; width: 400px; height: 400px; border-radius: 50%; background: rgba(255,255,255,0.06); }
        .page-header-content { position: relative; z-index: 1; }
        .page-header h1 { font-size: 2rem; font-weight: 900; color: #fff; letter-spacing: -0.5px; }
        .page-header p { color: rgba(255,255,255,0.8); font-weight: 500; margin-top: 6px; }
        .breadcrumb-nav { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
        .breadcrumb-nav a { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .breadcrumb-nav a:hover { color: #fff; }
        .breadcrumb-nav span { color: rgba(255,255,255,0.5); font-size: 0.85rem; }

        /* MAIN CONTAINER */
        .main-container { padding: 40px; max-width: 1200px; margin: 0 auto; }

        /* STATS ROW */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-chip { background: #fff; border-radius: 18px; border: 1px solid #f1f5f9; padding: 20px 22px; display: flex; align-items: center; gap: 14px; transition: all 0.3s; }
        .stat-chip:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(216,63,103,0.08); border-color: var(--light-pink); }
        .stat-chip-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .icon-pink   { background: var(--s-pink);  color: var(--p-pink); }
        .icon-blue   { background: #eff6ff;          color: #3b82f6; }
        .icon-yellow { background: #fffbeb;          color: #d97706; }
        .icon-green  { background: #ecfdf5;          color: #059669; }
        .stat-chip-label { font-size: 0.72rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-chip-val { font-size: 1.4rem; font-weight: 800; color: var(--text-dark); line-height: 1; }

        /* FILTER TABS */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-tab { background: #fff; border: 2px solid #e2e8f0; color: #718096; padding: 9px 22px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.3s; }
        .filter-tab:hover, .filter-tab.active { background: var(--p-pink); border-color: var(--p-pink); color: #fff; }

        /* SESI CARDS */
        .sesi-grid { display: grid; gap: 20px; }
        .sesi-card { background: #fff; border-radius: 20px; border: 1px solid #f1f5f9; overflow: hidden; transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .sesi-card:hover { transform: translateY(-4px); box-shadow: 0 16px 35px rgba(216,63,103,0.09); border-color: var(--light-pink); }

        .sesi-card-header { padding: 20px 24px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #f8fafc; }
        .sesi-card-left { display: flex; align-items: flex-start; gap: 16px; }
        .sesi-card-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }

        .sesi-order-id { font-weight: 800; font-size: 1rem; color: var(--text-dark); }
        .sesi-paket { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-top: 2px; }

        /* STATUS BADGE */
        .status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 800; padding: 6px 16px; border-radius: 50px; }
        .pill-terjadwal { background: #eff6ff; color: #3b82f6; }
        .pill-proses    { background: #fffbeb; color: #d97706; }
        .pill-selesai   { background: #ecfdf5; color: #059669; }

        /* SESI BODY */
        .sesi-card-body { padding: 20px 24px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .sesi-info-group label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); display: block; margin-bottom: 4px; }
        .sesi-info-group span { font-size: 0.88rem; font-weight: 700; color: var(--text-dark); }

        /* SESI FOOTER */
        .sesi-card-footer { padding: 16px 24px; background: #fafafa; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }

        /* PROGRESS BAR */
        .progress-track { display: flex; align-items: center; gap: 0; }
        .progress-step { display: flex; flex-direction: column; align-items: center; }
        .progress-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; transition: 0.3s; }
        .progress-dot.done  { background: var(--p-pink); color: #fff; }
        .progress-dot.active { background: #d97706; color: #fff; animation: pulse 1.5s infinite; }
        .progress-dot.idle  { background: #e2e8f0; color: #94a3b8; }
        .progress-label { font-size: 0.62rem; font-weight: 700; margin-top: 4px; color: var(--text-muted); }
        .progress-line { width: 50px; height: 3px; margin: 0 4px; margin-bottom: 20px; border-radius: 2px; transition: 0.3s; }
        .progress-line.done  { background: var(--p-pink); }
        .progress-line.idle  { background: #e2e8f0; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(217,119,6,0.4); } 50% { box-shadow: 0 0 0 6px rgba(217,119,6,0); } }

        /* DOWNLOAD BUTTON */
        .btn-download { background: linear-gradient(135deg, #059669, #047857); color: #fff; padding: 10px 24px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(5,150,105,0.25); }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5,150,105,0.35); color: #fff; }
        .btn-pending { background: #f1f5f9; color: #94a3b8; padding: 10px 24px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; cursor: not-allowed; display: inline-flex; align-items: center; gap: 8px; }

        /* EMPTY STATE */
        .empty-section { background: #fff; border-radius: 20px; border: 1px solid #f1f5f9; padding: 80px 40px; text-align: center; }
        .empty-section i { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 16px; }
        .empty-section h4 { font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .empty-section p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
        .btn-booking { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; padding: 13px 32px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-booking:hover { transform: translateY(-2px); color: #fff; }

        @media (max-width: 992px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .sesi-card-body { grid-template-columns: 1fr; }
            .top-navbar { padding: 16px 20px; }
            .main-container { padding: 20px; }
            .page-header { padding: 30px 20px; }
            .nav-menu-center { display: none; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="top-navbar">
    <a href="../../Role/Customer/index.php" class="nav-logo">
        SpotLight.<span>StudioFoto</span>
    </a>
    <div class="nav-menu-center">
        <a href="../../Role/Customer/index.php" class="nav-link-item">Dashboard</a>
        <a href="../../Role/Customer/Layanan/Paket/detail_paket.php" class="nav-link-item">Booking Baru</a>
        <a href="#" class="nav-link-item active">Sesi Foto</a>
        <a href="../../Role/Customer/Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
    </div>
    <div class="nav-right">
        <a href="../../Role/Customer/Layanan/Paket/detail_paket.php" class="nav-btn-booking">
            <i class="bi bi-plus-lg me-1"></i> Booking
        </a>
        <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil"
             title="<?= htmlspecialchars($nama_customer) ?>">
    </div>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="page-header-content">
        <div class="breadcrumb-nav">
            <a href="../../Role/Customer/index.php"><i class="bi bi-house-fill me-1"></i> Dashboard</a>
            <span>/</span>
            <span style="color:rgba(255,255,255,0.85);">Sesi Foto Saya</span>
        </div>
        <h1>📸 Sesi Foto Saya</h1>
        <p>Pantau status pemotretan dan unduh hasil foto Anda di sini.</p>
    </div>
</div>

<!-- MAIN -->
<main class="main-container">

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-chip">
            <div class="stat-chip-icon icon-pink"><i class="bi bi-camera-fill"></i></div>
            <div>
                <div class="stat-chip-label">Total Sesi</div>
                <div class="stat-chip-val"><?= $stats['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-chip-icon icon-blue"><i class="bi bi-calendar-event-fill"></i></div>
            <div>
                <div class="stat-chip-label">Terjadwal</div>
                <div class="stat-chip-val" style="color:#3b82f6;"><?= $stats['terjadwal'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-chip-icon icon-yellow"><i class="bi bi-camera-reels-fill"></i></div>
            <div>
                <div class="stat-chip-label">Sedang Proses</div>
                <div class="stat-chip-val" style="color:#d97706;"><?= $stats['proses'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-chip">
            <div class="stat-chip-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-chip-label">Selesai</div>
                <div class="stat-chip-val" style="color:#059669;"><?= $stats['selesai'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">Semua</button>
        <button class="filter-tab" data-filter="0">Terjadwal</button>
        <button class="filter-tab" data-filter="1">Proses</button>
        <button class="filter-tab" data-filter="2">Selesai</button>
    </div>

    <!-- SESI LIST -->
    <?php if (empty($sesi_list)): ?>
    <div class="empty-section">
        <i class="bi bi-camera-video-off d-block"></i>
        <h4>Belum Ada Sesi Foto</h4>
        <p>Anda belum memiliki sesi foto yang terdaftar.<br>Lakukan booking terlebih dahulu untuk mendapatkan jadwal sesi foto.</p>
        <a href="../../Role/Customer/Layanan/Paket/detail_paket.php" class="btn-booking">
            <i class="bi bi-calendar-plus-fill"></i> Booking Sekarang
        </a>
    </div>
    <?php else: ?>

    <div class="sesi-grid" id="sesiGrid">
        <?php foreach ($sesi_list as $row):
            // Format waktu
            $waktu_mulai = $row['Waktu_Mulai'] instanceof DateTime
                ? $row['Waktu_Mulai']->format('d M Y, H:i')
                : ($row['Waktu_Mulai'] ? date('d M Y, H:i', strtotime($row['Waktu_Mulai'])) : '-');

            $waktu_selesai = $row['Waktu_Selesai'] instanceof DateTime
                ? $row['Waktu_Selesai']->format('d M Y, H:i')
                : '-';

            $tgl_upload = $row['Tanggal_Upload_Hasil'] instanceof DateTime
                ? $row['Tanggal_Upload_Hasil']->format('d M Y, H:i')
                : '-';

            $status = intval($row['Status_Sesi']);

            // Card color per status
            $card_icon_bg = [
                0 => 'background:#eff6ff; color:#3b82f6;',
                1 => 'background:#fffbeb; color:#d97706;',
                2 => 'background:#ecfdf5; color:#059669;',
            ][$status] ?? '';

            $pill_class = ['pill-terjadwal','pill-proses','pill-selesai'][$status] ?? '';
            $pill_text  = ['Terjadwal','Sedang Proses','Selesai'][$status] ?? '';
            $pill_icon  = ['bi-calendar-event','bi-camera-reels','bi-check-circle'][$status] ?? '';

            // Progress steps: 0=Terjadwal, 1=Proses, 2=Selesai
            // dot classes: done, active, idle
            $steps = [
                0 => ['Terjadwal', 'Proses', 'Selesai'],
            ];
            $dot = ['idle','idle','idle'];
            $line = ['idle','idle'];
            if ($status == 0) { $dot[0] = 'active'; }
            if ($status == 1) { $dot[0] = 'done'; $dot[1] = 'active'; $line[0] = 'done'; }
            if ($status == 2) { $dot[0] = 'done'; $dot[1] = 'done'; $dot[2] = 'done'; $line[0] = 'done'; $line[1] = 'done'; }
        ?>
        <div class="sesi-card" data-status="<?= $status ?>">
            <!-- CARD HEADER -->
            <div class="sesi-card-header">
                <div class="sesi-card-left">
                    <div class="sesi-card-icon" style="<?= $card_icon_bg ?>">
                        <i class="bi <?= $pill_icon ?>"></i>
                    </div>
                    <div>
                        <div class="sesi-order-id">Booking #<?= $row['ID_Order'] ?></div>
                        <div class="sesi-paket">
                            <i class="bi bi-camera-fill me-1" style="color:var(--p-pink);"></i>
                            <?= htmlspecialchars($row['Nama_Paket']) ?>
                            &nbsp;·&nbsp;
                            <i class="bi bi-door-open me-1"></i>
                            <?= htmlspecialchars($row['Nama_Ruangan']) ?>
                        </div>
                    </div>
                </div>
                <span class="status-pill <?= $pill_class ?>">
                    <i class="bi <?= $pill_icon ?>"></i>
                    <?= $pill_text ?>
                </span>
            </div>

            <!-- CARD BODY -->
            <div class="sesi-card-body">
                <div class="sesi-info-group">
                    <label><i class="bi bi-calendar3 me-1"></i> Waktu Mulai</label>
                    <span><?= $waktu_mulai ?></span>
                </div>
                <div class="sesi-info-group">
                    <label><i class="bi bi-person-badge me-1"></i> Fotografer</label>
                    <span><?= htmlspecialchars($row['Nama_Karyawan']) ?></span>
                </div>
                <div class="sesi-info-group">
                    <label><i class="bi bi-cloud-check me-1"></i> File Tersedia</label>
                    <span>
                        <?php if (!empty($row['File_Hasil'])): ?>
                            <span style="color:#059669; font-weight:800;">
                                <i class="bi bi-check-circle-fill me-1"></i> Ya
                            </span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">
                                <i class="bi bi-hourglass-split me-1"></i> Belum tersedia
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- CARD FOOTER -->
            <div class="sesi-card-footer">
                <!-- PROGRESS TRACKER -->
                <div class="progress-track">
                    <div class="progress-step">
                        <div class="progress-dot <?= $dot[0] ?>">
                            <?= $dot[0] === 'done' ? '<i class="bi bi-check2"></i>' : '1' ?>
                        </div>
                        <div class="progress-label">Terjadwal</div>
                    </div>
                    <div class="progress-line <?= $line[0] ?>"></div>
                    <div class="progress-step">
                        <div class="progress-dot <?= $dot[1] ?>">
                            <?= $dot[1] === 'done' ? '<i class="bi bi-check2"></i>' : '2' ?>
                        </div>
                        <div class="progress-label">Proses</div>
                    </div>
                    <div class="progress-line <?= $line[1] ?>"></div>
                    <div class="progress-step">
                        <div class="progress-dot <?= $dot[2] ?>">
                            <?= $dot[2] === 'done' ? '<i class="bi bi-check2"></i>' : '3' ?>
                        </div>
                        <div class="progress-label">Selesai</div>
                    </div>
                </div>

                <!-- DOWNLOAD / STATUS -->
                <?php if ($status == 2 && !empty($row['File_Hasil'])): ?>
                    <a href="../../assets/img/hasil_foto/<?= htmlspecialchars($row['File_Hasil']) ?>"
                       download class="btn-download"
                       onclick="return confirmDownload(event, this.href)">
                        <i class="bi bi-cloud-arrow-down-fill"></i>
                        Unduh Hasil Foto
                    </a>
                <?php elseif ($status == 2 && empty($row['File_Hasil'])): ?>
                    <span class="btn-pending">
                        <i class="bi bi-hourglass-split"></i>
                        Menunggu Upload Admin
                    </span>
                <?php elseif ($status == 1): ?>
                    <span class="btn-pending">
                        <i class="bi bi-camera-reels"></i>
                        Sedang Diproses
                    </span>
                <?php else: ?>
                    <span class="btn-pending">
                        <i class="bi bi-calendar-event"></i>
                        Menunggu Jadwal
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</main>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// Filter tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        document.querySelectorAll('.sesi-card').forEach(card => {
            if (filter === 'all' || card.dataset.status === filter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// Confirm download
function confirmDownload(e, href) {
    e.preventDefault();
    Swal.fire({
        title: 'Unduh Hasil Foto?',
        text: 'File hasil foto Anda akan diunduh.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#718096',
        confirmButtonText: '<i class="bi bi-cloud-arrow-down-fill me-1"></i> Unduh Sekarang',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) window.location.href = href;
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
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) window.location.href = '../../logout.php';
    });
}
</script>
</body>
</html>