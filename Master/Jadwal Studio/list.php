<?php
session_start();
include '../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// *Penyesuaian: Lokasi direktori foto Karyawan diperbaiki dari pelanggan menjadi karyawan
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// AUTO-EXPIRED & AUTO-HAPUS JADWAL LAMPAU (Sesuai Validasi & Integritas DB)
// =====================================================
// 1. Hapus permanen jadwal hari kemarin/lampau yang TIDAK memiliki relasi booking di tabel junction (Hard Delete)
$hard_delete_past_sql = "DELETE FROM Jadwal_Studio 
                         WHERE Tanggal_Jadwal < CAST(GETDATE() AS DATE) 
                           AND ID_Jadwal NOT IN (SELECT DISTINCT ID_Jadwal FROM Order_Jadwal)";
sqlsrv_query($conn, $hard_delete_past_sql);

// 2. Soft delete jadwal hari kemarin/lampau yang MEMILIKI relasi booking (Is_Deleted = 1 & Status = 0)
// demi menjaga integritas data transaksional agar database tidak error
$soft_delete_past_sql = "UPDATE Jadwal_Studio 
                         SET Is_Deleted = 1, Status = 0, Modified_By = ?, Modified_Date = GETDATE() 
                         WHERE Tanggal_Jadwal < CAST(GETDATE() AS DATE) AND Is_Deleted = 0";
sqlsrv_query($conn, $soft_delete_past_sql, [$nama_admin]);

// =====================================================
// PAGINATION & FILTER
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$filter_ruangan = isset($_GET['ruangan']) ? (int)$_GET['ruangan'] : 0;
$filter_paket = isset($_GET['paket']) ? (int)$_GET['paket'] : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : "";
$filter_tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "tanggal_asc";

// =====================================================
// STATISTIK
// =====================================================
$q_stats = sqlsrv_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Status = 1 AND Tanggal_Jadwal >= CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN Status = 0 OR Tanggal_Jadwal < CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) as nonaktif,
        SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END) as terhapus
    FROM Jadwal_Studio
");
$stats = ['total' => 0, 'aktif' => 0, 'nonaktif' => 0, 'terhapus' => 0];
if ($q_stats !== false) {
    $stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC) ?: $stats;
}

// =====================================================
// QUERY LIST DATA
// =====================================================
$conditions = array("j.Is_Deleted = 0");
$params = array();

