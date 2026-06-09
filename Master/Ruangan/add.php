<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$error = ""; $success = false;

if (isset($_POST['simpan'])) {
    $nama       = trim($_POST['nama_ruangan']);
    $kapasitas  = (int)$_POST['kapasitas'];
    $luas        = trim($_POST['luas']);
    $fasilitas  = trim($_POST['fasilitas']);
    $desc       = trim($_POST['deskripsi']);

    // VALIDASI LOGIKA
    if ($kapasitas <= 0) {
        $error = "Kapasitas ruangan harus lebih dari 0!";
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
            $new_filename = "ruangan_" . time() . "." . $ext;
            $upload_path  = "../../assets/img/ruangan/" . $new_filename;

            if (move_uploaded_file($foto_tmp, $upload_path)) {
                $sql = "INSERT INTO Ruangan (Nama_Ruangan, Kapasitas, Luas_Ruangan, Fasilitas, Deskripsi, Foto_Ruangan, Status)
                        VALUES (?, ?, ?, ?, ?, ?, 'Aktif')";
                $params = array($nama, $kapasitas, $luas, $fasilitas, $desc, $new_filename);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Ruangan – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink: #e8457a;
            --pink-dark: #c73165;
            --pink-deep: #8b1a3e;
            --bg: #fdf2f7;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 15px;
        }

        .wrapper { width: 100%; max-width: 1050px; }

        .back-link {
            text-decoration: none;
            color: #64748b;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--pink); }

        .main-card {
            border-radius: 30px;
            overflow: hidden;
            background: white;
            box-shadow: 0 25px 70px rgba(232, 69, 122, 0.12);
            border: none;
            display: flex;
        }

        /* ---- Side Visual ---- */
        .side-visual {
            width: 38%;
            background: linear-gradient(150deg, var(--pink-deep) 0%, var(--pink) 100%);
            padding: 50px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .side-visual::before {
            content: '';
            position: absolute;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            top: -60px; right: -80px;
        }
        .side-visual::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: 30px; left: -50px;
        }
        .side-icon {
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin-bottom: 24px;
            backdrop-filter: blur(4px);
        }
        .side-visual .tag {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.6;
            margin-bottom: 10px;
        }
        .side-visual h2 { font-weight: 800; font-size: 28px; line-height: 1.3; margin-bottom: 14px; }
        .side-visual p { opacity: 0.7; font-size: 14px; line-height: 1.7; }

        .tips-box {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 16px 18px;
            backdrop-filter: blur(4px);
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.6;
        }
        .tips-box i { margin-right: 6px; }

        /* ---- Form Side ---- */
        .form-side {
            flex: 1;
            padding: 50px 45px;
            overflow-y: auto;
        }
        .form-side h4 {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .form-side .subtitle {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 32px;
        }

        .form-label {
            font-weight: 700;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .form-control {
            background: #f8fafc !important;
            border: 2px solid transparent !important;
            border-radius: 12px !important;
            padding: 12px 15px !important;
            font-size: 14px !important;
            transition: border-color 0.25s, box-shadow 0.25s !important;
        }
        .form-control:focus {
            border-color: var(--pink) !important;
            box-shadow: 0 0 0 4px rgba(232,69,122,0.08) !important;
        }
        .input-group .form-control { border-radius: 12px !important; }
        .input-group-text {
            background: #f1f5f9 !important;
            border: 2px solid transparent !important;
            border-radius: 12px 0 0 12px !important;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 700;
        }

        .file-input-wrapper {
            position: relative;
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.25s, background 0.25s;
            cursor: pointer;
        }
        .file-input-wrapper:hover { border-color: var(--pink); background: #fff0f5; }
        .file-input-wrapper input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-input-wrapper .file-icon { font-size: 32px; color: #cbd5e1; margin-bottom: 8px; }
        .file-input-wrapper p { font-size: 13px; color: #94a3b8; margin: 0; }
        .file-input-wrapper small { font-size: 11px; color: #cbd5e1; }

        #preview-img {
            display: none;
            width: 100%; max-height: 180px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 12px;
            border: 2px solid #ffe0ec;
        }

        .btn-simpan {
            background: linear-gradient(135deg, var(--pink), var(--pink-dark));
            color: white;
            border-radius: 14px;
            padding: 14px 28px;
            font-weight: 800;
            border: none;
            width: 100%;
            font-size: 15px;
            letter-spacing: 0.3px;
            transition: transform 0.25s, box-shadow 0.25s;
            margin-top: 8px;
        }
        .btn-simpan:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(232, 69, 122, 0.35);
            color: white;
        }

        .alert-custom {
            background: #fff1f3;
            border: none;
            border-left: 4px solid #f87171;
            border-radius: 12px;
            color: #991b1b;
            font-size: 13px;
            padding: 12px 16px;
        }

        @media (max-width: 768px) {
            .main-card { flex-direction: column; }
            .side-visual { width: 100%; padding: 36px 30px; }
            .form-side { padding: 36px 24px; }
        }
        .btn-batal {
    background: #e2e8f0; /* Warna abu-abu muda */
    color: #475569;      /* Warna teks abu-abu tua */
    border-radius: 14px; /* Menyamakan dengan border-radius .btn-simpan */
    padding: 14px 28px;
    font-weight: 800;
    border: none;
    width: 100%;
    font-size: 15px;
    text-align: center;
    display: block;
    text-decoration: none;
    transition: transform 0.25s, box-shadow 0.25s, background 0.25s;
    margin-top: 12px;
}

.btn-batal:hover {
    background: #cbd5e1;
    color: #1e293b;
    transform: translateY(-3px); /* Efek melayang yang sama dengan tombol simpan */
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}
    </style>
</head>
<body>
<div class="wrapper">
    <a href="list.php" class="back-link">
        <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Daftar Ruangan
    </a>

    <div class="main-card">
        <!-- Sisi Kiri Visual -->
        <div class="side-visual">
            <div>
                <div class="side-icon">🏠</div>
                <div class="tag">Master Data</div>
                <h2>Ruangan Studio Baru.</h2>
                <p>Tambahkan ruangan studio yang akan tersedia untuk booking oleh pelanggan SpotLight.</p>
            </div>
            <div class="tips-box">
                <i class="bi bi-lightbulb-fill"></i>
                <b>Tips:</b> Isi deskripsi dan fasilitas dengan detail agar pelanggan dapat memilih ruangan yang sesuai kebutuhan sesi foto mereka.
            </div>
        </div>

        <!-- Sisi Kanan Form -->
        <div class="form-side">
            <h4>Input Ruangan Studio</h4>
            <p class="subtitle">Lengkapi seluruh informasi di bawah ini dengan akurat.</p>

            <?php if ($error != ""): ?>
                <div class="alert-custom mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Nama Ruangan -->
                <div class="mb-3">
                    <label class="form-label">Nama Ruangan</label>
                    <input type="text" name="nama_ruangan" class="form-control" required placeholder="Contoh: Studio A – Classic White">
                </div>

                <!-- Kapasitas & Luas -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kapasitas Maksimal (Orang)</label>
                        <input type="number" name="kapasitas" class="form-control" required placeholder="5" min="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Luas Ruangan</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-aspect-ratio"></i></span>
                            <input type="text" name="luas" class="form-control" placeholder="Contoh: 4x5 m²">
                        </div>
                    </div>
                </div>

                <!-- Fasilitas -->
                <div class="mb-3">
                    <label class="form-label">Fasilitas Tersedia</label>
                    <input type="text" name="fasilitas" class="form-control" placeholder="Contoh: AC, Backdrop, Lighting Kit, Cermin">
                    <small class="text-muted" style="font-size: 11px; margin-top: 5px; display: block;">Pisahkan fasilitas dengan koma.</small>
                </div>

                <!-- Deskripsi -->
                <div class="mb-3">
                    <label class="form-label">Deskripsi Ruangan</label>
                    <textarea name="deskripsi" class="form-control" rows="3" required placeholder="Deskripsikan suasana dan keunggulan ruangan ini..."></textarea>
                </div>

                <!-- Upload Foto -->
                <div class="mb-4">
                    <label class="form-label">Foto Ruangan</label>
                    <div class="file-input-wrapper" id="dropzone">
                        <input type="file" name="foto" id="foto-input" required accept="image/jpg,image/jpeg,image/png" onchange="previewImage(event)">
                        <div class="file-icon">📷</div>
                        <p>Klik atau seret foto ke sini</p>
                        <small>JPG, JPEG, PNG – Maks 2MB</small>
                    </div>
                    <img id="preview-img" src="" alt="Preview Foto Ruangan">
                </div>

               <button type="submit" name="simpan" class="btn btn-simpan">
    <i class="bi bi-check2-circle me-2"></i>Simpan Ruangan
</button>


                <div class="text-center mt-3">
                    <a href="list.php" class="btn-batal shadow-sm">
    <i class="bi bi-x-circle me-1"></i> Batal & Kembali
</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Ruangan studio baru telah ditambahkan ke sistem.',
        confirmButtonColor: '#e8457a'
    }).then(() => window.location = 'list.php');
</script>
<?php endif; ?>

<script>
function previewImage(event) {
    const file  = event.target.files[0];
    const img   = document.getElementById('preview-img');
    const dz    = document.getElementById('dropzone');
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            img.style.display = 'block';
            dz.querySelector('.file-icon').style.display = 'none';
            dz.querySelector('p').textContent = file.name;
        };
        reader.readAsDataURL(file);
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
