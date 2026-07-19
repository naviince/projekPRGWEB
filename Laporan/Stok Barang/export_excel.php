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

// --- ATUR HEADER AGAR BISA DIDOWNLOAD SEBAGAI EXCEL ---
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Stok_Barang_Cetak_" . $tgl_mulai . "_ke_" . $tgl_selesai . ".xls");

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
<div align="center">
    <h2>LAPORAN PERSERDIAAN BARANG CETAK & ASSET INVENTARIS</h2>
    <h3>SPOTLIGHT PHOTO STUDIO</h3>
    <p>Periode Terjual: <?php echo date('d M Y', strtotime($tgl_mulai)); ?> s.d <?php echo date('d M Y', strtotime($tgl_selesai)); ?></p>
</div>

<table border="1" cellpadding="5" cellspacing="0" style="font-family: Arial, sans-serif; border-collapse: collapse; width:100%;">
    <thead>
        <tr style="background-color: #d83f67; color: white; font-weight: bold;">
            <th>ID Barang</th>
            <th>Nama Barang Cetak</th>
            <th>Harga Jual Satuan (Rp)</th>
            <th>Stok Saat Ini</th>
            <th>Stok Minimum</th>
            <th>Unit Terjual (Periode)</th>
            <th>Status Sisa Persediaan</th>
            <th>Total Nilai Aset Fisik (Rp)</th>
            <th>Total Pendapatan Terjual (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($query_laporan):
        while ($row = sqlsrv_fetch_array($query_laporan, SQLSRV_FETCH_ASSOC)):
            $stok = (int)$row['Stok_Barang'];
            $min = (int)$row['Stok_Minimum'];
            $nilai_persediaan = $stok * (float)$row['Harga_Barang'];

            if ($stok === 0) {
                $status_label = "Habis";
            } elseif ($stok <= $min) {
                $status_label = "Stok Menipis";
            } else {
                $status_label = "Stok Aman";
            }
        ?>
        <tr>
            <td align="center">#BRG-<?php echo str_pad($row['ID_Barang'], 3, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo htmlspecialchars($row['Nama_Barang']); ?></td>
            <td align="right"><?php echo number_format($row['Harga_Barang'], 0, ',', '.'); ?></td>
            <td align="center" style="font-weight: bold;"><?php echo $stok; ?> Unit</td>
            <td align="center"><?php echo $min; ?> Unit</td>
            <td align="center"><?php echo $row['Total_Terjual']; ?> Unit</td>
            <td align="center" style="font-weight: bold; color: <?php echo ($stok <= $min) ? 'orange' : 'green'; ?>;"><?php echo $status_label; ?></td>
            <td align="right" style="font-weight: bold; color: blue;"><?php echo number_format($nilai_persediaan, 0, ',', '.'); ?></td>
            <td align="right" style="font-weight: bold; color: green;"><?php echo number_format($row['Total_Pendapatan'], 0, ',', '.'); ?></td>
        </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>

<table style="width: 100%; border: none; margin-top: 30px;">
    <tr>
        <td style="width: 70%; border: none;"></td>
        <td align="center" style="width: 30%; border: none; font-family: sans-serif; font-size: 12px;">
            <p>Bekasi, <?php echo date('d F Y'); ?></p>
            <p style="margin-bottom: 50px;">Owner SpotLight Studio</p>
            <p style="text-decoration: underline; font-weight: bold;"><?php echo htmlspecialchars($nama_owner); ?></p>
        </td>
    </tr>
</table>