<?php
session_start();
include 'koneksi.php';

// Ambil Nama Pengguna & Tentukan Jalur Dashboard Secara Dinamis Sesuai Peran (Role)
$nama_user_nav = "";
$dashboard_link = "Role/Customer/index.php";

if (isset($_SESSION['status']) && $_SESSION['status'] == "login") {
    $id_nav = $_SESSION['id_user'];
    $role_nav = $_SESSION['role'];

    if ($role_nav == 'Customer') {
        $q_nav = sqlsrv_query($conn, "SELECT Username_Pelanggan as nama FROM Pelanggan WHERE ID_Pelanggan = ?", array($id_nav));
        $dashboard_link = "Role/Customer/index.php";
    } else {
        $q_nav = sqlsrv_query($conn, "SELECT Username_Karyawan as nama FROM Karyawan WHERE ID_Karyawan = ?", array($id_nav));
        if ($role_nav == 'Admin') $dashboard_link = "Role/Admin/index.php";
        elseif ($role_nav == 'Owner') $dashboard_link = "Role/Owner/index.php";
        elseif ($role_nav == 'Fotografer') $dashboard_link = "Role/Fotografer/index.php";
    }

    if ($q_nav) {
        $d_nav = sqlsrv_fetch_array($q_nav, SQLSRV_FETCH_ASSOC);
        $nama_user_nav = $d_nav['nama'] ?? $role_nav;
    }
}

// =====================================================
// QUERY PAKET FOTO (Status = 1, Is_Deleted = 0)
// =====================================================
$sql_paket = "SELECT * FROM Paket_Foto WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Paket ASC";
$query_paket = sqlsrv_query($conn, $sql_paket);

