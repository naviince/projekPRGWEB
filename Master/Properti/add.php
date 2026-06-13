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
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch(
    $conn,
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
// AMBIL DAFTAR RUANGAN
// =====================================================
$ruangan_list = safe_sqlsrv_fetch_all(
    $conn,
    "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Ruangan ASC",
    []
);

// =====================================================
// PROSES SIMPAN DATA
// =====================================================
$error = '';
$success = false;

if (isset($_POST['simpan'])) {
    $id_ruangan = (int)($_POST['id_ruangan'] ?? 0);
    $nama = trim($_POST['nama_properti'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // --- VALIDASI SERVER-SIDE ---
    if ($id_ruangan <= 0) {
        $error = 'Ruangan wajib dipilih.';
    } elseif (empty($nama)) {
        $error = 'Nama properti wajib diisi.';
    } elseif (strlen($nama) > 100) {
        $error = 'Nama properti maksimal 100 karakter.';
    } elseif (empty($kategori)) {
        $error = 'Kategori properti wajib diisi.';
    } elseif (strlen($kategori) > 50) {
        $error = 'Kategori properti maksimal 50 karakter.';
    } elseif (empty($deskripsi)) {
        $error = 'Deskripsi wajib diisi.';
    } elseif (strlen($deskripsi) > 255) {
        $error = 'Deskripsi maksimal 255 karakter.';
    }

    // Cek ruangan valid
    if ($error === '') {
        $cek_ruangan = safe_sqlsrv_fetch(
            $conn,
            "SELECT COUNT(*) as total FROM Ruangan WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0",
            [$id_ruangan]
        );
        if (($cek_ruangan['total'] ?? 0) <= 0) {
            $error = 'Ruangan tidak valid / tidak aktif.';
        }
    }

    // Cek duplikat nama properti di dalam ruangan (opsional)
    if ($error === '') {
        $cek_dup = safe_sqlsrv_fetch(
            $conn,
            "SELECT COUNT(*) as total FROM Properti WHERE Nama_Properti = ? AND ID_Ruangan = ? AND Is_Deleted = 0",
            [$nama, $id_ruangan]
        );
        if (($cek_dup['total'] ?? 0) > 0) {
            $error = 'Nama properti untuk ruangan ini sudah ada.';
        }
    }

    // Validasi upload foto (wajib)
    $foto = '';
    if ($error === '') {
        $foto_name = $_FILES['foto']['name'] ?? '';
        $foto_tmp = $_FILES['foto']['tmp_name'] ?? '';
        $foto_size = $_FILES['foto']['size'] ?? 0;
        $foto_error = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($foto_error == UPLOAD_ERR_NO_FILE || empty($foto_name)) {
            $error = 'Foto properti wajib diupload.';
        } elseif ($foto_error != UPLOAD_ERR_OK) {
            $error = 'Terjadi kesalahan saat upload foto.';
        } else {
            $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $error = 'Format foto harus JPG, JPEG, PNG, atau WEBP.';
            } elseif ($foto_size > 2097152) {
                $error = 'Ukuran foto maksimal 2MB.';
            } else {
                $check = @getimagesize($foto_tmp);
                if ($check === false) {
                    $error = 'File yang diupload bukan gambar valid.';
                }
            }
        }
    }

    if ($error === '') {
        $new_filename = 'properti_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $upload_dir = "../../assets/img/properti/";
        $upload_path = $upload_dir . $new_filename;

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!move_uploaded_file($foto_tmp, $upload_path)) {
            $error = 'Gagal memindahkan file ke server.';
        } else {
            // BEGIN TRANSACTION
            sqlsrv_begin_transaction($conn);
            try {
                $sql = "INSERT INTO Properti (ID_Ruangan, Nama_Properti, Kategori_Properti, Deskripsi, Foto_Properti, Status, Is_Deleted, Created_By, Created_Date)
                        VALUES (?, ?, ?, ?, ?, 1, 0, ?, GETDATE())";

                $params = [$id_ruangan, $nama, $kategori, $deskripsi, $new_filename, $nama_admin];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    throw new Exception('Gagal menyimpan data properti: ' . print_r(sqlsrv_errors(), true));
                }

                sqlsrv_commit($conn);
                $success = true;
            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                if (!empty($new_filename) && file_exists($upload_path)) {
                    unlink($upload_path);
                }
                $error = $e->getMessage();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Properti – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --body-bg: #f8fafc; --light-pink: #FFE4E9; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); overflow-x: hidden; }
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
        .nav-link-custom { display: flex; align-items: center; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; margin-bottom: 8px; font-size: 0.9rem; }
        .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); }
        .form-card { background: white; border-radius: 24px; padding: 40px; box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); border: 1px solid rgba(255, 228, 233, 0.8); }
        .form-input { border: 2px solid #f1f5f9; border-radius: 14px; padding: 12px; font-weight: 600; width: 100%; outline: none; transition: 0.3s; }
        .form-input:focus { border-color: var(--p-pink); }
        .btn-save { background: linear-gradient(135deg, var(--p-pink), #CA3366); color: white; border: none; border-radius: 14px; padding: 15px; font-weight: 800; box-shadow: 0 8px 20px rgba(213,61,102,0.25); width: 100%; }
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

    <div class="main-content">
        <div class="d-flex align-items-center mb-4">
            <a href="list.php" class="btn btn-light rounded-circle me-3 shadow-sm"><i class="bi bi-arrow-left"></i></a>
            <h3 class="fw-bold mb-0">Tambah Properti 🛋️</h3>
        </div>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <img id="prev" src="../../assets/img/properti/default_properti.jpg" class="rounded-4 img-fluid mb-3" style="max-height: 250px; border: 4px solid #f8fafc;">
                        <input type="file" name="foto" class="form-control" required onchange="document.getElementById('prev').src = window.URL.createObjectURL(this.files[0])">
                    </div>
                    <div class="col-md-8">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger mb-3" style="border-radius: 14px;">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">RUANGAN STUDIO</label>
                            <select name="id_ruangan" class="form-input" required>
                                <?php if (!empty($ruangan_list)): ?>
                                    <?php foreach ($ruangan_list as $r): ?>
                                        <option value="<?= (int)$r['ID_Ruangan'] ?>"><?= htmlspecialchars($r['Nama_Ruangan']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Belum ada ruangan aktif</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">NAMA PROPERTI</label>
                            <input type="text" name="nama_properti" class="form-input" required maxlength="100" placeholder="Contoh: Sofa Putih" value="<?= htmlspecialchars($_POST['nama_properti'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">KATEGORI</label>
                            <input type="text" name="kategori" class="form-input" required maxlength="50" placeholder="Mebel / Dekorasi" value="<?= htmlspecialchars($_POST['kategori'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-2">DESKRIPSI</label>
                            <textarea name="deskripsi" class="form-input" rows="3" maxlength="255"><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                        </div>

                        <div class="input-hint mt-1 mb-3" style="font-size: 0.75rem; color: #718096; font-weight: 600;">
                            <i class="bi bi-info-circle me-1"></i> Foto wajib (JPG/JPEG/PNG/WEBP, max 2MB)
                        </div>

                        <button type="submit" name="simpan" class="btn-save mt-3">SIMPAN DATA</button>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <?php if($success): ?>
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Properti disimpan.', confirmButtonColor: '#D53D66' }).then(() => location.href='list.php');</script>
    <?php endif; ?>
</body>
</html>