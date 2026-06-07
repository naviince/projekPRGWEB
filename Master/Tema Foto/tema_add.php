<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php"); exit();
}

$error = ""; $success = false;

if (isset($_POST['simpan'])) {
    $nama_tema = trim($_POST['nama_tema']);
    $kategori  = trim($_POST['kategori_tema']);
    $properti  = trim($_POST['properti']);
    $desc      = trim($_POST['deskripsi']);

    if (empty($nama_tema)) {
        $error = "Nama tema tidak boleh kosong!";
    } else {
        $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];
        if (!in_array($ext, $allowed)) { $error = "Format gambar harus JPG, JPEG, atau PNG!"; }
        elseif ($_FILES['foto']['size'] > 2000000) { $error = "Ukuran gambar maksimal 2MB!"; }
        else {
            $new_filename = "tema_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], "../../assets/img/tema/" . $new_filename)) {
                $sql    = "INSERT INTO Tema_Foto (Nama_Tema, Kategori_Tema, Properti_Pendukung, Deskripsi, Foto_Tema, Status, Created_at) VALUES (?, ?, ?, ?, ?, 'Aktif', GETDATE())";
                $params = array($nama_tema, $kategori, $properti, $desc, $new_filename);
                $stmt   = sqlsrv_query($conn, $sql, $params);
                if ($stmt) { $success = true; } else { $error = "Gagal menyimpan data ke database."; }
            } else { $error = "Gagal mengunggah foto ke server."; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Tema Foto – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --pink:#e8457a; --pink-dark:#c73165; --pink-deep:#8b1a3e; --purple:#6b21a8; --bg:#fdf2f7; }
        * { box-sizing:border-box; }
        body { background:var(--bg); font-family:'Plus Jakarta Sans',sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:30px 15px; }
        .wrapper { width:100%; max-width:1050px; }
        .back-link { text-decoration:none; color:#64748b; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px; margin-bottom:20px; transition:color 0.2s; }
        .back-link:hover { color:var(--pink); }
        .main-card { border-radius:30px; overflow:hidden; background:white; box-shadow:0 25px 70px rgba(232,69,122,0.12); border:none; display:flex; }
        .side-visual { width:38%; flex-shrink:0; background:linear-gradient(150deg,var(--purple) 0%,var(--pink) 100%); padding:50px 40px; color:white; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
        .side-visual::before { content:''; position:absolute; width:260px; height:260px; border-radius:50%; background:rgba(255,255,255,0.07); top:-60px; right:-70px; }
        .side-visual::after  { content:''; position:absolute; width:160px; height:160px; border-radius:50%; background:rgba(255,255,255,0.05); bottom:40px; left:-50px; }
        .side-icon { width:60px; height:60px; background:rgba(255,255,255,0.15); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:24px; }
        .side-visual .tag { font-size:11px; font-weight:800; letter-spacing:2px; text-transform:uppercase; opacity:0.6; margin-bottom:10px; }
        .side-visual h2 { font-weight:800; font-size:26px; line-height:1.3; margin-bottom:14px; }
        .side-visual p  { opacity:0.75; font-size:13.5px; line-height:1.75; }
        .tips-box { background:rgba(255,255,255,0.12); border-radius:16px; padding:16px 18px; font-size:12px; opacity:0.85; line-height:1.65; }
        .form-side { flex:1; padding:50px 45px; overflow-y:auto; }
        .form-side h4 { font-weight:800; color:#1e293b; margin-bottom:6px; }
        .form-side .subtitle { font-size:13px; color:#94a3b8; margin-bottom:30px; }
        .form-label { font-weight:700; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .form-control { background:#f8fafc!important; border:2px solid transparent!important; border-radius:12px!important; padding:12px 15px!important; font-size:14px!important; transition:border-color 0.25s!important; }
        .form-control:focus { border-color:var(--purple)!important; box-shadow:0 0 0 4px rgba(107,33,168,0.08)!important; }
        .file-input-wrapper { position:relative; background:#f8fafc; border:2px dashed #e2e8f0; border-radius:14px; padding:22px; text-align:center; transition:border-color 0.25s,background 0.25s; cursor:pointer; }
        .file-input-wrapper:hover { border-color:var(--purple); background:#faf5ff; }
        .file-input-wrapper input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .file-input-wrapper p { font-size:13px; color:#94a3b8; margin:0; }
        .file-input-wrapper small { font-size:11px; color:#cbd5e1; }
        #preview-img { display:none; width:100%; max-height:190px; object-fit:cover; border-radius:12px; margin-top:12px; border:2px solid #e9d5ff; }
        .btn-simpan { background:linear-gradient(135deg,var(--purple),var(--pink)); color:white; border-radius:14px; padding:14px 28px; font-weight:800; border:none; width:100%; font-size:15px; transition:transform 0.25s,box-shadow 0.25s; margin-top:8px; }
        .btn-simpan:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(107,33,168,0.3); color:white; }
        .alert-custom { background:#fff1f3; border:none; border-left:4px solid #f87171; border-radius:12px; color:#991b1b; font-size:13px; padding:12px 16px; }
        @media(max-width:768px){ .main-card{flex-direction:column;} .side-visual{width:100%;padding:36px 30px;} .form-side{padding:36px 24px;} }
    </style>
</head>
<body>
<div class="wrapper">
    <a href="list.php" class="back-link"><i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Daftar Tema</a>
    <div class="main-card">
        <div class="side-visual">
            <div>
                <div class="side-icon">🎨</div>
                <div class="tag">Master Data</div>
                <h2>Tema Foto Baru.</h2>
                <p>Tambahkan tema fotografi yang akan dipilih pelanggan saat melakukan booking sesi studio.</p>
            </div>
            <div class="tips-box">
                <i class="bi bi-lightbulb-fill me-1"></i>
                <b>Tips:</b> Isi kategori dan properti pendukung dengan detail agar pelanggan dapat membayangkan nuansa tema sebelum booking.
            </div>
        </div>
        <div class="form-side">
            <h4>Input Tema Foto</h4>
            <p class="subtitle">Lengkapi seluruh informasi tema fotografi di bawah ini.</p>

            <?php if ($error != ""): ?>
                <div class="alert-custom mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Nama Tema</label>
                    <input type="text" name="nama_tema" class="form-control" required placeholder="Contoh: Vintage Retro, Dark Elegance, Pastel Dreamy">
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori Tema</label>
                    <select name="kategori_tema" class="form-control">
                        <option value="">– Pilih Kategori –</option>
                        <option value="Classic">Classic</option>
                        <option value="Modern">Modern</option>
                        <option value="Vintage">Vintage</option>
                        <option value="Minimalis">Minimalis</option>
                        <option value="Romantic">Romantic</option>
                        <option value="Dark">Dark</option>
                        <option value="Outdoor">Outdoor</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Properti Pendukung</label>
                    <input type="text" name="properti" class="form-control" placeholder="Contoh: Kursi Antik, Lampu Vintage, Backdrop Floral">
                    <small class="text-muted" style="font-size:11px;margin-top:5px;display:block;">Pisahkan properti dengan koma.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi Tema</label>
                    <textarea name="deskripsi" class="form-control" rows="3" required placeholder="Jelaskan nuansa, cerita, dan keunggulan tema ini..."></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label">Foto Referensi Tema</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="foto" required accept="image/jpg,image/jpeg,image/png" onchange="previewImage(event)">
                        <div style="font-size:34px;margin-bottom:8px;">🖼️</div>
                        <p>Klik atau seret foto referensi ke sini</p>
                        <small>JPG, JPEG, PNG – Maks 2MB</small>
                    </div>
                    <img id="preview-img" src="" alt="Preview Foto Tema">
                </div>
                <button type="submit" name="simpan" class="btn btn-simpan"><i class="bi bi-palette-fill me-2"></i>Simpan Tema Foto</button>
                <div class="text-center mt-3"><a href="list.php" class="text-decoration-none text-muted small fw-bold">Batal & Kembali</a></div>
            </form>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>Swal.fire({icon:'success',title:'Berhasil!',text:'Tema foto baru telah ditambahkan.',confirmButtonColor:'#6b21a8'}).then(()=>window.location='list.php');</script>
<?php endif; ?>
<script>
function previewImage(e){
    const f=e.target.files[0],img=document.getElementById('preview-img');
    if(f){const r=new FileReader();r.onload=x=>{img.src=x.target.result;img.style.display='block';};r.readAsDataURL(f);}
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
