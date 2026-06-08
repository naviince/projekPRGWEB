<?php
session_start();

// 1. FIX PATH KONEKSI: Naik 2 tingkat ke folder root
include '../../koneksi.php'; 

// 2. PROTEKSI HALAMAN (Validasi Akurat)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 3. AMBIL DATA SESSION
$session_id_user = $_SESSION['id_user'];
$session_email   = $_SESSION['email'];

// 4. AMBIL NAMA LENGKAP DARI TABEL KARYAWAN (Sesuai PDM)
// Logika: Mencari biodata karyawan yang login saat ini
$sql_profile = "SELECT Nama_Karyawan FROM Karyawan WHERE ID_User = ?";
$stmt_profile = sqlsrv_query($conn, $sql_profile, array($session_id_user));
$row_p = sqlsrv_fetch_array($stmt_profile, SQLSRV_FETCH_ASSOC);

$nama_display = ($row_p) ? $row_p['Nama_Karyawan'] : "Administrator";

// 5. AMBIL DATA STATISTIK UTAMA (Validasi Akurat Berbasis Entitas PDM)
// Total User (Semua Akun Login)
$res_user = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users");
$row_user = sqlsrv_fetch_array($res_user, SQLSRV_FETCH_ASSOC);

// Total Pelanggan (Yang sudah punya biodata)
$res_pelanggan = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Pelanggan");
$row_pelanggan = sqlsrv_fetch_array($res_pelanggan, SQLSRV_FETCH_ASSOC);

// Total Paket Foto
$res_paket = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Paket_Foto");
$row_paket = sqlsrv_fetch_array($res_paket, SQLSRV_FETCH_ASSOC);

// Total Karyawan (Menghitung dari tabel Karyawan langsung agar akurat secara PDM)
$res_karyawan = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Karyawan");
$row_karyawan = sqlsrv_fetch_array($res_karyawan, SQLSRV_FETCH_ASSOC);

