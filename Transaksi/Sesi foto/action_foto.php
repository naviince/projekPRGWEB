<?php
session_start();
include '../../koneksi.php';

$act = $_GET['act'];
$user = $_SESSION['username_karyawan'] ?? 'Admin';

if ($act == 'add') {
    $id_order = $_POST['id_order'];
    $id_kar = $_POST['id_karyawan'];
    $mulai = $_POST['waktu_mulai'];
    $selesai = $_POST['waktu_selesai'];

    $sql = "INSERT INTO Sesi_Foto (ID_Order, ID_Karyawan, Waktu_Mulai, Waktu_Selesai, Status_Sesi, Status, Created_By, Created_Date) 
            VALUES (?, ?, ?, ?, 0, 1, ?, GETDATE())";
    $params = array($id_order, $id_kar, $mulai, $selesai, $user);
    $stmt = sqlsrv_query($conn, $sql, $params);

    header("Location: index.php?msg=success_add");

} elseif ($act == 'edit') {
    $id_sesi = $_POST['id_sesi'];
    $status_sesi = $_POST['status_sesi'];
    
    // Logika Upload File
    $file_name = "";
    if ($_FILES['file_hasil']['name'] != "") {
        $ext = pathinfo($_FILES['file_hasil']['name'], PATHINFO_EXTENSION);
        $file_name = "Hasil_" . $id_sesi . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES['file_hasil']['tmp_name'], "../../../assets/img/hasil_foto/" . $file_name);
        
        $sql = "UPDATE Sesi_Foto SET Status_Sesi = ?, File_Hasil = ?, Tanggal_Upload_Hasil = GETDATE(), Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = ?";
        $params = array($status_sesi, $file_name, $user, $id_sesi);
    } else {
        $sql = "UPDATE Sesi_Foto SET Status_Sesi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = ?";
        $params = array($status_sesi, $user, $id_sesi);
    }

    sqlsrv_query($conn, $sql, $params);
    header("Location: index.php?msg=success_edit");

} elseif ($act == 'delete') {
    $id = $_GET['id'];
    // Soft Delete sesuai struktur (Status = 0)
    $sql = "UPDATE Sesi_Foto SET Status = 0 WHERE ID_Sesi_Foto = ?";
    sqlsrv_query($conn, $sql, array($id));
    header("Location: index.php?msg=success_delete");
}
?>