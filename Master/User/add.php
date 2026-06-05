<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$error_email = "";
$error_general = "";
$success = false;

if (isset($_POST['simpan'])) {
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $role   = $_POST['role'];
    $status = $_POST['status'];

    // 1. VALIDASI: Cek duplikasi email
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email ini sudah terdaftar di sistem!";
    } else {
        // 2. PROSES INSERT PARENT (Users)
        // Kita ambil ID_User yang baru saja dibuat menggunakan OUTPUT INSERTED
        $sql_user = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                     OUTPUT INSERTED.ID_User 
                     VALUES (?, ?, ?, ?)";
        $stmt_user = sqlsrv_query($conn, $sql_user, array($email, $pass, $role, $status));

        if ($stmt_user) {
            $row = sqlsrv_fetch_array($stmt_user, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];

            // 3. PROSES INSERT CHILD (Sinkronisasi Otomatis)
            // Logika: Jika Admin/Foto/Owner masuk ke Karyawan, jika Customer masuk ke Pelanggan
            if ($role == 'Customer') {
                $sql_child = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp) VALUES (?, ?, ?)";
                sqlsrv_query($conn, $sql_child, array($new_id, 'Pelanggan Baru', '-'));
            } else {
                $sql_child = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp) VALUES (?, ?, ?)";
                sqlsrv_query($conn, $sql_child, array($new_id, 'Staff Baru', '-'));
            }
            
            $success = true;
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'list.php?msg=added';
                }, 1500);
            </script>";
        } else {
            $error_general = "Terjadi kesalahan saat menyimpan ke database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Akun – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        
        .main-card { 
            border: none; 
            border-radius: 25px; 
            overflow: hidden; 
            background: white; 
            box-shadow: 0 20px 60px rgba(232, 69, 122, 0.1);
            max-width: 900px;
            margin: auto;
        }

        /* Sisi Kiri: Gambar/Visual */
        .side-image {
            background: linear-gradient(rgba(232, 69, 122, 0.8), rgba(139, 26, 62, 0.9)), 
                        url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            padding: 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .side-image h3 { font-weight: 800; font-size: 2rem; margin-bottom: 10px; }
        .side-image p { opacity: 0.8; font-size: 14px; line-height: 1.6; }

        /* Sisi Kanan: Form */
        .form-section { padding: 50px; }
        .form-label { font-weight: 700; font-size: 12px; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #f1f5f9;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: white;
            border-color: #e8457a;
            box-shadow: 0 0 0 4px rgba(232, 69, 122, 0.1);
        }

        /* Validasi Error Style */
        .is-invalid-custom { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        .error-msg { color: #ef4444; font-size: 11px; margin-top: 5px; font-weight: 600; }

        .btn-simpan {
            background: #e8457a;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 800;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-simpan:hover { background: #c73165; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(232, 69, 122, 0.3); }
        
        .badge-info { background: #fff1f2; color: #e11d48; padding: 10px 15px; border-radius: 10px; font-size: 11px; margin-bottom: 25px; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Kiri (Gambar & Info) -->
            <div class="col-md-5 side-image d-none d-md-flex">
                <div class="mb-5"><i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i></div>
                <h3>SpotLight Security</h3>
                <p>Menambahkan akun baru akan memberikan hak akses sistem kepada pengguna. Pastikan email yang digunakan valid dan role sesuai dengan tugasnya.</p>
            </div>

            <!-- Sisi Kanan (Form) -->
            <div class="col-md-7 form-section">
                <h4 class="fw-bold mb-1">Tambah Akun Baru</h4>
                <p class="text-muted small mb-4">Silakan isi kredensial login pengguna.</p>

                <div class="badge-info">
                    <i class="bi bi-info-circle-fill me-2"></i>Sistem akan otomatis membuatkan record biodata kosong setelah akun disimpan.
                </div>

                <form method="POST">
                    <!-- Email dengan Validasi Akurat -->
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" 
                               class="form-control <?= $error_email != "" ? 'is-invalid-custom' : '' ?>" 
                               placeholder="nama@gmail.com" 
                               value="<?= isset($_POST['email']) ? $_POST['email'] : '' ?>" required>
                        <?php if($error_email != ""): ?>
                            <div class="error-msg"><i class="bi bi-exclamation-circle me-1"></i><?= $error_email ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="row">
                        <!-- Role Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Akses</label>
                            <select name="role" class="form-select">
                                <option value="Admin">Admin</option>
                                <option value="Fotografer">Fotografer</option>
                                <option value="Owner">Owner</option>
                                <option value="Customer">Customer</option>
                            </select>
                        </div>
                        <!-- Status Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Akun</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mt-3"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="simpan" class="btn btn-simpan shadow-sm">
                        <i class="bi bi-check-circle-fill me-2"></i>Simpan Akun
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold">Batalkan dan Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Alert Sukses (SweetAlert2) -->
    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Akun dan record profil otomatis telah dibuat.',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>