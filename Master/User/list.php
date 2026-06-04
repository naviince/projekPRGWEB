<?php
session_start();
include '../../koneksi.php';
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { header("Location: ../../login.php"); exit(); }

$sql = "SELECT * FROM Users ORDER BY ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Master User - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border: none; border-radius: 15px; }
        .badge-admin { background: #e8457a; }
        .badge-customer { background: #6c757d; }
        .badge-owner { background: #6610f2; }
        .badge-foto { background: #fd7e14; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Master User</h2>
                <p class="text-muted">Kelola autentikasi dan hak akses sistem</p>
            </div>
            <a href="add.php" class="btn btn-dark shadow-sm"><i class="bi bi-person-plus"></i> Tambah Akun</a>
        </div>

        <div class="card p-4 shadow-sm">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email / Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr class="align-middle">
                        <td><?= $row['ID_User'] ?></td>
                        <td class="fw-bold"><?= $row['Email_User'] ?></td>
                        <td>
                            <?php 
                                $role = $row['Role_User'];
                                $class = "badge-customer";
                                if($role == 'Admin') $class = "badge-admin";
                                if($role == 'Owner') $class = "badge-owner";
                                if($role == 'Fotografer') $class = "badge-foto";
                            ?>
                            <span class="badge <?= $class ?>"><?= $role ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $row['Status_User'] == 'Active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $row['Status_User'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-lock"></i> Edit</a>
                            <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="mt-3">
                <a href="../Admin/index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Akun?',
            text: "User tidak akan bisa login kembali!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => { if (result.isConfirmed) { window.location.href = 'delete.php?id=' + id; } })
    }
    </script>
</body>
</html>