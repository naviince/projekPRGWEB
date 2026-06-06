<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: list.php"); exit(); }

$success = false; $error = "";

// 1. AMBIL DATA LAMA
$sql  = "SELECT * FROM Tema_Foto WHERE ID_Tema = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$data) { header("Location: list.php"); exit(); }

// 2. PROSES UPDATE
if (isset($_POST['update'])) {
    $nama_tema = trim($_POST['nama_tema']);
    $konsep    = trim($_POST['konsep']);
    $suasana   = trim($_POST['suasana']);
    $properti  = trim($_POST['properti']);
    $desc      = trim($_POST['deskripsi']);

    if (empty($nama_tema)) {
        $error = "Nama tema tidak boleh kosong!";
    } else {
        if ($_FILES['foto']['name'] != "") {
            $foto_name = $_FILES['foto']['name'];
            $foto_size = $_FILES['foto']['size'];
            $ext       = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed   = ['jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowed)) {
                $error = "Format gambar harus JPG, JPEG, atau PNG!";
            } elseif ($foto_size > 2000000) {
                $error = "Ukuran gambar maksimal 2MB!";
            } else {
                $new_name    = "tema_" . time() . "." . $ext;
                $upload_path = "../../assets/img/tema/" . $new_name;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                    // Hapus foto lama
                    $old = $data['Foto_Tema'];
                    if (!empty($old) && $old != 'default_tema.jpg' && file_exists("../../assets/img/tema/" . $old)) {
                        unlink("../../assets/img/tema/" . $old);
                    }
                    $sql_u  = "UPDATE Tema_Foto SET Nama_Tema=?, Konsep=?, Suasana=?, Properti_Pendukung=?, Deskripsi=?, Foto_Tema=? WHERE ID_Tema=?";
                    $params = array($nama_tema, $konsep, $suasana, $properti, $desc, $new_name, $id);
                } else {
                    $error = "Gagal mengunggah foto baru.";
                }
            }
        } else {
            $sql_u  = "UPDATE Tema_Foto SET Nama_Tema=?, Konsep=?, Suasana=?, Properti_Pendukung=?, Deskripsi=? WHERE ID_Tema=?";
            $params = array($nama_tema, $konsep, $suasana, $properti, $desc, $id);
        }

        if ($error == "") {
            $res = sqlsrv_query($conn, $sql_u, $params);
            if ($res) { $success = true; }
            else      { $error = "Gagal memperbarui data ke database!"; }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tema Foto – SpotLight Studio</title>
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
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 30px 15px;
        }
        .wrapper { width: 100%; max-width: 1050px; }

        .back-link {
            text-decoration: none; color: #64748b;
            font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px;
            margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: var(--pink); }

        .main-card {
            border-radius: 30px; overflow: hidden;
            background: white;
            box-shadow: 0 25px 70px rgba(232, 69, 122, 0.12);
            border: none; display: flex;
        }

        .side-visual {
            width: 38%; flex-shrink: 0;
            background: linear-gradient(150deg, #e8457a 0%, #6b21a8 100%);
            padding: 50px 40px; color: white;
            display: flex; flex-direction: column; justify-content: space-between;
            position: relative; overflow: hidden;
        }
        .side-visual::before {
            content: ''; position: absolute;
            width: 260px; height: 260px; border-radius: 50%;
            background: rgba(255,255,255,0.07);
            bottom: -60px; right: -70px;
        }
        .side-visual::after {
            content: ''; position: absolute;
            width: 150px; height: 150px; border-radius: 50%;
            background: rgba(255,255,255,0.05);
            top: 30px; left: -40px;
        }
        .side-icon {
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 24px; backdrop-filter: blur(4px);
        }
        .side-visual .tag {
            font-size: 11px; font-weight: 800;
            letter-spacing: 2px; text-transform: uppercase;
            opacity: 0.6; margin-bottom: 10px;
        }
        .side-visual h2 { font-weight: 800; font-size: 26px; line-height: 1.3; margin-bottom: 14px; }
        .side-visual p  { opacity: 0.75; font-size: 13.5px; line-height: 1.75; }

        .current-foto-box {
            background: rgba(255,255,255,0.12);
            border-radius: 16px; overflow: hidden;
            backdrop-filter: blur(4px);
        }
        .current-foto-box img {
            width: 100%; height: 150px; object-fit: cover; display: block;
        }
        .current-foto-box .foto-label {
            padding: 10px 14px; font-size: 10px; opacity: 0.7; font-weight: 700;
        }

        .form-side { flex: 1; padding: 50px 45px; overflow-y: auto; }
        .form-side h4 { font-weight: 800; color: #1e293b; margin-bottom: 6px; }
        .form-side .subtitle { font-size: 13px; color: #94a3b8; margin-bottom: 30px; }

        .form-label {
            font-weight: 700; font-size: 11px; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;
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

        .file-input-wrapper {
            position: relative; background: #f8fafc;
            border: 2px dashed #e2e8f0; border-radius: 14px;
            padding: 18px; text-align: center;
            transition: border-color 0.25s, background 0.25s; cursor: pointer;
        }
        .file-input-wrapper:hover { border-color: var(--pink); background: #fff0f5; }
        .file-input-wrapper input[type="file"] {
            position: absolute; inset: 0; opacity: 0;
            cursor: pointer; width: 100%; height: 100%;
        }
        .file-input-wrapper p { font-size: 13px; color: #94a3b8; margin: 0; }

        #preview-img {
            display: none; width: 100%; max-height: 160px;
            object-fit: cover; border-radius: 12px;
            margin-top: 12px; border: 2px solid #ffe0ec;
        }

        .btn-update {
            background: linear-gradient(135deg, var(--pink), #6b21a8);
            color: white; border-radius: 14px;
            padding: 14px 28px; font-weight: 800;
            border: none; width: 100%; font-size: 15px;
            transition: transform 0.25s, box-shadow 0.25s;
            margin-top: 8px;
        }
        .btn-update:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(107,33,168,0.3);
            color: white;
        }

        .alert-custom {
            background: #fff1f3; border: none;
            border-left: 4px solid #f87171;
            border-radius: 12px; color: #991b1b;
            font-size: 13px; padding: 12px 16px;
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
        <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Daftar Tema
    </a>

    <div class="main-card">
        <!-- Sisi Kiri -->
        <div class="side-visual">
            <div>
                <div class="side-icon">✏️</div>
                <div class="tag">Edit Data</div>
                <h2>Perbarui Tema Foto.</h2>
                <p>Sesuaikan konsep, properti, atau foto referensi agar tema selalu relevan dengan tren pelanggan.</p>
            </div>

            <?php
                $path_preview = "../../assets/img/tema/" . $data['Foto_Tema'];
                $src_preview  = (!empty($data['Foto_Tema']) && file_exists($path_preview))
                                    ? $path_preview
                                    : "https://placehold.co/400x200?text=No+Image";
            ?>
            <div class="current-foto-box">
                <img src="<?= $src_preview ?>" alt="Foto Saat Ini">
                <div class="foto-label">📁 Foto saat ini: <?= htmlspecialchars($data['Foto_Tema'] ?: 'Belum ada') ?></div>
            </div>
        </div>

        <!-- Sisi Kanan -->
        <div class="form-side">
            <h4>Edit Data Tema</h4>
            <p class="subtitle">Ubah field yang perlu diperbarui, lalu simpan.</p>

            <?php if ($error != ""): ?>
                <div class="alert-custom mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Nama Tema</label>
                    <input type="text" name="nama_tema" class="form-control"
                           value="<?= htmlspecialchars($data['Nama_Tema']) ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Konsep Tema</label>
                        <input type="text" name="konsep" class="form-control"
                               value="<?= htmlspecialchars($data['Konsep']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Suasana / Mood</label>
                        <input type="text" name="suasana" class="form-control"
                               value="<?= htmlspecialchars($data['Suasana']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Properti Pendukung</label>
                    <input type="text" name="properti" class="form-control"
                           value="<?= htmlspecialchars($data['Properti_Pendukung']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi Tema</label>
                    <textarea name="deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($data['Deskripsi']) ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label">Ganti Foto Referensi
                        <span style="color:#94a3b8; text-transform:none; letter-spacing:0;">(kosongkan jika tidak diganti)</span>
                    </label>
                    <div class="file-input-wrapper">
                        <input type="file" name="foto" accept="image/jpg,image/jpeg,image/png" onchange="previewImage(event)">
                        <p>🖼️ Klik untuk pilih foto baru – JPG/PNG maks 2MB</p>
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
        text: 'Data tema foto telah diperbarui.',
        showConfirmButton: false,
        timer: 1500
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
