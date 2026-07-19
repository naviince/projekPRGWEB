<?php
session_start();
include 'koneksi.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Step management
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 2) $step = 1;

$error = "";
$success = false;
$verified_user = null;
$verified_type = ''; // 'pelanggan' or 'karyawan'

// ============================================================
// STEP 1: VERIFIKASI IDENTITAS (Email/Username)
// ============================================================
if (isset($_POST['verify']) && $step == 1) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Sesi tidak valid. Silakan refresh halaman.";
    } else {
        $user_input = trim($_POST['username_email'] ?? '');

        if (empty($user_input)) {
            $error = "Nama pengguna atau email wajib diisi!";
        } else {
            // Cek Pelanggan dulu
            $sql_cust = "SELECT ID_Pelanggan, Nama_Pelanggan, Email_Pelanggan, Username_Pelanggan, Password_Pelanggan 
                        FROM Pelanggan 
                        WHERE (Email_Pelanggan = ? OR Username_Pelanggan = ?) AND Is_Deleted = 0 AND Status = 1";
            $stmt_cust = sqlsrv_query($conn, $sql_cust, [$user_input, $user_input]);

            if ($stmt_cust === false) { die(print_r(sqlsrv_errors(), true)); }
            $user_cust = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC);

            if ($user_cust) {
                // Simpan data verifikasi di session (sementara)
                $_SESSION['reset_id'] = $user_cust['ID_Pelanggan'];
                $_SESSION['reset_type'] = 'pelanggan';
                $_SESSION['reset_name'] = $user_cust['Nama_Pelanggan'];
                $_SESSION['reset_email'] = $user_cust['Email_Pelanggan'];
                $_SESSION['reset_password'] = $user_cust['Password_Pelanggan']; // untuk cek tidak sama dengan yang lama

                // Redirect ke step 2
                header("Location: lupa_password.php?step=2");
                exit();
            } else {
                // Cek Karyawan
                $sql_karyawan = "SELECT ID_Karyawan, Nama_Karyawan, Email_Karyawan, Username_Karyawan, Password_Karyawan, Role_Karyawan
                                FROM Karyawan 
                                WHERE (Email_Karyawan = ? OR Username_Karyawan = ?) AND Is_Deleted = 0 AND Status = 1";
                $stmt_karyawan = sqlsrv_query($conn, $sql_karyawan, [$user_input, $user_input]);

                if ($stmt_karyawan === false) { die(print_r(sqlsrv_errors(), true)); }
                $user_karyawan = sqlsrv_fetch_array($stmt_karyawan, SQLSRV_FETCH_ASSOC);

                if ($user_karyawan) {
                    $_SESSION['reset_id'] = $user_karyawan['ID_Karyawan'];
                    $_SESSION['reset_type'] = 'karyawan';
                    $_SESSION['reset_name'] = $user_karyawan['Nama_Karyawan'];
                    $_SESSION['reset_email'] = $user_karyawan['Email_Karyawan'];
                    $_SESSION['reset_password'] = $user_karyawan['Password_Karyawan'];
                    $_SESSION['reset_role'] = $user_karyawan['Role_Karyawan'];

                    header("Location: lupa_password.php?step=2");
                    exit();
                } else {
                    // PRIVASI: Pesan samar — tidak reveal apakah email ada atau tidak
                    $error = "Nama pengguna atau email tidak valid. Silakan periksa kembali.";
                }
            }
        }
    }
}

