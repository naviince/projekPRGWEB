<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$act = $_GET['act'] ?? '';
$user = $_SESSION['username_karyawan'] ?? 'Admin';

// Helper: redirect dengan pesan
function redirectMsg($msg) {
    header("Location: index.php?msg=" . $msg);
    exit();
}

// =====================================================
// ADD SESI FOTO
// =====================================================
if ($act == 'add') {
    $id_order    = $_POST['id_order']      ?? '';
    $id_kar      = $_POST['id_karyawan']   ?? '';
    $mulai       = $_POST['waktu_mulai']   ?? '';
    $selesai     = $_POST['waktu_selesai'] ?? null;

    if (empty($id_order) || empty($id_kar) || empty($mulai)) {
        redirectMsg('error_validasi');
    }

    // Cek apakah order sudah punya sesi foto aktif
    $cek = sqlsrv_query($conn,
        "SELECT COUNT(*) as jml FROM Sesi_Foto WHERE ID_Order = ? AND Status = 1",
        array($id_order)
    );
    $row_cek = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    if ($row_cek['jml'] > 0) {
        redirectMsg('error_duplikat');
    }

    $params_selesai = !empty($selesai) ? $selesai : null;

    $sql = "INSERT INTO Sesi_Foto 
                (ID_Order, ID_Karyawan, Waktu_Mulai, Waktu_Selesai, Status_Sesi, File_Hasil, Status, Created_By, Created_Date) 
            VALUES (?, ?, ?, ?, 0, NULL, 1, ?, GETDATE())";
    $params = array($id_order, $id_kar, $mulai, $params_selesai, $user);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        redirectMsg('success_add');
    } else {
        redirectMsg('error_db');
    }
}

// =====================================================
// EDIT / UPLOAD HASIL SESI FOTO
// =====================================================
elseif ($act == 'edit') {
    $id_sesi     = $_POST['id_sesi']     ?? '';
    $status_sesi = $_POST['status_sesi'] ?? 0;

    if (empty($id_sesi)) {
        redirectMsg('error_validasi');
    }

    // Cek apakah ada file yang diupload
    $ada_file = isset($_FILES['file_hasil']) && $_FILES['file_hasil']['error'] === UPLOAD_ERR_OK && $_FILES['file_hasil']['name'] !== '';

    if ($ada_file) {
        $allowed_ext = ['zip', 'jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['file_hasil']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            redirectMsg('error_format');
        }

        $max_size = 50 * 1024 * 1024; // 50MB
        if ($_FILES['file_hasil']['size'] > $max_size) {
            redirectMsg('error_ukuran');
        }

        // Hapus file lama jika ada
        $q_old = sqlsrv_query($conn,
            "SELECT File_Hasil FROM Sesi_Foto WHERE ID_Sesi_Foto = ?",
            array($id_sesi)
        );
        $old_row = sqlsrv_fetch_array($q_old, SQLSRV_FETCH_ASSOC);
        if (!empty($old_row['File_Hasil'])) {
            $old_path = "../../../assets/img/hasil_foto/" . $old_row['File_Hasil'];
            if (file_exists($old_path)) {
                @unlink($old_path);
            }
        }

        $upload_dir = "../../../assets/img/hasil_foto/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = "Hasil_" . $id_sesi . "_" . time() . "." . $ext;
        $upload_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['file_hasil']['tmp_name'], $upload_path)) {
            redirectMsg('error_upload');
        }

        // Kalau file diupload dan status masih 0 atau 1, otomatis set selesai jika dipilih
        $sql = "UPDATE Sesi_Foto 
                SET Status_Sesi = ?, 
                    File_Hasil = ?, 
                    Tanggal_Upload_Hasil = GETDATE(), 
                    Modified_By = ?, 
                    Modified_Date = GETDATE() 
                WHERE ID_Sesi_Foto = ?";
        $params = array($status_sesi, $file_name, $user, $id_sesi);
    } else {
        // Tidak ada file baru, hanya update status
        $sql = "UPDATE Sesi_Foto 
                SET Status_Sesi = ?, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE() 
                WHERE ID_Sesi_Foto = ?";
        $params = array($status_sesi, $user, $id_sesi);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        redirectMsg('success_edit');
    } else {
        redirectMsg('error_db');
    }
}

// =====================================================
// DELETE (SOFT DELETE)
// =====================================================
elseif ($act == 'delete') {
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        redirectMsg('error_validasi');
    }

    $sql = "UPDATE Sesi_Foto SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = ?";
    $stmt = sqlsrv_query($conn, $sql, array($user, $id));

    if ($stmt) {
        redirectMsg('success_delete');
    } else {
        redirectMsg('error_db');
    }
}

else {
    header("Location: index.php");
    exit();
}
?>