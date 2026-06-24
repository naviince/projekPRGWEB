<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// --- Pesan Notifikasi ---
$msg = $_GET['msg'] ?? '';
$alert_map = [
    'success_add'    => ['type' => 'success', 'text' => 'Sesi foto berhasil ditambahkan!'],
    'success_edit'   => ['type' => 'success', 'text' => 'Sesi foto berhasil diperbarui!'],
    'success_delete' => ['type' => 'success', 'text' => 'Sesi foto berhasil dihapus.'],
    'error_duplikat' => ['type' => 'error',   'text' => 'Order ini sudah memiliki sesi foto aktif!'],
    'error_format'   => ['type' => 'error',   'text' => 'Format file tidak diizinkan. Gunakan ZIP, JPG, PNG, atau PDF.'],
    'error_ukuran'   => ['type' => 'error',   'text' => 'Ukuran file terlalu besar. Maksimal 50MB.'],
    'error_upload'   => ['type' => 'error',   'text' => 'Gagal mengunggah file. Coba lagi.'],
    'error_db'       => ['type' => 'error',   'text' => 'Terjadi kesalahan pada database. Coba lagi.'],
    'error_validasi' => ['type' => 'error',   'text' => 'Data tidak lengkap. Periksa kembali form.'],
];

// --- Pencarian ---
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$where_extra = '';
$params = [];

if (!empty($cari)) {
    $where_extra .= " AND (pl.Nama_Pelanggan LIKE ? OR CAST(o.ID_Order AS VARCHAR) LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($filter_status !== '') {
    $where_extra .= " AND s.Status_Sesi = ?";
    $params[] = intval($filter_status);
}

// --- Statistik ---
$q_stats = sqlsrv_query($conn,
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.Status_Sesi = 0 THEN 1 ELSE 0 END) as terjadwal,
        SUM(CASE WHEN s.Status_Sesi = 1 THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN s.Status_Sesi = 2 THEN 1 ELSE 0 END) as selesai
     FROM Sesi_Foto s
     JOIN [Order] o ON s.ID_Order = o.ID_Order
     JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
     WHERE s.Status = 1",
    []
);
$stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

// --- Data List ---
$sql_list = "SELECT s.ID_Sesi_Foto, s.Waktu_Mulai, s.Waktu_Selesai, s.Status_Sesi, s.File_Hasil, s.Tanggal_Upload_Hasil,
                    o.ID_Order, pl.Nama_Pelanggan, k.Nama_Karyawan
             FROM Sesi_Foto s
             JOIN [Order] o  ON s.ID_Order    = o.ID_Order
             JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
             JOIN Karyawan k   ON s.ID_Karyawan  = k.ID_Karyawan
             WHERE s.Status = 1 $where_extra
             ORDER BY 
                CASE s.Status_Sesi WHEN 1 THEN 0 WHEN 0 THEN 1 ELSE 2 END,
                s.Waktu_Mulai ASC";
$query = sqlsrv_query($conn, $sql_list, $params);

