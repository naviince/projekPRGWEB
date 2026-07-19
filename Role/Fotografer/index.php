<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Fotografer') {
    header("Location: ../../login.php");
    exit();
}

$id_fotografer = $_SESSION['id_user'];
sqlsrv_query($conn, "{CALL sp_AutoExpireSesiFoto (?)", ['system']);

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_fotografer));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_fotografer = $d_profile['nama_karyawan'] ?? 'Fotografer';
$username_fotografer = $d_profile['username_karyawan'] ?? 'fotografer';
$email_fotografer = $d_profile['email_karyawan'] ?? 'fotografer@spotlight.com';
$foto_fotografer = $d_profile['foto_profil'] ?? 'default.jpg';

$foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_fotografer)) 
    ? "../../assets/img/karyawan/" . $foto_fotografer : $default_svg_avatar;

// FILTER TAHUN UNTUK CHART (SAMAIN ADMIN)
$tahun_sekarang = (int) date('Y');
$tahun_filter = (isset($_GET['tahun']) && ctype_digit($_GET['tahun'])) ? (int) $_GET['tahun'] : $tahun_sekarang;
$tahun_options = range($tahun_sekarang, $tahun_sekarang - 4);

$error_profile = "";
$success_profile = false;

if (isset($_POST['update_profil'])) {
    $nama_input = trim($_POST['nama']);
    $username_input = trim($_POST['username']);
    $email_input = trim($_POST['email']);
    $no_hp_input = str_replace(' ', '', trim($_POST['no_hp']));
    $alamat_input = trim($_POST['alamat']);
    $pass_baru = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $hp_bersih_input = str_replace('+', '', $no_hp_input);

    if (empty($nama_input) || !preg_match("/^[a-zA-Z ]*$/", $nama_input)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf!";
    } elseif (empty($username_input) || !preg_match("/^[a-zA-Z0-9_]*$/", $username_input)) {
        $error_profile = "Nama pengguna tidak valid!";
    } elseif (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error_profile = "Email tidak valid!";
    } elseif (empty($no_hp_input) || !ctype_digit($hp_bersih_input) || strlen($hp_bersih_input) < 10) {
        $error_profile = "Nomor telepon tidak valid!";
    } elseif (empty($alamat_input) || strlen($alamat_input) < 10) {
        $error_profile = "Alamat lengkap minimal harus 10 karakter!";
    } else {
        $sandi_final = $d_profile['password_karyawan']; 
        if (!empty($pass_baru)) {
            if (strlen($pass_baru) < 8 || !preg_match("/[A-Za-z]/", $pass_baru) || !preg_match("/[0-9]/", $pass_baru) || !preg_match("/[^A-Za-z0-9]/", $pass_baru)) {
                $error_profile = "Sandi baru minimal 8 karakter (kombinasi huruf, angka, simbol)!";
            } elseif ($pass_baru !== $confirm_pass) {
                $error_profile = "Konfirmasi kata sandi tidak cocok!";
            } else {
                $sandi_final = $pass_baru; 
            }
        }
        if ($error_profile == "") {
            $sql_cek = "SELECT Email_Karyawan, Username_Karyawan, No_Hp FROM Karyawan WHERE (Email_Karyawan = ? OR Username_Karyawan = ? OR No_Hp = ?) AND ID_Karyawan != ?";
            $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_input, $username_input, $no_hp_input, $id_fotografer));
            if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
                while ($row_cek = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC)) {
                    $row_cek = array_change_key_case($row_cek, CASE_LOWER);
                    if (strtolower($row_cek['email_karyawan']) == strtolower($email_input)) { $error_profile = "Email sudah digunakan!"; } 
                    if (strtolower($row_cek['username_karyawan']) == strtolower($username_input)) { $error_profile = "Username sudah digunakan!"; }
                    if ($row_cek['no_hp'] == $no_hp_input) { $error_profile = "Nomor telepon sudah digunakan!"; }
                }
            }
        }
        if ($error_profile == "") {
            $foto_baru = $foto_fotografer;
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['foto_profil']['name'];
                $file_size = $_FILES['foto_profil']['size'];
                $file_tmp = $_FILES['foto_profil']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                if (!in_array($file_ext, $allowed_ext)) {
                    $error_profile = "Format foto profil harus JPG, JPEG, atau PNG!";
                } elseif ($file_size > 2097152) { 
                    $error_profile = "Ukuran foto profil maksimal 2MB!";
                } else {
                    $foto_baru = "fotografer_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/karyawan/";
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    if (move_uploaded_file($file_tmp, $target_dir . $foto_baru)) {
                        if ($foto_fotografer != 'default.jpg' && file_exists($target_dir . $foto_fotografer)) { unlink($target_dir . $foto_fotografer); }
                    } else {
                        $error_profile = "Gagal mengunggah foto profil!";
                    }
                }
            }
            if ($error_profile == "") {
                $sql_upd = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $stmt_upd = sqlsrv_query($conn, $sql_upd, array($nama_input, $username_input, $email_input, $sandi_final, $no_hp_input, $alamat_input, $foto_baru, $username_fotografer, $id_fotografer));
                if ($stmt_upd) {
                    $success_profile = true;
                    $nama_fotografer = $nama_input;
                    $username_fotografer = $username_input;
                    $email_fotografer = $email_input;
                    $foto_fotografer = $foto_baru;
                    $foto_fotografer_src = ($foto_fotografer != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_fotografer)) ? "../../assets/img/karyawan/" . $foto_fotografer : $default_svg_avatar;
                    $d_profile['no_hp'] = $no_hp_input;
                    $d_profile['alamat'] = $alamat_input;
                } else {
                    $error_profile = "Gagal memperbarui data di database!";
                }
            }
        }
    }
}

// STATISTIK
$q_total_sesi = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1", array($id_fotografer));
$d_total_sesi = sqlsrv_fetch_array($q_total_sesi, SQLSRV_FETCH_ASSOC);
$total_sesi = $d_total_sesi['total'] ?? 0;

$q_sesi_terjadwal = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 0", array($id_fotografer));
$d_sesi_terjadwal = sqlsrv_fetch_array($q_sesi_terjadwal, SQLSRV_FETCH_ASSOC);
$sesi_terjadwal = $d_sesi_terjadwal['total'] ?? 0;

$q_sesi_selesai = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 1", array($id_fotografer));
$d_sesi_selesai = sqlsrv_fetch_array($q_sesi_selesai, SQLSRV_FETCH_ASSOC);
$sesi_selesai = $d_sesi_selesai['total'] ?? 0;

$q_sesi_batal = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 2", array($id_fotografer));
$d_sesi_batal = sqlsrv_fetch_array($q_sesi_batal, SQLSRV_FETCH_ASSOC);
$sesi_batal = $d_sesi_batal['total'] ?? 0;

