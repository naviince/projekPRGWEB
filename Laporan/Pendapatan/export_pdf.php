<?php
session_start();

// Set timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// =====================================================
// KONSTANTA STATUS
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
// TCPDF CHECK (path lebih lengkap)
// =====================================================
$tcpdf_paths = [
    '../../assets/vendor/tcpdf/tcpdf.php',
    '../../assets/tcpdf/tcpdf.php',
    '../../tcpdf/tcpdf.php',
    '../../vendor/tecnickcom/tcpdf/tcpdf.php',
    '../../vendor/autoload.php',
    '../../../vendor/autoload.php',
];

$tcpdf_found = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $tcpdf_found = true;
        break;
    }
}

// =====================================================
// FALLBACK HTML + html2pdf.js (auto-download PDF)
// =====================================================
if (!$tcpdf_found) {
    header("Content-Type: text/html; charset=UTF-8");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Pendapatan - SpotLight Studio</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            @page { size: A4; margin: 12mm; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; color: #1e1e24; background: #fff; padding: 16px; }
            #laporan { width: 100%; max-width: 210mm; margin: 0 auto; }
            .kop-surat { display: flex; align-items: center; justify-content: center; gap: 14px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 3px solid #d83f67; }
            .kop-surat img { height: 50px; width: auto; flex-shrink: 0; }
            .kop-text h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1e1e24; letter-spacing: -0.5px; }
            .kop-text p { margin: 3px 0 0; font-size: 11px; color: #718096; font-weight: 600; }
            .summary-row { display: flex; gap: 10px; margin-bottom: 16px; }
            .summary-box { flex: 1; background: #f8fafc; border-radius: 10px; padding: 12px; text-align: center; }
            .summary-box .label { font-size: 9px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
            .summary-box .value { font-size: 15px; font-weight: 800; color: #d83f67; }
            .summary-box .value-dark { font-size: 15px; font-weight: 800; color: #1e1e24; }
            table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 9px; margin-top: 8px; }
            th { background: #fff; padding: 10px 8px; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; text-align: left; border-bottom: 2px solid #f1f5f9; }
            td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
            tr:nth-child(even) { background-color: #fff8f0; }
            .td-pink { font-weight: 800; color: #d83f67; }
            .td-dark { font-weight: 700; color: #1e1e24; }
            .td-muted { font-size: 8px; color: #94a3b8; font-weight: 600; }
            .badge-lunas { background: #ecfdf5; color: #059669; font-size: 8px; font-weight: 700; padding: 3px 8px; border-radius: 50px; display: inline-flex; align-items: center; gap: 3px; }
            .badge-dot { width: 4px; height: 4px; border-radius: 50%; background: #059669; display: inline-block; }
            .total-row { background: #1e1e24 !important; color: #fff; font-weight: 700; }
            .total-row td { border: none; padding: 10px 8px; }
            .signature-wrap { display: flex; justify-content: flex-end; margin-top: 24px; }
            .signature-box { text-align: center; min-width: 160px; }
            .signature-box .date { font-size: 10px; color: #4a5568; font-weight: 600; margin-bottom: 3px; }
            .signature-box .approval { font-size: 10px; color: #4a5568; font-weight: 600; margin-bottom: 32px; }
            .signature-box .role { font-size: 10px; color: #4a5568; font-weight: 700; text-decoration: underline; margin-bottom: 3px; }
            .signature-box .name { font-size: 10px; color: #4a5568; font-weight: 700; }
            .note { font-size: 9px; color: #94a3b8; margin-top: 12px; text-align: center; }
            .page-footer { text-align: right; font-size: 9px; color: #718096; margin-top: 10px; }
            .loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999; }
            .loading-spinner { width: 40px; height: 40px; border: 4px solid #ffe4e9; border-top: 4px solid #d83f67; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px; }
            .loading p { color: #d83f67; font-weight: 700; font-size: 14px; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="loading" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <p>Menyiapkan PDF...</p>
        </div>

        <div id="laporan">
            <div class="kop-surat">
                <?php if ($logo_exists): ?><img src="../../assets/img/logo.png" alt="SpotLight Studio"><?php endif; ?>
                <div class="kop-text">
                    <h1>SpotLight Studio</h1>
                    <p>Laporan Pendapatan &bull; Periode <?= $periode_str ?></p>
                </div>
            </div>
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
            <table>
                <thead>
                    <tr>
                        <th>No</th><th>No. Bayar</th><th>No. Order</th><th>Customer</th>
                        <th>Metode</th><th>Jumlah</th><th>Tanggal</th><th>Status</th><th>Verifikator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; if (count($rows) > 0): foreach ($rows as $row):
                        $tgl = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format')
                            ? $row['Tanggal_Upload']->format('d M Y H:i') : date('d M Y H:i', strtotime($row['Tanggal_Upload']));
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="td-pink">#<?= str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td class="td-muted">#ORD-<?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td><div class="td-dark"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div><div class="td-muted"><?= htmlspecialchars($row['No_Hp']) ?></div></td>
                        <td class="td-muted"><?= htmlspecialchars($row['Metode_Pembayaran']) ?></td>
                        <td class="td-pink">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></td>
                        <td class="td-muted"><?= $tgl ?></td>
                        <td><span class="badge-lunas"><span class="badge-dot"></span>Lunas</span></td>
                        <td class="td-muted"><?= htmlspecialchars($row['Nama_Verifikator'] ?? 'System') ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:#718096;">Tidak ada data.</td></tr>
                    <?php endif; ?>
                    <?php if (count($rows) > 0): ?>
                    <tr class="total-row">
                        <td colspan="5" style="text-align:right;">TOTAL PENDAPATAN:</td>
                        <td>Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="signature-wrap">
                <div class="signature-box">
                    <div class="date">tanggal <?= date('d M Y') ?></div>
                    <div class="approval">Approval</div>
                    <div class="role">Owner</div>
                    <div class="name"><?= htmlspecialchars($nama_owner) ?></div>
                </div>
            </div>
            <p class="note">Total <?= count($rows) ?> transaksi pelunasan.</p>
            <div class="page-footer">Halaman 1</div>
        </div>

        <script>
            window.onload = function() {
                const element = document.getElementById('laporan');
                const opt = {
                    margin: [12, 12, 12, 12],
                    filename: 'LaporanPendapatan_<?= date('dmY') ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, logging: false },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                html2pdf().set(opt).from(element).save().then(() => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                }).catch(() => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    alert('Gagal generate PDF. Silakan Print halaman ini dan pilih Save as PDF.');
                });
            };
        </script>
    </body>
    </html>
    <?php
    exit();
}

// =====================================================
// CUSTOM TCPDF CLASS -- Header & Footer setiap halaman
// =====================================================
class SpotLightPDF extends TCPDF {
    public $logo_path;
    public $logo_exists;
    public $periode_str;
    public $nama_owner;

    public function Header() {
        $this->SetY(10);
        if ($this->logo_exists && file_exists($this->logo_path)) {
            $this->Image($this->logo_path, 15, 10, 16, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            $textX = 36;
        } else {
            $textX = 15;
        }
        $this->SetXY($textX, 12);
        $this->SetFont('helvetica', 'B', 15);
        $this->SetTextColor(30, 30, 36);
        $this->Cell(0, 8, 'SpotLight Studio', 0, 1, 'L');
        $this->SetX($textX);
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(113, 128, 150);
        $this->Cell(0, 5, 'Laporan Pendapatan  •  Periode ' . $this->periode_str, 0, 1, 'L');
        $this->SetDrawColor(216, 63, 103);
        $this->Line(15, 28, 195, 28);
        $this->Ln(6);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

// =====================================================
// GENERATE PDF -- PORTRAIT
// =====================================================
$pdf = new SpotLightPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->logo_path = $logo_path;
$pdf->logo_exists = $logo_exists;
$pdf->periode_str = $periode_str;
$pdf->nama_owner = $nama_owner;

$pdf->SetCreator('SpotLight Studio');
$pdf->SetAuthor('SpotLight Studio');
$pdf->SetTitle('Laporan Pendapatan - SpotLight Studio');
$pdf->SetSubject('Laporan Pendapatan Pelunasan');
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 32, 15);
$pdf->SetHeaderMargin(8);
$pdf->SetFooterMargin(12);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// --- SUMMARY CARDS ---
$cardW = 55;
$cardH = 20;
$startX = 15;
$startY = $pdf->GetY();

$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(241, 245, 249);

$pdf->RoundedRect($startX, $startY, $cardW, $cardH, 3, '1111', 'DF');
$pdf->SetXY($startX, $startY + 3);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell($cardW, 4, 'TOTAL PENDAPATAN', 0, 2, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(216, 63, 103);
$pdf->Cell($cardW, 7, 'Rp ' . number_format($total_pendapatan, 0, ',', '.'), 0, 0, 'C');

$pdf->RoundedRect($startX + $cardW + 2, $startY, $cardW, $cardH, 3, '1111', 'DF');
$pdf->SetXY($startX + $cardW + 2, $startY + 3);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell($cardW, 4, 'ORDER LUNAS', 0, 2, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(30, 30, 36);
$pdf->Cell($cardW, 7, $jumlah_order, 0, 0, 'C');

$pdf->RoundedRect($startX + ($cardW + 2) * 2, $startY, $cardW, $cardH, 3, '1111', 'DF');
$pdf->SetXY($startX + ($cardW + 2) * 2, $startY + 3);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell($cardW, 4, 'PELANGGAN', 0, 2, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(30, 30, 36);
$pdf->Cell($cardW, 7, $jumlah_pelanggan, 0, 0, 'C');

$pdf->Ln($cardH + 6);

// --- TABEL HEADER ---
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(148, 163, 184);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetDrawColor(241, 245, 249);

$headers = ['No', 'No. Bayar', 'No. Order', 'Customer', 'Metode', 'Jumlah', 'Tanggal', 'Status', 'Verifikator'];
$widths = [6, 20, 18, 32, 24, 24, 24, 16, 16];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 9, $header, 'B', 0, 'L', true);
}
$pdf->Ln();

// --- TABEL DATA ---
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(30, 30, 36);
$fill = false;

if (count($rows) > 0):
    $no = 1;
    foreach ($rows as $row):
        $tgl = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format')
            ? $row['Tanggal_Upload']->format('d M Y H:i')
            : date('d M Y H:i', strtotime($row['Tanggal_Upload']));

        $bg = $fill ? [255, 248, 240] : [255, 255, 255];
        $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);

        $pdf->Cell($widths[0], 7, $no++, 0, 0, 'C', true);
        $pdf->SetTextColor(216, 63, 103);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($widths[1], 7, '#' . str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT), 0, 0, 'L', true);
        $pdf->SetTextColor(30, 30, 36);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($widths[2], 7, '#ORD-' . str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT), 0, 0, 'L', true);
        $pdf->Cell($widths[3], 7, htmlspecialchars($row['Nama_Pelanggan']), 0, 0, 'L', true);
        $pdf->Cell($widths[4], 7, htmlspecialchars($row['Metode_Pembayaran']), 0, 0, 'L', true);
        $pdf->SetTextColor(216, 63, 103);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($widths[5], 7, 'Rp ' . number_format((float)$row['Jumlah_Bayar'], 0, ',', '.'), 0, 0, 'R', true);
        $pdf->SetTextColor(30, 30, 36);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($widths[6], 7, $tgl, 0, 0, 'L', true);
        $pdf->SetTextColor(5, 150, 105);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($widths[7], 7, 'Lunas', 0, 0, 'L', true);
        $pdf->SetTextColor(30, 30, 36);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($widths[8], 7, htmlspecialchars($row['Nama_Verifikator'] ?? 'System'), 0, 1, 'L', true);

        $fill = !$fill;
    endforeach;
else:
    $pdf->Cell(array_sum($widths), 18, 'Tidak ada data pendapatan pelunasan pada periode ini.', 0, 1, 'C', false);
endif;

// --- TOTAL ROW ---
if (count($rows) > 0) {
    $pdf->SetFillColor(30, 30, 36);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(array_sum(array_slice($widths, 0, 5)), 9, 'TOTAL PENDAPATAN:', 'T', 0, 'R', true);
    $pdf->Cell($widths[5], 9, 'Rp ' . number_format($total_pendapatan, 0, ',', '.'), 'T', 0, 'R', true);
    $pdf->Cell(array_sum(array_slice($widths, 6)), 9, '', 'T', 1, 'C', true);
}

// --- TANDA TANGAN ---
$pdf->Ln(10);
$pdf->SetTextColor(74, 85, 104);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'tanggal ' . date('d M Y'), 0, 1, 'R');
$pdf->Cell(0, 6, 'Approval', 0, 1, 'R');
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 30, 36);
$pdf->Cell(0, 6, 'Owner', 0, 1, 'R');
$pdf->Cell(0, 6, $nama_owner, 0, 1, 'R');

// --- OUTPUT: langsung download ---
$filename = 'LaporanPendapatan_' . date('dmY') . '.pdf';
$pdf->Output($filename, 'D');
exit();