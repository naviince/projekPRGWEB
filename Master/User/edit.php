<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'];
$error_email = "";
$error_general = "";
$success = false;

// 1. AMBIL DATA LAMA
$sql = "SELECT * FROM Users WHERE ID_User = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php");
    exit();
}

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $email  = trim($_POST['email']);
    $pass   = $_POST['password'];
    $role_baru  = $_POST['role'];
    $role_lama  = $data['Role_User'];
    $status = $_POST['status'];

    // VALIDASI AKURAT: Cek jika email sudah dipakai user lain
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email ini sudah digunakan oleh akun lain!";
    } else {
        // MENGGUNAKAN TRANSACTION AGAR DATA AMAN (EFISIEN & AKURAT)
        sqlsrv_begin_transaction($conn);

        // A. Update Tabel Users
        $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Role_User = ?, Status_User = ? WHERE ID_User = ?";
        $params = array($email, $pass, $role_baru, $status, $id);
        $res_u = sqlsrv_query($conn, $sql_u, $params);

        // B. LOGIKA PERUBAHAN ROLE (SINKRONISASI TABEL CHILD)
        $res_child = true;
        if ($role_baru != $role_lama) {
            // Jika berubah dari Customer ke Staff (Admin/Foto/Owner)
            if ($role_lama == 'Customer' && $role_baru != 'Customer') {
                sqlsrv_query($conn, "DELETE FROM Pelanggan WHERE ID_User = ?", array($id));
                sqlsrv_query($conn, "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp) VALUES (?, ?, ?)", array($id, 'Staff Baru', '-'));
            } 
            // Jika berubah dari Staff ke Customer
            else if ($role_lama != 'Customer' && $role_baru == 'Customer') {
                sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_User = ?", array($id));
                sqlsrv_query($conn, "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp) VALUES (?, ?, ?)", array($id, 'Pelanggan Baru', '-'));
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
            $error_general = "Gagal memperbarui data. Terjadi kesalahan database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Akses User – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        .main-card { 
            border: none; border-radius: 25px; overflow: hidden; background: white; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.08); max-width: 900px; margin: auto;
        }

        /* Sisi Kiri: Visual */
        .side-image {
            background: linear-gradient(rgba(139, 26, 62, 0.85), rgba(232, 69, 122, 0.85)), 
                        url('https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?q=80&w=2070&auto=format&fit=crop');
            background-size: cover; background-position: center; padding: 40px; color: white; display: flex; flex-direction: column; justify-content: flex-end;
        }

        .side-image h3 { font-weight: 800; font-size: 2rem; }
        .side-image p { opacity: 0.8; font-size: 14px; }

        /* Sisi Kanan: Form */
        .form-section { padding: 50px; }
        .form-label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-control, .form-select {
            border-radius: 12px; padding: 12px 16px; border: 2px solid #f1f5f9; background: #f8fafc; transition: 0.3s;
        }

        .form-control:focus { background: white; border-color: #e8457a; box-shadow: 0 0 0 4px rgba(232, 69, 122, 0.1); }
        .is-invalid-custom { border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        .error-msg { color: #ef4444; font-size: 11px; margin-top: 5px; font-weight: 600; }

        .btn-update {
            background: #e8457a; color: white; border: none; border-radius: 12px; padding: 14px;
            font-weight: 800; width: 100%; transition: 0.3s; margin-top: 20px;
        }
        .btn-update:hover { background: #c73165; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(232, 69, 122, 0.25); }
        
        .user-badge { background: #f1f5f9; color: #475569; padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Kiri -->
            <div class="col-md-5 side-image d-none d-md-flex">
                <div class="mb-5"><i class="bi bi-shield-shaded" style="font-size: 3rem;"></i></div>
                <h3>Update Access</h3>
                <p>Mengubah email atau role akan berdampak pada hak akses pengguna di dashboard. Perubahan role akan secara otomatis memindahkan data profil pengguna.</p>
            </div>

            <!-- Sisi Kanan -->
            <div class="col-md-7 form-section">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h4 class="fw-bold mb-0">Edit Akses User</h4>
                    <span class="user-badge">ID #<?= $data['ID_User'] ?></span>
                </div>
                <p class="text-muted small mb-4">Kelola kredensial login dan status akun.</p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email / Username</label>
                        <input type="email" name="email" 
                               class="form-control <?= $error_email != "" ? 'is-invalid-custom' : '' ?>" 
                               value="<?= $data['Email_User'] ?>" required>
                        <?php if($error_email != ""): ?>
                            <div class="error-msg"><i class="bi bi-exclamation-circle me-1"></i><?= $error_email ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="text" name="password" class="form-control" value="<?= $data['Password_User'] ?>" required>
                            <span class="input-group-text bg-white border-start-0" style="border-radius: 0 12px 12px 0; border: 2px solid #f1f5f9;"><i class="bi bi-eye"></i></span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Pengguna</label>
                            <select name="role" class="form-select">
                                <option value="Admin" <?= $data['Role_User'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Fotografer" <?= $data['Role_User'] == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                                <option value="Owner" <?= $data['Role_User'] == 'Owner' ? 'selected' : '' ?>>Owner</option>
                                <option value="Customer" <?= $data['Role_User'] == 'Customer' ? 'selected' : '' ?>>Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Akun</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if($error_general != ""): ?>
                        <div class="alert alert-danger py-2 small mt-3"><?= $error_general ?></div>
                    <?php endif; ?>

                    <button type="submit" name="update" class="btn btn-update shadow-sm">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Perubahan
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold">Kembali ke Daftar</a>
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
            text: 'Data akun telah diperbarui secara akurat.',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>