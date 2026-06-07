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

// 2. AMBIL DATA LAMA (JOIN Users & Karyawan)
$sql = "SELECT u.Email_User, u.Password_User, u.Status_User, k.Nama_Karyawan, k.No_Hp, k.Alamat 
        FROM Users u 
        JOIN Karyawan k ON u.ID_User = k.ID_User 
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
    $alamat = trim($_POST['alamat']);
    $status = $_POST['status'];

    // --- VALIDASI AKURAT (SERVER SIDE) ---
    
    // A. Validasi Nama
    if (!preg_match("/^[a-zA-Z ]*$/", $nama)) {
        $error_nama = "Nama hanya boleh berisi huruf!";
    } elseif (strlen($nama) < 3) {
        $error_nama = "Nama terlalu pendek (Min. 3 karakter)!";
    }

    // B. Validasi Email (Koreksi: Sekarang Sudah Ada)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    }

    // C. Validasi No HP
    if (!ctype_digit($hp)) {
        $error_hp = "Nomor telepon harus berupa angka!";
    } elseif (strlen($hp) < 10 || strlen($hp) > 13) {
        $error_hp = "Nomor telepon harus 10-13 digit!";
    }

    // D. Validasi Alamat
    if (strlen($alamat) < 10) {
        $error_alamat = "Mohon isi alamat lengkap (Min. 10 Karakter)!";
    }

    // 4. Jika validasi karakter lolos, cek duplikasi email di DB
    if ($error_nama == "" && $error_email == "" && $error_hp == "" && $error_alamat == "") {
        // Cek apakah email sudah dipakai orang lain
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah digunakan oleh karyawan lain!";
        } else {
            // MENGGUNAKAN TRANSACTION (Logika Bisnis Akurat)
            sqlsrv_begin_transaction($conn);

            $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Status_User = ? WHERE ID_User = ?";
            $res_u = sqlsrv_query($conn, $sql_u, array($email, $pass, $status, $id));

            $sql_k = "UPDATE Karyawan SET Nama_Karyawan = ?, No_Hp = ?, Alamat = ? WHERE ID_User = ?";
            $res_k = sqlsrv_query($conn, $sql_k, array($nama, $hp, $alamat, $id));

            if ($res_u && $res_k) {
                sqlsrv_commit($conn);
                $success = true;
                echo "<script>setTimeout(function(){ window.location.href='list.php?msg=success_edit'; }, 1500);</script>";
            } else {
                sqlsrv_rollback($conn);
                $error_general = "Gagal memperbarui database. Perubahan dibatalkan.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil Karyawan – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        .main-card { border: none; border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15); max-width: 1100px; margin: auto; animation: slideIn 0.7s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
        .side-visual { background: linear-gradient(135deg, rgba(139, 26, 62, 0.9), rgba(232, 69, 122, 0.8)), url('https://images.unsplash.com/photo-1497366216548-37526070297c'); background-size: cover; background-position: center; padding: 60px; color: white; display: flex; flex-direction: column; justify-content: space-between; }
        .form-section { padding: 50px 80px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.05); }
        .btn-update { background: linear-gradient(to right, #c73165, #e8457a); color: white; border: none; border-radius: 18px; padding: 16px; font-weight: 800; width: 100%; transition: 0.4s; margin-top: 20px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.2); }
        .btn-update:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.3); }
        .error-text { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Left Side -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div>
                    <h2 class="fw-bold mb-3 text-white">Update <br>Data Karyawan.</h2>
                    <p class="opacity-75 small">Perubahan data profil akan langsung menyelaraskan akses operasional sistem Spotlight Studio.</p>
                </div>
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 25px; border-radius: 25px; border: 1px solid rgba(255,255,255,0.2);">
                    <div class="small fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>Integritas Data:</div>
                    <div class="small opacity-90">Sistem menggunakan prosedur SQL Transaction untuk menjamin sinkronisasi akun.</div>
                </div>
            </div>

            <!-- Right Side (Form) -->
            <div class="col-md-7 form-section">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <h3 class="fw-bold text-dark mb-0">Edit Profil</h3>
                    <span class="badge bg-light text-muted border p-2 px-3" style="border-radius: 10px;">ID KARYAWAN #<?= $id ?></span>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- NAMA (Placeholder Ditambahkan) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap Karyawan</label>
                            <input type="text" name="nama" id="inputNama" class="form-control <?= $error_nama ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($data['Nama_Karyawan']) ?>" 
                                   placeholder="Masukkan nama lengkap karyawan" required>
                            <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                        </div>

                        <!-- EMAIL (Validasi & Placeholder Ditambahkan) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Instansi</label>
                            <input type="email" name="email" class="form-control <?= $error_email ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($data['Email_User']) ?>" 
                                   placeholder="contoh: karyawan@spotlight.com" required>
                            <?php if($error_email): ?><div class="error-text"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error_email ?></div><?php endif; ?>
                        </div>

                        <!-- HP (Placeholder Ditambahkan) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="no_hp" id="inputHP" class="form-control <?= $error_hp ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($data['No_Hp']) ?>" 
                                   placeholder="Contoh: 0812..." required>
                            <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                        </div>

                        <!-- PASSWORD (Placeholder Ditambahkan) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Login</label>
                            <input type="text" name="password" class="form-control" 
                                   value="<?= htmlspecialchars($data['Password_User']) ?>" 
                                   placeholder="Masukkan kata sandi baru" required>
                        </div>

                        <!-- ALAMAT (Placeholder Ditambahkan) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Alamat Domisili Lengkap</label>
                            <textarea name="alamat" class="form-control <?= $error_alamat ? 'is-invalid' : '' ?>" rows="2" 
                                      placeholder="Masukkan alamat lengkap (Provinsi, Kota, Kecamatan)" required><?= htmlspecialchars($data['Alamat']) ?></textarea>
                            <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-12 mb-4">
                            <label class="form-label">Status Akses Sistem</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active (Aktif)</option>
                                <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive (Non-aktif)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update" class="btn btn-update shadow-sm">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Perubahan Karyawan
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
        document.getElementById('inputHP').oninput = function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 13) this.value = this.value.slice(0, 13);
        };
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Update Berhasil!', text: 'Profil karyawan telah diperbarui secara akurat.', showConfirmButton: false, timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>