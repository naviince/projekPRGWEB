<?php
session_start();
include '../../Role/Admin/koneksi.php';  // Adjust path to koneksi

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN: HANYA ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? 0;

// =====================================================
// FILTER STATUS DARI URL
// =====================================================
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// =====================================================
// AMBIL DATA ORDER
// =====================================================
$sql = "SELECT 
    o.ID_Order, o.Tanggal_Booking, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, 
    o.Status_Order, o.Rating, o.Review,
    p.Nama_Pelanggan, p.No_Hp, p.Email_Pelanggan,
    pk.Nama_Paket, pk.Durasi_Waktu,
    r.Nama_Ruangan,
    t.Nama_Tema,
    j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
FROM [Order] o
INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
WHERE o.Status = 1";

$params = array();

// Filter by status
if ($filter_status !== 'all' && is_numeric($filter_status)) {
    $sql .= " AND o.Status_Order = ?";
    $params[] = (int)$filter_status;
}

// Search by customer name or order ID
if (!empty($search)) {
    $sql .= " AND (p.Nama_Pelanggan LIKE ? OR CAST(o.ID_Order AS VARCHAR) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.Tanggal_Booking DESC";

$q_orders = sqlsrv_query($conn, $sql, $params);
if ($q_orders === false) {
    die("Error query orders: " . print_r(sqlsrv_errors(), true));
}

$orders = [];
while ($row = sqlsrv_fetch_array($q_orders, SQLSRV_FETCH_ASSOC)) {
    $orders[] = $row;
}

// =====================================================
// HITUNG JUMLAH ORDER PER STATUS
// =====================================================
$status_counts = [
    'all' => 0,
    '0' => 0,  // Menunggu DP
    '1' => 0,  // DP Terverifikasi
    '2' => 0,  // Selesai
    '3' => 0,  // Lunas
    '4' => 0,  // Dibatalkan
];

$q_counts = sqlsrv_query($conn, 
    "SELECT Status_Order, COUNT(*) as total FROM [Order] WHERE Status = 1 GROUP BY Status_Order"
);
if ($q_counts !== false) {
    while ($c = sqlsrv_fetch_array($q_counts, SQLSRV_FETCH_ASSOC)) {
        $status_counts[$c['Status_Order']] = $c['total'];
        $status_counts['all'] += $c['total'];
    }
}

// =====================================================
// AMBIL DATA FOTOGRAFER (untuk assign)
// =====================================================
$q_fotografer = sqlsrv_query($conn, 
    "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0"
);
$fotografer_list = [];
if ($q_fotografer !== false) {
    while ($f = sqlsrv_fetch_array($q_fotografer, SQLSRV_FETCH_ASSOC)) {
        $fotografer_list[] = $f;
    }
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function getStatusLabel($status) {
    $labels = [
        0 => ['Menunggu DP', '#d97706', '#fef3c7'],
        1 => ['DP Terverifikasi', '#059669', '#d1fae5'],
        2 => ['Selesai', '#2563eb', '#dbeafe'],
        3 => ['Lunas', '#7c3aed', '#ede9fe'],
        4 => ['Dibatalkan', '#dc2626', '#fee2e2'],
    ];
    return $labels[$status] ?? ['Unknown', '#718096', '#f1f5f9'];
}

function formatTanggal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y H:i');
    }
    return date('d M Y H:i', strtotime($dateObj));
}

function formatTanggalJadwal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y');
    }
    return date('d M Y', strtotime($dateObj));
}

function formatJam($timeObj) {
    if (is_object($timeObj) && method_exists($timeObj, 'format')) {
        return $timeObj->format('H:i');
    }
    return substr($timeObj, 0, 5);
}

