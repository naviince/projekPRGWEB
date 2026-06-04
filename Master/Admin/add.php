<?php
session_start();
include '../../koneksi.php';

// Inisialisasi variabel pesan
$error = "";

if (isset($_POST['simpan'])) {
    $nama  = $_POST['nama'];
    $email = $_POST['email'];
    $pass  = $_POST['password'];
    $hp    = $_POST['no_hp'];

    // VALIDASI AKURAT: Cek apakah email sudah terdaftar
    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    if (sqlsrv_has_rows($stmt_cek)) {
        $error = "Email sudah terdaftar, gunakan email lain!";
    } else {
        // 1. Insert ke tabel Users
        $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                 OUTPUT INSERTED.ID_User 
                 VALUES (?, ?, 'Admin', 'Active')";
        $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));
        
        if ($stmt1) {
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];

            // 2. Insert ke tabel Karyawan (Profil Admin)
            $sql2 = "INSERT INTO Karyawan (ID_User, Nama_Karyawan, No_Hp, Alamat, Foto_Profil) VALUES (?, ?, ?, ?, ?)";
            sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, '-', 'default.jpg'));

            echo "<script>alert('Admin Berhasil Ditambahkan!'); window.location='list.php';</script>";
        } else {
            $error = "Gagal menyimpan data ke database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Admin - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 20px; max-width: 500px; margin: 50px auto; border: none; }
        .btn-pink { background-color: #e8457a; color: white; border-radius: 10px; }
        .btn-pink:hover { background-color: #c73165; color: white; }
        .form-label { font-weight: 600; color: #444; }
    </style>
</head>
<body>
    <div class="card p-4 shadow">
        <h4 class="fw-bold mb-3 text-center">Tambah Admin Baru</h4>
        <hr>
        
        <?php if($error != ""): ?>
            <div class="alert alert-danger small"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" placeholder="Masukkan nama lengkap" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Admin</label>
                <input type="email" name="email" class="form-control" placeholder="Masukkan email admin" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="08..." required>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="******" required>
                </div>
            </div>
            
            <button type="submit" name="simpan" class="btn btn-pink w-100 py-2 fw-bold shadow-sm">Simpan Admin</button>
            <a href="list.php" class="btn btn-light w-100 mt-2">Batal</a>
        </form>
    </div>
</body>
</html>