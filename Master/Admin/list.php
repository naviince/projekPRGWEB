<?php
session_start();
include '../../koneksi.php';

// 1. PROTEKSI HALAMAN (Akurat & Aman)
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_user_login = $_SESSION['id_user'];

// 2. EFISIENSI QUERY: Statistik Karyawan dalam satu tarikan data
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN Status_User = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN Status_User = 'Inactive' THEN 1 ELSE 0 END) as inactive
              FROM Users WHERE Role_User = 'Admin'";
$stmt_stats = sqlsrv_query($conn, $sql_stats);
$stats = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);

// 3. QUERY DAFTAR KARYAWAN: Join tabel Users & Karyawan
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Karyawan – Studio SpotLight</title>
    
    <!-- CSS & Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --p-pink: #e8457a; --d-pink: #c73165; --s-pink: #fdf2f7; }
        body { background: var(--s-pink); font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        
        /* Stats Card Upgrade */
        .stat-card { 
            border: none; border-radius: 24px; background: white; 
            box-shadow: 0 10px 30px rgba(232, 69, 122, 0.05); 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            border: 1px solid rgba(232, 69, 122, 0.05);
        }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(232, 69, 122, 0.12); }
        .icon-gradient {
            width: 50px; height: 50px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white;
        }

        /* Main Card Table */
        .main-card { 
            border: none; border-radius: 30px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.06); 
            background: white; overflow: hidden; 
        }

        .btn-pink { background: var(--p-pink); color: white; border-radius: 14px; font-weight: 700; padding: 12px 28px; border:none; transition: 0.3s; }
        .btn-pink:hover { background: var(--d-pink); color: white; box-shadow: 0 10px 20px rgba(232, 69, 122, 0.2); }

        /* Row Highlighting */
        .is-me-row { background-color: #fff9fb !important; border-left: 5px solid var(--p-pink); }
        .status-badge { border-radius: 12px; padding: 6px 16px; font-size: 10px; font-weight: 800; letter-spacing: 0.5px; }
        
        .table thead th { 
            background: #f8fafc; color: #64748b; font-size: 11px; 
            text-transform: uppercase; font-weight: 800; padding: 20px; 
            border-bottom: 1px solid #f1f5f9;
        }
        
        .btn-action { 
            border-radius: 12px; border: 1.5px solid #f1f5f9; 
            background: white; transition: 0.2s; padding: 8px 12px;
        }
        .btn-action:hover { background: var(--s-pink); border-color: var(--p-pink); }

        .avatar-circle {
            width: 48px; height: 48px; object-fit: cover; border-radius: 15px; border: 2px solid var(--s-pink);
        }
    </style>
</head>
<body>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Master Karyawan</h2>
            <p class="text-muted mb-0 fw-500">Kelola profil dan otoritas staf Studio SpotLight.</p>
        </div>
        <a href="add.php" class="btn btn-pink shadow-sm">
            <i class="bi bi-person-plus-fill me-2"></i>Tambah Karyawan
        </a>
    </div>

    <!-- Statistik Grid -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="icon-gradient me-3 shadow-sm"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="text-muted small fw-bold">TOTAL KARYAWAN</div>
                    <div class="h3 fw-bold mb-0"><?= $stats['total'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="bi bi-check-all"></i></div>
                <div>
                    <div class="text-muted small fw-bold">STATUS AKTIF</div>
                    <div class="h3 fw-bold mb-0 text-success"><?= $stats['active'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center">
                <div class="icon-gradient me-3 shadow-sm" style="background: linear-gradient(135deg, #ef4444, #dc3545);"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="text-muted small fw-bold">NON-AKTIF</div>
                    <div class="h3 fw-bold mb-0 text-danger"><?= $stats['inactive'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Content -->
    <div class="main-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 35%;">Profil Karyawan</th>
                        <th style="width: 20%;">WhatsApp</th>
                        <th style="width: 20%;">Status</th>
                        <th style="width: 25%;" class="text-end">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                        $is_me = ($row['ID_User'] == $id_user_login);
                        $has_profile = !empty($row['Nama_Karyawan']);
                    ?>
                    <tr class="<?= $is_me ? 'is-me-row' : '' ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center py-2">
                                <img src="../../assets/img/<?= $row['Foto_Profil'] ?? 'default.jpg' ?>" 
                                     class="avatar-circle shadow-sm me-3"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['Email_User']) ?>&background=ffe0ec&color=e8457a&bold=true'">
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?= $has_profile ? $row['Nama_Karyawan'] : '<span class="text-muted fst-italic">Profil belum lengkap</span>' ?>
                                        <?php if($is_me): ?> <span class="badge bg-dark ms-1" style="font-size:8px;">YOU</span> <?php endif; ?>
                                    </div>
                                    <div class="text-muted small"><?= $row['Email_User'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="small fw-bold text-dark"><?= $row['No_Hp'] ?? '-' ?></span></td>
                        <td>
                            <span class="status-badge <?= $row['Status_User'] == 'Active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <?= strtoupper($row['Status_User']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if(!$is_me): ?>
                                <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                    <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-action btn-sm" title="Edit Profil">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </a>
                                    <button onclick="toggleStatus(<?= $row['ID_User'] ?>, '<?= $row['Status_User'] ?>')" class="btn btn-action btn-sm" title="Ubah Status">
                                        <i class="bi <?= $row['Status_User'] == 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-action btn-sm" title="Hapus Permanen">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-muted" style="font-size:10px">
                                    <i class="bi bi-person-gear me-1"></i>KELOLA PROFIL SAYA
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-light bg-opacity-50 border-top d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-arrow-left me-1"></i> DASHBOARD UTAMA</a>
            <span class="text-muted small fw-bold" style="font-size: 10px;">SpotLight Security Protocol: Active</span>
        </div>
    </div>
</div>

<script>
// VALIDASI & AKSI
function toggleStatus(id, current) {
    let action = current === 'Active' ? 'Menonaktifkan' : 'Aktifkan';
    Swal.fire({
        title: action + ' Karyawan?',
        text: "Karyawan ini tidak akan bisa login untuk sementara.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e8457a',
        confirmButtonText: 'Ya, Lakukan'
    }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_karyawan.php?type=soft&id=' + id + '&status=' + current; } })
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Karyawan Permanen?',
        text: "Data akun dan profil akan dihapus selamanya dari sistem!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Hapus'
    }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_karyawan.php?type=hard&id=' + id; } })
}
</script>

</body>
</html>