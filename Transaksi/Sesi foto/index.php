<?php
session_start();
include '../../koneksi.php';

// --- 1. PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// --- 2. AMBIL PROFIL ADMIN ---
$q_admin = sqlsrv_query($conn, "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", array($id_admin));
$admin_data = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;

// --- 3. LOGIKA PENCARIAN ---
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$filter_cari = "";
$params = array();

if (!empty($cari)) {
    // Mencari berdasarkan Nama Pelanggan atau ID Order
    $filter_cari = " AND (pl.Nama_Pelanggan LIKE ? OR o.ID_Order LIKE ?)";
    $params = array("%$cari%", "%$cari%");
}

// --- 4. QUERY STATISTIK (Disesuaikan dengan filter cari) ---
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN s.File_Hasil IS NULL OR s.File_Hasil = '' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.File_Hasil IS NOT NULL AND s.File_Hasil <> '' THEN 1 ELSE 0 END) as selesai
    FROM Sesi_Foto s
    JOIN [Order] o ON s.ID_Order = o.ID_Order
    JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
    WHERE s.Status = 1 $filter_cari";

$q_stats = sqlsrv_query($conn, $sql_stats, $params);
$stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

// --- 5. QUERY LIST DATA SESI FOTO (Disesuaikan dengan filter cari) ---
$sql_list = "SELECT s.*, o.ID_Order, pl.Nama_Pelanggan, k.Nama_Karyawan 
             FROM Sesi_Foto s
             JOIN [Order] o ON s.ID_Order = o.ID_Order
             JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
             JOIN Karyawan k ON s.ID_Karyawan = k.ID_Karyawan
             WHERE s.Status = 1 $filter_cari
             ORDER BY CASE WHEN s.File_Hasil IS NULL OR s.File_Hasil = '' THEN 0 ELSE 1 END, s.Waktu_Mulai DESC";

