<?php
session_start();

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_ORDER_MENUNGGU_DP', 0);
define('STATUS_ORDER_DP_TERVERIFIKASI', 1);
define('STATUS_ORDER_LUNAS', 2);
define('STATUS_ORDER_SELESAI', 3);
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

// --- FILTER TANGGAL ---
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// =====================================================
// QUERY DATA PENDAPATAN
// =====================================================
$sql_detail = "SELECT p.ID_Pembayaran, p.ID_Order, p.Jumlah_Bayar, p.Metode_Pembayaran, p.Tanggal_Upload, pl.Nama_Pelanggan, pl.No_Hp, pk.Nama_Paket, r.Nama_Ruangan, t.Nama_Tema, o.Total_Paket, o.Total_Barang_Cetak, o.Tanggal_Booking, o.Status_Order, k.Nama_Karyawan as Nama_Verifikator FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ? ORDER BY p.Tanggal_Upload DESC";

$params = [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, STATUS_ORDER_SELESAI, $tgl_mulai, $tgl_selesai];
$query = sqlsrv_query($conn, $sql_detail, $params);

if ($query === false) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $errorMsg = "Query Error:
";
    foreach ($errors as $error) {
        $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . " | Code: " . $error['code'] . " | Message: " . $error['message'] . "
";
    }
    die(nl2br($errorMsg));
}

// Hitung total
$q_total = sqlsrv_query($conn, "SELECT SUM(p.Jumlah_Bayar) AS total, COUNT(*) AS jumlah FROM Pembayaran p INNER JOIN [Order] o ON p.ID_Order = o.ID_Order WHERE p.Tipe_Pembayaran = 'Pelunasan' AND p.Status_Pembayaran = ? AND p.Status = 1 AND o.Status = 1 AND o.Status_Order IN (?, ?) AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?", $params);
if ($q_total === false) {
    die('Error menghitung total pendapatan.');
}
$d_total = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
$total_pendapatan = $d_total['total'] ?? 0;
$jumlah_order = $d_total['jumlah'] ?? 0;

// =====================================================
// GENERATE PDF DENGAN TCPDF
// =====================================================
$tcpdf_paths = [
    '../../assets/vendor/tcpdf/tcpdf.php',
    '../../assets/tcpdf/tcpdf.php',
    '../../tcpdf/tcpdf.php',
];

$tcpdf_found = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $tcpdf_found = true;
        break;
    }
}

