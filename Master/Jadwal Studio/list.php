<?php
session_start();
include '../../koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

$JAM_BUKA = "08:00";
$JAM_TUTUP = "20:00";
$JAM_OPERASIONAL = "Senin - Minggu | " . $JAM_BUKA . " - " . $JAM_TUTUP . " WIB";

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

function isJadwalValid($jam_mulai, $jam_selesai, $jam_buka, $jam_tutup) {
    return ($jam_mulai >= $jam_buka && $jam_selesai <= $jam_tutup && $jam_mulai < $jam_selesai);
}

function getErrorJadwal($jam_mulai, $jam_selesai, $jam_buka, $jam_tutup) {
    $errors = [];
    if ($jam_mulai < $jam_buka) $errors[] = "Mulai < " . $jam_buka;
    if ($jam_selesai > $jam_tutup) $errors[] = "Selesai > " . $jam_tutup;
    if ($jam_mulai >= $jam_selesai) $errors[] = "Mulai >= Selesai";
    return implode(", ", $errors);
}

function isJadwalLewat($tanggal) {
    $tgl = is_object($tanggal) && method_exists($tanggal, 'format') ? $tanggal->format('Y-m-d') : date('Y-m-d', strtotime($tanggal));
    return $tgl < date('Y-m-d');
}

$admin_data = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : "";
$ruangan_filter = isset($_GET['ruangan']) ? trim($_GET['ruangan']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "tanggal_desc";

$tab_view = isset($_GET['tab']) ? trim($_GET['tab']) : 'aktif';
if ($tab_view !== 'terhapus') $tab_view = 'aktif';

$stats = safe_sqlsrv_fetch($conn, 
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Status_Jadwal = 0 AND Status = 1 AND Is_Deleted = 0 THEN 1 ELSE 0 END) as tersedia,
        SUM(CASE WHEN Status_Jadwal = 1 AND Is_Deleted = 0 THEN 1 ELSE 0 END) as terpesan,
        SUM(CASE WHEN Status_Jadwal = 2 AND Is_Deleted = 0 THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END) as terhapus
    FROM Jadwal_Studio"
) ?? ['total' => 0, 'tersedia' => 0, 'terpesan' => 0, 'selesai' => 0, 'terhapus' => 0];

$jadwal_invalid = [];
$count_invalid = 0;
if ($tab_view == 'aktif') {
    $jadwal_invalid = safe_sqlsrv_fetch_all($conn,
        "SELECT j.ID_Jadwal, j.Jam_Mulai, j.Jam_Selesai, r.Nama_Ruangan, j.Tanggal_Jadwal
        FROM Jadwal_Studio j
        LEFT JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
        WHERE j.Is_Deleted = 0 AND j.Status = 1
        AND (j.Jam_Mulai < ? OR j.Jam_Selesai > ? OR j.Jam_Mulai >= j.Jam_Selesai)
        ORDER BY j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC",
        [$JAM_BUKA, $JAM_TUTUP]
    );
    $count_invalid = count($jadwal_invalid);
}

$list_ruangan = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Ruangan ASC"
);

$conditions = [];
$params = [];

if ($tab_view == 'aktif') {
    $conditions[] = "j.Is_Deleted = 0";
} else {
    $conditions[] = "j.Is_Deleted = 1";
}

if (!empty($cari)) {
    $conditions[] = "(r.Nama_Ruangan LIKE ? OR j.Keterangan LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
}
if ($status_filter !== "" && $tab_view == 'aktif') {
    $conditions[] = "j.Status_Jadwal = ?";
    $params[] = (int)$status_filter;
}
if ($ruangan_filter !== "") {
    $conditions[] = "j.ID_Ruangan = ?";
    $params[] = (int)$ruangan_filter;
}

$order_clause = "j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC";
if ($sort == "tanggal_asc") { $order_clause = "j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC"; }
elseif ($sort == "ruangan_asc") { $order_clause = "r.Nama_Ruangan ASC, j.Tanggal_Jadwal DESC"; }
elseif ($sort == "ruangan_desc") { $order_clause = "r.Nama_Ruangan DESC, j.Tanggal_Jadwal DESC"; }
elseif ($sort == "waktu_asc") { $order_clause = "j.Jam_Mulai ASC"; }
elseif ($sort == "waktu_desc") { $order_clause = "j.Jam_Mulai DESC"; }

