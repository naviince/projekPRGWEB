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
// HELPER FUNCTIONS
// =====================================================
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

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

// =====================================================
// INTEGRASI PENUH AJAX DETAIL (PENGGANTI DETAIL.PHP)
// =====================================================
if (isset($_GET['ajax_id'])) {
    $id_ajax = (int)$_GET['ajax_id'];
    
    // Ambil data ruangan spesifik (Kapasitas_Ruangan ditiadakan sesuai skema database Anda)
    $ruangan_ajax = safe_sqlsrv_fetch($conn, 
        "SELECT r.*, 
            (SELECT COUNT(*) FROM Paket_Ruangan pr WHERE pr.ID_Ruangan = r.ID_Ruangan) as total_paket,
            (SELECT COUNT(*) FROM Properti p WHERE p.ID_Ruangan = r.ID_Ruangan AND p.Is_Deleted = 0) as total_properti,
            (SELECT COUNT(*) FROM Ruangan_Tema rt WHERE rt.ID_Ruangan = r.ID_Ruangan) as total_tema
        FROM Ruangan r 
        WHERE r.ID_Ruangan = ? AND r.Is_Deleted = 0", 
        [$id_ajax]
    );

    if (!$ruangan_ajax) {
        echo '<div class="alert alert-danger m-0">Detail ruangan tidak ditemukan atau sudah diarsipkan.</div>';
        exit();
    }

    // Ambil paket foto terhubung
    $paket_terhubung = safe_sqlsrv_fetch_all($conn,
        "SELECT p.ID_Paket, p.Nama_Paket, p.Harga_Paket, p.Kapasitas_Orang, p.Durasi_Waktu, p.Foto_Paket
        FROM Paket_Ruangan pr
        JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket
        WHERE pr.ID_Ruangan = ? AND p.Is_Deleted = 0
        ORDER BY p.Harga_Paket ASC",
        [$id_ajax]
    );

    // Ambil properti pendukung ruangan
    $properti_list = safe_sqlsrv_fetch_all($conn,
        "SELECT * FROM Properti 
        WHERE ID_Ruangan = ? AND Is_Deleted = 0
        ORDER BY Nama_Properti ASC",
        [$id_ajax]
    );

    // Ambil tema foto terkait
    $tema_list = safe_sqlsrv_fetch_all($conn,
        "SELECT t.* FROM Ruangan_Tema rt
        JOIN Tema_Foto t ON rt.ID_Tema = t.ID_Tema
        WHERE rt.ID_Ruangan = ? AND t.Is_Deleted = 0
        ORDER BY t.Nama_Tema ASC",
        [$id_ajax]
    );
    
    $path_foto_ajax = "../../assets/img/ruangan/" . ($ruangan_ajax['Foto_Ruangan'] ?? '');
    $foto_src_ajax = (!empty($ruangan_ajax['Foto_Ruangan']) && file_exists($path_foto_ajax)) ? $path_foto_ajax : $default_svg_avatar;
    ?>
    <div class="text-center mb-4">
        <img src="<?= $foto_src_ajax ?>" class="detail-foto" alt="Foto Ruangan">
        <h4 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($ruangan_ajax['Nama_Ruangan']) ?></h4>
        <span class="badge bg-danger px-3 py-1" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">
            <?= ($ruangan_ajax['Is_Deleted'] == 0) ? 'RUANGAN AKTIF' : 'DIARSIPKAN' ?>
        </span>
    </div>

    <!-- Deskripsi -->
    <div class="card-3d p-3 border-0 mb-4" style="border-radius: 16px; background-color: #f8fafc;">
        <div class="detail-section-title"><i class="bi bi-info-circle-fill"></i> Deskripsi Ruangan</div>
        <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.6;">
            <?= htmlspecialchars($ruangan_ajax['Deskripsi'] ?? 'Tidak ada deskripsi.') ?>
        </p>
    </div>

    <!-- Info Grid -->
    <div class="detail-info-grid mb-4">
        <div class="detail-info-item">
            <div class="detail-icon"><i class="bi bi-camera-fill"></i></div>
            <div class="detail-label">Paket Terhubung</div>
            <div class="detail-value"><?= $ruangan_ajax['total_paket'] ?? 0 ?></div>
        </div>
        <div class="detail-info-item">
            <div class="detail-icon"><i class="bi bi-box-seam-fill"></i></div>
            <div class="detail-label">Total Properti</div>
            <div class="detail-value"><?= $ruangan_ajax['total_properti'] ?? 0 ?></div>
        </div>
        <div class="detail-info-item" style="grid-column: span 2;">
            <div class="detail-icon"><i class="bi bi-palette-fill"></i></div>
            <div class="detail-label">Tema Foto Terkait</div>
            <div class="detail-value"><?= $ruangan_ajax['total_tema'] ?? 0 ?></div>
        </div>
    </div>

    <!-- Paket Foto Terhubung -->
    <div class="mb-4">
        <div class="detail-section-title"><i class="bi bi-stars"></i> Paket Foto Terhubung</div>
        <?php if (!empty($paket_terhubung)): foreach ($paket_terhubung as $pkt): 
            $foto_p = $pkt['Foto_Paket'] ?? 'default_paket.jpg';
            $foto_p_src = ($foto_p != 'default_paket.jpg' && file_exists("../../assets/img/paket/" . $foto_p)) ? "../../assets/img/paket/" . $foto_p : $default_svg_avatar;
        ?>
            <div class="detail-paket-card">
                <img src="<?= $foto_p_src ?>" alt="Foto Paket">
                <div class="detail-paket-info">
                    <div class="detail-paket-nama"><?= htmlspecialchars($pkt['Nama_Paket']) ?></div>
                    <div class="detail-paket-harga">Rp <?= number_format($pkt['Harga_Paket'], 0, ',', '.') ?></div>
                    <div class="detail-paket-meta"><?= $pkt['Durasi_Waktu'] ?> Menit • <?= $pkt['Kapasitas_Orang'] ?> Orang</div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="alert alert-light border-2 border-dashed text-center text-muted" style="border-radius: 12px;">Belum terhubung dengan paket foto aktif.</div>
        <?php endif; ?>
    </div>

    <!-- Properti -->
    <div class="mb-4">
        <div class="detail-section-title"><i class="bi bi-box-seam-fill"></i> Properti Pendukung</div>
        <?php if (!empty($properti_list)): ?>
            <div class="detail-badge-list">
                <?php foreach ($properti_list as $prop): ?>
                    <div class="detail-badge">
                        <i class="bi bi-box"></i>
                        <?= htmlspecialchars($prop['Nama_Properti']) ?>
                        <span class="text-muted small">(<?= htmlspecialchars($prop['Kategori_Properti'] ?? '-') ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border-2 border-dashed text-center text-muted" style="border-radius: 12px;">Tidak ada properti pendukung di ruangan ini.</div>
        <?php endif; ?>
    </div>

    <!-- Tema -->
    <div class="mb-4">
        <div class="detail-section-title"><i class="bi bi-palette-fill"></i> Tema Foto Tersedia</div>
        <?php if (!empty($tema_list)): ?>
            <div class="detail-badge-list">
                <?php foreach ($tema_list as $tm): ?>
                    <div class="detail-badge">
                        <i class="bi bi-palette"></i>
                        <?= htmlspecialchars($tm['Nama_Tema']) ?>
                        <span class="text-muted small">(<?= htmlspecialchars($tm['Kategori_Tema'] ?? '-') ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border-2 border-dashed text-center text-muted" style="border-radius: 12px;">Belum ada tema foto khusus untuk ruangan ini.</div>
        <?php endif; ?>
    </div>
    <?php
    exit(); // Hentikan siklus kueri list.php setelah data AJAX ter-render
}

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn, 
    "SELECT Nama_Karyawan, Foto_Profil, Email_Karyawan FROM Karyawan WHERE ID_Karyawan = ? AND Is_Deleted = 0", 
    [$id_admin]
);

$nama_admin = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin = $admin_data['Foto_Profil'] ?? 'default.jpg';
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

// =====================================================
// PAGINATION & FILTER
// =====================================================
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) $halaman = 1;
$offset = ($halaman - 1) * $limit;

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : "nama_asc";

