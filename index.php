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
      --transition-3d: all 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    * { box-sizing: border-box; }

    body { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); background-color: #ffffff; scroll-behavior: smooth; overflow-x: hidden; }

    /* ========== RESPONSIVE NAVBAR ========== */
    .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 236, 239, 0.6); padding: 12px 0; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.01); transition: var(--transition-3d); position: sticky; top: 0; z-index: 1000; }
    .header:hover { box-shadow: 0 10px 30px rgba(216, 63, 103, 0.05); border-bottom-color: rgba(216, 63, 103, 0.2); }
    .navbar-logo { height: 55px; width: auto; transition: var(--transition-3d); }
    .navbar-logo:hover { transform: scale(1.05) rotate(-1deg); }

    .navmenu ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; align-items: center; }
    .navmenu ul li a { text-decoration: none; color: #4a5568; font-weight: 700; font-size: 0.95rem; position: relative; transition: 0.3s ease; }
    .navmenu ul li a:hover, .navmenu ul li a.active { color: var(--primary-pink); }
    .navmenu ul li a::after { content: ""; position: absolute; width: 0; height: 3px; bottom: -6px; left: 50%; background: var(--pink-gradient); border-radius: 50px; transition: 0.3s ease; transform: translateX(-50%); }
    .navmenu ul li a:hover::after, .navmenu ul li a.active::after { width: 15px; }

    .mobile-nav-toggle { display: none; font-size: 1.8rem; color: var(--primary-pink); cursor: pointer; background: none; border: none; padding: 5px; }

    @media (max-width: 991px) {
      .mobile-nav-toggle { display: block; }
      .navmenu { position: fixed; top: 0; right: -100%; width: 300px; height: 100vh; background: #fff; padding: 80px 30px 30px; box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: right 0.4s ease; z-index: 999; }
      .navmenu.active { right: 0; }
      .navmenu ul { flex-direction: column; align-items: flex-start; gap: 15px; }
      .navmenu ul li { width: 100%; }
      .navmenu ul li a { display: block; padding: 10px 0; font-size: 1rem; }
      .navmenu ul li a::after { display: none; }
      .nav-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); opacity: 0; visibility: hidden; transition: 0.3s; z-index: 998; }
      .nav-overlay.active { opacity: 1; visibility: visible; }
      .nav-profile { width: 100%; justify-content: center; }
    }

    /* BENDERA INDONESIA */
    .flag-id { width: 28px; height: 28px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border: 2px solid #ffffff; flex-shrink: 0; transition: var(--transition-3d); }
    .flag-id:hover { transform: scale(1.15) rotate(10deg); }
    .flag-id .merah { background: #ff0000; height: 50%; width: 100%; }
    .flag-id .putih { background: #ffffff; height: 50%; width: 100%; }

    .btn-masuk { color: var(--primary-pink) !important; font-weight: 700; padding: 10px 24px; border-radius: 12px; transition: var(--transition-3d); }
    .btn-masuk:hover { background: var(--light-pink); transform: translateY(-2px); }
    .btn-daftar { background: var(--pink-gradient); color: #ffffff !important; font-weight: 700; padding: 10px 28px; border-radius: 12px; transition: var(--transition-3d); border: none; box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15); }
    .btn-daftar:hover { background: var(--pink-gradient-hover); transform: translateY(-3px) scale(1.03); box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3); }

    /* ========== HERO ========== */
    .hero { padding: 80px 0 60px; background: linear-gradient(180deg, #ffffff 0%, var(--light-pink) 100%); }
    .hero h1 { font-weight: 800; font-size: 3.2rem; color: var(--text-dark); line-height: 1.15; }
    .hero h1 span { background: var(--pink-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero-script { font-size: 1.25rem; color: var(--primary-pink); font-style: italic; font-weight: 600; }

    @media (max-width: 768px) {
      .hero { padding: 50px 0 40px; }
      .hero h1 { font-size: 2.2rem; }
      .hero-script { font-size: 1rem; }
    }

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

    @media (max-width: 768px) {
      .hero-collage { height: 320px; }
      .collage-img-1 { width: 55%; left: 5%; }
      .collage-img-2 { width: 40%; top: 10%; }
      .collage-img-3 { width: 35%; }
      .collage-tag { font-size: 0.75rem; padding: 8px 16px; }
    }

    .stat-box { text-align: center; padding: 18px 10px; background: #ffffff; border-radius: 18px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border: 1px solid rgba(255, 236, 239, 0.8); transition: var(--transition-3d); }
    .stat-box:hover { transform: translateY(-5px) scale(1.05); box-shadow: 0 12px 25px rgba(216, 63, 103, 0.08); border-color: var(--primary-pink); }
    .stat-num { font-size: 1.9rem; font-weight: 800; color: var(--primary-pink); }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

    @media (max-width: 576px) {
      .stat-num { font-size: 1.4rem; }
      .stat-label { font-size: 0.7rem; }
      .stat-box { padding: 12px 6px; }
    }

    /* ========== ABOUT ========== */
    .about-section { padding: 80px 0; background: #ffffff; }
    .feature-card { padding: 35px 30px; background: #ffffff; border-radius: 24px; border: 1px solid rgba(255, 236, 239, 0.8); transition: var(--transition-3d); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.01); }
    .feature-card:hover { transform: translateY(-12px) scale(1.02); box-shadow: 0 20px 40px rgba(216, 63, 103, 0.12); border-color: var(--primary-pink); }
    .feature-icon-box { width: 65px; height: 65px; background: var(--light-pink); color: var(--primary-pink); display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 24px auto; font-size: 1.8rem; transition: var(--transition-3d); }
    .feature-card:hover .feature-icon-box { background: var(--pink-gradient); color: #ffffff; transform: scale(1.1) rotate(10deg); }

    @media (max-width: 768px) {
      .about-section { padding: 50px 0; }
      .feature-card { padding: 25px 20px; }
      .feature-icon-box { width: 55px; height: 55px; font-size: 1.5rem; }
    }

    /* ========== LAYANAN KAMI - LAYOUT BARU (BUKAN BUTTON) ========== */
    .services-section { padding: 80px 0; background: linear-gradient(135deg, #ffffff 0%, var(--light-pink) 50%, #ffffff 100%); position: relative; overflow: hidden; }
    .services-section::before {
      content: '';
      position: absolute;
      top: -100px;
      right: -100px;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(216,63,103,0.06) 0%, transparent 70%);
      border-radius: 50%;
    }
    .services-section::after {
      content: '';
      position: absolute;
      bottom: -80px;
      left: -80px;
      width: 250px;
      height: 250px;
      background: radial-gradient(circle, rgba(255,102,148,0.06) 0%, transparent 70%);
      border-radius: 50%;
    }

    .service-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; position: relative; z-index: 1; }

    .service-item {
      position: relative;
      border-radius: 24px;
      overflow: hidden;
      height: 280px;
      cursor: pointer;
      transition: var(--transition-3d);
      box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    .service-item:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: 0 20px 45px rgba(216, 63, 103, 0.18);
    }
    .service-item:nth-child(1) { grid-column: span 2; grid-row: span 2; height: 100%; }
    .service-item:nth-child(2) { grid-column: span 1; }
    .service-item:nth-child(3) { grid-column: span 1; }
    .service-item:nth-child(4) { grid-column: span 1; }
    .service-item:nth-child(5) { grid-column: span 1; }
    .service-item:nth-child(6) { grid-column: span 2; }
    .service-item:nth-child(7) { grid-column: span 1; }
    .service-item:nth-child(8) { grid-column: span 1; }

    .service-bg {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--primary-pink), #ff6694);
      transition: var(--transition-3d);
    }
    .service-item:hover .service-bg {
      background: linear-gradient(135deg, #ff6694, var(--primary-pink));
    }
    .service-bg-alt {
      background: linear-gradient(135deg, #ff8fab, var(--primary-pink)) !important;
    }
    .service-bg-soft {
      background: linear-gradient(135deg, #fff0f3, #ffe0e6) !important;
    }
    .service-bg-soft .service-title,
    .service-bg-soft .service-desc,
    .service-bg-soft .service-icon { color: var(--primary-pink) !important; }

    .service-content {
      position: absolute;
      inset: 0;
      padding: 30px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      z-index: 2;
    }
    .service-icon {
      font-size: 2.5rem;
      color: #ffffff;
      margin-bottom: 15px;
      opacity: 0.9;
      transition: var(--transition-3d);
    }
    .service-item:hover .service-icon {
      transform: scale(1.2) rotate(-5deg);
      opacity: 1;
    }
    .service-title {
      font-size: 1.3rem;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 8px;
      line-height: 1.2;
    }
    .service-desc {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.85);
      line-height: 1.5;
      margin: 0;
    }

    /* Decorative pattern overlay */
    .service-pattern {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 6rem;
      color: rgba(255,255,255,0.08);
      transform: rotate(-15deg);
      transition: var(--transition-3d);
      z-index: 1;
    }
    .service-item:hover .service-pattern {
      transform: rotate(0deg) scale(1.1);
      color: rgba(255,255,255,0.15);
    }

    @media (max-width: 992px) {
      .service-grid { grid-template-columns: repeat(2, 1fr); }
      .service-item:nth-child(1) { grid-column: span 2; grid-row: span 1; height: 280px; }
      .service-item:nth-child(n) { grid-column: span 1; height: 220px; }
      .service-item:nth-child(6) { grid-column: span 2; }
    }
    @media (max-width: 576px) {
      .services-section { padding: 50px 0; }
      .service-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
      .service-item { height: 180px !important; border-radius: 16px; }
      .service-item:nth-child(n) { grid-column: span 1 !important; }
      .service-item:nth-child(1) { grid-column: span 2 !important; height: 220px !important; }
      .service-content { padding: 20px; }
      .service-icon { font-size: 1.8rem; margin-bottom: 10px; }
      .service-title { font-size: 1rem; }
      .service-desc { font-size: 0.75rem; }
      .service-pattern { font-size: 3.5rem; }
    }

    /* ========== PAKET SLIDER DENGAN PANAH ========== */
    .package-section-wrapper { position: relative; }
    .package-slider-container { display: flex; overflow-x: auto; gap: 30px; padding: 20px 10px 45px 10px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; scroll-behavior: smooth; }
    .package-slider-container::-webkit-scrollbar { display: none; }
    .package-item { flex: 0 0 360px; scroll-snap-align: start; }

    @media (max-width: 576px) { 
      .package-item { flex: 0 0 88%; }
      .package-slider-container { gap: 16px; padding: 15px 5px 35px 5px; }
    }

    .pricing-card { background: #ffffff; border-radius: 26px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); height: 100%; transition: var(--transition-3d); border: 1px solid rgba(255, 236, 239, 0.8); display: flex; flex-direction: column; }
    .pricing-card:hover { transform: translateY(-15px) scale(1.02); box-shadow: 0 25px 50px rgba(216, 63, 103, 0.16); border-color: var(--primary-pink); }
    .card-img-box { height: 290px; overflow: hidden; }
    .card-img-box img { width: 100%; height: 100%; object-fit: cover; transition: var(--transition-3d); }
    .pricing-card:hover .card-img-box img { transform: scale(1.1); }
    .card-body-custom { padding: 30px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .price-label { color: var(--primary-pink); font-weight: 800; font-size: 1.8rem; margin-bottom: 15px; display: block; }
    .btn-pilih { background: var(--pink-gradient); color: #ffffff; width: 100%; padding: 14px; border-radius: 14px; border: none; font-weight: 700; text-align: center; text-decoration: none; display: block; transition: var(--transition-3d); box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15); }
    .btn-pilih:hover { background: var(--pink-gradient-hover); color: #ffffff; transform: translateY(-3px); box-shadow: 0 8px 22px rgba(216, 63, 103, 0.3); }

    .slider-arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: #ffffff;
      border: 2px solid var(--primary-pink);
      color: var(--primary-pink);
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 10;
      transition: var(--transition-3d);
      box-shadow: 0 4px 15px rgba(216, 63, 103, 0.15);
    }
    .slider-arrow:hover {
      background: var(--pink-gradient);
      color: #ffffff;
      transform: translateY(-50%) scale(1.1);
      box-shadow: 0 8px 25px rgba(216, 63, 103, 0.3);
    }
    .slider-arrow.prev { left: -25px; }
    .slider-arrow.next { right: -25px; }

    @media (max-width: 1200px) {
      .slider-arrow.prev { left: 10px; }
      .slider-arrow.next { right: 10px; }
    }
    @media (max-width: 576px) {
      .slider-arrow { width: 40px; height: 40px; font-size: 1.2rem; }
      .slider-arrow.prev { left: 5px; }
      .slider-arrow.next { right: 5px; }
    }

    /* ========== JAM OPERASIONAL BADGE ========== */
    .jam-operasional-badge { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: linear-gradient(135deg, #fff5f6, #ffecef); border-radius: 50px; border: 2px solid rgba(216, 63, 103, 0.15); font-weight: 700; font-size: 0.9rem; color: var(--primary-pink); transition: var(--transition-3d); box-shadow: 0 4px 15px rgba(216, 63, 103, 0.08); }
    .jam-operasional-badge:hover { transform: translateY(-3px) scale(1.03); box-shadow: 0 8px 25px rgba(216, 63, 103, 0.15); border-color: var(--primary-pink); }
    .jam-operasional-badge i { font-size: 1.2rem; animation: pulse-clock 2s ease-in-out infinite; }
    @keyframes pulse-clock { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }

    /* ========== GALERI PHOTOCARD GANTUNG - BENANG MELENGKUNG ========== */
    .gallery-string-section { padding: 100px 0 80px; background: linear-gradient(180deg, var(--light-pink) 0%, #ffffff 40%, #ffffff 60%, var(--light-pink) 100%); overflow: hidden; position: relative; }

    .string-container { position: relative; width: 100%; min-height: 520px; display: flex; justify-content: center; align-items: flex-start; padding-top: 60px; }

    /* SVG Tali Melengkung */
    .curved-string-svg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100px;
      z-index: 1;
      pointer-events: none;
    }
    .curved-string-svg path {
      fill: none;
      stroke: url(#stringGradient);
      stroke-width: 3;
      stroke-linecap: round;
      filter: drop-shadow(0 2px 3px rgba(0,0,0,0.1));
    }

    .photocard-wrapper {
      position: relative;
      display: inline-block;
      margin: 0 20px;
      z-index: 2;
      transform-origin: top center;
      animation: sway 3.5s ease-in-out infinite;
    }
    .photocard-wrapper:nth-child(2) { animation-delay: 0.4s; margin-top: 20px; }
    .photocard-wrapper:nth-child(3) { animation-delay: 0.9s; margin-top: 8px; }
    .photocard-wrapper:nth-child(4) { animation-delay: 1.4s; margin-top: 25px; }
    .photocard-wrapper:nth-child(5) { animation-delay: 0.7s; margin-top: 12px; }

    .photoclip {
      position: absolute;
      top: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 26px;
      height: 34px;
      background: linear-gradient(135deg, #e8c4a0, #c4956a);
      border-radius: 4px 4px 2px 2px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.3);
      z-index: 3;
    }
    .photoclip::before {
      content: '';
      position: absolute;
      top: 8px;
      left: 50%;
      transform: translateX(-50%);
      width: 18px;
      height: 5px;
      background: #a67c52;
      border-radius: 2px;
      box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
    }
    .photoclip::after {
      content: '';
      position: absolute;
      top: -5px;
      left: 50%;
      transform: translateX(-50%);
      width: 2px;
      height: 6px;
      background: #c4a484;
    }

    .photocard {
      width: 200px;
      background: #ffffff;
      border-radius: 12px;
      padding: 10px 10px 30px 10px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
      transition: var(--transition-3d);
      cursor: pointer;
      margin-top: 28px;
      position: relative;
    }
    .photocard::before {
      content: '';
      position: absolute;
      top: -28px;
      left: 50%;
      transform: translateX(-50%);
      width: 1.5px;
      height: 28px;
      background: linear-gradient(to bottom, #c4a484, #d4b896);
      z-index: 0;
    }
    /* Benang melengkung dari tali ke foto */
    .photocard::after {
      content: '';
      position: absolute;
      top: -32px;
      left: 50%;
      transform: translateX(-50%);
      width: 20px;
      height: 20px;
      border-left: 1.5px solid #c4a484;
      border-bottom: 1.5px solid #c4a484;
      border-radius: 0 0 0 14px;
      z-index: 0;
    }
    .photocard:hover {
      transform: translateY(-15px) scale(1.05) rotate(0deg) !important;
      box-shadow: 0 20px 50px rgba(216, 63, 103, 0.25), 0 5px 15px rgba(0,0,0,0.1);
      z-index: 10;
    }
    .photocard img {
      width: 100%;
      height: 240px;
      object-fit: cover;
      border-radius: 8px;
      display: block;
    }
    .photocard-caption {
      text-align: center;
      margin-top: 12px;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--primary-pink);
    }

    @keyframes sway {
      0%, 100% { transform: rotate(-2.5deg); }
      50% { transform: rotate(2.5deg); }
    }

    @media (max-width: 1200px) {
      .photocard { width: 170px; }
      .photocard img { height: 200px; }
    }
    @media (max-width: 992px) {
      .string-container { min-height: 480px; flex-wrap: wrap; gap: 25px; padding-top: 50px; }
      .photocard-wrapper { margin: 0 15px; }
      .photocard { width: 150px; }
      .photocard img { height: 180px; }
    }
    @media (max-width: 768px) {
      .gallery-string-section { padding: 60px 0 50px; }
      .string-container { min-height: 400px; padding-top: 40px; }
      .photocard { width: 130px; padding: 8px 8px 20px 8px; }
      .photocard img { height: 150px; }
      .photocard-caption { font-size: 0.75rem; }
      .photoclip { width: 20px; height: 26px; top: -8px; }
    }
    @media (max-width: 576px) {
      .string-container { gap: 12px; padding-top: 35px; }
      .photocard-wrapper { margin: 0 8px; }
      .photocard { width: 110px; }
      .photocard img { height: 130px; }
      .photocard-wrapper:nth-child(4), .photocard-wrapper:nth-child(5) { display: none; }
    }

    /* ========== TESTIMONI ========== */
    .testimonial-card { background: #ffffff; padding: 40px 35px; border-radius: 26px; border: 1px solid rgba(255, 236, 239, 0.8); box-shadow: 0 6px 25px rgba(0,0,0,0.01); height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: var(--transition-3d); }
    .testimonial-card:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(216, 63, 103, 0.08); border-color: var(--primary-pink); }
    .testi-text { font-size: 0.95rem; line-height: 1.8; color: #4a5568; font-style: italic; }
    .testi-stars { color: #ffb800; font-size: 1.1rem; margin-bottom: 15px; }

    @media (max-width: 768px) {
      .testimonial-card { padding: 25px 20px; }
    }

    /* ========== CTA ========== */
    .cta-banner { background: var(--pink-gradient); border-radius: 35px; padding: 70px 40px; color: #ffffff; box-shadow: 0 20px 45px rgba(216, 63, 103, 0.25); transition: var(--transition-3d); }
    .cta-banner:hover { transform: translateY(-5px); box-shadow: 0 25px 60px rgba(216, 63, 103, 0.35); }
    .btn-cta-light { background: #ffffff; color: var(--primary-pink); font-weight: 700; padding: 16px 38px; border-radius: 14px; text-decoration: none; transition: var(--transition-3d); display: inline-block; border: none; box-shadow: 0 8px 25px rgba(216, 63, 103, 0.1); }
    .btn-cta-light:hover { background: var(--peach-pink); transform: translateY(-3px) scale(1.05); box-shadow: 0 12px 30px rgba(216, 63, 103, 0.2); }

    @media (max-width: 576px) {
      .cta-banner { padding: 40px 25px; border-radius: 24px; }
      .cta-banner h3 { font-size: 1.6rem !important; }
    }

    /* ========== NAVBAR PROFILE ========== */
    .nav-profile { background: var(--light-pink); color: var(--primary-pink) !important; padding: 8px 20px !important; border-radius: 50px; display: inline-flex; align-items: center; font-weight: 700; text-decoration: none; transition: var(--transition-3d); }
    .nav-profile:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(216, 63, 103, 0.1); }

    /* ========== FOOTER PUTIH ELEGAN ========== */
    .footer { background: #ffffff; color: var(--text-dark); padding-top: 70px; border-top: 1px solid rgba(255, 236, 239, 0.8); position: relative; }
    .footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--pink-gradient);
      border-radius: 0 0 4px 4px;
    }
    .footer-logo { height: 72px; width: auto; transition: var(--transition-3d); }
    .footer-logo:hover { transform: scale(1.05); }
    .footer-links ul li a { color: var(--text-muted) !important; text-decoration: none; transition: 0.3s ease; font-weight: 600; }
    .footer-links ul li a:hover { color: var(--primary-pink) !important; padding-left: 6px; }
    .footer-social-links a { width: 42px; height: 42px; background: var(--light-pink); color: var(--primary-pink) !important; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 12px; transition: var(--transition-3d); text-decoration: none; border: 1px solid rgba(216, 63, 103, 0.15); }
    .footer-social-links a:hover { background: var(--pink-gradient); color: #ffffff !important; transform: translateY(-5px); border-color: var(--primary-pink); box-shadow: 0 8px 20px rgba(216, 63, 103, 0.2); }
    .border-top-custom { border-top: 1px solid rgba(255, 236, 239, 0.8) !important; }

    .footer-jam-operasional { display: flex; align-items: center; gap: 10px; margin-top: 15px; padding: 10px 18px; background: var(--light-pink); border-radius: 12px; border: 1px solid rgba(216, 63, 103, 0.15); }
    .footer-jam-operasional i { color: var(--primary-pink); font-size: 1.1rem; }
    .footer-jam-operasional span { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; }

    .footer-heading { color: var(--text-dark); font-weight: 800; }
    .footer-contact-text { color: var(--text-muted) !important; line-height: 1.8; font-size: 0.85rem; }
    .footer-contact-text strong { color: var(--primary-pink); }
    .footer-desc { color: var(--text-muted) !important; line-height: 1.7; font-size: 0.85rem; }
    .footer-copyright { color: #a0aec0; font-size: 0.75rem; }

    @media (max-width: 768px) {
      .footer { padding-top: 50px; }
      .footer-logo { height: 55px; }
    }

    /* ========== SECTION HEADINGS RESPONSIVE ========== */
    .section-heading { font-size: 2.5rem; font-weight: 800; }
    .section-subtitle { color: var(--text-muted); font-size: 1rem; }
    @media (max-width: 768px) {
      .section-heading { font-size: 1.8rem !important; }
      .section-subtitle { font-size: 0.9rem; }
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

      <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle Menu">
        <i class="bi bi-list"></i>
      </button>

      <div class="nav-overlay" id="navOverlay"></div>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="#hero">Beranda</a></li>
          <li><a href="#about">Tentang</a></li>
          <li><a href="#services">Layanan</a></li>
          <li><a href="#pricing">Paket</a></li>
          <li><a href="#portfolio">Galeri</a></li>
          <li><a href="#testimonials">Testimoni</a></li>

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

            <div class="mt-3 mb-4">
              <span class="jam-operasional-badge">
                <i class="bi bi-clock-fill"></i>
                <?= $jam_operasional; ?>
              </span>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4 mb-5">
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
        <h2 class="fw-bold section-heading">Studio Nyaman, Hasil Memukau</h2>
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

    <!-- LAYANAN KAMI - LAYOUT BENTO GRID BARU -->
    <section id="services" class="services-section">
      <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
          <span class="badge px-3 py-2 mb-2 text-uppercase" style="background: #ffffff; color: var(--primary-pink); font-weight: 800; border-radius: 50px; box-shadow: 0 2px 10px rgba(216,63,103,0.1);">Apa yang Kami Tawarkan?</span>
          <h2 class="fw-bold section-heading">Layanan Kami</h2>
          <p class="section-subtitle mt-2">Berbagai jenis pemotretan untuk setiap momen spesial Anda</p>
        </div>

        <div class="service-grid" data-aos="fade-up">
          <!-- Item 1: Foto Studio (Besar) -->
          <div class="service-item">
            <div class="service-bg"></div>
            <i class="bi bi-camera-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-camera-fill service-icon"></i>
              <h4 class="service-title">Foto Studio</h4>
              <p class="service-desc">Pemotretan profesional di studio dengan pencahayaan terbaik dan berbagai konsep menarik.</p>
            </div>
          </div>

          <!-- Item 2: Prewedding -->
          <div class="service-item">
            <div class="service-bg service-bg-alt"></div>
            <i class="bi bi-suit-heart-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-suit-heart-fill service-icon"></i>
              <h4 class="service-title">Prewedding</h4>
              <p class="service-desc">Abadikan kisah cinta Anda sebelum hari bahagia tiba.</p>
            </div>
          </div>

          <!-- Item 3: Family -->
          <div class="service-item">
            <div class="service-bg service-bg-soft"></div>
            <i class="bi bi-people-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-people-fill service-icon"></i>
              <h4 class="service-title">Family Portrait</h4>
              <p class="service-desc">Kenangan indah bersama keluarga tercinta.</p>
            </div>
          </div>

          <!-- Item 4: Baby -->
          <div class="service-item">
            <div class="service-bg service-bg-alt"></div>
            <i class="bi bi-balloon-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-balloon-fill service-icon"></i>
              <h4 class="service-title">Baby & Kids</h4>
              <p class="service-desc">Momen lucu dan menggemaskan si kecil.</p>
            </div>
          </div>

          <!-- Item 5: Graduation -->
          <div class="service-item">
            <div class="service-bg"></div>
            <i class="bi bi-mortarboard-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-mortarboard-fill service-icon"></i>
              <h4 class="service-title">Graduation</h4>
              <p class="service-desc">Rayakan kelulusan dengan foto kece dan berkesan.</p>
            </div>
          </div>

          <!-- Item 6: Event (Lebar) -->
          <div class="service-item">
            <div class="service-bg service-bg-alt"></div>
            <i class="bi bi-calendar-event-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-calendar-event-fill service-icon"></i>
              <h4 class="service-title">Event Documentation</h4>
              <p class="service-desc">Dokumentasi acara spesial Anda dengan tim profesional yang siap mengabadikan setiap detik berharga.</p>
            </div>
          </div>

          <!-- Item 7: Personal Branding -->
          <div class="service-item">
            <div class="service-bg service-bg-soft"></div>
            <i class="bi bi-person-badge-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-person-badge-fill service-icon"></i>
              <h4 class="service-title">Personal Branding</h4>
              <p class="service-desc">Foto profesional untuk profil dan karier Anda.</p>
            </div>
          </div>

          <!-- Item 8: Dan Lainnya -->
          <div class="service-item">
            <div class="service-bg"></div>
            <i class="bi bi-plus-circle-fill service-pattern"></i>
            <div class="service-content">
              <i class="bi bi-plus-circle-fill service-icon"></i>
              <h4 class="service-title">Dan Lainnya</h4>
              <p class="service-desc">Konsultasikan kebutuhan fotomu bersama kami.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- PAKET FOTO - DENGAN PANAH SCROLL -->
    <section id="pricing" class="container py-5">
      <div class="d-flex flex-wrap justify-content-between align-items-end mb-5 gap-3" data-aos="fade-up">
        <div>
          <span class="text-uppercase small fw-bold" style="color: var(--primary-pink); letter-spacing: 1px;">Paket Pilihan</span>
          <h2 class="fw-bold mb-0 section-heading">Paket Populer ♡</h2>
          <p class="text-muted mt-2 mb-0">Pilih paket sesuai kebutuhanmu, ruangan akan ditampilkan setelah memilih paket.</p>
        </div>
        <a href="#pricing" class="btn border px-4 py-2" style="border-radius: 12px; color: var(--primary-pink); border-color: var(--primary-pink) !important; font-weight: 700;">Lihat Semua Paket</a>
      </div>

      <div class="package-section-wrapper" data-aos="fade-up">
        <button class="slider-arrow prev" id="sliderPrev" aria-label="Previous Package">
          <i class="bi bi-chevron-left"></i>
        </button>
        <button class="slider-arrow next" id="sliderNext" aria-label="Next Package">
          <i class="bi bi-chevron-right"></i>
        </button>

        <div class="package-slider-container" id="packageSlider">
          <?php
          while($row = sqlsrv_fetch_array($query_paket, SQLSRV_FETCH_ASSOC)):
              $nama_p = $row['Nama_Paket'];

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
                    <?php if (isset($_SESSION['status']) && $_SESSION['status'] == "login" && isset($_SESSION['role']) && $_SESSION['role'] == 'Customer'): ?>
                    <a href="Transaksi/booking.php?id_paket=<?= (int)$row['ID_Paket'] ?>" class="btn-pilih">Pilih Paket</a>
                  <?php elseif (isset($_SESSION['status']) && $_SESSION['status'] == "login"): ?>
                    <a href="<?= $dashboard_link ?>" class="btn-pilih" onclick="alert('Silakan login sebagai Customer untuk melakukan booking.'); return false;">Pilih Paket</a>
                  <?php else: ?>
                    <a href="login.php?redirect=booking&id_paket=<?= (int)$row['ID_Paket'] ?>" class="btn-pilih">Pilih Paket</a>
                  <?php endif; ?>
                  </div>
                </div>
              </div>
          <?php endwhile; ?>
        </div>
      </div>
    </section>

    <!-- GALERI PHOTOCARD GANTUNG - BENANG MELENGKUNG -->
    <section id="portfolio" class="gallery-string-section">
      <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
          <span class="badge px-3 py-2 mb-2 text-uppercase" style="background: #ffffff; color: var(--primary-pink); font-weight: 800; border-radius: 50px; box-shadow: 0 2px 10px rgba(216,63,103,0.1);">Portfolio Kami</span>
          <h2 class="fw-bold section-heading">Galeri Portfolio</h2>
          <p class="section-subtitle">Koleksi momen terbaik yang telah kami abadikan</p>
        </div>

        <div class="string-container" data-aos="fade-up">
          <!-- SVG Tali Melengkung -->
          <svg class="curved-string-svg" viewBox="0 0 1200 80" preserveAspectRatio="none">
            <defs>
              <linearGradient id="stringGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" style="stop-color:#c4a484;stop-opacity:0.3" />
                <stop offset="20%" style="stop-color:#d4b896;stop-opacity:1" />
                <stop offset="50%" style="stop-color:#c4956a;stop-opacity:1" />
                <stop offset="80%" style="stop-color:#d4b896;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#c4a484;stop-opacity:0.3" />
              </linearGradient>
            </defs>
            <path d="M 0,40 Q 150,10 300,40 T 600,40 T 900,40 T 1200,40" />
          </svg>

          <div class="photocard-wrapper">
            <div class="photoclip"></div>
            <div class="photocard" style="transform: rotate(-3deg);">
              <img src="assets/img/Landing/foto7.png" alt="Foto Pernikahan">
              <div class="photocard-caption">Wedding</div>
            </div>
          </div>

          <div class="photocard-wrapper">
            <div class="photoclip"></div>
            <div class="photocard" style="transform: rotate(2deg);">
              <img src="assets/img/Landing/foto8.png" alt="Foto Bayi">
              <div class="photocard-caption">Baby & Kids</div>
            </div>
          </div>

          <div class="photocard-wrapper">
            <div class="photoclip"></div>
            <div class="photocard" style="transform: rotate(-2deg);">
              <img src="assets/img/Landing/foto9.png" alt="Foto Wisuda">
              <div class="photocard-caption">Graduation</div>
            </div>
          </div>

          <div class="photocard-wrapper">
            <div class="photoclip"></div>
            <div class="photocard" style="transform: rotate(3deg);">
              <img src="assets/img/Landing/foto10.png" alt="Foto Anak Kecil">
              <div class="photocard-caption">Family</div>
            </div>
          </div>

          <div class="photocard-wrapper">
            <div class="photoclip"></div>
            <div class="photocard" style="transform: rotate(-1deg);">
              <img src="assets/img/Landing/foto6.png" alt="Foto Keluarga">
              <div class="photocard-caption">Portrait</div>
            </div>
          </div>
        </div>

        <div class="text-center mt-5">
          <a href="#portfolio" class="btn border px-5 py-3" style="border-radius: 12px; color: var(--primary-pink); border-color: var(--primary-pink) !important; font-weight: 700;">Lihat Semua Foto</a>
        </div>
      </div>
    </section>

    <!-- TESTIMONI -->
    <section id="testimonials" class="py-5" style="background: linear-gradient(180deg, #ffffff 0%, var(--light-pink) 100%);">
      <div class="container py-4">
        <div class="text-center mb-5" data-aos="fade-up">
          <span class="badge px-3 py-2 mb-2 text-uppercase" style="background: #ffffff; color: var(--primary-pink); font-weight: 800; border-radius: 50px; box-shadow: 0 2px 10px rgba(216,63,103,0.1);">Testimoni</span>
          <h2 class="fw-bold section-heading">Kata Mereka ♡</h2>
          <p class="section-subtitle">Ulasan kepuasan dari pelanggan setia SpotLight Studio</p>
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
        <h3 class="fw-bold mb-3 section-heading">Yuk, Abadikan Momen Berhargamu!</h3>
        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.9;">Booking sekarang dan dapatkan pengalaman foto yang seru, nyaman, dan berkesan bersama kami.</p>
        <a href="#pricing" class="btn-cta-light"><i class="bi bi-calendar-check me-2"></i>Pesan Sekarang</a>
      </div>
    </section>
  </main>

  <!-- FOOTER PUTIH -->
  <footer id="footer" class="footer">
    <div class="container pb-5">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-12">
          <a href="index.php" class="d-inline-block"><img src="assets/img/logo.png" class="footer-logo" alt="SpotLight Studio Foto"></a>
          <p class="mt-3 footer-desc">Abadikan momen berhargamu dengan pencahayaan sinematik dan sentuhan fotografer profesional. Kami hadir untuk menceritakan kisah Anda di Cikarang.</p>

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
          <h6 class="fw-bold mb-3 footer-heading" style="font-size: 0.95rem;">Navigasi</h6>
          <ul class="list-unstyled" style="font-size: 0.85rem; line-height: 2;">
            <li><a href="#hero">Beranda</a></li>
            <li><a href="#about">Tentang Kami</a></li>
            <li><a href="#portfolio">Galeri</a></li>
            <li><a href="#pricing">Paket Foto</a></li>
          </ul>
        </div>
        <div class="col-lg-2 col-6 footer-links">
          <h6 class="fw-bold mb-3 footer-heading" style="font-size: 0.95rem;">Layanan</h6>
          <ul class="list-unstyled" style="font-size: 0.85rem; line-height: 2;">
            <li><a href="#services">Self Photo</a></li>
            <li><a href="#services">Wisuda</a></li>
            <li><a href="#services">Wedding</a></li>
            <li><a href="#services">Keluarga</a></li>
          </ul>
        </div>
        <div class="col-lg-4 col-md-12">
          <h6 class="fw-bold mb-3 footer-heading" style="font-size: 0.95rem;">Hubungi Kami</h6>
          <p class="footer-contact-text">Jl. Gilimanuk 3 No. 33, Cikarang Selatan<br>Kab. Bekasi, Jawa Barat 17530<br><span class="d-block mt-3"><strong>WA:</strong> +62 87899960176</span><strong>Email:</strong> spotlightstudio@gmail.com</p>
        </div>
      </div>
    </div>
    <div class="container border-top-custom py-4">
      <div class="row align-items-center">
        <div class="col-md-6 text-center text-md-start"><p class="mb-0 footer-copyright">&copy; 2026 <strong style="color: var(--primary-pink);">SpotLight Studio</strong>.</p></div>
        <div class="col-md-6 text-center text-md-end mt-1 mt-md-0"><p class="mb-0 footer-copyright">Designed for your memories.</p></div>
      </div>
    </div>
  </footer>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script>
    AOS.init({ duration: 1000, once: true });

    // ========== MOBILE NAV TOGGLE ==========
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const navmenu = document.getElementById('navmenu');
    const navOverlay = document.getElementById('navOverlay');

    function toggleNav() {
      navmenu.classList.toggle('active');
      navOverlay.classList.toggle('active');
      const icon = mobileNavToggle.querySelector('i');
      if (navmenu.classList.contains('active')) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x-lg');
      } else {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
      }
    }

    mobileNavToggle.addEventListener('click', toggleNav);
    navOverlay.addEventListener('click', toggleNav);

    document.querySelectorAll('.navmenu a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 991) {
          toggleNav();
        }
      });
    });

    // ========== PACKAGE SLIDER ARROWS ==========
    const slider = document.getElementById('packageSlider');
    const prevBtn = document.getElementById('sliderPrev');
    const nextBtn = document.getElementById('sliderNext');

    function getScrollAmount() {
      const item = slider.querySelector('.package-item');
      return item ? item.offsetWidth + 30 : 390;
    }

    prevBtn.addEventListener('click', () => {
      slider.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
    });

    nextBtn.addEventListener('click', () => {
      slider.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
    });

    // Touch swipe support
    let isDown = false;
    let startX;
    let scrollLeft;

    slider.addEventListener('mousedown', (e) => {
      isDown = true;
      slider.style.cursor = 'grabbing';
      startX = e.pageX - slider.offsetLeft;
      scrollLeft = slider.scrollLeft;
    });

    slider.addEventListener('mouseleave', () => {
      isDown = false;
      slider.style.cursor = 'grab';
    });

    slider.addEventListener('mouseup', () => {
      isDown = false;
      slider.style.cursor = 'grab';
    });

    slider.addEventListener('mousemove', (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - slider.offsetLeft;
      const walk = (x - startX) * 2;
      slider.scrollLeft = scrollLeft - walk;
    });

    slider.style.cursor = 'grab';

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') {
        slider.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
      } else if (e.key === 'ArrowRight') {
        slider.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
      }
    });
  </script>
</body>
</html>