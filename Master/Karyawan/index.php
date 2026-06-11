<?php
session_start();
include '../../koneksi.php'; 

// --- PROTEKSI KEAMANAN HAK AKSES BERLAPIS ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];

// Ambil Profil Owner untuk Navbar Atas Kanan, Audit Trail, & Pengamanan Akun Aktif
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) ? "../../assets/img/pelanggan/" . $foto_owner : $default_svg_avatar;

// =====================================================
// QUERY STATISTIK UNTUK KOTAK INDIKATOR STAF BERWARNA
// =====================================================

// 1. Hitung Jumlah Admin Aktif
$q_count_admin = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Admin' AND Is_Deleted = 0");
$d_count_admin = sqlsrv_fetch_array($q_count_admin, SQLSRV_FETCH_ASSOC);
$total_admin = $d_count_admin['total'] ?? 0;

// 2. Hitung Jumlah Fotografer Aktif
$q_count_foto = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Is_Deleted = 0");
$d_count_foto = sqlsrv_fetch_array($q_count_foto, SQLSRV_FETCH_ASSOC);
$total_foto = $d_count_foto['total'] ?? 0;

// 3. Hitung Jumlah Owner Aktif
$q_count_owner = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0");
$d_count_owner = sqlsrv_fetch_array($q_count_owner, SQLSRV_FETCH_ASSOC);
$total_owner = $q_count_owner ? $d_count_owner['total'] : 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Karyawan – SpotLight Studio</title>
    
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

        /* SIDEBAR STYLING DENGAN GULIR MANDIRI */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem; color: var(--p-pink);
            text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }

        .sidebar-menu-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .sidebar-menu-wrapper::-webkit-scrollbar {
            display: none;
        }

        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none;
            border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px; color: #718096;
            font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(216, 63, 103, 0.03); padding-left: 22px; }

        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; width: 100%; padding: 12px; border-radius: 12px;
            font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216,63, 103, 0.2); }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }

        /* PERBAIKAN TOMBOL TAMBAH KARYAWAN */
        .btn-reg-header { 
            background: #d83f67 !important;
            color: #ffffff !important; 
            border-radius: 14px !important; 
            padding: 12px 28px !important; 
            font-weight: 800 !important; 
            border: none !important; 
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.25) !important; 
            transition: var(--transition-3d) !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-reg-header:hover { 
            background: #ff6694 !important;
            transform: translateY(-4px) scale(1.03) !important; 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.4) !important; 
            color: #ffffff !important;
        }

        /* KARTU 3D FLOATING */
        .card-3d {
            background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 10px 25px rgba(216, 63, 103, 0.04); 
            transition: var(--transition-3d);
            padding: 30px; height: auto; position: relative;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.005);
            box-shadow: 0 20px 45px rgba(216, 63, 103, 0.15); 
            border-color: rgba(216, 63, 103, 0.2);
        }

        /* Indikator Stats Kecil Atas */
        .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
            transition: var(--transition-3d);
        }
        .card-3d:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* INPUT & FILTER OVERLAY STYLING */
        .search-filter-wrapper {
            position: relative;
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        .btn-filter-toggle {
            background: #ffffff; border: 2px solid #eef2f6; border-radius: 14px;
            padding: 12px 24px; font-weight: 700; color: var(--text-dark);
            transition: var(--transition-3d); display: flex; align-items: center; gap: 8px;
        }
        .btn-filter-toggle:hover {
            background: var(--light-pink); border-color: var(--p-pink);
            color: var(--p-pink); transform: translateY(-2px);
        }
        
        .filter-dropdown-panel {
            position: absolute; top: 110%; right: 0; width: 320px;
            background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(10px);
            border-radius: 24px; border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 15px 40px rgba(216, 63, 103, 0.15); padding: 25px;
            z-index: 50; display: none; animation: fadeIn 0.3s ease-out;
        }
        .filter-dropdown-panel.show { display: block; }

        /* ============================================================
           PERBAIKAN UTAMA: TABEL SCROLL HORIZONTAL - NO BORDER GARIS
           ============================================================ */
        
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            margin-top: 15px;
            border-radius: 20px;
            /* Custom scrollbar styling */
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
        .table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--d-pink);
        }

        /* Table Layout - Fixed width columns, NO vertical borders */
        .data-table {
            width: 100%;
            min-width: 950px; /* Minimum width untuk scroll horizontal */
            border-collapse: separate;
            border-spacing: 0 12px; /* Hanya jarak antar baris, bukan kolom */
        }

        /* Table Header - NO borders */
        .data-table thead th {
            background: transparent; /* Hapus background gradient */
            padding: 14px 20px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #718096;
            white-space: nowrap;
            border: none;
            text-align: left;
        }
        
        .data-table thead th:first-child {
            padding-left: 24px;
        }
        .data-table thead th:last-child {
            padding-right: 24px;
        }

        /* Table Body Rows - NO vertical borders */
        .data-table tbody tr {
            transition: var(--transition-3d);
            border-radius: 20px;
        }
        
        .data-table tbody td {
            padding: 18px 20px;
            background: #ffffff;
            border: none; /* HAPUS SEMUA BORDER */
            vertical-align: middle;
            white-space: nowrap;
        }

        .data-table tbody td:first-child {
            border-radius: 20px 0 0 20px;
            padding-left: 24px;
        }
        .data-table tbody td:last-child {
            border-radius: 0 20px 20px 0;
            padding-right: 24px;
        }

        /* Warna berdasarkan Role - HANYA border-left pada baris */
        .row-role-admin td:first-child {
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%) !important;
            border-left: 4px solid #2563eb !important;
        }
        .row-role-admin td {
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%) !important;
        }
        
        .row-role-foto td:first-child {
            background: linear-gradient(135deg, #fff5f6 0%, #ffffff 100%) !important;
            border-left: 4px solid var(--p-pink) !important;
        }
        .row-role-foto td {
            background: linear-gradient(135deg, #fff5f6 0%, #ffffff 100%) !important;
        }
        
        .row-role-owner td:first-child {
            background: linear-gradient(135deg, #fbf7ff 0%, #ffffff 100%) !important;
            border-left: 4px solid #8b5cf6 !important;
        }
        .row-role-owner td {
            background: linear-gradient(135deg, #fbf7ff 0%, #ffffff 100%) !important;
        }

        .data-table tbody tr:hover td {
            transform: translateY(-3px);
            box-shadow: none; /* Hapus shadow pada hover */
        }

        /* Column specific styling */
        .td-no {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-dark);
            width: 50px;
        }

        .td-nik {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 600;
            width: 130px;
        }

        .td-nama {
            width: 250px;
            min-width: 250px;
        }

        .td-nama-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .td-nama-text {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .td-hp {
            font-size: 0.85rem;
            color: #4a5568;
            font-weight: 600;
            width: 140px;
        }

        .td-kelamin {
            font-size: 0.85rem;
            color: #4a5568;
            font-weight: 600;
            width: 100px;
        }

        .td-role {
            width: 110px;
        }

        .td-role .badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 50px;
            display: inline-block;
            white-space: nowrap;
        }

        .td-status {
            width: 80px;
            text-align: center;
        }

        .td-aksi {
            width: 200px; /* Lebih lebar untuk 3 tombol */
            text-align: center;
        }

        /* Foto Profil */
        .profile-table-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }
        .profile-table-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Tombol Aksi - Detail, Edit, Delete */
        .btn-action-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-3d);
            border: 1.5px solid #eef2f6;
            background: #ffffff;
            font-size: 0.9rem;
            text-decoration: none;
            margin: 0 2px;
        }
        
        .btn-action-detail { 
            color: #2563eb; 
            border-color: #dbeafe;
        }
        .btn-action-detail:hover { 
            background: #2563eb; 
            color: #ffffff; 
            border-color: #2563eb; 
            transform: translateY(-2px); 
        }
        
        .btn-action-edit { 
            color: var(--p-pink); 
            border-color: #fce7f3;
        }
        .btn-action-edit:hover { 
            background: var(--p-pink); 
            color: #ffffff; 
            border-color: var(--p-pink); 
            transform: translateY(-2px); 
        }
        
        .btn-action-delete { 
            color: #dc2626; 
            border-color: #fee2e2;
        }
        .btn-action-delete:hover { 
            background: #dc2626; 
            color: #ffffff; 
            border-color: #dc2626; 
            transform: translateY(-2px); 
        }

        /* Sakelar Toggle Status */
        .switch-toggle {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
            cursor: pointer;
            transition: var(--transition-3d);
        }
        .switch-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider-toggle {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .slider-toggle:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input:checked + .slider-toggle {
            background-color: var(--p-pink);
        }
        input:checked + .slider-toggle:before {
            transform: translateX(20px);
        }

        /* ============================================================
           PAGINATION STYLING - SLIDE DATA
           ============================================================ */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px 24px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.04);
        }

        .pagination-info {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 600;
        }

        .pagination-info span {
            color: var(--p-pink);
            font-weight: 700;
        }

        .pagination-nav {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .page-link-pag {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            background: #ffffff;
            border: 2px solid #eef2f6;
            color: #4a5568;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-3d);
        }

        .page-link-pag:hover {
            background: var(--light-pink);
            border-color: var(--p-pink);
            color: var(--p-pink);
            transform: translateY(-2px);
        }

        .page-link-pag.active-pag {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important;
            border-color: var(--p-pink) !important;
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.3);
        }

        .page-link-pag.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Profile Header */
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* Profile Preview Box */
        .profile-preview-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        .profile-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .sidebar {
                transform: translateX(-100%);
            }
        }

        /* Animasi */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in-up {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>

    <!-- Bilah Samping -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
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
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                        <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuLaporan">
                        <ul class="list-unstyled">
                            <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                            <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                            <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                            <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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

    <!-- Area Konten Utama -->
    <div class="main-content">
        
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Kelola Master Karyawan ⚙</h3>
                <p class="text-muted small mb-0">Lakukan penambahan, pengeditan, serta pengarsipan staf aktif SpotLight Studio.</p>
            </div>
            <a href="tambah.php" class="btn btn-reg-header shadow text-decoration-none">
                <i class="bi bi-person-plus-fill me-2"></i>Tambah Karyawan
            </a>
        </div>

        <!-- KARTU PROFIL AKUN ANDA (OWNER) -->
        <div class="card-3d mb-4 p-4" style="background: linear-gradient(135deg, rgba(216, 63, 103, 0.04), rgba(255, 255, 255, 0.98)); border-left: 6px solid var(--p-pink) !important;">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start">
                    <div class="profile-preview-box shadow-sm" style="width: 80px; height: 80px; border: 3px solid #ffffff;">
                        <img src="<?= $foto_owner_src ?>" alt="Foto Profil Anda">
                    </div>
                </div>
                <div class="col-md-7 mt-3 mt-md-0">
                    <span class="badge rounded-pill px-3 py-1 text-white text-uppercase mb-2" style="font-size: 0.68rem; font-weight: 800; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;"><i class="bi bi-person-fill-check me-1"></i>Akun Anda (Pemilik)</span>
                    <h4 class="fw-bold mb-1" style="font-size: 1.6rem;"><?= htmlspecialchars($nama_owner) ?></h4>
                    <p class="text-muted small mb-0" style="font-size: 0.9rem;">NIK: <strong class="text-dark"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></strong> &nbsp;|&nbsp; Username: <strong class="text-dark">@<?= htmlspecialchars($username_owner) ?></strong> &nbsp;|&nbsp; Email: <strong class="text-dark"><?= htmlspecialchars($email_owner) ?></strong></p>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <a href="../../Role/Owner/index.php" class="btn py-2 px-4 border" style="border-radius: 12px; border-color: var(--p-pink) !important; color: var(--p-pink); font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-block;">
                        <i class="bi bi-gear-fill me-1"></i>Kelola Profil Anda
                    </a>
                </div>
            </div>
        </div>

        <!-- BARIS INDIKATOR SEBARAN STAF -->
        <div class="row g-4 mb-4" data-aos="fade-up">
            <div class="col-md-4">
                <div class="card-3d d-flex align-items-center gap-3 py-3" style="border-left: 5px solid #2563eb !important;">
                    <div class="stat-icon" style="background: #eff6ff; color: #2563eb;"><i class="bi bi-person-workspace"></i></div>
                    <div>
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Jumlah Admin</small>
                        <h4 class="fw-bold mb-0" style="color: var(--text-dark); font-size: 1.5rem;"><?= $total_admin ?> <span style="font-size: 0.85rem; font-weight: 600; color: #718096;">Orang</span></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-3d d-flex align-items-center gap-3 py-3" style="border-left: 5px solid var(--p-pink) !important;">
                    <div class="stat-icon" style="background: var(--s-pink); color: var(--p-pink);"><i class="bi bi-camera-fill"></i></div>
                    <div>
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Jumlah Fotografer</small>
                        <h4 class="fw-bold mb-0" style="color: var(--text-dark); font-size: 1.5rem;"><?= $total_foto ?> <span style="font-size: 0.85rem; font-weight: 600; color: #718096;">Orang</span></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-3d d-flex align-items-center gap-3 py-3" style="border-left: 5px solid #8b5cf6 !important;">
                    <div class="stat-icon" style="background: #f5f3ff; color: #8b5cf6;"><i class="bi bi-person-fill-check"></i></div>
                    <div>
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Jumlah Pemilik (Owner)</small>
                        <h4 class="fw-bold mb-0" style="color: var(--text-dark); font-size: 1.5rem;"><?= $total_owner ?> <span style="font-size: 0.85rem; font-weight: 600; color: #718096;">Orang</span></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- AREA TABEL & PENCARIAN & FILTER -->
        <div class="card-3d mb-4" style="background-color: #ffffff;">
            
            <form method="GET" class="search-filter-wrapper">
                <input type="hidden" name="status" value="<?= htmlspecialchars(@$_GET['status']) ?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars(@$_GET['role']) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars(@$_GET['sort']) ?>">

                <input type="text" name="cari" class="form-control" placeholder="Cari nama, NIK, atau email..." value="<?= htmlspecialchars(@$_GET['cari']) ?>" style="border-radius: 14px; border: 2px solid #eef2f6; padding: 12px 18px; font-weight: 600;">
                <button type="submit" class="btn btn-reg-header px-4 py-2 mt-0" style="border-radius: 14px !important;"><i class="bi bi-search me-2"></i>Cari</button>
                <button type="button" class="btn-filter-toggle" onclick="toggleFilterPanel()"><i class="bi bi-funnel-fill"></i> Saring Data</button>

                <!-- PANEL FILTER OVERLAY MELAYANG -->
                <div class="filter-dropdown-panel shadow-lg" id="filterPanel">
                    <h6 class="fw-bold mb-3">Saringan & Urutan</h6>
                    
                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.85rem;">Urutkan Berdasarkan</label>
                        <select name="sort" class="form-select" style="border-radius: 12px; border: 2px solid #eef2f6;">
                            <option value="nama_asc" <?= (@$_GET['sort'] == 'nama_asc') ? 'selected' : '' ?>>Nama (A - Z)</option>
                            <option value="nama_desc" <?= (@$_GET['sort'] == 'nama_desc') ? 'selected' : '' ?>>Nama (Z - A)</option>
                            <option value="umur_muda" <?= (@$_GET['sort'] == 'umur_muda') ? 'selected' : '' ?>>Umur Termuda</option>
                            <option value="umur_tua" <?= (@$_GET['sort'] == 'umur_tua') ? 'selected' : '' ?>>Umur Tertua</option>
                            <option value="baru" <?= (@$_GET['sort'] == 'baru') ? 'selected' : '' ?>>Baru Ditambahkan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.85rem;">Status Akun</label>
                        <select name="status" class="form-select" style="border-radius: 12px; border: 2px solid #eef2f6;">
                            <option value="" selected>Semua Status</option>
                            <option value="1" <?= (@$_GET['status'] == '1') ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= (@$_GET['status'] == '0') ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.85rem;">Peran Kerja (Role)</label>
                        <select name="role" class="form-select" style="border-radius: 12px; border: 2px solid #eef2f6;">
                            <option value="" selected>Semua Peran</option>
                            <option value="Admin" <?= (@$_GET['role'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Fotografer" <?= (@$_GET['role'] == 'Fotografer') ? 'selected' : '' ?>>Fotografer</option>
                            <option value="Owner" <?= (@$_GET['role'] == 'Owner') ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-reg-header mt-0 py-2 w-100" style="border-radius: 14px !important;">Terapkan Filter ✦</button>
                </div>
            </form>

            <!-- ============================================================
               TABEL SCROLL HORIZONTAL - NO BORDER GARIS VERTICAL
               ============================================================ -->
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="td-no">No</th>
                            <th class="td-nik">NIK</th>
                            <th class="td-nama">Nama Lengkap</th>
                            <th class="td-hp">Nomor Telepon</th>
                            <th class="td-kelamin">Jenis Kelamin</th>
                            <th class="td-role">Peran</th>
                            <th class="td-status">Status</th>
                            <th class="td-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // =====================================================
                        // LOGIKA HALAMAN / PAGINATION SERVER SIDE (SQL SERVER)
                        // =====================================================
                        $limit = 10; // Maksimal 10 data per halaman
                        $halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
                        if ($halaman < 1) $halaman = 1;
                        $offset = ($halaman - 1) * $limit;

                        $cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
                        $status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
                        $role_filter = isset($_GET['role']) ? trim($_GET['role']) : "";
                        $sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

                        // Kunci Logika: Menghapus data diri Owner ($id_owner) agar tidak tampil di daftar
                        $conditions = array("Is_Deleted = 0", "ID_Karyawan != ?");
                        $params = array($id_owner);

                        if (!empty($cari)) {
                            $conditions[] = "(Nama_Karyawan LIKE ? OR NIK LIKE ? OR Email_Karyawan LIKE ?)";
                            $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
                        }
                        if ($status_filter !== "") {
                            $conditions[] = "Status = ?";
                            $params[] = (int)$status_filter;
                        }
                        if (!empty($role_filter)) {
                            $conditions[] = "Role_Karyawan = ?";
                            $params[] = $role_filter;
                        }

                        $order_clause = "Nama_Karyawan ASC";
                        if ($sort == "nama_desc") { $order_clause = "Nama_Karyawan DESC"; }
                        elseif ($sort == "umur_muda") { $order_clause = "Tanggal_Lahir DESC"; }
                        elseif ($sort == "umur_tua") { $order_clause = "Tanggal_Lahir ASC"; }
                        elseif ($sort == "baru") { $order_clause = "Created_Date DESC"; }

                        // Hitung total baris untuk pagination
                        $sql_count = "SELECT COUNT(*) AS total FROM Karyawan WHERE " . implode(" AND ", $conditions);
                        $query_count = sqlsrv_query($conn, $sql_count, $params);
                        $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
                        $total_records = $row_count['total'] ?? 0;
                        $total_halaman = ceil($total_records / $limit);

                        // Ambil data halaman aktif menggunakan OFFSET FETCH (Standard SQL Server)
                        $sql_list = "SELECT * FROM Karyawan WHERE " . implode(" AND ", $conditions) . " ORDER BY " . $order_clause . " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                        
                        $params_list = $params;
                        $params_list[] = $offset;
                        $params_list[] = $limit;

                        $query_list = sqlsrv_query($conn, $sql_list, $params_list);

                        $no = $offset + 1;
                        if ($query_list && sqlsrv_has_rows($query_list)):
                            while($row = sqlsrv_fetch_array($query_list, SQLSRV_FETCH_ASSOC)):
                                
                                $foto_staf = $row['Foto_Profil'] ?? 'default.jpg';
                                $foto_staf_src = ($foto_staf != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_staf)) ? "../../assets/img/pelanggan/" . $foto_staf : $default_svg_avatar;

                                // KELAS WARNA BERDASARKAN PERAN
                                $class_warna_role = "";
                                if ($row['Role_Karyawan'] == 'Admin') $class_warna_role = "row-role-admin";
                                elseif ($row['Role_Karyawan'] == 'Fotografer') $class_warna_role = "row-role-foto";
                                elseif ($row['Role_Karyawan'] == 'Owner') $class_warna_role = "row-role-owner";

                                // Badge role color
                                $role_badge_class = "bg-secondary";
                                if ($row['Role_Karyawan'] == 'Admin') $role_badge_class = "bg-primary";
                                elseif ($row['Role_Karyawan'] == 'Fotografer') $role_badge_class = "bg-danger";
                                elseif ($row['Role_Karyawan'] == 'Owner') $role_badge_class = "bg-warning text-dark";
                        ?>
                                <tr class="<?= $class_warna_role ?> fade-in-up">
                                    <td class="td-no"><?= $no++ ?></td>
                                    <td class="td-nik"><?= htmlspecialchars($row['NIK']) ?></td>
                                    <td class="td-nama">
                                        <div class="td-nama-content">
                                            <div class="profile-table-avatar">
                                                <img src="<?= $foto_staf_src ?>" alt="Foto Profil">
                                            </div>
                                            <span class="td-nama-text" title="<?= htmlspecialchars($row['Nama_Karyawan']) ?>">
                                                <?= htmlspecialchars($row['Nama_Karyawan']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="td-hp"><?= htmlspecialchars($row['No_Hp']) ?></td>
                                    <td class="td-kelamin"><?= htmlspecialchars($row['Jenis_Kelamin']) ?></td>
                                    <td class="td-role">
                                        <span class="badge <?= $role_badge_class ?>"><?= htmlspecialchars($row['Role_Karyawan']) ?></span>
                                    </td>
                                    <td class="td-status">
                                        <label class="switch-toggle" onclick="location.href='toggle_status.php?id=<?= $row['ID_Karyawan'] ?>'" title="Klik untuk mengubah keaktifan staf">
                                            <input type="checkbox" <?= ($row['Status'] == 1) ? 'checked' : '' ?> disabled>
                                            <span class="slider-toggle"></span>
                                        </label>
                                    </td>
                                    <td class="td-aksi">
                                        <!-- TOMBOL DETAIL - TAMPIL LAGI -->
                                        <button class="btn-action-circle btn-action-detail" 
                                                data-nik="<?= htmlspecialchars($row['NIK']) ?>"
                                                data-nama="<?= htmlspecialchars($row['Nama_Karyawan']) ?>"
                                                data-username="<?= htmlspecialchars($row['Username_Karyawan']) ?>"
                                                data-email="<?= htmlspecialchars($row['Email_Karyawan']) ?>"
                                                data-jk="<?= htmlspecialchars($row['Jenis_Kelamin']) ?>"
                                                data-dob="<?= $row['Tanggal_Lahir'] ? $row['Tanggal_Lahir']->format('d M Y') : '-' ?>"
                                                data-role="<?= htmlspecialchars($row['Role_Karyawan']) ?>"
                                                data-hp="<?= htmlspecialchars($row['No_Hp']) ?>"
                                                data-alamat="<?= htmlspecialchars($row['Alamat']) ?>"
                                                data-status="<?= $row['Status'] == 1 ? 'Aktif' : 'Tidak Aktif' ?>"
                                                data-foto="<?= $foto_staf_src ?>"
                                                onclick="bukaModalDetailV2(this)"
                                                title="Lihat Detail Karyawan">
                                            <i class="bi bi-search"></i>
                                        </button>

                                        <!-- TOMBOL EDIT -->
                                        <a href="edit.php?id=<?= $row['ID_Karyawan'] ?>" class="btn-action-circle btn-action-edit" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        
                                        <!-- TOMBOL HARD DELETE - TAMPIL LAGI -->
                                        <button class="btn-action-circle btn-action-delete" onclick="confirmSoftDelete(<?= $row['ID_Karyawan'] ?>)" title="Hapus Karyawan">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i>
                                    <p class="fw-bold">Tidak ada data karyawan yang sesuai saringan.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ============================================================
               PAGINATION / SLIDE DATA - 10 DATA PER HALAMAN
               ============================================================ -->
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> karyawan
                </div>
                <nav class="pagination-nav">
                    <?php if ($halaman > 1): ?>
                        <a class="page-link-pag" href="index.php?halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&sort=<?= $sort ?>" title="Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php 
                    // Logic untuk menampilkan nomor halaman yang relevan
                    $start_page = max(1, $halaman - 2);
                    $end_page = min($total_halaman, $halaman + 2);
                    
                    if ($start_page > 1) {
                        echo '<a class="page-link-pag" href="index.php?halaman=1&cari=' . urlencode($cari) . '&status=' . $status_filter . '&role=' . $role_filter . '&sort=' . $sort . '">1</a>';
                        if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="index.php?halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&sort=<?= $sort ?>">
                            <?= $i ?>
                        </a>
                    <?php 
                    endfor; 
                    
                    if ($end_page < $total_halaman) {
                        if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
                        echo '<a class="page-link-pag" href="index.php?halaman=' . $total_halaman . '&cari=' . urlencode($cari) . '&status=' . $status_filter . '&role=' . $role_filter . '&sort=' . $sort . '">' . $total_halaman . '</a>';
                    }
                    ?>
                    
                    <?php if ($halaman < $total_halaman): ?>
                        <a class="page-link-pag" href="index.php?halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>&status=<?= $status_filter ?>&role=<?= $role_filter ?>&sort=<?= $sort ?>" title="Selanjutnya">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> karyawan
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- MODAL DETAIL LENGKAP KARYAWAN -->
    <div class="modal fade" id="modalDetailKaryawan" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Detail Data Karyawan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
                <img id="d_foto" src="" alt="Foto Profil">
              </div>
              <h5 class="fw-bold text-dark mt-3 mb-1" id="d_nama"></h5>
              <span class="badge bg-danger px-3 py-1 text-white text-uppercase" id="d_role" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;"></span>
            </div>

            <div class="card-3d p-3 border-0 mb-3" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_nik"></span>
                </div>
                <div class="col-6">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nama Pengguna</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_username"></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Email</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_email"></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_jk"></span>
                </div>
                <div class="col-6 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Tanggal Lahir</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_dob"></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nomor Telepon</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_hp"></span>
                </div>
                <div class="col-12 border-top pt-2">
                  <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Domisili</small>
                  <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_alamat"></span>
                </div>
              </div>
            </div>
            
            <button class="btn btn-reg-header shadow-sm py-3 mt-0 w-100" data-bs-dismiss="modal" style="border-radius: 14px !important;">Tutup Detail</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Script JS Vendor -->
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleFilterPanel() {
            document.getElementById('filterPanel').classList.toggle('show');
        }

        window.addEventListener('mouseup', function(event) {
            const panel = document.getElementById('filterPanel');
            const btn = document.querySelector('.btn-filter-toggle');
            if (event.target != panel && !panel.contains(event.target) && event.target != btn && !btn.contains(event.target)) {
                panel.classList.remove('show');
            }
        });

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

        // Fungsi Membuka Detail Karyawan V2 - TAMPIL LAGI
        function bukaModalDetailV2(button) {
            const ds = button.dataset;
            document.getElementById('d_nik').innerText = ds.nik;
            document.getElementById('d_nama').innerText = ds.nama;
            document.getElementById('d_username').innerText = '@' + ds.username;
            document.getElementById('d_email').innerText = ds.email;
            document.getElementById('d_jk').innerText = ds.jk;
            document.getElementById('d_dob').innerText = ds.dob;
            document.getElementById('d_role').innerText = ds.role;
            document.getElementById('d_hp').innerText = ds.hp;
            document.getElementById('d_alamat').innerText = ds.alamat;
            document.getElementById('d_foto').src = ds.foto;

            var modalDetail = new bootstrap.Modal(document.getElementById('modalDetailKaryawan'));
            modalDetail.show();
        }

        // Konfirmasi Hapus Lembut - TAMPIL LAGI
        function confirmSoftDelete(id) {
            Swal.fire({
                title: 'Hapus Karyawan? 🗑️',
                text: 'Data karyawan ini akan dinonaktifkan dan disembunyikan dari sistem harian SpotLight.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'hapus_lembut.php?id=' + id;
                }
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem? ❌',
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
                title: 'Kembali ke Beranda? ✦',
                text: 'Anda akan dialihkan kembali ke halaman utama publik SpotLight Studio.',
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
    </script>

    <!-- Notifikasi Sukses Redirect Pasca Aksi CRUD -->
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = "";
        let t_icon = "success";
        let t_title = "Berhasil! 🎉";

        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Karyawan baru berhasil didaftarkan ke sistem!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data karyawan berhasil diperbarui secara akurat!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'lembut') { msg = "Karyawan dinonaktifkan dari operasional."; t_title = "Dihapus! 🗑️"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'permanen') { msg = "Karyawan dihapus selamanya dari database."; t_title = "Dihapus! 🗑️"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'status_ubah') { msg = "Status keaktifan karyawan berhasil diubah!"; t_title = "Status Diubah! ⚙"; }

        Swal.fire({
            icon: t_icon,
            title: t_title,
            text: msg,
            confirmButtonColor: '#d83f67'
        });
    </script>
    <?php endif; ?>
</body>
</html>