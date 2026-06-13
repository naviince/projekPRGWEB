<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$GET['id'] : 0;
$sql_tema = "SELECT * FROM Tema_Foto WHERE ID_Tema = ? AND Is_Deleted = 0";
$stmt_tema = sqlsrv_query($conn, $sql_tema, [$id]);
$tema = sqlsrv_fetch_array($stmt_tema, SQLSRV_FETCH_ASSOC);

if (!$tema) { header("Location: list.php"); exit(); }

// Ambil Relasi Ruangan
$sql_rel = "SELECT ID_Ruangan FROM Ruangan_Tema WHERE ID_Tema = ?";
$stmt_rel = sqlsrv_query($conn, $sql_rel, [$id]);
$ruangan_terpilih = [];
while ($rel = sqlsrv_fetch_array($stmt_rel, SQLSRV_FETCH_ASSOC)) { $ruangan_terpilih[] = $rel['ID_Ruangan']; }

// Ambil Semua Ruangan
$sql_all = "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0";
$stmt_all = sqlsrv_query($conn, $sql_all);
$daftar_ruangan = [];
while ($r = sqlsrv_fetch_array($stmt_all, SQLSRV_FETCH_ASSOC)) { $daftar_ruangan[] = $r; }

$success = false; $error = "";

if (isset($_POST['update'])) {
    $nama = trim($_POST['nama_tema']);
    $deskripsi = trim($_POST['deskripsi']);
    $status = (int)$_POST['status'];
    $ruangan_baru = $_POST['ruangan'] ?? [];

    if (empty($nama) || empty($ruangan_baru)) {
        $error = "Nama dan ruangan wajib diisi!";
    } else {
        $foto_final = $tema['Foto_Tema'];
        if (!empty($_FILES['foto']['name'])) {
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto_final = "tema_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], "../../assets/img/tema/" . $foto_final);
        }

        sqlsrv_begin_transaction($conn);
        try {
            sqlsrv_query($conn, "UPDATE Tema_Foto SET Nama_Tema = ?, Deskripsi = ?, Foto_Tema = ?, Status = ? WHERE ID_Tema = ?", [$nama, $deskripsi, $foto_final, $status, $id]);
            sqlsrv_query($conn, "DELETE FROM Ruangan_Tema WHERE ID_Tema = ?", [$id]);
            foreach ($ruangan_baru as $id_r) { sqlsrv_query($conn, "INSERT INTO Ruangan_Tema (ID_Tema, ID_Ruangan) VALUES (?, ?)", [$id, $id_r]); }
            sqlsrv_commit($conn);
            $success = true;
        } catch (Exception $e) { sqlsrv_rollback($conn); $error = "Gagal memperbarui."; }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Tema – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --p-pink: #D53D66; --body-bg: #f8fafc; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--body-bg); }
        .sidebar { width: 260px; height: 100vh; background: #fff; position: fixed; padding: 30px 20px; border-right: 1px solid #eee; }
        .main-content { margin-left: 260px; padding: 40px; }
        .form-card { background: #fff; border-radius: 24px; border: 1px solid #f1f5f9; box-shadow: 0 10px 40px rgba(0,0,0,0.04); overflow: hidden; }
        .form-header { background: linear-gradient(135deg, var(--p-pink), #CA3366); padding: 35px; color: #fff; }
        .form-control-custom { border: 2px solid #f1f5f9; border-radius: 12px; padding: 12px; font-weight:600; }
        .ruangan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; background: #f8fafc; padding: 20px; border-radius: 15px; }
        .btn-update { background: var(--p-pink); color: #fff; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; }
    </style>
</head>
<body>

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
                <h4 class="fw-bold mb-0">Edit Tema: <?= $tema['Nama_Tema'] ?></h4>
            </div>
            <div class="p-5">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Nama Tema</label>
                        <input type="text" name="nama_tema" class="form-control form-control-custom" value="<?= $tema['Nama_Tema'] ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control form-control-custom" rows="3"><?= $tema['Deskripsi'] ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Pilih Ruangan</label>
                        <div class="ruangan-grid border">
                            <?php foreach($daftar_ruangan as $r): 
                                $is_checked = in_array($r['ID_Ruangan'], $ruangan_terpilih) ? 'checked' : '';
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ruangan[]" value="<?= $r['ID_Ruangan'] ?>" id="r<?= $r['ID_Ruangan'] ?>" <?= $is_checked ?>>
                                <label class="form-check-label fw-bold small" for="r<?= $r['ID_Ruangan'] ?>"><?= $r['Nama_Ruangan'] ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Ganti Foto (Opsional)</label>
                        <input type="file" name="foto" class="form-control form-control-custom">
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small mb-2 text-uppercase">Status</label>
                        <select name="status" class="form-control-custom">
                            <option value="1" <?= $tema['Status'] == 1 ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $tema['Status'] == 0 ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="text-center mt-5">
                        <button type="submit" name="update" class="btn-update">Perbarui Tema</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Perubahan disimpan.', confirmButtonColor: '#D53D66' }).then(() => window.location='list.php');
    </script>
    <?php endif; ?>
</body>
</html>