<?php
session_start();
include '../../../../koneksi.php';

// =====================================================
// KONSTANTA STATUS
// =====================================================
define('STATUS_DATA_AKTIF', 1);

// --- PROTEKSI HALAMAN ---
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login" || $_SESSION['role'] != 'Customer') {
    header("Location: ../../../../login.php");
    exit();
}

$id_customer = $_SESSION['id_user'];

// --- Profil ---
$default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d83f67'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";

$q_profile = sqlsrv_query($conn, 
    "SELECT Nama_Pelanggan, Foto_Profil FROM Pelanggan WHERE ID_Pelanggan = ? AND Is_Deleted = 0 AND Status = ?", 
    array($id_customer, STATUS_DATA_AKTIF)
);
$d_profile = sqlsrv_fetch_array($q_profile, SQLSRV_FETCH_ASSOC);
$nama_customer = $d_profile['Nama_Pelanggan'] ?? 'Customer';
$foto_customer = $d_profile['Foto_Profil'] ?? 'default.jpg';
$foto_customer_src = ($foto_customer != 'default.jpg' && file_exists("../../../../assets/img/pelanggan/" . $foto_customer)) 
    ? "../../../../assets/img/pelanggan/" . $foto_customer 
    : $default_svg_avatar;

// =====================================================
// AMBIL DATA KERANJANG DARI SESSION
// =====================================================
$keranjang = $_SESSION['keranjang_barang'] ?? [];
$barang_detail = [];
$total_keranjang = 0;
$total_item = 0;

if (!empty($keranjang)) {
    foreach ($keranjang as $id_barang => $item) {
        $q_barang = sqlsrv_query($conn, 
            "SELECT ID_Barang, Nama_Barang, Harga_Barang, Stok_Barang, Foto_Barang, Kategori_Barang 
             FROM Barang_Cetak WHERE ID_Barang = ? AND Is_Deleted = 0 AND Status = ?",
            array($id_barang, STATUS_DATA_AKTIF)
        );
        if ($q_barang !== false) {
            $d_barang = sqlsrv_fetch_array($q_barang, SQLSRV_FETCH_ASSOC);
            if ($d_barang) {
                $jumlah = (int)$item['jumlah'];
                $harga = (float)$d_barang['Harga_Barang'];
                $subtotal = $jumlah * $harga;
                $total_keranjang += $subtotal;
                $total_item += $jumlah;

                $barang_detail[] = [
                    'id_barang' => $id_barang,
                    'nama' => $d_barang['Nama_Barang'],
                    'harga' => $harga,
                    'harga_format' => number_format($harga, 0, ',', '.'),
                    'jumlah' => $jumlah,
                    'stok' => (int)$d_barang['Stok_Barang'],
                    'subtotal' => $subtotal,
                    'subtotal_format' => number_format($subtotal, 0, ',', '.'),
                    'foto' => $d_barang['Foto_Barang'],
                    'kategori' => $d_barang['Kategori_Barang']
                ];
            }
        }
    }
}

