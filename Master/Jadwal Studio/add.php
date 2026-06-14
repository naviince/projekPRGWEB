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
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// AMBIL DAFTAR RUANGAN AKTIF
// =====================================================
$list_ruangan = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan, Kapasitas_Ruangan 
     FROM Ruangan 
     WHERE Status = 1 AND Is_Deleted = 0 
     ORDER BY Nama_Ruangan ASC"
);

// =====================================================
// PROSES SIMPAN DATA
// =====================================================
$error = "";
$success = false;

if (isset($_POST['simpan'])) {
    $id_ruangan  = trim($_POST['id_ruangan'] ?? '');
    $tanggal     = trim($_POST['tanggal_jadwal'] ?? '');
    $jam_mulai   = trim($_POST['jam_mulai'] ?? '');
    $jam_selesai = trim($_POST['jam_selesai'] ?? '');
    $keterangan  = trim($_POST['keterangan'] ?? '');
    $status      = (int)($_POST['status'] ?? 1);

    // --- LAYER 1: Validasi empty ---
    if (empty($id_ruangan)) {
        $error = "Ruangan wajib dipilih!";
    } elseif (empty($tanggal)) {
        $error = "Tanggal jadwal wajib diisi!";
    } elseif (empty($jam_mulai)) {
        $error = "Jam mulai wajib diisi!";
    } elseif (empty($jam_selesai)) {
        $error = "Jam selesai wajib diisi!";
    } 
    // --- LAYER 2: Validasi format tanggal ---
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $error = "Format tanggal tidak valid! Gunakan format YYYY-MM-DD.";
    }
    // --- LAYER 3: Validasi tanggal tidak di masa lalu ---
    elseif ($tanggal < date('Y-m-d')) {
        $error = "Tanggal tidak boleh di masa lalu! Minimal hari ini.";
    }
    // --- LAYER 4: Validasi format jam (HH:mm) ---
    elseif (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $jam_mulai)) {
        $error = "Format jam mulai tidak valid! Gunakan format 24 jam (contoh: 08:00 atau 13:30).";
    } elseif (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $jam_selesai)) {
        $error = "Format jam selesai tidak valid! Gunakan format 24 jam (contoh: 08:00 atau 13:30).";
    }
    // --- LAYER 5: Validasi jam operasional (08:00 - 20:00) ---
    elseif ($jam_mulai < '08:00') {
        $error = "Jam mulai minimal 08:00! Studio buka pukul 08:00 WIB.";
    } elseif ($jam_selesai > '20:00') {
        $error = "Jam selesai maksimal 20:00! Studio tutup pukul 20:00 WIB.";
    }
    // --- LAYER 6: Validasi jam mulai < jam selesai ---
    elseif ($jam_mulai >= $jam_selesai) {
        $error = "Jam mulai harus lebih kecil dari jam selesai! Minimal selisih 30 menit.";
    }
    // --- LAYER 7: Validasi minimal 30 menit ---
    else {
        $mulai_min = (int)substr($jam_mulai, 0, 2) * 60 + (int)substr($jam_mulai, 3, 2);
        $selesai_min = (int)substr($jam_selesai, 0, 2) * 60 + (int)substr($jam_selesai, 3, 2);
        if (($selesai_min - $mulai_min) < 30) {
            $error = "Durasi minimal 30 menit! Silakan perpanjang jam selesai.";
        }
    }

    // --- LAYER 8: Cek bentrok jadwal ---
    if ($error == "") {
        // Cek apakah jadwal bentrok di ruangan & tanggal yang sama
        // Bentrok = ada overlap waktu
        $sql_cek = "SELECT COUNT(*) as total FROM Jadwal_Studio 
                    WHERE ID_Ruangan = ? 
                    AND Tanggal_Jadwal = ? 
                    AND Is_Deleted = 0
                    AND Status = 1
                    AND (
                        (Jam_Mulai < ? AND Jam_Selesai > ?) 
                        OR (Jam_Mulai >= ? AND Jam_Mulai < ?)
                        OR (Jam_Selesai > ? AND Jam_Selesai <= ?)
                    )";
        $params_cek = [
            $id_ruangan, $tanggal, 
            $jam_selesai, $jam_mulai,  // Kondisi 1: jadwal existing overlap
            $jam_mulai, $jam_selesai,   // Kondisi 2: jam mulai baru di dalam jadwal existing
            $jam_mulai, $jam_selesai    // Kondisi 3: jam selesai baru di dalam jadwal existing
        ];
        $res_cek = safe_sqlsrv_fetch($conn, $sql_cek, $params_cek);

        if (($res_cek['total'] ?? 0) > 0) {
            $error = "Gagal! Ruangan tersebut sudah memiliki jadwal di tanggal dan jam yang bersinggungan. Silakan pilih waktu lain.";
        }
    }

    // --- LAYER 9: Insert ke database ---
    if ($error == "") {
        // Status_Jadwal = 0 (Tersedia) untuk jadwal baru
        // Status = 1 (Aktif) atau 0 (Nonaktif) sesuai pilihan user
        $sql_ins = "INSERT INTO Jadwal_Studio 
                    (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal, Status, Is_Deleted, Created_By, Created_Date) 
                    VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, GETDATE())";
        $params_ins = [$id_ruangan, $tanggal, $jam_mulai, $jam_selesai, $keterangan, $status, $nama_admin];

        $stmt = sqlsrv_query($conn, $sql_ins, $params_ins);

        if ($stmt) {
            sqlsrv_free_stmt($stmt);
            $success = true;
        } else {
            $sql_errors = sqlsrv_errors();
            $error = "Gagal menyimpan ke database. ";
            if (!empty($sql_errors)) {
                $error .= "[SQL: " . ($sql_errors[0]['message'] ?? 'Unknown error') . "]";
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
    <title>Tambah Jadwal Studio – SpotLight Studio</title>

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

        .breadcrumb-custom {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 25px; font-size: 0.85rem; font-weight: 600;
        }
        .breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
        .breadcrumb-custom a:hover { color: var(--p-pink); }
        .breadcrumb-custom .active { color: var(--p-pink); }
        .breadcrumb-custom i { color: #cbd5e1; font-size: 0.7rem; }

        .form-card {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            overflow: hidden;
        }
        .form-card-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            padding: 30px 40px;
            color: #ffffff;
        }
        .form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
        .form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
        .form-card-body { padding: 40px; }

        .form-label {
            font-weight: 700; font-size: 0.75rem;
            color: var(--text-dark); text-transform: uppercase;
            letter-spacing: 0.8px; margin-bottom: 8px;
        }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control-custom, .form-select-custom {
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 14px 18px; font-weight: 600; font-size: 0.9rem;
            color: #1e293b; transition: var(--transition-3d); background: #ffffff;
        }
        .form-control-custom:focus, .form-select-custom:focus { 
            outline: none; border-color: var(--p-pink); 
            box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); 
        }
        .form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
        textarea.form-control-custom { min-height: 120px; resize: vertical; }

        .input-hint {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 600;
            margin-top: 6px; display: flex; align-items: center; gap: 4px;
        }

        /* STATUS TOGGLE */
        .status-toggle-group {
            display: flex; gap: 12px; margin-top: 8px;
        }
        .status-option {
            flex: 1; padding: 14px 16px; border-radius: 14px;
            border: 2px solid #e2e8f0; cursor: pointer;
            text-align: center; transition: var(--transition-3d);
            background: #ffffff;
        }
        .status-option:hover { border-color: var(--p-pink); }
        .status-option.active {
            border-color: var(--p-pink); background: var(--s-pink);
        }
        .status-option input { display: none; }
        .status-option .status-icon { font-size: 1.3rem; margin-bottom: 4px; }
        .status-option .status-label { font-weight: 700; font-size: 0.85rem; }
        .status-option .status-desc { font-size: 0.7rem; color: var(--text-muted); }

        /* BUTTONS */
        .btn-submit {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; border-radius: 14px;
            padding: 14px 32px; font-weight: 800; font-size: 0.95rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35);
            color: #ffffff;
        }
        .btn-batal {
            background: #f1f5f9; color: #475569; border: none;
            border-radius: 14px; padding: 14px 32px;
            font-weight: 800; font-size: 0.95rem;
            transition: var(--transition-3d); display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-batal:hover {
            background: #e2e8f0; color: #1e293b;
            transform: translateY(-3px);
        }

        /* ALERT */
        .alert-custom {
            background: #fef2f2; border: none;
            border-left: 4px solid #dc2626; border-radius: 12px;
            color: #991b1b; font-size: 0.85rem; padding: 14px 18px;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .alert-custom i { font-size: 1.1rem; }

        /* INFO BOX */
        .info-box {
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 12px; padding: 14px 18px;
            margin-bottom: 24px; font-size: 0.8rem;
            color: #0369a1; font-weight: 600;
        }
        .info-box i { margin-right: 8px; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
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
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Tambah Jadwal Studio</h3>
                <p class="text-muted small mb-0">Buat slot waktu operasional baru untuk studio foto.</p>
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

        <!-- BREADCRUMB -->
        <div class="breadcrumb-custom">
            <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Data Master</a>
            <i class="bi bi-chevron-right"></i>
            <a href="./list.php">Jadwal Studio</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Tambah Jadwal</span>
        </div>

        <!-- FORM CARD -->
        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-calendar-plus me-2"></i>Form Jadwal Studio Baru</h4>
                <p>Tentukan ruangan, tanggal, dan jam operasional slot foto. Format jam menggunakan 24 jam (contoh: 08:00, 13:30).</p>
            </div>
            <div class="form-card-body">

                <!-- INFO BOX -->
                <div class="info-box">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong>Petunjuk:</strong> Jam operasional studio 08:00 - 20:00 WIB. Format jam 24 jam (HH:mm). Minimal durasi 30 menit.
                </div>

                <?php if ($error != ""): ?>
                    <div class="alert-custom">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="formJadwal">
                    <div class="row">
                        <!-- Pilih Ruangan -->
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Pilih Ruangan <span class="required">*</span></label>
                            <select name="id_ruangan" class="form-select-custom" required>
                                <option value="">-- Pilih Ruangan Studio --</option>
                                <?php foreach($list_ruangan as $r): ?>
                                    <option value="<?= $r['ID_Ruangan'] ?>" <?= (isset($_POST['id_ruangan']) && $_POST['id_ruangan'] == $r['ID_Ruangan']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['Nama_Ruangan']) ?> (Kapasitas: <?= $r['Kapasitas_Ruangan'] ?> orang)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Hanya ruangan aktif yang ditampilkan
                            </div>
                        </div>

                        <!-- Tanggal Jadwal -->
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Tanggal Jadwal <span class="required">*</span></label>
                            <input type="date" name="tanggal_jadwal" class="form-control-custom" required 
                                   min="<?= date('Y-m-d') ?>" 
                                   value="<?= htmlspecialchars($_POST['tanggal_jadwal'] ?? '') ?>">
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Minimal hari ini, format: YYYY-MM-DD
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Jam Mulai -->
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Jam Mulai <span class="required">*</span></label>
                            <input type="text" name="jam_mulai" class="form-control-custom" required 
                                   placeholder="08:00" 
                                   pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                                   maxlength="5"
                                   value="<?= htmlspecialchars($_POST['jam_mulai'] ?? '') ?>">
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Format 24 jam (08:00 - 20:00), contoh: 08:00, 13:30, 17:45
                            </div>
                        </div>

                        <!-- Jam Selesai -->
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Jam Selesai <span class="required">*</span></label>
                            <input type="text" name="jam_selesai" class="form-control-custom" required 
                                   placeholder="17:00" 
                                   pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                                   maxlength="5"
                                   value="<?= htmlspecialchars($_POST['jam_selesai'] ?? '') ?>">
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Format 24 jam, minimal 30 menit dari jam mulai
                            </div>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="mb-4">
                        <label class="form-label">Keterangan / Memo</label>
                        <textarea name="keterangan" class="form-control-custom" rows="3" 
                                  placeholder="Contoh: Slot khusus pagi, promo weekend, atau catatan khusus (opsional)"
                                  maxlength="255"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Maksimal 255 karakter, akan ditampilkan ke pelanggan
                        </div>
                    </div>

                    <!-- Status Operasional -->
                    <div class="mb-4">
                        <label class="form-label">Status Operasional</label>
                        <div class="status-toggle-group">
                            <label class="status-option active" onclick="selectStatus(this, 1)">
                                <input type="radio" name="status" value="1" checked>
                                <div class="status-icon">✅</div>
                                <div class="status-label">Aktif</div>
                                <div class="status-desc">Tampil ke pelanggan</div>
                            </label>
                            <label class="status-option" onclick="selectStatus(this, 0)">
                                <input type="radio" name="status" value="0">
                                <div class="status-icon">⛔</div>
                                <div class="status-label">Nonaktif</div>
                                <div class="status-desc">Disembunyikan</div>
                            </label>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="simpan" class="btn-submit">
                            <i class="bi bi-check2-circle"></i> Simpan Jadwal
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

        // Status Toggle
        function selectStatus(el, val) {
            document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
            el.classList.add('active');
            el.querySelector('input').checked = true;
        }

        // Client-side validation for time format
        document.getElementById('formJadwal').addEventListener('submit', function(e) {
            const jamMulai = document.querySelector('input[name="jam_mulai"]').value;
            const jamSelesai = document.querySelector('input[name="jam_selesai"]').value;

            // Validate format HH:mm
            const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;

            if (!timeRegex.test(jamMulai)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Format Jam Mulai Salah',
                    text: 'Gunakan format 24 jam (contoh: 08:00, 13:30, 17:45)',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }

            if (!timeRegex.test(jamSelesai)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Format Jam Selesai Salah',
                    text: 'Gunakan format 24 jam (contoh: 08:00, 13:30, 17:45)',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }

            // Validate range 08:00 - 20:00
            if (jamMulai < '08:00') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Jam Mulai Terlalu Pagi',
                    text: 'Studio buka pukul 08:00 WIB',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }

            if (jamSelesai > '20:00') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Jam Selesai Terlalu Malam',
                    text: 'Studio tutup pukul 20:00 WIB',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }

            // Validate jam_mulai < jam_selesai
            if (jamMulai >= jamSelesai) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Jam Tidak Valid',
                    text: 'Jam mulai harus lebih kecil dari jam selesai',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }

            // Validate minimal 30 minutes
            const mulaiMin = parseInt(jamMulai.split(':')[0]) * 60 + parseInt(jamMulai.split(':')[1]);
            const selesaiMin = parseInt(jamSelesai.split(':')[0]) * 60 + parseInt(jamSelesai.split(':')[1]);

            if ((selesaiMin - mulaiMin) < 30) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Durasi Terlalu Pendek',
                    text: 'Minimal durasi 30 menit',
                    confirmButtonColor: '#D53D66'
                });
                return false;
            }
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

        // Jam Real-Time
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
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Jadwal studio baru telah ditambahkan.',
            confirmButtonColor: '#D53D66'
        }).then(() => window.location = 'list.php?status_sukses=tambah');
    </script>
    <?php endif; ?>
</body>
</html>