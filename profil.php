<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Ambil Data Detail Siswa & Guru Pembimbing
$query = "SELECT u.*, g.nama_lengkap as nama_guru 
          FROM users u 
          LEFT JOIN users g ON u.id_guru = g.id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 2. Data untuk Statistik Ringkas (diambil dari session yang sudah dihitung di dashboard)
$rata_nilai = $_SESSION['rata_nilai'] ?? 0; 
$total_progres = $_SESSION['total_progres'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya | PADI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        
        .profile-header {
            background: var(--primary-gradient);
            height: 200px;
            border-radius: 0 0 50px 50px;
            position: relative;
            margin-bottom: 80px;
        }

        .profile-img-container {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
        }

        .profile-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 5px solid white;
            background: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            object-fit: cover;
        }

        .card-info {
            border: none;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .info-label { color: #888; font-size: 0.8rem; text-transform: uppercase; font-weight: 600; }
        .info-value { color: #333; font-weight: 600; margin-bottom: 15px; }
        
        .btn-back {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            backdrop-filter: blur(5px);
        }
        .btn-back:hover { background: white; color: #764ba2; }
    </style>
</head>
<body>

<div class="profile-header">
    <a href="dashboard.php" class="btn btn-back rounded-pill px-3 fw-bold">
        <i class="fas fa-arrow-left me-2"></i> Kembali
    </a>
    <div class="profile-img-container text-center">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nama_lengkap']) ?>&background=764ba2&color=fff&size=128" class="profile-img" alt="Foto Profil">
        <h4 class="fw-bold mt-3"><?= htmlspecialchars($user['nama_lengkap']) ?></h4>
        <span class="badge bg-primary rounded-pill px-3">Siswa Aktif</span>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        <div class="col-md-5">
            <div class="card card-info p-4 h-100">
                <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-user-circle me-2"></i> Detail Akun</h5>
                
                <div class="info-label">Username</div>
                <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                
                <div class="info-label">Nama Lengkap</div>
                <div class="info-value"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                
                <div class="info-label">Status Peran</div>
                <div class="info-value text-capitalize"><?= htmlspecialchars($user['role']) ?></div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card card-info p-4 h-100">
                <h5 class="fw-bold mb-4 text-success"><i class="fas fa-graduation-cap me-2"></i> Akademik</h5>
                
                <div class="info-label">Kelas</div>
                <div class="info-value"><?= htmlspecialchars($user['kelas'] ?? 'N/A') ?></div>
                
                <div class="info-label">Guru Pembimbing</div>
                <div class="info-value"><?= htmlspecialchars($user['nama_guru'] ?? 'N/A') ?></div>
                
                <div class="info-label">Semester Aktif</div>
                <div class="info-value">Semester <?= htmlspecialchars($_SESSION['semester_aktif'] ?? '1') ?></div>
            </div>
        </div>

        <div class="col-md-10 text-center mt-4">
            <p class="text-muted small">Jika ada kesalahan data nama atau kelas, silakan hubungi Guru Pembimbing Anda.</p>
            <a href="logout.php" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-sign-out-alt me-2"></i> Keluar dari Akun
            </a>
        </div>
    </div>
</div>

<footer class="text-center pb-4 text-muted small">
    &copy; 2025 Portal PADI - Mathfiction School
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>