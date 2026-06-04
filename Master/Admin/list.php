<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Query khusus mengambil yang Role-nya Admin saja
$sql = "SELECT * FROM Users WHERE Role_User = 'Admin' ORDER BY ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Admin - SpotLight</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #fff0f5; font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-pink { background-color: #e8457a; color: white; border: none; }
        .btn-pink:hover { background-color: #c73165; color: white; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold">Master Admin</h3>
                <p class="text-muted">Kelola akun administrator sistem</p>
            </div>
            <a href="add.php" class="btn btn-pink px-4 py-2">
                <i class="bi bi-plus-lg"></i> Tambah Admin
            </a>
        </div>

        <div class="card p-3">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Email Admin</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo $row['ID_User']; ?></td>
                        <td><?php echo $row['Email_User']; ?></td>
                        <td><span class="badge bg-primary"><?php echo $row['Role_User']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $row['Status_User']; ?></span></td>
                        <td class="text-center">
                            <a href="edit.php?id=<?php echo $row['ID_User']; ?>" class="btn btn-sm btn-outline-warning shadow-sm"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?php echo $row['ID_User']; ?>" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('Hapus admin ini?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <a href="index.php" class="btn btn-link text-muted mt-3 text-decoration-none">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</body>
</html>