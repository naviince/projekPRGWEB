<?php
session_start();
include '../../koneksi.php'; 

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
$username_owner = $d_profile['username_karyawan'] ?? 'owner';

$error_crud = "";

// Mengambil data karyawan yang akan diedit berdasarkan parameter ID di URL [2]
if (isset($_GET['id'])) {
    $id_edit = $_GET['id'];
    $sql_fetch = "SELECT * FROM Karyawan WHERE ID_Karyawan = ? AND Is_Deleted = 0";
    $stmt_fetch = sqlsrv_query($conn, $sql_fetch, array($id_edit));
    $data_karyawan = sqlsrv_fetch_array($stmt_fetch, SQLSRV_FETCH_ASSOC);
    
    if (!$data_karyawan) {
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}

if (isset($_POST['edit_karyawan'])) {
    $nik      = trim($_POST['nik']);
    $nama     = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $pass     = $_POST['password'];
    $jk       = $_POST['jenis_kelamin'];
    $dob      = trim($_POST['tanggal_lahir']);
    $role     = $_POST['role_karyawan'];
    $hp       = trim($_POST['no_hp']);
    $alamat   = trim($_POST['alamat']);
    $status   = (int)$_POST['status_karyawan'];

    // Validasi Sisi Server (Akurat minimal 17 tahun) [1]
    $umur = date_diff(date_create($dob), date_create('today'))->y;

    if (empty($nik) || empty($nama) || empty($username) || empty($email) || empty($dob) || empty($hp) || empty($alamat)) {
        $error_crud = "Seluruh kolom bertanda bintang wajib diisi!";
    } elseif ($umur < 17) {
        $error_crud = "Pembaruan gagal! Umur karyawan minimal harus 17 tahun.";
    } else {
        // Cek duplikasi kecuali dirinya sendiri [2]
        $sql_cek = "SELECT NIK, Email_Karyawan, Username_Karyawan, No_Hp FROM Karyawan WHERE (NIK = ? OR Email_Karyawan = ? OR Username_Karyawan = ? OR No_Hp = ?) AND ID_Karyawan != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($nik, $email, $username, $hp, $id_edit));

        if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
            $error_crud = "Data NIK, Email, Username, atau Nomor Telepon sudah digunakan oleh staf lain!";
        } else {
            // Tentukan kueri update
            if (!empty($pass)) {
                $sql_upd = "UPDATE Karyawan SET NIK = ?, Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Role_Karyawan = ?, No_Hp = ?, Alamat = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $params = array($nik, $nama, $username, $email, $pass, $jk, $dob, $role, $hp, $alamat, $status, $username_owner, $id_edit);
            } else {
                $sql_upd = "UPDATE Karyawan SET NIK = ?, Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Jenis_Kelamin = ?, Tanggal_Lahir = ?, Role_Karyawan = ?, No_Hp = ?, Alamat = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $params = array($nik, $nama, $username, $email, $jk, $dob, $role, $hp, $alamat, $status, $username_owner, $id_edit);
            }

            $stmt_upd = sqlsrv_query($conn, $sql_upd, $params);
            if ($stmt_upd) {
                header("Location: index.php?status_sukses=edit");
                exit();
            } else {
                $error_crud = "Gagal memperbarui data di database!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Karyawan – SpotLight Studio</title>
    
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --p-pink: #d83f67; 
            --d-pink: #c73165; 
            --s-pink: #fff5f6; 
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
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

        /* SIDEBAR STYLING */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem; color: var(--p-pink);
            text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }

        .sidebar-menu-wrapper {
            flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none;
        }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }

        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none;
            border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px; color: #718096;
            font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(216, 63, 103, 0.03); padding-left: 22px; }

        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; width: 100%; padding: 12px; border-radius: 12px;
            font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(216,63, 103, 0.2); }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }

        /* KARTU 3D FLOATING MELAYANG */
        .card-3d {
            background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03); transition: var(--transition-3d);
            padding: 40px; height: 100%; position: relative;
        }
        .card-3d:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(216, 63, 103, 0.12); 
            border-color: var(--p-pink);
        }

        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .form-control, .form-select { 
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; 
            background: #f8fafc; font-size: 14px; font-weight: 600; 
            transition: var(--transition-3d); color: var(--text-dark); 
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--p-pink); background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); outline: none;
        }
    </style>
