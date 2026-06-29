<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin untuk Sidebar
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$email_admin = $d_admin['email_karyawan'] ?? 'admin@spotlight.com';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
    ? "../../assets/img/pelanggan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// AMBIL ID PENJUALAN
// =====================================================
$id_penjualan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_penjualan <= 0) {
    header("Location: list.php?status_sukses=error&message=ID Penjualan tidak valid");
    exit();
}

// =====================================================
// QUERY DETAIL PENJUALAN
// =====================================================
$sql_penjualan = "SELECT p.*, o.ID_Order, o.Tanggal_Booking, o.Status_Order,
                         pl.Nama_Pelanggan, pl.Email_Pelanggan, pl.No_Hp, pl.Alamat,
                         k.Nama_Karyawan as Nama_Admin
                  FROM Penjualan p 
                  LEFT JOIN [Order] o ON p.ID_Order = o.ID_Order 
                  LEFT JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan 
                  LEFT JOIN Karyawan k ON p.ID_Karyawan_Admin = k.ID_Karyawan
                  WHERE p.ID_Penjualan = ? AND p.Status = 1";
$stmt = sqlsrv_query($conn, $sql_penjualan, [$id_penjualan]);
$penjualan = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$penjualan) {
    header("Location: list.php?status_sukses=error&message=Data penjualan tidak ditemukan");
    exit();
}

// Format tanggal
$tanggal_penjualan = '';
if (isset($penjualan['Tanggal_Penjualan']) && $penjualan['Tanggal_Penjualan'] instanceof DateTime) {
    $tanggal_penjualan = $penjualan['Tanggal_Penjualan']->format('d M Y H:i');
}
$tanggal_booking = '';
if (isset($penjualan['Tanggal_Booking']) && $penjualan['Tanggal_Booking'] instanceof DateTime) {
    $tanggal_booking = $penjualan['Tanggal_Booking']->format('d M Y H:i');
}

// Status penjualan
if ($penjualan['Status_Penjualan'] == 0) {
    $badge_class = "badge-proses";
    $text_status = "Proses";
} else {
    $badge_class = "badge-selesai";
    $text_status = "Selesai";
}

// Status order
$status_order_text = '';
$status_order_class = '';
switch ($penjualan['Status_Order']) {
    case 0: $status_order_text = 'Menunggu DP'; $status_order_class = 'badge-menunggu'; break;
    case 1: $status_order_text = 'DP Terverifikasi'; $status_order_class = 'badge-dp'; break;
    case 2: $status_order_text = 'Pemotretan'; $status_order_class = 'badge-pelunasan'; break;
    case 3: $status_order_text = 'Lunas'; $status_order_class = 'badge-lunas'; break;
    case 4: $status_order_text = 'Dibatalkan'; $status_order_class = 'badge-batal'; break;
    default: $status_order_text = 'Unknown'; $status_order_class = 'badge-menunggu';
}

// =====================================================
// QUERY DETAIL BARANG
// =====================================================
$sql_barang = "SELECT d.*, b.Nama_Barang, b.Foto_Barang, b.Stok_Barang
               FROM Detail_Penjualan_Barang_Cetak d
               JOIN Barang_Cetak b ON d.ID_Barang = b.ID_Barang
               WHERE d.ID_Penjualan = ?";
$stmt_barang = sqlsrv_query($conn, $sql_barang, [$id_penjualan]);
$detail_barang = [];
while ($row = sqlsrv_fetch_array($stmt_barang, SQLSRV_FETCH_ASSOC)) {
    $detail_barang[] = $row;
}

