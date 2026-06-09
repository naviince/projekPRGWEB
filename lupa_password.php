<?php
session_start();
include 'koneksi.php';

$error = "";
$success = false;

if (isset($_POST['reset'])) {
    $email = trim($_POST['email']);
    $pass_baru = $_POST['password_baru'];
    $konfirmasi_pass = $_POST['konfirmasi_password'];

    // 1. AMBIL DATA USER BERDASARKAN EMAIL
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    $user_data = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    // 2. VALIDASI LOGIS & AKURAT
    if (!$user_data) {
        $error = "Maaf, email tersebut tidak terdaftar di sistem kami.";
    } elseif ($pass_baru !== $konfirmasi_pass) {
        $error = "Konfirmasi kata sandi tidak cocok!";
    } elseif (strlen($pass_baru) < 8) {
        // Tambahan: Minimal 8 karakter agar lebih aman jika ada simbol
        $error = "Kata sandi minimal harus 8 karakter!";
    } 
    // --- VALIDASI KOMBINASI: HURUF, ANGKA, DAN SIMBOL ---
    elseif (!preg_match("/[a-zA-Z]/", $pass_baru) || 
            !preg_match("/[0-9]/", $pass_baru) || 
            !preg_match("/[^a-zA-Z0-9]/", $pass_baru)) {
        $error = "Kata sandi harus kombinasi Huruf, Angka, dan Simbol!";
    } 
    // ----------------------------------------------------
    elseif ($user_data['Password_User'] === $pass_baru) {
        $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
    } else {
        // 3. PROSES UPDATE
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
        .btn-gray {
    background: #e2e8f0; /* Warna abu-abu muda */
    color: #475569;      /* Warna teks abu-abu tua */
    border-radius: 18px; /* Menyamakan dengan border-radius .btn-reset */
    padding: 16px;
    font-weight: 800;
    border: none;
    width: 100%;
    transition: 0.4s;
    margin-top: 12px;
    font-size: 15px;
    text-align: center;
    display: block;
    text-decoration: none;
}

.btn-gray:hover {
    background: #cbd5e1;
    color: #1e293b;
    transform: translateY(-3px); /* Efek melayang yang sama dengan tombol pink */
    box-shadow: 0 10px 25px rgba(0,0,0,0.06);
}
.password-wrapper {
    position: relative;
}
.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #94a3b8;
    font-size: 18px;
    z-index: 10;
    transition: 0.3s;
}
.toggle-password:hover {
    color: var(--p-pink);
}
/* Tambahkan padding kanan agar teks tidak tertutup ikon */
.form-control.pe-5 {
    padding-right: 45px !important;
}
    </style>
</head>
<body>

    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="reset-card">
            <!-- Link Beranda Kecil di Atas -->
            <a href="index.php" class="btn-home shadow-sm"><i class="bi bi-house-heart-fill"></i> Beranda</a>

            <div class="text-center mb-4">
                <div class="mb-3">
                    <div style="background: var(--s-pink); width: 80px; height: 80px; border-radius: 25px; display: flex; align-items: center; justify-content: center; margin: auto;">
                        <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem; color: var(--p-pink);"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1">Keamanan Akun</h3>
                <p class="text-muted small fw-500">Atur ulang kata sandi Anda secara akurat.</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-box"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email Terdaftar</label>
                    <input type="email" name="email" class="form-control" required placeholder="nama@email.com" value="<?= @$_POST['email'] ?>">
                </div>

                <div class="mb-3">
    <label class="form-label">Kata Sandi Baru</label>
    <div class="password-wrapper">
        <input type="password" name="password_baru" id="pass_baru" class="form-control pe-5" required placeholder="Minimal 8 karakter">
        <i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i>
    </div>
    
    <!-- Hint Aturan Password -->
    <div style="font-size: 10.5px; color: #94a3b8; font-weight: 700; margin-top: 10px; line-height: 1.5; display: flex; align-items: start; gap: 8px;">
        <i class="bi bi-info-circle-fill" style="color: var(--p-pink); font-size: 14px;"></i>
        <span>Wajib kombinasi minimal 8 karakter (Huruf, Angka, & Simbol).</span>
    </div>
</div>

                <div class="mb-4">
    <label class="form-label">Konfirmasi Kata Sandi Baru</label>
    <div class="password-wrapper">
        <input type="password" name="konfirmasi_password" id="pass_konf" class="form-control pe-5" required placeholder="Ulangi sandi baru">
        <i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i>
    </div>
</div>

                <!-- Tombol-Tombol Terintegrasi di Dalam Card -->
                <button type="submit" name="reset" class="btn btn-reset shadow-sm mb-3">
                    Perbarui Kata Sandi
                </button>
                
                <a href="login.php" class="btn-gray shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Login
                </a>
            </form>
        </div>
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
<script>
    // Fungsi untuk toggle password
    function initTogglePassword(buttonId, inputId) {
        const btn = document.getElementById(buttonId);
        const input = document.getElementById(inputId);

        btn.addEventListener('click', function() {
            // Ubah tipe input
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Ubah ikon mata
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    }

    // Jalankan fungsi untuk kedua input
    initTogglePassword('btnToggleBaru', 'pass_baru');
    initTogglePassword('btnToggleKonf', 'pass_konf');
</script>
</body>
</html>