// Status label helper
function statusSesi($s) {
    $map = [
        0 => ['label' => 'Terjadwal',  'class' => 'badge-terjadwal', 'icon' => 'bi-calendar-event'],
        1 => ['label' => 'Proses',     'class' => 'badge-proses',    'icon' => 'bi-camera-reels'],
        2 => ['label' => 'Selesai',    'class' => 'badge-selesai',   'icon' => 'bi-check-circle'],
    ];
    return $map[$s] ?? ['label' => 'Unknown', 'class' => '', 'icon' => 'bi-question'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096;
            --body-bg: #f8fafc; --sidebar-bg: #ffffff;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        ul, li { list-style: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); }

        /* SIDEBAR */
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background: rgba(213,61,102,0.04); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); cursor: pointer; }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }

        /* MAIN */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .clock-badge { background: var(--light-pink); color: var(--text-dark); padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }

        /* STATS */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); padding: 22px 24px; display: flex; align-items: center; gap: 16px; transition: var(--transition-3d); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(213,61,102,0.08); }
        .stat-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .stat-icon-pink   { background: var(--s-pink);  color: var(--p-pink); }
        .stat-icon-yellow { background: #fffbeb;         color: #d97706; }
        .stat-icon-blue   { background: #eff6ff;         color: #3b82f6; }
        .stat-icon-green  { background: #ecfdf5;         color: #059669; }
        .stat-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
        .stat-value { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); line-height: 1; margin-top: 2px; }

        /* SEARCH */
        .search-bar-row { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
        .search-wrap { position: relative; flex: 1; }
        .search-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-input { width: 100%; padding: 13px 18px 13px 44px; border: 2px solid #e2e8f0; border-radius: 14px; font-weight: 600; font-size: 0.9rem; background: #fff; transition: 0.3s; color: var(--text-dark); }
        .search-input:focus { outline: none; border-color: var(--p-pink); }
        .filter-select { padding: 13px 18px; border: 2px solid #e2e8f0; border-radius: 14px; font-weight: 600; font-size: 0.9rem; background: #fff; color: var(--text-dark); cursor: pointer; min-width: 180px; }
        .filter-select:focus { outline: none; border-color: var(--p-pink); }
        .btn-cari { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 14px; padding: 13px 28px; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s; white-space: nowrap; }
        .btn-cari:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(213,61,102,0.3); }
        .btn-add { background: #fff; border: 2px solid var(--p-pink); color: var(--p-pink); border-radius: 14px; padding: 13px 24px; font-weight: 700; font-size: 0.9rem; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; white-space: nowrap; }
        .btn-add:hover { background: var(--p-pink); color: #fff; }

        /* TABLE */
        .table-card { background: #fff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .table-card-header { padding: 20px 28px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .table-card-title { font-weight: 800; font-size: 1rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th { background: #fafafa; padding: 14px 20px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; border-bottom: 2px solid #f1f5f9; white-space: nowrap; }
        .data-table tbody td { padding: 16px 20px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: #fffbfc; }

        /* BADGES */
        .badge-sesi { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 800; padding: 6px 14px; border-radius: 50px; }
        .badge-terjadwal { background: #eff6ff; color: #3b82f6; }
        .badge-proses    { background: #fffbeb; color: #d97706; }
        .badge-selesai   { background: #ecfdf5; color: #059669; }

        /* ACTIONS */
        .action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 10px; font-size: 0.78rem; font-weight: 700; text-decoration: none; transition: 0.2s; border: none; cursor: pointer; }
        .btn-upload { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; box-shadow: 0 3px 10px rgba(213,61,102,0.2); }
        .btn-upload:hover { transform: translateY(-2px); color: #fff; }
        .btn-view   { background: #eff6ff; color: #3b82f6; }
        .btn-view:hover { background: #3b82f6; color: #fff; }
        .btn-edit   { background: #fffbeb; color: #d97706; }
        .btn-edit:hover { background: #d97706; color: #fff; }
        .btn-del    { background: #fff5f5; color: #ef4444; }
        .btn-del:hover { background: #ef4444; color: #fff; }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; }
        .empty-state p { color: var(--text-muted); font-weight: 600; }

        /* PELANGGAN INFO */
        .pelanggan-name { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .order-id-badge { display: inline-block; background: var(--s-pink); color: var(--p-pink); font-size: 0.7rem; font-weight: 800; padding: 2px 10px; border-radius: 50px; margin-top: 4px; }
        .fotografer-name { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }

        /* FILE INFO */
        .file-badge { display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; color: #059669; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 8px; }
        .no-file-badge { display: inline-flex; align-items: center; gap: 6px; background: #fff5f5; color: #ef4444; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 8px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../../Role/Admin/index.php" class="nav-link-custom">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="../../Master/Pelanggan/list.php" class="submenu-link"><i class="bi bi-person-fill me-2"></i>Pelanggan</a></li>
                        <li><a href="../../Master/Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                        <li><a href="../../Master/Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                        <li><a href="../../Master/Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                        <li><a href="../../Master/Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                        <li><a href="../../Master/Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        <li><a href="../../Master/Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                    <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu" id="submenuTransaksi">
                    <ul class="list-unstyled">
                        <li><a href="../../Transaksi/Order/index.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
                        <li><a href="../../Transaksi/Pembayaran/index.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                        <li><a href="../../Transaksi/Penjualan/index.php" class="submenu-link"><i class="bi bi-shop-fill me-2"></i>Penjualan Barang Cetak</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuSesi">
                    <span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu show" id="submenuSesi">
                    <ul class="list-unstyled">
                        <li><a href="./index.php" class="submenu-link active"><i class="bi bi-camera-video-fill me-2"></i>Kelola Sesi Foto</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                    <span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span>
                </a>
            </li>
        </ul>
    </div>
    <div>
        <button onclick="confirmLogout(event)" class="btn-logout">
            <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
        </button>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HEADER -->
    <div class="dashboard-header">
        <div>
            <h3 class="fw-800 mb-1">Sesi Foto 📸</h3>
            <p class="text-muted small mb-0">Kelola jadwal dan upload hasil foto untuk setiap pelanggan.</p>
        </div>
        <div class="clock-badge">
            <i class="bi bi-clock-history me-1 text-danger"></i>
            <span id="live-clock">Memuat...</span>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon stat-icon-pink"><i class="bi bi-camera-fill"></i></div>
            <div>
                <div class="stat-label">Total Sesi</div>
                <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue"><i class="bi bi-calendar-event-fill"></i></div>
            <div>
                <div class="stat-label">Terjadwal</div>
                <div class="stat-value" style="color:#3b82f6;"><?= $stats['terjadwal'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-yellow"><i class="bi bi-camera-reels-fill"></i></div>
            <div>
                <div class="stat-label">Sedang Proses</div>
                <div class="stat-value" style="color:#d97706;"><?= $stats['proses'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Selesai</div>
                <div class="stat-value" style="color:#059669;"><?= $stats['selesai'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- SEARCH & ADD -->
    <form method="GET" action="">
        <div class="search-bar-row">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="cari" class="search-input"
                       placeholder="Cari nama pelanggan atau ID order..."
                       value="<?= htmlspecialchars($cari) ?>">
            </div>
            <select name="filter_status" class="filter-select">
                <option value="">Semua Status</option>
                <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Terjadwal</option>
                <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Proses</option>
                <option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>Selesai</option>
            </select>
            <button type="submit" class="btn-cari">
                <i class="bi bi-search"></i> Cari
            </button>
            <a href="add.php" class="btn-add">
                <i class="bi bi-plus-lg"></i> Tambah Sesi
            </a>
        </div>
    </form>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="table-card-title"><i class="bi bi-table me-2 text-danger"></i>Daftar Sesi Foto</div>
            <?php if (!empty($cari) || $filter_status !== ''): ?>
                <a href="index.php" class="action-btn btn-view" style="font-size:0.78rem;">
                    <i class="bi bi-x-circle"></i> Reset Filter
                </a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pelanggan</th>
                        <th>Fotografer</th>
                        <th>Waktu Mulai</th>
                        <th>Status</th>
                        <th>File Hasil</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $ada_data = false;
                while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                    $ada_data = true;
                    $status = statusSesi($row['Status_Sesi']);
                    $waktu = $row['Waktu_Mulai'] instanceof DateTime
                        ? $row['Waktu_Mulai']->format('d M Y, H:i')
                        : ($row['Waktu_Mulai'] ? date('d M Y, H:i', strtotime($row['Waktu_Mulai'])) : '-');
                ?>
                <tr>
                    <td class="text-muted fw-600" style="font-size:0.85rem;"><?= $no++ ?></td>
                    <td>
                        <div class="pelanggan-name"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                        <span class="order-id-badge">#<?= $row['ID_Order'] ?></span>
                    </td>
                    <td>
                        <div class="fotografer-name">
                            <i class="bi bi-person-badge me-1"></i>
                            <?= htmlspecialchars($row['Nama_Karyawan']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.85rem; font-weight:600;">
                            <i class="bi bi-calendar3 me-1 text-danger"></i> <?= $waktu ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge-sesi <?= $status['class'] ?>">
                            <i class="bi <?= $status['icon'] ?>"></i>
                            <?= $status['label'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($row['File_Hasil'])): ?>
                            <span class="file-badge">
                                <i class="bi bi-file-earmark-check"></i> Terunggah
                            </span>
                        <?php else: ?>
                            <span class="no-file-badge">
                                <i class="bi bi-file-earmark-x"></i> Belum ada
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <?php if (!empty($row['File_Hasil'])): ?>
                                <a href="../../../assets/img/hasil_foto/<?= htmlspecialchars($row['File_Hasil']) ?>"
                                   target="_blank" class="action-btn btn-view" title="Lihat File">
                                    <i class="bi bi-eye"></i> Lihat
                                </a>
                            <?php endif; ?>
                            <a href="edit.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="action-btn btn-edit">
                                <i class="bi bi-cloud-upload"></i>
                                <?= empty($row['File_Hasil']) ? 'Upload' : 'Update' ?>
                            </a>
                            <button onclick="confirmDelete(<?= $row['ID_Sesi_Foto'] ?>)" class="action-btn btn-del">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$ada_data): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-camera-video-off"></i>
                            <p>Tidak ada data sesi foto<?= !empty($cari) ? ' untuk pencarian "' . htmlspecialchars($cari) . '"' : '' ?>.</p>
                            <a href="add.php" class="btn-add d-inline-flex mt-2">
                                <i class="bi bi-plus-lg"></i> Tambah Sesi Foto
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Submenu toggle
document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.dataset.target);
        const chevron = this.querySelector('.icon-chevron');
        if (!target) return;
        const isOpen = target.classList.contains('show');
        document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.icon-chevron').forEach(ic => ic.style.transform = '');
        if (!isOpen) {
            target.classList.add('show');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        }
    });
});

// Clock
function updateClock() {
    const now = new Date();
    const opt = { weekday:'long', day:'numeric', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' };
    document.getElementById('live-clock').innerText = now.toLocaleDateString('id-ID', opt) + ' WIB';
}
setInterval(updateClock, 1000); updateClock();

// Delete confirm
function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Sesi Foto?',
        text: 'Data sesi foto ini akan dihapus dari sistem.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D53D66',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        borderRadius: '16px'
    }).then(result => {
        if (result.isConfirmed) window.location.href = 'action_foto.php?act=delete&id=' + id;
    });
}

function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Keluar Sistem?',
        text: 'Anda akan keluar dari sesi administrator.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D53D66',
        confirmButtonText: 'Keluar',
        cancelButtonText: 'Batal'
    }).then(r => { if (r.isConfirmed) window.location.href = '../../logout.php'; });
}

function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Buka Landing Page?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#D53D66',
        confirmButtonText: 'Ya, Buka',
        cancelButtonText: 'Batal'
    }).then(r => { if (r.isConfirmed) window.location.href = '../../index.php'; });
}

// SweetAlert notifikasi dari PHP
<?php if (!empty($msg) && isset($alert_map[$msg])): ?>
Swal.fire({
    icon: '<?= $alert_map[$msg]['type'] ?>',
    title: '<?= $alert_map[$msg]['type'] === 'success' ? 'Berhasil!' : 'Gagal!' ?>',
    text: '<?= $alert_map[$msg]['text'] ?>',
    confirmButtonColor: '#D53D66',
    timer: 3000,
    timerProgressBar: true
});
<?php endif; ?>
</script>
</body>
</html>