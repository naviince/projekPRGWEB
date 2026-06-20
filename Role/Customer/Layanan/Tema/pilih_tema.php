<?php
session_start();
// Path dari Role/Customer/Layanan/Tema/ ke root projekPRGWEB/ = ../../../../koneksi.php
include '../../../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// =====================================================
// AMBIL ID PAKET & ID RUANGAN DARI STEP SEBELUMNYA (wajib ada)
// =====================================================
if (!isset($_GET['id_paket']) || empty($_GET['id_paket']) || !isset($_GET['id_ruangan']) || empty($_GET['id_ruangan'])) {
    header("Location: ../Paket/pilih_paket.php");
    exit();
}

$id_paket = (int)$_GET['id_paket'];
$id_ruangan = (int)$_GET['id_ruangan'];

// =====================================================
// AMBIL DATA PAKET (untuk tampilan header)
// =====================================================
$q_paket = sqlsrv_query($conn, 
    "SELECT Nama_Paket, Harga_Paket, Durasi_Waktu, Kapasitas_Orang, Foto_Paket 
     FROM Paket_Foto 
     WHERE ID_Paket = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_paket]
);
$d_paket = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_paket);

if (!$d_paket) {
    header("Location: ../Paket/pilih_paket.php?error=paket_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA RUANGAN (untuk tampilan header)
// =====================================================
$q_ruangan = sqlsrv_query($conn, 
    "SELECT Nama_Ruangan, Kapasitas_Ruangan, Foto_Ruangan 
     FROM Ruangan 
     WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_ruangan]
);
$d_ruangan = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_ruangan);

if (!$d_ruangan) {
    header("Location: ../Ruangan/pilih_ruangan.php?id_paket=$id_paket&error=ruangan_tidak_ditemukan");
    exit();
}

// =====================================================
// AMBIL DATA TEMA YANG TERVALIDASI VIA RUANGAN_TEMA
// Hanya tema yang terhubung dengan ruangan yang dipilih
// =====================================================
$q_tema = sqlsrv_query($conn, 
    "SELECT 
        t.ID_Tema,
        t.Nama_Tema,
        t.Kategori_Tema,
        t.Deskripsi,
        t.Foto_Tema
     FROM Tema_Foto t
     INNER JOIN Ruangan_Tema rt ON t.ID_Tema = rt.ID_Tema
     WHERE rt.ID_Ruangan = ?
       AND t.Status = 1
       AND t.Is_Deleted = 0
     ORDER BY t.Nama_Tema ASC", 
    [$id_ruangan]
);

$tema_list = [];
while ($row = sqlsrv_fetch_array($q_tema, SQLSRV_FETCH_ASSOC)) {
    $tema_list[] = $row;
}
sqlsrv_free_stmt($q_tema);

