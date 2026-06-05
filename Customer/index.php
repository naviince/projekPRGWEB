<?php
session_start();
include '../koneksi.php'; 

// VALIDASI AKURAT: Hanya Customer yang bisa masuk
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Customer') {
    header("Location: ../login.php");
    exit();
}

$id_user_login = $_SESSION['id_user'];
$session_email = $_SESSION['email'];

// Ambil Profil Pelanggan
$sql = "SELECT p.*, u.Email_User FROM Pelanggan p JOIN Users u ON p.ID_User = u.ID_User WHERE p.ID_User = ?";
$stmt = sqlsrv_query($conn, $sql, array($id_user_login));
$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$nama_customer = $user['Nama_Pelanggan'] ?? 'Pelanggan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --pink-primary: #e8457a;
            --pink-dark: #8b1a3e;
            --pink-soft: #fdf2f7;
            --pink-hover: #ffe0ec;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --sidebar-w: 260px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--pink-soft); color: var(--text-main); display: flex; min-height: 100vh; }

        /* SIDEBAR (Mirip Admin) */
        .sidebar { 
            width: var(--sidebar-w); 
            height: 100vh; 
            background: white; 
            border-right: 1px solid var(--pink-hover); 
            display: flex; 
            flex-direction: column; 
            position: fixed; 
            top: 0; left: 0; 
            z-index: 100;
            box-shadow: 4px 0 20px rgba(0,0,0,0.02);
        }

        .sidebar-brand { padding: 30px 25px; border-bottom: 1px solid var(--pink-soft); }
        .sidebar-brand h2 { color: var(--pink-primary); font-weight: 800; font-size: 22px; letter-spacing: -0.5px; }

        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { 
            display: flex; align-items: center; gap: 12px; padding: 12px 25px; 
            color: var(--text-muted); text-decoration: none; font-weight: 600; 
            transition: all 0.3s ease; border-right: 4px solid transparent;
        }
        .nav-item:hover { color: var(--pink-primary); background: var(--pink-soft); }
        .nav-item.active { 
            color: var(--pink-primary); background: var(--pink-soft); 
            border-right: 4px solid var(--pink-primary); font-weight: 700; 
        }

        /* User Profile di Bawah (Persis Admin) */
        .sidebar-profile { padding: 20px; border-top: 1px solid var(--pink-soft); background: #fafafa; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar { 
            width: 40px; height: 40px; border-radius: 12px; 
            background: var(--pink-primary); color: white;
            display: flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .user-detail h6 { font-size: 13px; font-weight: 700; margin: 0; color: var(--text-main); }
        .user-detail p { font-size: 11px; color: var(--text-muted); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 130px; }

        .btn-logout { 
            width: 100%; padding: 10px; border-radius: 10px; border: 1.5px solid var(--pink-hover);
            background: white; color: var(--pink-primary); font-weight: 700; font-size: 12px;
            transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-logout:hover { background: var(--pink-primary); color: white; border-color: var(--pink-primary); }

        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 40px 50px; }

        /* Banner Card */
        .welcome-card { 
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-dark)); 
            border-radius: 30px; padding: 50px; color: white; position: relative; overflow: hidden;
            box-shadow: 0 20px 40px rgba(232, 69, 122, 0.2); margin-bottom: 40px;
        }
        .welcome-card::after {
            content: ""; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
        }

        /* Action Cards */
        .action-card { 
            background: white; border-radius: 25px; border: none; padding: 30px; 
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); cursor: pointer;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); height: 100%; position: relative;
        }
        .action-card:hover { 
            transform: translateY(-12px); 
            box-shadow: 0 20px 40px rgba(232, 69, 122, 0.1); 
            background: linear-gradient(to bottom right, #ffffff, #fff9fb);
        }
        .icon-circle { 
            width: 55px; height: 55px; border-radius: 15px; 
            background: var(--pink-soft); color: var(--pink-primary); 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; margin-bottom: 20px; transition: 0.3s;
        }
        .action-card:hover .icon-circle { background: var(--pink-primary); color: white; transform: rotate(-10deg); }

        .btn-booking-hero {
            background: white; color: var(--pink-primary); border: none; border-radius: 12px;
            padding: 12px 30px; font-weight: 800; font-size: 14px; transition: 0.3s;
        }
        .btn-booking-hero:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>SpotLight</h2>
            <p class="text-muted small mb-0" style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Customer Dashboard</p>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active"><i class="bi bi-grid-1x2-fill"></i> Beranda</a>
            <a href="../Transaksi/booking.php" class="nav-item"><i class="bi bi-camera-reels-fill"></i> Booking Sesi</a>
            <a href="../Transaksi/pesanan.php" class="nav-item"><i class="bi bi-receipt-cutoff"></i> Pesanan Saya</a>
            <a href="../Transaksi/unduh.php" class="nav-item"><i class="bi bi-cloud-arrow-down-fill"></i> Galeri Digital</a>
            <a href="../Transaksi/ulasan.php" class="nav-item"><i class="bi bi-star-fill"></i> Rating & Ulasan</a>
        </nav>

        <!-- Bagian Bawah: Nama & Logout (Persis Admin) -->
        <div class="sidebar-profile">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($nama_customer, 0, 1)) ?>
                </div>
                <div class="user-detail">
                    <h6><?= $nama_customer ?></h6>
                    <p><?= $session_email ?></p>
                </div>
            </div>
            <button class="btn-logout" onclick="location.href='../logout.php'">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="mb-4">
            <h5 class="fw-bold" style="color: var(--text-muted)">Ringkasan Aktivitas</h5>
        </header>

        <div class="welcome-card shadow">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="fw-bold mb-2">Selamat Datang, <?= explode(' ', $nama_customer)[0] ?>! ✨</h1>
                    <p class="opacity-75 mb-4" style="font-size: 15px; max-width: 500px;">Abadikan momen berhargamu dengan tema eksklusif dan fotografer profesional di SpotLight Studio.</p>
                    <button class="btn-booking-hero" onclick="location.href='../Transaksi/booking.php'">
                        Mulai Booking Baru <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Card 1 -->
            <div class="col-md-4">
                <div class="action-card" onclick="location.href='../Transaksi/booking.php'">
                    <div class="icon-circle shadow-sm"><i class="bi bi-calendar2-check"></i></div>
                    <h5 class="fw-bold mb-2">Sewa Studio</h5>
                    <p class="text-muted small mb-0">Pilih paket, tema, dan jadwal sesi foto yang tersedia.</p>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-md-4">
                <div class="action-card" onclick="location.href='../Transaksi/pesanan.php'">
                    <div class="icon-circle shadow-sm"><i class="bi bi-wallet2"></i></div>
                    <h5 class="fw-bold mb-2">Pembayaran</h5>
                    <p class="text-muted small mb-0">Lihat tagihan, upload bukti transfer DP atau pelunasan.</p>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-md-4">
                <div class="action-card" onclick="location.href='../Transaksi/unduh.php'">
                    <div class="icon-circle shadow-sm"><i class="bi bi-images"></i></div>
                    <h5 class="fw-bold mb-2">Hasil Foto</h5>
                    <p class="text-muted small mb-0">Unduh file foto digital yang sudah selesai diedit oleh tim kami.</p>
                </div>
            </div>
        </div>

        <!-- Info tambahan -->
        <div class="mt-5 p-4 bg-white shadow-sm border-0 d-flex align-items-center justify-content-between" style="border-radius: 20px;">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                    <i class="bi bi-info-circle-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">Butuh bantuan?</h6>
                    <p class="text-muted small mb-0">Hubungi tim support kami melalui WhatsApp jika ada kendala booking.</p>
                </div>
            </div>
            <a href="#" class="btn btn-dark rounded-pill px-4 btn-sm fw-bold">Chat Support</a>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>