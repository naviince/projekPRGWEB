<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 1. Ambil Data Statistik Utama
$row_user = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users"), SQLSRV_FETCH_ASSOC);
$row_pelanggan = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Pelanggan"), SQLSRV_FETCH_ASSOC);
$row_paket = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Paket_Foto"), SQLSRV_FETCH_ASSOC);
$row_admin = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin'"), SQLSRV_FETCH_ASSOC);

// 2. Ambil Data Untuk Grafik (Jumlah User per Role)
$roles = [];
$counts = [];
$q_grafik = sqlsrv_query($conn, "SELECT Role_User, COUNT(*) as jumlah FROM Users GROUP BY Role_User");
while($data = sqlsrv_fetch_array($q_grafik, SQLSRV_FETCH_ASSOC)) {
    $roles[] = $data['Role_User'];
    $counts[] = $data['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpotLight Admin – Studio Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Tambahkan Chart.js untuk Grafik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --pink-50: #fff0f5; --pink-100: #ffe0ec; --pink-200: #ffb3cc;
            --pink-400: #f472a0; --pink-500: #e8457a; --pink-600: #c73165;
            --pink-700: #a01f4e; --rose-dark: #8b1a3e; --white: #ffffff;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-700: #374151;
            --gray-900: #111827; --sidebar-w: 240px; --radius: 12px;
            --shadow: 0 1px 4px rgba(0, 0, 0, .08), 0 4px 16px rgba(0, 0, 0, .06);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--pink-50); color: var(--gray-900); display: flex; min-height: 100vh; font-size: 14px; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: var(--white); border-right: 1px solid var(--pink-100); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid var(--pink-100); }
        .sidebar-brand h2 { font-size: 18px; font-weight: 800; color: var(--pink-600); }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-label { padding: 18px 20px 8px; font-size: 10px; font-weight: 800; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; cursor: pointer; color: var(--gray-500); text-decoration: none; font-weight: 500; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { color: var(--pink-600); background: var(--pink-50); border-left: 3px solid var(--pink-600); font-weight: 700; }
        
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--pink-100); }
        .user-info h4 { font-size: 13px; font-weight: 700; }
        .user-info p { font-size: 11px; color: var(--gray-500); }

        /* Main Content */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar { background: var(--white); border-bottom: 1px solid var(--pink-100); padding: 20px 32px; }
        .content { padding: 28px 32px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); position: relative; overflow: hidden; }
        .stat-label { font-size: 12px; color: var(--gray-400); font-weight: 500; }
        .stat-value { font-size: 24px; font-weight: 800; margin-top: 5px; }

        /* Grafik Area */
        .chart-container { background: white; padding: 25px; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 24px; }

        .btn-logout { width: 100%; padding: 8px; margin-top: 10px; border-radius: 8px; border: none; background: var(--pink-100); color: var(--pink-600); font-weight: 700; cursor: pointer; }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>SpotLight Admin</h2>
            <p>Studio Management</p>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <p class="nav-label">Master Data</p>
            <a href="admin/list.php" class="nav-item">
                <i class="bi bi-shield-lock"></i> Master Admin
            </a>
            <a href="users/list.php" class="nav-item">
                <i class="bi bi-people"></i> Master User
            </a>
            <a href="#" class="nav-item">
                <i class="bi bi-person-badge"></i> Pelanggan
            </a>
            <a href="#" class="nav-item">
                <i class="bi bi-box"></i> Paket Foto
            </a>

            <p class="nav-label">Transaksi</p>
            <a href="#" class="nav-item">
                <i class="bi bi-calendar-check"></i> Booking
            </a>
        </nav>

        <div class="sidebar-profile">
            <div class="user-info">
                <h4><?php echo $_SESSION['role']; ?></h4>
                <p><?php echo $_SESSION['email']; ?></p>
            </div>
            <button class="btn-logout" onclick="location.href='../../logout.php'">Logout</button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
        <div class="topbar">
            <h1>Overview Dashboard</h1>
            <p>Statistik performa Studio SpotLight hari ini.</p>
        </div>

        <div class="content">
            <!-- STATS GRID -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Admin</div>
                    <div class="stat-value"><?php echo $row_admin['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $row_user['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pelanggan</div>
                    <div class="stat-value"><?php echo $row_pelanggan['total']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Paket Foto</div>
                    <div class="stat-value"><?php echo $row_paket['total']; ?></div>
                </div>
            </div>

            <!-- GRAFIK STATISTIK -->
            <div class="row" style="display: flex; gap: 20px;">
                <div class="chart-container" style="flex: 2;">
                    <h3 style="margin-bottom: 20px;">Statistik Pengguna Berdasarkan Role</h3>
                    <canvas id="userChart" style="max-height: 300px;"></canvas>
                </div>
                
                <div class="chart-container" style="flex: 1; background: linear-gradient(135deg, var(--pink-600), var(--rose-dark)); color: white;">
                    <h3>Info Sistem</h3>
                    <p style="margin-top: 15px; opacity: 0.9;">Dashboard ini terhubung langsung dengan database SQL Server untuk memantau data <b>Users</b> dan <b>Pelanggan</b> secara real-time.</p>
                    <div style="margin-top: 30px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                        <small>Terakhir diperbarui:</small><br>
                        <b><?php echo date('d F Y, H:i'); ?> WIB</b>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Script Grafik -->
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
                        'rgba(232, 69, 122, 0.7)',
                        'rgba(244, 114, 160, 0.7)',
                        'rgba(199, 49, 101, 0.7)',
                        'rgba(139, 26, 62, 0.7)'
                    ],
                    borderColor: '#e8457a',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>

</body>
</html>