<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman: Sangat Ketat
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

    // 1. VALIDASI SERVER-SIDE (Akurat & Logis)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    } else {
        // Cek duplikasi email
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah digunakan oleh akun lain!";
        } else {
            // 2. MENGGUNAKAN TRANSACTION (Integritas Data Tinggi)
            sqlsrv_begin_transaction($conn);

            // A. INSERT PARENT: Users
            $sql_user = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                         OUTPUT INSERTED.ID_User 
                         VALUES (?, ?, ?, ?)";
            $stmt_user = sqlsrv_query($conn, $sql_user, array($email, $pass, $role, $status));

            if ($stmt_user) {
                $row = sqlsrv_fetch_array($stmt_user, SQLSRV_FETCH_ASSOC);
                $new_id = $row['ID_User'];

                // B. INSERT CHILD: Pelanggan atau Karyawan (Sinkronisasi Otomatis)
                if ($role == 'Customer') {
                    $sql_child = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
                    $params_child = array($new_id, 'Pelanggan Baru', '-', '-');
                } else {
                    $sql_child = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
                    $params_child = array($new_id, 'Staf Baru', '-', '-');
                }
                
                $stmt_child = sqlsrv_query($conn, $sql_child, $params_child);

                // C. FINALISASI: Commit jika semua sukses
                if ($stmt_child) {
                    sqlsrv_commit($conn);
                    $success = true;
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'list.php?msg=added';
                        }, 1500);
                    </script>";
                } else {
                    sqlsrv_rollback($conn);
                    $error_general = "Gagal menyinkronkan profil. Data dibatalkan.";
                }
            } else {
                sqlsrv_rollback($conn);
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
    <title>Master User – Tambah Akun Akurat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --p-pink: #e8457a;
            --d-pink: #c73165;
            --bg-body: #fdf2f7;
        }

        body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        
        .main-card { 
            border: none; 
            border-radius: 35px; 
            overflow: hidden; 
            background: white; 
            box-shadow: 0 25px 80px rgba(232, 69, 122, 0.15);
            max-width: 1000px;
            margin: auto;
        }

        /* Sisi Kiri: Visual Modern dengan Gradient Overlay */
        .side-image {
            background: linear-gradient(135deg, rgba(232, 69, 122, 0.9), rgba(139, 26, 62, 0.9)), 
                        url('https://images.unsplash.com/photo-1542038784456-1ea8e935640e?q=80&w=2070');
            background-size: cover;
            background-position: center;
            padding: 60px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
        }

        .glass-overlay {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .side-image h3 { font-weight: 800; font-size: 2.2rem; line-height: 1.2; margin-bottom: 15px; }

        /* Sisi Kanan: Form Section */
        .form-section { padding: 60px; }
        .form-label { font-weight: 700; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        
        .form-control, .form-select {
            border-radius: 16px;
            padding: 14px 20px;
            border: 2px solid #f1f5f9;
            background: #f8fafc;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control:focus, .form-select:focus {
            background: white;
            border-color: var(--p-pink);
            box-shadow: 0 10px 20px rgba(232, 69, 122, 0.05);
        }

        .btn-simpan {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: white;
            border: none;
            border-radius: 16px;
            padding: 16px;
            font-weight: 800;
            width: 100%;
            transition: all 0.4s;
            margin-top: 25px;
            box-shadow: 0 10px 25px rgba(232, 69, 122, 0.3);
        }
        .btn-simpan:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(232, 69, 122, 0.4); filter: brightness(1.1); }
        
        .is-invalid-custom { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
        .error-msg { color: #ef4444; font-size: 11px; margin-top: 6px; font-weight: 700; display: flex; align-items: center; gap: 5px; }

        .info-badge { background: #eff6ff; color: #1e40af; padding: 12px 20px; border-radius: 15px; font-size: 12px; font-weight: 600; border-left: 5px solid #3b82f6; }
    </style>
</head>
<body>

    <div class="container py-4">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-image d-none d-md-flex">
                <div class="glass-overlay">
                    <div class="mb-4 text-white"><i class="bi bi-shield-lock-fill" style="font-size: 3.5rem;"></i></div>
                    <h3>Identity & <br>Akses Kontrol</h3>
                    <p class="mb-0 opacity-90 small">Membuat akun baru akan memicu pembuatan profil otomatis pada database sesuai dengan peran yang dipilih.</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="mb-4">
                    <h3 class="fw-bold text-dark mb-1">Tambah User</h3>
                    <p class="text-muted small">Ciptakan kredensial baru untuk sistem SpotLight.</p>
                </div>

                <div class="info-badge mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>Sistem menjamin konsistensi data antara Akun dan Profil Biodata.
                </div>

                <form method="POST">
                    <!-- Email dengan Validasi Akurat -->
                    <div class="mb-4">
                        <label class="form-label">Email / Username Login</label>
                        <input type="email" name="email" 
                               class="form-control <?= $error_email != "" ? 'is-invalid-custom' : '' ?>" 
                               placeholder="user@spotlight.com" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        <?php if($error_email != ""): ?>
                            <div class="error-msg"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error_email ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label class="form-label">Kata Sandi Sementara</label>
                        <div class="input-group">
                            <input type="password" name="password" id="passInput" class="form-control border-end-0" placeholder="••••••••" required>
                            <span class="input-group-text bg-light border-start-0" style="border-radius: 0 16px 16px 0; cursor: pointer;" onclick="togglePass()">
                                <i class="bi bi-eye-slash" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Role Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Kontrol</label>
                            <select name="role" class="form-select">
                                <option value="Admin">Admin</option>
                                <option value="Fotografer">Fotografer</option>
                                <option value="Owner">Owner (Pemilik)</option>
                                <option value="Customer">Pelanggan</option>
                            </select>
                        </div>
                        <!-- Status Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Awal Akun</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mt-3 border-0 rounded-4 fw-bold"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="simpan" class="btn btn-simpan">
                        <i class="bi bi-check-circle-fill me-2"></i>Simpan User Baru
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar User</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Script Tampilan & Validasi -->
    <script>
        function togglePass() {
            const pass = document.getElementById('passInput');
            const icon = document.getElementById('toggleIcon');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                pass.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'User Terdaftar!',
            text: 'Akun dan Profil telah disinkronkan secara akurat.',
            showConfirmButton: false,
            timer: 2000
        });
    </script>
    <?php endif; ?>

</body>
</html>