// FIX SINKRONISASI VARIABEL: Menggunakan $filter_terhapus agar sinkron dengan HTML/JS
$filter_terhapus = isset($_GET['terhapus']) ? (int)$_GET['terhapus'] : 0;

// Statistik Ringkas (Status 1/0 ditiadakan sesuai instruksi)
$stats = safe_sqlsrv_fetch($conn, 
    "SELECT COUNT(*) as total,
        SUM(CASE WHEN Is_Deleted = 0 THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END) as terhapus
    FROM Ruangan"
) ?? ['total' => 0, 'aktif' => 0, 'terhapus' => 0];

$top_ruangan = safe_sqlsrv_fetch($conn,
    "SELECT TOP 1 r.Nama_Ruangan, COUNT(o.ID_Order) as total_booked 
    FROM Ruangan r 
    LEFT JOIN [Order] o ON r.ID_Ruangan = o.ID_Ruangan AND o.Status = 1 AND o.Status_Order <> 4
    WHERE r.Is_Deleted = 0
    GROUP BY r.Nama_Ruangan"
);

$conditions = [];
$params = [];

if ($filter_terhapus === 1) {
    $conditions[] = "r.Is_Deleted = 1";
} else {
    $conditions[] = "r.Is_Deleted = 0";
}

if (!empty($cari)) {
    $conditions[] = "(r.Nama_Ruangan LIKE ? OR r.Deskripsi LIKE ?)";
    $params[] = "%$cari%"; 
    $params[] = "%$cari%";
}

// FIX: Menggunakan $where_clause secara konsisten
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$order_clause = "r.Nama_Ruangan ASC";
if ($sort == "nama_desc") { $order_clause = "r.Nama_Ruangan DESC"; }
elseif ($sort == "paket_asc") { $order_clause = "total_paket ASC"; }
elseif ($sort == "paket_desc") { $order_clause = "total_paket DESC"; }

// FIX: Variabel diperbaiki dari $where_sql yang tidak terdefinisi menjadi $where_clause
$count_sql = "SELECT COUNT(*) AS total FROM Ruangan r {$where_clause}";
$total_records = safe_sqlsrv_count($conn, $count_sql, $params);
$total_halaman = ceil($total_records / $limit);

// FIX ANTI-CRASH: Menyematkan parameter integer $offset dan $limit langsung di dalam query SQL Server
$list_sql = "SELECT 
    r.ID_Ruangan, r.Nama_Ruangan, r.Deskripsi, r.Foto_Ruangan, r.Is_Deleted,
    (SELECT COUNT(*) FROM Paket_Ruangan pr WHERE pr.ID_Ruangan = r.ID_Ruangan) as total_paket,
    (SELECT COUNT(*) FROM Properti p WHERE p.ID_Ruangan = r.ID_Ruangan AND p.Is_Deleted = 0) as total_properti,
    (SELECT COUNT(*) FROM Ruangan_Tema rt WHERE rt.ID_Ruangan = r.ID_Ruangan) as total_tema
