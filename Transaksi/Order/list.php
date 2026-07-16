<?php
session_start();
include '../../koneksi.php';

define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);
define('STATUS_DATA_AKTIF', 1);

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

$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$tab_filter = isset($_GET['tab']) ? trim($_GET['tab']) : "semua";
$tgl_dari = isset($_GET['tgl_dari']) ? trim($_GET['tgl_dari']) : "";
$tgl_sampai = isset($_GET['tgl_sampai']) ? trim($_GET['tgl_sampai']) : "";
$urut = isset($_GET['urut']) ? trim($_GET['urut']) : "terbaru";

// =====================================================
// STATISTIK ORDER
// =====================================================
// Kondisi dasar: order aktif (Status=1) dan sudah diverifikasi (Status_Order >= 1)
// Tapi UNTUK LIST BOOKING CUSTOMER, kita hanya peduli yang BELUM punya sesi foto

$q_stats = "
    SELECT 
        COUNT(*) as total,
        -- DP Terverifikasi: semua yang Status_Order=1 (termasuk yang sudah assign & belum)
        SUM(CASE WHEN o.Status_Order = 1 THEN 1 ELSE 0 END) as dp_terverifikasi,
        -- Menunggu Assign: Status_Order IN (1,3) tapi BELUM punya sesi foto aktif
        SUM(CASE WHEN o.Status_Order IN (1, 3) 
            AND NOT EXISTS (
                SELECT 1 FROM Sesi_Foto sf 
                WHERE sf.ID_Order = o.ID_Order 
                  AND sf.Status = 1 
                  AND sf.Status_Sesi IN (0, 1)
            ) THEN 1 ELSE 0 END) as menunggu_assign,
        SUM(CASE WHEN o.Status_Order = 2 THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN o.Status_Order = 3 THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN o.Status_Order = 4 THEN 1 ELSE 0 END) as dibatalkan,
        -- Expired: DP terverifikasi ATAU Lunas, belum assign, tapi jadwal sudah lewat
        SUM(CASE WHEN o.Status_Order IN (1, 3)
            AND NOT EXISTS (
                SELECT 1 FROM Sesi_Foto sf 
                WHERE sf.ID_Order = o.ID_Order 
                  AND sf.Status = 1 
                  AND sf.Status_Sesi IN (0, 1)
            )
            AND EXISTS (SELECT 1 FROM Order_Jadwal ojx WHERE ojx.ID_Order = o.ID_Order)
            AND NOT EXISTS (
                SELECT 1 FROM Order_Jadwal ojy 
                INNER JOIN Jadwal_Studio jy ON ojy.ID_Jadwal = jy.ID_Jadwal
                WHERE ojy.ID_Order = o.ID_Order AND jy.Status = 1 AND jy.Is_Deleted = 0
                  AND DATEADD(SECOND, DATEDIFF(SECOND, 0, jy.Jam_Selesai), CAST(jy.Tanggal_Jadwal AS DATETIME)) >= GETDATE()
            ) THEN 1 ELSE 0 END) as expired
    FROM [Order] o
    WHERE o.Status = 1 AND o.Status_Order >= 1
";
$stmt_stats = sqlsrv_query($conn, $q_stats);
$stats = ['total'=>0,'dp_terverifikasi'=>0,'menunggu_assign'=>0,'selesai'=>0,'lunas'=>0,'dibatalkan'=>0, 'expired'=>0];
if ($stmt_stats !== false) {
    $row = sqlsrv_fetch_array($stmt_stats, SQLSRV_FETCH_ASSOC);
    if ($row) $stats = $row;
}

// =====================================================
// FILTER KONDISI QUERY UTAMA
// =====================================================
$conditions = ["o.Status = 1 AND o.Status_Order >= 1"];
$params = [];

if ($tab_filter === 'dp_terverifikasi') {
    $conditions[] = "o.Status_Order = 1";
} elseif ($tab_filter === 'menunggu_assign') {
    // Status_Order IN (1,3) DAN belum punya sesi foto aktif
    $conditions[] = "o.Status_Order IN (1, 3) AND NOT EXISTS (
        SELECT 1 FROM Sesi_Foto sf 
        WHERE sf.ID_Order = o.ID_Order 
          AND sf.Status = 1 
          AND sf.Status_Sesi IN (0, 1)
    )";
} elseif ($tab_filter === 'selesai') {
    $conditions[] = "o.Status_Order = 2";
} elseif ($tab_filter === 'lunas') {
    $conditions[] = "o.Status_Order = 3";
} elseif ($tab_filter === 'dibatalkan') {
    $conditions[] = "o.Status_Order = 4";
} elseif ($tab_filter === 'terlewat') {
    $conditions[] = "o.Status_Order IN (1, 3) 
        AND NOT EXISTS (
            SELECT 1 FROM Sesi_Foto sf 
            WHERE sf.ID_Order = o.ID_Order 
              AND sf.Status = 1 
              AND sf.Status_Sesi IN (0, 1)
        )
        AND EXISTS (SELECT 1 FROM Order_Jadwal ojx WHERE ojx.ID_Order = o.ID_Order)
        AND NOT EXISTS (
            SELECT 1 FROM Order_Jadwal ojy 
            INNER JOIN Jadwal_Studio jy ON ojy.ID_Jadwal = jy.ID_Jadwal
            WHERE ojy.ID_Order = o.ID_Order AND jy.Status = 1 AND jy.Is_Deleted = 0
              AND DATEADD(SECOND, DATEDIFF(SECOND, 0, jy.Jam_Selesai), CAST(jy.Tanggal_Jadwal AS DATETIME)) >= GETDATE()
        )";
}

