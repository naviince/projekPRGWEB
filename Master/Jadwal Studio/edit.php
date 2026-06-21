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
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// Ambil Profil Admin — Path: karyawan/ (BUKAN pelanggan/)
$q_admin = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", [$id_admin]);
$d_admin = sqlsrv_fetch_array($q_admin, SQLSRV_FETCH_ASSOC);
if ($d_admin) { $d_admin = array_change_key_case($d_admin, CASE_LOWER); }
$nama_admin = $d_admin['nama_karyawan'] ?? 'Administrator';
$foto_admin = $d_admin['foto_profil'] ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin)) 
    ? "../../assets/img/karyawan/" . $foto_admin 
    : $default_svg_avatar;

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { error_log("[safe_sqlsrv_fetch] SQL Error: " . json_encode(sqlsrv_errors())); return null; }
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

function safe_sqlsrv_fetch_all($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { error_log("[safe_sqlsrv_fetch_all] SQL Error: " . json_encode(sqlsrv_errors())); return []; }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $results[] = $row;
    sqlsrv_free_stmt($stmt);
    return $results;
}

function safe_sqlsrv_count($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { error_log("[safe_sqlsrv_count] SQL Error: " . json_encode(sqlsrv_errors())); return 0; }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['total'] ?? 0;
}

$id_jadwal = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_jadwal <= 0) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("ID Jadwal tidak valid"));
    exit();
}

$jadwal = safe_sqlsrv_fetch($conn,
    "SELECT j.*, r.Nama_Ruangan FROM Jadwal_Studio j INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan WHERE j.ID_Jadwal = ?",
    [$id_jadwal]
);

if (!$jadwal) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal tidak ditemukan"));
    exit();
}

if ((int)$jadwal['Status_Jadwal'] == STATUS_JADWAL_BOOKED) {
    header("Location: list.php?status_sukses=error&message=" . urlencode("Jadwal sedang booked, tidak bisa diedit. Selesaikan order terlebih dahulu."));
    exit();
}

$ruangan_list = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Ruangan, Nama_Ruangan, Deskripsi, Foto_Ruangan FROM Ruangan WHERE Status = ? AND Is_Deleted = 0 ORDER BY Nama_Ruangan",
    [STATUS_DATA_AKTIF]
);

$paket_list = safe_sqlsrv_fetch_all($conn,
    "SELECT ID_Paket, Nama_Paket, Durasi_Waktu, Harga_Paket, Kapasitas_Orang, Foto_Paket FROM Paket_Foto WHERE Status = ? AND Is_Deleted = 0 ORDER BY Nama_Paket",
    [STATUS_DATA_AKTIF]
);

$paket_ruangan_list = safe_sqlsrv_fetch_all($conn,
    "SELECT pr.ID_Paket, pr.ID_Ruangan, r.Nama_Ruangan FROM Paket_Ruangan pr INNER JOIN Ruangan r ON pr.ID_Ruangan = r.ID_Ruangan WHERE r.Status = ? AND r.Is_Deleted = 0 ORDER BY pr.ID_Paket, r.Nama_Ruangan",
    [STATUS_DATA_AKTIF]
);

$paket_ruangan_map = [];
foreach ($paket_ruangan_list as $pr) {
    $pid = (int)$pr['ID_Paket'];
    if (!isset($paket_ruangan_map[$pid])) $paket_ruangan_map[$pid] = [];
    $paket_ruangan_map[$pid][] = (int)$pr['ID_Ruangan'];
}

$paket_detail_map = [];
foreach ($paket_list as $p) {
    $paket_detail_map[(int)$p['ID_Paket']] = [
        'nama' => $p['Nama_Paket'],
        'durasi' => (int)$p['Durasi_Waktu'],
        'harga' => $p['Harga_Paket']
    ];
}

$default_id_ruangan = $jadwal['ID_Ruangan'];
$default_tanggal = $jadwal['Tanggal_Jadwal'] instanceof DateTime ? $jadwal['Tanggal_Jadwal']->format('Y-m-d') : $jadwal['Tanggal_Jadwal'];
$default_jam_mulai = $jadwal['Jam_Mulai'] instanceof DateTime ? $jadwal['Jam_Mulai']->format('H:i') : substr($jadwal['Jam_Mulai'], 0, 5);
$default_status_jadwal = (int)$jadwal['Status_Jadwal'];
$default_keterangan = $jadwal['Keterangan'] ?? '';

// Hitung durasi jadwal saat ini agar $existing_durasi tidak undefined
$existing_durasi = 0;
if ($jadwal['Jam_Mulai'] && $jadwal['Jam_Selesai']) {
    $mulai = $jadwal['Jam_Mulai'] instanceof DateTime ? $jadwal['Jam_Mulai'] : new DateTime($jadwal['Jam_Mulai']);
    $selesai = $jadwal['Jam_Selesai'] instanceof DateTime ? $jadwal['Jam_Selesai'] : new DateTime($jadwal['Jam_Selesai']);
    $diff = $mulai->diff($selesai);
    $existing_durasi = ($diff->h * 60) + $diff->i;
}

