<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php"); exit();
}

// Tema Terpopuler
$top_tema = null;
$res_top  = sqlsrv_query($conn, "SELECT TOP 1 t.Nama_Tema, COUNT(o.ID_Order) as total_booking FROM Tema_Foto t LEFT JOIN [Order] o ON t.ID_Tema = o.ID_Tema GROUP BY t.Nama_Tema ORDER BY total_booking DESC");
if ($res_top !== false) $top_tema = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC);

// Jumlah per status
$res_count  = sqlsrv_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN Status='Aktif' THEN 1 ELSE 0 END) as aktif, SUM(CASE WHEN Status='Nonaktif' THEN 1 ELSE 0 END) as nonaktif FROM Tema_Foto");
$count_data = ($res_count !== false) ? sqlsrv_fetch_array($res_count, SQLSRV_FETCH_ASSOC) : ['total'=>0,'aktif'=>0,'nonaktif'=>0];

// Semua Tema
$query = sqlsrv_query($conn, "SELECT * FROM Tema_Foto ORDER BY ID_Tema ASC");
if ($query === false) die("<pre>".print_r(sqlsrv_errors(),true)."</pre>");

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tema Foto – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --pink:#e8457a; --pink-dark:#c73165; --pink-deep:#8b1a3e; --purple:#6b21a8; --bg:#fdf2f7; }
        body { background:var(--bg); font-family:'Plus Jakarta Sans',sans-serif; color:#334155; }
        .back-link { text-decoration:none; color:#64748b; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px; margin-bottom:6px; transition:color 0.2s; }
        .back-link:hover { color:var(--pink); }
        .page-header h2 { font-weight:800; color:#1e293b; margin:0; }
        .page-header p  { font-size:13px; color:#94a3b8; margin:4px 0 0; }
        .stats-row { display:flex; gap:18px; margin-bottom:28px; flex-wrap:wrap; }
        .stat-card { flex:1; min-width:180px; border:none; border-radius:20px; background:white; padding:22px 24px; box-shadow:0 6px 20px rgba(0,0,0,0.05); display:flex; align-items:center; gap:18px; }
        .stat-card.highlight    { border-left:5px solid var(--purple); }
        .stat-card.stat-aktif   { border-left:5px solid #10b981; }
        .stat-card.stat-nonaktif{ border-left:5px solid #f59e0b; }
        .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
        .stat-icon.purple { background:#f3e8ff; }
        .stat-icon.green  { background:#d1fae5; }
        .stat-icon.amber  { background:#fef3c7; }
        .stat-content .label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:4px; }
        .stat-content .value { font-size:26px; font-weight:800; color:#1e293b; line-height:1; }
        .stat-content .sub   { font-size:11px; color:#94a3b8; margin-top:4px; }
        .main-card { border:none; border-radius:25px; box-shadow:0 12px 40px rgba(0,0,0,0.06); background:white; overflow:hidden; }
        .card-toolbar { padding:22px 28px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; }
        .card-toolbar h5 { font-weight:800; color:#1e293b; margin:0; font-size:16px; }
        .count-badge { background:#f3e8ff; color:var(--purple); font-size:11px; font-weight:800; padding:4px 12px; border-radius:20px; margin-left:10px; }
        .btn-pink { background:linear-gradient(135deg,var(--purple),var(--pink)); color:white; border-radius:12px; font-weight:700; padding:10px 22px; border:none; font-size:14px; transition:transform 0.2s,box-shadow 0.2s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-pink:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(107,33,168,0.3); color:white; }
        .table { margin:0; }
        .table thead th { background:#f8fafc; color:#94a3b8; font-size:11px; text-transform:uppercase; font-weight:700; padding:16px 20px; border-bottom:1px solid #f1f5f9; letter-spacing:0.5px; }
        .table tbody td { padding:16px 20px; vertical-align:middle; border-bottom:1px solid #f8fafc; }
        .table tbody tr:last-child td { border-bottom:none; }
        .table tbody tr:hover td { background:#fdf2f7; }
        .tema-img { width:90px; height:68px; object-fit:cover; border-radius:14px; border:2px solid #e9d5ff; box-shadow:0 4px 10px rgba(0,0,0,0.07); }
        .tema-name { font-weight:800; color:#1e293b; font-size:14px; }
        .tema-desc { font-size:12px; color:#94a3b8; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .kategori-badge { background:#f3e8ff; color:var(--purple); font-size:10px; font-weight:800; padding:5px 12px; border-radius:20px; display:inline-block; }
        .properti-tags { display:flex; flex-wrap:wrap; gap:4px; max-width:220px; margin-top:6px; }
        .properti-tag  { background:#f1f5f9; color:#64748b; font-size:10px; font-weight:700; padding:3px 9px; border-radius:20px; }
        .created-text  { font-size:11px; color:#94a3b8; }
        .status-badge { border-radius:10px; padding:6px 14px; font-size:10px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; }
        .bg-aktif    { background:#d1fae5; color:#065f46; }
        .bg-nonaktif { background:#fee2e2; color:#991b1b; }
        .btn-action { border-radius:10px; border:1px solid #f1f5f9; background:white; transition:background 0.2s,border-color 0.2s; padding:8px 12px; }
        .btn-action:hover { background:#f8fafc; border-color:var(--pink); }
        .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
        .empty-state .icon { font-size:56px; margin-bottom:16px; }
        .empty-state h5 { font-weight:800; color:#475569; }
        .card-footer-custom { padding:16px 24px; background:#f8fafc; border-top:1px solid #f1f5f9; text-align:center; }
        .card-footer-custom small { font-size:11px; color:#94a3b8; font-weight:700; }
    </style>
</head>
<body>
<div class="container py-5">
    <!-- Header -->
    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="../Admin/index.php" class="back-link"><i class="bi bi-arrow-left-circle-fill"></i> KEMBALI KE DASHBOARD</a>
            <h2>Master Tema Foto</h2>
            <p>Kelola tema dan konsep fotografi yang dapat dipilih pelanggan saat booking.</p>
        </div>
        <a href="add.php" class="btn btn-pink"><i class="bi bi-plus-lg"></i> Tambah Tema</a>
    </div>

    <!-- Statistik -->
    <div class="stats-row">
        <div class="stat-card highlight">
            <div class="stat-icon purple">🎨</div>
            <div class="stat-content">
                <div class="label">Tema Terpopuler</div>
                <div class="value" style="font-size:17px;line-height:1.3;"><?= ($top_tema && $top_tema['total_booking'] > 0) ? htmlspecialchars($top_tema['Nama_Tema']) : 'Belum Ada Data' ?></div>
                <div class="sub"><?= $top_tema['total_booking'] ?? 0 ?> kali dipilih pelanggan</div>
            </div>
        </div>
        <div class="stat-card stat-aktif">
            <div class="stat-icon green">✅</div>
            <div class="stat-content">
                <div class="label">Tema Aktif</div>
                <div class="value"><?= (int)($count_data['aktif'] ?? 0) ?></div>
                <div class="sub">Tersedia untuk dipilih</div>
            </div>
        </div>
        <div class="stat-card stat-nonaktif">
            <div class="stat-icon amber">⏸️</div>
            <div class="stat-content">
                <div class="label">Dinonaktifkan</div>
                <div class="value"><?= (int)($count_data['nonaktif'] ?? 0) ?></div>
                <div class="sub">Tidak tampil ke pelanggan</div>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="main-card">
        <div class="card-toolbar">
            <h5 class="d-inline">Daftar Tema Foto <span class="count-badge"><?= (int)($count_data['total'] ?? 0) ?> Tema</span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="ps-4">Foto Referensi</th>
                        <th>Nama & Deskripsi</th>
                        <th>Kategori & Properti</th>
                        <th>Dibuat</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no_data = true;
                while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                    $no_data  = false;
                    $path_img = "../../assets/img/tema/" . $row['Foto_Tema'];
                    $img_src  = (!empty($row['Foto_Tema']) && file_exists($path_img)) ? $path_img : "https://placehold.co/400x300?text=No+Image";
                    $prop_arr = !empty($row['Properti_Pendukung']) ? array_slice(array_map('trim', explode(',', $row['Properti_Pendukung'])), 0, 3) : [];
                    $created  = ($row['Created_at'] instanceof DateTime) ? $row['Created_at']->format('d M Y') : date('d M Y', strtotime($row['Created_at']));
                ?>
                <tr>
                    <td class="ps-4"><img src="<?= $img_src ?>" class="tema-img" alt="<?= htmlspecialchars($row['Nama_Tema']) ?>"></td>
                    <td>
                        <div class="tema-name"><?= htmlspecialchars($row['Nama_Tema']) ?></div>
                        <div class="tema-desc"><?= htmlspecialchars($row['Deskripsi']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($row['Kategori_Tema'])): ?><span class="kategori-badge"><?= htmlspecialchars($row['Kategori_Tema']) ?></span><?php endif; ?>
                        <?php if (!empty($prop_arr)): ?>
                        <div class="properti-tags">
                            <?php foreach ($prop_arr as $p): ?><span class="properti-tag"><?= htmlspecialchars($p) ?></span><?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><div class="created-text"><i class="bi bi-calendar3 me-1"></i><?= $created ?></div></td>
                    <td><span class="status-badge <?= $row['Status'] == 'Aktif' ? 'bg-aktif' : 'bg-nonaktif' ?>"><?= $row['Status'] ?></span></td>
                    <td class="text-end pe-4">
                        <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                            <a href="edit.php?id=<?= $row['ID_Tema'] ?>" class="btn btn-sm btn-action px-3" title="Edit"><i class="bi bi-pencil-square text-primary"></i></a>
                            <button onclick="toggleStatus(<?= $row['ID_Tema'] ?>, '<?= $row['Status'] ?>')" class="btn btn-sm btn-action px-3" title="Toggle Status">
                                <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                            </button>
                            <button onclick="confirmDelete(<?= $row['ID_Tema'] ?>)" class="btn btn-sm btn-action px-3" title="Hapus"><i class="bi bi-trash3 text-danger"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($no_data): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <div class="icon">🎭</div>
                        <h5>Belum Ada Tema Foto</h5>
                        <p>Tambahkan tema pertama agar pelanggan dapat memilih konsep saat booking.</p>
                        <a href="add.php" class="btn btn-pink mt-2"><i class="bi bi-plus-lg"></i> Tambah Tema Pertama</a>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer-custom"><small>SpotLight Studio – Theme Management v1.1</small></div>
    </div>
</div>

<script>
<?php if ($msg == 'status_updated'): ?>Swal.fire({icon:'success',title:'Status Diperbarui!',text:'Status tema berhasil diubah.',timer:1800,showConfirmButton:false});<?php endif; ?>
<?php if ($msg == 'deleted'): ?>Swal.fire({icon:'success',title:'Dihapus!',text:'Tema berhasil dihapus.',timer:1800,showConfirmButton:false});<?php endif; ?>

function toggleStatus(id, current) {
    Swal.fire({title:(current==='Aktif'?'Nonaktifkan':'Aktifkan')+' Tema?',text:'Status tema akan berubah dan mempengaruhi pilihan pelanggan saat booking.',icon:'question',showCancelButton:true,confirmButtonColor:'#6b21a8',cancelButtonText:'Batal',confirmButtonText:'Ya, Lakukan!'})
    .then(r=>{ if(r.isConfirmed) window.location.href='action_tema.php?type=soft&id='+id+'&status='+current; });
}
function confirmDelete(id) {
    Swal.fire({title:'Hapus Permanen?',html:'Tema dan foto referensi akan <b>dihapus selamanya</b>.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',cancelButtonText:'Batal',confirmButtonText:'Ya, Hapus!'})
    .then(r=>{ if(r.isConfirmed) window.location.href='action_tema.php?type=hard&id='+id; });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
