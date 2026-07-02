<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

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

// =====================================================
// AMBIL ID PROPERTI DARI URL
// =====================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: list.php?status_sukses=error&message=ID properti tidak valid");
    exit();
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
// AMBIL DATA PROPERTI
// =====================================================
$properti = safe_sqlsrv_fetch($conn, 
    "SELECT * FROM Properti WHERE ID_Properti = ? AND Is_Deleted = 0", 
    [$id]
);

if (!$properti) {
    header("Location: list.php?status_sukses=error&message=Properti tidak ditemukan atau sudah dihapus");
    exit();
}

// =====================================================
// AMBIL DAFTAR RUANGAN (UNTUK DROPDOWN)
// Termasuk ruangan ID nya sendiri walau statusnya nonaktif, agar tetap muncul saat edit
// =====================================================
$daftar_ruangan = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan, Kapasitas_Ruangan 
     FROM Ruangan 
     WHERE (Status = 1 OR ID_Ruangan = ?) AND Is_Deleted = 0 
     ORDER BY Nama_Ruangan ASC",
    [$properti['ID_Ruangan']]
);

// =====================================================
// KATEGORI PROPERTI (DAFTAR TETAP)
// =====================================================
$daftar_kategori = ['Furniture', 'Backdrop', 'Lighting', 'Dekorasi', 'Kostum', 'Lainnya'];
// Tambahkan kategori lama jika tidak ada di daftar (data lama/custom)
if (!empty($properti['Kategori_Properti']) && !in_array($properti['Kategori_Properti'], $daftar_kategori)) {
    $daftar_kategori[] = $properti['Kategori_Properti'];
}

// =====================================================
// PROSES UPDATE DATA
// =====================================================
$error = "";
$success = false;

