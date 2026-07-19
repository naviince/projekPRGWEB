<?php
session_start();

// Set timezone ke Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

include '../../koneksi.php';
if (!isset($conn) || $conn === false) {
    die('Koneksi database gagal.');
}

$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terjual_desc';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$d1 = DateTime::createFromFormat('Y-m-d', $tgl_mulai);
$d2 = DateTime::createFromFormat('Y-m-d', $tgl_selesai);
if (!$d1 || $d1->format('Y-m-d') !== $tgl_mulai) $tgl_mulai = date('Y-m-01');
if (!$d2 || $d2->format('Y-m-d') !== $tgl_selesai) $tgl_selesai = date('Y-m-d');
if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
    $tmp = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $tmp;
}

$periode_label = date('d M Y', strtotime($tgl_mulai)) . ' – ' . date('d M Y', strtotime($tgl_selesai));

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_owner = 'Pemilik';
$q_owner = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
if ($q_owner && ($r_owner = sqlsrv_fetch_array($q_owner, SQLSRV_FETCH_ASSOC))) {
    $nama_owner = $r_owner['Nama_Karyawan'];
}

$summary = [];
$q_summary = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangSummary ?, ?", array($tgl_mulai, $tgl_selesai));
if ($q_summary && ($row = sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC))) {
    $summary = $row;
}

$rows = [];
$q_detail = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangDetail ?, ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search ?: null, $status_filter ?: null, $sort, 0, 1000000));
if ($q_detail) {
    while ($row = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
}

$filename = 'LaporanStokBarang_' . date('dmY') . '.xls';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #000; padding: 8px; font-family: Arial, sans-serif; font-size: 11pt; }
th { background-color: #f8fafc; font-weight: bold; }
.kop { text-align: center; margin-bottom: 20px; }
.kop h2 { color: #d83f67; margin: 0; font-size: 16pt; }
.kop p { margin: 4px 0; font-size: 10pt; color: #555; }
.summary { margin-bottom: 16px; }
.summary td { border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; background: #fff5f6; }
.ttd { margin-top: 40px; text-align: right; }
.ttd-box { display: inline-block; text-align: center; width: 200px; }
.ttd-box .jabatan { font-weight: bold; margin-top: 60px; border-top: 1px solid #000; padding-top: 4px; }
.ttd-box .nama { color: #d83f67; font-weight: bold; }
</style>
</head>
<body>

<div class="kop">
<h2>SpotLight Studio</h2>
<p><strong>Laporan Stok Barang Cetak</strong></p>
<p>Periode: <?= $periode_label ?></p>
</div>

<table class="summary">
<tr>
<td>Jenis Barang: <?= $summary['Total_Jenis_Barang'] ?? 0 ?></td>
<td>Unit Terjual: <?= number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.') ?></td>
<td>Nilai Aset: Rp <?= number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.') ?></td>
<td>Omzet: Rp <?= number_format((float)($summary['Total_Omzet_Terjual'] ?? 0), 0, ',', '.') ?></td>
</tr>
</table>

<table>
<thead>
<tr>
<th>No</th>
<th>ID Barang</th>
<th>Nama Barang</th>
<th>Harga Jual</th>
<th>Stok Saat Ini</th>
<th>Stok Minimum</th>
<th>Unit Terjual</th>
<th>Status Persediaan</th>
<th>Nilai Persediaan</th>
<th>Omzet Terjual</th>
</tr>
</thead>
<tbody>
<?php $no = 1; foreach ($rows as $row): 
    $stok = (int)$row['Stok_Barang'];
    $min = (int)$row['Stok_Minimum'];
    $nilai = $stok * (float)$row['Harga_Barang'];
    if ($stok === 0) $status = 'Habis';
    elseif ($stok <= $min) $status = 'Stok Menipis';
    else $status = 'Stok Aman';
?>
<tr>
<td><?= $no++ ?></td>
<td>#BRG-<?= str_pad((int)$row['ID_Barang'], 3, '0', STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($row['Nama_Barang']) ?></td>
<td>Rp <?= number_format((float)$row['Harga_Barang'], 0, ',', '.') ?></td>
<td><?= number_format($stok, 0, ',', '.') ?> Unit</td>
<td><?= number_format($min, 0, ',', '.') ?> Unit</td>
<td><?= number_format((int)$row['Total_Terjual'], 0, ',', '.') ?> Unit</td>
<td><?= $status ?></td>
<td>Rp <?= number_format($nilai, 0, ',', '.') ?></td>
<td>Rp <?= number_format((float)$row['Total_Pendapatan'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
<?php if (count($rows) === 0): ?>
<tr><td colspan="10" style="text-align:center;">Tidak ada data.</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="ttd">
<div class="ttd-box">
<p> tanggal <?= date('d M Y') ?><br>Approval</p>
<div class="jabatan">Owner</div>
<div class="nama"><?= htmlspecialchars($nama_owner) ?></div>
</div>
</div>

</body>
</html>