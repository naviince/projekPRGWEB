<?php
session_start();
include '../../koneksi.php';

// 1. Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'];
$error_nama = ""; $error_general = ""; $error_foto = "";
$success = false; 

// 2. AMBIL DATA LAMA
$sql = "SELECT * FROM Paket_Foto WHERE ID_Paket = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) { header("Location: list.php"); exit(); }

// 3. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama      = trim($_POST['nama']);
    $durasi    = $_POST['durasi'];
    $harga     = $_POST['harga'];
    $kapasitas = $_POST['kapasitas'];
    $desc      = trim($_POST['deskripsi']);

    // --- VALIDASI AKURAT & LOGIS (BUSINESS LOGIC) ---
    if (strlen($nama) < 3) {
        $error_nama = "Nama paket terlalu pendek (Minimal 3 karakter)!";
    } elseif ($durasi < 15) {
        $error_general = "Durasi sesi foto minimal 15 menit!";
    } elseif ($harga < 10000) {
        $error_general = "Harga tidak logis (Minimal Rp 10.000)!";
    } elseif ($kapasitas < 1) {
        $error_general = "Kapasitas minimal harus 1 orang!";
    } elseif (strlen($desc) < 20) {
        $error_general = "Deskripsi minimal 20 karakter agar jelas bagi pelanggan!";
    } else {
        // Cek Nama Duplikat (Kecuali ID ini sendiri)
        $sql_cek = "SELECT * FROM Paket_Foto WHERE Nama_Paket = ? AND ID_Paket != ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($nama, $id));
        
        if (sqlsrv_has_rows($stmt_cek)) {
            $error_nama = "Nama paket sudah digunakan layanan lain!";
        } else {
            // PROSES UPLOAD FOTO (JIKA ADA)
            $new_name = $data['Foto_Paket']; // Default pake foto lama
            $upload_ok = true;

            if ($_FILES['foto']['name'] != "") {
                $foto_name = $_FILES['foto']['name'];
                $foto_tmp  = $_FILES['foto']['tmp_name'];
                $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png'];

                if (!in_array($ext, $allowed)) {
                    $error_foto = "Format harus JPG/PNG!";
                    $upload_ok = false;
                } else {
                    $new_name = "paket_" . time() . "." . $ext;
                    if (move_uploaded_file($foto_tmp, "../../assets/img/paket/" . $new_name)) {
                        // HAPUS FOTO LAMA (Efisien)
                        if ($data['Foto_Paket'] != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $data['Foto_Paket'])) {
                            unlink("../../assets/img/paket/" . $data['Foto_Paket']);
                        }
                    } else { $upload_ok = false; }
                }
            }

            if ($upload_ok) {
                $sql_u = "UPDATE Paket_Foto SET Nama_Paket=?, Durasi_Waktu=?, Harga_Paket=?, Deskripsi=?, Kapasitas_Orang=?, Foto_Paket=? WHERE ID_Paket=?";
                $params = array($nama, $durasi, $harga, $desc, $kapasitas, $new_name, $id);
                $res = sqlsrv_query($conn, $sql_u, $params);
                if ($res) { $success = true; }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Paket – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        .main-card { border: none; border-radius: 40px; overflow: hidden; background: white; box-shadow: 0 25px 80px rgba(232, 69, 122, 0.1); max-width: 1100px; margin: auto; animation: slideIn 0.7s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
        .side-visual { background: linear-gradient(135deg, #8b1a3e, #e8457a); padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .form-section { padding: 50px 80px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .form-control { border-radius: 16px; padding: 14px 20px; border: 2px solid #f1f5f9; background: #f8fafc; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.05); }
        .btn-update { background: linear-gradient(to right, #c73165, #e8457a); color: white; border: none; border-radius: 18px; padding: 16px; font-weight: 800; width: 100%; transition: 0.4s; margin-top: 20px; box-shadow: 0 10px 30px rgba(232, 69, 122, 0.2); }
        .btn-update:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(232, 69, 122, 0.3); }
        #preview-container { width: 100%; height: 180px; border-radius: 20px; border: 3px dashed #e2e8f0; overflow: hidden; margin-bottom: 15px; }
        #preview-img { width: 100%; height: 100%; object-fit: cover; }
        .error-msg { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 5px; display: block; }
        .btn-gray {
    background: #e2e8f0; /* Warna abu-abu muda */
    color: #475569;      /* Warna teks abu-abu tua */
    border-radius: 18px; /* Menyamakan dengan border-radius .btn-update */
    padding: 16px;
    font-weight: 800;
    border: none;
    width: 100%;
    transition: 0.4s;
    margin-top: 12px;
    font-size: 16px;
    text-align: center;
    display: block;
    text-decoration: none;
}

.btn-gray:hover {
    background: #cbd5e1;
    color: #1e293b;
    transform: translateY(-3px); /* Efek melayang yang sama dengan tombol update */
    box-shadow: 0 10px 25px rgba(0,0,0,0.06);
}
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card row g-0">
            <!-- Sisi Visual (Kiri) -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <h2 class="fw-bold mb-3">Pembaruan <br>Layanan Studio.</h2>
                <p class="opacity-75">Sesuaikan parameter paket untuk memastikan profitabilitas dan kepuasan pelanggan tetap terjaga.</p>
                <div class="mt-5 p-3 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-20">
                    <small class="fw-bold d-block mb-1">INFO SISTEM:</small>
                    <small class="opacity-75">ID Paket #<?= $id ?> terhubung otomatis ke katalog Landing Page.</small>
                </div>
            </div>

            <!-- Sisi Form (Kanan) -->
            <div class="col-md-7 form-section">
                <h4 class="fw-bold mb-4">Edit Data Paket</h4>
                
                <?php if($error_general): ?>
                    <div class="alert alert-danger border-0 rounded-4 py-2 small mb-4 fw-bold shadow-sm">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= $error_general ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Nama Layanan Paket</label>
                        <input type="text" name="nama" id="inputNama" class="form-control" value="<?= $data['Nama_Paket'] ?>" placeholder="Masukkan nama paket yang logis" required>
                        <?php if($error_nama): ?> <span class="error-msg"><?= $error_nama ?></span> <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durasi (Menit)</label>
                            <input type="number" name="durasi" class="form-control" value="<?= $data['Durasi_Waktu'] ?>" placeholder="Min. 15" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga Paket (Rp)</label>
                            <input type="number" name="harga" class="form-control" value="<?= (int)$data['Harga_Paket'] ?>" placeholder="Min. 10.000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kapasitas Maksimal Orang</label>
                        <input type="number" name="kapasitas" class="form-control" value="<?= $data['Kapasitas_Orang'] ?>" placeholder="Min. 1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi Lengkap</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Jelaskan detail layanan..." required><?= $data['Deskripsi'] ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Visual Paket (Abaikan jika tidak ganti)</label>
                        <div id="preview-container">
                            <img id="preview-img" src="../../assets/img/paket/<?= $data['Foto_Paket'] ?>" alt="Preview">
                        </div>
                        <input type="file" name="foto" id="fotoInput" class="form-control" accept="image/*">
                        <?php if($error_foto): ?> <span class="error-msg"><?= $error_foto ?></span> <?php endif; ?>
                    </div>

                    <button type="submit" name="update" class="btn btn-update shadow-sm">
    <i class="bi bi-cloud-arrow-up-fill me-2"></i>Simpan Perubahan Paket
</button>
                    <div class="text-center mt-3"><a href="list.php" class="btn-gray shadow-sm">
    <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
</a>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-time Preview & Character Filter
        document.getElementById('inputNama').oninput = function() { this.value = this.value.replace(/[^a-zA-Z0-9 ]/g, ''); };
        const fotoInput = document.getElementById('fotoInput');
        fotoInput.onchange = evt => {
            const [file] = fotoInput.files;
            if (file) { document.getElementById('preview-img').src = URL.createObjectURL(file); }
        }
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Paket telah diperbarui secara akurat.', confirmButtonColor: '#e8457a' }).then(() => window.location='list.php');
    </script>
    <?php endif; ?>
</body>
</html>