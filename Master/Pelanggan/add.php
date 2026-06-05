<?php
session_start();
include '../../koneksi.php';

$error_email = ""; $success = false;

if (isset($_POST['simpan'])) {
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $hp    = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);

    $sql_cek = "SELECT * FROM Users WHERE Email_User = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_email = "Email ini sudah terdaftar sebagai pelanggan/karyawan!";
    } else {
        sqlsrv_begin_transaction($conn);
        $sql1 = "INSERT INTO Users (Email_User, Password_User, Role_User, Status_User) 
                 OUTPUT INSERTED.ID_User VALUES (?, ?, 'Customer', 'Active')";
        $stmt1 = sqlsrv_query($conn, $sql1, array($email, $pass));
        
        if ($stmt1) {
            $row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
            $new_id = $row['ID_User'];
            $sql2 = "INSERT INTO Pelanggan (ID_User, Nama_Pelanggan, No_Hp, Alamat, Foto_Profil) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt2 = sqlsrv_query($conn, $sql2, array($new_id, $nama, $hp, $alamat, 'default.jpg'));

            if ($stmt2) {
                sqlsrv_commit($conn); $success = true;
                echo "<script>setTimeout(function(){ window.location.href='list.php'; }, 1500);</script>";
            } else { sqlsrv_rollback($conn); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Pelanggan – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        .main-card { border-radius: 30px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.1); max-width: 900px; margin: auto; }
        .side-visual { background: linear-gradient(135deg, #e8457a, #8b1a3e); padding: 50px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .btn-pink { background: #e8457a; color: white; border-radius: 12px; padding: 14px; font-weight: 800; border:none; width: 100%; transition: 0.3s; }
        .btn-pink:hover { background: #c73165; transform: translateY(-3px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card row g-0">
            <div class="col-md-5 side-visual d-none d-md-flex">
                <h2 class="fw-bold mb-3">Registrasi <br>Customer Baru</h2>
                <p class="opacity-75">Tambahkan data pelanggan baru secara manual ke dalam sistem Spotlight Studio.</p>
            </div>
            <div class="col-md-7 p-5">
                <h4 class="fw-bold mb-4">Profil Pelanggan</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NAMA LENGKAP</label>
                        <input type="text" name="nama" class="form-control bg-light border-0" style="padding:12px" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">EMAIL AKTIF</label>
                        <input type="email" name="email" class="form-control bg-light border-0" style="padding:12px" required>
                        <div class="text-danger small mt-1"><?= $error_email ?></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">WHATSAPP</label>
                            <input type="text" name="no_hp" class="form-control bg-light border-0" style="padding:12px" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">PASSWORD</label>
                            <input type="password" name="password" class="form-control bg-light border-0" style="padding:12px" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ALAMAT</label>
                        <input type="text" name="alamat" class="form-control bg-light border-0" style="padding:12px">
                    </div>
                    <button type="submit" name="simpan" class="btn btn-pink shadow-sm">Simpan Pelanggan</button>
                </form>
            </div>
        </div>
    </div>
    <?php if($success): ?><script>Swal.fire('Sukses!', 'Pelanggan telah ditambahkan.', 'success');</script><?php endif; ?>
</body>
</html>