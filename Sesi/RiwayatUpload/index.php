<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];

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
// QUERY: SEMUA SESI YANG SUDAH UPLOAD FILE
// Hanya tampilkan sesi yang File_Hasil IS NOT NULL
// =====================================================
$q_riwayat = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto, S.ID_Order, S.File_Hasil, S.Tanggal_Upload_Hasil,
        S.Waktu_Mulai, S.Waktu_Selesai,
        P.Nama_Pelanggan, PK.Nama_Paket, R.Nama_Ruangan,
        J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai,
        O.Status_Order
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
    WHERE S.ID_Karyawan = ? AND S.Status = 1 AND S.File_Hasil IS NOT NULL
    ORDER BY S.Tanggal_Upload_Hasil DESC
", array($id_fotografer));

function formatTanggal($date) {
    if (!$date) return '-';
    if (is_string($date)) $date = new DateTime($date);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $date->format('d').' '.$bulan[intval($date->format('m'))-1].' '.$date->format('Y');
}

// Helper: Status Order label
function getStatusOrderLabel($status) {
    switch ($status) {
        case 0: return ['Menunggu DP', '#f59e0b', '#fffbeb'];
        case 1: return ['DP Terverifikasi', '#3b82f6', '#eff6ff'];
        case 2: return ['Selesai', '#8b5cf6', '#f5f3ff'];
        case 3: return ['Lunas', '#059669', '#ecfdf5'];
        case 4: return ['Dibatalkan', '#dc2626', '#fef2f2'];
        default: return ['Unknown', '#718096', '#f8fafc'];
    }
}

