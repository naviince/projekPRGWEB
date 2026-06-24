<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES FOTOGRAFER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];
$username_fotografer = $_SESSION['username'] ?? 'fotografer';

// =====================================================
// KONFIGURASI UPLOAD
// =====================================================
$upload_dir = '../../uploads/hasil/';
$max_file_size = 100 * 1024 * 1024; // 100 MB
$allowed_extensions = ['zip', 'jpg', 'jpeg', 'png', 'rar', 'pdf'];
$allowed_mime_types = [
    'application/zip',
    'application/x-zip-compressed',
    'application/x-rar-compressed',
    'image/jpeg',
    'image/png',
    'application/pdf'
];

// =====================================================
// AMBIL DATA SESI FOTO
// =====================================================
$error = "";
$success = false;
$sesi_data = null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?error=noid");
    exit();
}

$id_sesi = intval($_GET['id']);

// Query data sesi dengan join ke order, pelanggan, paket, ruangan
$q_sesi = sqlsrv_query($conn, "
    SELECT 
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Status_Sesi,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        O.Keterangan AS Keterangan_Order,
        P.Nama_Pelanggan,
        P.Email_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
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
    WHERE S.ID_Sesi_Foto = ? AND S.ID_Karyawan = ? AND S.Status = 1
", array($id_sesi, $id_fotografer));

if (!$q_sesi || !sqlsrv_has_rows($q_sesi)) {
    header("Location: index.php?error=notfound");
    exit();
}

$sesi_data = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);
if ($sesi_data) {
    $sesi_data = array_change_key_case($sesi_data, CASE_LOWER);
}

// Validasi: hanya bisa upload jika Status_Sesi = 1 (Selesai)
if ($sesi_data['status_sesi'] != 1) {
    header("Location: index.php?error=notcompleted");
    exit();
}

// =====================================================
// PROSES UPLOAD FILE
// =====================================================
if (isset($_POST['upload_hasil'])) {

    if (!isset($_FILES['file_hasil']) || $_FILES['file_hasil']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Silakan pilih file untuk diupload!";
    } else {
        $file = $_FILES['file_hasil'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];

        // Cek error upload
        if ($file_error !== UPLOAD_ERR_OK) {
            $error = "Terjadi kesalahan saat upload file. Error code: " . $file_error;
        } else {
            // Validasi ukuran
            if ($file_size > $max_file_size) {
                $error = "Ukuran file terlalu besar! Maksimal 100 MB.";
            } else {
                // Validasi ekstensi
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_extensions)) {
                    $error = "Format file tidak didukung! Format yang diizinkan: ZIP, JPG, JPEG, PNG, RAR, PDF.";
                } else {
                    // Validasi MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file_tmp);
                    finfo_close($finfo);

                    // Cek MIME type dengan toleransi
                    $mime_valid = false;
                    foreach ($allowed_mime_types as $allowed) {
                        if (strpos($mime_type, $allowed) !== false || strpos($allowed, $mime_type) !== false) {
                            $mime_valid = true;
                            break;
                        }
                    }

                    // Fallback: jika MIME check gagal tapi ekstensi valid, tetap izinkan
                    if (!$mime_valid) {
                        // Whitelist ekstensi yang aman
                        if (in_array($file_ext, ['zip', 'jpg', 'jpeg', 'png', 'rar', 'pdf'])) {
                            $mime_valid = true;
                        }
                    }

                    if (!$mime_valid) {
                        $error = "Tipe file tidak valid!";
                    } else {
                        // Buat direktori upload jika belum ada
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        // Generate nama file unik
                        $id_order = $sesi_data['id_order'];
                        $timestamp = time();
                        $uniqid = uniqid();
                        $new_file_name = "hasil_order{$id_order}_{$timestamp}_{$uniqid}.{$file_ext}";
                        $target_path = $upload_dir . $new_file_name;

                        // Hapus file lama jika ada
                        if (!empty($sesi_data['file_hasil'])) {
                            $old_file = $upload_dir . $sesi_data['file_hasil'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }

                        // Pindahkan file
                        if (move_uploaded_file($file_tmp, $target_path)) {
                            // Update database
                            $update_sql = "UPDATE Sesi_Foto SET 
                                File_Hasil = ?, 
                                Tanggal_Upload_Hasil = GETDATE(),
                                Modified_By = ?,
                                Modified_Date = GETDATE()
                                WHERE ID_Sesi_Foto = ? AND Status = 1";

                            $update_stmt = sqlsrv_query($conn, $update_sql, array(
                                $new_file_name,
                                $username_fotografer,
                                $id_sesi
                            ));

                            if ($update_stmt) {
                                $success = true;
                                // Refresh data
                                $sesi_data['file_hasil'] = $new_file_name;
                                $sesi_data['tanggal_upload_hasil'] = new DateTime();
                            } else {
                                $error = "Gagal memperbarui database!";
                                // Hapus file yang sudah diupload
                                if (file_exists($target_path)) {
                                    unlink($target_path);
                                }
                            }
                        } else {
                            $error = "Gagal memindahkan file ke server!";
                        }
                    }
                }
            }
        }
    }
}

