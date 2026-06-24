<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI KEAMANAN HAK AKSES ADMIN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';
$default_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_admin)) ? "../../assets/img/pelanggan/" . $foto_admin : $default_svg;

// =====================================================
// PROSES ASSIGN FOTOGRAFER (AJAX)
// =====================================================
if (isset($_POST['ajax_assign']) && isset($_POST['id_sesi']) && isset($_POST['id_fotografer'])) {
    header('Content-Type: application/json');
    $id_sesi = intval($_POST['id_sesi']);
    $id_fotografer = intval($_POST['id_fotografer']);

    // Cek apakah fotografer valid
    $cek_fotografer = sqlsrv_query($conn, 
        "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0",
        array($id_fotografer)
    );

    if (!$cek_fotografer || !sqlsrv_has_rows($cek_fotografer)) {
        echo json_encode(['success' => false, 'message' => 'Fotografer tidak valid!']);
        exit();
    }

    // Update sesi foto
    $sql_update = "UPDATE Sesi_Foto SET ID_Karyawan = ?, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = ? AND Status_Sesi = 0 AND Status = 1";
    $stmt_update = sqlsrv_query($conn, $sql_update, array($id_fotografer, $id_sesi));

    if ($stmt_update) {
        $rows = sqlsrv_rows_affected($stmt_update);
        if ($rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Fotografer berhasil di-assign!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan atau sudah diproses!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database!']);
    }
    exit();
}

// =====================================================
// PAGINATION & FILTER SETUP
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$tab_filter = isset($_GET['tab']) ? trim($_GET['tab']) : "semua";

// =====================================================
// STATISTIK SESI FOTO - DENGAN ERROR CHECKING
// =====================================================
$stats = ['total'=>0,'menunggu'=>0,'belum_assign'=>0,'selesai'=>0,'dibatalkan'=>0];

$q_stats = sqlsrv_query($conn, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN Status_Sesi = 0 THEN 1 ELSE 0 END) AS menunggu,
        SUM(CASE WHEN Status_Sesi = 0 AND ID_Karyawan IS NULL THEN 1 ELSE 0 END) AS belum_assign,
        SUM(CASE WHEN Status_Sesi = 1 THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN Status_Sesi = 2 THEN 1 ELSE 0 END) AS dibatalkan
    FROM Sesi_Foto WHERE Status = 1
");

if ($q_stats !== false) {
    $row_stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
    if ($row_stats) {
        $stats['total'] = $row_stats['total'] ?? 0;
        $stats['menunggu'] = $row_stats['menunggu'] ?? 0;
        $stats['belum_assign'] = $row_stats['belum_assign'] ?? 0;
        $stats['selesai'] = $row_stats['selesai'] ?? 0;
        $stats['dibatalkan'] = $row_stats['dibatalkan'] ?? 0;
    }
}

// =====================================================
// BUILD WHERE CONDITIONS
// =====================================================
$conditions = ["S.Status = 1"];
$params = [];

if ($tab_filter === 'menunggu') {
    $conditions[] = "S.Status_Sesi = 0";
} elseif ($tab_filter === 'belum_assign') {
    $conditions[] = "S.Status_Sesi = 0 AND S.ID_Karyawan IS NULL";
} elseif ($tab_filter === 'selesai') {
    $conditions[] = "S.Status_Sesi = 1";
} elseif ($tab_filter === 'dibatalkan') {
    $conditions[] = "S.Status_Sesi = 2";
}

if (!empty($cari)) {
    $conditions[] = "(P.Nama_Pelanggan LIKE ? OR CAST(S.ID_Sesi_Foto AS VARCHAR) LIKE ? OR PK.Nama_Paket LIKE ? OR R.Nama_Ruangan LIKE ? OR K.Nama_Karyawan LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

$where = implode(" AND ", $conditions);

// =====================================================
// HITUNG TOTAL DATA - DENGAN ERROR CHECKING
// =====================================================
$sql_count = "SELECT COUNT(*) AS total FROM Sesi_Foto S 
    JOIN [Order] O ON S.ID_Order = O.ID_Order 
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan 
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket 
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan 
    LEFT JOIN Karyawan K ON S.ID_Karyawan = K.ID_Karyawan 
    WHERE " . $where;

$q_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0;
$total_halaman = 0;

if ($q_count !== false) {
    $r = sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC);
    if ($r) {
        $total_records = $r['total'] ?? 0;
        $total_halaman = ceil($total_records / $limit);
    }
}

// =====================================================
// AMBIL DATA SESI FOTO - DENGAN ERROR CHECKING
// =====================================================
$p_list = $params;
$p_list[] = $offset;
$p_list[] = $limit;

$sql_list = "SELECT 
    S.ID_Sesi_Foto,
    S.ID_Order,
    S.ID_Karyawan,
    S.Waktu_Mulai,
    S.Waktu_Selesai,
    S.File_Hasil,
    S.Tanggal_Upload_Hasil,
    S.Status_Sesi,
    S.Created_Date,
    P.Nama_Pelanggan,
    P.Email_Pelanggan,
    P.No_Hp AS NoHp_Pelanggan,
    PK.Nama_Paket,
    PK.Durasi_Waktu,
    PK.Harga_Paket,
    R.Nama_Ruangan,
    J.Tanggal_Jadwal,
    J.Jam_Mulai,
    J.Jam_Selesai,
    K.Nama_Karyawan AS Nama_Fotografer,
    K.Username_Karyawan AS Username_Fotografer,
    O.Status_Order,
    O.Keterangan AS Keterangan_Order
FROM Sesi_Foto S
JOIN [Order] O ON S.ID_Order = O.ID_Order
JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal
LEFT JOIN Karyawan K ON S.ID_Karyawan = K.ID_Karyawan
WHERE " . $where . "
ORDER BY S.Created_Date DESC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$query = sqlsrv_query($conn, $sql_list, $p_list);

// =====================================================
// AMBIL LIST FOTOGRAFER - DENGAN ERROR CHECKING
// =====================================================
$q_fg = sqlsrv_query($conn, 
    "SELECT ID_Karyawan, Nama_Karyawan, Username_Karyawan FROM Karyawan 
     WHERE Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0 
     ORDER BY Nama_Karyawan ASC"
);
$fotografer_list = [];
if ($q_fg !== false) {
    while ($f = sqlsrv_fetch_array($q_fg, SQLSRV_FETCH_ASSOC)) {
        $fotografer_list[] = $f;
    }
}

// Helper functions
function getStatusSesiLabel($s) {
    $l = [
        0 => ['Menunggu', '#d97706', '#fffbeb', 'bi-hourglass-split'],
        1 => ['Selesai', '#059669', '#ecfdf5', 'bi-check-circle-fill'],
        2 => ['Dibatalkan', '#dc2626', '#fef2f2', 'bi-x-octagon-fill']
    ];
    return $l[$s] ?? ['Unknown', '#718096', '#f1f5f9', 'bi-question-circle'];
}

function getStatusOrderLabel($s) {
    $l = [
        0 => ['Menunggu Pembayaran', '#d97706', '#fffbeb', 'bi-hourglass-split'],
        1 => ['DP Terverifikasi', '#2563eb', '#dbeafe', 'bi-check-circle-fill'],
        2 => ['Lunas', '#7c3aed', '#ede9fe', 'bi-cash-stack'],
        3 => ['Selesai', '#059669', '#ecfdf5', 'bi-camera-fill'],
        4 => ['Dibatalkan', '#dc2626', '#fef2f2', 'bi-x-circle-fill']
    ];
    return $l[$s] ?? ['Unknown', '#718096', '#f1f5f9', 'bi-question-circle'];
}

function fmtTgl($d) {
    return (is_object($d) && method_exists($d, 'format')) ? $d->format('d M Y H:i') : ($d ? date('d M Y H:i', strtotime($d)) : '-');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sesi Foto - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#D53D66;--d-pink:#CA3366;--s-pink:#FFF0F3;--light-pink:#FFE4E9;--text-dark:#1e1e24;--text-muted:#718096;--body-bg:#f8fafc;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--body-bg);color:var(--text-dark);}
.sidebar{width:260px;height:100vh;background:#fff;position:fixed;top:0;left:0;border-right:1px solid rgba(255,228,233,0.8);display:flex;flex-direction:column;justify-content:space-between;padding:30px 20px;z-index:100;}
.sidebar-brand{font-weight:800;font-size:1.5rem;color:var(--p-pink);text-decoration:none;letter-spacing:-1px;margin-bottom:40px;display:block;}
.sidebar-brand span{color:var(--text-dark);font-size:0.85rem;font-weight:600;}
.sidebar-menu-wrapper{flex-grow:1;overflow-y:auto;margin-bottom:20px;scrollbar-width:none;}
.sidebar-menu-wrapper::-webkit-scrollbar{display:none;}
.nav-menu{list-style:none;padding:0;margin:0;}
.nav-item{margin-bottom:8px;}
.nav-link-custom{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;color:#4a5568;font-weight:700;text-decoration:none;border-radius:12px;font-size:0.9rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
.nav-link-custom:hover,.nav-link-custom.active{background:var(--light-pink);color:var(--p-pink);transform:translateX(4px);}
.submenu{list-style:none;padding-left:20px;margin-top:5px;display:none;}
.submenu.show{display:block!important;}
.submenu-link{display:flex;align-items:center;padding:8px 18px;color:#718096;font-weight:600;font-size:0.85rem;text-decoration:none;border-radius:10px;transition:0.3s;}
.submenu-link:hover,.submenu-link.active{color:var(--p-pink);background:rgba(213,61,102,0.03);padding-left:22px;}
.btn-logout{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;width:100%;padding:12px;border-radius:12px;font-weight:800;font-size:0.85rem;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);}
.btn-logout:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(213,61,102,0.2);}
.main-content{margin-left:260px;padding:40px;min-height:100vh;}
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px;}
.profile-header-btn{width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #fff;cursor:pointer;transition:all 0.4s;background:#fff;}
.profile-header-btn:hover{transform:scale(1.08) translateY(-2px);box-shadow:0 8px 20px rgba(213,61,102,0.15);border-color:var(--p-pink);}
.profile-header-btn img{width:100%;height:100%;object-fit:cover;}
.stats-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:10px;margin-bottom:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.stats-scroll-wrapper::-webkit-scrollbar{height:6px;}
.stats-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.stats-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.stats-row{display:flex;gap:16px;min-width:max-content;}
.stat-card-item{min-width:220px;max-width:280px;flex:0 0 auto;}
.card-3d{background:#fff;border-radius:22px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 8px 24px rgba(213,61,102,0.03);transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);padding:20px;height:100%;position:relative;overflow:hidden;}
.card-3d:hover{transform:translateY(-8px) scale(1.01);box-shadow:0 22px 45px rgba(213,61,102,0.14);border-color:var(--p-pink);}
.stat-card{display:flex;align-items:center;gap:14px;}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:all 0.4s;flex-shrink:0;}
.stat-icon-purple{background:linear-gradient(135deg,#FFF0F3,#FFE4E9);color:#D53D66;}
.stat-icon-blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-icon-pink{background:linear-gradient(135deg,#fdf2f8,#fce7f3);color:#db2777;}
.stat-content{flex:1;min-width:0;overflow:hidden;}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:2px;line-height:1.2;}
.stat-title{font-size:0.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;}
.stat-subtitle{font-size:0.68rem;color:#a0aec0;font-weight:600;margin-top:2px;}
.tab-filter-bar{display:flex;gap:10px;margin-bottom:25px;flex-wrap:wrap;}
.tab-btn{padding:10px 20px;border-radius:14px;border:2px solid #e2e8f0;background:#fff;color:#4a5568;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.4s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
.tab-btn:hover{border-color:var(--p-pink);color:var(--p-pink);}
.tab-btn.active{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);box-shadow:0 4px 12px rgba(213,61,102,0.2);}
.tab-btn .tab-count{background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:50px;font-size:0.75rem;}
.search-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:25px;flex-wrap:wrap;}
.search-form-flex{display:flex;align-items:center;gap:10px;flex:1;min-width:300px;}
.search-input-wrapper{position:relative;flex:1;}
.search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;z-index:2;}
.search-input-main{width:100%;border:2px solid #e2e8f0;border-radius:14px;padding:12px 18px 12px 44px;font-weight:600;font-size:0.9rem;color:#1e293b;transition:all 0.4s;background:#fff;}
.search-input-main:focus{outline:none;border-color:var(--p-pink);box-shadow:0 0 0 4px rgba(213,61,102,0.08);}
.btn-search-icon{background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:12px 16px;color:#94a3b8;cursor:pointer;transition:all 0.4s;display:flex;align-items:center;justify-content:center;}
.btn-search-icon:hover{border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px);}
.table-scroll-wrapper{width:100%;overflow-x:auto;overflow-y:hidden;border-radius:20px;scrollbar-width:thin;scrollbar-color:var(--p-pink) #f1f5f9;}
.table-scroll-wrapper::-webkit-scrollbar{height:8px;}
.table-scroll-wrapper::-webkit-scrollbar-track{background:#f1f5f9;border-radius:10px;}
.table-scroll-wrapper::-webkit-scrollbar-thumb{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));border-radius:10px;}
.data-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0;}
.data-table thead th{background:#fff;padding:16px 20px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;white-space:nowrap;border:none;border-bottom:2px solid #f1f5f9;text-align:left;}
.data-table thead th:first-child{padding-left:24px;}
.data-table thead th:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr{transition:all 0.2s ease;}
.data-table tbody td{padding:16px 20px;border:none;border-bottom:1px solid #f1f5f9;vertical-align:middle;white-space:nowrap;}
.data-table tbody td:first-child{padding-left:24px;}
.data-table tbody td:last-child{padding-right:24px;text-align:center;}
.data-table tbody tr:nth-child(even){background-color:#FFF8F0;}
.data-table tbody tr:nth-child(odd){background-color:#fff;}
.data-table tbody tr:hover{background-color:#FFEDD5!important;transform:scale(1.002);}
.td-sesi-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-order-id{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-paket{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;font-weight:600;}
.td-jadwal{font-weight:700;font-size:0.85rem;color:var(--text-dark);}
.td-jam{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.fotografer-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:50px;background:#dbeafe;color:#2563eb;font-size:0.75rem;font-weight:700;}
.fotografer-missing{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:50px;background:#f1f5f9;color:#94a3b8;font-size:0.75rem;font-weight:700;}
.btn-action-circle{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;transition:all 0.4s;border:1.5px solid #eef2f6;background:#fff;font-size:0.85rem;text-decoration:none;margin:0 2px;cursor:pointer;}
.btn-action-view{color:#D53D66;border-color:#FFE4E9;}
.btn-action-view:hover{background:#D53D66;color:#fff;transform:translateY(-2px);}
.btn-action-assign{color:#d97706;border-color:#fef3c7;}
.btn-action-assign:hover{background:#d97706;color:#fff;transform:translateY(-2px);}
.btn-action-cancel{color:#dc2626;border-color:#fee2e2;}
.btn-action-cancel:hover{background:#dc2626;color:#fff;transform:translateY(-2px);}
.btn-action-download{color:#059669;border-color:#d1fae5;}
.btn-action-download:hover{background:#059669;color:#fff;transform:translateY(-2px);}
.pagination-wrapper{display:flex;justify-content:space-between;align-items:center;margin-top:30px;padding:20px 24px;background:#fff;border-radius:20px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 4px 15px rgba(213,61,102,0.04);}
.pagination-info{font-size:0.85rem;color:#718096;font-weight:600;}
.pagination-info span{color:var(--p-pink);font-weight:700;}
.pagination-nav{display:flex;gap:6px;align-items:center;}
.page-link-pag{display:flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 14px;border-radius:12px;background:#fff;border:2px solid #FFF5F7;color:#4a5568;font-weight:700;font-size:0.9rem;text-decoration:none;transition:all 0.4s;}
.page-link-pag:hover{background:var(--light-pink);border-color:var(--p-pink);color:var(--p-pink);transform:translateY(-2px);}
.page-link-pag.active-pag{background:linear-gradient(135deg,var(--p-pink),var(--d-pink))!important;color:#fff!important;border-color:var(--p-pink)!important;box-shadow:0 4px 12px rgba(213,61,102,0.3);}
.page-link-pag.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
.fade-in-up{animation:fadeIn 0.5s ease-out;}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;}
.modal-overlay.show{display:flex;}
.modal-content-custom{background:#fff;border-radius:24px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;animation:modalIn 0.3s ease;}
.modal-header-custom{padding:24px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;}
.modal-title-custom{font-size:1.2rem;font-weight:800;color:var(--text-dark);}
.modal-close-custom{background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all 0.3s;}
.modal-close-custom:hover{background:#f1f5f9;color:var(--text-dark);}
.modal-body-custom{padding:24px;}
@keyframes modalIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
@media(max-width:992px){.main-content{margin-left:0;padding:20px;}.sidebar{transform:translateX(-100%);}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="sidebar-menu-wrapper">
<a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Admin</span></a>
<ul class="nav-menu">
<li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>

<!-- DATA MASTER -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuMaster"><span><i class="bi bi-folder-fill me-2"></i> Data Master</span><i class="bi bi-chevron-down small icon-chevron"></i></a>
<div class="submenu" id="submenuMaster">
<ul class="list-unstyled">
<li><a href="../../Master/Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
<li><a href="../../Master/Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
<li><a href="../../Master/Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
<li><a href="../../Master/Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
<li><a href="../../Master/Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
<li><a href="../../Master/Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
<li><a href="../../Master/Barang Cetak/list.php" class="submenu-link"><i class="bi bi-printer-fill me-2"></i>Barang Cetak</a></li>
</ul>
</div>
</li>

<!-- TRANSAKSI -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuTransaksi"><span><i class="bi bi-cart-fill me-2"></i> Transaksi</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuTransaksi">
<ul class="list-unstyled">
<li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
<li><a href="../../Transaksi/Booking/list.php" class="submenu-link"><i class="bi bi-calendar-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
</ul>
</div>
</li>

<!-- SESI FOTO (ACTIVE) -->
<li class="nav-item">
<a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuSesi"><span><i class="bi bi-camera-reels-fill me-2"></i> Sesi Foto</span><i class="bi bi-chevron-up small icon-chevron" style="transform:rotate(180deg);"></i></a>
<div class="submenu show" id="submenuSesi">
<ul class="list-unstyled">
<li><a href="list.php" class="submenu-link active"><i class="bi bi-eye-fill me-2"></i>Lihat Sesi Foto</a></li>
</ul>
</div>
</li>

<li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Landing Page</span></a></li>
</ul>
</div>
<div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
<div class="dashboard-header">
<div><h3 class="fw-bold mb-1">Sesi Foto</h3><p class="text-muted small mb-0">Kelola dan pantau semua sesi foto pelanggan.</p></div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<div class="stats-scroll-wrapper animate-fade-in">
<div class="stats-row">
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-purple"><i class="bi bi-camera-fill"></i></div><div class="stat-content"><div class="stat-title">Total Sesi</div><div class="stat-val"><?= $stats['total']??0 ?> Sesi</div><div class="stat-subtitle">Semua sesi foto</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-hourglass-split"></i></div><div class="stat-content"><div class="stat-title">Menunggu</div><div class="stat-val"><?= $stats['menunggu']??0 ?> Sesi</div><div class="stat-subtitle">Belum diproses</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-blue"><i class="bi bi-person-plus"></i></div><div class="stat-content"><div class="stat-title">Belum Assign</div><div class="stat-val"><?= $stats['belum_assign']??0 ?> Sesi</div><div class="stat-subtitle">Perlu fotografer</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Selesai</div><div class="stat-val"><?= $stats['selesai']??0 ?> Sesi</div><div class="stat-subtitle">Sudah dipotret</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-x-octagon-fill"></i></div><div class="stat-content"><div class="stat-title">Dibatalkan</div><div class="stat-val"><?= $stats['dibatalkan']??0 ?> Sesi</div><div class="stat-subtitle">Tidak jadi</div></div></div></div></div>
</div>
</div>

<div class="tab-filter-bar">
<a href="list.php?tab=semua" class="tab-btn <?= $tab_filter==='semua'?'active':'' ?>"><i class="bi bi-grid-fill"></i> Semua <span class="tab-count"><?= $stats['total']??0 ?></span></a>
<a href="list.php?tab=menunggu" class="tab-btn <?= $tab_filter==='menunggu'?'active':'' ?>"><i class="bi bi-hourglass-split"></i> Menunggu <span class="tab-count"><?= $stats['menunggu']??0 ?></span></a>
<a href="list.php?tab=belum_assign" class="tab-btn <?= $tab_filter==='belum_assign'?'active':'' ?>"><i class="bi bi-person-plus"></i> Belum Assign <span class="tab-count"><?= $stats['belum_assign']??0 ?></span></a>
<a href="list.php?tab=selesai" class="tab-btn <?= $tab_filter==='selesai'?'active':'' ?>"><i class="bi bi-check-circle-fill"></i> Selesai <span class="tab-count"><?= $stats['selesai']??0 ?></span></a>
<a href="list.php?tab=dibatalkan" class="tab-btn <?= $tab_filter==='dibatalkan'?'active':'' ?>"><i class="bi bi-x-octagon-fill"></i> Dibatalkan <span class="tab-count"><?= $stats['dibatalkan']??0 ?></span></a>
</div>

<div class="search-filter-bar">
<form method="GET" class="search-form-flex" id="mainSearchForm">
<input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter) ?>">
<div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari nama pelanggan, no. sesi, paket, ruangan, atau fotografer..." value="<?= htmlspecialchars($cari) ?>"></div>
<button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
</form>
</div>

<div class="card-3d mb-4" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead><tr><th>No. Sesi</th><th>Pelanggan</th><th>Paket & Detail</th><th>Jadwal</th><th>Fotografer</th><th>Status Sesi</th><th>Status Order</th><th class="text-center">Aksi</th></tr></thead>
<tbody>
<?php
$no=$offset+1;
$has_data = false;
if($query && $query !== false):
while($row=sqlsrv_fetch_array($query,SQLSRV_FETCH_ASSOC)):
$has_data = true;
$statusSesi=getStatusSesiLabel((int)$row['Status_Sesi']);
$statusOrder=getStatusOrderLabel((int)$row['Status_Order']);
$tanggal_val=$row['Tanggal_Jadwal'];
if(is_object($tanggal_val)&&method_exists($tanggal_val,'format')){$tgl_format=$tanggal_val->format('d M Y');}else{$tgl_format=$tanggal_val;}
$jam_mulai_str=(is_object($row['Jam_Mulai'])&&method_exists($row['Jam_Mulai'],'format'))?$row['Jam_Mulai']->format('H:i'):(is_string($row['Jam_Mulai'])?substr($row['Jam_Mulai'],0,5):'-');
$jam_selesai_str=(is_object($row['Jam_Selesai'])&&method_exists($row['Jam_Selesai'],'format'))?$row['Jam_Selesai']->format('H:i'):(is_string($row['Jam_Selesai'])?substr($row['Jam_Selesai'],0,5):'-');
$has_fotografer=!empty($row['ID_Karyawan']);
$nama_fotografer=$row['Nama_Fotografer']??null;
$has_file=!empty($row['File_Hasil']);
$is_menunggu=((int)$row['Status_Sesi']===0);
?>
<tr class="fade-in-up" data-id="<?= $row['ID_Sesi_Foto'] ?>">
<td><div class="td-sesi-id">#<?= str_pad((int)$row['ID_Sesi_Foto'],5,'0',STR_PAD_LEFT) ?></div><div class="td-order-id">Order #<?= str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT) ?></div></td>
<td><div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div><div class="td-customer-contact"><?= htmlspecialchars($row['NoHp_Pelanggan']??'-') ?></div></td>
<td><div class="td-paket"><?= htmlspecialchars($row['Nama_Paket']) ?></div><div class="td-detail"><?= htmlspecialchars($row['Nama_Ruangan']) ?> &bull; <?= $row['Durasi_Waktu'] ?> menit</div></td>
<td><div class="td-jadwal"><?= $tgl_format ?></div><div class="td-jam"><?= $jam_mulai_str ?> - <?= $jam_selesai_str ?></div></td>
<td><?php if($has_fotografer):?><span class="fotografer-badge"><i class="bi bi-person-fill"></i><?= htmlspecialchars($nama_fotografer) ?></span><?php else:?><span class="fotografer-missing"><i class="bi bi-person-x me-1"></i>Belum diassign</span><?php endif;?></td>
<td><span class="badge-status" style="background:<?= $statusSesi[2] ?>;color:<?= $statusSesi[1] ?>"><span class="badge-dot" style="background:<?= $statusSesi[1] ?>"></span><?= $statusSesi[0] ?></span><?php if($has_file):?><div class="td-detail mt-1" style="color:#059669"><i class="bi bi-image me-1"></i>Hasil tersedia</div><?php endif;?></td>
<td><span class="badge-status" style="background:<?= $statusOrder[2] ?>;color:<?= $statusOrder[1] ?>"><span class="badge-dot" style="background:<?= $statusOrder[1] ?>"></span><?= $statusOrder[0] ?></span></td>
<td>
<button class="btn-action-circle btn-action-view" onclick="bukaDetail(<?= (int)$row['ID_Sesi_Foto'] ?>)" title="Lihat Detail"><i class="bi bi-eye"></i></button>
<?php if($is_menunggu && !$has_fotografer):?><button class="btn-action-circle btn-action-assign" onclick="konfirmasiAssign(<?= (int)$row['ID_Sesi_Foto'] ?>)" title="Assign Fotografer"><i class="bi bi-person-plus"></i></button><?php endif;?>
<?php if($is_menunggu):?><button class="btn-action-circle btn-action-cancel" onclick="konfirmasiBatal(<?= (int)$row['ID_Sesi_Foto'] ?>)" title="Batalkan Sesi"><i class="bi bi-x-circle"></i></button><?php endif;?>
<?php if((int)$row['Status_Sesi']===1 && $has_file):?><a href="../../uploads/sesi_foto/<?= htmlspecialchars($row['File_Hasil']) ?>" target="_blank" class="btn-action-circle btn-action-download" title="Unduh Hasil Foto"><i class="bi bi-download"></i></a><?php endif;?>
</td>
</tr>
<?php endwhile;endif;?>
<?php if(!$has_data):?>
<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color:#cbd5e1"></i><p class="fw-bold">Tidak ada sesi foto yang sesuai.</p><p class="small">Belum ada sesi foto yang tercatat.</p></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
<?php if($total_halaman>1):?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset+1 ?></span> - <span><?= min($offset+$limit,$total_records) ?></span> dari <span><?= $total_records ?></span> sesi</div>
<nav class="pagination-nav">
<?php if($halaman>1):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman-1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif;?>
<?php $start_page=max(1,$halaman-2);$end_page=min($total_halaman,$halaman+2);if($start_page>1){echo'<a class="page-link-pag" href="list.php?halaman=1&tab='.$tab_filter.'&cari='.urlencode($cari).'">1</a>';if($start_page>2)echo'<span class="page-link-pag disabled">...</span>';}for($i=$start_page;$i<=$end_page;$i++):?><a class="page-link-pag <?= ($halaman==$i)?'active-pag':'' ?>" href="list.php?halaman=<?= $i ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>"><?= $i ?></a><?php endfor;if($end_page<$total_halaman){if($end_page<$total_halaman-1)echo'<span class="page-link-pag disabled">...</span>';echo'<a class="page-link-pag" href="list.php?halaman='.$total_halaman.'&tab='.$tab_filter.'&cari='.urlencode($cari).'">'.$total_halaman.'</a>';}?>
<?php if($halaman<$total_halaman):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman+1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif;?>
</nav>
</div>
<?php elseif($total_records>0):?>
<div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> sesi</div></div>
<?php endif;?>
</div>
</div>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="modalDetail"><div class="modal-content-custom"><div class="modal-header-custom"><div class="modal-title-custom"><i class="bi bi-camera" style="color:var(--p-pink);margin-right:8px"></i> Detail Sesi Foto</div><button class="modal-close-custom" onclick="tutupModal('modalDetail')">&times;</button></div><div class="modal-body-custom" id="detailContent"></div><div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:20px 24px;display:flex;justify-content:flex-end;gap:12px"><button class="btn-search-icon" style="padding:12px 24px;font-weight:700;color:#4a5568" onclick="tutupModal('modalDetail')">Tutup</button></div></div></div>

<!-- MODAL ASSIGN FOTOGRAFER -->
<div class="modal-overlay" id="modalAssign"><div class="modal-content-custom" style="max-width:450px"><div class="modal-header-custom"><div class="modal-title-custom"><i class="bi bi-person-plus" style="color:var(--p-pink);margin-right:8px"></i> Assign Fotografer</div><button class="modal-close-custom" onclick="tutupModal('modalAssign')">&times;</button></div><div class="modal-body-custom"><p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:20px">Pilih fotografer untuk menangani sesi foto ini:</p><form id="formAssign" method="POST" action=""><input type="hidden" name="ajax_assign" value="1"><input type="hidden" name="id_sesi" id="assignSesiId"><div style="margin-bottom:20px"><label style="display:block;font-size:0.9rem;font-weight:700;color:var(--text-dark);margin-bottom:10px">Fotografer</label><select name="id_fotografer" id="selectFotografer" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:14px;font-family:inherit;font-weight:600;font-size:0.9rem;cursor:pointer" required><option value="">-- Pilih Fotografer --</option><?php foreach($fotografer_list as $fg):?><option value="<?= (int)$fg['ID_Karyawan'] ?>"><?= htmlspecialchars($fg['Nama_Karyawan']) ?> (@<?= htmlspecialchars($fg['Username_Karyawan']??$fg['Nama_Karyawan']) ?>)</option><?php endforeach;?></select></div></form></div><div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:20px 24px;display:flex;justify-content:flex-end;gap:12px"><button class="btn-search-icon" style="padding:12px 24px;font-weight:700;color:#4a5568" onclick="tutupModal('modalAssign')">Batal</button><button class="btn-search-icon" style="background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);padding:12px 24px;font-weight:800" onclick="submitAssign()"><i class="bi bi-check-lg me-1"></i> Simpan</button></div></div></div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)';}}});});
function bukaModal(id){document.getElementById(id).classList.add('show')}
function tutupModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(modal=>{modal.addEventListener('click',function(e){if(e.target===this)tutupModal(this.id);});});

function bukaDetail(idSesi){
const row=document.querySelector('tr[data-id="'+idSesi+'"]');
if(!row)return;
const cells=row.querySelectorAll('td');
const sesiId=cells[0].querySelector('.td-sesi-id').innerText;
const orderId=cells[0].querySelector('.td-order-id').innerText;
const customer=cells[1].querySelector('.td-customer').innerText;
const contact=cells[1].querySelector('.td-customer-contact').innerText;
const paket=cells[2].querySelector('.td-paket').innerText;
const detail=cells[2].querySelector('.td-detail').innerText;
const jadwal=cells[3].querySelector('.td-jadwal').innerText;
const jam=cells[3].querySelector('.td-jam').innerText;
const fotografer=cells[4].innerText.trim();
const statusSesi=cells[5].querySelector('.badge-status').innerText.trim();
const statusOrder=cells[6].querySelector('.badge-status').innerText.trim();
const html='<div style="display:grid;gap:16px"><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">No. Sesi</div><div style="font-weight:800;color:var(--p-pink);font-size:1.1rem">'+sesiId+'</div></div><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">No. Order</div><div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">'+orderId+'</div></div></div><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Customer</div><div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">'+customer+'</div><div style="font-size:0.8rem;color:#718096;font-weight:600">'+contact+'</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Paket</div><div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">'+paket+'</div><div style="font-size:0.8rem;color:#718096;font-weight:600">'+detail+'</div></div><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Jadwal</div><div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">'+jadwal+'</div><div style="font-size:0.8rem;color:#718096;font-weight:600">'+jam+'</div></div></div><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Fotografer</div><div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">'+fotografer+'</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Status Sesi</div><div style="font-weight:700;font-size:0.95rem">'+statusSesi+'</div></div><div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0"><div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Status Order</div><div style="font-weight:700;font-size:0.95rem">'+statusOrder+'</div></div></div></div>';
document.getElementById('detailContent').innerHTML=html;
bukaModal('modalDetail');
}

function konfirmasiAssign(idSesi){
Swal.fire({title:'Assign Fotografer?',text:'Pilih fotografer yang akan menangani sesi foto ini.',icon:'question',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Lanjutkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){document.getElementById('assignSesiId').value=idSesi;bukaModal('modalAssign');}});
}

function submitAssign(){
const idSesi=document.getElementById('assignSesiId').value;
const idFotografer=document.getElementById('selectFotografer').value;
if(!idFotografer){Swal.fire({icon:'warning',title:'Pilih Fotografer!',text:'Silakan pilih fotografer terlebih dahulu.',confirmButtonColor:'#D53D66'});return;}

const formData=new FormData();
formData.append('ajax_assign','1');
formData.append('id_sesi',idSesi);
formData.append('id_fotografer',idFotografer);

fetch('list.php',{method:'POST',body:formData})
.then(res=>res.json())
.then(data=>{
if(data.success){
Swal.fire({icon:'success',title:'Berhasil!',text:data.message,confirmButtonColor:'#D53D66',timer:1500,showConfirmButton:false}).then(()=>{tutupModal('modalAssign');location.reload();});
}else{
Swal.fire({icon:'error',title:'Gagal!',text:data.message,confirmButtonColor:'#D53D66'});
}
})
.catch(err=>{
Swal.fire({icon:'error',title:'Error!',text:'Terjadi kesalahan koneksi.',confirmButtonColor:'#D53D66'});
});
}

function konfirmasiBatal(idSesi){
Swal.fire({title:'Batalkan Sesi?',text:'Sesi foto akan dibatalkan. Pelanggan akan diberitahu.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#718096',confirmButtonText:'Ya, Batalkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='batal.php?id='+idSesi;}});
}

function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php';}});}
function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php';}});}
function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'});}
function updateLiveClock(){const now=new Date();const days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];document.getElementById('live-clock').innerText=`${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;}
setInterval(updateLiveClock,1000);updateLiveClock();
</script>
</body>
</html>