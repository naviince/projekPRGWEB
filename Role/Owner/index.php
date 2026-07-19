<?php
session_start();
include '../../koneksi.php'; 

// --- PROTEKSI KEAMANAN HAK AKSES BERLAPIS ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

$id_owner = $_SESSION['id_user'];

$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
if ($d_profile) { $d_profile = array_change_key_case($d_profile, CASE_LOWER); }

$nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
$username_owner = $d_profile['username_karyawan'] ?? 'owner';
$email_owner = $d_profile['email_karyawan'] ?? 'owner@spotlight.com';
$foto_owner = $d_profile['foto_profil'] ?? 'default.jpg';

if ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) {
    $foto_owner_src = "../../assets/img/pelanggan/" . $foto_owner;
} else {
    $foto_owner_src = $default_svg_avatar;
}

$error_profile = "";
$success_profile = false;

// =====================================================
// HELPER HASH PASSWORD (migrasi aman dari plain-text -> bcrypt)
// Password lama di database kemungkinan masih tersimpan sebagai plain-text
// (belum pernah di-hash). Fungsi ini menerima KEDUANYA: kalau nilai yang
// tersimpan sudah berupa hash bcrypt, verifikasi pakai password_verify();
// kalau belum (masih plain-text lama), fallback ke perbandingan string
// biasa. Supaya akun lama tidak langsung terkunci begitu fitur hashing ini
// diaktifkan, sebelum sempat ganti password.
// =====================================================
function verifikasiPasswordLegacy($input, $stored) {
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($input, $stored);
    }
    return hash_equals((string)$stored, (string)$input); // fallback plain-text lama
}

if (isset($_POST['update_profil'])) {
    $nama_input = trim($_POST['nama']);
    $username_input = trim($_POST['username']);
    $email_input = trim($_POST['email']);
    $no_hp_input = str_replace(' ', '', trim($_POST['no_hp']));
    $alamat_input = trim($_POST['alamat']);
    $pass_lama = $_POST['password_lama'] ?? '';
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
        $sandi_final = $d_profile['password_karyawan'];
        if (!empty($pass_baru)) {
            if (empty($pass_lama)) {
                $error_profile = "Masukkan password saat ini untuk mengonfirmasi perubahan password!";
            } elseif (!verifikasiPasswordLegacy($pass_lama, $d_profile['password_karyawan'])) {
                $error_profile = "Password saat ini salah!";
            } elseif (strlen($pass_baru) < 8 || !preg_match("/[A-Za-z]/", $pass_baru) || !preg_match("/[0-9]/", $pass_baru) || !preg_match("/[^A-Za-z0-9]/", $pass_baru)) {
                $error_profile = "Sandi baru minimal 8 karakter (kombinasi huruf, angka, simbol)!";
            } elseif ($pass_baru === $pass_lama) {
                $error_profile = "Password baru tidak boleh sama dengan password lama!";
            } elseif ($pass_baru !== $confirm_pass) {
                $error_profile = "Konfirmasi kata sandi tidak cocok!";
            } else {
                $sandi_final = password_hash($pass_baru, PASSWORD_BCRYPT);
            }
        }
        if ($error_profile == "") {
            $sql_cek = "SELECT Email_Karyawan, Username_Karyawan, No_Hp FROM Karyawan WHERE (Email_Karyawan = ? OR Username_Karyawan = ? OR No_Hp = ?) AND ID_Karyawan != ?";
            $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email_input, $username_input, $no_hp_input, $id_owner));
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
            $foto_baru = $foto_owner;
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
                    $foto_baru = "owner_" . time() . "_" . uniqid() . "." . $file_ext;
                    $target_dir = "../../assets/img/pelanggan/";
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    if (move_uploaded_file($file_tmp, $target_dir . $foto_baru)) {
                        if ($foto_owner != 'default.jpg' && file_exists($target_dir . $foto_owner)) { unlink($target_dir . $foto_owner); }
                    } else {
                        $error_profile = "Gagal mengunggah foto profil!";
                    }
                }
            }
            if ($error_profile == "") {
                $sql_upd = "UPDATE Karyawan SET Nama_Karyawan = ?, Username_Karyawan = ?, Email_Karyawan = ?, Password_Karyawan = ?, No_Hp = ?, Alamat = ?, Foto_Profil = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?";
                $stmt_upd = sqlsrv_query($conn, $sql_upd, array($nama_input, $username_input, $email_input, $sandi_final, $no_hp_input, $alamat_input, $foto_baru, $username_owner, $id_owner));
                if ($stmt_upd) {
                    $success_profile = true;
                    $nama_owner = $nama_input;
                    $username_owner = $username_input;
                    $email_owner = $email_input;
                    $foto_owner = $foto_baru;
                    $foto_owner_src = ($foto_owner != 'default.jpg' && file_exists("../../assets/img/pelanggan/" . $foto_owner)) ? "../../assets/img/pelanggan/" . $foto_owner : $default_svg_avatar;
                    $d_profile['no_hp'] = $no_hp_input;
                    $d_profile['alamat'] = $alamat_input;
                } else {
                    $error_profile = "Gagal memperbarui data di database!";
                }
            }
        }
    }
}

// =====================================================
// FILTER TAHUN (untuk laporan/statistik dashboard)
// Owner bisa memilih tahun mana yang ingin dilihat (tahun ini, tahun lalu,
// dst) atau "Semua Tahun" untuk agregat sepanjang waktu (perilaku default
// sebelum filter ini ada). Daftar tahun yang muncul di dropdown HANYA
// tahun yang benar-benar punya data transaksi (dari Order/Pembayaran/
// Penjualan), supaya tidak ada pilihan tahun kosong -- tahun berjalan tetap
// selalu tersedia meski belum ada transaksi.
// =====================================================
$q_tahun_tersedia = sqlsrv_query($conn, "
    SELECT DISTINCT thn FROM (
        SELECT YEAR(Created_Date) AS thn FROM [Order]
        UNION
        SELECT YEAR(Tanggal_Upload) AS thn FROM Pembayaran
        UNION
        SELECT YEAR(Tanggal_Penjualan) AS thn FROM Penjualan
    ) x
    WHERE thn IS NOT NULL
    ORDER BY thn DESC
");
$daftar_tahun = [];
if ($q_tahun_tersedia) {
    while ($r = sqlsrv_fetch_array($q_tahun_tersedia, SQLSRV_FETCH_ASSOC)) {
        $daftar_tahun[] = (int)$r['thn'];
    }
}
$tahun_sekarang = (int)date('Y');
if (!in_array($tahun_sekarang, $daftar_tahun, true)) {
    $daftar_tahun[] = $tahun_sekarang;
}
rsort($daftar_tahun);

// Tahun terpilih lewat dropdown (?tahun=2025, atau ?tahun=semua). Divalidasi
// ketat terhadap whitelist $daftar_tahun di atas -- tidak langsung dipakai
// mentah dari $_GET -- supaya tidak bisa disuntik nilai sembarang lewat URL.
$tahun_filter_raw = isset($_GET['tahun']) ? trim($_GET['tahun']) : (string)$tahun_sekarang;
$filter_semua_tahun = ($tahun_filter_raw === 'semua');
$tahun_filter = $filter_semua_tahun ? null : (int)$tahun_filter_raw;
if (!$filter_semua_tahun && !in_array($tahun_filter, $daftar_tahun, true)) {
    $tahun_filter = $tahun_sekarang; // fallback aman kalau nilai tidak valid/di luar whitelist
}
$label_periode = $filter_semua_tahun ? 'Semua Tahun' : "Tahun $tahun_filter";

// Helper: bikin fragmen kondisi "YEAR(kolom) = tahun_filter", atau string
// kosong kalau mode "Semua Tahun" (tanpa filter). $tahun_filter sendiri
// sudah dipastikan integer murni oleh validasi whitelist di atas, aman
// untuk disisipkan langsung ke SQL.
function condTahun($kolom) {
    global $filter_semua_tahun, $tahun_filter;
    return $filter_semua_tahun ? "" : " AND YEAR($kolom) = $tahun_filter";
}

$q_pendapatan = sqlsrv_query($conn, "SELECT SUM(Jumlah_Bayar) AS total FROM Pembayaran WHERE Status_Pembayaran = 1" . condTahun('Tanggal_Upload'));
$d_pendapatan = sqlsrv_fetch_array($q_pendapatan, SQLSRV_FETCH_ASSOC);
$total_pendapatan = $d_pendapatan['total'] ?? 0;

$q_karyawan = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0");
$d_karyawan = sqlsrv_fetch_array($q_karyawan, SQLSRV_FETCH_ASSOC);
$total_karyawan = $d_karyawan['total'] ?? 0;

$q_pelanggan = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Pelanggan WHERE Status = 1 AND Is_Deleted = 0");
$d_pelanggan = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC);
$total_pelanggan = $d_pelanggan['total'] ?? 0;