// Hitung total qty
$total_qty = 0;
foreach ($detail_barang as $b) {
    $total_qty += $b['Jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penjualan #<?= $id_penjualan ?> – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66;
            --d-pink: #CA3366;
            --s-pink: #FFF0F3;
            --light-pink: #FFE4E9;
            --accent-pink: #E85D84;
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

        /* SIDEBAR */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem; color: var(--p-pink);
            text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700;
            text-decoration: none; border-radius: 12px; font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px;
            color: #718096; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px;
        }
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* CARD 3D */
        .card-3d {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d); padding: 24px;
            position: relative; overflow: hidden;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14); border-color: var(--p-pink);
        }

        /* INFO CARDS */
        .info-label {
            font-size: 0.7rem; font-weight: 800; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
        }
        .info-value {
            font-size: 0.95rem; font-weight: 700; color: var(--text-dark);
        }
        .info-value-large {
            font-size: 1.5rem; font-weight: 800; color: var(--p-pink);
        }

        /* BADGE STATUS */
        .badge-status-penjualan {
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-proses { background: #fffbeb; color: #d97706; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-menunggu { background: #fffbeb; color: #d97706; }
        .badge-dp { background: #FFE4E9; color: #D53D66; }
        .badge-pelunasan { background: #FFD6E0; color: #D53D66; }
        .badge-lunas { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-proses .badge-dot { background: #d97706; }
        .badge-selesai .badge-dot { background: #059669; }

        /* BARANG ITEM */
        .barang-item {
            display: flex; align-items: center; gap: 16px;
            padding: 16px 0; border-bottom: 1px solid #f1f5f9;
        }
        .barang-item:last-child { border-bottom: none; }
        .barang-img {
            width: 60px; height: 60px; object-fit: cover;
            border-radius: 14px; border: 2px solid var(--light-pink);
            transition: var(--transition-3d);
        }
        .barang-item:hover .barang-img { transform: scale(1.08) rotate(2deg); }
        .barang-info { flex: 1; }
        .barang-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .barang-harga { font-size: 0.8rem; color: #718096; font-weight: 600; }
        .barang-qty {
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            color: var(--p-pink); padding: 6px 14px; border-radius: 10px;
            font-weight: 700; font-size: 0.8rem;
        }
        .barang-subtotal { font-weight: 800; color: var(--p-pink); font-size: 1rem; }

        /* TOTAL SECTION */
        .total-section {
            background: linear-gradient(135deg, var(--s-pink), #ffffff);
            border-radius: 20px; padding: 24px;
            border: 2px solid var(--light-pink);
        }
        .total-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; font-size: 0.9rem;
        }
        .total-row.grand {
            border-top: 2px solid var(--light-pink); padding-top: 16px; margin-top: 8px;
        }
        .total-label { font-weight: 600; color: #718096; }
        .total-value { font-weight: 700; color: var(--text-dark); }
        .total-value-grand { font-weight: 800; color: var(--p-pink); font-size: 1.5rem; }

        /* BUTTON */
        .btn-back {
            background: #f1f5f9; color: #475569; border: none;
            border-radius: 14px; padding: 12px 24px; font-weight: 700;
            transition: var(--transition-3d); text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px;
        }
        .btn-back:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 12px 28px; font-weight: 800; font-size: 0.9rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; cursor: pointer;
        }
        .btn-action:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 12px 25px rgba(213, 61, 102, 0.35);
        }
        .btn-action-success {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .btn-action-success:hover {
            box-shadow: 0 12px 25px rgba(5, 150, 105, 0.35);
        }
        .btn-action-disabled {
            background: #e2e8f0; color: #94a3b8; cursor: not-allowed;
        }
        .btn-action-disabled:hover { transform: none; box-shadow: none; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Administrator</span>
            </a>
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
                            <li><a href="../../Master/Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
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
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuTransaksi">
                        <ul class="list-unstyled">
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                            <li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                            <li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                            <li><a href="list.php" class="submenu-link active"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
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
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Detail Penjualan Barang Cetak</h3>
                <p class="text-muted small mb-0">No. Order #<?= $penjualan['ID_Order'] ?> | Penjualan #<?= $id_penjualan ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- INFO CARDS ROW -->
        <div class="row g-4 mb-4">
            <!-- Status Penjualan -->
            <div class="col-lg-3 col-md-6 fade-in-up">
                <div class="card-3d text-center">
                    <div class="info-label">Status Penjualan</div>
                    <span class="badge-status-penjualan <?= $badge_class ?> mt-2">
                        <span class="badge-dot"></span>
                        <?= $text_status ?>
                    </span>
                </div>
            </div>
            <!-- Tanggal Penjualan -->
            <div class="col-lg-3 col-md-6 fade-in-up" style="animation-delay: 0.1s;">
                <div class="card-3d text-center">
                    <div class="info-label">Tanggal Penjualan</div>
                    <div class="info-value mt-2"><?= $tanggal_penjualan ?></div>
                </div>
            </div>
            <!-- Total Barang -->
            <div class="col-lg-3 col-md-6 fade-in-up" style="animation-delay: 0.2s;">
                <div class="card-3d text-center">
                    <div class="info-label">Total Barang</div>
                    <div class="info-value-large mt-2"><?= $total_qty ?> <small style="font-size: 0.7rem; color: #94a3b8;">qty</small></div>
                </div>
            </div>
            <!-- Total Harga -->
            <div class="col-lg-3 col-md-6 fade-in-up" style="animation-delay: 0.3s;">
                <div class="card-3d text-center">
                    <div class="info-label">Total Harga</div>
                    <div class="info-value-large mt-2">Rp <?= number_format($penjualan['Total_Penjualan'] ?? 0, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- ROW 2: CUSTOMER INFO & BARANG LIST -->
        <div class="row g-4">
            <!-- Customer Info -->
            <div class="col-lg-4 fade-in-up">
                <div class="card-3d" style="height: 100%;">
                    <h5 class="fw-bold mb-4"><i class="bi bi-person-fill text-danger me-2"></i>Informasi Pelanggan</h5>
                    <div class="mb-3">
                        <div class="info-label">Nama Pelanggan</div>
                        <div class="info-value"><?= htmlspecialchars($penjualan['Nama_Pelanggan'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($penjualan['Email_Pelanggan'] ?? '-') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">No. Telepon</div>
                        <div class="info-value"><?= htmlspecialchars($penjualan['No_Hp'] ?? '-') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Alamat</div>
                        <div class="info-value"><?= htmlspecialchars($penjualan['Alamat'] ?? '-') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">No. Order</div>
                        <div class="info-value" style="color: var(--p-pink);">#<?= $penjualan['ID_Order'] ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label">Tanggal Booking</div>
                        <div class="info-value"><?= $tanggal_booking ?></div>
                    </div>
                    <div>
                        <div class="info-label">Status Order</div>
                        <span class="badge-status-penjualan <?= $status_order_class ?> mt-1">
                            <span class="badge-dot"></span>
                            <?= $status_order_text ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Barang List -->
            <div class="col-lg-8 fade-in-up" style="animation-delay: 0.1s;">
                <div class="card-3d" style="height: 100%;">
                    <h5 class="fw-bold mb-4"><i class="bi bi-box-seam-fill text-danger me-2"></i>Daftar Barang Cetak</h5>
                    <?php foreach ($detail_barang as $b): 
                        $path_img = "../../assets/img/barang/" . ($b['Foto_Barang'] ?? '');
                        $img_src = (!empty($b['Foto_Barang']) && file_exists($path_img)) ? $path_img : $default_svg_avatar;
                    ?>
                    <div class="barang-item">
                        <img src="<?= $img_src ?>" class="barang-img" alt="<?= htmlspecialchars($b['Nama_Barang']) ?>">
                        <div class="barang-info">
                            <div class="barang-nama"><?= htmlspecialchars($b['Nama_Barang']) ?></div>
                            <div class="barang-harga">Rp <?= number_format($b['Harga_Satuan'] ?? 0, 0, ',', '.') ?> / unit</div>
                            <div class="barang-harga" style="font-size: 0.75rem; color: #94a3b8;">Stok tersisa: <?= $b['Stok_Barang'] ?? 0 ?></div>
                        </div>
                        <div class="barang-qty">x<?= $b['Jumlah'] ?></div>
                        <div class="barang-subtotal">Rp <?= number_format($b['Subtotal'] ?? 0, 0, ',', '.') ?></div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Total Section -->
                    <div class="total-section mt-4">
                        <div class="total-row">
                            <span class="total-label">Total Barang</span>
                            <span class="total-value"><?= $total_qty ?> item</span>
                        </div>
                        <div class="total-row grand">
                            <span class="total-label" style="font-size: 1.1rem;">Total Penjualan</span>
                            <span class="total-value-grand">Rp <?= number_format($penjualan['Total_Penjualan'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="row g-4 mt-2">
            <div class="col-12 fade-in-up">
                <div class="card-3d d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <a href="list.php" class="btn-back">
                        <i class="bi bi-arrow-left"></i> Kembali ke List
                    </a>
                    <div class="d-flex gap-3">
                        <?php if ($penjualan['Status_Penjualan'] == 0): ?>
                        <button onclick="updateStatus(<?= $id_penjualan ?>, '<?= htmlspecialchars($penjualan['Nama_Pelanggan'] ?? 'Unknown') ?>')" class="btn-action btn-action-success">
                            <i class="bi bi-check-lg"></i> Tandai Selesai
                        </button>
                        <?php else: ?>
                        <button class="btn-action btn-action-disabled" disabled>
                            <i class="bi bi-check-circle-fill"></i> Sudah Selesai
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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

        // Update Status
        function updateStatus(id, nama) {
            Swal.fire({
                title: 'Tandai Selesai?',
                text: 'Penjualan untuk pelanggan "' + nama + '" akan ditandai sebagai SELESAI.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Selesai',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action.php?aksi=update_status&id=' + id;
                }
            });
        }

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
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
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>

    <!-- Notifikasi -->
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = "";
        let t_icon = "success";
        let t_title = "Berhasil!";

        if ("<?= $_GET['status_sukses'] ?>" == 'update_status') { 
            msg = "Status penjualan berhasil diperbarui menjadi SELESAI!"; 
            t_title = "Status Diperbarui"; 
        }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { 
            msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; 
            t_icon = "error"; t_title = "Gagal!"; 
        }

        Swal.fire({
            icon: t_icon,
            title: t_title,
            text: msg,
            confirmButtonColor: '#D53D66'
        });
    </script>
    <?php endif; ?>
</body>
</html>