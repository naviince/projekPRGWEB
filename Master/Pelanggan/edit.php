<?php
session_start();
include '../../koneksi.php';

// 1. Proteksi Halaman & Akses
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $_GET['id'];
$error_nama = ""; $error_email = ""; $error_hp = ""; $error_alamat = ""; $error_general = "";
$success = false;

// 2. AMBIL DATA LAMA (JOIN Users & Pelanggan)
$sql = "SELECT u.Email_User, u.Password_User, u.Status_User, p.Nama_Pelanggan, p.No_Hp, p.Alamat 
        FROM Users u 
        JOIN Pelanggan p ON u.ID_User = p.ID_User 
        WHERE u.ID_User = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php");
    exit();
}

// 3. PROSES UPDATE SAAT TOMBOL DIKLIK
if (isset($_POST['update'])) {
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $hp     = trim($_POST['no_hp']); 
    
    // --- STEP 1: MEMBERSIHKAN NOMOR HP UNTUK VALIDASI ---
    $hp_clean = str_replace(['+', ' '], '', $hp); 

    $alamat = trim($_POST['alamat']);
    $status = $_POST['status'];

    // --- VALIDASI LOGIS & AKURAT (SERVER SIDE) ---
    if (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    } elseif (strlen($nama) < 3) {
        $error_nama = "Nama terlalu pendek!";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    }

    if (!ctype_digit($hp_clean)) {
        $error_hp = "Nomor telepon harus berupa angka!";
    } elseif (strlen($hp_clean) < 11 || strlen($hp_clean) > 14) {
        $error_hp = "Nomor telepon tidak valid (Minimal 9 angka setelah +62)!";
    }

    if (strlen($alamat) < 10) {
        $error_alamat = "Mohon isi alamat lengkap (Min. 10 Karakter)!";
    }

    // --- EKSEKUSI DATABASE ---
    if ($error_nama == "" && $error_email == "" && $error_hp == "" && $error_alamat == "") {
        
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah digunakan oleh pengguna lain!";
        } else {
            sqlsrv_begin_transaction($conn);

            $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Status_User = ? WHERE ID_User = ?";
            $res_u = sqlsrv_query($conn, $sql_u, array($email, $pass, $status, $id));

            $sql_p = "UPDATE Pelanggan SET Nama_Pelanggan = ?, No_Hp = ?, Alamat = ? WHERE ID_User = ?";
            $res_p = sqlsrv_query($conn, $sql_p, array($nama, $hp, $alamat, $id));

            if ($res_u && $res_p) {
                sqlsrv_commit($conn);
                $success = true;
                echo "<script>setTimeout(function(){ window.location.href='list.php?msg=success_edit'; }, 1500);</script>";
            } else {
                sqlsrv_rollback($conn);
                $error_general = "Gagal memperbarui data database.";
            }
        }
    }
} // Tanda tutup ini sangat penting!
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil Pelanggan – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        
        .main-card { border: none; border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15); max-width: 1100px; margin: auto; animation: slideIn 0.7s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }

        .side-visual { background: linear-gradient(135deg, rgba(139, 26, 62, 0.9), rgba(232, 69, 122, 0.8)), url('https://images.unsplash.com/photo-1516035069371-29a1b244cc32'); background-size: cover; background-position: center; padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center; }
        
        .form-section { padding: 50px 80px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.05); }

        .btn-update { background: linear-gradient(to right, #c73165, #e8457a); color: white; border: none; border-radius: 18px; padding: 16px; font-weight: 800; width: 100%; transition: 0.4s; margin-top: 20px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.2); }
        .btn-update:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.3); }

        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
        /* Styling untuk Ikon Mata di Input Password */
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
/* Tambahkan padding kanan pada input agar teks tidak tertutup ikon */
.input-password-custom {
    padding-right: 45px !important;
}
.btn-gray {
    background: #e2e8f0;
    color: #475569;
    border-radius: 18px;
    padding: 16px;
    font-weight: 800;
    border: none;
    width: 100%;
    transition: 0.4s;
    margin-top: 12px;
    font-size: 16px;
    text-align: center;
    display: block;
    text-decoration: none;
}
.btn-gray:hover {
    background: #cbd5e1;
    color: #1e293b;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.06);
}
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(15px); padding: 35px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.25);">
                    <h2 class="fw-bold mb-3">Update Profil Pelanggan</h2>
                    <p class="opacity-90 small mb-0">Lakukan pembaruan data customer secara akurat untuk mempermudah koordinasi sesi foto dan pengiriman barang cetak.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <h3 class="fw-bold text-dark mb-0">Edit Biodata</h3>
                    <span class="badge bg-light text-muted border p-2 px-3" style="border-radius: 10px;">CUSTOMER #<?= $id ?></span>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- NAMA -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Customer</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= $error_nama ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($data['Nama_Pelanggan']) ?>" placeholder="Masukkan nama (Huruf saja)" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Pelanggan</label>
                            <input type="email" name="email" class="form-control <?= $error_email ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($data['Email_User']) ?>" placeholder="nama@email.com" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- WHATSAPP & PASSWORD -->
                        <div class="col-md-6 mb-3">
    <label class="form-label">Nomor Telepon</label>
    <?php 
        // Konversi otomatis format lama (08...) ke format baru (+62 )
        $phone_val = htmlspecialchars($data['No_Hp']);
        if (str_starts_with($phone_val, '0')) {
            $phone_val = '+62 ' . substr($phone_val, 1);
        } elseif (!str_starts_with($phone_val, '+62 ')) {
            $phone_val = '+62 ' . $phone_val;
        }
    ?>
    <input type="text" name="no_hp" id="inputHP" class="form-control <?= $error_hp ? 'is-invalid' : '' ?>" 
           value="<?= $phone_val ?>" required>
    <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
