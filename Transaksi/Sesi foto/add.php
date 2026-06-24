<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Ambil Order yang statusnya DP Terverifikasi (1) atau Lunas (3)
// dan belum punya sesi foto aktif
$sql_order = "SELECT o.ID_Order, p.Nama_Pelanggan 
              FROM [Order] o 
              JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan 
              WHERE o.Status_Order IN (1, 3)
              AND o.Status = 1
              AND NOT EXISTS (
                  SELECT 1 FROM Sesi_Foto s 
                  WHERE s.ID_Order = o.ID_Order AND s.Status = 1
              )
              ORDER BY o.ID_Order DESC";
$q_order = sqlsrv_query($conn, $sql_order);

// Ambil Fotografer / Karyawan aktif
$q_kar = sqlsrv_query($conn,
    "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan WHERE Is_Deleted = 0 AND Status = 1 ORDER BY Nama_Karyawan ASC"
);

// Tanggal minimum = hari ini untuk datetime-local
$min_datetime = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Sesi Foto – SpotLight Studio</title>

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

        /* FORM CARD */
        .form-card { background: #fff; border-radius: 24px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 10px 30px rgba(0,0,0,0.02); padding: 40px; }

        /* ALERT INFO */
        .alert-info-box { background: var(--s-pink); border: 1.5px dashed var(--p-pink); border-radius: 16px; padding: 18px 22px; display: flex; align-items: flex-start; gap: 14px; margin-bottom: 36px; }
        .alert-info-box i { font-size: 1.4rem; color: var(--p-pink); flex-shrink: 0; margin-top: 2px; }
        .alert-info-box .alert-text b { color: var(--p-pink); }
        .alert-info-box .alert-text { font-size: 0.88rem; line-height: 1.6; }

        /* FORM ELEMENTS */
        .form-section-title { font-weight: 800; font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
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

        /* DIVIDER */
        .form-divider { border: none; border-top: 2px dashed #f1f5f9; margin: 28px 0; }

        /* BUTTONS */
        .btn-save { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 16px; padding: 15px 44px; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: var(--transition-3d); box-shadow: 0 8px 20px rgba(213,61,102,0.2); }
        .btn-save:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 14px 30px rgba(213,61,102,0.3); }
        .btn-cancel { background: #f1f5f9; color: #718096; border-radius: 16px; padding: 15px 32px; font-weight: 700; font-size: 0.95rem; text-decoration: none; transition: 0.3s; display: inline-block; }
        .btn-cancel:hover { background: #e2e8f0; color: var(--text-dark); }
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
            <h3 class="fw-bold mb-1">Tambah Sesi Foto 📸</h3>
            <p class="text-muted small mb-0">Buat jadwal sesi foto baru untuk pelanggan SpotLight Studio.</p>
        </div>
        <div class="clock-badge">
            <i class="bi bi-clock-history me-1 text-danger"></i>
            <span id="live-clock">Memuat...</span>
        </div>
    </div>

    <div class="form-card">
        <div class="alert-info-box">
            <i class="bi bi-info-circle-fill"></i>
            <div class="alert-text">
                <b>Perhatian:</b> Hanya order dengan status <b>DP Terverifikasi</b> atau <b>Lunas</b> yang dapat dijadwalkan sesi fotonya.
                Pastikan fotografer tersedia pada waktu yang dipilih. Satu order hanya boleh memiliki satu sesi foto aktif.
            </div>
        </div>

        <form action="action_foto.php?act=add" method="POST" id="formAdd">
            <div class="row g-4">

                <!-- ORDER -->
                <div class="col-md-12">
                    <label class="form-section-title">Pelanggan / ID Order <span class="required-star">*</span></label>
                    <select name="id_order" class="form-select" required>
                        <option value="">-- Pilih Order --</option>
                        <?php while($ro = sqlsrv_fetch_array($q_order, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?= $ro['ID_Order'] ?>">
                                #<?= $ro['ID_Order'] ?> — <?= htmlspecialchars($ro['Nama_Pelanggan']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle me-1"></i>
                        Hanya order yang belum memiliki sesi foto yang ditampilkan.
                    </small>
                </div>

                <!-- FOTOGRAFER -->
                <div class="col-md-12">
                    <label class="form-section-title">Fotografer / Petugas <span class="required-star">*</span></label>
                    <select name="id_karyawan" class="form-select" required>
                        <option value="">-- Pilih Fotografer --</option>
                        <?php while($rk = sqlsrv_fetch_array($q_kar, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?= $rk['ID_Karyawan'] ?>">
                                <?= htmlspecialchars($rk['Nama_Karyawan']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-12"><hr class="form-divider"></div>

                <!-- WAKTU MULAI -->
                <div class="col-md-6">
                    <label class="form-section-title">Waktu Mulai <span class="required-star">*</span></label>
                    <input type="datetime-local" name="waktu_mulai" class="form-control"
                           min="<?= $min_datetime ?>" required>
                </div>

                <!-- WAKTU SELESAI -->
                <div class="col-md-6">
                    <label class="form-section-title">Waktu Selesai (Estimasi)</label>
                    <input type="datetime-local" name="waktu_selesai" class="form-control"
                           min="<?= $min_datetime ?>">
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle me-1"></i> Opsional. Bisa diisi setelah sesi selesai.
                    </small>
                </div>

            </div>

            <!-- BUTTONS -->
            <div class="mt-5 d-flex gap-3 align-items-center">
                <button type="submit" class="btn-save">
                    <i class="bi bi-calendar-plus-fill me-2"></i> Simpan Jadwal Sesi
                </button>
                <a href="index.php" class="btn-cancel">Batal</a>
            </div>
        </form>
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

// Validasi: waktu selesai harus setelah waktu mulai
document.getElementById('formAdd').addEventListener('submit', function(e) {
    const mulai  = document.querySelector('[name="waktu_mulai"]').value;
    const selesai = document.querySelector('[name="waktu_selesai"]').value;
    if (selesai && selesai <= mulai) {
        e.preventDefault();
        Swal.fire({ icon:'error', title:'Waktu Tidak Valid', text:'Waktu selesai harus setelah waktu mulai.', confirmButtonColor:'#D53D66' });
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