$q_sesi = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Sesi_Foto WHERE Status_Sesi = 1" . condTahun('Waktu_Selesai'));
$d_sesi = sqlsrv_fetch_array($q_sesi, SQLSRV_FETCH_ASSOC);
$total_sesi = $d_sesi['total'] ?? 0;

$q_booking_today = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)");
$d_booking_today = sqlsrv_fetch_array($q_booking_today, SQLSRV_FETCH_ASSOC);
$booking_today = $d_booking_today['total'] ?? 0;

$q_wait_dp = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Pembayaran WHERE Status_Pembayaran = 0 AND Tipe_Pembayaran = 'DP'");
$d_wait_dp = sqlsrv_fetch_array($q_wait_dp, SQLSRV_FETCH_ASSOC);
$wait_dp = $d_wait_dp['total'] ?? 0;

$q_batal_month = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM [Order] WHERE Status_Order = 4" . condTahun('Created_Date'));
$d_batal_month = sqlsrv_fetch_array($q_batal_month, SQLSRV_FETCH_ASSOC);
$batal_month = $d_batal_month['total'] ?? 0;

$q_stok_menipis = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Barang_Cetak WHERE Stok_Barang <= Stok_Minimum AND Status = 1 AND Is_Deleted = 0");
$d_stok_menipis = sqlsrv_fetch_array($q_stok_menipis, SQLSRV_FETCH_ASSOC);
$stok_menipis = $d_stok_menipis['total'] ?? 0;

$q_status_booking = sqlsrv_query($conn, "SELECT SUM(CASE WHEN Status_Order = 0 THEN 1 ELSE 0 END) AS menunggu_dp, SUM(CASE WHEN Status_Order = 1 THEN 1 ELSE 0 END) AS dp_verified, SUM(CASE WHEN Status_Order = 2 THEN 1 ELSE 0 END) AS tunggu_pelunasan, SUM(CASE WHEN Status_Order = 3 THEN 1 ELSE 0 END) AS lunas, SUM(CASE WHEN Status_Order = 4 THEN 1 ELSE 0 END) AS dibatalkan FROM [Order] WHERE Status = 1" . condTahun('Tanggal_Booking'));
$d_status_booking = sqlsrv_fetch_array($q_status_booking, SQLSRV_FETCH_ASSOC);

$q_top_paket = sqlsrv_query($conn, "SELECT TOP 5 p.Nama_Paket, COUNT(o.ID_Order) AS total_order FROM Paket_Foto p LEFT JOIN [Order] o ON p.ID_Paket = o.ID_Paket" . ($filter_semua_tahun ? "" : " AND YEAR(o.Tanggal_Booking) = $tahun_filter") . " WHERE p.Status = 1 AND p.Is_Deleted = 0 GROUP BY p.ID_Paket, p.Nama_Paket ORDER BY total_order DESC");
$top_paket_labels = [];
$top_paket_data = [];
while ($row = sqlsrv_fetch_array($q_top_paket, SQLSRV_FETCH_ASSOC)) {
    $top_paket_labels[] = $row['Nama_Paket'];
    $top_paket_data[] = $row['total_order'];
}

$q_pendapatan_dp = sqlsrv_query($conn, "SELECT SUM(Jumlah_Bayar) AS total FROM Pembayaran WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'DP'" . condTahun('Tanggal_Upload'));
$d_pendapatan_dp = sqlsrv_fetch_array($q_pendapatan_dp, SQLSRV_FETCH_ASSOC);
$pendapatan_dp = $d_pendapatan_dp['total'] ?? 0;

$q_pendapatan_lunas = sqlsrv_query($conn, "SELECT SUM(Jumlah_Bayar) AS total FROM Pembayaran WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'Pelunasan'" . condTahun('Tanggal_Upload'));
$d_pendapatan_lunas = sqlsrv_fetch_array($q_pendapatan_lunas, SQLSRV_FETCH_ASSOC);
$pendapatan_lunas = $d_pendapatan_lunas['total'] ?? 0;

$q_pendapatan_barang = sqlsrv_query($conn, "SELECT SUM(Total_Penjualan) AS total FROM Penjualan WHERE Status_Penjualan = 1" . condTahun('Tanggal_Penjualan'));
$d_pendapatan_barang = sqlsrv_fetch_array($q_pendapatan_barang, SQLSRV_FETCH_ASSOC);
$pendapatan_barang = $d_pendapatan_barang['total'] ?? 0;

// Tren pendaftaran pelanggan per bulan -- untuk tahun spesifik ditampilkan
// Jan-Des tahun itu; untuk "Semua Tahun" ditampilkan pola musiman gabungan
// dari seluruh tahun (bukan lagi rolling 5 bulan terakhir dari hari ini).
$q_tren_pelanggan = sqlsrv_query($conn, "SELECT MONTH(Created_Date) AS bulan, COUNT(*) AS total FROM Pelanggan WHERE 1=1" . condTahun('Created_Date') . " GROUP BY MONTH(Created_Date) ORDER BY MONTH(Created_Date)");
$tren_pelanggan_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tren_pelanggan_data = array_fill(0, 12, 0);
while ($row = sqlsrv_fetch_array($q_tren_pelanggan, SQLSRV_FETCH_ASSOC)) {
    $tren_pelanggan_data[$row['bulan'] - 1] = $row['total'];
}

$q_stok_alert = sqlsrv_query($conn, "SELECT TOP 3 Nama_Barang, Stok_Barang, Stok_Minimum FROM Barang_Cetak WHERE Stok_Barang <= Stok_Minimum AND Status = 1 AND Is_Deleted = 0 ORDER BY Stok_Barang ASC");

$q_pembayaran_alert = sqlsrv_query($conn, "SELECT TOP 3 p.ID_Pembayaran, pl.Nama_Pelanggan, p.Tanggal_Upload, p.Jumlah_Bayar FROM Pembayaran p JOIN [Order] o ON p.ID_Order = o.ID_Order JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan WHERE p.Status_Pembayaran = 0 AND DATEDIFF(HOUR, p.Tanggal_Upload, GETDATE()) > 24 ORDER BY p.Tanggal_Upload ASC");