// ============================================================
// STEP 2: RESET PASSWORD BARU
// ============================================================
if (isset($_POST['reset_password']) && $step == 2) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Sesi tidak valid. Silakan refresh halaman.";
    } elseif (!isset($_SESSION['reset_id']) || !isset($_SESSION['reset_type'])) {
        // Session habis atau invalid — kembali ke step 1
        header("Location: lupa_password.php?step=1&error=session_expired");
        exit();
    } else {
        $pass_baru = $_POST['password_baru'] ?? '';
        $konfirmasi_pass = $_POST['konfirmasi_password'] ?? '';
        $old_password_hash = $_SESSION['reset_password'] ?? '';

        // --- VALIDASI PASSWORD BARU ---
        if (empty($pass_baru)) {
            $error = "Kata sandi baru wajib diisi!";
        } elseif (strlen($pass_baru) < 8) {
            $error = "Kata sandi minimal 8 karakter!";
        } elseif (!preg_match("/[A-Za-z]/", $pass_baru)) {
            $error = "Kata sandi harus mengandung huruf!";
        } elseif (!preg_match("/[0-9]/", $pass_baru)) {
            $error = "Kata sandi harus mengandung angka!";
        } elseif (!preg_match("/[^A-Za-z0-9]/", $pass_baru)) {
            $error = "Kata sandi harus mengandung simbol!";
        } elseif ($pass_baru === $konfirmasi_pass && password_verify($pass_baru, $old_password_hash)) {
            // Cek dengan hash (untuk password yang sudah di-hash)
            $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama!";
        } elseif ($pass_baru === $konfirmasi_pass && $pass_baru === $old_password_hash) {
            // Cek dengan plaintext (untuk password lama)
            $error = "Kata sandi baru tidak boleh sama dengan kata sandi lama!";
        } elseif ($pass_baru !== $konfirmasi_pass) {
            $error = "Konfirmasi kata sandi tidak cocok!";
        } else {
            // HASH password baru
            $pass_hash = password_hash($pass_baru, PASSWORD_BCRYPT);

            $reset_id = $_SESSION['reset_id'];
            $reset_type = $_SESSION['reset_type'];

            if ($reset_type == 'pelanggan') {
                $sql_upd = "UPDATE Pelanggan SET Password_Pelanggan = ?, Modified_Date = GETDATE() WHERE ID_Pelanggan = ?";
            } else {
                $sql_upd = "UPDATE Karyawan SET Password_Karyawan = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
            }

            $stmt_upd = sqlsrv_query($conn, $sql_upd, [$pass_hash, $reset_id]);

            if ($stmt_upd) {
                $success = true;
                // Hapus session reset
                unset($_SESSION['reset_id']);
                unset($_SESSION['reset_type']);
                unset($_SESSION['reset_name']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_password']);
                unset($_SESSION['reset_role']);
            } else {
                $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
            }
        }
    }
}

