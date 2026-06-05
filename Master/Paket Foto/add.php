<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$error = ""; $success = false;

if (isset($_POST['simpan'])) {
    $nama = trim($_POST['nama']);
    $durasi = $_POST['durasi'];
    $harga = $_POST['harga'];
    $kapasitas = $_POST['kapasitas'];
    $desc = trim($_POST['deskripsi']);

    // VALIDASI LOGIKA (Akurat)
    if ($harga < 0 || $durasi <= 0) {
        $error = "Harga atau durasi tidak masuk akal!";
    } else {
        // VALIDASI UPLOAD GAMBAR
        $foto_name = $_FILES['foto']['name'];
        $foto_tmp  = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $error = "Format gambar harus JPG, JPEG, atau PNG!";
        } elseif ($foto_size > 2000000) {
            $error = "Ukuran gambar maksimal 2MB!";
        } else {
            $new_filename = "paket_" . time() . "." . $ext;
            $upload_path = "../../assets/img/paket/" . $new_filename;

            if (move_uploaded_file($foto_tmp, $upload_path)) {
                $sql = "INSERT INTO Paket_Foto (Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket, Status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Aktif')";
                $params = array($nama, $durasi, $harga, $desc, $kapasitas, $new_filename);
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) { $success = true; }
            } else {
                $error = "Gagal memindahkan file ke server.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Paket – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; min-height: 100vh; }
        .main-card { border-radius: 30px; overflow: hidden; background: white; box-shadow: 0 25px 70px rgba(232, 69, 122, 0.12); max-width: 1000px; margin: auto; border: none; }
        .side-visual { background: linear-gradient(135deg, #e8457a, #8b1a3e); padding: 50px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .btn-simpan { background: #e8457a; color: white; border-radius: 12px; padding: 14px; font-weight: 800; border:none; width: 100%; transition: 0.3s; }
        .btn-simpan:hover { background: #c73165; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(232, 69, 122, 0.3); }
        .form-label { font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card row g-0">
            <div class="col-md-5 side-visual d-none d-md-flex">
                <h2 class="fw-bold mb-3">Produk & Layanan Baru.</h2>
                <p class="opacity-75">Visual paket yang bagus akan menarik minat pelanggan untuk melakukan booking studio.</p>
            </div>
            <div class="col-md-7 p-5">
                <h4 class="fw-bold mb-4">Input Paket Foto</h4>
                <?php if($error != ""): ?>
                    <div class="alert alert-danger py-2 small border-0 shadow-sm mb-4"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Nama Layanan Paket</label>
                        <input type="text" name="nama" class="form-control bg-light border-0" required placeholder="Contoh: Couple Session">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durasi (Menit)</label>
                            <input type="number" name="durasi" class="form-control bg-light border-0" required placeholder="60">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga Paket (Rp)</label>
                            <input type="number" name="harga" class="form-control bg-light border-0" required placeholder="450000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kapasitas Maksimal Orang</label>
                        <input type="number" name="kapasitas" class="form-control bg-light border-0" required placeholder="2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Singkat</label>
                        <textarea name="deskripsi" class="form-control bg-light border-0" rows="2" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Foto Sampul Paket</label>
                        <input type="file" name="foto" class="form-control border-dashed" required accept="image/*">
                    </div>
                    <button type="submit" name="simpan" class="btn btn-simpan shadow-sm">Simpan ke Katalog</button>
                    <div class="text-center mt-3">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold">Batal & Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        Swal.fire('Berhasil!', 'Paket foto baru telah ditambahkan.', 'success').then(() => window.location='list.php');
    </script>
    <?php endif; ?>
</body>
</html>