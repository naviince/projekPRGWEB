<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman: Hanya Admin yang bisa menambah pelanggan dari sini
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 1. Inisialisasi variabel error agar tidak Undefined
$error_nama = ""; $error_email = ""; $error_hp = ""; $error_alamat = ""; $error_general = "";
$success = false;

if (isset($_POST['simpan'])) {
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $hp     = trim($_POST['no_hp']); // Input dari form, misal: "+62 895..."
    $alamat = trim($_POST['alamat']);

    // --- VALIDASI AKURAT & LOGIS (SERVER SIDE) ---
    
    // Validasi Nama: Hanya boleh huruf dan spasi
    if (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    }

    // --- MODIFIKASI VALIDASI NOMOR HP (STEP 1) ---
    // 1. Kita bersihkan dulu karakter '+' dan ' ' (spasi) agar sisa angka saja
    $hp_clean = str_replace(['+', ' '], '', $hp); 

    // 2. Cek apakah sisanya benar-benar angka
    if (!ctype_digit($hp_clean)) {
        $error_hp = "Nomor Telepon harus berupa angka!";
    } 
    // 3. Cek panjang logis: 62 (2 digit) + nomor HP (9-12 digit) = 11-14 digit
    elseif (strlen($hp_clean) < 11 || strlen($hp_clean) > 14) {
        $error_hp = "Nomor Telepon tidak valid (Harus 11-14 digit termasuk 62)!";
    }

    // Validasi Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    }

    // Validasi Alamat: Minimal 10 karakter agar akurat sesuai proses bisnis
    if (strlen($alamat) < 10) {
        $error_alamat = "Mohon isi alamat lengkap (Min. 10 Karakter)!";
    }

    // --- CATATAN PENTING ---
    // Saat insert ke database nanti, pastikan pakai variabel $hp_clean 
    // supaya yang tersimpan angkanya saja (contoh: 628951234567)

    // 2. Jika validasi lolos, cek duplikasi email di database
    if ($error_nama == "" && $error_hp == "" && $error_email == "" && $error_alamat == "") {
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));

        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah terdaftar di sistem!";
        } else {
            // 3. VALIDASI TRANSAKSI DATABASE (Akurat & Aman)
            sqlsrv_begin_transaction($conn);

            // A. Insert ke tabel Users
            $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                     OUTPUT INSERTED.ID_User VALUES (?, ?, 'Customer', 'Active')";
            $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

            if ($stmt1) {
                $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
                $new_id = $row['ID_User'];

                // B. Insert ke tabel Pelanggan
                $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat, Foto_Profil) 
                         VALUES (?, ?, ?, ?, 'default.jpg')";
                $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, $alamat));

                if ($stmt2) {
                    sqlsrv_commit($conn); // Simpan permanen jika keduanya sukses
                    $success = true;
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'list.php?msg=success_add';
                        }, 1500);
                    </script>";
                } else {
                    sqlsrv_rollback($conn); // Batalkan jika profil gagal
                    $error_general = "Gagal menyimpan biodata pelanggan.";
                }
            } else {
                sqlsrv_rollback($conn); // Batalkan jika akun gagal
                $error_general = "Kesalahan sistem saat membuat akun.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Pelanggan Baru – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        
        .main-card { border: none; border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15); max-width: 1050px; margin: auto; animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Sisi Kiri: Visual Modern */
        .side-visual { background: linear-gradient(135deg, rgba(232, 69, 122, 0.85), rgba(139, 26, 62, 0.95)), url('https://images.unsplash.com/photo-1516035069371-29a1b244cc32'); background-size: cover; background-position: center; padding: 60px; color: white; display: flex; flex-direction: column; justify-content: space-between; }
        .glass-box { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); padding: 30px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); }

        /* Sisi Kanan: Form Section */
        .form-section { padding: 50px 70px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.05); }

        .btn-simpan { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border: none; border-radius: 18px; padding: 16px; font-weight: 800; border: none; width: 100%; transition: 0.4s; margin-top: 15px; font-size: 16px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.3); }
        .btn-simpan:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.4); filter: brightness(1.1); }

        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div>
                    <h2 class="fw-bold mb-4" style="font-size: 2.8rem; line-height: 1.1;">Welcome to <br><span style="color: #ffe0ec">SpotLight</span> Family.</h2>
                    <p class="opacity-75">Manajemen data pelanggan membantu studio dalam mengelola riwayat booking dan pengiriman hasil foto cetak secara akurat.</p>
                </div>
                <div class="glass-box">
                    <p class="mb-0 small" style="line-height: 1.7;"><i class="bi bi-info-circle-fill me-2"></i>Sistem secara otomatis membuat akun login dengan role "Customer" setelah data profil disimpan.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Tambah Pelanggan</h3>
                    <p class="text-muted small fw-500">Daftarkan profil customer baru ke dalam sistem.</p>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- NAMA LENGKAP -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Customer</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan nama (Huruf saja)" value="<?= @$_POST['nama'] ?>" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Aktif</label>
                            <input type="email" name="email" class="form-control <?= ($error_email != '') ? 'is-invalid' : '' ?>" placeholder="customer@email.com" value="<?= @$_POST['email'] ?>" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- WHATSAPP & PASSWORD -->
                        <div class="col-md-6 mb-3">
    <label class="form-label">Nomor Telepon</label>
    <input type="text" name="no_hp" id="inputHP" 
           class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" 
           value="<?= isset($_POST['no_hp']) ? $_POST['no_hp'] : '+62 ' ?>" required>
    <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
