<?php
session_start();

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

// --- ATUR HEADER AGAR BISA DIDOWNLOAD SEBAGAI EXCEL ---
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Paket_Terfavorit_" . $tgl_mulai . "_ke_" . $tgl_selesai . ".xls");

// Ambil total seluruh booking untuk persentase
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
<div align="center">
    <h2>LAPORAN PERFORMANCE PAKET TERFAVORIT</h2>
    <h3>SPOTLIGHT PHOTO STUDIO</h3>
    <p>Periode Tanggal: <?php echo date('d M Y', strtotime($tgl_mulai)); ?> s.d <?php echo date('d M Y', strtotime($tgl_selesai)); ?></p>
</div>

<table border="1" cellpadding="5" cellspacing="0" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; border-collapse: collapse; width:100%;">
    <thead>
        <tr style="background-color: #d83f67; color: white; font-weight: bold;">
            <th>Peringkat</th>
            <th>ID Paket</th>
            <th>Nama Paket</th>
            <th>Durasi Sesi</th>
            <th>Kapasitas Sesi</th>
            <th>Harga Paket (Rp)</th>
            <th>Jumlah Booking</th>
            <th>Kontribusi</th>
            <th>Rata-rata Rating</th>
            <th>Jumlah Batal</th>
            <th>Estimasi Omzet (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($query_laporan):
        $rank = 1;
        while ($row = sqlsrv_fetch_array($query_laporan, SQLSRV_FETCH_ASSOC)):
            $kontribusi = $total_seluruh_booking > 0 ? ($row['Jumlah_Booking'] / $total_seluruh_booking) * 100 : 0;
            $rating_val = isset($row['Rata_Rata_Rating']) ? number_format((float)$row['Rata_Rata_Rating'], 1) : 'Belum Ada';
        ?>
        <tr>
            <td align="center">#<?php echo $rank++; ?></td>
            <td align="center">PKT-<?php echo str_pad($row['ID_Paket'], 3, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo htmlspecialchars($row['Nama_Paket']); ?></td>
            <td align="center"><?php echo $row['Durasi_Waktu']; ?> Menit</td>
            <td align="center"><?php echo $row['Kapasitas_Orang']; ?> Orang</td>
            <td align="right"><?php echo number_format($row['Harga_Paket'], 0, ',', '.'); ?></td>
            <td align="center"><?php echo $row['Jumlah_Booking']; ?> Sesi</td>
            <td align="center"><?php echo number_format($kontribusi, 1); ?>%</td>
            <td align="center"><?php echo $rating_val; ?></td>
            <td align="center" style="color: red;"><?php echo $row['Jumlah_Dibatalkan']; ?> Sesi</td>
            <td align="right" style="font-weight: bold; color: green;"><?php echo number_format($row['Estimasi_Omzet'], 0, ',', '.'); ?></td>
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