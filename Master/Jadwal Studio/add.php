<?php
ob_start();
session_start();
include '../../koneksi.php';

// Pastikan zona waktu server selaras dengan Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$id_admin   = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_admin = $_SESSION['nama'] ?? 'Administrator';

// =====================================================
// HELPER FUNCTIONS
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

// =====================================================
// AMBIL PROFIL ADMIN
// =====================================================
$admin_data = safe_sqlsrv_fetch($conn,
    "SELECT Nama_Karyawan, Foto_Profil FROM Karyawan WHERE ID_Karyawan = ? AND Status = 1 AND Is_Deleted = 0",
    [$id_admin]
);
$nama_admin    = $admin_data['Nama_Karyawan'] ?? 'Administrator';
$foto_admin    = $admin_data['Foto_Profil']   ?? 'default.jpg';

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D53D66'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
$foto_admin_src = ($foto_admin != 'default.jpg' && file_exists("../../assets/img/karyawan/" . $foto_admin))
    ? "../../assets/img/karyawan/" . $foto_admin
    : $default_svg_avatar;

// =====================================================
// AMBIL DATA PAKET UNTUK DROPDOWN
// =====================================================
$q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket, Durasi_Waktu FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");

// =====================================================
// PROSES SUBMIT
// =====================================================
$errors = [];
$old_values = $_POST ?? [];
$success = false;

