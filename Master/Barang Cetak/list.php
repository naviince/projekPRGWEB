<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// =====================================================
// HELPER FUNCTIONS - Safe SQLSRV (Anti-Crash)
// =====================================================
function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[SpotLight] SQL Error: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

// =====================================================
// AMBIL PROFIL ADMIN (Sinkron penuh)
// =====================================================
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// *Penyesuaian: Menggunakan SVG berlatar pink lembut dan ikon gambar beraksen merah muda tajam sebagai fallback produk cetak yang tidak pecah
$default_svg_item = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='100%25' height='100%25' fill='%23FFF0F3'/%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z' fill='%23D53D66' transform='scale(0.8) translate(3, 3)'/%3E%3C/svg%3e";

$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }

$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$username_admin = $d_admin['username_karyawan'] ?? 'admin';
$email_admin = $d_admin['email_karyawan'] ?? 'admin@spotlight.com';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;

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
    $hp_bersih_input = str_replace(['+', ' '], '', $no_hp_input);

    if (empty($nama_input) || !preg_match("/^[a-zA-Z ]*$/", $nama_input)) {
        $error_profile = "Nama lengkap hanya boleh berisi huruf!";
    } elseif (empty($username_input) || !preg_match("/^[a-zA-Z0-9_]*$/", $username_input)) {
        $error_profile = "Nama pengguna tidak valid!";
    } elseif (empty($email_input) || !filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error_profile = "Email tidak valid!";
    } elseif (empty($no_hp_input) || substr($no_hp_input, 0, 3) !== '+62' || !ctype_digit($hp_bersih_input) || strlen($no_hp_input) < 12 || strlen($no_hp_input) > 16) {
        $error_profile = "Nomor telepon tidak valid! Harus diawali dengan +62, berisi angka, dan panjang total 12-16 karakter.";
    } elseif (empty($alamat_input) || strlen($alamat_input) < 10) {
        $error_profile = "Alamat lengkap minimal harus 10 karakter!";
    } else {
        $sandi_final = $d_admin['password_karyawan'];
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
            $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_input, $username_input, $no_hp_input, $id_admin));
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
            $foto_baru = $foto_admin;
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
                    $foto_baru = "admin_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/karyawan/";
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    if (move_uploaded_file($file_tmp, $target_dir . $foto_baru)) {
                        if ($foto_admin != 'default.jpg' && file_exists($target_dir . $foto_admin)) { unlink($target_dir . $foto_admin); }
                    } else {
                        $error_profile = "Gagal mengunggah foto profil!";
                    }
                }
            }
            if ($error_profile == "") {
                $sql_upd = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $stmt_upd = sqlsrv_query($conn, $sql_upd, array($nama_input, $username_input, $email_input, $sandi_final, $no_hp_input, $alamat_input, $foto_baru, $username_admin, $id_admin));
                if ($stmt_upd) {
                    $success_profile = true;
                    $nama_admin = $nama_input;
                    $username_admin = $username_input;
                    $email_admin = $email_input;
                    $foto_admin = $foto_baru;
                    $foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
                        ? "../../assets/img/karyawan/" . $foto_admin : $default_svg_avatar;
                    $d_admin['no_hp'] = $no_hp_input;
                    $d_admin['alamat'] = $alamat_input;
                } else {
                    $error_profile = "Gagal memperbarui data di database!";
                }
            }
        }
    }
}

// =====================================================
// PAGINATION & FILTER
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$stok_filter = isset($_GET['stok']) ? trim($_GET['stok']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

// =====================================================
// FILTER TAB DATA (BARU - KUNCI PENTING!)
// =====================================================
$tab_filter = isset($_GET['tab']) ? trim($_GET['tab']) : 'aktif'; // aktif | dihapus | semua

// =====================================================
// QUERY STATISTIK (UPDATE - Menambahkan pembungkus ISNULL demi kestabilan data saat tabel kosong)
// =====================================================
$stats = safe_sqlsrv_fetch($conn, 
    "SELECT 
        COUNT(*) as total,
        ISNULL(SUM(CASE WHEN Status = 1 AND Is_Deleted = 0 THEN 1 ELSE 0 END), 0) as aktif,
        ISNULL(SUM(CASE WHEN Status = 0 AND Is_Deleted = 0 THEN 1 ELSE 0 END), 0) as nonaktif,
        ISNULL(SUM(CASE WHEN Stok_Barang <= Stok_Minimum AND Status = 1 AND Is_Deleted = 0 THEN 1 ELSE 0 END), 0) as stok_menipis,
        ISNULL(SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END), 0) as dihapus
    FROM Barang_Cetak"
) ?? ['total' => 0, 'aktif' => 0, 'nonaktif' => 0, 'stok_menipis' => 0, 'dihapus' => 0];

// =====================================================
// QUERY LIST DATA DENGAN FILTER & TAB
// =====================================================
$conditions = [];
$params = [];