$q_aktivitas = sqlsrv_query($conn, "SELECT TOP 5 o.ID_Order, pl.Nama_Pelanggan, pk.Nama_Paket, o.Tanggal_Booking, o.Status_Order, o.Total_Harga FROM [Order] o JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket WHERE 1=1" . condTahun('o.Created_Date') . " ORDER BY o.Created_Date DESC");

$q_role_admin = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Admin' AND Status = 1 AND Is_Deleted = 0");
$d_role_admin = sqlsrv_fetch_array($q_role_admin, SQLSRV_FETCH_ASSOC);
$count_admin = $d_role_admin['total'] ?? 0;

$q_role_foto = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Fotografer' AND Status = 1 AND Is_Deleted = 0");
$d_role_foto = sqlsrv_fetch_array($q_role_foto, SQLSRV_FETCH_ASSOC);
$count_foto = $d_role_foto['total'] ?? 0;

$q_role_owner = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM Karyawan WHERE Role_Karyawan = 'Owner' AND Status = 1 AND Is_Deleted = 0");
$d_role_owner = sqlsrv_fetch_array($q_role_owner, SQLSRV_FETCH_ASSOC);
$count_owner = $d_role_owner['total'] ?? 0;

$q_pendapatan_bulan = sqlsrv_query($conn, "SELECT MONTH(Tanggal_Upload) AS bulan, SUM(Jumlah_Bayar) AS total FROM Pembayaran WHERE Status_Pembayaran = 1" . condTahun('Tanggal_Upload') . " GROUP BY MONTH(Tanggal_Upload) ORDER BY MONTH(Tanggal_Upload)");
$pendapatan_bulan_data = array_fill(0, 12, 0);
while ($row = sqlsrv_fetch_array($q_pendapatan_bulan, SQLSRV_FETCH_ASSOC)) {
    $pendapatan_bulan_data[$row['bulan'] - 1] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Panel Owner – SpotLight Studio</title>
<link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
<link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root { 
    --p-pink: #d83f67; 
    --d-pink: #c73165; 
    --s-pink: #fff5f6; 
    --light-pink: #ffe4e9;
    --accent-pink: #ff6694;
    --text-dark: #1e1e24;
    --text-muted: #718096;
    --sidebar-bg: #ffffff;
    --body-bg: #f8fafc;
    --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    --sidebar-width: 260px;
    --header-height: 70px;
}

* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

body { 
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background-color: var(--body-bg);
    color: var(--text-dark);
    overflow-x: hidden;
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* ========== SIDEBAR ========== */
.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    position: fixed;
    top: 0;
    left: 0;
    border-right: 1px solid rgba(255, 236, 239, 0.8);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 24px 16px;
    z-index: 1040;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    scrollbar-width: none;
}
.sidebar::-webkit-scrollbar { display: none; }

.sidebar-brand {
    font-weight: 800;
    font-size: 1.4rem;
    color: var(--p-pink);
    text-decoration: none;
    letter-spacing: -1px;
    margin-bottom: 32px;
    display: block;
    padding: 0 4px;
}
.sidebar-brand span {
    color: var(--text-dark);
    font-size: 0.8rem;
    font-weight: 600;
    display: block;
    margin-top: 2px;
}

.sidebar-menu-wrapper {
    flex-grow: 1;
    overflow-y: auto;
    margin-bottom: 16px;
    scrollbar-width: none;
}
.sidebar-menu-wrapper::-webkit-scrollbar { display: none; }

.nav-menu { list-style: none; padding: 0; margin: 0; }
.nav-item { margin-bottom: 6px; }
.nav-link-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    color: #4a5568;
    font-weight: 700;
    text-decoration: none;
    border-radius: 12px;
    font-size: 0.85rem;
    transition: var(--transition-3d);
}
.nav-link-custom:hover, .nav-link-custom.active {
    background-color: var(--light-pink);
    color: var(--p-pink);
    transform: translateX(4px);
}
.nav-link-custom i.me-2 { flex-shrink: 0; }

.submenu {
    list-style: none;
    padding-left: 16px;
    margin-top: 4px;
    display: none;
    transition: var(--transition-3d);
}
.submenu.show { display: block !important; }
.submenu-link {
    display: flex;
    align-items: center;
    padding: 7px 14px;
    color: #718096;
    font-weight: 600;
    font-size: .82rem;
    text-decoration: none;
    border-radius: 10px;
    transition: .3s;
}
.submenu-link:hover, .submenu-link.active {
    color: var(--p-pink);
    background-color: rgba(216, 63, 103, 0.03);
    padding-left: 18px;
}

.btn-logout {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: #ffffff;
    border: none;
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    font-weight: 800;
    font-size: 0.85rem;
    transition: var(--transition-3d);
}
.btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(216, 63, 103, 0.2);
}

/* ========== MOBILE HEADER ========== */
.mobile-header {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--header-height);
    background: var(--sidebar-bg);
    border-bottom: 1px solid rgba(255, 236, 239, 0.8);
    z-index: 1030;
    padding: 0 16px;
    align-items: center;
    justify-content: space-between;
}
.mobile-brand {
    font-weight: 800;
    font-size: 1.2rem;
    color: var(--p-pink);
    text-decoration: none;
}
.mobile-brand span {
    font-size: 0.7rem;
    color: var(--text-dark);
    display: block;
    font-weight: 600;
}
.btn-toggle-sidebar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: none;
    background: var(--s-pink);
    color: var(--p-pink);
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition-3d);
    flex-shrink: 0;
}
.btn-toggle-sidebar:hover {
    background: var(--p-pink);
    color: #fff;
}
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1035;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.show {
    display: block;
    opacity: 1;
}

/* ========== MAIN CONTENT ========== */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 32px 28px;
    min-height: 100vh;
    transition: margin-left 0.35s ease;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 12px;
}
.dashboard-header h3 {
    font-size: 1.5rem;
    margin-bottom: 4px;
}
.dashboard-header p {
    font-size: 0.85rem;
}

/* ========== FILTER PERIODE LAPORAN ========== */
.period-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    background: #ffffff;
    border: 1px solid rgba(216,63,103,0.12);
    border-radius: 14px;
    padding: 12px 18px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}
.period-select {
    border: 1.5px solid var(--light-pink);
    background: var(--s-pink);
    color: var(--text-dark);
    font-weight: 700;
    font-size: 0.85rem;
    border-radius: 10px;
    padding: 7px 14px;
    cursor: pointer;
    outline: none;
    transition: var(--transition-3d);
}
.period-select:hover, .period-select:focus {
    border-color: var(--p-pink);
    box-shadow: 0 0 0 3px rgba(216,63,103,0.1);
}
.period-filter-note {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
    max-width: 480px;
}
@media (max-width: 768px) {
    .period-filter-bar { flex-direction: column; align-items: flex-start; }
    .period-filter-note { max-width: 100%; }
}

.profile-header-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid #ffffff;
    cursor: pointer;
    transition: var(--transition-3d);
    background: #ffffff;
    flex-shrink: 0;
}
.profile-header-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 8px 20px rgba(216, 63, 103, 0.15);
    border-color: var(--p-pink);
}
.profile-header-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* ========== STATS CARDS SCROLL ========== */
.stats-scroll-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 12px;
    margin-bottom: 16px;
    scrollbar-width: thin;
    scrollbar-color: var(--p-pink) #f1f5f9;
    -webkit-overflow-scrolling: touch;
}
.stats-scroll-wrapper::-webkit-scrollbar { height: 6px; }
.stats-scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.stats-scroll-wrapper::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }

.stats-row {
    display: flex;
    gap: 14px;
    min-width: max-content;
}
.stat-card-item {
    min-width: 200px;
    max-width: 260px;
    flex: 0 0 auto;
}

