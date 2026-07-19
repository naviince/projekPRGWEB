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
$filename = "LaporanPaketTerfavorit_" . date('dmY') . ".pdf";
header("Content-type: application/pdf");
header("Content-Disposition: inline; filename=" . $filename);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Laporan Paket Terfavorit</title>
<style>
    @page { size: A4 portrait; margin: 15mm; }
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #333; line-height: 1.4; margin: 0; padding: 20px; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .header-logo { font-size: 22px; font-weight: 800; color: #d83f67; letter-spacing: -1px; }
    .header-logo span { color: #1e1e24; font-size: 13px; font-weight: 500; }
    .header-title { text-align: right; font-size: 13px; font-weight: bold; text-transform: uppercase; color: #718096; }
    .report-meta { margin-bottom: 18px; font-size: 10px; background-color: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
    .summary-item { background: #f8fafc; border-radius: 10px; padding: 12px; text-align: center; border: 1px solid #e2e8f0; }
    .summary-item .val { font-size: 1.3rem; font-weight: 800; color: #d83f67; }
    .summary-item .lbl { font-size: 0.65rem; color: #718096; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 9.5px; }
    .data-table th { background-color: #d83f67; color: white; padding: 8px 10px; font-weight: bold; text-transform: uppercase; border: 1px solid #d83f67; text-align: left; }
    .data-table td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; }
    .data-table tr:nth-child(even) { background-color: #fdfafb; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }
    .rank-gold { background: #fffbeb; color: #92400e; font-weight: 800; padding: 2px 6px; border-radius: 6px; }
    .rank-silver { background: #f8fafc; color: #1e293b; font-weight: 800; padding: 2px 6px; border-radius: 6px; }
    .rank-bronze { background: #fff7ed; color: #9a3412; font-weight: 800; padding: 2px 6px; border-radius: 6px; }
    .rank-default { color: #d83f67; font-weight: 800; }
    .badge-batal { color: #dc2626; font-weight: 700; }
    .ttd-wrapper { margin-top: 40px; display: flex; justify-content: flex-end; }
    .ttd-box { text-align: center; width: 180px; }
    .ttd-box .tgl { font-size: 10px; color: #718096; margin-bottom: 50px; }
    .ttd-box .jabatan { font-size: 10px; color: #718096; margin-bottom: 4px; }
    .ttd-box .nama { font-weight: 800; border-top: 2px solid #1e1e24; padding-top: 4px; font-size: 11px; }
    .print-control-bar {
        background: #fff; border: 1px solid #e2e8f0; padding: 12px 18px; border-radius: 12px;
        margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .btn-action { padding: 8px 16px; font-weight: 700; font-size: 11px; border-radius: 8px; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
    .btn-download { background-color: #d83f67; color: white; }
    .btn-back { background-color: #edf2f7; color: #4a5568; }
    @media print {
        body { margin: 10px; }
        .print-control-bar { display: none !important; }
    }
</style>
</head>
<body>

<div class="print-control-bar">
    <div style="font-weight: 600; font-size: 12px; color: #4a5568;">
        <strong style="color: #d83f67;">Petunjuk:</strong> Klik tombol kanan untuk menyimpan dokumen sebagai file PDF atau mencetaknya.
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.close();" class="btn-action btn-back">Tutup Halaman</button>
        <button onclick="window.print();" class="btn-action btn-download">Cetak / Simpan PDF</button>
    </div>
</div>

<table class="header-table">
    <tr>
        <td class="header-logo">
            SpotLight.<br><span>Photo Studio Laporan</span>
        </td>
        <td class="header-title">
            Laporan Paket Terfavorit<br>Best Seller
        </td>
    </tr>
</table>

<div class="report-meta">
    <table style="width:100%; border:none;">
        <tr>
            <td style="width:50%;"><strong>Periode Laporan:</strong> <?= $label_periode ?></td>
            <td style="width:50%; text-align:right;"><strong>Tanggal Cetak:</strong> <?= date('d M Y H:i') ?> WIB</td>
        </tr>
        <tr>
            <td><strong>Dicetak Oleh:</strong> Owner System</td>
            <td style="text-align:right;"><strong>Total Paket Aktif:</strong> <?= $summary['Total_Paket_Aktif'] ?? 0 ?> Paket</td>
        </tr>
    </table>
</div>

<div class="summary-grid">
    <div class="summary-item">
        <div class="val"><?= $summary['Total_Paket_Aktif'] ?? 0 ?></div>
        <div class="lbl">Paket Aktif</div>
    </div>
    <div class="summary-item">
        <div class="val"><?= $summary['Total_Booking'] ?? 0 ?></div>
        <div class="lbl">Total Booking</div>
    </div>
    <div class="summary-item">
        <div class="val"><?= htmlspecialchars($summary['Best_Seller'] ?? '-') ?></div>
        <div class="lbl">Best Seller</div>
    </div>
    <div class="summary-item">
        <div class="val"><?= isset($summary['Rating_Nilai']) && $summary['Rating_Nilai'] > 0 ? number_format((float)$summary['Rating_Nilai'], 1) : '-' ?></div>
        <div class="lbl">Rating Tertinggi</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 50px;">Rank</th>
            <th>ID Paket</th>
            <th>Nama Paket Foto</th>
            <th class="text-center">Durasi</th>
            <th class="text-center">Kapasitas</th>
            <th class="text-right">Harga Paket</th>
            <th class="text-center">Jumlah Booking</th>
            <th class="text-center">Kontribusi</th>
            <th class="text-center">Rating Rata-rata</th>
            <th class="text-center">Jumlah Batal</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (count($data_paket) > 0):
        $rank = 1;
        foreach ($data_paket as $row):
            $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
            $rating_val = isset($row['Rata_Rata_Rating']) && $row['Rata_Rata_Rating'] !== null ? number_format((float)$row['Rata_Rata_Rating'], 1) : '-';
            $rank_class = 'rank-default';
            $rank_text = '#' . $rank;
            if ($rank == 1) { $rank_class = 'rank-gold'; $rank_text = '🥇 #1'; }
            elseif ($rank == 2) { $rank_class = 'rank-silver'; $rank_text = '🥈 #2'; }
            elseif ($rank == 3) { $rank_class = 'rank-bronze'; $rank_text = '🥉 #3'; }
        ?>
        <tr>
            <td class="text-center text-bold <?= $rank_class ?>"><?= $rank_text ?></td>
            <td class="text-center">PKT-<?= str_pad($row['ID_Paket'], 3, '0', STR_PAD_LEFT) ?></td>
            <td class="text-bold"><?= htmlspecialchars($row['Nama_Paket']) ?></td>
            <td class="text-center"><?= $row['Durasi_Waktu'] ?> Menit</td>
            <td class="text-center">Max <?= $row['Kapasitas_Orang'] ?> Orang</td>
            <td class="text-right">Rp <?= number_format((float)$row['Harga_Paket'], 0, ',', '.') ?></td>
            <td class="text-center text-bold"><?= $row['Jumlah_Booking'] ?> Sesi</td>
            <td class="text-center text-bold"><?= number_format($kontribusi, 1) ?>%</td>
            <td class="text-center text-bold">
                <?php if ($rating_val != '-'): ?>★ <?= $rating_val ?><?php else: ?>-<?php endif; ?>
            </td>
            <td class="text-center badge-batal"><?= $row['Jumlah_Batal'] ?> Sesi</td>
        </tr>
        <?php $rank++; endforeach; else: ?>
        <tr>
            <td colspan="10" class="text-center">Tidak ada data untuk periode ini.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="ttd-wrapper">
    <div class="ttd-box">
        <div class="tgl">Bekasi, <?= date('d F Y') ?></div>
        <div class="jabatan">Owner SpotLight Studio</div>
        <div class="nama"><?= htmlspecialchars($nama_owner) ?></div>
    </div>
</div>

</body>
</html>