if (isset($_POST['simpan'])) {
    $id_paket       = isset($_POST['id_paket']) ? (int)$_POST['id_paket'] : 0;
    $id_ruangan     = isset($_POST['id_ruangan']) ? (int)$_POST['id_ruangan'] : 0;
    $tanggal_jadwal = isset($_POST['tanggal_jadwal']) ? trim($_POST['tanggal_jadwal']) : '';
    $jam_mulai      = isset($_POST['jam_mulai']) ? trim($_POST['jam_mulai']) : '';
    $keterangan     = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

    // --- VALIDASI SERVER-SIDE diperkuat ---
    if ($id_paket <= 0) {
        $errors['id_paket'] = "Pilih paket foto!";
    } else {
        $cek_paket = sqlsrv_fetch_array(sqlsrv_query($conn, 
            "SELECT ID_Paket, Durasi_Waktu FROM Paket_Foto WHERE ID_Paket = ? AND Status = 1 AND Is_Deleted = 0", [$id_paket]), SQLSRV_FETCH_ASSOC);
        if (!$cek_paket) {
            $errors['id_paket'] = "Paket foto tidak valid atau tidak aktif!";
        }
    }

    if ($id_ruangan <= 0) {
        $errors['id_ruangan'] = "Pilih ruangan!";
    } else {
        $cek_ruangan = sqlsrv_fetch_array(sqlsrv_query($conn, 
            "SELECT ID_Ruangan FROM Ruangan WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", [$id_ruangan]), SQLSRV_FETCH_ASSOC);
        if (!$cek_ruangan) {
            $errors['id_ruangan'] = "Ruangan tidak valid atau tidak aktif!";
        }
    }

    if (empty($errors['id_paket']) && empty($errors['id_ruangan'])) {
        $cek_junction = sqlsrv_fetch_array(sqlsrv_query($conn,
            "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?",
            [$id_paket, $id_ruangan]), SQLSRV_FETCH_ASSOC);
        if (!$cek_junction) {
            $errors['id_ruangan'] = "Paket ini tidak tersedia untuk ruangan yang dipilih!";
        }
    }

    if (empty($tanggal_jadwal)) {
        $errors['tanggal_jadwal'] = "Tanggal jadwal wajib diisi!";
    } else {
        $date_parsed = date_parse($tanggal_jadwal);
        if (!$date_parsed || !checkdate($date_parsed['month'], $date_parsed['day'], $date_parsed['year'])) {
            $errors['tanggal_jadwal'] = "Format tanggal tidak valid!";
        } elseif (strtotime($tanggal_jadwal) < strtotime(date('Y-m-d'))) {
            $errors['tanggal_jadwal'] = "Tanggal tidak boleh di masa lalu!";
        }
    }

    if (empty($jam_mulai)) {
        $errors['jam_mulai'] = "Jam mulai wajib diisi!";
    } else {
        try {
            $jam_mulai_obj = new DateTime($jam_mulai);
            $jam_mulai_24h = $jam_mulai_obj->format('H:i');

            // Validasi jam operasional 08:00 - 20:00
            $jam_int = (int)$jam_mulai_obj->format('Hi');
            if ($jam_int < 800 || $jam_int >= 2000) {
                $errors['jam_mulai'] = "Jam mulai harus antara 08:00 - 20:00 WIB!";
            }

            // Validasi agar jam mulai hari ini tidak boleh di masa lalu
            if (empty($errors['tanggal_jadwal']) && $tanggal_jadwal === date('Y-m-d')) {
                $now = new DateTime('now');
                $selected_datetime = new DateTime($tanggal_jadwal . ' ' . $jam_mulai_24h);
                if ($selected_datetime < $now) {
                    $errors['jam_mulai'] = "Waktu mulai tidak boleh di masa lalu untuk hari ini!";
                }
            }
        } catch (Exception $e) {
            $errors['jam_mulai'] = "Format waktu tidak valid!";
        }
    }

    // --- HITUNG JAM SELESAI & SINKRONISASI FORMAT 24-JAM INDONESIA (WIB) ---
    $jam_selesai_24h = '';
    if (empty($errors['id_paket']) && empty($errors['jam_mulai'])) {
        $durasi = $cek_paket['Durasi_Waktu'] ?? 30;

        $jam_selesai_obj = clone $jam_mulai_obj;
        $jam_selesai_obj->modify("+{$durasi} minutes");
        $jam_selesai_24h = $jam_selesai_obj->format('H:i');

        // Validasi jam selesai tidak melebihi 20:00
        $jam_selesai_int = (int)$jam_selesai_obj->format('Hi');
        if ($jam_selesai_int > 2000) {
            $errors['jam_mulai'] = "Slot melebihi jam 20:00 WIB! Pilih jam mulai yang lebih awal.";
        }
    }

    // --- SINKRONISASI VALIDASI BENTROK JADWAL DI SISI PHP (PRE-CHECK) ---
    if (empty($errors)) {
        $sql_conflict = "SELECT COUNT(*) AS total FROM Jadwal_Studio 
                         WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Is_Deleted = 0 
                           AND (
                               (CAST(? AS TIME) >= Jam_Mulai AND CAST(? AS TIME) < Jam_Selesai) OR
                               (CAST(? AS TIME) > Jam_Mulai AND CAST(? AS TIME) <= Jam_Selesai) OR
                               (Jam_Mulai >= CAST(? AS TIME) AND Jam_Mulai < CAST(? AS TIME))
                           )";
        $params_conflict = [
            $id_ruangan, 
            $tanggal_jadwal, 
            $jam_mulai_24h, 
            $jam_mulai_24h, 
            $jam_selesai_24h, 
            $jam_selesai_24h, 
            $jam_mulai_24h, 
            $jam_selesai_24h
        ];
        $stmt_conflict = sqlsrv_query($conn, $sql_conflict, $params_conflict);
        if ($stmt_conflict !== false) {
            $row_conflict = sqlsrv_fetch_array($stmt_conflict, SQLSRV_FETCH_ASSOC);
            if (($row_conflict['total'] ?? 0) > 0) {
                $errors['jam_mulai'] = "Slot waktu tersebut bentrok dengan jadwal studio yang sudah terdaftar!";
            }
            sqlsrv_free_stmt($stmt_conflict);
        }
    }

    // --- PROSES SIMPAN TUNGGAL ---
    if (empty($errors)) {
        $sql = "EXEC sp_InsertJadwalStudio ?, ?, ?, ?, ?, ?";
        $params = [
            $id_ruangan,
            $tanggal_jadwal,
            $jam_mulai_24h,
            $jam_selesai_24h,
            !empty($keterangan) ? $keterangan : null,
            $nama_admin
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            sqlsrv_next_result($stmt);
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $id_jadwal_baru = $row['ID_Jadwal'] ?? null;
            sqlsrv_free_stmt($stmt);

            if ($id_jadwal_baru) {
                sqlsrv_query($conn, "UPDATE Jadwal_Studio SET Status_Jadwal = 0 WHERE ID_Jadwal = ?", [$id_jadwal_baru]);
            }

            // Langsung pindah ke list.php agar pop-up cuma muncul sekali di sana
            header("Location: list.php?status_sukses=tambah");
            exit();
        } else {
            $sql_errors = sqlsrv_errors();
            $error_msg = "Gagal menyimpan jadwal.";
            if (!empty($sql_errors)) {
                $error_msg = $sql_errors[0]['message'];
                if (strpos($error_msg, '[SQL Server]') !== false) {
                    $error_msg = substr($error_msg, strpos($error_msg, '[SQL Server]') + 12);
                }
            }
            $errors['general'] = $error_msg;
        }
    }
}

