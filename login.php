<?php
session_start();
include 'koneksi.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM Users WHERE Email_User = ? AND Password_User = ?";
    $params = array($email, $password);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['status'] = "login";
        $_SESSION['id_user'] = $user['ID_User'];
        $_SESSION['role'] = $user['Role_User'];
        $_SESSION['email'] = $user['Email_User'];

        if ($user['Role_User'] == 'Admin') {
            header("Location: Master/Admin/index.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        $error = "Email atau Password Salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SpotLight</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF5F7; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Montserrat', sans-serif; }
        .card-login { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .btn-pink { background: #ff66a1; color: white; border: none; font-weight: 700; width: 100%; padding: 12px; border-radius: 10px; }
        .btn-pink:hover { background: #e0558d; }
    </style>
</head>
<body>
    <div class="card-login">
        <h2 class="text-center mb-4" style="color: #2d0a18; font-weight: 800;">SpotLight</h2>
        <?php if($error != ""): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-pink">MASUK SEKARANG</button>
            <p class="text-center mt-3 small">Belum punya akun? <a href="register.php" style="color: #ff66a1;">Daftar</a></p>
        </form>
    </div>
</body>
</html>