<?php
ob_start();
session_start();
include '../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

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

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_fetch] SQL Error: " . json_encode(sqlsrv_errors()));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_execute($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[safe_sqlsrv_execute] SQL Error: " . json_encode(sqlsrv_errors()));
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

// =====================================================
// PROSES SUBMIT
// =====================================================
$errors = [];
$old_values = $_POST ?? [];
$success = false;

if (isset($_POST['simpan'])) {
    $nama_paket = isset($_POST['nama_paket']) ? trim($_POST['nama_paket']) : '';
    $durasi_waktu = isset($_POST['durasi_waktu']) ? (int)$_POST['durasi_waktu'] : 0;
    $harga_paket = isset($_POST['harga_paket']) ? (float)$_POST['harga_paket'] : 0;
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $kapasitas_orang = isset($_POST['kapasitas_orang']) ? (int)$_POST['kapasitas_orang'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : STATUS_DATA_AKTIF;

    // --- VALIDASI NAMA PAKET ---
    if (empty($nama_paket)) {
        $errors['nama_paket'] = "Nama paket wajib diisi!";
    } elseif (strlen($nama_paket) > 100) {
        $errors['nama_paket'] = "Nama paket maksimal 100 karakter!";
    } else {
        // Cek duplikat nama
        $cek_nama = safe_sqlsrv_fetch($conn,
            "SELECT ID_Paket FROM Paket_Foto WHERE Nama_Paket = ? AND Is_Deleted = 0",
            [$nama_paket]
        );
        if ($cek_nama) {
            $errors['nama_paket'] = "Nama paket sudah digunakan!";
        }
    }

    // --- VALIDASI DURASI WAKTU ---
    if ($durasi_waktu < 10) {
        $errors['durasi_waktu'] = "Durasi waktu minimal 10 menit!";
    }

    // --- VALIDASI HARGA PAKET ---
    if ($harga_paket < 0) {
        $errors['harga_paket'] = "Harga paket tidak boleh negatif!";
    }

    // --- VALIDASI KAPASITAS ORANG ---
    if ($kapasitas_orang <= 0) {
        $errors['kapasitas_orang'] = "Kapasitas orang harus lebih dari 0!";
    }

    // --- VALIDASI STATUS ---
    if (!in_array($status, [STATUS_DATA_AKTIF, STATUS_DATA_NONAKTIF])) {
        $errors['status'] = "Status tidak valid!";
    }

    // --- VALIDASI & UPLOAD FOTO PAKET ---
    $foto_paket = 'default_paket.jpg';
    if (isset($_FILES['foto_paket']) && $_FILES['foto_paket']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_paket'];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            $errors['foto_paket'] = "Format file harus JPG, JPEG, PNG, atau WEBP!";
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB max
            $errors['foto_paket'] = "Ukuran file maksimal 2MB!";
        } else {
            $upload_dir = '../../assets/img/paket/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $new_filename = 'paket_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $foto_paket = $new_filename;
            } else {
                $errors['foto_paket'] = "Gagal mengupload foto paket!";
            }
        }
    }

    // =====================================================
    // INSERT DATA
    // =====================================================
    if (empty($errors)) {
        if (!sqlsrv_begin_transaction($conn)) {
            $errors['general'] = "Gagal memulai transaksi database!";
        } else {
            $sql = "INSERT INTO Paket_Foto 
                    (Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket, Status, Created_By, Created_Date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

            $params = [
                $nama_paket,
                $durasi_waktu,
                $harga_paket,
                !empty($deskripsi) ? $deskripsi : null,
                $kapasitas_orang,
                $foto_paket,
                $status,
                $nama_admin
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                sqlsrv_commit($conn);
                $success = true;
                $old_values = []; // Clear form on success
            } else {
                sqlsrv_rollback($conn);
                $errors['general'] = "Gagal menyimpan paket foto. Silakan coba lagi!";
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
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        .form-card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }

        .section-title {
            font-weight: 800;
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i { color: var(--p-pink); font-size: 1.2rem; }

        .form-label-custom {
            font-weight: 800;
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }

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
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .form-input-custom:focus {
            outline: none;
            border-color: var(--p-pink);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08);
        }
        .form-input-custom.is-invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .form-input-custom.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
        }

        textarea.form-input-custom { resize: vertical; min-height: 100px; }

        select.form-input-custom {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 44px;
        }

        .error-text {
            color: #ef4444;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

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
        .alert-error i { font-size: 1.2rem; color: #dc2626; }

        .helper-text {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .helper-text i { color: var(--p-pink); }

        .info-card {
            background: linear-gradient(135deg, #FFF0F3, #FFF8F0);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .info-card i { font-size: 1.5rem; color: var(--p-pink); }
        .info-card .info-text {
            font-size: 0.85rem;
            color: #4a5568;
            font-weight: 600;
            line-height: 1.5;
        }
        .info-card .info-text strong { color: var(--p-pink); }

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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .upload-zone {
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: var(--transition-3d);
            cursor: pointer;
            background: #f8fafc;
        }
        .upload-zone:hover {
            border-color: var(--p-pink);
            background: var(--s-pink);
        }
        .upload-zone i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 10px;
            transition: var(--transition-3d);
        }
        .upload-zone:hover i { color: var(--p-pink); }
        .upload-zone .upload-text {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .upload-zone .upload-hint {
            font-size: 0.75rem;
            color: #cbd5e1;
            margin-top: 6px;
        }
        .upload-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 14px;
            margin-top: 12px;
            display: none;
            border: 2px solid var(--light-pink);
        }
        .upload-preview.active { display: block; }

        .input-group-custom {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .input-group-custom .input-prefix {
            background: linear-gradient(135deg, var(--s-pink), var(--light-pink));
            color: var(--p-pink);
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 0.9rem;
            border: 2px solid var(--light-pink);
        }
        .input-group-custom .form-input-custom {
            flex: 1;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar { transform: translateX(-100%); }
            .form-card { padding: 25px; }
            .btn-group-bottom { flex-direction: column; }
            .btn-simpan, .btn-kembali { width: 100%; justify-content: center; }
        }
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
                <p class="text-muted small mb-0">Buat paket layanan foto baru untuk SpotLight Studio.</p>
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

            <!-- Info Card -->
            <div class="info-card">
                <i class="bi bi-info-circle-fill"></i>
                <div class="info-text">
                    <strong>Perhatian:</strong> Isi data paket foto dengan lengkap. 
                    <strong>Durasi</strong> menentukan slot jadwal yang tersedia, 
                    <strong>Kapasitas</strong> menentukan maksimal orang per sesi, dan 
                    <strong>Harga</strong> adalah biaya utama layanan.
                </div>
            </div>

            <?php if(isset($errors['general'])): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formPaket" enctype="multipart/form-data">

                <!-- NAMA PAKET -->
                <div class="mb-4">
                    <label class="form-label-custom">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="nama_paket" 
                           class="form-input-custom <?= isset($errors['nama_paket']) ? 'is-invalid' : '' ?>" 
                           value="<?= htmlspecialchars($old_values['nama_paket'] ?? '') ?>" 
                           placeholder="Contoh: Basic, Couple, Family, Wisuda, Corporate" 
                           maxlength="100" required>
                    <?php if(isset($errors['nama_paket'])): ?>
                        <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['nama_paket'] ?></span>
                    <?php endif; ?>
                    <div class="helper-text">
                        <i class="bi bi-info-circle"></i>
                        Nama paket harus unik dan maksimal 100 karakter.
                    </div>
                </div>

                <div class="form-grid mb-4">
                    <!-- DURASI WAKTU -->
                    <div>
                        <label class="form-label-custom">Durasi Waktu (Menit) <span class="text-danger">*</span></label>
                        <input type="number" name="durasi_waktu"
                               class="form-input-custom <?= isset($errors['durasi_waktu']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($old_values['durasi_waktu'] ?? '') ?>"
                               placeholder="Contoh: 30, 60, 90, 120" min="10" required>
                        <?php if(isset($errors['durasi_waktu'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['durasi_waktu'] ?></span>
                        <?php endif; ?>
                        <div class="helper-text">
                            <i class="bi bi-info-circle"></i>
                            Minimal <strong>10 menit</strong>. Durasi menentukan jumlah slot per hari (12 jam / durasi).
                        </div>
                    </div>

                    <!-- HARGA PAKET -->
                    <div>
                        <label class="form-label-custom">Harga Paket (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group-custom">
                            <span class="input-prefix">Rp</span>
                            <input type="number" name="harga_paket" 
                                   class="form-input-custom <?= isset($errors['harga_paket']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($old_values['harga_paket'] ?? '') ?>" 
                                   placeholder="250000" min="0" required>
                        </div>
                        <?php if(isset($errors['harga_paket'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['harga_paket'] ?></span>
                        <?php endif; ?>
                        <div class="helper-text">
                            <i class="bi bi-info-circle"></i>
                            Harga utama layanan. Ruangan tidak menambah biaya.
                        </div>
                    </div>
                </div>

                <div class="form-grid mb-4">
                    <!-- KAPASITAS ORANG -->
                    <div>
                        <label class="form-label-custom">Kapasitas Orang <span class="text-danger">*</span></label>
                        <input type="number" name="kapasitas_orang" 
                               class="form-input-custom <?= isset($errors['kapasitas_orang']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($old_values['kapasitas_orang'] ?? '') ?>" 
                               placeholder="Contoh: 2, 5, 8, 20" min="1" required>
                        <?php if(isset($errors['kapasitas_orang'])): ?>
                            <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['kapasitas_orang'] ?></span>
                        <?php endif; ?>
                        <div class="helper-text">
                            <i class="bi bi-info-circle"></i>
                            Maksimal jumlah orang per sesi foto.
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div>
                        <label class="form-label-custom">Status Paket</label>
                        <select name="status" class="form-input-custom">
                            <option value="<?= STATUS_DATA_AKTIF ?>" <?= (!isset($old_values['status']) || $old_values['status'] == STATUS_DATA_AKTIF) ? 'selected' : '' ?>>
                                🟢 Aktif (Bisa Dipesan)
                            </option>
                            <option value="<?= STATUS_DATA_NONAKTIF ?>" <?= (isset($old_values['status']) && $old_values['status'] == STATUS_DATA_NONAKTIF) ? 'selected' : '' ?>>
                                🔴 Nonaktif (Tidak Tersedia)
                            </option>
                        </select>
                        <div class="helper-text">
                            <i class="bi bi-info-circle"></i>
                            Paket nonaktif tidak akan muncul di halaman pelanggan.
                        </div>
                    </div>
                </div>

                <!-- DESKRIPSI -->
                <div class="mb-4">
                    <label class="form-label-custom">Deskripsi <span style="color: #94a3b8; font-weight: 500;">(opsional)</span></label>
                    <textarea name="deskripsi" 
                              class="form-input-custom <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>" 
                              placeholder="Jelaskan detail paket, konsep, atau keunggulan layanan ini..." 
                              maxlength="255"><?= htmlspecialchars($old_values['deskripsi'] ?? '') ?></textarea>
                    <?php if(isset($errors['deskripsi'])): ?>
                        <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['deskripsi'] ?></span>
                    <?php endif; ?>
                    <div class="helper-text">
                        <i class="bi bi-info-circle"></i>
                        Maksimal 255 karakter. Akan ditampilkan di halaman pelanggan.
                    </div>
                </div>

                <!-- FOTO PAKET -->
                <div class="mb-4">
                    <label class="form-label-custom">Foto Paket <span style="color: #94a3b8; font-weight: 500;">(opsional)</span></label>
                    <div class="upload-zone" onclick="document.getElementById('fileFoto').click()">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <div class="upload-text">Klik untuk upload foto paket</div>
                        <div class="upload-hint">JPG, JPEG, PNG, WEBP • Maks 2MB</div>
                        <img id="previewFoto" class="upload-preview" alt="Preview">
                    </div>
                    <input type="file" name="foto_paket" id="fileFoto" accept="image/jpeg,image/png,image/webp" 
                           style="display: none;" onchange="previewImage(this)">
                    <?php if(isset($errors['foto_paket'])): ?>
                        <span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['foto_paket'] ?></span>
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
        // =====================================================
        // TOGGLE SUBMENU
        // =====================================================
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

        // =====================================================
        // IMAGE PREVIEW
        // =====================================================
        function previewImage(input) {
            const preview = document.getElementById('previewFoto');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('active');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // =====================================================
        // FORM VALIDATION BEFORE SUBMIT
        // =====================================================
        document.getElementById('formPaket').addEventListener('submit', function(e) {
            const nama = document.querySelector('input[name="nama_paket"]').value.trim();
            const durasi = document.querySelector('input[name="durasi_waktu"]').value;
            const harga = document.querySelector('input[name="harga_paket"]').value;
            const kapasitas = document.querySelector('input[name="kapasitas_orang"]').value;

            if (!nama) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Nama Paket Kosong',
                    text: 'Silakan isi nama paket foto.',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
            if (!durasi || durasi < 10) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Durasi Tidak Valid',
                    text: 'Durasi waktu minimal 10 menit.',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
            if (harga === '' || harga < 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Harga Tidak Valid',
                    text: 'Harga paket tidak boleh negatif.',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
            if (!kapasitas || kapasitas <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Kapasitas Tidak Valid',
                    text: 'Kapasitas orang harus lebih dari 0.',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
        });

        // =====================================================
        // JAM REAL-TIME
        // =====================================================
        function updateLiveClock() {
            var clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            var now = new Date();
            var days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            var months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            var dayName = days[now.getDay()];
            var day = now.getDate();
            var monthName = months[now.getMonth()];
            var year = now.getFullYear();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var seconds = now.getSeconds();
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            clockEl.innerText = dayName + ', ' + day + ' ' + monthName + ' ' + year + ' - ' + hours + ':' + minutes + ':' + seconds + ' WIB';
        }
        updateLiveClock();
        setInterval(updateLiveClock, 1000);

        // =====================================================
        // KONFIRMASI LOGOUT
        // =====================================================
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

        function bukaModalBiodata() {
            Swal.fire({
                title: '<?= htmlspecialchars($nama_admin) ?>',
                text: 'Administrator - SpotLight Studio',
                icon: 'info',
                confirmButtonColor: '#D53D66'
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