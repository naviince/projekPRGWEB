<?php
ob_start();
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin untuk Sidebar & Header
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) 
    ? "../../assets/img/pelanggan/" . $foto_admin 
    : $default_svg_avatar;

// Inisialisasi
$errors = [];
$old_values = $_POST ?? [];
$success = false;

// =====================================================
// PROSES SIMPAN
// =====================================================
if (isset($_POST['simpan'])) {
    $nama      = trim($_POST['nama'] ?? '');
    $durasi    = trim($_POST['durasi'] ?? '');
    $harga     = trim($_POST['harga'] ?? '');
    $kapasitas = trim($_POST['kapasitas'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // --- VALIDASI NAMA PAKET ---
    if (empty($nama)) {
        $errors['nama'] = "Nama paket wajib diisi!";
    } elseif (strlen($nama) < 3) {
        $errors['nama'] = "Nama paket minimal 3 karakter!";
    } elseif (strlen($nama) > 100) {
        $errors['nama'] = "Nama paket maksimal 100 karakter!";
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-&]+$/', $nama)) {
        $errors['nama'] = "Nama paket hanya boleh huruf, angka, spasi, -, &!";
    } else {
        $sql_cek = "SELECT ID_Paket FROM Paket_Foto WHERE Nama_Paket = ? AND Is_Deleted = 0";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, [$nama]);
        if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
            $errors['nama'] = "Nama paket sudah ada! Gunakan nama lain.";
        }
    }

    // --- VALIDASI DURASI ---
    if (empty($durasi)) {
        $errors['durasi'] = "Durasi wajib diisi!";
    } elseif (!ctype_digit($durasi)) {
        $errors['durasi'] = "Durasi hanya boleh angka!";
    } elseif ((int)$durasi < 15) {
        $errors['durasi'] = "Durasi minimal 15 menit!";
    } elseif ((int)$durasi > 300) {
        $errors['durasi'] = "Durasi maksimal 300 menit!";
    }

    // --- VALIDASI HARGA ---
    if (empty($harga)) {
        $errors['harga'] = "Harga wajib diisi!";
    } elseif (!is_numeric($harga)) {
        $errors['harga'] = "Harga hanya boleh angka!";
    } elseif ((float)$harga < 10000) {
        $errors['harga'] = "Harga minimal Rp 10.000!";
    } elseif ((float)$harga > 99999999) {
        $errors['harga'] = "Harga maksimal Rp 99.999.999!";
    }

    // --- VALIDASI KAPASITAS ---
    if (empty($kapasitas)) {
        $errors['kapasitas'] = "Kapasitas wajib diisi!";
    } elseif (!ctype_digit($kapasitas)) {
        $errors['kapasitas'] = "Kapasitas hanya boleh angka!";
    } elseif ((int)$kapasitas < 1) {
        $errors['kapasitas'] = "Kapasitas minimal 1 orang!";
    } elseif ((int)$kapasitas > 50) {
        $errors['kapasitas'] = "Kapasitas maksimal 50 orang!";
    }

    // --- VALIDASI DESKRIPSI ---
    if (empty($deskripsi)) {
        $errors['deskripsi'] = "Deskripsi wajib diisi!";
    } elseif (strlen($deskripsi) < 20) {
        $errors['deskripsi'] = "Deskripsi minimal 20 karakter!";
    } elseif (strlen($deskripsi) > 255) {
        $errors['deskripsi'] = "Deskripsi maksimal 255 karakter!";
    }

    // --- VALIDASI FOTO ---
    $foto_name = $_FILES['foto']['name'] ?? '';
    $foto_tmp  = $_FILES['foto']['tmp_name'] ?? '';
    $foto_size = $_FILES['foto']['size'] ?? 0;
    $foto_error = $_FILES['foto']['error'] ?? 0;

    if (empty($foto_name)) {
        $errors['foto'] = "Foto sampul paket wajib diupload!";
    } elseif ($foto_error != 0) {
        $errors['foto'] = "Terjadi kesalahan saat upload foto!";
    } else {
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $errors['foto'] = "Format foto harus JPG, JPEG, atau PNG!";
        } elseif ($foto_size > 2097152) {
            $errors['foto'] = "Ukuran foto maksimal 2MB!";
        }
    }

    // --- SIMPAN KE DATABASE ---
    if (empty($errors)) {
        $upload_dir = "../../assets/img/paket/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = "paket_" . time() . "_" . rand(1000, 9999) . "." . $ext;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($foto_tmp, $upload_path)) {
            sqlsrv_query($conn, "BEGIN TRAN");

            $sql = "INSERT INTO Paket_Foto (
                Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, 
                Kapasitas_Orang, Foto_Paket, Status, 
                Created_By, Created_Date
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, GETDATE())";

            $params = [
                $nama, 
                (int)$durasi, 
                (float)$harga, 
                $deskripsi, 
                (int)$kapasitas, 
                $new_filename,
                $nama_admin
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                sqlsrv_query($conn, "COMMIT");
                $success = true;
            } else {
                sqlsrv_query($conn, "ROLLBACK");
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
                $errors['general'] = "Gagal menyimpan ke database. Silakan coba lagi!";
            }
        } else {
            $errors['general'] = "Gagal mengupload foto ke server. Cek permission folder!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Paket Foto – SpotLight Studio</title>

    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
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
            color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }

        /* HEADER */
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
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* FORM CARD */
        .form-card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Section Title */
        .section-title {
            font-weight: 800;
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: var(--p-pink);
            font-size: 1.2rem;
        }

        /* Form Label */
        .form-label-custom {
            font-weight: 800;
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }

        /* Form Input */
        .form-input-custom {
            width: 100%;
            border-radius: 14px;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            transition: var(--transition-3d);
        }
        .form-input-custom:focus {
            outline: none;
            border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }
        .form-input-custom::placeholder {
            color: #cbd5e1;
            font-weight: 500;
        }
        .form-input-custom.is-invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .form-input-custom.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
        }

        textarea.form-input-custom {
            resize: vertical;
            min-height: 100px;
        }

        /* Error Text */
        .error-text {
            color: #ef4444;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* General Error Alert */
        .alert-error {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 25px;
            color: #991b1b;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error i {
            font-size: 1.2rem;
            color: #dc2626;
        }

        /* Foto Upload */
        .upload-area {
            width: 100%;
            height: 220px;
            border-radius: 20px;
            border: 3px dashed #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
            position: relative;
            transition: var(--transition-3d);
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .upload-area.has-image {
            border-style: solid;
            border-color: var(--p-pink);
            background: #ffffff;
        }
        .upload-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        .upload-placeholder {
            text-align: center;
            color: #94a3b8;
        }
        .upload-placeholder i {
            font-size: 3rem;
            margin-bottom: 12px;
            display: block;
            color: #cbd5e1;
        }
        .upload-placeholder .main-text {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .upload-placeholder .sub-text {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Buttons */
        .btn-simpan {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 16px;
            padding: 16px 32px;
            font-weight: 800;
            font-size: 1rem;
            transition: var(--transition-3d);
            box-shadow: 0 10px 25px rgba(213, 61, 102, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-simpan:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(213, 61, 102, 0.4);
            color: #ffffff;
        }

        .btn-kembali {
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 16px;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-kembali:hover {
            background: #e2e8f0;
            color: var(--text-dark);
            transform: translateY(-2px);
        }

        .btn-group-bottom {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f1f5f9;
        }

        /* Char Counter */
        .char-counter {
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: right;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
            .form-grid { grid-template-columns: 1fr; }
            .form-card { padding: 25px; }
            .btn-group-bottom { flex-direction: column; }
            .btn-simpan, .btn-kembali { width: 100%; justify-content: center; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Admin</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
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
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
                            <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
                            <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
                            <li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
                            <li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
                            <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
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

        <!-- HEADER -->
        <div class="dashboard-header fade-in-up">
            <div>
                <h3 class="fw-bold mb-1">Tambah Paket Foto</h3>
                <p class="text-muted small mb-0">Lengkapi data layanan foto studio dengan akurat.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <div class="form-card fade-in-up">

            <?php if(isset($errors['general'])): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formPaket">

                <!-- Info Paket -->
                <div class="section-title">
                    <i class="bi bi-info-circle-fill"></i>
                    Informasi Paket
                </div>

                <!-- Nama Paket -->
                <div class="mb-3">
                    <label class="form-label-custom">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="inputNama" 
                           class="form-input-custom <?= isset($errors['nama']) ? 'is-invalid' : '' ?>" 
                           placeholder="Contoh: Premium Graduation" 
                           value="<?= htmlspecialchars($old_values['nama'] ?? '') ?>" 
                           maxlength="100" required>
                    <?php if(isset($errors['nama'])): ?>
                        <span class="error-text"><?= $errors['nama'] ?></span>
                    <?php endif; ?>
                    <div class="char-counter"><span id="countNama">0</span>/100</div>
                </div>

                <!-- Grid: Durasi & Harga -->
                <div class="form-grid mb-3">
                    <div>
                        <label class="form-label-custom">Durasi (Menit) <span class="text-danger">*</span></label>
                        <input type="number" name="durasi" 
                               class="form-input-custom <?= isset($errors['durasi']) ? 'is-invalid' : '' ?>" 
                               placeholder="60" 
                               value="<?= htmlspecialchars($old_values['durasi'] ?? '') ?>" 
                               min="15" max="300" required>
                        <?php if(isset($errors['durasi'])): ?>
                            <span class="error-text"><?= $errors['durasi'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label-custom">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="harga" 
                               class="form-input-custom <?= isset($errors['harga']) ? 'is-invalid' : '' ?>" 
                               placeholder="450000" 
                               value="<?= htmlspecialchars($old_values['harga'] ?? '') ?>" 
                               min="10000" required>
                        <?php if(isset($errors['harga'])): ?>
                            <span class="error-text"><?= $errors['harga'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kapasitas -->
                <div class="mb-3">
                    <label class="form-label-custom">Kapasitas Maksimal (Orang) <span class="text-danger">*</span></label>
                    <input type="number" name="kapasitas" 
                           class="form-input-custom <?= isset($errors['kapasitas']) ? 'is-invalid' : '' ?>" 
                           placeholder="5" 
                           value="<?= htmlspecialchars($old_values['kapasitas'] ?? '') ?>" 
                           min="1" max="50" required>
                    <?php if(isset($errors['kapasitas'])): ?>
                        <span class="error-text"><?= $errors['kapasitas'] ?></span>
                    <?php endif; ?>
                </div>

                <!-- Deskripsi -->
                <div class="mb-4">
                    <label class="form-label-custom">Deskripsi Layanan <span class="text-danger">*</span></label>
                    <textarea name="deskripsi" id="inputDeskripsi" 
                              class="form-input-custom <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>" 
                              rows="3" 
                              placeholder="Jelaskan apa saja yang didapat pelanggan..." 
                              maxlength="255" required><?= htmlspecialchars($old_values['deskripsi'] ?? '') ?></textarea>
                    <?php if(isset($errors['deskripsi'])): ?>
                        <span class="error-text"><?= $errors['deskripsi'] ?></span>
                    <?php endif; ?>
                    <div class="char-counter"><span id="countDeskripsi">0</span>/255</div>
                </div>

                <!-- Foto -->
                <div class="section-title">
                    <i class="bi bi-image-fill"></i>
                    Foto Sampul Paket
                </div>

                <div class="mb-3">
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('fotoInput').click()">
                        <img id="previewImg" class="upload-preview" alt="Preview">
                        <div class="upload-placeholder" id="placeholderText">
                            <i class="bi bi-cloud-upload"></i>
                            <div class="main-text">Klik untuk Upload Foto</div>
                            <div class="sub-text">JPG, JPEG, PNG (Maksimal 2MB)</div>
                        </div>
                    </div>
                    <input type="file" name="foto" id="fotoInput" 
                           class="d-none" 
                           accept="image/jpeg,image/jpg,image/png" 
                           required>
                    <?php if(isset($errors['foto'])): ?>
                        <span class="error-text"><?= $errors['foto'] ?></span>
                    <?php endif; ?>
                </div>

                <!-- Buttons -->
                <div class="btn-group-bottom">
                    <a href="list.php" class="btn-kembali">
                        <i class="bi bi-arrow-left"></i>Kembali
                    </a>
                    <button type="submit" name="simpan" class="btn-simpan">
                        <i class="bi bi-check-circle-fill"></i>Simpan Paket
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

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

        // Preview Foto
        const fotoInput = document.getElementById('fotoInput');
        const previewImg = document.getElementById('previewImg');
        const placeholderText = document.getElementById('placeholderText');
        const uploadArea = document.getElementById('uploadArea');

        fotoInput.addEventListener('change', function() {
            const [file] = this.files;
            if (file) {
                previewImg.src = URL.createObjectURL(file);
                previewImg.style.display = 'block';
                placeholderText.style.display = 'none';
                uploadArea.classList.add('has-image');
            }
        });

        // Counter Nama
        const inputNama = document.getElementById('inputNama');
        const countNama = document.getElementById('countNama');
        inputNama.addEventListener('input', function() {
            countNama.textContent = this.value.length;
        });
        countNama.textContent = inputNama.value.length;

        // Counter Deskripsi
        const inputDeskripsi = document.getElementById('inputDeskripsi');
        const countDeskripsi = document.getElementById('countDeskripsi');
        inputDeskripsi.addEventListener('input', function() {
            countDeskripsi.textContent = this.value.length;
        });
        countDeskripsi.textContent = inputDeskripsi.value.length;

        // Validasi Real-time: angka only
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            document.getElementById('live-clock').innerText = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
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
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
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

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Paket foto baru berhasil ditambahkan.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Oke'
        }).then(() => {
            window.location.href = 'list.php?status_sukses=tambah';
        });
    </script>
    <?php endif; ?>

</body>
</html>
<?php ob_end_flush(); ?>