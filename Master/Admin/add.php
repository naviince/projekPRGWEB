<?php
session_start();
include '../../koneksi.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    if (empty($email) || empty($pass)) {
        $error = "Email dan Password wajib diisi!";
    } else {
        // Cek apakah email sudah ada
        $cek = sqlsrv_query($conn, "SELECT * FROM Users WHERE Email_User = ?", array($email));
        if (sqlsrv_has_rows($cek)) {
            $error = "Email sudah terdaftar!";
        } else {
            $sql = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) VALUES (?, ?, 'Admin', 'Active')";
            $params = array($email, $pass);
            if (sqlsrv_query($conn, $sql, $params)) {
                header("Location: list.php");
                exit();
            } else {
                $error = "Gagal menambah data.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Tambah Admin - SpotLight</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; }
        .card { border-radius: 20px; max-width: 450px; margin: 100px auto; }
        .btn-pink { background-color: #e8457a; color: white; }
    </style>
</head>
<body>
    <div class="card p-4 shadow">
        <h4 class="fw-bold mb-3">Tambah Admin Baru</h4>
        <?php if($error != ""): ?>
            <div class="alert alert-danger small"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email Admin</label>
                <input type="email" name="email" class="form-control" placeholder="admin@spotlight.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="******" required>
            </div>
            <button type="submit" class="btn btn-pink w-100 py-2 fw-bold">Simpan Admin</button>
            <a href="list.php" class="btn btn-light w-100 mt-2">Batal</a>
        </form>
    </div>
</body>
</html>