/* ========== CARD 3D ========== */
.card-3d {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid rgba(255, 236, 239, 0.8);
    box-shadow: 0 6px 20px rgba(216, 63, 103, 0.03);
    transition: var(--transition-3d);
    padding: 18px;
    height: 100%;
    position: relative;
    overflow: hidden;
}
.card-3d::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--p-pink), var(--accent-pink));
    opacity: 0;
    transition: opacity 0.3s ease;
}
.card-3d:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 18px 40px rgba(216, 63, 103, 0.12);
    border-color: var(--p-pink);
}
.card-3d:hover::before { opacity: 1; }

.stat-card {
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: var(--transition-3d);
    flex-shrink: 0;
}
.card-3d:hover .stat-icon { transform: scale(1.1) rotate(5deg); }

.stat-icon-pink { background: linear-gradient(135deg, #fff5f6, #ffe4e9); color: var(--p-pink); }
.stat-icon-blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; }
.stat-icon-green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669; }
.stat-icon-orange { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #d97706; }
.stat-icon-red { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; }
.stat-icon-purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed; }
.stat-icon-dark { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #1e1e24; }
.stat-icon-cyan { background: linear-gradient(135deg, #ecfeff, #cffafe); color: #0891b2; }

.stat-content { flex: 1; min-width: 0; overflow: hidden; }
.stat-val {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 2px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-title {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-subtitle {
    font-size: 0.65rem;
    color: #a0aec0;
    font-weight: 600;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ========== CHART CARDS ========== */
.chart-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid rgba(255, 236, 239, 0.8);
    box-shadow: 0 6px 20px rgba(216, 63, 103, 0.03);
    transition: var(--transition-3d);
    padding: 22px;
    height: 100%;
}
.chart-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 35px rgba(216, 63, 103, 0.1);
}
.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 8px;
}
.chart-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}
.chart-badge {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: #ffffff;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
}

/* ========== ALERT ITEMS ========== */
.alert-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(238, 242, 246, 0.8);
}
.alert-item:last-child { border-bottom: none; }
.alert-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.alert-icon-warning { background: #fffbeb; color: #d97706; }
.alert-icon-danger { background: #fef2f2; color: #dc2626; }
.alert-icon-info { background: #eff6ff; color: #2563eb; }

/* ========== TABLE ========== */
.table-custom {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}
.table-custom thead th {
    color: var(--text-muted);
    font-weight: 800;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 10px 14px;
    border: none;
    background: transparent;
    white-space: nowrap;
}
.table-custom tbody tr {
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: var(--transition-3d);
    border-radius: 12px;
}
.table-custom tbody tr:hover {
    transform: translateX(4px);
    box-shadow: 0 8px 20px rgba(216, 63, 103, 0.08);
}
.table-custom td {
    padding: 12px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    border: none;
    vertical-align: middle;
    white-space: nowrap;
}
.table-custom td:first-child { border-radius: 12px 0 0 12px; }
.table-custom td:last-child { border-radius: 0 12px 12px 0; }

.badge-status {
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-block;
}
.badge-menunggu { background: #fffbeb; color: #d97706; }
.badge-dp { background: #dbeafe; color: #2563eb; }
.badge-pelunasan { background: #e0e7ff; color: #4f46e5; }
.badge-lunas { background: #ecfdf5; color: #059669; }
.badge-batal { background: #fef2f2; color: #dc2626; }

/* ========== FORM ========== */
.required-star { color: #ef4444; font-weight: bold; margin-left: 2px; }
.form-label {
    font-weight: 800;
    font-size: 11px;
    color: #8a99a8;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 8px;
}
.form-control, .form-select {
    border-radius: 14px;
    padding: 12px 18px;
    border: 2px solid #eef2f6;
    background: #f8fafc;
    font-size: 14px;
    font-weight: 600;
    transition: var(--transition-3d);
    color: var(--text-dark);
}
.form-control:focus, .form-select:focus {
    border-color: var(--p-pink);
    background: #ffffff;
    transform: translateY(-3px) scale(1.01);
    box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15);
    outline: none;
}
.profile-preview-box {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    overflow: hidden;
    border: 2.5px solid #eef2f6;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    transition: var(--transition-3d);
}
.profile-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.btn-pilih-foto {
    background: #ffffff;
    border: 1.5px solid var(--p-pink);
    color: var(--p-pink);
    font-weight: 700;
    border-radius: 10px;
    padding: 8px 18px;
    font-size: 0.85rem;
    transition: var(--transition-3d);
}
.btn-pilih-foto:hover {
    background: var(--p-pink);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
}
.btn-reg {
    background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
    color: white;
    border-radius: 16px;
    padding: 16px;
    font-weight: 800;
    border: none;
    width: 100%;
    transition: var(--transition-3d);
    margin-top: 15px;
    font-size: 15px;
    box-shadow: 0 10px 25px rgba(216, 63, 103, 0.25);
}
.btn-reg:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 15px 35px rgba(216, 63, 103, 0.35);
}
.password-group { position: relative; transition: var(--transition-3d); border-radius: 14px; }
.password-group:focus-within { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); }
.password-group .form-control { transition: border-color 0.3s ease, background-color 0.3s ease; }
.password-group .form-control:focus { transform: none !important; box-shadow: none !important; background: #ffffff; border-color: var(--p-pink); }
.toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
.toggle-password:hover { color: var(--p-pink); }

/* ========== ANIMATIONS ========== */
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }

/* ========== SCROLLBAR ========== */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); border-radius: 10px; }

/* ========== RESPONSIVE BREAKPOINTS ========== */

/* Large Desktop */
@media (min-width: 1400px) {
    .main-content { padding: 40px 36px; }
    .stat-card-item { min-width: 220px; }
}

/* Tablet & Below - Sidebar collapses */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-260px);
        box-shadow: 4px 0 20px rgba(0,0,0,0.08);
    }
    .sidebar.open { transform: translateX(0); }
    .mobile-header { display: flex; }
    .main-content {
        margin-left: 0;
        padding: 90px 16px 24px;
    }
    .dashboard-header h3 { font-size: 1.2rem; }
    .dashboard-header p { font-size: 0.8rem; }
    .stat-card-item { min-width: 180px; }
    .card-3d { padding: 16px; }
    .chart-card { padding: 18px; }
}

/* Small Tablet */
@media (max-width: 767.98px) {
    .main-content { padding: 85px 12px 20px; }
    .dashboard-header { flex-direction: column; align-items: flex-start; }
    .dashboard-header > div:last-child { width: 100%; justify-content: space-between; }
    .stat-card-item { min-width: 160px; }
    .stat-val { font-size: 1.15rem; }
    .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
    .chart-title { font-size: 0.9rem; }
    .table-custom td { padding: 10px 12px; font-size: 0.78rem; }
    .table-custom thead th { font-size: 0.62rem; padding: 8px 12px; }
    .badge-status { padding: 4px 10px; font-size: 0.68rem; }
}

/* Mobile */
@media (max-width: 575.98px) {
    .main-content { padding: 80px 10px 16px; }
    .mobile-header { height: 64px; padding: 0 12px; }
    .mobile-brand { font-size: 1.1rem; }
    .btn-toggle-sidebar { width: 38px; height: 38px; font-size: 1.1rem; }
    .dashboard-header h3 { font-size: 1.1rem; }
    .stat-card-item { min-width: 150px; }
    .card-3d { padding: 14px; border-radius: 16px; }
    .stat-card { gap: 10px; }
    .stat-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
    .stat-val { font-size: 1.05rem; }
    .stat-title { font-size: 0.6rem; }
    .stat-subtitle { font-size: 0.58rem; }
    .chart-card { padding: 16px; border-radius: 16px; }
    .chart-header { margin-bottom: 14px; }
    .chart-title { font-size: 0.85rem; }
    .chart-badge { padding: 4px 10px; font-size: 0.68rem; }
    .table-responsive-custom {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -4px;
        padding: 0 4px;
    }
    .table-responsive-custom::-webkit-scrollbar { height: 4px; }
    .table-responsive-custom .table-custom { min-width: 600px; }
    .alert-item { padding: 8px 0; }
    .alert-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .modal-dialog { margin: 12px; }
    .modal-content { border-radius: 20px !important; }
    .profile-preview-box { width: 80px; height: 80px; }
    .form-control, .form-select { padding: 10px 14px; font-size: 16px; border-radius: 12px; }
    .btn-reg { padding: 14px; font-size: 14px; }
    .form-label { font-size: 10px; }
}

/* Very small mobile */
@media (max-width: 359.98px) {
    .stat-card-item { min-width: 140px; }
}

/* Landscape mobile */
@media (max-height: 500px) and (orientation: landscape) {
    .sidebar { padding: 16px 12px; }
    .sidebar-brand { font-size: 1.1rem; margin-bottom: 16px; }
    .nav-link-custom { padding: 8px 12px; font-size: 0.8rem; }
    .mobile-header { height: 52px; }
    .main-content { padding-top: 68px; }
}
</style>
</head>
<body>

<!-- MOBILE HEADER -->
<div class="mobile-header">
    <button class="btn-toggle-sidebar" onclick="toggleSidebar()" aria-label="Toggle Menu">
        <i class="bi bi-list"></i>
    </button>
    <a href="../../index.php" class="mobile-brand">
        SpotLight.<span>Panel Owner</span>
    </a>
    <div class="profile-header-btn shadow-sm" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
        <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
    </div>
</div>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-menu-wrapper">
        <a href="../../index.php" class="sidebar-brand">
            SpotLight.<br>
            <span>Beranda Pemilik</span>
        </a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link-custom active">
                    <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../../Master/Karyawan/index.php" class="nav-link-custom">
                    <span><i class="bi bi-person-badge-fill me-2"></i> Kelola Karyawan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link-custom btn-toggle-submenu" data-target="#submenuLaporan">
                    <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Bisnis</span>
                    <i class="bi bi-chevron-down small icon-chevron"></i>
                </a>
                <div class="submenu" id="submenuLaporan">
                    <ul class="list-unstyled">
                        <li><a href="../../Laporan/Pendapatan/index.php" class="submenu-link"><i class="bi bi-cash-stack me-2"></i>Laporan Pendapatan</a></li>
                        <li><a href="../../Laporan/Stok Barang/index.php" class="submenu-link"><i class="bi bi-box-seam-fill me-2"></i>Laporan Stok Barang</a></li>
                        <li><a href="../../Laporan/Pembatalan/index.php" class="submenu-link"><i class="bi bi-calendar-x-fill me-2"></i>Laporan Pembatalan</a></li>
                        <li><a href="../../Laporan/Paket Terfavorit/index.php" class="submenu-link"><i class="bi bi-star-fill text-warning me-2"></i>Laporan Paket Terfavorit</a></li>
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

<!-- BANNER BROADCAST -->
<div class="alert border-0 d-flex align-items-center gap-3 mb-4 shadow-sm animate-fade-in" style="border-radius:16px;background:linear-gradient(135deg,rgba(216,63,103,0.05),rgba(255,236,239,0.15));border:1px solid rgba(216,63,103,0.15)!important;padding:15px 20px;">
    <div class="stat-icon" style="width:40px;height:40px;background:var(--p-pink);color:#ffffff;font-size:1.1rem;border-radius:10px;flex-shrink:0;">
        <i class="bi bi-broadcast"></i>
    </div>
    <div style="min-width:0;">
        <h6 class="fw-bold mb-0" style="font-size:0.85rem;color:var(--p-pink);">Siaran Sistem SpotLight ✦</h6>
        <small class="text-muted" style="font-size:0.78rem;font-weight:600;">Semua database SQL Server, profil staf, dan laporan keuangan telah terintegrasi secara aman.</small>
    </div>
</div>

<!-- HEADER -->
<div class="dashboard-header animate-fade-in delay-1">
    <div style="min-width:0;">
        <h3 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($nama_owner) ?>! 👋</h3>
        <p class="text-muted small mb-0">Pantau operasional dan pendapatan studio Anda harian secara dinamis.</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="badge px-3 py-2 text-dark border-0 shadow-sm d-none d-sm-inline-block" style="background:var(--light-pink);font-weight:700;border-radius:10px;">
            <i class="bi bi-clock-history me-1 text-danger"></i> <span id="live-clock">Memuat waktu...</span>
        </span>
        <div class="profile-header-btn shadow-sm d-none d-lg-block" onclick="bukaModalBiodata()" title="Klik untuk melihat Biodata Anda">
            <img src="<?= $foto_owner_src ?>" alt="Owner Profil">
        </div>
    </div>
</div>

<!-- FILTER PERIODE LAPORAN -->
<div class="period-filter-bar animate-fade-in delay-1">
    <div class="d-flex align-items-center gap-2" style="min-width:0;">
        <i class="bi bi-funnel-fill text-danger"></i>
        <span class="fw-bold" style="font-size:0.85rem;">Periode Laporan:</span>
        <form method="GET" id="formFilterTahun" class="d-inline-flex">
            <select name="tahun" class="period-select" onchange="document.getElementById('formFilterTahun').submit()">
                <?php foreach ($daftar_tahun as $thn): ?>
                    <option value="<?= $thn ?>" <?= (!$filter_semua_tahun && $tahun_filter == $thn) ? 'selected' : '' ?>>
                        Tahun <?= $thn ?><?= $thn == $tahun_sekarang ? ' (Ini)' : ($thn == $tahun_sekarang - 1 ? ' (Lalu)' : '') ?>
                    </option>
                <?php endforeach; ?>
                <option value="semua" <?= $filter_semua_tahun ? 'selected' : '' ?>>Semua Tahun</option>
            </select>
        </form>
    </div>
    <div class="period-filter-note">
        <i class="bi bi-info-circle me-1"></i>Berlaku untuk pendapatan, grafik &amp; laporan. Kartu "Booking Hari Ini", "Menunggu Verifikasi" &amp; peringatan sistem selalu real-time.
    </div>
</div>

<!-- BARIS 1: STAT CARDS -->
<div class="stats-scroll-wrapper animate-fade-in delay-2">
    <div class="stats-row">
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-pink"><i class="bi bi-cash-coin"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Total Pendapatan</div>
                        <div class="stat-val">Rp<?= number_format($total_pendapatan, 0, ',', '.') ?></div>
                        <div class="stat-subtitle"><?= htmlspecialchars($label_periode) ?> · transaksi valid</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-blue"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Total Karyawan</div>
                        <div class="stat-val"><?= $total_karyawan ?> Staf</div>
                        <div class="stat-subtitle">Admin, Fotografer, Owner</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-green"><i class="bi bi-person-fill-check"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Pelanggan Aktif</div>
                        <div class="stat-val"><?= $total_pelanggan ?> Akun</div>
                        <div class="stat-subtitle">Terdaftar di sistem</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-purple"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Sesi Foto Selesai</div>
                        <div class="stat-val"><?= $total_sesi ?> Sesi</div>
                        <div class="stat-subtitle"><?= htmlspecialchars($label_periode) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-orange"><i class="bi bi-calendar-plus-fill"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Booking Hari Ini</div>
                        <div class="stat-val"><?= $booking_today ?> Order</div>
                        <div class="stat-subtitle"><i class="bi bi-broadcast-pin"></i> Real-time</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-red"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Menunggu Verifikasi</div>
                        <div class="stat-val"><?= $wait_dp ?> Pembayaran</div>
                        <div class="stat-subtitle"><i class="bi bi-broadcast-pin"></i> Real-time</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-dark"><i class="bi bi-x-octagon-fill"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Pembatalan Booking</div>
                        <div class="stat-val"><?= $batal_month ?> Booking</div>
                        <div class="stat-subtitle"><?= htmlspecialchars($label_periode) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card-item">
            <div class="card-3d">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-red"><i class="bi bi-box-seam-fill"></i></div>
                    <div class="stat-content">
                        <div class="stat-title">Stok Menipis</div>
                        <div class="stat-val"><?= $stok_menipis ?> Barang</div>
                        <div class="stat-subtitle">Di bawah minimum</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 2: ALERT & CHART PENDAPATAN -->
<div class="row g-3 mb-4">
    <div class="col-lg-4 animate-fade-in delay-1">
        <div class="chart-card" style="height:100%;">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Peringatan Sistem</h5>
            </div>
            <?php 
            $has_alert = false;
            if ($stok_menipis > 0 && $q_stok_alert && sqlsrv_has_rows($q_stok_alert)): 
                $has_alert = true;
                while ($row = sqlsrv_fetch_array($q_stok_alert, SQLSRV_FETCH_ASSOC)): 
            ?>
            <div class="alert-item">
                <div class="alert-icon alert-icon-danger"><i class="bi bi-box-seam"></i></div>
                <div style="min-width:0;">
                    <div class="fw-bold" style="font-size:0.82rem;"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">Stok: <?= $row['Stok_Barang'] ?> (Min: <?= $row['Stok_Minimum'] ?>)</div>
                </div>
            </div>
            <?php endwhile; endif; ?>
            <?php if ($q_pembayaran_alert && sqlsrv_has_rows($q_pembayaran_alert)): 
                $has_alert = true;
                while ($row = sqlsrv_fetch_array($q_pembayaran_alert, SQLSRV_FETCH_ASSOC)): 
            ?>
            <div class="alert-item">
                <div class="alert-icon alert-icon-warning"><i class="bi bi-clock-history"></i></div>
                <div style="min-width:0;">
                    <div class="fw-bold" style="font-size:0.82rem;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">DP Rp<?= number_format($row['Jumlah_Bayar'], 0, ',', '.') ?> - Menunggu > 24 jam</div>
                </div>
            </div>
            <?php endwhile; endif; ?>
            <?php if (!$has_alert): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle-fill fs-1 mb-2" style="color:#059669;"></i>
                <p class="text-muted fw-bold mb-0" style="font-size:0.85rem;">Tidak ada peringatan</p>
                <p class="text-muted" style="font-size:0.75rem;">Semua sistem berjalan normal</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8 animate-fade-in delay-2">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-graph-up-arrow text-danger me-2"></i>Tren Pendapatan Bulanan</h5>
                <span class="chart-badge"><?= htmlspecialchars($label_periode) ?></span>
            </div>
            <div style="height:300px;width:100%;">
                <canvas id="chartPendapatan"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 3: STATUS BOOKING & TOP PAKET -->
<div class="row g-3 mb-4">
    <div class="col-lg-6 animate-fade-in delay-1">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-bar-chart-fill text-danger me-2"></i>Distribusi Status Booking</h5>
                <span class="chart-badge"><?= htmlspecialchars($label_periode) ?></span>
            </div>
            <div style="height:280px;width:100%;">
                <canvas id="chartStatusBooking"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 animate-fade-in delay-2">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-star-fill text-warning me-2"></i>Top 5 Paket Favorit</h5>
                <span class="chart-badge"><?= htmlspecialchars($label_periode) ?></span>
            </div>
            <div style="height:280px;width:100%;">
                <canvas id="chartTopPaket"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 4: PENDAPATAN KATEGORI & TREN PELANGGAN -->
<div class="row g-3 mb-4">
    <div class="col-lg-6 animate-fade-in delay-1">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-pie-chart-fill text-danger me-2"></i>Pendapatan per Kategori</h5>
                <span class="chart-badge"><?= htmlspecialchars($label_periode) ?></span>
            </div>
            <div style="height:280px;width:100%;display:flex;align-items:center;justify-content:center;">
                <canvas id="chartPendapatanKategori"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 animate-fade-in delay-2">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-people-fill text-primary me-2"></i>Tren Pelanggan Baru</h5>
                <span class="chart-badge"><?= htmlspecialchars($label_periode) ?></span>
            </div>
            <div style="height:280px;width:100%;">
                <canvas id="chartTrenPelanggan"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 5: SEBARAN STAF & AKTIVITAS TERKINI -->
<div class="row g-3 mb-4">
    <div class="col-lg-4 animate-fade-in delay-1">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-diagram-3-fill text-danger me-2"></i>Sebaran Staf Studio</h5>
            </div>
            <div style="height:280px;width:100%;display:flex;align-items:center;justify-content:center;">
                <canvas id="chartKaryawan"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-8 animate-fade-in delay-2">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-activity text-danger me-2"></i>Aktivitas Booking Terkini</h5>
                <a href="../../Transaksi/Order/index.php" class="btn btn-sm" style="background:var(--s-pink);color:var(--p-pink);font-weight:700;border-radius:8px;font-size:0.72rem;text-decoration:none;white-space:nowrap;">Lihat Semua</a>
            </div>
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th class="d-none d-md-table-cell">Tanggal</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($q_aktivitas && sqlsrv_has_rows($q_aktivitas)):
                            while ($row = sqlsrv_fetch_array($q_aktivitas, SQLSRV_FETCH_ASSOC)):
                                $status_class = '';
                                $status_text = '';
                                switch ($row['Status_Order']) {
                                    case 0: $status_class = 'badge-menunggu'; $status_text = 'Menunggu DP'; break;
                                    case 1: $status_class = 'badge-dp'; $status_text = 'DP Terverifikasi'; break;
                                    case 2: $status_class = 'badge-pelunasan'; $status_text = 'Menunggu Pelunasan'; break;
                                    case 3: $status_class = 'badge-lunas'; $status_text = 'Lunas'; break;
                                    case 4: $status_class = 'badge-batal'; $status_text = 'Dibatalkan'; break;
                                }
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="profile-table-avatar" style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                                        <img src="<?= $default_svg_avatar ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                                    </div>
                                    <span class="text-truncate" style="max-width:120px;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['Nama_Paket']) ?></td>
                            <td class="d-none d-md-table-cell"><?= $row['Tanggal_Booking']->format('d M Y') ?></td>
                            <td class="fw-bold">Rp<?= number_format($row['Total_Harga'], 0, ',', '.') ?></td>
                            <td><span class="badge-status <?= $status_class ?>"><?= $status_text ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle-fill fs-1 mb-2" style="color:#059669;"></i>
                                <p class="fw-bold mb-0">Belum ada aktivitas booking</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 6: JADWAL & PEMBATALAN -->
<div class="row g-3">
    <div class="col-lg-7 animate-fade-in delay-1">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-calendar-event-fill text-danger me-2"></i>Sesi Pemotretan Terdekat</h5>
            </div>
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Ruangan</th>
                            <th class="d-none d-md-table-cell">Tanggal</th>
                            <th>Jam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_booking = "SELECT TOP 3 P.Nama_Pelanggan, R.Nama_Ruangan, J.Tanggal_Jadwal, J.Jam_Mulai, S.Status FROM Sesi_Foto S JOIN [Order] O ON S.ID_Order = O.ID_Order JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan JOIN Jadwal_Studio J ON O.ID_Jadwal = J.ID_Jadwal WHERE S.Status_Sesi = 0 ORDER BY J.Tanggal_Jadwal ASC";
                        $query_booking = sqlsrv_query($conn, $sql_booking);
                        if($query_booking && sqlsrv_has_rows($query_booking)):
                            while($row_book = sqlsrv_fetch_array($query_booking, SQLSRV_FETCH_ASSOC)):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row_book['Nama_Pelanggan']) ?></td>
                            <td><?= htmlspecialchars($row_book['Nama_Ruangan']) ?></td>
                            <td class="d-none d-md-table-cell"><?= $row_book['Tanggal_Jadwal']->format('d M Y') ?></td>
                            <td><?= $row_book['Jam_Mulai']->format('H:i') ?></td>
                            <td><span class="badge-status badge-menunggu">Terjadwal</span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada jadwal pemotretan terdekat.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5 animate-fade-in delay-2">
        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title"><i class="bi bi-calendar-x-fill text-danger me-2"></i>Pembatalan Booking Terakhir</h5>
            </div>
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th class="d-none d-md-table-cell">Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_batal = "SELECT TOP 3 P.Nama_Pelanggan, O.Tanggal_Booking FROM [Order] O JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan WHERE O.Status_Order = 4" . condTahun('O.Created_Date') . " ORDER BY O.Tanggal_Booking DESC";
                        $query_batal = sqlsrv_query($conn, $sql_batal);
                        if($query_batal && sqlsrv_has_rows($query_batal)):
                            while($row_batal = sqlsrv_fetch_array($query_batal, SQLSRV_FETCH_ASSOC)):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row_batal['Nama_Pelanggan']) ?></td>
                            <td class="d-none d-md-table-cell"><?= $row_batal['Tanggal_Booking']->format('d M Y') ?></td>
                            <td><span class="badge-status badge-batal">Dibatalkan</span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Belum ada riwayat pembatalan.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<!-- MODAL LIHAT BIODATA -->
