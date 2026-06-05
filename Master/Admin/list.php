<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 1. Ambil Statistik Admin untuk Kotak Atas
$stats_total  = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin'"), SQLSRV_FETCH_ASSOC);
$stats_active = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin' AND Status_User = 'Active'"), SQLSRV_FETCH_ASSOC);
$stats_non    = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin' AND Status_User = 'Inactive'"), SQLSRV_FETCH_ASSOC);

// 2. Query List Admin (Join dengan Karyawan)
$sql = "SELECT u.ID_User, u.Email_User, u.Status_User, k.Nama_Karyawan, k.No_Hp 
        FROM Users u 
        LEFT JOIN Karyawan k ON u.ID_User = k.ID_User 
        WHERE u.Role_User = 'Admin'
        ORDER BY u.ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Admin - SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; }
        .stat-card { border: none; border-radius: 15px; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: white; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(232, 69, 122, 0.12); }
        .main-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: white; }
        .text-pink { color: #e8457a !important; }
        .btn-pink { background: #e8457a; color: white; border-radius: 12px; font-weight: 600; transition: 0.3s; }
        .btn-pink:hover { background: #c73165; color: white; transform: scale(1.05); }
        .status-badge { border-radius: 8px; padding: 6px 12px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        .btn-action { border-radius: 10px; border: 1px solid #f1f1f1; transition: 0.2s; background: white; }
        .btn-action:hover { background: #f8f9fa; }
        .table thead th { background: #fafafa; color: #888; font-size: 11px; text-transform: uppercase; padding: 15px; border-top: none; }
    </style>
</head>
<body>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Master Admin</h2>
            <p class="text-muted">Manajemen hak akses administrator pusat</p>
        </div>
        <a href="add.php" class="btn btn-pink px-4 shadow-sm">
            <i class="bi bi-person-plus-fill me-2"></i>Tambah Admin
        </a>
    </div>

    <!-- Statistik Cards (Identik dengan Master User) -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card p-4 text-center">
                <div class="text-muted small fw-bold mb-1">TOTAL ADMIN</div>
                <div class="h2 fw-bold text-dark"><?= $stats_total['total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 text-center">
                <div class="text-muted small fw-bold mb-1 text-success">AKTIF</div>
                <div class="h2 fw-bold text-success"><?= $stats_active['total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 text-center">
                <div class="text-muted small fw-bold mb-1 text-danger">NON-AKTIF</div>
                <div class="h2 fw-bold text-danger"><?= $stats_non['total'] ?></div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="main-card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Administrator</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
               <tbody>
    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
        // VALIDASI OTOMATIS: Cek apakah baris ini adalah akun yang sedang login
        $is_me = ($row['ID_User'] == $_SESSION['id_user']);
    ?>
    <tr class="<?= $is_me ? 'table-light' : '' ?>">
        <td>
            <div class="d-flex align-items-center">
                <div class="avatar-pink me-3" style="width:40px; height:40px; background:<?= $is_me ? '#e8457a' : '#ffe0ec' ?>; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-person-badge <?= $is_me ? 'text-white' : 'text-pink' ?>"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">
                        <?= $row['Nama_Karyawan'] ?? 'Belum Diisi' ?>
                        <?= $is_me ? '<span class="badge bg-dark ms-1" style="font-size:9px">SAYA</span>' : '' ?>
                    </div>
                    <div class="text-muted small"><?= $row['Email_User'] ?></div>
                </div>
            </div>
        </td>
        <td>
            <div class="small fw-500 text-dark"><?= $row['No_Hp'] ?? '-' ?></div>
        </td>
        <td>
            <?php if($row['Status_User'] == 'Active'): ?>
                <span class="status-badge bg-success-subtle text-success border border-success-subtle">ACTIVE</span>
            <?php else: ?>
                <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle">INACTIVE</span>
            <?php endif; ?>
        </td>
        <td class="text-end">
            <!-- JIKA BUKAN AKUN SAYA, TAMPILKAN TOMBOL AKSI -->
            <?php if(!$is_me): ?>
                <div class="btn-group">
                    <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-action px-3" title="Edit Profile">
                        <i class="bi bi-pencil-square text-primary"></i>
                    </a>
                    <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-action px-3" title="Ubah Status">
                        <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                    </button>
                    <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-action px-3" title="Hapus Permanen">
                        <i class="bi bi-trash3 text-danger"></i>
                    </button>
                </div>
            <?php else: ?>
                <!-- JIKA AKUN SAYA, TOMBOL DIHILANGKAN AGAR TIDAK BISA DIHAPUS SENDIRI -->
                <span class="text-muted small fst-italic">Logged In</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
            </table>
        </div>
        <div class="mt-4 pt-3 border-top">
            <a href="index.php" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard Utama
            </a>
        </div>
    </div>
</div>

<script>
// VALIDASI 1: Soft Delete / Toggle Status
function toggleStatus(id, current) {
    let action = current === 'Active' ? 'Nonaktifkan' : 'Aktifkan';
    Swal.fire({
        title: action + ' Akun Admin?',
        text: "Akses login akan dibatasi sementara.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Lakukan!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_admin.php?type=soft&id=' + id + '&status=' + current;
        }
    })
}

// VALIDASI 2: Hard Delete (Hapus Total)
function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: "Seluruh data profil dan akun admin ini akan hilang!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus Selamanya'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_admin.php?type=hard&id=' + id;
        }
    })
}
</script>
</body>
</html>