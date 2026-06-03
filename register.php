<?php
session_start();
include 'koneksi.php';

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dan bersihkan dari spasi berlebih
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $hp = trim($_POST['hp']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. VALIDASI: SEMUA HARUS DIISI
    if (empty($nama) || empty($email) || empty($hp) || empty($pass) || empty($confirm_pass)) {
        $error_message = "Semua kolom harus diisi!";
    } 
    // 2. VALIDASI: NAMA HARUS HURUF
    else if (!preg_match("/^[a-zA-Z\s]*$/", $nama)) {
        $error_message = "Nama Lengkap hanya boleh berisi huruf!";
    }
    // 3. VALIDASI: NOMOR HP HARUS ANGKA
    else if (!is_numeric($hp)) {
        $error_message = "Nomor Telepon harus berupa angka!";
    }
    // 4. VALIDASI: PANJANG PASSWORD
    else if (strlen($pass) < 8) {
        $error_message = "Kata sandi minimal 8 karakter!";
    }
    // 5. VALIDASI: KONFIRMASI PASSWORD COCOK
    else if ($pass !== $confirm_pass) {
        $error_message = "Konfirmasi kata sandi tidak cocok!";
    }
    else {
        // CEK APAKAH EMAIL SUDAH TERDAFTAR
        $sql_cek = "SELECT Email_User FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_message = "Email sudah terdaftar!";
        } else {
            // PROSES INSERT (Dua Tabel)
            $sql_user = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) VALUES (?, ?, 'Customer', 'Active'); SELECT SCOPE_IDENTITY() AS last_id;";
            $stmt_user = sqlsrv_query($conn, $sql_user, array($email, $pass));

            if ($stmt_user) {
                sqlsrv_next_result($stmt_user);
                $row = sqlsrv_fetch_array($stmt_user, SQLSRV_FETCH_ASSOC);
                $id_user_baru = $row['last_id'];

                $sql_pelanggan = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp) VALUES (?, ?, ?)";
                $stmt_pelanggan = sqlsrv_query($conn, $sql_pelanggan, array($id_user_baru, $nama, $hp));

                if ($stmt_pelanggan) {
                    echo "<script>alert('Pendaftaran Berhasil! Silakan masuk.'); window.location='login.php';</script>";
                }
            } else {
                $error_message = "Terjadi kesalahan sistem.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Daftar Akun - SpotLight Studio</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root { --primary-color: #d82b6b; --bg-gradient: linear-gradient(135deg, #fff5f7 0%, #e3f2fd 100%); }
    body { font-family: 'Montserrat', sans-serif; background: var(--bg-gradient); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .register-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 25px; padding: 40px; box-shadow: 0 15px 35px rgba(216, 43, 107, 0.1); width: 100%; max-width: 500px; }
    
    /* Animasi Getar jika error */
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
    .shake { animation: shake 0.3s ease-in-out; border: 1px solid #ff4d4d !important; }

    .register-header h2 { font-weight: 800; color: #2d0a18; }
    .form-label { font-weight: 600; font-size: 0.85rem; color: #444; }
    .input-group-text { background-color: transparent; color: var(--primary-color); border-radius: 12px 0 0 12px; }
    .form-control { border-left: none; border-radius: 0 12px 12px 0; padding: 12px 15px; font-size: 0.95rem; }
    .btn-register { background: linear-gradient(45deg, #d82b6b, #e91e63); border: none; border-radius: 12px; padding: 12px; font-weight: 700; color: white; width: 100%; transition: 0.3s; margin-top: 10px; }
    .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(216, 43, 107, 0.4); }
    .error-text { color: #ff4d4d; font-size: 0.75rem; font-weight: 600; margin-top: 5px; display: none; }
  </style>
</head>

<body>

  <div class="register-card" id="regCard" data-aos="zoom-in">
    <div class="register-header text-center mb-4">
      <h2>Buat Akun Baru</h2>
      <p class="text-muted small">Lengkapi data untuk akses layanan SpotLight.</p>
    </div>

    <!-- Alert Notifikasi Atas -->
    <?php if($error_message != ""): ?>
        <div class="alert alert-danger py-2 small text-center" id="alertError"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="" method="POST" id="registerForm" novalidate>
      
      <!-- Nama Lengkap (Hanya Huruf) -->
      <div class="mb-3">
        <label class="form-label">Nama Lengkap</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
          <input type="text" name="nama" id="nama" class="form-control" placeholder="Hanya huruf" value="<?php echo isset($nama)?$nama:''; ?>" required>
        </div>
        <div id="namaError" class="error-text">Nama hanya boleh berisi huruf!</div>
      </div>

      <!-- Nomor Telepon (Hanya Angka) -->
      <div class="mb-3">
        <label class="form-label">Nomor Telepon</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-phone"></i></span>
          <input type="text" name="hp" id="hp" class="form-control" placeholder="Contoh: 0812345678" value="<?php echo isset($hp)?$hp:''; ?>" required>
        </div>
        <div id="hpError" class="error-text">Nomor telepon harus berupa angka!</div>
      </div>

      <!-- Email -->
      <div class="mb-3">
        <label class="form-label">Alamat Email</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" id="email" class="form-control" placeholder="email@domain.com" value="<?php echo isset($email)?$email:''; ?>" required>
        </div>
      </div>

      <!-- Password -->
      <div class="mb-3">
        <label class="form-label">Kata Sandi</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 8 karakter" required>
        </div>
      </div>

      <!-- Konfirmasi Password -->
      <div class="mb-4">
        <label class="form-label">Konfirmasi Kata Sandi</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
          <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Ulangi kata sandi" required>
        </div>
        <div id="passError" class="error-text">Kata sandi tidak cocok!</div>
      </div>

      <button type="submit" class="btn-register">Daftar Sekarang</button>
    </form>

    <div class="text-center mt-4 small">
      Sudah punya akun? <a href="login.php" style="color: #5cacee; font-weight: 700; text-decoration: none;">Masuk</a>
    </div>
  </div>

  <script src="assets/vendor/aos/aos.js"></script>
  <script>
    AOS.init();
    const form = document.getElementById('registerForm');
    const card = document.getElementById('regCard');

    form.addEventListener('submit', function(e) {
      let valid = true;
      const nama = document.getElementById('nama');
      const hp = document.getElementById('hp');
      const pass = document.getElementById('password');
      const confirmPass = document.getElementById('confirmPassword');
      
      // Sembunyikan semua error dulu
      document.querySelectorAll('.error-text').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.form-control').forEach(el => el.classList.remove('shake'));

      // 1. Validasi Kosong
      if (!nama.value || !hp.value || !pass.value || !confirmPass.value) {
        alert("Harap isi semua kolom!");
        valid = false;
      }

      // 2. Validasi Nama (Hanya Huruf)
      const namaRegex = /^[a-zA-Z\s]*$/;
      if (!namaRegex.test(nama.value)) {
        document.getElementById('namaError').style.display = 'block';
        nama.classList.add('shake');
        valid = false;
      }

      // 3. Validasi HP (Hanya Angka)
      if (isNaN(hp.value)) {
        document.getElementById('hpError').style.display = 'block';
        hp.classList.add('shake');
        valid = false;
      }

      // 4. Validasi Kesamaan Password
      if (pass.value !== confirmPass.value) {
        document.getElementById('passError').style.display = 'block';
        confirmPass.classList.add('shake');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        card.classList.add('shake');
        setTimeout(() => card.classList.remove('shake'), 400);
      }
    });
  </script>
</body>
</html>