<div class="modal fade" id="modalLihatBiodata" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(0,0,0,0.15);background:#ffffff;">
            <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard-fill text-danger me-2"></i>Biodata Pemilik</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <div class="text-center mb-4">
                    <div class="profile-preview-box mx-auto" style="width:100px;height:100px;border:3px solid var(--s-pink);">
                        <img src="<?= $foto_owner_src ?>" alt="Foto Profil">
                    </div>
                    <h5 class="fw-bold text-dark mt-3 mb-1"><?= htmlspecialchars($nama_owner) ?></h5>
                    <span class="badge bg-danger px-3 py-1 text-white text-uppercase" style="font-size:0.72rem;border-radius:50px;font-weight:700;">Owner (Pemilik)</span>
                </div>
                <div class="card-3d p-3 border-0 mb-4" style="border-radius:20px;background-color:#f8fafc;">
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">NIK</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['nik'] ?? '-') ?></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nama Pengguna</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;">@<?= htmlspecialchars($username_owner) ?></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Email</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($email_owner) ?></span>
                        </div>
                        <div class="col-6 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Jenis Kelamin</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['jenis_kelamin'] ?? '-') ?></span>
                        </div>
                        <div class="col-6 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Tanggal Lahir</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= isset($d_profile['tanggal_lahir']) && $d_profile['tanggal_lahir'] ? $d_profile['tanggal_lahir']->format('d M Y') : '-' ?></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Nomor Telepon</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['no_hp'] ?? '-') ?></span>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <small class="text-muted d-block fw-bold" style="font-size:0.7rem;text-transform:uppercase;">Alamat Lengkap</small>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($d_profile['alamat'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-reg shadow-sm py-3 mt-0" onclick="bukaModalEditDariBiodata()" style="border-radius:14px;">Edit Profil Anda ⚙</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL GANTI PROFIL -->
