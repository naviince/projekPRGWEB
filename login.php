<?php
session_start();
include 'koneksi.php'; 

$error_email = "";
$error_pass = "";

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email)) { $error_email = "Email wajib diisi!"; } 
    if (empty($password)) { $error_pass = "Password wajib diisi!"; }

    if (!empty($email) && !empty($password)) {
        // VALIDASI LOGIS: Cek kredensial ke database
        $sql    = "SELECT * FROM Users WHERE Email_User = ? AND Password_User = ?";
        $params = array($email, $password);
        $stmt   = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }

        $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($user) {
            // VALIDASI AKURAT: Cek status akun (Cegah user Non-aktif login)
            if ($user['Status_User'] == 'Inactive') {
                $error_email = "Akses akun ditangguhkan. Silakan hubungi tim kami.";
            } else {
                $_SESSION['status']   = "login";
                $_SESSION['id_user']  = $user['ID_User'];
                $_SESSION['email']    = $user['Email_User'];
                $_SESSION['role']     = $user['Role_User'];

                // REDIRECT BERDASARKAN ROLE (Alur Bisnis Matang)
                if ($user['Role_User'] == 'Admin') {
                    header("Location: Master/Admin/index.php");
                } elseif ($user['Role_User'] == 'Customer') {
                    header("Location: Customer/index.php");
                } elseif ($user['Role_User'] == 'Owner') {
                    header("Location: Master/Owner/index.php");
                } elseif ($user['Role_User'] == 'Fotografer') {
                    header("Location: Master/Fotografer/index.php");
                }
                exit();
            }
        } else {
            $error_email = "Email atau Password tidak cocok!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; --glass: rgba(255, 255, 255, 0.22); }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .login-card { border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 30px 100px rgba(232, 69, 122, 0.15); max-width: 1050px; width: 100%; border: none; position: relative; }

        .btn-home { position: absolute; top: 25px; right: 30px; z-index: 10; background: white; border: 1px solid var(--s-pink); padding: 10px 22px; border-radius: 50px; font-size: 13px; font-weight: 800; color: var(--p-pink); text-decoration: none; box-shadow: 0 10px 20px rgba(0,0,0,0.05); transition: 0.4s; display: flex; align-items: center; gap: 8px; }
        .btn-home:hover { background: var(--p-pink); color: white; transform: translateX(-8px); }

        .side-visual { background: linear-gradient(135deg, rgba(139, 26, 62, 0.9), rgba(232, 69, 122, 0.8)), url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070'); background-size: cover; background-position: center; color: white; padding: 60px; display: flex; flex-direction: column; justify-content: flex-end; }
        .glass-overlay { background: var(--glass); backdrop-filter: blur(15px); padding: 40px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.25); }

        .form-section { padding: 60px 80px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.08); }

        .btn-login { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 18px; padding: 18px; font-weight: 800; border: none; width: 100%; transition: 0.4s; margin-top: 15px; font-size: 16px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.3); }
        .btn-login:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.4); }

        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; }
        .link-pink { color: var(--p-pink); font-weight: 800; text-decoration: none; transition: 0.3s; }
        .link-pink:hover { color: var(--d-pink); text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-card container-fluid">
        <!-- Tombol Home -->
        <a href="index.php" class="btn-home"><i class="bi bi-house-heart-fill"></i> Beranda</a>

        <div class="row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div class="glass-overlay">
                    <h2 class="fw-bold mb-3 text-white">SpotLight Studio.</h2>
                    <!-- KALIMAT DESKRIPSI FINAL (NETRAL) -->
                    <p class="mb-0 opacity-90 small" style="line-height: 1.8;">Selamat datang kembali! Silakan masuk untuk mengakses layanan studio, mengelola pesanan, atau memantau operasional harian secara profesional.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Pintu Masuk Sistem</h3>
                    <p class="text-muted small fw-600">Akses dashboard Anda sesuai peran yang terdaftar.</p>
                </div>
                
                <form method="POST">
                    <!-- EMAIL -->
                    <div class="mb-4">
                        <label class="form-label">Email Terdaftar</label>
                        <input type="email" name="email" class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" placeholder="nama@email.com" value="<?= @$_POST['email'] ?>" required>
                        <?php if($error_email): ?><div class="error-text"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                    </div>

                    <!-- PASSWORD -->
                    <div class="mb-4">
                        <label class="form-label">Kata Sandi</label>
                        <input type="password" name="password" class="form-control <?= ($error_pass != '') ? 'is-invalid' : '' ?>" placeholder="••••••" required>
                        <?php if($error_pass): ?><div class="error-text"><i class="bi bi-shield-lock-fill"></i> <?= $error_pass ?></div><?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end mb-4">
                        <a href="lupa_password.php" class="link-pink small">Kesulitan masuk / Lupa Sandi?</a>
                    </div>

                    <button type="submit" name="login" class="btn btn-login shadow-sm">
                        Masuk Sekarang <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                    
                    <div class="text-center mt-5">
                        <p class="small text-muted fw-600">Belum memiliki akun? <a href="register.php" class="link-pink">Daftar Pelanggan Baru</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>