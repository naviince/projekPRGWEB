<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'];
$success = false; 
$error = "";

// 1. AMBIL DATA LAMA
$sql = "SELECT * FROM Paket_Foto WHERE ID_Paket = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) { header("Location: list.php"); exit(); }

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama = $_POST['nama'];
    $durasi = $_POST['durasi'];
    $harga = $_POST['harga'];
    $kapasitas = $_POST['kapasitas']; // Baris ini yang tadi error karena inputnya tidak ada di HTML
    $desc = $_POST['deskripsi'];

    // Cek jika ada upload foto baru
    if ($_FILES['foto']['name'] != "") {
        $foto_name = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $new_name = "paket_" . time() . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "../../assets/img/paket/" . $new_name);
        
        // Update dengan foto baru (Sesuaikan nama kolom Kapasitas_Orang)
        $sql_u = "UPDATE Paket_Foto SET Nama_Paket=?, Durasi_Waktu=?, Harga_Paket=?, Deskripsi=?, Kapasitas_Orang=?, Foto_Paket=? WHERE ID_Paket=?";
        $params = array($nama, $durasi, $harga, $desc, $kapasitas, $new_name, $id);
    } else {
        // Update tanpa ganti foto
        $sql_u = "UPDATE Paket_Foto SET Nama_Paket=?, Durasi_Waktu=?, Harga_Paket=?, Deskripsi=?, Kapasitas_Orang=? WHERE ID_Paket=?";
        $params = array($nama, $durasi, $harga, $desc, $kapasitas, $id);
    }

    $res = sqlsrv_query($conn, $sql_u, $params);
    if ($res) { 
        $success = true; 
    } else { 
        $error = "Gagal memperbarui data database!"; 
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Paket – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; min-height: 100vh; }
        .main-card { border-radius: 30px; overflow: hidden; background: white; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 1000px; margin: auto; border:none; }
        .side-visual { background: linear-gradient(135deg, #8b1a3e, #e8457a); padding: 50px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .btn-update { background: #e8457a; color: white; border-radius: 12px; padding: 14px; font-weight: 800; border:none; width: 100%; transition: 0.3s; }
        .btn-update:hover { background: #c73165; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(232, 69, 122, 0.3); }
        .form-label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Kiri -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <h2 class="fw-bold mb-3">Pembaruan Layanan.</h2>
                <p class="opacity-75">Sesuaikan harga, durasi, atau kapasitas paket untuk mengikuti tren pasar saat ini.</p>
            </div>

            <!-- Sisi Kanan -->
            <div class="col-md-7 p-5">
                <h4 class="fw-bold mb-4">Edit Data Paket</h4>
                
                <?php if($error != ""): ?>
                    <div class="alert alert-danger small"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">NAMA PAKET</label>
                        <input type="text" name="nama" class="form-control bg-light border-0" value="<?= $data['Nama_Paket'] ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DURASI (MENIT)</label>
                            <input type="number" name="durasi" class="form-control bg-light border-0" value="<?= $data['Durasi_Waktu'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">HARGA (RP)</label>
                            <input type="number" name="harga" class="form-control bg-light border-0" value="<?= (int)$data['Harga_Paket'] ?>" required>
                        </div>
                    </div>

                    <!-- INPUT KAPASITAS (Tadi ini yang hilang) -->
                    <div class="mb-3">
                        <label class="form-label">KAPASITAS MAKSIMAL (ORANG)</label>
                        <input type="number" name="kapasitas" class="form-control bg-light border-0" value="<?= $data['Kapasitas_Orang'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">DESKRIPSI</label>
                        <textarea name="deskripsi" class="form-control bg-light border-0" rows="3" required><?= $data['Deskripsi'] ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">GANTI FOTO (KOSONGKAN JIKA TIDAK)</label>
                        <input type="file" name="foto" class="form-control border-dashed">
                        <small class="text-muted" style="font-size: 10px;">Foto saat ini: <?= $data['Foto_Paket'] ?></small>
                    </div>

                    <button type="submit" name="update" class="btn btn-update shadow-sm mb-3">Simpan Perubahan</button>
                    <div class="text-center">
                        <a href="list.php" class="text-muted small text-decoration-none fw-bold">Kembali ke Daftar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Data paket telah diperbarui secara akurat.',
            showConfirmButton: false,
            timer: 1500
        }).then(() => window.location='list.php');
    </script>
    <?php endif; ?>
</body>
</html>