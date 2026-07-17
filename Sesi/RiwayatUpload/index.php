<?php
session_start();
include '../../koneksi.php';

// Validasi Koneksi Database
if (!$conn) {
    die("<div style='padding:30px; font-family:\"Plus Jakarta Sans\", sans-serif; text-align:center; color:#dc2626; background:#fef2f2; margin:50px auto; max-width:500px; border-radius:12px; border:1px solid #fee2e2;'>
            <h4 style='margin-bottom:10px; font-weight:700;'>Koneksi Terputus</h4>
            <p style='font-size:14px; color:#991b1b; margin:0;'>Sistem gagal terhubung ke database. Silakan coba beberapa saat lagi atau hubungi administrator.</p>
         </div>");
}

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : null;

if (!$id_fotografer) {
    header("Location: ../../login.php");
    exit();
}

// =====================================================
// HANDLE NOTIFIKASI DARI REDIRECT
// =====================================================
$upload_notif = false;
$deleted_notif = false;

if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') {
    $upload_notif = true;
}
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $deleted_notif = true;
}

// =====================================================
// QUERY: RIWAYAT UPLOAD
// =====================================================
$q_riwayat = sqlsrv_query($conn, "{CALL sp_ReadRiwayatUploadFotografer(?)}", array($id_fotografer));

// Validasi Eksekusi Query
$db_error = false;
if ($q_riwayat === false) {
    $db_error = true;
}

function formatTanggal($date) {
    if (!$date) return '-';
    try {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        if ($date instanceof DateTime) {
            $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            return $date->format('d').' '.$bulan[intval($date->format('m'))-1].' '.$date->format('Y');
        }
    } catch (Exception $e) {
        return '-';
    }
    return '-';
}

// Helper: Status Order label
function getStatusOrderLabel($status) {
    switch ($status) {
        case 0: return ['Menunggu DP', '#f59e0b', '#fffbeb', 'border: 1px solid #fef3c7;'];
        case 1: return ['DP Terverifikasi', '#3b82f6', '#eff6ff', 'border: 1px solid #dbeafe;'];
        case 2: return ['Selesai', '#8b5cf6', '#f5f3ff', 'border: 1px solid #ede9fe;'];
        case 3: return ['Lunas', '#059669', '#ecfdf5', 'border: 1px solid #d1fae5;'];
        case 4: return ['Dibatalkan', '#dc2626', '#fef2f2', 'border: 1px solid #fee2e2;'];
        default: return ['Tidak Diketahui', '#718096', '#f8fafc', 'border: 1px solid #e2e8f0;'];
    }
}

// Helper: Customer access status
function getCustomerAccessLabel($status_order) {
    if ($status_order == 3) {
        return ['<i class="bi bi-check-circle-fill me-1"></i> Customer Bisa Akses', '#059669', '#ecfdf5', 'border: 1px solid #d1fae5;'];
    } elseif ($status_order == 4) {
        return ['<i class="bi bi-x-circle-fill me-1"></i> Order Dibatalkan', '#dc2626', '#fef2f2', 'border: 1px solid #fee2e2;'];
    } else {
        return ['<i class="bi bi-hourglass-split me-1"></i> Menunggu Pelunasan', '#d97706', '#fffbeb', 'border: 1px solid #fef3c7;'];
    }
}

function formatWaktu($time) {
    if (!$time) return '-';
    try {
        if (is_string($time)) {
            $time = new DateTime($time);
        }
        if ($time instanceof DateTime) {
            return $time->format('H:i');
        }
    } catch (Exception $e) {
        return '-';
    }
    return '-';
}

