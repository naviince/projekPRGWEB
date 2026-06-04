<?php
session_start();
include 'koneksi.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $hp = trim($_POST['hp']);
    $pass = $_POST['password'];

    // --- VALIDASI LOGIKA ---
    if (empty($nama) || empty($email) || empty($hp) || empty($pass)) {
        $error = "Semua kolom harus diisi!";
    } else if (!preg_match("/^[a-zA-Z\s]*$/", $nama)) {
        $error = "Nama Lengkap hanya boleh berisi huruf!";
    } else if (!is_numeric($hp)) {
        $error = "Nomor Telepon harus berupa angka!";
    } else if (strlen($pass) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Cek apakah email sudah ada
        $sql_cek = "SELECT Email_User FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
        if (sqlsrv_has_rows($stmt_cek)) {
            $error = "Email sudah terdaftar, gunakan email lain!";
        } else {
            // INSERT KE USERS
            $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) VALUES (?, ?, 'Customer', 'Active'); SELECT SCOPE_IDENTITY() AS LastID;";
            $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));
            sqlsrv_next_result($stmt1);
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $id_baru = $row['LastID'];

            // INSERT KE PELANGGAN
            $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp) VALUES (?, ?, ?)";
            $stmt2 = sqlsrv_query($conn, $sql2, array($id_baru, $nama, $hp));

            if ($stmt2) {
                echo "<script>alert('Pendaftaran Berhasil!'); window.location='login.php';</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Daftar - SpotLight</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #fff5f7 0%, #e3f2fd 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Montserrat'; }
    .register-card { background: white; padding: 40px; border-radius: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
    .is-invalid { border: 1px solid #dc3545 !important; animation: shake 0.3s; }
    @keyframes shake { 0%, 100% {transform: translateX(0);} 25% {transform: translateX(-10px);} 75% {transform: translateX(10px);} }
    .btn-reg { background: #d82b6b; color: white; border: none; padding: 12px; width: 100%; border-radius: 12px; font-weight: 700; }
  </style>
</head>
<body>
  <div class="register-card">
    <h2 class="text-center fw-bold mb-4">Buat Akun</h2>
    <?php if($error != ""): ?>
        <div class="alert alert-danger py-2 small text-center"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <div class="mb-3">
        <label class="small fw-bold">Nama Lengkap</label>
        <input type="text" name="nama" class="form-control <?php echo (strpos($error, 'Nama') !== false) ? 'is-invalid' : ''; ?>" placeholder="Masukan Nama Lengkap" value="<?php echo @$nama; ?>">
      </div>
      <div class="mb-3">
        <label class="small fw-bold">Nomor Telepon</label>
        <input type="text" name="hp" class="form-control <?php echo (strpos($error, 'Telepon') !== false) ? 'is-invalid' : ''; ?>" placeholder="08111111111" value="<?php echo @$hp; ?>">
      </div>
      <div class="mb-3">
        <label class="small fw-bold">Alamat Email</label>
        <input type="email" name="email" class="form-control <?php echo (strpos($error, 'Email') !== false) ? 'is-invalid' : ''; ?>" placeholder="email@domain.com" value="<?php echo @$email; ?>">
      </div>
      <div class="mb-4">
        <label class="small fw-bold">Kata Sandi</label>
        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
      </div>
      <button type="submit" class="btn-reg">Daftar Sekarang</button>
    </form>
    <p class="text-center mt-3 small">Sudah punya akun? <a href="login.php" style="color:#5cacee; text-decoration:none; font-weight:700;">Masuk</a></p>
  </div>
</body>
</html>