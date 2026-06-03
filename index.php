<?php
session_start();
include 'koneksi.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>SpotLight - Studio Foto Terpopuler</title>

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Style Gabungan -->
  <style>
    :root {
      --primary-pink: #b33063;
      --light-pink: #fff0f5;
      --accent-color: #ff66a1;
      --text-dark: #2d0a18;
    }

    body { font-family: 'Montserrat', sans-serif; color: #444; }
    
    /* Header & Nav */
    .header { background: #fff; border-bottom: 1px solid #eee; }
    .sitename { color: var(--primary-pink); font-weight: 800; font-size: 1.5rem; text-decoration: none; }
    .navmenu ul { list-style: none; display: flex; gap: 30px; margin: 0; padding: 0; align-items: center; }
    .navmenu ul li a { text-decoration: none; color: #555; font-weight: 600; font-size: 0.9rem; }
    .navmenu ul li a.active { color: var(--primary-pink); }

    /* Hero Section */
    .hero { padding: 80px 0; background: white; }
    .hero h1 { font-weight: 800; font-size: 3.5rem; color: var(--text-dark); }
    
    /* Pricing Card Baru (Gabungan) */
    .pricing-card {
      background: #fff; border-radius: 15px; overflow: hidden;
      box-shadow: 0 10px 30px rgba(0,0,0,0.05); height: 100%;
      transition: 0.3s; border: 1px solid #eee;
    }
    .pricing-card:hover { transform: translateY(-10px); }
    .card-img { height: 200px; background-size: cover; background-position: center; background-color: #ddd; }
    .card-body-custom { padding: 30px; }
    .card-body-custom h3 { font-weight: 700; font-size: 1.4rem; }
    .price { color: var(--primary-pink); font-weight: 800; font-size: 1.5rem; margin-bottom: 20px; display: block; }
    .features-list { list-style: none; padding: 0; margin-bottom: 30px; }
    .features-list li { margin-bottom: 12px; font-size: 0.9rem; display: flex; align-items: center; }
    .features-list li i { color: #28a745; margin-right: 10px; }
    
    .btn-pilih {
      background-color: var(--primary-pink); color: white; width: 100%;
      padding: 12px; border-radius: 8px; border: none; font-weight: 600;
      text-align: center; text-decoration: none; display: block;
    }

    /* Why Choose Us */
    .why-section { padding: 80px 0; }
    .why-icon {
      width: 50px; height: 50px; background: var(--light-pink);
      border-radius: 10px; display: flex; align-items: center;
      justify-content: center; color: var(--primary-pink); font-size: 1.5rem;
    }
    .img-side { border-radius: 20px; width: 100%; box-shadow: 20px 20px 0 var(--light-pink); }
  </style>
</head>

<body>

  <header id="header" class="header sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="index.php" class="sitename">SpotLight</a>
      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero" class="active">Home</a></li>
          <li><a href="#portfolio">Portfolio</a></li>
          <li><a href="#pricing">Paket</a></li>
          <li><a href="#about">Tentang</a></li>
          
          <?php if(isset($_SESSION['status']) && $_SESSION['status'] == "login"): ?>
            <li class="dropdown"><a href="#"><span>Akun (<?php echo $_SESSION['role']; ?>)</span></a>
              <ul class="dropdown-menu shadow border-0 p-3">
                <li><a href="<?php echo ($_SESSION['role']=='Admin') ? 'Master/Admin/index.php' : 'Pelanggan/index.php'; ?>">Dashboard</a></li>
                <li><hr></li>
                <li><a href="logout.php" class="text-danger">Logout</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li><a href="login.php">Masuk</a></li>
            <li><a href="register.php" style="background: var(--primary-pink); color: white; padding: 8px 20px; border-radius: 20px;">Daftar</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>

  <main>
    <!-- Hero Section -->
    <section id="hero" class="hero">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6" data-aos="fade-up">
            <h1>Abadikan Setiap Momen <span style="color: var(--primary-pink);">Terbaikmu</span></h1>
            <p class="mt-4">Ciptakan kenangan abadi dengan kualitas profesional di studio terpopuler Cikarang Selatan.</p>
            <a href="#pricing" class="btn btn-lg mt-3" style="background: var(--primary-pink); color:white; border-radius:10px;">Lihat Paket</a>
          </div>
          <div class="col-lg-6" data-aos="zoom-in">
             <img src="assets/img/portfolio/studio.jpg" class="img-side" alt="Studio">
          </div>
        </div>
      </div>
    </section>

    <!-- Section Portfolio (Tetap Asli) -->
    <section id="portfolio" class="container py-5">
      <div class="text-center mb-5">
        <h2 style="font-weight: 800;">Portfolio Kami</h2>
      </div>
      <div class="row g-4">
        <!-- Pakai Gambar Aslimu di Sini -->
        <div class="col-lg-4"><img src="assets/img/portfolio/portfolio-3.webp" class="img-fluid rounded-4" alt=""></div>
        <div class="col-lg-4"><img src="assets/img/portfolio/portfolio-7.webp" class="img-fluid rounded-4" alt=""></div>
        <div class="col-lg-4"><img src="assets/img/portfolio/portfolio-8.webp" class="img-fluid rounded-4" alt=""></div>
      </div>
    </section>

    <!-- Section Paket (DINAMIS GABUNGAN) -->
    <section id="pricing" class="container py-5">
      <div class="section-title-area text-center mb-5" data-aos="fade-up">
        <h2 style="font-weight: 800; font-size: 2.5rem;">Pilih Paket Foto Spesialmu</h2>
        <p>Data diambil langsung dari sistem Master Paket Foto SpotLight.</p>
      </div>

      <div class="row g-4 mb-5">
        <?php
        $sql = "SELECT * FROM Paket_Foto WHERE Status = 'Aktif'";
        $query = sqlsrv_query($conn, $sql);
        while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
          // Tentukan gambar berdasarkan nama paket (opsional)
          $img = "assets/img/basic.jpg"; 
          if(strpos($row['Nama_Paket'], 'Couple') !== false) $img = "assets/img/couple.jpg";
          if(strpos($row['Nama_Paket'], 'Family') !== false) $img = "assets/img/family.jpg";
        ?>
        <div class="col-lg-4" data-aos="fade-up">
          <div class="pricing-card">
            <div class="card-img" style="background-image: url('<?php echo $img; ?>');"></div>
            <div class="card-body-custom">
              <h3><?php echo $row['Nama_Paket']; ?></h3>
              <span class="price">Rp <?php echo number_format($row['Harga_Paket'], 0, ',', '.'); ?></span>
              <ul class="features-list">
                <li><i class="bi bi-check-circle-fill"></i> <?php echo $row['Durasi_Waktu']; ?> Menit Sesi Foto</li>
                <li><i class="bi bi-check-circle-fill"></i> Kapasitas <?php echo $row['Kapasitas_Orang']; ?> Orang</li>
                <li><i class="bi bi-check-circle-fill"></i> <?php echo $row['Deskripsi']; ?></li>
              </ul>
              <a href="login.php" class="btn-pilih">Booking Sekarang</a>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </section>

    <!-- Section Kenapa Memilih Kami (Gabungan) -->
    <section class="why-section container">
      <div class="row align-items-center">
        <div class="col-lg-6" data-aos="fade-right">
          <h2 style="font-weight: 800;">Kenapa Memilih SpotLight?</h2>
          <div class="row mt-4">
            <div class="col-md-6 mb-4">
              <div class="why-item">
                <div class="why-icon"><i class="bi bi-camera-reels"></i></div>
                <div class="why-text">
                  <h4>Kamera High-End</h4>
                  <p>Hasil tajam dengan perlengkapan termutakhir.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 mb-4">
              <div class="why-item">
                <div class="why-icon"><i class="bi bi-magic"></i></div>
                <div class="why-text">
                  <h4>Editing Profesional</h4>
                  <p>Sentuhan artistik untuk estetika maksimal.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 text-center" data-aos="fade-left">
          <img src="assets/img/portfolio/studio.jpg" alt="Photographer" class="img-side">
        </div>
      </div>
    </section>
  </main>

  <footer class="py-4 mt-5" style="background: var(--light-pink);">
    <div class="container text-center text-muted">
        <small>SpotLight Photo Studio 2026. All rights reserved.</small>
    </div>
  </footer>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script>AOS.init({ duration: 1000, once: true });</script>
</body>
</html>