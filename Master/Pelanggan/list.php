<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// 1. STATISTIK (Akurat & Efisien)
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status_User = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN Status_User = 'Inactive' THEN 1 ELSE 0 END) as inactive
              FROM Users WHERE Role_User = 'Customer'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);

// 2. QUERY LIST (Join Pelanggan & Users)
$sql = "SELECT p.*, u.Email_User, u.Status_User 
        FROM Pelanggan p 
        JOIN Users u ON p.ID_User = u.ID_User 
        WHERE u.Role_User = 'Customer'
        ORDER BY p.ID_Pelanggan DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Pelanggan – SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .stat-card { border: none; border-radius: 20px; background: white; transition: 0.3s; box-shadow: 0 4px 15px rgba(232, 69, 122, 0.04); border: 1px solid rgba(232, 69, 122, 0.05); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(232, 69, 122, 0.1); }
        .main-card { border: none; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); background: white; overflow: hidden; }
        .btn-pink { background: #e8457a; color: white; border-radius: 14px; font-weight: 700; padding: 10px 24px; border:none; transition: 0.3s; }
        .btn-pink:hover { background: #c73165; color: white; transform: scale(1.02); }
        .status-badge { border-radius: 10px; padding: 6px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        .btn-action { border-radius: 10px; border: 1px solid #f1f1f1; transition: 0.2s; background: white; }
        .btn-action:hover { background: #f8f9fa; border-color: #e8457a; }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; padding: 18px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Master Pelanggan</h2>
            <p class="text-muted mb-0">Manajemen profil dan status akses customer studio.</p>
        </div>
        <a href="add.php" class="btn btn-pink shadow-sm"><i class="bi bi-person-plus-fill me-2"></i>Tambah Pelanggan</a>
    </div>

    <!-- Statistik Grid -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="h3 fw-bold mb-0 me-3 text-dark"><?= $stats['total'] ?? 0 ?></div>
                <div class="text-muted small fw-bold">TOTAL CUSTOMER</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center border-start border-success border-4">
                <div class="h3 fw-bold mb-0 me-3 text-success"><?= $stats['active'] ?? 0 ?></div>
                <div class="text-muted small fw-bold">PELANGGAN AKTIF</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center border-start border-danger border-4">
                <div class="h3 fw-bold mb-0 me-3 text-danger"><?= $stats['inactive'] ?? 0 ?></div>
                <div class="text-muted small fw-bold">NON-AKTIF</div>
            </div>
        </div>
    </div>

    <div class="main-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Customer</th>
                        <th>Email & WhatsApp</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="me-3" style="width:45px; height:45px; background:#ffe0ec; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-person-heart text-pink" style="color:#e8457a"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= $row['Nama_Pelanggan'] ?></div>
                                    <small class="text-muted">ID: #<?= $row['ID_Pelanggan'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-600 text-dark"><?= $row['Email_User'] ?></div>
                            <div class="text-success small"><i class="bi bi-whatsapp me-1"></i><?= $row['No_Hp'] ?></div>
                        </td>
                        <td>
                            <span class="status-badge <?= $row['Status_User'] == 'Active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <?= strtoupper($row['Status_User']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-action btn-sm px-3 border-end" title="Edit Profil">
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </a>
                                
                                <!-- SOFT DELETE: Ubah Status -->
                                <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-action btn-sm px-3 border-end" title="Ubah Status (Soft Delete)">
                                    <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                </button>

                                <!-- HARD DELETE: Hapus Total -->
                                <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-action btn-sm px-3" title="Hapus Permanen">
                                    <i class="bi bi-trash3 text-danger"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-light-subtle border-top d-flex justify-content-between align-items-center">
            <a href="../Admin/index.php" class="text-decoration-none text-muted small fw-600"><i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard</a>
            <span class="text-muted small" style="font-size: 10px;">SpotLight Customer Protocol: Secured</span>
        </div>
    </div>
</div>

<script>
// Validasi 1: Soft Delete (Ganti Status)
function toggleStatus(id, current) {
    let action = current === 'Active' ? 'Menonaktifkan' : 'Aktifkan';
    Swal.fire({
        title: action + ' Pelanggan?',
        text: "Status akun akan diubah, pelanggan tidak akan bisa login sementara.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Lakukan'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_pelanggan.php?type=soft&id=' + id + '&status=' + current;
        }
    })
}

// Validasi 2: Hard Delete (Hapus Total)
function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: "Data profil dan akun pelanggan ini akan dihapus selamanya!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus Selamanya'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_pelanggan.php?type=hard&id=' + id;
        }
    })
}
</script>
</body>
</html>