// Helper: Customer access status
function getCustomerAccessLabel($status_order) {
    if ($status_order == 3) {
        return ['<i class="bi bi-check-circle-fill me-1"></i> Customer Bisa Akses', '#059669', '#ecfdf5'];
    } elseif ($status_order == 4) {
        return ['<i class="bi bi-x-circle-fill me-1"></i> Order Dibatalkan', '#dc2626', '#fef2f2'];
    } else {
        return ['<i class="bi bi-hourglass-split me-1"></i> Menunggu Pelunasan', '#d97706', '#fffbeb'];
    }
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
    <title>Riwayat Upload – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096; --sidebar-bg: #ffffff; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); padding: 25px; position: relative; overflow: hidden; }
        .card-3d::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--p-pink), var(--accent-pink)); opacity: 0; transition: opacity 0.3s ease; }
        .card-3d:hover { transform: translateY(-4px); box-shadow: 0 22px 45px rgba(213, 61, 102, 0.1); border-color: var(--p-pink); }
        .card-3d:hover::before { opacity: 1; }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .content-title { font-weight: 700; font-size: 1.1rem; color: var(--text-dark); }
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom th { padding: 14px 16px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); background: #f8fafc; border-bottom: 2px solid #f1f5f9; }
        .table-custom td { padding: 16px; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .table-custom tr:hover td { background: linear-gradient(135deg, #ffffff, #FFF0F3); }
        .file-name { font-weight: 700; color: var(--p-pink); }
        .btn-action { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 8px; padding: 6px 14px; font-weight: 700; font-size: 0.75rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(213, 61, 102, 0.25); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg, #059669, #047857); }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-muted); }
        .btn-action-secondary:hover { background: #e2e8f0; color: var(--text-dark); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Fotografer</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Fotografer/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuJadwal"><span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
                    <div class="submenu" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="../Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuUpload"><span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span><i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i></a>
                    <div class="submenu show" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="../Upload/index.php" class="submenu-link"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button></div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="../../Role/Fotografer/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Riwayat Upload</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Riwayat Upload</h3>
                <p class="text-muted small mb-0">Semua file hasil foto yang pernah diupload.</p>
            </div>
        </div>

        <div class="card-3d animate-fade-in">
            <div class="content-header">
                <h5 class="content-title"><i class="bi bi-clock-history text-danger me-2"></i>Daftar File Upload</h5>
            </div>

            <!-- INFO STATUS -->
            <div class="alert border-0 mb-4" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 14px;">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                    <div style="font-size: 0.85rem; color: #1e40af;">
                        <strong>Keterangan Status:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <li><span style="color:#d97706;font-weight:700">Menunggu Pelunasan</span> — Customer belum bisa melihat hasil foto. Tunggu pelunasan terverifikasi admin.</li>
                            <li><span style="color:#059669;font-weight:700">Customer Bisa Akses</span> — Order sudah lunas, customer dapat melihat dan download hasil foto.</li>
                            <li><span style="color:#dc2626;font-weight:700">Order Dibatalkan</span> — Order dibatalkan, file tidak akan diteruskan ke customer.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID Sesi</th>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th>Nama File</th>
                            <th>Status Order</th>
                            <th>Akses Customer</th>
                            <th>Tanggal Upload</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $has_data = false;
                        if ($q_riwayat && sqlsrv_has_rows($q_riwayat)):
                            $has_data = true;
                            while ($row = sqlsrv_fetch_array($q_riwayat, SQLSRV_FETCH_ASSOC)):
                        ?>
                            <tr>
                                <td><span class="fw-bold">#<?= $row['ID_Sesi_Foto'] ?></span></td>
                                <td><?= htmlspecialchars($row['Nama_Pelanggan']) ?></td>
                                <td><?= htmlspecialchars($row['Nama_Paket']) ?></td>
                                <td><span class="file-name"><i class="bi bi-file-earmark-zip me-1"></i><?= htmlspecialchars($row['File_Hasil']) ?></span></td>
                                <td>
                                    <?php 
                                    $status_label = getStatusOrderLabel($row['Status_Order']);
                                    ?>
                                    <span style="background: <?= $status_label[2] ?>; color: <?= $status_label[1] ?>; padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; display: inline-block;">
                                        <?= $status_label[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $access_label = getCustomerAccessLabel($row['Status_Order']);
                                    ?>
                                    <span style="background: <?= $access_label[2] ?>; color: <?= $access_label[1] ?>; padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; display: inline-block;">
                                        <?= $access_label[0] ?>
                                    </span>
                                </td>
                                <td><?= formatTanggal($row['Tanggal_Upload_Hasil']) ?><br><small class="text-muted"><?= formatWaktu($row['Tanggal_Upload_Hasil']) ?></small></td>
                                <td>
                                    <a href="../../uploads/hasil/<?= rawurlencode($row['File_Hasil']) ?>" class="btn-action btn-action-success" download title="Download File">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <a href="../../Role/Fotografer/upload_hasil.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-action-secondary mt-1" title="Upload Ulang">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$has_data): ?>
                <div class="text-center py-5">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="bi bi-cloud-slash fs-1" style="color: #94a3b8;"></i>
                    </div>
                    <h6 class="fw-bold text-muted">Belum Ada Upload</h6>
                    <p class="text-muted" style="font-size: 0.85rem;">Anda belum pernah mengupload file hasil foto.</p>
                    <a href="../Upload/index.php" class="btn-action mt-2">
                        <i class="bi bi-cloud-upload"></i> Upload Sekarang
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
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }
    </script>
    <!-- Notifikasi Upload Sukses -->
    <?php if ($upload_notif): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Upload Berhasil!',
            html: '<div style="text-align:left"><p>File hasil foto berhasil diupload dan tersimpan.</p><hr style="border-color:#f1f5f9;margin:10px 0"><p style="color:#718096;font-size:0.85rem"><i class="bi bi-info-circle-fill text-warning me-1"></i> File akan tersedia untuk customer setelah <strong style="color:#D53D66">pelunasan terverifikasi</strong> oleh admin.</p></div>',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Mengerti'
        });
    </script>
    <?php endif; ?>
    <!-- Notifikasi Hapus Sukses -->
    <?php if ($deleted_notif): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'File Dihapus!',
            text: 'File hasil foto berhasil dihapus dari sistem.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Oke'
        });
    </script>
    <?php endif; ?>
</body>
</html>