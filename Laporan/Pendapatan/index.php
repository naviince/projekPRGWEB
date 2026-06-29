<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

if ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/pelanggan/" . $foto_owner;
} else {
    $foto_owner_src = $default_svg_avatar;
}

// =====================================================
// FILTER PARAMETER
// =====================================================
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$filter_tipe = isset($_GET['filter_tipe']) ? $_GET['filter_tipe'] : 'semua';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'semua';

// =====================================================
// QUERY STATISTIK SUMMARY
// =====================================================
// Total DP Valid
$q_total_dp = sqlsrv_query($conn, "
    SELECT SUM(Jumlah_Bayar) as total FROM Pembayaran 
    WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'DP' 
    AND CAST(Tanggal_Upload AS DATE) BETWEEN ? AND ?
", array($tgl_mulai, $tgl_selesai));
$d_total_dp = sqlsrv_fetch_array($q_total_dp, SQLSRV_FETCH_ASSOC);
$total_dp = $d_total_dp['total'] ?? 0;

// Total Pelunasan Valid
$q_total_pelunasan = sqlsrv_query($conn, "
    SELECT SUM(Jumlah_Bayar) as total FROM Pembayaran 
    WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'Pelunasan' 
    AND CAST(Tanggal_Upload AS DATE) BETWEEN ? AND ?
", array($tgl_mulai, $tgl_selesai));
$d_total_pelunasan = sqlsrv_fetch_array($q_total_pelunasan, SQLSRV_FETCH_ASSOC);
$total_pelunasan = $d_total_pelunasan['total'] ?? 0;

// Total Barang Cetak
$q_total_barang = sqlsrv_query($conn, "
    SELECT SUM(Total_Penjualan) as total FROM Penjualan 
    WHERE Status_Penjualan = 1 
    AND CAST(Tanggal_Penjualan AS DATE) BETWEEN ? AND ?
", array($tgl_mulai, $tgl_selesai));
$d_total_barang = sqlsrv_fetch_array($q_total_barang, SQLSRV_FETCH_ASSOC);
$total_barang = $d_total_barang['total'] ?? 0;

$grand_total = $total_dp + $total_pelunasan + $total_barang;

// =====================================================
// QUERY DATA LAPORAN
// =====================================================
$sql_data = "";
$params = array();

if ($filter_tipe == 'semua' || $filter_tipe == 'dp' || $filter_tipe == 'pelunasan') {
    $sql_data .= "
        SELECT 
            p.ID_Pembayaran as id_transaksi,
            p.Tanggal_Upload as tanggal,
            o.ID_Order,
            pl.Nama_Pelanggan,
            pk.Nama_Paket,
            p.Tipe_Pembayaran as tipe,
            p.Metode_Pembayaran as metode,
            p.Jumlah_Bayar as jumlah,
            p.Status_Pembayaran as status,
            k.Nama_Karyawan as verifikator,
            'Pembayaran' as sumber
        FROM Pembayaran p
        INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
        INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
        INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
        LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan
        WHERE p.Status = 1
        AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?
    ";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;

    if ($filter_tipe == 'dp') {
        $sql_data .= " AND p.Tipe_Pembayaran = 'DP'";
    } elseif ($filter_tipe == 'pelunasan') {
        $sql_data .= " AND p.Tipe_Pembayaran = 'Pelunasan'";
    }

    if ($filter_status != 'semua') {
        $status_map = ['menunggu' => 0, 'valid' => 1, 'ditolak' => 2];
        $sql_data .= " AND p.Status_Pembayaran = " . $status_map[$filter_status];
    }
}

if ($filter_tipe == 'semua' || $filter_tipe == 'barang') {
    if (!empty($sql_data)) {
        $sql_data .= " UNION ALL ";
    }
    $sql_data .= "
        SELECT 
            pe.ID_Penjualan as id_transaksi,
            pe.Tanggal_Penjualan as tanggal,
            pe.ID_Order,
            pl.Nama_Pelanggan,
            'Barang Cetak' as Nama_Paket,
            'Penjualan' as tipe,
            'Kasir' as metode,
            pe.Total_Penjualan as jumlah,
            pe.Status_Penjualan as status,
            k.Nama_Karyawan as verifikator,
            'Barang_Cetak' as sumber
        FROM Penjualan pe
        INNER JOIN [Order] o ON pe.ID_Order = o.ID_Order
        INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
        LEFT JOIN Karyawan k ON pe.ID_Karyawan_Admin = k.ID_Karyawan
        WHERE pe.Status = 1
        AND CAST(pe.Tanggal_Penjualan AS DATE) BETWEEN ? AND ?
    ";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;

    if ($filter_status != 'semua') {
        $status_map = ['menunggu' => 0, 'valid' => 1, 'ditolak' => 2];
        $sql_data .= " AND pe.Status_Penjualan = " . $status_map[$filter_status];
    }
}

$sql_data .= " ORDER BY tanggal DESC";

$query = sqlsrv_query($conn, $sql_data, $params);

function getStatusLabel($status, $sumber) {
    if ($sumber == 'Barang_Cetak') {
        $map = [0 => ['Proses', '#d97706', '#fffbeb'], 1 => ['Selesai', '#059669', '#d1fae5']];
    } else {
        $map = [0 => ['Menunggu', '#d97706', '#fffbeb'], 1 => ['Valid', '#059669', '#d1fae5'], 2 => ['Ditolak', '#dc2626', '#fee2e2']];
    }
    return $map[$status] ?? ['Unknown', '#718096', '#f1f5f9'];
}

function getTipeLabel($tipe) {
    $map = ['DP' => 'Uang Muka', 'Pelunasan' => 'Pelunasan', 'Penjualan' => 'Barang Cetak'];
    return $map[$tipe] ?? $tipe;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pendapatan - SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --sidebar-bg: #ffffff;
            --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR STYLING - SAMA DENGAN OWNER DASHBOARD */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1px;
            margin-bottom: 40px;
            display: block;
        }
        .sidebar-brand span {
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-menu-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .sidebar-menu-wrapper::-webkit-scrollbar {
            display: none;
        }
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            margin-bottom: 8px;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            color: #4a5568;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu {
            list-style: none;
            padding-left: 20px;
            margin-top: 5px;
            display: none; 
            transition: var(--transition-3d);
        }
        .submenu.show {
            display: block !important;
        }
        .submenu-link {
            display: flex;
            align-items: center;
            padding: 8px 18px;
            color: #718096;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink);
            background-color: rgba(216, 63, 103, 0.03);
            padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216,63, 103, 0.2);
        }

        /* MAIN CONTENT AREA */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        .profile-header-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ffffff;
            cursor: pointer;
            transition: var(--transition-3d);
            background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* STAT CARDS */
        .stats-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 10px;
            margin-bottom: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .stats-scroll-wrapper::-webkit-scrollbar {
            height: 6px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 10px;
        }
        .stats-row {
            display: flex;
            gap: 16px;
            min-width: max-content;
        }
        .stat-card-item {
            min-width: 220px;
            max-width: 280px;
            flex: 0 0 auto;
        }
        .card-3d {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d);
            padding: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 45px rgba(216, 63, 103, 0.14); 
            border-color: var(--p-pink);
        }
        .card-3d:hover::before {
            opacity: 1;
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: var(--transition-3d);
            flex-shrink: 0;
        }
        .card-3d:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .stat-icon-pink { background: linear-gradient(135deg, #fff5f6, #ffe4e9); color: var(--p-pink); }
        .stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
        .stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
        .stat-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .stat-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-title {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-subtitle {
            font-size: 0.68rem;
            color: #a0aec0;
            font-weight: 600;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* FILTER BAR */
        .filter-bar {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            padding: 24px;
            margin-bottom: 25px;
        }
        .filter-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }
        .filter-input {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: var(--transition-3d);
            width: 100%;
        }
        .filter-input:focus {
            outline: none;
            border-color: var(--p-pink);
            box-shadow: 0 0 0 4px rgba(216, 63, 103, 0.08);
        }
        .btn-filter {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 14px;
            padding: 10px 24px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition-3d);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
        }
        .btn-export {
            background: #ffffff;
            border: 2px solid var(--p-pink);
            color: var(--p-pink);
            border-radius: 14px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition-3d);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-export:hover {
            background: var(--p-pink);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }

        /* TABLE STYLING */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border-radius: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .table-scroll-wrapper::-webkit-scrollbar {
            height: 8px;
        }
        .table-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .table-scroll-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 10px;
        }
        .data-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .data-table thead th {
            background: #fff;
            padding: 16px 20px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            white-space: nowrap;
            border: none;
            border-bottom: 2px solid #f1f5f9;
            text-align: left;
        }
        .data-table thead th:first-child {
            padding-left: 24px;
        }
        .data-table thead th:last-child {
            padding-right: 24px;
            text-align: center;
        }
        .data-table tbody tr {
            transition: all 0.2s ease;
        }
        .data-table tbody td {
            padding: 14px 20px;
            border: none;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            white-space: nowrap;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .data-table tbody td:first-child {
            padding-left: 24px;
        }
        .data-table tbody td:last-child {
            padding-right: 24px;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #FFF8F0;
        }
        .data-table tbody tr:nth-child(odd) {
            background-color: #fff;
        }
        .data-table tbody tr:hover {
            background-color: #FFEDD5 !important;
            transform: scale(1.002);
        }
        .td-transaksi-id {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--p-pink);
        }
        .td-customer {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .td-detail {
            font-size: 0.8rem;
            color: #718096;
            font-weight: 600;
        }
        .td-jumlah {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--p-pink);
        }
        .badge-status {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .badge-tipe-dp { background: #dbeafe; color: #2563eb; }
        .badge-tipe-pelunasan { background: #d1fae5; color: #059669; }
        .badge-tipe-barang { background: #f5f3ff; color: #7c3aed; }

        /* Animasi */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up {
            animation: fadeIn 0.5s ease-out;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR - SAMA DENGAN OWNER DASHBOARD -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../Role/Owner/index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Beranda Pemilik</span>
            </a>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Owner/index.php" class="nav-link-custom">
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
                            <li><a href="../../Master/Karyawan/index.php" class="submenu-link"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLaporan">
                        <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuLaporan">
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                            <li><a href="../Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                            <li><a href="../Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                            <li><a href="../Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Laporan Pendapatan</h3>
                <p class="text-muted small mb-0">Pantau dan kelola seluruh pendapatan studio secara detail.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
                </div>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-blue"><i class="bi bi-credit-card-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Uang Muka</div>
                                <div class="stat-val">Rp<?= number_format($total_dp, 0, ',', '.') ?></div>
                                <div class="stat-subtitle">Pembayaran DP valid</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Pelunasan</div>
                                <div class="stat-val">Rp<?= number_format($total_pelunasan, 0, ',', '.') ?></div>
                                <div class="stat-subtitle">Pelunasan valid</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-purple"><i class="bi bi-box-seam-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Barang Cetak</div>
                                <div class="stat-val">Rp<?= number_format($total_barang, 0, ',', '.') ?></div>
                                <div class="stat-subtitle">Penjualan selesai</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-cash-coin"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Grand Total</div>
                                <div class="stat-val">Rp<?= number_format($grand_total, 0, ',', '.') ?></div>
                                <div class="stat-subtitle">Seluruh pendapatan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-2 col-md-4">
                        <label class="filter-label">Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" class="filter-input" value="<?= htmlspecialchars($tgl_mulai) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="filter-label">Tanggal Selesai</label>
                        <input type="date" name="tgl_selesai" class="filter-input" value="<?= htmlspecialchars($tgl_selesai) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="filter-label">Tipe Pendapatan</label>
                        <select name="filter_tipe" class="filter-input">
                            <option value="semua" <?= $filter_tipe=='semua'?'selected':'' ?>>Semua Tipe</option>
                            <option value="dp" <?= $filter_tipe=='dp'?'selected':'' ?>>Uang Muka (DP)</option>
                            <option value="pelunasan" <?= $filter_tipe=='pelunasan'?'selected':'' ?>>Pelunasan</option>
                            <option value="barang" <?= $filter_tipe=='barang'?'selected':'' ?>>Barang Cetak</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="filter-label">Status</label>
                        <select name="filter_status" class="filter-input">
                            <option value="semua" <?= $filter_status=='semua'?'selected':'' ?>>Semua Status</option>
                            <option value="valid" <?= $filter_status=='valid'?'selected':'' ?>>Valid</option>
                            <option value="menunggu" <?= $filter_status=='menunggu'?'selected':'' ?>>Menunggu</option>
                            <option value="ditolak" <?= $filter_status=='ditolak'?'selected':'' ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-8">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-filter">
                                <i class="bi bi-funnel-fill"></i> Terapkan Filter
                            </button>
                            <a href="export_pdf.php?<?= http_build_query($_GET) ?>" class="btn-export" target="_blank">
                                <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                            </a>
                            <a href="export_excel.php?<?= http_build_query($_GET) ?>" class="btn-export">
                                <i class="bi bi-file-earmark-excel-fill"></i> Excel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE DATA -->
        <div class="card-3d" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th>Tipe</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Verifikator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $total_rows = 0;
                        if ($query && sqlsrv_has_rows($query)):
                            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                $statusInfo = getStatusLabel((int)$row['status'], $row['sumber']);
                                $tipeLabel = getTipeLabel($row['tipe']);
                                $tipeClass = '';
                                if ($row['tipe'] == 'DP') $tipeClass = 'badge-tipe-dp';
                                elseif ($row['tipe'] == 'Pelunasan') $tipeClass = 'badge-tipe-pelunasan';
                                else $tipeClass = 'badge-tipe-barang';
                                $total_rows++;
                        ?>
                            <tr class="fade-in-up">
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="td-transaksi-id">#<?= str_pad((int)$row['id_transaksi'], 5, '0', STR_PAD_LEFT) ?></div>
                                    <div class="td-detail">Order #<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></div>
                                </td>
                                <td>
                                    <div class="td-detail">
                                        <?= (is_object($row['tanggal']) && method_exists($row['tanggal'], 'format')) ? $row['tanggal']->format('d M Y H:i') : date('d M Y H:i', strtotime($row['tanggal'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                </td>
                                <td>
                                    <div class="td-detail"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $tipeClass ?>"><?= $tipeLabel ?></span>
                                </td>
                                <td>
                                    <div class="td-detail"><?= htmlspecialchars($row['metode']) ?></div>
                                </td>
                                <td>
                                    <div class="td-jumlah">Rp<?= number_format((float)$row['jumlah'], 0, ',', '.') ?></div>
                                </td>
                                <td>
                                    <span class="badge-status" style="background:<?= $statusInfo[2] ?>;color:<?= $statusInfo[1] ?>">
                                        <span class="badge-dot" style="background:<?= $statusInfo[1] ?>"></span>
                                        <?= $statusInfo[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="td-detail"><?= htmlspecialchars($row['verifikator'] ?? '-') ?></div>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color:#cbd5e1"></i>
                                    <p class="fw-bold">Tidak ada data pendapatan.</p>
                                    <p class="small">Silakan sesuaikan filter periode atau tipe pendapatan.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_rows > 0): ?>
            <div class="mt-3 text-end">
                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">
                    Menampilkan <strong style="color: var(--p-pink);"><?= $total_rows ?></strong> transaksi
                </span>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Submenu
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');

                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.icon-chevron').forEach(icon => icon.style.transform = 'rotate(0deg)');

                    if (!isShown) {
                        targetEl.classList.add('show');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
        });

        function bukaModalBiodata() {
            Swal.fire({
                title: '<?= htmlspecialchars($nama_owner) ?>',
                text: 'Owner - SpotLight Studio',
                icon: 'info',
                confirmButtonColor: '#d83f67'
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama publik SpotLight Studio.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }

        // Live Clock
        function updateLiveClock() {
            const now = new Date();
            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            document.getElementById('live-clock').innerText = 
                `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>
</body>
</html>