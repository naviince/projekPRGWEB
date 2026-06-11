<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--pink-50); color: var(--gray-900); display: flex; min-height: 100vh; font-size: 14px; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.02); }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); letter-spacing: -0.5px; margin: 0; line-height: 1.2; }
        .sidebar-brand p { font-size: 11px; color: var(--gray-400); margin: 0; }
        .sidebar-nav { flex: 1; padding: 8px 0; overflow-y: auto; }
        .nav-label { padding: 14px 20px 4px !important; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; display: block; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 8px 20px !important; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: 0.3s; }
        .nav-item:hover { color: var(--pink-600); background: var(--pink-50); padding-left: 25px; }
        .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 4px solid var(--pink-600); font-weight: 700; }
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); background: #fafafa; }
        .user-info h4 { font-size: 13px; font-weight: 700; color: var(--gray-900); margin: 0 0 2px 0 !important; line-height: 1.2; }
        .user-info p { font-size: 11px; color: var(--gray-500); margin: 0 0 8px 0 !important; line-height: 1.2; }
        .btn-logout { width: 100%; padding: 8px !important; border-radius: 10px; border: none; background: var(--pink-600); color: white; font-weight: 700; cursor: pointer; }

        /* Main Area */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar { background: var(--white); border-bottom: 1px solid var(--pink-100); padding: 20px 32px; display: flex; justify-content: space-between; align-items: center; }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); margin: 0; }
        .topbar p  { font-size: 13px; color: var(--gray-500); margin: 4px 0 0; }
        .content { padding: 28px 32px; }

        /* Components */
        .card-box { background: var(--white); border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(232, 69, 122, 0.05); overflow: hidden; margin-bottom: 24px; }
        .best-seller-box { border-left: 6px solid var(--pink-600); padding: 24px 28px; }

        /* Search Toolbar */
        .card-toolbar { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px; }
        .toolbar-search { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 14px; padding: 0 14px; height: 42px; flex: 1; }
        .toolbar-search input { border: none; background: transparent; outline: none; font-size: 13px; width: 100%; color: var(--gray-900); }
        .toolbar-select { height: 42px; padding: 0 14px; border-radius: 14px; border: 1.5px solid #f1f5f9; background: #f8fafc; font-size: 13px; font-weight: 600; outline: none; width: 180px; color: var(--gray-700); cursor: pointer; }

        .btn-pink { background: var(--pink-600); color: white; border-radius: 10px; font-weight: 700; padding: 10px 22px; border: none; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-pink:hover { background: var(--rose-dark); color: white; }

        .paket-img { width: 64px; height: 64px; object-fit: cover; border-radius: 12px; border: 2px solid var(--pink-100); }
        .status-badge { border-radius: 8px; padding: 5px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        .bg-aktif { background: #d1fae5; color: #065f46; }
        .bg-nonaktif { background: #f1f5f9; color: #64748b; }

        table thead th { background: #fafafa; color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 800; padding: 18px 16px; border: none; }
        .btn-action { border-radius: 8px; border: 1px solid #f1f5f9; background: white; padding: 8px 11px; transition: 0.2s; }
        .btn-action:hover { background: var(--pink-50); border-color: var(--pink-100); }

        .card-footer-info { padding: 16px 24px; background: #fafafa; border-top: 1px solid #f1f5f9; text-align: right; }
        .card-footer-info span { font-size: 11px; font-weight: 800; color: var(--gray-400); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>SpotLight Admin</h2>
            <p>Studio Management System</p>
        </div>
        <nav class="sidebar-nav">
            <a href="../Admin/index.php" class="nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <span class="nav-label">Autentikasi & Akun</span>
            <a href="../User/list.php" class="nav-item"><i class="bi bi-person-lock"></i> Master User</a>
            <span class="nav-label">Master Data Profil</span>
            <a href="../Admin/list.php" class="nav-item"><i class="bi bi-shield-check"></i> Master Karyawan</a>
            <a href="../Pelanggan/list.php" class="nav-item"><i class="bi bi-people"></i> Pelanggan</a>
            <a href="list.php" class="nav-item active"><i class="bi bi-camera"></i> Paket Foto</a>
            <a href="../Ruangan/list.php" class="nav-item"><i class="bi bi-door-open"></i> Ruangan</a>
            <a href="../Tema/list.php" class="nav-item"><i class="bi bi-palette"></i> Tema Foto</a>
            <span class="nav-label">Operasional</span>
            <a href="#" class="nav-item"><i class="bi bi-calendar-event"></i> Booking Studio</a>
        </nav>
        <div class="sidebar-profile">
            <div class="user-info">
                <h4><?= htmlspecialchars($nama_display); ?></h4>
                <p><?= htmlspecialchars($session_email); ?></p>
            </div>
            <button class="btn-logout" onclick="location.href='../../logout.php'"><i class="bi bi-box-arrow-right me-1"></i> Logout</button>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1>Master Paket Foto</h1>
                <p>Kelola standar kualitas dan nilai jual layanan SpotLight Studio.</p>
            </div>
            <a href="add.php" class="btn-pink shadow-sm"><i class="bi bi-plus-circle-fill me-2"></i>Buat Paket Baru</a>
        </div>

        <div class="content">
            <!-- Best Seller -->
            <div class="card-box best-seller-box d-flex align-items-center gap-4">
                <div style="width:56px; height:56px; background:linear-gradient(135deg,#f59e0b,#d97706); border-radius:14px; display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem;"><i class="bi bi-award-fill"></i></div>
                <div>
                    <span style="font-size:10px; font-weight:800; color:var(--gray-400); text-transform:uppercase;">Layanan Terlaris</span>
                    <h3 style="font-size:20px; font-weight:800; margin:2px 0;"><?= ($top_paket && $top_paket['total_booked'] > 0) ? htmlspecialchars($top_paket['Nama_Paket']) : 'Belum Ada Transaksi' ?></h3>
                    <span style="font-size:11px; color:var(--gray-500);">Dipesan sebanyak <b><?= $top_paket['total_booked'] ?? 0 ?></b> kali</span>
                </div>
            </div>

            <!-- Tabel Card -->
            <div class="card-box">
                <!-- TOOLBAR PENCARIAN -->
                <div class="card-toolbar">
                    <div class="toolbar-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Cari Nama Paket, Harga, Kapasitas, atau Durasi...">
                    </div>
                    <select id="statusFilter" class="toolbar-select">
                        <option value="ALL">Semua Status</option>
                        <option value="AKTIF">Status: Aktif</option>
                        <option value="NONAKTIF">Status: Nonaktif</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="paketTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Preview</th>
                                <th>Katalog Layanan</th>
                                <th>Harga & Durasi</th>
                                <th>Kapasitas</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="paketTableBody">
                            <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                                $search_data = strtolower(htmlspecialchars($row['Nama_Paket'] . ' ' . $row['Harga_Paket'] . ' ' . $row['Kapasitas_Orang'] . ' orang ' . $row['Durasi_Waktu'] . ' menit'));
                                $status_upper = strtoupper($row['Status']);
                            ?>
                            <tr data-search="<?= $search_data ?>" data-status="<?= $status_upper ?>">
                                <td class="ps-4">
                                    <img src="../../assets/img/paket/<?= !empty($row['Foto_Paket']) ? $row['Foto_Paket'] : 'default.jpg' ?>" 
                                         class="paket-img shadow-sm" onerror="this.src='https://placehold.co/200x200?text=No+Image'">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark" style="font-size:15px;"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                    <small class="text-muted d-block text-truncate" style="max-width:250px;"><?= htmlspecialchars($row['Deskripsi']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-pink-600" style="font-size:16px;">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.') ?></div>
                                    <small class="text-muted fw-bold"><i class="bi bi-stopwatch me-1"></i><?= $row['Durasi_Waktu'] ?> menit</small>
                                </td>
                                <td><span class="fw-bold text-dark"><i class="bi bi-people me-1 text-pink-500"></i><?= $row['Kapasitas_Orang'] ?> orang</span></td>
                                <td>
                                    <span class="status-badge <?= $row['Status'] == 'Aktif' ? 'bg-aktif' : 'bg-nonaktif' ?>"><?= $status_upper ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="edit.php?id=<?= $row['ID_Paket'] ?>" class="btn-action" title="Edit"><i class="bi bi-pencil-square text-primary"></i></a>
                                        <button onclick="toggleStatus(<?= $row['ID_Paket'] ?>, '<?= $row['Status'] ?>')" class="btn-action" title="Toggle Status">
                                            <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $row['ID_Paket'] ?>)" class="btn-action" title="Hapus"><i class="bi bi-trash3-fill text-danger"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div id="noDataMessage" class="text-center p-5 d-none">
                        <i class="bi bi-camera-reels text-muted" style="font-size: 2.5rem;"></i>
                        <p class="text-muted mt-2 fw-bold">Paket foto tidak ditemukan.</p>
                    </div>
                </div>

                <div class="card-footer-info">
                    <span>Menampilkan <b id="footerVisible" style="color: #111827;">0</b> dari <b id="footerTotal" style="color: #111827;">0</b> paket foto</span>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput  = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const rows         = document.querySelectorAll('#paketTableBody tr');
        const footerVisible = document.getElementById('footerVisible');
        const footerTotal   = document.getElementById('footerTotal');
        const noData        = document.getElementById('noDataMessage');

        footerTotal.textContent = rows.length;

        function filter() {
            const kw = searchInput.value.toLowerCase().trim();
            const st = statusFilter.value;
            let visible = 0;

            rows.forEach(row => {
                const matchSearch = !kw || row.dataset.search.includes(kw);
                const matchStatus = st === 'ALL' || row.dataset.status === st;
                
                if (matchSearch && matchStatus) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
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
            title: (current === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan') + ' Paket?',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#e8457a', confirmButtonText: 'Ya, Ubah'
        }).then(r => { if (r.isConfirmed) window.location.href = 'action_paket.php?type=soft&id=' + id + '&status=' + current; });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Permanen?',
            text: "Data tidak bisa dikembalikan!",
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', confirmButtonText: 'Ya, Hapus'
        }).then(r => { if (r.isConfirmed) window.location.href = 'action_paket.php?type=hard&id=' + id; });
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>