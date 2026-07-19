<?php
session_start();

// --- PROTEKSI HALAMAN: HANYA OWNER ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Owner') {
    die("Akses ditolak.");
}

include '../../koneksi.php';

$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// =====================================================
// AMBIL DATA PROFIL OWNER (MEMPERBAIKI BUG UNDEFINED VARIABLE)
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

// Ambil total booking
$sql_total = "SELECT COUNT(*) AS total FROM [Order] WHERE Status = 1 AND Status_Order <> 4 AND CAST(Tanggal_Booking AS DATE) BETWEEN ? AND ?";
$stmt_total = sqlsrv_query($conn, $sql_total, [$tgl_mulai, $tgl_selesai]);
$row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC);
$total_seluruh_booking = $row_total['total'] ?? 0;

$sql_laporan = "SELECT 
                    pk.ID_Paket,
                    pk.Nama_Paket,
                    pk.Harga_Paket,
                    pk.Durasi_Waktu,
                    pk.Kapasitas_Orang,
                    COUNT(o.ID_Order) AS Jumlah_Booking,
                    SUM(CASE WHEN o.Status_Order <> 4 THEN o.Total_Paket ELSE 0 END) AS Estimasi_Omzet,
                    AVG(CAST(o.Rating AS DECIMAL(3,2))) AS Rata_Rata_Rating,
                    COUNT(CASE WHEN o.Status_Order = 4 THEN 1 END) AS Jumlah_Dibatalkan
                FROM Paket_Foto pk
                LEFT JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
                    AND o.Status = 1 
                    AND CAST(o.Tanggal_Booking AS DATE) BETWEEN ? AND ?
                WHERE pk.Status = 1 AND pk.Is_Deleted = 0
                GROUP BY pk.ID_Paket, pk.Nama_Paket, pk.Harga_Paket, pk.Durasi_Waktu, pk.Kapasitas_Orang
                ORDER BY Jumlah_Booking DESC, Estimasi_Omzet DESC";

$query_laporan = sqlsrv_query($conn, $sql_laporan, [$tgl_mulai, $tgl_selesai]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Laporan Paket Terfavorit</title>
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
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 10.5px; }
    .data-table th { background-color: #d83f67; color: white; padding: 10px 12px; font-weight: bold; text-transform: uppercase; border: 1px solid #d83f67; text-align: left; }
    .data-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; }
    .data-table tr:nth-child(even) { background-color: #fdfafb; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }
    
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
                Laporan Performa Paket Terfavorit
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
                <td style="text-align:right;"><strong>Total Sesi Terlaksana:</strong> <?php echo $total_seluruh_booking; ?> Sesi</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 50px;">Rank</th>
                <th>Nama Paket Foto</th>
                <th class="text-center">Durasi</th>
                <th class="text-center">Kapasitas</th>
                <th class="text-right">Harga Paket</th>
                <th class="text-center">Jumlah Booking</th>
                <th class="text-center">Kontribusi</th>
                <th class="text-center">Rating Rata-rata</th>
                <th class="text-center">Jumlah Batal</th>
                <th class="text-right">Estimasi Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($query_laporan && sqlsrv_has_rows($query_laporan)):
            $rank = 1;
            while ($row = sqlsrv_fetch_array($query_laporan, SQLSRV_FETCH_ASSOC)):
                $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
                $rating_val = isset($row['Rata_Rata_Rating']) ? number_format((float)$row['Rata_Rata_Rating'], 1) : '-';
            ?>
            <tr>
                <td class="text-center text-bold" style="color:#d83f67;">#<?php echo $rank++; ?></td>
                <td class="text-bold"><?php echo htmlspecialchars($row['Nama_Paket']); ?></td>
                <td class="text-center"><?php echo $row['Durasi_Waktu']; ?> Menit</td>
                <td class="text-center">Max <?php echo $row['Kapasitas_Orang']; ?> Orang</td>
                <td class="text-right">Rp <?php echo number_format($row['Harga_Paket'], 0, ',', '.'); ?></td>
                <td class="text-center text-bold"><?php echo $row['Jumlah_Booking']; ?> Sesi</td>
                <td class="text-center text-bold"><?php echo number_format($kontribusi, 1); ?>%</td>
                <td class="text-center text-bold">
                    <?php if ($rating_val != '-'): ?>
                        ★ <?php echo $rating_val; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-center" style="color: #dc2626;"><?php echo $row['Jumlah_Dibatalkan']; ?> Sesi</td>
                <td class="text-right text-bold" style="color:#059669;">Rp <?php echo number_format($row['Estimasi_Omzet'], 0, ',', '.'); ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="10" class="text-center">Tidak ada data untuk periode ini.</td>
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