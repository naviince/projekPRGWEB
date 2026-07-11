<?php
ob_start();
session_start();
include '../../koneksi.php';

// Atur zona waktu ke WIB (Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

define('STATUS_DATA_AKTIF', 1);
define('STATUS_DATA_NONAKTIF', 0);

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

// --- AMBIL DATA JADWAL (ID_Paket ditiadakan dari kueri langsung) ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: list.php");
    exit();
}

$sql_data = "SELECT j.*, r.Nama_Ruangan 
             FROM Jadwal_Studio j 
             INNER JOIN Ruangan r ON j.ID_Ruangan = r.ID_Ruangan 
             WHERE j.ID_Jadwal = ? AND j.Is_Deleted = 0";
$stmt_data = sqlsrv_query($conn, $sql_data, [$id]);
$data = sqlsrv_fetch_array($stmt_data, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: list.php?status_sukses=error&message=Jadwal+tidak+ditemukan");
    exit();
}

// Cek apakah jadwal sudah di-booked (Status_Jadwal = 1)
$is_booked = ($data['Status_Jadwal'] == 1);

// Hitung durasi dinamis dari selisih Jam_Mulai dan Jam_Selesai
$jam_mulai_str = is_object($data['Jam_Mulai']) ? $data['Jam_Mulai']->format('H:i') : $data['Jam_Mulai'];
$jam_selesai_str = is_object($data['Jam_Selesai']) ? $data['Jam_Selesai']->format('H:i') : $data['Jam_Selesai'];

$time1 = new DateTime($jam_mulai_str);
$time2 = new DateTime($jam_selesai_str);
$interval = $time1->diff($time2);
$durasi_menit = ($interval->h * 60) + $interval->i;

// Cari Paket Foto terhubung ke ruangan ini yang memiliki durasi yang cocok (UX PRE-FILLED)
$pkg_match = safe_sqlsrv_fetch($conn,
    "SELECT TOP 1 p.ID_Paket, p.Nama_Paket 
     FROM Paket_Ruangan pr
     JOIN Paket_Foto p ON pr.ID_Paket = p.ID_Paket
     WHERE pr.ID_Ruangan = ? AND p.Durasi_Waktu = ? AND p.Is_Deleted = 0",
    [$data['ID_Ruangan'], $durasi_menit]
);
$current_paket_id = $pkg_match['ID_Paket'] ?? 0;
$current_paket_nama = $pkg_match['Nama_Paket'] ?? '';

function safe_sqlsrv_fetch($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $result;
}

// Ambil data untuk dropdown
$q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket, Durasi_Waktu FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");

// Inisialisasi
$errors = [];
$old_values = $_POST ?? $data;
$success = false;

