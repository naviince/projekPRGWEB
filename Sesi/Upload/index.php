<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES FOTOGRAFER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

// =====================================================
// QUERY: SESI SELESAI YANG BELUM/BELUM LENGKAP UPLOAD
// =====================================================
$q_list = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Waktu_Selesai,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        R.Nama_Ruangan,
        J.Tanggal_Jadwal,
        J.Jam_Mulai,
        J.Jam_Selesai
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
    WHERE S.ID_Karyawan = ? AND S.Status = 1 AND S.Status_Sesi = 1
    ORDER BY S.Waktu_Selesai DESC
", array($id_fotografer));

// =====================================================
// AMBIL DATA PROFIL FOTOGRAFER
// =====================================================
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_fotografer));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) {
    $d_profile = array_change_key_case($d_profile, CASE_LOWER);
}

$nama_fotografer = $d_profile['nama_karyawan'] ?? 'Fotografer';
$foto_fotografer = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_fotografer)) 
    ? "../../assets/img/pelanggan/" . $foto_fotografer 
    : $default_svg_avatar;

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $date->format('d') . ' ' . $bulan[intval($date->format('m')) - 1] . ' ' . $date->format('Y');
}

function formatWaktu($time) {
    if (!$time) return '-';
    if (is_string($time)) {
        $time = new DateTime($time);
    }
    return $time->format('H:i');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Hasil Foto – SpotLight Studio</title>

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
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }

        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
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
        .submenu.show { display: block !important; }
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

        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }

        .card-3d {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            transition: var(--transition-3d);
            padding: 25px;
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
            transform: translateY(-4px);
            box-shadow: 0 22px 45px rgba(213, 61, 102, 0.1); 
            border-color: var(--p-pink);
        }
        .card-3d:hover::before { opacity: 1; }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .content-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .sesi-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: linear-gradient(135deg, #ffffff, #FFF0F3);
            border-radius: 16px;
            margin-bottom: 12px;
            transition: var(--transition-3d);
            border: 2px solid transparent;
        }
        .sesi-item:hover {
            transform: translateX(6px);
            border-color: var(--p-pink);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.1);
        }
        .sesi-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .sesi-time {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--p-pink);
        }
        .sesi-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        .sesi-info {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-upload { background: #dbeafe; color: #2563eb; }
        .badge-belum { background: #fffbeb; color: #d97706; }

        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 0.8rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(213, 61, 102, 0.25);
            color: #ffffff;
        }
        .btn-action-success {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .btn-action-success:hover {
            box-shadow: 0 6px 15px rgba(5, 150, 105, 0.25);
        }
        .btn-action-secondary {
            background: #f1f5f9;
            color: var(--text-muted);
        }
        .btn-action-secondary:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Panel Fotografer</span>
            </a>

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
                            <li><a href="../../Sesi/Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../../Sesi/Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../../Sesi/Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
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
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="../../Sesi/RiwayatUpload/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
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

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Upload Foto</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Upload Hasil Foto</h3>
                <p class="text-muted small mb-0">Pilih sesi foto yang sudah selesai untuk upload hasil.</p>
            </div>
        </div>

        <div class="card-3d animate-fade-in">
            <div class="content-header">
                <h5 class="content-title"><i class="bi bi-list-check text-danger me-2"></i>Daftar Sesi Selesai</h5>
                <span class="badge" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem;">
                    Sesi Selesai
                </span>
            </div>

            <?php
            $has_data = false;
            if ($q_list && sqlsrv_has_rows($q_list)):
                $has_data = true;
                while ($row = sqlsrv_fetch_array($q_list, SQLSRV_FETCH_ASSOC)):
                    $is_uploaded = !empty($row['File_Hasil']);
                    $status_class = $is_uploaded ? 'badge-upload' : 'badge-belum';
                    $status_text = $is_uploaded ? 'Sudah Upload' : 'Belum Upload';
                    $icon_bg = $is_uploaded 
                        ? 'background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb;' 
                        : 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;';
            ?>
                <div class="sesi-item">
                    <div class="sesi-icon" style="<?= $icon_bg ?>">
                        <i class="bi bi-camera-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="sesi-title"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                <div class="sesi-info">
                                    <?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?>
                                </div>
                                <div class="sesi-time mt-1">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= formatTanggal($row['Tanggal_Jadwal']) ?> • 
                                    <?= formatWaktu($row['Jam_Mulai']) ?> - <?= formatWaktu($row['Jam_Selesai']) ?>
                                </div>
                                <?php if ($is_uploaded): ?>
                                    <div class="sesi-info mt-1">
                                        <i class="bi bi-file-earmark-check me-1 text-success"></i>
                                        <?= htmlspecialchars($row['File_Hasil']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="badge-status <?= $status_class ?>"><?= $status_text ?></span>
                        </div>
                    </div>
                    <a href="../../Role/Fotografer/upload_hasil.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action <?= $is_uploaded ? 'btn-action-secondary' : '' ?>">
                        <i class="bi bi-cloud-upload"></i> <?= $is_uploaded ? 'Upload Ulang' : 'Upload' ?>
                    </a>
                </div>
            <?php 
                endwhile; 
            endif;

            if (!$has_data):
            ?>
                <div class="text-center py-5">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="bi bi-inbox fs-1" style="color: #94a3b8;"></i>
                    </div>
                    <h6 class="fw-bold text-muted">Tidak Ada Sesi Selesai</h6>
                    <p class="text-muted" style="font-size: 0.85rem;">Belum ada sesi foto yang selesai dan siap diupload.</p>
                    <a href="../../Sesi/Selesai/index.php" class="btn-action mt-2">
                        <i class="bi bi-check-circle"></i> Lihat Sesi Selesai
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
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

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar?',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
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
                text: 'Anda akan dialihkan ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = '../../index.php';
            });
        }
    </script>
</body>
</html>