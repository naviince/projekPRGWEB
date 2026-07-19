<?php
session_start();

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php"); exit();
}

if (!file_exists('../../koneksi.php')) { die('Error: File koneksi.php tidak ditemukan!'); }
include '../../koneksi.php';
if (!isset($conn) || $conn === false) { die('Error: Koneksi database gagal!'); }

$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_owner = 'Owner';
$q_owner = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
if ($q_owner && $d = sqlsrv_fetch_array($q_owner, SQLSRV_FETCH_ASSOC)) { $nama_owner = $d['Nama_Karyawan'] ?? 'Owner'; }

$tgl_mulai = isset($_GET['tgl_mulai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) { [$tgl_mulai, $tgl_selesai] = [$tgl_selesai, $tgl_mulai]; }
$periode_str = date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai));

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$alasan_filter = isset($_GET['alasan']) && in_array($_GET['alasan'], ['belum_bayar_dp','dp_ditolak','dibatalkan_pelanggan','dibatalkan_sistem']) ? $_GET['alasan'] : '';
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['terbaru','terlama','nama_asc','nama_desc','harga_tertinggi','harga_terendah','jadwal_terdekat','jadwal_terjauh']) ? $_GET['sort'] : 'terbaru';

$q_summary = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanSummary (?, ?)}", array($tgl_mulai, $tgl_selesai));
$summary = []; if ($q_summary) { while ($r = sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC)) { $summary[] = $r; } }
$total_batal = $summary[0]['Total_Batal'] ?? 0;
$total_belum_bayar_dp = $summary[1]['Total_BelumBayarDP'] ?? 0;
$total_dp_ditolak = $summary[2]['Total_DPDitolak'] ?? 0;
$total_dibatalkan_plg = $summary[3]['Total_DibatalkanPelanggan'] ?? 0;

$q_detail = sqlsrv_query($conn, "{CALL sp_LaporanPembatalanDetail (?, ?, ?, ?, ?, 0, 1000000)}", array($tgl_mulai, $tgl_selesai, $search, $alasan_filter, $sort));
$rows = []; if ($q_detail) { while ($r = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; } }

$logo_path = '../../assets/img/logo.png'; $logo_exists = file_exists($logo_path);

$tcpdf_paths = ['../../assets/vendor/tcpdf/tcpdf.php','../../assets/tcpdf/tcpdf.php','../../tcpdf/tcpdf.php','../../vendor/tecnickcom/tcpdf/tcpdf.php','../../vendor/autoload.php','../../../vendor/autoload.php'];
$tcpdf_found = false;
foreach ($tcpdf_paths as $path) { if (file_exists($path)) { require_once($path); $tcpdf_found = true; break; } }

function formatTanggalSingkat($dateObj) {
    if (is_object($dateObj) && method_exists($dateObj, 'format')) return $dateObj->format('d M Y');
    return date('d M Y', strtotime($dateObj));
}
function getAlasanColor($alasan) {
    $map = ['Belum Bayar DP'=>'#dc2626','DP Ditolak'=>'#d97706','Dibatalkan Pelanggan'=>'#2563eb','Dibatalkan Sistem'=>'#4b5563'];
    return $map[$alasan] ?? '#4b5563';
}

