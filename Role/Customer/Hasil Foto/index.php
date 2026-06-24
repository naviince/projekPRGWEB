<?php
session_start();
include '../../../koneksi.php';

// =====================================================
// PROTEKSI HALAMAN - HANYA CUSTOMER
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../login.php");
    exit();
}

$id_pelanggan = $_SESSION['id_user'] ?? $_SESSION['id_pelanggan'] ?? null;
if (!$id_pelanggan) {
    header("Location: ../../login.php");
    exit();
}

// Ambil data pelanggan
$q_pelanggan = sqlsrv_query($conn, "SELECT * FROM Pelanggan WHERE ID_Pelanggan = ?", [$id_pelanggan]);
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
if ($d_pelanggan) { $d_pelanggan = array_change_key_case($d_pelanggan, CASE_LOWER); }
$nama_pelanggan = $d_pelanggan['nama_pelanggan'] ?? 'Pelanggan';
$foto_pelanggan = $d_pelanggan['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_pelanggan_src = ($foto_pelanggan != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_pelanggan)) 
    ? "../../assets/img/pelanggan/" . $foto_pelanggan 
    : $default_svg_avatar;

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_SELESAI_FOTO', 2);

// =====================================================
// AMBIL LIST HASIL FOTO (hanya yang sudah selesai/lunas + punya file)
// =====================================================
$sql = "
SELECT 
    sf.ID_Sesi_Foto,
    sf.ID_Order,
    sf.File_Hasil,
    sf.Tanggal_Upload_Hasil,
    sf.Status_Sesi,

    o.Tanggal_Order,
    o.Total_Harga,
    o.Rating,
    o.Review,

    p.ID_Paket,
    p.Nama_Paket,
    p.Durasi_Waktu,
    p.Harga_Paket,
    p.Foto_Paket,

    r.ID_Ruangan,
    r.Nama_Ruangan,

    t.ID_Tema,
    t.Nama_Tema,

    j.Tanggal_Jadwal,
    j.Jam_Mulai,
    j.Jam_Selesai,

    k.ID_Karyawan as ID_Fotografer,
    k.Nama_Karyawan as Nama_Fotografer,
    k.Username_Karyawan as Username_Fotografer,
    k.Foto_Profil as Foto_Fotografer

FROM Sesi_Foto sf
JOIN [Order] o ON sf.ID_Order = o.ID_Order
JOIN Paket_Foto p ON o.ID_Paket = p.ID_Paket
JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Karyawan k ON sf.ID_Karyawan = k.ID_Karyawan
WHERE o.ID_Pelanggan = ? 
    AND o.Status_Order IN (?, ?)
    AND sf.Status_Sesi = 1
    AND sf.File_Hasil IS NOT NULL
    AND sf.Status = 1
    AND o.Is_Deleted = 0
ORDER BY sf.Tanggal_Upload_Hasil DESC
";

$q_hasil = sqlsrv_query($conn, $sql, [$id_pelanggan, STATUS_ORDER_SELESAI_FOTO, STATUS_ORDER_LUNAS]);

$hasil_list = [];
if ($q_hasil !== false) {
    while ($row = sqlsrv_fetch_array($q_hasil, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['Tanggal_Order']) && is_object($row['Tanggal_Order'])) {
            $row['Tanggal_Order'] = $row['Tanggal_Order']->format('Y-m-d H:i:s');
        }
        if (isset($row['Tanggal_Jadwal']) && is_object($row['Tanggal_Jadwal'])) {
            $row['Tanggal_Jadwal'] = $row['Tanggal_Jadwal']->format('Y-m-d');
        }
        if (isset($row['Tanggal_Upload_Hasil']) && is_object($row['Tanggal_Upload_Hasil'])) {
            $row['Tanggal_Upload_Hasil'] = $row['Tanggal_Upload_Hasil']->format('Y-m-d H:i:s');
        }
        if (isset($row['Jam_Mulai']) && is_object($row['Jam_Mulai'])) {
            $row['Jam_Mulai'] = $row['Jam_Mulai']->format('H:i');
        }
        if (isset($row['Jam_Selesai']) && is_object($row['Jam_Selesai'])) {
            $row['Jam_Selesai'] = $row['Jam_Selesai']->format('H:i');
        }
        $hasil_list[] = $row;
    }
}