// =====================================================
// PROSES HAPUS FILE
// =====================================================
if (isset($_POST['hapus_hasil'])) {
    if (!empty($sesi_data['file_hasil'])) {
        $file_path = $upload_dir . $sesi_data['file_hasil'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $delete_sql = "UPDATE Sesi_Foto SET 
            File_Hasil = NULL, 
            Tanggal_Upload_Hasil = NULL,
            Modified_By = ?,
            Modified_Date = GETDATE()
            WHERE ID_Sesi_Foto = ? AND Status = 1";

        sqlsrv_query($conn, $delete_sql, array($username_fotografer, $id_sesi));

        header("Location: upload_hasil.php?id=" . $id_sesi . "&success=deleted");
        exit();
    }
}

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

// Format tanggal
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

// Cek apakah ada parameter success=deleted
if (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $success = true;
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

        /* SIDEBAR */
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

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
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

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }

        /* CARDS */
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

        /* INFO ITEM */
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .info-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
        }

        /* UPLOAD ZONE */
        .upload-zone {
            border: 2px dashed var(--light-pink);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff, var(--s-pink));
            transition: var(--transition-3d);
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--p-pink);
            background: linear-gradient(135deg, #ffffff, #FFE4E9);
            transform: scale(1.01);
        }
        .upload-zone i {
            font-size: 3rem;
            color: var(--p-pink);
            margin-bottom: 15px;
        }
        .upload-zone-text {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        .upload-zone-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* FILE PREVIEW */
        .file-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-radius: 16px;
            border: 2px solid #a7f3d0;
        }
        .file-preview-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: #059669;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .file-preview-info { flex: 1; }
        .file-preview-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            word-break: break-all;
        }
        .file-preview-meta {
            font-size: 0.75rem;
            color: #059669;
            font-weight: 600;
        }

        /* BUTTONS */
        .btn-action {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 800;
            font-size: 0.9rem;
            transition: var(--transition-3d);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3);
            color: #ffffff;
        }
        .btn-action-success {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .btn-action-success:hover {
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
        }
        .btn-action-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .btn-action-danger:hover {
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
        }
        .btn-action-secondary {
            background: #f1f5f9;
            color: var(--text-muted);
        }
        .btn-action-secondary:hover {
            background: #e2e8f0;
            color: var(--text-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* BADGE */
        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-upload { background: #dbeafe; color: #2563eb; }

        /* PROGRESS BAR */
        .progress-wrapper {
            display: none;
            margin-top: 20px;
        }
        .progress {
            height: 10px;
            border-radius: 10px;
            background: #f1f5f9;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* ANIMATION */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* RESPONSIVE */
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
                SpotLight.<br>
                <span>Panel Fotografer</span>
            </a>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link-custom">
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
                            <li><a href="../../Sesi/Upload/index.php" class="submenu-link active"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../../Sesi/Upload/index.php" style="color: var(--p-pink); text-decoration: none; font-weight: 600;">Upload</a></li>
                        <li class="breadcrumb-item active" style="color: var(--text-muted); font-weight: 600;">Upload Hasil</li>
                    </ol>
                </nav>
                <h3 class="fw-bold mb-0">Upload Hasil Foto</h3>
                <p class="text-muted small mb-0">Upload hasil pemotretan untuk customer.</p>
            </div>
            <a href="../../Sesi/Upload/index.php" class="btn-action btn-action-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="row g-4">
            <!-- INFO SESI -->
            <div class="col-lg-5 animate-fade-in">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-info-circle-fill text-danger me-2"></i>Detail Sesi</h5>
                        <span class="badge-status badge-selesai">Selesai</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">ID Sesi</span>
                        <span class="info-value">#<?= $sesi_data['id_sesi_foto'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID Order</span>
                        <span class="info-value">#<?= $sesi_data['id_order'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nama Pelanggan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_pelanggan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Pelanggan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['email_pelanggan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Paket Foto</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_paket']) ?> (<?= $sesi_data['durasi_waktu'] ?> menit)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ruangan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['nama_ruangan']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal Sesi</span>
                        <span class="info-value"><?= formatTanggal($sesi_data['tanggal_jadwal']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jam Sesi</span>
                        <span class="info-value"><?= formatWaktu($sesi_data['jam_mulai']) ?> - <?= formatWaktu($sesi_data['jam_selesai']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Waktu Mulai</span>
                        <span class="info-value"><?= $sesi_data['waktu_mulai'] ? formatTanggal($sesi_data['waktu_mulai']) . ' ' . formatWaktu($sesi_data['waktu_mulai']) : '-' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Waktu Selesai</span>
                        <span class="info-value"><?= $sesi_data['waktu_selesai'] ? formatTanggal($sesi_data['waktu_selesai']) . ' ' . formatWaktu($sesi_data['waktu_selesai']) : '-' ?></span>
                    </div>
                    <?php if (!empty($sesi_data['keterangan_order'])): ?>
                    <div class="info-item">
                        <span class="info-label">Keterangan</span>
                        <span class="info-value"><?= htmlspecialchars($sesi_data['keterangan_order']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UPLOAD AREA -->
            <div class="col-lg-7 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="card-3d h-100">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-cloud-upload-fill text-danger me-2"></i>Upload File Hasil</h5>
                        <?php if (!empty($sesi_data['file_hasil'])): ?>
                            <span class="badge-status badge-upload">Sudah Upload</span>
                        <?php else: ?>
                            <span class="badge-status badge-selesai">Belum Upload</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($sesi_data['file_hasil'])): ?>
                        <!-- FILE SUDAH ADA -->
                        <div class="file-preview mb-4">
                            <div class="file-preview-icon">
                                <i class="bi bi-file-earmark-zip"></i>
                            </div>
                            <div class="file-preview-info">
                                <div class="file-preview-name"><?= htmlspecialchars($sesi_data['file_hasil']) ?></div>
                                <div class="file-preview-meta">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Diupload: <?= $sesi_data['tanggal_upload_hasil'] ? formatTanggal($sesi_data['tanggal_upload_hasil']) . ' ' . formatWaktu($sesi_data['tanggal_upload_hasil']) : '-' ?>
                                </div>
                            </div>
                            <a href="../../uploads/hasil/<?= rawurlencode($sesi_data['file_hasil']) ?>" 
                               class="btn-action btn-action-success" 
                               download 
                               title="Download File">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>

                        <div class="alert border-0 mb-4" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-radius: 14px;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-warning"></i>
                                <span style="font-size: 0.85rem; font-weight: 600; color: #92400e;">
                                    File sudah pernah diupload. Upload ulang akan menimpa file lama.
                                </span>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="formUpload">
                            <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click();" id="dropZone">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <div class="upload-zone-text">Klik atau seret file ke sini</div>
                                <div class="upload-zone-sub">Format: ZIP, JPG, JPEG, PNG, RAR, PDF (Max 100 MB)</div>
                                <input type="file" name="file_hasil" id="fileInput" class="d-none" 
                                       accept=".zip,.jpg,.jpeg,.png,.rar,.pdf" required>
                            </div>

                            <div id="fileSelected" class="d-none mb-3">
                                <div class="d-flex align-items-center gap-2 p-3" style="background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                    <i class="bi bi-file-earmark-check text-success fs-4"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.85rem; color: #166534;" id="selectedFileName">-</div>
                                        <div style="font-size: 0.75rem; color: #22c55e;" id="selectedFileSize">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-wrapper" id="progressWrapper">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Mengupload...</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--p-pink);" id="progressText">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="upload_hasil" class="btn-action flex-fill" id="btnUpload">
                                    <i class="bi bi-cloud-upload"></i> Upload Ulang
                                </button>
                                <button type="submit" name="hapus_hasil" class="btn-action btn-action-danger" 
                                        onclick="return confirmHapus(event)">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- FILE BELUM ADA -->
                        <form method="POST" enctype="multipart/form-data" id="formUpload">
                            <div class="upload-zone mb-3" onclick="document.getElementById('fileInput').click();" id="dropZone">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <div class="upload-zone-text">Klik atau seret file ke sini</div>
                                <div class="upload-zone-sub">Format: ZIP, JPG, JPEG, PNG, RAR, PDF (Max 100 MB)</div>
                                <input type="file" name="file_hasil" id="fileInput" class="d-none" 
                                       accept=".zip,.jpg,.jpeg,.png,.rar,.pdf" required>
                            </div>

                            <div id="fileSelected" class="d-none mb-3">
                                <div class="d-flex align-items-center gap-2 p-3" style="background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                    <i class="bi bi-file-earmark-check text-success fs-4"></i>
                                    <div>
                                        <div class="fw-bold" style="font-size: 0.85rem; color: #166534;" id="selectedFileName">-</div>
                                        <div style="font-size: 0.75rem; color: #22c55e;" id="selectedFileSize">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-wrapper" id="progressWrapper">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Mengupload...</span>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--p-pink);" id="progressText">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                                </div>
                            </div>

                            <button type="submit" name="upload_hasil" class="btn-action w-100" id="btnUpload">
                                <i class="bi bi-cloud-upload"></i> Upload Hasil Foto
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- PETUNJUK -->
                    <div class="mt-4 p-3" style="background: #f8fafc; border-radius: 14px;">
                        <h6 class="fw-bold mb-2" style="font-size: 0.8rem; color: var(--text-muted);">
                            <i class="bi bi-lightbulb-fill text-warning me-1"></i> Petunjuk Upload
                        </h6>
                        <ul class="mb-0 ps-3" style="font-size: 0.75rem; color: var(--text-muted);">
                            <li>Gunakan format <strong>ZIP</strong> untuk mengupload banyak foto sekaligus.</li>
                            <li>Pastikan file tidak melebihi <strong>100 MB</strong>.</li>
                            <li>Beri nama file yang jelas (contoh: hasil_foto_nama_customer.zip).</li>
                            <li>Pastikan kualitas foto sudah di-edit sebelum diupload.</li>
                        </ul>
                    </div>
                </div>
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

        // File Input Handler
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const selectedFileName = document.getElementById('selectedFileName');
        const selectedFileSize = document.getElementById('selectedFileSize');
        const dropZone = document.getElementById('dropZone');

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    selectedFileName.textContent = file.name;
                    selectedFileSize.textContent = formatFileSize(file.size);
                    fileSelected.classList.remove('d-none');
                }
            });
        }

        // Drag & Drop
        if (dropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

            dropZone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    const file = files[0];
                    selectedFileName.textContent = file.name;
                    selectedFileSize.textContent = formatFileSize(file.size);
                    fileSelected.classList.remove('d-none');
                }
            });
        }

        // Form Submit with Progress Simulation
        const formUpload = document.getElementById('formUpload');
        const progressWrapper = document.getElementById('progressWrapper');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const btnUpload = document.getElementById('btnUpload');

        if (formUpload) {
            formUpload.addEventListener('submit', function(e) {
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'File Belum Dipilih',
                        text: 'Silakan pilih file terlebih dahulu.',
                        confirmButtonColor: '#D53D66'
                    });
                    return false;
                }

                // Cek tombol mana yang ditekan menggunakan document.activeElement
                const activeElement = document.activeElement;
                const isUploadBtn = activeElement && (activeElement.name === 'upload_hasil' || activeElement.closest('button[name="upload_hasil"]'));
                
                // Show progress hanya untuk upload, bukan hapus
                if (isUploadBtn) {
                    progressWrapper.style.display = 'block';
                    btnUpload.disabled = true;
                    btnUpload.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengupload...';

                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += Math.random() * 15;
                        if (progress >= 90) {
                            progress = 90;
                            clearInterval(interval);
                        }
                        progressBar.style.width = progress + '%';
                        progressText.textContent = Math.round(progress) + '%';
                    }, 200);
                }
            });
        }

        function confirmHapus(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Hapus File?',
                text: 'File hasil foto akan dihapus permanen. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    e.target.closest('form').submit();
                }
            });
            return false;
        }

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

    <!-- SweetAlert Notifikasi -->
    <?php if ($success === true): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Upload Berhasil!',
            text: 'File hasil foto berhasil diupload.',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Selesai'
        }).then(() => {
            // Complete progress bar
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            if (progressBar) progressBar.style.width = '100%';
            if (progressText) progressText.textContent = '100%';
        });
    </script>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Upload Gagal!',
            text: '<?= addslashes($error) ?>',
            confirmButtonColor: '#D53D66',
            confirmButtonText: 'Coba Lagi'
        });
    </script>
    <?php endif; ?>
</body>
</html>