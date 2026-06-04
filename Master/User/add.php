<?php
session_start();
include '../../koneksi.php';
$error = "";

if (isset($_POST['simpan'])) {
    $email = $_POST['email'];
    $pass  = $_POST['password'];
    $role  = $_POST['role'];
    $status = $_POST['status'];

    $sql = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) VALUES (?, ?, ?, ?)";
    $res = sqlsrv_query($conn, $sql, array($email, $pass, $role, $status));

    if ($res) { header("Location: list.php?msg=added"); } 
    else { $error = "Gagal menambah akun. Email mungkin sudah digunakan."; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Tambah Akun - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #fff0f5;">
    <div class="container mt-5">
        <div class="card mx-auto shadow-sm" style="max-width: 500px; border-radius: 15px;">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3">Tambah Akun Baru</h4>
                <?php if($error != ""): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="Admin">Admin</option>
                                <option value="Fotografer">Fotografer</option>
                                <option value="Owner">Owner</option>
                                <option value="Customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="simpan" class="btn btn-dark w-100 py-2">Simpan Akun</button>
                    <a href="list.php" class="btn btn-light w-100 mt-2">Batal</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>