// KUNCI: Filter tab menentukan kondisi Is_Deleted
if ($tab_filter === 'aktif') {
    $conditions[] = "Is_Deleted = 0";
} elseif ($tab_filter === 'dihapus') {
    $conditions[] = "Is_Deleted = 1";
} else {
    $conditions[] = "Is_Deleted IN (0, 1)";
}

if (!empty($cari)) {
    $conditions[] = "(Nama_Barang LIKE ? OR Deskripsi LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
}
if ($status_filter !== "") {
    $conditions[] = "Status = ?";
    $params[] = (int)$status_filter;
}
if ($stok_filter === "menipis") {
    $conditions[] = "Stok_Barang <= Stok_Minimum AND Status = 1";
}

$order_clause = "Nama_Barang ASC";
if ($sort == "nama_desc") { $order_clause = "Nama_Barang DESC"; }
elseif ($sort == "harga_asc") { $order_clause = "Harga_Barang ASC"; }
elseif ($sort == "harga_desc") { $order_clause = "Harga_Barang DESC"; }
elseif ($sort == "stok_asc") { $order_clause = "Stok_Barang ASC"; }
elseif ($sort == "stok_desc") { $order_clause = "Stok_Barang DESC"; }

// Hitung total untuk pagination
$count_sql = "SELECT COUNT(*) AS total FROM Barang_Cetak WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $count_sql, $params);
$total_halaman = ceil($total_records / $limit);

// Ambil data
$list_sql = "SELECT 
    ID_Barang,
    Nama_Barang,
    Deskripsi,
    Harga_Barang,
    Stok_Barang,
    Stok_Minimum,
    Foto_Barang,
    Status,
    Is_Deleted