// =====================================================
// PROSES UPDATE
// =====================================================
if (isset($_POST['update'])) {
    $id_paket = isset($_POST['id_paket']) ? (int)$_POST['id_paket'] : $current_paket_id;
    $id_ruangan = isset($_POST['id_ruangan']) ? (int)$_POST['id_ruangan'] : $data['ID_Ruangan'];
    $tanggal_jadwal = isset($_POST['tanggal_jadwal']) ? trim($_POST['tanggal_jadwal']) : '';
    $jam_mulai = isset($_POST['jam_mulai']) ? trim($_POST['jam_mulai']) : '';
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : $data['Status'];

    // Kalau sudah booked, hanya keterangan dan status yang bisa diubah
    if ($is_booked) {
        // Update hanya keterangan dan status (Atomic UPDATE)
        $sql_update = "UPDATE Jadwal_Studio SET 
            Keterangan = ?, Status = ?, Modified_By = ?, Modified_Date = GETDATE()
            WHERE ID_Jadwal = ?";
        $params_update = [
            !empty($keterangan) ? $keterangan : null,
            $status, $nama_admin, $id
        ];
        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);
        if ($stmt_update) {
            $success = true;
            $data['Keterangan'] = $keterangan;
            $data['Status'] = $status;
        } else {
            $sql_errors = sqlsrv_errors();
            $error_msg = "Gagal memperbarui keterangan jadwal.";
            if (!empty($sql_errors)) {
                $error_msg = $sql_errors[0]['message'];
            }
            $errors['general'] = $error_msg;
        }
    } else {
        // Belum booked, bisa edit semua
        // --- VALIDASI PAKET ---
        if ($id_paket <= 0) {
            $errors['id_paket'] = "Pilih paket foto!";
        } else {
            $cek_paket = sqlsrv_fetch_array(sqlsrv_query($conn, 
                "SELECT ID_Paket, Durasi_Waktu FROM Paket_Foto WHERE ID_Paket = ? AND Status = 1 AND Is_Deleted = 0", [$id_paket]), SQLSRV_FETCH_ASSOC);
            if (!$cek_paket) {
                $errors['id_paket'] = "Paket foto tidak valid atau tidak aktif!";
            }
        }

        // --- VALIDASI RUANGAN ---
        if ($id_ruangan <= 0) {
            $errors['id_ruangan'] = "Pilih ruangan!";
        } else {
            $cek_ruangan = sqlsrv_fetch_array(sqlsrv_query($conn, 
                "SELECT ID_Ruangan FROM Ruangan WHERE ID_Ruangan = ? AND Status = 1 AND Is_Deleted = 0", [$id_ruangan]), SQLSRV_FETCH_ASSOC);
            if (!$cek_ruangan) {
                $errors['id_ruangan'] = "Ruangan tidak valid atau tidak aktif!";
            }
        }

        // --- VALIDASI PAKET_RUANGAN ---
        if (empty($errors['id_paket']) && empty($errors['id_ruangan'])) {
            $cek_junction = sqlsrv_fetch_array(sqlsrv_query($conn,
                "SELECT ID_Paket FROM Paket_Ruangan WHERE ID_Paket = ? AND ID_Ruangan = ?",
                [$id_paket, $id_ruangan]), SQLSRV_FETCH_ASSOC);
            if (!$cek_junction) {
                $errors['id_ruangan'] = "Paket ini tidak tersedia untuk ruangan yang dipilih!";
            }
        }

        // --- VALIDASI TANGGAL ---
        if (empty($tanggal_jadwal)) {
            $errors['tanggal_jadwal'] = "Tanggal jadwal wajib diisi!";
        } elseif (strtotime($tanggal_jadwal) < strtotime(date('Y-m-d'))) {
            $errors['tanggal_jadwal'] = "Tanggal tidak boleh di masa lalu!";
        }

        // --- VALIDASI JAM MULAI ---
        if (empty($jam_mulai)) {
            $errors['jam_mulai'] = "Jam mulai wajib diisi!";
        } else {
            $jam_int = (int)str_replace(':', '', substr($jam_mulai, 0, 5));
            if ($jam_int < 800 || $jam_int >= 2000) {
                $errors['jam_mulai'] = "Jam mulai harus antara 08:00 - 20:00 WIB!";
            }
        }

        // --- HITUNG JAM SELESAI & SINKRONISASI FORMAT 24-JAM INDONESIA (WIB) ---
        $jam_mulai_24h = '';
        $jam_selesai_24h = '';
        if (empty($errors['id_paket']) && !empty($jam_mulai)) {
            $durasi = $cek_paket['Durasi_Waktu'] ?? 30;
            $jam_mulai_obj = new DateTime($jam_mulai);
            $jam_mulai_24h = $jam_mulai_obj->format('H:i'); // Paksa format 24 jam (Indonesian Style)

            $jam_selesai_obj = clone $jam_mulai_obj;
            $jam_selesai_obj->modify("+{$durasi} minutes");
            $jam_selesai_24h = $jam_selesai_obj->format('H:i'); // Paksa format 24 jam (Indonesian Style)

            $jam_selesai_int = (int)$jam_selesai_obj->format('Hi');
            if ($jam_selesai_int > 2000) {
                $errors['jam_mulai'] = "Slot melebihi jam 20:00 WIB! Pilih jam mulai yang lebih awal.";
            }
        }

        // --- VALIDASI BENTROK (kecuali jadwal sendiri) diperkuat secara matematis ---
        if (empty($errors['id_ruangan']) && empty($errors['tanggal_jadwal']) && empty($errors['jam_mulai'])) {
            $cek_bentrok = sqlsrv_query($conn, "
                SELECT ID_Jadwal, Jam_Mulai, Jam_Selesai 
                FROM Jadwal_Studio 
                WHERE ID_Ruangan = ? AND Tanggal_Jadwal = ? AND Is_Deleted = 0
                AND ID_Jadwal != ?
                AND (
                    (CAST(? AS TIME) >= Jam_Mulai AND CAST(? AS TIME) < Jam_Selesai) OR
                    (CAST(? AS TIME) > Jam_Mulai AND CAST(? AS TIME) <= Jam_Selesai) OR
                    (Jam_Mulai >= CAST(? AS TIME) AND Jam_Mulai < CAST(? AS TIME))
                )
            ", [$id_ruangan, $tanggal_jadwal, $id, $jam_mulai_24h, $jam_mulai_24h, $jam_selesai_24h, $jam_selesai_24h, $jam_mulai_24h, $jam_selesai_24h]);

            if ($cek_bentrok && sqlsrv_has_rows($cek_bentrok)) {
                $errors['jam_mulai'] = "Jadwal bentrok! Ruangan ini sudah ada slot di waktu tersebut.";
            }
        }

        // --- VALIDASI STATUS ---
        if (!in_array($status, [STATUS_DATA_AKTIF, STATUS_DATA_NONAKTIF])) {
            $errors['status'] = "Status tidak valid!";
        }

        // --- UPDATE DATABASE ---
        if (empty($errors)) {
            // Pembaruan data atomic menggunakan kueri Stored Procedure sp_UpdateJadwalStudio (9 Parameter)
            $sql_update = "{CALL sp_UpdateJadwalStudio(?, ?, ?, ?, ?, ?, ?, ?, ?)}";

            $params_update = [
                $id,
                $id_ruangan,
                $tanggal_jadwal,
                $jam_mulai_24h,
                $jam_selesai_24h,
                !empty($keterangan) ? $keterangan : null,
                (int)($data['Status_Jadwal'] ?? 0),
                $status,
                $nama_admin
            ];

            $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

            if ($stmt_update) {
                $success = true;
                $data['ID_Ruangan'] = $id_ruangan;
                $data['Tanggal_Jadwal'] = $tanggal_jadwal;
                $data['Jam_Mulai'] = $jam_mulai_24h;
                $data['Jam_Selesai'] = $jam_selesai_24h;
                $data['Keterangan'] = $keterangan;
                $data['Status'] = $status;
                $current_paket_id = $id_paket;
            } else {
                $sql_errors = sqlsrv_errors();
                $error_msg = "Gagal memperbarui jadwal. Silakan coba lagi!";
                if (!empty($sql_errors)) {
                    $error_msg = $sql_errors[0]['message'];
                }
                $errors['general'] = $error_msg;
            }
        }
    }
}

