<?php
session_start();
include 'koneksi.php'; 

// 1. Inisialisasi Variabel Masuk (Login)
$error_email_login = "";
$error_pass_login = "";

// 2. Inisialisasi Variabel Daftar (Register)
$error_nama = ""; $error_username = ""; $error_email_reg = ""; $error_hp = ""; 
$error_jk = ""; $error_dob = ""; $error_alamat = ""; $error_pass = ""; $error_confirm_pass = "";
$error_foto = "";
$success_register = false;

// KUNCI LOGIKA OTOMATIS: Membaca letak panel geser berdasarkan aksi URL atau kegagalan pendaftaran
$panel_aktif = "";
if (isset($_GET['aksi']) && $_GET['aksi'] == 'daftar') {
    $panel_aktif = "ke-daftar"; // Otomatis membuka panel daftar jika diakses dari tombol DAFTAR di Landing Page
}
$foto_profil = isset($_POST['existing_foto_profil']) ? trim($_POST['existing_foto_profil']) : 'default.jpg';


// =====================================================
// BLOK A: PENANGANAN TRANSAKSI MASUK (LOGIN) MULTI-ROLE
// =====================================================
if (isset($_POST['login'])) {
    $email_login = trim($_POST['email_login']);
    $password_login = $_POST['password_login'];

    if (empty($email_login) || empty($password_login)) { 
        $error_email_login = "Nama pengguna dan kata sandi wajib diisi!"; 
    }

    if (!empty($email_login) && !empty($password_login)) {
        
        // 1. Cek Pertama ke Tabel Pelanggan (Customer) Sesuai Database Baru Kita
        $sql_cust = "SELECT * FROM Pelanggan WHERE (Email_Pelanggan = ? OR Username_Pelanggan = ?) AND Password_Pelanggan = ? AND Is_Deleted = 0";
        $stmt_cust = sqlsrv_query($conn, $sql_cust, array($email_login, $email_login, $password_login));

        if ($stmt_cust === false) { die(print_r(sqlsrv_errors(), true)); }
        $user_cust = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC);

        if ($user_cust) {
            // Blokir Login jika akun ditangguhkan (Status = 0)
            if ($user_cust['Status'] == 0) {
                $error_email_login = "Akses akun ditangguhkan. Silakan hubungi tim kami.";
            } else {
                $_SESSION['status']   = "login";
                $_SESSION['id_user']  = $user_cust['ID_Pelanggan'];
                $_SESSION['email']    = $user_cust['Email_Pelanggan'];
                $_SESSION['role']     = "Customer";

                // DIALIKKAN KE DIREKTORI BARU: Role/Customer/index.php
                header("Location: Role/Customer/index.php");
                exit();
            }
        } else {
            // 2. Cek Kedua ke Tabel Karyawan (Admin, Owner, Fotografer) jika tidak ditemukan di Pelanggan
            $sql_karyawan = "SELECT * FROM Karyawan WHERE (Email_Karyawan = ? OR Username_Karyawan = ?) AND Password_Karyawan = ? AND Is_Deleted = 0";
            $stmt_karyawan = sqlsrv_query($conn, $sql_karyawan, array($email_login, $email_login, $password_login));

            if ($stmt_karyawan === false) { die(print_r(sqlsrv_errors(), true)); }
            $user_karyawan = sqlsrv_fetch_array($stmt_karyawan, SQLSRV_FETCH_ASSOC);

            if ($user_karyawan) {
                // Blokir Login jika status karyawan tidak aktif (Status = 0)
                if ($user_karyawan['Status'] == 0) {
                    $error_email_login = "Akses akun ditangguhkan. Silakan hubungi tim kami.";
                } else {
                    $_SESSION['status']   = "login";
                    $_SESSION['id_user']  = $user_karyawan['ID_Karyawan'];
                    $_SESSION['email']    = $user_karyawan['Email_Karyawan'];
                    $_SESSION['role']     = $user_karyawan['Role_Karyawan'];

                    // REDIRECT BERDASARKAN ROLE (Alur Merujuk pada Folder Baru /Role/)
                    if ($user_karyawan['Role_Karyawan'] == 'Admin') {
                        header("Location: Role/Admin/index.php");
                    } elseif ($user_karyawan['Role_Karyawan'] == 'Owner') {
                        header("Location: Role/Owner/index.php");
                    } elseif ($user_karyawan['Role_Karyawan'] == 'Fotografer') {
                        header("Location: Role/Fotografer/index.php");
                    }
                    exit();
                }
            } else {
                // Notifikasi disamarkan murni menjadi tidak valid demi keamanan data privasi di dunia nyata
                $error_email_login = "Nama pengguna atau kata sandi tidak valid!";
            }
        }
    }
}