$matched_paket_id = (int)$jadwal['ID_Paket'];
$matched_paket_nama = '';
$matched_durasi = $existing_durasi;

if ($matched_paket_id > 0 && isset($paket_detail_map[$matched_paket_id])) {
    $matched_paket_nama = $paket_detail_map[$matched_paket_id]['nama'];
    $matched_durasi = $paket_detail_map[$matched_paket_id]['durasi'];
}

$errors = [];
$old_values = $_POST ?? [];
$success = false;

if (isset($_POST['update'])) {
    $id_ruangan = isset($_POST['id_ruangan']) ? (int)$_POST['id_ruangan'] : 0;
    $id_paket = isset($_POST['id_paket']) ? (int)$_POST['id_paket'] : 0;
    $tanggal_jadwal = isset($_POST['tanggal_jadwal']) ? trim($_POST['tanggal_jadwal']) : '';
    $jam_mulai = isset($_POST['jam_mulai']) ? trim($_POST['jam_mulai']) : '';
    $status_jadwal = isset($_POST['status_jadwal']) ? (int)$_POST['status_jadwal'] : STATUS_JADWAL_TERSEDIA;
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

    $durasi_waktu = 0; $nama_paket = '';
    if ($id_paket <= 0) $errors['id_paket'] = "Paket Foto harus dipilih!";
    elseif (!isset($paket_ruangan_map[$id_paket]) || empty($paket_ruangan_map[$id_paket])) $errors['id_paket'] = "Paket Foto ini tidak memiliki ruangan yang terhubung.";
    else {
        $paket = safe_sqlsrv_fetch($conn, "SELECT Nama_Paket, Durasi_Waktu FROM Paket_Foto WHERE ID_Paket = ? AND Status = ? AND Is_Deleted = 0", [$id_paket, STATUS_DATA_AKTIF]);
        if (!$paket) $errors['id_paket'] = "Paket Foto tidak valid atau tidak aktif!";
        else { $durasi_waktu = (int)$paket['Durasi_Waktu']; $nama_paket = $paket['Nama_Paket']; }
    }

    $nama_ruangan = '';
    if ($id_ruangan <= 0) $errors['id_ruangan'] = "Ruangan harus dipilih!";
    else {
        $cek_ruangan = safe_sqlsrv_fetch($conn, "SELECT ID_Ruangan, Nama_Ruangan FROM Ruangan WHERE ID_Ruangan = ? AND Status = ? AND Is_Deleted = 0", [$id_ruangan, STATUS_DATA_AKTIF]);
        if (!$cek_ruangan) $errors['id_ruangan'] = "Ruangan tidak valid atau tidak aktif!";
        else $nama_ruangan = $cek_ruangan['Nama_Ruangan'];
    }

    if ($id_ruangan > 0 && $id_paket > 0 && empty($errors['id_ruangan']) && empty($errors['id_paket'])) {
        $cek_paket_ruangan = safe_sqlsrv_fetch($conn, "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?", [$id_paket, $id_ruangan]);
        if (!$cek_paket_ruangan) $errors['id_ruangan'] = "Ruangan yang dipilih tidak tersedia untuk paket foto ini!";
    }

    if (empty($tanggal_jadwal)) $errors['tanggal_jadwal'] = "Tanggal jadwal wajib diisi!";
    else {
        $tgl_obj = DateTime::createFromFormat('Y-m-d', $tanggal_jadwal);
        if (!$tgl_obj || $tgl_obj->format('Y-m-d') !== $tanggal_jadwal) $errors['tanggal_jadwal'] = "Format tanggal tidak valid (YYYY-MM-DD)!";
    }

    if (empty($jam_mulai)) $errors['jam_mulai'] = "Jam mulai wajib diisi!";
    elseif (!preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/', $jam_mulai)) $errors['jam_mulai'] = "Format jam mulai tidak valid (HH:MM)!";

    if (!in_array($status_jadwal, [STATUS_JADWAL_TERSEDIA, STATUS_JADWAL_MAINTENANCE])) $errors['status_jadwal'] = "Status jadwal tidak valid!";

    $jam_selesai = '';
    if (empty($errors) && $durasi_waktu > 0 && !empty($jam_mulai)) {
        $mulai_obj = DateTime::createFromFormat('H:i', $jam_mulai);
        if ($mulai_obj) {
            $mulai_obj->modify("+{$durasi_waktu} minutes");
            $jam_selesai = $mulai_obj->format('H:i');
            $jam_mulai_obj = DateTime::createFromFormat('H:i', $jam_mulai);
            $buka = DateTime::createFromFormat('H:i', '08:00');
            if ($jam_mulai_obj < $buka) $errors['jam_mulai'] = "Jam mulai minimal 08:00 (jam operasional)!";
            $tutup = DateTime::createFromFormat('H:i', '20:00');
            if ($mulai_obj > $tutup) $errors['jam_mulai'] = "Jam selesai ({$jam_selesai}) melebihi jam tutup (20:00). Paket {$nama_paket} membutuhkan {$durasi_waktu} menit. Pilih jam mulai lebih awal!";
        } else $errors['jam_mulai'] = "Gagal menghitung jam selesai!";
    }

    if (empty($errors) && $id_ruangan > 0 && !empty($tanggal_jadwal) && !empty($jam_selesai)) {
        $cek_overlap = safe_sqlsrv_count($conn,
            "SELECT COUNT(*) as total FROM Jadwal_Studio WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Is_Deleted = 0 AND Status = 1 AND ID_Jadwal <> ? AND ((Jam_Mulai < ? AND Jam_Selesai > ?) OR (Jam_Mulai >= ? AND Jam_Mulai < ?) OR (Jam_Selesai > ? AND Jam_Selesai <= ?))",
            [$id_ruangan, $tanggal_jadwal, $id_jadwal, $jam_selesai . ':00', $jam_mulai . ':00', $jam_mulai . ':00', $jam_selesai . ':00', $jam_mulai . ':00', $jam_selesai . ':00']
        );
        if ($cek_overlap > 0) $errors['jam_mulai'] = "Jadwal bertabrakan dengan {$cek_overlap} slot existing. Pilih jam atau tanggal lain!";
    }

    if (empty($errors)) {
        if (empty($keterangan)) $keterangan = "Slot {$nama_paket} {$nama_ruangan}";
        error_log("[Jadwal Update] ID: {$id_jadwal}, Ruangan: {$id_ruangan}, ID_Paket: {$id_paket}, Tanggal: {$tanggal_jadwal}, Jam: {$jam_mulai}-{$jam_selesai}");
        
        $begin_result = sqlsrv_begin_transaction($conn);
        if ($begin_result === false) {
            $sql_errors = sqlsrv_errors();
            $error_msg = "Gagal memulai transaksi database.";
            if ($sql_errors) foreach ($sql_errors as $err) $error_msg .= " [SQLSTATE: {$err['SQLSTATE']}, Code: {$err['code']}, Msg: {$err['message']}]";
            $errors['general'] = $error_msg;
        } else {
            $sql = "UPDATE Jadwal_Studio SET ID_Ruangan = ?, ID_Paket = ?, Tanggal_Jadwal = ?, Jam_Mulai = ?, Jam_Selesai = ?, Keterangan = ?, Status_Jadwal = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
            $params = [$id_ruangan, $id_paket, $tanggal_jadwal, $jam_mulai . ':00', $jam_selesai . ':00', $keterangan, $status_jadwal, $nama_admin, $id_jadwal];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                sqlsrv_commit($conn);
                $success = true;
                error_log("[Jadwal Update] SUCCESS");
            } else {
                sqlsrv_rollback($conn);
                $sql_errors = sqlsrv_errors();
                $error_details = [];
                if ($sql_errors) foreach ($sql_errors as $error) $error_details[] = "[SQLSTATE: {$error['SQLSTATE']}, Code: {$error['code']}] {$error['message']}";
                $error_msg = "Gagal mengupdate jadwal. " . (!empty($error_details) ? implode(" | ", $error_details) : "Silakan coba lagi!");
                $errors['general'] = $error_msg;
                error_log("[Jadwal Update] FAILED: " . $error_msg);
            }
        }
    }
}