<div class="modal fade" id="modalGantiProfil" tabindex="-1" aria-hidden="true" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:28px;box-shadow:0 20px 50px rgba(216,63,103,0.25);background:rgba(255,255,255,0.95);">
            <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-gear-fill text-danger me-2"></i>Pengaturan Profil Owner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-3">
                <p class="text-muted small mb-4" style="line-height:1.6;">Perbarui informasi profil pribadi Anda di bawah ini secara akurat. Data yang diubah akan langsung disinkronkan ke seluruh sistem SpotLight.</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="d-inline-block position-relative">
                            <div class="profile-preview-box mx-auto">
                                <img id="profile-preview-modal" src="<?= $foto_owner_src ?>" alt="Foto Profil">
                            </div>
                            <input type="file" name="foto_profil" id="inputFotoModal" class="form-control d-none" accept=".jpg,.jpeg,.png">
                            <button type="button" class="btn btn-pilih-foto btn-sm position-absolute" style="bottom:-10px;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:0.75rem;padding:5px 12px;" onclick="document.getElementById('inputFotoModal').click();">Ganti Foto</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label>
                        <input type="text" name="nama" id="inputNamaModal" class="form-control" placeholder="Masukkan nama lengkap Anda" value="<?= htmlspecialchars($nama_owner) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
                        <input type="text" name="username" id="inputUsernameModal" class="form-control" placeholder="Masukkan nama pengguna kustom" value="<?= htmlspecialchars($username_owner) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= htmlspecialchars($email_owner) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
                        <input type="text" name="no_hp" id="inputHPModal" class="form-control" placeholder="Contoh: +628xxxxxxxxxx" value="<?= htmlspecialchars($d_profile['no_hp'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap<span class="required-star">*</span></label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Masukkan alamat domisili lengkap" required style="resize:none;"><?= htmlspecialchars($d_profile['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini <span class="text-muted" style="font-weight:500;font-size:0.78rem;">(isi hanya jika ingin ganti password)</span></label>
                        <div class="password-group">
                            <input type="password" name="password_lama" id="pass_lama_modal" class="form-control" placeholder="Password Anda sekarang" autocomplete="current-password">
                            <i class="bi bi-eye-slash toggle-password" id="btnToggleLama"></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sandi Baru (Opsional)</label>
                            <div class="password-group">
                                <input type="password" name="password" id="pass_baru_modal" class="form-control" placeholder="Minimal 8 karakter" autocomplete="new-password">
                                <i class="bi bi-eye-slash toggle-password" id="btnToggleBaru"></i>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Konfirmasi Sandi</label>
                            <div class="password-group">
                                <input type="password" name="confirm_password" id="pass_konf_modal" class="form-control" placeholder="Ulangi sandi baru">
                                <i class="bi bi-eye-slash toggle-password" id="btnToggleKonf"></i>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_profil" class="btn btn-reg shadow-sm py-3 mt-2">Simpan Perubahan ✨</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

// Auto-close sidebar saat klik link di mobile
document.querySelectorAll('.submenu-link, .nav-link-custom:not(.btn-toggle-submenu)').forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth < 992) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('open')) toggleSidebar();
        }
    });
});

