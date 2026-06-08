<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// AMBIL DATA SESSION UNTUK SIDEBAR
$session_id_user = $_SESSION['id_user'];
$session_email   = $_SESSION['email'];

// AMBIL NAMA LENGKAP DARI TABEL KARYAWAN
$sql_profile = "SELECT Nama_Karyawan FROM Karyawan WHERE ID_User = ?";
$stmt_profile = sqlsrv_query($conn, $sql_profile, array($session_id_user));
$row_p = sqlsrv_fetch_array($stmt_profile, SQLSRV_FETCH_ASSOC);

$nama_display = ($row_p) ? $row_p['Nama_Karyawan'] : "Administrator";

// 1. STATISTIK: Tema Paling Sering Dipilih (Diselaraskan menggunakan [Order])
$top_tema = null;
$sql_top  = "SELECT TOP 1 t.Nama_Tema, COUNT(o.ID_Order) as total_booking
             FROM Tema_Foto t
             LEFT JOIN [Order] o ON t.ID_Tema = o.ID_Tema
             GROUP BY t.Nama_Tema
             ORDER BY total_booking DESC";
$res_top = sqlsrv_query($conn, $sql_top);
if ($res_top !== false) {
    $top_tema = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC);
}

// 2. STATISTIK: Jumlah Aktif & Nonaktif
$sql_count = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN Status='Aktif' THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN Status='Nonaktif' THEN 1 ELSE 0 END) as nonaktif
              FROM Tema_Foto";
$res_count  = sqlsrv_query($conn, $sql_count);
$count_data = ($res_count !== false)
                ? sqlsrv_fetch_array($res_count, SQLSRV_FETCH_ASSOC)
                : ['total'=>0,'aktif'=>0,'nonaktif'=>0];

