<?php
session_start();
include '../../koneksi.php';

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    header("Location: ../../login.php");
    exit();
}

// =====================================================
// FILTER PARAMETER
// =====================================================
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$filter_tipe = isset($_GET['filter_tipe']) ? $_GET['filter_tipe'] : 'semua';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'semua';

// =====================================================
// QUERY DATA
// =====================================================
$sql_data = "";
$params = array();

if ($filter_tipe == 'semua' || $filter_tipe == 'dp' || $filter_tipe == 'pelunasan') {
    $sql_data .= "
        SELECT 
            p.ID_Pembayaran as id_transaksi,
            p.Tanggal_Upload as tanggal,
            o.ID_Order,
            pl.Nama_Pelanggan,
            pk.Nama_Paket,
            p.Tipe_Pembayaran as tipe,
            p.Metode_Pembayaran as metode,
            p.Jumlah_Bayar as jumlah,
            p.Status_Pembayaran as status,
            k.Nama_Karyawan as verifikator,
            'Pembayaran' as sumber
        FROM Pembayaran p
        INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
        INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
        INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
        LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan
        WHERE p.Status = 1
        AND CAST(p.Tanggal_Upload AS DATE) BETWEEN ? AND ?
    ";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;

    if ($filter_tipe == 'dp') {
        $sql_data .= " AND p.Tipe_Pembayaran = 'DP'";
    } elseif ($filter_tipe == 'pelunasan') {
        $sql_data .= " AND p.Tipe_Pembayaran = 'Pelunasan'";
    }

    if ($filter_status != 'semua') {
        $status_map = ['menunggu' => 0, 'valid' => 1, 'ditolak' => 2];
        $sql_data .= " AND p.Status_Pembayaran = " . $status_map[$filter_status];
    }
}

if ($filter_tipe == 'semua' || $filter_tipe == 'barang') {
    if (!empty($sql_data)) {
        $sql_data .= " UNION ALL ";
    }
    $sql_data .= "
        SELECT 
            pe.ID_Penjualan as id_transaksi,
            pe.Tanggal_Penjualan as tanggal,
            pe.ID_Order,
            pl.Nama_Pelanggan,
            'Barang Cetak' as Nama_Paket,
            'Penjualan' as tipe,
            'Kasir' as metode,
            pe.Total_Penjualan as jumlah,
            pe.Status_Penjualan as status,
            k.Nama_Karyawan as verifikator,
            'Barang_Cetak' as sumber
        FROM Penjualan pe
        INNER JOIN [Order] o ON pe.ID_Order = o.ID_Order
        INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
        LEFT JOIN Karyawan k ON pe.ID_Karyawan_Admin = k.ID_Karyawan
        WHERE pe.Status = 1
        AND CAST(pe.Tanggal_Penjualan AS DATE) BETWEEN ? AND ?
    ";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;

    if ($filter_status != 'semua') {
        $status_map = ['menunggu' => 0, 'valid' => 1, 'ditolak' => 2];
        $sql_data .= " AND pe.Status_Penjualan = " . $status_map[$filter_status];
    }
}

$sql_data .= " ORDER BY tanggal DESC";

$query = sqlsrv_query($conn, $sql_data, $params);

// =====================================================
// HITUNG TOTAL
// =====================================================
$total_dp = 0; $total_pelunasan = 0; $total_barang = 0;

$q_dp = sqlsrv_query($conn, "SELECT SUM(Jumlah_Bayar) as total FROM Pembayaran WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'DP' AND CAST(Tanggal_Upload AS DATE) BETWEEN ? AND ?", array($tgl_mulai, $tgl_selesai));
$d_dp = sqlsrv_fetch_array($q_dp, SQLSRV_FETCH_ASSOC);
$total_dp = $d_dp['total'] ?? 0;

