<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_sesi = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($id_sesi)) {
    header("Location: index.php");
    exit();
}

// Ambil data sesi lengkap
$sql = "SELECT s.ID_Sesi_Foto, s.Status_Sesi, s.File_Hasil, s.Waktu_Mulai, s.Waktu_Selesai, s.Tanggal_Upload_Hasil,
               o.ID_Order, p.Nama_Pelanggan, k.Nama_Karyawan
        FROM Sesi_Foto s
        JOIN [Order] o    ON s.ID_Order   = o.ID_Order
        JOIN Pelanggan p  ON o.ID_Pelanggan = p.ID_Pelanggan
        JOIN Karyawan k   ON s.ID_Karyawan  = k.ID_Karyawan
        WHERE s.ID_Sesi_Foto = ? AND s.Status = 1";
$query = sqlsrv_query($conn, $sql, array($id_sesi));
$d = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if (!$d) {
    header("Location: index.php?msg=error_validasi");
    exit();
}

// Format waktu
$waktu_mulai = $d['Waktu_Mulai'] instanceof DateTime
    ? $d['Waktu_Mulai']->format('d M Y, H:i')
    : ($d['Waktu_Mulai'] ? date('d M Y, H:i', strtotime($d['Waktu_Mulai'])) : '-');

$tgl_upload = $d['Tanggal_Upload_Hasil'] instanceof DateTime
    ? $d['Tanggal_Upload_Hasil']->format('d M Y, H:i')
    : '-';

