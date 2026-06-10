<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_user_login = $_SESSION['id_user'];
$session_email = $_SESSION['email'];

$sql_profile = "SELECT Nama_Karyawan FROM Karyawan WHERE ID_User = ?";
$stmt_profile = sqlsrv_query($conn, $sql_profile, array($id_user_login));
$row_p = sqlsrv_fetch_array($stmt_profile, SQLSRV_FETCH_ASSOC);
$nama_display = ($row_p) ? $row_p['Nama_Karyawan'] : "Administrator";

$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status_User = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN Status_User = 'Inactive' THEN 1 ELSE 0 END) as inactive
              FROM Users WHERE Role_User = 'Admin'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);

$sql = "SELECT u.ID_User, u.Email_User, u.Status_User, k.Nama_Karyawan, k.No_Hp, k.Foto_Profil 
        FROM Users u 
        LEFT JOIN Karyawan k ON u.ID_User = k.ID_User 
        WHERE u.Role_User = 'Admin'
        ORDER BY CASE WHEN u.ID_User = $id_user_login THEN 0 ELSE 1 END, u.ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Karyawan – Studio SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink-50:   #fff0f5;
            --pink-100:  #ffe0ec;
            --pink-200:  #ffb3cc;
            --pink-500:  #e8457a;
            --pink-600:  #c73165;
            --rose-dark: #8b1a3e;
            --white:     #ffffff;
            --gray-400:  #9ca3af;
            --gray-500:  #6b7280;
            --gray-700:  #374151;
            --gray-900:  #111827;
            --sidebar-w: 240px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--pink-50);
            color: var(--gray-900);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.02); }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); letter-spacing: -0.5px; margin: 0 0 2px 0 !important; line-height: 1.2; }
        .sidebar-brand p { font-size: 11px; color: var(--gray-400); margin: 0 !important; line-height: 1.2; }
        .sidebar-nav { flex: 1; padding: 8px 0; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--pink-100); border-radius: 4px; }
        .nav-label { padding: 14px 20px 4px !important; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; display: block; margin: 0 !important; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 8px 20px !important; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: all 0.3s ease; margin: 0 !important; }
        .nav-item:hover { color: var(--pink-600); background: var(--pink-50); padding-left: 25px; }
        .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 4px solid var(--pink-600); font-weight: 700; }
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); background: #fafafa; }
        .user-info h4 { font-size: 13px; font-weight: 700; color: var(--gray-900); margin: 0 0 2px 0 !important; line-height: 1.2; }
        .user-info p { font-size: 11px; color: var(--gray-500); margin: 0 0 8px 0 !important; line-height: 1.2; }
        .btn-logout { width: 100%; padding: 8px !important; border-radius: 10px; border: none; background: var(--pink-600); color: white; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { background: var(--rose-dark); }

        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar { background: var(--white); border-bottom: 1px solid var(--pink-100); padding: 20px 32px; display: flex; justify-content: space-between; align-items: center; }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); margin: 0; }
        .topbar p { font-size: 13px; color: var(--gray-500); margin: 4px 0 0; }
        .content { padding: 28px 32px; }

        .stat-card { border-radius: 24px; background: white; box-shadow: 0 10px 30px rgba(232,69,122,0.05); transition: 0.4s cubic-bezier(0.165,0.84,0.44,1); border: 1px solid rgba(232,69,122,0.05); }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(232,69,122,0.12); }
        .icon-gradient { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: linear-gradient(135deg, var(--pink-500), var(--pink-600)); color: white; }

        .main-card { border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.06); background: white; overflow: hidden; }

        .card-toolbar { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
        .toolbar-search { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 14px; padding: 0 14px; height: 42px; flex: 1; }
        .toolbar-search i { color: var(--gray-400); font-size: 15px; flex-shrink: 0; }
        .toolbar-search input { border: none; background: transparent; outline: none; font-size: 13px; font-family: inherit; color: var(--gray-900); width: 100%; }
        .toolbar-search input::placeholder { color: var(--gray-400); }
        .toolbar-select { height: 42px; padding: 0 14px; border-radius: 14px; border: 1.5px solid #f1f5f9; background: #f8fafc; font-size: 13px; font-weight: 600; font-family: inherit; color: var(--gray-700); outline: none; cursor: pointer; flex-shrink: 0; width: 180px; }
        .result-badge { flex-shrink: 0; font-size: 11px; font-weight: 800; color: var(--gray-400); white-space: nowrap; background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 10px; padding: 6px 14px; }
        .result-badge span { color: var(--gray-900); }

        .btn-pink { background: var(--pink-500); color: white; border-radius: 14px; font-weight: 700; padding: 12px 28px; border: none; transition: 0.3s; text-decoration: none; }
        .btn-pink:hover { background: var(--pink-600); color: white; box-shadow: 0 10px 20px rgba(232,69,122,0.2); }

        .is-me-row { background-color: #fff9fb !important; border-left: 5px solid var(--pink-500); }
        .status-badge { border-radius: 12px; padding: 6px 16px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; display: inline-block; }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 800; padding: 20px; border-bottom: 1px solid #f1f5f9; }
        .btn-action { border-radius: 12px; border: 1.5px solid #f1f5f9; background: white; transition: 0.2s; padding: 8px 12px; }
        .btn-action:hover { background: var(--pink-50); border-color: var(--pink-500); }
        .avatar-circle { width: 48px; height: 48px; object-fit: cover; border-radius: 15px; border: 2px solid var(--pink-100); }
        #noDataMessage i { font-size: 2.5rem; }

        .card-footer-info { padding: 16px 24px; background: #fafafa; border-top: 1px solid #f1f5f9; text-align: center; }
        .card-footer-info span { font-size: 11px; font-weight: 800; color: var(--gray-400); }
        .card-footer-info span b { color: var(--gray-900); font-weight: 800; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <h2>SpotLight Admin</h2>
        <p>Studio Management System</p>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <span class="nav-label">Autentikasi &amp; Akun</span>
        <a href="../User/list.php" class="nav-item"><i class="bi bi-person-lock"></i> Master User</a>
        <span class="nav-label">Master Data Profil</span>
        <a href="list.php" class="nav-item active"><i class="bi bi-shield-check"></i> Master Karyawan</a>
        <a href="../Pelanggan/list.php" class="nav-item"><i class="bi bi-people"></i> Pelanggan</a>
        <a href="../Paket Foto/list.php" class="nav-item"><i class="bi bi-camera"></i> Paket Foto</a>
        <a href="../Ruangan/list.php" class="nav-item"><i class="bi bi-door-open"></i> Ruangan</a>
        <a href="../Tema/list.php" class="nav-item"><i class="bi bi-palette"></i> Tema Foto</a>
        <span class="nav-label">Operasional</span>
        <a href="#" class="nav-item"><i class="bi bi-calendar-event"></i> Booking Studio</a>
    </nav>
    <div class="sidebar-profile">
        <div class="user-info">
            <h4><?php echo htmlspecialchars($nama_display); ?></h4>
            <p><?php echo htmlspecialchars($session_email); ?></p>
        </div>
        <button class="btn-logout" onclick="location.href='../../logout.php'">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </button>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Master Karyawan</h1>
            <p>Kelola profil dan otoritas staf Studio SpotLight.</p>
        </div>
        <a href="add.php" class="btn btn-pink shadow-sm">
            <i class="bi bi-person-plus-fill me-2"></i>Tambah Karyawan
        </a>
    </div>

    <div class="content">
        <!-- Statistik -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center">
                    <div class="icon-gradient me-3 shadow-sm"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL KARYAWAN</div>
                        <div class="h3 fw-bold mb-0"><?= $stats['total'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center">
                    <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="bi bi-check-all"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">STATUS AKTIF</div>
                        <div class="h3 fw-bold mb-0 text-success"><?= $stats['active'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center">
                    <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #ef4444, #dc3545);"><i class="bi bi-x-circle"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">NON-AKTIF</div>
                        <div class="h3 fw-bold mb-0 text-danger"><?= $stats['inactive'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel -->
        <div class="main-card">

            <div class="card-toolbar">
                <div class="toolbar-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="Cari nama, email, atau no. HP...">
                </div>
                <select id="statusFilter" class="toolbar-select">
                    <option value="ALL">Semua Status</option>
                    <option value="ACTIVE">Aktif</option>
                    <option value="INACTIVE">Non-Aktif</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:35%">Profil Karyawan</th>
                            <th style="width:20%">WhatsApp</th>
                            <th style="width:20%">Status</th>
                            <th style="width:25%" class="text-end">Aksi Manajemen</th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                            $is_me = ($row['ID_User'] == $id_user_login);
                            $has_profile = !empty($row['Nama_Karyawan']);
                            $status_upper = strtoupper($row['Status_User']);
                        ?>
                        <tr class="<?= $is_me ? 'is-me-row' : '' ?>"
                            data-search="<?= strtolower(htmlspecialchars(($row['Nama_Karyawan'] ?? '') . ' ' . $row['Email_User'] . ' ' . ($row['No_Hp'] ?? ''))) ?>"
                            data-status="<?= $status_upper ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center py-2">
                                    <img src="../../assets/img/<?= $row['Foto_Profil'] ?? 'default.jpg' ?>"
                                         class="avatar-circle shadow-sm me-3"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['Nama_Karyawan'] ?? 'User') ?>&background=ffe0ec&color=e8457a&bold=true'">
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <?= $has_profile ? htmlspecialchars($row['Nama_Karyawan']) : '<span class="text-muted fst-italic">Profil belum lengkap</span>' ?>
                                            <?php if($is_me): ?><span class="badge bg-dark ms-1" style="font-size:8px;">YOU</span><?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><?= htmlspecialchars($row['Email_User']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="small fw-bold text-dark"><?= htmlspecialchars($row['No_Hp'] ?? '-') ?></span></td>
                            <td>
                                <span class="status-badge <?= $row['Status_User'] == 'Active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                    <?= $status_upper ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if(!$is_me): ?>
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-action btn-sm" title="Edit Profil">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-action btn-sm" title="Ubah Status">
                                            <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-action btn-sm" title="Hapus Permanen">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-muted" style="font-size:10px">
                                        <i class="bi bi-person-gear me-1"></i>KELOLA PROFIL SAYA
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div id="noDataMessage" class="text-center p-5 d-none">
                    <i class="bi bi-person-x text-muted"></i>
                    <p class="text-muted mt-2 fw-bold">Tidak ada karyawan ditemukan.</p>
                </div>
            </div>

            <!-- Footer card -->
            <div class="card-footer-info" style="text-align: right;">
                <span>Menampilkan <b id="footerVisible">0</b> dari <b id="footerTotal">0</b> karyawan</span>
            </div>

        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput  = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const rows         = document.querySelectorAll('#employeeTableBody tr');
    const footerVisible = document.getElementById('footerVisible');
    const footerTotal   = document.getElementById('footerTotal');
    const noData       = document.getElementById('noDataMessage');

    const total = rows.length;
    footerTotal.textContent  = total;
    footerVisible.textContent = total;

    function filter() {
        const kw = searchInput.value.toLowerCase().trim();
        const st = statusFilter.value;
        let visible = 0;

        rows.forEach(row => {
            const matchSearch = !kw || row.dataset.search.includes(kw);
            const matchStatus = st === 'ALL' || row.dataset.status === st;
            const show = matchSearch && matchStatus;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        footerVisible.textContent = visible;
        noData.classList.toggle('d-none', visible > 0);
    }

    searchInput.addEventListener('input', filter);
    statusFilter.addEventListener('change', filter);
    filter();
});

function toggleStatus(id, current) {
    Swal.fire({
        title: (current === 'Active' ? 'Nonaktifkan' : 'Aktifkan') + ' Karyawan?',
        text: "Status login karyawan akan berubah.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Lakukan'
    }).then(r => { if (r.isConfirmed) location.href = 'action_karyawan.php?type=soft&id=' + id + '&status=' + current; });
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Karyawan Permanen?',
        text: "Data akun dan profil akan dihapus selamanya!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus'
    }).then(r => { if (r.isConfirmed) location.href = 'action_karyawan.php?type=hard&id=' + id; });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>