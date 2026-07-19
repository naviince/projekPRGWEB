<?php
session_start();

// Set timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS -- SAMA PERSIS DENGAN index.php
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_SELESAI_SESI', 2);
define('STATUS_ORDER_LUNAS', 3);
define('STATUS_ORDER_DIBATALKAN', 4);

define('STATUS_PEMBAYARAN_MENUNGGU', 0);
define('STATUS_PEMBAYARAN_VALID', 1);
define('STATUS_PEMBAYARAN_DITOLAK', 2);

// --- PROTEKSI HALAMAN: HANYA OWNER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

// --- INCLUDE KONEKSI ---
if (!file_exists('../../koneksi.php')) {
    die('Error: File koneksi.php tidak ditemukan!');
}
include '../../koneksi.php';

if (!isset($conn) || $conn === false) {
    die('Error: Koneksi database gagal!');
}

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;

// --- AMBIL NAMA OWNER ---
$nama_owner = 'Owner';
$q_owner = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
if ($q_owner && $d = sqlsrv_fetch_array($q_owner, SQLSRV_FETCH_ASSOC)) {
    $nama_owner = $d['Nama_Karyawan'] ?? 'Owner';
}

// --- FILTER TANGGAL ---
$tgl_mulai = isset($_GET['tgl_mulai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
    [$tgl_mulai, $tgl_selesai] = [$tgl_selesai, $tgl_mulai];
}

$periode_str = date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai));

// =====================================================
// AMBIL DATA VIA STORED PROCEDURE
// =====================================================
$q_summary = sqlsrv_query($conn, "{CALL sp_LaporanPendapatanSummary (?, ?)}", array($tgl_mulai, $tgl_selesai));
$summary = $q_summary ? sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC) : null;
$total_pendapatan = $summary['Total_Pendapatan'] ?? 0;
$jumlah_order = $summary['Jumlah_Order'] ?? 0;
$jumlah_pelanggan = $summary['Jumlah_Pelanggan'] ?? 0;