if (!$tcpdf_found) {
    header("Content-Type: text/html; charset=UTF-8");
    ?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Laporan Pembatalan</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>@page{size:A4;margin:12mm}*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Segoe UI',Arial,sans-serif;font-size:10px;color:#1e1e24;background:#fff;padding:16px}#laporan{width:100%;max-width:210mm;margin:0 auto}.kop-surat{display:flex;align-items:center;justify-content:center;gap:14px;padding-bottom:14px;margin-bottom:14px;border-bottom:3px solid #d83f67}.kop-surat img{height:50px;width:auto;flex-shrink:0}.kop-text h1{margin:0;font-size:20px;font-weight:800;color:#1e1e24;letter-spacing:-0.5px}.kop-text p{margin:3px 0 0;font-size:11px;color:#718096;font-weight:600}.summary-row{display:flex;gap:10px;margin-bottom:16px}.summary-box{flex:1;background:#f8fafc;border-radius:10px;padding:12px;text-align:center}.summary-box .label{font-size:9px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px}.summary-box .value{font-size:15px;font-weight:800;color:#d83f67}table{width:100%;border-collapse:separate;border-spacing:0;font-size:9px;margin-top:8px}th{background:#fff;padding:10px 8px;font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;text-align:left;border-bottom:2px solid #f1f5f9}td{padding:8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}tr:nth-child(even){background-color:#fff8f0}.td-pink{font-weight:800;color:#d83f67}.td-dark{font-weight:700;color:#1e1e24}.td-muted{font-size:8px;color:#94a3b8;font-weight:600}.signature-wrap{display:flex;justify-content:flex-end;margin-top:24px}.signature-box{text-align:center;min-width:160px}.signature-box .date{font-size:10px;color:#4a5568;font-weight:600;margin-bottom:3px}.signature-box .approval{font-size:10px;color:#4a5568;font-weight:600;margin-bottom:32px}.signature-box .role{font-size:10px;color:#4a5568;font-weight:700;text-decoration:underline;margin-bottom:3px}.signature-box .name{font-size:10px;color:#4a5568;font-weight:700}.note{font-size:9px;color:#94a3b8;margin-top:12px;text-align:center}.page-footer{text-align:right;font-size:9px;color:#718096;margin-top:10px}.loading{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.95);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999}.loading-spinner{width:40px;height:40px;border:4px solid #ffe4e9;border-top:4px solid #d83f67;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:16px}.loading p{color:#d83f67;font-weight:700;font-size:14px}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}</style></head><body>
    <div class="loading" id="loadingOverlay"><div class="loading-spinner"></div><p>Menyiapkan PDF...</p></div>
    <div id="laporan">
        <div class="kop-surat"><?php if($logo_exists):?><img src="../../assets/img/logo.png" alt="SpotLight Studio"><?php endif;?>
            <div class="kop-text"><h1>SpotLight Studio</h1><p>Laporan Pembatalan &bull; Periode <?= $periode_str ?></p></div>
        </div>
        <div class="summary-row">
            <div class="summary-box"><div class="label">Total Batal</div><div class="value"><?= $total_batal ?></div></div>
            <div class="summary-box"><div class="label">Belum Bayar DP</div><div class="value" style="color:#dc2626;"><?= $total_belum_bayar_dp ?></div></div>
            <div class="summary-box"><div class="label">DP Ditolak</div><div class="value" style="color:#d97706;"><?= $total_dp_ditolak ?></div></div>
            <div class="summary-box"><div class="label">Dibatalkan Plg</div><div class="value" style="color:#2563eb;"><?= $total_dibatalkan_plg ?></div></div>
        </div>
        <table><thead><tr><th>No</th><th>No. Order</th><th>Customer</th><th>Paket</th><th>Ruangan</th><th>Tema</th><th>Alasan</th><th>Keterangan</th></tr></thead>
        <tbody><?php $no=1; if(count($rows)>0): foreach($rows as $row): ?>
            <tr><td><?= $no++ ?></td><td class="td-pink">#ORD-<?= str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT) ?></td>
            <td><div class="td-dark"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div><div class="td-muted"><?= htmlspecialchars($row['No_Hp']) ?></div></td>
            <td class="td-muted"><?= htmlspecialchars($row['Nama_Paket']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['Nama_Ruangan']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['Nama_Tema']) ?></td>
            <td style="font-weight:700;color:<?= getAlasanColor($row['Alasan_Batal']) ?>;"><?= htmlspecialchars($row['Alasan_Batal']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['Keterangan_Order']??'-') ?></td></tr>
        <?php endforeach; else: ?><tr><td colspan="8" style="text-align:center;padding:24px;color:#718096;">Tidak ada data.</td></tr><?php endif; ?></tbody>
        </table>
        <div class="signature-wrap"><div class="signature-box"><div class="date">tanggal <?= date('d M Y') ?></div><div class="approval">Approval</div><div class="role">Owner</div><div class="name"><?= htmlspecialchars($nama_owner) ?></div></div></div>
        <p class="note">Total <?= count($rows) ?> booking batal.</p><div class="page-footer">Halaman 1</div>
    </div>
    <script>window.onload=function(){const e=document.getElementById('laporan');html2pdf().set({margin:[12,12,12,12],filename:'LaporanPembatalan_<?= date('dmY') ?>.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true,logging:false},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}}).from(e).save().then(()=>{document.getElementById('loadingOverlay').style.display='none'}).catch(()=>{document.getElementById('loadingOverlay').style.display='none';alert('Gagal generate PDF.')});};</script>
    </body></html><?php exit();
}

class SpotLightPDF extends TCPDF {
    public $logo_path; public $logo_exists; public $periode_str; public $nama_owner;
    public function Header() {
        $this->SetY(10);
        if ($this->logo_exists && file_exists($this->logo_path)) { $this->Image($this->logo_path,15,10,16,0,'PNG','','T',false,300,'',false,false,0,false,false,false); $textX=36; } else { $textX=15; }
        $this->SetXY($textX,12); $this->SetFont('helvetica','B',15); $this->SetTextColor(30,30,36); $this->Cell(0,8,'SpotLight Studio',0,1,'L');
        $this->SetX($textX); $this->SetFont('helvetica','',9); $this->SetTextColor(113,128,150); $this->Cell(0,5,'Laporan Pembatalan  •  Periode '.$this->periode_str,0,1,'L');
        $this->SetDrawColor(216,63,103); $this->Line(15,28,195,28); $this->Ln(6);
    }
    public function Footer() { $this->SetY(-15); $this->SetFont('helvetica','',8); $this->SetTextColor(148,163,184); $this->Cell(0,10,'Halaman '.$this->getAliasNumPage().' dari '.$this->getAliasNbPages(),0,0,'R'); }
}

$pdf = new SpotLightPDF('P','mm','A4',true,'UTF-8',false);
$pdf->logo_path=$logo_path; $pdf->logo_exists=$logo_exists; $pdf->periode_str=$periode_str; $pdf->nama_owner=$nama_owner;
$pdf->SetCreator('SpotLight Studio'); $pdf->SetAuthor('SpotLight Studio'); $pdf->SetTitle('Laporan Pembatalan'); $pdf->SetSubject('Laporan Pembatalan Booking');
$pdf->setPrintHeader(true); $pdf->setPrintFooter(true); $pdf->SetMargins(15,32,15); $pdf->SetHeaderMargin(8); $pdf->SetFooterMargin(12); $pdf->SetAutoPageBreak(true,18); $pdf->AddPage();

$cardW=42; $cardH=20; $startX=15; $startY=$pdf->GetY(); $pdf->SetFillColor(248,250,252); $pdf->SetDrawColor(241,245,249);
$pdf->RoundedRect($startX,$startY,$cardW,$cardH,3,'1111','DF'); $pdf->SetXY($startX,$startY+3); $pdf->SetFont('helvetica','',7); $pdf->SetTextColor(148,163,184); $pdf->Cell($cardW,4,'TOTAL BATAL',0,2,'C'); $pdf->SetFont('helvetica','B',12); $pdf->SetTextColor(216,63,103); $pdf->Cell($cardW,7,$total_batal.' Booking',0,0,'C');
$pdf->RoundedRect($startX+$cardW+2,$startY,$cardW,$cardH,3,'1111','DF'); $pdf->SetXY($startX+$cardW+2,$startY+3); $pdf->SetFont('helvetica','',7); $pdf->SetTextColor(148,163,184); $pdf->Cell($cardW,4,'BELUM BAYAR DP',0,2,'C'); $pdf->SetFont('helvetica','B',12); $pdf->SetTextColor(220,38,38); $pdf->Cell($cardW,7,$total_belum_bayar_dp,0,0,'C');
$pdf->RoundedRect($startX+($cardW+2)*2,$startY,$cardW,$cardH,3,'1111','DF'); $pdf->SetXY($startX+($cardW+2)*2,$startY+3); $pdf->SetFont('helvetica','',7); $pdf->SetTextColor(148,163,184); $pdf->Cell($cardW,4,'DP DITOLAK',0,2,'C'); $pdf->SetFont('helvetica','B',12); $pdf->SetTextColor(217,119,6); $pdf->Cell($cardW,7,$total_dp_ditolak,0,0,'C');
$pdf->RoundedRect($startX+($cardW+2)*3,$startY,$cardW,$cardH,3,'1111','DF'); $pdf->SetXY($startX+($cardW+2)*3,$startY+3); $pdf->SetFont('helvetica','',7); $pdf->SetTextColor(148,163,184); $pdf->Cell($cardW,4,'DIBATALKAN PLG',0,2,'C'); $pdf->SetFont('helvetica','B',12); $pdf->SetTextColor(37,99,235); $pdf->Cell($cardW,7,$total_dibatalkan_plg,0,0,'C');
$pdf->Ln($cardH+6);

$pdf->SetFillColor(255,255,255); $pdf->SetTextColor(148,163,184); $pdf->SetFont('helvetica','B',7); $pdf->SetDrawColor(241,245,249);
$headers=['No','No. Order','Customer','Paket','Ruangan','Tema','Alasan','Keterangan']; $widths=[6,20,32,24,24,24,24,22];
foreach($headers as $i=>$header){$pdf->Cell($widths[$i],9,$header,'B',0,'L',true);} $pdf->Ln();

$pdf->SetFont('helvetica','',8); $pdf->SetTextColor(30,30,36); $fill=false;
if(count($rows)>0){$no=1; foreach($rows as $row){$bg=$fill?[255,248,240]:[255,255,255]; $pdf->SetFillColor($bg[0],$bg[1],$bg[2]); $color=getAlasanColor($row['Alasan_Batal']);
$pdf->Cell($widths[0],7,$no++,0,0,'C',true); $pdf->SetTextColor(216,63,103); $pdf->SetFont('helvetica','B',8); $pdf->Cell($widths[1],7,'#ORD-'.str_pad((int)$row['ID_Order'],5,'0',STR_PAD_LEFT),0,0,'L',true);
$pdf->SetTextColor(30,30,36); $pdf->SetFont('helvetica','',8); $pdf->Cell($widths[2],7,htmlspecialchars($row['Nama_Pelanggan']),0,0,'L',true); $pdf->Cell($widths[3],7,htmlspecialchars($row['Nama_Paket']),0,0,'L',true); $pdf->Cell($widths[4],7,htmlspecialchars($row['Nama_Ruangan']),0,0,'L',true); $pdf->Cell($widths[5],7,htmlspecialchars($row['Nama_Tema']),0,0,'L',true);
$pdf->SetTextColor(hexdec(substr($color,1,2)),hexdec(substr($color,3,2)),hexdec(substr($color,5,2))); $pdf->SetFont('helvetica','B',7); $pdf->Cell($widths[6],7,$row['Alasan_Batal'],0,0,'L',true);
$pdf->SetTextColor(30,30,36); $pdf->SetFont('helvetica','',8); $pdf->Cell($widths[7],7,htmlspecialchars($row['Keterangan_Order']??'-'),0,1,'L',true); $fill=!$fill;}}
else{$pdf->Cell(array_sum($widths),18,'Tidak ada data pembatalan pada periode ini.',0,1,'C',false);}

$pdf->Ln(10); $pdf->SetTextColor(74,85,104); $pdf->SetFont('helvetica','',10); $pdf->Cell(0,6,'tanggal '.date('d M Y'),0,1,'R'); $pdf->Cell(0,6,'Approval',0,1,'R'); $pdf->Ln(10); $pdf->SetFont('helvetica','B',10); $pdf->SetTextColor(30,30,36); $pdf->Cell(0,6,'Owner',0,1,'R'); $pdf->Cell(0,6,$nama_owner,0,1,'R');
$filename='LaporanPembatalan_'.date('dmY').'.pdf'; $pdf->Output($filename,'D'); exit();