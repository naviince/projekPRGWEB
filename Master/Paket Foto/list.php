<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI HALAMAN (Akurat)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 2. AMBIL STATISTIK PAKET TERLARIS (Validasi Aman)
$top_paket = null;
$sql_top = "SELECT TOP 1 p.Nama_Paket, COUNT(o.ID_Order) as total_booked 
            FROM Paket_Foto p 
            LEFT JOIN Orders o ON p.ID_Paket = o.ID_Paket 
            GROUP BY p.Nama_Paket 
            ORDER BY total_booked DESC";

$res_top = sqlsrv_query($conn, $sql_top);
// Cek jika query berhasil (tabel Orders sudah ada)
if ($res_top !== false) {
    $top_paket = sqlsrv_fetch_array($res_top, SQLSRV_FETCH_ASSOC);
}

// 3. AMBIL DAFTAR SEMUA PAKET FOTO
$sql = "SELECT * FROM Paket_Foto ORDER BY Harga_Paket ASC";
$query = sqlsrv_query($conn, $sql);

if ($query === false) {
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}
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
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        
        /* Navigasi */
        .back-link { text-decoration: none; color: #64748b; font-weight: 700; font-size: 13px; transition: 0.3s; }
        .back-link:hover { color: #e8457a; }

        /* Statistik Card */
        .stat-card { 
            border: none; border-radius: 20px; background: white; 
            box-shadow: 0 8px 25px rgba(232, 69, 122, 0.08); 
            border-left: 6px solid #e8457a; 
        }

        /* Main Table Card */
        .main-card { 
            border: none; border-radius: 25px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.06); 
            background: white; overflow: hidden; 
        }

        .btn-pink { background: #e8457a; color: white; border-radius: 12px; font-weight: 700; padding: 10px 24px; border:none; transition: 0.3s; }
        .btn-pink:hover { background: #c73165; transform: scale(1.03); color: white; }

        .paket-img { width: 70px; height: 70px; object-fit: cover; border-radius: 15px; border: 2px solid #ffe0ec; }
        
        /* Status Badges */
        .status-badge { border-radius: 10px; padding: 6px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        .bg-aktif { background: #d1fae5; color: #065f46; }
        .bg-nonaktif { background: #fee2e2; color: #991b1b; }

        .btn-action { border-radius: 10px; border: 1px solid #f1f1f1; background: white; transition: 0.2s; }
        .btn-action:hover { background: #f8f9fa; border-color: #e8457a; }

        .table thead th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; padding: 18px; }
    </style>
</head>
<body>

<div class="container py-5">
    <!-- Navigasi & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="../Admin/index.php" class="back-link d-block mb-2">
                <i class="bi bi-arrow-left-circle-fill me-1"></i> KEMBALI KE DASHBOARD
            </a>
            <h2 class="fw-bold text-dark">Master Paket Foto</h2>
        </div>
        <a href="add.php" class="btn btn-pink shadow-sm">
            <i class="bi bi-plus-lg me-2"></i>Tambah Paket
        </a>
    </div>

    <!-- Statistik Terlaris -->
    <div class="stat-card p-4 mb-5">
        <div class="d-flex align-items-center">
            <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-4">
                <i class="bi bi-fire text-danger fs-2"></i>
            </div>
            <div>
                <span class="text-muted small fw-bold text-uppercase">Analisis Paket Terlaris</span>
                <h3 class="fw-bold mb-0">
                    <?= ($top_paket && $top_paket['total_booked'] > 0) ? $top_paket['Nama_Paket'] : 'Belum Ada Transaksi' ?>
                </h3>
                <p class="text-muted small mb-0">Total pemesanan sistem: <b><?= $top_paket['total_booked'] ?? 0 ?> kali</b></p>
            </div>
        </div>
    </div>

    <!-- Tabel Content -->
    <div class="main-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Visual</th>
                        <th>Layanan Paket</th>
                        <th>Harga & Durasi</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="ps-4">
                            <!-- Validasi Gambar: Jika file tidak ada, pakai placeholder -->
                            <?php 
                                $path_img = "../../assets/img/paket/" . $row['Foto_Paket'];
                                $img_src = (!empty($row['Foto_Paket']) && file_exists($path_img)) ? $path_img : "https://placehold.co/400x400?text=No+Image";
                            ?>
                            <img src="<?= $img_src ?>" class="paket-img shadow-sm">
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= $row['Nama_Paket'] ?></div>
                            <small class="text-muted d-block text-truncate" style="max-width: 180px;"><?= $row['Deskripsi'] ?></small>
                        </td>
                        <td>
                            <div class="fw-bold" style="color: #e8457a;">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.') ?></div>
                            <span class="badge bg-light text-dark" style="font-size: 10px;"><?= $row['Durasi_Waktu'] ?> MENIT</span>
                        </td>
                        <td>
                            <div class="small fw-600"><i class="bi bi-people-fill me-1 text-muted"></i> Max <?= $row['Kapasitas_Orang'] ?> Org</div>
                        </td>
                        <td>
                            <?php if($row['Status'] == 'Aktif'): ?>
                                <span class="status-badge bg-aktif">AKTIF</span>
                            <?php else: ?>
                                <span class="status-badge bg-nonaktif">NONAKTIF</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                <!-- Link Edit -->
                                <a href="edit.php?id=<?= $row['ID_Paket'] ?>" class="btn btn-sm btn-action px-3" title="Edit">
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </a>
                                
                                <!-- Tombol Soft Delete (Ubah Status) -->
                                <button onclick="toggleStatus(<?= $row['ID_Paket'] ?>, '<?= $row['Status'] ?>')" class="btn btn-sm btn-action px-3" title="Aktif/Nonaktif">
                                    <i class="bi <?= $row['Status'] == 'Aktif' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                </button>

                                <!-- Tombol Hard Delete -->
                                <button onclick="confirmDelete(<?= $row['ID_Paket'] ?>)" class="btn btn-sm btn-action px-3" title="Hapus Permanen">
                                    <i class="bi bi-trash3 text-danger"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-light bg-opacity-50 border-top text-center">
            <small class="text-muted fw-bold">SpotLight Studio Photo Management v1.0</small>
        </div>
    </div>
</div>

<!-- JavaScript Validasi & Aksi -->
<script>
// 1. FUNGSI SOFT DELETE (Ubah Status)
function toggleStatus(id, current) {
    let action = current === 'Aktif' ? 'Menonaktifkan' : 'Mengaktifkan';
    Swal.fire({
        title: action + ' Paket?',
        text: "Status paket akan berubah dan mempengaruhi tampilan di pelanggan.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Lakukan!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mengarah ke file action_paket.php
            window.location.href = 'action_paket.php?type=soft&id=' + id + '&status=' + current;
        }
    })
}

// 2. FUNGSI HARD DELETE (Hapus Total)
function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: "Data paket dan file gambar akan dihapus selamanya!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus Saja'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mengarah ke file action_paket.php dengan type hard
            window.location.href = 'action_paket.php?type=hard&id=' + id;
        }
    })
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>