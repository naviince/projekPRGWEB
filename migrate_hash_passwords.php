<?php
/**
 * =====================================================
 * MIGRASI SEKALI-JALAN: HASH SEMUA PASSWORD PLAINTEXT
 * =====================================================
 * Taruh file ini di root project (sejajar sama koneksi.php), lalu
 * buka lewat browser SEKALI: http://localhost/projekPRGWEB/migrate_hash_passwords.php
 *
 * Yang dilakukan:
 * - Scan semua baris di Pelanggan & Karyawan
 * - Kalau Password_Pelanggan/Password_Karyawan BELUM berbentuk hash
 *   bcrypt (gak diawali "$2y$" / "$2a$" / "$2b$"), hash pakai
 *   password_hash() lalu UPDATE ke database.
 * - Password yang UDAH ke-hash (dari register normal atau dari
 *   auto-migrate login.php) DILEWATI, gak di-hash dua kali.
 *
 * PENTING: HAPUS FILE INI dari server setelah selesai dipakai satu
 * kali. Jangan biarkan menggantung di server produksi -- siapa pun
 * yang tau URL-nya bisa buka halaman ini.
 * =====================================================
 */

session_start();

// --- PROTEKSI: HANYA OWNER YANG SUDAH LOGIN YANG BOLEH JALANKAN INI ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    die('<div style="font-family:Arial;padding:40px;text-align:center;"><h2 style="color:#d83f67;">Akses ditolak.</h2><p>Login sebagai Owner dulu, baru buka halaman ini.</p><a href="login.php">Ke halaman login</a></div>');
}

include 'koneksi.php';

if (!isset($conn) || $conn === false) {
    die('<h2 style="color:red;">Koneksi database gagal!</h2>');
}

function isBcryptHash($str) {
    return is_string($str) && preg_match('/^\$2[aby]\$/', $str) === 1;
}

$log = [];
$total_migrated = 0;
$total_skipped = 0;
$total_error = 0;

// =====================================================
// MIGRASI TABEL Pelanggan
// =====================================================
$q_pelanggan = sqlsrv_query($conn, "SELECT ID_Pelanggan, Nama_Pelanggan, Username_Pelanggan, Password_Pelanggan FROM Pelanggan");
if ($q_pelanggan === false) {
    die('<h2 style="color:red;">Gagal query Pelanggan!</h2><pre>' . print_r(sqlsrv_errors(), true) . '</pre>');
}

while ($row = sqlsrv_fetch_array($q_pelanggan, SQLSRV_FETCH_ASSOC)) {
    $pw = $row['Password_Pelanggan'];
    if (isBcryptHash($pw)) {
        $total_skipped++;
        continue;
    }

    $new_hash = password_hash($pw, PASSWORD_BCRYPT);
    $upd = sqlsrv_query($conn, "UPDATE Pelanggan SET Password_Pelanggan = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Pelanggan = ?", [$new_hash, 'migration_script', $row['ID_Pelanggan']]);

    if ($upd) {
        $total_migrated++;
        $log[] = "✅ Pelanggan #{$row['ID_Pelanggan']} ({$row['Username_Pelanggan']}) -- password di-hash.";
    } else {
        $total_error++;
        $errors = sqlsrv_errors();
        $log[] = "❌ Pelanggan #{$row['ID_Pelanggan']} ({$row['Username_Pelanggan']}) -- GAGAL: " . ($errors[0]['message'] ?? 'unknown error');
    }
}

// =====================================================
// MIGRASI TABEL Karyawan
// =====================================================
$q_karyawan = sqlsrv_query($conn, "SELECT ID_Karyawan, Nama_Karyawan, Username_Karyawan, Password_Karyawan FROM Karyawan");
if ($q_karyawan === false) {
    die('<h2 style="color:red;">Gagal query Karyawan!</h2><pre>' . print_r(sqlsrv_errors(), true) . '</pre>');
}

while ($row = sqlsrv_fetch_array($q_karyawan, SQLSRV_FETCH_ASSOC)) {
    $pw = $row['Password_Karyawan'];
    if (isBcryptHash($pw)) {
        $total_skipped++;
        continue;
    }

    $new_hash = password_hash($pw, PASSWORD_BCRYPT);
    $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Password_Karyawan = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", [$new_hash, 'migration_script', $row['ID_Karyawan']]);

    if ($upd) {
        $total_migrated++;
        $log[] = "✅ Karyawan #{$row['ID_Karyawan']} ({$row['Username_Karyawan']}) -- password di-hash.";
    } else {
        $total_error++;
        $errors = sqlsrv_errors();
        $log[] = "❌ Karyawan #{$row['ID_Karyawan']} ({$row['Username_Karyawan']}) -- GAGAL: " . ($errors[0]['message'] ?? 'unknown error');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Migrasi Password - SpotLight Studio</title>
<style>
body{font-family:'Segoe UI',Arial,sans-serif;background:#f8fafc;padding:40px;color:#1e1e24;}
.box{max-width:800px;margin:0 auto;background:#fff;border-radius:16px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,0.06);}
h1{color:#d83f67;font-size:1.5rem;}
.summary{display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;}
.stat{flex:1;min-width:140px;background:#f8fafc;border-radius:12px;padding:16px;text-align:center;}
.stat b{display:block;font-size:1.6rem;}
.stat.migrated b{color:#059669;}
.stat.skipped b{color:#2563eb;}
.stat.error b{color:#dc2626;}
.log{background:#0f172a;color:#e2e8f0;padding:16px;border-radius:12px;max-height:400px;overflow:auto;font-family:monospace;font-size:0.85rem;line-height:1.6;}
.warning{background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:14px 18px;border-radius:12px;margin-top:20px;font-weight:600;}
</style>
</head>
<body>
<div class="box">
<h1>🔐 Migrasi Password ke Bcrypt Hash</h1>
<div class="summary">
<div class="stat migrated"><b><?= $total_migrated ?></b>Di-hash sekarang</div>
<div class="stat skipped"><b><?= $total_skipped ?></b>Udah hash (dilewati)</div>
<div class="stat error"><b><?= $total_error ?></b>Gagal</div>
</div>
<div class="log">
<?php if (empty($log)): ?>
Semua password sudah dalam bentuk hash. Tidak ada yang perlu dimigrasikan. ✅
<?php else: foreach ($log as $line): ?>
<?= htmlspecialchars($line) ?><br>
<?php endforeach; endif; ?>
</div>
<div class="warning">⚠️ Migrasi selesai. Sekarang HAPUS file <code>migrate_hash_passwords.php</code> ini dari server -- jangan dibiarkan menggantung, siapa pun yang tau URL-nya bisa jalanin ulang.</div>
</div>
</body>
</html>