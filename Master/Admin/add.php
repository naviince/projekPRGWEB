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
    // Sanitasi Input (Efisien & Akurat)
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $hp    = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);

    // 1. VALIDASI: Cek Duplikasi Email di tabel Parent (Users)
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email sudah terdaftar! Gunakan email lain untuk karyawan baru.";
    } else {
        // 2. MENGGUNAKAN TRANSACTION (Logika Akurat: Jika satu gagal, semua batal)
        sqlsrv_begin_transaction($conn);

        // A. Insert ke Tabel Users (Parent) - Role tetap Admin untuk akses sistem
        $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                 OUTPUT INSERTED.ID_User 
                 VALUES (?, ?, 'Admin', 'Active')";
        $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));

        if ($stmt1) {
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];

            // B. Insert ke Tabel Karyawan (Child)
            $sql2 = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Alamat, Foto_Profil) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, $alamat, 'default.jpg'));

            if ($stmt2) {
                sqlsrv_commit($conn); // Simpan permanen jika keduanya sukses
                $success = true;
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'list.php?msg=success_add';
                    }, 1500);
                </script>";
            } else {
                sqlsrv_rollback($conn); // Batalkan jika gagal di tabel Karyawan
                $error_general = "Gagal menyimpan profil karyawan.";
            }
        } else {
            sqlsrv_rollback($conn); // Batalkan jika gagal di tabel Users
            $error_general = "Gagal menyimpan akun user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Karyawan Baru – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        
        .main-card { 
            border: none; border-radius: 30px; overflow: hidden; background: white; 
            box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15); max-width: 1000px; margin: auto;
        }

        /* Sisi Kiri: Visual Modern */
        .side-visual {
            background: linear-gradient(135deg, rgba(232, 69, 122, 0.9), rgba(139, 26, 62, 0.9)), 
                        url('https://images.unsplash.com/photo-1590602847861-f357a9332bbc?q=80&w=1974&auto=format&fit=crop');
            background-size: cover; background-position: center; padding: 50px; color: white; display: flex; flex-direction: column; justify-content: space-between;
        }

        .side-visual h2 { font-weight: 800; font-size: 2.5rem; line-height: 1.2; }
        .feature-item { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 15px; backdrop-filter: blur(5px); }

        /* Sisi Kanan: Form Section */
        .form-section { padding: 50px; }
        .form-label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        
        .form-control {
            border-radius: 12px; padding: 12px 18px; border: 2px solid #f1f5f9; background: #f8fafc; transition: all 0.3s;
        }

        .form-control:focus { background: white; border-color: #e8457a; box-shadow: 0 0 0 4px rgba(232, 69, 122, 0.1); }
        .is-invalid-custom { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        .error-msg { color: #ef4444; font-size: 11px; margin-top: 5px; font-weight: 600; }

        .btn-simpan {
            background: linear-gradient(to right, #e8457a, #c73165); color: white; border: none; border-radius: 12px; padding: 15px;
            font-weight: 800; width: 100%; transition: all 0.3s; margin-top: 10px;
        }
        .btn-simpan:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(232, 69, 122, 0.4); }
        
        .back-link { color: #94a3b8; font-weight: 700; font-size: 13px; text-decoration: none; transition: 0.3s; }
        .back-link:hover { color: #e8457a; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Kiri (Visual & Marketing) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div>
                    <img src="../../assets/img/logo-white.png" height="40" class="mb-5" onerror="this.style.display='none'">
                    <h2>Grow our team, <br> <span style="color: #ffe0ec">SpotLight</span> Karyawan.</h2>
                </div>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="bi bi-shield-check-fill fs-4"></i>
                        <div class="small"><b>Keamanan Akun</b><br>Otoritas akses penuh ke sistem.</div>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-person-plus-fill fs-4"></i>
                        <div class="small"><b>Integrasi Otomatis</b><br>Akun dan Profil disinkronkan.</div>
                    </div>
                </div>
            </div>

            <!-- Sisi Kanan (Form) -->
            <div class="col-md-7 form-section">
                <h3 class="fw-bold text-dark mb-1">Registrasi Karyawan</h3>
                <p class="text-muted small mb-4">Lengkapi data autentikasi dan profil staf karyawan.</p>

                <form method="POST">
                    <div class="row">
                        <!-- Nama Lengkap -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama lengkap karyawan" required>
                        </div>

                        <!-- Email -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Karyawan</label>
                            <input type="email" name="email" 
                                   class="form-control <?= $error_email != "" ? 'is-invalid-custom' : '' ?>" 
                                   placeholder="karyawan@spotlight.com" required>
                            <?php if($error_email != ""): ?>
                                <div class="error-msg"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error_email ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- No HP & Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor WhatsApp</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Akses</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <!-- Alamat -->
                        <div class="col-md-12 mb-4">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."></textarea>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="simpan" class="btn btn-simpan shadow-sm mb-3">
                        <i class="bi bi-plus-circle-fill me-2"></i>Daftarkan Karyawan Baru
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
            title: 'Pendaftaran Sukses!',
            text: 'Akun dan profil karyawan telah berhasil disinkronkan.',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>