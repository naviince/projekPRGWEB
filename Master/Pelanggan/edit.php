<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman: Hanya Admin yang boleh akses Master Pelanggan
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Validasi ID
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $_GET['id'];
$error_email = "";
$error_general = "";
$success = false;

// 1. AMBIL DATA LAMA (JOIN Users & Pelanggan)
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

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $hp     = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $status = $_POST['status'];

    // VALIDASI AKURAT: Cek jika email sudah dipakai user lain (Kecuali ID ini sendiri)
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email ini sudah terdaftar oleh pengguna lain!";
    } else {
        // MENGGUNAKAN TRANSACTION (Logika Bisnis Akurat: Users & Pelanggan harus sinkron)
        sqlsrv_begin_transaction($conn);

        // A. Update Tabel Users (Parent)
        $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Status_User = ? WHERE ID_User = ?";
        $params_u = array($email, $pass, $status, $id);
        $res_u = sqlsrv_query($conn, $sql_u, $params_u);

        // B. Update Tabel Pelanggan (Child)
        $sql_p = "UPDATE Pelanggan SET Nama_Pelanggan = ?, No_Hp = ?, Alamat = ? WHERE ID_User = ?";
        $params_p = array($nama, $hp, $alamat, $id);
        $res_p = sqlsrv_query($conn, $sql_p, $params_p);

        if ($res_u && $res_p) {
            sqlsrv_commit($conn); // Sukses Semua
            $success = true;
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'list.php?msg=success_edit';
                }, 1500);
            </script>";
        } else {
            sqlsrv_rollback($conn); // Gagal Salah Satu, Batalkan Semua
            $error_general = "Terjadi kesalahan sistem saat memperbarui data pelanggan.";
        }
    }
}
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
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        
        .main-card { 
            border: none; border-radius: 30px; overflow: hidden; background: white; 
            box-shadow: 0 25px 80px rgba(232, 69, 122, 0.1); max-width: 1000px; margin: auto;
        }

        /* Sisi Kiri: Visual Modern */
        .side-visual {
            background: linear-gradient(135deg, rgba(232, 69, 122, 0.9), rgba(139, 26, 62, 0.9)), 
                        url('https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=1938&auto=format&fit=crop');
            background-size: cover; background-position: center; padding: 50px; color: white; display: flex; flex-direction: column; justify-content: center;
        }

        .side-visual h2 { font-weight: 800; font-size: 2.3rem; }
        .side-visual p { opacity: 0.8; font-size: 14px; line-height: 1.6; }

        /* Sisi Kanan: Form Section */
        .form-section { padding: 50px; }
        .form-label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        
        .form-control, .form-select {
            border-radius: 12px; padding: 12px 18px; border: 2px solid #f1f5f9; background: #f8fafc; transition: 0.3s;
        }

        .form-control:focus { background: white; border-color: #e8457a; box-shadow: 0 0 0 4px rgba(232, 69, 122, 0.1); }
        .is-invalid { border-color: #ef4444 !important; }
        
        .btn-update {
            background: linear-gradient(to right, #e8457a, #c73165); color: white; border: none; border-radius: 12px; padding: 15px;
            font-weight: 800; width: 100%; transition: 0.3s; margin-top: 10px;
        }
        .btn-update:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(232, 69, 122, 0.3); color: white; }
        
        .back-link { color: #94a3b8; font-weight: 700; font-size: 13px; text-decoration: none; transition: 0.3s; }
        .back-link:hover { color: #e8457a; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Kiri -->
            <div class="col-md-5 side-visual d-none d-md-flex text-center">
                <div class="mb-4"><i class="bi bi-people-fill" style="font-size: 4rem;"></i></div>
                <h2 class="mb-3">Update Profil Pelanggan</h2>
                <p>Pastikan data pelanggan diperbarui dengan benar. Email dan password yang diubah akan menjadi akses login baru bagi pelanggan tersebut.</p>
            </div>

            <!-- Sisi Kanan -->
            <div class="col-md-7 form-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold text-dark mb-0">Edit Data Customer</h4>
                    <span class="badge bg-light text-dark border p-2 px-3" style="border-radius: 8px;">Customer ID #<?= $id ?></span>
                </div>

                <form method="POST">
                    <div class="row">
                        <!-- Nama Lengkap -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" value="<?= $data['Nama_Pelanggan'] ?>" required>
                        </div>

                        <!-- Email -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Autentikasi</label>
                            <input type="email" name="email" 
                                   class="form-control <?= $error_email != "" ? 'is-invalid' : '' ?>" 
                                   value="<?= $data['Email_User'] ?>" required>
                            <?php if($error_email != ""): ?>
                                <div class="text-danger fw-bold mt-1" style="font-size: 11px;"><i class="bi bi-exclamation-circle me-1"></i><?= $error_email ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Nomor HP & Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor WhatsApp</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= $data['No_Hp'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Login</label>
                            <input type="text" name="password" class="form-control" value="<?= $data['Password_User'] ?>" required title="Ubah jika ingin mengganti password pelanggan">
                        </div>

                        <!-- Alamat -->
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Alamat Lengkap</label>
                            <input type="text" name="alamat" class="form-control" value="<?= $data['Alamat'] ?>">
                        </div>

                        <!-- Status -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Status Akun</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="update" class="btn btn-update shadow-sm mb-3">
                        <i class="bi bi-save-fill me-2"></i>Simpan Perubahan
                    </button>
                    
                    <div class="text-center">
                        <a href="list.php" class="back-link"><i class="bi bi-arrow-left me-1"></i> Batalkan & Kembali ke Daftar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 Sukses -->
    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Data profil pelanggan telah diperbarui.',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>