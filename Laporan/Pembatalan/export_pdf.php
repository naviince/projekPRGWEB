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

// Ambil total seluruh booking yang batal
$sql_total = "SELECT COUNT(DISTINCT o.ID_Order) AS total 
              FROM [Order] o 
              LEFT JOIN Pembayaran p ON o.ID_Order = p.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1 
              WHERE o.Status = 1 AND (o.Status_Order = 4 OR p.Status_Pembayaran = 2)
                AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_total = sqlsrv_query($conn, $sql_total, [$tgl_mulai, $tgl_selesai]);
$row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
$total_batal = $row_total['total'] ?? 0;

$sql_detail = "SELECT 
                    o.ID_Order,
                    o.Tanggal_Booking,
                    o.Total_Harga,
                    o.Status_Order,
                    o.Keterangan AS Keterangan_Order,
                    p.Nama_Pelanggan,
                    p.No_Hp,
                    pk.Nama_Paket,
                    r.Nama_Ruangan,
                    t.Nama_Tema,
                    j.Tanggal_Jadwal,
                    j.Jam_Mulai,
                    j.Jam_Selesai,
                    pb.ID_Pembayaran,
                    pb.Jumlah_Bayar,
                    pb.Metode_Pembayaran,
                    pb.Bukti_Transfer,
                    pb.Status_Pembayaran,
                    pb.Tanggal_Upload,
                    k.Nama_Karyawan AS Nama_Verifikator
                FROM [Order] o
                INNER JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
                INNER JOIN Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
                INNER JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
                INNER JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
                INNER JOIN Jadwal_Studio j ON o.ID_Jadwal = j.ID_Jadwal
                LEFT JOIN Pembayaran pb ON o.ID_Order = pb.ID_Order AND pb.Tipe_Pembayaran = 'DP' AND pb.Status = 1
                LEFT JOIN Karyawan k ON pb.ID_Karyawan_Verifikator = k.ID_Karyawan
                WHERE o.Status = 1 AND (o.Status_Order = 4 OR pb.Status_Pembayaran = 2)
                  AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
                ORDER BY o.Tanggal_Booking DESC";

$query_detail = sqlsrv_query($conn, $sql_detail, [$tgl_mulai, $tgl_selesai]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Laporan Pembatalan</title>
<style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #333; line-height: 1.4; margin: 30px; }
    
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
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 9.5px; }
    .data-table th { background-color: #d83f67; color: white; padding: 10px 12px; font-weight: bold; text-transform: uppercase; border: 1px solid #d83f67; text-align: left; }
    .data-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; }
    .data-table tr:nth-child(even) { background-color: #fdfafb; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }
    .badge-batal { color: #dc2626; font-weight: bold; }
    .badge-ditolak { color: #d97706; font-weight: bold; }
    
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
                Laporan Kasus Pembatalan Booking
            </td>
        </tr>
    </table>

    <div class="report-meta">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:50%;"><strong>Periode Laporan:</strong> <?php echo date('d M Y', strtotime($tgl_mulai)) . ' - ' . date('d M Y', strtotime($tgl_selesai)); ?></td>
                <td style="width:50%; text-align:right;"><strong>Tanggal Cetak:</strong> <?php echo date('d M Y H:i'); ?> WIB</td>
            </tr>
            <tr>
                <td><strong>Dicetak Oleh:</strong> Owner System</td>
                <td style="text-align:right;"><strong>Total Sesi Batal:</strong> <?php echo $total_batal; ?> Sesi Gagal</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 70px;">No. Order</th>
                <th>Tanggal Order</th>
                <th>Nama Customer</th>
                <th>Layanan Paket & Sesi</th>
                <th>Jadwal Terpilih</th>
                <th class="text-right">DP Diupload</th>
                <th class="text-center">Status Masalah</th>
                <th>Verifikator</th>
                <th class="text-right">Potensi Omzet Hilang</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($query_detail && sqlsrv_has_rows($query_detail)):
            while ($row = sqlsrv_fetch_array($query_detail, SQLSRV_FETCH_ASSOC)):
                $jam_mulai = (is_object($row['Jam_Mulai']) && method_exists($row['Jam_Mulai'], 'format')) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
                $jam_selesai = (is_object($row['Jam_Selesai']) && method_exists($row['Jam_Selesai'], 'format')) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
                
                if ((int)$row['Status_Order'] === 4) {
                    $classText = "badge-batal";
                    $statusText = "Order Dibatalkan";
                } else {
                    $classText = "badge-ditolak";
                    $statusText = "Bukti DP Ditolak";
                }
                $tgl_jadwal_format = (is_object($row['Tanggal_Jadwal']) && method_exists($row['Tanggal_Jadwal'], 'format')) ? $row['Tanggal_Jadwal']->format('d M Y') : $row['Tanggal_Jadwal'];
                $tgl_booking_format = (is_object($row['Tanggal_Booking']) && method_exists($row['Tanggal_Booking'], 'format')) ? $row['Tanggal_Booking']->format('d M Y H:i') : date('d M Y H:i', strtotime($row['Tanggal_Booking']));
            ?>
            <tr>
                <td class="text-center text-bold">#ORD-<?php echo str_pad($row['ID_Order'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo $tgl_booking_format; ?></td>
                <td class="text-bold"><?php echo htmlspecialchars($row['Nama_Pelanggan']); ?> (<?php echo htmlspecialchars($row['No_Hp']); ?>)</td>
                <td><?php echo htmlspecialchars($row['Nama_Paket']); ?> <br><span style="font-size:8.5px; color:#718096;"><?php echo htmlspecialchars($row['Nama_Ruangan'] . " - " . $row['Nama_Tema']); ?></span></td>
                <td class="text-center"><?php echo $tgl_jadwal_format; ?><br><span style="font-size:8.5px; color:#718096;"><?php echo $jam_mulai . " - " . $jam_selesai; ?></span></td>
                <td class="text-right"><?php echo isset($row['ID_Pembayaran']) ? 'Rp ' . number_format($row['Jumlah_Bayar'], 0, ',', '.') : 'Belum Bayar'; ?></td>
                <td class="text-center <?php echo $classText; ?>"><?php echo $statusText; ?></td>
                <td><?php echo htmlspecialchars($row['Nama_Verifikator'] ?? '-'); ?></td>
                <td class="text-right text-bold" style="color: #dc2626;">Rp <?php echo number_format($row['Total_Harga'], 0, ',', '.'); ?></td>
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