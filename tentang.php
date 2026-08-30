<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$namaUser = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];
$role = $_SESSION['role'] ?? 'siswa';

// LOGIKA DINAMIS: Menentukan link kembali berdasarkan role
$link_kembali = ($role === 'guru') ? 'dashboard_guru.php' : 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang PADI | Pembelajaran Anak Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); --bg-light: #f4f7fe; }
        body { background-color: var(--bg-light); font-family: 'Poppins', sans-serif; color: #444; }

        .navbar-custom { background: white; box-shadow: 0 2px 15px rgba(0,0,0,0.05); padding: 15px 0; }
        .hero-about { 
            background: var(--primary-gradient); color: white; 
            padding: 80px 0; border-radius: 0 0 50px 50px; margin-bottom: -50px;
        }

        .glass-card {
            background: white; border: none; border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); padding: 40px; margin-bottom: 30px;
        }

        .profile-img-container {
            width: 220px; 
            height: 280px; 
            margin: 0 auto;
            overflow: hidden;
            border-radius: 25px; 
            border: 6px solid white;
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            background: #eee;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .feature-icon {
            width: 60px; height: 60px; background: #f0f4ff;
            color: #764ba2; border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 15px;
        }

        .badge-dev {
            background: rgba(118, 75, 162, 0.1); color: #764ba2;
            padding: 5px 15px; border-radius: 50px; font-weight: 600; font-size: 0.8rem;
            display: inline-block;
        }

        .location-text { color: #6c757d; font-size: 0.9rem; line-height: 1.6; }
    </style>
</head>
<body>

<nav class="navbar navbar-light navbar-custom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $link_kembali ?>" style="color: #764ba2;">
            <i class="fas fa-chevron-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>
</nav>

<div class="hero-about text-center">
    <div class="container">
        <h1 class="display-4 fw-bold">PADI</h1>
        <p class="lead opacity-75">Pembelajaran Anak Digital</p>
    </div>
</div>

<div class="container" style="position: relative; z-index: 10;">
    <div class="row justify-content-center">
        
        <div class="col-lg-10">
            <div class="glass-card">
                <div class="row align-items-center text-center text-md-start">
                    <div class="col-md-7">
                        <h2 class="fw-bold mb-4" style="color: #764ba2;">Tentang Aplikasi</h2>
                        <p class="text-muted"><strong>PADI (Pembelajaran Anak Digital)</strong> adalah ekosistem media pembelajaran interaktif yang dirancang khusus untuk mendampingi siswa Sekolah Dasar menghadapi tantangan zaman. Dengan memadukan kurikulum nasional dan teknologi kecerdasan buatan, PADI bertugas memberikan pengalaman belajar yang cerdas, modern, dan menyenangkan.</p>
                        
                        <div class="row mt-4">
                            <div class="col-6 col-md-4 mb-3">
                                <div class="feature-icon mx-auto mx-md-0"><i class="fas fa-laptop-code"></i></div>
                                <h6 class="fw-bold">Digital</h6>
                                <p class="small text-muted">Belajar tanpa batas ruang</p>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="feature-icon mx-auto mx-md-0"><i class="fas fa-robot"></i></div>
                                <h6 class="fw-bold">Cerdas</h6>
                                <p class="small text-muted">Bantuan Kak PADI AI </p>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="feature-icon mx-auto mx-md-0"><i class="fas fa-award"></i></div>
                                <h6 class="fw-bold">Prestasi</h6>
                                <p class="small text-muted">Kuis interaktif</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 text-center d-none d-md-block">
                        <img src="https://illustrations.popsy.co/purple/creative-work.svg" alt="Ilustrasi Belajar" class="img-fluid" style="max-height: 280px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-10 mt-2">
            <div class="glass-card">
                <div class="row align-items-center text-center text-md-start">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="profile-img-container">
                            <img src="aset/profil.png" alt="Hari Advent Kristian, S.Pd. Gr." class="profile-img" onerror="this.src='https://ui-avatars.com/api/?name=Hari+Advent+Kristian&background=764ba2&color=fff&size=256&bold=true'">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <span class="badge-dev mb-2">PROFIL PENGEMBANG</span>
                        <h2 class="fw-bold mb-1">Hari Advent Kristian, S.Pd. Gr.</h2>
                        <div class="location-text mb-3">
                            <i class="fas fa-school me-2"></i> <strong>SD Negeri 06 Martapura</strong><br>
                            <i class="fas fa-map-marker-alt me-2"></i> Kab. OKU Timur, Sumatera Selatan
                        </div>
                        <p class="text-muted italic">"Membangun pendidikan masa depan melalui sentuhan teknologi yang akrab dan mendidik bagi setiap anak Indonesia."</p>
                        <p class="text-muted">Sebagai pendidik, fokus saya adalah memastikan setiap siswa memiliki akses ke media belajar yang tidak hanya canggih, tapi juga relevan dengan karakter mereka sebagai generasi digital asli.</p>
                        
                        <div class="d-flex justify-content-center justify-content-md-start gap-3 mt-4">
                            <a href="mailto:hariadventsajo@gmail.com" class="btn btn-primary rounded-pill px-4 shadow-sm" style="background: #764ba2; border: none;">
                                <i class="fas fa-envelope me-2"></i> Hubungi Saya
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<footer class="text-center py-5 text-muted small">
    <p>&copy; 2025 PADI - Pembelajaran Anak Digital<br>
    Pengembangan Media oleh <strong>Hari Advent Kristian, S.Pd. Gr.</strong></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>