// --- PRE-LOAD RUANGAN SAAT VALIDASI GAGAL (UX PREMIUM) ---
$old_ruangan_list = [];
if (isset($old_values['id_paket']) && (int)$old_values['id_paket'] > 0) {
    $old_paket_id = (int)$old_values['id_paket'];
    $q_old_ruangan = sqlsrv_query($conn, 
        "SELECT r.ID_Ruangan, r.Nama_Ruangan 
         FROM Ruangan r
         JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
         WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0 
         ORDER BY r.Nama_Ruangan", 
        [$old_paket_id]
    );
    if ($q_old_ruangan) {
        while ($r = sqlsrv_fetch_array($q_old_ruangan, SQLSRV_FETCH_ASSOC)) {
            $old_ruangan_list[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Jadwal Studio – SpotLight Studio</title>
<link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --accent-pink: #E85D84; --text-dark: #1e1e24; --text-muted: #718096; --sidebar-bg: #ffffff; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }
.sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; border-right: 1px solid rgba(255, 228, 233, 0.8); display: flex; flex-direction: column; justify-content: space-between; padding: 30px 20px; z-index: 100; }
.sidebar-brand { font-weight: 800; font-size: 1.5rem; color: var(--p-pink); letter-spacing: -1px; margin-bottom: 40px; display: block; text-decoration: none; }
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

/* BREADCRUMB */
.breadcrumb-custom { display: flex; align-items: center; gap: 8px; margin-bottom: 25px; font-size: 0.85rem; font-weight: 600; }
.breadcrumb-custom a { color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.breadcrumb-custom a:hover { color: var(--p-pink); }
.breadcrumb-custom .active { color: var(--p-pink); }
.breadcrumb-custom i { color: #cbd5e1; font-size: 0.7rem; }

/* FORM CARD */
.form-card { background: #ffffff; border-radius: 22px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); overflow: hidden; }
.form-card-header { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); padding: 30px 40px; color: #ffffff; }
.form-card-header h4 { font-weight: 800; font-size: 1.4rem; margin-bottom: 4px; }
.form-card-header p { opacity: 0.85; font-size: 0.85rem; margin: 0; }
.form-card-body { padding: 40px; }

.form-label { font-weight: 700; font-size: 0.75rem; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; display: block; }
.form-label .required { color: #dc2626; margin-left: 2px; }
.form-control-custom, .form-select-custom { width: 100%; border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-weight: 600; font-size: 0.9rem; color: #1e293b; transition: var(--transition-3d); background: #ffffff; }
.form-control-custom:focus, .form-select-custom:focus { outline: none; border-color: var(--p-pink); box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
.form-control-custom::placeholder { color: #a0aec0; font-weight: 500; }
.form-control-custom.is-invalid, .form-select-custom.is-invalid { border-color: #ef4444; background: #fef2f2; }
.form-control-custom.is-invalid:focus, .form-select-custom.is-invalid:focus { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08); }
select.form-select-custom { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; padding-right: 44px; }
.input-hint { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 4px; }
.input-hint i { color: var(--p-pink); }
.error-text { color: #ef4444; font-size: 0.8rem; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
.alert-custom { background: #fef2f2; border: none; border-left: 4px solid #dc2626; border-radius: 12px; color: #991b1b; font-size: 0.85rem; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.alert-custom i { font-size: 1.1rem; }

/* INFO CARD */
.info-card { background: linear-gradient(135deg, #FFF0F3, #FFF8F0); border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; border: 1px solid rgba(255, 228, 233, 0.8); display: flex; align-items: center; gap: 12px; }
.info-card i { font-size: 1.5rem; color: var(--p-pink); flex-shrink: 0; }
.info-card .info-text { font-size: 0.85rem; color: #4a5568; font-weight: 600; line-height: 1.5; }
.info-card .info-text strong { color: var(--p-pink); }

/* DURASI PREVIEW */
.durasi-preview { background: linear-gradient(135deg, #FFF0F3, #FFF8F0); border-radius: 14px; padding: 16px 20px; border: 2px solid var(--light-pink); display: flex; align-items: center; gap: 12px; margin-top: 10px; display: none; }
.durasi-preview.show { display: flex; }
.durasi-preview i { font-size: 1.5rem; color: var(--p-pink); flex-shrink: 0; }
.durasi-preview .durasi-text { font-size: 0.9rem; color: #4a5568; font-weight: 600; }
.durasi-preview .durasi-text strong { color: var(--p-pink); }

/* BUTTONS */
.btn-submit { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(213, 61, 102, 0.35); color: #ffffff; }
.btn-batal { background: #f1f5f9; color: #475569; border: none; border-radius: 14px; padding: 14px 32px; font-weight: 800; font-size: 0.95rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-batal:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-3px); }
.btn-group-bottom { display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; padding-top: 25px; border-top: 2px solid #f1f5f9; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in-up { animation: fadeIn 0.5s ease-out; }

/* Mobile Menu Button */
.mobile-menu-btn { display: none; width: 44px; height: 44px; border-radius: 12px; background: #ffffff; border: 2px solid var(--light-pink); color: var(--p-pink); align-items: center; justify-content: center; font-size: 1.4rem; cursor: pointer; transition: var(--transition-3d); flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.mobile-menu-btn:hover { background: var(--s-pink); transform: scale(1.05); }

/* Sidebar Overlay */
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(30, 30, 36, 0.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99; opacity: 0; transition: opacity 0.35s ease; }
.sidebar-overlay.show { display: block; opacity: 1; }

/* RESPONSIVE */
@media (max-width: 1199px) { .form-card-body { padding: 35px; } }

@media (max-width: 992px) {
    .mobile-menu-btn { display: inline-flex; }
    .sidebar { transform: translateX(-100%); transition: transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: none; }
    .sidebar.mobile-open { transform: translateX(0); box-shadow: 10px 0 50px rgba(0,0,0,0.15); }
    .main-content { margin-left: 0; padding: 24px; }
    .dashboard-header { flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
    .dashboard-header h3 { font-size: 1.35rem; }
    .form-card { border-radius: 20px; }
    .form-card-header { padding: 25px 30px; }
    .form-card-header h4 { font-size: 1.2rem; }
    .form-card-body { padding: 30px; }
    .breadcrumb-custom { font-size: 0.8rem; margin-bottom: 20px; }
    .btn-group-bottom { flex-direction: column; }
    .btn-submit, .btn-batal { width: 100%; justify-content: center; }
}

@media (max-width: 768px) {
    .main-content { padding: 18px; }
    .dashboard-header { margin-bottom: 22px; }
    .dashboard-header h3 { font-size: 1.15rem; }
    .dashboard-header p { font-size: 0.8rem; }
    .form-card { border-radius: 18px; }
    .form-card-header { padding: 22px 24px; }
    .form-card-header h4 { font-size: 1.1rem; }
    .form-card-body { padding: 24px 18px; }
    .form-control-custom, .form-select-custom { padding: 12px 14px; font-size: 0.85rem; border-radius: 12px; }
    .form-label { font-size: 0.7rem; }
    .input-hint { font-size: 0.7rem; }
    .info-card { padding: 14px 16px; font-size: 0.8rem; gap: 10px; }
    .info-card i { font-size: 1.3rem; }
    .durasi-preview { padding: 12px 14px; border-radius: 12px; }
    .durasi-preview i { font-size: 1.3rem; }
    .durasi-preview .durasi-text { font-size: 0.8rem; }
    .btn-submit, .btn-batal { padding: 12px 20px; font-size: 0.9rem; border-radius: 12px; }
    .btn-group-bottom { flex-direction: column; gap: 10px; margin-top: 24px; padding-top: 20px; }
    .error-text { font-size: 0.75rem; }
    .alert-custom { padding: 12px 14px; font-size: 0.8rem; border-radius: 12px; }
    .breadcrumb-custom { flex-wrap: wrap; gap: 6px; font-size: 0.75rem; }
    .profile-header-btn { width: 40px; height: 40px; }
}

@media (max-width: 576px) {
    .main-content { padding: 14px; }
    .dashboard-header h3 { font-size: 1.05rem; }
    .form-card { border-radius: 16px; }
    .form-card-header { padding: 18px 20px; }
    .form-card-header h4 { font-size: 1rem; }
    .form-card-body { padding: 20px 14px; }
    .form-control-custom, .form-select-custom { padding: 10px 12px; font-size: 0.85rem; border-radius: 10px; }
    .info-card { padding: 12px 14px; font-size: 0.75rem; flex-direction: column; text-align: center; gap: 8px; }
    .info-card i { font-size: 1.2rem; }
    .durasi-preview { padding: 10px 12px; flex-direction: column; text-align: center; gap: 8px; }
    .durasi-preview i { font-size: 1.2rem; }
    .btn-submit, .btn-batal { padding: 12px 16px; font-size: 0.85rem; border-radius: 10px; }
    .btn-group-bottom { gap: 8px; }
}

@media (max-width: 375px) {
    .dashboard-header h3 { font-size: 0.95rem; }
    .form-card-body { padding: 18px 12px; }
    .btn-submit, .btn-batal { padding: 10px 14px; font-size: 0.8rem; }
}
</style>
</head>
<body>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">SpotLight.<br><span>Panel Administrator</span></a>
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
                        <li><a href="../../Transaksi/Pembayaran/list.php" class="submenu-link"><i class="bi bi-credit-card-fill me-2"></i>Verifikasi Pembayaran DP</a></li>
                        <li><a href="../../Transaksi/Order/list.php" class="submenu-link"><i class="bi bi-bag-check-fill me-2"></i>Booking Customer</a></li>
                        <li><a href="../../Transaksi/Pelunasan/list.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Verifikasi Pelunasan</a></li>
                        <li><a href="../../Transaksi/Penjualan/list.php" class="submenu-link"><i class="bi bi-bag-fill me-2"></i>Penjualan Barang Cetak</a></li>
                    </ul>
                </div>
            </li>
            <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i> Beranda</span></a></li>
        </ul>
    </div>
    <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dashboard-header fade-in-up">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Menu" aria-label="Toggle Menu">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <h3 class="fw-bold mb-1">Tambah Jadwal Studio</h3>
                <p class="text-muted small mb-0">Buat slot jadwal pemotretan baru.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
            <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
        </div>
    </div>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-custom fade-in-up">
        <a href="../../Role/Admin/index.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <a href="list.php">Data Master</a>
        <i class="bi bi-chevron-right"></i>
        <a href="list.php">Jadwal Studio</a>
        <i class="bi bi-chevron-right"></i>
        <span class="active">Tambah Jadwal</span>
    </div>

    <!-- FORM CARD -->
    <div class="form-card fade-in-up">
        <div class="form-card-header">
            <h4><i class="bi bi-calendar-event-fill me-2"></i>Form Jadwal Baru</h4>
            <p>Lengkapi informasi jadwal, tentukan paket foto, dan ruangan terkait.</p>
        </div>
        <div class="form-card-body">

            <!-- INFO CARD -->
            <div class="info-card">
                <i class="bi bi-info-circle-fill"></i>
                <div class="info-text">
                    <strong>Perhatian:</strong> Pilih <strong>Paket Foto</strong> dulu, lalu <strong>Ruangan</strong> yang valid akan muncul. Sistem otomatis menghitung <strong>Jam Selesai</strong> dari durasi paket. Jam operasional: <strong>08:00 - 20:00 WIB</strong>.
                </div>
            </div>

            <!-- ALERT ERROR -->
            <?php if(isset($errors['general'])): ?>
                <div class="alert-custom">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="formJadwal">
                <div class="row">
                    <!-- Paket Foto -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Paket Foto <span class="required">*</span></label>
                        <select name="id_paket" id="idPaket" class="form-select-custom <?= isset($errors['id_paket']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Paket Foto --</option>
                            <?php 
                            sqlsrv_free_stmt($q_paket);
                            $q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket, Durasi_Waktu FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");
                            while($p = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)): 
                            ?>
                                <option value="<?= $p['ID_Paket'] ?>" data-durasi="<?= $p['Durasi_Waktu'] ?>" <?= (isset($old_values['id_paket']) && $old_values['id_paket'] == $p['ID_Paket']) ? 'selected' : '' ?>><?= htmlspecialchars($p['Nama_Paket']) ?> (<?= $p['Durasi_Waktu'] ?> menit)</option>
                            <?php endwhile; ?>
                        </select>
                        <?php if(isset($errors['id_paket'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['id_paket'] ?></span><?php endif; ?>
                        <div class="input-hint"><i class="bi bi-info-circle"></i>Pilih paket foto terlebih dahulu.</div>
                    </div>

                    <!-- Ruangan -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Ruangan <span class="required">*</span></label>
                        <select name="id_ruangan" id="idRuangan" class="form-select-custom <?= isset($errors['id_ruangan']) ? 'is-invalid' : '' ?>" required>
                            <?php if (!empty($old_ruangan_list)): ?>
                                <option value="">-- Pilih Ruangan --</option>
                                <?php foreach ($old_ruangan_list as $r_old): ?>
                                    <option value="<?= $r_old['ID_Ruangan'] ?>" <?= (isset($old_values['id_ruangan']) && $old_values['id_ruangan'] == $r_old['ID_Ruangan']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r_old['Nama_Ruangan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">-- Pilih Paket Dulu --</option>
                            <?php endif; ?>
                        </select>
                        <?php if(isset($errors['id_ruangan'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['id_ruangan'] ?></span><?php endif; ?>
                        <div class="input-hint"><i class="bi bi-info-circle"></i>Ruangan akan muncul sesuai paket yang dipilih.</div>
                    </div>
                </div>

                <!-- Durasi Preview -->
                <div class="durasi-preview mb-4" id="durasiPreview">
                    <i class="bi bi-clock-history"></i>
                    <div class="durasi-text">
                        Durasi Paket: <span id="durasiText">-</span> menit<br>
                        <span style="font-size: 0.8rem; color: #718096;" id="liveJamSelesai">Jam selesai dihitung otomatis</span>
                    </div>
                </div>

                <div class="row">
                    <!-- Tanggal -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Tanggal Jadwal <span class="required">*</span></label>
                        <input type="date" name="tanggal_jadwal" id="tanggalJadwal" class="form-control-custom <?= isset($errors['tanggal_jadwal']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_values['tanggal_jadwal'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required>
                        <?php if(isset($errors['tanggal_jadwal'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['tanggal_jadwal'] ?></span><?php endif; ?>
                        <div class="input-hint"><i class="bi bi-info-circle"></i>Tidak boleh di masa lalu.</div>
                    </div>

                    <!-- Jam Mulai -->
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Jam Mulai <span class="required">*</span></label>
                        <input type="time" name="jam_mulai" id="jamMulai" class="form-control-custom <?= isset($errors['jam_mulai']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_values['jam_mulai'] ?? '08:00') ?>" step="60" required>
                        <?php if(isset($errors['jam_mulai'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['jam_mulai'] ?></span><?php endif; ?>
                        <div class="input-hint"><i class="bi bi-info-circle"></i>Jam operasional: 08:00 - 20:00 WIB.</div>
                    </div>
                </div>

                <!-- Keterangan -->
                <div class="mb-4">
                    <label class="form-label">Keterangan <span style="color: #94a3b8; font-weight: 500;">(opsional)</span></label>
                    <input type="text" name="keterangan" class="form-control-custom" value="<?= htmlspecialchars($old_values['keterangan'] ?? '') ?>" placeholder="Contoh: Slot Basic Studio A, Libur Hari Raya, Maintenance..." maxlength="255">
                    <div class="input-hint"><i class="bi bi-info-circle"></i>Isi 'Libur' untuk menandai hari libur full.</div>
                </div>

                <!-- Buttons -->
                <div class="btn-group-bottom">
                    <a href="list.php" class="btn-batal"><i class="bi bi-arrow-left"></i>Kembali</a>
                    <button type="submit" name="simpan" class="btn-submit"><i class="bi bi-check-circle-fill"></i>Simpan Jadwal</button>
                </div>
            </form>

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
            if (!isShown) { targetEl.classList.add('show'); if (chevron) chevron.style.transform = 'rotate(180deg)'; }
        }
    });
});

// Mobile Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const isOpen = sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}
document.querySelectorAll('.sidebar .nav-link-custom, .sidebar .submenu-link, .sidebar .btn-logout').forEach(el => {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 992) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('mobile-open')) toggleSidebar();
        }
    });
});
window.addEventListener('resize', function() {
    if (window.innerWidth > 992) {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Fungsi Interaktif Kalkulasi Estimasi Jam Selesai Pemotretan
function calculateEndTime() {
    const paketSelect = document.getElementById('idPaket');
    const jamMulaiInput = document.getElementById('jamMulai');
    const liveJamSelesai = document.getElementById('liveJamSelesai');

    if (!paketSelect || !jamMulaiInput || !liveJamSelesai) return;

    if (!paketSelect.value || !jamMulaiInput.value) {
        liveJamSelesai.innerHTML = "Jam selesai dihitung otomatis";
        liveJamSelesai.style.color = "#718096";
        return;
    }

    const selectedOption = paketSelect.options[paketSelect.selectedIndex];
    const durasi = parseInt(selectedOption.getAttribute('data-durasi') ?? 30);
    const jamMulaiVal = jamMulaiInput.value; // Format: "HH:MM"

    const timeParts = jamMulaiVal.split(':');
    if (timeParts.length < 2) return;

    let hours = parseInt(timeParts[0]);
    let minutes = parseInt(timeParts[1]);

    // Lakukan penjumlahan matematika menit dan jam operasional
    minutes += durasi;
    hours += Math.floor(minutes / 60);
    minutes = minutes % 60;
    hours = hours % 24;

    const jamSelesaiVal = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');

    // Tampilkan notifikasi visual secara interaktif dan presisi
    liveJamSelesai.innerHTML = `<span class="badge bg-success mt-1 fw-bold px-2 py-1"><i class="bi bi-clock-fill me-1"></i>Estimasi Selesai: ${jamSelesaiVal} WIB</span>`;
}

// Cascade Paket -> Ruangan
document.getElementById('idPaket').addEventListener('change', function() {
    const paketId = this.value;
    const ruanganSelect = document.getElementById('idRuangan');
    const durasiPreview = document.getElementById('durasiPreview');
    const durasiText = document.getElementById('durasiText');

    if (!paketId) {
        ruanganSelect.innerHTML = '<option value="">-- Pilih Paket Dulu --</option>';
        durasiPreview.classList.remove('show');
        return;
    }

    const selectedOption = this.options[this.selectedIndex];
    const durasi = selectedOption.getAttribute('data-durasi');
    durasiText.textContent = durasi;
    durasiPreview.classList.add('show');

    // Trigger update estimasi jam selesai
    calculateEndTime();

    fetch('get_ruangan_by_paket.php?id_paket=' + paketId)
        .then(response => response.json())
        .then(data => {
            ruanganSelect.innerHTML = '<option value="">-- Pilih Ruangan --</option>';
            data.forEach(r => {
                ruanganSelect.innerHTML += '<option value="' + r.ID_Ruangan + '">' + r.Nama_Ruangan + '</option>';
            });
        })
        .catch(err => {
            console.error('Error fetching ruangan:', err);
            ruanganSelect.innerHTML = '<option value="">Error loading ruangan</option>';
        });
});

// Listener interaktif untuk perubahan input Jam Mulai
document.getElementById('jamMulai').addEventListener('input', calculateEndTime);

// Form Validation
document.getElementById('formJadwal').addEventListener('submit', function(e) {
    const paket = document.getElementById('idPaket').value;
    const ruangan = document.getElementById('idRuangan').value;
    const tanggal = document.getElementById('tanggalJadwal').value;
    const jam = document.getElementById('jamMulai').value;

    if (!paket) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Paket Belum Dipilih', text: 'Silakan pilih paket foto.', confirmButtonColor: '#D53D66' }); return false; }
    if (!ruangan) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Ruangan Belum Dipilih', text: 'Silakan pilih ruangan.', confirmButtonColor: '#D53D66' }); return false; }
    if (!tanggal) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Tanggal Kosong', text: 'Silakan pilih tanggal jadwal.', confirmButtonColor: '#D53D66' }); return false; }
    if (!jam) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'Jam Mulai Kosong', text: 'Silakan pilih jam mulai.', confirmButtonColor: '#D53D66' }); return false; }
});

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

function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({ title: 'Keluar Sistem?', text: 'Apakah Anda yakin ingin keluar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Keluar', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../logout.php'; });
}
function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({ title: 'Kembali ke Beranda?', text: 'Anda akan dialihkan ke halaman utama.', icon: 'info', showCancelButton: true, confirmButtonColor: '#D53D66', cancelButtonColor: '#718096', confirmButtonText: 'Ya, Kembali', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) window.location.href = '../../index.php'; });
}
function bukaModalBiodata() {
    Swal.fire({ title: '<?= htmlspecialchars($nama_admin) ?>', text: 'Administrator - SpotLight Studio', icon: 'info', confirmButtonColor: '#D53D66' });
}

// Init durasi preview jika paket sudah terpilih (pasca-reload error)
window.addEventListener('DOMContentLoaded', function() {
    const paketSelect = document.getElementById('idPaket');
    if (paketSelect && paketSelect.value) {
        const selectedOption = paketSelect.options[paketSelect.selectedIndex];
        const durasi = selectedOption.getAttribute('data-durasi');
        if (durasi) {
            document.getElementById('durasiText').textContent = durasi;
            document.getElementById('durasiPreview').classList.add('show');
            calculateEndTime(); // Jalankan kalkulasi inisial saat reload gagal
        }
    }
});
</script>

</body>
</html>
<?php ob_end_flush(); ?>