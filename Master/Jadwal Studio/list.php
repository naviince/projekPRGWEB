<?php
ob_start();
session_start();
include '../../koneksi.php';

define('STATUS_JADWAL_TERSEDIA', 0);
define('STATUS_JADWAL_BOOKED', 1);
define('STATUS_JADWAL_MAINTENANCE', 2);
define('STATUS_DATA_AKTIF', 1);

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return [];
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $results[] = $row;
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

$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$filter_ruangan = isset($_GET['filter_ruangan']) ? (int)$_GET['filter_ruangan'] : 0;
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : "";

// Filter Tanggal Default dikosongkan agar menampilkan seluruh riwayat jadwal tanpa batasan rentang waktu sepihak di awal
$filter_tanggal_dari = isset($_GET['filter_tanggal_dari']) ? $_GET['filter_tanggal_dari'] : "";
$filter_tanggal_sampai = isset($_GET['filter_tanggal_sampai']) ? $_GET['filter_tanggal_sampai'] : "";

$filter_status_data = isset($_GET['filter_status_data']) ? $_GET['filter_status_data'] : 'aktif';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "tanggal_desc";
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";

$ruangan_list = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = ? AND Is_Deleted = 0 ORDER BY Nama_Ruangan",
    [STATUS_DATA_AKTIF]
);

$conditions = array("1=1");
$params = array();

if ($filter_status_data == 'aktif') {
    $conditions[] = "j.Is_Deleted = 0";
} elseif ($filter_status_data == 'terhapus') {
    $conditions[] = "j.Is_Deleted = 1";
}