// Status label
$status_labels = [
    0 => ['text' => 'Terjadwal',  'class' => 'status-terjadwal', 'icon' => 'bi-calendar-event'],
    1 => ['text' => 'Proses',     'class' => 'status-proses',    'icon' => 'bi-camera-reels'],
    2 => ['text' => 'Selesai',    'class' => 'status-selesai',   'icon' => 'bi-check-circle'],
];
$current_status = $status_labels[$d['Status_Sesi']] ?? $status_labels[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Sesi Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096;
            --body-bg: #f8fafc; --sidebar-bg: #ffffff;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        ul, li { list-style: none !important; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); }

        /* SIDEBAR */
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background: rgba(213,61,102,0.04); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }

        /* MAIN */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .clock-badge { background: var(--light-pink); color: var(--text-dark); padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }

        /* LAYOUT 2 KOLOM */
        .edit-layout { display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start; }

        /* PANEL INFO KIRI */
        .info-panel { background: #fff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); padding: 30px; }
        .customer-avatar-area { text-align: center; padding-bottom: 24px; border-bottom: 2px dashed #f1f5f9; margin-bottom: 24px; }
        .customer-avatar { width: 70px; height: 70px; border-radius: 50%; background: var(--s-pink); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--p-pink); margin: 0 auto 12px; }
        .customer-name { font-weight: 800; font-size: 1.1rem; color: var(--text-dark); }
        .booking-badge { display: inline-block; background: var(--s-pink); color: var(--p-pink); font-weight: 800; font-size: 0.75rem; padding: 4px 14px; border-radius: 50px; margin-top: 6px; }

        .info-row { display: flex; flex-direction: column; gap: 14px; }
        .info-item { display: flex; align-items: flex-start; gap: 12px; }
        .info-item-icon { width: 34px; height: 34px; border-radius: 10px; background: var(--s-pink); display: flex; align-items: center; justify-content: center; color: var(--p-pink); font-size: 0.9rem; flex-shrink: 0; }
        .info-item-text { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }
        .info-item-val  { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); margin-top: 2px; }

        /* STATUS BADGES */
        .status-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 800; padding: 5px 14px; border-radius: 50px; }
        .status-terjadwal { background: #eff6ff; color: #3b82f6; }
        .status-proses    { background: #fffbeb; color: #d97706; }
        .status-selesai   { background: #ecfdf5; color: #059669; }

        /* FORM CARD KANAN */
        .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); padding: 36px; }
        .form-card-title { font-weight: 800; font-size: 1.05rem; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .form-card-title i { color: var(--p-pink); }

        .form-section-title { font-weight: 800; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .required-star { color: var(--p-pink); }
        .form-control, .form-select {
            border-radius: 14px; padding: 14px 18px;
            border: 2px solid #eef2f6; background: #f8fafc;
            font-size: 14px; font-weight: 600; color: var(--text-dark);
            transition: var(--transition-3d);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--p-pink); background: #fff;
            transform: translateY(-2px); box-shadow: 0 10px 20px rgba(213,61,102,0.08);
            outline: none;
        }

        /* FILE CURRENT */
        .file-current-box { background: #f8fafc; border: 2px solid #eef2f6; border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; margin-top: 12px; }
        .file-icon { font-size: 2rem; color: #3b82f6; }
        .file-name { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); word-break: break-all; }
        .file-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
        .btn-lihat-file { background: #eff6ff; color: #3b82f6; border: none; border-radius: 10px; padding: 6px 16px; font-size: 0.78rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: 0.2s; }
        .btn-lihat-file:hover { background: #3b82f6; color: #fff; }

        /* BUTTONS */
        .btn-save { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 16px; padding: 15px 44px; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: var(--transition-3d); box-shadow: 0 8px 20px rgba(213,61,102,0.2); }
        .btn-save:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 14px 30px rgba(213,61,102,0.3); }
        .btn-cancel { background: #f1f5f9; color: #718096; border-radius: 16px; padding: 15px 32px; font-weight: 700; font-size: 0.95rem; text-decoration: none; transition: 0.3s; display: inline-block; }
        .btn-cancel:hover { background: #e2e8f0; color: var(--text-dark); }

        /* UPLOAD HINT */
        .upload-hint { background: #fffbeb; border: 1.5px dashed #d97706; border-radius: 12px; padding: 12px 16px; margin-top: 10px; font-size: 0.82rem; color: #92400e; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
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
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuSesi">
                    <span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu show" id="submenuSesi">
                    <ul class="list-unstyled">
                        <li><a href="./index.php" class="submenu-link active"><i class="bi bi-camera-video-fill me-2"></i>Kelola Sesi Foto</a></li>
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
        <button onclick="confirmLogout(event)" class="btn-logout">
            <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
        </button>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dashboard-header">
        <div>
            <h3 class="fw-bold mb-1">Update Sesi Foto 📁</h3>
            <p class="text-muted small mb-0">Perbarui status progres dan unggah hasil foto untuk pelanggan.</p>
        </div>
        <div class="clock-badge">
            <i class="bi bi-clock-history me-1 text-danger"></i>
            <span id="live-clock">Memuat...</span>
        </div>
    </div>

    <div class="edit-layout">

        <!-- PANEL KIRI: INFO SESI -->
        <div class="info-panel">
            <div class="customer-avatar-area">
                <div class="customer-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="customer-name"><?= htmlspecialchars($d['Nama_Pelanggan']) ?></div>
                <span class="booking-badge">Booking #<?= $d['ID_Order'] ?></span>
                <div class="mt-3">
                    <span class="status-badge <?= $current_status['class'] ?>">
                        <i class="bi <?= $current_status['icon'] ?>"></i>
                        <?= $current_status['text'] ?>
                    </span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-icon"><i class="bi bi-person-badge-fill"></i></div>
                    <div>
                        <div class="info-item-text">Fotografer</div>
                        <div class="info-item-val"><?= htmlspecialchars($d['Nama_Karyawan']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-icon"><i class="bi bi-calendar-event-fill"></i></div>
                    <div>
                        <div class="info-item-text">Waktu Mulai</div>
                        <div class="info-item-val"><?= $waktu_mulai ?></div>
                    </div>
                </div>
                <?php if (!empty($d['File_Hasil'])): ?>
                <div class="info-item">
                    <div class="info-item-icon" style="background:#ecfdf5; color:#059669;"><i class="bi bi-cloud-check-fill"></i></div>
                    <div>
                        <div class="info-item-text">Tanggal Upload</div>
                        <div class="info-item-val"><?= $tgl_upload ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL KANAN: FORM EDIT -->
        <div class="form-card">
            <div class="form-card-title">
                <i class="bi bi-pencil-square"></i>
                Update Status & Hasil Foto
            </div>

            <form action="action_foto.php?act=edit" method="POST" enctype="multipart/form-data" id="formEdit">
                <input type="hidden" name="id_sesi" value="<?= $d['ID_Sesi_Foto'] ?>">

                <div class="row g-4">
                    <!-- STATUS -->
                    <div class="col-md-12">
                        <label class="form-section-title">Status Progres <span class="required-star">*</span></label>
                        <select name="status_sesi" class="form-select" required id="selectStatus">
                            <option value="0" <?= $d['Status_Sesi'] == 0 ? 'selected' : '' ?>>
                                📅 Terjadwal — Menunggu jadwal pemotretan
                            </option>
                            <option value="1" <?= $d['Status_Sesi'] == 1 ? 'selected' : '' ?>>
                                🎥 Proses — Sedang pemotretan / editing
                            </option>
                            <option value="2" <?= $d['Status_Sesi'] == 2 ? 'selected' : '' ?>>
                                ✅ Selesai — File siap diunduh pelanggan
                            </option>
                        </select>
                    </div>

                    <!-- FILE UPLOAD -->
                    <div class="col-md-12">
                        <label class="form-section-title">
                            Upload File Hasil Foto
                            <span id="file-required-star" style="<?= empty($d['File_Hasil']) ? '' : 'display:none;' ?>">
                                <span class="required-star">*</span> (wajib untuk status Selesai)
                            </span>
                        </label>
                        <input type="file" name="file_hasil" class="form-control" id="inputFile"
                               accept=".zip,.jpg,.jpeg,.png,.pdf">

                        <?php if (!empty($d['File_Hasil'])): ?>
                        <div class="file-current-box">
                            <i class="bi bi-file-earmark-zip-fill file-icon"></i>
                            <div class="flex-grow-1">
                                <div class="file-name"><?= htmlspecialchars($d['File_Hasil']) ?></div>
                                <div class="file-meta">File saat ini — upload baru akan menggantikan file ini</div>
                            </div>
                            <a href="../../../assets/img/hasil_foto/<?= htmlspecialchars($d['File_Hasil']) ?>"
                               target="_blank" class="btn-lihat-file">
                                <i class="bi bi-eye me-1"></i> Lihat
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="upload-hint mt-2" id="uploadHint" style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Status <b>Selesai</b> dipilih. Pastikan file hasil foto sudah diunggah agar pelanggan dapat mengunduhnya.
                        </div>

                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle me-1"></i>
                            Format: <b>.zip, .jpg, .png, .pdf</b> — Maks. <b>50MB</b>.
                            Gunakan <b>.zip</b> untuk banyak foto sekaligus.
                        </small>
                    </div>
                </div>

                <div class="mt-5 d-flex gap-3 align-items-center">
                    <button type="submit" class="btn-save">
                        <i class="bi bi-cloud-upload-fill me-2"></i> Simpan Perubahan
                    </button>
                    <a href="index.php" class="btn-cancel">Batal</a>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
document.querySelectorAll('.btn-toggle-submenu').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.dataset.target);
        const chevron = this.querySelector('.icon-chevron');
        if (!target) return;
        const isOpen = target.classList.contains('show');
        document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.icon-chevron').forEach(ic => ic.style.transform = '');
        if (!isOpen) {
            target.classList.add('show');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        }
    });
});

function updateClock() {
    const now = new Date();
    const opt = { weekday:'long', day:'numeric', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' };
    document.getElementById('live-clock').innerText = now.toLocaleDateString('id-ID', opt) + ' WIB';
}
setInterval(updateClock, 1000); updateClock();

// Tampilkan hint jika status Selesai dipilih
const selectStatus = document.getElementById('selectStatus');
const uploadHint = document.getElementById('uploadHint');
const hasFile = <?= !empty($d['File_Hasil']) ? 'true' : 'false' ?>;

function checkStatusHint() {
    if (selectStatus.value == '2' && !hasFile) {
        uploadHint.style.display = 'block';
    } else {
        uploadHint.style.display = 'none';
    }
}
selectStatus.addEventListener('change', checkStatusHint);
checkStatusHint();

// Validasi: jika status Selesai dan belum ada file, wajib upload
document.getElementById('formEdit').addEventListener('submit', function(e) {
    const status = selectStatus.value;
    const fileInput = document.getElementById('inputFile');
    if (status == '2' && !hasFile && fileInput.files.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'File Belum Diupload',
            text: 'Status "Selesai" membutuhkan file hasil foto. Silakan upload file terlebih dahulu.',
            confirmButtonColor: '#D53D66'
        });
    }
});

function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({ title:'Keluar Sistem?', icon:'warning', showCancelButton:true, confirmButtonColor:'#D53D66', confirmButtonText:'Keluar', cancelButtonText:'Batal' })
        .then(r => { if (r.isConfirmed) window.location.href = '../../logout.php'; });
}
function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({ title:'Buka Landing Page?', icon:'question', showCancelButton:true, confirmButtonColor:'#D53D66', confirmButtonText:'Ya, Buka', cancelButtonText:'Batal' })
        .then(r => { if (r.isConfirmed) window.location.href = '../../index.php'; });
}
</script>
</body>
</html>