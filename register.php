<?php
session_start();
include 'koneksi.php';

// 1. Inisialisasi variabel error agar tidak Undefined
$error_nama = ""; $error_email = ""; $error_hp = ""; $error_alamat = "";
$success = false;

if (isset($_POST['register'])) {
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $hp     = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $pass   = $_POST['password'];

    // --- VALIDASI SERVER SIDE (AKURAT & LOGIS) ---
    if (empty($nama)) { $error_nama = "Nama lengkap wajib diisi!"; } 
    elseif (!preg_match("/^[a-zA-Z ]*$/", $nama)) { $error_nama = "Nama hanya boleh berisi huruf!"; }

    if (empty($hp)) { $error_hp = "Nomor telepon wajib diisi!"; } 
    elseif (!ctype_digit($hp)) { $error_hp = "Nomor telepon harus berupa angka!"; } 
    elseif (strlen($hp) < 10 || strlen($hp) > 13) { $error_hp = "Nomor telepon harus 10-13 digit!"; }

    if (empty($email)) { $error_email = "Email wajib diisi!"; } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error_email = "Format email salah!"; }

    if (empty($alamat)) { $error_alamat = "Alamat pengiriman wajib diisi!"; }

    // 2. Jika validasi lolos, cek duplikasi & simpan
    if ($error_nama == "" && $error_hp == "" && $error_email == "" && $error_alamat == "") {
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));

        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah terdaftar!";
        } else {
            sqlsrv_begin_transaction($conn);
            $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                     OUTPUT INSERTED.ID_User VALUES (?, ?, 'Customer', 'Active')";
            $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

            if ($stmt1) {
                $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
                $new_id = $row['ID_User'];
                $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
                $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, $alamat));

                if ($stmt2) {
                    sqlsrv_commit($conn);
                    $success = true;
                } else { sqlsrv_rollback($conn); }
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
    <title>Daftar Pelanggan – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; --glass: rgba(255, 255, 255, 0.18); }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        
        .reg-card { border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 30px 100px rgba(232, 69, 122, 0.15); max-width: 1050px; width: 100%; border: none; position: relative; animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Tombol Kembali yang Keren */
        .btn-home { position: absolute; top: 25px; right: 30px; z-index: 10; background: white; border: 1.5px solid var(--s-pink); padding: 10px 22px; border-radius: 50px; font-size: 13px; font-weight: 800; color: var(--p-pink); text-decoration: none; box-shadow: 0 10px 20px rgba(0,0,0,0.05); transition: 0.4s; display: flex; align-items: center; gap: 8px; }
        .btn-home:hover { background: var(--p-pink); color: white; transform: translateX(-8px); }

        .side-visual { background: linear-gradient(135deg, rgba(232, 69, 122, 0.85), rgba(139, 26, 62, 0.95)), url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070'); background-size: cover; background-position: center; color: white; padding: 60px; display: flex; flex-direction: column; justify-content: flex-end; }
        .glass-overlay { background: var(--glass); backdrop-filter: blur(15px); padding: 35px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.25); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }

        .form-section { padding: 50px 60px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.08); }

        .btn-reg { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 18px; padding: 18px; font-weight: 800; border: none; width: 100%; transition: 0.4s; margin-top: 20px; font-size: 16px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.3); }
        .btn-reg:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.4); filter: brightness(1.1); }

        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
        .login-link { color: var(--p-pink); font-weight: 800; text-decoration: none; border-bottom: 2px solid transparent; transition: 0.3s; }
        .login-link:hover { border-bottom-color: var(--p-pink); }
    </style>
</head>
<body>

    <div class="reg-card container-fluid">
        <!-- Tombol Home -->
        <a href="index.php" class="btn-home"><i class="bi bi-house-heart-fill"></i> Beranda</a>

        <div class="row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div class="glass-overlay">
                    <h2 class="fw-bold mb-3 text-white">Abadikan Momen <br>Terbaikmu.</h2>
                    <p class="mb-0 opacity-90 small" style="line-height: 1.7;">Daftarkan diri Anda hari ini dan nikmati pengalaman fotografi profesional dengan tema eksklusif di SpotLight Studio.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Daftar Pelanggan</h3>
                    <p class="text-muted small fw-500">Ciptakan akun untuk akses penuh layanan studio.</p>
                </div>
                
                <form method="POST">
                    <div class="row">
                        <!-- NAMA -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Anda</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan nama lengkap" value="<?= @$_POST['nama'] ?>" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" placeholder="nama@email.com" value="<?= @$_POST['email'] ?>" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- HP & SANDI -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="no_hp" id="inputHP" class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" placeholder="08..." value="<?= @$_POST['no_hp'] ?>" required>
                            <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kata Sandi</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••">
                        </div>

                        <!-- ALAMAT (DENGAN DESIGN LEGA) -->
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control <?= ($error_alamat != '') ? 'is-invalid' : '' ?>" rows="2" placeholder="Masukkan alamat untuk keperluan data pelanggan" required><?= @$_POST['alamat'] ?></textarea>
                            <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn btn-reg shadow-sm">
                        Yuk Buat Akun Sekarang ✨
                    </button>
                    
                    <div class="text-center mt-4">
                        <p class="small text-muted fw-600">Sudah punya akun? <a href="login.php" class="login-link">Masuk di sini</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT FILTER REAL-TIME (EFISIEN) -->
    <script>
        // Filter Nama: Hapus apapun yang bukan huruf/spasi secara real-time
        document.getElementById('inputNama').oninput = function() {
            this.value = this.value.replace(/[^a-zA-Z ]/g, '');
        };

        // Filter HP: Hapus apapun yang bukan angka secara real-time
        document.getElementById('inputHP').oninput = function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 13) this.value = this.value.slice(0, 13);
        };
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Registrasi Berhasil!', text: 'Akun anda telah aktif. Selamat datang di SpotLight!', confirmButtonColor: '#e8457a'
        }).then(() => { window.location = 'login.php'; });
    </script>
    <?php endif; ?>
</body>
</html>