// Jika TCPDF tidak ditemukan, generate HTML sederhana yang bisa di-print ke PDF
if (!$tcpdf_found) {
    header("Content-Type: text/html; charset=UTF-8");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Pendapatan - SpotLight Studio</title>
        <style>
            @page { size: A4 landscape; margin: 15mm; }
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e1e24; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #d83f67; padding-bottom: 15px; }
            .header h1 { color: #d83f67; font-size: 22px; margin: 0 0 5px 0; }
            .header p { color: #718096; margin: 0; font-size: 10px; }
            .info { margin-bottom: 15px; font-size: 10px; }
            .info strong { color: #d83f67; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
            th { background: linear-gradient(135deg, #d83f67, #c73165); color: white; padding: 10px 8px; text-align: left; font-weight: 700; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; }
            td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) { background-color: #fff8f0; }
            tr:hover { background-color: #ffedd5; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .total-row { background: #1e1e24 !important; color: white; font-weight: 700; }
            .total-row td { border: none; padding: 12px 8px; }
            .footer { margin-top: 30px; display: flex; justify-content: space-between; font-size: 10px; }
            .signature { text-align: center; width: 200px; }
            .signature-line { border-top: 1px solid #1e1e24; margin-top: 50px; padding-top: 5px; font-weight: 700; }
            .print-btn { position: fixed; top: 20px; right: 20px; background: #d83f67; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 700; }
            @media print { .print-btn { display: none; } body { margin: 0; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

        <div class="header">
            <h1>SpotLight Studio Foto</h1>
            <p>Cikarang Pusat, Bekasi, Jawa Barat | Telepon: +62 878-7143-8459</p>
            <p style="margin-top:10px; font-size:14px; color:#1e1e24; font-weight:700;">LAPORAN PENDAPATAN PELUNASAN</p>
            <p>Periode: <strong><?= date('d M Y', strtotime($tgl_mulai)) ?> s/d <?= date('d M Y', strtotime($tgl_selesai)) ?></strong></p>
        </div>

        <div class="info">
            <p><strong>Total Pendapatan:</strong> Rp <?= number_format($total_pendapatan, 0, ',', '.') ?> | 
            <strong>Jumlah Order:</strong> <?= $jumlah_order ?> | 
            <strong>Dicetak:</strong> <?= date('d M Y H:i') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="8%">No. Bayar</th>
                    <th width="8%">No. Order</th>
                    <th width="15%">Pelanggan</th>
                    <th width="12%">Paket</th>
                    <th width="12%">Ruangan</th>
                    <th width="10%">Tema</th>
                    <th width="10%">Metode</th>
                    <th width="12%" class="text-right">Jumlah</th>
                    <th width="12%">Tanggal</th>
                    <th width="10%">Verifikator</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($query && sqlsrv_has_rows($query)):
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $tgl = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format') 
                            ? $row['Tanggal_Upload']->format('d-m-Y H:i') 
                            : date('d-m-Y H:i', strtotime($row['Tanggal_Upload']));
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>#<?= str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td>#<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($row['Nama_Pelanggan']) ?><br><small style="color:#718096;"><?= htmlspecialchars($row['No_Hp']) ?></small></td>
                    <td><?= htmlspecialchars($row['Nama_Paket']) ?></td>
                    <td><?= htmlspecialchars($row['Nama_Ruangan']) ?></td>
                    <td><?= htmlspecialchars($row['Nama_Tema']) ?></td>
                    <td><?= htmlspecialchars($row['Metode_Pembayaran']) ?></td>
                    <td class="text-right">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></td>
                    <td><?= $tgl ?></td>
                    <td><?= htmlspecialchars($row['Nama_Verifikator'] ?? 'System') ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="11" class="text-center" style="padding:30px; color:#718096;">Tidak ada data pada periode ini.</td></tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="8" class="text-right">TOTAL PENDAPATAN:</td>
                    <td class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div>
                <p style="color:#718096; font-size:9px;">Dicetak oleh sistem SpotLight Studio</p>
            </div>
            <div class="signature">
                <p>Bekasi, <?= date('d F Y') ?></p>
                <div class="signature-line">Owner SpotLight Studio</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// =====================================================
// TCPPDF TERDETEKSI - GENERATE PDF LANGSUNG
// =====================================================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('SpotLight Studio');
$pdf->SetAuthor('SpotLight Studio');
$pdf->SetTitle('Laporan Pendapatan - SpotLight Studio');
$pdf->SetSubject('Laporan Pendapatan Pelunasan');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Header
$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(216, 63, 103);
$pdf->Cell(0, 10, 'SpotLight Studio Foto', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(113, 128, 150);
$pdf->Cell(0, 5, 'Cikarang Pusat, Bekasi, Jawa Barat | Telepon: +62 878-7143-8459', 0, 1, 'C');
$pdf->SetDrawColor(216, 63, 103);
$pdf->Line(15, 32, 282, 32);
$pdf->Ln(3);

$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(30, 30, 36);
$pdf->Cell(0, 8, 'LAPORAN PENDAPATAN PELUNASAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Periode: ' . date('d M Y', strtotime($tgl_mulai)) . ' s/d ' . date('d M Y', strtotime($tgl_selesai)), 0, 1, 'C');
$pdf->Ln(5);

// Info Box
$pdf->SetFillColor(255, 245, 246);
$pdf->SetDrawColor(255, 228, 233);
$pdf->RoundedRect(15, 50, 267, 18, 3, '1111', 'DF');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(216, 63, 103);
$pdf->SetXY(20, 54);
$pdf->Cell(80, 6, 'Total Pendapatan: Rp ' . number_format($total_pendapatan, 0, ',', '.'), 0, 0, 'L');
$pdf->SetTextColor(30, 30, 36);
$pdf->Cell(80, 6, 'Jumlah Order: ' . $jumlah_order, 0, 0, 'L');
$pdf->Cell(80, 6, 'Dicetak: ' . date('d M Y H:i'), 0, 1, 'R');
$pdf->Ln(12);

// Tabel Header
$pdf->SetFillColor(216, 63, 103);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetDrawColor(216, 63, 103);

$headers = ['No', 'No. Bayar', 'No. Order', 'Pelanggan', 'Paket', 'Ruangan', 'Tema', 'Metode', 'Jumlah', 'Tanggal', 'Verifikator'];
$widths = [8, 18, 18, 35, 30, 30, 25, 25, 30, 30, 25];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Tabel Data
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(30, 30, 36);
$fill = false;

if ($query && sqlsrv_has_rows($query)):
    $no = 1;
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
        $tgl = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format') 
            ? $row['Tanggal_Upload']->format('d-m-Y') 
            : date('d-m-Y', strtotime($row['Tanggal_Upload']));

        if ($fill) {
            $pdf->SetFillColor(255, 248, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->Cell($widths[0], 8, $no++, 1, 0, 'C', true);
        $pdf->Cell($widths[1], 8, '#' . str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT), 1, 0, 'C', true);
        $pdf->Cell($widths[2], 8, '#' . str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT), 1, 0, 'C', true);
        $pdf->Cell($widths[3], 8, htmlspecialchars($row['Nama_Pelanggan']), 1, 0, 'L', true);
        $pdf->Cell($widths[4], 8, htmlspecialchars($row['Nama_Paket']), 1, 0, 'L', true);
        $pdf->Cell($widths[5], 8, htmlspecialchars($row['Nama_Ruangan']), 1, 0, 'L', true);
        $pdf->Cell($widths[6], 8, htmlspecialchars($row['Nama_Tema']), 1, 0, 'L', true);
        $pdf->Cell($widths[7], 8, htmlspecialchars($row['Metode_Pembayaran']), 1, 0, 'L', true);
        $pdf->Cell($widths[8], 8, 'Rp ' . number_format((float)$row['Jumlah_Bayar'], 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell($widths[9], 8, $tgl, 1, 0, 'C', true);
        $pdf->Cell($widths[10], 8, htmlspecialchars($row['Nama_Verifikator'] ?? 'System'), 1, 1, 'C', true);

        $fill = !$fill;
    endwhile;
else:
    $pdf->Cell(array_sum($widths), 20, 'Tidak ada data pada periode ini.', 1, 1, 'C', false);
endif;

// Total Row
$pdf->SetFillColor(30, 30, 36);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(array_sum(array_slice($widths, 0, 8)), 10, 'TOTAL PENDAPATAN:', 1, 0, 'R', true);
$pdf->Cell($widths[8], 10, 'Rp ' . number_format($total_pendapatan, 0, ',', '.'), 1, 0, 'R', true);
$pdf->Cell($widths[9] + $widths[10], 10, '', 1, 1, 'C', true);

// Footer
$pdf->Ln(15);
$pdf->SetTextColor(30, 30, 36);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Bekasi, ' . date('d F Y'), 0, 1, 'R');
$pdf->Cell(0, 5, 'Owner SpotLight Studio', 0, 1, 'R');
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, '_________________________', 0, 1, 'R');

$filename = 'Laporan_Pendapatan_' . $tgl_mulai . '_to_' . $tgl_selesai . '.pdf';
$pdf->Output($filename, 'D');
exit();