// ===== SUBMENU TOGGLE =====
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

// ===== MODAL FUNCTIONS =====
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

// ===== CONFIRM DIALOGS =====
function confirmLandingPage(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Kembali ke Beranda? ✦',
        text: 'Anda akan dialihkan kembali ke halaman utama publik SpotLight Studio.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Kembali',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = '../../index.php';
    });
}
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Keluar Sistem? ❌',
        text: 'Apakah Anda yakin ingin keluar dari sistem SpotLight Studio?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d83f67',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Keluar',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = '../../logout.php';
    });
}

// ===== FILE INPUT PREVIEW =====
const inputFotoModal = document.getElementById('inputFotoModal');
if (inputFotoModal) {
    inputFotoModal.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('profile-preview-modal').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}

// ===== INPUT VALIDATION =====
const inputNamaModal = document.getElementById('inputNamaModal');
if (inputNamaModal) {
    inputNamaModal.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z ]/g, '');
    });
}
const inputUsernameModal = document.getElementById('inputUsernameModal');
if (inputUsernameModal) {
    inputUsernameModal.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
    });
}

// ===== PASSWORD TOGGLE =====
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
setupPasswordToggle('btnToggleLama', 'pass_lama_modal');
setupPasswordToggle('btnToggleBaru', 'pass_baru_modal');
setupPasswordToggle('btnToggleKonf', 'pass_konf_modal');

