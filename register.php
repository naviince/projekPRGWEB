<?php
session_start();
include 'koneksi.php';

$error_email = "";
$success = false;

if (isset($_POST['register'])) {
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $hp    = trim($_POST['no_hp']);
    $pass  = $_POST['password'];

    // VALIDASI 1: Cek email duplikat
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));

    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email sudah digunakan! Silakan gunakan email lain.";
    } else {
        // VALIDASI 2: Database Transaction (Perfect & Akurat)
        sqlsrv_begin_transaction($conn);

        // A. Simpan ke tabel Users (Induk)
        $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                 OUTPUT INSERTED.ID_User VALUES (?, ?, 'Customer', 'Active')";
        $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

        if ($stmt1) {
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];

            // B. Simpan ke tabel Pelanggan (Detail)
            $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
            $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, '-'));

            if ($stmt2) {
                sqlsrv_commit($conn);
                $success = true;
            } else {
                sqlsrv_rollback($conn);
            }
        }
    }
}
?>
<!-- Tampilan Register (Desain Modern Split-Layout) -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        .reg-card { border-radius: 30px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(0,0,0,0.08); max-width: 900px; margin: auto; }
        .side-img { background: linear-gradient(rgba(232, 69, 122, 0.8), rgba(139, 26, 62, 0.9)), url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070'); background-size: cover; background-position: center; color: white; padding: 40px; display: flex; flex-direction: column; justify-content: flex-end; }
        .btn-reg { background: #e8457a; color: white; border-radius: 12px; padding: 12px; font-weight: 700; border: none; transition: 0.3s; }
        .btn-reg:hover { background: #c73165; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="reg-card row g-0">
            <div class="col-md-5 side-img d-none d-md-flex">
                <h2 class="fw-bold">Abadikan Momen Spesialmu.</h2>
                <p class="opacity-75">Daftar sekarang untuk mendapatkan akses booking studio foto dengan tema-tema eksklusif.</p>
            </div>
            <div class="col-md-7 p-5">
                <h3 class="fw-bold mb-1">Daftar Pelanggan</h3>
                <p class="text-muted small mb-4">Lengkapi profil Anda untuk mulai memesan jasa kami.</p>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NAMA LENGKAP</label>
                        <input type="text" name="nama" class="form-control" required placeholder="Masukan Nama Lengkap">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">EMAIL</label>
                        <input type="email" name="email" class="form-control" required placeholder="nama@email.com">
                        <div class="text-danger small"><?= $error_email ?></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">NO. HP</label>
                            <input type="text" name="no_hp" class="form-control" required placeholder="08...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">KATA SANDI</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••">
                        </div>
                    </div>
                    <button type="submit" name="register" class="btn btn-reg w-100 mt-3 shadow-sm">Yuk Buat Akun Sekarang😊</button>
                    <div class="text-center mt-3 small">Sudah punya akun? <a href="login.php" class="text-decoration-none fw-bold" style="color:#e8457a">Masuk di sini</a></div>
                </form>
            </div>
        </div>
    </div>
    <?php if($success): ?>
    <script>Swal.fire('Berhasil!', 'Akun Anda sudah aktif. Silakan masuk.', 'berhasil').then(() => window.location='login.php');</script>
    <?php endif; ?>
</body>
</html>