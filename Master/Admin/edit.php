<?php
session_start();
include '../../koneksi.php';

$id = $_GET['id'];
$error = "";

// 1. AMBIL DATA LAMA
$sql = "SELECT u.Email_User, u.Password_User, k.Nama_Karyawan, k.No_Hp 
        FROM Users u 
        JOIN Karyawan k ON u.ID_User = k.ID_User 
        WHERE u.ID_User = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) { header("Location: list.php"); exit(); }

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama  = $_POST['nama'];
    $email = $_POST['email'];
    $pass  = $_POST['password'];
    $hp    = $_POST['no_hp'];

    // Update Tabel Users
    $sql_u = "UPDATE Users SET Email_User = ?, Password_User = ? WHERE ID_User = ?";
    sqlsrv_query($conn, $sql_u, array($email, $pass, $id));

    // Update Tabel Karyawan
    $sql_k = "UPDATE Karyawan SET Nama_Karyawan = ?, No_Hp = ? WHERE ID_User = ?";
    $res = sqlsrv_query($conn, $sql_k, array($nama, $hp, $id));

    if ($res) {
        echo "<script>alert('Data Admin Berhasil Diperbarui!'); window.location='list.php';</script>";
    } else {
        $error = "Gagal memperbarui data.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Admin - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 20px; max-width: 500px; margin: 50px auto; border: none; }
        .btn-warning-pink { background-color: #f472a0; color: white; border-radius: 10px; }
        .btn-warning-pink:hover { background-color: #e8457a; color: white; }
    </style>
</head>
<body>
    <div class="card p-4 shadow">
        <h4 class="fw-bold mb-3 text-center">Edit Data Admin</h4>
        <hr>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" value="<?= $data['Nama_Karyawan'] ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Admin</label>
                <input type="email" name="email" class="form-control" value="<?= $data['Email_User'] ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= $data['No_Hp'] ?>" required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control" value="<?= $data['Password_User'] ?>" required>
                </div>
            </div>
            
            <button type="submit" name="update" class="btn btn-warning-pink w-100 py-2 fw-bold shadow-sm">Simpan Perubahan</button>
            <a href="list.php" class="btn btn-light w-100 mt-2">Batal</a>
        </form>
    </div>
</body>
</html>