$q_pel = sqlsrv_query($conn, "SELECT SUM(Jumlah_Bayar) as total FROM Pembayaran WHERE Status_Pembayaran = 1 AND Tipe_Pembayaran = 'Pelunasan' AND CAST(Tanggal_Upload AS DATE) BETWEEN ? AND ?", array($tgl_mulai, $tgl_selesai));
$d_pel = sqlsrv_fetch_array($q_pel, SQLSRV_FETCH_ASSOC);
$total_pelunasan = $d_pel['total'] ?? 0;

$q_brg = sqlsrv_query($conn, "SELECT SUM(Total_Penjualan) as total FROM Penjualan WHERE Status_Penjualan = 1 AND CAST(Tanggal_Penjualan AS DATE) BETWEEN ? AND ?", array($tgl_mulai, $tgl_selesai));
$d_brg = sqlsrv_fetch_array($q_brg, SQLSRV_FETCH_ASSOC);
$total_barang = $d_brg['total'] ?? 0;

$grand_total = $total_dp + $total_pelunasan + $total_barang;

function getStatusLabel($status, $sumber) {
    if ($sumber == 'Barang_Cetak') {
        $map = [0 => 'Proses', 1 => 'Selesai'];
    } else {
        $map = [0 => 'Menunggu', 1 => 'Valid', 2 => 'Ditolak'];
    }
    return $map[$status] ?? 'Unknown';
}

function getTipeLabel($tipe) {
    $map = ['DP' => 'Uang Muka', 'Pelunasan' => 'Pelunasan', 'Penjualan' => 'Barang Cetak'];
    return $map[$tipe] ?? $tipe;
}

// =====================================================
// GENERATE EXCEL (CSV FORMAT)
// =====================================================
$filename = 'Laporan_Pendapatan_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Info
fputcsv($output, ['SpotLight Studio - Laporan Pendapatan']);
fputcsv($output, ['Periode: ' . date('d M Y', strtotime($tgl_mulai)) . ' s/d ' . date('d M Y', strtotime($tgl_selesai))]);
fputcsv($output, ['Dicetak: ' . date('d M Y H:i:s')]);
fputcsv($output, []);

// Summary
fputcsv($output, ['RINGKASAN PENDAPATAN']);
fputcsv($output, ['Total Uang Muka (DP)', 'Rp ' . number_format($total_dp, 0, ',', '.')]);
fputcsv($output, ['Total Pelunasan', 'Rp ' . number_format($total_pelunasan, 0, ',', '.')]);
fputcsv($output, ['Total Barang Cetak', 'Rp ' . number_format($total_barang, 0, ',', '.')]);
fputcsv($output, ['GRAND TOTAL', 'Rp ' . number_format($grand_total, 0, ',', '.')]);
fputcsv($output, []);

// Table Header
fputcsv($output, ['No', 'No. Transaksi', 'Tanggal', 'Pelanggan', 'Paket', 'Tipe', 'Metode', 'Jumlah (Rp)', 'Status', 'Verifikator']);

// Table Data
$no = 1;
if ($query && sqlsrv_has_rows($query)):
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
        $statusLabel = getStatusLabel((int)$row['status'], $row['sumber']);
        $tipeLabel = getTipeLabel($row['tipe']);
        $tanggal = (is_object($row['tanggal']) && method_exists($row['tanggal'], 'format')) 
            ? $row['tanggal']->format('d M Y H:i') 
            : date('d M Y H:i', strtotime($row['tanggal']));

        fputcsv($output, [
            $no++,
            '#' . str_pad((int)$row['id_transaksi'], 5, '0', STR_PAD_LEFT),
            $tanggal,
            $row['Nama_Pelanggan'],
            $row['Nama_Paket'],
            $tipeLabel,
            $row['metode'],
            number_format((float)$row['jumlah'], 0, ',', '.'),
            $statusLabel,
            $row['verifikator'] ?? '-'
        ]);
    endwhile;
endif;

fclose($output);
exit();
?>