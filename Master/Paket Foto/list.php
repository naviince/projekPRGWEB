<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Ambil nama dari tabel Karyawan (sama dengan index.php)
$session_id_user = $_SESSION['id_user'];
$session_email   = $_SESSION['email'];

$sql_profile  = "SELECT Nama_Karyawan FROM Karyawan WHERE ID_User = ?";
$stmt_profile = sqlsrv_query($conn, $sql_profile, array($session_id_user));
$row_p        = sqlsrv_fetch_array($stmt_profile, SQLSRV_FETCH_ASSOC);
$nama_display = ($row_p) ? $row_p['Nama_Karyawan'] : "Administrator";

// Statistik paket terlaris
$top_paket = null;
$sql_top   = "SELECT TOP 1 p.Nama_Paket, p.Foto_Paket, COUNT(o.ID_Order) as total_booked 
              FROM Paket_Foto p 
              LEFT JOIN [Order] o ON p.ID_Paket = o.ID_Paket 
              GROUP BY p.Nama_Paket, p.Foto_Paket 
              ORDER BY total_booked DESC";
$res_top = sqlsrv_query($conn, $sql_top);
if ($res_top !== false) {
    $top_paket = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC);
}

// Daftar semua paket
$sql   = "SELECT * FROM Paket_Foto ORDER BY Harga_Paket ASC";
$query = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Paket Foto – SpotLight Studio</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* Sidebar (Scrollable Nav) */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.02); }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); letter-spacing: -0.5px; margin: 0 0 2px; }
        .sidebar-brand p { font-size: 11px; color: var(--gray-400); margin: 0; }
        
        /* Navigasi Scrollable */
        .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--pink-100); border-radius: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: var(--pink-200); }

        .nav-label { padding: 18px 20px 8px; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; display: block; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: all 0.3s ease; }
        .nav-item:hover { color: var(--pink-600); background: var(--pink-50); padding-left: 25px; }
        .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 4px solid var(--pink-600); font-weight: 700; }
        
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); background: #fafafa; }
        .user-info h4 { font-size: 13px; font-weight: 700; color: var(--gray-900); margin: 0 0 2px; }
        .user-info p { font-size: 11px; color: var(--gray-500); margin: 0 0 8px; }
        .btn-logout { width: 100%; padding: 10px; border-radius: 10px; border: none; background: var(--pink-600); color: white; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { background: var(--rose-dark); box-shadow: 0 4px 12px rgba(139, 26, 62, 0.2); }

        /* Main Content */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }

        .topbar {
            background: var(--white); border-bottom: 1px solid var(--pink-100);
            padding: 20px 32px; display: flex; justify-content: space-between; align-items: center;
        }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); margin: 0; }
        .topbar p  { font-size: 13px; color: var(--gray-500); margin: 4px 0 0; }

        .content { padding: 28px 32px; }

        /* ============================================================
           KOMPONEN KONTEN
           ============================================================ */
        .card-box {
            background: var(--white); border-radius: 16px;
            box-shadow: var(--shadow-sm); border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        /* Best seller banner */
        .best-seller-box {
            border-left: 6px solid var(--pink-600);
            padding: 24px 28px; margin-bottom: 24px;
        }

        .btn-pink {
            background: var(--pink-600);
            color: white; border-radius: 10px; font-weight: 700;
            padding: 10px 22px; border: none; transition: 0.3s;
            font-size: 13px; cursor: pointer;
            text-decoration: none;
        }
        .btn-pink:hover { background: var(--rose-dark); color: white; }

        .paket-img {
            width: 72px; height: 72px; object-fit: cover;
            border-radius: 14px; border: 2px solid var(--pink-100);
            transition: 0.3s;
        }
        tr:hover .paket-img { transform: scale(1.08) rotate(2deg); }

        .status-badge {
            border-radius: 8px; padding: 5px 14px;
            font-size: 10px; font-weight: 800; letter-spacing: 1px;
        }
        .bg-aktif    { background: #d1fae5; color: #065f46; }
        .bg-nonaktif { background: #f1f5f9; color: #64748b; }

        table thead th {
            background: #fafafa; color: var(--gray-400);
            font-size: 10px; text-transform: uppercase;
            font-weight: 800; padding: 18px 16px;
            letter-spacing: 1px; border: none;
        }
        table tbody td { padding: 16px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover { background: var(--pink-50); }

        .btn-action {
            border-radius: 8px; border: 1px solid #f1f5f9;
            background: white; transition: 0.25s; padding: 8px 11px;
            cursor: pointer;
        }
        .btn-action:hover { background: var(--pink-50); border-color: var(--pink-100); transform: translateY(-2px); }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>SpotLight Admin</h2>
            <p style="font-size: 11px; color: var(--gray-400);">Studio Management System</p>
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
            <a href="../Paket Foto/list.php" class="nav-item active">
                <i class="bi bi-camera"></i> Paket Foto
            </a>
            <a href="../Ruangan/list.php" class="nav-item">
                <i class="bi bi-door-open"></i> Ruangan
            </a>
            <a href="../Tema/list.php" class="nav-item">
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

    <!-- MAIN SYSTEM -->
    <main class="main">
        <div class="topbar">
            <div>
                <h1>Master Paket Foto</h1>
                <p>Kelola standar kualitas dan nilai jual layanan SpotLight Studio.</p>
            </div>
            <a href="add.php" class="btn-pink">
                <i class="bi bi-plus-circle-fill me-1"></i> Buat Paket Baru
            </a>
        </div>

        <div class="content">

            <!-- Best Seller -->
            <div class="card-box best-seller-box d-flex align-items-center gap-4">
                <div style="width:56px; height:56px; background:linear-gradient(135deg,#f59e0b,#d97706); border-radius:14px; display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem; flex-shrink:0;">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:800; color:var(--gray-400); text-transform:uppercase; letter-spacing:1px;">Layanan Paling Diminati</span>
                    <h3 style="font-size:20px; font-weight:800; color:var(--gray-900); margin:2px 0;">
                        <?= ($top_paket && $top_paket['total_booked'] > 0) ? htmlspecialchars($top_paket['Nama_Paket']) : 'Belum Ada Transaksi' ?>
                    </h3>
                    <span style="font-size:11px; color:var(--gray-500);">Total Reservasi: <b><?= $top_paket['total_booked'] ?? 0 ?></b> kali</span>
                </div>
            </div>

            <!-- Tabel -->
            <div class="card-box">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="padding-left:24px;">Preview</th>
                                <th>Katalog Layanan</th>
                                <th>Harga &amp; Durasi</th>
                                <th>Kapasitas</th>
                                <th>Status</th>
                                <th class="text-end" style="padding-right:24px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td style="padding-left:24px;">
                                    <?php
                                        $path_img = "../../assets/img/paket/" . $row['Foto_Paket'];
                                        $img_src  = (!empty($row['Foto_Paket']) && file_exists($path_img))
                                                    ? $path_img : "https://placehold.co/200x200?text=No+Image";
                                    ?>
                                    <img src="<?= $img_src ?>" class="paket-img" alt="">
                                </td>
                                <td>
                                    <div style="font-weight:700; font-size:15px; color:var(--gray-900);"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                    <small style="color:var(--gray-500); line-height:1.5; display:block; max-width:240px;"><?= htmlspecialchars($row['Deskripsi']) ?></small>
                                </td>
                                <td>
                                    <div style="font-weight:800; color:var(--pink-600); font-size:16px;">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.') ?></div>
                                    <small style="color:var(--gray-400); font-weight:600;"><i class="bi bi-stopwatch me-1"></i><?= $row['Durasi_Waktu'] ?> menit</small>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:var(--gray-700);">
                                        <i class="bi bi-people me-1" style="color:var(--pink-600);"></i><?= $row['Kapasitas_Orang'] ?> orang
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['Status'] == 'Aktif'): ?>
                                        <span class="status-badge bg-aktif">AKTIF</span>
                                    <?php else: ?>
                                        <span class="status-badge bg-nonaktif">NONAKTIF</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" style="padding-right:24px;">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="edit.php?id=<?= $row['ID_Paket'] ?>" class="btn-action" title="Edit">
                                            <i class="bi bi-pencil-square" style="color:#3b82f6;"></i>
                                        </a>
                                        <button onclick="toggleStatus(<?= $row['ID_Paket'] ?>, '<?= $row['Status'] ?>')" class="btn-action" title="Toggle Status">
                                            <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $row['ID_Paket'] ?>)" class="btn-action" title="Hapus">
                                            <i class="bi bi-trash3-fill text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:14px 24px; background:#fafafa; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between;">
                    <small style="color:var(--gray-400); font-weight:600;">SPOTLIGHT STUDIO</small>
                    <small style="color:var(--gray-400); font-weight:600;">Total: <?= sqlsrv_num_rows($query) ?> Paket</small>
                </div>
            </div>

        </div>
    </main>

<script>
function toggleStatus(id, current) {
    Swal.fire({
        title: (current === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan') + ' Paket?',
        text: "Paket nonaktif tidak muncul di pilihan booking.",
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#c73165', confirmButtonText: 'Ya, Ubah',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed)
            window.location.href = 'action_paket.php?type=soft&id=' + id + '&status=' + current;
    });
}
function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: "Data dan gambar akan dihapus selamanya!",
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed)
            window.location.href = 'action_paket.php?type=hard&id=' + id;
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>