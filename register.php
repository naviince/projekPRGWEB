<?php
session_start();
include 'koneksi.php';

// 1. Inisialisasi variabel error agar tidak Undefined
$error_nama = "";
$error_email = "";
$error_hp = "";
$success = false;

if (isset($_POST['register'])) {
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $hp    = trim($_POST['no_hp']);
    $pass  = $_POST['password'];

    // --- VALIDASI AKURAT & LOGIS (SERVER SIDE) ---

    // Validasi Nama: Hanya boleh huruf dan spasi
    if (empty($nama)) {
        $error_nama = "Nama lengkap wajib diisi!";
    } elseif (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    }

    // Validasi Nomor HP: Harus angka dan panjang logis (10-13 digit)
    if (empty($hp)) {
        $error_hp = "Nomor Telepon wajib diisi!";
    } elseif (!ctype_digit($hp)) {
        $error_hp = "Nomor Telepon harus berupa angka!";
    } elseif (strlen($hp) < 10 || strlen($hp) > 13) {
        $error_hp = "Nomor Telepon harus 10-13 digit!";
    }

    // Validasi Email
    if (empty($email)) {
        $error_email = "Email wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email salah!";
    }

    // 2. Jika tidak ada error validasi, lanjut cek database
    if ($error_nama == "" && $error_hp == "" && $error_email == "") {
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));

        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah digunakan pelanggan lain!";
        } else {
            // VALIDASI TRANSAKSI DATABASE (Akurat)
            sqlsrv_begin_transaction($conn);

            $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                     OUTPUT INSERTED.ID_User VALUES (?, ?, 'Customer', 'Active')";
            $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

            if ($stmt1) {
                $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
                $new_id = $row['ID_User'];

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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join SpotLight – Capture Your Story</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --pink-primary: #e8457a;
            --pink-dark: #c73165;
            --pink-soft: #fdf2f7;
            --glass: rgba(255, 255, 255, 0.15);
        }

        body { 
            background: #fdf2f7; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding: 20px;
        }

        .reg-card { 
            border-radius: 40px; 
            overflow: hidden; 
            background: white; 
            box-shadow: 0 30px 100px rgba(232, 69, 122, 0.12); 
            max-width: 1000px; 
            width: 100%;
            border: none;
        }

        /* Side Image Styling */
        .side-visual { 
            background: linear-gradient(135deg, rgba(232, 69, 122, 0.8), rgba(139, 26, 62, 0.9)), 
                        url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070'); 
            background-size: cover; 
            background-position: center; 
            color: white; 
            padding: 50px; 
            display: flex; 
            flex-direction: column; 
            justify-content: flex-end;
            position: relative;
        }

        .glass-box {
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Form Styling */
        .form-section { padding: 50px; }
        .form-label { font-weight: 700; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        
        .form-control { 
            border-radius: 15px; 
            padding: 14px 18px; 
            border: 2px solid #f1f5f9; 
            background: #f8fafc; 
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s; 
        }

        .form-control:focus { 
            border-color: var(--pink-primary); 
            background: white; 
            box-shadow: 0 10px 20px rgba(232, 69, 122, 0.05); 
        }

        .btn-reg { 
            background: linear-gradient(135deg, var(--pink-primary), var(--pink-dark)); 
            color: white; 
            border-radius: 16px; 
            padding: 16px; 
            font-weight: 800; 
            border: none; 
            width: 100%; 
            transition: all 0.4s; 
            margin-top: 20px;
            font-size: 15px;
        }

        .btn-reg:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 15px 30px rgba(232, 69, 122, 0.3); 
            filter: brightness(1.1);
        }

        /* Error States */
        .is-invalid { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 4px; }

        .login-link { color: var(--pink-primary); font-weight: 800; text-decoration: none; }
        .login-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="reg-card container-fluid">
        <div class="row g-0">
            <!-- Bagian Gambar (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div class="glass-box">
                    <h2 class="fw-bold mb-2">Abadikan Momen <br>Terbaikmu.</h2>
                    <p class="mb-0 opacity-90 small">Bergabunglah dengan ribuan pelanggan SpotLight Studio dan dapatkan akses tema eksklusif.</p>
                </div>
            </div>

            <!-- Bagian Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Daftar Pelanggan</h3>
                    <p class="text-muted small">Ciptakan akun untuk mulai booking sesi foto.</p>
                </div>
                
                <form method="POST" id="regForm">
                    <!-- NAMA LENGKAP -->
                    <div class="mb-3">
                        <label class="form-label">NAMA LENGKAP</label>
                        <input type="text" name="nama" id="inputNama"
                               class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" 
                               placeholder="Masukkan nama lengkap Anda" 
                               value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>" required>
                        <?php if($error_nama != ""): ?>
                            <div class="error-text"><i class="bi bi-exclamation-circle-fill"></i> <?= $error_nama ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- EMAIL -->
                    <div class="mb-3">
                        <label class="form-label">EMAIL</label>
                        <input type="email" name="email" 
                               class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" 
                               placeholder="nama@email.com" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        <?php if($error_email != ""): ?>
                            <div class="error-text"><i class="bi bi-exclamation-circle-fill"></i> <?= $error_email ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <!-- NO. HP -->
                        <div class="col-md-6 mb-3">
                        <label class="form-label">NOMOR TELEPON</label>
                            <input type="text" name="no_hp" id="inputHP" 
                                   class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" 
                                   placeholder="0812..." 
                                   value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '' ?>" required>
                            <?php if($error_hp != ""): ?>
                                <div class="error-text"><i class="bi bi-exclamation-circle-fill"></i> <?= $error_hp ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- KATA SANDI -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">KATA SANDI</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••">
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn btn-reg shadow-sm">
                        Yuk Buat Akun Sekarang ✨
                    </button>
                    
                    <div class="text-center mt-4">
                        <p class="small text-muted fw-500">Sudah punya akun? <a href="login.php" class="login-link">Masuk di sini</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VALIDASI JAVASCRIPT (Real-time Filtering) -->
    <script>
        // Mencegah input angka/simbol pada Nama (Logika: Nama = Huruf)
        document.getElementById('inputNama').oninput = function() {
            this.value = this.value.replace(/[^a-zA-Z ]/g, '');
        };

        // Mencegah input huruf pada No HP (Logika: HP = Angka)
        document.getElementById('inputHP').oninput = function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 13) this.value = this.value.slice(0, 13);
        };
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Bergabung!',
            text: 'Akun Anda sudah aktif. Silakan masuk untuk mulai booking.',
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