if (!empty($cari)) {
    $conditions[] = "(p.Nama_Pelanggan LIKE ? OR CAST(o.ID_Order AS VARCHAR) LIKE ? OR pk.Nama_Paket LIKE ?)";
    $params[] = "%$cari%"; $params[] = "%$cari%"; $params[] = "%$cari%";
}
if (!empty($tgl_dari) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_dari)) {
    $conditions[] = "CAST(o.Tanggal_Booking AS DATE) >= ?";
    $params[] = $tgl_dari;
}
if (!empty($tgl_sampai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_sampai)) {
    $conditions[] = "CAST(o.Tanggal_Booking AS DATE) <= ?";
    $params[] = $tgl_sampai;
}
$where = implode(" AND ", $conditions);

// =====================================================
// HITUNG TOTAL RECORDS
// =====================================================
$sql_count = "SELECT COUNT(*) AS total 
              FROM [Order] o 
              INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan 
              INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket 
              WHERE $where";
$q_count = sqlsrv_query($conn, $sql_count, $params);
$total_records = 0; $total_halaman = 0;
if ($q_count !== false) {
    $r = sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC);
    $total_records = $r['total'] ?? 0;
    $total_halaman = ceil($total_records / $limit);
}

// =====================================================
// SORTER QUERY
// =====================================================
$order_map = [
    'terbaru'        => 'o.Tanggal_Booking DESC',
    'terlama'        => 'o.Tanggal_Booking ASC',
    'nama_az'        => 'p.Nama_Pelanggan ASC',
    'nama_za'        => 'p.Nama_Pelanggan DESC',
    'total_tinggi'   => 'o.Total_Harga DESC',
    'total_rendah'   => 'o.Total_Harga ASC',
];
$order_by = $order_map[$urut] ?? $order_map['terbaru'];

// =====================================================
// QUERY UTAMA LIST ORDER
// PERUBAHAN KRUSIAL:
// 1. Base query hanya ambil Status_Order IN (1, 3) — DP OK atau Lunas
// 2. LEFT JOIN Sesi_Foto dengan kondisi Status_Sesi IN (0, 1) untuk cek assign
// 3. Tombol assign muncul kalau belum ada fotografer (ID_Fotografer IS NULL)
// =====================================================
$sql_list = "SELECT o.ID_Order, o.Tanggal_Booking, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, o.Status_Order, o.Rating, o.Review, o.Keterangan, 
                    p.Nama_Pelanggan, p.No_Hp, p.Email_Pelanggan, 
                    pk.Nama_Paket, pk.Durasi_Waktu, pk.Harga_Paket, 
                    r.Nama_Ruangan, r.Foto_Ruangan,
                    t.Nama_Tema, t.Foto_Tema,
                    sf.ID_Karyawan as ID_Fotografer, sf.Status_Sesi, 
                    k.Nama_Karyawan as Nama_Fotografer 
             FROM [Order] o 
             INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan 
             INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket 
             INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan 
             INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema 
             LEFT JOIN Sesi_Foto sf ON o.ID_Order = sf.ID_Order 
                 AND sf.Status = 1 
                 AND sf.Status_Sesi IN (0, 1)
             LEFT JOIN Karyawan k ON sf.ID_Karyawan = k.ID_Karyawan 
             WHERE $where 
             ORDER BY $order_by 
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$p_list = $params; $p_list[] = $offset; $p_list[] = $limit;
$query = sqlsrv_query($conn, $sql_list, $p_list);

// =====================================================
// PENAMPUNGAN DATA ORDER
// =====================================================
$orders_list = [];
$order_ids = [];
if ($query !== false) {
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $orders_list[] = $row;
        $order_ids[] = (int)$row['ID_Order'];
    }
}