$query = sqlsrv_query($conn, $sql_list, $params);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Hasil Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
             --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --sidebar-bg: #ffffff;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }

        /* SIDEBAR (Template Matching) */


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


        /* CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); padding: 20px; position: relative; overflow: hidden; }
        .card-3d:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.1); }

        /* STATS */
        .stats-row { display: flex; gap: 16px; margin-bottom: 30px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .stat-icon-pink { background: var(--s-pink); color: var(--p-pink); }
        .stat-icon-orange { background: #fff7ed; color: #ea580c; }
        .stat-icon-green { background: #ecfdf5; color: #059669; }

        /* TABLE */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table thead th { background: #ffffff; padding: 16px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; border-bottom: 2px solid #f1f5f9; }
        .data-table tbody td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table tbody tr:hover { background-color: #FFF8F0; }

        /* BUTTONS */
        .btn-upload-task { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 12px; padding: 8px 16px; font-weight: 700; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(213, 61, 102, 0.2); }
        .btn-upload-task:hover { color: white; transform: translateY(-2px); }
        
        .badge-status { font-size: 0.7rem; font-weight: 700; padding: 6px 12px; border-radius: 50px; }
        .badge-pending { background: #fff7ed; color: #ea580c; }
        .badge-selesai { background: #ecfdf5; color: #059669; }

        /* Menghilangkan titik-titik pada seluruh menu sidebar */
.nav-menu, 
.nav-menu li, 
.submenu, 
.submenu li, 
.list-unstyled {
    list-style: none !important;
    list-style-type: none !important;
    padding-left: 0 !important;
    margin-left: 0 !important;
}

/* Memastikan item navigasi tidak memiliki gaya list */
.nav-item {
    list-style: none !important;
    list-style-type: none !important;
}

/* SEARCH & FILTER BAR */
.search-filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; }
.search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; }
.search-input-wrapper { position: relative; flex: 1; }
.search-icon-inside { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-input-main {
    width: 100%; border: 2px solid #e2e8f0; border-radius: 14px;
    padding: 12px 18px 12px 45px; font-weight: 600; font-size: 0.9rem;
    color: #1e293b; transition: 0.3s; background: #ffffff;
}
.search-input-main:focus { outline: none; border-color: var(--p-pink); }

.btn-filter-custom {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: #ffffff; border: none; border-radius: 14px;
    padding: 12px 24px; font-weight: 700; font-size: 0.9rem;
    display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.3s;
}
.btn-filter-custom:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(213, 61, 102, 0.3); }

.btn-search-icon-only {
    background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px;
    padding: 12px 16px; color: #94a3b8; transition: 0.3s; display: flex; align-items: center;
}
.btn-search-icon-only:hover { border-color: var(--p-pink); color: var(--p-pink); }
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Upload Hasil Foto 📁</h3>
                <p class="text-muted small">Kelola dan unggah file dokumentasi untuk sesi foto yang telah selesai.</p>
            </div>
            <div class="text-end">
                <span class="badge px-3 py-2 text-dark border shadow-sm bg-white" style="border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">...</span>
                </span>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-row">
            <div class="card-3d flex-fill">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-pink"><i class="bi bi-camera-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL SESI</div>
                        <div class="h4 fw-800 mb-0"><?= $stats['total'] ?> Sesi</div>
                    </div>
                </div>
            </div>
            <div class="card-3d flex-fill" style="border-left: 5px solid #ea580c;">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-orange"><i class="bi bi-cloud-arrow-up"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">BELUM UPLOAD</div>
                        <div class="h4 fw-800 mb-0" style="color: #ea580c;"><?= $stats['pending'] ?> Sesi</div>
                    </div>
                </div>
            </div>
            <div class="card-3d flex-fill" style="border-left: 5px solid #059669;">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-green"><i class="bi bi-check-all"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">SELESAI</div>
                        <div class="h4 fw-800 mb-0" style="color: #059669;"><?= $stats['selesai'] ?> Sesi</div>
                    </div>
                </div>
            </div>
        </div>

  <!-- SEARCH & FILTER SECTION -->
<div class="search-filter-bar">
    <form method="GET" action="" class="search-form-flex">
        <!-- Input Utama -->
        <div class="search-input-wrapper">
            <i class="bi bi-search search-icon-inside"></i>
            <input type="text" name="cari" class="search-input-main" 
                   placeholder="Cari nama pelanggan atau ID order..." 
                   value="<?= htmlspecialchars($cari) ?>">
        </div>

        <!-- Tombol Filter Pink -->
        <button type="submit" class="btn btn-filter-custom">
    <i class="bi bi-search"></i> Cari Data
</button>   

        <!-- Tombol Cari Icon Saja -->
        
    </form>
</div>

        <!-- TABLE -->
        <div class="card-3d">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Jadwal & Fotografer</th>
                            <th>Status File</th>
                            <th class="text-center">Aksi Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td>
                                <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                <div class="text-muted small">Order ID: #<?= $row['ID_Order'] ?></div>
                            </td>
                            <td>
                                <div class="fw-600 mb-1" style="font-size: 0.85rem;">
    <i class="bi bi-calendar-event me-1 text-danger"></i> 
    <?= $row['Waktu_Mulai'] ? $row['Waktu_Mulai']->format('d M Y, H:i') : '<span class="text-muted">Belum diatur</span>' ?>
</div>
                            </td>
                            <td>
                                <?php if(!$row['File_Hasil']): ?>
                                    <span class="badge-status badge-pending">PENDING</span>
                                <?php else: ?>
                                    <span class="badge-status badge-selesai">TERUNGGAH</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if(!$row['File_Hasil']): ?>
                                    <a href="edit.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-upload-task">
                                        <i class="bi bi-cloud-upload-fill"></i> Upload Sekarang
                                    </a>
                                <?php else: ?>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="../../../assets/img/hasil_foto/<?= $row['File_Hasil'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat File" style="border-radius: 10px;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn btn-sm btn-outline-danger" style="border-radius: 10px; font-weight: 700;">Update</a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Toggle Submenu (Template Logic)
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');
                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
                    if (!isShown) {
                        targetEl.classList.add('show');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
        });

        // Live Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('live-clock').innerText = now.toLocaleTimeString('id-ID') + " WIB";
        }
        setInterval(updateClock, 1000); updateClock();

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: "Anda akan keluar dari sesi administrator.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                confirmButtonText: 'Keluar'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }
    </script>
</body>
</html>