<?php
session_start();
include '../../../koneksi.php';
$sql = "SELECT * FROM Users ORDER BY ID_User DESC";
$query = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Master User</title>
    <link href="../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Gunakan style yang sama dengan dashboard agar desain konsisten -->
</head>
<body class="p-4 bg-light">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Daftar Pengguna (Users)</h3>
        <a href="add.php" class="btn btn-primary" style="background:#c73165; border:none;">+ Tambah User</a>
    </div>

    <div class="card border-0 shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td><?php echo $row['Email_User']; ?></td>
                    <td><span class="badge bg-info text-dark"><?php echo $row['Role_User']; ?></span></td>
                    <td><?php echo $row['Status_User']; ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $row['ID_User']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="delete.php?id=<?php echo $row['ID_User']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus user ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <br>
    <a href="../index.php" class="btn btn-secondary btn-sm">Kembali ke Dashboard</a>
</body>
</html>