// =====================================================
// BLOK B: PENANGANAN PENDAFTARAN (REGISTER) PELANGGAN
// =====================================================
if (isset($_POST['register'])) {
    $nama      = trim($_POST['nama']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $hp        = trim($_POST['no_hp']); 
    
    // Sinkronisasi Aturan Constraint: Hilangkan spasi agar formatnya valid (+628xxxxxxxx)
    $hp_db     = '+' . preg_replace('/[^0-9]/', '', $hp); 
    $hp_bersih = str_replace(['+', ' '], '', $hp); 
    
    // Membaca Pilihan Radio Button Jenis Kelamin
    $jk        = isset($_POST['jenis_kelamin']) ? $_POST['jenis_kelamin'] : "";
    
    // Membaca Tiga Dropdown Tanggal Lahir (Hari, Bulan, Tahun)
    $dob_hari  = isset($_POST['dob_hari']) ? $_POST['dob_hari'] : "";
    $dob_bulan = isset($_POST['dob_bulan']) ? $_POST['dob_bulan'] : "";
    $dob_tahun = isset($_POST['dob_tahun']) ? $_POST['dob_tahun'] : "";

    $alamat    = trim($_POST['alamat']);
    $pass      = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // Paksa agar tampilan form tetap bertahan di sisi "Daftar" saat muat ulang halaman akibat eror
    $panel_aktif = "ke-daftar";

    // --- VALIDASI SERVER SIDE DAFTAR ---
    if (empty($nama)) { 
        $error_nama = "Nama lengkap wajib diisi!"; 
    } elseif (!preg_match("/^[a-zA-Z ]*$/", $nama)) { 
        $error_nama = "Format salah! Gunakan nama asli berupa huruf tanpa simbol."; 
    }
    
    if (empty($username)) { 
        $error_username = "Nama pengguna wajib diisi!"; 
    } elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) { 
        $error_username = "Format salah! Gunakan kombinasi huruf, angka, atau garis bawah tanpa spasi."; 
    }
    
    // KETAT: Validasi panjang nomor telepon disesuaikan dengan fisik kolom VARCHAR(15) pada database Anda
    if (empty($hp)) { 
        $error_hp = "Nomor telepon wajib diisi!"; 
    } elseif (!ctype_digit($hp_bersih) || strlen($hp_db) < 12 || strlen($hp_db) > 15) { 
        $error_hp = "Format salah! Gunakan nomor hp aktif diawali +62 dengan total 12-15 karakter."; 
    }
    
    if (empty($email)) { 
        $error_email_reg = "Email wajib diisi!"; 
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
        $error_email_reg = "Format salah! Masukkan alamat email aktif (contoh: nama@domain.com)."; 
    }
    
    if (empty($jk) || !in_array($jk, ['Laki-laki', 'Perempuan'])) { 
        $error_jk = "Pilih salah satu jenis kelamin Anda."; 
    }

    // Rekonstruksi & Validasi Logika Tanggal Lahir Kustom 100% Bahasa Indonesia
    $tanggal_lahir = "";
    if (empty($dob_hari) || empty($dob_bulan) || empty($dob_tahun)) {
        $error_dob = "Masukkan hari, nama bulan, dan tahun lahir secara lengkap.";
    } else {
        $tanggal_lahir = "$dob_tahun-$dob_bulan-$dob_hari"; // Format standar database SQL Server
        
        // Pengecekan kebenaran tanggal kalender (mencegah eror matematika seperti 31 Februari)
        if (!checkdate((int)$dob_bulan, (int)$dob_hari, (int)$dob_tahun)) {
            $error_dob = "Tanggal kalender yang dimasukkan tidak valid.";
        } elseif (strtotime($tanggal_lahir) > time()) {
            $error_dob = "Tanggal lahir tidak boleh melebihi tanggal hari ini.";
        }
    }
    
    if (empty($alamat)) { 
        $error_alamat = "Alamat wajib diisi!"; 
    } elseif (strlen($alamat) < 10) { 
        $error_alamat = "Tuliskan alamat tinggal Anda secara jelas (minimal 10 karakter)."; 
    }
    
    // Validasi pola sandi sesuai constraint database
    if (strlen($pass) < 8 || !preg_match("/[A-Za-z]/", $pass) || !preg_match("/[0-9]/", $pass) || !preg_match("/[^A-Za-z0-9]/", $pass)) { 
        $error_pass = "Format salah! Sandi wajib minimal 8 karakter dengan kombinasi huruf, angka, dan simbol."; 
    }
    
    if ($pass !== $confirm_pass) { 
        $error_confirm_pass = "Verifikasi sandi tidak cocok! Pastikan ketikannya sama persis."; 
    }

    // --- PENGUNGGAHAN FOTO PROFIL ---
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['foto_profil']['name'];
        $file_size = $_FILES['foto_profil']['size'];
        $file_tmp  = $_FILES['foto_profil']['tmp_name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $error_foto = "Ekstensi tidak sesuai! Gunakan berkas gambar berformat JPG, JPEG, atau PNG.";
        } elseif ($file_size > 2097152) { 
            $error_foto = "Ukuran berkas terlalu besar! Batas maksimal foto adalah 2MB.";
        } else {
            $new_file_name = "pelanggan_" . time() . "_" . uniqid() . "." . $file_ext;
            $target_dir = "assets/img/pelanggan/";
            
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            if (move_uploaded_file($file_tmp, $target_dir . $new_file_name)) {
                if ($foto_profil != 'default.jpg' && file_exists($target_dir . $foto_profil)) { unlink($target_dir . $foto_profil); }
                $foto_profil = $new_file_name;
            } else {
                $error_foto = "Gagal mengunggah berkas foto profil ke server!";
            }
        }
    }

    // --- CEK DUPLIKASI DATABASE ---
    if ($error_nama == "" && $error_username == "" && $error_hp == "" && $error_email_reg == "" && $error_jk == "" && $error_dob == "" && $error_alamat == "" && $error_pass == "" && $error_confirm_pass == "" && $error_foto == "") {
        
        // Pengecekan data menggunakan format nomor telepon tanpa spasi ($hp_db)
        $sql_cek = "SELECT Email_Pelanggan, Username_Pelanggan, No_Hp FROM Pelanggan WHERE Email_Pelanggan = ? OR Username_Pelanggan = ? OR No_Hp = ?";
        $stmt_cek = sqlsrv_query($conn, $sql_cek, array($email, $username, $hp_db));

        if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
            // Demi alasan privasi keamanan data, menyamarkan pesan bentrok data agar tidak dapat dilacak pihak luar
            $error_email_reg = "Pendaftaran tidak dapat diproses. Mohon pastikan data yang dimasukkan benar dan belum terdaftar.";
        } else {
            // Proses penyimpanan data pelanggan baru
            $sql_insert = "INSERT INTO Pelanggan (Nama_Pelanggan, Username_Pelanggan, Email_Pelanggan, Password_Pelanggan, Jenis_Kelamin, Tanggal_Lahir, No_Hp, Alamat, Foto_Profil, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt_insert = sqlsrv_query($conn, $sql_insert, array($nama, $username, $email, $pass, $jk, $tanggal_lahir, $hp_db, $alamat, $foto_profil));
            
            if ($stmt_insert) { 
                $success_register = true;
                $panel_aktif = ""; // Kembali ke panel masuk saat registrasi sukses
            } else { 
                $error_email_reg = "Terjadi kesalahan sistem saat menyimpan data ke database!"; 
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pintu Masuk & Daftar Akun – SpotLight Studio</title>
    
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --p-pink: #d83f67; 
            --d-pink: #c73165; 
            --s-pink: #fff5f6; 
            --accent-pink: #ff6694;
            --text-dark: #1e1e24;
            --glass: rgba(255, 255, 255, 0.94); 
            --transition-3d: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --transition-slide: all 0.6s cubic-bezier(0.76, 0, 0.24, 1); 
        }

        body { 
            background: linear-gradient(135deg, rgba(30, 31, 34, 0.85), rgba(216, 63, 103, 0.45)), 
                        url('assets/img/login/ruangan1.jpg') no-repeat center center fixed; 
            background-size: cover; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 40px 20px; 
            perspective: 2000px; 
            overflow-x: hidden;
        }

        .reg-card { 
            border-radius: 35px; 
            overflow: hidden; 
            max-width: 1100px; 
            width: 100%; 
            height: 720px; 
            position: relative; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08), 0 25px 60px rgba(216, 63, 103, 0.2), inset 0 1px 0 rgba(255,255,255,0.4); 
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.3s ease;
            background: #ffffff;
            padding: 0 !important; 
        }

        .btn-kembali-beranda {
            position: absolute; top: 30px; right: 30px; z-index: 100;
            background: #ffffff; border: 1.5px solid var(--p-pink); padding: 8px 18px;
            border-radius: 50px; font-size: 13px; font-weight: 500; color: var(--p-pink);
            text-decoration: none; box-shadow: 0 6px 15px rgba(216, 63, 103, 0.1);
            transition: var(--transition-3d); display: flex; align-items: center; gap: 6px;
        }
        .btn-kembali-beranda:hover {
            background: var(--p-pink); color: #ffffff; transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(216, 63, 103, 0.25);
        }

        .form-section { 
            position: absolute;
            top: 0;
            height: 100%;
            transition: var(--transition-slide);
            padding: 45px 50px; 
            overflow-y: auto; 
            background: var(--glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .form-masuk {
            left: 45%;
            width: 55%;
            z-index: 5;
            opacity: 1;
        }

        .form-daftar {
            left: 0;
            width: 55%;
            z-index: 1;
            opacity: 0;
            pointer-events: none;
        }

        .side-visual { 
            position: absolute;
            top: 0;
            left: 0;
            width: 45%;
            height: 100%;
            z-index: 10;
            transition: var(--transition-slide);
            background: linear-gradient(135deg, rgba(216, 63, 103, 0.9), rgba(18, 20, 22, 0.95));
            color: white; 
            padding: 50px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
        }

        .ke-daftar .form-masuk {
            opacity: 0;
            z-index: 1;
            pointer-events: none;
        }
        .ke-daftar .form-daftar {
            opacity: 1;
            z-index: 5;
            pointer-events: auto;
        }
        .ke-daftar .side-visual {
            left: 55%;
        }

        .visual-badge {
            background: rgba(255, 255, 255, 0.15); padding: 8px 18px; border-radius: 50px;
            font-size: 0.8rem; font-weight: 500; display: inline-block; margin-bottom: 20px;
            backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.2); color: #ffffff;
        }

        .required-star { color: #ef4444; font-weight: normal; margin-left: 2px; }
        .form-label { font-weight: 500; font-size: 11px; color: #8a99a8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; }
        
        .form-control, .form-select { 
            border-radius: 14px; padding: 12px 18px; border: 2px solid #eef2f6; 
            background: #f8fafc; font-size: 14px; font-weight: 500; 
            transition: var(--transition-3d); color: var(--text-dark); 
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23d83f67' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important; background-position: right 18px center !important;
            background-size: 16px 12px !important; padding-right: 45px !important; 
        }

        .form-control:focus, .form-select:focus { 
            border-color: var(--p-pink); background: #ffffff; 
            transform: translateY(-3px) scale(1.01); 
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15); outline: none;
        }

        .link-lupa-sandi {
            color: var(--p-pink) !important;
            font-weight: 500;
            text-decoration: none !important;
            font-size: 0.85rem;
            transition: var(--transition-3d);
            border-bottom: 2px solid transparent;
        }
        .link-lupa-sandi:hover {
            color: var(--accent-pink) !important;
            border-bottom-color: var(--accent-pink);
        }

        .password-group { 
            position: relative; 
            transition: var(--transition-3d);
            border-radius: 14px;
        }
        .password-group:focus-within {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 12px 25px rgba(216, 63, 103, 0.15);
        }
        .password-group .form-control {
            transition: border-color 0.3s ease, background-color 0.3s ease; 
        }
        .password-group .form-control:focus {
            transform: none !important; 
            box-shadow: none !important;
            background: #ffffff;
            border-color: var(--p-pink);
        }

        .form-logo {
            height: 70px; width: auto; background: transparent;
            border: none; display: block;
        }

        .profile-preview-box {
            width: 70px; height: 70px; border-radius: 50%; overflow: hidden;
            border: 2px solid #eef2f6; background: #f8fafc; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            transition: var(--transition-3d);
        }
        .profile-preview-box img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .btn-pilih-foto {
            background: #ffffff; border: 1.5px solid var(--p-pink); color: var(--p-pink);
            font-weight: 500; border-radius: 10px; padding: 8px 18px; font-size: 0.85rem;
            transition: var(--transition-3d);
        }
        .btn-pilih-foto:hover {
            background: var(--p-pink); color: #ffffff; transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 63, 103, 0.15);
        }

        .btn-reg { 
            background: linear-gradient(135deg, var(--p-pink), var(--d-pink)); color: white; 
            border-radius: 16px; padding: 16px; font-weight: 500; border: none; 
            width: 100%; transition: var(--transition-3d); margin-top: 15px; 
            font-size: 15px; box-shadow: 0 10px 25px rgba(216, 63, 103, 0.25); 
        }
        .btn-reg:hover { 
            transform: translateY(-4px) scale(1.02); 
            box-shadow: 0 15px 35px rgba(216, 63, 103, 0.35); 
        }
        .error-text { color: #ef4444; font-size: 11px; font-weight: 500; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .is-invalid { border-color: #ef4444 !important; background-color: #fff1f2 !important; }
        
        .password-group .form-control.is-invalid {
            background-image: none !important;
            padding-right: 45px !important; 
        }

        .login-link { color: var(--p-pink); font-weight: 500; text-decoration: none; border-bottom: 2px solid transparent; transition: 0.3s; }
        .login-link:hover { border-bottom-color: var(--p-pink); }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; font-size: 18px; z-index: 10; transition: 0.3s; }
        .toggle-password:hover { color: var(--p-pink); }

        /* Kustom Desain Radio Button */
        .gender-radio-group {
            display: flex;
            gap: 15px;
            padding-top: 2px;
        }
        .gender-radio-box {
            position: relative;
            flex: 1;
        }
        .gender-radio-box input[type="radio"] {
            display: none;
        }
        .gender-radio-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #f8fafc;
            border: 2px solid #eef2f6;
            border-radius: 14px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            transition: var(--transition-3d);
        }
        .gender-radio-box input[type="radio"]:checked + .gender-radio-label {
            background: var(--s-pink);
            border-color: var(--p-pink);
            color: var(--p-pink);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(216, 63, 103, 0.08);
        }

        @media (max-width: 991px) {
            .reg-card {
                height: auto;
            }
            .side-visual {
                display: none !important;
            }
            .form-section {
                position: relative;
                width: 100% !important;
                left: 0 !important;
                padding: 40px 30px;
                opacity: 1 !important;
                pointer-events: auto !important;
            }
            .form-daftar {
                display: none; 
            }
            .ke-daftar .form-daftar {
                display: block;
            }
            .ke-daftar .form-masuk {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="reg-card <?= $panel_aktif ?>">
        
        <!-- =====================================================
           PANEL VISUAL SLIDER (LEBAR 45%)
           ===================================================== -->
        <div class="side-visual">
            <div class="panel-info-masuk">
                <span class="visual-badge">✦ STUDIO TERPOPULER DI CIKARANG</span>
                <h1 class="mb-3 text-white" style="font-size: 2.3rem; line-height: 1.2; font-weight: 500;">Yuk, Bergabung<br>Bersama Kami! 🌟🎉</h1>
                <p class="mb-0 opacity-90 small" style="line-height: 1.8; color: #e2e8f0; font-weight: normal;">Daftar sekarang dalam hitungan detik dan nikmati kemudahan melakukan pemesanan studio secara online, memilih tema foto estetik favorit, serta memantau jadwal Anda dengan praktis! ✨📸</p>
                <button class="btn btn-pilih-foto mt-4 text-white border-white bg-transparent" onclick="tampilkanDaftar()"><i class="bi bi-person-plus me-2"></i>Daftar Baru</button>
            </div>
            
            <div class="panel-info-daftar d-none">
                <span class="visual-badge">✦ SPOTLIGHT STUDIO FOTO</span>
                <h1 class="mb-3 text-white" style="font-size: 2.3rem; line-height: 1.2; font-weight: 500;">Pintu Masuk<br>Kehangatan Studio! 👋💖</h1>
                <p class="mb-0 opacity-90 small" style="line-height: 1.8; color: #e2e8f0; font-weight: normal;">Selamat datang kembali, Sahabat SpotLight! Silakan masuk untuk mengelola pesanan, menyelesaikan sisa pelunasan, atau mengunduh langsung seluruh hasil jepretan terbaik Anda di sini! 📸🎈</p>
                <button class="btn btn-pilih-foto mt-4 text-white border-white bg-transparent" onclick="tampilkanMasuk()"><i class="bi bi-box-arrow-in-right me-2"></i>Masuk Akun</button>
            </div>
        </div>

        <!-- =====================================================
           FORMULIR MASUK / LOGIN (LEBAR 55%)
           ===================================================== -->
        <div class="form-section form-masuk">
            <a href="index.php" class="btn-kembali-beranda shadow-sm">
                <i class="bi bi-house-door-fill"></i> Beranda
            </a>
            
            <div class="text-center text-lg-start mb-4">
                <img src="assets/img/logo.png" class="form-logo mb-2" alt="SpotLight Logo">
                <h3 class="text-dark mb-1" style="font-size: 1.8rem; font-weight: 500;">Halo Kak, Selamat Datang Kembali! 👋✨</h3>
                <p class="text-muted small" style="font-weight: normal;">Masukkan akun terdaftar Anda untuk melanjutkan kisah indah bersama SpotLight Studio! 📸💖</p>
            </div>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Nama Pengguna / Email Terdaftar<span class="required-star">*</span></label>
                    <input type="text" name="email_login" class="form-control" placeholder="nama@email.com atau username" value="<?= @$_POST['email_login'] ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Kata Sandi<span class="required-star">*</span></label>
                    <div class="password-group">
                        <input type="password" name="password_login" id="password_login" class="form-control" placeholder="Masukkan kata sandi Anda" required value="<?= htmlspecialchars(@$_POST['password_login']) ?>">
                        <i class="bi bi-eye-slash toggle-password" id="btnToggleLoginPass"></i>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-4">
                    <a href="lupa_password.php" class="link-lupa-sandi">Kesulitan masuk / Lupa Sandi?</a>
                </div>

                <button type="submit" name="login" class="btn btn-reg shadow">
                    Masuk Sekarang <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
            </form>
        </div>

        <!-- =====================================================
           FORMULIR PENDAFTARAN / REGISTER (LEBAR 55%)
           ===================================================== -->
        <div class="form-section form-daftar">
            <a href="index.php" class="btn-kembali-beranda shadow-sm">
                <i class="bi bi-house-door-fill"></i> Beranda
            </a>
            
            <div class="text-center text-lg-start mb-4">
                <img src="assets/img/logo.png" class="form-logo mb-2" alt="SpotLight Logo">
                <h3 class="text-dark mb-1" style="font-size: 1.6rem; font-weight: 500;">Mari Mulai Kisah Indah Anda! 🌟📷</h3>
                <p class="text-muted small" style="font-weight: normal;">Ciptakan akun dalam sekejap untuk akses penuh ke seluruh layanan studio terbaik kami! ✨🎈</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap Anda<span class="required-star">*</span></label>
                        <input type="text" name="nama" id="inputNama" class="form-control <?= ($error_nama != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan nama lengkap" value="<?= @$_POST['nama'] ?>" required>
                        <?php if($error_nama): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_nama ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Pengguna (Username)<span class="required-star">*</span></label>
                        <input type="text" name="username" id="inputUsername" class="form-control <?= ($error_username != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan username" value="<?= @$_POST['username'] ?>" required>
                        <?php if($error_username): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_username ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alamat Email<span class="required-star">*</span></label>
                        <input type="email" name="email" class="form-control <?= ($error_email_reg != '') ? 'is-invalid' : '' ?>" placeholder="nama@email.com" value="<?= @$_POST['email'] ?>" required>
                        <?php if($error_email_reg): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_email_reg ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nomor Telepon<span class="required-star">*</span></label>
                        <input type="text" name="no_hp" id="inputHP" class="form-control <?= ($error_hp != '') ? 'is-invalid' : '' ?>" value="<?= isset($_POST['no_hp']) ? @$_POST['no_hp'] : '+62 ' ?>" required>
                        <?php if($error_hp): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_hp ?></div><?php endif; ?>
                    </div>

                    <!-- Kustom Radio Button Jenis Kelamin (Smooth UI) -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Kelamin<span class="required-star">*</span></label>
                        <div class="gender-radio-group">
                            <div class="gender-radio-box">
                                <input type="radio" name="jenis_kelamin" id="jk_laki" value="Laki-laki" <?= (@$_POST['jenis_kelamin'] == 'Laki-laki') ? 'checked' : '' ?> required>
                                <label for="jk_laki" class="gender-radio-label">
                                    <i class="bi bi-gender-male"></i> Laki-laki
                                </label>
                            </div>
                            <div class="gender-radio-box">
                                <input type="radio" name="jenis_kelamin" id="jk_perempuan" value="Perempuan" <?= (@$_POST['jenis_kelamin'] == 'Perempuan') ? 'checked' : '' ?> required>
                                <label for="jk_perempuan" class="gender-radio-label">
                                    <i class="bi bi-gender-female"></i> Perempuan
                                </label>
                            </div>
                        </div>
                        <?php if($error_jk): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_jk ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Lahir<span class="required-star">*</span></label>
                        <div class="row g-2">
                            <div class="col-4">
                                <select name="dob_hari" class="form-select <?= ($error_dob != '') ? 'is-invalid' : '' ?>" required style="padding: 12px 10px;">
                                    <option value="" disabled selected>Hari</option>
                                    <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?= $val ?>" <?= (@$_POST['dob_hari'] == $val) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select name="dob_bulan" class="form-select <?= ($error_dob != '') ? 'is-invalid' : '' ?>" required style="padding: 12px 10px; background-position: right 10px center !important;">
                                    <option value="" disabled selected>Bulan</option>
                                    <?php 
                                    $bulan_indo = [
                                        "01"=>"Januari", "02"=>"Februari", "03"=>"Maret", "04"=>"April", 
                                        "05"=>"Mei", "06"=>"Juni", "07"=>"Juli", "08"=>"Agustus", 
                                        "09"=>"September", "10"=>"Oktober", "11"=>"November", "12"=>"Desember"
                                    ];
                                    foreach($bulan_indo as $num => $nama_bln):
                                    ?>
                                        <option value="<?= $num ?>" <?= (@$_POST['dob_bulan'] == $num) ? 'selected' : '' ?>><?= $nama_bln ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select name="dob_tahun" class="form-select <?= ($error_dob != '') ? 'is-invalid' : '' ?>" required style="padding: 12px 10px;">
                                    <option value="" disabled selected>Tahun</option>
                                    <?php for($i=date('Y'); $i>=1940; $i--): ?>
                                        <option value="<?= $i ?>" <?= (@$_POST['dob_tahun'] == $i) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <?php if($error_dob): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_dob ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kata Sandi<span class="required-star">*</span></label>
                        <div class="password-group">
                            <input type="password" name="password" id="password" class="form-control <?= ($error_pass != '') ? 'is-invalid' : '' ?>" required placeholder="Huruf, Angka, & Simbol" value="<?= htmlspecialchars(@$_POST['password']) ?>">
                            <i class="bi bi-eye-slash toggle-password" id="btnPass"></i>
                        </div>
                        <?php if($error_pass): ?><div class="error-text"><i class="bi bi-shield-lock-fill"></i> <?= $error_pass ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Verifikasi Sandi<span class="required-star">*</span></label>
                        <div class="password-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?= ($error_confirm_pass != '') ? 'is-invalid' : '' ?>" required placeholder="Ulangi kata sandi anda" value="<?= htmlspecialchars(@$_POST['confirm_password']) ?>">
                            <i class="bi bi-eye-slash toggle-password" id="btnConfirmPass"></i>
                        </div>
                        <?php if($error_confirm_pass): ?><div class="error-text"><i class="bi bi-check-circle-fill"></i> <?= $error_confirm_pass ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Alamat Lengkap<span class="required-star">*</span></label>
                        <input type="text" name="alamat" class="form-control <?= ($error_alamat != '') ? 'is-invalid' : '' ?>" placeholder="Masukkan alamat lengkap" value="<?= @$_POST['alamat'] ?>" required>
                        <?php if($error_alamat): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_alamat ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Upload Foto Profil (Opsional)</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="profile-preview-box">
                                <?php 
                                $default_svg_avatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3e";
                                $preview_src = $default_svg_avatar;
                                if ($foto_profil != 'default.jpg') {
                                    $preview_src = "assets/img/pelanggan/" . $foto_profil;
                                }
                                ?>
                                <img id="profile-preview" src="<?= $preview_src ?>" alt="Tidak ada profil">
                            </div>
                            <div>
                                <input type="hidden" name="existing_foto_profil" value="<?= htmlspecialchars($foto_profil) ?>">
                                <input type="file" name="foto_profil" id="inputFoto" class="form-control d-none" accept=".jpg,.jpeg,.png">
                                <button type="button" class="btn btn-pilih-foto" onclick="document.getElementById('inputFoto').click();"><i class="bi bi-upload me-2"></i>Pilih Foto</button>
                            </div>
                        </div>
                        <?php if($error_foto): ?><div class="error-text"><i class="bi bi-x-circle-fill"></i> <?= $error_foto ?></div><?php endif; ?>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-reg shadow">Daftar Akun Sekarang ✨</button>
            </form>
        </div>

    </div>

    <!-- =====================================================
       MODAL LUPA KATA SANDI (POPUP)
       ===================================================== -->
    <div class="modal fade" id="modalLupaSandi" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 28px; box-shadow: 0 15px 40px rgba(216, 63, 103, 0.25);">
          <div class="modal-header border-0 pb-0 px-4 pt-4 d-flex justify-content-between align-items-center">
            <h5 class="text-dark mb-0" style="font-weight: 500;"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Kesulitan Masuk?</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 pb-4 pt-3">
            <p class="text-muted small mb-4" style="line-height: 1.6; font-weight: normal;">Masukkan alamat email terdaftar Anda di bawah ini. Kami akan mengirimkan tautan pemulihan kata sandi kustom yang aman ke kotak masuk email Anda.</p>
            <form method="POST" action="lupa_password.php">
              <div class="mb-4">
                <label class="form-label text-dark">Email Terdaftar Anda<span class="required-star">*</span></label>
                <input type="email" name="email_lupa" class="form-control" placeholder="nama@email.com" required style="border-radius: 14px;">
              </div>
              <button type="submit" name="request_reset" class="btn btn-reg shadow-sm py-3 mt-0" style="border-radius: 14px;">Kirim Tautan Pemulihan ✉</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        const regCard = document.querySelector('.reg-card');
        const panelMasukInfo = document.querySelector('.panel-info-masuk');
        const panelDaftarInfo = document.querySelector('.panel-info-daftar');

        function tampilkanDaftar() {
            regCard.classList.add('ke-daftar');
            panelMasukInfo.classList.add('d-none');
            panelDaftarInfo.classList.remove('d-none');
        }

        function tampilkanMasuk() {
            regCard.classList.remove('ke-daftar');
            panelDaftarInfo.classList.add('d-none');
            panelMasukInfo.classList.remove('d-none');
        }

        <?php if($panel_aktif == "ke-daftar"): ?>
            tampilkanDaftar();
        <?php endif; ?>

        const inputNama = document.getElementById('inputNama');
        if (inputNama) {
            inputNama.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z ]/g, '');
            });
        }

        const inputUsername = document.getElementById('inputUsername');
        if (inputUsername) {
            inputUsername.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            });
        }
        
        const inputFoto = document.getElementById('inputFoto');
        if (inputFoto) {
            inputFoto.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.getElementById('profile-preview').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function () {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('bi-eye'); this.classList.toggle('bi-eye-slash');
                });
            }
        }
        setupPasswordToggle('btnPass', 'password');
        setupPasswordToggle('btnConfirmPass', 'confirm_password');
        setupPasswordToggle('btnToggleLoginPass', 'password_login');
        
        const inputHP = document.getElementById('inputHP'), prefix = '+62 ';
        function moveCursorToEnd() { if (inputHP.selectionStart < prefix.length) { if (inputHP.setSelectionRange) inputHP.setSelectionRange(prefix.length, prefix.length); } }
        if (inputHP) {
            inputHP.addEventListener('mousedown', () => setTimeout(moveCursorToEnd, 1));
            inputHP.addEventListener('focus', moveCursorToEnd);
            inputHP.addEventListener('keyup', moveCursorToEnd);
            inputHP.addEventListener('keydown', function(e) { if (this.selectionStart <= prefix.length && (e.keyCode === 8 || e.keyCode === 46)) { e.preventDefault(); } });
            inputHP.addEventListener('input', function() {
                if (!this.value.startsWith(prefix)) { this.value = prefix + this.value.replace(/[^0-9]/g, '').substring(2); }
                let digits = this.value.split(prefix)[1].replace(/[^0-9]/g, '');
                // Batasi digit maksimal di sisi klien agar selalu pas dengan VARCHAR(15) database (+62 dan maksimal 12 digit)
                if (digits.length > 12) digits = digits.slice(0, 12);
                this.value = prefix + digits;
            });
        }

        if (regCard) {
            document.addEventListener('mousemove', (e) => {
                const { clientX, clientY } = e;
                const { innerWidth, innerHeight } = window;
                const xRotation = ((clientY / innerHeight) - 0.5) * -6; 
                const yRotation = ((clientX / innerWidth) - 0.5) * 6;  
                regCard.style.transform = `rotateX(${xRotation}deg) rotateY(${yRotation}deg)`;
            });
            document.addEventListener('mouseleave', () => {
                regCard.style.transform = `rotateX(0deg) rotateY(0deg)`; 
            });
        }
    </script>
    
    <!-- SweetAlert Berhasil Daftar -->
    <?php if($success_register): ?>
    <script>
        Swal.fire({
            icon: 'success', 
            title: 'Daftar Akun Berhasil! 🎉', 
            text: 'Selamat datang di SpotLight! Akun Anda telah aktif, silakan masuk.', 
            confirmButtonColor: '#d83f67', 
            confirmButtonText: 'Masuk Sekarang'
        });
    </script>
    <?php endif; ?>

    <!-- SweetAlert Gagal Pendaftaran -->
    <?php if($error_email_reg != "" && $success_register == false): ?>
    <script>
        Swal.fire({
            icon: 'error', 
            title: 'Pendaftaran Gagal! ❌', 
            text: '<?= $error_email_reg ?>', 
            confirmButtonColor: '#d83f67', 
            confirmButtonText: 'Periksa Kembali'
        });
    </script>
    <?php endif; ?>

    <!-- SweetAlert Gagal Masuk -->
    <?php if($error_email_login != ""): ?>
    <script>
        Swal.fire({
            icon: 'error', 
            title: 'Masuk Gagal! ❌', 
            text: '<?= $error_email_login ?>', 
            confirmButtonColor: '#d83f67', 
            confirmButtonText: 'Coba Lagi'
        });
    </script>
    <?php endif; ?>
</body>
</html>