// 6. AMBIL DATA GRAFIK
$roles = [];
$counts = [];
$q_grafik = sqlsrv_query($conn, "SELECT Role_User, COUNT(*) as jumlah FROM Users GROUP BY Role_User");
while($data = sqlsrv_fetch_array($q_grafik, SQLSRV_FETCH_ASSOC)) {
    $roles[]  = $data['Role_User'];
    $counts[] = $data['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpotLight Admin – Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Tambahkan ini di dalam tag <style> */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-back-home {
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    color: var(--pink-600);
    background: var(--pink-50);
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid var(--pink-200);
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-back-home:hover {
    background: var(--pink-600);
    color: white;
    box-shadow: 0 4px 12px rgba(232, 69, 122, 0.15);
}
        :root {
            --pink-50: #fff0f5; --pink-100: #ffe0ec; --pink-200: #ffb3cc;
            --pink-400: #f472a0; --pink-500: #e8457a; --pink-600: #c73165;
            --pink-700: #a01f4e; --rose-dark: #8b1a3e; --white: #ffffff;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-700: #374151;
            --gray-900: #111827; --sidebar-w: 240px; --radius: 12px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .04);
            --shadow-md: 0 10px 25px -5px rgba(232, 69, 122, 0.1), 0 8px 10px -6px rgba(232, 69, 122, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--pink-50); color: var(--gray-900); display: flex; min-height: 100vh; font-size: 14px; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; box-shadow: 4px 0 15px rgba(0,0,0,0.02); }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); letter-spacing: -0.5px; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-label { padding: 18px 20px 8px; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: all 0.3s ease; }
        .nav-item:hover { color: var(--pink-600); background: var(--pink-50); padding-left: 25px; }
        .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 4px solid var(--pink-600); font-weight: 700; }
        
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); background: #fafafa; }
        .user-info h4 { font-size: 13px; font-weight: 700; color: var(--gray-900); }
        .user-info p { font-size: 11px; color: var(--gray-500); }

        /* Main Content */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar { background: var(--white); border-bottom: 1px solid var(--pink-100); padding: 20px 32px; }
        .topbar h1 { font-size: 22px; font-weight: 800; color: var(--gray-900); }
        .content { padding: 28px 32px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-sm); transition: all 0.3s ease; border: 1px solid transparent; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--pink-100); }
        .stat-label { font-size: 12px; color: var(--gray-400); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 28px; font-weight: 800; margin-top: 5px; color: var(--gray-900); }

        /* Chart Area */
        .chart-container { background: white; padding: 25px; border-radius: 16px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
        .btn-logout { width: 100%; padding: 10px; margin-top: 10px; border-radius: 10px; border: none; background: var(--pink-600); color: white; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-logout:hover { background: var(--rose-dark); box-shadow: 0 4px 12px rgba(139, 26, 62, 0.2); }
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

        <a href="../../index.php" class="nav-item" style="color: var(--pink-600); background: var(--pink-50); margin-bottom: 10px;">
        <i class="bi bi-house-door-fill"></i> Kembali ke Landing Page
    </a>
    
    
            <a href="index.php" class="nav-item active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            
            <p class="nav-label">Autentikasi & Akun</p>
            <a href="../User/list.php" class="nav-item">
                <i class="bi bi-person-lock"></i> Master User
            </a>

            <p class="nav-label">Master Data Profil</p>
            <!-- PERUBAHAN: Master Admin -> Master Karyawan -->
            <a href="list.php" class="nav-item">
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
            <a href="../Tema/list.php" class="nav-item">
                <i class="bi bi-palette"></i> Tema Foto
            </a>

            <p class="nav-label">Operasional</p>
            <a href="#" class="nav-item"><i class="bi bi-calendar-event"></i> Booking Studio</a>
        </nav>

        <div class="sidebar-profile">
            <div class="user-info">
                <h4><?php echo $nama_display; ?></h4>
                <p><?php echo $session_email; ?></p>
            </div>
            <button class="btn-logout" onclick="location.href='../../logout.php'">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Overview Dashboard</h1>
            <p class="text-muted">Pantau data autentikasi dan operasional secara real-time.</p>
        </div>

        <div class="content">
            <!-- STATS GRID -->
            <div class="stats-grid">
                <div class="stat-card">
                    <!-- PERUBAHAN: Akun Admin -> Total Karyawan -->
                    <div class="stat-label">Total Karyawan</div>
                    <div class="stat-value"><?php echo $row_karyawan['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Akun User</div>
                    <div class="stat-value"><?php echo $row_user['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Biodata Pelanggan</div>
                    <div class="stat-value"><?php echo $row_pelanggan['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Paket Aktif</div>
                    <div class="stat-value"><?php echo $row_paket['total']; ?></div>
                </div>
            </div>

            <div style="display: flex; gap: 24px;">
                <!-- GRAFIK -->
                <div class="chart-container" style="flex: 2;">
                    <h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700;">Sebaran Akun Berdasarkan Role</h3>
                    <canvas id="userChart" style="max-height: 300px;"></canvas>
                </div>
                
                <!-- INFO SISTEM -->
                <div class="chart-container" style="flex: 1; background: linear-gradient(135deg, var(--pink-600), var(--rose-dark)); color: white; border: none;">
                    <h3 style="font-size: 16px; font-weight: 700;"><i class="bi bi-info-circle me-2"></i>Status Sistem</h3>
                    <!-- PERUBAHAN: Profil Admin -> Profil Karyawan -->
                    <p style="margin-top: 15px; opacity: 0.9; line-height: 1.6;">Sistem saat ini terhubung dengan database SQL Server. Data User dan Profil Karyawan/Pelanggan telah disinkronkan sesuai hierarki sistem.</p>
                    <div style="margin-top: 40px; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 10px; backdrop-filter: blur(5px);">
                        <small style="opacity: 0.8;">Data terakhir ditarik:</small><br>
                        <b style="font-size: 15px;"><?php echo date('d F Y, H:i'); ?> WIB</b>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('userChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($roles); ?>,
                datasets: [{
                    label: 'Jumlah Akun',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: [
                        '#e8457a', // Admin
                        '#f472a0', // Customer
                        '#c73165', // Fotografer
                        '#8b1a3e'  // Owner
                    ],
                    borderRadius: 10,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>