if (isset($_POST['update'])) {
    $id_ruangan = (int)($_POST['id_ruangan'] ?? 0);
    $nama       = trim($_POST['nama_properti'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $status     = (int)($_POST['status'] ?? 1);

    // --- VALIDASI SERVER-SIDE (KUAT) ---
    if ($id_ruangan <= 0) {
        $error = "Ruangan wajib dipilih!";
    } elseif (empty($nama)) {
        $error = "Nama properti wajib diisi!";
    } elseif (strlen($nama) > 100) {
        $error = "Nama properti maksimal 100 karakter!";
    } elseif (empty($kategori)) {
        $error = "Kategori properti wajib dipilih!";
    } elseif (strlen($kategori) > 50) {
        $error = "Kategori maksimal 50 karakter!";
    } elseif (strlen($deskripsi) > 255) {
        $error = "Deskripsi maksimal 255 karakter!";
    } else {
        // --- CEK RUANGAN VALID ---
        $cek_ruangan = safe_sqlsrv_fetch($conn,
            "SELECT COUNT(*) as total FROM Ruangan WHERE ID_Ruangan = ? AND Is_Deleted = 0",
            [$id_ruangan]
        );
        if (($cek_ruangan['total'] ?? 0) == 0) {
            $error = "Ruangan yang dipilih tidak valid!";
        } else {
            // --- CEK DUPLIKAT NAMA DALAM RUANGAN YANG SAMA (kecuali milik sendiri) ---
            $cek_dup = safe_sqlsrv_fetch($conn, 
                "SELECT COUNT(*) as total FROM Properti WHERE Nama_Properti = ? AND ID_Ruangan = ? AND ID_Properti <> ? AND Is_Deleted = 0", 
                [$nama, $id_ruangan, $id]
            );
            if (($cek_dup['total'] ?? 0) > 0) {
                $error = "Properti '{$nama}' sudah digunakan pada ruangan tersebut!";
            } else {
                // --- PROSES FOTO ---
                $foto_lama = $properti['Foto_Properti'] ?? '';
                $foto_baru = $foto_lama;
                $upload_path = '';
                $hapus_foto_lama = false;

                if (isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
                    $foto_name = $_FILES['foto']['name'];
                    $foto_tmp  = $_FILES['foto']['tmp_name'];
                    $foto_size = $_FILES['foto']['size'];
                    $foto_error = $_FILES['foto']['error'];

                    if ($foto_error != UPLOAD_ERR_OK) {
                        $error = "Terjadi kesalahan saat upload foto.";
                    } else {
                        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                        if (!in_array($ext, $allowed)) {
                            $error = "Format gambar harus JPG, JPEG, PNG, atau WEBP!";
                        } elseif ($foto_size > 2097152) {
                            $error = "Ukuran gambar maksimal 2MB!";
                        } else {
                            $check = getimagesize($foto_tmp);
                            if ($check === false) {
                                $error = "File yang diupload bukan gambar valid!";
                            } else {
                                $new_filename = "properti_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                                $upload_dir = "../../assets/img/properti/";
                                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                                $upload_path = $upload_dir . $new_filename;

                                if (move_uploaded_file($foto_tmp, $upload_path)) {
                                    $foto_baru = $new_filename;
                                    $hapus_foto_lama = true;
                                } else {
                                    $error = "Gagal memindahkan file ke server.";
                                }
                            }
                        }
                    }
                }

                if ($error == "") {
                    // --- UPDATE PROPERTI ---
                    $sql_update = "UPDATE Properti SET 
                        ID_Ruangan = ?, Nama_Properti = ?, Kategori_Properti = ?, Deskripsi = ?, 
                        Foto_Properti = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE() 
                        WHERE ID_Properti = ?";
                    $params_update = [$id_ruangan, $nama, $kategori, $deskripsi, $foto_baru, $status, $nama_admin, $id];
                    $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

                    if ($stmt_update === false) {
                        if (!empty($upload_path) && file_exists($upload_path)) unlink($upload_path);
                        $error = "Gagal update properti: " . print_r(sqlsrv_errors(), true);
                    } else {
                        sqlsrv_free_stmt($stmt_update);

                        // Hapus foto lama kalau berhasil ganti
                        if ($hapus_foto_lama && !empty($foto_lama) && $foto_lama != 'default_properti.jpg') {
                            $old_path = "../../assets/img/properti/" . $foto_lama;
                            if (file_exists($old_path)) unlink($old_path);
                        }

                        $success = true;

                        // Refresh data
                        $properti = safe_sqlsrv_fetch($conn, "SELECT * FROM Properti WHERE ID_Properti = ?", [$id]);
                    }
                }
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
    <title>Edit Properti – SpotLight Studio</title>

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

        /* CURRENT FOTO PREVIEW */
        .current-foto-box {
            border-radius: 16px; overflow: hidden;
            border: 2px solid var(--light-pink); margin-bottom: 16px;
            position: relative;
        }
        .current-foto-box img {
            width: 100%; max-height: 200px; object-fit: cover; display: block;
        }
        .current-foto-label {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.6));
            color: #fff; padding: 12px 16px; font-size: 0.8rem; font-weight: 700;
        }

        .file-upload-zone {
            border: 2px dashed #e2e8f0; border-radius: 16px;
            padding: 24px; text-align: center;
            transition: var(--transition-3d); cursor: pointer;
            background: #f8fafc;
        }
        .file-upload-zone:hover, .file-upload-zone.dragover {
            border-color: var(--p-pink); background: var(--s-pink);
        }
        .file-upload-zone i { font-size: 2rem; color: #cbd5e1; margin-bottom: 8px; display: block; }
        .file-upload-zone p { font-size: 0.85rem; color: #64748b; font-weight: 600; margin: 0; }
        .file-upload-zone small { font-size: 0.7rem; color: #94a3b8; }
        .file-upload-zone input[type="file"] { display: none; }

        #preview-container {
            display: none; margin-top: 16px;
            position: relative; border-radius: 14px; overflow: hidden;
            border: 2px solid var(--light-pink);
        }
        #preview-container img {
            width: 100%; max-height: 200px; object-fit: cover; display: block;
        }
        #preview-container .remove-preview {
            position: absolute; top: 10px; right: 10px;
            background: rgba(220, 38, 38, 0.9); color: #fff;
            border: none; border-radius: 50%; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.85rem; transition: all 0.2s;
        }
        #preview-container .remove-preview:hover { background: #dc2626; transform: scale(1.1); }

        .ruangan-info-box {
            background: #f8fafc; border-radius: 16px;
            padding: 16px 20px; border: 2px solid #e2e8f0;
            margin-top: 10px; display: none;
            align-items: center; gap: 14px;
        }
        .ruangan-info-box.show { display: flex; }
        .ruangan-info-box i { font-size: 1.6rem; color: var(--p-pink); flex-shrink: 0; }
        .ruangan-info-box .ri-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .ruangan-info-box .ri-kapasitas { font-size: 0.75rem; color: var(--text-muted); }

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

        .alert-custom {
            background: #fef2f2; border: none;
            border-left: 4px solid #dc2626; border-radius: 12px;
            color: #991b1b; font-size: 0.85rem; padding: 14px 18px;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .alert-custom i { font-size: 1.1rem; }

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
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
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

        <!-- HEADER -->
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Edit Properti</h3>
                <p class="text-muted small mb-0">Perbarui data properti dan relasi ruangan.</p>
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
            <a href="./list.php">Properti</a>
            <i class="bi bi-chevron-right"></i>
            <span class="active">Edit Properti</span>
        </div>

        <!-- FORM CARD -->
        <div class="form-card fade-in-up">
            <div class="form-card-header">
                <h4><i class="bi bi-pencil-square me-2"></i>Edit Properti: <?= htmlspecialchars($properti['Nama_Properti']) ?></h4>
                <p>Perbarui informasi properti dan relasi ruangan.</p>
            </div>
            <div class="form-card-body">

                <?php if ($error != ""): ?>
                    <div class="alert-custom">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="formProperti">
                    <div class="row">
                        <!-- Pilih Ruangan -->
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Ruangan <span class="required">*</span></label>
                            <select name="id_ruangan" id="id_ruangan" class="form-select-custom" required onchange="updateRuanganInfo()">
                                <option value="">-- Pilih Ruangan --</option>
                                <?php foreach ($daftar_ruangan as $r): 
                                    $sel = ($properti['ID_Ruangan'] == $r['ID_Ruangan']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $r['ID_Ruangan'] ?>" data-kapasitas="<?= $r['Kapasitas_Ruangan'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($r['Nama_Ruangan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Properti akan ditampilkan kepada pelanggan saat memilih ruangan ini
                            </div>
                            <div class="ruangan-info-box" id="ruanganInfoBox">
                                <i class="bi bi-door-open-fill"></i>
                                <div>
                                    <div class="ri-nama" id="riNama">-</div>
                                    <div class="ri-kapasitas" id="riKapasitas">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Nama Properti -->
                        <div class="col-md-8 mb-4">
                            <label class="form-label">Nama Properti <span class="required">*</span></label>
                            <input type="text" name="nama_properti" class="form-control-custom" required 
                                   maxlength="100" placeholder="Contoh: Sofa Beludru Pink"
                                   value="<?= htmlspecialchars($properti['Nama_Properti']) ?>">
                            <div class="input-hint">
                                <i class="bi bi-info-circle"></i> Maksimal 100 karakter, nama harus unik dalam satu ruangan
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Kategori <span class="required">*</span></label>
                            <select name="kategori" class="form-select-custom" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($daftar_kategori as $kat): 
                                    $sel = ($properti['Kategori_Properti'] == $kat) ? 'selected' : '';
                                ?>
                                    <option value="<?= $kat ?>" <?= $sel ?>><?= $kat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="mb-4">
                        <label class="form-label">Deskripsi Properti</label>
                        <textarea name="deskripsi" class="form-control-custom" 
                                  maxlength="255" placeholder="Deskripsikan properti ini (opsional)..."><?= htmlspecialchars($properti['Deskripsi'] ?? '') ?></textarea>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i> Maksimal 255 karakter, akan ditampilkan ke pelanggan
                        </div>
                    </div>

                    <!-- Foto Properti -->
                    <div class="mb-4">
                        <label class="form-label">Foto Properti</label>

                        <!-- Foto Saat Ini -->
                        <?php 
                        $path_foto = "../../assets/img/properti/" . ($properti['Foto_Properti'] ?? '');
                        $foto_exists = !empty($properti['Foto_Properti']) && file_exists($path_foto);
                        ?>
                        <?php if ($foto_exists): ?>
                            <div class="current-foto-box">
                                <img src="<?= $path_foto ?>" alt="Foto Saat Ini">
                                <div class="current-foto-label">
                                    <i class="bi bi-image me-1"></i> Foto saat ini: <?= htmlspecialchars($properti['Foto_Properti']) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px;">
                                <i class="bi bi-image me-2 text-muted"></i> Menggunakan foto default
                            </div>
                        <?php endif; ?>

                        <!-- Upload Foto Baru -->
                        <div class="file-upload-zone" id="dropzone" onclick="document.getElementById('foto-input').click()">
                            <input type="file" name="foto" id="foto-input" 
                                   accept="image/jpeg,image/jpg,image/png,image/webp" onchange="handleFileSelect(event)">
                            <i class="bi bi-camera-fill" id="upload-icon"></i>
                            <p id="upload-text">Klik untuk ganti foto (opsional)</p>
                            <small>JPG, JPEG, PNG, WEBP — Maksimal 2MB</small>
                        </div>
                        <div id="preview-container">
                            <img id="preview-img" src="" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview(event)" title="Hapus foto">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="update" class="btn-submit">
                            <i class="bi bi-check2-all"></i> Simpan Perubahan
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

        // Update Info Ruangan Terpilih
        function updateRuanganInfo() {
            const select = document.getElementById('id_ruangan');
            const box = document.getElementById('ruanganInfoBox');
            const opt = select.options[select.selectedIndex];

            if (select.value) {
                document.getElementById('riNama').textContent = opt.text.trim();
                document.getElementById('riKapasitas').textContent = opt.getAttribute('data-kapasitas') + ' orang kapasitas';
                box.classList.add('show');
            } else {
                box.classList.remove('show');
            }
        }

        // File Upload & Preview
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('preview-container');
            const previewImg = document.getElementById('preview-img');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');

            if (file) {
                if (file.size > 2097152) {
                    Swal.fire({
                        icon: 'error', title: 'Ukuran Terlalu Besar',
                        text: 'Ukuran gambar maksimal 2MB.', confirmButtonColor: '#D53D66'
                    });
                    event.target.value = ''; return;
                }
                const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    Swal.fire({
                        icon: 'error', title: 'Format Tidak Valid',
                        text: 'Format gambar harus JPG, JPEG, PNG, atau WEBP.', confirmButtonColor: '#D53D66'
                    });
                    event.target.value = ''; return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                    uploadIcon.style.display = 'none';
                    uploadText.textContent = 'Foto baru: ' + file.name;
                };
                reader.readAsDataURL(file);
            }
        }

        function removePreview(e) {
            e.stopPropagation();
            const input = document.getElementById('foto-input');
            const previewContainer = document.getElementById('preview-container');
            const uploadIcon = document.getElementById('upload-icon');
            const uploadText = document.getElementById('upload-text');
            input.value = '';
            previewContainer.style.display = 'none';
            uploadIcon.style.display = 'block';
            uploadText.textContent = 'Klik untuk ganti foto (opsional)';
        }

        // Drag & Drop
        const dropzone = document.getElementById('dropzone');
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault(); dropzone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('foto-input').files = files;
                handleFileSelect({ target: { files: files } });
            }
        });

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info', showCancelButton: true,
                confirmButtonColor: '#D53D66', cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            document.getElementById('live-clock').innerText = 
                `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();

        // Init: tampilkan info ruangan saat halaman dimuat
        updateRuanganInfo();
    </script>

    <?php if ($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!',
            text: 'Data properti telah diperbarui.',
            confirmButtonColor: '#D53D66'
        }).then(() => window.location = 'list.php?status_sukses=edit');
    </script>
    <?php endif; ?>
</body>
</html>