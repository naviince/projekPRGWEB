<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// Ambil Profil Admin
$admin_data = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]), SQLSRV_FETCH_ASSOC);
$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin_src = ($admin_data['Foto_Profil'] && file_exists("../../assets/img/karyawan/".$admin_data['Foto_Profil'])) ? "../../assets/img/karyawan/".$admin_data['Foto_Profil'] : "../../assets/img/default.jpg";

// Pagination & Filter
$limit = 5;
$halaman = isset($_GET['halaman']) ? (int)$GET['halaman'] : 1;
$offset = ($halaman - 1) * $limit;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";

$params = [];
$where = "WHERE Is_Deleted = 0";
if($cari) {
    $where .= " AND Nama_Tema LIKE ?";
    $params[] = "%$cari%";
}

// Total Data untuk Pagination
$sql_count = "SELECT COUNT(*) as total FROM Tema_Foto $where";
$stmt_count = sqlsrv_query($conn, $sql_count, $params);
$total_data = sqlsrv_fetch_array($stmt_count)['total'] ?? 0;
$total_halaman = ceil($total_data / $limit);

// Ambil Data Tema
$sql_list = "SELECT * FROM Tema_Foto $where ORDER BY ID_Tema DESC OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
$stmt_list = sqlsrv_query($conn, $sql_list, $params);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tema Foto – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --text-dark: #1e1e24; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); }
        
        /* SIDEBAR STYLE (Identik dengan Ruangan) */
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; }
        .submenu.show { display: block; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; }
        .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); }
        
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff; }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
        
        /* THEME CARD STYLE */
        .theme-card { background: #fff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); transition: var(--transition-3d); overflow: hidden; margin-bottom: 20px; }
        .theme-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.1); }
        .theme-img { width: 140px; height: 140px; object-fit: cover; border-radius: 18px; }
        .badge-ruangan { background: var(--s-pink); color: var(--p-pink); font-size: 0.7rem; padding: 5px 12px; border-radius: 50px; font-weight: 700; border: 1px solid var(--light-pink); }
        
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <a href="#" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
        <ul class="nav-menu list-unstyled">
            <li class="nav-item mb-2"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
            <li class="nav-item mb-2">
                <a href="#" class="nav-link-custom active btn-toggle-submenu" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-up"></i></a>
                <div class="submenu show" id="submenuMaster">
                    <a href="../Ruangan/list.php" class="submenu-link">Ruangan</a>
                    <a href="../Properti/list.php" class="submenu-link">Properti</a>
                    <a href="./list.php" class="submenu-link active">Tema Foto</a>
                    <a href="../Paket Foto/list.php" class="submenu-link">Paket Foto</a>
                </div>
            </li>
        </ul>
        <div class="mt-auto"><button onclick="confirmLogout()" class="btn btn-logout">Keluar Sistem</button></div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="dashboard-header">
            <div><h3 class="fw-bold mb-1">Master Tema Foto</h3><p class="text-muted small">Kelola konsep tema yang terhubung dengan ruangan studio.</p></div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock"></span></span>
                <div class="profile-header-btn shadow-sm"><img src="<?= $foto_admin_src ?>" alt="Profile"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <form method="GET" class="d-flex gap-2 flex-grow-1 me-4">
                <div class="position-relative flex-grow-1">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" name="cari" class="form-control ps-5" style="border-radius:14px; padding:12px;" placeholder="Cari nama tema..." value="<?= htmlspecialchars($cari) ?>">
                </div>
                <button type="submit" class="btn btn-white border shadow-sm" style="border-radius:14px; padding:0 20px;"><i class="bi bi-funnel"></i></button>
            </form>
            <a href="add.php" class="btn btn-danger" style="background:var(--p-pink); border-radius:14px; padding:12px 25px; font-weight:800; border:none;"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Tema</a>
        </div>

        <div class="row">
            <?php while($row = sqlsrv_fetch_array($stmt_list, SQLSRV_FETCH_ASSOC)): 
                $id_t = $row['ID_Tema'];
                $sql_r = "SELECT r.Nama_Ruangan FROM Ruangan_Tema rt JOIN Ruangan r ON rt.ID_Ruangan = r.ID_Ruangan WHERE rt.ID_Tema = ?";
                $stmt_r = sqlsrv_query($conn, $sql_r, [$id_t]);
            ?>
            <div class="col-12">
                <div class="theme-card p-3">
                    <div class="d-flex align-items-center gap-4">
                        <img src="../../assets/img/tema/<?= $row['Foto_Tema'] ?>" class="theme-img shadow-sm">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($row['Nama_Tema']) ?></h5>
                                    <p class="small text-muted mb-3"><?= htmlspecialchars($row['Deskripsi']) ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php while($ruang = sqlsrv_fetch_array($stmt_r, SQLSRV_FETCH_ASSOC)): ?>
                                            <span class="badge-ruangan"><i class="bi bi-door-open me-1"></i><?= $ruang['Nama_Ruangan'] ?></span>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= $row['Status'] == 1 ? 'bg-success' : 'bg-secondary' ?> mb-3 d-block" style="font-size:0.65rem; border-radius:50px;">
                                        <?= $row['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                    <div class="d-flex gap-2">
                                        <a href="edit.php?id=<?= $id_t ?>" class="btn btn-sm btn-light text-primary border" style="border-radius:10px;"><i class="bi bi-pencil-square"></i></a>
                                        <button onclick="hapusTema(<?= $id_t ?>)" class="btn btn-sm btn-light text-danger border" style="border-radius:10px;"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <nav class="mt-4"><ul class="pagination justify-content-center">
            <?php for($i=1; $i<=$total_halaman; $i++): ?>
                <li class="page-item <?= ($i == $halaman) ? 'active' : '' ?>"><a class="page-link border-0 shadow-sm mx-1 px-3" style="border-radius:10px;" href="?halaman=<?= $i ?>&cari=<?= $cari ?>"><?= $i ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    </div>

    <script>
        function updateLiveClock() {
            const now = new Date();
            document.getElementById('live-clock').innerText = now.toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) + " WIB";
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();

        function hapusTema(id) {
            Swal.fire({ title: 'Hapus Tema?', text: "Tema ini tidak akan muncul lagi di sistem.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', confirmButtonText: 'Ya, Hapus' }).then((r) => { if(r.isConfirmed) window.location.href='action_tema.php?aksi=hard_delete&id='+id; });
        }
    </script>
</body>
</html>