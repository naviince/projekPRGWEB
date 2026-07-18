<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    die("Akses ditolak.");
}

include '../../koneksi.php';

// =====================================================
// PARAMETER & VALIDASI
// =====================================================
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'bulan';
if (!in_array($mode, ['bulan', 'tahun', 'custom'])) $mode = 'bulan';

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulan = max(1, min(12, $bulan));
$tahun = max(2020, min(2030, $tahun));

if ($mode == 'bulan') {
    $tgl_mulai = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    $tgl_selesai = date('Y-m-t', strtotime("$tahun-$bulan-01"));
    $label_periode = date('F Y', strtotime("$tahun-$bulan-01"));
} elseif ($mode == 'tahun') {
    $tgl_mulai = "$tahun-01-01";
    $tgl_selesai = "$tahun-12-31";
    $label_periode = "Tahun $tahun";
} else {
    $tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
    $tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_mulai)) $tgl_mulai = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_selesai)) $tgl_selesai = date('Y-m-d');
    if ($tgl_mulai > $tgl_selesai) { $tmp = $tgl_mulai; $tgl_mulai = $tgl_selesai; $tgl_selesai = $tmp; }
    $label_periode = date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai));
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!preg_match('/^[a-zA-Z0-9\s\-\+\#\.\@]*$/', $search)) { $search = ''; }

$allowed_sort = ['booking_desc','booking_asc','nama_asc','nama_desc','harga_desc','harga_asc','rating_desc','rating_asc','batal_desc','batal_asc'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'booking_desc';

// =====================================================
// AMBIL DATA PROFIL OWNER
// =====================================================
$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_owner = 'Pemilik';
if ($id_owner) {
    $q_profile = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
    if ($q_profile !== false) {
        $d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
        if ($d_profile) {
            $d_profile = array_change_key_case($d_profile, CASE_LOWER);
            $nama_owner = $d_profile['nama_karyawan'] ?? 'Pemilik';
        }
    }
}

// =====================================================
// AMBIL DATA
// =====================================================
$stmt_summary = sqlsrv_query($conn, "EXEC sp_LaporanPaketTerfavoritSummary ?, ?", array($tgl_mulai, $tgl_selesai));
$summary = ($stmt_summary) ? sqlsrv_fetch_array($stmt_summary, SQLSRV_FETCH_ASSOC) : null;

$stmt_detail = sqlsrv_query($conn, "EXEC sp_LaporanPaketTerfavoritDetail ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search, $sort, 0, 10000));

$data_paket = [];
$total_seluruh_booking = $summary['Total_Booking'] ?? 0;
if ($stmt_detail) {
    while ($row = sqlsrv_fetch_array($stmt_detail, SQLSRV_FETCH_ASSOC)) {
        $data_paket[] = $row;
    }
}

// =====================================================
// HEADER DOWNLOAD
// =====================================================
$filename = "LaporanPaketTerfavorit_" . date('dmY') . ".xls";
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
?>
<div align="center">
    <h2>LAPORAN PAKET TERFAVORIT</h2>
    <h3>SPOTLIGHT PHOTO STUDIO</h3>
    <p>Periode: <?= $label_periode ?></p>
    <p>Tanggal Cetak: <?= date('d M Y H:i') ?> WIB</p>
</div>

<table border="1" cellpadding="5" cellspacing="0" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; border-collapse: collapse; width:100%;">
    <thead>
        <tr style="background-color: #d83f67; color: white; font-weight: bold;">
            <th>Rank</th>
            <th>ID Paket</th>
            <th>Nama Paket</th>
            <th>Durasi Sesi</th>
            <th>Kapasitas Sesi</th>
            <th>Harga Paket (Rp)</th>
            <th>Jumlah Booking</th>
            <th>Kontribusi</th>
            <th>Rata-rata Rating</th>
            <th>Jumlah Batal</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (count($data_paket) > 0):
        $rank = 1;
        foreach ($data_paket as $row):
            $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
            $rating_val = isset($row['Rata_Rata_Rating']) && $row['Rata_Rata_Rating'] !== null ? number_format((float)$row['Rata_Rata_Rating'], 1) : 'Belum Ada';
            $rank_badge = '';
            if ($rank == 1) $rank_badge = '🥇 ';
            elseif ($rank == 2) $rank_badge = '🥈 ';
            elseif ($rank == 3) $rank_badge = '🥉 ';
        ?>
        <tr>
            <td align="center"><?= $rank_badge ?>#<?= $rank++ ?></td>
            <td align="center">PKT-<?= str_pad($row['ID_Paket'], 3, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($row['Nama_Paket']) ?></td>
            <td align="center"><?= $row['Durasi_Waktu'] ?> Menit</td>
            <td align="center"><?= $row['Kapasitas_Orang'] ?> Orang</td>
            <td align="right"><?= number_format((float)$row['Harga_Paket'], 0, ',', '.') ?></td>
            <td align="center"><?= $row['Jumlah_Booking'] ?> Sesi</td>
            <td align="center"><?= number_format($kontribusi, 1) ?>%</td>
            <td align="center"><?= $rating_val ?></td>
            <td align="center" style="color: red;"><?= $row['Jumlah_Batal'] ?> Sesi</td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<table style="width: 100%; border: none; margin-top: 30px;">
    <tr>
        <td style="width: 70%; border: none;"></td>
        <td align="center" style="width: 30%; border: none; font-family: sans-serif; font-size: 12px;">
            <p>Bekasi, <?= date('d F Y') ?></p>
            <p style="margin-bottom: 50px;">Owner SpotLight Studio</p>
            <p style="text-decoration: underline; font-weight: bold;"><?= htmlspecialchars($nama_owner) ?></p>
        </td>
    </tr>
</table>