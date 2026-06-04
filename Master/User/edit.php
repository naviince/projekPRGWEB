<?php
session_start();
include '../../koneksi.php';
// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'];
$error = "";

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
    $email  = $_POST['email'];
    $pass   = $_POST['password'];
    $role   = $_POST['role'];
    $status = $_POST['status'];

    // Validasi Akurat: Cek jika email sudah dipakai user lain
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $id));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error = "Email sudah digunakan oleh akun lain!";
    } else {
        $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ?, Role_User = ?, Status_User = ? WHERE ID_User = ?";
        $params = array($email, $pass, $role, $status, $id);
        $res = sqlsrv_query($conn, $sql_u, $params);

        if ($res) {
            echo "<script>alert('Akun berhasil diperbarui!'); window.location='list.php';</script>";
        } else {
            $error = "Gagal memperbarui data.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit User - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 20px; max-width: 500px; margin: 50px auto; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn-update { background-color: #e8457a; color: white; border-radius: 10px; font-weight: 700; }
        .btn-update:hover { background-color: #c73165; color: white; }
    </style>
</head>
<body>
    <div class="card p-4">
        <h4 class="fw-bold mb-3 text-center">Edit Akses User</h4>
        <p class="text-muted text-center small">ID User: #<?= $data['ID_User'] ?></p>
        <hr>
        
        <?php if($error != ""): ?>
            <div class="alert alert-danger small"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Email / Username</label>
                <input type="email" name="email" class="form-control" value="<?= $data['Email_User'] ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Password</label>
                <input type="text" name="password" class="form-control" value="<?= $data['Password_User'] ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Role Akses</label>
                    <select name="role" class="form-select">
                        <option value="Admin" <?= $data['Role_User'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Customer" <?= $data['Role_User'] == 'Customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="Fotografer" <?= $data['Role_User'] == 'Fotografer' ? 'selected' : '' ?>>Fotografer</option>
                        <option value="Owner" <?= $data['Role_User'] == 'Owner' ? 'selected' : '' ?>>Owner</option>
                    </select>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">Status Akun</label>
                    <select name="status" class="form-select">
                        <option value="Active" <?= $data['Status_User'] == 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $data['Status_User'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="update" class="btn btn-update w-100 py-2 shadow-sm">Simpan Perubahan</button>
            <a href="list.php" class="btn btn-light w-100 mt-2">Batal</a>
        </form>
    </div>
</body>
</html>