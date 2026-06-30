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
header("Content-Disposition: attachment; filename=Laporan_Pembatalan_Studio_" . $tgl_mulai . "_ke_" . $tgl_selesai . ".xls");

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
<div align="center">
    <h2>LAPORAN PEMBATALAN ORDER & PENOLAKAN DP</h2>
    <h3>SPOTLIGHT PHOTO STUDIO</h3>
    <p>Periode Tanggal: <?php echo date('d M Y', strtotime($tgl_mulai)); ?> s.d <?php echo date('d M Y', strtotime($tgl_selesai)); ?></p>
</div>

<table border="1" cellpadding="5" cellspacing="0" style="font-family: Arial, sans-serif; border-collapse: collapse; width:100%;">
    <thead>
        <tr style="background-color: #d83f67; color: white; font-weight: bold;">
            <th>No. Order</th>
            <th>Tanggal Order</th>
            <th>Nama Customer</th>
            <th>No. HP</th>
            <th>Paket Foto</th>
            <th>Ruangan / Tema</th>
            <th>Jadwal Sesi</th>
            <th>Jumlah DP Diupload</th>
            <th>Metode Bayar</th>
            <th>Status Masalah</th>
            <th>Verifikator Admin</th>
            <th>Total Kerugian (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($query_detail):
        while ($row = sqlsrv_fetch_array($query_detail, SQLSRV_FETCH_ASSOC)):
            $jam_mulai = (is_object($row['Jam_Mulai']) && method_exists($row['Jam_Mulai'], 'format')) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
            $jam_selesai = (is_object($row['Jam_Selesai']) && method_exists($row['Jam_Selesai'], 'format')) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
            $status_text = ((int)$row['Status_Order'] === 4) ? "Order Dibatalkan" : "Bukti DP Ditolak";
            $tgl_jadwal_format = (is_object($row['Tanggal_Jadwal']) && method_exists($row['Tanggal_Jadwal'], 'format')) ? $row['Tanggal_Jadwal']->format('d M Y') : $row['Tanggal_Jadwal'];
            $tgl_booking_format = (is_object($row['Tanggal_Booking']) && method_exists($row['Tanggal_Booking'], 'format')) ? $row['Tanggal_Booking']->format('d M Y H:i') : date('d M Y H:i', strtotime($row['Tanggal_Booking']));
        ?>
        <tr>
            <td align="center">#ORD-<?php echo str_pad($row['ID_Order'], 5, '0', STR_PAD_LEFT); ?></td>
            <td align="center"><?php echo $tgl_booking_format; ?></td>
            <td><?php echo htmlspecialchars($row['Nama_Pelanggan']); ?></td>
            <td align="center"><?php echo htmlspecialchars($row['No_Hp']); ?></td>
            <td><?php echo htmlspecialchars($row['Nama_Paket']); ?></td>
            <td><?php echo htmlspecialchars($row['Nama_Ruangan'] . " - " . $row['Nama_Tema']); ?></td>
            <td align="center"><?php echo $tgl_jadwal_format . " (" . $jam_mulai . " - " . $jam_selesai . ")"; ?></td>
            <td align="right"><?php echo isset($row['ID_Pembayaran']) ? number_format($row['Jumlah_Bayar'], 0, ',', '.') : '0'; ?></td>
            <td align="center"><?php echo isset($row['ID_Pembayaran']) ? htmlspecialchars($row['Metode_Pembayaran']) : '-'; ?></td>
            <td align="center" style="font-weight: bold; color: #dc2626;"><?php echo $status_text; ?></td>
            <td align="center"><?php echo htmlspecialchars($row['Nama_Verifikator'] ?? '-'); ?></td>
            <td align="right" style="font-weight: bold; color: #dc2626;"><?php echo number_format($row['Total_Harga'], 0, ',', '.'); ?></td>
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