$count_sql = "SELECT COUNT(*) AS total FROM Jadwal_Studio j LEFT JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan WHERE " . implode(" AND ", $conditions);
$total_records = safe_sqlsrv_count($conn, $count_sql, $params);
$total_halaman = ceil($total_records / $limit);

$list_sql = "SELECT 
    j.ID_Jadwal,
    j.ID_Ruangan,
    j.Tanggal_Jadwal,
    j.Jam_Mulai,
    j.Jam_Selesai,
    j.Keterangan,
    j.Status_Jadwal,
    j.Status,
    j.Is_Deleted,
    r.Nama_Ruangan
FROM Jadwal_Studio j
LEFT JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
WHERE " . implode(" AND ", $conditions) . "
ORDER BY " . $order_clause . "
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_list = array_merge($params, [$offset, $limit]);
$daftar_jadwal = safe_sqlsrv_fetch_all($conn, $list_sql, $params_list);

function formatTanggal($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        return $dateObj->format('d M Y');
    }
    return date('d M Y', strtotime($dateObj));
}

function formatWaktu($timeObj) {
    if (is_object($timeObj) && method_exists($timeObj, 'format')) {
        return $timeObj->format('H:i');
    }
    return substr($timeObj, 0, 5);
}

function getHariIndo($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) {
        $hari = $dateObj->format('l');
    } else {
        $hari = date('l', strtotime($dateObj));
    }
    $daftar = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    return $daftar[$hari] ?? $hari;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Jadwal Studio – SpotLight Studio</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3;
            --light-pink: #FFE4E9; --accent-pink: #E85D84;
            --text-dark: #1e1e24; --text-muted: #718096;
            --sidebar-bg: #ffffff; --body-bg: #f8fafc;
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255,228,233,0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
        .sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); text-decoration: none; letter-spacing: -1px; margin-bottom: 40px; display: block; }
        .sidebar-brand span { color: var(--text-dark); font-size: 0.85rem; font-weight: 600; }
        .sidebar-menu-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 20px; scrollbar-width: none; }
        .sidebar-menu-wrapper::-webkit-scrollbar { display: none; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link-custom { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; color: #4a5568; font-weight: 700; text-decoration: none; border-radius: 12px; font-size: 0.9rem; transition: var(--transition-3d); }
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--light-pink); color: var(--p-pink); transform: translateX(4px); }
        .submenu { list-style: none; padding-left: 20px; margin-top: 5px; display: none; transition: var(--transition-3d); }
        .submenu.show { display: block !important; }
        .submenu-link { display: flex; align-items: center; padding: 8px 18px; color: #718096; font-weight: 600; font-size: 0.85rem; text-decoration: none; border-radius: 10px; transition: 0.3s; }
        .submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213,61,102,0.03); padding-left: 22px; }
        .btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
        .btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213,61,102,0.2); }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff; }
        .profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213,61,102,0.15); border-color: var(--p-pink); }
        .profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
        .jam-operasional-badge { display: inline-flex; align-items: center; gap: 10px; padding: 10px 20px; background: linear-gradient(135deg, #fff5f6, #ffecef); border-radius: 50px; border: 2px solid rgba(213,61,102,0.15); font-weight: 700; font-size: 0.85rem; color: var(--p-pink); transition: var(--transition-3d); box-shadow: 0 4px 15px rgba(213,61,102,0.08); }
        .jam-operasional-badge:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 8px 25px rgba(213,61,102,0.15); border-color: var(--p-pink); }
        .jam-operasional-badge i { font-size: 1.1rem; animation: pulse-clock 2s ease-in-out infinite; }
        @keyframes pulse-clock { 0%,100% { transform: scale(1); } 50% { transform: scale(1.15); } }
        .tab-view-container { display: flex; gap: 8px; margin-bottom: 20px; }
        .tab-view-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: var(--transition-3d); border: 2px solid #e2e8f0; background: #ffffff; color: #64748b; cursor: pointer; }
        .tab-view-btn:hover { border-color: var(--p-pink); color: var(--p-pink); }
        .tab-view-btn.active { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border-color: var(--p-pink); box-shadow: 0 4px 15px rgba(213,61,102,0.2); }
        .tab-view-btn .tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; }
        .tab-view-btn.active .tab-badge { background: rgba(255,255,255,0.3); color: #ffffff; }
        .tab-view-btn:not(.active) .tab-badge { background: var(--s-pink); color: var(--p-pink); }
        .alert-invalid { background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 2px solid #fecaca; border-radius: 16px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; transition: var(--transition-3d); }
        .alert-invalid:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239,68,68,0.1); }
        .alert-invalid-icon { width: 40px; height: 40px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; color: #dc2626; font-size: 1.2rem; flex-shrink: 0; }
        .alert-invalid-text { flex: 1; }
        .alert-invalid-text strong { color: #dc2626; font-size: 0.9rem; }
        .alert-invalid-text span { color: #991b1b; font-size: 0.8rem; }
        .alert-invalid-btn { background: #dc2626; color: white; border: none; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: var(--transition-3d); flex-shrink: 0; }
        .alert-invalid-btn:hover { background: #b91c1c; transform: translateY(-2px); }
        .stats-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 10px; margin-bottom: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
        .stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
        .stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .stats-row { display: flex; gap: 16px; min-width: max-content; }
        .stat-card-item { min-width: 220px; max-width: 280px; flex: 0 0 auto; }
        .card-3d { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 8px 24px rgba(213,61,102,0.03); transition: var(--transition-3d); padding: 20px; height: 100%; position: relative; overflow: hidden; }
        .card-3d:hover { transform: translateY(-8px) scale(1.01); box-shadow: 0 22px 45px rgba(213,61,102,0.14); border-color: var(--p-pink); }
        .stat-card { display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0; }
        .stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
        .stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
        .stat-icon-orange { background: linear-gradient(135deg, #fff7ed, #fed7aa); color: #ea580c; }
        .stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
        .stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
        .stat-content { flex: 1; min-width: 0; overflow: hidden; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }
        .search-filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
        .search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 300px; }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; }
        .search-input-main { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
        .search-input-main:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213,61,102,0.08); }
        .btn-filter-modal { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 12px 24px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; cursor: pointer; transition: var(--transition-3d); white-space: nowrap; }
        .btn-filter-modal:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(213,61,102,0.3); }
        .btn-search-icon { background: #ffffff; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; color: #94a3b8; cursor: pointer; transition: var(--transition-3d); display: flex; align-items: center; justify-content: center; }
        .btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .btn-reg-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; color: #ffffff !important; border-radius: 14px !important; padding: 12px 28px !important; font-weight: 800 !important; border: none !important; box-shadow: 0 8px 20px rgba(213,61,102,0.25) !important; transition: var(--transition-3d) !important; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-reg-header:hover { background: linear-gradient(135deg, #E85D84, var(--p-pink)) !important; transform: translateY(-4px) scale(1.03) !important; box-shadow: 0 12px 25px rgba(213,61,102,0.4) !important; }
        .table-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; border-radius: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
        .data-table { width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0; }
        .data-table thead th { background: #ffffff; padding: 16px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; white-space: nowrap; border: none; border-bottom: 2px solid #f1f5f9; text-align: left; }
        .data-table thead th:first-child { padding-left: 24px; }
        .data-table thead th:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody td { padding: 16px 20px; border: none; border-bottom: 1px solid #f1f5f9; vertical-align: middle; white-space: nowrap; }
        .data-table tbody td:first-child { padding-left: 24px; }
        .data-table tbody td:last-child { padding-right: 24px; text-align: center; }
        .data-table tbody tr:nth-child(even) { background-color: #FFF8F0; }
        .data-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .data-table tbody tr:hover { background-color: #FFEDD5 !important; transform: scale(1.002); }
        .td-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
        .td-deskripsi { font-size: 0.8rem; color: #718096; max-width: 200px; white-space: normal; }
        .badge-ruangan { font-size: 0.72rem; font-weight: 700; padding: 6px 14px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #475569; border: 1px solid #e2e8f0; }
        .badge-status { font-size: 0.72rem; font-weight: 700; padding: 6px 14px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-tersedia { background: #ecfdf5; color: #059669; }
        .badge-terpesan { background: #fff7ed; color: #ea580c; }
        .badge-selesai { background: #eff6ff; color: #2563eb; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-tersedia .badge-dot { background: #059669; }
        .badge-terpesan .badge-dot { background: #ea580c; }
        .badge-selesai .badge-dot { background: #2563eb; }
        .badge-invalid { font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 50px; display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; margin-left: 8px; }
        .badge-invalid i { font-size: 0.7rem; }
        .badge-terhapus { font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 50px; display: inline-flex; align-items: center; gap: 4px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-terhapus i { font-size: 0.7rem; }
        .btn-action-circle { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: var(--transition-3d); border: 1.5px solid #eef2f6; background: #ffffff; font-size: 0.85rem; text-decoration: none; margin: 0 2px; cursor: pointer; }
        .btn-action-edit { color: var(--p-pink); border-color: #FFE4E9; }
        .btn-action-edit:hover { background: var(--p-pink); color: #ffffff; transform: translateY(-2px); }
        .btn-action-edit.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
        .btn-action-delete { color: #dc2626; border-color: #fee2e2; }
        .btn-action-delete:hover { background: #dc2626; color: #ffffff; transform: translateY(-2px); }
        .btn-action-delete.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
        .btn-action-restore { color: #059669; border-color: #d1fae5; }
        .btn-action-restore:hover { background: #059669; color: #ffffff; transform: translateY(-2px); }
        .btn-action-restore.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
        .btn-action-hard { color: #7f1d1d; border-color: #fca5a5; }
        .btn-action-hard:hover { background: #7f1d1d; color: #ffffff; transform: translateY(-2px); }
        .btn-toggle-soft { color: #059669; border-color: #d1fae5; background: #ecfdf5; }
        .btn-toggle-soft:hover { background: #059669; color: #ffffff; transform: translateY(-2px); }
        .btn-toggle-soft.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
        .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding: 20px 24px; background: #ffffff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 4px 15px rgba(213,61,102,0.04); }
        .pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
        .pagination-info span { color: var(--p-pink); font-weight: 700; }
        .pagination-nav { display: flex; gap: 6px; align-items: center; }
        .page-link-pag { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 14px; border-radius: 12px; background: #ffffff; border: 2px solid #FFF5F7; color: #4a5568; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: var(--transition-3d); }
        .page-link-pag:hover { background: var(--light-pink); border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
        .page-link-pag.active-pag { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; color: #ffffff !important; border-color: var(--p-pink) !important; box-shadow: 0 4px 12px rgba(213,61,102,0.3); }
        .page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeIn 0.5s ease-out; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }
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
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg);"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="../Ruangan/list.php" class="submenu-link"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
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
        <div class="dashboard-header">
            <div>
                <h3 class="fw-bold mb-1">Master Jadwal Studio</h3>
                <p class="text-muted small mb-0">Kelola operasional dan ketersediaan slot studio.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="jam-operasional-badge" title="Jam Operasional Studio"><i class="bi bi-clock-fill"></i><?= $JAM_OPERASIONAL; ?></span>
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
            </div>
        </div>

        <div class="breadcrumb-custom" style="display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: 0.85rem; font-weight: 600;">
            <a href="../../Role/Admin/index.php" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
            <i class="bi bi-chevron-right" style="color: #cbd5e1; font-size: 0.7rem;"></i>
            <a href="./list.php" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;">Data Master</a>
            <i class="bi bi-chevron-right" style="color: #cbd5e1; font-size: 0.7rem;"></i>
            <span class="active" style="color: var(--p-pink);">Jadwal Studio</span>
        </div>

        <div class="tab-view-container">
            <a href="?tab=aktif<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?><?= !empty($ruangan_filter) ? '&ruangan='.$ruangan_filter : '' ?><?= !empty($sort) ? '&sort='.$sort : '' ?>" class="tab-view-btn <?= $tab_view == 'aktif' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                Jadwal Aktif
                <span class="tab-badge"><?= ($stats['tersedia'] ?? 0) + ($stats['terpesan'] ?? 0) + ($stats['selesai'] ?? 0) ?></span>
            </a>
            <a href="?tab=terhapus<?= !empty($cari) ? '&cari='.urlencode($cari) : '' ?><?= !empty($ruangan_filter) ? '&ruangan='.$ruangan_filter : '' ?><?= !empty($sort) ? '&sort='.$sort : '' ?>" class="tab-view-btn <?= $tab_view == 'terhapus' ? 'active' : '' ?>">
                <i class="bi bi-trash"></i>
                Jadwal Terhapus
                <span class="tab-badge"><?= $stats['terhapus'] ?? 0 ?></span>
            </a>
        </div>

        <?php if ($tab_view == 'aktif'): ?>
        <?php if ($count_invalid > 0): ?>
        <div class="alert-invalid" id="alertInvalid">
            <div class="alert-invalid-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="alert-invalid-text">
                <strong>Perhatian!</strong> Terdapat <strong><?= $count_invalid ?></strong> jadwal di luar jam operasional (<?= $JAM_BUKA ?> - <?= $JAM_TUTUP ?> WIB).
                <br><span>Silakan perbaiki jadwal tersebut untuk memastikan akurasi sistem booking.</span>
            </div>
            <button class="alert-invalid-btn" onclick="scrollToInvalid()"><i class="bi bi-eye me-1"></i> Lihat</button>
        </div>
        <?php endif; ?>

        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-calendar3"></i></div><div class="stat-content"><div class="stat-title">Total Jadwal</div><div class="stat-val"><?= ($stats['tersedia'] ?? 0) + ($stats['terpesan'] ?? 0) + ($stats['selesai'] ?? 0) ?> Jadwal</div><div class="stat-subtitle">Semua slot aktif</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Tersedia</div><div class="stat-val"><?= $stats['tersedia'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Siap di-booking</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-bookmark-check-fill"></i></div><div class="stat-content"><div class="stat-title">Terpesan</div><div class="stat-val"><?= $stats['terpesan'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Sedang aktif</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-blue"><i class="bi bi-check-all"></i></div><div class="stat-content"><div class="stat-title">Selesai</div><div class="stat-val"><?= $stats['selesai'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Sesi foto selesai</div></div></div></div></div>
            </div>
        </div>
        <?php else: ?>
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-trash-fill"></i></div><div class="stat-content"><div class="stat-title">Jadwal Terhapus</div><div class="stat-val"><?= $stats['terhapus'] ?? 0 ?> Jadwal</div><div class="stat-subtitle">Soft delete / arsip</div></div></div></div></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="tab" value="<?= $tab_view ?>">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="ruangan" id="hiddenRuangan" value="<?= htmlspecialchars($ruangan_filter) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari ruangan atau keterangan..." value="<?= htmlspecialchars($cari) ?>"></div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()"><i class="bi bi-funnel-fill me-2"></i>Filter<i class="bi bi-chevron-down ms-2"></i></button>
                <button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
            </form>
            <?php if ($tab_view == 'aktif'): ?>
            <a href="add.php" class="btn-reg-header text-decoration-none"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Jadwal</a>
            <?php endif; ?>
        </div>

        <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px; background: #f8fafc;">
            <i class="bi bi-info-circle-fill me-2 text-info"></i>
            <span class="small fw-bold text-muted">
                <?php if ($tab_view == 'aktif'): ?>
                <strong>Info:</strong> Jadwal studio menentukan slot waktu yang tersedia untuk booking. 
                Status <strong>Tersedia</strong> = bisa di-booking, <strong>Terpesan</strong> = sudah ada order, <strong>Selesai</strong> = sesi foto selesai.
                Jam operasional: <strong><?= $JAM_BUKA ?> - <?= $JAM_TUTUP ?> WIB</strong>.
                <?php else: ?>
                <strong>Info:</strong> Jadwal yang sudah dihapus (soft delete) tidak tampil di booking. 
                Anda bisa <strong>Restore</strong> jadwal yang belum terpesan, atau <strong>Hard Delete</strong> untuk menghapus permanen.
                <?php endif; ?>
            </span>
        </div>

        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead><tr><th>No</th><th>Ruangan</th><th>Tanggal</th><th>Waktu (WIB)</th><th>Keterangan</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        <?php if (!empty($daftar_jadwal)): foreach($daftar_jadwal as $idx => $j):
                            $jam_mulai_str = formatWaktu($j['Jam_Mulai']);
                            $jam_selesai_str = formatWaktu($j['Jam_Selesai']);
                            $is_valid = isJadwalValid($jam_mulai_str, $jam_selesai_str, $JAM_BUKA, $JAM_TUTUP);
                            $error_msg = $is_valid ? '' : getErrorJadwal($jam_mulai_str, $jam_selesai_str, $JAM_BUKA, $JAM_TUTUP);
                            $is_lewat = isJadwalLewat($j['Tanggal_Jadwal']);
                            $status_jadwal = (int)($j['Status_Jadwal'] ?? 0);
                            $is_terpesan = ($status_jadwal == 1);
                            $is_selesai = ($status_jadwal == 2);
                            $is_terhapus = (int)($j['Is_Deleted'] ?? 0) == 1;

                            $badge_class = ''; $status_text = '';
                            if ($status_jadwal == 0) { $badge_class = 'badge-tersedia'; $status_text = 'Tersedia'; }
                            elseif ($status_jadwal == 1) { $badge_class = 'badge-terpesan'; $status_text = 'Terpesan'; }
                            else { $badge_class = 'badge-selesai'; $status_text = 'Selesai'; }
                        ?>
                        <tr class="fade-in-up <?= !$is_valid ? 'table-danger' : '' ?>" id="jadwal-<?= $j['ID_Jadwal'] ?>">
                            <td><?= $offset + $idx + 1 ?></td>
                            <td>
                                <span class="badge-ruangan"><i class="bi bi-door-open-fill"></i><?= htmlspecialchars($j['Nama_Ruangan'] ?? 'Ruangan Tidak Ditemukan') ?></span>
                                <?php if (!$is_valid): ?><span class="badge-invalid"><i class="bi bi-exclamation-circle-fill"></i>Invalid</span><?php endif; ?>
                                <?php if ($is_terhapus): ?><span class="badge-terhapus"><i class="bi bi-trash-fill"></i>Terhapus</span><?php endif; ?>
                            </td>
                            <td>
                                <div class="td-nama"><?= formatTanggal($j['Tanggal_Jadwal']) ?></div>
                                <div class="td-deskripsi"><?= getHariIndo($j['Tanggal_Jadwal']) ?></div>
                                <?php if ($is_lewat): ?><div class="td-deskripsi" style="color: #dc2626;"><i class="bi bi-calendar-x-fill me-1"></i>Sudah lewat</div><?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $jam_mulai_str ?></strong> - <strong><?= $jam_selesai_str ?></strong>
                                <?php if (!$is_valid): ?><br><small style="color: #dc2626; font-weight: 600;"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error_msg ?></small><?php endif; ?>
                            </td>
                            <td class="td-deskripsi"><?= htmlspecialchars($j['Keterangan'] ?? '-') ?></td>
                            <td><span class="badge-status <?= $badge_class ?>"><span class="badge-dot"></span><?= $status_text ?></span></td>
                            <td>
                                <?php if (!$is_terhapus): ?>
                                    <!-- TAB AKTIF -->
                                    <?php if (!$is_terpesan && !$is_selesai && !$is_lewat): ?>
                                        <a href="edit.php?id=<?= $j['ID_Jadwal'] ?>" class="btn-action-circle btn-action-edit" title="Edit Jadwal"><i class="bi bi-pencil"></i></a>
                                        <button class="btn-action-circle btn-toggle-soft" onclick="softDelete(<?= $j['ID_Jadwal'] ?>, '<?= htmlspecialchars($j['Nama_Ruangan'] ?? '') ?>')" title="Jadwal Aktif — Klik untuk Soft Delete"><i class="bi bi-toggle-on"></i></button>
                                        <button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $j['ID_Jadwal'] ?>, '<?= htmlspecialchars($j['Nama_Ruangan'] ?? '') ?>')" title="Hapus Jadwal (Soft Delete)"><i class="bi bi-trash"></i></button>
                                    <?php else: ?>
                                        <span class="btn-action-circle btn-action-edit disabled" title="<?= $is_selesai ? 'Sesi sudah selesai' : ($is_terpesan ? 'Sudah terpesan customer' : 'Tanggal sudah lewat') ?> — tidak bisa edit"><i class="bi bi-pencil"></i></span>
                                        <span class="btn-action-circle btn-toggle-soft disabled" title="<?= $is_selesai ? 'Sesi sudah selesai' : ($is_terpesan ? 'Sudah terpesan customer' : 'Tanggal sudah lewat') ?> — tidak bisa hapus"><i class="bi bi-toggle-on"></i></span>
                                        <span class="btn-action-circle btn-action-delete disabled" title="<?= $is_selesai ? 'Sesi sudah selesai' : ($is_terpesan ? 'Sudah terpesan customer' : 'Tanggal sudah lewat') ?> — tidak bisa hapus"><i class="bi bi-trash"></i></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- TAB TERHAPUS -->
                                    <?php if (!$is_terpesan && !$is_selesai): ?>
                                        <button class="btn-action-circle btn-action-restore" onclick="restoreJadwal(<?= $j['ID_Jadwal'] ?>, '<?= htmlspecialchars($j['Nama_Ruangan'] ?? '') ?>')" title="Restore Jadwal (Kembalikan)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php else: ?>
                                        <span class="btn-action-circle btn-action-restore disabled" title="Jadwal pernah <?= $is_selesai ? 'selesai' : 'terpesan' ?> — tidak bisa restore"><i class="bi bi-arrow-counterclockwise"></i></span>
                                    <?php endif; ?>
                                    <button class="btn-action-circle btn-action-hard" onclick="hardDelete(<?= $j['ID_Jadwal'] ?>, '<?= htmlspecialchars($j['Nama_Ruangan'] ?? '') ?>')" title="Hapus Permanen (Hard Delete)"><i class="bi bi-trash-fill"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i><p class="fw-bold">Tidak ada data jadwal studio yang sesuai.</p><p class="small"><?= $tab_view == 'aktif' ? 'Coba ubah filter atau tambah jadwal baru.' : 'Belum ada jadwal yang dihapus.' ?></p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> jadwal studio</div>
                <nav class="pagination-nav">
                    <?php $base_qs = "tab=" . $tab_view . "&cari=" . urlencode($cari) . "&status=" . $status_filter . "&ruangan=" . urlencode($ruangan_filter) . "&sort=" . $sort; ?>
                    <?php if ($halaman > 1): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&<?= $base_qs ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
                    <?php $start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2);
                    if ($start_page > 1) { echo '<a class="page-link-pag" href="list.php?halaman=1&' . $base_qs . '">1</a>'; if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; }
                    for ($i = $start_page; $i <= $end_page; $i++): ?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&<?= $base_qs ?>"><?= $i ?></a><?php endfor;
                    if ($end_page < $total_halaman) { if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&' . $base_qs . '">' . $total_halaman . '</a>'; } ?>
                    <?php if ($halaman < $total_halaman): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&<?= $base_qs ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> jadwal studio</div></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;">
                <div class="modal-header" style="border: none; padding: 24px 24px 16px; background: #ffffff;"><h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" style="padding: 0 24px 20px; background: #ffffff;">
                    <div class="mb-3"><label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">URUT BERDASARKAN</label><select class="form-select" id="modalSort" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;"><option value="tanggal_desc" <?= $sort == 'tanggal_desc' ? 'selected' : '' ?>>Tanggal Terbaru</option><option value="tanggal_asc" <?= $sort == 'tanggal_asc' ? 'selected' : '' ?>>Tanggal Terlama</option><option value="ruangan_asc" <?= $sort == 'ruangan_asc' ? 'selected' : '' ?>>Ruangan A - Z</option><option value="ruangan_desc" <?= $sort == 'ruangan_desc' ? 'selected' : '' ?>>Ruangan Z - A</option><option value="waktu_asc" <?= $sort == 'waktu_asc' ? 'selected' : '' ?>>Waktu Pagi - Malam</option><option value="waktu_desc" <?= $sort == 'waktu_desc' ? 'selected' : '' ?>>Waktu Malam - Pagi</option></select></div>
                    <div class="mb-3"><label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">RUANGAN</label><select class="form-select" id="modalRuangan" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;"><option value="" <?= $ruangan_filter === '' ? 'selected' : '' ?>>Semua Ruangan</option><?php foreach ($list_ruangan as $r): ?><option value="<?= $r['ID_Ruangan'] ?>" <?= $ruangan_filter == $r['ID_Ruangan'] ? 'selected' : '' ?>><?= htmlspecialchars($r['Nama_Ruangan']) ?></option><?php endforeach; ?></select></div>
                    <?php if ($tab_view == 'aktif'): ?>
                    <div><label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">STATUS JADWAL</label><select class="form-select" id="modalStatus" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600;"><option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option><option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Tersedia</option><option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Terpesan</option><option value="2" <?= $status_filter === '2' ? 'selected' : '' ?>>Selesai</option></select></div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="border: none; padding: 0 24px 24px; background: #ffffff; display: flex; gap: 12px;"><button type="button" class="btn btn-secondary" style="flex: 1; background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="resetFilter()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</button><button type="button" class="btn btn-danger" style="flex: 1; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 20px; font-weight: 700;" onclick="applyFilter()"><i class="bi bi-check-lg me-2"></i>Terapkan</button></div>
            </div>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
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
        var filterModal;
        function bukaModalFilter() { filterModal = new bootstrap.Modal(document.getElementById('modalFilterData')); filterModal.show(); }
        function applyFilter() {
            document.getElementById('hiddenSort').value = document.getElementById('modalSort').value;
            document.getElementById('hiddenRuangan').value = document.getElementById('modalRuangan').value;
            <?php if ($tab_view == 'aktif'): ?>
            document.getElementById('hiddenStatus').value = document.getElementById('modalStatus').value;
            <?php endif; ?>
            document.getElementById('mainSearchForm').submit();
        }
        function resetFilter() {
            document.getElementById('modalSort').value = 'tanggal_desc';
            document.getElementById('modalRuangan').value = '';
            <?php if ($tab_view == 'aktif'): ?>
            document.getElementById('modalStatus').value = '';
            document.getElementById('hiddenStatus').value = '';
            <?php endif; ?>
            document.getElementById('hiddenSort').value = 'tanggal_desc';
            document.getElementById('hiddenRuangan').value = '';
            document.getElementById('mainSearchForm').submit();
        }
        function scrollToInvalid() {
            const firstInvalid = document.querySelector('.badge-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.closest('tr').style.backgroundColor = '#fef2f2';
                setTimeout(() => { firstInvalid.closest('tr').style.backgroundColor = ''; }, 2000);
            }
        }
        function softDelete(id, nama) {
            Swal.fire({ title: 'Hapus Jadwal?', text: 'Anda akan menghapus jadwal untuk ruangan "' + nama + '" (soft delete). Jadwal akan pindah ke tab "Jadwal Terhapus".', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_jadwal.php?aksi=soft_delete&id=' + id; } });
        }
        function restoreJadwal(id, nama) {
            Swal.fire({ title: 'Restore Jadwal?', text: 'Anda akan mengembalikan jadwal untuk ruangan "' + nama + '" ke daftar aktif.', icon: 'question', showCancelButton: true, confirmButtonColor: '#059669', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Restore', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_jadwal.php?aksi=restore&id=' + id; } });
        }
        function hardDelete(id, nama) {
            Swal.fire({ title: 'Hapus Permanen?', text: 'Anda akan menghapus PERMANEN jadwal untuk ruangan "' + nama + '". Data tidak bisa dikembalikan!', icon: 'error', showCancelButton: true, confirmButtonColor: '#7f1d1d', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Hapus Permanen', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = 'action_jadwal.php?aksi=hard_delete&id=' + id; } });
        }
        function confirmLogout(e) {
            e.preventDefault();
            Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = '../../logout.php'; } });
        }
        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama publik.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) { window.location.href = '../../index.php'; } });
        }
        function updateLiveClock() {
            const now = new Date();
            const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
            const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
            document.getElementById('live-clock').innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' - ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0') + ' WIB';
        }
        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = ""; let t_icon = "success"; let t_title = "Berhasil!";
        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Jadwal studio baru berhasil ditambahkan!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data jadwal studio berhasil diperbarui!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'soft_delete') { msg = "<?= $_GET['message'] ?? 'Jadwal studio berhasil dihapus (soft delete)!' ?>"; t_title = "Dihapus"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'hard_delete') { msg = "Jadwal studio berhasil dihapus permanen!"; t_title = "Hard Delete Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'restore') { msg = "Jadwal studio berhasil dikembalikan!"; t_title = "Restore Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }
        Swal.fire({ icon: t_icon, title: t_title, text: msg, confirmButtonColor: '#D53D66' });
    </script>
    <?php endif; ?>
</body>
</html>