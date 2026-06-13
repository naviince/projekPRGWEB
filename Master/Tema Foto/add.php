<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$admin_data = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]), SQLSRV_FETCH_ASSOC);
$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';

// Ambil Ruangan untuk Checkbox
$sql_ruangan = "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0";
$stmt_ruangan = sqlsrv_query($conn, $sql_ruangan);
$daftar_ruangan = [];
while ($r = sqlsrv_fetch_array($stmt_ruangan, SQLSRV_FETCH_ASSOC)) { $daftar_ruangan[] = $r; }

$error = ""; $success = false;

if (isset($_POST['simpan'])) {
    $nama = trim($_POST['nama_tema']);
    $deskripsi = trim($_POST['deskripsi']);
    $ruangan_pilihan = $_POST['ruangan'] ?? [];

    if (empty($nama) || empty($ruangan_pilihan)) {
        $error = "Nama tema dan minimal satu ruangan harus dipilih!";
    } else {
        $foto_name = "default_tema.jpg";
        if (!empty($_FILES['foto']['name'])) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto_name = "tema_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], "../../assets/img/tema/" . $foto_name);
        }

        sqlsrv_begin_transaction($conn);
        try {
            $sql_tema = "INSERT INTO Tema_Foto (Nama_Tema, Deskripsi, Foto_Tema, Status, Is_Deleted, Created_By, Created_Date) OUTPUT INSERTED.ID_Tema VALUES (?, ?, ?, 1, 0, ?, GETDATE())";
            $stmt_tema = sqlsrv_query($conn, $sql_tema, [$nama, $deskripsi, $foto_name, $nama_admin]);
            $row_tema = sqlsrv_fetch_array($stmt_tema, SQLSRV_FETCH_ASSOC);
            $id_tema_baru = $row_tema['ID_Tema'];

            foreach ($ruangan_pilihan as $id_ruangan) {
                sqlsrv_query($conn, "INSERT INTO Ruangan_Tema (ID_Tema, ID_Ruangan) VALUES (?, ?)", [$id_tema_baru, $id_ruangan]);
            }
            sqlsrv_commit($conn);
            $success = true;
        } catch (Exception $e) { sqlsrv_rollback($conn); $error = "Gagal menyimpan data."; }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Tema – Panel Admin</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --body-bg: #f8fafc; --light-pink: #FFE4E9; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); }
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; padding: 30px 20px; border-right: 1px solid #eee; }
        .main-content { margin-left: 260px; padding: 40px; }
        .form-card { background: #fff; border-radius: 24px; border: 1px solid #f1f5f9; box-shadow: 0 10px 40px rgba(0,0,0,0.04); overflow: hidden; }
        .form-header { background: linear-gradient(135deg, var(--p-pink), #CA3366); padding: 35px; color: #fff; }
        .form-control-custom { border: 2px solid #f1f5f9; border-radius: 12px; padding: 12px; font-weight:600; }
        .ruangan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; background: #f8fafc; padding: 20px; border-radius: 15px; }
        .btn-submit { background: var(--p-pink); color: #fff; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; }
    </style>
</head>
<body>

    <!-- SIDEBAR (Sama dengan list) -->
    <div class="sidebar">
        <a href="#" class="sidebar-brand text-decoration-none fw-bold" style="color:var(--p-pink); font-size:1.5rem;">SpotLight.</a>
        <ul class="nav flex-column mt-4">
            <li class="nav-item mb-2"><a href="../Ruangan/list.php" class="nav-link text-dark fw-bold">Ruangan</a></li>
            <li class="nav-item mb-2"><a href="./list.php" class="nav-link text-danger fw-bold">Tema Foto</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="form-card mx-auto" style="max-width: 850px;">
            <div class="form-header text-center">
                <h4 class="fw-bold mb-0">Tambah Tema Foto Baru</h4>
                <p class="small mb-0 opacity-75">Tentukan konsep dan hubungkan ke ruangan studio.</p>
            </div>
            <div class="p-5">
                <?php if($error): ?><div class="alert alert-danger border-0 mb-4"><?= $error ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Nama Tema</label>
                        <input type="text" name="nama_tema" class="form-control form-control-custom" placeholder="Contoh: Modern Korean" required>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Deskripsi Tema</label>
                        <textarea name="deskripsi" class="form-control form-control-custom" rows="3" placeholder="Jelaskan konsep singkat..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Tersedia di Ruangan</label>
                        <div class="ruangan-grid border">
                            <?php foreach($daftar_ruangan as $r): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ruangan[]" value="<?= $r['ID_Ruangan'] ?>" id="r<?= $r['ID_Ruangan'] ?>">
                                <label class="form-check-label fw-bold small" for="r<?= $r['ID_Ruangan'] ?>"><?= $r['Nama_Ruangan'] ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Foto Preview</label>
                        <input type="file" name="foto" class="form-control form-control-custom">
                    </div>
                    <div class="d-flex gap-3 justify-content-center mt-5">
                        <button type="submit" name="simpan" class="btn-submit">Simpan Tema</button>
                        <a href="list.php" class="btn btn-light px-4" style="border-radius:12px; padding-top:15px; font-weight:bold;">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Tema telah disimpan.', confirmButtonColor: '#D53D66' }).then(() => window.location='list.php');
    </script>
    <?php endif; ?>
</body>
</html>