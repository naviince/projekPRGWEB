<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// PROSES SIMPAN DATA (SELF-POST)
// =====================================================
$errors = [];
$success = false;

if (isset($_POST['simpan'])) {
    $nama_barang = isset($_POST['nama_barang']) ? trim($_POST['nama_barang']) : '';
    $harga_barang = isset($_POST['harga_barang']) ? $_POST['harga_barang'] : '';
    $stok_barang = isset($_POST['stok_barang']) ? $_POST['stok_barang'] : '';
    $stok_minimum = isset($_POST['stok_minimum']) ? $_POST['stok_minimum'] : '5';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    // --- LAYER 1: Validasi Nama Barang (Wajib) ---
    if (empty($nama_barang)) {
        $errors[] = 'Nama barang wajib diisi!';
    }
    // --- LAYER 2: Validasi Max Length (100 karakter) ---
    elseif (strlen($nama_barang) > 100) {
        $errors[] = 'Nama barang maksimal 100 karakter!';
    }
    // --- LAYER 3: Cek Duplikat Nama (Unik) ---
    else {
        $count = safe_sqlsrv_count($conn, 
            "SELECT COUNT(*) as total FROM Barang_Cetak WHERE Nama_Barang = ? AND Is_Deleted = 0", 
            [$nama_barang]
        );
        if ($count > 0) {
            $errors[] = 'Nama barang "'.htmlspecialchars($nama_barang).'" sudah ada! Gunakan nama lain.';
        }
    }

    // --- LAYER 4: Validasi Harga (Wajib) ---
    if ($harga_barang === '' || $harga_barang === null) {
        $errors[] = 'Harga barang wajib diisi!';
    }
    // --- LAYER 5: Validasi Harga >= 0 ---
    elseif (!is_numeric($harga_barang) || (float)$harga_barang < 0) {
        $errors[] = 'Harga barang tidak boleh negatif!';
    }

    // --- LAYER 6: Validasi Stok (Wajib) ---
    if ($stok_barang === '' || $stok_barang === null) {
        $errors[] = 'Stok barang wajib diisi!';
    }
    // --- LAYER 7: Validasi Stok >= 0 ---
    elseif (!is_numeric($stok_barang) || (int)$stok_barang < 0) {
        $errors[] = 'Stok barang tidak boleh negatif!';
    }

    // --- LAYER 8: Validasi Stok Minimum <= Stok Saat Ini ---
    if (!is_numeric($stok_minimum) || (int)$stok_minimum < 0) {
        $errors[] = 'Stok minimum tidak boleh negatif!';
    } elseif ((int)$stok_minimum > (int)$stok_barang) {
        $errors[] = 'Stok minimum ('.$stok_minimum.') tidak boleh lebih besar dari stok saat ini ('.$stok_barang.')!';
    }

    // --- LAYER 9: Validasi File Upload (Opsional) ---
    $new_filename = 'default_barang.jpg';
    $upload_error = '';

    if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto_barang'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_error = 'Gagal upload file. Error code: '.$file['error'];
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $upload_error = 'Ukuran file maksimal 2MB!';
        } else {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                $upload_error = 'Format file harus JPG atau PNG!';
            } elseif (!getimagesize($file['tmp_name'])) {
                $upload_error = 'File bukan gambar yang valid!';
            }
        }

        if (!empty($upload_error)) {
            $errors[] = $upload_error;
        } else {
            $upload_dir = '../../uploads/barang/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'barang_'.time().'_'.rand(1000,9999).'.'.$ext;
            $target_path = $upload_dir . $new_filename;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                $errors[] = 'Gagal menyimpan file ke server!';
                $new_filename = 'default_barang.jpg';
            }
        }
    }

    // --- LAYER 10: Insert ke Database (Transaction) ---
    if (empty($errors)) {
        sqlsrv_begin_transaction($conn);

        try {
            $sql_insert = "INSERT INTO Barang_Cetak (
                Nama_Barang, Harga_Barang, Stok_Barang, Stok_Minimum, 
                Deskripsi, Foto_Barang, Status, Is_Deleted, 
                Created_By, Created_Date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, GETDATE())";

            $params = [
                $nama_barang,
                (float)$harga_barang,
                (int)$stok_barang,
                (int)$stok_minimum,
                $deskripsi,
                $new_filename,
                $status,
                $nama_admin
            ];

            $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);

            if ($stmt_insert === false) {
                throw new Exception('Gagal insert barang: '.print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt_insert);

            sqlsrv_commit($conn);
            $success = true;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $errors[] = 'Gagal menyimpan ke database: '.$e->getMessage();

            // Hapus file yang sudah diupload kalau gagal
            if ($new_filename !== 'default_barang.jpg' && isset($target_path) && file_exists($target_path)) {
                unlink($target_path);
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
    <title>Tambah Barang Cetak – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-bg: #ffffff;
            --p-pink: #D53D66;
            --d-pink: #CA3366;
            --s-pink: #FFF0F3;
            --light-pink: #FFE4E9;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar {
            width: 260px; height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 30px 20px; z-index: 100;
        }
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
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; cursor: pointer; transition: var(--transition-3d); background: #fff; }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        .breadcrumb-custom { display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: 0.85rem; font-weight: 600; }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }
        .breadcrumb-custom i { color: #cbd5e1; font-size: 0.7rem; }

        /* FORM CARD */
        .form-card { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); overflow: hidden; }
        .form-card-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); padding: 30px 40px; color: #ffffff; }
        .form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
        .form-card-body { padding: 40px; }

        .form-label { font-weight: 700; font-size: 0.75rem; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control-custom { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
        .form-control-custom:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
        .form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
        textarea.form-control-custom { min-height: 120px; resize: vertical; }

        .input-hint { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 4px; }

        /* STATUS TOGGLE */
        .status-toggle-group { display: flex; gap: 12px; margin-top: 8px; }
        .status-option { flex: 1; padding: 14px 16px; border-radius: 14px; border: 2px solid #e2e8f0; cursor: pointer; text-align: center; transition: var(--transition-3d); background: #ffffff; }
        .status-option:hover { border-color: var(--p-pink); }
        .status-option.active { border-color: var(--p-pink); background: var(--s-pink); }
        .status-option input { display: none; }
        .status-option .status-icon { font-size: 1.3rem; margin-bottom: 4px; }
        .status-option .status-label { font-weight: 700; font-size: 0.85rem; }
        .status-option .status-desc { font-size: 0.7rem; color: var(--text-muted); }

        /* BUTTONS */
        .btn-submit { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35); color: #ffffff; }
        .btn-batal { background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-batal:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-3px); }

        /* ALERT */
        .alert-custom-error { background: #fef2f2; border: none; border-left: 4px solid #dc2626; border-radius: 12px; color: #991b1b; font-size: 0.85rem; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: start; gap: 10px; }
        .alert-custom-error i { font-size: 1.1rem; margin-top: 2px; }
        .alert-custom-success { background: #f0fdf4; border: none; border-left: 4px solid #22c55e; border-radius: 12px; color: #166534; font-size: 0.85rem; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .alert-custom-success i { font-size: 1.1rem; }

        /* INFO BOX */
        .info-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; font-size: 0.8rem; color: #0369a1; font-weight: 600; }
        .info-box i { margin-right: 8px; }

        /* PREVIEW IMAGE */
        .preview-container { width: 120px; height: 120px; border: 2px dashed #cbd5e0; border-radius: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; overflow: hidden; background: #f8fafc; }
        .preview-container:hover { border-color: var(--p-pink); background: var(--s-pink); }
        .preview-container img { width: 100%; height: 100%; object-fit: cover; }
        .preview-container i { font-size: 32px; color: #cbd5e0; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">
            SpotLight.<br><span>Panel Administrator</span>
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
                        <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                        <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                        <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                        <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                        <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                        <li><a href="./list.php" class="submenu-link active"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
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
        <div>
            <h3 class="fw-bold mb-1">Tambah Barang Cetak</h3>
            <p class="text-muted small mb-0">Tambahkan produk baru ke dalam katalog cetak studio.</p>
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

    <div class="breadcrumb-custom">
        <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <a href="./list.php">Data Master</a>
        <i class="bi bi-chevron-right"></i>
        <a href="./list.php">Barang Cetak</a>
        <i class="bi bi-chevron-right"></i>
        <span class="active">Tambah Barang</span>
    </div>

    <!-- FORM CARD -->
    <div class="form-card fade-in-up">
        <div class="form-card-header">
            <h4><i class="bi bi-box-seam me-2"></i>Form Barang Cetak Baru</h4>
            <p>Lengkapi informasi nama, harga, stok, dan deskripsi barang. Stok minimum untuk alert menipis.</p>
        </div>
        <div class="form-card-body">

            <!-- INFO BOX -->
            <div class="info-box">
                <i class="bi bi-info-circle-fill"></i>
                <strong>Petunjuk:</strong> Nama harus unik (tidak boleh sama dengan barang lain). 
                Harga dalam Rupiah (contoh: 50000). Stok minimum untuk alert saat stok menipis. 
                Status Aktif = ditampilkan ke customer saat booking.
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert-custom-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <strong>Gagal menyimpan data!</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert-custom-success">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Barang cetak berhasil ditambahkan!</strong>
            </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="formBarang">
                <div class="row">
                    <!-- Nama Barang -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Nama Barang <span class="required">*</span></label>
                        <input type="text" name="nama_barang" class="form-control-custom" 
                               placeholder="Contoh: Album Foto 4R, Frame Kayu 8x10" 
                               maxlength="100"
                               value="<?= isset($_POST['nama_barang']) ? htmlspecialchars($_POST['nama_barang']) : '' ?>"
                               required>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Maksimal 100 karakter. Harus unik, tidak boleh sama dengan barang lain.
                        </div>
                    </div>
                    <!-- Harga -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Harga (Rp) <span class="required">*</span></label>
                        <input type="number" name="harga_barang" class="form-control-custom" 
                               placeholder="Contoh: 50000" min="0" step="100"
                               value="<?= isset($_POST['harga_barang']) ? htmlspecialchars($_POST['harga_barang']) : '' ?>"
                               required>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Harga dalam Rupiah. Tidak boleh negatif.
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Stok -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Stok Saat Ini <span class="required">*</span></label>
                        <input type="number" name="stok_barang" class="form-control-custom" 
                               placeholder="Contoh: 20" min="0"
                               value="<?= isset($_POST['stok_barang']) ? htmlspecialchars($_POST['stok_barang']) : '' ?>"
                               required>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Stok real-time. Tidak boleh negatif.
                        </div>
                    </div>
                    <!-- Stok Minimum -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Stok Minimum (Alert) <span class="required">*</span></label>
                        <input type="number" name="stok_minimum" class="form-control-custom" 
                               placeholder="Contoh: 5" min="0"
                               value="<?= isset($_POST['stok_minimum']) ? htmlspecialchars($_POST['stok_minimum']) : '5' ?>"
                               required>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Alert saat stok <= nilai ini. Harus <= Stok Saat Ini.
                        </div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="mb-4">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control-custom" rows="3" 
                              placeholder="Jelaskan detail barang, ukuran, atau keterangan lain (opsional, max 255 karakter)"
                              maxlength="255"><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                    <div class="input-hint">
                        <i class="bi bi-info-circle"></i> Maksimal 255 karakter. Opsional.
                    </div>
                </div>

                <!-- Foto Barang -->
                <div class="mb-4">
                    <label class="form-label">Foto Barang</label>
                    <div class="d-flex align-items-center gap-3">
                        <div class="preview-container" onclick="document.getElementById('foto_barang').click()">
                            <img id="previewImg" src="" alt="" style="display: none;">
                            <i class="bi bi-camera" id="previewIcon"></i>
                        </div>
                        <div>
                            <input type="file" name="foto_barang" id="foto_barang" 
                                   class="d-none" accept="image/jpeg,image/jpg,image/png"
                                   onchange="previewFile()">
                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                    onclick="document.getElementById('foto_barang').click()"
                                    style="border-radius: 10px;">
                                <i class="bi bi-upload me-1"></i> Pilih Foto
                            </button>
                            <div class="small text-muted mt-1">
                                Format: JPG/PNG. Max: 2MB. Opsional.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="d-flex gap-3 mt-5">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="bi bi-check2-circle"></i> Simpan Barang
                    </button>
                    <a href="list.php" class="btn-batal">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// Live Clock
function updateLiveClock() {
    const now = new Date();
    const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    document.getElementById('live-clock').innerText = 
        days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + 
        String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0') + ' WIB';
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

// Status Toggle
function selectStatus(el, val) {
    document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    el.querySelector('input').checked = true;
}

// Preview Image
function previewFile() {
    const file = document.getElementById('foto_barang').files[0];
    const preview = document.getElementById('previewImg');
    const icon = document.getElementById('previewIcon');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            icon.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

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

<?php if ($success): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: 'Barang cetak baru telah ditambahkan.',
    confirmButtonColor: '#D53D66'
}).then(() => {
    window.location = 'list.php?status_sukses=tambah';
});
<?php endif; ?>
</script>

</body>
</html>