$total_keranjang_format = number_format($total_keranjang, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - SpotLight Studio</title>
    <link href="../../../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --p-pink: #d83f67;
            --d-pink: #c73165;
            --s-pink: #fff5f6;
            --light-pink: #ffe4e9;
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --text-muted: #718096;
            --body-bg: #f8fafc;
            --success: #059669;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
        }

        /* ===== NAVBAR ATAS (SAMA PERSIS) ===== */
        .top-navbar {
            background: #ffffff;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .nav-logo {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--p-pink);
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        .nav-logo span { color: var(--text-dark); font-weight: 700; font-size: 0.9rem; }
        .nav-menu-center {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        .nav-link-item {
            color: #4a5568;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 8px 0;
            position: relative;
        }
        .nav-link-item:hover, .nav-link-item.active {
            color: var(--p-pink);
        }
        .nav-link-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--p-pink);
            border-radius: 3px;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-btn-booking {
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #fff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(216, 63, 103, 0.25);
        }
        .nav-btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.35);
            color: #fff;
        }
        .nav-avatar-wrapper {
            position: relative;
        }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-pink);
            cursor: pointer;
            transition: all 0.3s;
        }
        .nav-avatar:hover {
            transform: scale(1.1);
            border-color: var(--p-pink);
        }
        .nav-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 12px;
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid #f1f5f9;
        }
        .nav-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
        }
        .dropdown-item:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .dropdown-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 8px 0;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
        .dropdown-header {
            padding: 8px 16px;
            font-weight: 800;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        /* ===== MAIN CONTENT ===== */
        .main-container {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .page-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ===== KERANJANG SECTION ===== */
        .keranjang-section {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
        }

        /* ===== BARANG LIST ===== */
        .barang-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .barang-item {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        .barang-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            border-color: var(--light-pink);
        }
        .barang-img {
            width: 80px;
            height: 80px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid #f1f5f9;
            flex-shrink: 0;
        }
        .barang-img-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 14px;
            background: var(--s-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--p-pink);
            font-size: 2rem;
            flex-shrink: 0;
        }
        .barang-info {
            flex: 1;
            min-width: 0;
        }
        .barang-nama {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .barang-kategori {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--p-pink);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .barang-harga {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .barang-qty-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .qty-control {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
        }
        .qty-btn {
            width: 36px;
            height: 40px;
            border: none;
            background: #ffffff;
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qty-btn:hover {
            background: var(--s-pink);
            color: var(--p-pink);
        }
        .qty-input {
            width: 50px;
            height: 40px;
            border: none;
            background: #f8fafc;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-dark);
            -moz-appearance: textfield;
        }
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .barang-subtotal {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--text-dark);
            min-width: 120px;
            text-align: right;
        }
        .btn-hapus {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 2px solid #fee2e2;
            background: #fef2f2;
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .btn-hapus:hover {
            background: var(--danger);
            color: #ffffff;
            transform: scale(1.1);
        }

        /* ===== SUMMARY CARD ===== */
        .summary-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            position: sticky;
            top: 90px;
            height: fit-content;
        }
        .summary-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 0.95rem;
        }
        .summary-label {
            color: var(--text-muted);
            font-weight: 600;
        }
        .summary-value {
            font-weight: 700;
            color: var(--text-dark);
        }
        .summary-divider {
            height: 2px;
            background: #f1f5f9;
            margin: 16px 0;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
        }
        .summary-total-label {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
        }
        .summary-total-value {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--p-pink);
        }
        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink));
            color: #ffffff;
            border: none;
            padding: 16px;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            box-shadow: 0 8px 25px rgba(216, 63, 103, 0.25);
            text-decoration: none;
        }
        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(216, 63, 103, 0.35);
            color: #ffffff;
        }
        .btn-kembali {
            width: 100%;
            background: #ffffff;
            color: var(--text-muted);
            border: 2px solid #e2e8f0;
            padding: 14px;
            border-radius: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
            text-decoration: none;
        }
        .btn-kembali:hover {
            border-color: var(--p-pink);
            color: var(--p-pink);
            background: var(--s-pink);
        }

        /* ===== EMPTY STATE ===== */
        .empty-keranjang {
            text-align: center;
            padding: 80px 20px;
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
        }
        .empty-keranjang i {
            font-size: 5rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        .empty-keranjang h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .empty-keranjang p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .keranjang-section { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .main-container { padding: 20px; }
            .top-navbar { padding: 16px 20px; }
            .nav-menu-center { display: none; }
            .barang-item { flex-wrap: wrap; }
            .barang-subtotal { width: 100%; text-align: left; margin-top: 8px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="top-navbar">
        <a href="../../../index.php" class="nav-logo">
            SpotLight.<span>StudioFoto</span>
        </a>
        <div class="nav-menu-center">
            <a href="../../../index.php" class="nav-link-item">Dashboard</a>
            <a href="../../../Layanan/Paket/pilih_paket.php" class="nav-link-item">Booking Baru</a>
            <a href="../../../Riwayat/index.php" class="nav-link-item">Riwayat</a>
            <a href="index.php" class="nav-link-item">Barang Cetak</a>
            <a href="../../Hasil Foto/hasil_foto.php" class="nav-link-item">Hasil Foto</a>        
        </div>
        <div class="nav-right">
            <a href="../../../Layanan/Paket/pilih_paket.php" class="nav-btn-booking">
                <i class="bi bi-plus-lg"></i> Booking
            </a>
            <div class="nav-avatar-wrapper">
                <img src="<?= $foto_customer_src ?>" class="nav-avatar" alt="Profil" onclick="toggleDropdown()">
                <div class="nav-dropdown" id="navDropdown">
                    <div class="dropdown-header">Halo, <?= htmlspecialchars($nama_customer) ?></div>
                    <div class="dropdown-divider"></div>
                    <a href="../../../index.php" class="dropdown-item" onclick="return confirmLandingPage(event)">
                        <i class="bi bi-house-door"></i> Kembali ke Beranda
                    </a>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item logout" onclick="confirmLogout()">
                        <i class="bi bi-box-arrow-right"></i> Keluar Sistem
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-container">
        <div class="page-header">
            <div class="page-title"><i class="bi bi-cart-fill me-2" style="color:var(--p-pink)"></i>Keranjang Belanja</div>
            <div class="page-subtitle"><?= $total_item ?> item dalam keranjang Anda</div>
        </div>

        <?php if (empty($barang_detail)): ?>
        <!-- EMPTY STATE -->
        <div class="empty-keranjang">
            <i class="bi bi-cart-x"></i>
            <h3>Keranjang Kosong</h3>
            <p>Anda belum menambahkan barang cetak ke keranjang.</p>
            <a href="index.php" class="btn-checkout" style="max-width:300px;margin:0 auto;">
                <i class="bi bi-bag-fill"></i> Lihat Katalog Barang
            </a>
        </div>
        <?php else: ?>
        <!-- KERANJANG SECTION -->
        <div class="keranjang-section">
            <!-- Left: Barang List -->
            <div class="barang-list">
                <?php foreach ($barang_detail as $item): ?>
                <div class="barang-item" id="barang-<?= $item['id_barang'] ?>">
                    <?php 
                    $foto_path = "../../../../assets/img/barang/" . $item['foto'];
                    if ($item['foto'] != 'default_barang.jpg' && file_exists($foto_path)): 
                    ?>
                        <img src="<?= $foto_path ?>" class="barang-img" alt="<?= htmlspecialchars($item['nama']) ?>">
                    <?php else: ?>
                        <div class="barang-img-placeholder">
                            <i class="bi bi-printer-fill"></i>
                        </div>
                    <?php endif; ?>

                    <div class="barang-info">
                        <div class="barang-kategori"><?= htmlspecialchars($item['kategori']) ?></div>
                        <div class="barang-nama"><?= htmlspecialchars($item['nama']) ?></div>
                        <div class="barang-harga">Rp <?= $item['harga_format'] ?> / item</div>
                    </div>

                    <div class="barang-qty-section">
                        <div class="qty-control">
                            <button class="qty-btn" onclick="updateQty(<?= $item['id_barang'] ?>, -1, <?= $item['stok'] ?>)">-</button>
                            <input type="number" class="qty-input" id="qty-<?= $item['id_barang'] ?>" value="<?= $item['jumlah'] ?>" min="1" max="<?= $item['stok'] ?>" readonly>
                            <button class="qty-btn" onclick="updateQty(<?= $item['id_barang'] ?>, 1, <?= $item['stok'] ?>)">+</button>
                        </div>
                    </div>

                    <div class="barang-subtotal" id="subtotal-<?= $item['id_barang'] ?>">
                        Rp <?= $item['subtotal_format'] ?>
                    </div>

                    <button class="btn-hapus" onclick="hapusBarang(<?= $item['id_barang'] ?>, '<?= htmlspecialchars(addslashes($item['nama'])) ?>')">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Right: Summary -->
            <div class="summary-card">
                <div class="summary-title"><i class="bi bi-receipt me-2"></i>Ringkasan Belanja</div>

                <div class="summary-row">
                    <span class="summary-label">Total Item</span>
                    <span class="summary-value" id="total-item"><?= $total_item ?> item</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value" id="subtotal-all">Rp <?= $total_keranjang_format ?></span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-total">
                    <span class="summary-total-label">Total</span>
                    <span class="summary-total-value" id="total-all">Rp <?= $total_keranjang_format ?></span>
                </div>

                <button class="btn-checkout" onclick="checkout()">
                    <i class="bi bi-check2-circle"></i> Lanjut ke Pembayaran
                </button>
                <a href="index.php" class="btn-kembali">
                    <i class="bi bi-arrow-left"></i> Kembali Belanja
                </a>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script src="../../../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.nav-avatar-wrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('navDropdown').classList.remove('show');
            }
        });

        function confirmLandingPage(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Kembali ke Beranda?',
                text: 'Anda akan meninggalkan halaman customer dan kembali ke halaman utama.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Kembali',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../index.php';
                }
            });
            return false;
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin keluar dari SpotLight Studio?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../../../logout.php';
                }
            });
        }

        // Update qty
        function updateQty(idBarang, delta, stok) {
            const input = document.getElementById('qty-' + idBarang);
            let current = parseInt(input.value);
            let newQty = current + delta;

            if (newQty < 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jumlah Minimal',
                    text: 'Jumlah minimal adalah 1',
                    confirmButtonColor: '#d83f67'
                });
                return;
            }
            if (newQty > stok) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stok Tidak Cukup',
                    text: 'Stok tersisa hanya ' + stok + ' item',
                    confirmButtonColor: '#d83f67'
                });
                return;
            }

            // Kirim ke server
            fetch('update_keranjang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_barang=' + idBarang + '&jumlah=' + newQty
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = newQty;
                    document.getElementById('subtotal-' + idBarang).innerText = 'Rp ' + data.subtotal_format;
                    document.getElementById('subtotal-all').innerText = 'Rp ' + data.total_format;
                    document.getElementById('total-all').innerText = 'Rp ' + data.total_format;
                    document.getElementById('total-item').innerText = data.total_item + ' item';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message || 'Terjadi kesalahan',
                        confirmButtonColor: '#d83f67'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan sistem',
                    confirmButtonColor: '#d83f67'
                });
            });
        }

        // Hapus barang
        function hapusBarang(idBarang, namaBarang) {
            Swal.fire({
                title: 'Hapus Barang?',
                text: 'Apakah Anda yakin ingin menghapus "' + namaBarang + '" dari keranjang?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_keranjang.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id_barang=' + idBarang + '&hapus=1'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const item = document.getElementById('barang-' + idBarang);
                            if (item) item.remove();

                            document.getElementById('subtotal-all').innerText = 'Rp ' + data.total_format;
                            document.getElementById('total-all').innerText = 'Rp ' + data.total_format;
                            document.getElementById('total-item').innerText = data.total_item + ' item';

                            if (data.total_item === 0) {
                                location.reload();
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus',
                                text: 'Barang berhasil dihapus dari keranjang',
                                confirmButtonColor: '#d83f67',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: data.message || 'Terjadi kesalahan',
                                confirmButtonColor: '#d83f67'
                            });
                        }
                    });
                }
            });
        }

        // Checkout
        function checkout() {
            Swal.fire({
                title: 'Konfirmasi Checkout',
                text: 'Anda akan melanjutkan ke proses pembayaran. Lanjutkan?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d83f67',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'checkout.php';
                }
            });
        }
    </script>
</body>
</html>