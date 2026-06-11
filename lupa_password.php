<?php
session_start();
include 'koneksi.php';

$error = "";
$success = false;

if (isset($_POST['reset'])) {
    $user_input      = trim($_POST['username_email']);
    $pass_baru      = $_POST['password_baru'];
    $konfirmasi_pass = $_POST['konfirmasi_password'];

    // --- JALANKAN VALIDASI KOMPLEKSITAS SANDI BARU ---
    if (empty($user_input)) {
        $error = "Nama pengguna atau email wajib diisi!";
    } elseif (strlen($pass_baru) < 8) {
        $error = "Kata sandi minimal harus 8 karakter!";
    } elseif (!preg_match("/[A-Za-z]/", $pass_baru) || 
              !preg_match("/[0-9]/", $pass_baru) || 
              !preg_match("/[^A-Za-z0-9]/", $pass_baru)) {
        $error = "Kata sandi harus kombinasi Huruf, Angka, dan Simbol!";
    } elseif ($pass_baru !== $konfirmasi_pass) {
        $error = "Konfirmasi kata sandi tidak cocok!";
    } else {
        
        // --- PROSES VALIDASI DATABASE MULTI-TABEL (SINKRON STEP 12) ---
        // 1. Periksa ke tabel Pelanggan terlebih dahulu
        $sql_cust = "SELECT ID_Pelanggan, Email_Pelanggan, Password_Pelanggan FROM Pelanggan WHERE (Email_Pelanggan = ? OR Username_Pelanggan = ?) AND Is_Deleted = 0";
        $stmt_cust = sqlsrv_query($conn, $sql_cust, array($user_input, $user_input));
        
        if ($stmt_cust === false) { die(print_r(sqlsrv_errors(), true)); }
        $user_cust = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC);

        if ($user_cust) {
            // Validasi: Larang sandi baru sama dengan sandi lama
            if ($user_cust['Password_Pelanggan'] === $pass_baru) {
                $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
            } else {
                // Lakukan pembaruan langsung di tabel Pelanggan
                $sql_upd_cust = "UPDATE Pelanggan SET Password_Pelanggan = ? WHERE ID_Pelanggan = ?";
                $stmt_upd_cust = sqlsrv_query($conn, $sql_upd_cust, array($pass_baru, $user_cust['ID_Pelanggan']));
                if ($stmt_upd_cust) {
                    $success = true;
                } else {
                    $error = "Terjadi kesalahan sistem saat memperbarui kata sandi.";
                }
            }
        } else {
            // 2. Jika tidak ada di Pelanggan, periksa ke tabel Karyawan
            $sql_karyawan = "SELECT ID_Karyawan, Email_Karyawan, Password_Karyawan FROM Karyawan WHERE (Email_Karyawan = ? OR Username_Karyawan = ?) AND Is_Deleted = 0";
            $stmt_karyawan = sqlsrv_query($conn, $sql_karyawan, array($user_input, $user_input));
            
            if ($stmt_karyawan === false) { die(print_r(sqlsrv_errors(), true)); }
            $user_karyawan = sqlsrv_fetch_array($stmt_karyawan, SQLSRV_FETCH_ASSOC);

            if ($user_karyawan) {
                // Validasi: Larang sandi baru sama dengan sandi lama
                if ($user_karyawan['Password_Karyawan'] === $pass_baru) {
                    $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
                } else {
                    // Lakukan pembaruan langsung di tabel Karyawan
                    $sql_upd_karyawan = "UPDATE Karyawan SET Password_Karyawan = ? WHERE ID_Karyawan = ?";
                    $stmt_upd_karyawan = sqlsrv_query($conn, $sql_upd_karyawan, array($pass_baru, $user_karyawan['ID_Karyawan']));
                    if ($stmt_upd_karyawan) {
                        $success = true;
                    } else {
                        $error = "Terjadi kesalahan sistem saat memperbarui kata sandi.";
                    }
                }
            } else {
                // KUNCI PRIVASI: Menyamarkan kegagalan pencarian menjadi "tidak valid" secara umum
                $error = "Nama pengguna atau email tidak valid!";
            }
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
    
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --p-pink: #d83f67; 
            --d-pink: #c73165; 
            --s-pink: #fff5f6; 
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --glass: rgba(255, 255, 255, 0.94); 
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body { 
            background: linear-gradient(135deg, rgba(30, 31, 34, 0.85), rgba(216, 63, 103, 0.45)), 
                        url('assets/img/login/ruangan1.jpg') no-repeat center center fixed; 
            background-size: cover; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 40px 20px; 
            position: relative;
            perspective: 2000px; 
            overflow-x: hidden;
        }

        /* Kartu Lupa Sandi 3D Glassmorphism */
        .reset-card { 
            background: var(--glass); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 35px; 
            padding: 50px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08), 
                        0 25px 60px rgba(216, 63, 103, 0.2), 
                        inset 0 1px 0 rgba(255,255,255,0.4); 
            width: 100%; 
            max-width: 480px; 
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative; 
            animation: slideUp 0.6s ease-out; 
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.3s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        /* Tombol Beranda Kapsul Rapi */
        .btn-kembali-beranda {
            position: absolute; top: 25px; right: 25px; z-index: 100;
            background: #ffffff; border: 1.5px solid var(--p-pink); padding: 6px 14px;
            border-radius: 50px; font-size: 11px; font-weight: 800; color: var(--p-pink);
            text-decoration: none; box-shadow: 0 4px 10px rgba(216, 63, 103, 0.1);
            transition: var(--transition-3d); display: flex; align-items: center; gap: 4px;
        }
        .btn-kembali-beranda:hover {
            background: var(--p-pink); color: #ffffff; transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(216, 63, 103, 0.2);
        }

        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        
        /* Input & Dropdown */
        .form-control { 
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; 
            background: #f8fafc; font-size: 14px; font-weight: 600; 
            transition: var(--transition-3d); color: var(--text-dark); 
        }
        .form-control:focus { 
            border-color: var(--p-pink); background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); outline: none;
        }

        /* Grup Sandi (Bundling Efek 3D Agar Ikon Mata Tidak Bergeser) */
        .password-group { 
            position: relative; 
            transition: var(--transition-3d);
            border-radius: 14px;
        }
        .password-group:focus-within {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15);
        }
        .password-group .form-control {
            transition: border-color 0.3s ease, background-color 0.3s ease; 
        }
        .password-group .form-control:focus {
            transform: none !important; 
            box-shadow: none !important;
            background: #ffffff;
            border-color: var(--p-pink);
        }

        /* Ikon Mata */
        .toggle-password {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s;
        }
        .toggle-password:hover { color: var(--p-pink); }
        .form-control.pe-5 { padding-right: 45px !important; }

        /* Tombol Aksi 3D */
        .btn-reset { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; 
            border-radius: 16px; padding: 16px; font-weight: 800; border: none; 
            width: 100%; transition: var(--transition-3d); margin-top: 15px; 
            font-size: 15px; box-shadow: 0 10px 25px rgba(216, 63, 103, 0.25); 
        }
        .btn-reset:hover { 
            transform: translateY(-4px) scale(1.01); 
            box-shadow: 0 15px 35px rgba(216, 63, 103, 0.35); 
        }

        /* Tombol Kembali Abu-abu 3D */
        .btn-gray {
            background: #e2e8f0; color: #475569; border-radius: 16px; 
            padding: 16px; font-weight: 800; border: none; width: 100%; 
            transition: var(--transition-3d); margin-top: 12px; font-size: 15px;
            text-align: center; display: block; text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .btn-gray:hover {
            background: #cbd5e1; color: #1e293b;
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="reset-card">
            <!-- Tombol Beranda Kapsul Rapi -->
            <a href="index.php" class="btn-kembali-beranda shadow-sm"><i class="bi bi-house-door-fill"></i> Beranda</a>

            <div class="text-center mb-4">
                <div class="mb-3">
                    <div style="background: var(--s-pink); width: 80px; height: 80px; border-radius: 25px; display: flex; align-items: center; justify-content: center; margin: auto; box-shadow: inset 0 2px 5px rgba(216,63,103,0.05);">
                        <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem; color: var(--p-pink);"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-1">Keamanan Akun</h3>
                <p class="text-muted small fw-500">Atur ulang kata sandi Anda secara akurat.</p>
            </div>

            <form method="POST">
                <!-- NAMA PENGGUNA / EMAIL -->
                <div class="mb-3">
                    <label class="form-label">Nama Pengguna / Email Terdaftar</label>
                    <!-- Input value bertipe teks biasa untuk fleksibilitas username/email sesuai analisis pablo_21 -->
                    <input type="text" name="username_email" class="form-control" required placeholder="nama@email.com atau username" value="<?= @$_POST['username_email'] ?>">
                </div>

                <!-- SANDI BARU -->
                <div class="mb-3">
                    <label class="form-label">Kata Sandi Baru</label>
                    <div class="password-group">
                        <input type="password" name="password_baru" id="pass_baru" class="form-control pe-5" required placeholder="Minimal 8 karakter" value="<?= htmlspecialchars(@$_POST['password_baru']) ?>">
                        <i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i>
                    </div>
                    
                    <!-- Hint Aturan Password -->
                    <div style="font-size: 10.5px; color: #94a3b8; font-weight: 700; margin-top: 10px; line-height: 1.5; display: flex; align-items: start; gap: 8px;">
                        <i class="bi bi-info-circle-fill" style="color: var(--p-pink); font-size: 14px;"></i>
                        <span>Wajib kombinasi minimal 8 karakter (Huruf, Angka, & Simbol).</span>
                    </div>
                </div>

                <!-- VERIFIKASI SANDI BARU -->
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                    <div class="password-group">
                        <input type="password" name="konfirmasi_password" id="pass_konf" class="form-control pe-5" required placeholder="Ulangi sandi baru" value="<?= htmlspecialchars(@$_POST['konfirmasi_password']) ?>">
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

    <!-- Script JavaScript Terpadu (Anti-crash & 3D Mouse Tilt Lembut Skala 6) -->
    <script>
        // Setup Toggle Password (Mencegah Bug Ikon Mata Melayang Terpisah)
        function initTogglePassword(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('bi-eye'); this.classList.toggle('bi-eye-slash');
                });
            }
        }
        initTogglePassword('btnToggleBaru', 'pass_baru');
        initTogglePassword('btnToggleKonf', 'pass_konf');

        // Efek Interaktif Mouse-Tilt 3D Lembut (Sensitivitas Skala 6)
        const resetCard = document.querySelector('.reset-card');
        if (resetCard) {
            document.addEventListener('mousemove', (e) => {
                const { clientX, clientY } = e;
                const { innerWidth, innerHeight } = window;
                const xRotation = ((clientY / innerHeight) - 0.5) * -6; 
                const yRotation = ((clientX / innerWidth) - 0.5) * 6;  
                resetCard.style.transform = `rotateX(${xRotation}deg) rotateY(${yRotation}deg)`;
            });
            document.addEventListener('mouseleave', () => {
                resetCard.style.transform = `rotateX(0deg) rotateY(0deg)`; 
            });
        }
    </script>

    <!-- SweetAlert Berhasil Update (Bahasa Indonesia) -->
    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Sandi Diperbarui! 🎉',
            text: 'Sekarang Anda bisa masuk menggunakan sandi yang baru.',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Masuk Sekarang'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = 'login.php';
            }
        });
    </script>
    <?php endif; ?>

    <!-- SweetAlert Gagal Update Dengan Logika Keamanan Privasi (Bebas dari Broken Email Info) -->
    <?php if($error != ""): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Pembaruan Gagal! ❌',
            text: '<?= $error ?>',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Coba Lagi'
        });
    </script>
    <?php endif; ?>
</body>
</html>