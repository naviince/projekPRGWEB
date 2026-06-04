<?php
session_start();
include '../../koneksi.php';
if (!isset($_SESSION['status']) || $_SESSION['role'] != 'Admin') { header("Location: ../../login.php"); exit(); }

// Query Join untuk mengambil Nama dari Karyawan dan Email dari Users
$sql = "SELECT u.ID_User, u.Email_User, u.Role_User, u.Status_User, k.Nama_Karyawan 
        FROM Users u 
        LEFT JOIN Karyawan k ON u.ID_User = k.ID_User 
        WHERE u.Role_User = 'Admin'";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Master Admin - SpotLight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-pink { background: #e8457a; color: white; border-radius: 8px; }
        .btn-pink:hover { background: #c73165; color: white; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark">Master Admin</h2>
                <p class="text-muted">Kelola akun administrator sistem</p>
            </div>
            <a href="add.php" class="btn btn-pink shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Admin</a>
        </div>

        <div class="card p-4">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nama Lengkap</th>
                        <th>Email Admin</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr class="align-middle">
                        <td><?= $row['ID_User'] ?></td>
                        <td><strong><?= $row['Nama_Karyawan'] ?? '-' ?></strong></td>
                        <td><?= $row['Email_User'] ?></td>
                        <td><span class="badge bg-success"><?= $row['Status_User'] ?></span></td>
                        <td class="text-center">
                            <a href="edit.php?id=<?= $row['ID_User'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                            <button onclick="confirmDelete(<?= $row['ID_User'] ?>)" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="mt-3">
                <a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah anda yakin?',
            text: "Data admin dan profilnya akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e8457a',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete.php?id=' + id;
            }
        })
    }
    </script>
</body>
</html>