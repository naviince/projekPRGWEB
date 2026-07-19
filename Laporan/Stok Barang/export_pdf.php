<?php
session_start();

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    die("Akses ditolak.");
}

include '../../koneksi.php';

$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// =====================================================
// AMBIL DATA PROFIL OWNER (MENCEGAH BUG WARNING)
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

// Ambil total jenis barang cetak aktif
$sql_jenis = "SELECT COUNT(*) AS total FROM Barang_Cetak WHERE Status = 1 AND Is_Deleted = 0";
$stmt_jenis = sqlsrv_query($conn, $sql_jenis);
$row_jenis = sqlsrv_fetch_array($stmt_jenis, SQLSRV_FETCH_ASSOC);
$total_jenis_barang = $row_jenis['total'] ?? 0;

$sql_laporan = "SELECT 
                    bc.ID_Barang,
                    bc.Nama_Barang,
                    bc.Harga_Barang,
                    bc.Stok_Barang,
                    bc.Stok_Minimum,
                    ISNULL(SUM(CASE WHEN p.Status = 1 AND p.Status_Penjualan = 1 AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN ? AND ? THEN dp.Jumlah ELSE 0 END), 0) AS Total_Terjual,
                    ISNULL(SUM(CASE WHEN p.Status = 1 AND p.Status_Penjualan = 1 AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN ? AND ? THEN dp.Subtotal ELSE 0 END), 0) AS Total_Pendapatan
                FROM Barang_Cetak bc
                LEFT JOIN Detail_Penjualan_Barang_Cetak dp ON bc.ID_Barang = dp.ID_Barang
                LEFT JOIN Penjualan p ON dp.ID_Penjualan = p.ID_Penjualan
                WHERE bc.Status = 1 AND bc.Is_Deleted = 0
                GROUP BY bc.ID_Barang, bc.Nama_Barang, bc.Harga_Barang, bc.Stok_Barang, bc.Stok_Minimum
                ORDER BY Total_Terjual DESC, bc.Stok_Barang ASC";

$query_laporan = sqlsrv_query($conn, $sql_laporan, [$tgl_mulai, $tgl_selesai, $tgl_mulai, $tgl_selesai]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Laporan Persediaan Barang</title>
<link rel="icon" type="image/png" href="/projekPRGWEB/assets/img/favicon.png">
<style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; margin: 30px; }
    
    /* Tombol Kontrol Interaktif */
    .print-control-bar {
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .btn-action {
        padding: 10px 20px;
        font-weight: 700;
        font-size: 12px;
        border-radius: 8px;
        cursor: pointer;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
    }
    .btn-download {
        background-color: #d83f67;
        color: white;
    }
    .btn-download:hover {
        background-color: #c73165;
    }
    .btn-back {
        background-color: #edf2f7;
        color: #4a5568;
    }
    .btn-back:hover {
        background-color: #e2e8f0;
    }

    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .header-logo { font-size: 24px; font-weight: 800; color: #d83f67; letter-spacing: -1px; }
    .header-logo span { color: #1e1e24; font-size: 14px; font-weight: 500; }
    .header-title { text-align: right; font-size: 14px; font-weight: bold; text-transform: uppercase; color: #718096; }
    .report-meta { margin-bottom: 20px; font-size: 11px; background-color: #f8fafc; padding: 12px 18px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 10px; }
    .data-table th { background-color: #d83f67; color: white; padding: 10px 12px; font-weight: bold; text-transform: uppercase; border: 1px solid #d83f67; text-align: left; }
    .data-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; }
    .data-table tr:nth-child(even) { background-color: #fdfafb; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }
    .badge-aman { color: #059669; font-weight: bold; }
    .badge-tipis { color: #d97706; font-weight: bold; }
    .badge-habis { color: #dc2626; font-weight: bold; }
    
    @media print {
        body { margin: 15px; }
        .print-control-bar { display: none !important; }
    }
</style>
</head>
<body>

    <!-- BAR KONTROL UNTUK CETAK & DOWNLOAD PDF -->
    <div class="print-control-bar">
        <div style="font-weight: 600; font-size: 13px; color: #4a5568;">
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
                Laporan Persediaan Barang Cetak
            </td>
        </tr>
    </table>

    <div class="report-meta">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:50%;"><strong>Periode Analisis Penjualan:</strong> <?php echo date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai)); ?></td>
                <td style="width:50%; text-align:right;"><strong>Tanggal Cetak:</strong> <?php echo date('d M Y H:i'); ?> WIB</td>
            </tr>
            <tr>
                <td><strong>Dicetak Oleh:</strong> Owner System</td>
                <td style="text-align:right;"><strong>Katalog Aktif:</strong> <?php echo $total_jenis_barang; ?> Jenis Produk</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 70px;">ID Barang</th>
                <th>Nama Produk</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-center">Stok Saat Ini</th>
                <th class="text-center">Stok Minimum</th>
                <th class="text-center">Unit Terjual</th>
                <th class="text-center">Status Sisa Persediaan</th>
                <th class="text-right">Total Nilai Aset Fisik</th>
                <th class="text-right">Omzet Penjualan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($query_laporan && sqlsrv_has_rows($query_laporan)):
            while ($row = sqlsrv_fetch_array($query_laporan, SQLSRV_FETCH_ASSOC)):
                $stok = (int)$row['Stok_Barang'];
                $min = (int)$row['Stok_Minimum'];
                $nilai_persediaan = $stok * (float)$row['Harga_Barang'];
                
                if ($stok === 0) {
                    $classText = "badge-habis";
                    $statusText = "Habis";
                } elseif ($stok <= $min) {
                    $classText = "badge-tipis";
                    $statusText = "Stok Menipis";
                } else {
                    $classText = "badge-aman";
                    $statusText = "Stok Aman";
                }
            ?>
            <tr>
                <td class="text-center text-bold">#BRG-<?php echo str_pad($row['ID_Barang'], 3, '0', STR_PAD_LEFT); ?></td>
                <td class="text-bold"><?php echo htmlspecialchars($row['Nama_Barang']); ?></td>
                <td class="text-right">Rp <?php echo number_format($row['Harga_Barang'], 0, ',', '.'); ?></td>
                <td class="text-center text-bold"><?php echo $stok; ?> Unit</td>
                <td class="text-center"><?php echo $min; ?> Unit</td>
                <td class="text-center text-bold" style="color: #4a5568;"><?php echo $row['Total_Terjual']; ?> Unit</td>
                <td class="text-center <?php echo $classText; ?>"><?php echo $statusText; ?></td>
                <td class="text-right text-bold" style="color: #2563eb;">Rp <?php echo number_format($nilai_persediaan, 0, ',', '.'); ?></td>
                <td class="text-right text-bold" style="color: #059669;">Rp <?php echo number_format($row['Total_Pendapatan'], 0, ',', '.'); ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="9" class="text-center">Tidak ada data untuk periode ini.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table style="width: 100%; border: none; margin-top: 50px;">
        <tr>
            <td style="width: 70%;"></td>
            <td class="text-center" style="width: 30%;">
                <p>Bekasi, <?php echo date('d F Y'); ?></p>
                <p style="margin-bottom: 70px;">Owner SpotLight Studio</p>
                <p class="text-bold" style="text-decoration: underline;"><?php echo htmlspecialchars($nama_owner); ?></p>
            </td>
        </tr>
    </table>

</body>
</html>