<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI HALAMAN (Akurat)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 2. AMBIL STATISTIK PAKET TERLARIS (Validasi Cerdas)
$top_paket = null;
$sql_top = "SELECT TOP 1 p.Nama_Paket, p.Foto_Paket, COUNT(o.ID_Order) as total_booked 
            FROM Paket_Foto p 
            LEFT JOIN Orders o ON p.ID_Paket = o.ID_Paket 
            GROUP BY p.Nama_Paket, p.Foto_Paket 
            ORDER BY total_booked DESC";

$res_top = sqlsrv_query($conn, $sql_top);
if ($res_top !== false) {
    $top_paket = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC);
}

// 3. AMBIL DAFTAR SEMUA PAKET FOTO
$sql = "SELECT * FROM Paket_Foto ORDER BY Harga_Paket ASC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Paket Foto – SpotLight Studio</title>
    
    <!-- CSS & Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        
        /* 3D Visual Effects */
        .premium-card {
            border: none; border-radius: 30px; background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04), 0 20px 60px rgba(232, 69, 122, 0.08);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .premium-card:hover { transform: translateY(-5px); }

        /* Statistik "3D" Style */
        .best-seller-box {
            background: linear-gradient(135deg, white 0%, #fff9fb 100%);
            border-left: 8px solid var(--p-pink);
            position: relative; overflow: hidden;
        }
        .best-seller-box::after {
            content: 'TOP'; position: absolute; top: -10px; right: -10px;
            font-size: 5rem; font-weight: 900; color: rgba(232, 69, 122, 0.03);
        }

        .btn-pink { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: white; border-radius: 16px; font-weight: 800; padding: 12px 28px; 
            border:none; transition: 0.4s; box-shadow: 0 10px 20px rgba(232, 69, 122, 0.25);
        }
        .btn-pink:hover { transform: scale(1.05); box-shadow: 0 15px 30px rgba(232, 69, 122, 0.4); color: white; }

        .paket-img { 
            width: 80px; height: 80px; object-fit: cover; border-radius: 20px; 
            box-shadow: 0 8px 15px rgba(0,0,0,0.1); border: 3px solid white;
            transition: 0.3s;
        }
        tr:hover .paket-img { transform: scale(1.1) rotate(3deg); }
        
        .status-badge { 
            border-radius: 12px; padding: 7px 16px; font-size: 10px; font-weight: 800; 
            letter-spacing: 1px; box-shadow: inset 0 -2px 0 rgba(0,0,0,0.1);
        }
        .bg-aktif { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .bg-nonaktif { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }

        .table thead th { 
            background: #f8fafc; color: #94a3b8; font-size: 11px; 
            text-transform: uppercase; font-weight: 800; padding: 22px; 
            letter-spacing: 1px; border: none;
        }
        
        .btn-action { 
            border-radius: 14px; border: 1.5px solid #f1f5f9; 
            background: white; transition: 0.3s; padding: 10px 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .btn-action:hover { background: var(--s-pink); border-color: var(--p-pink); transform: translateY(-3px); }

        .back-link { 
            display: inline-flex; align-items: center; gap: 8px;
            background: white; padding: 10px 20px; border-radius: 50px;
            color: #64748b; font-weight: 800; font-size: 12px; text-decoration: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s;
        }
        .back-link:hover { background: var(--p-pink); color: white; transform: translateX(-5px); }
    </style>
</head>
<body>

<div class="container py-5">
    <!-- Header Navigasi -->
    <div class="row align-items-center mb-5">
        <div class="col-md-7">
            <a href="../Admin/index.php" class="back-link mb-3">
                <i class="bi bi-grid-1x2-fill"></i> DASHBOARD UTAMA
            </a>
            <h2 class="fw-bold text-dark mb-1" style="font-size: 2.2rem;">Master Paket Foto</h2>
            <p class="text-muted fw-500 mb-0">Kelola standar kualitas dan nilai jual layanan SpotLight Studio.</p>
        </div>
        <div class="col-md-5 text-md-end mt-4 mt-md-0">
            <a href="add.php" class="btn btn-pink">
                <i class="bi bi-plus-circle-fill me-2"></i>BUAT PAKET BARU
            </a>
        </div>
    </div>

    <!-- Statistik "Best Seller" 3D Box -->
    <div class="premium-card best-seller-box p-4 mb-5">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="icon-gradient" style="width:70px; height:70px; background:linear-gradient(135deg, #f59e0b, #d97706); border-radius:20px; display:flex; align-items:center; justify-content:center; color:white; font-size:2rem; box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);">
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
            <div class="col">
                <span class="text-muted small fw-800 text-uppercase letter-spacing-1">Layanan Paling Diminati</span>
                <h3 class="fw-bold mb-0 text-dark">
                    <?= ($top_paket && $top_paket['total_booked'] > 0) ? $top_paket['Nama_Paket'] : 'Belum Ada Transaksi' ?>
                </h3>
                <div class="d-flex align-items-center mt-1">
                    <span class="badge bg-warning text-dark me-2 rounded-pill fw-bold" style="font-size:10px;">BEST SELLER</span>
                    <small class="text-muted fw-600">Total Reservasi: <?= $top_paket['total_booked'] ?? 0 ?> Kali</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="premium-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-5">Preview</th>
                        <th>Katalog Layanan</th>
                        <th>Investasi & Waktu</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th class="text-end pe-5">Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="ps-5">
                            <?php 
                                $path_img = "../../assets/img/paket/" . $row['Foto_Paket'];
                                $img_src = (!empty($row['Foto_Paket']) && file_exists($path_img)) ? $path_img : "https://placehold.co/400x400?text=No+Image";
                            ?>
                            <img src="<?= $img_src ?>" class="paket-img">
                        </td>
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 16px;"><?= $row['Nama_Paket'] ?></div>
                            <small class="text-muted d-block mt-1" style="max-width: 250px; line-height: 1.4;"><?= $row['Deskripsi'] ?></small>
                        </td>
                        <td>
                            <div class="fw-bold" style="color: var(--p-pink); font-size: 18px;">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.') ?></div>
                            <div class="small fw-800 text-muted mt-1"><i class="bi bi-stopwatch me-1"></i><?= $row['Durasi_Waktu'] ?> MENIT SESSION</div>
                        </td>
                        <td>
                            <span class="fw-bold text-dark"><i class="bi bi-people-fill me-2 color-p"></i><?= $row['Kapasitas_Orang'] ?> Orang</span>
                        </td>
                        <td>
                            <?php if($row['Status'] == 'Aktif'): ?>
                                <span class="status-badge bg-aktif">AKTIF</span>
                            <?php else: ?>
                                <span class="status-badge bg-nonaktif">NONAKTIF</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-5">
                            <div class="btn-group gap-2">
                                <a href="edit.php?id=<?= $row['ID_Paket'] ?>" class="btn-action" title="Ubah Data"><i class="bi bi-pencil-square text-primary"></i></a>
                                <button onclick="toggleStatus(<?= $row['ID_Paket'] ?>, '<?= $row['Status'] ?>')" class="btn-action" title="Toggle Status">
                                    <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $row['ID_Paket'] ?>)" class="btn-action" title="Hapus Permanen"><i class="bi bi-trash3-fill text-danger"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-light bg-opacity-50 border-top d-flex justify-content-between">
            <small class="text-muted fw-bold">SPOTLIGHT SECURITY PROTOCOL: VERIFIED</small>
            <small class="text-muted fw-bold text-uppercase">Total Katalog: <?= sqlsrv_num_rows($query) ?> Paket</small>
        </div>
    </div>
</div>

<script>
function toggleStatus(id, current) {
    let action = current === 'Aktif' ? 'Menonaktifkan' : 'Mengaktifkan';
    Swal.fire({
        title: action + ' Paket?',
        text: "Paket yang nonaktif tidak akan muncul di pilihan booking pelanggan.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Ubah Status',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_paket.php?type=soft&id=' + id + '&status=' + current;
        }
    })
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: "Data paket dan file gambar akan dihapus selamanya!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus Saja',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_paket.php?type=hard&id=' + id;
        }
    })
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>