<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: list.php"); exit(); }

$success = false;
$error = "";

// 1. AMBIL DATA LAMA
$sql  = "SELECT * FROM Ruangan WHERE ID_Ruangan = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) { header("Location: list.php"); exit(); }

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama      = trim($_POST['nama_ruangan']);
    $kapasitas = (int)$_POST['kapasitas'];
    $luas      = trim($_POST['luas']);
    $fasilitas = trim($_POST['fasilitas']);
    $desc      = trim($_POST['deskripsi']);

    if ($kapasitas <= 0) {
        $error = "Kapasitas ruangan harus lebih dari 0!";
    } else {
        // Cek jika ada upload foto baru
        if ($_FILES['foto']['name'] != "") {
            $foto_name = $_FILES['foto']['name'];
            $foto_size = $_FILES['foto']['size'];
            $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowed)) {
                $error = "Format gambar harus JPG, JPEG, atau PNG!";
            } elseif ($foto_size > 2000000) {
                $error = "Ukuran gambar maksimal 2MB!";
            } else {
                $new_name = "ruangan_" . time() . "." . $ext;
                $upload_path = "../../assets/img/ruangan/" . $new_name;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                    // Hapus foto lama jika bukan default
                    $old_foto = $data['Foto_Ruangan'];
                    if (!empty($old_foto) && $old_foto != 'default_ruangan.jpg' && file_exists("../../assets/img/ruangan/" . $old_foto)) {
                        unlink("../../assets/img/ruangan/" . $old_foto);
                    }

                    $sql_u  = "UPDATE Ruangan SET Nama_Ruangan=?, Kapasitas=?, Luas_Ruangan=?, Fasilitas=?, Deskripsi=?, Foto_Ruangan=? WHERE ID_Ruangan=?";
                    $params = array($nama, $kapasitas, $luas, $fasilitas, $desc, $new_name, $id);
                } else {
                    $error = "Gagal mengunggah foto baru.";
                }
            }
        } else {
            // Update tanpa ganti foto
            $sql_u  = "UPDATE Ruangan SET Nama_Ruangan=?, Kapasitas=?, Luas_Ruangan=?, Fasilitas=?, Deskripsi=? WHERE ID_Ruangan=?";
            $params = array($nama, $kapasitas, $luas, $fasilitas, $desc, $id);
        }

        if ($error == "") {
            $res = sqlsrv_query($conn, $sql_u, $params);
            if ($res) {
                $success = true;
            } else {
                $error = "Gagal memperbarui data ke database!";
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
    <title>Edit Ruangan – SpotLight Studio</title>
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
            background: linear-gradient(150deg, var(--pink) 0%, var(--pink-deep) 100%);
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
            bottom: -60px; right: -80px;
        }
        .side-visual::after {
            content: '';
            position: absolute;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            top: 30px; left: -50px;
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

        .current-foto-box {
            background: rgba(255,255,255,0.12);
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(4px);
        }
        .current-foto-box img {
            width: 100%; height: 160px;
            object-fit: cover;
            display: block;
        }
        .current-foto-box .foto-label {
            padding: 10px 14px;
            font-size: 11px;
            opacity: 0.7;
            font-weight: 700;
        }

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
            padding: 18px;
            text-align: center;
            transition: border-color 0.25s, background 0.25s;
            cursor: pointer;
        }
        .file-input-wrapper:hover { border-color: var(--pink); background: #fff0f5; }
        .file-input-wrapper input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-input-wrapper p { font-size: 13px; color: #94a3b8; margin: 0; }
        .file-input-wrapper small { font-size: 11px; color: #cbd5e1; }

        #preview-img {
            display: none;
            width: 100%; max-height: 160px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 12px;
            border: 2px solid #ffe0ec;
        }

        .btn-update {
            background: linear-gradient(135deg, var(--pink-deep), var(--pink));
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
        .btn-update:hover {
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
                <div class="side-icon">✏️</div>
                <div class="tag">Edit Data</div>
                <h2>Pembaruan Ruangan.</h2>
                <p>Perbarui informasi ruangan studio agar data selalu akurat dan terkini untuk pelanggan.</p>
            </div>

            <?php
                $path_preview = "../../assets/img/ruangan/" . $data['Foto_Ruangan'];
                $src_preview = (!empty($data['Foto_Ruangan']) && file_exists($path_preview))
                    ? $path_preview
                    : "https://placehold.co/400x200?text=No+Image";
            ?>
            <div class="current-foto-box">
                <img src="<?= $src_preview ?>" alt="Foto Saat Ini">
                <div class="foto-label">📁 Foto saat ini: <?= htmlspecialchars($data['Foto_Ruangan'] ?: 'Belum ada') ?></div>
            </div>
        </div>

        <!-- Sisi Kanan Form -->
        <div class="form-side">
            <h4>Edit Data Ruangan</h4>
            <p class="subtitle">Ubah field yang ingin diperbarui, lalu simpan perubahan.</p>

            <?php if ($error != ""): ?>
                <div class="alert-custom mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Nama Ruangan -->
                <div class="mb-3">
                    <label class="form-label">Nama Ruangan</label>
                    <input type="text" name="nama_ruangan" class="form-control" value="<?= htmlspecialchars($data['Nama_Ruangan']) ?>" required>
                </div>

                <!-- Kapasitas & Luas -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kapasitas (Orang)</label>
                        <input type="number" name="kapasitas" class="form-control" value="<?= (int)$data['Kapasitas'] ?>" required min="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Luas Ruangan</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-aspect-ratio"></i></span>
                            <input type="text" name="luas" class="form-control" value="<?= htmlspecialchars($data['Luas_Ruangan']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Fasilitas -->
                <div class="mb-3">
                    <label class="form-label">Fasilitas Tersedia</label>
                    <input type="text" name="fasilitas" class="form-control" value="<?= htmlspecialchars($data['Fasilitas']) ?>">
                </div>

                <!-- Deskripsi -->
                <div class="mb-3">
                    <label class="form-label">Deskripsi Ruangan</label>
                    <textarea name="deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($data['Deskripsi']) ?></textarea>
                </div>

                <!-- Upload Foto Baru (Opsional) -->
                <div class="mb-4">
                    <label class="form-label">Ganti Foto <span style="color:#94a3b8; text-transform:none; letter-spacing:0;">(kosongkan jika tidak ingin diganti)</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" name="foto" id="foto-input" accept="image/jpg,image/jpeg,image/png" onchange="previewImage(event)">
                        <p>📷 Klik untuk pilih foto baru – JPG/PNG maks 2MB</p>
                    </div>
                    <img id="preview-img" src="" alt="Preview">
                </div>

                <button type="submit" name="update" class="btn btn-update">
                    <i class="bi bi-check2-all me-2"></i>Simpan Perubahan
                </button>

                <div class="text-center mt-3">
                    <a href="list.php" class="text-muted small text-decoration-none fw-bold">Kembali ke Daftar</a>
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
        text: 'Data ruangan telah diperbarui.',
        showConfirmButton: false,
        timer: 1500,
        confirmButtonColor: '#e8457a'
    }).then(() => window.location = 'list.php');
</script>
<?php endif; ?>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    const img  = document.getElementById('preview-img');
    if (file) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
        reader.readAsDataURL(file);
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
