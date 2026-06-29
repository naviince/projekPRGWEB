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
// GENERATE PDF DENGAN FPDF
// =====================================================
require('../../assets/vendor/fpdf/fpdf.php');

class PDF extends FPDF {
    function Header() {
        // Logo/Brand
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(216, 63, 103);
        $this->Cell(0, 10, 'SpotLight Studio', 0, 1, 'L');

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Laporan Pendapatan', 0, 1, 'L');
        $this->Cell(0, 6, 'Periode: ' . date('d M Y', strtotime($GLOBALS['tgl_mulai'])) . ' - ' . date('d M Y', strtotime($GLOBALS['tgl_selesai'])), 0, 1, 'L');
        $this->Ln(5);

        // Garis pemisah
        $this->SetDrawColor(216, 63, 103);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' | Dicetak pada ' . date('d M Y H:i'), 0, 0, 'C');
    }

    function SummaryRow($label, $value, $isTotal = false) {
        $this->SetFont('Arial', $isTotal ? 'B' : '', 10);
        $this->SetTextColor($isTotal ? 216 : 80, $isTotal ? 63 : 80, $isTotal ? 103 : 80);
        $this->Cell(100, 8, $label, 0, 0, 'L');
        $this->Cell(0, 8, 'Rp ' . number_format($value, 0, ',', '.'), 0, 1, 'R');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Summary Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(216, 63, 103);
$pdf->Cell(0, 10, 'Ringkasan Pendapatan', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SummaryRow('Total Uang Muka (DP)', $total_dp);
$pdf->SummaryRow('Total Pelunasan', $total_pelunasan);
$pdf->SummaryRow('Total Barang Cetak', $total_barang);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);
$pdf->SummaryRow('GRAND TOTAL', $grand_total, true);
$pdf->Ln(10);

// Table Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(216, 63, 103);
$pdf->Cell(0, 10, 'Detail Transaksi', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFillColor(216, 63, 103);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);

$headers = ['No', 'Tanggal', 'Pelanggan', 'Paket', 'Tipe', 'Metode', 'Jumlah', 'Status'];
$widths = [10, 30, 40, 35, 25, 25, 30, 25];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 10, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Table Data
$pdf->SetTextColor(50, 50, 50);
$pdf->SetFont('Arial', '', 8);
$fill = false;

$no = 1;
if ($query && sqlsrv_has_rows($query)):
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
        $statusLabel = getStatusLabel((int)$row['status'], $row['sumber']);
        $tipeLabel = getTipeLabel($row['tipe']);
        $tanggal = (is_object($row['tanggal']) && method_exists($row['tanggal'], 'format')) 
            ? $row['tanggal']->format('d M Y') 
            : date('d M Y', strtotime($row['tanggal']));

        $pdf->SetFillColor($fill ? 255 : 255, $fill ? 245 : 255, $fill ? 246 : 255);

        $pdf->Cell($widths[0], 8, $no++, 1, 0, 'C', true);
        $pdf->Cell($widths[1], 8, $tanggal, 1, 0, 'C', true);
        $pdf->Cell($widths[2], 8, substr($row['Nama_Pelanggan'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell($widths[3], 8, substr($row['Nama_Paket'], 0, 18), 1, 0, 'L', true);
        $pdf->Cell($widths[4], 8, $tipeLabel, 1, 0, 'C', true);
        $pdf->Cell($widths[5], 8, $row['metode'], 1, 0, 'C', true);
        $pdf->Cell($widths[6], 8, 'Rp ' . number_format((float)$row['jumlah'], 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell($widths[7], 8, $statusLabel, 1, 1, 'C', true);

        $fill = !$fill;
    endwhile;
else:
    $pdf->Cell(220, 20, 'Tidak ada data transaksi.', 1, 1, 'C');
endif;

// Output PDF
$pdf->Output('Laporan_Pendapatan_' . date('Ymd') . '.pdf', 'D');
exit();
?>