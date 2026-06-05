<?php
session_start();
include '../../koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_user_login = $_SESSION['id_user'];

// 1. EFISIENSI QUERY: Ambil semua statistik karyawan dalam SATU query (Lebih Cepat)
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status_User = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN Status_User = 'Inactive' THEN 1 ELSE 0 END) as inactive
              FROM Users WHERE Role_User = 'Admin'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);

// 2. Query List Karyawan (Join dengan Karyawan) - Validasi Sinkronisasi
$sql = "SELECT u.ID_User, u.Email_User, u.Status_User, k.Nama_Karyawan, k.No_Hp, k.Foto_Profil 
        FROM Users u 
        LEFT JOIN Karyawan k ON u.ID_User = k.ID_User 
        WHERE u.Role_User = 'Admin'
        ORDER BY CASE WHEN u.ID_User = $id_user_login THEN 0 ELSE 1 END, u.ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Karyawan – Studio SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #fdf2f7; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        
        /* Stats Design */
        .stat-card { 
            border: none; 
            border-radius: 20px; 
            background: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(232, 69, 122, 0.04);
            border: 1px solid rgba(232, 69, 122, 0.05);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(232, 69, 122, 0.1); }
        .icon-box {
            width: 45px; height: 45px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }

        /* Main Table Card */
        .main-card { 
            border: none; 
            border-radius: 24px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.05); 
            background: white; 
            overflow: hidden;
        }

        .btn-pink { background: #e8457a; color: white; border-radius: 14px; font-weight: 700; padding: 10px 24px; transition: 0.3s; border:none; }
        .btn-pink:hover { background: #c73165; color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(232, 69, 122, 0.3); }

        .status-badge { border-radius: 10px; padding: 6px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        
        /* Row Highlighting */
        .is-me-row { background-color: #fff9fb !important; border-left: 4px solid #e8457a; }
        .no-data-alert { font-size: 11px; color: #e11d48; background: #fff1f2; padding: 2px 8px; border-radius: 6px; }

        .table thead th { 
            background: #f8fafc; 
            color: #64748b; 
            font-size: 11px; 
            text-transform: uppercase; 
            font-weight: 700;
            padding: 18px;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-end mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Master Karyawan</h2>
            <p class="text-muted mb-0">Manajemen profil dan otoritas karyawan sistem.</p>
        </div>
        <a href="add.php" class="btn btn-pink shadow-sm">
            <i class="bi bi-person-plus-fill me-2"></i>Tambah Karyawan
        </a>
    </div>

    <!-- Statistik Grid -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="icon-box bg-dark text-white me-3"><i class="bi bi-person-gear"></i></div>
                <div>
                    <div class="text-muted small fw-bold">TOTAL KARYAWAN</div>
                    <div class="h3 fw-bold mb-0"><?= $stats['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="icon-box bg-success-subtle text-success me-3"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="text-muted small fw-bold">STATUS AKTIF</div>
                    <div class="h3 fw-bold mb-0 text-success"><?= $stats['active'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center border-start border-danger border-3">
                <div class="icon-box bg-danger-subtle text-danger me-3"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="text-muted small fw-bold">NON-AKTIF</div>
                    <div class="h3 fw-bold mb-0 text-danger"><?= $stats['inactive'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="main-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40%;">Profil Karyawan</th>
                        <th style="width: 25%;">Informasi Kontak</th>
                        <th style="width: 15%;">Status Akun</th>
                        <th style="width: 20%;" class="text-end">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                        $is_me = ($row['ID_User'] == $id_user_login);
                        $has_profile = !empty($row['Nama_Karyawan']);
                    ?>
                    <tr class="<?= $is_me ? 'is-me-row' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center px-2">
                                <div class="position-relative">
                                    <img src="../../assets/img/<?= $row['Foto_Profil'] ?? 'default.jpg' ?>" 
                                         class="rounded-circle border" style="width:45px; height:45px; object-fit:cover;"
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['Email_User']) ?>&background=ffe0ec&color=e8457a'">
                                    <?php if($is_me): ?>
                                        <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width:12px; height:12px;"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3">
                                    <div class="fw-bold text-dark">
                                        <?= $has_profile ? $row['Nama_Karyawan'] : '<span class="text-muted fst-italic">Biodata belum diatur</span>' ?>
                                        <?php if($is_me): ?> <span class="badge bg-dark ms-1" style="font-size:8px;">SAYA</span> <?php endif; ?>
                                    </div>
                                    <div class="text-muted small"><?= $row['Email_User'] ?></div>
                                    <?php if(!$has_profile): ?> <div class="no-data-alert mt-1 d-inline-block">Lengkapi biodata!</div> <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="small fw-600"><i class="bi bi-whatsapp me-1 text-success"></i> <?= $row['No_Hp'] ?? '-' ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if($row['Status_User'] == 'Active'): ?>
                                <span class="status-badge bg-success-subtle text-success border border-success-subtle">ACTIVE</span>
                            <?php else: ?>
                                <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle">INACTIVE</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end px-4">
                            <?php if(!$is_me): ?>
                                <div class="btn-group shadow-sm rounded-3">
                                    <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-white border px-3" title="Edit Biodata">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </a>
                                    <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-sm btn-white border px-3" title="Status Toggle">
                                        <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-sm btn-white border px-3" title="Hapus Permanen">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-muted" style="font-size:11px">
                                    <i class="bi bi-person-gear me-1"></i>Kelola Profil
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-light-subtle border-top d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-muted small fw-600">
                <i class="bi bi-arrow-left me-1"></i> Dashboard Utama
            </a>
            <span class="text-muted small" style="font-size: 10px;">SpotLight Security Protocol: Active</span>
        </div>
    </div>
</div>

<script>
// UPDATE: Link target diganti ke action_karyawan.php agar alur tidak rusak
function toggleStatus(id, current) {
    let action = current === 'Active' ? 'Nonaktifkan' : 'Aktifkan';
    Swal.fire({
        title: action + ' Akun Karyawan?',
        text: "Karyawan ini tidak akan bisa mengakses sistem untuk sementara.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Ubah Status'
    }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_karyawan.php?type=soft&id=' + id + '&status=' + current; } })
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Karyawan Permanen?',
        text: "Tindakan ini tidak bisa dibatalkan dan akan menghapus semua biodata!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Hapus Selamanya'
    }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_karyawan.php?type=hard&id=' + id; } })
}
</script>
</body>
</html>