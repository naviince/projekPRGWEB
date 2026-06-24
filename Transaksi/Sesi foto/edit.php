<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// Ambil ID Sesi dari URL
$id_sesi = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($id_sesi)) {
    header("Location: index.php");
    exit();
}

// Ambil data sesi, pelanggan, dan order
$sql = "SELECT s.*, p.Nama_Pelanggan, o.ID_Order 
        FROM Sesi_Foto s 
        JOIN [Order] o ON s.ID_Order = o.ID_Order 
        JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan 
        WHERE s.ID_Sesi_Foto = ?";
$params = array($id_sesi);
$query = sqlsrv_query($conn, $sql, $params);
$d = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if (!$d) {
    die("Data sesi tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Sesi & Upload Foto – SpotLight Studio</title>
    
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; 
            --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* GLOBAL RESET */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        ul, li { list-style: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); }
 /* SIDEBAR STYLING */
        .sidebar {
            width: 260px;
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
            background-color: rgba(213, 61, 102, 0.03);
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
            box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2);
        }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        
        /* HEADER */
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .clock-badge { background: var(--light-pink); color: var(--text-dark); padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }

        /* CARD MAIN (FULL WIDTH) */
        .card-main { background: #ffffff; border-radius: 24px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 10px 30px rgba(0,0,0,0.02); padding: 40px; width: 100%; }
        
        /* INFO PANEL PELANGGAN */
        .customer-info-panel { background: #fff5f7; border: 1.5px dashed var(--p-pink); border-radius: 16px; padding: 25px; text-align: center; margin-bottom: 40px; }
        .customer-info-panel small { font-weight: 800; color: var(--p-pink); text-transform: uppercase; letter-spacing: 1px; }
        .customer-info-panel h3 { font-weight: 800; margin-top: 5px; color: var(--text-dark); }

        /* FORM ELEMENTS */
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 10px; display: block; }
        .required-star { color: var(--p-pink); font-weight: bold; }
        
        .form-control, .form-select { border-radius: 14px; padding: 14px 18px; border: 2px solid #eef2f6; background: #f8fafc; font-size: 14px; font-weight: 600; transition: var(--transition-3d); color: var(--text-dark); }
        .form-control:focus, .form-select:focus { border-color: var(--p-pink); background: #ffffff; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(213, 61, 102, 0.1); outline: none; }

        /* BUTTONS */
        .btn-reg { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 16px; padding: 16px 45px; font-weight: 800; border: none; transition: var(--transition-3d); font-size: 15px; box-shadow: 0 10px 25px rgba(213, 61, 102, 0.25); cursor: pointer; }
        .btn-reg:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.35); color: white; }
        .btn-back { border-radius: 16px; padding: 16px 35px; font-weight: 700; color: #718096; text-decoration: none; transition: 0.3s; background: #f1f5f9; display: inline-block; }
        .btn-back:hover { background: #e2e8f0; color: #1e1e24; }
    </style>
</head>
<body>

    <!-- SIDEBAR (Identik dengan Template Anda) -->
     <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Panel Administrator</span>
            </a>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                
                <!-- DATA MASTER -->
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

                <!-- TRANSAKSI -->
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

                <!-- SESI FOTO -->
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuSesi">
                        <span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuSesi">
                        <ul class="list-unstyled">
                            <li><a href="./index.php" class="submenu-link active"><i class="bi bi-upload-fill me-2"></i>Upload Hasil Foto</a></li>
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
        <!-- HEADER WITH CLOCK -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Update & Upload Hasil Foto 📁</h3>
                <p class="text-muted small mb-0">Kelola status progres dan unggah dokumentasi untuk pelanggan.</p>
            </div>
            <div class="clock-badge">
                <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
            </div>
        </div>

        <div class="card-main">
            <!-- Customer Banner -->
            <div class="customer-info-panel">
                <small>Informasi Pelanggan</small>
                <h3><?= htmlspecialchars($d['Nama_Pelanggan']) ?></h3>
                <span class="badge bg-white text-danger border px-3 py-2 mt-2" style="border-radius: 10px; font-weight: 700;">BOOKING ID: #<?= $d['ID_Order'] ?></span>
            </div>

            <!-- Form Full Width -->
            <form action="action_foto.php?act=edit" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_sesi" value="<?= $d['ID_Sesi_Foto'] ?>">

                <div class="row g-4">
                    <!-- Status Sesi (Full Width) -->
                    <div class="col-md-12">
                        <label class="form-label">Status Progres Sesi <span class="required-star">*</span></label>
                        <select name="status_sesi" class="form-select" required>
                            <option value="0" <?= $d['Status_Sesi'] == 0 ? 'selected' : '' ?>>Terjadwal (Menunggu Jadwal)</option>
                            <option value="1" <?= $d['Status_Sesi'] == 1 ? 'selected' : '' ?>>Proses (Sedang Pemotretan/Editing)</option>
                            <option value="2" <?= $d['Status_Sesi'] == 2 ? 'selected' : '' ?>>Selesai (File Siap Diunduh)</option>
                        </select>
                    </div>

                    <!-- File Upload (Full Width) -->
                    <div class="col-md-12">
                        <label class="form-label">Unggah File Hasil (Format ZIP/JPG) <span class="required-star">*</span></label>
                        <input type="file" name="file_hasil" class="form-control">
                        
                        <?php if (!empty($d['File_Hasil'])) : ?>
                            <div class="mt-3 p-3 border rounded-3 bg-light d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-zip-fill fs-2 text-primary"></i>
                                <div>
                                    <div class="small fw-bold text-dark">File saat ini terunggah:</div>
                                    <div class="small text-muted text-break"><?= htmlspecialchars($d['File_Hasil']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle me-1 text-primary"></i> Gunakan format <b>.zip</b> untuk mengunggah banyak foto sekaligus.
                        </small>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="mt-5 d-flex gap-3">
                    <button type="submit" class="btn btn-reg">Simpan Perubahan & Unggah ✨</button>
                    <a href="index.php" class="btn btn-back">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // Toggle Submenu Sidebar
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');
                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    if (!isShown) {
                        targetEl.classList.add('show');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    } else {
                        targetEl.classList.remove('show');
                        if (chevron) chevron.style.transform = 'rotate(0deg)';
                    }
                }
            });
        });

        // Live Clock pojok kanan atas
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            document.getElementById('live-clock').innerText = now.toLocaleDateString('id-ID', options) + " WIB";
        }
        setInterval(updateClock, 1000); updateClock();
    </script>
</body>
</html>