if ($filter_ruangan > 0) {
    $conditions[] = "j.ID_Ruangan = ?";
    $params[] = $filter_ruangan;
}
if ($filter_status !== "" && in_array($filter_status, ['0', '1', '2'])) {
    $conditions[] = "j.Status_Jadwal = ?";
    $params[] = (int)$filter_status;
}
if (!empty($filter_tanggal_dari)) {
    $conditions[] = "j.Tanggal_Jadwal >= ?";
    $params[] = $filter_tanggal_dari;
}
if (!empty($filter_tanggal_sampai)) {
    $conditions[] = "j.Tanggal_Jadwal <= ?";
    $params[] = $filter_tanggal_sampai;
}
if (!empty($cari)) {
    $conditions[] = "(j.Keterangan LIKE ? OR r.Nama_Ruangan LIKE ? OR p.Nama_Paket LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

$order_clause = "j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC";
if ($sort == "tanggal_asc") $order_clause = "j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC";
elseif ($sort == "tanggal_desc") $order_clause = "j.Tanggal_Jadwal DESC, j.Jam_Mulai DESC";
elseif ($sort == "jam_asc") $order_clause = "j.Jam_Mulai ASC";
elseif ($sort == "jam_desc") $order_clause = "j.Jam_Mulai DESC";
elseif ($sort == "ruangan_asc") $order_clause = "r.Nama_Ruangan ASC, j.Tanggal_Jadwal DESC";
elseif ($sort == "ruangan_desc") $order_clause = "r.Nama_Ruangan DESC, j.Tanggal_Jadwal DESC";

// SQL Count dengan JOIN Paket_Foto
$sql_count = "SELECT COUNT(*) AS total 
              FROM Jadwal_Studio j 
              INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan 
              INNER JOIN Paket_Foto p ON j.ID_Paket = p.ID_Paket
              WHERE " . implode(" AND ", $conditions);

$query_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0; $total_halaman = 0;
if ($query_count !== false) {
    $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
    $total_records = $row_count['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// Menambahkan SELECT p.Nama_Paket dan melakukan INNER JOIN ke Paket_Foto
$sql_list = "SELECT j.ID_Jadwal, j.ID_Ruangan, j.ID_Paket, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai, 
               j.Keterangan, j.Status_Jadwal, j.Status, j.Is_Deleted, j.Created_By, j.Created_Date,
               j.Modified_By, j.Modified_Date, j.Deleted_By, j.Deleted_Date,
               r.Nama_Ruangan, r.Foto_Ruangan,
               p.Nama_Paket,
               DATEDIFF(MINUTE, j.Jam_Mulai, j.Jam_Selesai) as Durasi_Menit
        FROM Jadwal_Studio j
        INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
        INNER JOIN Paket_Foto p ON j.ID_Paket = p.ID_Paket
        WHERE " . implode(" AND ", $conditions) . " 
        ORDER BY " . $order_clause . " 
        OFFSET " . (int)$offset . " ROWS FETCH NEXT " . (int)$limit . " ROWS ONLY";

$query = sqlsrv_query($conn, $sql_list, $params);

$total_jadwal = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE Is_Deleted = 0", []);
$total_tersedia = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE Status_Jadwal = ? AND Is_Deleted = 0", [STATUS_JADWAL_TERSEDIA]);
$total_booked = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE Status_Jadwal = ? AND Is_Deleted = 0", [STATUS_JADWAL_BOOKED]);
$total_maintenance = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE Status_Jadwal = ? AND Is_Deleted = 0", [STATUS_JADWAL_MAINTENANCE]);
$total_terhapus = safe_sqlsrv_count($conn, "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE Is_Deleted = 1", []);

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_admin = sqlsrv_query($conn, 
    "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ? AND Status = ? AND Is_Deleted = 0",
    array($id_admin, STATUS_DATA_AKTIF)
);

$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) {
    $d_admin = array_change_key_case($d_admin, CASE_LOWER);
}
$nama_admin_display = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

$jadwal_list = [];
if ($query) {
    while ($j = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $jadwal_list[] = $j;
    }
}

// Konversi Objek DateTime ke String murni PHP sebelum di-parse ke JSON (Mencegah Crash JSON)
$jadwal_json_list = [];
foreach ($jadwal_list as $row) {
    $item = $row;
    if (isset($item['Tanggal_Jadwal']) && $item['Tanggal_Jadwal'] instanceof DateTime) {
        $item['Tanggal_Jadwal'] = $item['Tanggal_Jadwal']->format('Y-m-d');
    }
    if (isset($item['Jam_Mulai']) && $item['Jam_Mulai'] instanceof DateTime) {
        $item['Jam_Mulai'] = $item['Jam_Mulai']->format('H:i:s');
    }
    if (isset($item['Jam_Selesai']) && $item['Jam_Selesai'] instanceof DateTime) {
        $item['Jam_Selesai'] = $item['Jam_Selesai']->format('H:i:s');
    }
    if (isset($item['Created_Date']) && $item['Created_Date'] instanceof DateTime) {
        $item['Created_Date'] = $item['Created_Date']->format('Y-m-d H:i:s');
    }
    if (isset($item['Modified_Date']) && $item['Modified_Date'] instanceof DateTime) {
        $item['Modified_Date'] = $item['Modified_Date']->format('Y-m-d H:i:s');
    }
    if (isset($item['Deleted_Date']) && $item['Deleted_Date'] instanceof DateTime) {
        $item['Deleted_Date'] = $item['Deleted_Date']->format('Y-m-d H:i:s');
    }
    
    // Siapkan Helper String untuk JS Detail modal
    $tgl_str = $item['Tanggal_Jadwal'];
    $item['Tanggal_Format'] = date('d M Y', strtotime($tgl_str));
    $hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $item['Hari'] = $hari_indo[date('w', strtotime($tgl_str))];
    $item['Jam_Mulai_Str'] = substr($item['Jam_Mulai'], 0, 5);
    $item['Jam_Selesai_Str'] = substr($item['Jam_Selesai'], 0, 5);
    
    $jadwal_json_list[] = $item;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Jadwal Studio - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#D53D66;--d-pink:#CA3366;--s-pink:#FFF0F3;--light-pink:#FFE4E9;--accent-pink:#E85D84;--text-dark:#1e1e24;--text-muted:#718096;--sidebar-bg:#ffffff;--body-bg:#f8fafc;--t:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275)}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--body-bg);color:var(--text-dark);overflow-x:hidden}
.sidebar{width:260px;height:100vh;background:var(--sidebar-bg);position:fixed;top:0;left:0;border-right:1px solid rgba(255,228,233,0.8);display:flex;flex-direction:column;justify-content:space-between;padding:30px 20px;z-index:100}
.sidebar-brand{font-weight:800;font-size:1.5rem;color:var(--p-pink);text-decoration:none;letter-spacing:-1px;margin-bottom:40px;display:block}
.sidebar-brand span{color:var(--text-dark);font-size:0.85rem;font-weight:600}
.sidebar-menu-wrapper{flex-grow:1;overflow-y:auto;margin-bottom:20px;scrollbar-width:none}
.sidebar-menu-wrapper::-webkit-scrollbar{display:none}
.nav-menu{list-style:none;padding:0;margin:0}
.nav-item{margin-bottom:8px}
.nav-link-custom{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;color:#4a5568;font-weight:700;text-decoration:none;border-radius:12px;font-size:0.9rem;transition:var(--t)}
.nav-link-custom:hover,.nav-link-custom.active{background:var(--light-pink);color:var(--p-pink);transform:translateX(4px)}
.submenu{list-style:none;padding-left:20px;margin-top:5px;display:none;transition:var(--t)}
.submenu.show{display:block!important}
.submenu-link{display:flex;align-items:center;padding:8px 18px;color:#718096;font-weight:600;font-size:0.85rem;text-decoration:none;border-radius:10px;transition:0.3s}
.submenu-link:hover,.submenu-link.active{color:var(--p-pink);background:rgba(213,61,102,0.03);padding-left:22px}
.btn-logout{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;width:100%;padding:12px;border-radius:12px;font-weight:800;font-size:0.85rem;transition:var(--t)}
.btn-logout:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(213,61,102,0.2)}
.main-content{margin-left:260px;padding:40px;min-height:100vh}
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px}
.profile-header-btn{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #fff;cursor:pointer;transition:var(--t);background:#fff}
.profile-header-btn:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 20px rgba(213,61,102,0.15);border-color:var(--p-pink)}
.profile-header-btn img{width:100%;height:100%;object-fit:cover}
.stats-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:10px;margin-bottom:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9}
.stats-scroll-wrapper::-webkit-scrollbar{height:6px}
.stats-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px}
.stats-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px}
.stats-row{display:flex;gap:16px;min-width:max-content}
.stat-card-item{min-width:220px;max-width:280px;flex:0 0 auto}
.card-3d{background:#fff;border-radius:22px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 8px 24px rgba(213,61,102,0.03);transition:var(--t);padding:20px;height:100%;position:relative;overflow:hidden}
.card-3d:hover{transform:translateY(-8px) scale(1.01);box-shadow:0 22px 45px rgba(213,61,102,0.14);border-color:var(--p-pink)}
.stat-card{display:flex;align-items:center;gap:14px}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:var(--t);flex-shrink:0}
.stat-icon-pink{background:linear-gradient(135deg,#FFF0F3,#FFE4E9);color:#D53D66}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669}
.stat-icon-orange{background:linear-gradient(135deg,#fff7ed,#fed7aa);color:#ea580c}
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fecaca);color:#dc2626}
.stat-icon-gray{background:linear-gradient(135deg,#f3f4f6,#e5e7eb);color:#6b7280}
.stat-content{flex:1;min-width:0;overflow:hidden}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px}
.status-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.status-tab{padding:10px 20px;border-radius:12px;font-weight:700;font-size:0.85rem;text-decoration:none;color:#64748b;background:#fff;border:2px solid #e2e8f0;transition:var(--t);display:inline-flex;align-items:center;gap:6px}
.status-tab:hover{border-color:var(--p-pink);color:var(--p-pink)}
.status-tab.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);box-shadow:0 4px 12px rgba(213,61,102,0.2)}
.status-tab .tab-count{background:rgba(255,255,255,0.3);color:inherit;padding:2px 8px;border-radius:50px;font-size:0.7rem;font-weight:800}
.search-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:25px;flex-wrap:wrap}
.search-form-flex{display:flex;align-items:center;gap:10px;flex:1;min-width:300px}
.search-input-wrapper{position:relative;flex:1}
.search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;z-index:2}
.search-input-main{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 18px 12px 44px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:var(--t);background:#fff}
.search-input-main:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(213,61,102,0.08)}
.btn-filter-modal{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;border-radius:14px;padding:12px 24px;font-weight:700;font-size:0.9rem;display:inline-flex;align-items:center;cursor:pointer;transition:var(--t);white-space:nowrap}
.btn-filter-modal:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(213,61,102,0.3)}
.btn-search-icon{background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;color:#94a3b8;cursor:pointer;transition:var(--t);display:flex;align-items:center;justify-content:center}
.btn-search-icon:hover{border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px)}
.btn-reg-header{background:linear-gradient(135deg,var(--p-pink),var(--d-pink))!important;color:#fff!important;border-radius:14px!important;padding:12px 28px!important;font-weight:800!important;border:none!important;box-shadow:0 8px 20px rgba(213,61,102,0.25)!important;transition:var(--t)!important;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-reg-header:hover{background:linear-gradient(135deg,#E85D84,var(--p-pink))!important;transform:translateY(-4px) scale(1.03)!important;box-shadow:0 12px 25px rgba(213,61,102,0.4)!important}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9}
.table-scroll-wrapper::-webkit-scrollbar{height:8px}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px}
.data-table{width:100%;min-width:1050px;border-collapse:separate;border-spacing:0}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left}
.data-table thead th:first-child{padding-left:24px}
.data-table thead th:last-child{padding-right:24px;text-align:center}
.data-table tbody tr{transition:all 0.2s ease}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap}
.data-table tbody td:first-child{padding-left:24px}
.data-table tbody td:last-child{padding-right:24px;text-align:center}
.data-table tbody tr:nth-child(even){background:#FFF8F0}
.data-table tbody tr:nth-child(odd){background:#fff}
.data-table tbody tr:hover{background:#FFEDD5!important;transform:scale(1.002)}
.ruangan-img{width:50px;height:50px;object-fit:cover;border-radius:12px;border:2px solid var(--light-pink);transition:var(--t)}
.data-table tbody tr:hover .ruangan-img{transform:scale(1.08) rotate(2deg)}
.td-ruangan-name{font-weight:700;font-size:0.9rem;color:var(--text-dark)}
.td-tanggal{font-weight:700;font-size:0.9rem}
.td-tanggal .hari{font-size:0.75rem;color:#94a3b8}
.td-waktu{font-weight:800;color:var(--p-pink);font-size:0.95rem}
.td-durasi{font-size:0.8rem;color:#718096;font-weight:600}
.td-keterangan{font-size:0.85rem;color:#4a5568;max-width:200px;white-space:normal}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px}
.badge-tersedia{background:#ecfdf5;color:#059669}
.badge-booked{background:#fef2f2;color:#dc2626}
.badge-maintenance{background:#fffbeb;color:#d97706}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block}
.badge-tersedia .badge-dot{background:#059669}
.badge-booked .badge-dot{background:#dc2626}
.badge-maintenance .badge-dot{background:#d97706}
.btn-action-circle{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;transition:var(--t);border:1.5px solid #eef2f6;background:#fff;font-size:0.85rem;text-decoration:none;margin:0 2px;cursor:pointer}
.btn-action-detail{color:#D53D66;border-color:#FFE4E9}
.btn-action-detail:hover{background:#D53D66;color:#fff;transform:translateY(-2px)}
.btn-action-edit{color:var(--p-pink);border-color:#FFE4E9}
.btn-action-edit:hover{background:var(--p-pink);color:#fff;transform:translateY(-2px)}
.btn-action-delete{color:#dc2626;border-color:#fee2e2}
.btn-action-delete:hover{background:#dc2626;color:#fff;transform:translateY(-2px)}
.btn-action-restore{color:#059669;border-color:#d1fae5}
.btn-action-restore:hover{background:#059669;color:#fff;transform:translateY(-2px)}
.btn-action-circle:disabled{opacity:0.4;cursor:not-allowed;transform:none!important}
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:30px;padding:20px 24px;background:#fff;border-radius:20px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 4px 15px rgba(213,61,102,0.04)}
.pagination-info{font-size:0.85rem;color:#718096;font-weight:600}
.pagination-info span{color:var(--p-pink);font-weight:700}
.pagination-nav{display:flex;gap:6px;align-items:center}
.page-link-pag{display:flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 14px;border-radius:12px;background:#fff;border:2px solid #FFF5F7;color:#4a5568;font-weight:700;font-size:0.9rem;text-decoration:none;transition:var(--t)}
.page-link-pag:hover{background:var(--light-pink);border-color:var(--p-pink);color:var(--p-pink);transform:translateX(0);transform:translateY(-2px)}
.page-link-pag.active-pag{background:linear-gradient(135deg,var(--p-pink),var(--d-pink))!important;color:#fff!important;border-color:var(--p-pink)!important;box-shadow:0 4px 12px rgba(213,61,102,0.3)}
.page-link-pag.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none}

/* STYLE UNTUK TOGGLE SWITCH MERAH MUDA */
.form-switch .form-check-input {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -2 8 8'%3E%3Ccircle cx='4' cy='4' r='3' fill='%2394a3b8'/%3E%3C/svg%3E");
    border-color: #cbd5e1;
    cursor: pointer;
    width: 2.8em;
    height: 1.5em;
    transition: var(--t);
}
.form-switch .form-check-input:checked {
    background-color: var(--p-pink);
    border-color: var(--p-pink);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -2 8 8'%3E%3Ccircle cx='4' cy='4' r='3' fill='%23fff'/%3E%3C/svg%3E");
}
.form-switch .form-check-input:focus {
    border-color: var(--p-pink);
    box-shadow: 0 0 0 4px rgba(213,61,102,0.1);
}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.fade-in-up{animation:fadeIn 0.5s ease-out}
@media(max-width:992px){.main-content{margin-left:0;padding:20px}.sidebar{transform:translateX(-100%)}}
</style>
</head>
<body>
<div class="sidebar">
<div class="sidebar-menu-wrapper">
<a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
<ul class="nav-menu">
<li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
<span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
<i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg)"></i>
</a>
<div class="submenu show" id="submenuMaster">
<ul class="list-unstyled">
<li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
<li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
<li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
<li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
<li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
<li><a href="list.php" class="submenu-link active"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
<li><a href="../Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
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
<li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Kelola Booking</a></li>
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran</a></li>
<li><a href="../../Transaksi/Pembatalan/list.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Pembatalan Booking</a></li>
<li><a href="../../Transaksi/Sesi Foto/list.php" class="submenu-link"><i class="bi bi-camera-reels-fill me-2"></i>Upload Hasil Foto</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang</a></li>
</ul>
</div>
</li>
<li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
</ul>
</div>
<div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<div class="main-content">
<div class="dashboard-header" data-aos="fade-up">
<div><h3 class="fw-bold mb-1">Master Jadwal Studio</h3><p class="text-muted small mb-0">Kelola slot waktu tersedia di setiap ruangan studio.</p></div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<div class="stats-scroll-wrapper animate-fade-in">
<div class="stats-row">
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-week-fill"></i></div><div class="stat-content"><div class="stat-title">Total Jadwal</div><div class="stat-val"><?= $total_jadwal ?> Jadwal</div><div class="stat-subtitle">Aktif di sistem</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Tersedia</div><div class="stat-val"><?= $total_tersedia ?> Slot</div><div class="stat-subtitle">Bisa dipesan</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-lock-fill"></i></div><div class="stat-content"><div class="stat-title">Booked</div><div class="stat-val"><?= $total_booked ?> Slot</div><div class="stat-subtitle">Sedang dipesan</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-tools"></i></div><div class="stat-content"><div class="stat-title">Maintenance</div><div class="stat-val"><?= $total_maintenance ?> Slot</div><div class="stat-subtitle">Sedang perbaikan</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-gray"><i class="bi bi-trash-fill"></i></div><div class="stat-content"><div class="stat-title">Terhapus</div><div class="stat-val"><?= $total_terhapus ?> Jadwal</div><div class="stat-subtitle">Soft deleted</div></div></div></div></div>
</div>
</div>

<div class="status-tabs">
<a href="list.php<?= !empty($cari) ? '?cari=' . urlencode($cari) . '&' : '?' ?>sort=<?= $sort ?>" class="status-tab <?= $filter_status_data == 'aktif' && $filter_status === '' ? 'active' : '' ?>"><i class="bi bi-grid"></i> Semua<span class="tab-count"><?= $total_jadwal ?></span></a>
<a href="list.php?filter_status=0<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_status === '0' ? 'active' : '' ?>"><i class="bi bi-check-circle"></i> Tersedia<span class="tab-count"><?= $total_tersedia ?></span></a>
<a href="list.php?filter_status=1<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_status === '1' ? 'active' : '' ?>"><i class="bi bi-lock"></i> Booked<span class="tab-count"><?= $total_booked ?></span></a>
<a href="list.php?filter_status=2<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_status === '2' ? 'active' : '' ?>"><i class="bi bi-tools"></i> Maintenance<span class="tab-count"><?= $total_maintenance ?></span></a>
<a href="list.php?filter_status_data=terhapus<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_status_data == 'terhapus' ? 'active' : '' ?>"><i class="bi bi-trash"></i> Terhapus<span class="tab-count"><?= $total_terhapus ?></span></a>
</div>

<div class="search-filter-bar">
<form method="GET" class="search-form-flex" id="mainSearchForm">
<input type="hidden" name="filter_status" id="hiddenStatus" value="<?= htmlspecialchars($filter_status) ?>">
<input type="hidden" name="filter_ruangan" id="hiddenRuangan" value="<?= $filter_ruangan ?>">
<input type="hidden" name="filter_tanggal_dari" id="hiddenTglDari" value="<?= htmlspecialchars($filter_tanggal_dari) ?>">
<input type="hidden" name="filter_tanggal_sampai" id="hiddenTglSampai" value="<?= htmlspecialchars($filter_tanggal_sampai) ?>">
<input type="hidden" name="filter_status_data" id="hiddenStatusData" value="<?= htmlspecialchars($filter_status_data) ?>">
<input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
<div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari keterangan, ruangan, paket..." value="<?= htmlspecialchars($cari) ?>"></div>
<button type="button" class="btn-filter-modal" onclick="bukaModalFilter()"><i class="bi bi-funnel-fill me-2"></i>Filter<i class="bi bi-chevron-down ms-2"></i></button>
<button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
</form>
<a href="add.php" class="btn-reg-header text-decoration-none"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Jadwal</a>
</div>

<div class="card-3d mb-4" style="padding:24px">
<div class="table-scroll-wrapper">
<table class="data-table">
<!-- PERBAIKAN: Header kolom pertama diubah menjadi No. untuk privasi database ID -->
<thead><tr><th>No.</th><th>Ruangan & Paket</th><th>Tanggal</th><th>Waktu</th><th>Durasi</th><th>Keterangan</th><th>Status Jadwal</th><th>Aktif</th><th class="text-center">Aksi</th></tr></thead>
<tbody>
<?php
$no = $offset + 1;
if (!empty($jadwal_list)):
    foreach($jadwal_list as $row):
        $is_deleted = (int)$row['Is_Deleted'] == 1;
        $status_jadwal = (int)$row['Status_Jadwal'];
        $is_booked = $status_jadwal == STATUS_JADWAL_BOOKED;
        $tgl = $row['Tanggal_Jadwal'] instanceof DateTime ? $row['Tanggal_Jadwal']->format('Y-m-d') : $row['Tanggal_Jadwal'];
        $jam_mulai = $row['Jam_Mulai'] instanceof DateTime ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
        $jam_selesai = $row['Jam_Selesai'] instanceof DateTime ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
        $durasi = $row['Durasi_Menit'] ?? 0;
        $hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        $nama_hari = $hari_indo[date('w', strtotime($tgl))];
        $path_img = "../../assets/img/ruangan/" . ($row['Foto_Ruangan'] ?? 'default_ruangan.jpg');
        $img_src = file_exists($path_img) ? $path_img : "../../assets/img/ruangan/default_ruangan.jpg";
?>
<tr class="fade-in-up">
<!-- PERBAIKAN: Kolom pertama menampilkan nomor urut ($no++) murni program, bukan database primary key -->
<td><span style="font-family:monospace;font-weight:800;color:#94a3b8"><?= $no++ ?></span></td>
<td>
    <div class="d-flex align-items-center gap-3">
        <img src="<?= $img_src ?>" class="ruangan-img" alt="" onerror="this.src='../../assets/img/ruangan/default_ruangan.jpg'">
        <div>
            <div class="td-ruangan-name"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
            <!-- Menampilkan Nama Paket Foto pendukung slot jadwal -->
            <div class="text-muted" style="font-size:0.75rem;font-weight:700;margin-top:2px">
                <i class="bi bi-camera-fill text-danger me-1"></i>Paket: <?= htmlspecialchars($row['Nama_Paket'] ?? '-') ?>
            </div>
        </div>
    </div>
</td>
<td><div class="td-tanggal"><?= date('d M Y', strtotime($tgl)) ?></div><div class="hari"><?= $nama_hari ?></div></td>
<td><div class="td-waktu"><?= $jam_mulai ?> - <?= $jam_selesai ?></div></td>
<td><div class="td-durasi"><?= $durasi ?> menit</div></td>
<td><div class="td-keterangan"><?= htmlspecialchars($row['Keterangan'] ?? '-') ?></div></td>
<td>
<?php if ($status_jadwal == STATUS_JADWAL_TERSEDIA): ?><span class="badge-status badge-tersedia"><span class="badge-dot"></span>Tersedia</span>
<?php elseif ($status_jadwal == STATUS_JADWAL_BOOKED): ?><span class="badge-status badge-booked"><span class="badge-dot"></span>Booked</span>
<?php else: ?><span class="badge-status badge-maintenance"><span class="badge-dot"></span>Maintenance</span><?php endif; ?>
</td>
<td>
    <div class="form-check form-switch d-inline-block">
        <input class="form-check-input" type="checkbox" role="switch" 
               id="switchStatus_<?= $row['ID_Jadwal'] ?>" 
               <?= (int)$row['Status'] == 1 ? 'checked' : '' ?>
               onclick="toggleDataStatus(<?= $row['ID_Jadwal'] ?>, <?= (int)$row['Status'] ?>)"
               <?= ($is_booked || $is_deleted) ? 'disabled title="Jadwal Booked atau Terhapus tidak bisa diubah statusnya"' : '' ?>>
    </div>
</td>
<td>
<?php if (!$is_deleted): ?>
<button class="btn-action-circle btn-action-detail" onclick="showDetail(<?= $row['ID_Jadwal'] ?>)" title="Detail"><i class="bi bi-eye"></i></button>
<?php if ($is_booked): ?><button class="btn-action-circle btn-action-edit" disabled title="Booked - tidak bisa edit"><i class="bi bi-pencil"></i></button>
<?php else: ?><a href="edit.php?id=<?= $row['ID_Jadwal'] ?>" class="btn-action-circle btn-action-edit" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
<?php if (!$is_booked): ?>
<button class="btn-action-circle btn-action-delete" onclick="toggleStatus(<?= $row['ID_Jadwal'] ?>, <?= $status_jadwal ?>)" title="Toggle Maintenance"><i class="bi bi-tools"></i></button>
<button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $row['ID_Jadwal'] ?>)" title="Hapus"><i class="bi bi-trash"></i></button>
<?php else: ?><button class="btn-action-circle btn-action-delete" disabled title="Booked"><i class="bi bi-tools"></i></button><button class="btn-action-circle btn-action-delete" disabled title="Booked"><i class="bi bi-trash"></i></button><?php endif; ?>
<?php else: ?>
<button class="btn-action-circle btn-action-restore" onclick="restoreJadwal(<?= $row['ID_Jadwal'] ?>)" title="Kembalikan"><i class="bi bi-arrow-counterclockwise"></i></button>
<button class="btn-action-circle btn-action-delete" onclick="hardDelete(<?= $row['ID_Jadwal'] ?>)" title="Hapus Permanen"><i class="bi bi-trash-fill"></i></button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 mb-3 d-block" style="color:#cbd5e1"></i><p class="fw-bold">Tidak ada data jadwal studio yang sesuai.</p></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php if ($total_halaman > 1): ?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> jadwal</div>
<nav class="pagination-nav">
<?php if ($halaman > 1): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_ruangan=<?= $filter_ruangan ?>&filter_tanggal_dari=<?= urlencode($filter_tanggal_dari) ?>&filter_tanggal_sampai=<?= urlencode($filter_tanggal_sampai) ?>&filter_status_data=<?= urlencode($filter_status_data) ?>&sort=<?= urlencode($sort) ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
<?php $start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2); if ($start_page > 1) { echo '<a class="page-link-pag" href="list.php?halaman=1&cari=' . urlencode($cari) . '&filter_status=' . urlencode($filter_status) . '&filter_ruangan=<?= $filter_ruangan ?>&filter_tanggal_dari=' . urlencode($filter_tanggal_dari) . '&filter_tanggal_sampai=' . urlencode($filter_tanggal_sampai) . '&filter_status_data=' . urlencode($filter_status_data) . '&sort=' . urlencode($sort) . '">1</a>'; if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; } for ($i = $start_page; $i <= $end_page; $i++): ?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_ruangan=<?= $filter_ruangan ?>&filter_tanggal_dari=<?= urlencode($filter_tanggal_dari) ?>&filter_tanggal_sampai=<?= urlencode($filter_tanggal_sampai) ?>&filter_status_data=<?= urlencode($filter_status_data) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a><?php endfor; if ($end_page < $total_halaman) { if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&cari=' . urlencode($cari) . '&filter_status=' . urlencode($filter_status) . '&filter_ruangan=' . $filter_ruangan . '&filter_tanggal_dari=' . urlencode($filter_tanggal_dari) . '&filter_tanggal_sampai=' . urlencode($filter_tanggal_sampai) . '&filter_status_data=' . urlencode($filter_status_data) . '&sort=' . urlencode($sort) . '">' . $total_halaman . '</a>'; } ?>
<?php if ($halaman < $total_halaman): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_ruangan=<?= $filter_ruangan ?>&filter_tanggal_dari=<?= urlencode($filter_tanggal_dari) ?>&filter_tanggal_sampai=<?= urlencode($filter_tanggal_sampai) ?>&filter_status_data=<?= urlencode($filter_status_data) ?>&sort=<?= urlencode($sort) ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
</nav>
</div>
<?php elseif ($total_records > 0): ?><div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> jadwal</div></div><?php endif; ?>
</div>
</div>

<div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content" style="border:none;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden">
<div class="modal-header" style="border:none;padding:24px 24px 16px;background:#fff"><h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body" style="padding:0 24px 20px;background:#fff">
<div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">URUT BERDASARKAN</label><select class="form-select" id="modalSort" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600"><option value="tanggal_desc" <?= $sort == 'tanggal_desc' ? 'selected' : '' ?>>Tanggal Terbaru</option><option value="tanggal_asc" <?= $sort == 'tanggal_asc' ? 'selected' : '' ?>>Tanggal Terlama</option><option value="jam_asc" <?= $sort == 'jam_asc' ? 'selected' : '' ?>>Jam Mulai Awal</option><option value="jam_desc" <?= $sort == 'jam_desc' ? 'selected' : '' ?>>Jam Mulai Akhir</option><option value="ruangan_asc" <?= $sort == 'ruangan_asc' ? 'selected' : '' ?>>Ruangan A - Z</option><option value="ruangan_desc" <?= $sort == 'ruangan_desc' ? 'selected' : '' ?>>Ruangan Z - A</option></select></div>
<div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">STATUS JADWAL</label><select class="form-select" id="modalStatus" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600"><option value="" <?= $filter_status === '' ? 'selected' : '' ?>>Semua Status</option><option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Tersedia</option><option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Booked</option><option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>Maintenance</option></select></div>
<div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">RUANGAN</label><select class="form-select" id="modalRuangan" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600"><option value="0">Semua Ruangan</option><?php foreach ($ruangan_list as $r): ?><option value="<?= $r['ID_Ruangan'] ?>" <?= $filter_ruangan == $r['ID_Ruangan'] ? 'selected' : '' ?>><?= htmlspecialchars($r['Nama_Ruangan']) ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">DARI TANGGAL</label><input type="date" id="modalTglDari" class="form-control" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600" value="<?= htmlspecialchars($filter_tanggal_dari) ?>"></div>
<div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">SAMPAI TANGGAL</label><input type="date" id="modalTglSampai" class="form-control" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600" value="<?= htmlspecialchars($filter_tanggal_sampai) ?>"></div>
<div><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">DATA</label><select class="form-select" id="modalStatusData" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600"><option value="aktif" <?= $filter_status_data == 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="terhapus" <?= $filter_status_data == 'terhapus' ? 'selected' : '' ?>>Terhapus</option></select></div>
</div>
<div class="modal-footer" style="border:none;padding:0 24px 24px;background:#fff;display:flex;gap:12px">
<button type="button" class="btn btn-secondary" style="flex:1;background:#f1f5f9;color:#475569;border:none;border-radius:14px;padding:14px 20px;font-weight:700" onclick="resetFilter()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</button>
<button type="button" class="btn btn-danger" style="flex:1;background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;border-radius:14px;padding:14px 20px;font-weight:700" onclick="applyFilter()"><i class="bi bi-check-lg me-2"></i>Terapkan</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border:none;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden">
<div class="modal-header" style="border:none;padding:24px 24px 16px;background:#fff"><h5 class="fw-bold mb-0"><i class="bi bi-calendar-week me-2 text-danger"></i>Detail Jadwal Studio</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="detailContent" style="padding:0 24px 24px;background:#fff"></div>
</div>
</div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)'}}})});
function updateLiveClock(){const now=new Date();const days=["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];const months=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];const dayName=days[now.getDay()];const day=now.getDate();const monthName=months[now.getMonth()];const year=now.getFullYear();const hours=String(now.getHours()).padStart(2,'0');const minutes=String(now.getMinutes()).padStart(2,'0');const seconds=String(now.getSeconds()).padStart(2,'0');document.getElementById('live-clock').innerText=dayName+', '+day+' '+monthName+' '+year+' - '+hours+':'+minutes+':'+seconds+' WIB'}updateLiveClock();setInterval(updateLiveClock,1000);
var filterModal;function bukaModalFilter(){filterModal=new bootstrap.Modal(document.getElementById('modalFilterData'));filterModal.show()}function applyFilter(){document.getElementById('hiddenSort').value=document.getElementById('modalSort').value;document.getElementById('hiddenStatus').value=document.getElementById('modalStatus').value;document.getElementById('hiddenRuangan').value=document.getElementById('modalRuangan').value;document.getElementById('hiddenTglDari').value=document.getElementById('modalTglDari').value;document.getElementById('hiddenTglSampai').value=document.getElementById('modalTglSampai').value;document.getElementById('hiddenStatusData').value=document.getElementById('modalStatusData').value;document.getElementById('mainSearchForm').submit()}function resetFilter(){document.getElementById('modalSort').value='tanggal_desc';document.getElementById('modalStatus').value='';document.getElementById('modalRuangan').value='0';document.getElementById('modalTglDari').value='';document.getElementById('modalTglSampai').value='';document.getElementById('modalStatusData').value='aktif';document.getElementById('hiddenSort').value='tanggal_desc';document.getElementById('hiddenStatus').value='';document.getElementById('hiddenRuangan').value='0';document.getElementById('hiddenTglDari').value='';document.getElementById('hiddenTglSampai').value='';document.getElementById('hiddenStatusData').value='aktif';document.getElementById('mainSearchForm').submit()}

// SINKRONISASI AKSI TOGGLE STATUS DATA (AKTIF / NONAKTIF)
function toggleDataStatus(id, currentStatus) {
    const actionText = currentStatus === 1 ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: 'Ubah Keaktifan Data?',
        text: 'Anda akan ' + actionText + ' jadwal studio ini.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#D53D66',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Ubah',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action_jadwal.php?aksi=toggle_status_data&id=' + id;
        } else {
            // Jika batal, kembalikan posisi toggle switch ke semula
            const checkbox = document.getElementById('switchStatus_' + id);
            if (checkbox) checkbox.checked = (currentStatus === 1);
        }
    });
}

function toggleStatus(id,currentStatus){const newStatus=currentStatus===0?2:0;const actionText=currentStatus===0?'maintenance':'aktifkan';Swal.fire({title:'Ubah Status Jadwal?',text:'Anda akan '+actionText+' jadwal ini.',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Ubah',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='action_jadwal.php?aksi=toggle_status&id='+id}})}function softDelete(id){Swal.fire({title:'Hapus Jadwal?',text:'Jadwal akan dihapus (bisa dikembalikan di tab Terhapus).',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='action_jadwal.php?aksi=soft_delete&id='+id}})}function restoreJadwal(id){Swal.fire({title:'Kembalikan Jadwal?',text:'Jadwal yang dihapus akan dikembalikan.',icon:'info',showCancelButton:true,confirmButtonColor:'#059669',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembalikan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='action_jadwal.php?aksi=restore&id='+id}})}function hardDelete(id){Swal.fire({title:'HAPUS PERMANEN?',text:'Jadwal akan dihapus PERMANEN dari database!',icon:'error',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#718096',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='action_jadwal.php?aksi=hard_delete&id='+id}})}function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php'}})}function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php'}})}function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin_display) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'})}

// Variabel JSON penampung data detail aman dari bug datetime object
const jadwalData=<?= json_encode($jadwal_json_list) ?>;

function showDetail(id){const j=jadwalData.find(item=>item.ID_Jadwal==id);if(!j)return;const statusLabels={0:['Tersedia','#059669','bi-check-circle-fill'],1:['Booked','#dc2626','bi-lock-fill'],2:['Maintenance','#d97706','bi-tools']};const status=statusLabels[j.Status_Jadwal]||['Unknown','#6b7280','bi-question-circle'];const statusKeaktifan = parseInt(j.Status) === 1 ? '<span class="text-success">Aktif</span>' : '<span class="text-secondary">Nonaktif</span>';const html=`<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px"><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">ID Jadwal</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">#${j.ID_Jadwal}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Status Jadwal</div><div style="font-weight:700;font-size:0.95rem;color:${status[1]}"><i class="bi ${status[2]}"></i> ${status[0]}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Ruangan</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Nama_Ruangan}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Paket Foto</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Nama_Paket ?? '-'}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Tanggal</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Hari}, ${j.Tanggal_Format}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Jam</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Jam_Mulai_Str} - ${j.Jam_Selesai_Str}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Durasi</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Durasi_Menit} menit</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Status Data</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${statusKeaktifan}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Dibuat Oleh</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Created_By}</div></div><div style="background:#f8fafc;border-radius:14px;padding:16px"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Keterangan</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Keterangan||'-'}</div></div>${j.Modified_By?`<div style="background:#f8fafc;border-radius:14px;padding:16px;grid-column:span 2"><div style="font-size:0.75rem;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Terakhir Diubah</div><div style="font-weight:700;font-size:0.95rem;color:var(--text-dark)">${j.Modified_By}</div></div>`:''}</div>`;document.getElementById('detailContent').innerHTML=html;new bootstrap.Modal(document.getElementById('detailModal')).show()}
</script>

<?php if(isset($_GET['status_sukses'])): ?>
<script>
let msg="";let t_icon="success";let t_title="Berhasil!";
if("<?= $_GET['status_sukses'] ?>"=="tambah")msg="Jadwal studio baru berhasil ditambahkan!";
else if("<?= $_GET['status_sukses'] ?>"=="edit")msg="Data jadwal studio berhasil diperbarui!";
else if("<?= $_GET['status_sukses'] ?>"=="toggle_status"){msg="Status berhasil diperbarui!";t_title="Status Diubah"}
else if("<?= $_GET['status_sukses'] ?>"=="soft_delete"){msg="Jadwal berhasil dihapus (bisa dikembalikan di tab Terhapus)!";t_title="Berhasil Dihapus"}
else if("<?= $_GET['status_sukses'] ?>"=="restore"){msg="Jadwal berhasil dikembalikan!";t_title="Restore Berhasil"}
else if("<?= $_GET['status_sukses'] ?>"=="hard_delete"){msg="Jadwal berhasil dihapus permanen dari sistem!";t_title="Hard Delete Berhasil"}
else if("<?= $_GET['status_sukses'] ?>"=="error"){msg="<?= htmlspecialchars($_GET['message'] ?? 'Terjadi kesalahan!') ?>";t_icon="error";t_title="Gagal!"}
Swal.fire({icon:t_icon,title:t_title,text:msg,confirmButtonColor:'#D53D66'});
</script>
<?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>