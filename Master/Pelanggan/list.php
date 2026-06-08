<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman: Hanya Admin yang boleh akses
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Ambil data session untuk sidebar
$session_id_user = $_SESSION['id_user'];
$session_email   = $_SESSION['email'];

// AMBIL NAMA LENGKAP DARI TABEL KARYAWAN
$sql_profile = "SELECT Nama_Karyawan FROM Karyawan WHERE ID_User = ?";
$stmt_profile = sqlsrv_query($conn, $sql_profile, array($session_id_user));
$row_p = sqlsrv_fetch_array($stmt_profile, SQLSRV_FETCH_ASSOC);

$nama_display = ($row_p) ? $row_p['Nama_Karyawan'] : "Administrator";

// 1. STATISTIK (Akurat & Efisien dalam satu query)
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status_User = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN Status_User = 'Inactive' THEN 1 ELSE 0 END) as inactive
              FROM Users WHERE Role_User = 'Customer'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);

// 2. QUERY LIST (Join Pelanggan & Users)
$sql = "SELECT p.*, u.Email_User, u.Status_User 
        FROM Pelanggan p 
        JOIN Users u ON p.ID_User = u.ID_User 
        WHERE u.Role_User = 'Customer'
        ORDER BY p.ID_Pelanggan DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Pelanggan – Studio SpotLight</title>
    
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
            --white:     #ffffff;
            --gray-400:  #9ca3af;
            --gray-500:  #6b7280;
            --gray-700:  #374151;
            --gray-900:  #111827;
            --sidebar-w: 240px;
            --radius: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,.04);
            --shadow-md: 0 10px 25px -5px rgba(232,69,122,0.1);
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
           KONSISTENSI SIDEBAR CSS (Sama di semua halaman)
           ============================================================ */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.02); }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); letter-spacing: -0.5px; margin: 0 0 2px 0 !important; line-height: 1.2; }
        .sidebar-brand p { font-size: 11px; color: var(--gray-400); margin: 0 !important; line-height: 1.2; }
        
        .sidebar-nav { flex: 1; padding: 8px 0; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--pink-100); border-radius: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb:hover { background: var(--pink-200); }

        .nav-label { padding: 14px 20px 4px !important; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; display: block; margin: 0 !important; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 8px 20px !important; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: all 0.3s ease; margin: 0 !important; }
        .nav-item:hover { color: var(--pink-600); background: var(--pink-50); padding-left: 25px; }
        .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 4px solid var(--pink-600); font-weight: 700; }
        
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); background: #fafafa; }
        .user-info h4 { font-size: 13px; font-weight: 700; color: var(--gray-900); margin: 0 0 2px 0 !important; line-height: 1.2; }
        .user-info p { font-size: 11px; color: var(--gray-500); margin: 0 0 8px 0 !important; line-height: 1.2; }
        .btn-logout { width: 100%; padding: 8px !important; border-radius: 10px; border: none; background: var(--pink-600); color: white; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { background: var(--rose-dark); box-shadow: 0 4px 12px rgba(139, 26, 62, 0.2); }

        /* Main Content area */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar {
            background: var(--white); border-bottom: 1px solid var(--pink-100);
            padding: 20px 32px; display: flex; justify-content: space-between; align-items: center;
        }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); margin: 0; }
        .topbar p  { font-size: 13px; color: var(--gray-500); margin: 4px 0 0; }
        .content { padding: 28px 32px; }

        /* Stats Card Upgrade */
        .stat-card { 
            border: none; border-radius: 24px; background: white; 
            box-shadow: 0 10px 30px rgba(232, 69, 122, 0.05); 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            border: 1px solid rgba(232, 69, 122, 0.05);
        }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(232, 69, 122, 0.12); }
        
        .icon-gradient {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            background: linear-gradient(135deg, #e8457a, #c73165); color: white;
        }

        /* Main Table Card */
        .main-card { 
            border: none; border-radius: 30px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.06); 
            background: white; overflow: hidden; 
        }

        .btn-pink { background: #e8457a; color: white; border-radius: 14px; font-weight: 700; padding: 12px 28px; border:none; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-pink:hover { background: #c73165; color: white; transform: scale(1.02); box-shadow: 0 10px 20px rgba(232, 69, 122, 0.2); }

        .status-badge { border-radius: 12px; padding: 6px 16px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; display: inline-block; }
        
        .table thead th { 
            background: #f8fafc; color: #64748b; font-size: 11px; 
            text-transform: uppercase; font-weight: 800; padding: 20px; 
            border-bottom: 1px solid #f1f5f9;
        }
        
        .btn-action { 
            border-radius: 12px; border: 1.5px solid #f1f5f9; 
            background: white; transition: 0.2s; padding: 8px 12px;
        }
        .btn-action:hover { background: #fdf2f7; border-color: #e8457a; }

        .customer-avatar {
            width: 45px; height: 45px; background: #ffe0ec; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; color: #e8457a; font-size: 1.2rem;
        }
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
            <a href="../Pelanggan/list.php" class="nav-item active">
                <i class="bi bi-people"></i> Pelanggan
            </a>
            <a href="../Paket Foto/list.php" class="nav-item">
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

    <!-- MAIN CONTENT -->
    <main class="main">
        <div class="topbar">
            <div>
                <h1>Master Pelanggan</h1>
                <p>Kelola database profil dan akses login customer.</p>
            </div>
            <a href="add.php" class="btn btn-pink shadow-sm">
                <i class="bi bi-person-plus-fill me-2"></i>Tambah Pelanggan
            </a>
        </div>

        <div class="content">
            <!-- Statistik Grid -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="stat-card p-4 d-flex align-items-center">
                        <div class="icon-gradient me-3 shadow-sm"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="text-muted small fw-bold">TOTAL PELANGGAN</div>
                            <div class="h3 fw-bold mb-0"><?= $stats['total'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-4 d-flex align-items-center">
                        <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="bi bi-person-check-fill"></i></div>
                        <div>
                            <div class="text-muted small fw-bold">PELANGGAN AKTIF</div>
                            <div class="h3 fw-bold mb-0 text-success"><?= $stats['active'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-4 d-flex align-items-center">
                        <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #ef4444, #dc3545);"><i class="bi bi-person-x-fill"></i></div>
                        <div>
                            <div class="text-muted small fw-bold">NON-AKTIF</div>
                            <div class="h3 fw-bold mb-0 text-danger"><?= $stats['inactive'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="main-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Identitas Customer</th>
                                <th>Kontak & Email</th>
                                <th>Status Akun</th>
                                <th class="text-end pe-4">Aksi Manajemen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center py-2">
                                        <div class="customer-avatar me-3 shadow-sm">
                                            <i class="bi bi-person-heart"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                            <small class="text-muted">ID Pelanggan: #<?= $row['ID_Pelanggan'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-600 text-dark"><?= htmlspecialchars($row['Email_User']) ?></div>
                                    <div class="text-success small fw-bold"><i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($row['No_Hp']) ?></div>
                                end text-muted small py-1 shadow-sm
                                </td>
                                <td>
                                    <span class="status-badge <?= $row['Status_User'] == 'Active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
                                        <?= strtoupper($row['Status_User']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <!-- EDIT PROFIL -->
                                        <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-action btn-sm" title="Edit Profil">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        
                                        <!-- SOFT DELETE: Toggle Status (Mengarah ke action_pelanggan.php) -->
                                        <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-action btn-sm" title="Ubah Status (Soft Delete)">
                                            <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>

                                        <!-- HARD DELETE: Hapus Permanen (Mengarah ke action_pelanggan.php) -->
                                        <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-action btn-sm" title="Hapus Permanen">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 bg-light bg-opacity-50 border-top d-flex justify-content-between align-items-center">
                    <a href="../Admin/index.php" class="text-decoration-none text-muted small fw-bold">
                        <i class="bi bi-arrow-left-circle-fill me-1"></i> DASHBOARD UTAMA
                    </a>
                    <span class="text-muted small fw-bold" style="font-size: 10px;">SpotLight Database Management System</span>
                </div>
            </div>
        </div>
    </main>

    <script>
    // Validasi 1: SOFT DELETE (Mengubah Status Aktif/Nonaktif)
    function toggleStatus(id, current) {
        let action = current === 'Active' ? 'Menonaktifkan' : 'Aktifkan';
        Swal.fire({
            title: action + ' Pelanggan?',
            text: "Status akun akan diubah. Jika Nonaktif, pelanggan tidak bisa melakukan booking.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#e8457a',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Lakukan'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_pelanggan.php?type=soft&id=' + id + '&status=' + current;
            }
        })
    }

    // Validasi 2: HARD DELETE (Menghapus Data Selamanya)
    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Pelanggan Permanen?',
            text: "Data profil dan akun login akan dihapus selamanya dari database!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus Saja'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_pelanggan.php?type=hard&id=' + id;
            }
        })
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>