$q_detail = sqlsrv_query($conn, "{CALL sp_LaporanPendapatanDetail (?, ?, 0, 1000000)}", array($tgl_mulai, $tgl_selesai));
$rows = [];
if ($q_detail) {
    while ($r = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
}

$logo_path = '../../assets/img/logo.png';
$logo_exists = file_exists($logo_path);

// =====================================================
// HEADER UNTUK DOWNLOAD EXCEL
// =====================================================
$filename = 'LaporanPendapatan_' . date('dmY') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

// =====================================================
// OUTPUT HTML TABLE UNTUK EXCEL
// =====================================================
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Calibri', 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e1e24; padding: 16px; }

    /* Kop Surat */
    .kop-surat { display: flex; align-items: center; justify-content: center; gap: 14px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 3px solid #d83f67; }
    .kop-surat img { height: 50px; width: auto; flex-shrink: 0; }
    .kop-text h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1e1e24; letter-spacing: -0.5px; }
    .kop-text p { margin: 3px 0 0; font-size: 11px; color: #718096; font-weight: 600; }

    /* Summary */
    .summary-row { display: flex; gap: 10px; margin-bottom: 16px; }
    .summary-box { flex: 1; background: #f8fafc; border-radius: 10px; padding: 12px; text-align: center; }
    .summary-box .label { font-size: 9px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .summary-box .value { font-size: 15px; font-weight: 800; color: #d83f67; }
    .summary-box .value-dark { font-size: 15px; font-weight: 800; color: #1e1e24; }

    /* Tabel */
    table { border-collapse: collapse; width: 100%; margin-top: 8px; }
    th { background-color: #fff; color: #94a3b8; font-weight: 800; padding: 10px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; }
    td { padding: 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; vertical-align: middle; }
    tr:nth-child(even) { background-color: #fff8f0; }
    .td-pink { font-weight: 800; color: #d83f67; }
    .td-dark { font-weight: 700; color: #1e1e24; }
    .td-muted { font-size: 9px; color: #94a3b8; font-weight: 600; }
    .total-row { background-color: #1e1e24 !important; color: white; font-weight: 700; }
    .total-row td { border: none; padding: 10px 8px; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }

    /* Tanda Tangan */
    .signature-wrap { display: flex; justify-content: flex-end; margin-top: 24px; }
    .signature-box { text-align: center; min-width: 160px; }
    .signature-box .date { font-size: 10px; color: #4a5568; font-weight: 600; margin-bottom: 3px; }
    .signature-box .approval { font-size: 10px; color: #4a5568; font-weight: 600; margin-bottom: 32px; }
    .signature-box .role { font-size: 10px; color: #4a5568; font-weight: 700; text-decoration: underline; margin-bottom: 3px; }
    .signature-box .name { font-size: 10px; color: #4a5568; font-weight: 700; }
    .note { font-size: 9px; color: #94a3b8; margin-top: 12px; text-align: center; }
</style>
</head>
<body>

<!-- KOP SURAT -->
<div class="kop-surat">
    <?php if ($logo_exists): ?>
    <img src="../../assets/img/logo.png" alt="SpotLight Studio">
    <?php endif; ?>
    <div class="kop-text">
        <h1>SpotLight Studio</h1>
        <p>Laporan Pendapatan &bull; Periode <?= $periode_str ?></p>
    </div>
</div>

<!-- SUMMARY CARDS -->
<div class="summary-row">
    <div class="summary-box">
        <div class="label">Total Pendapatan</div>
        <div class="value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
    </div>
    <div class="summary-box">
        <div class="label">Order Lunas</div>
        <div class="value-dark"><?= $jumlah_order ?></div>
    </div>
    <div class="summary-box">
        <div class="label">Pelanggan</div>
        <div class="value-dark"><?= $jumlah_pelanggan ?></div>
    </div>
</div>

<!-- TABEL -->
<table>
    <thead>
        <tr>
            <th>No</th>
            <th>No. Pembayaran</th>
            <th>No. Order</th>
            <th>Customer</th>
            <th>Metode Bayar</th>
            <th>Jumlah</th>
            <th>Tanggal Pelunasan</th>
            <th>Status</th>
            <th>Verifikator</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        if (count($rows) > 0):
            foreach ($rows as $row):
                $tgl = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format')
                    ? $row['Tanggal_Upload']->format('d M Y H:i')
                    : date('d M Y H:i', strtotime($row['Tanggal_Upload']));
        ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td class="td-pink">#<?= str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT) ?></td>
            <td class="td-muted">#ORD-<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></td>
            <td>
                <div class="td-dark"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                <div class="td-muted"><?= htmlspecialchars($row['No_Hp']) ?></div>
            </td>
            <td class="td-muted"><?= htmlspecialchars($row['Metode_Pembayaran']) ?></td>
            <td class="td-pink text-right">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></td>
            <td class="td-muted"><?= $tgl ?></td>
            <td class="text-center" style="color:#059669;font-weight:700;font-size:9px;">Lunas</td>
            <td class="td-muted"><?= htmlspecialchars($row['Nama_Verifikator'] ?? 'System') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9" class="text-center" style="padding:24px;color:#718096;">Tidak ada data pendapatan pelunasan pada periode ini.</td></tr>
        <?php endif; ?>
        <?php if (count($rows) > 0): ?>
        <tr class="total-row">
            <td colspan="5" class="text-right">TOTAL PENDAPATAN:</td>
            <td class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
            <td colspan="3"></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- TANDA TANGAN -->
<div class="signature-wrap">
    <div class="signature-box">
        <div class="date">tanggal <?= date('d M Y') ?></div>
        <div class="approval">Approval</div>
        <div class="role">Owner</div>
        <div class="name"><?= htmlspecialchars($nama_owner) ?></div>
    </div>
</div>

<p class="note">Total <?= count($rows) ?> transaksi pelunasan pada periode ini.</p>

</body>
</html>