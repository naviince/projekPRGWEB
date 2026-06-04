<?php
session_start();
include '../../koneksi.php';

// 1. Proteksi: Cek apakah yang akses benar-benar Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 2. Ambil ID dari URL dan Tarik data lama dari Database
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$id = $_GET['id'];
$sql_ambil = "SELECT * FROM Users WHERE ID_User = ?";
$stmt_ambil = sqlsrv_query($conn, $sql_ambil, array($id));
$data = sqlsrv_fetch_array($stmt_ambil, SQLSRV_FETCH_ASSOC);

// Jika ID tidak ditemukan di database
if (!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location='list.php';</script>";
    exit();
}

$error = "";

// 3. Logika Update saat tombol Simpan diklik
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_baru = trim($_POST['email']);
    $password_baru = $_POST['password'];

    // Validasi input kosong
    if (empty($email_baru) || empty($password_baru)) {
        $error = "Email dan Password tidak boleh kosong!";
    } else {
        // Validasi: Cek apakah email sudah dipakai orang lain (selain diri sendiri)
        $sql_cek = "SELECT * FROM Users WHERE Email_User = ? AND ID_User != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_baru, $id));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error = "Email ini sudah digunakan oleh akun lain!";
        } else {
            // Proses Update ke SQL Server
            $sql_update = "UPDATE Users SET Email_User = ?, Password_User = ? WHERE ID_User = ?";
            $params = array($email_baru, $password_baru, $id);
            $stmt_update = sqlsrv_query($conn, $sql_update, $params);

            if ($stmt_update) {
                echo "<script>alert('Data Admin berhasil diperbarui!'); window.location='list.php';</script>";
            } else {
                $error = "Gagal memperbarui data ke database.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Admin - SpotLight</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 20px; border: none; max-width: 500px; margin: 80px auto; }
        .btn-pink { background-color: #e8457a; color: white; border: none; font-weight: 700; }
        .btn-pink:hover { background-color: #c73165; color: white; }
        .form-label { font-weight: 600; color: #333; }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-lg p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold" style="color: #c73165;">Edit Akun Admin</h3>
            <p class="text-muted">Ubah informasi akun administrator ID #<?php echo $id; ?></p>
        </div>

        <?php if($error != ""): ?>
            <div class="alert alert-danger small py-2"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Email -->
            <div class="mb-3">
                <label class="form-label">Email Admin</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control border-start-0" 
                           value="<?php echo $data['Email_User']; ?>" required>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="text" name="password" class="form-control border-start-0" 
                           value="<?php echo $data['Password_User']; ?>" required>
                </div>
                <small class="text-muted">Pastikan password aman dan mudah diingat.</small>
            </div>

            <div class="row g-2">
                <div class="col-8">
                    <button type="submit" class="btn btn-pink w-100 py-2">
                        <i class="bi bi-check-circle"></i> Simpan Perubahan
                    </button>
                </div>
                <div class="col-4">
                    <a href="list.php" class="btn btn-light w-100 py-2 border">Batal</a>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>