<?php
session_start();
include '../../koneksi.php';

// =====================================================
// HELPER FUNCTIONS - SAFE SQLSRV ANTI-CRASH
// =====================================================
function safe_sqlsrv_query($conn, $sql, $params = array()) {
    $query = sqlsrv_query($conn, $sql, $params);
    if ($query === false) {
        error_log("SQLSRV Error: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    return $query;
}

function safe_sqlsrv_fetch($query) {
    if (!$query) return false;
    return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
}

function safe_sqlsrv_count($conn, $sql, $params = array()) {
    $query = safe_sqlsrv_query($conn, $sql, $params);
    if (!$query) return 0;
    $row = safe_sqlsrv_fetch($query);
    return $row ? ($row['total'] ?? 0) : 0;
}

// =====================================================
// PROTEKSI KEAMANAN HAK AKSES
// =====================================================
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];
$username_session = $_SESSION['username'] ?? 'system';

// Ambil Profil Owner
$q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = safe_sqlsrv_fetch($q_profile);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// Mencari file foto di lokasi folder aset yang sesuai
if ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/karyawan/" . $foto_owner;
} elseif ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/pelanggan/" . $foto_owner;
} else {
    $foto_owner_src = $default_svg_avatar;
}

// =====================================================
// LOGIKA UPDATE PROFIL OWNER (SINKRON DENGAN DASHBOARD)
// =====================================================
$error_profile = "";
$success_profile = false;
if (isset($_POST['update_profil'])) {
    $nama_post = trim($_POST['nama']);
    $username_post = trim($_POST['username']);
    $email_post = trim($_POST['email']);
    $no_hp_post = trim($_POST['no_hp']);
    $alamat_post = trim($_POST['alamat']);
    $password_post = $_POST['password'];
    $confirm_password_post = $_POST['confirm_password'];

    if (preg_match('/[^a-zA-Z ]/', $nama_post)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf dan spasi!";
    } elseif (preg_match('/[^a-zA-Z0-9_]/', $username_post)) {
        $error_profile = "Username hanya boleh berisi huruf, angka, dan underscore!";
    } else {
        $q_check = safe_sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE (Username_Karyawan = ? OR Email_Karyawan = ?) AND ID_Karyawan != ?", array($username_post, $email_post, $id_owner));
        $d_check = safe_sqlsrv_fetch($q_check);
        if (($d_check['total'] ?? 0) > 0) {
            $error_profile = "Username atau Email sudah terdaftar oleh akun staf lain!";
        } else {
            $sql_up = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, No_Hp = ?, Alamat = ? WHERE ID_Karyawan = ?";
            $params_up = array($nama_post, $username_post, $email_post, $no_hp_post, $alamat_post, $id_owner);
            $res_up = safe_sqlsrv_query($conn, $sql_up, $params_up);

            if ($res_up !== false) {
                if (!empty($password_post)) {
                    if (strlen($password_post) < 8) {
                        $error_profile = "Sandi baru minimal harus 8 karakter!";
                    } elseif ($password_post !== $confirm_password_post) {
                        $error_profile = "Konfirmasi sandi tidak cocok!";
                    } else {
                        $hashed_password = password_hash($password_post, PASSWORD_DEFAULT);
                        safe_sqlsrv_query($conn, "UPDATE Karyawan SET Password_Karyawan = ? WHERE ID_Karyawan = ?", array($hashed_password, $id_owner));
                    }
                }

                if (empty($error_profile) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
                    $file_name = $_FILES['foto_profil']['name'];
                    $file_tmp = $_FILES['foto_profil']['tmp_name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = array('jpg', 'jpeg', 'png');

                    if (in_array($file_ext, $allowed_ext)) {
                        $new_file_name = "owner_" . $id_owner . "_" . time() . "." . $file_ext;
                        $upload_dir = "../../assets/img/karyawan/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        
                        if ($foto_owner != 'default.jpg' && file_exists($upload_dir . $foto_owner)) {
                            @unlink($upload_dir . $foto_owner);
                        }

                        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                            safe_sqlsrv_query($conn, "UPDATE Karyawan SET Foto_Profil = ? WHERE ID_Karyawan = ?", array($new_file_name, $id_owner));
                            $foto_owner = $new_file_name;
                        }
                    }
                }

                if (empty($error_profile)) {
                    $success_profile = true;
                    $q_profile = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
                    $d_profile = safe_sqlsrv_fetch($q_profile);
                    if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }
                    $nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
                    $username_owner = $d_profile['username_karyawan'] ?? 'owner';
                    $email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
                    $foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';
                    $foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_owner)) ? "../../assets/img/karyawan/" . $foto_owner : $default_svg_avatar;
                }
            } else {
                $error_profile = "Gagal memperbarui profil di database!";
            }
        }
    }
}

// =====================================================
// HITUNG UMUR
// =====================================================
function hitungUmur($tanggal_lahir) {
    if (!$tanggal_lahir) return '-';
    if (is_object($tanggal_lahir) && method_exists($tanggal_lahir, 'format')) {
        $tgl = $tanggal_lahir->format('Y-m-d');
    } else { $tgl = $tanggal_lahir; }
    $birthDate = new DateTime($tgl);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y . " tahun";
}

// =====================================================
// TAB FILTER
// =====================================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'aktif';

