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

// --- Profil ---
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

// =====================================================
// QUERY: Hasil Foto yang bisa diakses customer
// Hanya order dengan Status_Order = 3 (Lunas) dan File_Hasil IS NOT NULL
// =====================================================
$q_hasil = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        J.Tanggal_Jadwal,
        J.Jam_Mulai,
        J.Jam_Selesai,
        O.Total_Harga,
        O.Status_Order
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
    WHERE O.ID_Pelanggan = ? 
      AND O.Status = ? 
      AND O.Status_Order = ?
      AND S.Status = ?
      AND S.File_Hasil IS NOT NULL
    ORDER BY S.Tanggal_Upload_Hasil DESC
", array($id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_LUNAS, STATUS_DATA_AKTIF));

// =====================================================
// QUERY: Order yang masih menunggu pelunasan (untuk info)
// =====================================================
$q_menunggu = sqlsrv_query($conn, "
    SELECT COUNT(*) as total_menunggu
    FROM [Order]
    WHERE ID_Pelanggan = ? AND Status = ? AND Status_Order IN (?, ?) AND Status_Order != ?
", array($id_customer, STATUS_DATA_AKTIF, STATUS_ORDER_SELESAI, STATUS_ORDER_DP_TERVERIFIKASI, STATUS_ORDER_DIBATALKAN));
$d_menunggu = sqlsrv_fetch_array($q_menunggu, SQLSRV_FETCH_ASSOC);
$total_menunggu = $d_menunggu['total_menunggu'] ?? 0;

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
            --success: #059669;
            --warning: #d97706;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS (SAMA DENGAN CUSTOMER INDEX) ===== */
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
        .nav-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
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
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 0;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== HERO ===== */
        .hero-hasil {
            background: linear-gradient(135deg, var(--p-pink) 0%, var(--d-pink) 50%, #b82e52 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        .hero-hasil::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            border-radius: 50%;
        }
        .hero-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 8px;
            position: relative;
        }
        .hero-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
            position: relative;
        }

        /* ===== INFO ALERT ===== */
        .info-alert {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 32px;
            border: 1px solid #bfdbfe;
        }
        .info-alert-title {
            font-weight: 800;
            font-size: 0.9rem;
            color: #1e40af;
            margin-bottom: 6px;
        }
        .info-alert-text {
            font-size: 0.85rem;
            color: #3b82f6;
            line-height: 1.5;
        }

        /* ===== HASIL CARD ===== */
        .hasil-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
        }
        .hasil-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        .hasil-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12);
            border-color: var(--light-pink);
        }
        .hasil-header {
            background: linear-gradient(135deg, var(--s-pink), #f8fafc);
            padding: 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        .hasil-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: var(--success);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .hasil-paket {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .hasil-ruangan {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hasil-body {
            padding: 20px 24px;
        }
        .hasil-info {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .hasil-info:last-child {
            border-bottom: none;
        }
        .hasil-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hasil-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .hasil-footer {
            padding: 0 24px 24px;
            display: flex;
            gap: 10px;
        }
        .btn-download {
            flex: 1;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
            color: #fff;
        }
        .btn-preview {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #4a5568;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }
        .btn-preview:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        /* ===== EMPTY STATE ===== */
        .empty-hasil {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-hasil i {
            font-size: 5rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-hasil h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-hasil p {
            color: var(--text-muted);
            font-size: 0.95rem;
            max-width: 500px;
            margin: 0 auto;
        }
        .empty-hasil .btn-action {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        .empty-hasil .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.25);
        }

        /* ===== WAITING CARD ===== */
        .waiting-card {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 2px dashed #fcd34d;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            margin-bottom: 32px;
        }
        .waiting-card i {
            font-size: 2.5rem;
            color: #f59e0b;
            margin-bottom: 12px;
        }
        .waiting-card h4 {
            font-weight: 800;
            color: #92400e;
            font-size: 1.1rem;
            margin-bottom: 6px;
        }
        .waiting-card p {
            color: #b45309;
            font-size: 0.85rem;
            margin-bottom: 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .nav-menu-center { display: none; }
            .hero-hasil { padding: 30px 20px; }
            .hasil-grid { grid-template-columns: 1fr; }
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
            <a href="../../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../../Riwayat/riwayat.php" class="nav-link-item">Riwayat</a>
            <a href="../../Barang/Katalog/index.php" class="nav-link-item">Barang Cetak</a>
            <a href="index.php" class="nav-link-item active">Hasil Foto</a>
        </div>
        <div class="nav-right">
            <a href="../../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
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

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <!-- HERO -->
        <div class="hero-hasil">
            <div class="hero-title"><i class="bi bi-images me-2"></i>Hasil Foto Saya</div>
            <div class="hero-subtitle">Download hasil pemotretan dari sesi foto Anda</div>
        </div>

        <?php if ($total_menunggu > 0): ?>
        <!-- INFO: Ada order yang menunggu pelunasan -->
        <div class="waiting-card">
            <i class="bi bi-hourglass-split"></i>
            <h4>Ada <?= $total_menunggu ?> Hasil Foto Menunggu</h4>
            <p>Hasil foto dari sesi Anda sudah tersedia, tetapi belum bisa diakses karena order masih menunggu pelunasan. Silakan selesaikan pembayaran pelunasan untuk mengakses hasil foto.</p>
        </div>
        <?php endif; ?>

        <!-- INFO ALERT -->
        <div class="info-alert">
            <div class="info-alert-title"><i class="bi bi-info-circle-fill me-2"></i>Informasi Akses Hasil Foto</div>
            <div class="info-alert-text">
                Hasil foto hanya dapat diakses setelah order Anda <strong>status LUNAS</strong>. 
                File hasil akan tersedia dalam format ZIP. Pastikan Anda memiliki cukup ruang penyimpanan untuk mengunduh file.
            </div>
        </div>

        <!-- HASIL GRID -->
        <div class="hasil-grid">
            <?php
            $has_data = false;
            if ($q_hasil && sqlsrv_has_rows($q_hasil)):
                $has_data = true;
                while ($row = sqlsrv_fetch_array($q_hasil, SQLSRV_FETCH_ASSOC)):
                    $file_url = "../../../../uploads/hasil/" . rawurlencode($row['File_Hasil']);
            ?>
                <div class="hasil-card">
                    <div class="hasil-header">
                        <div class="hasil-badge">
                            <i class="bi bi-check-circle-fill"></i> Lunas & Siap Download
                        </div>
                        <div class="hasil-paket"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                        <div class="hasil-ruangan"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                    </div>
                    <div class="hasil-body">
                        <div class="hasil-info">
                            <span class="hasil-label">ID Order</span>
                            <span class="hasil-value">#<?= $row['ID_Order'] ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Tanggal Sesi</span>
                            <span class="hasil-value"><?= formatTanggal($row['Tanggal_Jadwal']) ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Jam Sesi</span>
                            <span class="hasil-value"><?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Durasi</span>
                            <span class="hasil-value"><?= $row['Durasi_Waktu'] ?> menit</span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Total Harga</span>
                            <span class="hasil-value" style="color: var(--p-pink);">Rp<?= number_format($row['Total_Harga'], 0, ',', '.') ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">File</span>
                            <span class="hasil-value" style="color: var(--p-pink);"><i class="bi bi-file-earmark-zip me-1"></i><?= htmlspecialchars($row['File_Hasil']) ?></span>
                        </div>
                        <div class="hasil-info">
                            <span class="hasil-label">Diupload</span>
                            <span class="hasil-value"><?= formatTanggal($row['Tanggal_Upload_Hasil']) ?> <?= formatWaktu($row['Tanggal_Upload_Hasil']) ?></span>
                        </div>
                    </div>
                    <div class="hasil-footer">
                        <a href="<?= $file_url ?>" class="btn-download" download>
                            <i class="bi bi-download"></i> Download Hasil Foto
                        </a>
                    </div>
                </div>
            <?php 
                endwhile;
            endif;
            ?>
        </div>

        <?php if (!$has_data): ?>
        <!-- EMPTY STATE -->
        <div class="empty-hasil">
            <i class="bi bi-images"></i>
            <h3>Belum Ada Hasil Foto</h3>
            <p>Anda belum memiliki hasil foto yang tersedia. Hasil foto akan muncul di sini setelah sesi foto selesai dan pembayaran lunas terverifikasi.</p>
            <a href="../../Layanan/Paket/pilih_paket.php" class="btn-action">
                <i class="bi bi-calendar-plus"></i> Booking Sekarang
            </a>
        </div>
        <?php endif; ?>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
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