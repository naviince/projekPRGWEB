<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin   = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

// =====================================================
// AMBIL ID JADWAL DARI URL
// =====================================================
$id_jadwal = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_jadwal <= 0) {
    header("Location: list.php?status_sukses=error&message=ID tidak valid");
    exit();
}

// Ambil Profil Admin untuk Header
$admin_data = safe_sqlsrv_fetch($conn, "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$foto_admin_src = ($admin_data['Foto_Profil'] != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $admin_data['Foto_Profil']))
    ? "../../assets/img/karyawan/" . $admin_data['Foto_Profil'] : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// =====================================================
// AMBIL DATA JADWAL LAMA
// =====================================================
$data_lama = safe_sqlsrv_fetch($conn, "SELECT * FROM Jadwal_Studio WHERE ID_Jadwal = ? AND Is_Deleted = 0", [$id_jadwal]);
if (!$data_lama) {
    header("Location: list.php?status_sukses=error&message=Data tidak ditemukan");
    exit();
}

// Ambil Daftar Ruangan untuk Dropdown
$q_ruangan = sqlsrv_query($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Is_Deleted = 0 AND Status = 1");
$list_ruangan = [];
while($row = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)) { $list_ruangan[] = $row; }

// =====================================================
// PROSES SIMPAN PERUBAHAN
// =====================================================
$error = "";
$success = false;

if (isset($_POST['simpan'])) {
    $id_ruangan  = $_POST['id_ruangan'];
    $tanggal     = $_POST['tanggal_jadwal'];
    $jam_mulai   = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $keterangan  = trim($_POST['keterangan']);
    $status      = (int)$_POST['status'];

    if (empty($id_ruangan) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
        $error = "Semua bidang bertanda bintang (*) wajib diisi!";
    } elseif ($jam_mulai >= $jam_selesai) {
        $error = "Jam mulai harus lebih awal dari jam selesai!";
    } else {
        // Update Query
        $sql_upd = "UPDATE Jadwal_Studio SET ID_Ruangan=?, Tanggal_Jadwal=?, Jam_Mulai=?, Jam_Selesai=?, Keterangan=?, Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Jadwal=?";
        $params_upd = [$id_ruangan, $tanggal, $jam_mulai, $jam_selesai, $keterangan, $status, $nama_admin, $id_jadwal];
        $stmt = sqlsrv_query($conn, $sql_upd, $params_upd);

        if ($stmt) { $success = true; } 
        else { $error = "Gagal memperbarui data di database."; }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Jadwal Studio – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: .85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        /* Tambahkan ini untuk menghilangkan titik-titik di semua list sidebar */
.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Jika kamu menggunakan class .nav-menu di HTML-nya */
.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}
        
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: .85rem; text-decoration: none; border-radius: 10px; transition: .3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,.03); padding-left: 22px; }
        
       .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: .85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,.2); }

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; cursor: pointer; transition: var(--transition-3d); background: #fff; }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        .breadcrumb-custom { display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: 0.85rem; font-weight: 600; }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb-custom .active { color: var(--p-pink); }

        .form-card { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); overflow: hidden; }
        .form-card-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); padding: 30px 40px; color: #ffffff; }
        .form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
        .form-card-body { padding: 40px; }

        .form-label { font-weight: 700; font-size: 0.75rem; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .form-label span { color: #dc2626; margin-left: 2px; }
        .form-control-custom { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
        .form-control-custom:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }

        .status-toggle-group { display: flex; gap: 12px; margin-top: 8px; }
        .status-option { flex: 1; padding: 14px 16px; border-radius: 14px; border: 2px solid #e2e8f0; cursor: pointer; text-align: center; transition: var(--transition-3d); background: #ffffff; }
        .status-option.active { border-color: var(--p-pink); background: var(--s-pink); }
        .status-option input { display: none; }
        .status-option .status-label { font-weight: 700; font-size: 0.85rem; }

        .btn-submit { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35); color: #ffffff; }
        .btn-batal { background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../../Role/Admin/index.php" class="nav-link-custom">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="../Pelanggan/list.php"     class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                        <li><a href="../Paket Foto/list.php"   class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                        <li><a href="../Ruangan/list.php"       class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                        <li><a href="../Properti/list.php"      class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                        <li><a href="../Tema Foto/list.php"     class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                        <li><a href="./list.php" class="submenu-link active"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        <li><a href="../Barang Cetak/list.php"  class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
                        <li><a href="../../Transaksi/Order/list.php"      class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                        <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                        <li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                        <li><a href="../../Transaksi/Sesi Foto/list.php"  class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                        <li><a href="../../Transaksi/Penjualan/list.php"  class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
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
    <div class="dashboard-header">
        <div><h3 class="fw-bold mb-1">Edit Jadwal Studio</h3><p class="text-muted small mb-0">Ubah rincian slot operasional studio.</p></div>
        <div class="profile-header-btn shadow-sm"><img src="<?= $foto_admin_src ?>" alt="Admin"></div>
    </div>

    <div class="breadcrumb-custom">
        <a href="../../Role/Admin/index.php">Dashboard</a> <i class="bi bi-chevron-right small"></i> 
        <a href="./list.php">Data Master</a> <i class="bi bi-chevron-right small"></i> 
        <a href="./list.php">Jadwal Studio</a> <i class="bi bi-chevron-right small"></i> 
        <span class="active">Edit Jadwal #<?= $id_jadwal ?></span>
    </div>

    <!-- FORM CARD -->
    <div class="form-card fade-in-up">
        <div class="form-card-header">
            <h4><i class="bi bi-pencil-square me-2"></i>Edit Jadwal Studio</h4>
            <p>Sesuaikan data ruangan, waktu, atau status operasional.</p>
        </div>
        <div class="form-card-body">
            <?php if ($error != ""): ?>
                <div class="alert alert-danger border-0 rounded-4 mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Pilih Ruangan <span>*</span></label>
                        <select name="id_ruangan" class="form-control-custom" required>
                            <?php foreach($list_ruangan as $r): ?>
                                <option value="<?= $r['ID_Ruangan'] ?>" <?= $data_lama['ID_Ruangan'] == $r['ID_Ruangan'] ? 'selected' : '' ?>><?= $r['Nama_Ruangan'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Tanggal Jadwal <span>*</span></label>
                        <input type="date" name="tanggal_jadwal" class="form-control-custom" value="<?= $data_lama['Tanggal_Jadwal']->format('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Jam Mulai <span>*</span></label>
                        <input type="time" name="jam_mulai" class="form-control-custom" value="<?= $data_lama['Jam_Mulai']->format('H:i') ?>" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Jam Selesai <span>*</span></label>
                        <input type="time" name="jam_selesai" class="form-control-custom" value="<?= $data_lama['Jam_Selesai']->format('H:i') ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Keterangan / Memo</label>
                    <textarea name="keterangan" class="form-control-custom" rows="3"><?= htmlspecialchars($data_lama['Keterangan']) ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label">Status Operasional</label>
                    <div class="status-toggle-group">
                        <label class="status-option <?= $data_lama['Status'] == 1 ? 'active' : '' ?>" onclick="selectStatus(this, 1)">
                            <input type="radio" name="status" value="1" <?= $data_lama['Status'] == 1 ? 'checked' : '' ?>>
                            <div class="status-label">✅ AKTIF</div>
                        </label>
                        <label class="status-option <?= $data_lama['Status'] == 0 ? 'active' : '' ?>" onclick="selectStatus(this, 0)">
                            <input type="radio" name="status" value="0" <?= $data_lama['Status'] == 0 ? 'checked' : '' ?>>
                            <div class="status-label">⛔ TIDAK AKTIF</div>
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-5">
                    <button type="submit" name="simpan" class="btn-submit"><i class="bi bi-save2"></i> Simpan Perubahan</button>
                    <a href="list.php" class="btn-batal"><i class="bi bi-x-circle"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Ambil ID target dari atribut data-target
        const targetId = this.getAttribute('data-target');
        const submenu = document.querySelector(targetId);
        const icon = this.querySelector('.icon-chevron');

        // Toggle class 'show' (ini yang memicu CSS display: block !important)
        if (submenu) {
            submenu.classList.toggle('show');
        }

        // Putar icon chevron jika ada
        if (icon) {
            if (submenu.classList.contains('show')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }
    });
});
</script>
<script>
    function selectStatus(el, val) {
        document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
        el.classList.add('active');
        el.querySelector('input').checked = true;
    }
    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' })
        .then(r => { if (r.isConfirmed) window.location.href = '../../logout.php'; });
    }
</script>

<?php if($success): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Berhasil Diperbarui!', text: 'Jadwal studio telah diperbarui.', confirmButtonColor: '#D53D66' })
    .then(() => window.location = 'list.php?status_sukses=edit');
</script>
<?php endif; ?>

</body>
</html>