// --- PRE-LOAD RUANGAN UNTUK DROPDOWN SINKRON (UX PREMIUM) ---
$valid_ruangan_list = [];
$selected_paket_id = isset($_POST['id_paket']) ? (int)$_POST['id_paket'] : $current_paket_id;
if ($selected_paket_id > 0) {
    $q_valid_ruangan = sqlsrv_query($conn, 
        "SELECT r.ID_Ruangan, r.Nama_Ruangan 
         FROM Ruangan r
         JOIN Paket_Ruangan pr ON r.ID_Ruangan = pr.ID_Ruangan
         WHERE pr.ID_Paket = ? AND r.Status = 1 AND r.Is_Deleted = 0 
         ORDER BY r.Nama_Ruangan", 
        [$selected_paket_id]
    );
    if ($q_valid_ruangan) {
        while ($r = sqlsrv_fetch_array($q_valid_ruangan, SQLSRV_FETCH_ASSOC)) {
            $valid_ruangan_list[] = $r;
        }
    }
}

// Format tanggal untuk input date
$tgl_input = '';
if (isset($data['Tanggal_Jadwal'])) {
    $tgl_obj = is_object($data['Tanggal_Jadwal']) ? $data['Tanggal_Jadwal'] : new DateTime($data['Tanggal_Jadwal']);
    $tgl_input = $tgl_obj->format('Y-m-d');
}

