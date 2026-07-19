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
// CEK TCPDF - Jika ada, generate PDF server-side
// =====================================================
$tcpdf_path = '../../tcpdf/tcpdf.php';
$use_tcpdf = file_exists($tcpdf_path);

if ($use_tcpdf) {
    require_once($tcpdf_path);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SpotLight Studio');
    $pdf->SetAuthor('Owner');
    $pdf->SetTitle('Laporan Paket Terfavorit');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFooterMargin(10);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Logo & Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(216, 63, 103);
    $pdf->Cell(0, 8, 'SpotLight.', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(30, 30, 36);
    $pdf->Cell(0, 5, 'Photo Studio Laporan', 0, 1, 'L');

    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(113, 128, 150);
    $pdf->Cell(0, 10, 'LAPORAN PAKET TERFAVORIT', 0, 1, 'R');
    $pdf->Cell(0, 6, 'BEST SELLER', 0, 1, 'R');

    $pdf->Ln(3);

    // Meta box
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Cell(95, 6, 'Periode Laporan: ' . $label_periode, 1, 0, 'L', true);
    $pdf->Cell(0, 6, 'Tanggal Cetak: ' . date('d M Y H:i') . ' WIB', 1, 1, 'R', true);
    $pdf->Cell(95, 6, 'Dicetak Oleh: Owner System', 1, 0, 'L', true);
    $pdf->Cell(0, 6, 'Total Paket Aktif: ' . ($summary['Total_Paket_Aktif'] ?? 0) . ' Paket', 1, 1, 'R', true);

    $pdf->Ln(4);

    // Summary cards (4 kolom)
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $w = 45;
    $h = 18;

    $sums = [
        ['Paket Aktif', $summary['Total_Paket_Aktif'] ?? 0],
        ['Total Booking', $summary['Total_Booking'] ?? 0],
        ['Best Seller', $summary['Best_Seller'] ?? '-'],
        ['Rating Tertinggi', isset($summary['Rating_Nilai']) && $summary['Rating_Nilai'] > 0 ? number_format((float)$summary['Rating_Nilai'], 1) : '-']
    ];

    foreach ($sums as $i => $s) {
        $x = 15 + ($i * $w);
        $pdf->SetXY($x, $pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(216, 63, 103);
        $pdf->Cell($w, 7, (string)$s[1], 'LRT', 2, 'C', true);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(113, 128, 150);
        $pdf->Cell($w, 5, strtoupper($s[0]), 'LRB', 0, 'C', true);
    }
    $pdf->Ln(22);

    // Table Header
    $pdf->SetFillColor(216, 63, 103);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(18, 8, 'Rank', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Nama Paket', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Durasi', 1, 0, 'C', true);
    $pdf->Cell(22, 8, 'Kapasitas', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Harga', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Booking', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Kontribusi', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Rating', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Batal', 1, 1, 'C', true);

    // Table Body
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(51, 51, 51);

    if (count($data_paket) > 0) {
        $rank = 1;
        foreach ($data_paket as $row) {
            $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
            $rating_val = isset($row['Rata_Rata_Rating']) && $row['Rata_Rata_Rating'] > 0 ? number_format((float)$row['Rata_Rata_Rating'], 1) : '-';
            $rank_text = ($rank <= 3) ? (($rank == 1) ? '#1' : (($rank == 2) ? '#2' : '#3')) : '#' . $rank;

            $fill = ($rank % 2 == 0);
            $pdf->SetFillColor(253, 250, 251);

            $pdf->Cell(18, 7, $rank_text, 1, 0, 'C', $fill);
            $pdf->Cell(35, 7, htmlspecialchars($row['Nama_Paket']), 1, 0, 'L', $fill);
            $pdf->Cell(20, 7, $row['Durasi_Waktu'] . ' Menit', 1, 0, 'C', $fill);
            $pdf->Cell(22, 7, 'Max ' . $row['Kapasitas_Orang'] . ' Org', 1, 0, 'C', $fill);
            $pdf->Cell(25, 7, 'Rp ' . number_format((float)$row['Harga_Paket'], 0, ',', '.'), 1, 0, 'R', $fill);
            $pdf->Cell(20, 7, $row['Jumlah_Booking'] . ' Sesi', 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, number_format($kontribusi, 1) . '%', 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, ($rating_val != '-' ? '★ ' . $rating_val : '-'), 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, $row['Jumlah_Batal'] . ' Sesi', 1, 1, 'C', $fill);

            $rank++;
        }
    } else {
        $pdf->Cell(0, 10, 'Tidak ada data untuk periode ini.', 1, 1, 'C');
    }

    // Tanda tangan
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(113, 128, 150);
    $pdf->Cell(0, 5, 'Bekasi, ' . date('d F Y'), 0, 1, 'R');
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(30, 30, 36);
    $pdf->Cell(0, 5, htmlspecialchars($nama_owner), 0, 1, 'R');
    $pdf->SetDrawColor(30, 30, 36);
    $pdf->Line(150, $pdf->GetY() - 1, 195, $pdf->GetY() - 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Owner SpotLight Studio', 0, 1, 'R');

    $filename = "LaporanPaketTerfavorit_" . date('dmY') . ".pdf";
    $pdf->Output($filename, 'D');
    exit;
}

// =====================================================
// FALLBACK: HTML + html2pdf.js (jika TCPDF tidak tersedia)
// =====================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Laporan Paket Terfavorit</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
        <button onclick="downloadPDF()" class="btn-action btn-download" style="background:#1e1e24;">Download PDF (html2pdf)</button>
    </div>
</div>

<div id="pdf-content">
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
            $rating_val = isset($row['Rata_Rata_Rating']) && $row['Rata_Rata_Rating'] > 0 ? number_format((float)$row['Rata_Rata_Rating'], 1) : '-';
            $rank_class = 'rank-default';
            $rank_text = '#' . $rank;
            if ($rank == 1) { $rank_class = 'rank-gold'; $rank_text = '#1'; }
            elseif ($rank == 2) { $rank_class = 'rank-silver'; $rank_text = '#2'; }
            elseif ($rank == 3) { $rank_class = 'rank-bronze'; $rank_text = '#3'; }
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
</div>

<script>
function downloadPDF() {
    const element = document.getElementById('pdf-content');
    const opt = {
        margin: [15, 15, 15, 15],
        filename: 'LaporanPaketTerfavorit_<?= date('dmY') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>