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
// HELPER FUNCTIONS - Safe SQLSRV
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return [];
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
$admin_data = safe_sqlsrv_fetch($conn, "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil']   ?? 'default.jpg';
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// =====================================================
// FILTER & PAGINATION
// =====================================================
$search    = trim($_GET['search'] ?? '');
$f_ruangan = trim($_GET['ruangan'] ?? '');
$f_booking = $_GET['booking'] ?? ''; 
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 10;
$offset     = ($page - 1) * $per_page;

// =====================================================
// QUERY STATISTIK (Langsung simpan angkanya ke variabel)
// =====================================================
$res_total    = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) as n FROM Jadwal_Studio WHERE Is_Deleted = 0");
$total_jadwal = $res_total['n'] ?? 0;

$res_tersedia = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) as n FROM Jadwal_Studio WHERE Is_Deleted = 0 AND Status_Jadwal = 0 AND Status = 1");
$jml_tersedia = $res_tersedia['n'] ?? 0;

$res_booked   = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) as n FROM Jadwal_Studio WHERE Is_Deleted = 0 AND Status_Jadwal = 1");
$jml_booked   = $res_booked['n'] ?? 0;

// =====================================================
// QUERY DATA JADWAL (JOIN RUANGAN)
// =====================================================
$where_parts = ["j.Is_Deleted = 0"];
$params      = [];

if ($search !== '') {
    $where_parts[] = "(r.Nama_Ruangan LIKE ? OR j.Keterangan LIKE ?)";
    $like = "%{$search}%"; $params[] = $like; $params[] = $like;
}
if ($f_ruangan !== '') {
    $where_parts[] = "j.ID_Ruangan = ?"; $params[] = $f_ruangan;
}

$where_sql = implode(' AND ', $where_parts);

$count_row = safe_sqlsrv_fetch($conn, "SELECT COUNT(*) AS total FROM Jadwal_Studio j LEFT JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan WHERE {$where_sql}", $params);
$total_data = $count_row['total'] ?? 0;
$total_page = max(1, ceil($total_data / $per_page));

$sql_list = "SELECT j.*, r.Nama_Ruangan FROM Jadwal_Studio j LEFT JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan WHERE {$where_sql} ORDER BY j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params_final = array_merge($params, [$offset, $per_page]);
$daftar_jadwal = safe_sqlsrv_fetch_all($conn, $sql_list, $params_final);

