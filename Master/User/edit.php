<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI HALAMAN (Sangat Ketat)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Validasi keberadaan ID
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $_GET['id'];
$error_email = "";
$error_general = "";
$success = false;

// 2. AMBIL DATA LAMA (Akurat)
$sql = "SELECT * FROM Users WHERE ID_User = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php");
    exit();
}

// 3. PROSES UPDATE
if (isset($_POST['update'])) {
    $email      = trim($_POST['email']);
    $pass       = $_POST['password'];
    $role_baru  = $_POST['role'];
    $role_lama  = $data['Role_User'];
    $status     = $_POST['status'];

    // VALIDASI EMAIL: Format harus benar
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "Format email tidak valid!";
    } else {
        // VALIDASI UNIK: Cek jika email dipakai user lain (kecuali ID ini)
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_email = "Email ini sudah digunakan oleh akun lain!";
        } else {
            // 4. MENGGUNAKAN TRANSACTION (Keamanan Data Level Tinggi)
            sqlsrv_begin_transaction($conn);

            // A. Update Tabel Utama: Users
            $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Role_User = ?, Status_User = ? WHERE ID_User = ?";
            $params = array($email, $pass, $role_baru, $status, $id);
            $res_u = sqlsrv_query($conn, $sql_u, $params);

            // B. LOGIKA SINKRONISASI ROLE (Masuk Logika Bisnis)
            $res_child = true;
            if ($role_baru != $role_lama) {
                // Skenario 1: Dari Pelanggan pindah ke Staff (Admin/Foto/Owner)
                if ($role_lama == 'Customer' && $role_baru != 'Customer') {
                    sqlsrv_query($conn, "DELETE FROM Pelanggan WHERE ID_User = ?", array($id));
                    $sql_ins = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
                    sqlsrv_query($conn, $sql_ins, array($id, 'Staff Baru (Promosi)', '-', '-'));
                } 
                // Skenario 2: Dari Staff pindah ke Pelanggan
                else if ($role_lama != 'Customer' && $role_baru == 'Customer') {
                    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
                    $sql_ins = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat) VALUES (?, ?, ?, ?)";
                    sqlsrv_query($conn, $sql_ins, array($id, 'Pelanggan Baru (Migrasi)', '-', '-'));
                }
            }

            if ($res_u && $res_child) {
                sqlsrv_commit($conn);
                $success = true;
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'list.php?msg=updated';
                    }, 1500);
                </script>";
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
    <title>Master User – Edit Akses Akurat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --bg-body: #fdf2f7; }
        body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        
        .main-card { 
            border: none; border-radius: 35px; overflow: hidden; background: white; 
            box-shadow: 0 25px 80px rgba(0,0,0,0.08); max-width: 1000px; margin: auto;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Sisi Kiri: Visual */
        .side-image {
            background: linear-gradient(rgba(139, 26, 62, 0.85), rgba(232, 69, 122, 0.85)), 
                        url('https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?q=80&w=2070');
            background-size: cover; background-position: center; padding: 60px; color: white; display: flex; flex-direction: column; justify-content: flex-end;
        }

        .glass-overlay {
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); padding: 30px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.2);
        }

        .side-image h3 { font-weight: 800; font-size: 2.2rem; margin-bottom: 10px; }

        /* Sisi Kanan: Form */
        .form-section { padding: 60px; }
        .form-label { font-weight: 700; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
        
        .form-control, .form-select {
            border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; transition: 0.3s;
        }

        .form-control:focus, .form-select:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 20px rgba(232, 69, 122, 0.05); }

        .btn-update {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border: none; border-radius: 16px;
            padding: 16px; font-weight: 800; width: 100%; transition: all 0.4s; margin-top: 25px;
        }
        .btn-update:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(232, 69, 122, 0.3); }
        
        .is-invalid-custom { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        .error-msg { color: #ef4444; font-size: 11px; margin-top: 6px; font-weight: 700; display: flex; align-items: center; gap: 5px; }

        .id-badge { background: #f1f5f9; color: #475569; padding: 6px 15px; border-radius: 10px; font-size: 11px; font-weight: 800; }
    </style>
</head>
<body>

    <div class="container py-4">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-image d-none d-md-flex">
                <div class="glass-overlay">
                    <div class="mb-4 text-white"><i class="bi bi-shield-shaded" style="font-size: 3.5rem;"></i></div>
                    <h3>Pembaruan <br>Otoritas</h3>
                    <p class="mb-0 opacity-90 small">Setiap perubahan role akan secara otomatis memigrasikan data profil ke tabel yang relevan (Karyawan/Pelanggan).</p>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">Edit User</h3>
                        <p class="text-muted small">Sesuaikan kredensial dan hak akses pengguna.</p>
                    </div>
                    <span class="id-badge shadow-sm">UID: #<?= $data['ID_User'] ?></span>
                </div>

                <form method="POST">
                    <!-- Email dengan Validasi Akurat -->
                    <div class="mb-4">
                        <label class="form-label">Email / Username Login</label>
                        <input type="email" name="email" 
                               class="form-control <?= $error_email != "" ? 'is-invalid-custom' : '' ?>" 
                               value="<?= htmlspecialchars($data['Email_User']) ?>" required>
                        <?php if($error_email != ""): ?>
                            <div class="error-msg"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error_email ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Password dengan Toggle Lihat -->
                    <div class="mb-4">
                        <label class="form-label">Kata Sandi Akun</label>
                        <div class="input-group">
                            <input type="password" name="password" id="passInput" class="form-control border-end-0" value="<?= htmlspecialchars($data['Password_User']) ?>" required>
                            <span class="input-group-text bg-light border-start-0" style="border-radius: 0 16px 16px 0; cursor: pointer;" onclick="togglePass()">
                                <i class="bi bi-eye-slash" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Role Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Akses Baru</label>
                            <select name="role" class="form-select">
                                <option value="Admin" <?= $data['Role_User'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Fotografer" <?= $data['Role_User'] == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                                <option value="Owner" <?= $data['Role_User'] == 'Owner' ? 'selected' : '' ?>>Owner (Pemilik)</option>
                                <option value="Customer" <?= $data['Role_User'] == 'Customer' ? 'selected' : '' ?>>Pelanggan</option>
                            </select>
                        </div>
                        <!-- Status Selection -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Keaktifan</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mt-3 border-0 rounded-4 fw-bold"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="update" class="btn btn-update shadow-sm">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Perubahan
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-arrow-left me-1"></i> Batalkan & Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Script Password Toggle -->
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
            title: 'Update Berhasil!',
            text: 'Data user dan migrasi profil telah selesai secara akurat.',
            showConfirmButton: false,
            timer: 2000
        });
    </script>
    <?php endif; ?>

</body>
</html>