// =====================================================
// SINKRONISASI JADWAL MULTI-SLOT
// =====================================================
$jadwal_per_order = [];
if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $sql_jadwal = "
        SELECT oj.ID_Order, j.ID_Jadwal, j.Tanggal_Jadwal, j.Jam_Mulai, j.Jam_Selesai
        FROM Order_Jadwal oj
        INNER JOIN Jadwal_Studio j ON oj.ID_Jadwal = j.ID_Jadwal
        WHERE oj.ID_Order IN ($placeholders) AND j.Status = 1 AND j.Is_Deleted = 0
        ORDER BY oj.ID_Order, j.Tanggal_Jadwal ASC, j.Jam_Mulai ASC
    ";
    $q_jadwal = sqlsrv_query($conn, $sql_jadwal, $order_ids);
    if ($q_jadwal !== false) {
        while ($j = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
            $oid = (int)$j['ID_Order'];
            if (!isset($jadwal_per_order[$oid])) {
                $jadwal_per_order[$oid] = [];
            }
            
            if (is_object($j['Tanggal_Jadwal']) && method_exists($j['Tanggal_Jadwal'], 'format')) {
                $tgl_str = $j['Tanggal_Jadwal']->format('d M Y');
            } else {
                $tgl_str = date('d M Y', strtotime($j['Tanggal_Jadwal']));
            }
            
            $jam_mulai_str = (is_object($j['Jam_Mulai']) && method_exists($j['Jam_Mulai'], 'format')) ? $j['Jam_Mulai']->format('H:i') : substr($j['Jam_Mulai'], 0, 5);
            $jam_selesai_str = (is_object($j['Jam_Selesai']) && method_exists($j['Jam_Selesai'], 'format')) ? $j['Jam_Selesai']->format('H:i') : substr($j['Jam_Selesai'], 0, 5);

            $tgl_raw = (is_object($j['Tanggal_Jadwal']) && method_exists($j['Tanggal_Jadwal'], 'format')) ? $j['Tanggal_Jadwal']->format('Y-m-d') : date('Y-m-d', strtotime($j['Tanggal_Jadwal']));
            $jam_selesai_raw = (is_object($j['Jam_Selesai']) && method_exists($j['Jam_Selesai'], 'format')) ? $j['Jam_Selesai']->format('H:i:s') : substr($j['Jam_Selesai'], 0, 8);
            $ts_selesai = strtotime($tgl_raw . ' ' . $jam_selesai_raw);

            $jadwal_per_order[$oid][] = [
                'tanggal' => $tgl_str,
                'jam' => $jam_mulai_str . ' - ' . $jam_selesai_str,
                'ts_selesai' => $ts_selesai
            ];
        }
    }
}

// =====================================================
// VALIDASI: TANDAI ORDER YANG JADWALNYA SUDAH LEWAT
// =====================================================
$order_terlewat = [];
foreach ($jadwal_per_order as $oid_chk => $schedules_chk) {
    if (empty($schedules_chk)) continue;
    $ts_list = array_column($schedules_chk, 'ts_selesai');
    $ts_terakhir = max($ts_list);
    if ($ts_terakhir !== false && $ts_terakhir < time()) {
        $order_terlewat[$oid_chk] = true;
    }
}

// =====================================================
// AMBIL LIST FOTOGRAFER AKTIF
// =====================================================
$q_fg = sqlsrv_query($conn, "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0");
$fotografer_list = [];
if ($q_fg !== false) { while ($f = sqlsrv_fetch_array($q_fg, SQLSRV_FETCH_ASSOC)) $fotografer_list[] = $f; }

// =====================================================
// OBJEK JSON UNTUK MODAL DETAIL
// =====================================================
$order_data_js = [];
foreach ($orders_list as $row) {
    $oid = (int)$row['ID_Order'];
    $s_info = getStatusLabel((int)$row['Status_Order']);
    
    $schedules = $jadwal_per_order[$oid] ?? [];
    $jadwal_str = [];
    foreach ($schedules as $s) {
        $jadwal_str[] = $s['tanggal'] . ' | ' . $s['jam'] . ' WIB';
    }

    $order_data_js[$oid] = [
        'id_order' => $oid,
        'nama_pelanggan' => $row['Nama_Pelanggan'],
        'no_hp' => $row['No_Hp'],
        'email_pelanggan' => $row['Email_Pelanggan'],
        'nama_paket' => $row['Nama_Paket'],
        'nama_ruangan' => $row['Nama_Ruangan'],
        'nama_tema' => $row['Nama_Tema'],
        'total_paket' => (float)$row['Total_Paket'],
        'total_cetak' => (float)$row['Total_Barang_Cetak'],
        'total_harga' => (float)$row['Total_Harga'],
        'tanggal_booking' => fmtTgl($row['Tanggal_Booking']),
        'status_order_label' => $s_info[0],
        'status_order_color' => $s_info[1],
        'nama_fotografer' => $row['Nama_Fotografer'] ?? 'Belum diassign',
        'jadwal' => $jadwal_str,
        'durasi' => $row['Durasi_Waktu'] ?? 0,
        'kapasitas' => $row['Harga_Paket'] ?? 0,
        'foto_ruangan' => $row['Foto_Ruangan'] ?? 'default_ruangan.jpg',
        'foto_tema' => $row['Foto_Tema'] ?? 'default_tema.jpg'
    ];
}