// =====================================================
// AMBIL PROFIL CUSTOMER
// =====================================================
$q_customer = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_customer]
);
$d_customer = sqlsrv_fetch_array($q_customer, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($q_customer);

$nama_customer = $d_customer['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_customer['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// Path foto
$path_foto_paket = "../../../../assets/img/paket/" . ($d_paket['Foto_Paket'] ?? 'default_paket.jpg');
$foto_paket_src = (file_exists($path_foto_paket) && $d_paket['Foto_Paket'] != 'default_paket.jpg') 
    ? $path_foto_paket 
    : "../../../../assets/img/paket/default_paket.jpg";

$path_foto_ruangan = "../../../../assets/img/ruangan/" . ($d_ruangan['Foto_Ruangan'] ?? 'default_ruangan.jpg');
$foto_ruangan_src = (file_exists($path_foto_ruangan) && $d_ruangan['Foto_Ruangan'] != 'default_ruangan.jpg') 
    ? $path_foto_ruangan 
    : "../../../../assets/img/ruangan/default_ruangan.jpg";

$harga_format = number_format($d_paket['Harga_Paket'] ?? 0, 0, ',', '.');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Tema Foto - SpotLight Studio</title>

    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        /* SIDEBAR */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex; flex-direction: column;
            justify-content: space-between; padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem;
            color: var(--p-pink); text-decoration: none;
            letter-spacing: -1px; margin-bottom: 40px; display: block;
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
            background-color: var(--light-pink); color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px;
            color: #718096; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink); background-color: rgba(216, 63, 103, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216, 63, 103, 0.2); }

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 35px;
        }
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

        /* PROGRESS BAR */
        .booking-progress {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 40px; gap: 0;
        }
        .progress-step {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .progress-step-circle {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem; transition: var(--transition-3d);
            border: 3px solid #e2e8f0; background: #ffffff; color: #94a3b8;
        }
        .progress-step.active .progress-step-circle {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-color: var(--p-pink); color: #ffffff;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.3);
        }
        .progress-step.completed .progress-step-circle {
            background: #059669; border-color: #059669; color: #ffffff;
        }
        .progress-step-label {
            font-size: 0.75rem; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .progress-step.active .progress-step-label { color: var(--p-pink); }
        .progress-step.completed .progress-step-label { color: #059669; }
        .progress-line {
            width: 60px; height: 3px; background: #e2e8f0;
            margin: 0 10px; margin-bottom: 24px;
        }
        .progress-line.completed { background: #059669; }

        /* INFO CARDS */
        .info-cards-row {
            display: flex; gap: 16px; margin-bottom: 30px; flex-wrap: wrap;
        }
        .info-card {
            background: linear-gradient(135deg, #fff, var(--s-pink));
            border: 1px solid var(--light-pink);
            border-radius: 20px; padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
            flex: 1; min-width: 250px;
        }
        .info-card-img {
            width: 60px; height: 60px; object-fit: cover;
            border-radius: 14px; border: 2px solid var(--light-pink);
        }
        .info-card-detail h5 {
            font-weight: 700; font-size: 0.95rem; margin-bottom: 2px;
        }
        .info-card-detail .info-meta {
            color: var(--text-muted); font-size: 0.8rem;
        }

        /* TEMA GRID */
        .tema-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .tema-card {
            background: #ffffff; border-radius: 24px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d); overflow: hidden;
            cursor: pointer; position: relative;
        }
        .tema-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 22px 45px rgba(216, 63, 103, 0.14);
            border-color: var(--p-pink);
        }
        .tema-card-img {
            width: 100%; height: 180px; object-fit: cover;
            border-bottom: 1px solid var(--light-pink);
        }
        .tema-card-body { padding: 24px; }
        .tema-card-badge {
            display: inline-block; padding: 4px 12px;
            background: var(--s-pink); color: var(--p-pink);
            border-radius: 50px; font-size: 0.7rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;
        }
        .tema-card-title {
            font-size: 1.2rem; font-weight: 800; color: var(--text-dark);
            margin-bottom: 8px; line-height: 1.3;
        }
        .tema-card-desc {
            font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;
            line-height: 1.5; min-height: 40px;
        }
        .btn-pilih-tema {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            font-weight: 800; font-size: 0.9rem;
            transition: var(--transition-3d); display: flex;
            align-items: center; justify-content: center; gap: 8px;
        }
        .btn-pilih-tema:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.3);
        }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 20px; }
        .empty-state h4 { font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
            .tema-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Studio Foto</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuLayanan">
                        <span><i class="bi bi-camera-fill me-2"></i> Layanan Studio</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuLayanan">
                        <ul class="list-unstyled">
                            <li><a href="../Paket/pilih_paket.php" class="submenu-link"><i class="bi bi-collection-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/pilih_ruangan.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan Studio</a></li>
                            <li><a href="pilih_tema.php" class="submenu-link active"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal/pilih_jadwal.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Tersedia</a></li>
                            <li><a href="../Portofolio/index.php" class="submenu-link"><i class="bi bi-images me-2"></i>Portofolio</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuBooking">
                        <span><i class="bi bi-calendar-check-fill me-2"></i> Booking Saya</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuBooking">
                        <ul class="list-unstyled">
                            <li><a href="../../../../Booking/Baru/index.php" class="submenu-link"><i class="bi bi-plus-circle-fill me-2"></i>Booking Baru</a></li>
                            <li><a href="../../../../Booking/Riwayat/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Booking</a></li>
                            <li><a href="../../../../Booking/Pembayaran/index.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Pembayaran</a></li>
                            <li><a href="../../../../Booking/Hasil/index.php" class="submenu-link"><i class="bi bi-download me-2"></i>Download Hasil</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuCetak">
                        <span><i class="bi bi-printer-fill me-2"></i> Barang Cetak</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuCetak">
                        <ul class="list-unstyled">
                            <li><a href="../../../../Cetak/Katalog/index.php" class="submenu-link"><i class="bi bi-shop me-2"></i>Katalog Barang</a></li>
                            <li><a href="../../../../Cetak/Pesanan/index.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Pesanan Saya</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="../../../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
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

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Pilih Tema Foto</h3>
                <p class="text-muted small mb-0">Langkah 3 dari 4 - Pilih tema yang sesuai dengan konsep foto Anda.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-person-circle me-1 text-danger"></i> <?= htmlspecialchars($nama_customer) ?>
                </span>
                <div class="profile-header-btn shadow-sm" title="Profil Anda">
                    <img src="<?= $foto_customer_src ?>" alt="Profil">
                </div>
            </div>
        </div>

        <!-- PROGRESS BAR -->
        <div class="booking-progress">
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Paket</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="progress-step-circle"><i class="bi bi-check-lg"></i></div>
                <div class="progress-step-label">Ruangan</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="progress-step-circle">3</div>
                <div class="progress-step-label">Tema</div>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step">
                <div class="progress-step-circle">4</div>
                <div class="progress-step-label">Jadwal</div>
            </div>
        </div>

        <!-- INFO CARDS: PAKET & RUANGAN -->
        <div class="info-cards-row">
            <div class="info-card">
                <img src="<?= $foto_paket_src ?>" class="info-card-img" alt="<?= htmlspecialchars($d_paket['Nama_Paket']) ?>">
                <div class="info-card-detail">
                    <h5><?= htmlspecialchars($d_paket['Nama_Paket']) ?></h5>
                    <div class="info-meta">Rp <?= $harga_format ?> &bull; <?= $d_paket['Durasi_Waktu'] ?> menit</div>
                </div>
            </div>
            <div class="info-card">
                <img src="<?= $foto_ruangan_src ?>" class="info-card-img" alt="<?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?>">
                <div class="info-card-detail">
                    <h5><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></h5>
                    <div class="info-meta">Kapasitas <?= $d_ruangan['Kapasitas_Ruangan'] ?> orang</div>
                </div>
            </div>
        </div>

        <!-- TEMA GRID -->
        <div class="tema-grid">
            <?php
            if (!empty($tema_list)):
                foreach($tema_list as $row):
                    $path_img = "../../../../assets/img/tema/" . ($row['Foto_Tema'] ?? '');
                    $img_src = (!empty($row['Foto_Tema']) && file_exists($path_img))
                        ? $path_img 
                        : "../../../../assets/img/tema/default_tema.jpg";
            ?>
                <div class="tema-card animate-fade-in-up" onclick="pilihTema(<?= $row['ID_Tema'] ?>, '<?= htmlspecialchars(addslashes($row['Nama_Tema'])) ?>')">
                    <img src="<?= $img_src ?>" class="tema-card-img" alt="<?= htmlspecialchars($row['Nama_Tema']) ?>">
                    <div class="tema-card-body">
                        <div class="tema-card-badge"><?= htmlspecialchars($row['Kategori_Tema'] ?? 'Tema Foto') ?></div>
                        <div class="tema-card-title"><?= htmlspecialchars($row['Nama_Tema']) ?></div>
                        <div class="tema-card-desc"><?= htmlspecialchars($row['Deskripsi'] ?? 'Tema foto ' . $row['Nama_Tema'] . ' untuk sesi foto Anda.') ?></div>
                        <button class="btn-pilih-tema" id="btn-tema-<?= $row['ID_Tema'] ?>">
                            <i class="bi bi-check-circle-fill"></i>
                            Pilih Tema Ini
                        </button>
                    </div>
                </div>
            <?php 
                endforeach; 
            else:
            ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="bi bi-palette"></i>
                    <h4>Tidak Ada Tema Tersedia</h4>
                    <p>Maaf, tidak ada tema yang terhubung dengan ruangan <strong><?= htmlspecialchars($d_ruangan['Nama_Ruangan']) ?></strong>.<br>Silakan kembali dan pilih ruangan lain.</p>
                    <a href="../Ruangan/pilih_ruangan.php?id_paket=<?= $id_paket ?>" class="btn-pilih-tema text-decoration-none d-inline-block mt-3" style="width: auto; padding: 12px 30px;">
                        <i class="bi bi-arrow-left me-2"></i>Kembali ke Pilih Ruangan
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

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

        function pilihTema(idTema, namaTema) {
            Swal.fire({
                title: 'Pilih Tema Ini?',
                text: 'Anda akan memilih tema "' + namaTema + '"',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pilih',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../Jadwal/pilih_jadwal.php?id_paket=<?= $id_paket ?>&id_ruangan=<?= $id_ruangan ?>&id_tema=' + idTema;
                }
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar?',
                text: 'Apakah Anda yakin ingin keluar dari akun?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama.',
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
        }
    </script>
</body>
</html>