FROM Ruangan r {$where_clause} ORDER BY {$order_clause} OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

$ruangan_list = safe_sqlsrv_fetch_all($conn, $list_sql, $params);

$paket_per_ruangan = [];
if (!empty($ruangan_list)) {
    $ruangan_ids = array_column($ruangan_list, 'ID_Ruangan');
    $placeholders = implode(',', array_fill(0, count($ruangan_ids), '?'));
    $paket_sql = "SELECT pr.ID_Ruangan, p.Nama_Paket, p.Kapasitas_Orang, p.Durasi_Waktu, p.Harga_Paket 
                  FROM Paket_Ruangan pr JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket 
                  WHERE pr.ID_Ruangan IN ($placeholders) AND p.Is_Deleted = 0";
    $paket_data = safe_sqlsrv_fetch_all($conn, $paket_sql, $ruangan_ids);
    foreach ($paket_data as $p) {
        $paket_per_ruangan[$p['ID_Ruangan']][] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Ruangan - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#D53D66;--d-pink:#CA3366;--s-pink:#FFF0F3;--light-pink:#FFE4E9;--accent-pink:#E85D84;--text-dark:#1e1e24;--text-muted:#718096;--sidebar-bg:#ffffff;--body-bg:#f8fafc;--transition-3d:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275)}
body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
.sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
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
.submenu-link:hover, .submenu-link.active { color: var(--p-pink); background-color: rgba(213, 61, 102, 0.03); padding-left: 22px; }
.btn-logout { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; width: 100%; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; transition: var(--transition-3d); }
.btn-logout:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(213, 61, 102, 0.2); }
.main-content { margin-left: 260px; padding: 40px; min-height: 100vh; }
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
.profile-header-btn { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #ffffff; cursor: pointer; transition: var(--transition-3d); background: #ffffff; }
.profile-header-btn:hover { transform: scale(1.08) translateY(-2px); box-shadow: 0 8px 20px rgba(213, 61, 102, 0.15); border-color: var(--p-pink); }
.profile-header-btn img { width: 100%; height: 100%; object-fit: cover; }
.stats-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 10px; margin-bottom: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
.stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
.stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
.stats-row { display: flex; gap: 16px; min-width: max-content; }
.stat-card-item { min-width: 220px; max-width: 280px; flex: 0 0 auto; }
.card-3d { background:#fff; border-radius:22px; border:1px solid rgba(255,228,233,0.8); box-shadow:0 8px 24px rgba(213,61,102,0.03); transition:var(--transition-3d); padding:20px; height:100%; position:relative; overflow:hidden; }
.card-3d:hover { transform:translateY(-8px) scale(1.01); box-shadow:0 22px 45px rgba(213,61,102,0.14); border-color:var(--p-pink); }
.stat-card { display: flex; align-items: center; gap: 14px; }
.stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; transition: var(--transition-3d); flex-shrink: 0; }
.stat-icon-pink { background: linear-gradient(135deg, #FFF0F3, #FFE4E9); color: #D53D66; }
.stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
.stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
.stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
.stat-content { flex: 1; min-width: 0; overflow: hidden; }
.stat-val { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
.stat-title { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
.stat-subtitle { font-size: 0.68rem; color: #a0aec0; font-weight: 600; margin-top: 2px; }

/* TABS FILTER */
.status-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.status-tab { padding: 10px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; text-decoration: none; color: #64748b; background: #fff; border: 2px solid #e2e8f0; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 6px; }
.status-tab:hover { border-color: var(--p-pink); color: var(--p-pink); }
.status-tab.active { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border-color: var(--p-pink); box-shadow: 0 4px 12px rgba(213,61,102,0.2); }
.status-tab .tab-count { background: rgba(255,255,255,0.3); color: inherit; padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; }

/* SEARCH */
.search-filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
.search-form-flex { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 300px; }
.search-input-wrapper { position: relative; flex: 1; }
.search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1rem; z-index: 2; }
.search-input-main { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 18px 12px 44px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #fff; }
.search-input-main:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213,61,102,0.08); }
.btn-filter-modal { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; border-radius: 14px; padding: 12px 24px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; cursor: pointer; transition: var(--transition-3d); white-space: nowrap; }
.btn-filter-modal:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(213,61,102,0.3); }
.btn-search-icon { background: #fff; border: 2px solid #e2e8f0; border-radius: 14px; padding: 12px 16px; color: #94a3b8; cursor: pointer; transition: var(--transition-3d); display: flex; align-items: center; justify-content: center; }
.btn-search-icon:hover { border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
.btn-reg-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink))!important; color: #fff!important; border-radius: 14px!important; padding: 12px 28px!important; font-weight: 800!important; border: none!important; box-shadow: 0 8px 20px rgba(213,61,102,0.25)!important; transition: var(--transition-3d)!important; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn-reg-header:hover { background: linear-gradient(135deg,#E85D84, var(--p-pink))!important; transform: translateY(-4px) scale(1.03)!important; box-shadow: 0 12px 25px rgba(213,61,102,0.4)!important; }

/* TABLE */
.table-scroll-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; border-radius: 20px; scrollbar-width: thin; scrollbar-color: var(--p-pink) #f1f5f9; }
.table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }
.data-table { width: 100%; min-width: 900px; border-collapse: separate; border-spacing: 0; }
.data-table thead th { background: #fff; padding: 16px 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; white-space: nowrap; border: none; border-bottom: 2px solid #f1f5f9; text-align: left; }
.data-table thead th:first-child { padding-left: 24px; }
.data-table thead th:last-child { padding-right: 24px; text-align: center; }
.data-table tbody tr { transition: all 0.2s ease; }
.data-table tbody td { padding: 16px 20px; border: none; border-bottom: 1px solid #f1f5f9; vertical-align: middle; white-space: nowrap; }
.data-table tbody td:first-child { padding-left: 24px; }
.data-table tbody td:last-child { padding-right: 24px; text-align: center; }
.data-table tbody tr:nth-child(even) { background: #FFF8F0; }
.data-table tbody tr:nth-child(odd) { background: #fff; }
.data-table tbody tr:hover { background: #FFEDD5!important; transform: scale(1.002); }
.data-table tbody tr.row-deleted { background: #fef2f2!important; opacity: 0.85; }
.data-table tbody tr.row-deleted:hover { background: #fee2e2!important; }

.ruangan-preview { width: 70px; height: 70px; object-fit: cover; border-radius: 16px; border: 2px solid var(--light-pink); transition: var(--transition-3d); flex-shrink: 0; }
.data-table tbody tr:hover .ruangan-preview { transform: scale(1.08) rotate(2deg); }
.td-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); }
.td-deskripsi { font-size: 0.8rem; color: #718096; max-width: 200px; white-space: normal; }
.td-relasi { font-size: 0.8rem; color: #718096; font-weight: 600; }

/* BADGES */
.badge-status { font-size: 0.72rem; font-weight: 700; padding: 6px 14px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; }
.badge-aktif { background: #ecfdf5; color: #059669; }
.badge-terhapus { background: #fee2e2; color: #991b1b; }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.badge-aktif .badge-dot { background: #059669; }
.badge-terhapus .badge-dot { background: #991b1b; }
.badge-paket { font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 50px; background: linear-gradient(135deg,#FFF0F3,#FFE4E9); color: var(--p-pink); border: 1px solid var(--light-pink); display: inline-block; margin: 1px; }

/* ACTION BUTTONS */
.btn-action-circle { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; transition: var(--transition-3d); border: 1px solid var(--light-pink); background: #fff; font-size: 0.88rem; text-decoration: none; margin: 0 4px; cursor: pointer; }
.btn-action-detail { color: var(--p-pink); border-color: var(--light-pink); }
.btn-action-detail:hover { background: var(--p-pink); color: #fff; transform: translateY(-2px); }
.btn-action-edit { color: var(--p-pink); border-color: var(--light-pink); }
.btn-action-edit:hover { background: var(--p-pink); color: #fff; transform: translateY(-2px); }
.btn-action-soft-delete { color: var(--p-pink); border-color: var(--light-pink); }
.btn-action-soft-delete:hover { background: var(--p-pink); color: #fff; transform: translateY(-2px); }
.btn-action-restore { color: #059669; border-color: #d1fae5; }
.btn-action-restore:hover { background: #059669; color: #fff; transform: translateY(-2px); }
.btn-action-hard-delete { color: #dc2626; border-color: #fee2e2; }
.btn-action-hard-delete:hover { background: #dc2626; color: #fff; transform: translateY(-2px); }

/* PAGINATION */
.pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding: 20px 24px; background: #fff; border-radius: 20px; border: 1px solid rgba(255,228,233,0.8); box-shadow: 0 4px 15px rgba(213,61,102,0.04); }
.pagination-info { font-size: 0.85rem; color: #718096; font-weight: 600; }
.pagination-info span { color: var(--p-pink); font-weight: 700; }
.pagination-nav { display: flex; gap: 6px; align-items: center; }
.page-link-pag { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 14px; border-radius: 12px; background: #fff; border: 2px solid #FFF5F7; color: #4a5568; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: var(--transition-3d); }
.page-link-pag:hover { background: var(--light-pink); border-color: var(--p-pink); color: var(--p-pink); transform: translateY(-2px); }
.page-link-pag.active-pag { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)) !important; color: #fff !important; border-color: var(--p-pink) !important; box-shadow: 0 4px 12px rgba(213,61,102,0.3); }
.page-link-pag.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in-up { animation: fadeIn 0.5s ease-out; }
@media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } }

/* MODAL DETAIL STYLE (PENGGABUNGAN DARI DETAIL.PHP) */
.modal-detail .modal-content { border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
.modal-detail .modal-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; padding: 24px 30px; }
.modal-detail .modal-header h5 { font-weight: 800; font-size: 1.2rem; }
.modal-detail .modal-body { padding: 30px; background: #f8fafc; max-height: 75vh; overflow-y: auto; }
.modal-detail .detail-foto { width: 100%; height: 220px; object-fit: cover; border-radius: 16px; border: 3px solid var(--light-pink); margin-bottom: 20px; }
.modal-detail .detail-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
.modal-detail .detail-info-item { background: #fff; border-radius: 14px; padding: 16px; border: 2px solid #e2e8f0; text-align: center; transition: var(--transition-3d); }
.modal-detail .detail-info-item:hover { border-color: var(--p-pink); transform: translateY(-2px); }
.modal-detail .detail-icon { font-size: 1.5rem; color: var(--p-pink); margin-bottom: 8px; }
.modal-detail .detail-label { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
.modal-detail .detail-value { font-size: 1.2rem; font-weight: 800; color: var(--text-dark); }
.modal-detail .detail-section-title { font-weight: 800; font-size: 0.85rem; color: var(--text-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.8px; }
.modal-detail .detail-section-title i { color: var(--p-pink); }
.modal-detail .detail-paket-card { background: #fff; border-radius: 14px; border: 2px solid #e2e8f0; padding: 14px; display: flex; align-items: center; gap: 12px; margin-bottom: 8px; transition: var(--transition-3d); }
.modal-detail .detail-paket-card:hover { border-color: var(--p-pink); }
.modal-detail .detail-paket-card img { width: 50px; height: 50px; border-radius: 12px; object-fit: cover; border: 2px solid var(--light-pink); }
.modal-detail .detail-paket-info { flex: 1; min-width: 0; }
.modal-detail .detail-paket-nama { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.modal-detail .detail-paket-harga { font-size: 0.85rem; color: var(--p-pink); font-weight: 800; }
.modal-detail .detail-paket-meta { font-size: 0.75rem; color: #718096; font-weight: 600; }
.modal-detail .detail-badge-list { display: flex; flex-wrap: wrap; gap: 6px; }
.modal-detail .detail-badge { background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 8px 14px; font-weight: 600; font-size: 0.8rem; color: #4a5568; display: flex; align-items: center; gap: 6px; transition: var(--transition-3d); }
.modal-detail .detail-badge:hover { border-color: var(--p-pink); color: var(--p-pink); }
.modal-detail .detail-badge i { font-size: 0.9rem; color: var(--p-pink); }
</style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-menu-wrapper">
            <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../Role/Admin/index.php" class="nav-link-custom"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link-custom btn-toggle-submenu active" data-target="#submenuMaster">
                        <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                        <i class="bi bi-chevron-up small icon-chevron" style="transform: rotate(180deg)"></i>
                    </a>
                    <div class="submenu show" id="submenuMaster">
                        <ul class="list-unstyled">
                            <li><a href="../Pelanggan/list.php" class="submenu-link"><i class="bi bi-people-fill me-2"></i>Pelanggan</a></li>
                            <li><a href="../Paket Foto/list.php" class="submenu-link"><i class="bi bi-camera-fill me-2"></i>Paket Foto</a></li>
                            <li><a href="./list.php" class="submenu-link active"><i class="bi bi-door-open-fill me-2"></i>Ruangan</a></li>
                            <li><a href="../Properti/list.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Properti</a></li>
                            <li><a href="../Tema Foto/list.php" class="submenu-link"><i class="bi bi-palette-fill me-2"></i>Tema Foto</a></li>
                            <li><a href="../Jadwal Studio/list.php" class="submenu-link"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Studio</a></li>
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
                <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
            </ul>
        </div>
        <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
    </div>

    <div class="main-content">
        <div class="dashboard-header" data-aos="fade-up">
            <div>
                <h3 class="fw-bold mb-1">Master Ruangan</h3>
                <p class="text-muted small mb-0">Kelola data ruangan studio. Relasi status aktif dikelola melalui arsip ruangan.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
                <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
            </div>
        </div>

        <!-- STATS CARD -->
        <div class="stats-scroll-wrapper animate-fade-in">
            <div class="stats-row">
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-pink"><i class="bi bi-building"></i></div><div class="stat-content"><div class="stat-title">Total Ruangan</div><div class="stat-val"><?= $stats['total'] ?? 0 ?> Ruangan</div><div class="stat-subtitle">Tersedia di sistem</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div><div class="stat-content"><div class="stat-title">Ruangan Aktif</div><div class="stat-val"><?= $stats['aktif'] ?? 0 ?> Ruangan</div><div class="stat-subtitle">Siap digunakan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-red"><i class="bi bi-trash-fill"></i></div><div class="stat-content"><div class="stat-title">Ruangan Terhapus</div><div class="stat-val"><?= $stats['terhapus'] ?? 0 ?> Ruangan</div><div class="stat-subtitle">Bisa dipulihkan</div></div></div></div></div>
                <div class="stat-card-item"><div class="card-3d"><div class="stat-card"><div class="stat-icon stat-icon-blue"><i class="bi bi-award-fill"></i></div><div class="stat-content"><div class="stat-title">Terpopuler</div><div class="stat-val" style="font-size:1.1rem"><?= $top_ruangan ? htmlspecialchars($top_ruangan['Nama_Ruangan']) : '-' ?></div><div class="stat-subtitle"><?= $top_ruangan ? ($top_ruangan['total_booked'] ?? 0) . ' booking' : 'Belum ada data' ?></div></div></div></div></div>
            </div>
        </div>

        <!-- TABS FILTER -->
        <div class="status-tabs">
            <a href="list.php<?= !empty($cari) ? '?cari=' . urlencode($cari) . '&' : '?' ?>sort=<?= $sort ?>&terhapus=0" class="status-tab <?= $filter_terhapus == 0 ? 'active' : '' ?>"><i class="bi bi-check-circle"></i> Data Aktif<span class="tab-count"><?= $stats['aktif'] ?? 0 ?></span></a>
            <a href="list.php?terhapus=1<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_terhapus == 1 ? 'active' : '' ?>"><i class="bi bi-trash"></i> Terhapus<span class="tab-count"><?= $stats['terhapus'] ?? 0 ?></span></a>
            <a href="list.php?terhapus=all<?= !empty($cari) ? '&cari=' . urlencode($cari) : '' ?>&sort=<?= $sort ?>" class="status-tab <?= $filter_terhapus === 'all' ? 'active' : '' ?>"><i class="bi bi-list-ul"></i> Semua Data <span class="tab-count"><?= $stats['total'] ?? 0 ?></span></a>
        </div>

        <div class="search-filter-bar">
            <form method="GET" class="search-form-flex" id="mainSearchForm">
                <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="terhapus" id="hiddenTerhapus" value="<?= $filter_terhapus ?>">
                <div class="search-input-wrapper"><i class="bi bi-search search-icon"></i><input type="text" name="cari" class="search-input-main" placeholder="Cari nama ruangan atau deskripsi..." value="<?= htmlspecialchars($cari) ?>"></div>
                <button type="button" class="btn-filter-modal" onclick="bukaModalFilter()"><i class="bi bi-funnel-fill me-2"></i>Filter<i class="bi bi-chevron-down ms-2"></i></button>
                <button type="submit" class="btn-search-icon" title="Cari"><i class="bi bi-search"></i></button>
            </form>
            <?php if ($filter_terhapus != 1): ?><a href="add.php" class="btn-reg-header text-decoration-none"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Ruangan</a><?php endif; ?>
        </div>

        <div class="alert alert-light border-2 border-dashed mb-3" style="border-color:#e2e8f0;border-radius:14px;background:#f8fafc">
            <i class="bi bi-info-circle-fill me-2 text-info"></i>
            <span class="small fw-bold text-muted">
                <?php if ($filter_terhapus == 1): ?><strong>Info:</strong> Ruangan yang dihapus bisa dikembalikan atau dihapus permanen.<?php else: ?><strong>Info:</strong> Kapasitas ruangan diatur oleh <strong>Paket Foto</strong>. Klik <i class="bi bi-eye"></i> untuk lihat detail.<?php endif; ?>
            </span>
        </div>

        <div class="card-3d mb-4" style="padding:24px">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead><tr><th>Ruangan</th><th>Paket Tersedia</th><th>Properti</th><th>Tema</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        <?php if (!empty($ruangan_list)): foreach($ruangan_list as $row):
                            $path_img = "../../assets/img/ruangan/" . ($row['Foto_Ruangan'] ?? '');
                            $img_src = (!empty($row['Foto_Ruangan']) && file_exists($path_img)) ? $path_img : $default_svg_avatar;
                            $is_deleted = ($row['Is_Deleted'] ?? 0) == 1;
                            $paket_list = $paket_per_ruangan[$row['ID_Ruangan']] ?? [];
                        ?>
                        <tr class="fade-in-up <?= $is_deleted ? 'row-deleted' : '' ?>">
                            <td><div class="d-flex align-items-center gap-3"><img src="<?= $img_src ?>" class="ruangan-preview" alt="<?= htmlspecialchars($row['Nama_Ruangan']) ?>"><div><div class="td-nama"><?= htmlspecialchars($row['Nama_Ruangan']) ?></div><div class="td-deskripsi"><?= htmlspecialchars($row['Deskripsi'] ?? '-') ?></div></div></div></td>
                            <td><?php if (!empty($paket_list)): foreach (array_slice($paket_list, 0, 2) as $paket): ?><span class="badge-paket"><?= htmlspecialchars($paket['Nama_Paket']) ?></span><?php endforeach; if (count($paket_list) > 2): ?><span class="badge-paket">+<?= count($paket_list) - 2 ?></span><?php endif; else: ?><span class="text-muted small">Belum terhubung</span><?php endif; ?></td>
                            <td class="td-relasi"><i class="bi bi-box-seam me-1 text-warning"></i><?= $row['total_properti'] ?? 0 ?> properti</td>
                            <td class="td-relasi"><i class="bi bi-palette me-1 text-info"></i><?= $row['total_tema'] ?? 0 ?> tema</td>
                            <td><?php if ($is_deleted): ?><span class="badge-status badge-terhapus"><span class="badge-dot"></span>Terhapus</span><?php else: ?><span class="badge-status badge-aktif"><span class="badge-dot"></span>Aktif</span><?php endif; ?></td>
                            <td class="text-center">
                                <?php if (!$is_deleted): ?>
                                    <!-- DETAIL (BULAT PERSIS SEPERTI GAMBAR) -->
                                    <button class="btn-action-circle btn-action-detail" onclick="bukaDetailModal(<?= $row['ID_Ruangan'] ?>)" title="Lihat Detail Ruangan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <!-- EDIT (BULAT PERSIS SEPERTI GAMBAR) -->
                                    <a href="edit.php?id=<?= $row['ID_Ruangan'] ?>" class="btn-action-circle btn-action-edit" title="Edit Ruangan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <!-- ARSIPKAN / TOGGLE SOFT DELETE (BULAT PERSIS SEPERTI GAMBAR) -->
                                    <button class="btn-action-circle btn-action-soft-delete" onclick="softDeleteConfirm(<?= $row['ID_Ruangan'] ?>, '<?= htmlspecialchars($row['Nama_Ruangan']) ?>')" title="Arsipkan Ruangan">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                <?php else: ?>
                                    <!-- UNTUK STATUS TERARSIP -->
                                    <!-- DETAIL DISABLED -->
                                    <button class="btn-action-circle btn-action-detail" style="opacity: 0.35; cursor: not-allowed;" disabled title="Data diarsipkan (Pulihkan dahulu untuk melihat detail)"><i class="bi bi-eye"></i></button>
                                    
                                    <!-- PULIHKAN / TOGGLE RESTORE (BULAT PERSIS SEPERTI GAMBAR) -->
                                    <button class="btn-action-circle btn-action-restore" onclick="restoreConfirm(<?= $row['ID_Ruangan'] ?>, '<?= htmlspecialchars($row['Nama_Ruangan']) ?>')" title="Pulihkan Ruangan">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    
                                    <!-- HAPUS PERMANEN (BULAT PERSIS SEPERTI GAMBAR) -->
                                    <button class="btn-action-circle btn-action-hard-delete" onclick="hardDeleteConfirm(<?= $row['ID_Ruangan'] ?>, '<?= htmlspecialchars($row['Nama_Ruangan']) ?>')" title="Hapus Permanen">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 mb-3 d-block" style="color:#cbd5e1"></i><p class="fw-bold">Tidak ada data ruangan yang sesuai.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_halaman > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">Menampilkan <span><?= $offset + 1 ?></span> - <span><?= min($offset + $limit, $total_records) ?></span> dari <span><?= $total_records ?></span> ruangan</div>
                <nav class="pagination-nav">
                    <?php if ($halaman > 1): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman - 1 ?>&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>&terhapus=<?= $filter_terhapus ?>" title="Sebelumnya"><i class="bi bi-chevron-left"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-left"></i></span><?php endif; ?>
                    <?php $start_page = max(1, $halaman - 2); $end_page = min($total_halaman, $halaman + 2); if ($start_page > 1) { echo '<a class="page-link-pag" href="list.php?halaman=1&cari=' . urlencode($cari) . '&sort=' . $sort . '&terhapus=' . $filter_terhapus . '">1</a>'; if ($start_page > 2) echo '<span class="page-link-pag disabled">...</span>'; } for ($i = $start_page; $i <= $end_page; $i++): ?><a class="page-link-pag <?= ($halaman == $i) ? 'active-pag' : '' ?>" href="list.php?halaman=<?= $i ?>&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>&terhapus=<?= $filter_terhapus ?>"><?= $i ?></a><?php endfor; if ($end_page < $total_halaman) { if ($end_page < $total_halaman - 1) echo '<span class="page-link-pag disabled">...</span>'; echo '<a class="page-link-pag" href="list.php?halaman=' . $total_halaman . '&cari=' . urlencode($cari) . '&sort=' . $sort . '&terhapus=' . $filter_terhapus . '">' . $total_halaman . '</a>'; } ?>
                    <?php if ($halaman < $total_halaman): ?><a class="page-link-pag" href="list.php?halaman=<?= $halaman + 1 ?>&cari=<?= urlencode($cari) ?>&sort=<?= $sort ?>&terhapus=<?= $filter_terhapus ?>" title="Selanjutnya"><i class="bi bi-chevron-right"></i></a><?php else: ?><span class="page-link-pag disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
                </nav>
            </div>
            <?php elseif ($total_records > 0): ?>
            <div class="pagination-wrapper"><div class="pagination-info">Menampilkan <span>1</span> - <span><?= $total_records ?></span> dari <span><?= $total_records ?></span> ruangan</div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL DETAIL SEKALIGUS SINKRONISASI FULL SPESIFIKASI DARI DETAIL.PHP -->
    <div class="modal fade modal-detail" id="modalDetailRuangan" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-door-open-fill me-2"></i>Detail Spesifikasi Ruangan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailRuanganContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div>
                        <p class="mt-3 text-muted">Memuat spesifikasi detail ruangan...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER MODAL -->
    <div class="modal fade" id="modalFilterData" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border:none;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,0.15);overflow:hidden">
                <div class="modal-header" style="border:none;padding:24px 24px 16px;background:#fff"><h5 class="fw-bold mb-0"><i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body" style="padding:0 24px 20px;background:#fff">
                    <div class="mb-3"><label style="display:block;font-size:0.75rem;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">URUT BERDASARKAN</label><select class="form-select" id="modalSort" style="border:2px solid #e2e8f0;border-radius:14px;padding:14px 18px;font-weight:600"><option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option><option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Z - A</option><option value="paket_asc" <?= $sort == 'paket_asc' ? 'selected' : '' ?>>Paket Terhubung (Sedikit)</option><option value="paket_desc" <?= $sort == 'paket_desc' ? 'selected' : '' ?>>Paket Terhubung (Banyak)</option></select></div>
                </div>
                <div class="modal-footer" style="border:none;padding:0 24px 24px;background:#fff;display:flex;gap:12px">
                    <button type="button" class="btn btn-secondary" style="flex:1;background:#f1f5f9;color:#475569;border:none;border-radius:14px;padding:14px 20px;font-weight:700" onclick="resetFilter()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</button>
                    <button type="button" class="btn btn-danger" style="flex:1;background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;border-radius:14px;padding:14px 20px;font-weight:700" onclick="applyFilter()"><i class="bi bi-check-lg me-2"></i>Terapkan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PROFILE BIODATA -->
    <div class="modal fade" id="modalBiodataAdmin" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(8px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); background: #fff;">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center"><h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-badge-fill text-danger me-2"></i>Profil Anda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body px-4 pb-4 pt-3">
            <div class="text-center mb-4">
              <div class="profile-preview-box" style="width: 100px; height: 100px; border: 3px solid var(--s-pink); margin: 0 auto; border-radius: 50%; overflow: hidden;"><img src="<?= $foto_admin_src ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;"></div>
              <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_admin) ?></h5><span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size: 0.72rem; border-radius: 50px; font-weight: 700;">Admin</span>
            </div>
            <div class="card-3d p-3 border-0 mb-3" style="border-radius: 20px; background-color: #f8fafc;">
              <div class="row g-3">
                <div class="col-12"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Email Karyawan</small><span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($admin_data['Email_Karyawan'] ?? 'admin@spotlight.com') ?></span></div>
                <div class="col-12 border-top pt-2"><small class="text-muted d-block fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Hak Akses Sistem</small><span class="fw-bold text-dark" style="font-size: 0.85rem;">Administrator (Admin)</span></div>
              </div>
            </div>
            <button class="btn btn-reg-header shadow-sm py-3 mt-0 w-100" data-bs-dismiss="modal" style="border-radius: 14px !important; background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #fff; border: none; font-weight: 700;">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)'}}})});
        var filterModal;
        function bukaModalFilter(){filterModal=new bootstrap.Modal(document.getElementById('modalFilterData'));filterModal.show()}
        function applyFilter(){document.getElementById('hiddenSort').value=document.getElementById('modalSort').value;document.getElementById('mainSearchForm').submit()}
        function resetFilter(){document.getElementById('modalSort').value='nama_asc';document.getElementById('hiddenSort').value='nama_asc';document.getElementById('mainSearchForm').submit()}
        
        function softDeleteConfirm(id,nama){Swal.fire({title:'Arsipkan Ruangan?',html:'Ruangan <b>"'+nama+'"</b> akan diarsipkan (soft delete).<br><br><span style="color:#059669"><i class="bi bi-info-circle-fill"></i> Data masih tersimpan di arsip dan bisa dipulihkan.</span>',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#718096',confirmButtonText:'<i class="bi bi-archive-fill"></i> Ya, Arsipkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed)window.location.href='action_ruangan.php?aksi=soft_delete&id='+id})}
        function restoreConfirm(id,nama){Swal.fire({title:'Pulihkan Ruangan?',html:'Ruangan <b>"'+nama+'"</b> akan dikembalikan ke daftar aktif studio.',icon:'question',showCancelButton:true,confirmButtonColor:'#059669',cancelButtonColor:'#718096',confirmButtonText:'<i class="bi bi-arrow-counterclockwise"></i> Ya, Pulihkan',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed)window.location.href='action_ruangan.php?aksi=restore&id='+id})}
        
        // BUG FIX: Kunci ID parameter kueri diperbaiki dari ID_Karyawan yang salah kirim menjadi ID_Ruangan yang benar
        function hardDeleteConfirm(id,nama){Swal.fire({title:'HAPUS PERMANEN?',html:'Ruangan <b>"'+nama+'"</b> akan dihapus <span style="color:#dc2626;font-weight:800">PERMANEN</span> dari database!<br><br><i class="bi bi-exclamation-triangle-fill" style="color:#dc2626"></i> Tindakan tidak bisa dibatalkan!',icon:'error',showCancelButton:true,confirmButtonColor:'#7c2d12',cancelButtonColor:'#718096',confirmButtonText:'<i class="bi bi-trash-fill"></i> Ya, Hapus Permanen',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed)window.location.href='action_ruangan.php?aksi=hard_delete&id='+id})}
        
        var detailModal;
        function bukaDetailModal(id){
            detailModal=new bootstrap.Modal(document.getElementById('modalDetailRuangan'));
            detailModal.show();
            // Memanggil AJAX internal list.php untuk memuat detail spesifikasi secara instan
            fetch('list.php?ajax_id='+id)
                .then(response=>response.text())
                .then(html=>{
                    document.getElementById('detailRuanganContent').innerHTML=html;
                })
                .catch(()=>{
                    document.getElementById('detailRuanganContent').innerHTML='<div class="alert alert-danger rounded-3 m-0">Gagal memuat spesifikasi detail ruangan.</div>';
                });
        }
        
        function bukaModalBiodata() {
            var modalBiodata = new bootstrap.Modal(document.getElementById('modalBiodataAdmin'));
            modalBiodata.show();
        }
        
        function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed)window.location.href='../../logout.php'})}
        function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed)window.location.href='../../index.php'})}
        
        function updateLiveClock(){const now=new Date();const days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];const dayName=days[now.getDay()];const day=now.getDate();const monthName=months[now.getMonth()];const year=now.getFullYear();let hours=now.getHours();let minutes=now.getMinutes();let seconds=now.getSeconds();hours=hours<10?'0'+hours:hours;minutes=minutes<10?'0'+minutes:minutes;seconds=seconds<10?'0'+seconds:seconds;document.getElementById('live-clock').innerText=dayName+', '+day+' '+monthName+' '+year+' - '+hours+':'+minutes+':'+seconds+' WIB'}
        setInterval(updateLiveClock,1000);updateLiveClock();
    </script>

    <?php if(isset($_GET['status_sukses'])): ?>
    <script>
        var msg='';var t_icon='success';var t_title='Berhasil!';
        if('<?= $_GET['status_sukses'] ?>'=='tambah')msg='Ruangan baru berhasil ditambahkan!';
        else if('<?= $_GET['status_sukses'] ?>'=='edit')msg='Data ruangan berhasil diperbarui!';
        else if('<?= $_GET['status_sukses'] ?>'=='soft_delete'){msg='Ruangan berhasil diarsipkan! Data dipindahkan ke tab Terhapus.';t_title='Arsip Berhasil'}
        else if('<?= $_GET['status_sukses'] ?>'=='restore'){msg='Ruangan berhasil dipulihkan ke daftar aktif studio!';t_title='Restore Berhasil'}
        else if('<?= $_GET['status_sukses'] ?>'=='hard_delete'){msg='Ruangan studio berhasil dihapus secara permanen dari sistem!';t_title='Hapus Permanen'}
        else if('<?= $_GET['status_sukses'] ?>'=='error_relasi'){msg='Gagal Hapus! Ruangan ini masih terikat dengan data transaksi booking atau jadwal studio aktif.';t_icon='error';t_title='Relasi Terdeteksi'}
        else if('<?= $_GET['status_sukses'] ?>'=='error'){msg='<?= $_GET['message'] ?? 'Terjadi kesalahan!' ?>';t_icon='error';t_title='Gagal!'}
        Swal.fire({icon:t_icon,title:t_title,text:msg,confirmButtonColor:'#D53D66'});
    </script>
    <?php endif; ?>
</body>
</html>