</head>
<body>

    <!-- Bilah Samping -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br>
                <span>Beranda Pemilik</span>
            </a>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Owner/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="submenu-link active"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                        <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuLaporan">
                        <ul class="list-unstyled">
                            <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                            <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                            <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                            <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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

    <!-- Area Konten Utama -->
    <div class="main-content">
        
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Edit Data Karyawan ⚙</h3>
                <p class="text-muted small mb-0">Perbarui informasi staf aktif secara akurat dan sesuaikan perannya.</p>
            </div>
            <a href="index.php" class="btn btn-masuk py-2 px-4 border shadow-sm text-decoration-none" style="border-color: var(--p-pink) !important; color: var(--p-pink); border-radius: 12px; font-weight: 700; font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>

        <!-- FORMULIR EDIT KARYAWAN -->
        <div class="card-3d">
            <form method="POST" onsubmit="return validasiUmur()">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">NIK<span class="required-star">*</span></label>
                        <input type="text" name="nik" id="inputNIK" class="form-control" value="<?= htmlspecialchars($data_karyawan['NIK']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap Karyawan<span class="required-star">*</span></label>
                        <input type="text" name="nama" id="inputNama" class="form-control" value="<?= htmlspecialchars($data_karyawan['Nama_Karyawan']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username Log-in<span class="required-star">*</span></label>
                        <input type="text" name="username" id="inputUsername" class="form-control" value="<?= htmlspecialchars($data_karyawan['Username_Karyawan']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data_karyawan['Email_Karyawan']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ganti Kata Sandi (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Isi hanya jika ingin ganti sandi">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
                        <input type="text" name="no_hp" id="inputHP" class="form-control" value="<?= htmlspecialchars($data_karyawan['No_Hp']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Kelamin<span class="required-star">*</span></label>
                        <select name="jenis_kelamin" class="form-select" required>
                            <option value="Laki-laki" <?= ($data_karyawan['Jenis_Kelamin'] == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="Perempuan" <?= ($data_karyawan['Jenis_Kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Lahir<span class="required-star">*</span></label>
                        <input type="date" name="tanggal_lahir" id="t_dob" class="form-control" value="<?= $data_karyawan['Tanggal_Lahir']->format('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Peran Kerja (Role)<span class="required-star">*</span></label>
                        <select name="role_karyawan" class="form-select" required>
                            <option value="Admin" <?= ($data_karyawan['Role_Karyawan'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Fotografer" <?= ($data_karyawan['Role_Karyawan'] == 'Fotografer') ? 'selected' : '' ?>>Fotografer</option>
                            <option value="Owner" <?= ($data_karyawan['Role_Karyawan'] == 'Owner') ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status Akun<span class="required-star">*</span></label>
                        <select name="status_karyawan" class="form-select" required>
                            <option value="1" <?= ($data_karyawan['Status'] == 1) ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= ($data_karyawan['Status'] == 0) ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat Domisili<span class="required-star">*</span></label>
                        <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($data_karyawan['Alamat']) ?>" required>
                    </div>
                </div>
                <button type="submit" name="edit_karyawan" class="btn btn-reg shadow-sm py-3 mt-4">Simpan Perubahan Karyawan ✨</button>
            </form>
        </div>
    </div>

    <!-- Script JS Vendor -->
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

        // Validasi Umur Klien Minimal 17 Tahun [1]
        function validasiUmur() {
            const dobValue = document.getElementById('t_dob').value;
            if (!dobValue) return false;

            const lahir = new Date(dobValue);
            const hariIni = new Date();
            let umur = hariIni.getFullYear() - lahir.getFullYear();
            const m = hariIni.getMonth() - lahir.getMonth();
            if (m < 0 || (m === 0 && hariIni.getDate() < lahir.getDate())) {
                umur--;
            }

            if (umur < 17) {
                Swal.fire({
                    icon: 'error',
                    title: 'Pembaruan Gagal! ❌',
                    text: 'Umur karyawan minimal harus 17 tahun.',
                    confirmButtonColor: '#d83f67'
                });
                return false;
            }
            return true;
        }

        // Kunci input NIK agar hanya menerima angka dan maks 16 digit [1]
        const inputNIK = document.getElementById('inputNIK');
        if (inputNIK) {
            inputNIK.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 16) this.value = this.value.slice(0, 16);
            });
        }

        // Kunci input Nama agar hanya menerima huruf dan spasi
        const inputNama = document.getElementById('inputNama');
        if (inputNama) {
            inputNama.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z ]/g, '');
            });
        }

        // Kunci input Username agar hanya menerima huruf, angka, dan garis bawah
        const inputUsername = document.getElementById('inputUsername');
        if (inputUsername) {
            inputUsername.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            });
        }

        // Masking Telepon "+62"
        const inputHP = document.getElementById('inputHP'), prefix = '+62 ';
        function moveCursorToEnd() { if (inputHP.selectionStart < prefix.length) { if (inputHP.setSelectionRange) inputHP.setSelectionRange(prefix.length, prefix.length); } }
        if (inputHP) {
            inputHP.addEventListener('mousedown', () => setTimeout(moveCursorToEnd, 1));
            inputHP.addEventListener('focus', moveCursorToEnd);
            inputHP.addEventListener('keyup', moveCursorToEnd);
            inputHP.addEventListener('keydown', function(e) { if (this.selectionStart <= prefix.length && (e.keyCode === 8 || e.keyCode === 46)) { e.preventDefault(); } });
            inputHP.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
                let digits = this.value.split(prefix)[1].replace(/[^0-9]/g, '');
                if (digits.length > 13) digits = digits.slice(0, 13);
                this.value = prefix + digits;
            });
        }

        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem? ❌',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
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
                title: 'Kembali ke Beranda? ✦',
                text: 'Anda akan dialihkan kembali ke halaman utama publik SpotLight Studio.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }
    </script>

    <?php if($error_crud != ""): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal! ❌',
            text: '<?= $error_crud ?>',
            confirmButtonColor: '#d83f67'
        });
    </script>
    <?php endif; ?>
</body>
</html>