$q_sesi_hari_ini = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto S CROSS APPLY (SELECT TOP 1 J.Tanggal_Jadwal FROM Order_Jadwal OJ JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal WHERE OJ.ID_Order = S.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0 ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC) Slot WHERE S.ID_Karyawan = ? AND S.Status = 1 AND S.Status_Sesi = 0 AND Slot.Tanggal_Jadwal = CAST(GETDATE() AS DATE)", array($id_fotografer));
$d_sesi_hari_ini = sqlsrv_fetch_array($q_sesi_hari_ini, SQLSRV_FETCH_ASSOC);
$sesi_hari_ini = $d_sesi_hari_ini['total'] ?? 0;

$q_sesi_minggu_ini = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto S CROSS APPLY (SELECT TOP 1 J.Tanggal_Jadwal FROM Order_Jadwal OJ JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal WHERE OJ.ID_Order = S.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0 ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC) Slot WHERE S.ID_Karyawan = ? AND S.Status = 1 AND S.Status_Sesi = 0 AND Slot.Tanggal_Jadwal BETWEEN CAST(GETDATE() AS DATE) AND DATEADD(DAY, 7, CAST(GETDATE() AS DATE))", array($id_fotografer));
$d_sesi_minggu_ini = sqlsrv_fetch_array($q_sesi_minggu_ini, SQLSRV_FETCH_ASSOC);
$sesi_minggu_ini = $d_sesi_minggu_ini['total'] ?? 0;

$q_belum_upload = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 1 AND File_Hasil IS NULL", array($id_fotografer));
$d_belum_upload = sqlsrv_fetch_array($q_belum_upload, SQLSRV_FETCH_ASSOC);
$belum_upload = $d_belum_upload['total'] ?? 0;

$q_sudah_upload = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE ID_Karyawan = ? AND Status = 1 AND Status_Sesi = 1 AND File_Hasil IS NOT NULL", array($id_fotografer));
$d_sudah_upload = sqlsrv_fetch_array($q_sudah_upload, SQLSRV_FETCH_ASSOC);
$sudah_upload = $d_sudah_upload['total'] ?? 0;

$q_jadwal_hari_ini = sqlsrv_query($conn, "SELECT S.ID_Sesi_Foto, O.ID_Order, P.Nama_Pelanggan, PK.Nama_Paket, R.Nama_Ruangan, Slot.Tanggal_Jadwal, Slot.Jam_Mulai, Slot.Jam_Selesai, O.Keterangan, S.Status_Sesi FROM Sesi_Foto S JOIN [Order] O ON S.ID_Order = O.ID_Order JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan CROSS APPLY (SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai FROM Order_Jadwal OJ JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0 ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC) Slot WHERE S.ID_Karyawan = ? AND S.Status = 1 AND O.Status = 1 AND Slot.Tanggal_Jadwal = CAST(GETDATE() AS DATE) ORDER BY Slot.Jam_Mulai ASC", array($id_fotografer));

// FILTER TABEL
$jm_cari = isset($_GET['jm_cari']) ? trim($_GET['jm_cari']) : "";
$jm_dari = isset($_GET['jm_dari']) ? trim($_GET['jm_dari']) : "";
$jm_sampai = isset($_GET['jm_sampai']) ? trim($_GET['jm_sampai']) : "";
$rs_cari = isset($_GET['rs_cari']) ? trim($_GET['rs_cari']) : "";
$rs_dari = isset($_GET['rs_dari']) ? trim($_GET['rs_dari']) : "";
$rs_sampai = isset($_GET['rs_sampai']) ? trim($_GET['rs_sampai']) : "";

$jm_conditions = ["S.ID_Karyawan = ?", "S.Status_Sesi = 0"];
$jm_params = [$id_fotografer];
if (!empty($jm_cari)) { $jm_conditions[] = "P.Nama_Pelanggan LIKE ?"; $jm_params[] = "%$jm_cari%"; }
if (!empty($jm_dari) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $jm_dari)) { $jm_conditions[] = "CAST(Slot.Tanggal_Jadwal AS DATE) >= ?"; $jm_params[] = $jm_dari; }
if (!empty($jm_sampai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $jm_sampai)) { $jm_conditions[] = "CAST(Slot.Tanggal_Jadwal AS DATE) <= ?"; $jm_params[] = $jm_sampai; }
$jm_where = implode(" AND ", $jm_conditions);
$jm_top = (!empty($jm_cari) || !empty($jm_dari) || !empty($jm_sampai)) ? 20 : 5;

$q_jadwal_mendatang = sqlsrv_query($conn, "SELECT TOP $jm_top S.ID_Sesi_Foto, P.Nama_Pelanggan, PK.Nama_Paket, R.Nama_Ruangan, Slot.Tanggal_Jadwal, Slot.Jam_Mulai, Slot.Jam_Selesai, S.Status_Sesi FROM Sesi_Foto S JOIN [Order] O ON S.ID_Order = O.ID_Order JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan CROSS APPLY (SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai FROM Order_Jadwal OJ JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0 ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC) Slot WHERE $jm_where AND S.Status = 1 AND O.Status = 1 AND Slot.Tanggal_Jadwal >= CAST(GETDATE() AS DATE) ORDER BY Slot.Tanggal_Jadwal ASC, Slot.Jam_Mulai ASC", $jm_params);

$rs_conditions = ["S.ID_Karyawan = ?", "S.Status_Sesi = 1"];
$rs_params = [$id_fotografer];
if (!empty($rs_cari)) { $rs_conditions[] = "P.Nama_Pelanggan LIKE ?"; $rs_params[] = "%$rs_cari%"; }
if (!empty($rs_dari) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rs_dari)) { $rs_conditions[] = "CAST(S.Waktu_Selesai AS DATE) >= ?"; $rs_params[] = $rs_dari; }
if (!empty($rs_sampai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rs_sampai)) { $rs_conditions[] = "CAST(S.Waktu_Selesai AS DATE) <= ?"; $rs_params[] = $rs_sampai; }
$rs_where = implode(" AND ", $rs_conditions);
$rs_top = (!empty($rs_cari) || !empty($rs_dari) || !empty($rs_sampai)) ? 20 : 5;

$q_riwayat_selesai = sqlsrv_query($conn, "SELECT TOP $rs_top S.ID_Sesi_Foto, P.Nama_Pelanggan, PK.Nama_Paket, S.Waktu_Mulai, S.Waktu_Selesai, S.File_Hasil, S.Tanggal_Upload_Hasil FROM Sesi_Foto S JOIN [Order] O ON S.ID_Order = O.ID_Order JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket WHERE $rs_where AND S.Status = 1 AND O.Status = 1 ORDER BY S.Waktu_Selesai DESC", $rs_params);

