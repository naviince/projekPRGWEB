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
// PAGINATION & FILTER
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$ruangan_f = isset($_GET['ruangan']) ? trim($_GET['ruangan']) : "";

// =====================================================
// STATISTIK
// =====================================================
$stats = safe_sqlsrv_fetch(
    $conn,
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) as aktif,
            SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) as nonaktif
     FROM Properti
     WHERE Is_Deleted = 0"
) ?? ['total' => 0, 'aktif' => 0, 'nonaktif' => 0];

// =====================================================
// QUERY LIST DATA DENGAN FILTER & PAGINATION
// =====================================================
$conditions = ["p.Is_Deleted = 0"];
$params = [];

if (!empty($cari)) {
    $conditions[] = "(p.Nama_Properti LIKE ? OR p.Kategori_Properti LIKE ? OR p.Deskripsi LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

if (!empty($ruangan_f)) {
    $conditions[] = "p.ID_Ruangan = ?";
    $params[] = (int)$ruangan_f;
}

$count_sql = "SELECT COUNT(*) AS total FROM Properti p WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $count_sql, $params);
$total_halaman = ceil($total_records / $limit);

$list_sql = "SELECT
                p.ID_Properti,
                p.Nama_Properti,
                p.Kategori_Properti,
                p.Deskripsi,
                p.Foto_Properti,
                p.ID_Ruangan,
                p.Status,
                r.Nama_Ruangan
            FROM Properti p
            JOIN Ruangan r ON p.ID_Ruangan = r.ID_Ruangan
            WHERE " . implode(" AND ", $conditions) . "
            ORDER BY r.Nama_Ruangan ASC, p.Nama_Properti ASC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_list = $params;
$params_list[] = $offset;
$params_list[] = $limit;

$properti_list = safe_sqlsrv_fetch_all($conn, $list_sql, $params_list);
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Properti – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --body-bg: #f8fafc; --zebra: #FFF8F0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: #1e1e24; overflow-x: hidden; }
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

        /* CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .card-3d { background: white; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); padding: 20px; transition: 0.4s; }
        .stats-scroll-wrapper { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: var(--s-pink); color: var(--p-pink); }
        .search-input-main { border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 18px 12px 44px; width: 100%; transition: 0.4s; }
        .btn-reg-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 14px; padding: 12px 25px; font-weight: 800; border: none; box-shadow: 0 8px 20px rgba(213, 61, 102, 0.25); text-decoration: none; display: inline-flex; align-items: center; }
        .data-table { width: 100%; min-width: 900px; border-collapse: separate; border-spacing: 0; }
        .data-table thead th { padding: 16px 20px; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; text-align: left; }
        .data-table tbody tr:nth-child(even) { background-color: var(--zebra); }
        .data-table tbody tr:hover { background-color: #FFEDD5 !important; }
        .btn-action-circle { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid #eef2f6; background: white; color: #D53D66; text-decoration: none; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #059669; }
        input:checked + .slider:before { transform: translateX(20px); }
        .page-link-pag { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; border-radius: 12px; background: white; border: 2px solid #FFF5F7; color: #4a5568; font-weight: 700; text-decoration: none; transition: 0.3s; }
        .page-link-pag.active-pag { background: var(--p-pink) !important; color: white !important; border-color: var(--p-pink) !important; }
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Master Properti 🛋️</h3>
                <p class="text-muted small mb-0">Kelola item properti pendukung berdasarkan ruangan studio.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-white shadow-sm text-dark p-2 px-3 rounded-3" style="border: 1px solid #eee; font-weight: 700;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock"></span>
                </span>
                <img src="<?= $foto_admin_src ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            </div>
        </div>

        <div class="stats-scroll-wrapper">
            <div class="card-3d" style="min-width: 240px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div><div class="small text-muted fw-bold">TOTAL PROPERTI</div><div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div></div>
                </div>
            </div>
            <div class="card-3d" style="min-width: 240px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: #ecfdf5; color: #059669;"><i class="bi bi-check-circle"></i></div>
                    <div><div class="small text-muted fw-bold">PROPERTI AKTIF</div><div class="h4 fw-bold mb-0"><?= $stats['aktif'] ?></div></div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mb-4 flex-wrap align-items-center">
            <form method="GET" class="d-flex gap-2 flex-grow-1" style="position: relative;">
                <i class="bi bi-search" style="position: absolute; left: 16px; top: 14px; color: #94a3b8;"></i>
                <input type="text" name="cari" class="search-input-main" placeholder="Cari nama properti..." value="<?= htmlspecialchars($cari) ?>">
                <select name="ruangan" class="search-input-main" style="width: 200px; padding-left: 15px;">
                    <option value="">Semua Ruangan</option>
                    <?php $qrl = sqlsrv_query($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Is_Deleted=0"); 
                    while($r = sqlsrv_fetch_array($qrl, SQLSRV_FETCH_ASSOC)) echo "<option value='".$r['ID_Ruangan']."' ".($ruangan_f == $r['ID_Ruangan'] ? 'selected' : '').">".$r['Nama_Ruangan']."</option>"; ?>
                </select>
                <button type="submit" class="btn btn-dark rounded-4 px-4 fw-bold">Filter</button>
            </form>
            <a href="add.php" class="btn-reg-header"><i class="bi bi-plus-lg me-2"></i>Tambah Properti</a>
        </div>

        <div class="card-3d">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Info Properti</th>
                            <th>Ruangan</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = $offset + 1; foreach(($properti_list ?? []) as $row): ?>

                        <tr>
                            <td class="fw-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="../../assets/img/properti/<?= $row['Foto_Properti'] ?>" style="width: 45px; height: 45px; border-radius: 10px; object-fit: cover;">
                                    <span class="fw-bold"><?= htmlspecialchars($row['Nama_Properti']) ?></span>
                                </div>
                            </td>
                            <td class="fw-semibold"><?= $row['Nama_Ruangan'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $row['Kategori_Properti'] ?></span></td>
                            <td><span class="badge rounded-pill <?= $row['Status']==1 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?> px-3 py-2 fw-bold" style="font-size: 0.7rem;"><?= $row['Status']==1 ? 'AKTIF' : 'NONAKTIF' ?></span></td>
                            <td class="text-center">
                                <a href="edit.php?id=<?= $row['ID_Properti'] ?>" class="btn-action-circle me-1" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                <label class="toggle-switch me-1" title="Ubah Status">
                                    <input type="checkbox" <?= $row['Status'] == 1 ? 'checked' : '' ?> onchange="window.location.href='action_properti.php?aksi=toggle_status&id=<?= $row['ID_Properti'] ?>&status='+(this.checked?1:0)">
                                    <span class="slider"></span>
                                </label>
                                <button onclick="del(<?= $row['ID_Properti'] ?>, '<?= $row['Nama_Properti'] ?>')" class="btn-action-circle text-danger"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($properti_list)): ?>
                        <tr></tr>
                            <td colspan="6" class="text-center text-muted py-4">Tidak ada data properti.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="small text-muted fw-bold">Menampilkan <?= min($offset + 1, $total_records) ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data</div>
                <div class="d-flex gap-1">
                    <?php for($i=1; $i<=$total_halaman; $i++): ?>
                        <a href="list.php?halaman=<?= $i ?>&cari=<?= $cari ?>&ruangan=<?= $ruangan_f ?>" class="page-link-pag <?= $halaman==$i ? 'active-pag' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateClock() { const now = new Date(); document.getElementById('live-clock').innerText = now.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'medium' }); }
        setInterval(updateClock, 1000); updateClock();
        function del(id, nm) { Swal.fire({ title: 'Hapus Properti?', text: `"${nm}" akan dihapus permanen!`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', confirmButtonText: 'Ya, Hapus' }).then((r) => { if(r.isConfirmed) window.location.href='action_properti.php?aksi=hard_delete&id='+id; }); }
    </script>
</body>
</html>