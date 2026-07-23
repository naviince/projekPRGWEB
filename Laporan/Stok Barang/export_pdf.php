<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

include '../../koneksi.php';

// Ambil Parameter
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'terjual_desc';
$status_filter = $_GET['status_filter'] ?? '';

$periode_label = date('d M Y', strtotime($tgl_mulai)) . ' – ' . date('d M Y', strtotime($tgl_selesai));

// Ambil Profil Owner
$id_owner = $_SESSION['id_user'] ?? $_SESSION['id_karyawan'] ?? null;
$nama_owner = 'Pemilik';
$q_owner = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?", array($id_owner));
if ($q_owner && ($r_owner = sqlsrv_fetch_array($q_owner, SQLSRV_FETCH_ASSOC))) {
    $nama_owner = $r_owner['Nama_Karyawan'];
}

// Data Summary
$summary = ['Total_Jenis_Barang' => 0, 'Total_Unit_Terjual' => 0, 'Total_Nilai_Aset' => 0, 'Total_Omzet_Terjual' => 0];
$q_summary = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangSummary ?, ?", array($tgl_mulai, $tgl_selesai));
if ($q_summary && ($row_s = sqlsrv_fetch_array($q_summary, SQLSRV_FETCH_ASSOC))) {
    $summary = $row_s;
}

// Data Detail
$rows = [];
$q_detail = sqlsrv_query($conn, "EXEC sp_LaporanStokBarangDetail ?, ?, ?, ?, ?, ?, ?", 
    array($tgl_mulai, $tgl_selesai, $search ?: null, $status_filter ?: null, $sort, 0, 1000));
if ($q_detail) {
    while ($row_d = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row_d;
    }
}