$status_labels = [
    'all' => 'Semua Order',
    '0' => 'Menunggu DP',
    '1' => 'DP Terverifikasi',
    '2' => 'Selesai',
    '3' => 'Lunas',
    '4' => 'Dibatalkan',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Order - SpotLight Studio Admin</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        .sidebar-brand h1 {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .sidebar-brand p {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .sidebar-menu {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }
        .menu-section {
            margin-bottom: 8px;
        }
        .menu-section-title {
            font-size: 0.7rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 16px 8px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #4a5568;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
            margin-bottom: 4px;
            cursor: pointer;
        }
        .menu-item:hover, .menu-item.active {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .menu-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .menu-badge {
            margin-left: auto;
            background: var(--p-pink);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 50px;
        }
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .btn-logout {
            width: 100%;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--text-dark);
        }
        .page-title span {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .admin-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
        }
        .admin-role {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .admin-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
        }

        /* ===== STATUS CARDS ===== */
        .status-cards {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .status-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            position: relative;
        }
        .status-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }
        .status-card.active {
            border-color: var(--p-pink);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.15);
        }
        .status-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }
        .status-card-count {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--text-dark);
        }
        .status-card-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ===== SEARCH & FILTER BAR ===== */
        .filter-bar {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            font-weight: 600;
            transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.1);
        }
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
        }
        .btn-filter {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-filter:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
        }
        .btn-export {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* ===== TABLE ===== */
        .table-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead th {
            background: #f8fafc;
            padding: 14px 20px;
            font-size: 0.8rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            white-space: nowrap;
        }
        .data-table tbody td {
            padding: 16px 20px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Customer Info */
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .customer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .customer-name {
            font-weight: 700;
            color: var(--text-dark);
        }
        .customer-contact {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Order Info */
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .order-paket {
            font-weight: 700;
            color: var(--text-dark);
        }
        .order-detail {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Jadwal Info */
        .jadwal-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .jadwal-tanggal {
            font-weight: 700;
            color: var(--text-dark);
        }
        .jadwal-jam {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Harga */
        .harga-cell {
            font-weight: 800;
            color: var(--p-pink);
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .btn-action.view { background: #dbeafe; color: #2563eb; }
        .btn-action.view:hover { background: #2563eb; color: #fff; }
        .btn-action.cancel { background: #fee2e2; color: #dc2626; }
        .btn-action.cancel:hover { background: #dc2626; color: #fff; }
        .btn-action.assign { background: #d1fae5; color: #059669; }
        .btn-action.assign:hover { background: #059669; color: #fff; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.3s ease;
        }
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .modal-close:hover {
            background: #f1f5f9;
            color: var(--text-dark);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .detail-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
        }
        .detail-value.price {
            color: var(--p-pink);
            font-size: 1.1rem;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .status-cards { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .status-cards { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h1>SpotLight.</h1>
            <p>Panel Administrator</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">
                <a href="../../Role/Admin/index.php" class="menu-item">
                    <i class="bi bi-grid-fill"></i> Dashboard
                </a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Transaksi</div>
                <a href="index.php" class="menu-item active">
                    <i class="bi bi-cart-fill"></i> Booking Customer
                    <?php if ($status_counts['0'] > 0): ?>
                    <span class="menu-badge"><?= $status_counts['0'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="../Pembayaran/index.php" class="menu-item">
                    <i class="bi bi-credit-card-fill"></i> Pembayaran
                </a>
                <a href="../Barang_Cetak/index.php" class="menu-item">
                    <i class="bi bi-box-seam-fill"></i> Barang Cetak
                </a>
            </div>
            <div class="menu-section">
                <div class="menu-section-title">Master Data</div>
                <a href="../../Role/Admin/Master/Paket/index.php" class="menu-item">
                    <i class="bi bi-box-fill"></i> Paket Foto
                </a>
                <a href="../../Role/Admin/Master/Ruangan/index.php" class="menu-item">
                    <i class="bi bi-door-open-fill"></i> Ruangan
                </a>
                <a href="../../Role/Admin/Master/Jadwal/index.php" class="menu-item">
                    <i class="bi bi-calendar-week-fill"></i> Jadwal Studio
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
            <button class="btn-logout" onclick="confirmLogout()">
                <i class="bi bi-box-arrow-right"></i> Keluar Sistem
            </button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOP BAR -->
        <div class="top-bar">
            <div>
                <div class="page-title">Kelola Order <span>/ <?= htmlspecialchars($status_labels[$filter_status]) ?></span></div>
            </div>
            <div class="admin-info">
                <div>
                    <div class="admin-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></div>
                    <div class="admin-role">Administrator</div>
                </div>
                <div class="admin-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
            </div>
        </div>

        <!-- STATUS CARDS -->
        <div class="status-cards">
            <?php 
            $card_configs = [
                'all' => ['Semua Order', 'bi bi-stack', '#4a5568', '#f1f5f9'],
                '0' => ['Menunggu DP', 'bi bi-hourglass-split', '#d97706', '#fef3c7'],
                '1' => ['DP Terverifikasi', 'bi bi-check-circle-fill', '#059669', '#d1fae5'],
                '2' => ['Selesai', 'bi bi-camera-fill', '#2563eb', '#dbeafe'],
                '3' => ['Lunas', 'bi bi-cash-stack', '#7c3aed', '#ede9fe'],
                '4' => ['Dibatalkan', 'bi bi-x-circle-fill', '#dc2626', '#fee2e2'],
            ];
            foreach ($card_configs as $key => $cfg):
                $isActive = ($filter_status === $key);
                $count = $status_counts[$key] ?? 0;
            ?>
            <a href="?status=<?= $key ?>" class="status-card <?= $isActive ? 'active' : '' ?>" style="border-color:<?= $isActive ? $cfg[2] : 'transparent' ?>;">
                <div class="status-card-icon" style="background:<?= $cfg[3] ?>;color:<?= $cfg[2] ?>;">
                    <i class="<?= $cfg[1] ?>"></i>
                </div>
                <div class="status-card-count"><?= $count ?></div>
                <div class="status-card-label"><?= $cfg[0] ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" style="display:flex;gap:12px;flex:1;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Cari nama customer atau no. order..." 
                           value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
                </div>
            </form>
            <button class="btn-export" onclick="exportData()">
                <i class="bi bi-download"></i> Export Excel
            </button>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Daftar Order</div>
                <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">
                    Total: <?= count($orders) ?> order
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Customer</th>
                            <th>Paket</th>
                            <th>Jadwal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <h3>Tidak Ada Order</h3>
                                    <p>Belum ada order dengan status ini.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o): 
                            $statusInfo = getStatusLabel((int)$o['Status_Order']);
                        ?>
                        <tr>
                            <td>
                                <span style="font-weight:800;color:var(--text-dark);">#<?= str_pad((int)$o['ID_Order'], 5, '0', STR_PAD_LEFT) ?></span>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= formatTanggal($o['Tanggal_Booking']) ?></div>
                            </td>
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?= strtoupper(substr($o['Nama_Pelanggan'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="customer-name"><?= htmlspecialchars($o['Nama_Pelanggan']) ?></div>
                                        <div class="customer-contact"><?= htmlspecialchars($o['No_Hp']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="order-info">
                                    <div class="order-paket"><?= htmlspecialchars($o['Nama_Paket']) ?></div>
                                    <div class="order-detail"><?= htmlspecialchars($o['Nama_Ruangan']) ?> &bull; <?= htmlspecialchars($o['Nama_Tema']) ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="jadwal-info">
                                    <div class="jadwal-tanggal"><?= formatTanggalJadwal($o['Tanggal_Jadwal']) ?></div>
                                    <div class="jadwal-jam"><?= formatJam($o['Jam_Mulai']) ?> - <?= formatJam($o['Jam_Selesai']) ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="harga-cell">Rp <?= number_format((float)$o['Total_Harga'], 0, ',', '.') ?></div>
                            </td>
                            <td>
                                <span class="status-badge" style="background:<?= $statusInfo[2] ?>;color:<?= $statusInfo[1] ?>;">
                                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $statusInfo[1] ?>;display:inline-block;"></span>
                                    <?= $statusInfo[0] ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-action view" onclick="viewDetail(<?= (int)$o['ID_Order'] ?>)" title="Lihat Detail">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                    <?php if ((int)$o['Status_Order'] === STATUS_ORDER_MENUNGGU_DP): ?>
                                    <button class="btn-action cancel" onclick="batalkanOrder(<?= (int)$o['ID_Order'] ?>)" title="Batalkan Order">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <?php elseif ((int)$o['Status_Order'] === STATUS_ORDER_DP_TERVERIFIKASI): ?>
                                    <button class="btn-action assign" onclick="assignFotografer(<?= (int)$o['ID_Order'] ?>)" title="Assign Fotografer">
                                        <i class="bi bi-person-plus-fill"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="bi bi-receipt" style="color:var(--p-pink);margin-right:8px;"></i> Detail Order</div>
                <button class="modal-close" onclick="closeModal('modalDetail')">&times;</button>
            </div>
            <div class="modal-body" id="modalDetailBody">
                <!-- Content loaded via JS -->
            </div>
            <div class="modal-footer">
                <button class="btn-filter" onclick="closeModal('modalDetail')">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL ASSIGN FOTOGRAFER -->
    <div class="modal-overlay" id="modalAssign">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <div class="modal-title"><i class="bi bi-person-plus" style="color:var(--p-pink);margin-right:8px;"></i> Assign Fotografer</div>
                <button class="modal-close" onclick="closeModal('modalAssign')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:16px;">Pilih fotografer untuk sesi foto ini:</p>
                <form id="formAssign" method="POST" action="assign_fotografer.php">
                    <input type="hidden" name="id_order" id="assignOrderId">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label style="display:block;font-size:0.9rem;font-weight:700;color:var(--text-dark);margin-bottom:8px;">Fotografer</label>
                        <select name="id_fotografer" class="form-input" style="width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:12px;font-family:inherit;font-weight:600;" required>
                            <option value="">Pilih Fotografer</option>
                            <?php foreach ($fotografer_list as $fg): ?>
                            <option value="<?= (int)$fg['ID_Karyawan'] ?>"><?= htmlspecialchars($fg['Nama_Karyawan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-filter" onclick="closeModal('modalAssign')">Batal</button>
                <button class="btn-export" onclick="document.getElementById('formAssign').submit()">
                    <i class="bi bi-check-lg"></i> Simpan
                </button>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });

        // View detail
        function viewDetail(idOrder) {
            // In production, fetch via AJAX
            // For now, show placeholder
            document.getElementById('modalDetailBody').innerHTML = `
                <div style="text-align:center;padding:40px;">
                    <i class="bi bi-receipt" style="font-size:3rem;color:#e2e8f0;"></i>
                    <p style="margin-top:16px;color:var(--text-muted);font-weight:600;">Memuat detail order #${String(idOrder).padStart(5, '0')}...</p>
                    <p style="font-size:0.85rem;color:#94a3b8;">(Implementasi: fetch detail via AJAX ke detail.php)</p>
                </div>
            `;
            openModal('modalDetail');
        }

        // Batalkan order
        function batalkanOrder(idOrder) {
            Swal.fire({
                title: 'Batalkan Order?',
                text: 'Order yang dibatalkan tidak dapat dikembalikan. Jadwal akan kembali tersedia.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'batalkan_order.php?id_order=' + idOrder;
                }
            });
        }

        // Assign fotografer
        function assignFotografer(idOrder) {
            document.getElementById('assignOrderId').value = idOrder;
            openModal('modalAssign');
        }

        // Export data
        function exportData() {
            Swal.fire({
                title: 'Export Data?',
                text: 'Data order akan diexport ke format Excel.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Export',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Berhasil!', 'Data sedang diproses untuk export.', 'success');
                }
            });
        }

        // Logout
        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari panel admin?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }
    </script>
</body>
</html>