// 3. AMBIL SEMUA TEMA
$sql   = "SELECT * FROM Tema_Foto ORDER BY ID_Tema ASC";
$query = sqlsrv_query($conn, $sql);
if ($query === false) {
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tema Foto – SpotLight Studio</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --pink-50:   #fff0f5;
            --pink-100:  #ffe0ec;
            --pink-200:  #ffb3cc;
            --pink-400:  #f472a0;
            --pink-500:  #e8457a;
            --pink-600:  #c73165;
            --pink-700:  #a01f4e;
            --rose-dark: #8b1a3e;
            --purple:    #6b21a8;
            --white:     #ffffff;
            --gray-400:  #9ca3af;
            --gray-500:  #6b7280;
            --gray-700:  #374151;
            --gray-900:  #111827;
            --sidebar-w: 240px;
            --radius: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,.04);
            --shadow-md: 0 10px 25px -5px rgba(232, 69, 122, 0.1), 0 8px 10px -6px rgba(232, 69, 122, 0.1);
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

        /* ============================================================
           KONSISTENSI SIDEBAR CSS (Bebas Override Bootstrap)
           ============================================================ */
        .sidebar, .sidebar * { box-sizing: border-box !important; }
        .sidebar p, .sidebar h2, .sidebar h4, .sidebar a, .sidebar button {
            margin: 0 !important;
            padding: 0;
            line-height: 1.2 !important;
        }
        .sidebar { 
            width: var(--sidebar-w); 
            height: 100vh; 
            background: var(--white); 
            border-right: 1px solid var(--pink-100); 
            display: flex; 
            flex-direction: column; 
            position: fixed; 
            top: 0; 
            left: 0; 
            z-index: 100; 
            box-shadow: 4px 0 15px rgba(0,0,0,0.02); 
        }
        .sidebar-brand { 
            padding: 24px 20px; 
            border-bottom: 1px solid var(--pink-100); 
        }
        .sidebar-brand h2 { 
            font-size: 18px !important; 
            font-weight: 800 !important; 
            color: var(--pink-600) !important; 
            letter-spacing: -0.5px !important; 
            margin-bottom: 2px !important;
            display: block !important;
        }
        .sidebar-brand p { 
            font-size: 11px !important; 
            color: var(--gray-400) !important; 
            display: block !important;
        }
        .sidebar-nav { 
            flex: 1; 
            padding: 12px 0 !important; 
            overflow-y: auto; 
        }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--pink-100); border-radius: 4px; }

        .nav-label { 
            padding: 18px 20px 8px !important; 
            font-size: 10px !important; 
            font-weight: 800 !important; 
            color: var(--gray-400) !important; 
            text-transform: uppercase !important; 
            letter-spacing: 1px !important; 
            display: block !important;
        }
        .nav-item { 
            display: flex !important; 
            align-items: center !important; 
            gap: 10px !important; 
            padding: 10px 20px !important; 
            cursor: pointer !important; 
            color: var(--gray-500) !important; 
            text-decoration: none !important; 
            font-weight: 500 !important; 
            transition: all 0.3s ease !important; 
        }
        .nav-item:hover { 
            color: var(--pink-600) !important; 
            background: var(--pink-50) !important; 
            padding-left: 25px !important; 
        }
        .nav-item.active { 
            color: var(--pink-600) !important; 
            background: var(--pink-50) !important; 
            border-left: 4px solid var(--pink-600) !important; 
            font-weight: 700 !important; 
        }
        .sidebar-profile { 
            padding: 16px 20px !important; 
            border-top: 1px solid var(--pink-100) !important; 
            background: #fafafa !important; 
        }
        .user-info h4 { 
            font-size: 13px !important; 
            font-weight: 700 !important; 
            color: var(--gray-900) !important; 
            margin-bottom: 2px !important;
            display: block !important;
        }
        .user-info p { 
            font-size: 11px !important; 
            color: var(--gray-505) !important; 
            margin-bottom: 8px !important;
            display: block !important;
        }
        .btn-logout { 
            width: 100% !important; 
            padding: 10px !important; 
            border-radius: 10px !important; 
            border: none !important; 
            background: var(--pink-600) !important; 
            color: white !important; 
            font-weight: 700 !important; 
            cursor: pointer !important; 
            transition: 0.3s !important; 
            display: block !important;
        }
        .btn-logout:hover { 
            background: var(--rose-dark) !important; 
        }

        /* ---- Main Content ---- */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar {
            background: var(--white); border-bottom: 1px solid var(--pink-100);
            padding: 20px 32px; display: flex; justify-content: space-between; align-items: center;
        }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); margin: 0; }
        .topbar p  { font-size: 13px; color: var(--gray-500); margin: 4px 0 0; }

        .content { padding: 28px 32px; }

        /* ---- Stat Cards ---- */
        .stats-row { display: flex; gap: 18px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat-card {
            flex: 1; min-width: 180px;
            border: none; border-radius: 20px;
            background: white; padding: 22px 24px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 18px;
        }
        .stat-card.highlight    { border-left: 5px solid var(--purple); }
        .stat-card.stat-aktif   { border-left: 5px solid #10b981; }
        .stat-card.stat-nonaktif{ border-left: 5px solid #f59e0b; }

        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.purple { background: #f3e8ff; }
        .stat-icon.green  { background: #d1fae5; }
        .stat-icon.amber  { background: #fef3c7; }

        .stat-content .label {
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: #94a3b8; margin-bottom: 4px;
        }
        .stat-content .value { font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1; }
        .stat-content .sub   { font-size: 11px; color: #94a3b8; margin-top: 4px; }

        /* ---- Main Card ---- */
        .main-card {
            border: none; border-radius: 25px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.06);
            background: white; overflow: hidden;
        }
        .card-toolbar {
            padding: 22px 28px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .card-toolbar h5 { font-weight: 800; color: #1e293b; margin: 0; font-size: 16px; }
        .count-badge {
            background: #f3e8ff; color: var(--purple);
            font-size: 11px; font-weight: 800;
            padding: 4px 12px; border-radius: 20px; margin-left: 10px;
        }

        .btn-pink {
            background: linear-gradient(135deg, var(--purple), var(--pink-500));
            color: white; border-radius: 12px;
            font-weight: 700; padding: 10px 22px;
            border: none; font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-pink:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(107,33,168,0.3);
            color: white;
        }

        /* ---- Table ---- */
        .table { margin: 0; }
        .table thead th {
            background: #f8fafc; color: #94a3b8;
            font-size: 11px; text-transform: uppercase;
            font-weight: 700; padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9; letter-spacing: 0.5px;
        }
        .table tbody td {
            padding: 18px 20px; vertical-align: middle;
            border-bottom: 1px solid #f8fafc;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover td { background: #fdf2f7; }

        .tema-img {
            width: 90px; height: 68px; object-fit: cover;
            border-radius: 14px; border: 2px solid #e9d5ff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.07);
        }
        .tema-name { font-weight: 800; color: #1e293b; font-size: 14px; }
        .tema-desc {
            font-size: 12px; color: #94a3b8;
            max-width: 200px; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }

        .konsep-badge {
            background: #f3e8ff; color: var(--purple);
            font-size: 10px; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            display: inline-block; margin-bottom: 4px;
        }
        .suasana-text { font-size: 11px; color: #64748b; font-style: italic; }

        .properti-tags { display: flex; flex-wrap: wrap; gap: 4px; max-width: 230px; }
        .properti-tag {
            background: #f1f5f9; color: #64748b;
            font-size: 10px; font-weight: 700;
            padding: 3px 9px; border-radius: 20px;
        }

        .status-badge {
            border-radius: 10px; padding: 6px 14px;
            font-size: 10px; font-weight: 800;
            letter-spacing: 0.5px; text-transform: uppercase;
        }
        .bg-aktif    { background: #d1fae5; color: #065f46; }
        .bg-nonaktif { background: #fee2e2; color: #991b1b; }

        .btn-action {
            border-radius: 10px; border: 1px solid #f1f5f9;
            background: white; transition: background 0.2s, border-color 0.2s;
            padding: 8px 12px;
        }
        .btn-action:hover { background: #f8fafc; border-color: var(--pink-500); }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state .icon { font-size: 56px; margin-bottom: 16px; }
        .empty-state h5 { font-weight: 800; color: #475569; }
        .empty-state p  { font-size: 13px; }

        .card-footer-custom {
            padding: 16px 24px;
            background: #f8fafc; border-top: 1px solid #f1f5f9;
            text-align: center;
        }
        .card-footer-custom small { font-size: 11px; color: #94a3b8; font-weight: 700; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>SpotLight Admin</h2>
            <p>Studio Management System</p>
        </div>

        <nav class="sidebar-nav">
            <a href="../Admin/index.php" class="nav-item">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <span class="nav-label">Autentikasi &amp; Akun</span>
            <a href="../User/list.php" class="nav-item">
                <i class="bi bi-person-lock"></i> Master User
            </a>

            <span class="nav-label">Master Data Profil</span>
            <a href="../Admin/list.php" class="nav-item">
                <i class="bi bi-shield-check"></i> Master Karyawan
            </a>
            <a href="../Pelanggan/list.php" class="nav-item">
                <i class="bi bi-people"></i> Pelanggan
            </a>
            <a href="../Paket Foto/list.php" class="nav-item">
                <i class="bi bi-camera"></i> Paket Foto
            </a>
            <a href="../Ruangan/list.php" class="nav-item">
                <i class="bi bi-door-open"></i> Ruangan
            </a>
            <a href="../Tema_Foto/list.php" class="nav-item active">
                <i class="bi bi-palette"></i> Tema Foto
            </a>

            <span class="nav-label">Operasional</span>
            <a href="#" class="nav-item">
                <i class="bi bi-calendar-event"></i> Booking Studio
            </a>
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

    <!-- MAIN CONTENT -->
    <main class="main">
        <div class="topbar">
            <div>
                <h2>Master Tema Foto</h2>
                <p>Kelola tema dan konsep fotografi yang dapat dipilih pelanggan saat booking.</p>
            </div>
            <a href="add.php" class="btn btn-pink shadow-sm">
                <i class="bi bi-plus-lg"></i> Tambah Tema
            </a>
        </div>

        <div class="content">
            <!-- Statistik -->
            <div class="stats-row">
                <div class="stat-card highlight">
                    <div class="stat-icon purple">🎨</div>
                    <div class="stat-content">
                        <div class="label">Tema Terpopuler</div>
                        <div class="value" style="font-size:17px; line-height:1.3;">
                            <?= ($top_tema && $top_tema['total_booking'] > 0) ? htmlspecialchars($top_tema['Nama_Tema']) : 'Belum Ada Data' ?>
                        </div>
                        <div class="sub"><?= $top_tema['total_booking'] ?? 0 ?> kali dipilih pelanggan</div>
                    </div>
                </div>

                <div class="stat-card stat-aktif">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-content">
                        <div class="label">Tema Aktif</div>
                        <div class="value"><?= (int)($count_data['aktif'] ?? 0) ?></div>
                        <div class="sub">Tersedia untuk dipilih</div>
                    </div>
                </div>

                <div class="stat-card stat-nonaktif">
                    <div class="stat-icon amber">⏸️</div>
                    <div class="stat-content">
                        <div class="label">Dinonaktifkan</div>
                        <div class="value"><?= (int)($count_data['nonaktif'] ?? 0) ?></div>
                        <div class="sub">Tidak tampil ke pelanggan</div>
                    </div>
                </div>
            </div>

            <!-- Tabel -->
            <div class="main-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="ps-4">Foto Referensi</th>
                                <th>Nama &amp; Deskripsi</th>
                                <th>Konsep &amp; Mood</th>
                                <th>Properti Pendukung</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no_data = true;
                        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                            $no_data  = false;
                            $path_img = "../../assets/img/tema/" . $row['Foto_Tema'];
                            $img_src  = (!empty($row['Foto_Tema']) && file_exists($path_img))
                                            ? $path_img
                                            : "https://placehold.co/400x300?text=No+Image";

                            $prop_arr = !empty($row['Properti_Pendukung'])
                                            ? array_slice(array_map('trim', explode(',', $row['Properti_Pendukung'])), 0, 4)
                                            : [];
                        ?>
                        <tr>
                            <!-- Foto -->
                            <td class="ps-4">
                                <img src="<?= $img_src ?>" class="tema-img" alt="<?= htmlspecialchars($row['Nama_Tema']) ?>">
                            </td>

                            <!-- Nama & Deskripsi -->
                            <td>
                                <div class="tema-name"><?= htmlspecialchars($row['Nama_Tema']) ?></div>
                                <div class="tema-desc"><?= htmlspecialchars($row['Deskripsi']) ?></div>
                            </td>

                            <!-- Konsep & Mood -->
                            <td>
                                <?php if (!empty($row['Konsep'])): ?>
                                    <span class="konsep-badge"><?= htmlspecialchars($row['Konsep']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($row['Suasana'])): ?>
                                    <div class="suasana-text">✨ <?= htmlspecialchars($row['Suasana']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Properti -->
                            <td>
                                <?php if (!empty($prop_arr)): ?>
                                <div class="properti-tags">
                                    <?php foreach ($prop_arr as $p): ?>
                                        <span class="properti-tag"><?= htmlspecialchars($p) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">–</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="status-badge <?= $row['Status'] == 'Aktif' ? 'bg-aktif' : 'bg-nonaktif' ?>">
                                    <?= $row['Status'] ?>
                                </span>
                            </td>

                            <!-- Aksi -->
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                    <a href="edit.php?id=<?= $row['ID_Tema'] ?>" class="btn btn-sm btn-action px-3" title="Edit">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </a>
                                    <button onclick="toggleStatus(<?= $row['ID_Tema'] ?>, '<?= $row['Status'] ?>')"
                                            class="btn btn-sm btn-action px-3" title="Aktifkan/Nonaktifkan">
                                        <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $row['ID_Tema'] ?>)"
                                            class="btn btn-sm btn-action px-3" title="Hapus Permanen">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if ($no_data): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="icon">🎭</div>
                                    <h5>Belum Ada Tema Foto</h5>
                                    <p>Tambahkan tema pertama agar pelanggan dapat memilih konsep sesi foto saat booking.</p>
                                    <a href="add.php" class="btn btn-pink mt-2">
                                        <i class="bi bi-plus-lg"></i> Tambah Tema Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer-custom">
                    <small>SpotLight Studio – Theme Management v1.0</small>
                </div>
            </div>
        </div>
    </main>

<script>
<?php if ($msg == 'status_updated'): ?>
Swal.fire({ icon:'success', title:'Status Diperbarui!', text:'Status tema berhasil diubah.', timer:1800, showConfirmButton:false });
<?php elseif ($msg == 'deleted'): ?>
Swal.fire({ icon:'success', title:'Dihapus!', text:'Tema berhasil dihapus dari sistem.', timer:1800, showConfirmButton:false });
<?php endif; ?>

function toggleStatus(id, current) {
    let action = current === 'Aktif' ? 'Menonaktifkan' : 'Mengaktifkan';
    Swal.fire({
        title: action + ' Tema?',
        text: 'Status tema akan berubah dan mempengaruhi pilihan pelanggan saat booking.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6b21a8',
        cancelButtonText: 'Batal',
        confirmButtonText: 'Ya, Lakukan!'
    }).then(result => {
        if (result.isConfirmed)
            window.location.href = 'action_tema.php?type=soft&id=' + id + '&status=' + current;
    });
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        html: 'Tema dan foto referensi akan <b>dihapus selamanya</b>.<br>Pastikan tema belum memiliki riwayat booking.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Batal',
        confirmButtonText: 'Ya, Hapus!'
    }).then(result => {
        if (result.isConfirmed)
            window.location.href = 'action_tema.php?type=hard&id=' + id;
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>