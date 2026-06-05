<?php
session_start();
include '../../koneksi.php';
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { header("Location: ../../login.php"); exit(); }

// Ambil Statistik untuk Kotak Atas
$stats_admin = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin'"), SQLSRV_FETCH_ASSOC);
$stats_cust  = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Customer'"), SQLSRV_FETCH_ASSOC);
$stats_foto  = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Fotografer'"), SQLSRV_FETCH_ASSOC);
$stats_owner = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Owner'"), SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Master User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; }
        .stat-card { border: none; border-radius: 15px; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(232, 69, 122, 0.15); }
        .main-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: white; }
        .nav-pills .nav-link.active { background-color: #e8457a; box-shadow: 0 4px 12px rgba(232, 69, 122, 0.3); }
        .nav-link { color: #e8457a; font-weight: 600; }
        .status-badge { border-radius: 8px; padding: 5px 12px; font-size: 11px; font-weight: 700; }
        .btn-action { border-radius: 10px; transition: 0.2s; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><i class="bi bi-person-gear me-2"></i>Pengaturan Akun</h2>
            <a href="add.php" class="btn btn-dark px-4 py-2" style="border-radius:12px;"><i class="bi bi-plus-lg me-2"></i>Tambah Akun</a>
        </div>

        <!-- 4 KOTAK STATISTIK (REFERENSI GAMBAR KAMU) -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card p-4 bg-white text-center">
                    <div class="text-muted small fw-bold mb-1">ADMIN</div>
                    <div class="h2 fw-bold text-pink" style="color:#e8457a"><?= $stats_admin['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 bg-white text-center">
                    <div class="text-muted small fw-bold mb-1">CUSTOMER</div>
                    <div class="h2 fw-bold text-primary"><?= $stats_cust['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 bg-white text-center">
                    <div class="text-muted small fw-bold mb-1">FOTOGRAFER</div>
                    <div class="h2 fw-bold text-warning"><?= $stats_foto['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 bg-white text-center">
                    <div class="text-muted small fw-bold mb-1">OWNER</div>
                    <div class="h2 fw-bold text-info"><?= $stats_owner['total'] ?></div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT DENGAN TABS -->
        <div class="main-card p-4">
            <ul class="nav nav-pills mb-4 gap-2" id="pills-tab" role="tablist">
                <li class="nav-item"><button class="nav-link active rounded-3" data-bs-toggle="pill" data-bs-target="#tab-admin">Admin</button></li>
                <li class="nav-item"><button class="nav-link rounded-3" data-bs-toggle="pill" data-bs-target="#tab-customer">Customer</button></li>
                <li class="nav-item"><button class="nav-link rounded-3" data-bs-toggle="pill" data-bs-target="#tab-fotografer">Fotografer</button></li>
                <li class="nav-item"><button class="nav-link rounded-3" data-bs-toggle="pill" data-bs-target="#tab-owner">Owner</button></li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <?php
                $roles = ['Admin', 'Customer', 'Fotografer', 'Owner'];
                foreach($roles as $role):
                    $active = ($role == 'Admin') ? 'show active' : '';
                ?>
                <div class="tab-pane fade <?= $active ?>" id="tab-<?= strtolower($role) ?>">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small text-uppercase">
                                <tr>
                                    <th>Email / Username</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q = sqlsrv_query($conn, "SELECT * FROM Users WHERE Role_User = '$role' ORDER BY ID_User DESC");
                                while($u = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= $u['Email_User'] ?></td>
                                    <td>
                                        <?php if($u['Status_User'] == 'Active'): ?>
                                            <span class="status-badge bg-success-subtle text-success border border-success-subtle">ACTIVE</span>
                                        <?php else: ?>
                                            <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                            <a href="edit.php?id=<?= $u['ID_User'] ?>" class="btn btn-white btn-sm px-3 border-end"><i class="bi bi-pencil-square text-primary"></i></a>
                                            
                                            <!-- Tombol Soft Delete (Toggle Status) -->
                                            <button onclick="softDelete(<?= $u['ID_User'] ?>, '<?= $u['Status_User'] ?>')" class="btn btn-white btn-sm px-3 border-end" title="Soft Delete">
                                                <i class="bi <?= $u['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                            </button>

                                            <!-- Tombol Hard Delete -->
                                            <button onclick="hardDelete(<?= $u['ID_User'] ?>)" class="btn btn-white btn-sm px-3" title="Hard Delete">
                                                <i class="bi bi-trash3 text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 border-top pt-3">
                <a href="../Admin/index.php" class="text-decoration-none text-muted small"><i class="bi bi-chevron-left"></i> Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script>
    // FUNGSI SOFT DELETE (Hanya ubah status)
    function softDelete(id, currentStatus) {
        let action = currentStatus === 'Active' ? 'Menonaktifkan' : 'Mengaktifkan';
        Swal.fire({
            title: action + ' Akun?',
            text: "Status akun akan diubah sementara.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#e8457a',
            confirmButtonText: 'Ya, Lakukan'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_user.php?type=soft&id=' + id + '&status=' + currentStatus;
            }
        })
    }

    // FUNGSI HARD DELETE (Hapus Permanen)
    function hardDelete(id) {
        Swal.fire({
            title: 'Hapus Permanen?',
            text: "Data tidak bisa dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus Selamanya'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_user.php?type=hard&id=' + id;
            }
        })
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>