</div>
                        <div class="col-md-6 mb-3">
    <label class="form-label">Kata Sandi</label>
    <div class="position-relative">
        <input type="password" name="password" id="inputPass" class="form-control" placeholder="••••••••" required>
        <!-- Ikon Mata -->
        <i class="bi bi-eye-slash position-absolute top-50 end-0 translate-middle-y me-3 text-muted" 
           id="togglePassword" 
           style="cursor: pointer; z-index: 10;"></i>
    </div>
</div>
                        <!-- ALAMAT (VALIDASI MIN 10 KARAKTER) -->
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat" class="form-control <?= ($error_alamat != '') ? 'is-invalid' : '' ?>" rows="2" placeholder="Masukkan alamat lengkap (Min. 10 Karakter)..." required><?= @$_POST['alamat'] ?></textarea>
                            <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                        </div>
                    </div>

                    <?php if($error_general): ?>
                        <div class="alert alert-danger py-2 small rounded-3 mb-3 fw-bold"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="simpan" class="btn btn-simpan shadow-sm">
                        Simpan Data Pelanggan ✨
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="list.php" class="text-muted small fw-bold text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT VALIDASI REAL-TIME -->
   <script>
    document.getElementById('inputNama').oninput = function() { 
        this.value = this.value.replace(/[^a-zA-Z ]/g, ''); 
    };

    const inputHP = document.getElementById('inputHP');
    const prefix = '+62 ';

    inputHP.oninput = function() {
        // Cegah user menghapus prefix "+62 "
        if (!this.value.startsWith(prefix)) {
            this.value = prefix;
        }

        // Hanya izinkan angka setelah prefix
        let parts = this.value.split(prefix);
        let digits = parts[1].replace(/[^0-9]/g, '');
        
        // Batasi maksimal 13 digit angka setelah prefix (Total 17 karakter termasuk +62 )
        if (digits.length > 13) {
            digits = digits.slice(0, 13);
        }

        this.value = prefix + digits;
    };

    // Mencegah kursor diletakkan di tengah prefix saat diklik
    inputHP.onclick = function() {
        if (this.selectionStart < prefix.length) {
            this.setSelectionRange(prefix.length, prefix.length);
        }
    };
    // SCRIPT SHOW/HIDE PASSWORD
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#inputPass');

togglePassword.addEventListener('click', function () {
    // Toggle tipe input (password ke text atau sebaliknya)
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    // Toggle ikon (bi-eye-slash ke bi-eye)
    this.classList.toggle('bi-eye');
    this.classList.toggle('bi-eye-slash');
});
</script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!', text: 'Data Pelanggan telah disinkronkan ke database.', confirmButtonColor: '#e8457a'
        }).then(() => { window.location = 'list.php'; });
    </script>
    <?php endif; ?>

</body>
</html>