<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Inisialisasi variabel error
$error_nama = ""; $error_foto = ""; $error_general = "";
$success = false;

if (isset($_POST['simpan'])) {
    $nama      = trim($_POST['nama']);
    $durasi    = $_POST['durasi'];
    $harga     = $_POST['harga'];
    $kapasitas = $_POST['kapasitas'];
    $desc      = trim($_POST['deskripsi']);

    // --- VALIDASI AKURAT & LOGIS (BUSINESS LOGIC) ---

    // 1. Cek Duplikasi Nama Paket
    $sql_cek = "SELECT * FROM Paket_Foto WHERE Nama_Paket = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($nama));
    
    if (sqlsrv_has_rows($stmt_cek)) {
        $error_nama = "Nama paket sudah ada, gunakan nama unik lainnya!";
    } 
    // 2. Validasi Harga & Kapasitas (Minimal Logic)
    elseif ($harga < 10000) {
        $error_general = "Harga paket tidak logis (terlalu murah)!";
    } elseif ($durasi < 15) {
        $error_general = "Durasi sesi foto minimal 15 menit!";    
    } elseif ($kapasitas <= 0) {
        $error_general = "Kapasitas minimal harus 1 orang!";
    } elseif (strlen($desc) < 20) {
        $error_general = "Deskripsi terlalu singkat, berikan info lebih detail!";
    } else {
        // 3. VALIDASI FILE GAMBAR
        $foto_name = $_FILES['foto']['name'];
        $foto_tmp  = $_FILES['foto']['tmp_name'];
        $foto_size = $_FILES['foto']['size'];
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $error_foto = "Hanya file JPG, JPEG, dan PNG yang diizinkan!";
        } elseif ($foto_size > 2000000) { // Max 2MB
            $error_foto = "Ukuran gambar maksimal adalah 2MB!";
        } else {
            // Proses Simpan
            $new_filename = "paket_" . time() . "." . $ext;
            $upload_path = "../../assets/img/paket/" . $new_filename;

            // Pastikan folder tersedia
            if (!is_dir("../../assets/img/paket/")) {
                mkdir("../../assets/img/paket/", 0777, true);
            }

            if (move_uploaded_file($foto_tmp, $upload_path)) {
                $sql = "INSERT INTO Paket_Foto (Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket, Status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Aktif')";
                $params = array($nama, $durasi, $harga, $desc, $kapasitas, $new_filename);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $success = true;
                } else {
                    $error_general = "Gagal menyimpan ke database SQL Server.";
                }
            } else {
                $error_foto = "Sistem gagal mengunggah gambar ke server.";
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 40px 0; }
        
        /* 3D Glass Card Design */
        .main-card { 
            border: none; border-radius: 40px; overflow: hidden; background: white; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.05), 0 10px 20px rgba(232, 69, 122, 0.05); 
            max-width: 1100px; margin: auto; animation: slideUp 0.8s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

        /* Left Side visual */
        .side-visual { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center;
            position: relative;
        }
        .side-visual::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;
        }

        /* Input Styling */
        .form-section { padding: 50px 70px; }
        .form-label { font-weight: 800; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .form-control { border-radius: 15px; padding: 12px 18px; border: 2px solid #f1f5f9; background: #f8fafc; font-weight: 600; transition: 0.3s; }
        .form-control:focus { border-color: var(--p-pink); background: white; box-shadow: 0 10px 20px rgba(232, 69, 122, 0.08); }

        /* Image Preview Area */
        #preview-container {
            width: 100%; height: 180px; border-radius: 20px; border: 3px dashed #e2e8f0;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            margin-bottom: 20px; background: #f8fafc; position: relative;
        }
        #preview-img { width: 100%; height: 100%; object-fit: cover; display: none; }

        .btn-simpan { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border: none; 
            border-radius: 16px; padding: 16px; font-weight: 800; width: 100%; transition: 0.4s; 
            margin-top: 20px; box-shadow: 0 10px 25px rgba(232, 69, 122, 0.3);
        }
        .btn-simpan:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(232, 69, 122, 0.4); }

        .error-msg { color: #ef4444; font-size: 11px; font-weight: 700; margin-top: 5px; display: block; }
    </style>
</head>
<body>

    <div class="container">
        <div class="main-card row g-0">
            <!-- Left Side -->
            <div class="col-md-5 side-visual d-none d-md-flex">
                <div style="z-index: 1;">
                    <i class="bi bi-camera-fill mb-4" style="font-size: 4rem;"></i>
                    <h2 class="fw-bold mb-3" style="font-size: 2.5rem;">Ciptakan Paket <br>Unggulan.</h2>
                    <p class="opacity-75">Visual yang menarik dan deskripsi yang jelas akan meningkatkan peluang konversi pelanggan.</p>
                </div>
            </div>

            <!-- Right Side (Form) -->
            <div class="col-md-7 form-section">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark mb-1">Input Paket Foto</h3>
                    <p class="text-muted small fw-500">Isi data layanan katalog studio secara akurat.</p>
                </div>

                <?php if($error_general): ?>
                    <div class="alert alert-danger border-0 rounded-4 py-2 small mb-4 fw-bold shadow-sm">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= $error_general ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Nama Layanan Paket</label>
                        <input type="text" name="nama" class="form-control <?= $error_nama ? 'is-invalid' : '' ?>" placeholder="Contoh: Premium Graduation" value="<?= @$_POST['nama'] ?>" required>
                        <?php if($error_nama): ?> <span class="error-msg"><?= $error_nama ?></span> <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durasi (Menit)</label>
                            <input type="number" name="durasi" class="form-control" placeholder="60" value="<?= @$_POST['durasi'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga Paket (Rp)</label>
                            <input type="number" name="harga" class="form-control" placeholder="450000" value="<?= @$_POST['harga'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kapasitas Maksimal Orang</label>
                        <input type="number" name="kapasitas" class="form-control" placeholder="5" value="<?= @$_POST['kapasitas'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi Layanan</label>
                        <textarea name="deskripsi" class="form-control" rows="2" placeholder="Sebutkan apa saja yang didapat pelanggan..." required><?= @$_POST['deskripsi'] ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Foto Sampul Paket</label>
                        <div id="preview-container">
                            <img id="preview-img" src="" alt="Preview">
                            <div id="placeholder-text" class="text-center">
                                <i class="bi bi-image text-muted fs-1 d-block"></i>
                                <span class="text-muted small">Belum ada foto dipilih</span>
                            </div>
                        </div>
                        <input type="file" name="foto" id="fotoInput" class="form-control <?= $error_foto ? 'is-invalid' : '' ?>" required accept="image/*">
                        <?php if($error_foto): ?> <span class="error-msg"><?= $error_foto ?></span> <?php endif; ?>
                    </div>

                    <button type="submit" name="simpan" class="btn btn-simpan">
                        <i class="bi bi-cloud-upload-fill me-2"></i>Publikasikan Paket
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="list.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-arrow-left me-1"></i> Batal & Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // SCRIPT PREVIEW GAMBAR (UX PREMIUM)
        const fotoInput = document.getElementById('fotoInput');
        const previewImg = document.getElementById('preview-img');
        const placeholderText = document.getElementById('placeholder-text');

        fotoInput.onchange = evt => {
            const [file] = fotoInput.files;
            if (file) {
                previewImg.src = URL.createObjectURL(file);
                previewImg.style.display = 'block';
                placeholderText.style.display = 'none';
            }
        }
    </script>

    <?php if($success): ?>
    <script>
        Swal.fire({
            icon: 'success', title: 'Berhasil!', text: 'Paket foto baru telah resmi dipublikasikan.', confirmButtonColor: '#e8457a'
        }).then(() => { window.location = 'list.php'; });
    </script>
    <?php endif; ?>

</body>
</html>