</div>
                        <div class="col-md-6 mb-3">
    <label class="form-label">Kata Sandi Akses</label>
    <div class="password-wrapper">
        <input type="password" name="password" id="inputPass" 
               class="form-control input-password-custom" 
               value="<?= htmlspecialchars($data['Password_User']) ?>" 
               placeholder="Masukkan sandi baru" required>
        <!-- Ikon Mata -->
        <i class="bi bi-eye-slash toggle-password" id="btnToggle"></i>
    </div>
</div>

                        <!-- ALAMAT (DENGAN VALIDASI AKURAT) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control <?= $error_alamat ? 'is-invalid' : '' ?>" rows="2" placeholder="Provinsi, Kota, Kecamatan, No Rumah..." required><?= htmlspecialchars($data['Alamat']) ?></textarea>
                            <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-12 mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active (Bisa Login)</option>
                                <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive (Blokir Akses)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update" class="btn btn-update shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i>Simpan Perubahan Customer
</button>
                    
                    <div class="text-center mt-4">
                        <div class="text-center mt-4">
    <a href="list.php" class="btn-gray shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
    </a>
</div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT VALIDASI REAL-TIME -->
<script>
    // 1. Validasi Nama (Hanya Huruf)
    document.getElementById('inputNama').oninput = function() {
        this.value = this.value.replace(/[^a-zA-Z ]/g, '');
    };

    // 2. LOGIKA KUNCI PREFIX +62 (PERMANEN)
    const inputHP = document.getElementById('inputHP');
    const prefix = '+62 ';

    function moveCursorToEnd() {
        if (inputHP.selectionStart < prefix.length) {
            inputHP.setSelectionRange(prefix.length, prefix.length);
        }
    }

    inputHP.addEventListener('mousedown', () => setTimeout(moveCursorToEnd, 1));
    inputHP.addEventListener('focus', moveCursorToEnd);
    inputHP.addEventListener('keyup', moveCursorToEnd);

    inputHP.addEventListener('keydown', function(e) {
        if (this.selectionStart <= prefix.length) {
            if (e.keyCode === 8 || e.keyCode === 46) {
                e.preventDefault();
            }
        }
    });

    inputHP.addEventListener('input', function() {
        if (!this.value.startsWith(prefix)) {
            let remainingDigits = this.value.replace(/[^0-9]/g, '').substring(2); 
            this.value = prefix + remainingDigits;
        }
        let parts = this.value.split(prefix);
        let digits = parts[1].replace(/[^0-9]/g, '');
        if (digits.length > 13) digits = digits.slice(0, 13);
        this.value = prefix + digits;
    });

    // 3. SHOW/HIDE PASSWORD
    const btnToggle = document.querySelector('#btnToggle');
    const inputPass = document.querySelector('#inputPass');
    btnToggle.addEventListener('click', function() {
        const type = inputPass.getAttribute('type') === 'password' ? 'text' : 'password';
        inputPass.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
</script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!', text: 'Profil pelanggan telah diperbarui secara akurat.', showConfirmButton: false, timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>