<?php
session_start();
// PERBAIKAN PATH: koneksi naik 3 tingkat
include '../../../koneksi.php';

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
    // PERBAIKAN PATH: redirect naik 3 tingkat ke login.php di root
    header("Location: ../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil ---
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
// PERBAIKAN PATH: letak foto profil naik 3 tingkat ke root
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// QUERY: Hasil Foto yang bisa diakses customer
// =====================================================
$q_hasil = sqlsrv_query($conn, "{CALL sp_ReadHasilFotoRingkasanCustomer(?)}", array($id_customer));

// =====================================================
// QUERY: Sesi yang sudah ada hasil foto tapi order belum Lunas
// =====================================================
$q_menunggu = sqlsrv_query($conn, "{CALL sp_HitungHasilFotoMenungguCustomer(?)}", array($id_customer));
$d_menunggu = $q_menunggu ? sqlsrv_fetch_array($q_menunggu, SQLSRV_FETCH_ASSOC) : null;
$total_menunggu = $d_menunggu['Total_Menunggu'] ?? 0;

function formatUkuran($bytes) {
    if ($bytes <= 0) return '0 KB';
    $units = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) $date = new DateTime($date);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $date->format('d').' '.$bulan[intval($date->format('m'))-1].' '.$date->format('Y');
}
function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) $time = new DateTime($time);
    return $time->format('H:i');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Foto Saya - SpotLight Studio</title>
    <!-- PERBAIKAN PATH: pemanggilan aset CSS naik 3 tingkat -->
    <link href="../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SINKRONISASI VARIABEL DESIGN SYSTEM DARI RIWAYAT.PHP */
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-soft: 0 4px 24px rgba(0, 0, 0, 0.06);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 48px rgba(216, 63, 103, 0.18);
            --shadow-glow: 0 0 40px rgba(216, 63, 103, 0.15);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
            --transition-smooth: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
            background-attachment: fixed;
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* ===== NAVBAR ATAS SINKRON ===== */
        .top-navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 14px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
            border-bottom: 1px solid var(--glass-border);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.7rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
            transition: var(--transition-smooth);
        }
        .nav-logo:hover { transform: scale(1.02); }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.85rem; }
        
        .nav-menu-center {
            display: flex;
            gap: 36px;
            align-items: center;
        }
        .nav-link-item {
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: var(--transition-smooth);
            padding: 8px 4px;
            position: relative;
        }
        .nav-link-item::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 3px;
            transition: var(--transition-smooth);
            transform: translateX(-50%);
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item:hover::after, .nav-link-item.active::after {
            width: 100%;
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
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-btn-booking:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 28px rgba(216, 63, 103, 0.4);
            color: #fff;
        }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid var(--light-pink);
            cursor: pointer;
            transition: var(--transition-smooth);
            box-shadow: 0 2px 8px rgba(216, 63, 103, 0.15);
        }
        .nav-avatar:hover {
            transform: scale(1.12) rotate(3deg);
            border-color: var(--p-pink);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .nav-dropdown {
            position: absolute;
            top: 58px;
            right: -8px;
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card), 0 0 0 1px rgba(0,0,0,0.04);
            padding: 12px;
            min-width: 240px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--glass-border);
            animation: dropdownSlide 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .nav-dropdown.show { display: block; }
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition-smooth);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .dropdown-item i { font-size: 1.1rem; width: 22px; text-align: center; }
        .dropdown-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 8px 0;
        }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fef2f2; }
        .dropdown-header {
            padding: 10px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== BREADCRUMB BAR SINKRON ===== */
        .breadcrumb-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            padding: 14px 40px;
            border-bottom: 1px solid var(--glass-border);
        }
        .breadcrumb-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition-smooth);
            padding: 4px 8px;
            border-radius: 8px;
        }
        .breadcrumb-inner a:hover { 
            color: var(--p-pink); 
            background: var(--s-pink);
        }
        .breadcrumb-inner .separator { color: #cbd5e1; font-size: 0.75rem; }
        .breadcrumb-inner .current { 
            color: var(--p-pink); 
            font-weight: 800; 
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            padding: 4px 14px;
            border-radius: 20px;
        }

        /* ===== MAIN CONTAINER ===== */
        .main-container {
            padding: 32px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title h1 {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title h1 i { color: var(--p-pink); font-size: 1.5rem; }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ===== WAITING CARD SINKRON ===== */
        .waiting-card {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fcd34d;
            border-radius: var(--radius-lg);
            padding: 24px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-soft);
        }
        .waiting-icon {
            width: 50px;
            height: 50px;
            background: #f59e0b;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .waiting-card h4 {
            font-weight: 800;
            color: #92400e;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
        .waiting-card p {
            color: #b45309;
            font-size: 0.85rem;
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* ===== INFO ALERT SINKRON ===== */
        .info-alert {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: var(--radius-md);
            padding: 18px 24px;
            margin-bottom: 32px;
            border: 1px solid #bfdbfe;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: var(--shadow-soft);
        }
        .info-alert i { font-size: 1.4rem; color: #1e40af; }
        .info-alert-text {
            font-size: 0.85rem;
            color: #1e40af;
            font-weight: 500;
            line-height: 1.5;
        }

        /* ===== ORDER CARDS SINKRON DENGAN RIWAYAT.PHP ===== */
        .orders-container { display: flex; flex-direction: column; gap: 20px; }
        .order-card {
            background: var(--glass-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            transition: var(--transition-smooth);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(16px);
            animation: fadeSlideUp 0.5s ease forwards;
        }
        .order-card:hover { 
            box-shadow: var(--shadow-card); 
            border-color: var(--light-pink); 
            transform: translateY(-3px);
        }
        .order-card.lunas { border-left: 4px solid var(--success); }

        .order-header {
            padding: 18px 24px;
            background: linear-gradient(135deg, #fafafa 0%, #f8fafc 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-id { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .order-id strong { color: var(--p-pink); font-size: 1rem; font-weight: 900; }
        .order-date { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .order-date i { color: var(--p-pink); }

        .order-body { padding: 24px; }
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        @media (max-width: 992px) { .order-grid { grid-template-columns: 1fr; } }

        .paket-section { display: flex; gap: 16px; }
        .paket-img {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-md);
            object-fit: cover;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border: 3px solid #ffffff;
            transition: var(--transition-smooth);
        }
        .paket-section:hover .paket-img { transform: scale(1.05) rotate(2deg); }
        .paket-info h3 { color: var(--text-dark); font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; }
        .paket-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .paket-meta span {
            background: var(--s-pink);
            color: var(--p-pink);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .paket-price { font-size: 1.3rem; font-weight: 900; color: var(--p-pink); letter-spacing: -0.5px; }

        .detail-section { display: flex; flex-direction: column; gap: 10px; }
        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #fafafa, #ffffff);
            border-radius: var(--radius-md);
            transition: var(--transition-smooth);
            border: 1px solid transparent;
        }
        .detail-item:hover {
            transform: translateX(4px);
            border-color: var(--light-pink);
            box-shadow: 0 4px 12px rgba(216, 63, 103, 0.06);
        }
        .detail-item i {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--s-pink);
            color: var(--p-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
            transition: var(--transition-smooth);
        }
        .detail-item:hover i { background: var(--p-pink); color: #fff; transform: scale(1.1); }
        .detail-item .detail-label { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 2px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-item .detail-value { font-size: 0.9rem; color: var(--text-dark); font-weight: 700; }

        /* ===== FOOTER CARDS / AKSI BAR ===== */
        .order-aksi {
            padding: 16px 24px;
            background: linear-gradient(135deg, #fafafa, #f8fafc);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            border-top: 1px solid #f1f5f9;
        }
        .btn-aksi {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none !important;
            transition: var(--transition-smooth);
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .btn-aksi:hover { transform: translateY(-2px); box-shadow: var(--shadow-card); }
        
        .btn-download-zip {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff !important;
        }
        .btn-download-zip:hover { box-shadow: 0 6px 20px rgba(216, 63, 103, 0.3); }

        .btn-buka-galeri {
            background: #1e1e24;
            color: #fff !important;
        }
        .btn-buka-galeri:hover { background: #32323d; }

        /* ===== BADGES ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .badge-lunas { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

        /* ===== EMPTY STATE SINKRON ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeSlideUp 0.5s ease forwards;
        }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 20px; display: block; }
        .empty-state h3 { color: var(--text-dark); font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        .empty-state .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.85rem;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 16px rgba(216, 63, 103, 0.25);
        }
        .empty-state .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(216, 63, 103, 0.35); }

        /* ===== POPUP MODAL LIGHTBOX SINKRON VARIABEL RIWAYAT.PHP ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal-content-popup {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-xl);
            padding: 0;
            width: 100%;
            max-width: 900px;
            box-shadow: var(--shadow-hover);
            animation: modalIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header-popup {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header-popup h3 {
            font-size: 1.2rem;
            font-weight: 900;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        .modal-close-btn:hover { background: rgba(255,255,255,0.4); transform: rotate(90deg); }
        .modal-body-popup { padding: 32px; text-align: center; }

        /* Detail Slider */
        .lightbox-wrapper {
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            user-select: none;
        }
        .lightbox-main {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            gap: 20px;
            position: relative;
            margin: 10px 0;
        }
        .lightbox-img-container {
            max-width: 100%;
            max-height: 60vh;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-card);
        }
        .lightbox-img-container img {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
            display: block;
            transition: var(--transition-smooth);
        }
        .lightbox-nav-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--transition-smooth);
            z-index: 10;
            flex-shrink: 0;
        }
        .lightbox-nav-btn:hover {
            background: var(--p-pink);
            border-color: var(--p-pink);
            transform: scale(1.1);
            color: #ffffff;
        }
        .lightbox-details {
            text-align: center;
            color: #ffffff;
            margin-top: 15px;
        }
        .lightbox-caption {
            font-size: 0.95rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 4px;
        }
        .lightbox-counter {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .btn-download-single {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #ffffff;
            background: rgba(216, 63, 103, 0.2);
            border: 1px solid rgba(216, 63, 103, 0.4);
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 800;
            transition: var(--transition-smooth);
        }
        .btn-download-single:hover {
            background: var(--p-pink);
            color: #ffffff;
            border-color: var(--p-pink);
            transform: translateY(-2px);
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .top-navbar { padding: 14px 20px; }
            .nav-menu-center { display: none; }
            .main-container { padding: 20px; }
            .breadcrumb-bar { padding: 14px 20px; }
            .modal-content-popup { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .lightbox-main { gap: 10px; }
            .lightbox-nav-btn { width: 38px; height: 38px; font-size: 1rem; }
        }
        @media (max-width: 480px) {
            .order-header { flex-direction: column; align-items: flex-start; }
            .paket-section { flex-direction: column; }
            .btn-aksi { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS SINKRON -->
    <nav class="top-navbar">
        <a href="../../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../index.php" class="nav-link-item">Dashboard</a>
            <a href="../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="hasil_foto.php" class="nav-link-item active">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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
            <a href="../index.php">Home</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current"><i class="bi bi-images"></i> Hasil Foto Saya</span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- PAGE HEADER SINKRON -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="bi bi-images"></i> Hasil Foto Saya</h1>
                <p>Unduh dokumentasi hasil pemotretan dari setiap sesi pemotretan Anda</p>
            </div>
        </div>

        <?php if ($total_menunggu > 0): ?>
        <!-- INFO: Menunggu Pelunasan -->
        <div class="waiting-card">
            <div class="waiting-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <h4>Ada <?= (int)$total_menunggu ?> Hasil Foto Menunggu Pelunasan</h4>
                <p>Proses unggah hasil foto oleh fotografer telah selesai. Namun berkas belum dapat diakses sepenuhnya karena status administrasi order Anda masih menunggu pelunasan. Silakan selesaikan pelunasan pembayaran Anda terlebih dahulu pada tab Riwayat.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- INFO ALERT -->
        <div class="info-alert">
            <i class="bi bi-info-circle-fill"></i>
            <div class="info-alert-text">
                Semua hasil foto di bawah ini siap diunduh secara instan. Anda dapat melihat, menggeser (*slide*) gambar, mengunduh satuan melalui tombol di dalam galeri, atau mengunduh seluruh file sekaligus dalam kemasan file ZIP.
            </div>
        </div>

        <!-- ORDERS CONTAINER (SINKRON DENGAN RIWAYAT.PHP) -->
        <div class="orders-container">
            <?php
            $has_data = false;
            if ($q_hasil && sqlsrv_has_rows($q_hasil)):
                $has_data = true;
                $card_delay = 0;
                while ($row = sqlsrv_fetch_array($q_hasil, SQLSRV_FETCH_ASSOC)):
                    $safe_id_order = htmlspecialchars($row['ID_Order'], ENT_QUOTES, 'UTF-8');
                    $card_delay += 0.05;
            ?>
                <div class="order-card lunas" style="animation-delay: <?= $card_delay; ?>s;">
                    <div class="order-header">
                        <div class="order-id">
                            <strong>#ORDER-<?= str_pad($row['ID_Order'], 4, '0', STR_PAD_LEFT) ?></strong>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <div class="order-date">
                                <i class="bi bi-calendar3"></i>
                                Diupload: <?= formatTanggal($row['Tanggal_Upload_Hasil']) ?>
                            </div>
                            <span class="badge badge-lunas"><i class="bi bi-check2-all"></i> Lunas &amp; Siap Diambil</span>
                        </div>
                    </div>

                    <div class="order-body">
                        <div class="order-grid">
                            <!-- KIRI: Paket Info -->
                            <div class="paket-section">
                                <?php 
                                $foto_paket = $row['Foto_Paket'] ?? 'default_paket.jpg';
                                $foto_src = file_exists("../../../assets/img/paket/" . $foto_paket) 
                                    ? "../../../assets/img/paket/" . $foto_paket 
                                    : "../../../assets/img/paket/default_paket.jpg";
                                ?>
                                <img src="<?= $foto_src ?>" alt="Paket" class="paket-img">
                                <div class="paket-info">
                                    <h3><?= htmlspecialchars($row['Nama_Paket'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <div class="paket-meta">
                                        <span><i class="bi bi-clock"></i><?= $row['Durasi_Waktu'] ?> menit</span>
                                        <span><i class="bi bi-people"></i>Max <?= (int)$row['Kapasitas_Orang'] ?> orang</span>
                                        <span><i class="bi bi-door-open"></i><?= htmlspecialchars($row['Nama_Ruangan'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="paket-price">Rp<?= number_format($row['Total_Harga'], 0, ',', '.') ?></div>
                                </div>
                            </div>

                            <!-- KANAN: Detail Info -->
                            <div class="detail-section">
                                <div class="detail-item">
                                    <i class="bi bi-calendar-check"></i>
                                    <div>
                                        <div class="detail-label">Tanggal Sesi</div>
                                        <div class="detail-value"><?= formatTanggal($row['Tanggal_Jadwal']) ?></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="bi bi-clock-history"></i>
                                    <div>
                                        <div class="detail-label">Jam Sesi</div>
                                        <div class="detail-value"><?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?> WIB</div>
                                    </div>
                                </div>
                                <div class="detail-item" style="background: linear-gradient(135deg, var(--s-pink), #ffffff);">
                                    <i class="bi bi-images" style="background: var(--p-pink); color: #fff;"></i>
                                    <div>
                                        <div class="detail-label">Jumlah Berkas</div>
                                        <div class="detail-value" style="color:var(--p-pink);font-weight:900;"><?= (int)$row['Total_Foto'] ?> foto (<?= formatUkuran($row['Total_Ukuran']) ?>)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AKSI BUTTONS SINKRON FORMAT BENTUK DAN WARNA -->
                    <div class="order-aksi">
                        <button type="button" class="btn-aksi btn-buka-galeri" onclick="lihatGaleriFoto(<?= $safe_id_order ?>)">
                            <i class="bi bi-images"></i> Buka Galeri Foto
                        </button>
                        <a href="download_zip.php?id_order=<?= $safe_id_order ?>" class="btn-aksi btn-download-zip">
                            <i class="bi bi-file-earmark-zip"></i> Download Semua (ZIP)
                        </a>
                    </div>
                </div>
            <?php 
                endwhile;
            endif;
            ?>
        </div>

        <?php if (!$has_data): ?>
        <!-- EMPTY STATE SINKRON -->
        <div class="empty-state">
            <i class="bi bi-images"></i>
            <h3>Belum Ada Hasil Foto</h3>
            <p>Sesi foto Anda belum memiliki berkas unggahan hasil yang siap diakses. Sesi dokumentasi akan tampil di sini secara otomatis setelah pelunasan selesai divalidasi admin pada menu Riwayat.</p>
            <a href="../Layanan/Paket/pilih_paket.php" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Mulai Booking Baru
            </a>
        </div>
        <?php endif; ?>

    </main>

    <!-- ===== POPUP MODAL LIGHTBOX SINKRON DENGAN RIWAYAT.PHP ===== -->
    <div class="modal-overlay" id="lightboxModal">
        <div class="modal-content-popup">
            <div class="modal-header-popup">
                <h3><i class="bi bi-images"></i> Galeri Hasil Foto</h3>
                <button class="modal-close-btn" onclick="closeLightbox()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body-popup">
                <div class="lightbox-wrapper">
                    <div class="lightbox-main">
                        <!-- Tombol Navigasi Kiri -->
                        <button class="lightbox-nav-btn" onclick="prevImage()" aria-label="Foto Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        
                        <!-- Kontainer Image -->
                        <div class="lightbox-img-container" id="lightboxTouchArea">
                            <img id="lightboxImg" src="" alt="Hasil Foto SpotLight Studio">
                        </div>
                        
                        <!-- Tombol Navigasi Kanan -->
                        <button class="lightbox-nav-btn" onclick="nextImage()" aria-label="Foto Selanjutnya">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="lightbox-details">
                        <div class="lightbox-caption" id="lightboxCaption">-</div>
                        <div class="lightbox-counter" id="lightboxCounter">0 dari 0</div>
                        <a href="#" id="lightboxDownloadBtn" download class="btn-download-single">
                            <i class="bi bi-cloud-arrow-down-fill"></i> Download Gambar Ini
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN PATH: Mengambil bootstrap bundle dari assets -->
    <script src="../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables untuk menyimpan array file gambar yang sedang aktif dibuka
        let activeGallery = [];
        let currentIndex = 0;

        // Toggle dropdown menu profile
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }
        
        // Menutup dropdown jika klik di luar area profil
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
                text: 'Anda akan dialihkan kembali menuju halaman publik SpotLight Studio.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../index.php';
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin mengakhiri sesi aktif akun Anda?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../logout.php';
                }
            });
        }

        // =====================================================
        // AJAX: AMBIL DATA HASIL FOTO & TAMPILKAN DAFTAR PREVIEW
        // =====================================================
        function lihatGaleriFoto(idOrder) {
            Swal.fire({
                title: 'Mempersiapkan Galeri...',
                html: 'Mohon tunggu sebentar...',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false
            });

            fetch('ajax_hasil_foto.php?id_order=' + idOrder, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => {
                    if (!res.ok) throw new Error('Koneksi bermasalah');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) {
                        Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan', text: data.message, confirmButtonColor: '#d83f67' });
                        return;
                    }

                    // Saring hanya file dengan tipe 'image'
                    activeGallery = data.files.filter(f => f.tipe === 'image');
                    
                    if (activeGallery.length === 0) {
                        Swal.fire({ 
                            icon: 'info', 
                            title: 'Galeri Kosong', 
                            text: 'Belum ada berkas foto berformat gambar yang dapat ditampilkan.', 
                            confirmButtonColor: '#d83f67' 
                        });
                        return;
                    }

                    // Tampilkan Grid Mini menggunakan SweetAlert2 untuk pemilihan awal gambar [1]
                    const gridHtml = activeGallery.map((f, idx) => {
                        return `<div style="cursor:pointer; overflow:hidden; border-radius:12px; position:relative; aspect-ratio:1/1;" onclick="startLightbox(${idx})">
                                    <img src="${f.url}" alt="${f.nama}" style="width:100%; height:100%; object-fit:cover; border:2px solid transparent; border-radius:12px; transition: var(--transition-smooth);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                </div>`;
                    }).join('');

                    Swal.fire({
                        title: 'Galeri Hasil Foto (' + activeGallery.length + ' file)',
                        html: `<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:5px 0;">${gridHtml}</div>
                              <p style="font-size:0.75rem; color:#718096; margin-top:15px; margin-bottom:0;">Klik pada salah satu foto untuk melihat layar penuh, lalu geser kiri atau kanan.</p>`,
                        width: 'min(620px, 94vw)',
                        confirmButtonColor: '#d83f67',
                        confirmButtonText: 'Tutup Galeri'
                    });
                })
                .catch(err => {
                    Swal.fire({ icon: 'error', title: 'Gagal Memuat', text: 'Koneksi gagal atau sesi terputus. Silakan muat ulang halaman.', confirmButtonColor: '#d83f67' });
                });
        }

        // =====================================================
        // LIGHTBOX CONTROLLER (GESER GAMBAR KANAN KIRI) SINKRON OVERLAY
        // =====================================================
        function startLightbox(index) {
            // Tutup Sweetalert Grid Mini
            Swal.close();
            
            // Atur index gambar saat ini
            currentIndex = index;
            updateLightboxContent();

            // Tampilkan Modal Lightbox dengan efek transisi aktif
            const modal = document.getElementById('lightboxModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Pasang Event Keyboard & Gestur Swipe
            document.addEventListener('keydown', handleKeyPress);
            initSwipeGestures();
        }

        function updateLightboxContent() {
            if (activeGallery.length === 0) return;
            
            const fileData = activeGallery[currentIndex];
            const imgEl = document.getElementById('lightboxImg');
            const captionEl = document.getElementById('lightboxCaption');
            const counterEl = document.getElementById('lightboxCounter');
            const downloadBtn = document.getElementById('lightboxDownloadBtn');

            // Set sumber gambar dan konten metadata pendukung
            imgEl.style.transform = 'scale(0.95)';
            imgEl.style.opacity = '0.7';

            setTimeout(() => {
                imgEl.src = fileData.url;
                imgEl.alt = fileData.nama;
                captionEl.textContent = fileData.nama;
                counterEl.textContent = `${currentIndex + 1} dari ${activeGallery.length}`;
                downloadBtn.href = fileData.url;
                
                imgEl.style.transform = 'scale(1)';
                imgEl.style.opacity = '1';
            }, 150);
        }

        function prevImage() {
            if (activeGallery.length <= 1) return;
            currentIndex = (currentIndex === 0) ? activeGallery.length - 1 : currentIndex - 1;
            updateLightboxContent();
        }

        function nextImage() {
            if (activeGallery.length <= 1) return;
            currentIndex = (currentIndex === activeGallery.length - 1) ? 0 : currentIndex + 1;
            updateLightboxContent();
        }

        function closeLightbox() {
            const modal = document.getElementById('lightboxModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';

            // Lepas event listener untuk menghemat memori
            document.removeEventListener('keydown', handleKeyPress);
        }

        // Handler untuk Keyboard Navigasi
        function handleKeyPress(e) {
            if (e.key === 'ArrowRight') {
                nextImage();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
            } else if (e.key === 'Escape') {
                closeLightbox();
            }
        }

        // Deteksi Gestur Swipe (Layar Sentuh Handphone/Mobile)
        let touchStartX = 0;
        let touchEndX = 0;

        function initSwipeGestures() {
            const touchArea = document.getElementById('lightboxTouchArea');
            if(!touchArea) return;

            touchArea.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            touchArea.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipeGesture();
            }, { passive: true });
        }

        function handleSwipeGesture() {
            const threshold = 50; // Sensitivitas geseran minimal (pixel)
            if (touchEndX < touchStartX - threshold) {
                nextImage(); // Swipe ke kiri -> Foto berikutnya
            }
            if (touchEndX > touchStartX + threshold) {
                prevImage(); // Swipe ke kanan -> Foto sebelumnya
            }
        }
    </script>
</body>
</html>