// =====================================================
// QUERY RUANGAN TERSEDIA HARI INI (UNTUK INTERNAL, TIDAK DITAMPILKAN DI LANDING)
// =====================================================
$tanggal_hari_ini = date('Y-m-d');
$hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_indo = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$timestamp = strtotime($tanggal_hari_ini);
$tanggal_display = $hari_indo[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $bulan_indo[date('n', $timestamp)-1] . ' ' . date('Y', $timestamp);

// JAM OPERASIONAL STUDIO
$jam_buka = "08:00";
$jam_tutup = "20:00";
$jam_operasional = "Senin - Minggu | " . $jam_buka . " - " . $jam_tutup . " WIB";
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>SpotLight Studio Foto - Abadikan Setiap Momen Berhargamu</title>

  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <style>
    :root {
      --primary-pink: #d83f67;
      --light-pink: #fff5f6;
      --peach-pink: #ffecef;
      --accent-pink: #ff6694;
      --pink-gradient: linear-gradient(135deg, #d83f67, #ff6694);
      --pink-gradient-hover: linear-gradient(135deg, #ff6694, #d83f67);
      --text-dark: #1e1e24;
      --text-muted: #6c757d;
      --footer-bg: #111215;
      --transition-3d: all 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); background-color: #ffffff; scroll-behavior: smooth; }

    .header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 236, 239, 0.6); padding: 12px 0; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.01); transition: var(--transition-3d); }
    .header:hover { box-shadow: 0 10px 30px rgba(216, 63, 103, 0.05); border-bottom-color: rgba(216, 63, 103, 0.2); }
    .navbar-logo { height: 55px; width: auto; transition: var(--transition-3d); }
    .navbar-logo:hover { transform: scale(1.05) rotate(-1deg); }

    .navmenu ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; align-items: center; }
    .navmenu ul li a { text-decoration: none; color: #4a5568; font-weight: 700; font-size: 0.95rem; position: relative; transition: 0.3s ease; }
    .navmenu ul li a:hover, .navmenu ul li a.active { color: var(--primary-pink); }
    .navmenu ul li a::after { content: ""; position: absolute; width: 0; height: 3px; bottom: -6px; left: 50%; background: var(--pink-gradient); border-radius: 50px; transition: 0.3s ease; transform: translateX(-50%); }
    .navmenu ul li a:hover::after, .navmenu ul li a.active::after { width: 15px; }

    /* BENDERA INDONESIA */
    .flag-id {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      overflow: hidden;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      border: 2px solid #ffffff;
      flex-shrink: 0;
      transition: var(--transition-3d);
    }
    .flag-id:hover { transform: scale(1.15) rotate(10deg); }
    .flag-id .merah { background: #ff0000; height: 50%; width: 100%; }
    .flag-id .putih { background: #ffffff; height: 50%; width: 100%; }

    .btn-masuk { color: var(--primary-pink) !important; font-weight: 700; padding: 10px 24px; border-radius: 12px; transition: var(--transition-3d); }
    .btn-masuk:hover { background: var(--light-pink); transform: translateY(-2px); }
    .btn-daftar { background: var(--pink-gradient); color: #ffffff !important; font-weight: 700; padding: 10px 28px; border-radius: 12px; transition: var(--transition-3d); border: none; box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15); }
    .btn-daftar:hover { background: var(--pink-gradient-hover); transform: translateY(-3px) scale(1.03); box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3); }

    .hero { padding: 110px 0; background: linear-gradient(180deg, #ffffff 0%, var(--light-pink) 100%); }
    .hero h1 { font-weight: 800; font-size: 3.8rem; color: var(--text-dark); line-height: 1.15; }
    .hero h1 span { background: var(--pink-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero-script { font-size: 1.25rem; color: var(--primary-pink); font-style: italic; font-weight: 600; }

    .hero-collage { position: relative; width: 100%; height: 460px; perspective: 1000px; }
    .hero-collage img { transition: var(--transition-3d); }
    .collage-img-1 { position: absolute; width: 60%; top: 0; left: 10%; z-index: 2; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.06); }
    .collage-img-2 { position: absolute; width: 45%; top: 15%; right: 0; z-index: 3; border-radius: 20px; border: 8px solid #ffffff; box-shadow: 0 20px 45px rgba(216, 63, 103, 0.15); }
    .collage-img-3 { position: absolute; width: 40%; bottom: 0; left: 0; z-index: 4; border-radius: 20px; border: 6px solid #ffffff; box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
    .hero-collage:hover .collage-img-1 { transform: translate3d(-10px, -5px, 20px) rotateY(-5deg); }
    .hero-collage:hover .collage-img-2 { transform: translate3d(15px, -10px, 40px) rotateY(8deg); box-shadow: 0 30px 60px rgba(216, 63, 103, 0.25); }
    .hero-collage:hover .collage-img-3 { transform: translate3d(-5px, 10px, 30px) rotateX(10deg); }
    .collage-tag { position: absolute; bottom: 5%; right: 8%; background: #ffffff; padding: 12px 24px; border-radius: 50px; box-shadow: 0 10px 25px rgba(216,63,103,0.1); z-index: 5; font-weight: 700; font-size: 0.9rem; color: var(--primary-pink); transition: var(--transition-3d); }
    .hero-collage:hover .collage-tag { transform: scale(1.08) translate3d(5px, -5px, 50px); }

    .stat-box { text-align: center; padding: 18px 10px; background: #ffffff; border-radius: 18px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border: 1px solid rgba(255, 236, 239, 0.8); transition: var(--transition-3d); }
    .stat-box:hover { transform: translateY(-5px) scale(1.05); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.08); border-color: var(--primary-pink); }
    .stat-num { font-size: 1.9rem; font-weight: 800; color: var(--primary-pink); }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

    .about-section { padding: 100px 0; background: #ffffff; }
    .feature-card { padding: 35px 30px; background: #ffffff; border-radius: 24px; border: 1px solid rgba(255, 236, 239, 0.8); transition: var(--transition-3d); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.01); }
    .feature-card:hover { transform: translateY(-12px) scale(1.02); box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12); border-color: var(--primary-pink); }
    .feature-icon-box { width: 65px; height: 65px; background: var(--light-pink); color: var(--primary-pink); display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 24px auto; font-size: 1.8rem; transition: var(--transition-3d); }
    .feature-card:hover .feature-icon-box { background: var(--pink-gradient); color: #ffffff; transform: scale(1.1) rotate(10deg); }

    .service-badge { padding: 16px 28px; background: var(--light-pink); border-radius: 50px; text-align: center; font-weight: 700; font-size: 0.95rem; color: var(--primary-pink); border: 1px solid #ffecef; display: inline-block; transition: var(--transition-3d); box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
    .service-badge:hover { background: var(--pink-gradient); color: #ffffff; transform: translateY(-5px) scale(1.05); box-shadow: 0 8px 20px rgba(216, 63, 103, 0.2); }

    /* PAKET SLIDER */
    .package-slider-container { display: flex; overflow-x: auto; gap: 30px; padding: 20px 10px 45px 10px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .package-slider-container::-webkit-scrollbar { display: none; }
    .package-item { flex: 0 0 360px; scroll-snap-align: start; }
    @media (max-width: 576px) { .package-item { flex: 0 0 88%; } }
    .pricing-card { background: #ffffff; border-radius: 26px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); height: 100%; transition: var(--transition-3d); border: 1px solid rgba(255, 236, 239, 0.8); display: flex; flex-direction: column; }
    .pricing-card:hover { transform: translateY(-15px) scale(1.02); box-shadow: 0 25px 50px rgba(216, 63, 103, 0.16); border-color: var(--primary-pink); }
    .card-img-box { height: 290px; overflow: hidden; }
    .card-img-box img { width: 100%; height: 100%; object-fit: cover; transition: var(--transition-3d); }
    .pricing-card:hover .card-img-box img { transform: scale(1.1); }
    .card-body-custom { padding: 30px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .price-label { color: var(--primary-pink); font-weight: 800; font-size: 1.8rem; margin-bottom: 15px; display: block; }
    .btn-pilih { background: var(--pink-gradient); color: #ffffff; width: 100%; padding: 14px; border-radius: 14px; border: none; font-weight: 700; text-align: center; text-decoration: none; display: block; transition: var(--transition-3d); box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15); }
    .btn-pilih:hover { background: var(--pink-gradient-hover); color: #ffffff; transform: translateY(-3px); box-shadow: 0 8px 22px rgba(216, 63, 103, 0.3); }

    /* JAM OPERASIONAL BADGE */
    .jam-operasional-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 24px;
      background: linear-gradient(135deg, #fff5f6, #ffecef);
      border-radius: 50px;
      border: 2px solid rgba(216, 63, 103, 0.15);
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--primary-pink);
      transition: var(--transition-3d);
      box-shadow: 0 4px 15px rgba(216, 63, 103, 0.08);
    }
    .jam-operasional-badge:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 8px 25px rgba(216, 63, 103, 0.15);
      border-color: var(--primary-pink);
    }
    .jam-operasional-badge i {
      font-size: 1.2rem;
      animation: pulse-clock 2s ease-in-out infinite;
    }
    @keyframes pulse-clock {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.15); }
    }

    /* PORTFOLIO */
    .portfolio-deck { position: relative; width: 100%; height: 480px; display: flex; justify-content: center; align-items: center; margin: 40px auto; perspective: 1200px; }
    .portfolio-card { position: absolute; width: 250px; height: 360px; border-radius: 24px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08); border: 6px solid #ffffff; overflow: hidden; transition: var(--transition-3d); transform-origin: bottom center; }
    .portfolio-card img { width: 100%; height: 100%; object-fit: cover; }
    .portfolio-card::after { content: ""; position: absolute; left: 0; right: 0; bottom: -60px; height: 60px; background: transparent; pointer-events: auto; }
    .c1 { z-index: 1; transform: rotate(-12deg) translate3d(-30px, 0, 0); }
    .c2 { z-index: 2; transform: rotate(-6deg) translate3d(-15px, 0, 0); }
    .c3 { z-index: 3; transform: rotate(0deg); }
    .c4 { z-index: 2; transform: rotate(6deg) translate3d(15px, 0, 0); }
    .c5 { z-index: 1; transform: rotate(12deg) translate3d(30px, 0, 0); }
    .portfolio-deck:hover .c1 { transform: rotate(-28deg) translate3d(-340px, -15px, 10px); }
    .portfolio-deck:hover .c2 { transform: rotate(-14deg) translate3d(-170px, -5px, 20px); }
    .portfolio-deck:hover .c3 { transform: rotate(0deg) translate3d(0, -10px, 30px) scale(1.05); }
    .portfolio-deck:hover .c4 { transform: rotate(14deg) translate3d(170px, -5px, 20px); }
    .portfolio-deck:hover .c5 { transform: rotate(28deg) translate3d(340px, -15px, 10px); }
    .portfolio-card:hover { transform: scale(1.08) translateY(-25px) rotate(0deg) !important; z-index: 99 !important; box-shadow: 0 25px 50px rgba(216, 63, 103, 0.28) !important; border-color: var(--primary-pink); }
    .gallery-img-box { overflow: hidden; border-radius: 24px; box-shadow: 0 8px 25px rgba(0,0,0,0.03); height: 100%; transition: var(--transition-3d); }
    .gallery-img-box img { width: 100%; height: 100%; object-fit: cover; transition: var(--transition-3d); }
    .gallery-img-box:hover { transform: translateY(-8px) scale(1.03); box-shadow: 0 15px 35px rgba(216, 63, 103, 0.15); }
    .gallery-img-box:hover img { transform: scale(1.06); }

    /* TESTIMONI */
    .testimonial-card { background: #ffffff; padding: 40px 35px; border-radius: 26px; border: 1px solid rgba(255, 236, 239, 0.8); box-shadow: 0 6px 25px rgba(0,0,0,0.01); height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: var(--transition-3d); }
    .testimonial-card:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(216, 63, 103, 0.08); border-color: var(--primary-pink); }
    .testi-text { font-size: 0.95rem; line-height: 1.8; color: #4a5568; font-style: italic; }
    .testi-stars { color: #ffb800; font-size: 1.1rem; margin-bottom: 15px; }

    /* CTA */
    .cta-banner { background: var(--pink-gradient); border-radius: 35px; padding: 70px 40px; color: #ffffff; box-shadow: 0 20px 45px rgba(216, 63, 103, 0.25); transition: var(--transition-3d); }
    .cta-banner:hover { transform: translateY(-5px); box-shadow: 0 25px 60px rgba(216, 63, 103, 0.35); }
    .btn-cta-light { background: #ffffff; color: var(--primary-pink); font-weight: 700; padding: 16px 38px; border-radius: 14px; text-decoration: none; transition: var(--transition-3d); display: inline-block; border: none; box-shadow: 0 8px 25px rgba(216, 63, 103, 0.1); }
    .btn-cta-light:hover { background: var(--peach-pink); transform: translateY(-3px) scale(1.05); box-shadow: 0 12px 30px rgba(216, 63, 103, 0.2); }

    /* NAVBAR PROFILE */
    .nav-profile { background: var(--light-pink); color: var(--primary-pink) !important; padding: 8px 20px !important; border-radius: 50px; display: inline-flex; align-items: center; font-weight: 700; text-decoration: none; transition: var(--transition-3d); }
    .nav-profile:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(216, 63, 103, 0.1); }

    /* FOOTER */
    .footer { background-color: var(--footer-bg); color: #e2e8f0; padding-top: 70px; border-top: none; }
    .footer-logo { height: 72px; width: auto; transition: var(--transition-3d); }
    .footer-logo:hover { transform: scale(1.05); }
    .footer-links ul li a { color: #a0aec0 !important; text-decoration: none; transition: 0.3s ease; }
    .footer-links ul li a:hover { color: var(--primary-pink) !important; padding-left: 6px; }
    .footer-social-links a { width: 42px; height: 42px; background: #1e2227; color: var(--primary-pink) !important; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 12px; transition: var(--transition-3d); text-decoration: none; }
    .footer-social-links a:hover { background: var(--primary-pink); color: #ffffff !important; transform: translateY(-5px); }
    .border-top-custom { border-top: 1px solid #232830 !important; }

    /* FOOTER JAM OPERASIONAL */
    .footer-jam-operasional {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 15px;
      padding: 10px 18px;
      background: rgba(216, 63, 103, 0.1);
      border-radius: 12px;
      border: 1px solid rgba(216, 63, 103, 0.2);
    }
    .footer-jam-operasional i {
      color: var(--primary-pink);
      font-size: 1.1rem;
    }
    .footer-jam-operasional span {
      color: #a0aec0;
      font-size: 0.85rem;
      font-weight: 600;
    }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <header id="header" class="header sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="index.php" class="d-flex align-items-center">
        <img src="assets/img/logo.png" class="navbar-logo" alt="SpotLight Studio Foto">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero">Beranda</a></li>
          <li><a href="#about">Tentang</a></li>
          <li><a href="#services">Layanan</a></li>
          <li><a href="#pricing">Paket</a></li>
          <!-- RUANGAN DIHAPUS DARI NAVBAR — sesuai alur bisnis: transaksi dimulai dari Paket Foto -->
          <li><a href="#portfolio">Galeri</a></li>
          <li><a href="#testimonials">Testimoni</a></li>

          <!-- BENDERA INDONESIA -->
          <li>
            <div class="flag-id" title="Bahasa Indonesia">
              <div style="display:flex; flex-direction:column; width:100%; height:100%;">
                <div class="merah"></div>
                <div class="putih"></div>
              </div>
            </div>
          </li>

          <?php if(isset($_SESSION['status']) && $_SESSION['status'] == "login"): ?>
            <?php
            $dashboard_link = "Role/Customer/index.php";
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] == 'Admin') $dashboard_link = "Role/Admin/index.php";
                elseif ($_SESSION['role'] == 'Owner') $dashboard_link = "Role/Owner/index.php";
                elseif ($_SESSION['role'] == 'Fotografer') $dashboard_link = "Role/Fotografer/index.php";
                else $dashboard_link = "Role/Customer/index.php";
            }
            ?>
            <li><a href="<?= $dashboard_link ?>" class="nav-profile"><i class="bi bi-person-circle me-2"></i><span><?= htmlspecialchars($nama_user_nav); ?></span></a></li>
            <li><a href="logout.php" class="btn btn-masuk py-2 px-3 border ms-2" style="border-color: var(--primary-pink) !important; color: var(--primary-pink); border-radius: 12px; font-weight: 700; font-size: 0.85rem;"><i class="bi bi-box-arrow-right me-1"></i>Keluar</a></li>
          <?php else: ?>
            <li><a href="login.php" class="btn-masuk">Masuk</a></li>
            <li><a href="login.php?aksi=daftar" class="btn-daftar">Daftar</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section id="hero" class="hero">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6" data-aos="fade-up">
            <span class="hero-script d-block mb-2">Abadikan setiap momen, ♡</span>
            <h1>Buat Kenanganmu<br>Jadi <span>Lebih Berarti</span></h1>
            <p class="mt-4 text-muted" style="font-size: 1.1rem; line-height: 1.6;">SpotLight Studio Foto siap membantumu mengabadikan momen berharga dengan hasil profesional dan pengalaman yang menyenangkan.</p>

            <!-- JAM OPERASIONAL BADGE -->
            <div class="mt-3 mb-4">
              <span class="jam-operasional-badge">
                <i class="bi bi-clock-fill"></i>
                <?= $jam_operasional; ?>
              </span>
            </div>

            <div class="d-flex gap-3 mt-4 mb-5">
              <a href="#pricing" class="btn btn-daftar px-4 py-3" style="border-radius: 15px;"><i class="bi bi-calendar-event me-2"></i>Pesan Sekarang</a>
              <a href="#portfolio" class="btn btn-masuk border px-4 py-3" style="border-radius: 15px; border-color: var(--primary-pink) !important;"><i class="bi bi-images me-2"></i>Lihat Galeri</a>
            </div>
            <div class="row g-3">
              <div class="col-3"><div class="stat-box"><div class="stat-num">500+</div><div class="stat-label">Pelanggan Puas</div></div></div>
              <div class="col-3"><div class="stat-box"><div class="stat-num">1000+</div><div class="stat-label">Momen Terekam</div></div></div>
              <div class="col-3"><div class="stat-box"><div class="stat-num">5+</div><div class="stat-label">Tahun Pengalaman</div></div></div>
              <div class="col-3"><div class="stat-box"><div class="stat-num">4.9</div><div class="stat-label">Rating Pelanggan</div></div></div>
            </div>
          </div>
          <div class="col-lg-6 mt-5 mt-lg-0" data-aos="zoom-in">
            <div class="hero-collage">
              <img src="assets/img/Landing/foto1.png" class="collage-img-1" alt="Studio Foto">
              <img src="assets/img/Landing/foto2.png" class="collage-img-2" alt="Sesi Pemotretan">
              <img src="assets/img/Landing/foto3.png" class="collage-img-3" alt="Dekorasi Studio">
              <div class="collage-tag">Foto terbaik, kenangan terindah ♡</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ABOUT -->
    <section id="about" class="about-section">
      <div class="container text-center mb-5" data-aos="fade-up">
        <span class="badge px-3 py-2 mb-2 text-uppercase" style="background: var(--light-pink); color: var(--primary-pink); font-weight: 800; border-radius: 50px;">Kenapa Memilih Kami?</span>
        <h2 class="fw-bold" style="font-size: 2.5rem;">Studio Nyaman, Hasil Memukau</h2>
      </div>
      <div class="container">
        <div class="row g-4 justify-content-center">
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100"><div class="feature-card"><div class="feature-icon-box"><i class="bi bi-camera"></i></div><h5 class="fw-bold">Peralatan Modern</h5><p class="text-muted small mb-0">Peralatan fotografi lengkap dan terbaru untuk menjamin hasil gambar berkualitas tinggi.</p></div></div>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200"><div class="feature-card"><div class="feature-icon-box"><i class="bi bi-people"></i></div><h5 class="fw-bold">Fotografer Profesional</h5><p class="text-muted small mb-0">Tim berpengalaman yang siap membantumu mengarahkan gaya terbaik di setiap pemotretan.</p></div></div>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300"><div class="feature-card"><div class="feature-icon-box"><i class="bi bi-stars"></i></div><h5 class="fw-bold">Studio Nyaman</h5><p class="text-muted small mb-0">Ruangan ber-AC yang bersih, estetik, dan nyaman untuk mendukung kelancaran konsep fotomu.</p></div></div>
          <div class="col-lg-4 col-md-6 mt-lg-4" data-aos="fade-up" data-aos-delay="400"><div class="feature-card"><div class="feature-icon-box"><i class="bi bi-bag-heart"></i></div><h5 class="fw-bold">Properti Lengkap</h5><p class="text-muted small mb-0">Tersedia beragam aksesoris menarik dan latar belakang estetik sesuai tema pilihanmu.</p></div></div>
          <div class="col-lg-4 col-md-6 mt-lg-4" data-aos="fade-up" data-aos-delay="550"><div class="feature-card"><div class="feature-icon-box"><i class="bi bi-emoji-smile"></i></div><h5 class="fw-bold">Pelayanan Ramah</h5><p class="text-muted small mb-0">Kepuasan dan kenyamanan Anda dari awal hingga akhir proses adalah prioritas utama kami.</p></div></div>
        </div>
      </div>
    </section>

    <!-- LAYANAN -->
    <section id="services" class="py-5" style="background: var(--light-pink);">
      <div class="container text-center py-4">
        <h3 class="fw-bold mb-4" style="color: var(--text-dark);">Layanan Kami</h3>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <div class="service-badge"><i class="bi bi-camera-fill me-2"></i>Foto Studio</div>
          <div class="service-badge"><i class="bi bi-suit-heart-fill me-2"></i>Prewedding</div>
          <div class="service-badge"><i class="bi bi-people-fill me-2"></i>Family Portrait</div>
          <div class="service-badge"><i class="bi bi-balloon-fill me-2"></i>Baby & Kids</div>
          <div class="service-badge"><i class="bi bi-mortarboard-fill me-2"></i>Graduation</div>
          <div class="service-badge"><i class="bi bi-calendar-event-fill me-2"></i>Event</div>
          <div class="service-badge"><i class="bi bi-person-badge-fill me-2"></i>Personal Branding</div>
          <div class="service-badge"><i class="bi bi-plus-circle-fill me-2"></i>Dan Lainnya</div>
        </div>
      </div>
    </section>

    <!-- PAKET FOTO - OTOMATIS DARI DATABASE -->
    <section id="pricing" class="container py-5">
      <div class="d-flex justify-content-between align-items-end mb-5" data-aos="fade-up">
        <div>
          <span class="text-uppercase small fw-bold" style="color: var(--primary-pink); letter-spacing: 1px;">Paket Pilihan</span>
          <h2 class="fw-bold mb-0" style="font-size: 2.5rem;">Paket Populer ♡</h2>
          <p class="text-muted mt-2 mb-0">Pilih paket sesuai kebutuhanmu, ruangan akan ditampilkan setelah memilih paket.</p>
        </div>
        <a href="#pricing" class="btn border px-4 py-2" style="border-radius: 12px; color: var(--primary-pink); border-color: var(--primary-pink) !important; font-weight: 700;">Lihat Semua Paket</a>
      </div>

      <div class="package-slider-container" data-aos="fade-up">
        <?php
        while($row = sqlsrv_fetch_array($query_paket, SQLSRV_FETCH_ASSOC)):
            $nama_p = $row['Nama_Paket'];

            // GAMBAR OTOMATIS DARI DATABASE
            $path_img = "assets/img/paket/" . ($row['Foto_Paket'] ?? '');
            $img_display = (!empty($row['Foto_Paket']) && file_exists($path_img))
                ? $path_img 
                : "assets/img/Landing/foto1.png";
        ?>
            <div class="package-item">
              <div class="pricing-card">
                <div class="card-img-box">
                    <img src="<?= $img_display ?>" alt="<?= htmlspecialchars($nama_p) ?>">
                </div>
                <div class="card-body-custom">
                  <div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($nama_p); ?></h4>
                    <span class="price-label">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.'); ?></span>
                    <ul class="list-unstyled mb-4 small fw-bold text-muted" style="line-height: 2;">
                      <li><i class="bi bi-clock text-danger me-2"></i>Durasi <?= $row['Durasi_Waktu']; ?> Menit</li>
                      <li><i class="bi bi-people-fill text-danger me-2"></i>Kapasitas Maksimal <?= $row['Kapasitas_Orang']; ?> Orang</li>
                      <li class="fw-normal text-muted fst-italic mt-2"><i class="bi bi-info-circle text-danger me-2"></i><?= htmlspecialchars($row['Deskripsi'] ?? ''); ?></li>
                    </ul>
                  </div>
                  <a href="<?= isset($_SESSION['status']) ? 'Transaksi/booking.php' : 'login.php'; ?>" class="btn-pilih">Pilih Paket</a>
                </div>
              </div>
            </div>
        <?php endwhile; ?>
      </div>
    </section>

    <!-- RUANGAN STUDIO - DIHAPUS DARI LANDING PAGE -->
    <!-- Ruangan akan ditampilkan setelah user memilih Paket Foto di halaman booking -->
    <!-- Sesuai alur bisnis: Landing Page → Pilih Paket → Pilih Ruangan (validasi Paket_Ruangan) -->

    <!-- PORTFOLIO -->
    <section id="portfolio" class="container py-5 overflow-hidden">
      <div class="text-center mb-5" data-aos="fade-up">
        <h2 class="fw-bold" style="font-size: 2.5rem;">Galeri Portfolio</h2>
        <p class="text-muted">Arahkan kursor Anda ke tumpukan kartu di bawah ini untuk menyebarkan portofolio terbaik kami.</p>
      </div>
      <div class="portfolio-deck d-none d-lg-flex" data-aos="fade-up">
        <div class="portfolio-card c1"><img src="assets/img/Landing/foto7.png" alt="Foto Pernikahan"></div>
        <div class="portfolio-card c2"><img src="assets/img/Landing/foto8.png" alt="Foto Bayi"></div>
        <div class="portfolio-card c3"><img src="assets/img/Landing/foto9.png" alt="Foto Wisuda"></div>
        <div class="portfolio-card c4"><img src="assets/img/Landing/foto10.png" alt="Foto Anak Kecil"></div>
        <div class="portfolio-card c5"><img src="assets/img/Landing/foto6.png" alt="Foto Keluarga"></div>
      </div>
      <div class="row g-4 d-flex d-lg-none" data-aos="fade-up">
        <div class="col-md-4 col-6"><div class="gallery-img-box" style="height: 300px;"><img src="assets/img/Landing/foto7.png" alt="Foto Pernikahan"></div></div>
        <div class="col-md-4 col-6"><div class="gallery-img-box" style="height: 300px;"><img src="assets/img/Landing/foto8.png" alt="Foto Bayi"></div></div>
        <div class="col-md-4 col-6"><div class="gallery-img-box" style="height: 300px;"><img src="assets/img/Landing/foto9.png" alt="Foto Wisuda"></div></div>
        <div class="col-md-4 col-6"><div class="gallery-img-box" style="height: 300px;"><img src="assets/img/Landing/foto10.png" alt="Foto Anak Kecil"></div></div>
        <div class="col-md-4 col-6"><div class="gallery-img-box" style="height: 300px;"><img src="assets/img/Landing/foto6.png" alt="Foto Keluarga"></div></div>
      </div>
      <div class="text-center mt-5">
        <a href="#portfolio" class="btn border px-5 py-3" style="border-radius: 12px; color: var(--primary-pink); border-color: var(--primary-pink) !important; font-weight: 700;">Lihat Semua Foto</a>
      </div>
    </section>

    <!-- TESTIMONI -->
    <section id="testimonials" class="py-5" style="background: linear-gradient(180deg, #ffffff 0%, var(--light-pink) 100%);">
      <div class="container py-4">
        <div class="text-center mb-5" data-aos="fade-up">
          <h2 class="fw-bold" style="font-size: 2.5rem;">Kata Mereka ♡</h2>
          <p class="text-muted">Ulasan kepuasan dari pelanggan setia SpotLight Studio.</p>
        </div>
        <div class="row g-4 justify-content-center" data-aos="fade-up">
          <?php
          $sql_testi = "SELECT TOP 3 O.Rating, O.Review, P.Nama_Pelanggan, P.Jenis_Kelamin 
                        FROM [Order] O 
                        JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan 
                        WHERE O.Rating IS NOT NULL AND O.Review IS NOT NULL 
                        ORDER BY O.Tanggal_Booking DESC";
          $query_testi = sqlsrv_query($conn, $sql_testi);
          if ($query_testi):
              while($testi = sqlsrv_fetch_array($query_testi, SQLSRV_FETCH_ASSOC)):
                  $rating = $testi['Rating'];
          ?>
          <div class="col-lg-4 col-md-6">
            <div class="testimonial-card">
              <div>
                <div class="testi-stars">
                  <?php for($i=1; $i<=$rating; $i++): ?><i class="bi bi-star-fill"></i><?php endfor; ?>
                </div>
                <p class="testi-text">"<?= htmlspecialchars($testi['Review']); ?>"</p>
              </div>
              <div class="d-flex align-items-center mt-4 pt-3 border-top">
                <div class="me-3 fs-3 text-secondary">
                  <?php if($testi['Jenis_Kelamin'] == 'Laki-laki'): ?><i class="bi bi-person-fill text-primary"></i><?php else: ?><i class="bi bi-person-fill text-danger"></i><?php endif; ?>
                </div>
                <div>
                  <h6 class="fw-bold mb-0" style="font-size: 0.95rem;"><?= htmlspecialchars($testi['Nama_Pelanggan']); ?></h6>
                  <small class="text-muted" style="font-size: 0.8rem;">Pelanggan Terverifikasi</small>
                </div>
              </div>
            </div>
          </div>
          <?php endwhile; endif; ?>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="container py-5">
      <div class="cta-banner text-center" data-aos="fade-up">
        <h3 class="fw-bold mb-3" style="font-size: 2.2rem;">Yuk, Abadikan Momen Berhargamu!</h3>
        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.9;">Booking sekarang dan dapatkan pengalaman foto yang seru, nyaman, dan berkesan bersama kami.</p>
        <a href="#pricing" class="btn-cta-light"><i class="bi bi-calendar-check me-2"></i>Pesan Sekarang</a>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer id="footer" class="footer">
    <div class="container pb-5">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-12">
          <a href="index.php" class="d-inline-block"><img src="assets/img/logo.png" class="footer-logo" alt="SpotLight Studio Foto"></a>
          <p class="mt-3 text-muted" style="line-height: 1.7; font-size: 0.85rem; color: #a0aec0 !important;">Abadikan momen berhargamu dengan pencahayaan sinematik dan sentuhan fotografer profesional. Kami hadir untuk menceritakan kisah Anda di Cikarang.</p>

          <!-- JAM OPERASIONAL DI FOOTER -->
          <div class="footer-jam-operasional">
            <i class="bi bi-clock-fill"></i>
            <span><?= $jam_operasional; ?></span>
          </div>

          <div class="footer-social-links d-flex mt-4">
            <a href="https://www.instagram.com/stynndka" target="_blank"><i class="bi bi-instagram"></i></a>
            <a href="https://www.tiktok.com/@satyaaaxieee" target="_blank"><i class="bi bi-tiktok"></i></a>
            <a href="https://wa.me/6287899960176" target="_blank"><i class="bi bi-whatsapp"></i></a>
            <a href="https://facebook.com/" target="_blank"><i class="bi bi-facebook"></i></a>
          </div>
        </div>
        <div class="col-lg-2 col-6 footer-links">
          <h6 class="fw-bold mb-3 text-white" style="font-size: 0.95rem;">Navigasi</h6>
          <ul class="list-unstyled" style="font-size: 0.85rem; line-height: 2;">
            <li><a href="#hero">Beranda</a></li>
            <li><a href="#about">Tentang Kami</a></li>
            <li><a href="#portfolio">Galeri</a></li>
            <li><a href="#pricing">Paket Foto</a></li>
          </ul>
        </div>
        <div class="col-lg-2 col-6 footer-links">
          <h6 class="fw-bold mb-3 text-white" style="font-size: 0.95rem;">Layanan</h6>
          <ul class="list-unstyled" style="font-size: 0.85rem; line-height: 2;">
            <li><a href="#services">Self Photo</a></li>
            <li><a href="#services">Wisuda</a></li>
            <li><a href="#services">Wedding</a></li>
            <li><a href="#services">Keluarga</a></li>
          </ul>
        </div>
        <div class="col-lg-4 col-md-12">
          <h6 class="fw-bold mb-3 text-white" style="font-size: 0.95rem;">Hubungi Kami</h6>
          <p class="text-muted" style="font-size: 0.85rem; line-height: 1.8; color: #a0aec0 !important;">Jl. Gilimanuk 3 No. 33, Cikarang Selatan<br>Kab. Bekasi, Jawa Barat 17530<br><span class="d-block mt-3"><strong>WA:</strong> +62 87899960176</span><strong>Email:</strong> spotlightstudio@gmail.com</p>
        </div>
      </div>
    </div>
    <div class="container border-top-custom py-4">
      <div class="row align-items-center">
        <div class="col-md-6 text-center text-md-start"><p class="mb-0" style="font-size: 0.75rem; color: #718096;">&copy; 2026 <strong>SpotLight Studio</strong>.</p></div>
        <div class="col-md-6 text-center text-md-end mt-1 mt-md-0"><p class="mb-0" style="font-size: 0.75rem; color: #718096;">Designed for your memories.</p></div>
      </div>
    </div>
  </footer>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script>AOS.init({ duration: 1000, once: true });</script>
</body>
</html>