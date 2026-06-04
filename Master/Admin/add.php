<?php
session_start();
include '../../../koneksi.php';

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = $_POST['role'];
    $nama = $_POST['nama']; // Nama asli untuk Pelanggan/Karyawan

    // 1. Validasi Input Kosong
    if (empty($email) || empty($pass) || empty($nama)) {
        $error = "Semua kolom harus diisi!";
    } else {
        // 2. Cek Email apakah sudah ada
        $cek = sqlsrv_query($conn, "SELECT * FROM Users WHERE Email_User = ?", array($email));
        if (sqlsrv_has_rows($cek)) {
            $error = "Email sudah digunakan!";
        } else {
            // 3. Simpan ke tabel Users
            $sql_u = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) VALUES (?, ?, ?, 'Active'); SELECT SCOPE_IDENTITY() AS id;";
            $stmt_u = sqlsrv_query($conn, $sql_u, array($email, $pass, $role));
            sqlsrv_next_result($stmt_u);
            $user = sqlsrv_fetch_array($stmt_u, SQLSRV_FETCH_ASSOC);
            $id_baru = $user['id'];

            // 4. Simpan ke tabel detail sesuai Role
            if ($role == 'Customer') {
                sqlsrv_query($conn, "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp) VALUES (?, ?, '0')", array($id_baru, $nama));
            } else {
                sqlsrv_query($conn, "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Jabatan_Karyawan) VALUES (?, ?, '0', ?)", array($id_baru, $nama, $role));
            }
            header("Location: list.php?msg=success");
        }
    }
}
?>
<!-- Tampilan Form dengan Desain yang Tetap Cantik -->
<!DOCTYPE html>
<html lang="id">
<head>
    <link href="../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #fff0f5; }</style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="card p-4 shadow-sm" style="width: 400px; border-radius: 20px;">
        <h4 class="text-center mb-4">Tambah User Baru</h4>
        <?php if($error != ""): ?>
            <div class="alert alert-danger small"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3"><label>Nama Lengkap</label><input type="text" name="nama" class="form-control" required></div>
            <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-3">
                <label>Role</label>
                <select name="role" class="form-select">
                    <option value="Customer">Customer</option>
                    <option value="Admin">Admin</option>
                    <option value="Fotografer">Fotografer</option>
                    <option value="Owner">Owner</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="background:#c73165; border:none;">Simpan Pengguna</button>
            <a href="list.php" class="btn btn-link w-100 text-muted small">Batal</a>
        </form>
    </div>
</body>
</html>