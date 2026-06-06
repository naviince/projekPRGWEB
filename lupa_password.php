<?php
session_start();
include 'koneksi.php';

$error = "";
$success = false;

if (isset($_POST['reset'])) {
    $email = trim($_POST['email']);
    $pass_baru = $_POST['password_baru'];

    // 1. AMBIL DATA USER BERDASARKAN EMAIL
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    $user_data = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    // 2. VALIDASI LOGIS & AKURAT
    if (!$user_data) {
        // Validasi: Email tidak ada di database
        $error = "Maaf, email tersebut tidak terdaftar di sistem kami.";
    } elseif ($user_data['Password_User'] === $pass_baru) {
        // VALIDASI IDE KAMU: Kata sandi baru tidak boleh sama dengan yang lama
        $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
    } else {
        // 3. PROSES UPDATE (Hanya jika lolos semua validasi)
        $sql_upd = "UPDATE Users SET Password_User = ? WHERE Email_User = ?";
        $params = array($pass_baru, $email);
        $res = sqlsrv_query($conn, $sql_upd, $params);
        
        if ($res) { 
            $success = true; 
        } else {
            $error = "Terjadi kesalahan sistem saat memperbarui kata sandi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .reset-card { background: white; border-radius: 40px; padding: 50px; box-shadow: 0 30px 100px rgba(232, 69, 122, 0.15); width: 100%; max-width: 480px; position: relative; animation: slideUp 0.6s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .btn-home { position: absolute; top: -60px; left: 50%; transform: translateX(-50%); background: white; padding: 8px 20px; border-radius: 50px; font-size: 12px; font-weight: 800; color: var(--p-pink); text-decoration: none; box-shadow: 0 10px 20px rgba(0,0,0,0.05); transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-home:hover { background: var(--p-pink); color: white; }

        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.08); }

        .btn-reset { background: linear-gradient(135deg, #e8457a, #c73165); color: white; border-radius: 18px; padding: 16px; font-weight: 800; border: none; width: 100%; transition: 0.4s; margin-top: 10px; font-size: 15px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.3); }
        .btn-reset:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.4); }
        
        .error-box { background: #fff1f2; color: #e11d48; padding: 12px; border-radius: 12px; font-size: 12px; font-weight: 700; border-left: 4px solid #e11d48; margin-bottom: 20px; }
        .link-back { color: #94a3b8; font-weight: 700; font-size: 13px; text-decoration: none; transition: 0.3s; }
        .link-back:hover { color: var(--p-pink); }
    </style>
</head>
<body>

    <div class="reset-card">
        <a href="index.php" class="btn-home"><i class="bi bi-house-heart-fill"></i> Beranda Utama</a>

        <div class="text-center mb-5">
            <div class="mb-3">
                <i class="bi bi-shield-lock-fill" style="font-size: 3.5rem; color: var(--p-pink);"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Keamanan Akun</h3>
            <p class="text-muted small fw-500">Atur ulang kata sandi Anda dengan memasukkan email terdaftar.</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-box"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Email Terdaftar</label>
                <input type="email" name="email" class="form-control" required placeholder="nama@email.com" value="<?= @$_POST['email'] ?>">
            </div>

            <div class="mb-4">
                <label class="form-label">Kata Sandi Baru</label>
                <input type="password" name="password_baru" class="form-control" required placeholder="Minimal 6 karakter">
            </div>

            <button type="submit" name="reset" class="btn btn-reset shadow-sm">
                Perbarui Kata Sandi
            </button>
            
            <div class="text-center mt-4">
                <a href="login.php" class="link-back"><i class="bi bi-arrow-left"></i> Kembali ke Halaman Login</a>
            </div>
        </form>
    </div>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Sandi Diperbarui!',
            text: 'Sekarang Anda bisa masuk menggunakan sandi yang baru.',
            confirmButtonColor: '#e8457a',
            confirmButtonText: 'Masuk Sekarang'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = 'login.php';
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>