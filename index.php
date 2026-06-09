<?php
session_start();
include 'koneksi.php';

// Ambil Nama User jika sudah login untuk Navbar
$nama_user_nav = "";
if (isset($_SESSION['status']) && $_SESSION['status'] == "login") {
    $id_nav = $_SESSION['id_user'];
    if ($_SESSION['role'] == 'Admin') {
        $q_nav = sqlsrv_query($conn, "SELECT Nama_Karyawan as nama FROM Karyawan WHERE ID_User = ?", array($id_nav));
    } else {
        $q_nav = sqlsrv_query($conn, "SELECT Nama_Pelanggan as nama FROM Pelanggan WHERE ID_User = ?", array($id_nav));
    }
    $d_nav = sqlsrv_fetch_array($q_nav, SQLSRV_FETCH_ASSOC);
    $nama_user_nav = $d_nav['nama'] ?? $_SESSION['role'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>SpotLight - Studio Foto Terpopuler</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <style>

    /* CSS untuk Horizontal Slider */
.package-slider-container {
  display: flex;
  overflow-x: auto;
  gap: 25px;
  padding: 20px 10px 40px 10px;
  scroll-snap-type: x mandatory; /* Biar bergesernya 'klik' pas di tengah */
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none; /* Sembunyikan scrollbar di Firefox */
}

.package-slider-container::-webkit-scrollbar {
  display: none; /* Sembunyikan scrollbar di Chrome/Safari */
}

.package-item {
  flex: 0 0 350px; /* Lebar tiap kartu paket (bisa diatur sesuai selera) */
  scroll-snap-align: start;
}

@media (max-width: 576px) {
  .package-item {
    flex: 0 0 85%; /* Di HP, tampilkan 85% lebar layar agar terlihat ada kartu selanjutnya */
  }
}

/* Tambahan styling agar kartu seragam tingginya */
.pricing-card {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.card-body-custom {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
    :root {
      --primary-pink: #b33063;
      --light-pink: #fff0f5;
      --accent-color: #ff66a1;
      --text-dark: #2d0a18;
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; color: #444; scroll-behavior: smooth; }
    
    /* Header & Navbar Fix */
    .header { background: #fff; border-bottom: 1px solid #f1f1f1; padding: 15px 0; }
    .sitename { color: var(--primary-pink); font-weight: 800; font-size: 1.8rem; text-decoration: none; }
    .navmenu ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; align-items: center; }
    .navmenu ul li a { text-decoration: none; color: #555; font-weight: 700; font-size: 0.95rem; transition: 0.3s; }
    .navmenu ul li a:hover, .navmenu ul li a.active { color: var(--primary-pink); }

    /* Hero Section */
    .hero { padding: 80px 0; background: white; }
    .hero h1 { font-weight: 800; font-size: 3.8rem; color: var(--text-dark); line-height: 1.1; }
    .hero span { color: var(--primary-pink); }
    
    /* Section About & Why Us */
    .about-section { padding: 80px 0; }
    .img-side { border-radius: 30px; width: 100%; box-shadow: 25px 25px 0 var(--light-pink); transition: 0.3s; }
    .img-side:hover { transform: translate(-10px, -10px); }

    /* Pricing Card Modern */
    .pricing-card {
      background: #fff; border-radius: 25px; overflow: hidden;
      box-shadow: 0 10px 40px rgba(0,0,0,0.05); height: 100%;
      transition: 0.4s; border: 1px solid #f1f5f9;
    }
    .pricing-card:hover { transform: translateY(-12px); box-shadow: 0 20px 50px rgba(179, 48, 99, 0.15); }
    .card-img-box { 
  height: 500px; /* Ubah dari 220px ke 300px atau sesuai selera */
  overflow: hidden; 
}
    .card-img-box img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .pricing-card:hover .card-img-box img { transform: scale(1.1); }
    
    .card-body-custom { padding: 30px; }
    .price-label { color: var(--primary-pink); font-weight: 800; font-size: 1.6rem; margin-bottom: 15px; display: block; }
    .btn-pilih {
      background: linear-gradient(135deg, var(--primary-pink), var(--accent-color));
      color: white; width: 100%; padding: 12px; border-radius: 12px; border: none; font-weight: 700;
      text-align: center; text-decoration: none; display: block; transition: 0.3s;
    }
    .btn-pilih:hover { transform: scale(1.02); color: white; box-shadow: 0 8px 20px rgba(179, 48, 99, 0.3); }

/* CSS Dropdown Profile Fix */
    .nav-profile { 
      background: var(--light-pink); 
      color: var(--primary-pink) !important; 
      padding: 8px 20px !important; 
      border-radius: 50px; 
      display: inline-flex; 
      align-items: center;
      font-weight: 700;
      text-decoration: none;
      transition: 0.3s;
    }

    .navmenu li.dropdown { 
      position: relative; /* Ini KUNCI agar menu tidak melayang jauh */
    }

    .navmenu .dropdown-menu {
      position: absolute;
      right: 0 !important;
      left: auto !important;
      top: 100%;
      margin-top: 10px !important;
      min-width: 180px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
      border: 1px solid #eee !important;
      display: none;
      
      /* Tambahkan dua baris ini untuk mengatasi masalah klik */
      z-index: 99999 !important; /* Memastikan menu berada di lapisan paling atas */
      pointer-events: auto !important; /* Memastikan elemen bisa menerima klik */
    }

    /* Trik "Jembatan Gaib" agar menu tidak menutup saat kursor lewat */
    .navmenu li.dropdown::after {
      content: "";
      position: absolute;
      left: 0;
      right: 0;
      bottom: -15px;
      height: 15px; /* Menutupi jarak kosong 10px */
      background: transparent;
    }

    /* Munculkan saat diklik atau di-hover */
    .navmenu li.dropdown:hover .dropdown-menu,
    .navmenu li.dropdown .dropdown-menu.show {
      display: block;
    }

    .dropdown-item { padding: 10px 20px; font-weight: 600; color: #555; }
    .dropdown-item:hover { background: var(--light-pink); color: var(--primary-pink); }
    .dropdown-item { padding: 10px 20px; font-weight: 600; }

    /* Merapikan Tombol di Hero Section */
.hero .btn {
  height: 55px; /* Mengunci tinggi agar sama persis */
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 15px !important; /* Membuat sudut lebih halus */
  font-weight: 700;
  transition: 0.3s;
  padding: 0 35px; /* Padding samping yang seragam */
  border-width: 2px; /* Menebalkan garis pinggir tombol outline */
}

/* Penyesuaian khusus untuk tombol outline agar tidak terlihat kekecilan */
.btn-outline-dark {
  border-color: #2d0a18 !important;
  color: #2d0a18 !important;
}

.btn-outline-dark:hover {
  background-color: #2d0a18 !important;
  color: #fff !important;
}
  </style>

  <style>
  .footer-links ul li a {
      transition: 0.3s;
  }
  .footer-links ul li a:hover {
      color: var(--primary-pink) !important;
      padding-left: 3px;
  }
  .social-links a:hover {
      transform: translateY(-3px);
  }
  /* Responsif: Mengurangi margin bawah kolom pada mobile agar lebih rapat */
  @media (max-width: 767px) {
      .footer-about, .footer-links, .footer-contact {
          margin-bottom: 10px;
      }
  }
</style>
</head>

<body>

  <header id="header" class="header sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
      <a href="index.php" class="sitename">SpotLight.</a>
      
      <nav id="navmenu" class="navmenu">
  <ul>
    <li><a href="#hero">Home</a></li>
    <li><a href="#about">Tentang</a></li>
    <li><a href="#portfolio">Portfolio</a></li>
    <li><a href="#pricing">Paket</a></li>
    
    <?php if(isset($_SESSION['status']) && $_SESSION['status'] == "login"): ?>
      <li class="dropdown">
        <a href="#" class="nav-profile">
          <i class="bi bi-person-circle me-2"></i> 
          <span><?= explode(' ', $nama_user_nav)[0]; ?></span>
          <i class="bi bi-chevron-down ms-1 small"></i>
        </a>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="<?= ($_SESSION['role']=='Admin') ? 'Master/Admin/index.php' : 'Customer/index.php'; ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </li>
    <?php else: ?>
      <li><a href="login.php">Masuk</a></li>
      <li><a href="register.php" class="btn-pilih px-4 py-2" style="width: auto; color: white;">DAFTAR</a></li>
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
            <span class="badge px-3 py-2 mb-3" style="background: var(--light-pink); color: var(--primary-pink); border-radius: 10px; font-weight: 800;">STUDIO TERPOPULER DI CIKARANG</span>
            <h1>Abadikan <br><span>Kisah</span> Sempurna Anda.</h1>
            <p class="mt-4 text-muted" style="font-size: 1.1rem;">Abadikan setiap momen berhargamu dengan pencahayaan sinematik dan fotografer profesional di SpotLight Studio.</p>
            <div class="d-flex gap-3 mt-4 align-items-center">
    <a href="#pricing" class="btn btn-pilih shadow-sm">Pesan Sekarang</a>
    <a href="#portfolio" class="btn btn-outline-dark">Lihat Galeri</a>
</div>
          </div>
          <div class="col-lg-6 mt-5 mt-lg-0 text-center" data-aos="zoom-in">
             <img src="assets/img/portfolio/studio.jpg" class="img-side" alt="Studio Background">
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION TENTANG (KEMBALI DIHADIRKAN) -->
    <section id="about" class="about-section container">
      <div class="row align-items-center">
        <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
          <h6 class="text-uppercase fw-bold mb-2" style="color: var(--primary-pink); letter-spacing: 2px;">Profil Studio</h6>
          <h2 class="fw-bold mb-4" style="font-size: 2.5rem; color: var(--text-dark);">Kenapa Harus <br>SpotLight Studio?</h2>
          <p class="text-muted mb-4" style="line-height: 1.8;">
            SpotLight Studio adalah destinasi utama di Cikarang Selatan bagi Anda yang mengutamakan kualitas visual kelas dunia. Kami mengombinasikan perlengkapan kamera <i>high-end</i> dengan sentuhan artistik fotografer profesional.
          </p>
          <div class="row g-4">
              <div class="col-6">
                  <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-patch-check-fill text-success fs-4"></i>
                      <span class="fw-bold small">Kamera Full Frame</span>
                  </div>
              </div>
              <div class="col-6">
                  <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-patch-check-fill text-success fs-4"></i>
                      <span class="fw-bold small">Editing Profesional</span>
                  </div>
              </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-left">
           <div class="p-3 bg-white shadow rounded-4">
                <img src="assets/img/tentangg.jpeg" class="img-fluid rounded-4 shadow-sm" alt="Tentang Kami">
           </div>
        </div>
      </div>
    </section>

    <!-- Section Portfolio -->
    <section id="portfolio" class="container py-5">
      <div class="text-center mb-5" data-aos="fade-up">
        <h2 class="fw-bold" style="font-size: 2.5rem;">Galeri Portfolio</h2>
        <p class="text-muted">Inspirasi gaya dari hasil jepretan terbaik kami.</p>
      </div>
      <div class="row g-4">
        <div class="col-lg-4"><img src="assets/img/portfolio/Foto 3.jpeg" class="img-fluid rounded-4 shadow-sm" alt="Portfolio 1"></div>
        <div class="col-lg-4"><img src="assets/img/portfolio/Foto 1.jpeg" class="img-fluid rounded-4 shadow-sm" alt="Portfolio 2"></div>
        <div class="col-lg-4"><img src="assets/img/portfolio/Foto 2.jpeg" class="img-fluid rounded-4 shadow-sm" alt="Portfolio 3"></div>
      </div>
    </section>

    <!-- Section Paket (SINKRON & BERBEDA GAMBAR) -->
    <section id="pricing" class="container py-5">
  <div class="text-center mb-5" data-aos="fade-up">
    <h2 class="fw-bold" style="font-size: 2.5rem;">Pilih Paket Foto Spesialmu</h2>
    
  </div>

     <div class="package-slider-container" data-aos="fade-up">
    <?php
    $sql = "SELECT * FROM Paket_Foto WHERE Status = 'Aktif'";
    $query = sqlsrv_query($conn, $sql);
    
    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
        $nama_p = $row['Nama_Paket'];
        $foto_db = $row['Foto_Paket'];
        $path_custom = "assets/img/paket/" . $foto_db;

        if(!empty($foto_db) && file_exists($path_custom)) {
            $img_display = $path_custom;
        } else {
            if (stripos($nama_p, 'Basic') !== false) $img_display = "assets/img/basic.jpg";
            elseif (stripos($nama_p, 'Couple') !== false) $img_display = "assets/img/couple.jpg";
            elseif (stripos($nama_p, 'Family') !== false) $img_display = "assets/img/family.jpg";
            else $img_display = "assets/img/portfolio/studio.jpg";
        }
    ?>
        <div class="package-item">
      <div class="pricing-card">
        <div class="card-img-box">
            <img src="<?= $img_display ?>" alt="<?= $nama_p ?>">
        </div>
        <div class="card-body-custom">
          <div>
            <h3 class="fw-bold"><?= $nama_p; ?></h3>
            <span class="price-label">Rp <?= number_format($row['Harga_Paket'], 0, ',', '.'); ?></span>
            <ul class="list-unstyled mb-4 small fw-bold text-muted">
              <li class="mb-2"><i class="bi bi-alarm text-primary me-2"></i><?= $row['Durasi_Waktu']; ?> Menit</li>
              <li class="mb-2"><i class="bi bi-people-fill text-primary me-2"></i>Max <?= $row['Kapasitas_Orang']; ?> Orang</li>
              <li class="fst-italic fw-normal"><?= $row['Deskripsi']; ?></li>
            </ul>
          </div>
          <a href="<?= isset($_SESSION['status']) ? 'Transaksi/booking.php' : 'login.php'; ?>" class="btn-pilih">Booking Sekarang</a>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</section>

  </main>

  <!-- ======= Footer ======= -->
<footer id="footer" class="footer mt-5" style="background-color: #fff; border-top: 1px solid #f1f1f1; padding-top: 40px;">
  <div class="container">
    <div class="row gy-3">
      
      <!-- Kolom 1: Tentang Studio -->
      <div class="col-lg-4 col-md-12 footer-about">
        <a href="index.php" class="sitename" style="text-decoration: none; font-size: 1.5rem; color: var(--primary-pink); font-weight: 800;">SpotLight.</a>
        <p class="mt-2 text-muted" style="line-height: 1.6; font-size: 0.85rem;">
          Abadikan momen berhargamu dengan pencahayaan sinematik dan sentuhan fotografer profesional. Kami hadir untuk menceritakan kisah Anda di Cikarang.
        </p>
        <div class="social-links d-flex mt-3">
          <a href="https://www.instagram.com/stynndka?igsh=MTg3a2R1MTczcnJoNQ%3D%3D&utm_source=qr" target="_blank" class="me-3 fs-5" style="color: var(--primary-pink); transition: 0.3s;">
            <i class="bi bi-instagram"></i>
          </a>
          <a href="https://www.tiktok.com/@satyaaaxieee?_r=1&_t=ZS-972qOmjC0F2" target="_blank" class="me-3 fs-5" style="color: var(--primary-pink); transition: 0.3s;">
            <i class="bi bi-tiktok"></i>
          </a>
          <a href="https://wa.me/6287899960176?text=Halo%20SpotLight%20Studio" target="_blank" class="me-3 fs-5" style="color: var(--primary-pink); transition: 0.3s;">
            <i class="bi bi-whatsapp"></i>
          </a>
          <a href="https://facebook.com/" target="_blank" class="me-3 fs-5" style="color: var(--primary-pink); transition: 0.3s;">
            <i class="bi bi-facebook"></i>
          </a>
        </div>
      </div>

      <!-- Kolom 2: Navigasi Cepat -->
      <div class="col-lg-2 col-6 footer-links">
        <h6 class="fw-bold mb-2" style="color: var(--text-dark); font-size: 0.95rem;">Navigasi</h6>
        <ul class="list-unstyled" style="font-size: 0.85rem;">
          <li class="mb-1"><a href="#hero" class="text-decoration-none text-muted">Beranda</a></li>
          <li class="mb-1"><a href="#about" class="text-decoration-none text-muted">Tentang Kami</a></li>
          <li class="mb-1"><a href="#portfolio" class="text-decoration-none text-muted">Galeri</a></li>
          <li class="mb-1"><a href="#pricing" class="text-decoration-none text-muted">Paket Foto</a></li>
        </ul>
      </div>

      <!-- Kolom 3: Layanan Kami -->
      <div class="col-lg-2 col-6 footer-links">
        <h6 class="fw-bold mb-2" style="color: var(--text-dark); font-size: 0.95rem;">Layanan</h6>
        <ul class="list-unstyled" style="font-size: 0.85rem;">
          <li class="mb-1"><a href="#" class="text-decoration-none text-muted">Self Photo</a></li>
          <li class="mb-1"><a href="#" class="text-decoration-none text-muted">Wisuda</a></li>
          <li class="mb-1"><a href="#" class="text-decoration-none text-muted">Wedding</a></li>
          <li class="mb-1"><a href="#" class="text-decoration-none text-muted">Keluarga</a></li>
        </ul>
      </div>

      <!-- Kolom 4: Kontak -->
      <div class="col-lg-4 col-md-12 footer-contact">
        <h6 class="fw-bold mb-2" style="color: var(--text-dark); font-size: 0.95rem;">Hubungi Kami</h6>
        <p class="text-muted" style="font-size: 0.85rem;">
          Jl. Gilimanuk 3 No. 33, Cikarang Selatan<br>
          Kab. Bekasi, Jawa Barat 17530<br>
          <span class="d-block mt-2"><strong>WA:</strong> +62 87899960176</span>
          <strong>Email:</strong> spotlightstudio@gmail.com
        </p>
      </div>

    </div>
  </div>

  <!-- Copyright Area -->
  <div class="container mt-4 border-top py-3">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start">
        <p class="mb-0 text-muted" style="font-size: 0.75rem;">
          &copy; 2026 <strong>SpotLight Studio</strong>.
        </p>
      </div>
      <div class="col-md-6 text-center text-md-end mt-1 mt-md-0">
        <p class="mb-0 text-muted" style="font-size: 0.75rem;">
          Designed for your memories.
        </p>
      </div>
    </div>
  </div>
</footer>

  <!-- Tambahkan CSS ini di dalam tag <style> Anda agar link footer ada efek hover-nya -->
  <style>
    .footer-links ul li a:hover {
        color: var(--primary-pink) !important;
        padding-left: 5px;
        transition: 0.3s;
    }
    .social-links a:hover {
        transform: translateY(-5px);
    }
  </style>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script>AOS.init({ duration: 1000, once: true });</script>
</body>
</html>