// CHART STATISTIK SESI PER BULAN (FILTER TAHUN)
$q_sesi_bulan = sqlsrv_query($conn, "SELECT MONTH(S.Waktu_Selesai) AS bulan, COUNT(*) AS total FROM Sesi_Foto S WHERE S.ID_Karyawan = ? AND S.Status = 1 AND S.Status_Sesi = 1 AND YEAR(S.Waktu_Selesai) = ? GROUP BY MONTH(S.Waktu_Selesai) ORDER BY MONTH(S.Waktu_Selesai)", array($id_fotografer, $tahun_filter));
$sesi_bulan_data = array_fill(0, 12, 0);
if ($q_sesi_bulan !== false) {
    while ($row = sqlsrv_fetch_array($q_sesi_bulan, SQLSRV_FETCH_ASSOC)) {
        $sesi_bulan_data[$row['bulan'] - 1] = $row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel Fotografer – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9;
            --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --sidebar-width: 260px; --sidebar-collapsed: -260px; --header-height: 70px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg);
            color: var(--text-dark); overflow-x: hidden; margin: 0; padding: 0;
            -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
        }
        .sidebar {
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg);
            position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 24px 16px; z-index: 1040;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            overflow-y: auto; scrollbar-width: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar-brand { font-weight: 800; font-size: 1.4rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 32px; display: block; padding: 0 4px; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.8rem; font-weight: 600; display: block; margin-top: 2px; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 16px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 6px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; color: #4a5568; font-weight: 700; text-decoration: none;
            border-radius: 12px; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .nav-link-custom i.me-2 { flex-shrink: 0; }
        .submenu { list-style: none; padding-left: 16px; margin-top: 4px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 7px 14px; color: #718096; font-weight: 600; font-size: 0.82rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,0.03); padding-left: 18px; }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff;
            border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800;
            font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }

        .mobile-header {
            display: none; position: fixed; top: 0; left: 0; right: 0;
            height: var(--header-height); background: var(--sidebar-bg);
            border-bottom: 1px solid rgba(255,228,233,0.8); z-index: 1030;
            padding: 0 16px; align-items: center; justify-content: space-between;
        }
        .mobile-brand { font-weight: 800; font-size: 1.2rem; color: var(--p-pink); text-decoration: none; }
        .mobile-brand span { font-size: 0.7rem; color: var(--text-dark); display: block; font-weight: 600; }
        .btn-toggle-sidebar {
            width: 42px; height: 42px; border-radius: 12px; border: none;
            background: var(--s-pink); color: var(--p-pink); font-size: 1.3rem;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            transition: var(--transition-3d); flex-shrink: 0;
        }
        .btn-toggle-sidebar:hover { background: var(--p-pink); color: #fff; }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1035;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        .main-content { margin-left: var(--sidebar-width); padding: 32px 28px; min-height: 100vh; transition: margin-left 0.35s ease; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
        .dashboard-header h3 { font-size: 1.5rem; margin-bottom: 4px; }
        .dashboard-header p { font-size: 0.85rem; }

        /* BADGE NAMA - SAMA PERSIS ADMIN */
        .fotografer-name-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; font-weight: 700; font-size: 0.78rem;
            padding: 6px 14px; border-radius: 50px;
            box-shadow: 0 4px 12px rgba(213,61,102,0.2); white-space: nowrap;
        }
        .fotografer-role-tag { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
        .fotografer-id-chip { text-align: right; line-height: 1.4; }

        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d);
            background: #ffffff; flex-shrink: 0;
        }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,0.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* TOOLBAR - SAMA PERSIS ADMIN */
        .dashboard-toolbar {
            background: #ffffff; border: 1px solid rgba(255,228,233,0.8);
            border-radius: 16px; padding: 12px 18px;
            box-shadow: 0 4px 14px rgba(213,61,102,0.04); margin-bottom: 20px;
        }
        .live-clock-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--light-pink); color: var(--text-dark);
            font-weight: 700; font-size: 0.8rem; padding: 8px 14px; border-radius: 10px;
        }
        .live-clock-chip i { color: var(--p-pink); }
        .year-filter-form { margin: 0; }
        .year-filter-label {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.78rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.5px; margin: 0;
        }
        .year-filter-label i { color: var(--p-pink); }
        .year-filter-select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23D53D66'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E") no-repeat right 12px center;
            background-size: 14px; border: 1.5px solid rgba(213,61,102,0.25);
            color: var(--p-pink); font-weight: 800; font-size: 0.85rem;
            padding: 8px 34px 8px 16px; border-radius: 10px; cursor: pointer;
            transition: var(--transition-3d);
        }
        .year-filter-select:hover, .year-filter-select:focus { border-color: var(--p-pink); box-shadow: 0 6px 15px rgba(213,61,102,0.15); outline: none; }

        /* STATS */
        .stats-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 12px; margin-bottom: 16px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; -webkit-overflow-scrolling: touch; }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 14px; min-width: max-content; }
        .stat-card-item { min-width: 200px; max-width: 260px; flex: 0 0 auto; }
        .card-3d {
            background: #ffffff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8);
            box-shadow: 0 6px 20px rgba(213,61,102,0.03); padding: 18px; height: 100%;
            position: relative; overflow: hidden;
        }
        .card-3d-clickable { transition: var(--transition-3d); }
        .card-3d-clickable::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0; transition: opacity 0.3s ease;
        }
        .card-3d-clickable:hover { transform: translateY(-6px) scale(1.01); border-color: var(--p-pink); }
        .card-3d-clickable:hover::before { opacity: 1; }
        .stat-card { display: flex; align-items: center; gap: 12px; }
        .stat-icon {
            width: 44px; height: 44px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.2rem;
            transition: var(--transition-3d); flex-shrink: 0;
        }
        .card-3d-clickable:hover .stat-icon { transform: scale(1.1) rotate(5deg); }
        .stat-icon-purple { background: linear-gradient(135deg,#FFF0F3,#FFE4E9); color: #D53D66; }
        .stat-icon-blue { background: linear-gradient(135deg,#eff6ff,#dbeafe); color: #2563eb; }
        .stat-icon-green { background: linear-gradient(135deg,#ecfdf5,#d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg,#fffbeb,#fef3c7); color: #d97706; }
        .stat-icon-red { background: linear-gradient(135deg,#fef2f2,#fee2e2); color: #dc2626; }
        .stat-icon-pink { background: linear-gradient(135deg,#fdf2f8,#fce7f3); color: #db2777; }
        .stat-icon-cyan { background: linear-gradient(135deg,#ecfeff,#cffafe); color: #0891b2; }
        .stat-icon-yellow { background: linear-gradient(135deg,#fefce8,#fef9c3); color: #ca8a04; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.35rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stat-title { font-size: 0.68rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stat-subtitle { font-size: 0.65rem; color: #a0aec0; font-weight: 600; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* CONTENT CARDS */
        .content-card { background: #ffffff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 6px 20px rgba(213,61,102,0.03); transition: var(--transition-3d); padding: 22px; height: 100%; }
        .content-card:hover { transform: translateY(-4px); }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 8px; }
        .content-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
        .content-badge { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; padding: 5px 12px; border-radius: 8px; font-size: 0.72rem; font-weight: 700; }

        /* FILTER FORM */
        .filter-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 0 2px; }
        .filter-input { flex: 1; min-width: 140px; padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.8rem; font-weight: 600; color: var(--text-dark); background: #fff; transition: var(--transition-3d); }
        .filter-input:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 6px 15px rgba(213,61,102,0.15); }
        .filter-date { padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.78rem; font-weight: 600; color: var(--text-dark); background: #fff; transition: var(--transition-3d); }
        .filter-date:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 6px 15px rgba(213,61,102,0.15); }
        .btn-filter { background: var(--p-pink); color: #fff; border: none; border-radius: 10px; padding: 8px 14px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 4px; }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.25); }
        .btn-reset { background: #f1f5f9; color: #64748b; border: none; border-radius: 10px; padding: 8px 12px; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; transition: var(--transition-3d); }
        .btn-reset:hover { background: #e2e8f0; color: var(--text-dark); }

        /* SESI ITEM */
        .sesi-item { display: flex; align-items: center; gap: 14px; padding: 14px; background: linear-gradient(135deg,#ffffff,#FFF0F3); border-radius: 16px; margin-bottom: 10px; transition: var(--transition-3d); border: 2px solid transparent; }
        .sesi-item:hover { transform: translateX(4px); border-color: var(--p-pink); }
        .sesi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .sesi-time { font-size: 0.82rem; font-weight: 700; color: var(--p-pink); }
        .sesi-title { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .sesi-info { font-size: 0.78rem; color: var(--text-muted); }

        /* TIMELINE */
        .timeline { position: relative; padding-left: 26px; }
        .timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg,var(--p-pink),var(--accent-pink)); border-radius: 2px; }
        .timeline-item { position: relative; padding-bottom: 18px; }
        .timeline-item::before { content: ''; position: absolute; left: -22px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--p-pink); border: 2px solid #ffffff; box-shadow: 0 0 0 2px var(--p-pink); }
        .timeline-item.completed::before { background: #059669; box-shadow: 0 0 0 2px #059669; }
        .timeline-item.cancelled::before { background: #dc2626; box-shadow: 0 0 0 2px #dc2626; }

        /* BADGE STATUS */
        .badge-status { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .badge-terjadwal { background: #fffbeb; color: #d97706; }
        .badge-selesai { background: #ecfdf5; color: #059669; }
        .badge-batal { background: #fef2f2; color: #dc2626; }
        .badge-upload { background: #dbeafe; color: #2563eb; }

        /* BUTTONS */
        .btn-action { background: linear-gradient(135deg,var(--p-pink),var(--d-pink)); color: #ffffff; border: none; border-radius: 10px; padding: 6px 14px; font-weight: 700; font-size: 0.78rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.25); color: #ffffff; }
        .btn-action-success { background: linear-gradient(135deg,#059669,#047857); }
        .btn-action-success:hover { box-shadow: 0 6px 15px rgba(5,150,105,0.25); }
        .btn-detail { background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none; padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px; transition: var(--transition-3d); border: none; white-space: nowrap; }
        .btn-detail:hover { background: var(--p-pink); color: #fff; transform: translateY(-2px); }

        /* MODAL */
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; background: #f8fafc; font-size: 14px; font-weight: 600; transition: var(--transition-3d); color: var(--text-dark); }
        .form-control:focus, .form-select:focus { border-color: var(--p-pink); background: #ffffff; transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213,61,102,0.15); outline: none; }
        .profile-preview-box { width: 90px; height: 90px; border-radius: 50%; overflow: hidden; border: 2.5px solid #eef2f6; background: #f8fafc; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition: var(--transition-3d); }
        .profile-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .btn-pilih-foto { background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink); font-weight: 700; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-pilih-foto:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.15); }
        .btn-reg { background: linear-gradient(135deg,var(--p-pink),var(--d-pink)); color: white; border-radius: 16px; padding: 16px; font-weight: 800; border: none; width: 100%; transition: var(--transition-3d); margin-top: 15px; font-size: 15px; box-shadow: 0 10px 25px rgba(213,61,102,0.25); }
        .btn-reg:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 15px 35px rgba(213,61,102,0.35); }
        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px; }
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213,61,102,0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none !important; box-shadow: none !important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        @media (min-width: 1400px) { .main-content { padding: 40px 36px; } .stat-card-item { min-width: 220px; } }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(var(--sidebar-collapsed)); box-shadow: 4px 0 20px rgba(0,0,0,0.08); }
            .sidebar.open { transform: translateX(0); }
            .mobile-header { display: flex; }
            .main-content { margin-left: 0; padding: 90px 16px 24px; }
            .dashboard-header h3 { font-size: 1.2rem; }
            .dashboard-header p { font-size: 0.8rem; }
            .stat-card-item { min-width: 180px; }
            .card-3d { padding: 16px; }
            .content-card { padding: 18px; }
        }
        @media (max-width: 767.98px) {
            .main-content { padding: 85px 12px 20px; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .dashboard-header > div:last-child { width: 100%; justify-content: space-between; }
            .stat-card-item { min-width: 160px; }
            .stat-val { font-size: 1.15rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .filter-input { min-width: 100%; }
            .filter-toolbar { flex-direction: column; }
            .filter-date { width: 100%; }
            .sesi-item { padding: 12px; gap: 10px; }
            .sesi-icon { width: 38px; height: 38px; font-size: 1rem; border-radius: 10px; }
            .sesi-title { font-size: 0.85rem; }
            .sesi-time { font-size: 0.78rem; }
            .sesi-info { font-size: 0.72rem; }
            .content-title { font-size: 0.9rem; }
            .btn-action, .btn-detail { padding: 5px 10px; font-size: 0.72rem; }
        }
        @media (max-width: 575.98px) {
            .main-content { padding: 80px 10px 16px; }
            .mobile-header { height: 64px; padding: 0 12px; }
            .mobile-brand { font-size: 1.1rem; }
            .btn-toggle-sidebar { width: 38px; height: 38px; font-size: 1.1rem; }
            .dashboard-header h3 { font-size: 1.1rem; }
            .stat-card-item { min-width: 150px; }
            .card-3d { padding: 14px; border-radius: 16px; }
            .stat-card { gap: 10px; }
            .stat-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
            .stat-val { font-size: 1.05rem; }
            .stat-title { font-size: 0.6rem; }
            .stat-subtitle { font-size: 0.58rem; }
            .content-card { padding: 16px; border-radius: 16px; }
            .content-header { margin-bottom: 14px; }
            .dashboard-toolbar { padding: 10px 14px; border-radius: 14px; }
            .live-clock-chip { font-size: 0.72rem; padding: 7px 12px; }
            .year-filter-label { font-size: 0.7rem; }
            .year-filter-select { font-size: 0.78rem; padding: 7px 30px 7px 12px; }
            .timeline { padding-left: 22px; }
            .timeline::before { left: 6px; }
            .timeline-item::before { left: -20px; width: 8px; height: 8px; }
            .modal-dialog { margin: 12px; }
            .modal-content { border-radius: 20px !important; }
            .profile-preview-box { width: 80px; height: 80px; }
            .form-control, .form-select { padding: 10px 14px; font-size: 16px; border-radius: 12px; }
            .btn-reg { padding: 14px; font-size: 14px; }
            .form-label { font-size: 10px; }
            .quick-access-col { flex: 0 0 50%; max-width: 50%; }
        }
        @media (max-width: 359.98px) { .stat-card-item { min-width: 140px; } .quick-access-col { flex: 0 0 100%; max-width: 100%; } }
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar { padding: 16px 12px; }
            .sidebar-brand { font-size: 1.1rem; margin-bottom: 16px; }
            .nav-link-custom { padding: 8px 12px; font-size: 0.8rem; }
            .mobile-header { height: 52px; }
            .main-content { padding-top: 68px; }
        }
    </style>
</head>
<body>

    <!-- MOBILE HEADER -->
    <div class="mobile-header">
        <button class="btn-toggle-sidebar" onclick="toggleSidebar()" aria-label="Toggle Menu"><i class="bi bi-list"></i></button>
        <a href="../../index.php" class="mobile-brand">SpotLight.<span>Panel Fotografer</span></a>
        <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
            <img src="<?= $foto_fotografer_src ?>" alt="Fotografer Profil">
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<span>Panel Fotografer</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="index.php" class="nav-link-custom active"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuJadwal">
                        <span><i class="bi bi-calendar-week-fill me-2"></i> Jadwal & Sesi</span><i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuJadwal">
                        <ul class="list-unstyled">
                            <li><a href="../../Sesi/Jadwal/index.php" class="submenu-link"><i class="bi bi-calendar-day-fill me-2"></i>Jadwal Saya</a></li>
                            <li><a href="../../Sesi/Terjadwal/index.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Sesi Terjadwal</a></li>
                            <li><a href="../../Sesi/Selesai/index.php" class="submenu-link"><i class="bi bi-check-circle-fill me-2"></i>Sesi Selesai</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuUpload">
                        <span><i class="bi bi-cloud-upload-fill me-2"></i> Upload Hasil</span><i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuUpload">
                        <ul class="list-unstyled">
                            <li><a href="../../Sesi/Upload/index.php" class="submenu-link"><i class="bi bi-image-fill me-2"></i>Upload Foto</a></li>
                            <li><a href="../../Sesi/RiwayatUpload/index.php" class="submenu-link"><i class="bi bi-clock-history me-2"></i>Riwayat Upload</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
            </ul>
        </div>
        <div>
            <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- BANNER -->
        <div class="alert border-0 d-flex align-items-center gap-3 mb-4 shadow-sm animate-fade-in" style="border-radius: 16px; background: linear-gradient(135deg, rgba(213,61,102,0.05), rgba(255,228,233,0.15)); border: 1px solid rgba(213,61,102,0.15) !important; padding: 15px 20px;">
            <div class="stat-icon" style="width: 40px; height: 40px; background: var(--p-pink); color: #ffffff; font-size: 1.1rem; border-radius: 10px; flex-shrink: 0;"><i class="bi bi-camera-fill"></i></div>
            <div style="min-width: 0;">
                <h6 class="fw-bold mb-0" style="font-size: 0.85rem; color: var(--p-pink);">Panel Fotografer SpotLight ✦</h6>
                <small class="text-muted" style="font-size: 0.78rem; font-weight: 600;">Kelola jadwal sesi foto dan upload hasil pemotretan.</small>
            </div>
        </div>

        <!-- HEADER DENGAN BADGE NAMA (SAMAIN ADMIN) -->
        <div class="dashboard-header animate-fade-in delay-1">
            <div style="min-width: 0;">
                <h3 class="fw-bold mb-1">Halo, <?= htmlspecialchars($nama_fotografer) ?>! 📷</h3>
                <p class="text-muted small mb-0">Pantau jadwal sesi foto dan kelola hasil pemotretan.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="fotografer-id-chip d-none d-sm-block">
                    <span class="fotografer-name-badge"><i class="bi bi-camera-fill"></i> <?= htmlspecialchars($nama_fotografer) ?></span><br>
                    <span class="fotografer-role-tag">Fotografer</span>
                </div>
                <div class="profile-header-btn shadow-sm d-none d-lg-block" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_fotografer_src ?>" alt="Fotografer Profil">
                </div>
            </div>
        </div>

        <!-- TOOLBAR: LIVE CLOCK + FILTER TAHUN (SAMAIN ADMIN) -->
        <div class="dashboard-toolbar d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 animate-fade-in delay-1">
            <span class="live-clock-chip">
                <i class="bi bi-clock-history"></i> <span id="live-clock">Memuat waktu...</span>
            </span>
            <form method="GET" class="year-filter-form d-flex align-items-center gap-2 flex-wrap">
                <input type="hidden" name="jm_cari" value="<?= htmlspecialchars($jm_cari) ?>">
                <input type="hidden" name="jm_dari" value="<?= htmlspecialchars($jm_dari) ?>">
                <input type="hidden" name="jm_sampai" value="<?= htmlspecialchars($jm_sampai) ?>">
                <input type="hidden" name="rs_cari" value="<?= htmlspecialchars($rs_cari) ?>">
                <input type="hidden" name="rs_dari" value="<?= htmlspecialchars($rs_dari) ?>">
                <input type="hidden" name="rs_sampai" value="<?= htmlspecialchars($rs_sampai) ?>">
                <label for="filterTahun" class="year-filter-label"><i class="bi bi-funnel-fill"></i> Filter Laporan Tahun</label>
                <select name="tahun" id="filterTahun" class="year-filter-select" onchange="this.form.submit()">
                    <?php foreach ($tahun_options as $th): ?>
                    <option value="<?= $th ?>" <?= $th == $tahun_filter ? 'selected' : '' ?>><?= $th ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in delay-2">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-purple"><i class="bi bi-camera-fill"></i></div><div class="stat-content"><div class="stat-title">Total Sesi</div><div class="stat-val"><?= $total_sesi ?>x</div><div class="stat-subtitle">Semua pemotretan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-calendar-check-fill"></i></div><div class="stat-content"><div class="stat-title">Sesi Hari Ini</div><div class="stat-val"><?= $sesi_hari_ini ?> Sesi</div><div class="stat-subtitle">Jadwal terdekat</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-blue"><i class="bi bi-calendar-week-fill"></i></div><div class="stat-content"><div class="stat-title">Minggu Ini</div><div class="stat-val"><?= $sesi_minggu_ini ?> Sesi</div><div class="stat-subtitle">7 hari ke depan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-yellow"><i class="bi bi-hourglass-split"></i></div><div class="stat-content"><div class="stat-title">Terjadwal</div><div class="stat-val"><?= $sesi_terjadwal ?> Sesi</div><div class="stat-subtitle">Belum diproses</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Selesai</div><div class="stat-val"><?= $sesi_selesai ?> Sesi</div><div class="stat-subtitle">Sudah dipotret</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-cloud-arrow-up-fill"></i></div><div class="stat-content"><div class="stat-title">Belum Upload</div><div class="stat-val"><?= $belum_upload ?> Hasil</div><div class="stat-subtitle">Perlu diupload</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-cyan"><i class="bi bi-cloud-check-fill"></i></div><div class="stat-content"><div class="stat-title">Sudah Upload</div><div class="stat-val"><?= $sudah_upload ?> Hasil</div><div class="stat-subtitle">Foto terkirim</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-x-octagon-fill"></i></div><div class="stat-content"><div class="stat-title">Dibatalkan</div><div class="stat-val"><?= $sesi_batal ?> Sesi</div><div class="stat-subtitle">Tidak jadi</div></div></div></div></div>
            </div>
        </div>

        <!-- BARIS 2: JADWAL HARI INI & STATISTIK -->
        <div class="row g-3 mb-4">
            <div class="col-lg-7 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-calendar-day-fill text-danger me-2"></i>Jadwal Sesi Hari Ini</h5>
                        <span class="content-badge"><?= date('d M Y') ?></span>
                    </div>
                    <?php if ($q_jadwal_hari_ini && sqlsrv_has_rows($q_jadwal_hari_ini)): while ($row = sqlsrv_fetch_array($q_jadwal_hari_ini, SQLSRV_FETCH_ASSOC)):
                        $status_class = $row['Status_Sesi'] == 0 ? 'badge-terjadwal' : ($row['Status_Sesi'] == 1 ? 'badge-selesai' : 'badge-batal');
                        $status_text = $row['Status_Sesi'] == 0 ? 'Terjadwal' : ($row['Status_Sesi'] == 1 ? 'Selesai' : 'Dibatalkan');
                        $icon_bg = $row['Status_Sesi'] == 0 ? 'background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706;' : ($row['Status_Sesi'] == 1 ? 'background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;' : 'background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626;');
                    ?>
                        <div class="sesi-item">
                            <div class="sesi-icon" style="<?= $icon_bg ?>"><i class="bi bi-camera-fill"></i></div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div style="min-width: 0;">
                                        <div class="sesi-time"><i class="bi bi-clock-fill me-1"></i><?= $row['Jam_Mulai']->format('H:i') ?> - <?= $row['Jam_Selesai']->format('H:i') ?></div>
                                        <div class="sesi-title text-truncate"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                        <div class="sesi-info text-truncate"><?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                        <?php if (!empty($row['Keterangan'])): ?><div class="sesi-info mt-1"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['Keterangan']) ?></div><?php endif; ?>
                                    </div>
                                    <span class="badge-status <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <?php if ($row['Status_Sesi'] == 0): ?>
                                        <a href="../../Sesi/Proses/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action btn-action-success"><i class="bi bi-check-lg"></i> Mulai Sesi</a>
                                    <?php elseif ($row['Status_Sesi'] == 1 && empty($row['File_Hasil'])): ?>
                                        <a href="../../Sesi/Upload/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action"><i class="bi bi-cloud-upload"></i> Upload Hasil</a>
                                    <?php endif; ?>
                                    <a href="../../Sesi/Detail/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-detail"><i class="bi bi-eye"></i> Detail</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-5">
                            <div class="stat-icon stat-icon-purple mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-calendar-x"></i></div>
                            <h6 class="fw-bold text-muted">Tidak Ada Sesi Hari Ini</h6>
                            <p class="text-muted" style="font-size: 0.8rem;">Nikmati waktu luang Anda atau periksa jadwal mendatang.</p>
                            <a href="../../Sesi/Jadwal/index.php" class="btn-action" style="padding: 8px 20px; font-size: 0.85rem;"><i class="bi bi-calendar-week"></i> Lihat Jadwal</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 animate-fade-in delay-2">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-graph-up-arrow text-danger me-2"></i>Statistik Sesi</h5>
                        <span class="content-badge"><?= $tahun_filter ?></span>
                    </div>
                    <div style="height: 280px; width: 100%;"><canvas id="chartSesiBulan"></canvas></div>
                </div>
            </div>
        </div>

        <!-- BARIS 3: JADWAL MENDATANG & RIWAYAT SELESAI -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-calendar-event-fill text-danger me-2"></i>Sesi Mendatang</h5>
                        <a href="../../Sesi/Terjadwal/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Lihat Semua</a>
                    </div>
                    <form method="GET" class="filter-toolbar">
                        <input type="hidden" name="tahun" value="<?= $tahun_filter ?>">
                        <input type="hidden" name="rs_cari" value="<?= htmlspecialchars($rs_cari) ?>">
                        <input type="hidden" name="rs_dari" value="<?= htmlspecialchars($rs_dari) ?>">
                        <input type="hidden" name="rs_sampai" value="<?= htmlspecialchars($rs_sampai) ?>">
                        <input type="text" name="jm_cari" value="<?= htmlspecialchars($jm_cari) ?>" placeholder="Cari nama customer..." class="filter-input">
                        <input type="date" name="jm_dari" value="<?= htmlspecialchars($jm_dari) ?>" class="filter-date">
                        <input type="date" name="jm_sampai" value="<?= htmlspecialchars($jm_sampai) ?>" class="filter-date">
                        <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill"></i> Filter</button>
                        <?php if (!empty($jm_cari) || !empty($jm_dari) || !empty($jm_sampai)): ?>
                        <a href="?tahun=<?= $tahun_filter ?>&<?= http_build_query(['rs_cari'=>$rs_cari,'rs_dari'=>$rs_dari,'rs_sampai'=>$rs_sampai]) ?>" class="btn-reset"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                    <div class="timeline">
                        <?php if ($q_jadwal_mendatang && sqlsrv_has_rows($q_jadwal_mendatang)): while ($row = sqlsrv_fetch_array($q_jadwal_mendatang, SQLSRV_FETCH_ASSOC)): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div style="min-width: 0;">
                                        <div class="fw-bold" style="font-size: 0.85rem; color: var(--p-pink);"><?= $row['Tanggal_Jadwal']->format('d M Y') ?> • <?= $row['Jam_Mulai']->format('H:i') ?></div>
                                        <div class="fw-bold mt-1" style="font-size: 0.9rem;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                        <div class="text-muted" style="font-size: 0.78rem;"><?= htmlspecialchars($row['Nama_Paket']) ?> • <?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                    </div>
                                    <span class="badge-status badge-terjadwal">Terjadwal</span>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-check fs-1 mb-2" style="color: #cbd5e1;"></i>
                                <p class="text-muted" style="font-size: 0.85rem;"><?= (!empty($jm_cari)||!empty($jm_dari)||!empty($jm_sampai)) ? 'Tidak ada sesi yang cocok dengan filter.' : 'Tidak ada sesi mendatang.' ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 animate-fade-in delay-2">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-clock-history text-danger me-2"></i>Riwayat Selesai</h5>
                        <a href="../../Sesi/Selesai/index.php" class="btn btn-sm" style="background: var(--s-pink); color: var(--p-pink); font-weight: 700; border-radius: 8px; font-size: 0.75rem; text-decoration: none;">Lihat Semua</a>
                    </div>
                    <form method="GET" class="filter-toolbar">
                        <input type="hidden" name="tahun" value="<?= $tahun_filter ?>">
                        <input type="hidden" name="jm_cari" value="<?= htmlspecialchars($jm_cari) ?>">
                        <input type="hidden" name="jm_dari" value="<?= htmlspecialchars($jm_dari) ?>">
                        <input type="hidden" name="jm_sampai" value="<?= htmlspecialchars($jm_sampai) ?>">
                        <input type="text" name="rs_cari" value="<?= htmlspecialchars($rs_cari) ?>" placeholder="Cari nama customer..." class="filter-input">
                        <input type="date" name="rs_dari" value="<?= htmlspecialchars($rs_dari) ?>" class="filter-date">
                        <input type="date" name="rs_sampai" value="<?= htmlspecialchars($rs_sampai) ?>" class="filter-date">
                        <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill"></i> Filter</button>
                        <?php if (!empty($rs_cari) || !empty($rs_dari) || !empty($rs_sampai)): ?>
                        <a href="?tahun=<?= $tahun_filter ?>&<?= http_build_query(['jm_cari'=>$jm_cari,'jm_dari'=>$jm_dari,'jm_sampai'=>$jm_sampai]) ?>" class="btn-reset"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                    <?php if ($q_riwayat_selesai && sqlsrv_has_rows($q_riwayat_selesai)): while ($row = sqlsrv_fetch_array($q_riwayat_selesai, SQLSRV_FETCH_ASSOC)):
                        $upload_status = !empty($row['File_Hasil']) ? 'Sudah Upload' : 'Belum Upload';
                        $upload_class = !empty($row['File_Hasil']) ? 'badge-selesai' : 'badge-upload';
                    ?>
                        <div class="sesi-item" style="background: linear-gradient(135deg, #ffffff, #ecfdf5);">
                            <div class="sesi-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;"><i class="bi bi-check-lg"></i></div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div style="min-width: 0;">
                                        <div class="sesi-title text-truncate"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                        <div class="sesi-info"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                        <div class="sesi-time mt-1"><i class="bi bi-clock me-1"></i><?= $row['Waktu_Mulai'] ? $row['Waktu_Mulai']->format('d M Y H:i') : '-' ?></div>
                                    </div>
                                    <span class="badge-status <?= $upload_class ?>"><?= $upload_status ?></span>
                                </div>
                                <?php if (empty($row['File_Hasil'])): ?>
                                    <div class="mt-2"><a href="../../Sesi/Upload/index.php?id=<?= $row['ID_Sesi_Foto'] ?>" class="btn-action"><i class="bi bi-cloud-upload"></i> Upload Hasil</a></div>
                                <?php else: ?>
                                    <div class="mt-1 text-muted text-truncate" style="font-size: 0.75rem;"><i class="bi bi-file-earmark-image me-1"></i><?= htmlspecialchars($row['File_Hasil']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 mb-2" style="color: #cbd5e1;"></i>
                            <p class="text-muted" style="font-size: 0.85rem;"><?= (!empty($rs_cari)||!empty($rs_dari)||!empty($rs_sampai)) ? 'Tidak ada riwayat yang cocok dengan filter.' : 'Belum ada sesi selesai.' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- BARIS 4: QUICK ACTION -->
        <div class="row g-3">
            <div class="col-12 animate-fade-in delay-1">
                <div class="content-card">
                    <div class="content-header">
                        <h5 class="content-title"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Aksi Cepat</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6 quick-access-col">
                            <a href="../../Sesi/Jadwal/index.php" class="text-decoration-none">
                                <div class="card-3d card-3d-clickable text-center p-3">
                                    <div class="stat-icon stat-icon-purple mx-auto mb-2" style="width: 48px; height: 48px;"><i class="bi bi-calendar-week-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--text-dark);">Lihat Jadwal</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Semua sesi foto</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 quick-access-col">
                            <a href="../../Sesi/Terjadwal/index.php" class="text-decoration-none">
                                <div class="card-3d card-3d-clickable text-center p-3">
                                    <div class="stat-icon stat-icon-orange mx-auto mb-2" style="width: 48px; height: 48px;"><i class="bi bi-camera-reels-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--text-dark);">Sesi Terjadwal</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Mulai pemotretan</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 quick-access-col">
                            <a href="../../Sesi/Upload/index.php" class="text-decoration-none">
                                <div class="card-3d card-3d-clickable text-center p-3">
                                    <div class="stat-icon stat-icon-blue mx-auto mb-2" style="width: 48px; height: 48px;"><i class="bi bi-cloud-upload-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--text-dark);">Upload Hasil</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Kirim foto customer</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 quick-access-col">
                            <a href="../../Sesi/Selesai/index.php" class="text-decoration-none">
                                <div class="card-3d card-3d-clickable text-center p-3">
                                    <div class="stat-icon stat-icon-green mx-auto mb-2" style="width: 48px; height: 48px;"><i class="bi bi-check-circle-fill"></i></div>
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--text-dark);">Riwayat Selesai</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Sesi sudah diproses</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Fotografer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);"><img src="<?= $foto_fotografer_src ?>" alt="Foto Profil"></div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_fotografer) ?></h5>
              <span class="badge px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700; background: var(--p-pink) !important;">Fotografer</span>
            </div>
            <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></span></div>
                <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Username</small><span class="fw-bold text-dark" style="font-size: 0.85rem;">@<?= htmlspecialchars($username_fotografer) ?></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_fotografer) ?></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span></div>
                <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">No. HP</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span></div>
              </div>
            </div>
            <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius: 14px;">Edit Profil Saya ⚙</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL GANTI PROFIL -->
    <div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(213,61,102,0.25); background: rgba(255,255,255,0.95);">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Edit Profil</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <form method="POST" enctype="multipart/form-data">
              <div class="text-center mb-4">
                <div class="d-inline-block position-relative">
                  <div class="profile-preview-box mx-auto"><img id="profile-preview-modal" src="<?= $foto_fotografer_src ?>" alt="Foto Profil"></div>
                  <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                  <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom: -10px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 0.75rem; padding: 5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                </div>
              </div>
              <div class="mb-3"><label class="form-label">Nama Lengkap<span class="required-star">*</span></label><input type="text" name="nama" id="inputNamaModal" class="form-control" value="<?= htmlspecialchars($nama_fotografer) ?>" required></div>
              <div class="mb-3"><label class="form-label">Username<span class="required-star">*</span></label><input type="text" name="username" id="inputUsernameModal" class="form-control" value="<?= htmlspecialchars($username_fotografer) ?>" required></div>
              <div class="mb-3"><label class="form-label">Email<span class="required-star">*</span></label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email_fotografer) ?>" required></div>
              <div class="mb-3"><label class="form-label">No. HP<span class="required-star">*</span></label><input type="text" name="no_hp" id="inputHPModal" class="form-control" value="<?= htmlspecialchars($d_profile['no_hp'] ?? '') ?>" required></div>
              <div class="mb-3"><label class="form-label">Alamat<span class="required-star">*</span></label><textarea name="alamat" class="form-control" rows="2" required style="resize: none;"><?= htmlspecialchars($d_profile['alamat'] ?? '') ?></textarea></div>
              <div class="row">
                  <div class="col-md-6 mb-3"><label class="form-label">Sandi Baru (Opsional)</label><div class="password-group"><input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter"><i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i></div></div>
                  <div class="col-md-6 mb-3"><label class="form-label">Konfirmasi Sandi</label><div class="password-group"><input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru"><i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i></div></div>
              </div>
              <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan ✨</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        document.querySelectorAll('.submenu-link, .nav-link-custom:not(.btn-toggle-submenu)').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) { const sidebar = document.getElementById('sidebar'); if (sidebar.classList.contains('open')) toggleSidebar(); }
            });
        });
        document.querySelectorAll('.btn-toggle-submenu').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.querySelector(targetId);
                const chevron = this.querySelector('.icon-chevron');
                if (targetEl) {
                    const isShown = targetEl.classList.contains('show');
                    document.querySelectorAll('.submenu').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.icon-chevron').forEach(icon => icon.style.transform = 'rotate(0deg)');
                    if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
                }
            });
        });
        function bukaModalProfil() { var modalProfil = new bootstrap.Modal(document.getElementById('modalGantiProfil')); modalProfil.show(); }
        function bukaModalBiodata() { var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata')); modalBiodata.show(); }
        function bukaModalEditDariBiodata() { var modalBiodata = bootstrap.Modal.getInstance(document.getElementById('modalLihatBiodata')); if (modalBiodata) modalBiodata.hide(); setTimeout(bukaModalProfil, 400); }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda? ✦', text: 'Anda akan dialihkan ke halaman utama.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
        }
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar? ❌', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
        }
        const inputFotoModal = document.getElementById('inputFotoModal');
        if (inputFotoModal) {
            inputFotoModal.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) { const reader = new FileReader(); reader.onload = function(event) { document.getElementById('profile-preview-modal').src = event.target.result; }; reader.readAsDataURL(file); }
            });
        }
        const inputNamaModal = document.getElementById('inputNamaModal');
        if (inputNamaModal) { inputNamaModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z ]/g, ''); }); }
        const inputUsernameModal = document.getElementById('inputUsernameModal');
        if (inputUsernameModal) { inputUsernameModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z0-9_]/g, ''); }); }
        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId); const input = document.getElementById(inputId);
            if (btn && input) { btn.addEventListener('click', function () { const type = input.getAttribute('type') === 'password' ? 'text' : 'password'; input.setAttribute('type', type); this.classList.toggle('bi-eye'); this.classList.toggle('bi-eye-slash'); }); }
        }
        setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
        setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');
        const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62';
        if (inputHPModal) {
            inputHPModal.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
                let digits = this.value.split(prefix)[1]?.replace(/[^0-9]/g, '') || '';
                if (digits.length > 13) digits = digits.slice(0, 13);
                this.value = prefix + digits;
            });
        }
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
            const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
            const str = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;
            const el = document.getElementById('live-clock');
            if (el) el.innerText = str;
        }
        setInterval(updateLiveClock, 1000); updateLiveClock();

        const ctxSesiBulan = document.getElementById('chartSesiBulan');
        if (ctxSesiBulan) {
            const ctx = ctxSesiBulan.getContext('2d');
            const gradientPink = ctx.createLinearGradient(0, 0, 0, 300);
            gradientPink.addColorStop(0, 'rgba(213, 61, 102, 0.45)');
            gradientPink.addColorStop(1, 'rgba(255, 240, 243, 0.05)');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
                    datasets: [{
                        label: 'Sesi Selesai',
                        data: <?= json_encode($sesi_bulan_data) ?>,
                        backgroundColor: ['#FFE4E9','#FFD6E0','#FFB3C6','#E85D84','#E85D84','#D53D66','#CA3366','#B52D56','#7C1440','#FFD6E0','#FFB3C6','#E85D84'],
                        borderRadius: 10, borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(30,30,36,0.9)', titleFont: { family: 'Plus Jakarta Sans', size: 13 }, bodyFont: { family: 'Plus Jakarta Sans', size: 12 }, padding: 12, cornerRadius: 10 }
                    },
                    scales: {
                        y: { grid: { color: 'rgba(255,228,233,0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 }, stepSize: 1 } },
                        x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } }
                    }
                }
            });
        }
    </script>

    <?php if(isset($success_profile) && $success_profile === true): ?>
    <script>Swal.fire({ icon: 'success', title: 'Profil Diperbarui! 🎉', text: 'Informasi profil Anda berhasil diperbarui.', confirmButtonColor: '#D53D66', confirmButtonText: 'Selesai' });</script>
    <?php endif; ?>

    <?php if(isset($error_profile) && $error_profile !== ""): ?>
    <script>Swal.fire({ icon: 'error', title: 'Gagal! ❌', text: '<?= $error_profile ?>', confirmButtonColor: '#D53D66', confirmButtonText: 'Periksa Kembali' }).then(() => { var modalGanti = new bootstrap.Modal(document.getElementById('modalGantiProfil')); modalGanti.show(); });</script>
    <?php endif; ?>
</body>
</html>