// ===== PHONE INPUT =====
const inputHPModal = document.getElementById('inputHPModal'), prefix = '+62 ';
function moveCursorToEnd() {
    if (inputHPModal && inputHPModal.selectionStart < prefix.length) {
        if (inputHPModal.setSelectionRange) inputHPModal.setSelectionRange(prefix.length, prefix.length);
    }
}
if (inputHPModal) {
    inputHPModal.addEventListener('mousedown', () => setTimeout(moveCursorToEnd, 1));
    inputHPModal.addEventListener('focus', moveCursorToEnd);
    inputHPModal.addEventListener('keyup', moveCursorToEnd);
    inputHPModal.addEventListener('keydown', function(e) {
        if (this.selectionStart <= prefix.length && (e.keyCode === 8 || e.keyCode === 46)) { e.preventDefault(); }
    });
    inputHPModal.addEventListener('input', function() {
        if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
        let digits = (this.value.split(prefix)[1] || '').replace(/[^0-9]/g, '');
        if (digits.length > 13) digits = digits.slice(0, 13);
        this.value = prefix + digits;
    });
}

// ===== LIVE CLOCK =====
function updateLiveClock() {
    const now = new Date();
    const days = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
    const months = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
    const clockEl = document.getElementById('live-clock');
    if (clockEl) {
        clockEl.innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} - ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
    }
}
setInterval(updateLiveClock, 1000);
updateLiveClock();
</script>

<!-- CHARTS -->
<script>
// 1. Chart Pendapatan Bulanan
const ctxPendapatan = document.getElementById('chartPendapatan');
if (ctxPendapatan) {
    const ctx = ctxPendapatan.getContext('2d');
    const gradientPink = ctx.createLinearGradient(0, 0, 0, 300);
    gradientPink.addColorStop(0, 'rgba(216, 63, 103, 0.45)');
    gradientPink.addColorStop(1, 'rgba(255, 245, 246, 0.05)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: <?= json_encode($pendapatan_bulan_data) ?>,
                borderColor: '#d83f67',
                borderWidth: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#d83f67',
                pointBorderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 10,
                fill: true,
                backgroundColor: gradientPink,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 30, 36, 0.9)',
                    titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 236, 239, 0.4)' },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11 },
                        callback: function(value) {
                            if (value >= 1000000) return 'Rp' + (value/1000000).toFixed(0) + 'jt';
                            if (value >= 1000) return 'Rp' + (value/1000).toFixed(0) + 'rb';
                            return 'Rp' + value;
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } }
                }
            }
        }
    });
}

// 2. Chart Status Booking
const ctxStatusBooking = document.getElementById('chartStatusBooking');
if (ctxStatusBooking) {
    new Chart(ctxStatusBooking.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Menunggu DP', 'DP Terverifikasi', 'Menunggu Pelunasan', 'Lunas', 'Dibatalkan'],
            datasets: [{
                label: 'Jumlah Booking',
                data: [
                    <?= $d_status_booking['menunggu_dp'] ?? 0 ?>,
                    <?= $d_status_booking['dp_verified'] ?? 0 ?>,
                    <?= $d_status_booking['tunggu_pelunasan'] ?? 0 ?>,
                    <?= $d_status_booking['lunas'] ?? 0 ?>,
                    <?= $d_status_booking['dibatalkan'] ?? 0 ?>
                ],
                backgroundColor: ['#fbbf24', '#3b82f6', '#6366f1', '#10b981', '#ef4444'],
                borderRadius: 10,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 236, 239, 0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans' } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } } }
            }
        }
    });
}

// 3. Chart Top 5 Paket Favorit
const ctxTopPaket = document.getElementById('chartTopPaket');
if (ctxTopPaket) {
    new Chart(ctxTopPaket.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($top_paket_labels) ?>,
            datasets: [{
                label: 'Jumlah Order',
                data: <?= json_encode($top_paket_data) ?>,
                backgroundColor: ['#d83f67', '#ff6694', '#f472b6', '#fda4af', '#fecdd3'],
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255, 236, 239, 0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans' } } },
                y: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' } } }
            }
        }
    });
}

// 4. Chart Pendapatan per Kategori
const ctxPendapatanKategori = document.getElementById('chartPendapatanKategori');
if (ctxPendapatanKategori) {
    new Chart(ctxPendapatanKategori.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['DP', 'Pelunasan', 'Barang Cetak'],
            datasets: [{
                data: [<?= $pendapatan_dp ?>, <?= $pendapatan_lunas ?>, <?= $pendapatan_barang ?>],
                backgroundColor: ['#3b82f6', '#10b981', '#d83f67'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', weight: '600' }, padding: 20 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed) + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

// 5. Chart Tren Pelanggan Baru
const ctxTrenPelanggan = document.getElementById('chartTrenPelanggan');
if (ctxTrenPelanggan) {
    const ctx = ctxTrenPelanggan.getContext('2d');
    const gradientBlue = ctx.createLinearGradient(0, 0, 0, 300);
    gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.02)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
            datasets: [{
                label: 'Pelanggan Baru',
                data: <?= json_encode($tren_pelanggan_data) ?>,
                borderColor: '#3b82f6',
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 8,
                fill: true,
                backgroundColor: gradientBlue,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 236, 239, 0.4)' }, ticks: { font: { family: 'Plus Jakarta Sans' }, stepSize: 1 } },
                x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } }
            }
        }
    });
}

// 6. Chart Sebaran Staf
const ctxKaryawan = document.getElementById('chartKaryawan');
if (ctxKaryawan) {
    new Chart(ctxKaryawan.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Admin', 'Fotografer', 'Owner'],
            datasets: [{
                data: [<?= $count_admin ?>, <?= $count_foto ?>, <?= $count_owner ?>],
                backgroundColor: ['#d83f67', '#ff6694', '#1e1e24'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', weight: '600' }, padding: 20 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' orang (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}
</script>

<!-- SweetAlert Notifikasi -->
<?php if(isset($success_profile) && $success_profile === true): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Profil Diperbarui! 🎉',
        text: 'Informasi profil Anda berhasil disinkronkan ke seluruh sistem SpotLight.',
        confirmButtonColor: '#d83f67',
        confirmButtonText: 'Selesai'
    });
</script>
<?php endif; ?>

<?php if(isset($error_profile) && $error_profile !== ""): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Pembaruan Gagal! ❌',
        text: '<?= $error_profile ?>',
        confirmButtonColor: '#d83f67',
        confirmButtonText: 'Periksa Kembali'
    }).then(() => {
        var modalGanti = new bootstrap.Modal(document.getElementById('modalGantiProfil'));
        modalGanti.show();
    });
</script>
<?php endif; ?>
</body>
</html>