function formatUkuran($bytes) {
    if ($bytes <= 0) return '0 KB';
    $units = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Upload – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --p-pink: #D53D66; 
            --d-pink: #CA3366; 
            --s-pink: #FFF0F3; 
            --light-pink: #FFE4E9; 
            --accent-pink: #E85D84; 
            --text-dark: #1e1e24; 
            --text-muted: #64748b; 
            --sidebar-bg: #ffffff; 
            --body-bg: #f8fafc; 
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--body-bg); 
            color: var(--text-dark); 
            overflow-x: hidden; 
        }
        
        /* Sidebar Styling */
        .sidebar { 
            width: 270px; 
            height: 100vh; 
            background: var(--sidebar-bg); 
            position: fixed; 
            top: 0; 
            left: 0; 
            border-right: 1px solid rgba(255, 228, 233, 0.8); 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            padding: 30px 20px; 
            z-index: 1040; 
            transition: var(--transition-smooth);
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
            color: #475569; 
            font-weight: 700; 
            text-decoration: none; 
            border-radius: 12px; 
            font-size: 0.9rem; 
            transition: var(--transition-smooth); 
        }
        .nav-link-custom:hover, .nav-link-custom.active { 
            background-color: var(--light-pink); 
            color: var(--p-pink); 
        }
        .submenu { 
            list-style: none; 
            padding-left: 15px; 
            margin-top: 5px; 
            display: none; 
        }
        .submenu.show { 
            display: block !important; 
        }
        .submenu-link { 
            display: flex; 
            align-items: center; 
            padding: 8px 18px; 
            color: #64748b; 
            font-weight: 600; 
            font-size: 0.85rem; 
            text-decoration: none; 
            border-radius: 10px; 
            transition: var(--transition-smooth); 
        }
        .submenu-link:hover, .submenu-link.active { 
            color: var(--p-pink); 
            background-color: rgba(213, 61, 102, 0.05); 
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
            transition: var(--transition-smooth); 
        }
        .btn-logout:hover { 
            box-shadow: 0 6px 20px rgba(213, 61, 102, 0.3); 
            transform: translateY(-1px);
        }

        /* Mobile Header Toggle */
        .mobile-header {
            display: none;
            background: #ffffff;
            border-bottom: 1px solid rgba(255, 228, 233, 0.8);
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            justify-content: space-between;
            align-items: center;
        }
        .mobile-toggle {
            background: none;
            border: none;
            color: var(--p-pink);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(4px);
            z-index: 1035;
        }

        /* Main Content Styling */
        .main-content { 
            margin-left: 270px; 
            padding: 40px; 
            min-height: 100vh; 
            transition: var(--transition-smooth);
        }
        .card-custom { 
            background: #ffffff; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 228, 233, 0.6); 
            box-shadow: 0 10px 30px rgba(213, 61, 102, 0.02); 
            padding: 30px; 
            margin-top: 15px;
        }
        .content-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            flex-wrap: wrap;
            gap: 15px;
        }
        .content-title { 
            font-weight: 700; 
            font-size: 1.15rem; 
            color: var(--text-dark); 
            margin: 0;
        }
        
        /* Modern Table Customization */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }
        .table-custom { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0; 
            margin-bottom: 0;
        }
        .table-custom th { 
            padding: 16px; 
            font-size: 0.75rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            color: var(--text-muted); 
            background: #f8fafc; 
            border-bottom: 2px solid #f1f5f9; 
        }
        .table-custom td { 
            padding: 16px; 
            font-size: 0.85rem; 
            border-bottom: 1px solid #f1f5f9; 
            vertical-align: middle; 
            background: #ffffff;
            transition: var(--transition-smooth);
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }
        .table-custom tr:hover td { 
            background: #fffcfc; 
        }
        
        /* Actions & Badges */
        .file-name { 
            font-weight: 700; 
            color: var(--p-pink); 
        }
        .btn-action { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; 
            border: none; 
            border-radius: 10px; 
            padding: 8px 16px; 
            font-weight: 700; 
            font-size: 0.8rem; 
            transition: var(--transition-smooth); 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
        }
        .btn-action:hover { 
            box-shadow: 0 4px 12px rgba(213, 61, 102, 0.25); 
            color: #ffffff; 
            transform: translateY(-1px);
        }
        .status-badge {
            padding: 6px 14px; 
            border-radius: 50px; 
            font-size: 0.725rem; 
            font-weight: 800; 
            display: inline-block;
            white-space: nowrap;
        }
        
        /* Search Box */
        .search-container {
            position: relative;
            max-width: 300px;
            width: 100%;
        }
        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            font-weight: 500;
            outline: none;
            transition: var(--transition-smooth);
        }
        .search-input:focus {
            border-color: var(--p-pink);
            box-shadow: 0 0 0 3px rgba(213, 61, 102, 0.1);
        }
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) { 
            .sidebar { 
                transform: translateX(-100%); 
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-overlay.show {
                display: block;
            }
            .mobile-header {
                display: flex;
            }
            .main-content { 
                margin-left: 0; 
                padding: 100px 20px 40px 20px; 
            } 
        }
    </style>
</head>
<body>

    <!-- Overlay Backdrop for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Mobile Top Navigation Header -->
    <div class="mobile-header">
        <a href="../../index.php" style="font-weight: 800; font-size: 1.25rem; color: var(--p-pink); text-decoration: none; letter-spacing: -0.5px;">SpotLight.<span style="color:var(--text-dark); font-size: 0.75rem;">Panel</span></a>
        <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Sidebar Menu Container -->
    <div class="sidebar" id="sidebarContainer">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Fotografer</span></a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Fotografer/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuJadwal">
                        <span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="../Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuUpload">
                        <span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span>
                        <i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="../Upload/index.php" class="submenu-link"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                        <span><i class="bi bi-house-door-fill me-2"></i> Beranda</span>
                    </a>
                </li>
            </ul>
        </div>
        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Keluar
            </button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Riwayat Upload</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-1" style="letter-spacing: -0.5px;">Riwayat Upload</h3>
                <p class="text-muted small mb-0">Informasi log berkas hasil foto yang telah Anda kirimkan kepada customer.</p>
            </div>
        </div>

        <!-- Alert Informasi Status Order -->
        <div class="alert border-0 mb-4" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 16px;">
            <div class="d-flex align-items-start gap-3">
                <div style="background: #bbf7d0; color: #166534; padding: 6px 10px; border-radius: 10px; font-size: 1.1rem;">
                    <i class="bi bi-patch-question"></i>
                </div>
                <div style="font-size: 0.85rem; color: #166534; line-height: 1.5;">
                    <strong style="font-weight: 700;">Petunjuk Akses File:</strong>
                    <ul class="mb-0 mt-1 ps-3" style="list-style-type: square;">
                        <li><strong style="color: #b45309;">Menunggu Pelunasan:</strong> Berkas tersimpan aman namun belum dapat diunduh oleh klien hingga pembayaran diverifikasi.</li>
                        <li><strong style="color: #15803d;">Customer Bisa Akses:</strong> Klien dapat mengunduh langsung berkas resolusi tinggi dari dashboard mereka.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Database Error Notification Block -->
        <?php if ($db_error): ?>
            <div class="card-custom text-center py-5">
                <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="bi bi-database-exclamation fs-3" style="color: #dc2626;"></i>
                </div>
                <h5 class="fw-bold" style="color: var(--text-dark);">Gagal Memuat Riwayat</h5>
                <p class="text-muted mx-auto" style="max-width: 450px; font-size: 0.85rem;">Terjadi kendala pada server saat memproses pengambilan berkas riwayat upload. Silakan lakukan pemuatan ulang halaman beberapa saat lagi.</p>
                <button onclick="window.location.reload();" class="btn-action mt-2">
                    <i class="bi bi-arrow-clockwise"></i> Muat Ulang Halaman
                </button>
            </div>
        <?php else: ?>

            <div class="card-custom">
                <div class="content-header">
                    <h5 class="content-title"><i class="bi bi-collection-play-fill text-danger me-2"></i>Daftar Berkas Terkirim</h5>
                    
                    <!-- Search Feature Client Side -->
                    <div class="search-container">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" id="tableSearch" class="search-input" placeholder="Cari nama, paket, ID...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-custom" id="historyTable">
                        <thead>
                            <tr>
                                <th>ID Sesi</th>
                                <th>Pelanggan</th>
                                <th>Paket</th>
                                <th>File Hasil</th>
                                <th>Status Order</th>
                                <th>Akses Customer</th>
                                <th>Tanggal Upload</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $has_data = false;
                            if ($q_riwayat && sqlsrv_has_rows($q_riwayat)):
                                $has_data = true;
                                while ($row = sqlsrv_fetch_array($q_riwayat, SQLSRV_FETCH_ASSOC)):
                                    $safe_id_sesi = htmlspecialchars($row['ID_Sesi_Foto'], ENT_QUOTES, 'UTF-8');
                                    $safe_pelanggan = htmlspecialchars($row['Nama_Pelanggan'], ENT_QUOTES, 'UTF-8');
                                    $safe_paket = htmlspecialchars($row['Nama_Paket'], ENT_QUOTES, 'UTF-8');
                            ?>
                                <tr>
                                    <td><span class="fw-bold text-dark">#<?= $safe_id_sesi ?></span></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= $safe_pelanggan ?></div>
                                    </td>
                                    <td>
                                        <div class="text-secondary" style="font-size: 0.8rem; font-weight: 500;"><?= $safe_paket ?></div>
                                    </td>
                                    <td>
                                        <span class="file-name d-block">
                                            <i class="bi bi-images me-1"></i><?= (int)$row['Total_Foto'] ?> Item
                                        </span>
                                        <span class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-hdd-fill me-1" style="font-size: 0.7rem;"></i><?= formatUkuran($row['Total_Ukuran']) ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_label = getStatusOrderLabel($row['Status_Order']);
                                        ?>
                                        <span class="status-badge" style="background: <?= $status_label[2] ?>; color: <?= $status_label[1] ?>; <?= $status_label[3] ?>">
                                            <?= $status_label[0] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $access_label = getCustomerAccessLabel($row['Status_Order']);
                                        ?>
                                        <span class="status-badge" style="background: <?= $access_label[2] ?>; color: <?= $access_label[1] ?>; <?= $access_label[3] ?>">
                                            <?= $access_label[0] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block" style="font-size: 0.8rem;"><?= formatTanggal($row['Tanggal_Upload_Hasil']) ?></span>
                                        <span class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-clock me-1" style="font-size: 0.7rem;"></i><?= formatWaktu($row['Tanggal_Upload_Hasil']) ?> WIB</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="../../Role/Fotografer/upload_hasil.php?id=<?= $safe_id_sesi ?>" class="btn-action" title="Lihat & Kelola Foto">
                                            <i class="bi bi-images"></i> Kelola Foto
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State Visual -->
                <?php if (!$has_data): ?>
                    <div class="text-center py-5" id="emptyState">
                        <div style="width: 80px; height: 80px; background: #fff1f2; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <i class="bi bi-cloud-slash fs-1" style="color: var(--p-pink);"></i>
                        </div>
                        <h6 class="fw-bold" style="color: var(--text-dark);">Belum Ada Berkas Upload</h6>
                        <p class="text-muted mx-auto" style="max-width: 320px; font-size: 0.85rem;">Anda belum pernah mengirimkan berkas dokumentasi hasil pemotretan sesi apapun.</p>
                        <a href="../Upload/index.php" class="btn-action mt-2">
                            <i class="bi bi-cloud-upload"></i> Upload Sekarang
                        </a>
                    </div>
                <?php else: ?>
                    <!-- JS Filter Dynamic Empty State -->
                    <div class="text-center py-5 d-none" id="searchEmptyState">
                        <div style="width: 70px; height: 70px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <i class="bi bi-search fs-3" style="color: #94a3b8;"></i>
                        </div>
                        <h6 class="fw-bold" style="color: var(--text-dark);">Pencarian Tidak Ditemukan</h6>
                        <p class="text-muted" style="font-size: 0.85rem;">Tidak ditemukan kecocokan data dengan kata kunci yang Anda masukkan.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Responsive Toggle Controller
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebarContainer = document.getElementById('sidebarContainer');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if(mobileToggle && sidebarContainer && sidebarOverlay) {
            mobileToggle.addEventListener('click', () => {
                sidebarContainer.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', () => {
                sidebarContainer.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        }

        // Submenu Accordeon Controller
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

        // Search Bar Functionality (Instant Client-Side Filtering)
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const value = this.value.toLowerCase().trim();
                const tableRows = document.querySelectorAll('#historyTable tbody tr');
                const searchEmptyState = document.getElementById('searchEmptyState');
                let foundMatch = false;

                tableRows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    if (text.includes(value)) {
                        row.style.display = '';
                        foundMatch = true;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (searchEmptyState) {
                    if (!foundMatch && value !== '') {
                        searchEmptyState.classList.remove('d-none');
                    } else {
                        searchEmptyState.classList.add('d-none');
                    }
                }
            });
        }

        // Confirmation Actions using SweetAlert2
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ 
                title: 'Konfirmasi Keluar?', 
                text: 'Sesi aktif Anda akan segera dihentikan dari sistem.', 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#D53D66', 
                cancelButtonColor: '#64748b', 
                confirmButtonText: 'Ya, Keluar', 
                cancelButtonText: 'Batal' 
            }).then((result) => { 
                if (result.isConfirmed) window.location.href = '../../logout.php'; 
            });
        }
        
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ 
                title: 'Kembali ke Beranda?', 
                text: 'Anda akan dialihkan menuju ke halaman utama website.', 
                icon: 'info', 
                showCancelButton: true, 
                confirmButtonColor: '#D53D66', 
                cancelButtonColor: '#64748b', 
                confirmButtonText: 'Ya, Beralih', 
                cancelButtonText: 'Batal' 
            }).then((result) => { 
                if (result.isConfirmed) window.location.href = '../../index.php'; 
            });
        }
    </script>
    
    <!-- Notification SweetAlert - Upload Succesful -->
    <?php if ($upload_notif): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Sesi Upload Berhasil!',
            html: '<div style="text-align:left"><p>Seluruh item file dokumentasi berhasil diunggah ke database.</p><hr style="border-color:#f1f5f9;margin:10px 0"><p style="color:#64748b;font-size:0.85rem"><i class="bi bi-info-circle-fill text-success me-1"></i> Order ini berstatus <strong>Lunas</strong>, hasil foto otomatis langsung dapat diunduh oleh pelanggan pada portal akun milik mereka.</p></div>',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Selesai'
        });
    </script>
    <?php endif; ?>
    
    <!-- Notification SweetAlert - File Deleted -->
    <?php if ($deleted_notif): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berkas Berhasil Dihapus!',
            text: 'File hasil foto yang dipilih telah dihapus permanen dari direktori penyimpanan.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Mengerti'
        });
    </script>
    <?php endif; ?>
</body>
</html>