// =====================================================
// STATISTIK
// =====================================================
$stats = array();
$stats['total'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['admin'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Admin' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['fotografer'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['owner'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['aktif'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['nonaktif'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Status = 0 AND Is_Deleted = 0 AND ID_Karyawan != ?", array($id_owner));
$stats['dihapus'] = safe_sqlsrv_count($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Is_Deleted = 1 AND ID_Karyawan != ?", array($id_owner));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Karyawan – SpotLight Studio</title>
    
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --p-pink: #d83f67; 
            --d-pink: #c73165; 
            --s-pink: #fff5f6; 
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --sidebar-bg: #ffffff;
            --body-bg: #f8fafc;
            --border-color: #f1f5f9;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR STYLING */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 236, 239, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1px;
            margin-bottom: 40px;
            display: block;
        }
        .sidebar-brand span {
            color: var(--text-dark);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-menu-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            scrollbar-width: none;
        }
        .sidebar-menu-wrapper::-webkit-scrollbar {
            display: none;
        }
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .nav-item {
            margin-bottom: 8px;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            color: #4a5568;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink);
            color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu {
            list-style: none;
            padding-left: 20px;
            margin-top: 5px;
            display: none; 
            transition: var(--transition-3d);
        }
        .submenu.show {
            display: block !important;
        }
        .submenu-link {
            display: flex;
            align-items: center;
            padding: 8px 18px;
            color: #718096;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink);
            background-color: rgba(216, 63, 103, 0.03);
            padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.2);
        }

        /* MAIN CONTENT AREA */
        .main-content {
            margin-left: 260px;
            padding: 40px;
            min-height: 100vh;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        /* TOMBOL AVATAR PROFIL ATAS KANAN */
        .profile-header-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ffffff;
            cursor: pointer;
            transition: var(--transition-3d);
            background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* BREADCRUMB */
        .breadcrumb-custom { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.8rem; 
            color: var(--text-muted); 
            margin-bottom: 12px; 
        }
        .breadcrumb-custom a { 
            color: var(--p-pink); 
            text-decoration: none; 
            font-weight: 700; 
            transition: var(--transition-3d);
        }
        .breadcrumb-custom a:hover {
            color: var(--d-pink);
        }
        .breadcrumb-custom .current { 
            color: var(--text-dark); 
            font-weight: 800; 
        }

        /* STATS SCROLL WRAPPER */
        .stats-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 15px;
            margin-bottom: 25px;
            scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .stats-scroll-wrapper::-webkit-scrollbar {
            height: 6px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            border-radius: 10px;
        }
        .stats-row {
            display: flex;
            gap: 16px;
            min-width: max-content;
        }
        .stat-card-item {
            min-width: 200px;
            max-width: 260px;
            flex: 0 0 auto;
        }

        /* KARTU INDIKATOR MELAYANG 3D */
        .card-3d {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.03);
            transition: var(--transition-3d);
            padding: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 45px rgba(216, 63, 103, 0.14); 
            border-color: var(--p-pink);
        }
        .card-3d:hover::before {
            opacity: 1;
        }

        /* Stat Cards dengan Icon Background */
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: var(--transition-3d);
            flex-shrink: 0;
        }
        .card-3d:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .stat-icon-pink { background: linear-gradient(135deg, #fff5f6, #ffe4e9); color: var(--p-pink); }
        .stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
        .stat-icon-dark { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #1e1e24; }

        .stat-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .stat-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-title {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-subtitle {
            font-size: 0.68rem;
            color: #a0aec0;
            font-weight: 600;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* TAB FILTER */
        .tab-filter-wrapper { 
            display: flex; 
            gap: 8px; 
            margin-bottom: 25px; 
        }
        .tab-filter-btn {
            padding: 10px 22px; 
            border-radius: 12px; 
            border: 1px solid rgba(255, 236, 239, 0.8);
            font-weight: 700; 
            font-size: 0.82rem; 
            cursor: pointer; 
            transition: var(--transition-3d);
            background: #ffffff; 
            color: var(--text-muted); 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .tab-filter-btn:hover { 
            border-color: var(--p-pink); 
            color: var(--p-pink); 
            transform: translateY(-2px); 
        }
        .tab-filter-btn.active { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); 
            color: #ffffff; 
            border-color: var(--p-pink); 
            box-shadow: 0 8px 20px rgba(216,63,103,0.2); 
        }
        .tab-filter-btn .badge-count { 
            background: rgba(0,0,0,0.05); 
            padding: 2px 8px; 
            border-radius: 50px; 
            font-size: 0.7rem; 
            font-weight: 800; 
            color: var(--p-pink); 
        }
        .tab-filter-btn.active .badge-count { 
            background: rgba(255,255,255,0.2); 
            color: #ffffff; 
        }

        /* BUTTONS */
        .btn-reg-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; 
            border-radius: 12px !important;
            padding: 12px 24px !important; 
            font-weight: 800 !important; 
            border: none !important;
            box-shadow: 0 6px 16px rgba(216, 63, 103, 0.25) !important; 
            transition: var(--transition-3d) !important;
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.85rem;
            text-decoration: none;
        }
        .btn-reg-header:hover { 
            transform: translateY(-3px) scale(1.02); 
            box-shadow: 0 10px 22px rgba(216, 63, 103, 0.35) !important; 
        }

        /* SEARCH & FILTER */
        .search-filter-wrapper { 
            display: flex; 
            gap: 12px; 
            margin-bottom: 25px; 
            align-items: center; 
        }
        .btn-filter-toggle {
            background: #ffffff; 
            border: 2px solid #eef2f6; 
            border-radius: 14px;
            padding: 12px 18px; 
            font-weight: 700; 
            color: var(--text-dark); 
            font-size: 0.85rem;
            transition: var(--transition-3d); 
            display: flex; 
            align-items: center; 
            gap: 6px;
        }
        .btn-filter-toggle:hover { 
            border-color: var(--p-pink); 
            color: var(--p-pink); 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.05);
        }

        /* TABLE STYLING FLOATING ROW (TABLE CUSTOM) */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table-custom thead th {
            color: var(--text-muted);
            font-weight: 800;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 16px;
            border: none;
            background: transparent;
        }
        .table-custom tbody tr {
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            transition: var(--transition-3d);
        }
        .table-custom tbody tr:hover {
            transform: translateX(4px) scale(1.002);
            box-shadow: 0 8px 24px rgba(216, 63, 103, 0.08);
            background: #fff8f9 !important;
        }
        .table-custom td {
            padding: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            vertical-align: middle;
        }
        .table-custom td:first-child {
            border-radius: 12px 0 0 12px;
            font-weight: 800;
            color: var(--text-muted);
        }
        .table-custom td:last-child {
            border-radius: 0 12px 12px 0;
        }

        /* ARSIP / DELETED ROW */
        .table-custom tbody tr.row-deleted {
            background-color: #fef2f2;
            opacity: 0.85;
        }
        .table-custom tbody tr.row-deleted:hover {
            background-color: #fee2e2 !important;
        }

        /* AVATAR */
        .avatar-default {
            width: 40px; 
            height: 40px; 
            border-radius: 12px; 
            background: var(--s-pink);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--p-pink);
            font-size: 1.2rem; 
            flex-shrink: 0;
            overflow: hidden;
            border: 1.5px solid var(--light-pink);
        }
        .avatar-default img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }

        /* TEXT KARYAWAN */
        .nama-karyawan { 
            font-weight: 800; 
            color: var(--text-dark); 
            font-size: 0.9rem; 
        }
        .username-karyawan { 
            font-size: 0.75rem; 
            color: var(--text-muted); 
            font-weight: 600; 
        }

        /* ROLE BADGE */
        .badge-role {
            font-size: 0.7rem; 
            font-weight: 800; 
            padding: 5px 12px; 
            border-radius: 50px;
            display: inline-block; 
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-role-admin { background: #eff6ff; color: #2563eb; }
        .badge-role-foto { background: var(--s-pink); color: var(--p-pink); }
        .badge-role-owner { background: #f5f3ff; color: #8b5cf6; }

        /* STATUS DOT */
        .status-dot { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 0.8rem; 
            font-weight: 700; 
        }
        .status-dot .dot { 
            width: 8px; 
            height: 8px; 
            border-radius: 50%; 
            display: inline-block; 
        }
        .status-dot .dot.aktif { 
            background: #10b981; 
            box-shadow: 0 0 0 3px rgba(16,185,129,0.25); 
        }
        .status-dot .dot.nonaktif { 
            background: #cbd5e1; 
        }
        .status-dot .text-aktif { color: #10b981; }
        .status-dot .text-nonaktif { color: var(--text-muted); }

        /* BUTTON AKSI BULAT OUTLINE PINK MUDA */
        .btn-aksi {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-3d);
            border: 1.5px solid var(--light-pink);
            background: #ffffff;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            margin: 0 4px;
        }
        .btn-aksi:hover {
            transform: translateY(-3px) scale(1.08);
            background-color: var(--s-pink);
            border-color: var(--p-pink);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }
        .btn-aksi-edit { color: var(--p-pink); }
        .btn-aksi-toggle { color: var(--p-pink); }
        .btn-aksi-delete { color: var(--p-pink); }
        
        .btn-aksi-restore { color: #059669; border-color: #d1fae5; }
        .btn-aksi-restore:hover { background-color: #ecfdf5; border-color: #059669; }
        .btn-aksi-hard { color: #dc2626; border-color: #fee2e2; }
        .btn-aksi-hard:hover { background-color: #fef2f2; border-color: #dc2626; }

        /* PAGINATION */
        .pagination-wrapper { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 25px; 
            padding: 16px 24px; 
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid rgba(255, 236, 239, 0.8);
            box-shadow: 0 4px 12px rgba(0,0,0,0.01);
        }
        .pagination-info { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 800; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; }
        .page-link-pag {
            display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
            padding: 0 12px; border-radius: 10px; background: transparent; border: 1px solid transparent;
            color: var(--text-muted); font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: var(--transition-3d);
        }
        .page-link-pag:hover { background: var(--s-pink); color: var(--p-pink); }
        .page-link-pag.active-pag { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; }
        .page-link-pag.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        /* MODAL STYLING */
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .form-control, .form-select { 
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; 
            background: #f8fafc; font-size: 14px; font-weight: 600; 
            transition: var(--transition-3d); color: var(--text-dark); 
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--p-pink); background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); outline: none;
        }
        .profile-preview-box {
            width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
            border: 2.5px solid #eef2f6; background: #f8fafc; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            transition: var(--transition-3d);
        }
        .profile-preview-box img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .btn-pilih-foto {
            background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink);
            font-weight: 700; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-pilih-foto:hover {
            background: var(--p-pink); color: #ffffff; transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }
        .btn-reg { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; 
            border-radius: 16px; padding: 16px; font-weight: 800; border: none; 
            width: 100%; transition: var(--transition-3d); margin-top: 15px; 
            font-size: 15px; box-shadow: 0 10px 25px rgba(216, 63, 103, 0.25); 
        }
        .btn-reg:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 15px 35px rgba(216, 63, 103, 0.35); 
        }

        /* CSS Sandi Grup 3D */
        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px;}
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none !important; box-shadow: none !important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        @media (max-width: 1200px) {
            .main-content { padding: 20px; }
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 15px; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>

<!-- Bilah Samping -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">
            SpotLight.<br>
            <span>Beranda Pemilik</span>
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../../Role/Owner/index.php" class="nav-link-custom">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                    <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                    <i class="bi bi-chevron-down small icon-chevron" style="transform: rotate(180deg);"></i>
                </a>
                <div class="submenu show" id="submenuMaster">
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="submenu-link active"><i class="bi bi-person-badge-fill me-2"></i>Kelola Karyawan</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                    <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu" id="submenuLaporan">
                    <ul class="list-unstyled">
                        <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                        <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                        <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                        <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)">
                    <span><i class="bi bi-house-door-fill me-2"></i>Beranda</span>
                </a>
            </li>
        </ul>
    </div>

    <div>
        <button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100">
            <i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem
        </button>
    </div>
</div>

<!-- Area Konten Utama -->
<div class="main-content">

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom">
        <a href="../../Role/Owner/index.php">Dashboard</a>
        <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
        <span class="current">Kelola Karyawan</span>
    </div>

    <!-- HEADER SINKRON DENGAN DASHBOARD -->
    <div class="dashboard-header">
        <div>
            <h3 class="fw-bold mb-1">Kelola Karyawan ✦</h3>
            <p class="text-muted small mb-0">Kelola dan pantau hak akses data staf SpotLight Studio.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
            </span>
            <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
            </div>
            <a href="tambah.php" class="btn btn-reg-header"><i class="bi bi-plus-lg"></i> Tambah Staf</a>
        </div>
    </div>

    <!-- BARIS KARTU INDIKATOR UTAMA - SCROLL HORIZONTAL (SINKRON DASHBOARD) -->
    <div class="stats-scroll-wrapper">
        <div class="stats-row">
            <!-- Card 1: Total Staf -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid var(--p-pink);">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-pink"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Total Staf</div>
                            <div class="stat-val"><?= $stats['total'] ?></div>
                            <div class="stat-subtitle">Staf terdaftar</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Admin -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid #2563eb;">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-blue"><i class="bi bi-person-workspace"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Admin</div>
                            <div class="stat-val"><?= $stats['admin'] ?></div>
                            <div class="stat-subtitle">Tim Pengelola</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Fotografer -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid var(--p-pink);">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-pink"><i class="bi bi-camera-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Fotografer</div>
                            <div class="stat-val"><?= $stats['fotografer'] ?></div>
                            <div class="stat-subtitle">Tim Kreatif</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Owner -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid #8b5cf6;">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-purple"><i class="bi bi-person-check-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Owner</div>
                            <div class="stat-val"><?= $stats['owner'] ?></div>
                            <div class="stat-subtitle">Hak Akses Penuh</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 5: Aktif -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid #10b981;">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Aktif</div>
                            <div class="stat-val"><?= $stats['aktif'] ?></div>
                            <div class="stat-subtitle">Staf aktif bekerja</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 6: Nonaktif -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid #d97706;">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-orange"><i class="bi bi-x-circle-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Nonaktif</div>
                            <div class="stat-val"><?= $stats['nonaktif'] ?></div>
                            <div class="stat-subtitle">Staf dinonaktifkan</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 7: Dihapus -->
            <div class="stat-card-item">
                <div class="card-3d" style="border-left: 3px solid #dc2626;">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-red"><i class="bi bi-trash-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-title">Dihapus</div>
                            <div class="stat-val"><?= $stats['dihapus'] ?></div>
                            <div class="stat-subtitle">Staf diarsipkan</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB FILTER -->
    <div class="tab-filter-wrapper">
        <a href="?tab=aktif<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'aktif' ? 'active' : '' ?>">
            <i class="bi bi-check-circle-fill"></i> Aktif <span class="badge-count"><?= $stats['total'] ?></span>
        </a>
        <a href="?tab=dihapus<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'dihapus' ? 'active' : '' ?>">
            <i class="bi bi-trash-fill"></i> Dihapus <span class="badge-count"><?= $stats['dihapus'] ?></span>
        </a>
        <a href="?tab=semua<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?>" class="tab-filter-btn <?= $tab == 'semua' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i> Semua <span class="badge-count"><?= $stats['total'] + $stats['dihapus'] ?></span>
        </a>
    </div>

    <!-- SEARCH & FILTER SINKRON DENGAN ELEMEN FORMULIR -->
    <div class="search-filter-wrapper">
        <form method="GET" class="d-flex gap-2 align-items-center" style="flex: 1;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="cari" class="form-control" style="max-width: 350px;" placeholder="Cari nama, NIK, atau email..." value="<?= htmlspecialchars(@$_GET['cari']) ?>">
            <button type="submit" class="btn btn-reg-header" style="padding: 12px 20px !important;"><i class="bi bi-search"></i> Cari</button>
        </form>
        <button type="button" class="btn-filter-toggle" data-bs-toggle="modal" data-bs-target="#modalFilter"><i class="bi bi-funnel-fill"></i> Filter Data</button>
    </div>

    <!-- TABLE DENGAN PROSES FLOATING ROW (SINKRON INTERAKSI DASHBOARD) -->
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 120px;">NIK</th>
                    <th>Nama</th>
                    <th style="width: 80px;">Umur</th>
                    <th style="width: 140px;">Telepon</th>
                    <th style="width: 100px;">Kelamin</th>
                    <th style="width: 100px;">Peran</th>
                    <th style="width: 80px;">Status</th>
                    <th style="width: 180px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
<?php
// =====================================================
// QUERY DATA DENGAN OFFSET & FETCH ROWS
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

$conditions = array("ID_Karyawan != ?");
$params = array($id_owner);

if ($tab == 'aktif') { $conditions[] = "Is_Deleted = 0"; }
elseif ($tab == 'dihapus') { $conditions[] = "Is_Deleted = 1"; }

if (!empty($cari)) {
    $conditions[] = "(Nama_Karyawan LIKE ? OR NIK LIKE ? OR Email_Karyawan LIKE ? OR Username_Karyawan LIKE ?)";
    $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
}
if ($status_filter !== "") { $conditions[] = "Status = ?"; $params[] = (int)$status_filter; }
if (!empty($role_filter)) { $conditions[] = "Role_Karyawan = ?"; $params[] = $role_filter; }

$order_clause = "Nama_Karyawan ASC";
if ($sort == "nama_desc") { $order_clause = "Nama_Karyawan DESC"; }
elseif ($sort == "umur_muda") { $order_clause = "Tanggal_Lahir DESC"; }
elseif ($sort == "umur_tua") { $order_clause = "Tanggal_Lahir ASC"; }
elseif ($sort == "baru") { $order_clause = "Created_Date DESC"; }
elseif ($sort == "lama") { $order_clause = "Created_Date ASC"; }

$sql_count = "SELECT COUNT(*) AS total FROM Karyawan WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $sql_count, $params);
$total_halaman = ceil($total_records / $limit);

$sql_list = "SELECT * FROM Karyawan WHERE " . implode(" AND ", $conditions) . " ORDER BY " . $order_clause . " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
$query_list = safe_sqlsrv_query($conn, $sql_list, $params);

$no = $offset + 1;
if ($query_list && sqlsrv_has_rows($query_list)):
    while($row = sqlsrv_fetch_array($query_list, SQLSRV_FETCH_ASSOC)):
        $umur = hitungUmur($row['Tanggal_Lahir'] ?? null);
        $isDeleted = ($row['Is_Deleted'] == 1);
        $isOwnerSelf = ($row['ID_Karyawan'] == $id_owner);
        $roleClass = "badge-role-" . ($row['Role_Karyawan'] == 'Fotografer' ? 'foto' : strtolower($row['Role_Karyawan']));
        
        // Cek file foto karyawan untuk merender di tabel
        $foto_karyawan = $row['Foto_Profil'] ?? 'default.jpg';
        if ($foto_karyawan != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_karyawan)) {
            $foto_karyawan_src = "../../assets/img/karyawan/" . $foto_karyawan;
        } elseif ($foto_karyawan != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_karyawan)) {
            $foto_karyawan_src = "../../assets/img/pelanggan/" . $foto_karyawan;
        } else {
            $foto_karyawan_src = $default_svg_avatar;
        }
?>
                <tr class="<?= $isDeleted ? 'row-deleted' : '' ?>">
                    <td><?= $no++ ?></td>
                    <td style="font-weight: 800; color: var(--text-muted);"><?= htmlspecialchars($row['NIK']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-3" style="cursor: pointer;" onclick="bukaDetailRow(this)"
                                data-nama="<?= htmlspecialchars($row['Nama_Karyawan']) ?>"
                                data-nik="<?= htmlspecialchars($row['NIK']) ?>"
                                data-role="<?= htmlspecialchars($row['Role_Karyawan']) ?>"
                                data-umur="<?= $umur ?>"
                                data-hp="<?= htmlspecialchars($row['No_Hp']) ?>"
                                data-jk="<?= htmlspecialchars($row['Jenis_Kelamin']) ?>"
                                data-email="<?= htmlspecialchars($row['Email_Karyawan']) ?>"
                                data-alamat="<?= htmlspecialchars($row['Alamat'] ?? '-') ?>"
                                data-foto="<?= $foto_karyawan_src ?>"
                                data-status="<?= $row['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?>">
                            <div class="avatar-default">
                                <img src="<?= $foto_karyawan_src ?>" alt="Avatar Staf">
                            </div>
                            <div>
                                <div class="nama-karyawan"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div>
                                <div class="username-karyawan">@<?= htmlspecialchars($row['Username_Karyawan']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= $umur ?></td>
                    <td><?= htmlspecialchars($row['No_Hp']) ?></td>
                    <td><?= htmlspecialchars($row['Jenis_Kelamin']) ?></td>
                    <td><span class="badge-role <?= $roleClass ?>"><?= htmlspecialchars($row['Role_Karyawan']) ?></span></td>
                    <td>
                        <?php if (!$isDeleted): ?>
                        <div class="status-dot">
                            <span class="dot <?= $row['Status'] == 1 ? 'aktif' : 'nonaktif' ?>"></span>
                            <span class="<?= $row['Status'] == 1 ? 'text-aktif' : 'text-nonaktif' ?>"><?= $row['Status'] == 1 ? 'Aktif' : 'Nonaktif' ?></span>
                        </div>
                        <?php else: ?>
                        <span style="font-size: 0.75rem; color: #dc2626; font-weight: 800;">DIHAPUS</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!$isDeleted): ?>
                            <!-- AKSI DATA AKTIF -->
                            <a href="edit.php?id=<?= $row['ID_Karyawan'] ?>" class="btn-aksi btn-aksi-edit" title="Edit Karyawan">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <button class="btn-aksi btn-aksi-toggle" onclick="confirmSoftDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Arsipkan Karyawan">
                                <i class="bi bi-toggle2-on" style="font-size: 1.25rem;"></i>
                            </button>

                            <?php if (!$isOwnerSelf): ?>
                                <button class="btn-aksi btn-aksi-delete" onclick="confirmHardDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-aksi btn-aksi-delete" style="opacity: 0.35; cursor: not-allowed;" disabled title="Tidak bisa hapus akun sendiri"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- AKSI DATA ARSIP -->
                            <button class="btn-aksi btn-aksi-edit" style="opacity: 0.35; cursor: not-allowed;" disabled title="Pulihkan terlebih dahulu untuk mengedit">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn-aksi btn-aksi-restore" onclick="confirmRestore(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Pulihkan Karyawan">
                                <i class="bi bi-toggle2-off" style="font-size: 1.25rem;"></i>
                            </button>

                            <?php if (!$isOwnerSelf): ?>
                                <button class="btn-aksi btn-aksi-hard" onclick="confirmHardDelete(<?= $row['ID_Karyawan'] ?>, '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-aksi btn-aksi-hard" style="opacity: 0.35; cursor: not-allowed;" disabled title="Tidak bisa hapus akun sendiri"><i class="bi bi-trash-fill"></i></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endwhile; else: ?>
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                        <p class="text-muted mt-2 fw-bold" style="font-size: 0.85rem;">Tidak ada data karyawan ditemukan</p>
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_halaman > 1 || $total_records > 0): ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> data</div>
        <nav class="pagination-nav">
            <?php if ($halaman > 1): ?>
                <a class="page-link-pag" href="?tab=<?= $tab ?>&halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>

            <?php for ($i = 1; $i <= $total_halaman; $i++): ?>
                <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="?tab=<?= $tab ?>&halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($halaman < $total_halaman): ?>
                <a class="page-link-pag" href="?tab=<?= $tab ?>&halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL FILTER SINKRON DENGAN ELEMEN DESAIN FORMULIR -->
<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-funnel-fill text-danger me-2"></i>Filter Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <form method="GET" id="formModalFilter">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <div class="mb-3">
                        <label class="form-label">Urutkan Berdasarkan</label>
                        <select name="sort" class="form-select">
                            <option value="nama_asc" <?= ($sort == 'nama_asc') ? 'selected' : '' ?>>Nama A-Z</option>
                            <option value="nama_desc" <?= ($sort == 'nama_desc') ? 'selected' : '' ?>>Nama Z-A</option>
                            <option value="umur_muda" <?= ($sort == 'umur_muda') ? 'selected' : '' ?>>Umur Termuda</option>
                            <option value="umur_tua" <?= ($sort == 'umur_tua') ? 'selected' : '' ?>>Umur Tertua</option>
                            <option value="baru" <?= ($sort == 'baru') ? 'selected' : '' ?>>Terbaru</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status Akun</label>
                        <select name="status" class="form-select">
                            <option value="" <?= ($status_filter == '') ? 'selected' : '' ?>>Semua</option>
                            <option value="1" <?= ($status_filter == '1') ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= ($status_filter == '0') ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Peran Kerja</label>
                        <select name="role" class="form-select">
                            <option value="" <?= ($role_filter == '') ? 'selected' : '' ?>>Semua</option>
                            <option value="Admin" <?= ($role_filter == 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Fotografer" <?= ($role_filter == 'Fotografer') ? 'selected' : '' ?>>Fotografer</option>
                            <option value="Owner" <?= ($role_filter == 'Owner') ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-reg shadow-sm py-3 mt-0" style="border-radius: 14px;">Terapkan Filter ✨</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL LIHAT BIODATA OWNER (SINKRON DASHBOARD) -->
<div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
      <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pemilik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 pb-4 pt-3">
        <div class="text-center mb-4">
          <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
            <img src="<?= $foto_owner_src ?>" alt="Foto Profil">
          </div>
          <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_owner) ?></h5>
          <span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Owner (Pemilik)</span>
        </div>
        <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc; box-shadow: none;">
          <div class="row g-3">
            <div class="col-6">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['nik']) ?></span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nama Pengguna</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;">@<?= htmlspecialchars($username_owner) ?></span>
            </div>
            <div class="col-12 border-top pt-2">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Email</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($email_owner) ?></span>
            </div>
            <div class="col-6 border-top pt-2">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin']) ?></span>
            </div>
            <div class="col-6 border-top pt-2">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Tanggal Lahir</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= $d_profile['tanggal_lahir'] ? $d_profile['tanggal_lahir']->format('d M Y') : '-' ?></span>
            </div>
            <div class="col-12 border-top pt-2">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Nomor Telepon</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['no_hp']) ?></span>
            </div>
            <div class="col-12 border-top pt-2">
              <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Lengkap</small>
              <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($d_profile['alamat']) ?></span>
            </div>
          </div>
        </div>
        <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius: 14px;">Edit Profil Anda ⚙</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL GANTI PROFIL OWNER (SINKRON DASHBOARD) -->
<div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(216, 63, 103, 0.25); background: rgba(255, 255, 255, 0.95);">
      <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Owner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 pb-4 pt-3">
        <p class="text-muted small mb-4" style="line-height: 1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat. Data yang diubah akan langsung disinkronkan ke seluruh sistem harian SpotLight.</p>
        <form method="POST" enctype="multipart/form-data">
          <div class="text-center mb-4">
            <div class="d-inline-block position-relative">
              <div class="profile-preview-box mx-auto">
                <img id="profile-preview-modal" src="<?= $foto_owner_src ?>" alt="Foto Profil">
              </div>
              <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
              <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom: -10px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 0.75rem; padding: 5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label>
            <input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
            <input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Alamat Email<span class="required-star">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_owner) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
            <input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: 08xxxxxxxxxx" value="<?= htmlspecialchars($d_profile['no_hp']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Alamat Lengkap<span class="required-star">*</span></label>
            <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize: none;"><?= htmlspecialchars($d_profile['alamat']) ?></textarea>
          </div>

          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">Sandi Baru (Opsional)</label>
                  <div class="password-group">
                      <input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter">
                      <i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i>
                  </div>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Konfirmasi Sandi</label>
                  <div class="password-group">
                      <input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru">
                      <i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i>
                  </div>
              </div>
          </div>

          <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan ✨</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL DETAIL STAF (MENDUKUNG PREVIEW FOTO PROFIL) -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #ffffff;">
            <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Detail Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <div class="text-center mb-4">
                    <div class="profile-preview-box mx-auto" style="width: 100px; height: 100px; border: 3px solid var(--s-pink);">
                        <img id="d_foto" src="" alt="Foto Karyawan">
                    </div>
                    <h5 class="fw-bold text-dark mt-3 mb-1" id="d_nama"></h5>
                    <span class="badge bg-danger px-3 py-1 text-white text-uppercase" id="d_role" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;"></span>
                </div>
                <div class="card-3d p-3 border-0 mb-4" style="border-radius: 20px; background-color: #f8fafc; box-shadow: none;">
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">NIK</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_nik"></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Umur</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_umur"></span>
                        </div>
                        <div class="col-6 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Jenis Kelamin</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_jk"></span>
                        </div>
                        <div class="col-6 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Telepon</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_hp"></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Email</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_email"></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Alamat Lengkap</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_alamat"></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Status Staf</small>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;" id="d_status"></span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-reg shadow-sm py-3 mt-0" data-bs-dismiss="modal" style="border-radius: 14px;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
    // JAM REAL-TIME SINKRON FORMAT INDONESIA
    function updateLiveClock() {
        const now = new Date();
        const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
        const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        
        const dayName = days[now.getDay()];
        const day = now.getDate();
        const monthName = months[now.getMonth()];
        const year = now.getFullYear();
        
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        const timeString = `${dayName}, ${day} ${monthName} ${year} - ${hours}:${minutes}:${seconds} WIB`;
        const clockEl = document.getElementById('live-clock');
        if (clockEl) clockEl.innerText = timeString;
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    // TOGGLE SUBMENU
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
                
                if (!isShown) {
                    targetEl.classList.add('show');
                    if (chevron) chevron.style.transform = 'rotate(180deg)';
                }
            }
        });
    });

    // PROFIL OWNER MODALS
    function bukaModalProfil() {
        var modalProfil = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
        modalProfil.show();
    }

    function bukaModalBiodata() {
        var modalBiodata = new bootstrap.Modal(document.getElementById('modalLihatBiodata'));
        modalBiodata.show();
    }

    function bukaModalEditDariBiodata() {
        var modalBiodata = bootstrap.Modal.getInstance(document.getElementById('modalLihatBiodata'));
        if (modalBiodata) modalBiodata.hide();
        setTimeout(bukaModalProfil, 400);
    }

    // DETAIL MODAL STAF (MENDUKUNG LINK FOTO PROFIL)
    function bukaDetailRow(element) {
        const d = element.dataset;
        document.getElementById('d_nama').textContent = d.nama;
        document.getElementById('d_nik').textContent = d.nik;
        document.getElementById('d_role').textContent = d.role;
        document.getElementById('d_role').className = 'badge px-3 py-1 text-white text-uppercase';
        
        if (d.role === 'Admin') {
            document.getElementById('d_role').className = 'badge bg-primary px-3 py-1 text-white text-uppercase';
        } else if (d.role === 'Fotografer') {
            document.getElementById('d_role').className = 'badge bg-info px-3 py-1 text-white text-uppercase';
        } else if (d.role === 'Owner') {
            document.getElementById('d_role').className = 'badge bg-dark px-3 py-1 text-white text-uppercase';
        }
        
        document.getElementById('d_umur').textContent = d.umur;
        document.getElementById('d_jk').textContent = d.jk;
        document.getElementById('d_hp').textContent = d.hp;
        document.getElementById('d_email').textContent = d.email;
        document.getElementById('d_alamat').textContent = d.alamat;
        document.getElementById('d_status').textContent = d.status;
        document.getElementById('d_foto').src = d.foto;
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // SWEETALERT FUNCTIONS & ACTIONS
    function confirmSoftDelete(id, nama) {
        Swal.fire({ 
            title: 'Arsipkan Karyawan? 📦', 
            text: '"' + nama + '" akan diarsipkan dan dinonaktifkan sementara dari sistem.', 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#d83f67', 
            cancelButtonColor: '#718096', 
            confirmButtonText: 'Ya, Arsipkan', 
            cancelButtonText: 'Batal' 
        }).then(r => { 
            if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=soft_delete&id=' + id; 
        });
    }
    
    function confirmRestore(id, nama) {
        Swal.fire({ 
            title: 'Pulihkan Data? 🟢', 
            text: '"' + nama + '" akan dikembalikan ke daftar aktif dengan status diaktifkan kembali.', 
            icon: 'info', 
            showCancelButton: true, 
            confirmButtonColor: '#059669', 
            cancelButtonColor: '#718096', 
            confirmButtonText: 'Ya, Pulihkan', 
            cancelButtonText: 'Batal' 
        }).then(r => { 
            if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=restore&id=' + id; 
        });
    }
    
    function confirmHardDelete(id, nama) {
        Swal.fire({ 
            title: 'Hapus Permanen? ❌', 
            text: '"' + nama + '" akan dihapus secara PERMANEN dari database!', 
            icon: 'error', 
            showCancelButton: true, 
            confirmButtonColor: '#dc2626', 
            cancelButtonColor: '#718096', 
            confirmButtonText: 'Ya, Hapus', 
            cancelButtonText: 'Batal', 
            input: 'text', 
            inputPlaceholder: 'Ketik "HAPUS" untuk konfirmasi', 
            inputValidator: v => v !== 'HAPUS' ? 'Ketik "HAPUS" untuk mengonfirmasi!' : null 
        }).then(r => { 
            if (r.isConfirmed) window.location = 'action_karyawan.php?aksi=hard_delete&id=' + id; 
        });
    }
    
    function confirmLogout(e) { 
        e.preventDefault(); 
        Swal.fire({ 
            title: 'Keluar Sistem? ❌', 
            text: 'Yakin ingin keluar dari sistem SpotLight Studio?', 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#d83f67', 
            cancelButtonColor: '#718096', 
            confirmButtonText: 'Ya, Keluar', 
            cancelButtonText: 'Batal' 
        }).then(r => { 
            if (r.isConfirmed) window.location = '../../logout.php'; 
        }); 
    }
    
    function confirmLandingPage(e) { 
        e.preventDefault(); 
        Swal.fire({ 
            title: 'Kembali ke Beranda? ✦', 
            text: 'Anda akan dialihkan kembali ke halaman utama publik SpotLight Studio.', 
            icon: 'info', 
            showCancelButton: true, 
            confirmButtonColor: '#d83f67', 
            cancelButtonColor: '#718096', 
            confirmButtonText: 'Ya, Kembali', 
            cancelButtonText: 'Batal' 
        }).then(r => { 
            if (r.isConfirmed) window.location = '../../index.php'; 
        }); 
    }

    // LIVE PREVIEW FOTO PROFIL MODAL
    const inputFotoModal = document.getElementById('inputFotoModal');
    if (inputFotoModal) {
        inputFotoModal.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profile-preview-modal').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // VALIDASI INPUT TEXT
    const inputNamaModal = document.getElementById('inputNamaModal');
    if (inputNamaModal) {
        inputNamaModal.addEventListener('input', function() {
            this.value = this.value.replace(/a-zA-Z ]/g, '');
        });
    }

    const inputUsernameModal = document.getElementById('inputUsernameModal');
    if (inputUsernameModal) {
        inputUsernameModal.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    }

    // TOGGLE PASSWORD EYE VISIBILITY
    function setupPasswordToggle(buttonId, inputId) {
        const btn = document.getElementById(buttonId);
        const input = document.getElementById(inputId);
        if (btn && input) {
            btn.addEventListener('click', function () {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('bi-eye'); this.classList.toggle('bi-eye-slash');
            });
        }
    }
    setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
    setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');

    // TELEPHONE MASKING
    const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62 ';
    function moveCursorToEnd() { if (inputHPModal.selectionStart < prefix.length) { if (inputHPModal.setSelectionRange) inputHPModal.setSelectionRange(prefix.length, prefix.length); } }
    if (inputHPModal) {
        inputHPModal.addEventListener('mousedown', () => setTimeout(moveCursorToEnd, 1));
        inputHPModal.addEventListener('focus', moveCursorToEnd);
        inputHPModal.addEventListener('keyup', moveCursorToEnd);
        inputHPModal.addEventListener('keydown', function(e) { if (this.selectionStart <= prefix.length && (e.keyCode === 8 || e.keyCode === 46)) { e.preventDefault(); } });
        inputHPModal.addEventListener('input', function() {
            if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
            let digits = this.value.split(prefix)[1].replace(/[^0-9]/g, '');
            if (digits.length > 13) digits = digits.slice(0, 13);
            this.value = prefix + digits;
        });
    }
</script>

<!-- SWEETALERT INTEGRASI NOTIFIKASI HALAMAN -->
<?php if(isset($_GET['status_sukses'])): ?>
<script>
    const s = "<?= $_GET['status_sukses'] ?>";
    let msg = "", t = "success", title = "Berhasil!";
    if (s === 'tambah') msg = "Karyawan baru berhasil didaftarkan!";
    else if (s === 'edit') msg = "Data berhasil diperbarui!";
    else if (s === 'soft_delete') { msg = "Karyawan berhasil diarsipkan."; title = "Diarsipkan!"; }
    else if (s === 'restore') { msg = "Karyawan berhasil dipulihkan!"; title = "Dipulihkan!"; }
    else if (s === 'hard_delete') { msg = "Karyawan dihapus permanen."; title = "Dihapus!"; t = "warning"; }
    else if (s === 'error_relasi') { msg = "Tidak bisa hapus! Masih ada data transaksi terkait."; title = "Gagal!"; t = "error"; }
    else if (s === 'error_self') { msg = "Anda tidak bisa mengarsipkan atau menghapus akun sendiri!"; title = "Ditolak!"; t = "error"; }
    else if (s === 'error_general') { msg = "Terjadi kesalahan. Silakan coba kembali."; title = "Error!"; t = "error"; }
    Swal.fire({ icon: t, title: title, text: msg, confirmButtonColor: '#d83f67' });
</script>
<?php endif; ?>

<?php if(isset($success_profile) && $success_profile === true): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Profil Diperbarui! 🎉',
        text: 'Informasi profil Anda berhasil disinkronkan ke seluruh sistem SpotLight.',
        confirmButtonColor: '#d83f67',
        confirmButtonText: 'Selesai'
    });
</script>
<?php endif; ?>

<?php if(isset($error_profile) && $error_profile !== ""): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Pembaruan Gagal! ❌',
        text: '<?= $error_profile ?>',
        confirmButtonColor: '#d83f67',
        confirmButtonText: 'Periksa Kembali'
    }).then(() => {
        var modalGanti = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
        modalGanti.show();
    });
</script>
<?php endif; ?>

</body>
</html>