$filename = 'LaporanStok_' . date('dmy') . '.pdf';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Stok Barang</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; background: #525659; }
        
        #pdf-content {
            width: 210mm; /* A4 Width */
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-sizing: border-box;
            position: relative;
        }

        /* Header Style */
        .header-table { width: 100%; border-bottom: 3px solid #d83f67; margin-bottom: 10px; padding-bottom: 10px; }
        .header-logo { width: 80px; }
        .header-text { text-align: right; }
        .header-text h1 { margin: 0; color: #d83f67; font-size: 24pt; font-weight: 800; }
        .header-text p { margin: 2px 0; color: #64748b; font-size: 10pt; font-weight: 600; }

        /* Summary Cards */
        .summary-wrapper { display: table; width: 100%; margin: 20px 0; border-spacing: 10px; border-collapse: separate; }
        .summary-card { display: table-cell; background: #fff5f6; border: 1px solid #ffe4e9; padding: 15px; border-radius: 12px; text-align: center; width: 25%; }
        .summary-card .val { display: block; font-weight: 800; color: #d83f67; font-size: 14pt; margin-bottom: 4px; }
        .summary-card .lbl { font-size: 7pt; color: #718096; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }

        /* Table Style */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
        .data-table th { background: #f8fafc; color: #475569; font-weight: 800; text-transform: uppercase; border: 1px solid #e2e8f0; padding: 12px 8px; }
        .data-table td { padding: 10px 8px; border: 1px solid #e2e8f0; color: #1e293b; }
        .data-table tr:nth-child(even) { background-color: #fcfcfc; }

        /* Status Badge */
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 7.5pt; font-weight: 700; display: inline-block; }
        .aman { background: #dcfce7; color: #166534; }
        .menipis { background: #fef9c3; color: #854d0e; }
        .habis { background: #fee2e2; color: #991b1b; }

        /* Helper Utility */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 700; }

        /* Signature */
        .footer-section { margin-top: 50px; width: 100%; }
        .signature-box { float: right; width: 250px; text-align: center; }
        .signature-box p { margin: 0; font-size: 10pt; color: #1e293b; }
        .signature-name { margin-top: 60px; font-weight: 800; color: #d83f67; font-size: 11pt; text-decoration: underline; }

        /* Loader */
        #loader { position: fixed; inset: 0; background: rgba(255,255,255,0.9); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 100; }
        .spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #d83f67; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div id="loader">
    <div class="spinner"></div>
    <p style="margin-top: 15px; font-weight: 700; color: #d83f67;">Menyiapkan Laporan...</p>
</div>

<div id="pdf-content">
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="border:none;">
                <img src="../../assets/img/logo.png" style="height: 60px;" alt="Logo" onerror="this.style.display='none'">
            </td>
            <td class="header-text" style="border:none;">
                <h1>SpotLight Studio</h1>
                <p>Manajemen Stok & Inventaris Barang Cetak</p>
                <p>Periode: <?= $periode_label ?></p>
            </td>
        </tr>
    </table>

    <!-- Summary Cards -->
    <div class="summary-wrapper">
        <div class="summary-card">
            <span class="val"><?= $summary['Total_Jenis_Barang'] ?? 0 ?></span>
            <span class="lbl">Jenis Barang</span>
        </div>
        <div class="summary-card">
            <span class="val"><?= number_format($summary['Total_Unit_Terjual'] ?? 0, 0, ',', '.') ?></span>
            <span class="lbl">Unit Terjual</span>
        </div>
        <div class="summary-card">
            <span class="val">Rp <?= number_format($summary['Total_Nilai_Aset'] ?? 0, 0, ',', '.') ?></span>
            <span class="lbl">Nilai Aset</span>
        </div>
        <div class="summary-card">
            <span class="val">Rp <?= number_format($summary['Total_Omzet_Terjual'] ?? 0, 0, ',', '.') ?></span>
            <span class="lbl">Total Omzet</span>
        </div>
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">ID Barang</th>
                <th width="30%">Nama Barang</th>
                <th width="15%">Stok</th>
                <th width="10%">Terjual</th>
                <th width="10%">Status</th>
                <th width="15%">Nilai Aset</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            if(count($rows) > 0):
                foreach ($rows as $row): 
                    $stok = (int)$row['Stok_Barang'];
                    $min = (int)$row['Stok_Minimum'];
                    $nilai_aset = $stok * (float)$row['Harga_Barang'];
                    
                    if ($stok <= 0) { $cls = 'habis'; $txt = 'Habis'; }
                    elseif ($stok <= $min) { $cls = 'menipis'; $txt = 'Menipis'; }
                    else { $cls = 'aman'; $txt = 'Aman'; }
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center fw-bold">#BRG-<?= str_pad($row['ID_Barang'], 3, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($row['Nama_Barang']) ?></td>
                <td class="text-center"><?= number_format($stok, 0, ',', '.') ?> <small style="color:#94a3b8">Unit</small></td>
                <td class="text-center"><?= number_format($row['Total_Terjual'], 0, ',', '.') ?></td>
                <td class="text-center">
                    <span class="badge <?= $cls ?>"><?= $txt ?></span>
                </td>
                <td class="text-right fw-bold">Rp <?= number_format($nilai_aset, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center" style="padding: 20px;">Data tidak ditemukan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Footer / Tanda Tangan -->
    <div class="footer-section">
        <div class="signature-box">
            <p>Jakarta, <?= date('d F Y') ?></p>
            <p style="margin-top: 5px;">Mengetahui,</p>
            <p class="fw-bold">Owner SpotLight Studio</p>
            <div class="signature-name"><?= htmlspecialchars($nama_owner) ?></div>
        </div>
        <div style="clear: both;"></div>
    </div>
</div>

<script>
window.onload = function() {
    const element = document.getElementById('pdf-content');
    const opt = {
        margin: 0,
        filename: '<?= $filename ?>',
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        document.getElementById('loader').innerHTML = '<p style="font-weight:800; color:#166534;">✓ Download Berhasil</p>';
        setTimeout(() => { window.close(); }, 1000);
    });
};
</script>

</body>
</html>