if (!empty($cari)) {
    $conditions[] = "(r.Nama_Ruangan LIKE ? OR j.Keterangan LIKE ? OR r.ID_Ruangan IN (SELECT pr.ID_Ruangan FROM Paket_Ruangan pr JOIN Paket_Foto pf ON pr.ID_Paket = pf.ID_Paket WHERE pf.Nama_Paket LIKE ?))";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($filter_ruangan > 0) {
    $conditions[] = "j.ID_Ruangan = ?";
    $params[] = $filter_ruangan;
}
if ($filter_paket > 0) {
    // Filter berdasarkan subquery Paket_Ruangan
    $conditions[] = "j.ID_Ruangan IN (SELECT pr.ID_Ruangan FROM Paket_Ruangan pr WHERE pr.ID_Paket = ?)";
    $params[] = $filter_paket;
}
if ($filter_status !== "") {
    $conditions[] = "j.Status = ?";
    $params[] = (int)$filter_status;
}
if (!empty($filter_tanggal)) {
    $conditions[] = "j.Tanggal_Jadwal = ?";
    $params[] = $filter_tanggal;
}

// Kriteria urutan disesuaikan
$order_clause = "j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC, r.Nama_Ruangan ASC";
if ($sort == "tanggal_desc") { $order_clause = "j.Tanggal_Jadwal DESC, j.Jam_Mulai ASC"; }
elseif ($sort == "ruangan_asc") { $order_clause = "r.Nama_Ruangan ASC, j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC"; }
elseif ($sort == "paket_asc") { 
    $order_clause = "(SELECT STRING_AGG(pf.Nama_Paket, ', ') FROM Paket_Ruangan pr JOIN Paket_Foto pf ON pr.ID_Paket = pf.ID_Paket WHERE pr.ID_Ruangan = r.ID_Ruangan AND pf.Is_Deleted = 0) ASC, j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC"; 
}

// Hitung total data kueri count diperkecil
$sql_count = "SELECT COUNT(*) AS total FROM Jadwal_Studio j 
              INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan 
              WHERE " . implode(" AND ", $conditions);
$query_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0;
$total_halaman = 0;
if ($query_count !== false) {
    $row_count = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC);
    $total_records = $row_count['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// Ambil data (Ditambahkan kueri DATEDIFF MINUTE untuk menghitung durasi dinamis langsung dari database)
$sql_list = "SELECT 
    j.ID_Jadwal, j.ID_Ruangan, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai,
    j.Keterangan, j.Status, j.Status_Jadwal,
    r.Nama_Ruangan,
    DATEDIFF(MINUTE, j.Jam_Mulai, j.Jam_Selesai) AS Durasi_Waktu,
    (SELECT STRING_AGG(pf.Nama_Paket, ', ') 
     FROM Paket_Ruangan pr 
     JOIN Paket_Foto pf ON pr.ID_Paket = pf.ID_Paket 
     WHERE pr.ID_Ruangan = r.ID_Ruangan AND pf.Is_Deleted = 0) as Nama_Paket
FROM Jadwal_Studio j
INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan
WHERE " . implode(" AND ", $conditions) . " 
ORDER BY " . $order_clause . " 
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_list = $params;
$params_list[] = $offset;
$params_list[] = $limit;

$query = sqlsrv_query($conn, $sql_list, $params_list);

// Ambil data untuk filter dropdown
$q_ruangan = sqlsrv_query($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Ruangan");
$q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");

function fmtTgl($d) {
    return (is_object($d) && method_exists($d, 'format')) ? $d->format('d M Y') : date('d M Y', strtotime($d));
}
function fmtJam($d) {
    return (is_object($d) && method_exists($d, 'format')) ? $d->format('H:i') : date('H:i', strtotime($d));
}
function hariIndo($d) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $idx = date('w', strtotime(is_object($d) ? $d->format('Y-m-d') : $d));
    return $hari[$idx];
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
.stat-card-item { min-width: 200px; max-width: 260px; flex: 0 0 auto; }
.card-3d {
    background: #ffffff; border-radius: 22px;
    border: 1px solid rgba(255, 228, 233, 0.8);
    box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03);
    transition: var(--transition-3d); padding: 20px;
    height: 100%; position: relative; overflow: hidden;
}
.card-3d:hover {
    transform: translateY(-8px) scale(1.01);
    box-shadow: 0 22px 45px rgba(213, 61, 102, 0.14);
    border-color: var(--p-pink);
}
.stat-card { display: flex; align-items: center; gap: 14px; }
.stat-icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0;
}
.stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
.stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
.stat-icon-orange { background: linear-gradient(135deg, #fff7ed, #fed7aa); color: #ea580c; }
.stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
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
.btn-filter-modal {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: #ffffff; border: none; border-radius: 14px;
    padding: 12px 24px; font-weight: 700; font-size: 0.9rem;
    display: inline-flex; align-items: center; cursor: pointer;
    transition: var(--transition-3d); white-space: nowrap;
}
.btn-filter-modal:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(213, 61, 102, 0.3); }
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
.btn-generate {
    background: linear-gradient(135deg, #059669, #047857) !important;
    color: #ffffff !important; border-radius: 14px !important;
    padding: 12px 28px !important; font-weight: 800 !important;
    border: none !important; box-shadow: 0 8px 20px rgba(5, 150, 105, 0.25) !important;
    transition: var(--transition-3d) !important; display: inline-flex;
    align-items: center; gap: 8px; text-decoration: none;
}
.btn-generate:hover {
    transform: translateY(-4px) scale(1.03) !important;
    box-shadow: 0 12px 25px rgba(5, 150, 105, 0.4) !important;
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
    width: 100%; min-width: 950px; border-collapse: separate; border-spacing: 0;
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
.data-table tbody tr:hover { background-color: #FFEDD5 !important; transform: scale(1.002); }

.td-ruangan { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
.td-paket { font-size: 0.8rem; color: #718096; font-weight: 600; }
.td-tanggal { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
.td-hari { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
.td-waktu { font-weight: 800; font-size: 1rem; color: var(--p-pink); }
.td-durasi { font-size: 0.8rem; color: #718096; font-weight: 600; }
.td-keterangan { font-size: 0.8rem; color: #4a5568; max-width: 200px; white-space: normal; font-weight: 600; }

.badge-status {
    font-size: 0.72rem; font-weight: 700; padding: 6px 14px;
    border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
}
.badge-aktif { background: #ecfdf5; color: #059669; }
.badge-nonaktif { background: #fef2f2; color: #dc2626; }
.badge-libur { background: #fff7ed; color: #ea580c; }
.badge-expired { background: #f1f5f9; color: #94a3b8; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.badge-aktif .badge-dot { background: #059669; }
.badge-nonaktif .badge-dot { background: #dc2626; }
.badge-libur .badge-dot { background: #ea580c; }
.badge-expired .badge-dot { background: #94a3b8; }

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #e2e8f0;
    transition: .4s;
    border-radius: 24px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider { background-color: var(--p-pink); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-switch input:disabled + .toggle-slider { background-color: #cbd5e1; cursor: not-allowed; }

.btn-submit {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: #ffffff; border: none; border-radius: 14px;
    padding: 14px 32px; font-weight: 800; font-size: 0.95rem;
    transition: var(--transition-3d); display: inline-flex;
    align-items: center; gap: 8px;
}
.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35);
    color: #ffffff;
}
.btn-batal {
    background: #f1f5f9; color: #475569; border: none;
    border-radius: 14px; padding: 14px 32px;
    font-weight: 800; font-size: 0.95rem;
    transition: var(--transition-3d); display: inline-flex;
    align-items: center; gap: 8px; text-decoration: none;
}
.btn-batal:hover {
    background: #e2e8f0; color: #1e293b;
    transform: translateY(-3px);
}

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

/* FILTER MODAL POPUP */
.modal-dialog-centered { display: flex; align-items: center; min-height: calc(100% - 3.5rem); }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in-up { animation: fadeIn 0.5s ease-out; }

@media (max-width: 992px) {
    .main-content { margin-left: 0; padding: 20px; }
    .sidebar { transform: translateX(-100%); }
}
</style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
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
            <div>
                <h3 class="fw-bold mb-1">Master Jadwal Studio</h3>
                <p class="text-muted small mb-0">Kelola slot jadwal pemotretan per ruangan dan paket.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;">
                    <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
                </span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda">
                    <img src="<?= $foto_admin_src ?>" alt="Admin Profil">
                </div>
            </div>
        </div>

        <!-- STATISTIK CARDS -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-week-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Total Jadwal</div>
                                <div class="stat-val"><?= $stats['total'] ?? 0 ?> Jadwal</div>
                                <div class="stat-subtitle">Aktif di sistem</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Jadwal Aktif</div>
                                <div class="stat-val"><?= $stats['aktif'] ?? 0 ?> Jadwal</div>
                                <div class="stat-subtitle">Bisa dipesan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-orange"><i class="bi bi-x-circle-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Jadwal Nonaktif</div>
                                <div class="stat-val"><?= $stats['nonaktif'] ?? 0 ?> Jadwal</div>
                                <div class="stat-subtitle">Sudah lewat / Dinonaktifkan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card-item">
                    <div class="card-3d">
                        <div class="stat-card">
                            <div class="stat-icon stat-icon-red"><i class="bi bi-trash-fill"></i></div>
                            <div class="stat-content">
                                <div class="stat-title">Terhapus</div>
                                <div class="stat-val"><?= $stats['terhapus'] ?? 0 ?> Jadwal</div>
                                <div class="stat-subtitle">Soft deleted</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="ruangan" id="hiddenRuangan" value="<?= htmlspecialchars($filter_ruangan) ?>">
                <input type="hidden" name="paket" id="hiddenPaket" value="<?= htmlspecialchars($filter_paket) ?>">
                <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="hidden" name="tanggal" id="hiddenTanggal" value="<?= htmlspecialchars($filter_tanggal) ?>">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="cari" class="search-input-main" placeholder="Cari ruangan, paket, keterangan..." value="<?= htmlspecialchars($cari) ?>">
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
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Jadwal
            </a>
            <a href="action_jadwal.php?aksi=generate_7hari" class="btn-generate text-decoration-none" onclick="confirmGenerate(event)">
                <i class="bi bi-plus-circle-fill me-2"></i>Generate 7 Hari
            </a>
        </div>

        <!-- INFO TEXT -->
        <div class="alert alert-light border-2 border-dashed mb-3" style="border-color: #e2e8f0; border-radius: 14px; background: #f8fafc;">
            <i class="bi bi-info-circle-fill me-2 text-info"></i>
            <span class="small fw-bold text-muted">
                <strong>Info:</strong> Tema foto akan ditampilkan kepada pelanggan berdasarkan ruangan yang dipilih. 
                Kelola ruangan di menu <a href="../Ruangan/list.php" style="color: var(--p-pink);">Ruangan</a>.
            </span>
        </div>

        <!-- TABEL DATA -->
        <div class="card-3d mb-4" style="padding: 24px;">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Paket & Ruangan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Durasi</th>
                            <th>Keterangan</th>
                            <th>Aktif</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = $offset + 1;
                        if ($query && sqlsrv_has_rows($query)):
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                $is_expired = false;
                                $tgl_jadwal = is_object($row['Tanggal_Jadwal']) ? $row['Tanggal_Jadwal']->format('Y-m-d') : $row['Tanggal_Jadwal'];
                                
                                // *Penyesuaian WIB: Logika expired disesuaikan agar mengecek Tanggal + Jam secara bersamaan
                                $jam_mulai_check = is_object($row['Jam_Mulai']) ? $row['Jam_Mulai']->format('H:i:s') : $row['Jam_Mulai'];
                                $today_now = date('Y-m-d');
                                $time_now = date('H:i:s');

                                if ($tgl_jadwal < $today_now) {
                                    $is_expired = true;
                                } elseif ($tgl_jadwal == $today_now && $jam_mulai_check < $time_now) {
                                    $is_expired = true;
                                }

                                $is_libur = (stripos($row['Keterangan'] ?? '', 'libur') !== false);
                        ?>
                            <tr class="fade-in-up <?= $is_expired ? 'opacity-50' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="td-ruangan"><?= htmlspecialchars($row['Nama_Paket']) ?></div>
                                    <div class="td-paket"><i class="bi bi-door-open-fill me-1 text-danger"></i><?= htmlspecialchars($row['Nama_Ruangan']) ?></div>
                                </td>
                                <td>
                                    <div class="td-tanggal"><?= fmtTgl($row['Tanggal_Jadwal']) ?></div>
                                    <div class="td-hari"><?= hariIndo($row['Tanggal_Jadwal']) ?></div>
                                </td>
                                <td>
                                    <!-- Menampilkan format jam 24 jam Indonesia WIB tanpa format AM/PM -->
                                    <div class="td-waktu"><?= fmtJam($row['Jam_Mulai']) ?> - <?= fmtJam($row['Jam_Selesai']) ?></div>
                                </td>
                                <td>
                                    <div class="td-durasi"><?= $row['Durasi_Waktu'] ?? 0 ?> menit</div>
                                </td>
                                <td>
                                    <!-- BADGE SINKRON STATUS JADWAL -->
                                    <div class="mb-2">
                                        <?php if ($row['Status_Jadwal'] == 1): ?>
                                            <span class="badge bg-success text-white" style="font-size: 0.7rem; font-weight: 700; padding: 5px 10px; border-radius: 6px;">
                                                <i class="bi bi-bookmark-check-fill me-1"></i> Booked
                                            </span>
                                        <?php elseif ($row['Status_Jadwal'] == 2): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 0.7rem; font-weight: 700; padding: 5px 10px; border-radius: 6px;">
                                                <i class="bi bi-tools me-1"></i> Maintenance
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.7rem; font-weight: 700; padding: 5px 10px; border-radius: 6px;">
                                                <i class="bi bi-calendar-check me-1"></i> Tersedia
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="td-keterangan">
                                        <?php if ($is_libur): ?>
                                            <span class="badge badge-libur"><span class="badge-dot"></span> Libur</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($row['Keterangan'] ?? '-') ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge badge-expired"><span class="badge-dot"></span> Expired</span>
                                    <?php else: ?>
                                        <label class="toggle-switch">
                                            <!-- Proteksi: Nonaktifkan toggle jika status jadwal sudah dipesan (Booked) -->
                                            <input type="checkbox" <?= ($row['Status'] == 1) ? 'checked' : '' ?> 
                                                   <?= ($row['Status_Jadwal'] == 1) ? 'disabled' : '' ?>
                                                   onchange="toggleStatus(<?= $row['ID_Jadwal'] ?>, <?= $row['Status'] ?>, '<?= htmlspecialchars($row['Nama_Paket']) ?> - <?= htmlspecialchars($row['Nama_Ruangan']) ?> <?= fmtTgl($row['Tanggal_Jadwal']) ?>')">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <?php if ($row['Status_Jadwal'] == 1): ?>
                                            <div class="small text-muted mt-1" style="font-size:0.65rem; font-weight: 700;">Sesi Terpesan</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $row['ID_Jadwal'] ?>" class="btn-action-circle btn-action-edit" title="Edit Jadwal">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn-action-circle btn-action-delete" onclick="softDelete(<?= $row['ID_Jadwal'] ?>, '<?= htmlspecialchars($row['Nama_Paket']) ?> - <?= htmlspecialchars($row['Nama_Ruangan']) ?> <?= fmtTgl($row['Tanggal_Jadwal']) ?> <?= fmtJam($row['Jam_Mulai']) ?>')" title="Hapus Jadwal">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 mb-3 d-block" style="color: #cbd5e1;"></i>
                                    <p class="fw-bold">Tidak ada data jadwal studio yang sesuai.</p>
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
                    Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> jadwal
                </div>
                <nav class="pagination-nav">
                    <?php if ($halaman > 1): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>&ruangan=<?= $filter_ruangan ?>&paket=<?= $filter_paket ?>&status=<?= $filter_status ?>&tanggal=<?= $filter_tanggal ?>&sort=<?= $sort ?>" title="Sebelumnya">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php 
                    $start_page = max(1, $halaman - 2);
                    $end_page = min($total_halaman, $halaman + 2);

                    if ($start_page > 1) {
                        echo '<a class="page-link-pag" href="list.php?halaman=1&cari=' . urlencode($cari) . '&ruangan=' . $filter_ruangan . '&paket=' . $filter_paket . '&status=' . $filter_status . '&tanggal=' . $filter_tanggal . '&sort=' . $sort . '">1</a>';
                        if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&ruangan=<?= $filter_ruangan . '&paket=' . $filter_paket . '&status=' . $filter_status . '&tanggal=' . $filter_tanggal . '&sort=' . $sort ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; 

                    if ($end_page < $total_halaman) {
                        if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>';
                        echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&cari=' . urlencode($cari) . '&ruangan=' . $filter_ruangan . '&paket=' . $filter_paket . '&status=' . $filter_status . '&tanggal=' . $filter_tanggal . '&sort=' . $sort . '">' . $total_halaman . '</a>';
                    }
                    ?>

                    <?php if ($halaman < $total_halaman): ?>
                        <a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>&ruangan=<?= $filter_ruangan ?>&paket=<?= $filter_paket ?>&status=<?= $filter_status ?>&tanggal=<?= $filter_tanggal ?>&sort=<?= $sort ?>" title="Selanjutnya">
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
                    Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> jadwal
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- FILTER MODAL -->
    <div class="modal fade filter-modal" id="modalFilterData" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">PAKET</label>
                        <select class="filter-select" id="modalPaket">
                            <option value="0" <?= $filter_paket == 0 ? 'selected' : '' ?>>Semua Paket</option>
                            <?php 
                            sqlsrv_free_stmt($q_paket);
                            $q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");
                            while($p = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)): 
                            ?>
                                <option value="<?= $p['ID_Paket'] ?>" <?= $filter_paket == $p['ID_Paket'] ? 'selected' : '' ?>><?= htmlspecialchars($p['Nama_Paket']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">RUANGAN</label>
                        <select class="filter-select" id="modalRuangan">
                            <option value="0" <?= $filter_ruangan == 0 ? 'selected' : '' ?>>Semua Ruangan</option>
                            <?php 
                            sqlsrv_free_stmt($q_ruangan);
                            $q_ruangan = sqlsrv_query($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Ruangan");
                            while($r = sqlsrv_fetch_array($q_ruangan, SQLSRV_FETCH_ASSOC)): 
                            ?>
                                <option value="<?= $r['ID_Ruangan'] ?>" <?= $filter_ruangan == $r['ID_Ruangan'] ? 'selected' : '' ?>><?= htmlspecialchars($r['Nama_Ruangan']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">TANGGAL</label>
                        <input type="date" class="filter-select" id="modalTanggal" value="<?= htmlspecialchars($filter_tanggal) ?>">
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">STATUS AKTIF</label>
                        <select class="filter-select" id="modalStatus">
                            <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>Semua</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">URUTAN</label>
                        <select class="filter-select" id="modalSort">
                            <option value="tanggal_asc" <?= $sort == 'tanggal_asc' ? 'selected' : '' ?>>Tanggal ↑ (Terdekat)</option>
                            <option value="tanggal_desc" <?= $sort == 'tanggal_desc' ? 'selected' : '' ?>>Tanggal ↓ (Terjauh)</option>
                            <option value="ruangan_asc" <?= $sort == 'ruangan_asc' ? 'selected' : '' ?>>Ruangan A-Z</option>
                            <option value="paket_asc" <?= $sort == 'paket_asc' ? 'selected' : '' ?>>Paket A-Z</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
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
        document.getElementById('hiddenPaket').value = document.getElementById('modalPaket').value;
        document.getElementById('hiddenRuangan').value = document.getElementById('modalRuangan').value;
        document.getElementById('hiddenTanggal').value = document.getElementById('modalTanggal').value;
        document.getElementById('hiddenStatus').value = document.getElementById('modalStatus').value;
        document.getElementById('hiddenSort').value = document.getElementById('modalSort').value;
        document.getElementById('mainSearchForm').submit();
    }
    function resetFilter() {
        document.getElementById('modalPaket').value = '0';
        document.getElementById('modalRuangan').value = '0';
        document.getElementById('modalTanggal').value = '';
        document.getElementById('modalStatus').value = '';
        document.getElementById('modalSort').value = 'tanggal_asc';
        document.getElementById('hiddenPaket').value = '0';
        document.getElementById('hiddenRuangan').value = '0';
        document.getElementById('hiddenTanggal').value = '';
        document.getElementById('hiddenStatus').value = '';
        document.getElementById('hiddenSort').value = 'tanggal_asc';
        document.getElementById('mainSearchForm').submit();
    }

    // Toggle Status
    function toggleStatus(id, currentStatus, info) {
        const newStatus = currentStatus === 1 ? 0 : 1;
        const actionText = currentStatus === 1 ? 'menonaktifkan' : 'mengaktifkan';

        Swal.fire({
            title: 'Ubah Status Jadwal?',
            text: 'Anda akan ' + actionText + ' jadwal ' + info,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#D53D66',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Ubah',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_jadwal.php?aksi=toggle_status&id=' + id + '&status=' + newStatus;
            }
        });
    }

    // Soft Delete
    function softDelete(id, info) {
        Swal.fire({
            title: 'Hapus Jadwal?',
            text: 'Jadwal ' + info + ' akan dihapus (soft delete).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'action_jadwal.php?aksi=soft_delete&id=' + id;
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

    // Jam Real-Time 24-Jam WIB
    function updateLiveClock() {
        var clockEl = document.getElementById('live-clock');
        if (!clockEl) return;
        var now = new Date();
        var days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
        var months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        var dayName = days[now.getDay()];
        var day = now.getDate();
        var monthName = months[now.getMonth()];
        var year = now.getFullYear();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        var seconds = now.getSeconds();
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        clockEl.innerText = dayName + ', ' + day + ' ' + monthName + ' ' + year + ' - ' + hours + ':' + minutes + ':' + seconds + ' WIB';
    }
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    function confirmGenerate(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Generate Jadwal 7 Hari?',
            text: 'Sistem akan membuat jadwal otomatis untuk 7 hari ke depan berdasarkan paket dan ruangan yang valid.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Generate',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = e.target.href || e.target.closest('a').href;
            }
        });
        return false;
    }

    function bukaModalBiodata() {
        Swal.fire({
            title: '<?= htmlspecialchars($nama_admin) ?>',
            text: 'Administrator - SpotLight Studio',
            icon: 'info',
            confirmButtonColor: '#D53D66'
        });
    }
    </script>

    <!-- Notifikasi -->
    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        let msg = "";
        let t_icon = "success";
        let t_title = "Berhasil!";

        if ("<?= $_GET['status_sukses'] ?>" == 'tambah') msg = "Jadwal studio berhasil ditambahkan!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'edit') msg = "Data jadwal studio berhasil diperbarui!";
        else if ("<?= $_GET['status_sukses'] ?>" == 'toggle_status') { msg = "Status jadwal berhasil diubah!"; t_title = "Status Diubah"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'soft_delete') { msg = "Jadwal berhasil dihapus!"; t_title = "Hapus Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'generate') { msg = "<?= $_GET['message'] ?? 'Jadwal 7 hari berhasil digenerate!' ?>"; t_title = "Generate Berhasil"; }
        else if ("<?= $_GET['status_sukses'] ?>" == 'error') { msg = "<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>"; t_icon = "error"; t_title = "Gagal!"; }

        Swal.fire({
            icon: t_icon,
            title: t_title,
            text: msg,
            confirmButtonColor: '#D53D66'
        });
    </script>
    <?php endif; ?>
</body>
</html>