$list_ruangan = safe_sqlsrv_fetch_all($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Is_Deleted = 0");

function getHariIndo($date) {
    if(!$date) return "-";
    $hari = $date->format('l');
    $daftar = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    return $daftar[$hari] ?? $hari;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Jadwal Studio – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {--sidebar-bg: #ffffff; --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        
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

        .main-content { margin-left: 260px; padding: 40px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; cursor: pointer; transition: var(--transition-3d); background: #fff; }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* STATS CARDS */
        .stat-card { background: #fff; border-radius: 18px; border: 1px solid rgba(255,228,233,.8); padding: 22px 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 16px rgba(213,61,102,.04); transition: var(--transition-3d); }
        .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .stat-icon.pink { background: var(--s-pink); color: var(--p-pink); }
        .stat-icon.green { background: #f0fdf4; color: #16a34a; }
        .stat-icon.blue { background: #eff6ff; color: #2563eb; }
        .stat-num { font-size: 1.6rem; font-weight: 800; }
        .stat-label { font-size: .78rem; color: var(--text-muted); font-weight: 600; }

        /* TOOLBAR & SEARCH */
        .toolbar-card { background: #fff; border-radius: 18px; border: 1px solid rgba(255,228,233,.8); padding: 20px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-input { border: 2px solid #e2e8f0; border-radius: 12px; padding: 10px 16px 10px 40px; font-size: .85rem; font-weight: 600; width: 100%; transition: .3s; }
        .search-input:focus { border-color: var(--p-pink); outline: none; box-shadow: 0 0 0 4px rgba(213,61,102,.08); }
        .btn-tambah { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 12px; padding: 11px 22px; font-weight: 800; font-size: .85rem; text-decoration: none; transition: var(--transition-3d); }
        .btn-tambah:hover { transform: translateY(-2px); color: #fff; box-shadow: 0 8px 20px rgba(213, 61, 102, 0.3); }

        /* TABLE */
        .card-table { background: white; border-radius: 20px; padding: 30px; border: 1px solid #f1f5f9; box-shadow: 0 8px 24px rgba(0,0,0,0.02); }
        .table thead th { background: transparent; border-bottom: 2px solid #f8fafc; color: #a0aec0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; padding: 15px; }
        .table tbody td { padding: 20px 15px; vertical-align: middle; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; font-weight: 600; }
        .badge-status { padding: 6px 14px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
        .badge-aktif { background: #f0fdf4; color: #16a34a; }
        .badge-booked { background: #fff7ed; color: #ea580c; }
        
        .action-btn { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; margin: 0 2px; border: none; }
        .btn-edit { background: #eff6ff; color: #2563eb; }
        .btn-delete { background: #fef2f2; color: #dc2626; }

        /* Update Gaya Tombol Filter Persis Gambar */
.btn-filter {
    border: 2px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 10px 20px;
    font-size: 0.9rem;
    font-weight: 700;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 45px; /* Sesuaikan dengan tinggi input search */
}

.btn-filter:hover {
    border-color: var(--p-pink);
    color: var(--p-pink);
    background: var(--s-pink);
}

.btn-filter i {
    font-size: 1.1rem;
    color: #475569;
}

.btn-filter:hover i {
    color: var(--p-pink);
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
    <div class="dashboard-header">
        <div><h3 class="fw-bold mb-1">Jadwal Studio</h3><p class="text-muted small mb-0">Kelola operasional dan ketersediaan slot studio.</p></div>
        <div class="profile-header-btn shadow-sm"><img src="<?= $foto_admin_src ?>" alt="Admin"></div>
    </div>

    <!-- CARDS STATISTIK (BAGIAN INI YANG TADI ERROR) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon pink"><i class="bi bi-calendar3"></i></div>
                <div>
                    <div class="stat-num"><?= $total_jadwal ?></div>
                    <div class="stat-label">Total Jadwal</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $jml_tersedia ?></div>
                    <div class="stat-label">Tersedia</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-bookmark-check-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $jml_booked ?></div>
                    <div class="stat-label">Terbooking</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar-card">
        <form method="GET" class="d-flex align-items-center gap-2 flex-grow-1">
            <div style="position:relative; flex:1;">
                <i class="bi bi-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#a0aec0;"></i>
                <input type="text" name="search" class="search-input" placeholder="Cari ruangan atau keterangan..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <!-- Ganti Select Ruangan dengan Button Filter ini -->
<input type="hidden" name="ruangan" id="hidden-ruangan" value="<?= htmlspecialchars($f_ruangan) ?>">

<!-- Cari tombol filter lama Anda dan ganti dengan ini -->
<button type="button" class="btn-filter" data-bs-toggle="modal" data-bs-target="#modalFilterJadwal">
    <i class="bi bi-sliders2"></i> Filter
</button>
            <button type="submit" class="btn btn-primary px-4" style="border-radius:12px; height:45px;">Cari</button>
            <?php if($search || $f_ruangan): ?><a href="list.php" class="btn btn-light border px-3" style="border-radius:12px; height:45px; display:flex; align-items:center;">Reset</a><?php endif; ?>
        </form>
        <div class="ms-3"><a href="add.php" class="btn-tambah"><i class="bi bi-plus-lg"></i> Tambah Jadwal</a></div>
    </div>

    <!-- DATA TABLE -->
    <div class="card-table">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>RUANGAN</th>
                        <th>TANGGAL</th>
                        <th>WAKTU (WIB)</th>
                        <th>KETERANGAN</th>
                        <th>STATUS</th>
                        <th class="text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($daftar_jadwal)): foreach($daftar_jadwal as $idx => $j): ?>
                    <tr>
                        <td><?= $offset + $idx + 1 ?></td>
                        <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($j['Nama_Ruangan']) ?></span></td>
                        <td><?= $j['Tanggal_Jadwal']->format('d M Y') ?></td>
                        <td><strong><?= $j['Jam_Mulai']->format('H:i') ?></strong> - <strong><?= $j['Jam_Selesai']->format('H:i') ?></strong></td>
                        <td class="text-muted small"><?= htmlspecialchars($j['Keterangan'] ?? '-') ?></td>
                        <td>
                            <span class="badge-status <?= $j['Status_Jadwal'] == 1 ? 'badge-booked' : 'badge-aktif' ?>">
                                <i class="bi bi-circle-fill me-2" style="font-size:0.5rem;"></i>
                                <?= $j['Status_Jadwal'] == 1 ? 'Terbooking' : 'Tersedia' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center">
                                <a href="edit.php?id=<?= $j['ID_Jadwal'] ?>" class="action-btn btn-edit"><i class="bi bi-pencil-fill"></i></a>
                                <button onclick="confirmDelete(<?= $j['ID_Jadwal'] ?>)" class="action-btn btn-delete"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center py-5">Belum ada data jadwal studio.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="small text-muted">Halaman <?= $page ?> dari <?= $total_page ?></div>
            <div class="d-flex gap-1">
                <a href="?page=<?= max(1, $page-1) ?>&search=<?= $search ?>&ruangan=<?= $f_ruangan ?>" class="btn btn-sm btn-light border <?= $page==1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
                <a href="?page=<?= min($total_page, $page+1) ?>&search=<?= $search ?>&ruangan=<?= $f_ruangan ?>" class="btn btn-sm btn-light border <?= $page==$total_page?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div>
</div>
<!-- MODAL FILTER JADWAL -->
<!-- MODAL FILTER JADWAL -->
<div class="modal fade" id="modalFilterJadwal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header border-0 pb-0">
                <h6 class="fw-bold mb-0">Filter Ruangan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-2 d-block">PILIH RUANGAN STUDIO</label>
                    <select id="modal-select-ruangan" class="form-select border-2" style="border-radius: 12px; padding: 10px;">
                        <option value="">Semua Ruangan</option>
                        <?php foreach($list_ruangan as $r): ?>
                            <option value="<?= $r['ID_Ruangan'] ?>" <?= $f_ruangan == $r['ID_Ruangan'] ? 'selected' : '' ?>><?= $r['Nama_Ruangan'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary w-100" style="border-radius: 12px; background: #D53D66; border:none;" onclick="applyFilter()">Terapkan Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- PASTIKAN FILE JS BOOTSTRAP TERPANGGIL DI SINI -->
<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi untuk memproses filter saat tombol di dalam modal ditekan
function applyFilter() {
    const val = document.getElementById('modal-select-ruangan').value;
    const inputHidden = document.getElementById('hidden-ruangan');
    
    if(inputHidden) {
        inputHidden.value = val;
        // Cari form terdekat dan submit
        document.querySelector('.toolbar-card form').submit();
    }
}

// Notifikasi Hapus (Swal)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('status_sukses') && urlParams.get('status_sukses') === 'hapus') {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data jadwal telah dipindahkan ke folder sampah.',
        confirmButtonColor: '#D53D66'
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

// Konfirmasi Logout
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Keluar Sistem?',
        text: 'Apakah Anda yakin ingin keluar?',
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

// Sidebar Submenu Toggle
document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const submenu = document.querySelector(targetId);
        const icon = this.querySelector('.icon-chevron');
        
        submenu.classList.toggle('show');
        if(icon) {
            icon.style.transform = submenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    });
});
</script>
</body>
</html>