// Handle session expired error
if (isset($_GET['error']) && $_GET['error'] == 'session_expired') {
    $error = "Sesi telah berakhir. Silakan mulai dari awal.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi - SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --transition-soft: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #d83f67 100%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        /* Floating particles */
        .particles {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 0; overflow: hidden;
        }
        .particle {
            position: absolute; width: 8px; height: 8px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
            animation: float 15s infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; } 90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* Main card */
        .reset-card {
            position: relative;
            width: 100%; max-width: 460px;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 45px 40px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
            z-index: 10;
            animation: slideUp 0.6s ease-out;
            transition: var(--transition-soft);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Progress steps */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
        }
        .step-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition-soft);
        }
        .step-dot.active {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: white;
            box-shadow: 0 4px 15px rgba(216,63,103,0.3);
        }
        .step-dot.inactive {
            background: #f1f5f9;
            color: #94a3b8;
        }
        .step-line {
            width: 40px; height: 3px;
            background: #f1f5f9;
            border-radius: 3px;
            transition: var(--transition-soft);
        }
        .step-line.active {
            background: linear-gradient(90deg, var(--p-pink), var(--d-pink));
        }
        .step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-align: center;
            margin-top: 6px;
        }
        .step-label.active {
            color: var(--p-pink);
        }

        /* Header */
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, var(--s-pink), #ffe4e9);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(216,63,103,0.15);
        }
        .reset-icon i {
            font-size: 2rem;
            color: var(--p-pink);
        }
        .reset-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .reset-subtitle {
            font-size: 0.9rem;
            color: #8a99a8;
            line-height: 1.6;
        }

        /* Input styles */
        .input-group-custom {
            margin-bottom: 20px;
        }
        .input-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #8a99a8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .input-label .required {
            color: #ef4444;
            margin-left: 2px;
        }
        .input-field {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #eef2f6;
            border-radius: 16px;
            background: #f8fafc;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-dark);
            transition: var(--transition-soft);
            outline: none;
        }
        .input-field:focus {
            border-color: var(--p-pink);
            background: white;
            box-shadow: 0 0 0 4px rgba(216,63,103,0.1), 0 8px 25px rgba(216,63,103,0.1);
            transform: translateY(-2px);
        }
        .input-field::placeholder {
            color: #cbd5e1;
        }
        .input-field.is-invalid {
            border-color: #ef4444;
            background: #fff1f2;
        }

        /* Password group */
        .password-wrap {
            position: relative;
        }
        .password-wrap .input-field {
            padding-right: 50px;
        }
        .toggle-eye {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 1.2rem;
            transition: 0.3s;
        }
        .toggle-eye:hover {
            color: var(--p-pink);
        }

        /* Password strength */
        .password-hint {
            display: flex;
            align-items: start;
            gap: 8px;
            margin-top: 10px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 12px;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }
        .password-hint i {
            color: var(--p-pink);
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-soft);
            box-shadow: 0 10px 30px rgba(216,63,103,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-submit:hover::after {
            left: 100%;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(216,63,103,0.4);
        }
        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-back {
            width: 100%;
            padding: 14px;
            background: #f1f5f9;
            color: #64748b;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            transition: var(--transition-soft);
        }
        .btn-back:hover {
            background: #e2e8f0;
            color: var(--text-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        /* Home button */
        .btn-home {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 2px solid #eef2f6;
            border-radius: 50px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition-soft);
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .btn-home:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216,63,103,0.15);
        }

        /* User info card (step 2) */
        .user-info-card {
            background: linear-gradient(135deg, #fff5f6, #ffe4e9);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid rgba(216,63,103,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-info-avatar {
            width: 50px; height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .user-info-text {
            flex: 1;
        }
        .user-info-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
        .user-info-email {
            font-size: 0.8rem;
            color: #8a99a8;
            margin-top: 2px;
        }

        /* Error message */
        .error-msg {
            color: #ef4444;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Loading */
        .btn-submit.loading {
            pointer-events: none;
        }
        .btn-submit.loading .btn-text {
            opacity: 0;
        }
        .btn-submit.loading::before {
            content: '';
            position: absolute;
            width: 24px; height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive */
        @media (max-width: 576px) {
            .reset-card {
                padding: 30px 25px;
                border-radius: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Particles -->
    <div class="particles" id="particles"></div>

    <!-- Main Card -->
    <div class="reset-card">
        <a href="index.php" class="btn-home">
            <i class="bi bi-house-door"></i> Beranda
        </a>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="text-center">
                <div class="step-dot <?= $step >= 1 ? 'active' : 'inactive' ?>">1</div>
                <div class="step-label <?= $step >= 1 ? 'active' : '' ?>">Verifikasi</div>
            </div>
            <div class="step-line <?= $step >= 2 ? 'active' : '' ?>"></div>
            <div class="text-center">
                <div class="step-dot <?= $step >= 2 ? 'active' : 'inactive' ?>">2</div>
                <div class="step-label <?= $step >= 2 ? 'active' : '' ?>">Reset</div>
            </div>
        </div>

        <?php if ($step == 1): ?>
        <!-- STEP 1: VERIFIKASI -->
        <div class="reset-header">
            <div class="reset-icon">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h2 class="reset-title">Lupa Kata Sandi? 🔐</h2>
            <p class="reset-subtitle">Masukkan email atau username terdaftar Anda. Kami akan membantu Anda mengatur ulang kata sandi dengan aman.</p>
        </div>

        <form method="POST" id="formVerify">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="input-group-custom">
                <label class="input-label">Email atau Username <span class="required">*</span></label>
                <input type="text" name="username_email" class="input-field" placeholder="nama@email.com atau username" value="<?= htmlspecialchars(@$_POST['username_email'] ?? '') ?>" required>
            </div>

            <button type="submit" name="verify" class="btn-submit" onclick="showLoading(this)">
                <span class="btn-text">Verifikasi Akun <i class="bi bi-arrow-right"></i></span>
            </button>

            <a href="login.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali ke Login
            </a>
        </form>

        <?php elseif ($step == 2): ?>
        <!-- STEP 2: RESET PASSWORD -->
        <div class="reset-header">
            <div class="reset-icon">
                <i class="bi bi-key"></i>
            </div>
            <h2 class="reset-title">Atur Ulang Sandi 🔑</h2>
            <p class="reset-subtitle">Buat kata sandi baru yang kuat untuk melindungi akun Anda.</p>
        </div>

        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="user-info-avatar">
                <?= $_SESSION['reset_type'] == 'pelanggan' ? '👤' : '👨‍💼' ?>
            </div>
            <div class="user-info-text">
                <div class="user-info-name"><?= htmlspecialchars($_SESSION['reset_name'] ?? '') ?></div>
                <div class="user-info-email"><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></div>
            </div>
        </div>

        <form method="POST" id="formReset">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="input-group-custom">
                <label class="input-label">Kata Sandi Baru <span class="required">*</span></label>
                <div class="password-wrap">
                    <input type="password" name="password_baru" id="passBaru" class="input-field" placeholder="Minimal 8 karakter" required>
                    <i class="bi bi-eye-slash toggle-eye" onclick="togglePassword('passBaru', this)"></i>
                </div>
                <div class="password-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Kata sandi harus minimal 8 karakter dengan kombinasi huruf, angka, dan simbol. Tidak boleh sama dengan sandi lama.</span>
                </div>
            </div>

            <div class="input-group-custom">
                <label class="input-label">Konfirmasi Kata Sandi <span class="required">*</span></label>
                <div class="password-wrap">
                    <input type="password" name="konfirmasi_password" id="passKonf" class="input-field" placeholder="Ulangi kata sandi baru" required>
                    <i class="bi bi-eye-slash toggle-eye" onclick="togglePassword('passKonf', this)"></i>
                </div>
            </div>

            <button type="submit" name="reset_password" class="btn-submit" onclick="showLoading(this)">
                <span class="btn-text">Perbarui Kata Sandi <i class="bi bi-check-lg"></i></span>
            </button>

            <a href="lupa_password.php?step=1" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali ke Verifikasi
            </a>
        </form>
        <?php endif; ?>
    </div>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Particles
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 15; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (10 + Math.random() * 10) + 's';
            particle.style.width = particle.style.height = (5 + Math.random() * 10) + 'px';
            particlesContainer.appendChild(particle);
        }

        // Toggle password
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        }

        // Loading state
        function showLoading(btn) {
            btn.classList.add('loading');
            setTimeout(() => {
                btn.classList.remove('loading');
            }, 3000);
        }

        // SweetAlert Success
        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Yeay, Berhasil! 🎉',
            text: 'Kata sandi Anda telah diperbarui. Silakan masuk dengan kata sandi baru.',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Masuk Sekarang 🚀',
            backdrop: 'rgba(216, 63, 103, 0.2)',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = 'login.php';
        });
        <?php endif; ?>

        // SweetAlert Error
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Ups, Ada Masalah! 😢',
            text: '<?= addslashes($error) ?>',
            confirmButtonColor: '#d83f67',
            confirmButtonText: 'Oke, Dicek Lagi',
            backdrop: 'rgba(216, 63, 103, 0.2)'
        });
        <?php endif; ?>
    </script>
</body>
</html>