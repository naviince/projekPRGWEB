<?php
// 1. Memulai Session dan Koneksi
session_start();
include 'koneksi.php'; 

// 2. Inisialisasi Variabel Error (Supaya tidak muncul Warning Undefined Variable)
$error_email = "";
$error_pass = "";

// 3. Proses Logic Login
if (isset($_POST['login'])) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    // Validasi Input Kosong
    if (empty($email)) {
        $error_email = "Email tidak boleh kosong!";
    } 
    if (empty($password)) {
        $error_pass = "Password tidak boleh kosong!";
    }

    // Jika input sudah diisi semua, baru cek ke Database
    if (!empty($email) && !empty($password)) {
        
        // Query sesuai PDM: Tabel 'Users', Kolom 'Email_User' dan 'Password_User'
        $sql    = "SELECT * FROM Users WHERE Email_User = ? AND Password_User = ?";
        $params = array($email, $password);
        $stmt   = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($user) {
            // Jika data ditemukan, simpan ke Session
            $_SESSION['status']   = "login";
            $_SESSION['id_user']  = $user['ID_User'];
            $_SESSION['email']    = $user['Email_User'];
            $_SESSION['role']     = $user['Role_User'];

            // Alur Bisnis: Redirect berdasarkan Role
            if ($user['Role_User'] == 'Admin') {
                header("Location: Master/Admin/index.php");
            } elseif ($user['Role_User'] == 'Customer') {
                header("Location: Master/Customer/index.php");
            } elseif ($user['Role_User'] == 'Owner') {
                header("Location: Master/Owner/index.php");
            } elseif ($user['Role_User'] == 'Fotografer') {
                header("Location: Master/Fotografer/index.php");
            }
            exit();
        } else {
            // Jika login gagal
            $error_email = "Email atau Password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SpotLight Studio</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Plus Jakarta Sans', sans-serif; }
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 1000px; display: flex; }
        .login-image { width: 50%; background: url('assets/img/login-bg.jpg') no-repeat center center; background-size: cover; min-height: 500px; }
        /* Jika gambar di folder berbeda, sesuaikan path url di atas */
        .login-form { width: 50%; padding: 50px; }
        .btn-primary { background-color: #e8457a; border: none; padding: 12px; border-radius: 10px; font-weight: 700; }
        .btn-primary:hover { background-color: #c73165; }
        .form-control { border-radius: 10px; padding: 12px 15px; border: 1px solid #eee; }
        .text-danger { font-size: 12px; margin-top: 5px; display: block; }
    </style>
</head>
<body>

<div class="container login-container">
    <div class="login-card">
        <!-- Bagian Foto (Kiri) -->
        <div class="login-image d-none d-md-block">
            <!-- Kamu bisa taruh <img> tag di sini jika url CSS tidak muncul -->
            <img src="https://images.unsplash.com/photo-1542038784456-1ea8e935640e?ixlib=rb-4.0.3" alt="Studio" style="width: 100%; height: 100%; object-fit: cover;">
        </div>

        <!-- Bagian Form (Kanan) -->
        <div class="login-form">
            <h2 class="fw-bold mb-2">SpotLight Studio</h2>
            <p class="text-muted mb-4">Silakan masuk untuk melanjutkan akses ke dasbor Anda.</p>

            <form action="" method="POST">
                <!-- Input Email -->
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control bg-light border-0" placeholder="nama@email.com" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                    </div>
                    <!-- TAMPILAN ERROR EMAIL -->
                    <?php if ($error_email != ""): ?>
                        <span class="text-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $error_email; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Input Password -->
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control bg-light border-0" placeholder="........">
                    </div>
                    <!-- TAMPILAN ERROR PASSWORD -->
                    <?php if ($error_pass != ""): ?>
                        <span class="text-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $error_pass; ?></span>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Ingat Saya</label>
                    </div>
                    <a href="#" class="text-decoration-none" style="color: #e8457a;">Lupa Password?</a>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 shadow-sm">Masuk Sekarang</button>
            </form>
            
            <p class="text-center mt-4 text-muted">Belum punya akun? <a href="register.php" class="text-decoration-none" style="color: #e8457a;">Daftar Sekarang</a></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>