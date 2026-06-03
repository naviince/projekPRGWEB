<?php
session_start();
include 'koneksi.php';

$error_email = "";
$error_pass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Validasi Kosong
    if (empty($email)) {
        $error_email = "Email tidak boleh kosong!";
    } else if (empty($password)) {
        $error_pass = "Kata sandi tidak boleh kosong!";
    } else {
        // 2. Cek ke Database
        $sql = "SELECT * FROM Users WHERE Email_User = ?";
        $params = array($email);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }

        $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($user) {
            // Cek Password
            if ($password === $user['Password_User']) {
                $_SESSION['status'] = "login";
                $_SESSION['id_user'] = $user['ID_User'];
                $_SESSION['role'] = $user['Role_User'];
                $_SESSION['email'] = $user['Email_User'];

                if ($user['Role_User'] == 'Admin') {
                    header("Location: Master/Admin/index.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_pass = "Kata sandi yang Anda masukkan salah!";
            }
        } else {
            $error_email = "Email tidak terdaftar di sistem kami!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - SpotLight Photo Studio</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root { --accent-color: #ff66a1; --heading-color: #2d0a18; }
    body { font-family: 'Montserrat', sans-serif; background-color: #fff9fa; height: 100vh; overflow: hidden; }
    .login-container { height: 100vh; display: flex; }

    /* Sisi Kiri - Gambar (SESUAI ASLI) */
    .login-sidebar {
      background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('assets/img/about/poto.webp') center/cover;
      width: 50%;
      position: relative;
    }

    /* Sisi Kanan - Form (SESUAI ASLI) */
    .login-form-side { width: 50%; display: flex; align-items: center; justify-content: center; padding: 60px; }
    .form-box { width: 100%; max-width: 450px; }
    .form-box h2 { font-weight: 700; color: #1a1a1a; margin-bottom: 10px; }
    
    .input-group { background: white; border: 1px solid #eee; border-radius: 10px; overflow: hidden; margin-bottom: 5px; transition: 0.3s; }
    .input-group:focus-within { border-color: var(--accent-color); }
    .input-group-text { background: none; border: none; color: #999; padding-left: 15px; }
    .form-control { border: none; padding: 12px 10px; font-size: 0.9rem; }
    .form-control:focus { box-shadow: none; }

    .btn-masuk {
      background: var(--accent-color); color: white; width: 100%; padding: 14px;
      border-radius: 10px; border: none; font-weight: 700; margin-top: 20px; transition: 0.3s;
    }
    .btn-masuk:hover { background: #ff4d94; transform: translateY(-2px); }

    /* Validasi Red Text */
    .error-msg { color: #dc3545; font-size: 0.75rem; font-weight: 600; margin-bottom: 15px; display: block; }
    
    /* Animasi Shake */
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
    .is-invalid { animation: shake 0.3s ease-in-out; border: 1px solid #dc3545 !important; }

    @media (max-width: 992px) { .login-sidebar { display: none; } .login-form-side { width: 100%; } }
  </style>
</head>
<body>

  <div class="login-container">
    <!-- Kiri (Gambar) -->
    <div class="login-sidebar"></div>

    <!-- Kanan (Form) -->
    <div class="login-form-side">
      <div class="form-box">
        <h2>Selamat Datang Kembali</h2>
        <p class="text-muted mb-4">Silakan masuk untuk mengakses dasbor Anda.</p>

        <form method="POST" action="" id="loginForm">
          
          <!-- Email -->
          <div class="mb-1">
            <label class="small fw-bold text-dark">Email</label>
            <div class="input-group <?php echo ($error_email != "") ? 'is-invalid' : ''; ?>">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?php echo isset($email) ? $email : ''; ?>">
            </div>
            <?php if($error_email != ""): ?>
                <span class="error-msg"><?php echo $error_email; ?></span>
            <?php endif; ?>
          </div>

          <!-- Password -->
          <div class="mb-1">
            <label class="small fw-bold text-dark">Password</label>
            <div class="input-group <?php echo ($error_pass != "") ? 'is-invalid' : ''; ?>">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" class="form-control" placeholder="••••••••">
              <span class="input-group-text" style="cursor: pointer;"><i class="bi bi-eye"></i></span>
            </div>
            <?php if($error_pass != ""): ?>
                <span class="error-msg"><?php echo $error_pass; ?></span>
            <?php endif; ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember">
              <label class="form-check-label small" for="remember">Ingat Saya</label>
            </div>
            <a href="#" style="color: var(--accent-color); font-size: 0.85rem; font-weight: 600; text-decoration: none;">Lupa Password?</a>
          </div>

          <button type="submit" class="btn-masuk">Masuk</button>
        </form>

        <p class="text-center mt-5 small text-muted">
          Belum punya akun? <a href="register.php" style="color: var(--accent-color); font-weight: 700; text-decoration: none;">Daftar di sini</a>
        </p>
      </div>
    </div>
  </div>

  <script>
    // Validasi Sederhana Browser
    document.getElementById('loginForm').onsubmit = function(e) {
      const email = document.getElementsByName('email')[0].value;
      const pass = document.getElementsByName('password')[0].value;
      if(!email || !pass) {
        alert("Email dan Password tidak boleh kosong!");
      }
    };
  </script>
</body>
</html>