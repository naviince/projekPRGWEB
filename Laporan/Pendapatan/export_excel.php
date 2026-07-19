<?php
session_start();
include '../../koneksi.php';

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

// --- FILTER TANGGAL ---
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// =====================================================
// HEADER UNTUK DOWNLOAD EXCEL
// =====================================================
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=Laporan_Pendapatan_" . $tgl_mulai . "_to_" . $tgl_selesai . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

// =====================================================
// QUERY DATA PENDAPATAN
// =====================================================
$sql_detail = "
    SELECT 
        p.ID_Pembayaran,
        p.ID_Order,
        p.Jumlah_Bayar,
        p.Metode_Pembayaran,
        p.Tanggal_Upload,
        pl.Nama_Pelanggan,
        pl.No_Hp,
        pl.Email_Pelanggan,
        pk.Nama_Paket,
        r.Nama_Ruangan,
        t.Nama_Tema,
        o.Total_Paket,
        o.Total_Barang_Cetak,
        o.Total_Harga,
        o.Tanggal_Booking,
        k.Nama_Karyawan as Nama_Verifikator
    FROM Pembayaran p
    INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
    INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
    INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
    INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
    INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
    LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan
    WHERE p.Tipe_Pembayaran = 'Pelunasan' 
    AND p.Status_Pembayaran = ?
    AND p.Status = 1
    AND o.Status = 1
    AND o.Status_Order = ?
    AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?
    ORDER BY p.Tanggal_Upload DESC
";
$query = sqlsrv_query($conn, $sql_detail, [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, $tgl_mulai, $tgl_selesai]);

// Hitung total
$q_total = sqlsrv_query($conn, "
    SELECT SUM(p.Jumlah_Bayar) AS total, COUNT(*) AS jumlah
    FROM Pembayaran p
    INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
    WHERE p.Tipe_Pembayaran = 'Pelunasan' 
    AND p.Status_Pembayaran = ?
    AND p.Status = 1
    AND o.Status = 1
    AND o.Status_Order = ?
    AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?
", [STATUS_PEMBAYARAN_VALID, STATUS_ORDER_LUNAS, $tgl_mulai, $tgl_selesai]);
$d_total = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
$total_pendapatan = $d_total['total'] ?? 0;
$jumlah_order = $d_total['jumlah'] ?? 0;

// =====================================================
// OUTPUT HTML TABLE UNTUK EXCEL
// =====================================================
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'Calibri', Arial, sans-serif; }
    .header { text-align: center; margin-bottom: 20px; }
    .header h1 { color: #d83f67; font-size: 20px; margin-bottom: 5px; }
    .header p { color: #718096; font-size: 11px; margin: 0; }
    .info { font-size: 11px; margin-bottom: 15px; color: #1e1e24; }
    table { border-collapse: collapse; width: 100%; }
    th { background-color: #d83f67; color: white; font-weight: bold; padding: 10px 8px; text-align: left; font-size: 10px; border: 1px solid #c73165; }
    td { padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; vertical-align: middle; }
    tr:nth-child(even) { background-color: #fff8f0; }
    .total-row { background-color: #1e1e24 !important; color: white; font-weight: bold; }
    .total-row td { border: 1px solid #1e1e24; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .num { mso-number-format: "\#\,\#\#0"; }
    .currency { mso-number-format: "\"Rp\"\ #\,\#\#0"; }
</style>
</head>
<body>

<div class="header">
    <h1>SpotLight Studio Foto</h1>
    <p>Cikarang Pusat, Bekasi, Jawa Barat | Telepon: +62 878-7143-8459</p>
    <p style="font-size:14px; color:#1e1e24; font-weight:bold; margin-top:10px;">LAPORAN PENDAPATAN PELUNASAN</p>
    <p>Periode: <strong><?= date('d M Y', strtotime($tgl_mulai)) ?> s/d <?= date('d M Y', strtotime($tgl_selesai)) ?></strong></p>
</div>

<div class="info">
    <strong>Total Pendapatan:</strong> Rp <?= number_format($total_pendapatan, 0, ',', '.') ?> | 
    <strong>Jumlah Order:</strong> <?= $jumlah_order ?> | 
    <strong>Dicetak:</strong> <?= date('d M Y H:i') ?>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>No. Pembayaran</th>
            <th>No. Order</th>
            <th>Nama Pelanggan</th>
            <th>No. HP</th>
            <th>Email</th>
            <th>Paket Foto</th>
            <th>Ruangan</th>
            <th>Tema</th>
            <th>Metode Pembayaran</th>
            <th>Jumlah Bayar</th>
            <th>Tanggal Pelunasan</th>
            <th>Tanggal Booking</th>
            <th>Total Paket</th>
            <th>Total Barang Cetak</th>
            <th>Total Harga</th>
            <th>Verifikator</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if ($query && sqlsrv_has_rows($query)):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $tgl_upload = is_object($row['Tanggal_Upload']) && method_exists($row['Tanggal_Upload'], 'format') 
                    ? $row['Tanggal_Upload']->format('d-m-Y H:i') 
                    : date('d-m-Y H:i', strtotime($row['Tanggal_Upload']));
                $tgl_booking = is_object($row['Tanggal_Booking']) && method_exists($row['Tanggal_Booking'], 'format') 
                    ? $row['Tanggal_Booking']->format('d-m-Y H:i') 
                    : date('d-m-Y H:i', strtotime($row['Tanggal_Booking']));
        ?>
        <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td class="text-center"><?= str_pad((int)$row['ID_Pembayaran'], 5, '0', STR_PAD_LEFT) ?></td>
            <td class="text-center"><?= str_pad((int)$row['ID_Order'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($row['Nama_Pelanggan']) ?></td>
            <td><?= htmlspecialchars($row['No_Hp']) ?></td>
            <td><?= htmlspecialchars($row['Email_Pelanggan']) ?></td>
            <td><?= htmlspecialchars($row['Nama_Paket']) ?></td>
            <td><?= htmlspecialchars($row['Nama_Ruangan']) ?></td>
            <td><?= htmlspecialchars($row['Nama_Tema']) ?></td>
            <td><?= htmlspecialchars($row['Metode_Pembayaran']) ?></td>
            <td class="text-right">Rp <?= number_format((float)$row['Jumlah_Bayar'], 0, ',', '.') ?></td>
            <td class="text-center"><?= $tgl_upload ?></td>
            <td class="text-center"><?= $tgl_booking ?></td>
            <td class="text-right">Rp <?= number_format((float)$row['Total_Paket'], 0, ',', '.') ?></td>
            <td class="text-right">Rp <?= number_format((float)$row['Total_Barang_Cetak'], 0, ',', '.') ?></td>
            <td class="text-right">Rp <?= number_format((float)$row['Total_Harga'], 0, ',', '.') ?></td>
            <td><?= htmlspecialchars($row['Nama_Verifikator'] ?? 'System') ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="17" class="text-center" style="padding:30px;">Tidak ada data pada periode ini.</td></tr>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="10" class="text-right">TOTAL PENDAPATAN:</td>
            <td class="text-right">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></td>
            <td colspan="6"></td>
        </tr>
    </tbody>
</table>

<div style="margin-top:30px; text-align:right; font-size:11px;">
    <p>Bekasi, <?= date('d F Y') ?></p>
    <p style="font-weight:bold;">Owner SpotLight Studio</p>
    <br><br>
    <p style="font-weight:bold; border-top:1px solid #1e1e24; display:inline-block; padding-top:5px;">_________________________</p>
</div>

</body>
</html>