FROM Barang_Cetak
WHERE " . implode(" AND ", $conditions) . "
ORDER BY " . $order_clause . "
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_list = array_merge($params, [$offset, $limit]);
$daftar_barang = safe_sqlsrv_fetch_all($conn, $list_sql, $params_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Barang Cetak – SpotLight Studio</title>
    <link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --p-pink: #D53D66;
            --d-pink: #CA3366;
            --s-pink: #FFF0F3;
            --light-pink: #FFE4E9;
            --accent-pink: #E85D84;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --sidebar-bg: #ffffff;
            --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            border-right: 1px solid rgba(255, 228, 233, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 30px 20px;
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem;
            color: var(--p-pink); text-decoration: none;
            letter-spacing: -1px; margin-bottom: 40px; display: block;
        }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; color: #4a5568; font-weight: 700;
            text-decoration: none; border-radius: 12px; font-size: 0.9rem;
            transition: var(--transition-3d);
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background-color: var(--light-pink); color: var(--p-pink);
            transform: translateX(4px);
        }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link {
            display: flex; align-items: center; padding: 8px 18px;
            color: #718096; font-weight: 600; font-size: 0.85rem;
            text-decoration: none; border-radius: 10px; transition: 0.3s;
        }
        .submenu-link:hover, .submenu-link.active {
            color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px;
        }
        .btn-logout {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border: none; width: 100%; padding: 12px;
            border-radius: 12px; font-weight: 800; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 35px;
        }
        .profile-header-btn {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden;
            border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff;
        }
        .profile-header-btn:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15);
            border-color: var(--p-pink);
        }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* STAT CARDS */
        .stats-scroll-wrapper {
            width: 100%; overflow-x: auto; overflow-y: hidden;
            padding-bottom: 10px; margin-bottom: 20px;
            scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 16px; min-width: max-content; }
        .stat-card-item { min-width: 220px; max-width: 280px; flex: 0 0 auto; }
        /* card-3d = struktur visual dasar (dipakai statistik & info non-klik).
           card-3d-clickable = modifier opt-in untuk elemen yang benar-benar bisa diklik,
           supaya efek hover-lift hanya muncul saat memang ada aksi. */
        .card-3d {
            background: #ffffff; border-radius: 22px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
            padding: 20px; height: 100%; position: relative; overflow: hidden;
        }
        .card-3d-clickable {
            transition: var(--transition-3d);
            cursor: pointer;
        }
        .card-3d-clickable::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
            opacity: 0; transition: opacity 0.3s ease;
        }
        .card-3d-clickable:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 18px 40px rgba(213, 61, 102, 0.12);
            border-color: var(--p-pink);
        }
        .card-3d-clickable:hover::before { opacity: 1; }
        .stat-card { display: flex; align-items: center; gap: 14px; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0;
        }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-icon-orange { background: linear-gradient(135deg, #fff7ed, #fed7aa); color: #ea580c; }
        .stat-icon-gray { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #6b7280; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }

        /* SEARCH & FILTER */
        .search-filter-bar {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 25px; flex-wrap: wrap;
        }
        .search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 300px; }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; }
        .search-input-main {
            width: 100%; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem;
            color: #1e293b; transition: var(--transition-3d); background: #ffffff;
        }
        .search-input-main:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
        /* Filter: gaya outline ringan (sinkron dengan tombol "Verifikasi Semua" di dashboard),
           supaya tidak terlihat sama seperti tombol solid "Tambah Barang". */
        .btn-filter-modal {
            background: var(--s-pink);
            color: var(--p-pink);
            border: 1.5px solid var(--light-pink);
            border-radius: 14px;
            padding: 12px 24px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: var(--transition-3d);
            white-space: nowrap;
        }
        .btn-filter-modal:hover {
            background: var(--light-pink);
            border-color: var(--p-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(213, 61, 102, 0.2);
        }
        .btn-search-icon {
            background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px;
            padding: 12px 16px; color: #94a3b8; cursor: pointer; transition: var(--transition-3d);
            display: flex; align-items: center; justify-content: center;
        }
        .btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .btn-reg-header {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; border-radius: 14px !important;
            padding: 12px 28px !important; font-weight: 800 !important;
            border: none !important; box-shadow: 0 8px 20px rgba(213, 61, 102, 0.25) !important;
            transition: var(--transition-3d) !important; display: inline-flex;
            align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-reg-header:hover {
            background: linear-gradient(135deg, #E85D84, var(--p-pink)) !important;
            transform: translateY(-4px) scale(1.03) !important;
            box-shadow: 0 12px 25px rgba(213, 61, 102, 0.4) !important;
        }

        /* TABEL */
        .table-scroll-wrapper {
            width: 100%; overflow-x: auto; overflow-y: hidden;
            border-radius: 20px; scrollbar-width: thin;
            scrollbar-color: var(--p-pink) #f1f5f9;
        }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .data-table {
            width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0;
        }
        .data-table thead th {
            background: #ffffff; padding: 16px 20px;
            font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #94a3b8; white-space: nowrap;
            border: none; border-bottom: 2px solid #f1f5f9; text-align: left;
        }
        .data-table thead th:first-child { padding-left: 24px; }
        .data-table thead th:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody td {
            padding: 16px 20px; border: none;
            border-bottom: 1px solid #f1f5f9; vertical-align: middle; white-space: nowrap;
        }
        .data-table tbody td:first-child { padding-left: 24px; }
        .data-table tbody td:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr:nth-child(even) { background-color: #FFF8F0; }
        .data-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .data-table tbody tr:hover { background-color: #fff8f9 !important; transform: translateX(2px); }

        .brg-preview {
            width: 55px; height: 55px; object-fit: cover;
            border-radius: 14px; border: 2px solid var(--light-pink);
            transition: var(--transition-3d); flex-shrink: 0;
        }
        .data-table tbody tr:hover .brg-preview { transform: scale(1.08) rotate(2deg); }

        .td-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .td-deskripsi { font-size: 0.8rem; color: #718096; max-width: 200px; white-space: normal; }
        .td-harga { font-weight: 800; color: #dc2626; font-size: 0.9rem; }
        .td-stok { font-weight: 700; font-size: 0.9rem; }
        .td-stok.menipis { color: #dc2626; }
        .td-stok.aman { color: #059669; }
        .stok-minimum { font-size: 0.75rem; color: #a0aec0; font-weight: 600; }
        .stok-minimum.menipis { color: #dc2626; font-weight: 700; }

        .badge-status {
            font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
            border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-aktif { background: #ecfdf5; color: #059669; }
        .badge-nonaktif { background: #fef2f2; color: #dc2626; }
        .badge-deleted { background: #f3f4f6; color: #6b7280; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-aktif .badge-dot { background: #059669; }
        .badge-nonaktif .badge-dot { background: #dc2626; }
        .badge-deleted .badge-dot { background: #6b7280; }

        .btn-action-circle {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            transition: var(--transition-3d); border: 1.5px solid #eef2f6;
            background: #ffffff; font-size: 0.85rem; text-decoration: none;
            margin: 0 2px; cursor: pointer;
        }
        .btn-action-edit { color: var(--p-pink); border-color: #FFE4E9; }
        .btn-action-edit:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); }
        .btn-action-restore { color: #059669; border-color: #d1fae5; }
        .btn-action-restore:hover { background: #059669; color: #ffffff; transform: translateY(-2px); }
        .btn-action-hard { color: #7f1d1d; border-color: #fee2e2; }
        .btn-action-hard:hover { background: #7f1d1d; color: #ffffff; transform: translateY(-2px); }

        /* PAGINATION */
        .pagination-wrapper {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 30px; padding: 20px 24px;
            background: #ffffff; border-radius: 20px;
            border: 1px solid rgba(255, 228, 233, 0.8);
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.04);
        }
        .pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; }
        .page-link-pag {
            display: flex; align-items: center; justify-content: center;
            min-width: 40px; height: 40px; padding: 0 14px;
            border-radius: 12px; background: #ffffff;
            border: 2px solid #FFF5F7; color: #4a5568;
            font-weight: 700; font-size: 0.9rem; text-decoration: none;
            transition: var(--transition-3d);
        }
        .page-link-pag:hover {
            background: var(--light-pink); border-color: var(--p-pink); color: var(--p-pink);
            transform: translateY(-2px);
        }
        .page-link-pag.active-pag {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important;
            color: #ffffff !important; border-color: var(--p-pink) !important;
            box-shadow: 0 4px 12px rgba(213, 61, 102, 0.3);
        }
        .page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

        /* TAB FILTER BARU */
        .tab-filter-wrapper {
            display: flex; gap: 8px; margin-bottom: 20px;
        }
        .tab-filter-btn {
            padding: 10px 24px; border-radius: 12px;
            font-weight: 700; font-size: 0.85rem;
            border: 2px solid #e2e8f0; background: #ffffff;
            color: #718096; text-decoration: none;
            transition: var(--transition-3d); cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .tab-filter-btn:hover {
            border-color: var(--p-pink); color: var(--p-pink);
            transform: translateY(-2px);
        }
        .tab-filter-btn.active {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff; border-color: var(--p-pink);
            box-shadow: 0 4px 15px rgba(213, 61, 102, 0.2);
        }
        .tab-filter-btn .tab-badge {
            background: rgba(255,255,255,0.2); color: inherit;
            padding: 2px 10px; border-radius: 50px;
            font-size: 0.75rem; font-weight: 800;
        }
        .tab-filter-btn.active .tab-badge {
            background: rgba(255,255,255,0.3); color: #ffffff;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }


        /* PROFIL: modal biodata & edit profil */
        .required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
        .form-label { font-weight: 800; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        .form-control, .form-select {
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6;
            background: #f8fafc; font-size: 14px; font-weight: 600; transition: var(--transition-3d); color: var(--text-dark);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--p-pink); background: #ffffff;
            transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); outline: none;
        }
        .profile-preview-box {
            width: 90px; height: 90px; border-radius: 50%; overflow: hidden;
            border: 2.5px solid #eef2f6; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition: var(--transition-3d);
        }
        .profile-preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .btn-pilih-foto {
            background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink);
            font-weight: 700; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem; transition: var(--transition-3d);
        }
        .btn-pilih-foto:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.15); }
        .btn-reg {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; border-radius: 16px;
            padding: 16px; font-weight: 800; border: none; width: 100%; transition: var(--transition-3d);
            margin-top: 15px; font-size: 15px; box-shadow: 0 10px 25px rgba(213, 61, 102, 0.25);
        }
        .btn-reg:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.35); }
        .password-group { position: relative; transition: var(--transition-3d); border-radius: 14px; }
        .password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(213, 61, 102, 0.15); }
        .password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
        .password-group .form-control:focus { transform: none!important; box-shadow: none!important; background: #ffffff; border-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        /* =====================================================
           RESPONSIVE ENHANCEMENTS
           ===================================================== */
        .mobile-menu-btn {
            display: none; width: 44px; height: 44px; border-radius: 12px;
            background: #ffffff; border: 2px solid var(--light-pink); color: var(--p-pink);
            align-items: center; justify-content: center; font-size: 1.4rem; cursor: pointer;
            transition: var(--transition-3d); flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .mobile-menu-btn:hover { background: var(--s-pink); transform: scale(1.05); }

        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(30, 30, 36, 0.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 99; opacity: 0; transition: opacity 0.35s ease;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        @media (max-width: 1199px) {
            .stats-row { gap: 12px; }
            .stat-card-item { min-width: 200px; }
        }

        @media (max-width: 992px) {
            .mobile-menu-btn { display: inline-flex; }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                box-shadow: none;
            }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: 10px 0 50px rgba(0,0,0,0.15); }
            .main-content { margin-left: 0; padding: 24px; }
            .dashboard-header { flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
            .dashboard-header h3 { font-size: 1.35rem; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 18px; }
            .dashboard-header { margin-bottom: 22px; }
            .dashboard-header h3 { font-size: 1.15rem; }
            .dashboard-header p { font-size: 0.8rem; }

            .search-filter-bar { flex-direction: column; align-items: stretch; gap: 10px; }
            .search-form-flex { min-width: 100%; flex-wrap: wrap; }
            .search-input-wrapper { width: 100%; }
            .btn-reg-header { width: 100%; justify-content: center; }
            .tab-filter-wrapper { flex-wrap: wrap; }
            .tab-filter-btn { flex: 1; justify-content: center; }
            .pagination-wrapper { flex-direction: column; gap: 12px; padding: 16px; }
            .pagination-nav { justify-content: center; flex-wrap: wrap; }
            .stat-card-item { min-width: 170px; }
        }

        @media (max-width: 576px) {
            .main-content { padding: 14px; }
            .dashboard-header h3 { font-size: 1.05rem; }

            .data-table tbody td { padding: 12px 14px; }
            .data-table tbody td:first-child { padding-left: 16px; border-radius: 10px 0 0 10px; }
            .data-table tbody td:last-child { padding-right: 16px; border-radius: 0 10px 10px 0; }
            .brg-preview { width: 48px; height: 48px; border-radius: 10px; }
            .td-nama { font-size: 0.85rem; }
            .td-deskripsi { font-size: 0.75rem; }
            .td-harga { font-size: 0.85rem; }
            .badge-status { font-size: 0.65rem; padding: 5px 10px; }
            .btn-action-circle { width: 32px; height: 32px; font-size: 0.8rem; margin: 0 2px; }
            .page-link-pag { min-width: 36px; height: 36px; padding: 0 10px; font-size: 0.85rem; }
            .stat-val { font-size: 1.25rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.2rem; }
            .modal-dialog { margin: 12px; }
            .modal-content { border-radius: 20px !important; }
        }

        @media (max-width: 375px) {
            .dashboard-header h3 { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">
                SpotLight.<br><span>Panel Administrator</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="../../Role/Admin/index.php" class="nav-link-custom">
                        <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuTransaksi">
                        <span><i class="bi bi-cart-fill me-2"></i> Transaksi</span>
                        <i class="bi bi-chevron-down small icon-chevron"></i>
                    </a>
                    <div class="submenu" id="submenuTransaksi">
                        <ul class="list-unstyled">
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <div class="dashboard-header fade-in-up">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Master Barang Cetak</h3>
                    <p class="text-muted small mb-0">Kelola katalog produk cetak dan ketersediaan stok.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- BREADCRUMB -->
        <div class="breadcrumb-custom" style="display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: 0.85rem; font-weight: 600;">
            <a href="../../Role/Admin/index.php" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right" style="color: #cbd5e1; font-size: 0.7rem;"></i>
            <a href="./list.php" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;">Data Master</a>
            <i class="bi bi-chevron-right" style="color: #cbd5e1; font-size: 0.7rem;"></i>
            <span class="active" style="color: var(--p-pink);">Barang Cetak</span>
        </div>

        <!-- STATISTIK CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-box-seam"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Produk</div>
                                <div class="stat-val"><?= $stats['total'] ?? 0 ?> Produk</div>
                                <div class="stat-subtitle">Tersedia di sistem</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Produk ...</div>
                                <div class="stat-val"><?= $stats['aktif'] ?? 0 ?> Produk</div>
                                <div class="stat-subtitle">Tampil ke pelanggan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Stok Menipis</div>
                                <div class="stat-val"><?= $stats['stok_menipis'] ?? 0 ?> Produk</div>
                                <div class="stat-subtitle">Perlu restock segera</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-x-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Produk Nonaktif</div>
                                <div class="stat-val"><?= $stats['nonaktif'] ?? 0 ?> Produk</div>
                                <div class="stat-subtitle">Disembunyikan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-gray"><i class="bi bi-trash-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Produk Dihapus</div>
                                <div class="stat-val"><?= $stats['dihapus'] ?? 0 ?> Produk</div>
                                <div class="stat-subtitle">Soft delete</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="tab" id="hiddenTab" value="<?= htmlspecialchars($tab_filter) ?>">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="stok" id="hiddenStok" value="<?= htmlspecialchars($stok_filter) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="cari" class="search-input-main" placeholder="Cari nama barang atau deskripsi..." value="<?= htmlspecialchars($cari) ?>">
                </div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()">
                    <i class="bi bi-funnel-fill me-2"></i>Filter
                    <i class="bi bi-chevron-down ms-2"></i>
                </button>
                <button type="submit" class="btn-search-icon" title="Cari">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            <a href="add.php" class="btn-reg-header text-decoration-none">
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Barang
            </a>
        </div>

        <!-- TAB FILTER (BARU - KUNCI PENTING!) -->
        <div class="tab-filter-wrapper">
            <a href="list.php?tab=aktif<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($stok_filter) ? '&stok='.$stok_filter : '' ?><?= !empty($sort) ? '&sort='.$sort : '' ?>" 
               class="tab-filter-btn <?= $tab_filter === 'aktif' ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill"></i> Data ...
                <span class="tab-badge"><?= ($stats['aktif'] ?? 0) + ($stats['nonaktif'] ?? 0) ?></span>
            </a>
            <a href="list.php?tab=dihapus<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($stok_filter) ? '&stok='.$stok_filter : '' ?><?= !empty($sort) ? '&sort='.$sort : '' ?>" 
               class="tab-filter-btn <?= $tab_filter === 'dihapus' ? 'active' : '' ?>">
                <i class="bi bi-trash-fill"></i> Sudah Dihapus
                <span class="tab-badge"><?= $stats['dihapus'] ?? 0 ?></span>
            </a>
            <a href="list.php?tab=semua<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?><?= !empty($status_filter) ? '&status='.$status_filter : '' ?><?= !empty($stok_filter) ? '&stok='.$stok_filter : '' ?><?= !empty($sort) ? '&sort='.$sort : '' ?>" 
               class="tab-filter-btn <?= $tab_filter === 'semua' ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i> Semua Data
                <span class="tab-badge"><?= $stats['total'] ?? 0 ?></span>
            </a>
        </div>

        <!-- INFO ALERT -->
        <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px; background: #f8fafc;">
            <i class="bi bi-info-circle-fill me-2 text-info"></i>
            <span class="small fw-bold text-muted">
                <strong>Info:</strong> Barang cetak ditampilkan ke pelanggan saat booking. 
                Stok menipis = stok <= minimum. Kelola stok di menu <a href="../../Transaksi/Penjualan/list.php" style="color: var(--p-pink);">Penjualan Barang</a>.
            </span>
        </div>

        <!-- TABEL DATA -->
        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama Produk</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($daftar_barang)):
                            foreach($daftar_barang as $idx => $b):
                                // KUNCI: Path foto harus sinkron dengan add.php/edit.php
                                $path_img = "../../uploads/barang/" . ($b['Foto_Barang'] ?? '');
                                $img_src = (!empty($b['Foto_Barang']) && $b['Foto_Barang'] !== 'default_barang.jpg' && file_exists($path_img))
                                    ? $path_img 
                                    : $default_svg_item;

                                $low_stok = ($b['Stok_Barang'] <= $b['Stok_Minimum']);

                                // KUNCI: Status badge sesuai Is_Deleted and Status
                                if ($b['Is_Deleted'] == 1) {
                                    $badge_status = "badge-deleted";
                                    $text_status = "Dihapus";
                                } else {
                                    $badge_status = ($b['Status'] == 1) ? "badge-aktif" : "badge-nonaktif";
                                    $text_status = ($b['Status'] == 1) ? "Aktif" : "Nonaktif";
                                }
                        ?>
                            <tr class="fade-in-up">
                                <td><img src="<?= $img_src ?>" class="brg-preview" alt="<?= htmlspecialchars($b['Nama_Barang']) ?>"></td>
                                <td>
                                    <div class="td-nama"><?= htmlspecialchars($b['Nama_Barang']) ?></div>
                                    <div class="td-deskripsi"><?= htmlspecialchars(substr($b['Deskripsi'] ?? '-', 0, 35)) ?><?= strlen($b['Deskripsi'] ?? '') > 35 ? '...' : '' ?></div>
                                </td>
                                <td class="td-harga">Rp <?= number_format($b['Harga_Barang'], 0, ',', '.') ?></td>
                                <td>
                                    <div class="td-stok <?= $low_stok ? 'menipis' : 'aman' ?>">
                                        <?= $b['Stok_Barang'] ?> Unit
                                        <?php if ($low_stok): ?>
                                            <span class="badge bg-danger ms-1" style="font-size: 0.65rem; padding: 3px 8px;">Menipis</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="stok-minimum <?= $low_stok ? 'menipis' : '' ?>">Min: <?= $b['Stok_Minimum'] ?> unit</div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $badge_status ?>">
                                        <span class="badge-dot"></span>
                                        <?= $text_status ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($b['Is_Deleted'] == 0): ?>
                                        <!-- Data ...: Edit, Toggle, Soft Delete -->
                                        <a href="edit.php?id=<?= $b['ID_Barang'] ?>" class="btn-action-circle btn-action-edit" title="Edit Barang">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn-action-circle btn-action-delete" onclick="toggleStatus(<?= $b['ID_Barang'] ?>, <?= $b['Status'] ?>, '<?= htmlspecialchars($b['Nama_Barang']) ?>')" title="Toggle Status">
                                            <i class="bi bi-toggle-<?= $b['Status'] == 1 ? 'on' : 'off' ?>"></i>
                                        </button>
                                        <button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $b['ID_Barang'] ?>, '<?= htmlspecialchars($b['Nama_Barang']) ?>')" title="Hapus Barang (Soft Delete)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- Data Dihapus: Restore, Hard Delete -->
                                        <button class="btn-action-circle btn-action-restore" onclick="restoreBarang(<?= $b['ID_Barang'] ?>, '<?= htmlspecialchars($b['Nama_Barang']) ?>')" title="Pulihkan Barang">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <button class="btn-action-circle btn-action-hard" onclick="hardDelete(<?= $b['ID_Barang'] ?>, '<?= htmlspecialchars($b['Nama_Barang']) ?>')" title="Hapus Permanen (Hard Delete)">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i>
                                    <p class="fw-bold">Tidak ada data barang cetak yang sesuai.</p>
                                    <p class="small">Coba ubah filter atau tambah barang baru.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> barang cetak
                </div>
                <nav class="pagination-nav">
                    <?php 
                    $base_qs = "tab=" . $tab_filter . "&cari=" . urlencode($cari) . "&status=" . $status_filter . "&stok=" . urlencode($stok_filter) . "&sort=" . $sort;
                    ?>
                    <?php if ($halaman > 1): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&<?= $base_qs ?>" title="Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php 
                    $start_page = max(1, $halaman - 2);
                    $end_page = min($total_halaman, $halaman + 2);

                    if ($start_page > 1) {
                        echo '<a class="page-link-pag" href="list.php?halaman=1&' . $base_qs . '">1</a>';
                        if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&<?= $base_qs ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; 

                    if ($end_page < $total_halaman) {
                        if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
                        echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&' . $base_qs . '">' . $total_halaman . '</a>';
                    }
                    ?>

                    <?php if ($halaman < $total_halaman): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&<?= $base_qs ?>" title="Selanjutnya">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> barang cetak
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- FILTER MODAL POPUP -->
    <div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
                <div class="modal-header" style="border: none; padding: 24px 24px 16px; background: #ffffff;">
                    <h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 0 24px 20px; background: #ffffff;">
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">URUT BERDASARKAN</label>
                        <select class="form-select" id="modalSort" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option>
                            <option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Z - A</option>
                            <option value="harga_asc" <?= $sort == 'harga_asc' ? 'selected' : '' ?>>Harga Termurah</option>
                            <option value="harga_desc" <?= $sort == 'harga_desc' ? 'selected' : '' ?>>Harga Termahal</option>
                            <option value="stok_asc" <?= $sort == 'stok_asc' ? 'selected' : '' ?>>Stok Sedikit</option>
                            <option value="stok_desc" <?= $sort == 'stok_desc' ? 'selected' : '' ?>>Stok Banyak</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">STATUS</label>
                        <select class="form-select" id="modalStatus" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">STOK</label>
                        <select class="form-select" id="modalStok" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;">
                            <option value="" <?= $stok_filter === '' ? 'selected' : '' ?>>Semua Stok</option>
                            <option value="menipis" <?= $stok_filter === 'menipis' ? 'selected' : '' ?>>Stok Menipis (<= Minimum)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 0 24px 24px; background: #ffffff; display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1; background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="resetFilter()">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                    </button>
                    <button type="button" class="btn btn-danger" style="flex: 1; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="applyFilter()">
                        <i class="bi bi-check-lg me-2"></i>Terapkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle Submenu
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

        // Filter Modal
        var filterModal;
        function bukaModalFilter() {
            filterModal = new bootstrap.Modal(document.getElementById('modalFilterData'));
            filterModal.show();
        }
        function applyFilter() {
            document.getElementById('hiddenSort').value = document.getElementById('modalSort').value;
            document.getElementById('hiddenStatus').value = document.getElementById('modalStatus').value;
            document.getElementById('hiddenStok').value = document.getElementById('modalStok').value;
            document.getElementById('mainSearchForm').submit();
        }
        function resetFilter() {
            document.getElementById('modalSort').value = 'nama_asc';
            document.getElementById('modalStatus').value = '';
            document.getElementById('modalStok').value = '';
            document.getElementById('hiddenSort').value = 'nama_asc';
            document.getElementById('hiddenStatus').value = '';
            document.getElementById('hiddenStok').value = '';
            document.getElementById('mainSearchForm').submit();
        }

        // Toggle Status
        // KUNCI: URL harus action_cetak.php?act= (bukan action_barang.php?aksi=)
        function toggleStatus(id, currentStatus, nama) {
            const newStatus = currentStatus === 1 ? 0 : 1;
            const actionText = currentStatus === 1 ? 'menonaktifkan' : 'mengaktifkan';

            Swal.fire({
                title: 'Ubah Status Barang?',
                text: 'Anda akan ' + actionText + ' barang "' + nama + '"',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Ubah',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_cetak.php?act=toggle_status&id=' + id;
                }
            });
        }

        // Soft Delete
        // KUNCI: URL harus action_cetak.php?act=soft_delete (bukan action_barang.php?aksi=)
        function softDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Barang?',
                text: 'Anda akan menghapus barang "' + nama + '" (soft delete). Data masih bisa dipulihkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_cetak.php?act=soft_delete&id=' + id;
                }
            });
        }

        // Restore Barang (BARU - KUNCI PENTING!)
        function restoreBarang(id, nama) {
            Swal.fire({
                title: 'Pulihkan Barang?',
                text: 'Anda akan memulihkan barang "' + nama + '" ke data aktif.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Pulihkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_cetak.php?act=restore&id=' + id;
                }
            });
        }

        // Hard Delete (BARU - KUNCI PENTING!)
        function hardDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Permanen?',
                text: 'Barang "' + nama + '" akan dihapus PERMANEN dari database dan tidak bisa dikembalikan!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#7f1d1d',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus Permanen',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'action_cetak.php?act=hard_delete&id=' + id;
                }
            });
        }

        // Konfirmasi Logout
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan dialihkan ke halaman utama publik.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#D53D66',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php';
                }
            });
        }

        // Jam Real-Time
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
            const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            document.getElementById('live-clock').innerText = 
                days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + 
                String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0') + ' WIB';
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();

        // ===== SIDEBAR TOGGLE (MOBILE) =====
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        document.querySelectorAll('.sidebar .nav-link-custom, .sidebar .submenu-link, .sidebar .btn-logout').forEach(el => {
            el.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar.classList.contains('mobile-open')) toggleSidebar();
                }
            });
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // ===== MODAL PROFIL =====
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

        // ===== FILE INPUT PREVIEW =====
        const inputFotoModal = document.getElementById('inputFotoModal');
        if (inputFotoModal) {
            inputFotoModal.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) { document.getElementById('profile-preview-modal').src = event.target.result; };
                    reader.readAsDataURL(file);
                }
            });
        }

        // ===== VALIDASI INPUT =====
        const inputNamaModal = document.getElementById('inputNamaModal');
        if (inputNamaModal) {
            inputNamaModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z ]/g, ''); });
        }
        const inputUsernameModal = document.getElementById('inputUsernameModal');
        if (inputUsernameModal) {
            inputUsernameModal.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z0-9_]/g, ''); });
        }

        // ===== TOGGLE PASSWORD =====
        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('bi-eye');
                    this.classList.toggle('bi-eye-slash');
                });
            }
        }
        setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
        setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');

        // ===== FORMAT NOMOR TELEPON =====
        const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62';
        if (inputHPModal) {
            inputHPModal.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, ''); }
                let digits = this.value.split(prefix)[1]?.replace(/[^0-9]/g, '') || '';
                if (digits.length > 13) digits = digits.slice(0, 13);
                this.value = prefix + digits;
            });
        }
    </script>

    <!-- Notifikasi -->
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = "";
        let t_icon = "success";
        let t_title = "Berhasil!";

        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Barang cetak baru berhasil ditambahkan!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data barang cetak berhasil diperbarui!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'toggle_status') { msg = "<?= $_GET['message'] ?? 'Status barang berhasil diubah!' ?>"; t_title = "Status Diubah"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'soft_delete') { msg = "<?= $_GET['message'] ?? 'Barang cetak berhasil dihapus (soft delete)!' ?>"; t_title = "Dihapus"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'hard_delete') { msg = "Barang cetak berhasil dihapus permanen!"; t_title = "Hard Delete Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'restore') { msg = "Barang cetak berhasil dipulihkan!"; t_title = "Pulihkan Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }

        Swal.fire({
            icon: t_icon,
            title: t_title,
            text: msg,
            confirmButtonColor: '#D53D66'
        });
    </script>
    <?php endif; ?>

    <!-- MODAL LIHAT BIODATA -->
    <div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="text-center mb-4">
                        <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);">
                            <img src="<?= $foto_admin_src ?>" alt="Foto Profil">
                        </div>
                        <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5>
                        <span class="badge bg-primary px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Administrator</span>
                    </div>
                    <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                        <div class="row g-3">
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">NIK</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_admin['nik'] ?? '-') ?></span></div>
                            <div class="col-6"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nama Pengguna</small><span class="fw-bold text-dark" style="font-size:0.85rem;">@<?= htmlspecialchars($username_admin) ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($email_admin) ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_admin['jenis_kelamin'] ?? '-') ?></span></div>
                            <div class="col-6 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_admin['no_hp'] ?? '-') ?></span></div>
                            <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small><span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_admin['alamat'] ?? '-') ?></span></div>
                        </div>
                    </div>
                    <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius:14px;">Edit Profil Anda</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GANTI PROFIL -->
    <div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(213,61,102,0.25);background:rgba(255,255,255,0.95);">
                <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <p class="text-muted small mb-4" style="line-height:1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <div class="d-inline-block position-relative">
                                <div class="profile-preview-box mx-auto"><img id="profile-preview-modal" src="<?= $foto_admin_src ?>" alt="Foto Profil"></div>
                                <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                                <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom:-10px;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:0.75rem;padding:5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label><input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label><input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Alamat Email<span class="required-star">*</span></label><input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_admin) ?>" required></div>
                        <div class="mb-3"><label class="form-label">Nomor Telepon<span class="required-star">*</span></label><input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: +628xxxxxxxxxx" value="<?= htmlspecialchars($d_admin['no_hp'] ?? '') ?>" required></div>
                        <div class="mb-3"><label class="form-label">Alamat Lengkap<span class="required-star">*</span></label><textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize:none;"><?= htmlspecialchars($d_admin['alamat'] ?? '') ?></textarea></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Sandi Baru (Opsional)</label><div class="password-group"><input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter"><i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i></div></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Konfirmasi Sandi</label><div class="password-group"><input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru"><i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i></div></div>
                        </div>
                        <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($success_profile) && $success_profile === true): ?>
    <script>Swal.fire({icon:'success',title:'Profil Diperbarui!',text:'Informasi profil Anda berhasil disinkronkan.',confirmButtonColor:'#D53D66',confirmButtonText:'Selesai'});</script>
    <?php endif; ?>

    <?php if(isset($error_profile) && $error_profile !== ""): ?>
    <script>Swal.fire({icon:'error',title:'Pembaruan Gagal!',text:'<?= addslashes($error_profile) ?>',confirmButtonColor:'#D53D66',confirmButtonText:'Periksa Kembali'}).then(()=>{var modalGanti=new bootstrap.Modal(document.getElementById('modalGantiProfil'));modalGanti.show();});</script>
    <?php endif; ?>

</body>
</html>