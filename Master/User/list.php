<?php
session_start();
include '../../koneksi.php';

// Validasi Akses: Hanya Admin yang boleh masuk
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { 
    header("Location: ../../login.php"); 
    exit(); 
}

$id_user_login = $_SESSION['id_user'];

// Ambil Statistik dengan Query yang Efisien
$stats_admin = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Admin'"), SQLSRV_FETCH_ASSOC);
$stats_cust  = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Customer'"), SQLSRV_FETCH_ASSOC);
$stats_foto  = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Fotografer'"), SQLSRV_FETCH_ASSOC);
$stats_owner = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Users WHERE Role_User = 'Owner'"), SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master User – SpotLight Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Statistik Cards Improvement */
        .stat-card { 
            border: none; 
            border-radius: 18px; 
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
            box-shadow: 0 4px 15px rgba(232, 69, 122, 0.05);
            position: relative;
            overflow: hidden;
            background: #ffffff;
        }
        .stat-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 12px 25px rgba(232, 69, 122, 0.12); 
        }
        .stat-icon {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 3rem;
            opacity: 0.05;
            transform: rotate(-15deg);
        }

        /* Main Card */
        .main-card { 
            border: none; 
            border-radius: 24px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.06); 
            background: white; 
        }

        /* Tabs Styling */
        .nav-pills .nav-link {
            color: #6b7280;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 12px;
            transition: 0.3s;
        }
        .nav-pills .nav-link.active { 
            background-color: #e8457a; 
            box-shadow: 0 6px 15px rgba(232, 69, 122, 0.3); 
        }

        .status-badge { border-radius: 10px; padding: 6px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        
        /* Action Buttons */
        .btn-action { 
            border: 1px solid #f3f4f6;
            background: #ffffff;
            transition: 0.2s;
        }
        .btn-action:hover { background: #f9fafb; border-color: #e8457a; }
        
        .text-pink { color: #e8457a; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-person-lock me-2 text-pink"></i>Pengaturan Akun</h2>
                <p class="text-muted small mb-0">Kelola kredensial dan tingkat keamanan akses pengguna.</p>
            </div>
            <a href="add.php" class="btn btn-dark px-4 py-2 shadow-sm" style="border-radius:14px;">
                <i class="bi bi-person-plus-fill me-2"></i>Tambah Akun
            </a>
        </div>

        <!-- STATS GRID -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card p-4">
                    <i class="bi bi-shield-check stat-icon"></i>
                    <div class="text-muted small fw-bold mb-1">ADMIN</div>
                    <div class="h2 fw-bold text-pink mb-0"><?= $stats_admin['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 text-center">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="text-muted small fw-bold mb-1">CUSTOMER</div>
                    <div class="h2 fw-bold text-primary mb-0"><?= $stats_cust['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 text-center">
                    <i class="bi bi-camera stat-icon"></i>
                    <div class="text-muted small fw-bold mb-1">FOTOGRAFER</div>
                    <div class="h2 fw-bold text-warning mb-0"><?= $stats_foto['total'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 text-center">
                    <i class="bi bi-briefcase stat-icon"></i>
                    <div class="text-muted small fw-bold mb-1">OWNER</div>
                    <div class="h2 fw-bold text-info mb-0"><?= $stats_owner['total'] ?></div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-card p-4 p-md-5">
            <ul class="nav nav-pills mb-4 gap-2" id="pills-tab" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-admin">Admin</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-customer">Customer</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-fotografer">Fotografer</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-owner">Owner</button></li>
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
                                    <th>Akun Email</th>
                                    <th>Status Akses</th>
                                    <th class="text-end">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q = sqlsrv_query($conn, "SELECT * FROM Users WHERE Role_User = '$role' ORDER BY ID_User DESC");
                                while($u = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)):
                                    $is_me = ($u['ID_User'] == $id_user_login);
                                ?>
                                <tr class="<?= $is_me ? 'bg-light-subtle' : '' ?>">
                                    <td>
                                        <div class="fw-bold"><?= $u['Email_User'] ?></div>
                                        <?php if($is_me): ?>
                                            <span class="badge bg-dark" style="font-size: 9px;">SAYA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['Status_User'] == 'Active'): ?>
                                            <span class="status-badge bg-success-subtle text-success border border-success-subtle">ACTIVE</span>
                                        <?php else: ?>
                                            <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group rounded-3 shadow-sm overflow-hidden">
                                            <a href="edit.php?id=<?= $u['ID_User'] ?>" class="btn btn-action btn-sm px-3 border-end" title="Edit Akses">
                                                <i class="bi bi-shield-lock text-primary"></i>
                                            </a>
                                            
                                            <!-- Validasi Akurat: Admin tidak bisa menonaktifkan atau menghapus diri sendiri -->
                                            <?php if(!$is_me): ?>
                                                <button onclick="softDelete(<?= $u['ID_User'] ?>, '<?= $u['Status_User'] ?>')" class="btn btn-action btn-sm px-3 border-end" title="Soft Delete">
                                                    <i class="bi <?= $u['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                                </button>
                                                <button onclick="hardDelete(<?= $u['ID_User'] ?>)" class="btn btn-action btn-sm px-3" title="Hard Delete">
                                                    <i class="bi bi-trash3 text-danger"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-action btn-sm px-3 disabled" style="opacity: 0.3;">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            <?php endif; ?>
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
            
            <div class="mt-5 pt-3 border-top d-flex justify-content-between align-items-center">
                <a href="../Admin/index.php" class="text-decoration-none text-muted small fw-600">
                    <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
                </a>
                <p class="text-muted" style="font-size: 11px;">SpotLight Studio Management v1.0</p>
            </div>
        </div>
    </div>

    <script>
    function softDelete(id, currentStatus) {
        let action = currentStatus === 'Active' ? 'Menonaktifkan' : 'Mengaktifkan';
        Swal.fire({
            title: action + ' Akun?',
            text: "Pengguna tidak akan bisa masuk ke sistem untuk sementara.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e8457a',
            confirmButtonText: 'Ya, Lakukan'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_user.php?type=soft&id=' + id + '&status=' + currentStatus;
            }
        })
    }

    function hardDelete(id) {
        Swal.fire({
            title: 'Hapus Selamanya?',
            text: "Data akun dan profil terkait akan dihapus permanen dari database!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus Permanen'
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