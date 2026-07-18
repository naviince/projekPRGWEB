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

$tcpdf_paths = [
    '../../vendor/tecnickcom/tcpdf/tcpdf.php',
    '../../tcpdf/tcpdf.php',
    '../../../vendor/tecnickcom/tcpdf/tcpdf.php',
    '../../../tcpdf/tcpdf.php',
    '../../assets/vendor/tcpdf/tcpdf.php',
];
$tcpdf_found = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) { require_once $path; $tcpdf_found = true; break; }
}

$filename = 'LaporanStokBarang_' . date('dmY') . '.pdf';

if ($tcpdf_found && class_exists('TCPDF')) {
    class SpotLightPDF extends TCPDF {
        public function Header() {
            $logo_path = '../../assets/img/logo.png';
            $this->SetY(10);
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 15, 10, 18, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetXY(38, 12);
            } else {
                $this->SetXY(15, 12);
            }
            $this->SetFont('helvetica', 'B', 14);
            $this->SetTextColor(216, 63, 103);
            $this->Cell(0, 8, 'SpotLight Studio', 0, 1, 'L');
            $this->SetFont('helvetica', '', 9);
            $this->SetTextColor(113, 128, 150);
            $this->Cell(0, 5, 'Laporan Stok Barang Cetak', 0, 1, 'L');
            $this->Cell(0, 5, 'Periode: ' . $GLOBALS['periode_label'], 0, 1, 'L');
            $this->SetDrawColor(216, 63, 103);
            $this->Line(15, $this->GetY() + 2, 195, $this->GetY() + 2);
            $this->Ln(4);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, 0, 'R');
        }
    }

    $pdf = new SpotLightPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('SpotLight Studio');
    $pdf->SetAuthor('SpotLight Studio');
    $pdf->SetTitle('Laporan Stok Barang Cetak');
    $pdf->SetMargins(15, 35, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(30, 30, 36);
    $pdf->Cell(0, 8, 'Ringkasan', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 245, 246);
    $pdf->Cell(48, 8, 'Jenis: ' . ($summary['Total_Jenis_Barang'] ?? 0), 1, 0, 'C', true);
    $pdf->Cell(48, 8, 'Terjual: ' . number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.'), 1, 0, 'C', true);
    $pdf->Cell(48, 8, 'Aset: Rp ' . number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.'), 1, 0, 'C', true);
    $pdf->Cell(48, 8, 'Omzet: Rp ' . number_format((float)($summary['Total_Omzet_Terjual'] ?? 0), 0, ',', '.'), 1, 1, 'C', true);
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetTextColor(30, 30, 36);
    $headers = ['No', 'ID', 'Nama Barang', 'Harga', 'Stok', 'Min', 'Terjual', 'Status', 'Nilai', 'Omzet'];
    $widths = [10, 18, 45, 22, 15, 15, 18, 22, 22, 22];
    foreach ($headers as $i => $h) {
        $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 8);
    $no = 1;
    foreach ($rows as $row) {
        $stok = (int)$row['Stok_Barang'];
        $min = (int)$row['Stok_Minimum'];
        $nilai = $stok * (float)$row['Harga_Barang'];
        if ($stok === 0) $status = 'Habis';
        elseif ($stok <= $min) $status = 'Menipis';
        else $status = 'Aman';

        $pdf->Cell($widths[0], 7, $no++, 1, 0, 'C');
        $pdf->Cell($widths[1], 7, '#BRG-' . str_pad((int)$row['ID_Barang'], 3, '0', STR_PAD_LEFT), 1, 0, 'C');
        $pdf->Cell($widths[2], 7, substr($row['Nama_Barang'], 0, 22), 1, 0, 'L');
        $pdf->Cell($widths[3], 7, 'Rp ' . number_format((float)$row['Harga_Barang'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[4], 7, number_format($stok, 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($widths[5], 7, number_format($min, 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($widths[6], 7, number_format((int)$row['Total_Terjual'], 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($widths[7], 7, $status, 1, 0, 'C');
        $pdf->Cell($widths[8], 7, 'Rp ' . number_format($nilai, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($widths[9], 7, 'Rp ' . number_format((float)$row['Total_Pendapatan'], 0, ',', '.'), 1, 1, 'R');
    }

    if (count($rows) === 0) {
        $pdf->Cell(189, 10, 'Tidak ada data.', 1, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'tanggal ' . date('d M Y'), 0, 1, 'R');
    $pdf->Cell(0, 6, 'Approval', 0, 1, 'R');
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'Owner', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(216, 63, 103);
    $pdf->Cell(0, 6, $nama_owner, 0, 1, 'R');

    $pdf->Output($filename, 'D');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Export PDF — Laporan Stok Barang</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f8fafc;color:#1e1e24;margin:0;padding:20px;}
.kop{display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #d83f67;}
.kop img{height:60px;}
.kop-text h2{margin:0;font-weight:800;color:#d83f67;font-size:1.4rem;}
.kop-text p{margin:4px 0;font-size:10pt;color:#555;}
.summary{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.summary-item{flex:1;min-width:140px;background:#f8fafc;border-radius:12px;padding:12px;text-align:center;}
.summary-item .val{font-weight:800;font-size:1.1rem;color:#d83f67;}
.summary-item .lbl{font-size:0.7rem;color:#718096;font-weight:700;text-transform:uppercase;}
table{width:100%;border-collapse:collapse;font-size:0.8rem;margin-bottom:20px;}
th,td{padding:10px;border:1px solid #e2e8f0;text-align:left;}
th{background:#f8fafc;font-weight:800;color:#94a3b8;font-size:0.7rem;text-transform:uppercase;}
td{font-weight:600;}
tr:nth-child(even){background:#fff8f0;}
.ttd{margin-top:30px;display:flex;justify-content:flex-end;}
.ttd-box{text-align:center;width:200px;}
.ttd-box .tanggal{font-size:0.85rem;color:#718096;margin-bottom:40px;}
.ttd-box .jabatan{font-weight:800;font-size:0.9rem;}
.ttd-box .nama{font-weight:700;font-size:0.85rem;color:#d83f67;margin-top:4px;}
.btn-download{background:linear-gradient(135deg,#d83f67,#c73165);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-weight:800;cursor:pointer;margin-bottom:20px;}
.loading{text-align:center;padding:40px;color:#718096;font-weight:600;}
</style>
</head>
<body>
<div class="loading" id="loading">Menyiapkan PDF... <br><small>Mohon tunggu sebentar</small></div>
<div id="pdf-content" style="display:none;">
<div class="kop">
<img src="../../assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
<div class="kop-text">
<h2>SpotLight Studio</h2>
<p>Laporan Stok Barang Cetak &bull; Periode <?= $periode_label ?></p>
</div>
</div>
<div class="summary">
<div class="summary-item"><div class="val"><?= $summary['Total_Jenis_Barang'] ?? 0 ?></div><div class="lbl">Jenis Barang</div></div>
<div class="summary-item"><div class="val"><?= number_format((int)($summary['Total_Unit_Terjual'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Unit Terjual</div></div>
<div class="summary-item"><div class="val">Rp <?= number_format((float)($summary['Total_Nilai_Aset'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Nilai Aset</div></div>
<div class="summary-item"><div class="val">Rp <?= number_format((float)($summary['Total_Omzet_Terjual'] ?? 0), 0, ',', '.') ?></div><div class="lbl">Omzet Terjual</div></div>
</div>
<table>
<thead>
<tr><th>No</th><th>ID</th><th>Nama Barang</th><th>Harga</th><th>Stok</th><th>Min</th><th>Terjual</th><th>Status</th><th>Nilai</th><th>Omzet</th></tr>
</thead>
<tbody>
<?php $no = 1; foreach ($rows as $row): 
    $stok = (int)$row['Stok_Barang']; $min = (int)$row['Stok_Minimum'];
    $nilai = $stok * (float)$row['Harga_Barang'];
    if ($stok === 0) $status = 'Habis'; elseif ($stok <= $min) $status = 'Menipis'; else $status = 'Aman';
?>
<tr>
<td><?= $no++ ?></td>
<td>#BRG-<?= str_pad((int)$row['ID_Barang'], 3, '0', STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($row['Nama_Barang']) ?></td>
<td>Rp <?= number_format((float)$row['Harga_Barang'], 0, ',', '.') ?></td>
<td><?= number_format($stok, 0, ',', '.') ?></td>
<td><?= number_format($min, 0, ',', '.') ?></td>
<td><?= number_format((int)$row['Total_Terjual'], 0, ',', '.') ?></td>
<td><?= $status ?></td>
<td>Rp <?= number_format($nilai, 0, ',', '.') ?></td>
<td>Rp <?= number_format((float)$row['Total_Pendapatan'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
<?php if (count($rows) === 0): ?><tr><td colspan="10" style="text-align:center;">Tidak ada data.</td></tr><?php endif; ?>
</tbody>
</table>
<div class="ttd">
<div class="ttd-box">
<p> tanggal <?= date('d M Y') ?><br>Approval</p>
<div class="jabatan">Owner</div>
<div class="nama"><?= htmlspecialchars($nama_owner) ?></div>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const element = document.getElementById('pdf-content');
    const opt = {
        margin: [15, 15, 15, 15],
        filename: '<?= $filename ?>',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save().then(() => {
        document.getElementById('loading').innerHTML = '<div style="color:#059669;">PDF berhasil diunduh!</div>';
    }).catch(err => {
        document.getElementById('loading').innerHTML = '<div style="color:#dc2626;">Gagal membuat PDF. Silakan cetak manual (Ctrl+P).</div>';
        element.style.display = 'block';
        console.error(err);
    });
});
</script>
</body>
</html>