<?php
session_start();
include '../../koneksi.php';

// 1. Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 2. Inisialisasi variabel error agar tidak Undefined
$error_nama = ""; $error_email = ""; $error_hp = ""; $error_general = "";
$success = false;

if (isset($_POST['simpan'])) {
    // Sanitasi & Input
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $hp     = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $role   = $_POST['role_user']; // Dinamis: Admin/Fotografer/Owner

    // --- VALIDASI AKURAT & LOGIS (SERVER SIDE) ---
    
    // Validasi Nama: Hanya huruf dan spasi
    if (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    }

    // Validasi No HP: Harus angka dan 10-13 digit
    if (!ctype_digit($hp)) {
        $error_hp = "Nomor WhatsApp harus berupa angka!";
    } elseif (strlen($hp) < 10 || strlen($hp) > 13) {
        $error_hp = "Nomor HP harus 10-13 digit!";
    }

    // Validasi Email Format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email instansi tidak valid!";
    }

    // Jika tidak ada error validasi karakter
    if ($error_nama == "" && $error_hp == "" && $error_email == "") {
        
        // Cek Duplikasi Email di database
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email sudah terdaftar untuk staf lain!";
        } else {
            // 3. MENGGUNAKAN TRANSACTION (Logika Bisnis Akurat)
            sqlsrv_begin_transaction($conn);

            // A. Insert ke Tabel Users (Parent)
            $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                     OUTPUT INSERTED.ID_User 
                     VALUES (?, ?, ?, 'Active')";
            $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass, $role));

            if ($stmt1) {
                $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
                $new_id = $row['ID_User'];

                // B. Insert ke Tabel Karyawan (Child)
                $sql2 = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Alamat, Foto_Profil) 
                         VALUES (?, ?, ?, ?, 'default.jpg')";
                $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, $alamat));

                if ($stmt2) {
                    sqlsrv_commit($conn); // Sukses sinkronisasi
                    $success = true;
                } else {
                    sqlsrv_rollback($conn);
                    $error_general = "Gagal menyimpan biodata karyawan.";
                }
            } else {
                sqlsrv_rollback($conn);
                $error_general = "Gagal membuat akun sistem.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Karyawan – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        
        .main-card { border: none; border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15); max-width: 1050px; margin: auto; animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Sisi Kiri: Visual Premium */
        .side-visual { background: linear-gradient(135deg, rgba(232, 69, 122, 0.85), rgba(139, 26, 62, 0.95)), url('https://images.unsplash.com/photo-1590602847861-f357a9332bbc?q=80&w=1974'); background-size: cover; background-position: center; padding: 60px; color: white; display: flex; flex-direction: column; justify-content: space-between; }
        .glass-box { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); padding: 30px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); }

        /* Sisi Kanan: Form */
        .form-section { padding: 50px 70px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus, .form-select:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.05); }

        .btn-simpan { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 18px; padding: 16px; font-weight: 800; border: none; width: 100%; transition: 0.4s; margin-top: 15px; font-size: 16px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.3); }
        .btn-simpan:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.4); filter: brightness(1.1); }

        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Visual Side -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div>
                    <img src="../../assets/img/logo-white.png" height="40" class="mb-5" onerror="this.style.display='none'">
                    <h2 class="fw-bold mb-4" style="font-size: 2.8rem; line-height: 1.1;">Grow the <br><span style="color: #ffe0ec">SpotLight</span> Team.</h2>
                </div>
                <div class="glass-box">
                    <p class="mb-0 small" style="line-height: 1.7;">Menambah karyawan baru akan memberikan akses operasional sesuai tugasnya. Pastikan profil dan role disinkronkan dengan benar pada database.</p>
                </div>
            </div>

            <!-- Form Side -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Registrasi Karyawan</h3>
                    <p class="text-muted small fw-500">Lengkapi kredensial akun dan biodata staf.</p>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- NAMA -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Karyawan</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan nama lengkap karyawan" value="<?= @$_POST['nama'] ?>" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Instansi / Login</label>
                            <input type="email" name="email" class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" placeholder="karyawan@spotlight.com" value="<?= @$_POST['email'] ?>" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- NO HP & ROLE -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="no_hp" id="inputHP" class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" placeholder="08..." value="<?= @$_POST['no_hp'] ?>" required>
                            <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Pekerjaan</label>
                            <select name="role_user" class="form-select" required>
                                <option value="Admin">Admin / Staff</option>
                                <option value="Fotografer">Fotografer</option>
                                <option value="Owner">Owner</option>
                            </select>
                        </div>

                        <!-- PASSWORD & ALAMAT -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Kata Sandi Akses Sementara</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat lengkap karyawan..."><?= @$_POST['alamat'] ?></textarea>
                        </div>
                    </div>

                    <?php if($error_general): ?>
                        <div class="alert alert-danger py-2 small rounded-3 mb-3 fw-bold"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="simpan" class="btn btn-simpan shadow-sm">
                        Daftarkan Staf Baru ✨
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="list.php" class="text-muted small fw-bold text-decoration-none">Batalkan & Kembali ke Daftar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT FILTER REAL-TIME -->
    <script>
        document.getElementById('inputNama').oninput = function() {
            this.value = this.value.replace(/[^a-zA-Z ]/g, '');
        };
        document.getElementById('inputHP').oninput = function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 13) this.value = this.value.slice(0, 13);
        };
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!', text: 'Akun dan Profil Karyawan telah disinkronkan.', confirmButtonColor: '#e8457a'
        }).then(() => { window.location = 'list.php'; });
    </script>
    <?php endif; ?>

</body>
</html>