// =====================================================
// HITUNG STATISTIK
// =====================================================
$total_hasil = count($hasil_list);
$total_download = 0;
$total_rating = 0;
$avg_rating = 0;

foreach ($hasil_list as $item) {
    if (!empty($item['Rating'])) {
        $total_rating++;
        $avg_rating += $item['Rating'];
    }
}

if ($total_rating > 0) {
    $avg_rating = round($avg_rating / $total_rating, 1);
}

// =====================================================
// FUNGSI HELPER
// =====================================================
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $date = new DateTime($tanggal);
    $b = $date->format('m');
    return $date->format('d') . ' ' . $bulan[$b] . ' ' . $date->format('Y');
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        return 'fa-file-image';
    } elseif ($ext === 'zip') {
        return 'fa-file-archive';
    } elseif ($ext === 'rar') {
        return 'fa-file-archive';
    } elseif ($ext === 'pdf') {
        return 'fa-file-pdf';
    } else {
        return 'fa-file';
    }
}

function getFileSize($filepath) {
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        if ($size < 1024) return $size . ' B';
        elseif ($size < 1024 * 1024) return round($size / 1024, 2) . ' KB';
        else return round($size / (1024 * 1024), 2) . ' MB';
    }
    return 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Foto Saya - SpotLight Studio</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #D53D66;
            --primary-dark: #B82E52;
            --primary-light: #F8E1E7;
            --secondary: #2D2D2D;
            --accent: #FF8FB0;
            --bg: #F5F5F5;
            --white: #FFFFFF;
            --shadow: 0 8px 32px rgba(213, 61, 102, 0.15);
            --shadow-card: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 20px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: var(--bg); min-height: 100vh; }

        /* TOP NAVBAR - KONSISTEN */
        .top-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 40px; background: var(--white);
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .nav-logo {
            font-size: 24px; font-weight: 700; color: var(--primary); text-decoration: none;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-logo span { color: var(--secondary); font-weight: 400; }
        .nav-menu-center {
            display: flex; gap: 32px; align-items: center;
        }
        .nav-link-item {
            text-decoration: none; color: #666; font-size: 14px; font-weight: 500;
            padding: 8px 0; position: relative; transition: all 0.3s;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--primary);
        }
        .nav-link-item.active::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
            background: var(--primary); border-radius: 3px;
        }
        .nav-right {
            display: flex; align-items: center; gap: 16px;
        }
        .nav-btn-booking {
            background: var(--primary); color: var(--white); padding: 10px 24px;
            border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 600;
            display: flex; align-items: center; gap: 6px; transition: all 0.3s;
        }
        .nav-btn-booking:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .nav-avatar-wrapper { position: relative; }
        .nav-avatar {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
            cursor: pointer; border: 2px solid var(--primary-light);
        }
        .nav-dropdown {
            display: none; position: absolute; top: 50px; right: 0;
            background: var(--white); border-radius: 12px; box-shadow: var(--shadow);
            min-width: 200px; padding: 8px 0; overflow: hidden;
        }
        .nav-dropdown.show { display: block; }
        .dropdown-header {
            padding: 12px 16px; font-size: 14px; font-weight: 600; color: var(--secondary);
            border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-divider { height: 1px; background: #f0f0f0; margin: 4px 0; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; color: #666; text-decoration: none; font-size: 13px;
            transition: all 0.2s; cursor: pointer; border: none; background: none; width: 100%;
        }
        .dropdown-item:hover { background: var(--primary-light); color: var(--primary); }
        .dropdown-item.logout { color: #D32F2F; }
        .dropdown-item.logout:hover { background: #FFEBEE; }

        /* MAIN CONTENT */
        .main-content { padding: 100px 40px 40px; max-width: 1400px; margin: 0 auto; }
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }
        .page-title h1 { color: var(--secondary); font-size: 28px; font-weight: 700; }
        .page-title p { color: #888; font-size: 14px; margin-top: 4px; }

        /* STATS */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 24px;
            box-shadow: var(--shadow-card); transition: transform 0.3s;
            border-left: 4px solid var(--primary); position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card::before {
            content: ''; position: absolute; top: -20px; right: -20px;
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--primary-light); opacity: 0.5;
        }
        .stat-card .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 12px; position: relative; z-index: 1;
        }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--secondary); position: relative; z-index: 1; }
        .stat-card .stat-label { font-size: 13px; color: #888; margin-top: 2px; position: relative; z-index: 1; }
        .stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary); }
        .stat-card.download .stat-icon { background: #E8F5E9; color: #388E3C; }
        .stat-card.download { border-left-color: #388E3C; }
        .stat-card.rating .stat-icon { background: #FFF8E1; color: #F9A825; }
        .stat-card.rating { border-left-color: #F9A825; }

        /* HASIL CARDS */
        .hasil-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        .hasil-card {
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow-card); overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid transparent;
        }
        .hasil-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow);
            border-color: var(--primary-light);
        }
        .hasil-header {
            padding: 20px 24px; background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
            border-bottom: 1px solid #f0f0f0;
        }
        .hasil-header-top {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .hasil-id { font-size: 14px; color: #666; }
        .hasil-id strong { color: var(--primary); font-size: 16px; }
        .hasil-status {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: #E8F5E9; color: #388E3C;
        }
        .hasil-date { font-size: 13px; color: #888; }
        .hasil-date i { margin-right: 5px; color: var(--primary); }

        .hasil-body { padding: 24px; }
        .hasil-paket {
            display: flex; gap: 16px; margin-bottom: 20px;
        }
        .hasil-paket-img {
            width: 80px; height: 80px; border-radius: 12px; object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .hasil-paket-info h3 { color: var(--secondary); font-size: 18px; font-weight: 600; margin-bottom: 6px; }
        .hasil-paket-meta {
            display: flex; flex-wrap: wrap; gap: 8px;
        }
        .hasil-paket-meta span {
            background: var(--primary-light); color: var(--primary);
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;
        }

        .hasil-detail-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;
        }
        .hasil-detail-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; background: #FAFAFA; border-radius: 10px;
        }
        .hasil-detail-item i {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--primary-light); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .hasil-detail-item .detail-label { font-size: 11px; color: #888; }
        .hasil-detail-item .detail-value { font-size: 13px; color: var(--secondary); font-weight: 600; }

        /* FILE BOX */
        .file-box {
            background: linear-gradient(135deg, var(--primary-light), #ffffff);
            border-radius: 16px; padding: 20px;
            border: 2px dashed var(--primary-light); text-align: center;
            transition: all 0.3s;
        }
        .file-box:hover { border-color: var(--primary); }
        .file-box i {
            font-size: 3rem; color: var(--primary); margin-bottom: 12px;
        }
        .file-box .file-name {
            font-size: 14px; font-weight: 600; color: var(--secondary);
            word-break: break-all; margin-bottom: 4px;
        }
        .file-box .file-size {
            font-size: 12px; color: #888; margin-bottom: 12px;
        }
        .file-box .file-date {
            font-size: 12px; color: #888; margin-bottom: 16px;
        }
        .file-box .file-date i { margin-right: 4px; color: var(--primary); }

        .btn-download {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white); padding: 12px 28px;
            border-radius: 12px; text-decoration: none;
            font-weight: 600; font-size: 14px; transition: all 0.3s;
            border: none; cursor: pointer;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3);
            color: var(--white);
        }

        /* RATING SECTION */
        .rating-section {
            margin-top: 16px; padding-top: 16px; border-top: 1px dashed #ddd;
        }
        .rating-display {
            display: flex; gap: 4px; align-items: center;
        }
        .star-filled { color: #F9A825; font-size: 18px; }
        .star-empty { color: #DDD; font-size: 18px; }
        .rating-text { font-size: 13px; color: #888; margin-left: 8px; }
        .review-text {
            margin-top: 8px; padding: 10px 14px; background: #FFF8E1;
            border-radius: 10px; font-size: 13px; color: #666; font-style: italic;
        }
        .btn-rating {
            display: inline-flex; align-items: center; gap: 8px;
            background: #FFF8E1; color: #F9A825; padding: 10px 20px;
            border-radius: 10px; text-decoration: none;
            font-weight: 600; font-size: 13px; transition: all 0.3s;
            border: none; cursor: pointer;
        }
        .btn-rating:hover { background: #F9A825; color: var(--white); }

        /* FOTOGRAFER INFO */
        .fotografer-info {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; background: #E3F2FD; border-radius: 12px;
            margin-bottom: 16px;
        }
        .fotografer-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            display: flex; align-items: center; justify-content: center;
            color: var(--white); font-size: 16px; font-weight: 700;
        }
        .fotografer-text { font-size: 13px; color: #1976D2; }
        .fotografer-text strong { color: var(--secondary); font-weight: 600; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center; padding: 80px 20px; background: var(--white);
            border-radius: var(--radius); box-shadow: var(--shadow-card);
        }
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 20px; }
        .empty-state h3 { color: var(--secondary); font-size: 20px; margin-bottom: 8px; }
        .empty-state p { color: #888; font-size: 14px; margin-bottom: 20px; }
        .empty-state .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px; background: var(--primary);
            color: var(--white); border-radius: 12px; text-decoration: none;
            font-weight: 600; font-size: 14px; transition: all 0.3s;
        }
        .empty-state .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .top-navbar { padding: 12px 20px; }
            .nav-menu-center { display: none; }
            .main-content { padding: 80px 20px 20px; }
            .hasil-grid { grid-template-columns: 1fr; }
            .hasil-detail-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }

        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
    </style>
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="top-navbar">
    <a href="../index.php" class="nav-logo">
        <i class="bi bi-camera-fill"></i> SpotLight<span>.StudioFoto</span>
    </a>
    <div class="nav-menu-center">
        <a href="../index.php" class="nav-link-item">Dashboard</a>
        <a href="../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
        <a href="../Riwayat/index.php" class="nav-link-item">Riwayat</a>
        <a href="index.php" class="nav-link-item active">Hasil Foto</a>
        <a href="../Cetak/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
    </div>
    <div class="nav-right">
        <a href="../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
            <i class="bi bi-plus-lg"></i> Booking
        </a>
        <div class="nav-avatar-wrapper">
            <img src="<?php echo $foto_pelanggan_src; ?>" alt="Profil" class="nav-avatar" onclick="toggleDropdown()">
            <div class="nav-dropdown" id="navDropdown">
                <div class="dropdown-header">Halo, <?php echo htmlspecialchars($nama_pelanggan); ?></div>
                <div class="dropdown-divider"></div>
                <a href="../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
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

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-images" style="color:var(--primary);margin-right:10px;"></i>Hasil Foto Saya</h1>
            <p>Lihat dan download hasil pemotretan profesional Anda</p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-images"></i></div>
            <div class="stat-value"><?php echo $total_hasil; ?></div>
            <div class="stat-label">Total Hasil Foto</div>
        </div>
        <div class="stat-card download">
            <div class="stat-icon"><i class="fas fa-download"></i></div>
            <div class="stat-value"><?php echo $total_hasil; ?></div>
            <div class="stat-label">Siap Download</div>
        </div>
        <div class="stat-card rating">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-value"><?php echo $avg_rating > 0 ? $avg_rating : '-'; ?></div>
            <div class="stat-label">Rating Rata-rata</div>
        </div>
    </div>

    <!-- HASIL FOTO LIST -->
    <?php if (empty($hasil_list)): ?>
        <div class="empty-state">
            <i class="fas fa-camera-retro"></i>
            <h3>Belum Ada Hasil Foto</h3>
            <p>Hasil foto Anda akan muncul di sini setelah sesi pemotretan selesai dan fotografer mengupload hasilnya.</p>
            <a href="../Layanan/Paket/pilih_paket.php" class="btn-primary">
                <i class="fas fa-calendar-plus"></i> Booking Sekarang
            </a>
        </div>
    <?php else: ?>
        <div class="hasil-grid">
            <?php foreach ($hasil_list as $item): 
                $foto_paket = $item['Foto_Paket'] ?? 'default_paket.jpg';
                $foto_src = file_exists("../../assets/img/paket/" . $foto_paket) 
                    ? "../../assets/img/paket/" . $foto_paket 
                    : "../../assets/img/paket/default_paket.jpg";
                $file_path = "../../uploads/sesi_foto/" . $item['File_Hasil'];
                $file_size = getFileSize($file_path);
                $file_icon = getFileIcon($item['File_Hasil']);
                $tgl_upload = formatTanggalIndo($item['Tanggal_Upload_Hasil']);
                $tgl_jadwal = formatTanggalIndo($item['Tanggal_Jadwal']);
                $has_rating = !empty($item['Rating']);
            ?>
            <div class="hasil-card">
                <!-- HEADER -->
                <div class="hasil-header">
                    <div class="hasil-header-top">
                        <div class="hasil-id">
                            Order <strong>#<?php echo str_pad($item['ID_Order'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <span class="hasil-status">
                            <i class="fas fa-check-circle"></i> Selesai
                        </span>
                    </div>
                    <div class="hasil-date">
                        <i class="fas fa-calendar-alt"></i>
                        Sesi: <?php echo $tgl_jadwal; ?> • <?php echo $item['Jam_Mulai'] ?? '-'; ?> - <?php echo $item['Jam_Selesai'] ?? '-'; ?> WIB
                    </div>
                </div>

                <!-- BODY -->
                <div class="hasil-body">
                    <!-- Paket Info -->
                    <div class="hasil-paket">
                        <img src="<?php echo $foto_src; ?>" alt="Paket" class="hasil-paket-img">
                        <div class="hasil-paket-info">
                            <h3><?php echo htmlspecialchars($item['Nama_Paket']); ?></h3>
                            <div class="hasil-paket-meta">
                                <span><i class="fas fa-clock" style="margin-right:4px;"></i><?php echo $item['Durasi_Waktu']; ?> menit</span>
                                <span><i class="fas fa-door-open" style="margin-right:4px;"></i><?php echo htmlspecialchars($item['Nama_Ruangan']); ?></span>
                                <span><i class="fas fa-palette" style="margin-right:4px;"></i><?php echo htmlspecialchars($item['Nama_Tema']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Grid -->
                    <div class="hasil-detail-grid">
                        <div class="hasil-detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>
                                <div class="detail-label">Total Harga</div>
                                <div class="detail-value"><?php echo formatRupiah($item['Total_Harga']); ?></div>
                            </div>
                        </div>
                        <div class="hasil-detail-item">
                            <i class="fas fa-calendar-check"></i>
                            <div>
                                <div class="detail-label">Tanggal Order</div>
                                <div class="detail-value"><?php echo formatTanggalIndo($item['Tanggal_Order']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Fotografer -->
                    <?php if (!empty($item['Nama_Fotografer'])): ?>
                    <div class="fotografer-info">
                        <div class="fotografer-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="fotografer-text">
                            Fotografer: <strong><?php echo htmlspecialchars($item['Nama_Fotografer']); ?></strong> (@<?php echo htmlspecialchars($item['Username_Fotografer']); ?>)
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- File Box -->
                    <div class="file-box">
                        <i class="fas <?php echo $file_icon; ?>"></i>
                        <div class="file-name"><?php echo htmlspecialchars($item['File_Hasil']); ?></div>
                        <div class="file-size"><i class="fas fa-hdd" style="margin-right:4px;"></i><?php echo $file_size; ?></div>
                        <div class="file-date">
                            <i class="fas fa-upload"></i>
                            Diupload: <?php echo $tgl_upload; ?>
                        </div>
                        <a href="<?php echo $file_path; ?>" download class="btn-download">
                            <i class="fas fa-download"></i> Download Hasil Foto
                        </a>
                    </div>

                    <!-- Rating Section -->
                    <?php if ($has_rating): ?>
                    <div class="rating-section">
                        <div style="font-size:12px;color:#888;margin-bottom:6px;font-weight:600;">Rating Anda:</div>
                        <div class="rating-display">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $item['Rating'] ? 'star-filled' : 'star-empty'; ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-text">(<?php echo $item['Rating']; ?>/5)</span>
                        </div>
                        <?php if (!empty($item['Review'])): ?>
                        <div class="review-text">
                            "<?php echo htmlspecialchars($item['Review']); ?>"
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="rating-section">
                        <div style="font-size:12px;color:#888;margin-bottom:8px;font-weight:600;">Beri Rating:</div>
                        <a href="../Riwayat/index.php?tab=selesai" class="btn-rating">
                            <i class="fas fa-star"></i> Beri Rating & Review
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// DROPDOWN
function toggleDropdown() {
    document.getElementById('navDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.nav-avatar-wrapper');
    if (!wrapper.contains(e.target)) {
        document.getElementById('navDropdown').classList.remove('show');
    }
});

function confirmLandingPage(e) {
    return true;
}

function confirmLogout() {
    Swal.fire({
        title: 'Keluar?',
        text: 'Apakah Anda yakin ingin keluar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D53D66',
        cancelButtonColor: '#888',
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