function getStatusLabel($s) {
    $l = [
        1 => ['DP Terverifikasi','#059669','#d1fae5','bi-check-circle-fill'],
        2 => ['Selesai Foto','#2563eb','#dbeafe','bi-camera-fill'],
        3 => ['Lunas','#7c3aed','#ede9fe','bi-cash-stack'],
        4 => ['Dibatalkan','#dc2626','#fee2e2','bi-x-circle-fill']
    ];
    return $l[$s] ?? ['Unknown','#718096','#f1f5f9','bi-question-circle'];
}
function fmtTgl($d) { 
    return (is_object($d) && method_exists($d, 'format')) 
        ? $d->format('d M Y H:i') 
        : date('d M Y H:i', strtotime($d)); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Customer - SpotLight Studio</title>
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
.stat-icon-pink{background:linear-gradient(135deg,#FFF0F3,#FFE4E9);color:#D53D66;}
.stat-icon-green{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;}
.stat-icon-blue{background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#2563eb;}
.stat-icon-purple{background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#7c3aed;}
.stat-icon-red{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:#dc2626;}
.stat-icon-orange{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#d97706;}
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
.td-order-id{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.td-customer{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-customer-contact{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-paket{font-weight:700;font-size:0.9rem;color:var(--text-dark);}
.td-detail{font-size:0.8rem;color:#718096;font-weight:600;}
.td-jadwal{font-weight:700;font-size:0.85rem;color:var(--text-dark);}
.td-jam{font-size:0.75rem;color:#94a3b8;font-weight:600;}
.td-harga{font-weight:800;font-size:0.95rem;color:var(--p-pink);}
.badge-status{font-size:0.72rem;font-weight:700;padding:6px 14px;border-radius:50px;display:inline-flex;align-items:center;gap:6px;}
.badge-terlewat{margin-top:6px;font-size:0.68rem;font-weight:700;color:#b45309;background:#fef3c7;border:1px solid #fde68a;padding:4px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:5px;}
.badge-expired{margin-top:6px;font-size:0.68rem;font-weight:800;color:#dc2626;background:#fee2e2;border:1px solid #fecaca;padding:4px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:5px;animation:pulseExpired 2s ease-in-out infinite;}
@keyframes pulseExpired{0%,100%{opacity:1;}50%{opacity:0.6;}}
.badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block;}
.btn-action-circle{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;transition:all 0.4s;border:1.5px solid #eef2f6;background:#fff;font-size:0.85rem;text-decoration:none;margin:0 2px;cursor:pointer;}
.btn-action-view{color:#D53D66;border-color:#FFE4E9;}
.btn-action-view:hover{background:#D53D66;color:#fff;transform:translateY(-2px);}
.btn-action-assign{color:#059669;border-color:#d1fae5;}
.btn-action-assign:hover{background:#059669;color:#fff;transform:translateY(-2px);}
.fotografer-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:50px;background:#dbeafe;color:#2563eb;font-size:0.75rem;font-weight:700;}
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

/* ===== FILTER MODAL ===== */
.btn-filter-toggle{position:relative;display:inline-flex;align-items:center;gap:8px;background:var(--p-pink);color:#fff;border:none;padding:10px 18px;border-radius:12px;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all .2s;}
.btn-filter-toggle:hover{background:var(--d-pink);}
.filter-dot{width:8px;height:8px;border-radius:50%;background:#fff;display:inline-block;margin-left:2px;}
.filter-modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:2000;align-items:center;justify-content:center;padding:16px;}
.filter-modal-overlay.active{display:flex;}
.filter-modal-box{background:#fff;border-radius:20px;width:100%;max-width:420px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,0.25);}
.filter-modal-header{display:flex;justify-content:space-between;align-items:center;font-weight:800;font-size:1.15rem;margin-bottom:20px;color:var(--text-dark);}
.filter-modal-close{cursor:pointer;color:#94a3b8;font-size:1.1rem;}
.filter-modal-close:hover{color:var(--p-pink);}
.filter-modal-body label{display:block;font-size:0.75rem;font-weight:700;color:#94a3b8;letter-spacing:.5px;margin:16px 0 6px;}
.filter-modal-body label:first-child{margin-top:0;}
.filter-select{width:100%;padding:12px 14px;border-radius:12px;border:1.5px solid #e2e8f0;font-size:0.9rem;color:var(--text-dark);background:#fff;}
.filter-select:focus{outline:none;border-color:var(--p-pink);}
.filter-modal-footer{display:flex;gap:12px;margin-top:24px;}
.btn-filter-reset{flex:1;background:#f1f5f9;color:#64748b;border:none;padding:13px;border-radius:12px;font-weight:700;cursor:pointer;}
.btn-filter-reset:hover{background:#e2e8f0;}
.btn-filter-terapkan{flex:1.4;background:var(--p-pink);color:#fff;border:none;padding:13px;border-radius:12px;font-weight:700;cursor:pointer;}
.btn-filter-terapkan:hover{background:var(--d-pink);}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="sidebar-menu-wrapper">
<a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
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
<li><a href="list.php" class="submenu-link active"><i class="bi bi-calendar-check-fill me-2"></i>Booking Customer</a></li>
<li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
<li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
</ul>
</div>
</li>

<li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
</ul>
</div>
<div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
<div class="dashboard-header">
<div><h3 class="fw-bold mb-1">Booking Customer</h3><p class="text-muted small mb-0">Kelola order yang sudah dikonfirmasi dan arahkan fotografer.</p></div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<div class="stats-scroll-wrapper animate-fade-in">
<div class="stats-row">
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-calendar-check-fill"></i></div><div class="stat-content"><div class="stat-title">Total Order</div><div class="stat-val"><?= $stats['total']??0 ?> Order</div><div class="stat-subtitle">Sudah dikonfirmasi</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">DP Terverifikasi</div><div class="stat-val"><?= $stats['dp_terverifikasi']??0 ?> Order</div><div class="stat-subtitle">Menunggu assign fotografer</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-orange"><i class="bi bi-person-plus"></i></div><div class="stat-content"><div class="stat-title">Menunggu Assign</div><div class="stat-val"><?= $stats['menunggu_assign']??0 ?> Order</div><div class="stat-subtitle">Belum punya fotografer</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-blue"><i class="bi bi-camera-fill"></i></div><div class="stat-content"><div class="stat-title">Selesai</div><div class="stat-val"><?= $stats['selesai']??0 ?> Order</div><div class="stat-subtitle">Sesi foto selesai</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-purple"><i class="bi bi-cash-stack"></i></div><div class="stat-content"><div class="stat-title">Lunas</div><div class="stat-val"><?= $stats['lunas']??0 ?> Order</div><div class="stat-subtitle">Pembayaran lunas</div></div></div></div></div>
<div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-x-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Dibatalkan</div><div class="stat-val"><?= $stats['dibatalkan']??0 ?> Order</div><div class="stat-subtitle">Order dibatalkan</div></div></div></div></div>
</div>
</div>

<div class="tab-filter-bar">
<a href="list.php?tab=semua" class="tab-btn <?= $tab_filter==='semua'?'active':'' ?>"><i class="bi bi-grid-fill"></i> Semua <span class="tab-count"><?= $stats['total']??0 ?></span></a>
<a href="list.php?tab=dp_terverifikasi" class="tab-btn <?= $tab_filter==='dp_terverifikasi'?'active':'' ?>"><i class="bi bi-check-circle-fill"></i> DP Terverifikasi <span class="tab-count"><?= $stats['dp_terverifikasi']??0 ?></span></a>
<a href="list.php?tab=menunggu_assign" class="tab-btn <?= $tab_filter==='menunggu_assign'?'active':'' ?>"><i class="bi bi-person-plus"></i> Menunggu Assign <span class="tab-count"><?= $stats['menunggu_assign']??0 ?></span></a>
<a href="list.php?tab=selesai" class="tab-btn <?= $tab_filter==='selesai'?'active':'' ?>"><i class="bi bi-camera-fill"></i> Selesai <span class="tab-count"><?= $stats['selesai']??0 ?></span></a>
<a href="list.php?tab=lunas" class="tab-btn <?= $tab_filter==='lunas'?'active':'' ?>"><i class="bi bi-cash-stack"></i> Lunas <span class="tab-count"><?= $stats['lunas']??0 ?></span></a>
<a href="list.php?tab=dibatalkan" class="tab-btn <?= $tab_filter==='dibatalkan'?'active':'' ?>"><i class="bi bi-x-circle-fill"></i> Dibatalkan <span class="tab-count"><?= $stats['dibatalkan']??0 ?></span></a>
<a href="list.php?tab=terlewat" class="tab-btn <?= $tab_filter==='terlewat'?'active':'' ?>" style="<?= ($stats['expired']??0)>0 ? 'color:#b45309;' : '' ?>"><i class="bi bi-exclamation-triangle-fill"></i> Jadwal Terlewat <span class="tab-count"><?= $stats['expired']??0 ?></span></a>
</div>

<div class="search-filter-bar">
<form method="GET" class="search-form-flex" id="mainSearchForm">
<input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter) ?>">
<input type="hidden" name="urut" id="inputUrut" value="<?= htmlspecialchars($urut) ?>">
<input type="hidden" name="tgl_dari" id="inputTglDari" value="<?= htmlspecialchars($tgl_dari) ?>">
<input type="hidden" name="tgl_sampai" id="inputTglSampai" value="<?= htmlspecialchars($tgl_sampai) ?>">
<div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari nama customer, no. order, atau paket..." value="<?= htmlspecialchars($cari) ?>"></div>
<button type="button" class="btn-filter-toggle" onclick="bukaModalFilter()"><i class="bi bi-funnel-fill"></i> Filter<?php if($urut!=='terbaru'||!empty($tgl_dari)||!empty($tgl_sampai)):?> <span class="filter-dot"></span><?php endif;?></button>
<button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
<?php if (!empty($cari) || !empty($tgl_dari) || !empty($tgl_sampai) || $urut!=='terbaru'): ?>
<a href="list.php?tab=<?= htmlspecialchars($tab_filter) ?>" class="btn-search-icon" style="background:#f1f5f9;color:#64748b;" title="Reset filter"><i class="bi bi-x-lg"></i></a>
<?php endif; ?>
</form>
</div>

<!-- MODAL FILTER -->
<div id="modalFilterData" class="filter-modal-overlay" onclick="if(event.target===this) tutupModalFilter()">
  <div class="filter-modal-box">
    <div class="filter-modal-header">
      <div><i class="bi bi-funnel-fill" style="color:#D53D66;"></i> Filter Data</div>
      <span class="filter-modal-close" onclick="tutupModalFilter()"><i class="bi bi-x-lg"></i></span>
    </div>
    <div class="filter-modal-body">
      <label>URUT BERDASARKAN</label>
      <select id="selectUrut" class="filter-select">
        <option value="terbaru">Tanggal Booking Terbaru</option>
        <option value="terlama">Tanggal Booking Terlama</option>
        <option value="nama_az">Nama A - Z</option>
        <option value="nama_za">Nama Z - A</option>
        <option value="total_tinggi">Total Tagihan Tertinggi</option>
        <option value="total_rendah">Total Tagihan Terendah</option>
      </select>

      <label>DARI TANGGAL BOOKING</label>
      <input type="date" id="selectTglDari" class="filter-select">

      <label>SAMPAI TANGGAL BOOKING</label>
      <input type="date" id="selectTglSampai" class="filter-select">
    </div>
    <div class="filter-modal-footer">
      <button type="button" class="btn-filter-reset" onclick="resetModalFilter()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
      <button type="button" class="btn-filter-terapkan" onclick="terapkanModalFilter()"><i class="bi bi-check-lg"></i> Terapkan</button>
    </div>
  </div>
</div>

<div class="card-3d mb-4" style="padding:24px;">
<div class="table-scroll-wrapper">
<table class="data-table">
<thead><tr><th>No.</th><th>No. Order</th><th>Customer</th><th>Paket & Detail</th><th>Jadwal Sesi</th><th>Total</th><th>Status</th><th>Fotografer</th><th class="text-center">Aksi</th></tr></thead>
<tbody>
<?php
$no=$offset+1;
if(!empty($orders_list)):
foreach($orders_list as $row):
$statusInfo=getStatusLabel((int)$row['Status_Order']);
$has_fotografer=!empty($row['ID_Fotografer']);
$nama_fotografer=$row['Nama_Fotografer']??null;
// =====================================================
// TOMBOL ASSIGN MUNCUL KALAU:
// 1. Status_Order = 1 (DP Terverifikasi) ATAU Status_Order = 3 (Lunas)
// 2. BELUM punya fotografer (ID_Fotografer IS NULL)
// 3. Jadwal sesinya BELUM expired (jam selesai terakhir belum lewat)
// =====================================================
$is_expired = !empty($order_terlewat[(int)$row['ID_Order']]);
$bisa_assign = ((int)$row['Status_Order'] === STATUS_ORDER_DP_TERVERIFIKASI || (int)$row['Status_Order'] === STATUS_ORDER_LUNAS) && !$has_fotografer && !$is_expired;
?>
<tr class="fade-in-up" data-id="<?= $row['ID_Order'] ?>">
<td><div class="td-order-id"><?= $no++ ?></div></td>
<td><div class="td-order-id" style="color:var(--text-dark);">#<?= str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT) ?></div><div class="td-customer-contact"><?= fmtTgl($row['Tanggal_Booking']) ?></div></td>
<td><div class="td-customer"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div><div class="td-customer-contact"><?= htmlspecialchars($row['No_Hp']) ?></div></td>
<td><div class="td-paket"><?= htmlspecialchars($row['Nama_Paket']) ?></div><div class="td-detail"><?= htmlspecialchars($row['Nama_Ruangan']) ?> &bull; <?= htmlspecialchars($row['Nama_Tema']) ?></div><div class="td-detail"><i class="bi bi-clock me-1"></i><?= $row['Durasi_Waktu'] ?> menit</div></td>
<td>
    <?php 
    $schedules = $jadwal_per_order[(int)$row['ID_Order']] ?? [];
    if (!empty($schedules)):
        foreach ($schedules as $idx => $s):
    ?>
        <div class="td-jadwal" style="<?= $idx > 0 ? 'margin-top: 6px; padding-top: 6px; border-top: 1px dashed #f1f5f9;' : '' ?>"><?= htmlspecialchars($s['tanggal']) ?></div>
        <div class="td-jam"><?= htmlspecialchars($s['jam']) ?> WIB</div>
    <?php 
        endforeach;
    else:
    ?>
        <div class="td-detail" style="color:#94a3b8"><i class="bi bi-calendar-x me-1"></i>Belum Terjadwal</div>
    <?php endif; ?>
</td>
<td><div class="td-harga">Rp <?= number_format((float)$row['Total_Harga'],0,',','.') ?></div></td>
<td><span class="badge-status" style="background:<?= $statusInfo[2] ?>;color:<?= $statusInfo[1] ?>"><span class="badge-dot" style="background:<?= $statusInfo[1] ?>"></span><?= $statusInfo[0] ?></span><?php if(in_array((int)$row['Status_Order'],[STATUS_ORDER_DP_TERVERIFIKASI,STATUS_ORDER_LUNAS]) && !$has_fotografer && $is_expired):?><div class="badge-expired" title="Jadwal sesi sudah lewat waktu dan belum punya fotografer. Assign tidak bisa dilakukan, order harus di-reschedule dulu."><i class="bi bi-clock-history"></i> Expired</div><?php endif;?></td>
<td><?php if($has_fotografer):?><span class="fotografer-badge"><i class="bi bi-person-fill"></i><?= htmlspecialchars($nama_fotografer) ?></span><?php else:?><span class="td-detail" style="color:#94a3b8"><i class="bi bi-person-x me-1"></i>Belum diassign</span><?php endif;?></td>
<td><button class="btn-action-circle btn-action-view" onclick="bukaDetail(<?= (int)$row['ID_Order'] ?>)" title="Lihat Detail"><i class="bi bi-eye"></i></button><?php if($bisa_assign):?><button class="btn-action-circle btn-action-assign" onclick="konfirmasiAssign(<?= (int)$row['ID_Order'] ?>)" title="Assign Fotografer"><i class="bi bi-person-plus"></i></button><?php endif;?></td>
</tr>
<?php endforeach;else:?>
<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color:#cbd5e1"></i><p class="fw-bold">Tidak ada order yang sesuai.</p><p class="small">Belum ada order yang sudah dikonfirmasi dan siap di-assign fotografer.</p></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
<?php if($total_halaman>1):?>
<div class="pagination-wrapper">
<div class="pagination-info">Menampilkan <span><?= $offset+1 ?></span> - <span><?= min($offset+$limit,$total_records) ?></span> dari <span><?= $total_records ?></span> order</div>
<nav class="pagination-nav">
<?php $extra_qs = (!empty($tgl_dari)?'&tgl_dari='.urlencode($tgl_dari):'').(!empty($tgl_sampai)?'&tgl_sampai='.urlencode($tgl_sampai):'').(!empty($urut)?'&urut='.urlencode($urut):''); ?>
<?php if($halaman>1):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman-1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?><?= $extra_qs ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif;?>
<?php $start_page=max(1,$halaman-2);$end_page=min($total_halaman,$halaman+2);if($start_page>1){echo'<a class="page-link-pag" href="list.php?halaman=1&tab='.$tab_filter.'&cari='.urlencode($cari).$extra_qs.'">1</a>';if($start_page>2)echo'<span class="page-link-pag disabled">...</span>';}for($i=$start_page;$i<=$end_page;$i++):?><a class="page-link-pag <?= ($halaman==$i)?'active-pag':'' ?>" href="list.php?halaman=<?= $i ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?><?= $extra_qs ?>"><?= $i ?></a><?php endfor;if($end_page<$total_halaman){if($end_page<$total_halaman-1)echo'<span class="page-link-pag disabled">...</span>';echo'<a class="page-link-pag" href="list.php?halaman='.$total_halaman.'&tab='.$tab_filter.'&cari='.urlencode($cari).$extra_qs.'">'.$total_halaman.'</a>';}?>
<?php if($halaman<$total_halaman):?><a class="page-link-pag" href="list.php?halaman=<?= $halaman+1 ?>&tab=<?= $tab_filter ?>&cari=<?= urlencode($cari) ?><?= $extra_qs ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else:?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif;?>
</nav>
</div>
<?php elseif($total_records>0):?>
<div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> order</div></div>
<?php endif;?>
</div>
</div>

<div class="modal-overlay" id="modalDetail"><div class="modal-content-custom"><div class="modal-header-custom"><div class="modal-title-custom"><i class="bi bi-receipt" style="color:var(--p-pink);margin-right:8px"></i> Detail Order</div><button class="modal-close-custom" onclick="tutupModal('modalDetail')">&times;</button></div><div class="modal-body-custom" id="detailContent"></div><div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:20px 24px;display:flex;justify-content:flex-end;gap:12px"><button class="btn-search-icon" style="padding:12px 24px;font-weight:700;color:#4a5568" onclick="tutupModal('modalDetail')">Tutup</button></div></div></div>

<div class="modal-overlay" id="modalAssign"><div class="modal-content-custom" style="max-width:450px"><div class="modal-header-custom"><div class="modal-title-custom"><i class="bi bi-person-plus" style="color:var(--p-pink);margin-right:8px"></i> Assign Fotografer</div><button class="modal-close-custom" onclick="tutupModal('modalAssign')">&times;</button></div><div class="modal-body-custom"><p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:20px">Pilih fotografer untuk menangani sesi foto order ini:</p><form id="formAssign" method="POST" action="assign_fotografer.php"><input type="hidden" name="id_order" id="assignOrderId"><div style="margin-bottom:20px"><label style="display:block;font-size:0.9rem;font-weight:700;color:var(--text-dark);margin-bottom:10px">Fotografer</label><select name="id_fotografer" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:14px;font-family:inherit;font-weight:600;font-size:0.9rem;cursor:pointer" required><option value="">-- Pilih Fotografer --</option><?php foreach($fotografer_list as $fg):?><option value="<?= (int)$fg['ID_Karyawan'] ?>"><?= htmlspecialchars($fg['Nama_Karyawan']) ?></option><?php endforeach;?></select></div></form></div><div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:20px 24px;display:flex;justify-content:flex-end;gap:12px"><button class="btn-search-icon" style="padding:12px 24px;font-weight:700;color:#4a5568" onclick="tutupModal('modalAssign')">Batal</button><button class="btn-search-icon" style="background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border-color:var(--p-pink);padding:12px 24px;font-weight:800" onclick="document.getElementById('formAssign').submit()"><i class="bi bi-check-lg me-1"></i> Simpan</button></div></div></div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const orderData = <?= json_encode($order_data_js) ?>;

document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)';}}});});
function bukaModal(id){document.getElementById(id).classList.add('show')}
function tutupModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(modal=>{modal.addEventListener('click',function(e){if(e.target===this)tutupModal(this.id);});});

function bukaDetail(idOrder) {
    const data = orderData[idOrder] || {};
    if (!data.id_order) return;

    let jadwalHtml = '';
    if (data.jadwal && data.jadwal.length > 0) {
        data.jadwal.forEach((j, idx) => {
            jadwalHtml += `<div style="${idx > 0 ? 'margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2e8f0;' : ''}">Slot ${idx + 1}: ${j}</div>`;
        });
    } else {
        jadwalHtml = 'Belum Terjadwal';
    }

    const html = `
        <div style="display:grid;gap:16px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                    <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">No. Order</div>
                    <div style="font-weight:800;color:var(--p-pink);font-size:1.1rem">#${String(data.id_order).padStart(5, '0')}</div>
                </div>
                <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                    <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Status</div>
                    <div style="font-weight:700;font-size:0.95rem;color:${data.status_order_color}">${data.status_order_label}</div>
                </div>
            </div>
            <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Customer</div>
                <div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">${data.nama_pelanggan}</div>
                <div style="font-size:0.8rem;color:#718096;font-weight:600">${data.no_hp} &bull; ${data.email_pelanggan}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                    <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Paket</div>
                    <div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">${data.nama_paket}</div>
                    <div style="font-size:0.8rem;color:#718096;font-weight:600">${data.nama_ruangan} &bull; ${data.nama_tema}</div>
                </div>
                <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                    <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Jadwal Sesi</div>
                    <div style="font-weight:700;color:var(--text-dark);font-size:0.85rem">${jadwalHtml}</div>
                </div>
            </div>
            <div style="background:#f8fafc;padding:14px 18px;border-radius:14px;border:1px solid #e2e8f0">
                <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Fotografer Assigned</div>
                <div style="font-weight:700;color:var(--text-dark);font-size:0.95rem">${data.nama_fotografer}</div>
            </div>
            <div style="background:linear-gradient(135deg,#FFF0F3,#FFF8F0);padding:14px 18px;border-radius:14px;border:1px solid rgba(255,228,233,0.8)">
                <div style="font-size:0.7rem;color:#94a3b8;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Total Pembayaran</div>
                <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid #ffe4e9">
                    <span style="font-size:0.9rem;font-weight:800;color:var(--text-dark)">Total</span>
                    <span style="font-weight:800;font-size:1.1rem;color:var(--p-pink)">Rp ${data.total_harga.toLocaleString('id-ID')}</span>
                </div>
            </div>
        </div>`;
    document.getElementById('detailContent').innerHTML = html;
    bukaModal('modalDetail');
}

function konfirmasiAssign(idOrder){Swal.fire({title:'Assign Fotografer?',text:'Pilih fotografer yang akan menangani sesi foto ini.',icon:'question',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Lanjutkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){document.getElementById('assignOrderId').value=idOrder;bukaModal('modalAssign');}});}
function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php';}});}
function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php';}});}
function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'});}
function updateLiveClock(){const now=new Date();const days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];document.getElementById('live-clock').innerText=`${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')} WIB`;}
setInterval(updateLiveClock,1000);updateLiveClock();

function bukaModalFilter(){
    document.getElementById('selectUrut').value = document.getElementById('inputUrut').value || 'terbaru';
    document.getElementById('selectTglDari').value = document.getElementById('inputTglDari').value || '';
    document.getElementById('selectTglSampai').value = document.getElementById('inputTglSampai').value || '';
    document.getElementById('modalFilterData').classList.add('active');
}
function tutupModalFilter(){
    document.getElementById('modalFilterData').classList.remove('active');
}
function terapkanModalFilter(){
    document.getElementById('inputUrut').value = document.getElementById('selectUrut').value;
    document.getElementById('inputTglDari').value = document.getElementById('selectTglDari').value;
    document.getElementById('inputTglSampai').value = document.getElementById('selectTglSampai').value;
    document.getElementById('mainSearchForm').submit();
}
function resetModalFilter(){
    document.getElementById('selectUrut').value = 'terbaru';
    document.getElementById('selectTglDari').value = '';
    document.getElementById('selectTglSampai').value = '';
    document.getElementById('inputUrut').value = 'terbaru';
    document.getElementById('inputTglDari').value = '';
    document.getElementById('inputTglSampai').value = '';
    document.getElementById('mainSearchForm').submit();
}
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') tutupModalFilter();
});

<?php if(isset($_GET['status'])):?><?php if($_GET['status']=='sukses_assign'):?>Swal.fire({icon:'success',title:'Berhasil!',text:'<?= htmlspecialchars($_GET['msg']??'Fotografer berhasil diassign.') ?>',confirmButtonColor:'#D53D66'});<?php elseif($_GET['status']=='error'):?>Swal.fire({icon:'error',title:'Gagal!',text:'<?= htmlspecialchars($_GET['msg']??'Terjadi kesalahan.') ?>',confirmButtonColor:'#D53D66'});<?php endif;?><?php endif;?>
</script>
</body>
</html>