// Format jam untuk input time
$jam_input = '';
if (isset($data['Jam_Mulai'])) {
    $jam_obj = is_object($data['Jam_Mulai']) ? $data['Jam_Mulai'] : new DateTime($data['Jam_Mulai']);
    $jam_input = $jam_obj->format('H:i');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Jadwal Studio – SpotLight Studio</title>

<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root { --p-pink: #D53D66; --d-pink: #CA3366; --s-pink: #FFF0F3; --light-pink: #FFE4E9; --text-dark: #1e1e24; --text-muted: #718096; --sidebar-bg: #ffffff; --body-bg: #f8fafc; --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
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
.form-card { background: #ffffff; border-radius: 24px; border: 1px solid rgba(255, 228, 233, 0.8); box-shadow: 0 8px 24px rgba(213, 61, 102, 0.03); padding: 40px; max-width: 900px; margin: 0 auto; }
.section-title { font-weight: 800; font-size: 1rem; color: var(--text-dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.section-title i { color: var(--p-pink); font-size: 1.2rem; }
.form-label-custom { font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: block; }
.form-input-custom { width: 100%; border-radius: 14px; padding: 14px 18px; border: 2px solid #e2e8f0; background: #f8fafc; font-weight: 600; font-size: 0.9rem; color: var(--text-dark); transition: var(--transition-3d); font-family: 'Plus Jakarta Sans', sans-serif; }
.form-input-custom:focus { outline: none; border-color: var(--p-pink); background: #ffffff; box-shadow: 0 0 0 4px rgba(213, 61, 102, 0.08); }
.form-input-custom.is-invalid { border-color: #ef4444; background: #fef2f2; }
.form-input-custom.is-invalid:focus { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08); }
.form-input-custom:disabled { background: #e2e8f0; cursor: not-allowed; opacity: 0.7; }
select.form-input-custom { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; padding-right: 44px; }
.error-text { color: #ef4444; font-size: 0.8rem; font-weight: 700; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
.alert-error { background: #fef2f2; border: 2px solid #fecaca; border-radius: 16px; padding: 16px 20px; margin-bottom: 25px; color: #991b1b; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
.alert-error i { font-size: 1.2rem; color: #dc2626; }
.alert-warning { background: #fff7ed; border: 2px solid #fed7aa; border-radius: 16px; padding: 16px 20px; margin-bottom: 25px; color: #9a3412; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
.alert-warning i { font-size: 1.2rem; color: #ea580c; }
.helper-text { font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
.helper-text i { color: var(--p-pink); }
.info-card { background: linear-gradient(135deg, #FFF0F3, #FFF8F0); border-radius: 16px; padding: 16px 20px; margin-bottom: 25px; border: 1px solid rgba(255, 228, 233, 0.8); display: flex; align-items: center; gap: 12px; }
.info-card i { font-size: 1.5rem; color: var(--p-pink); }
.info-card .info-text { font-size: 0.85rem; color: #4a5568; font-weight: 600; line-height: 1.5; }
.info-card .info-text strong { color: var(--p-pink); }
.btn-simpan { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: #ffffff; border: none; border-radius: 16px; padding: 16px 32px; font-weight: 800; font-size: 1rem; transition: var(--transition-3d); box-shadow: 0 10px 25px rgba(213, 61, 102, 0.3); display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn-simpan:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(213, 61, 102, 0.4); color: #ffffff; }
.btn-kembali { background: #f1f5f9; color: #475569; border: none; border-radius: 16px; padding: 14px 28px; font-weight: 700; font-size: 0.95rem; transition: var(--transition-3d); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-kembali:hover { background: #e2e8f0; color: var(--text-dark); transform: translateY(-2px); }
.btn-group-bottom { display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; padding-top: 25px; border-top: 2px solid #f1f5f9; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
.durasi-preview { background: linear-gradient(135deg, #FFF0F3, #FFF8F0); border-radius: 14px; padding: 16px 20px; border: 2px solid var(--light-pink); display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.durasi-preview i { font-size: 1.5rem; color: var(--p-pink); }
.durasi-preview .durasi-text { font-size: 0.9rem; color: #4a5568; font-weight: 600; }
.durasi-preview .durasi-text strong { color: var(--p-pink); }
.booked-badge { background: linear-gradient(135deg, #fef2f2, #fee2e2); border-radius: 14px; padding: 16px 20px; border: 2px solid #fecaca; display: flex; align-items: center; gap: 12px; margin-bottom: 25px; }
.booked-badge i { font-size: 1.5rem; color: #dc2626; }
.booked-badge .booked-text { font-size: 0.9rem; color: #991b1b; font-weight: 600; }
.booked-badge .booked-text strong { color: #dc2626; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in-up { animation: fadeIn 0.5s ease-out; }
@media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } .sidebar { transform: translateX(-100%); } .form-card { padding: 25px; } .btn-group-bottom { flex-direction: column; } .btn-simpan, .btn-kembali { width: 100%; justify-content: center; } }
</style>
</head>
<body>

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
            <li class="nav-item"><a href="../../index.php" class="nav-link-custom" onclick="confirmLandingPage(event)"><span><i class="bi bi-house-door-fill me-2"></i>Beranda</span></a></li>
        </ul>
    </div>
    <div><button onclick="confirmLogout(event)" class="btn btn-logout text-center d-block w-100"><i class="bi bi-box-arrow-right me-2"></i> Keluar Sistem</button></div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dashboard-header fade-in-up">
        <div><h3 class="fw-bold mb-1">Edit Jadwal Studio</h3><p class="text-muted small mb-0">Perbarui data jadwal.</p></div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge px-3 py-2 text-dark border-0 shadow-sm" style="background: var(--light-pink); font-weight: 700; border-radius: 10px;"><i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span></span>
            <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat profil Anda"><img src="<?= $foto_admin_src ?>" alt="Admin Profil"></div>
        </div>
    </div>

    <div class="form-card fade-in-up">
        <div class="info-card"><i class="bi bi-info-circle-fill"></i><div class="info-text"><strong>Perhatian:</strong> Pilih <strong>Paket Foto</strong> dulu, lalu <strong>Ruangan</strong> yang valid akan muncul. Sistem otomatis menghitung <strong>Jam Selesai</strong> dari durasi paket. Jam operasional: <strong>08:00 - 20:00 WIB</strong>.</div></div>

        <?php if(isset($errors['general'])): ?><div class="alert-error"><i class="bi bi-exclamation-octagon-fill"></i><?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>

        <?php if ($is_booked): ?>
            <div class="booked-badge"><i class="bi bi-exclamation-triangle-fill"></i><div class="booked-text">Jadwal ini sudah di-booking oleh pelanggan. Pilihan Paket Foto, Ruangan, Tanggal, dan Jam Mulai dikunci demi menjaga integritas data transaksi. Anda hanya dapat mengubah keterangan.</div></div>
        <?php endif; ?>

        <form method="POST" id="formJadwal">
            <div class="form-grid mb-4">
                <div>
                    <label class="form-label-custom">Paket Foto <span class="text-danger">*</span></label>
                    <select name="id_paket" id="idPaket" class="form-input-custom <?= isset($errors['id_paket']) ? 'is-invalid' : '' ?>" <?= $is_booked ? 'disabled' : '' ?> required>
                        <option value="">-- Pilih Paket Foto --</option>
                        <?php 
                        sqlsrv_free_stmt($q_paket);
                        $q_paket = sqlsrv_query($conn, "SELECT ID_Paket, Nama_Paket, Durasi_Waktu FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Paket");
                        while($p = sqlsrv_fetch_array($q_paket, SQLSRV_FETCH_ASSOC)): 
                        ?>
                            <option value="<?= $p['ID_Paket'] ?>" data-durasi="<?= $p['Durasi_Waktu'] ?>" <?= ($selected_paket_id == $p['ID_Paket']) ? 'selected' : '' ?>><?= htmlspecialchars($p['Nama_Paket']) ?> (<?= $p['Durasi_Waktu'] ?> menit)</option>
                        <?php endwhile; ?>
                    </select>
                    <?php if($is_booked): ?><input type="hidden" name="id_paket" value="<?= $current_paket_id ?>"><?php endif; ?>
                    <?php if(isset($errors['id_paket'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['id_paket'] ?></span><?php endif; ?>
                    <div class="helper-text"><i class="bi bi-info-circle"></i><?= $is_booked ? 'Paket tidak bisa diubah karena sudah dipesan.' : 'Pilih paket foto terlebih dahulu.' ?></div>
                </div>
                <div>
                    <label class="form-label-custom">Ruangan <span class="text-danger">*</span></label>
                    <select name="id_ruangan" id="idRuangan" class="form-input-custom <?= isset($errors['id_ruangan']) ? 'is-invalid' : '' ?>" <?= $is_booked ? 'disabled' : '' ?> required>
                        <option value="">-- Pilih Ruangan --</option>
                        <?php foreach ($valid_ruangan_list as $r_valid): 
                            $selected_room_id = isset($old_values['id_ruangan']) ? (int)$old_values['id_ruangan'] : (int)$data['ID_Ruangan'];
                            $sel = ($r_valid['ID_Ruangan'] == $selected_room_id) ? 'selected' : '';
                        ?>
                            <option value="<?= $r_valid['ID_Ruangan'] ?>" <?= $sel ?>><?= htmlspecialchars($r_valid['Nama_Ruangan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($is_booked): ?><input type="hidden" name="id_ruangan" value="<?= $data['ID_Ruangan'] ?>"><?php endif; ?>
                    <?php if(isset($errors['id_ruangan'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['id_ruangan'] ?></span><?php endif; ?>
                    <div class="helper-text"><i class="bi bi-info-circle"></i><?= $is_booked ? 'Ruangan tidak bisa diubah karena sudah dipesan.' : 'Ruangan akan muncul sesuai paket yang dipilih.' ?></div>
                </div>
            </div>

            <div class="durasi-preview mb-4" id="durasiPreview"><i class="bi bi-clock-history"></i><div class="durasi-text">Durasi Paket: <strong id="durasiText"><?= $durasi_menit ?></strong> menit<br><span style="font-size: 0.8rem; color: #718096;">Jam selesai dihitung otomatis</span></div></div>

            <div class="form-grid mb-4">
                <div>
                    <label class="form-label-custom">Tanggal Jadwal <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_jadwal" id="tanggalJadwal" class="form-input-custom <?= isset($errors['tanggal_jadwal']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_values['tanggal_jadwal'] ?? $tgl_input) ?>" min="<?= date('Y-m-d') ?>" <?= $is_booked ? 'disabled' : '' ?> required>
                    <?php if($is_booked): ?><input type="hidden" name="tanggal_jadwal" value="<?= $tgl_input ?>"><?php endif; ?>
                    <?php if(isset($errors['tanggal_jadwal'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['tanggal_jadwal'] ?></span><?php endif; ?>
                    <div class="helper-text"><i class="bi bi-info-circle"></i><?= $is_booked ? 'Tanggal tidak bisa diubah karena sudah dipesan.' : 'Tidak boleh di masa lalu.' ?></div>
                </div>
                <div>
                    <label class="form-label-custom">Jam Mulai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_mulai" id="jamMulai" class="form-input-custom <?= isset($errors['jam_mulai']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_values['jam_mulai'] ?? $jam_input) ?>" step="60" <?= $is_booked ? 'disabled' : '' ?> required>
                    <?php if($is_booked): ?><input type="hidden" name="jam_mulai" value="<?= $jam_input ?>"><?php endif; ?>
                    <?php if(isset($errors['jam_mulai'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['jam_mulai'] ?></span><?php endif; ?>
                    <div class="helper-text"><i class="bi bi-info-circle"></i><?= $is_booked ? 'Jam tidak bisa diubah karena sudah dipesan.' : 'Jam operasional: 08:00 - 20:00 WIB.' ?></div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label-custom">Keterangan <span style="color: #94a3b8; font-weight: 500;">(opsional)</span></label>
                <input type="text" name="keterangan" class="form-input-custom <?= isset($errors['keterangan']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_values['keterangan'] ?? $data['Keterangan'] ?? '') ?>" placeholder="Contoh: Slot Basic Studio A, Libur Hari Raya, Maintenance..." maxlength="255">
                <?php if(isset($errors['keterangan'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['keterangan'] ?></span><?php endif; ?>
                <div class="helper-text"><i class="bi bi-info-circle"></i>Isi 'Libur' untuk menandai hari libur full.</div>
            </div>

            <!-- Status Aktif / Nonaktif -->
            <div class="mb-4">
                <label class="form-label-custom">Status Aktif <span class="text-danger">*</span></label>
                <select name="status" class="form-input-custom <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                    <option value="1" <?= ($data['Status'] == 1) ? 'selected' : '' ?>>Aktif (Tersedia untuk Booking)</option>
                    <option value="0" <?= ($data['Status'] == 0) ? 'selected' : '' ?>>Nonaktif (Disembunyikan dari Pelanggan)</option>
                </select>
                <?php if(isset($errors['status'])): ?><span class="error-text"><i class="bi bi-exclamation-circle-fill"></i><?= $errors['status'] ?></span><?php endif; ?>
            </div>

            <div class="btn-group-bottom">
                <a href="list.php" class="btn-kembali"><i class="bi bi-arrow-left"></i>Kembali</a>
                <button type="submit" name="update" class="btn-simpan"><i class="bi bi-check-circle-fill"></i>Simpan Perubahan</button>
            </div>
        </form>
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

// Cascade Paket → Ruangan (only if not booked)
<?php if (!$is_booked): ?>
document.getElementById('idPaket').addEventListener('change', function() {
    const paketId = this.value;
    const ruanganSelect = document.getElementById('idRuangan');
    const durasiText = document.getElementById('durasiText');

    if (!paketId) {
        ruanganSelect.innerHTML = '<option value="">-- Pilih Paket Dulu --</option>';
        return;
    }

    const selectedOption = this.options[this.selectedIndex];
    durasiText.textContent = selectedOption.getAttribute('data-durasi');

    fetch('get_ruangan_by_paket.php?id_paket=' + paketId)
        .then(response => response.json())
        .then(data => {
            ruanganSelect.innerHTML = '<option value="">-- Pilih Ruangan --</option>';
            data.forEach(r => {
                ruanganSelect.innerHTML += '<option value="' + r.ID_Ruangan + '">' + r.Nama_Ruangan + '</option>';
            });
        })
        .catch(err => {
            console.error('Error:', err);
            ruanganSelect.innerHTML = '<option value="">Error loading ruangan</option>';
        });
});
<?php endif; ?>

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
</script>

<?php if($success): ?>
<script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data jadwal studio berhasil diperbarui.', confirmButtonColor: '#D53D66', confirmButtonText: 'Oke' }).then(() => { window.location.href = 'list.php?status_sukses=edit'; });</script>
<?php endif; ?>

</body>
</html>
<?php ob_end_flush(); ?>