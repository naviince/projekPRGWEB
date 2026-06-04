<?php
session_start();
include '../../koneksi.php';
$error = "";

if (isset($_POST['register'])) {
    $nama  = $_POST['nama'];
    $email = $_POST['email'];
    $hp    = $_POST['no_hp'];
    $pass  = $_POST['password'];

    // 1. VALIDASI: Cek apakah email sudah ada
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));

    if (sqlsrv_has_rows($stmt_cek)) {
        $error = "Email sudah terdaftar! Silakan gunakan email lain.";
    } else {
        // 2. INSERT KE TABEL USERS (Otomatis Role: Customer)
        $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                 OUTPUT INSERTED.ID_User 
                 VALUES (?, ?, 'Customer', 'Active')";
        $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

        if ($stmt1) {
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];

            // 3. INSERT KE TABEL PELANGGAN (Data Profil)
            $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
            $params2 = array($new_id, $nama, $hp, '-');
            $res2 = sqlsrv_query($conn, $sql2, $params2);

            if ($res2) {
                echo "<script>alert('Registrasi Berhasil! Silakan Login.'); window.location='login.php';</script>";
            }
        } else {
            $error = "Terjadi kesalahan sistem saat mendaftar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .reg-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .reg-card { background: white; border-radius: 25px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 900px; display: flex; }
        .reg-image { width: 45%; background: url('https://images.unsplash.com/photo-1554048612-b6a482bc67e5?q=80&w=2070&auto=format&fit=crop') no-repeat center center; background-size: cover; }
        .reg-form { width: 55%; padding: 40px; }
        .btn-reg { background-color: #e8457a; border: none; padding: 12px; border-radius: 12px; font-weight: 700; color: white; }
        .btn-reg:hover { background-color: #c73165; }
        .form-control { border-radius: 10px; padding: 10px 15px; background-color: #f8f9fa; border: 1px solid #f1f1f1; }
    </style>
</head>
<body>

<div class="reg-container">
    <div class="reg-card">
        <div class="reg-image d-none d-md-block"></div>
        <div class="reg-form">
            <h2 class="fw-extrabold mb-1">Daftar Akun</h2>
            <p class="text-muted mb-4 small">Lengkapi data untuk mulai booking sesi foto.</p>

            <?php if($error != ""): ?>
                <div class="alert alert-danger py-2 small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: Bintang Basev" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="nama@email.com" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">No. HP</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="08..." required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="******" required>
                    </div>
                </div>
                
                <div class="form-check mb-4 small">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label text-muted" for="terms">Saya setuju dengan ketentuan layanan Spotlight Studio.</label>
                </div>

                <button type="submit" name="register" class="btn btn-reg w-100 shadow-sm">Daftar Sekarang</button>
            </form>
            
            <p class="text-center mt-4 text-muted small">Sudah punya akun? <a href="login.php" class="text-decoration-none" style="color: #e8457a; font-weight: 700;">Login di sini</a></p>
        </div>
    </div>
</div>

</body>
</html>