$selected_paket_id = isset($old_values['id_paket']) ? (int)$old_values['id_paket'] : $matched_paket_id;
$selected_paket_durasi = 0; $selected_paket_nama = '';
if ($selected_paket_id > 0 && isset($paket_detail_map[$selected_paket_id])) {
    $selected_paket_durasi = $paket_detail_map[$selected_paket_id]['durasi'];
    $selected_paket_nama = $paket_detail_map[$selected_paket_id]['nama'];
}
$selected_ruangan_id = isset($old_values['id_ruangan']) ? (int)$old_values['id_ruangan'] : $default_id_ruangan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Jadwal Studio - SpotLight Studio</title>
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--p-pink:#D53D66;--d-pink:#CA3366;--s-pink:#FFF0F3;--light-pink:#FFE4E9;--accent-pink:#E85D84;--text-dark:#1e1e24;--text-muted:#718096;--sidebar-bg:#ffffff;--body-bg:#f8fafc;--t:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275)}
*{margin:0;padding:0;box-sizing:border-box}
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
.form-card{background:#fff;border-radius:24px;border:1px solid rgba(255,228,233,0.8);box-shadow:0 8px 24px rgba(213,61,102,0.03);padding:40px;max-width:900px;margin:0 auto}
.section-title{font-weight:800;font-size:1rem;color:var(--text-dark);margin-bottom:20px;display:flex;align-items:center;gap:10px}
.section-title i{color:var(--p-pink);font-size:1.2rem}
.form-label-custom{font-weight:800;font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;display:block}
.form-input-custom{width:100%;border-radius:14px;padding:14px 18px;border:2px solid #e2e8f0;background:#f8fafc;font-weight:600;font-size:0.9rem;color:var(--text-dark);transition:var(--t);font-family:'Plus Jakarta Sans',sans-serif}
.form-input-custom:focus{outline:none;border-color:var(--p-pink);background:#fff;box-shadow:0 0 0 4px rgba(213,61,102,0.08)}
.form-input-custom.is-invalid{border-color:#ef4444;background:#fef2f2}
.form-input-custom.is-invalid:focus{box-shadow:0 0 0 4px rgba(239,68,68,0.08)}
select.form-input-custom{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 18px center;padding-right:44px}
.error-text{color:#ef4444;font-size:0.8rem;font-weight:700;margin-top:6px;display:flex;align-items:center;gap:5px}
.alert-error{background:#fef2f2;border:2px solid #fecaca;border-radius:16px;padding:16px 20px;margin-bottom:25px;color:#991b1b;font-weight:700;font-size:0.9rem;display:flex;align-items:center;gap:10px}
.alert-error i{font-size:1.2rem;color:#dc2626}
.helper-text{font-size:0.75rem;color:#94a3b8;font-weight:600;margin-top:6px;display:flex;align-items:center;gap:5px}
.helper-text i{color:var(--p-pink)}
.paket-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:8px}
.paket-card{border:2px solid #e2e8f0;border-radius:16px;padding:20px;cursor:pointer;transition:var(--t);text-align:center;background:#fff}
.paket-card:hover{border-color:var(--light-pink);transform:translateY(-4px) scale(1.02)}
.paket-card.selected{border-color:var(--p-pink);background:var(--s-pink);box-shadow:0 4px 15px rgba(213,61,102,0.15)}
.paket-card .paket-nama{font-weight:800;font-size:0.95rem;color:var(--text-dark);margin-bottom:6px}
.paket-card .paket-durasi{font-size:0.8rem;color:var(--p-pink);font-weight:700;background:var(--light-pink);padding:4px 12px;border-radius:50px;display:inline-block}
.paket-card .paket-harga{font-size:0.85rem;color:var(--text-muted);font-weight:600;margin-top:8px}
.paket-card.selected .paket-nama{color:var(--p-pink)}
.ruangan-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:8px}
.ruangan-card{border:2px solid #e2e8f0;border-radius:16px;padding:16px;cursor:pointer;transition:var(--t);text-align:center;background:#fff}
.ruangan-card:hover{border-color:var(--light-pink);transform:translateY(-4px) scale(1.02)}
.ruangan-card.selected{border-color:var(--p-pink);background:var(--s-pink);box-shadow:0 4px 15px rgba(213,61,102,0.15)}
.ruangan-card .ruangan-nama{font-weight:800;font-size:0.9rem;color:var(--text-dark)}
.ruangan-card.selected .ruangan-nama{color:var(--p-pink)}
.ruangan-card.disabled{opacity:0.35;pointer-events:none;filter:grayscale(1);cursor:not-allowed;border-color:#e2e8f0!important;background:#f8fafc!important}
.ruangan-card.disabled .ruangan-nama{color:#94a3b8!important}
.ruangan-card.disabled:hover{transform:none;border-color:#e2e8f0!important}
.ruangan-filter-notice{background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:0.85rem;color:#92400e;font-weight:600;display:none;align-items:center;gap:8px}
.ruangan-filter-notice.show{display:flex}
.ruangan-filter-notice i{color:#f59e0b;font-size:1.1rem}
.durasi-info{background:linear-gradient(135deg,var(--s-pink),var(--light-pink));border:2px solid var(--light-pink);border-radius:16px;padding:20px;margin-bottom:24px;display:none}
.durasi-info.active{display:block}
.durasi-info .durasi-title{font-weight:800;font-size:1rem;color:var(--p-pink);margin-bottom:8px}
.durasi-info .durasi-detail{font-size:0.9rem;color:var(--text-dark);font-weight:600}
.durasi-info .durasi-hint{font-size:0.8rem;color:var(--text-muted);margin-top:8px;padding-top:8px;border-top:1px dashed var(--light-pink)}
.jam-preview{background:#f8fafc;border-radius:14px;padding:16px 20px;display:none;align-items:center;gap:16px;margin-top:12px;border:2px solid #e2e8f0}
.jam-preview.active{display:flex}
.jam-preview-item{text-align:center}
.jam-preview-item .jam-label{font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:4px}
.jam-preview-item .jam-value{font-size:1.3rem;font-weight:900;color:var(--p-pink)}
.jam-preview-arrow{color:var(--text-muted);font-size:1.5rem}
.jam-preview-durasi{margin-left:auto;background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;padding:8px 16px;border-radius:50px;font-weight:800;font-size:0.85rem}
.operating-hours{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;background:#ecfdf5;color:#059669;border-radius:50px;font-size:0.8rem;font-weight:700;margin-bottom:16px}
.operating-hours i{font-size:1rem}
.current-info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:16px 20px;margin-bottom:24px}
.current-info .current-title{font-weight:800;font-size:0.9rem;color:#2563eb;margin-bottom:8px}
.current-info .current-detail{font-size:0.85rem;color:#4a5568;font-weight:600}
.info-card{background:linear-gradient(135deg,#FFF0F3,#FFF8F0);border-radius:16px;padding:16px 20px;margin-bottom:25px;border:1px solid rgba(255,228,233,0.8);display:flex;align-items:center;gap:12px}
.info-card i{font-size:1.5rem;color:var(--p-pink)}
.info-card .info-text{font-size:0.85rem;color:#4a5568;font-weight:600;line-height:1.5}
.info-card .info-text strong{color:var(--p-pink)}
.btn-simpan{background:linear-gradient(135deg,var(--p-pink),var(--d-pink));color:#fff;border:none;border-radius:16px;padding:16px 32px;font-weight:800;font-size:1rem;transition:var(--t);box-shadow:0 10px 25px rgba(213,61,102,0.3);display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-simpan:hover{transform:translateY(-3px);box-shadow:0 15px 35px rgba(213,61,102,0.4);color:#fff}
.btn-kembali{background:#f1f5f9;color:#475569;border:none;border-radius:16px;padding:14px 28px;font-weight:700;font-size:0.95rem;transition:var(--t);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn-kembali:hover{background:#e2e8f0;color:var(--text-dark);transform:translateY(-2px)}
.btn-group-bottom{display:flex;gap:12px;justify-content:flex-end;margin-top:30px;padding-top:25px;border-top:2px solid #f1f5f9}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
input[type="radio"].card-radio{display:none}
@media(max-width:992px){.main-content{margin-left:0;padding:20px}.sidebar{transform:translateX(-100%)}.form-card{padding:25px}.btn-group-bottom{flex-direction:column}.btn-simpan,.btn-kembali{width:100%;justify-content:center}.form-grid{grid-template-columns:1fr}.paket-grid{grid-template-columns:repeat(2,1fr)}.ruangan-grid{grid-template-columns:repeat(2,1fr)}}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.fade-in-up{animation:fadeIn 0.5s ease-out}
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
<div class="dashboard-header fade-in-up">
<div><h3 class="fw-bold mb-1">Edit Jadwal Studio</h3><p class="text-muted small mb-0">ID Jadwal: #<?= $id_jadwal ?> — Ubah slot jadwal dengan validasi durasi.</p></div>
<div class="d-flex align-items-center gap-3">
<span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background:var(--light-pink);font-weight:700;border-radius:10px"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
<div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
</div>
</div>

<div class="form-card fade-in-up">
<div class="current-info">
<div class="current-title"><i class="bi bi-info-circle-fill"></i> Data Jadwal Saat Ini</div>
<div class="current-detail">
<strong>Ruangan:</strong> <?= htmlspecialchars($jadwal['Nama_Ruangan']) ?> &nbsp;|&nbsp;
<strong>Tanggal:</strong> <?= $default_tanggal ?> &nbsp;|&nbsp;
<strong>Jam:</strong> <?= $default_jam_mulai ?> - <?= $jadwal['Jam_Selesai'] instanceof DateTime ? $jadwal['Jam_Selesai']->format('H:i') : substr($jadwal['Jam_Selesai'], 0, 5) ?> &nbsp;|&nbsp;
<strong>Durasi:</strong> <?= $existing_durasi ?> menit &nbsp;|&nbsp;
<strong>Status:</strong> <?php if ($default_status_jadwal == STATUS_JADWAL_TERSEDIA): ?><span style="color:#059669">Tersedia</span><?php elseif ($default_status_jadwal == STATUS_JADWAL_MAINTENANCE): ?><span style="color:#d97706">Maintenance</span><?php endif; ?>
</div>
</div>
<div class="info-card"><i class="bi bi-info-circle-fill"></i><div class="info-text"><strong>Perhatian:</strong> Pilih <strong>Paket Foto</strong> untuk mengubah durasi slot. Ruangan akan disesuaikan otomatis dengan paket yang dipilih. Jam operasional: <strong>08:00 - 20:00</strong>.</div></div>
<?php if(isset($errors['general'])): ?><div class="alert-error"><i class="bi bi-exclamation-octagon-fill"></i><?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>
<form method="POST" id="formJadwal">
<div class="section-title"><i class="bi bi-1-circle-fill"></i>Pilih Paket Foto <span class="text-danger">*</span></div>
<p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;font-weight:600">Durasi slot saat ini: <strong><?= $existing_durasi ?> menit</strong>. Pilih paket untuk mengubah.</p>
<div class="paket-grid">
<?php foreach ($paket_list as $paket): $is_selected = (isset($old_values['id_paket']) && $old_values['id_paket'] == $paket['ID_Paket']) || (!isset($old_values['id_paket']) && $matched_paket_id == $paket['ID_Paket']); ?>
<label class="paket-card <?= $is_selected ? 'selected' : '' ?>" onclick="selectPaket(this,<?= $paket['ID_Paket'] ?>,<?= $paket['Durasi_Waktu'] ?>,'<?= htmlspecialchars($paket['Nama_Paket']) ?>',<?= $paket['Harga_Paket'] ?>)"><input type="radio" name="id_paket" value="<?= $paket['ID_Paket'] ?>" class="card-radio" <?= $is_selected ? 'checked' : '' ?>><div class="paket-nama"><?= htmlspecialchars($paket['Nama_Paket']) ?></div><div class="paket-durasi"><?= $paket['Durasi_Waktu'] ?> Menit</div><div class="paket-harga">Rp <?= number_format($paket['Harga_Paket'],0,',','.') ?></div></label>
<?php endforeach; ?>
</div>
<?php if(isset($errors['id_paket'])): ?><span class="error-text"><?= $errors['id_paket'] ?></span><?php endif; ?>
<div class="durasi-info" id="durasiInfo"><div class="durasi-title"><i class="bi bi-info-circle-fill"></i> Informasi Durasi</div><div class="durasi-detail" id="durasiDetail"></div><div class="durasi-hint" id="durasiHint"></div></div>
<hr style="border:none;border-top:1px solid #f1f5f9;margin:32px 0">
<div class="section-title"><i class="bi bi-2-circle-fill"></i>Pilih Ruangan <span class="text-danger">*</span></div>
<div class="ruangan-filter-notice" id="ruanganFilterNotice"><i class="bi bi-funnel-fill"></i><span id="ruanganFilterText">Pilih paket foto terlebih dahulu untuk melihat ruangan yang tersedia.</span></div>
<div class="ruangan-grid" id="ruanganGrid">
<?php foreach ($ruangan_list as $ruangan): $is_selected = (isset($old_values['id_ruangan']) && $old_values['id_ruangan'] == $ruangan['ID_Ruangan']) || (!isset($old_values['id_ruangan']) && $default_id_ruangan == $ruangan['ID_Ruangan']); $current_paket_id = $selected_paket_id; $valid_for_paket = true; if ($current_paket_id > 0) $valid_for_paket = in_array((int)$ruangan['ID_Ruangan'], $paket_ruangan_map[$current_paket_id] ?? []); ?>
<label class="ruangan-card <?= $is_selected ? 'selected' : '' ?> <?= !$valid_for_paket ? 'disabled' : '' ?>" data-ruangan-id="<?= $ruangan['ID_Ruangan'] ?>" onclick="selectRuangan(this,<?= $ruangan['ID_Ruangan'] ?>,'<?= htmlspecialchars($ruangan['Nama_Ruangan']) ?>')"><input type="radio" name="id_ruangan" value="<?= $ruangan['ID_Ruangan'] ?>" class="card-radio" <?= $is_selected ? 'checked' : '' ?>><div class="ruangan-nama"><?= htmlspecialchars($ruangan['Nama_Ruangan']) ?></div></label>
<?php endforeach; ?>
</div>
<?php if(isset($errors['id_ruangan'])): ?><span class="error-text"><?= $errors['id_ruangan'] ?></span><?php endif; ?>
<hr style="border:none;border-top:1px solid #f1f5f9;margin:32px 0">
<div class="section-title"><i class="bi bi-3-circle-fill"></i>Tanggal & Waktu <span class="text-danger">*</span></div>
<div class="operating-hours"><i class="bi bi-clock"></i>Jam Operasional: 08:00 - 20:00 WIB</div>
<div class="form-grid mb-3">
<div><label class="form-label-custom">Tanggal Jadwal <span class="text-danger">*</span></label><input type="date" name="tanggal_jadwal" class="form-input-custom <?= isset($errors['tanggal_jadwal']) ? 'is-invalid' : '' ?>" value="<?= isset($old_values['tanggal_jadwal']) ? htmlspecialchars($old_values['tanggal_jadwal']) : $default_tanggal ?>" min="<?= date('Y-m-d') ?>" required><?php if(isset($errors['tanggal_jadwal'])): ?><span class="error-text"><?= $errors['tanggal_jadwal'] ?></span><?php endif; ?></div>
<div><label class="form-label-custom">Jam Mulai <span class="text-danger">*</span></label><input type="time" name="jam_mulai" class="form-input-custom <?= isset($errors['jam_mulai']) ? 'is-invalid' : '' ?>" value="<?= isset($old_values['jam_mulai']) ? htmlspecialchars($old_values['jam_mulai']) : $default_jam_mulai ?>" min="08:00" max="19:30" step="1800" required onchange="updateJamPreview()"><?php if(isset($errors['jam_mulai'])): ?><span class="error-text"><?= $errors['jam_mulai'] ?></span><?php endif; ?><div class="helper-text"><i class="bi bi-info-circle"></i>Pilih jam mulai, sistem akan menghitung jam selesai otomatis berdasarkan durasi paket.</div></div>
</div>
<div class="jam-preview" id="jamPreview"><div class="jam-preview-item"><div class="jam-label">Mulai</div><div class="jam-value" id="previewMulai">--:--</div></div><div class="jam-preview-arrow"><i class="bi bi-arrow-right"></i></div><div class="jam-preview-item"><div class="jam-label">Selesai</div><div class="jam-value" id="previewSelesai">--:--</div></div><div class="jam-preview-durasi" id="previewDurasi">-- Menit</div></div>
<hr style="border:none;border-top:1px solid #f1f5f9;margin:32px 0">
<div class="section-title"><i class="bi bi-4-circle-fill"></i>Status & Keterangan</div>
<div class="form-grid mb-3">
<div><label class="form-label-custom">Status Jadwal</label><select name="status_jadwal" class="form-input-custom"><option value="<?= STATUS_JADWAL_TERSEDIA ?>" <?= ($default_status_jadwal == STATUS_JADWAL_TERSEDIA) ? 'selected' : '' ?>>Tersedia</option><option value="<?= STATUS_JADWAL_MAINTENANCE ?>" <?= ($default_status_jadwal == STATUS_JADWAL_MAINTENANCE) ? 'selected' : '' ?>>Maintenance</option></select><div class="helper-text"><i class="bi bi-info-circle"></i>Status "Booked" akan otomatis saat ada order.</div></div>
<div><label class="form-label-custom">Keterangan <span style="color:#94a3b8;font-weight:500">(opsional)</span></label><input type="text" name="keterangan" class="form-input-custom" value="<?= isset($old_values['keterangan']) ? htmlspecialchars($old_values['keterangan']) : htmlspecialchars($default_keterangan) ?>" placeholder="Contoh: Slot Basic Studio A"><div class="helper-text"><i class="bi bi-magic"></i>Akan di-generate otomatis: "Slot [Paket] [Ruangan]"</div></div>
</div>
<div class="btn-group-bottom"><a href="list.php" class="btn-kembali"><i class="bi bi-arrow-left"></i>Kembali</a><button type="submit" name="update" class="btn-simpan"><i class="bi bi-check-circle-fill"></i>Simpan Perubahan</button></div>
</form>
</div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const paketRuanganMap=<?= json_encode($paket_ruangan_map) ?>;
const paketDetailMap=<?= json_encode($paket_detail_map) ?>;
let selectedPaketId=<?= $selected_paket_id > 0 ? $selected_paket_id : 'null' ?>;
let selectedDurasi=<?= $selected_paket_durasi > 0 ? $selected_paket_durasi : 'null' ?>;
let selectedPaketNama='<?= addslashes($selected_paket_nama) ?>';
let selectedRuanganId=<?= $selected_ruangan_id > 0 ? $selected_ruangan_id : 'null' ?>;
let selectedRuanganNama='';

document.querySelectorAll('.btn-toggle-submenu').forEach(button=>{button.addEventListener('click',function(e){e.preventDefault();const targetId=this.getAttribute('data-target');const targetEl=document.querySelector(targetId);const chevron=this.querySelector('.icon-chevron');if(targetEl){const isShown=targetEl.classList.contains('show');document.querySelectorAll('.submenu').forEach(el=>el.classList.remove('show'));document.querySelectorAll('.icon-chevron').forEach(icon=>icon.style.transform='rotate(0deg)');if(!isShown){targetEl.classList.add('show');if(chevron)chevron.style.transform='rotate(180deg)'}}})});

function selectPaket(card,id,durasi,nama,harga){
    document.querySelectorAll('.paket-card').forEach(c=>c.classList.remove('selected'));
    document.querySelectorAll('.paket-card input').forEach(i=>i.checked=false);
    card.classList.add('selected');
    card.querySelector('input').checked=true;
    selectedPaketId=id;
    selectedDurasi=durasi;
    selectedPaketNama=nama;
    const durasiInfo=document.getElementById('durasiInfo');
    const durasiDetail=document.getElementById('durasiDetail');
    const durasiHint=document.getElementById('durasiHint');
    durasiInfo.classList.add('active');
    durasiDetail.innerHTML='Paket <strong>'+nama+'</strong> membutuhkan durasi <strong>'+durasi+' menit</strong> per sesi.';
    const maxStart=new Date();
    maxStart.setHours(20,0,0,0);
    maxStart.setMinutes(maxStart.getMinutes()-durasi);
    const maxStartStr=maxStart.toTimeString().slice(0,5);
    durasiHint.innerHTML='<i class="bi bi-clock-history"></i> Jam mulai maksimal: <strong>'+maxStartStr+'</strong> (agar selesai sebelum 20:00)';
    
    // Reset pilihan ruangan secara visual saat paket diganti
    document.querySelectorAll('.ruangan-card').forEach(c => {
        c.classList.remove('selected');
        const radio = c.querySelector('input[type="radio"]');
        if (radio) radio.checked = false;
    });

    filterRuanganByPaket(id);
    selectedRuanganId=null;
    selectedRuanganNama='';
    updateJamPreview()
}

function filterRuanganByPaket(paketId){const ruanganGrid=document.getElementById('ruanganGrid');const filterNotice=document.getElementById('ruanganFilterNotice');const filterText=document.getElementById('ruanganFilterText');if(!ruanganGrid)return;const validRuanganIds=paketRuanganMap[paketId]||[];const ruanganCards=ruanganGrid.querySelectorAll('.ruangan-card');let validCount=0;let totalCount=ruanganCards.length;ruanganCards.forEach(card=>{const ruanganId=parseInt(card.getAttribute('data-ruangan-id'));const radio=card.querySelector('input[type="radio"]');if(validRuanganIds.includes(ruanganId)){card.classList.remove('disabled');card.style.opacity='1';card.style.pointerEvents='auto';card.style.filter='none';validCount++}else{card.classList.add('disabled');card.style.opacity='0.35';card.style.pointerEvents='none';card.style.filter='grayscale(1)';if(radio&&radio.checked){radio.checked=false;card.classList.remove('selected')}}});if(filterNotice&&filterText){filterNotice.classList.add('show');if(validCount>0){filterText.innerHTML='Menampilkan <strong>'+validCount+' dari '+totalCount+'</strong> ruangan yang tersedia untuk paket <strong>'+selectedPaketNama+'</strong>.'}else{filterText.innerHTML='<i class="bi bi-exclamation-triangle-fill"></i> Tidak ada ruangan yang tersedia untuk paket <strong>'+selectedPaketNama+'</strong>.'}}}

function selectRuangan(card,id,nama){if(card.classList.contains('disabled'))return;document.querySelectorAll('.ruangan-card').forEach(c=>c.classList.remove('selected'));document.querySelectorAll('.ruangan-card input').forEach(i=>i.checked=false);card.classList.add('selected');card.querySelector('input').checked=true;selectedRuanganId=id;selectedRuanganNama=nama}

function updateJamPreview(){const jamMulai=document.querySelector('input[name="jam_mulai"]').value;const jamPreview=document.getElementById('jamPreview');if(!jamMulai||!selectedDurasi){jamPreview.classList.remove('active');return}const[hours,minutes]=jamMulai.split(':').map(Number);const mulaiDate=new Date();mulaiDate.setHours(hours,minutes,0,0);const selesaiDate=new Date(mulaiDate.getTime()+selectedDurasi*60000);const mulaiStr=mulaiDate.toTimeString().slice(0,5);const selesaiStr=selesaiDate.toTimeString().slice(0,5);document.getElementById('previewMulai').textContent=mulaiStr;document.getElementById('previewSelesai').textContent=selesaiStr;document.getElementById('previewDurasi').textContent=selectedDurasi+' Menit';jamPreview.classList.add('active')}

document.getElementById('formJadwal').addEventListener('submit',function(e){if(!selectedPaketId){e.preventDefault();Swal.fire({icon:'warning',title:'Paket Belum Dipilih',text:'Silakan pilih Paket Foto terlebih dahulu.',confirmButtonColor:'#D53D66'});return false}if(!selectedRuanganId){e.preventDefault();Swal.fire({icon:'warning',title:'Ruangan Belum Dipilih',text:'Silakan pilih Ruangan terlebih dahulu.',confirmButtonColor:'#D53D66'});return false}});

window.addEventListener('load',function(){const filterNotice=document.getElementById('ruanganFilterNotice');if(selectedPaketId&&selectedDurasi){const durasiInfo=document.getElementById('durasiInfo');const durasiDetail=document.getElementById('durasiDetail');const durasiHint=document.getElementById('durasiHint');durasiInfo.classList.add('active');durasiDetail.innerHTML='Paket <strong>'+selectedPaketNama+'</strong> membutuhkan durasi <strong>'+selectedDurasi+' menit</strong> per sesi.';const maxStart=new Date();maxStart.setHours(20,0,0,0);maxStart.setMinutes(maxStart.getMinutes()-selectedDurasi);const maxStartStr=maxStart.toTimeString().slice(0,5);durasiHint.innerHTML='<i class="bi bi-clock-history"></i> Jam mulai maksimal: <strong>'+maxStartStr+'</strong> (agar selesai sebelum 20:00)';filterRuanganByPaket(selectedPaketId)}else{document.querySelectorAll('.ruangan-card').forEach(card=>{card.classList.add('disabled')});if(filterNotice)filterNotice.classList.add('show')}updateJamPreview()});

function updateLiveClock(){var clockEl=document.getElementById('live-clock');if(!clockEl)return;var now=new Date();var days=["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];var months=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];var dayName=days[now.getDay()];var day=now.getDate();var monthName=months[now.getMonth()];var year=now.getFullYear();var hours=now.getHours();var minutes=now.getMinutes();var seconds=now.getSeconds();hours=hours<10?'0'+hours:hours;minutes=minutes<10?'0'+minutes:minutes;seconds=seconds<10?'0'+seconds:seconds;clockEl.innerText=dayName+', '+day+' '+monthName+' '+year+' - '+hours+':'+minutes+':'+seconds+' WIB'}updateLiveClock();setInterval(updateLiveClock,1000);

function confirmLogout(e){e.preventDefault();Swal.fire({title:'Keluar Sistem?',text:'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Keluar',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../logout.php'}})}
function confirmLandingPage(e){e.preventDefault();Swal.fire({title:'Kembali ke Beranda?',text:'Anda akan dialihkan ke halaman utama publik.',icon:'info',showCancelButton:true,confirmButtonColor:'#D53D66',cancelButtonColor:'#718096',confirmButtonText:'Ya, Kembali',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){window.location.href='../../index.php'}})}
function bukaModalBiodata(){Swal.fire({title:'<?= htmlspecialchars($nama_admin) ?>',text:'Administrator - SpotLight Studio',icon:'info',confirmButtonColor:'#D53D66'})}
</script>
<?php if($success): ?><script>Swal.fire({icon:'success',title:'Berhasil!',text:'Data jadwal studio berhasil diperbarui.',confirmButtonColor:'#D53D66',confirmButtonText:'Oke'}).then(()=>{window.location.href='list.php?status_sukses=edit'})</script><?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>