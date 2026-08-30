<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id             = (int)($_SESSION['user_id'] ?? 0);
$nama_pengguna       = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas         = $_SESSION['kelas'] ?? 0;
$id_guru_pembimbing  = (int)($_SESSION['id_guru'] ?? 0);

$filter_status = $_GET['status'] ?? 'semua';

$materi_list = [];
$stats = ['total' => 0, 'selesai' => 0, 'belum' => 0];
$error_message = "";

if ($user_id <= 0 || $level_kelas === 0 || $id_guru_pembimbing <= 0) {
    $error_message = "Kesalahan: Data Siswa atau Guru Pembimbing tidak teridentifikasi.";
}

if (empty($error_message)) {
    // Query Seni: Menarik info Kuis dan Nilai dari kolom nilai_angka
    $query = "
        SELECT m.id, m.judul, m.deskripsi, m.level_kategori,
               (SELECT COUNT(*) FROM riwayat_kuis rk WHERE rk.id_materi = m.id AND rk.id_user = ?) as ada_kuis,
               (SELECT COUNT(*) FROM praktek_siswa ps WHERE ps.materi_id = m.id AND ps.id_siswa = ?) as ada_praktek,
               (SELECT nilai_angka FROM praktek_siswa ps WHERE ps.materi_id = m.id AND ps.id_siswa = ? LIMIT 1) as nilai_praktek,
               (SELECT AVG(persentase) FROM riwayat_kuis rk WHERE rk.id_materi = m.id AND rk.id_user = ?) as rata_nilai
        FROM materi m
        WHERE m.level_kategori = ? AND m.id_guru = ?
        ORDER BY m.id DESC
    ";

    $stmt = $db_mapel->prepare($query);
    // Bind 6 parameter sesuai urutan tanda tanya (?) di atas
    $stmt->bind_param("iiiisi", $user_id, $user_id, $user_id, $user_id, $level_kelas, $id_guru_pembimbing);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Status Selesai: Jika sudah kuis ATAU sudah praktek
            $is_selesai = ($row['ada_kuis'] > 0 || $row['ada_praktek'] > 0);
            
            $stats['total']++;
            $is_selesai ? $stats['selesai']++ : $stats['belum']++;

            if ($filter_status === 'selesai' && !$is_selesai) continue;
            if ($filter_status === 'belum' && $is_selesai) continue;
            
            $materi_list[] = $row;
        }
    }
    $stmt->close();
}
$db_mapel->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Seni | Portal Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-pink: #e91e63;
            --soft-pink: #fff5f8;
        }
        body { 
            background-color: var(--soft-pink); 
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .hero-seni {
            background: linear-gradient(135deg, #c2185b 0%, #e91e63 100%);
            padding: 80px 0 120px 0;
            color: white;
            border-radius: 0 0 50px 50px;
            margin-bottom: -60px;
        }
        .stats-glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(233, 30, 99, 0.1);
        }
        .seni-card {
            background: white;
            border: none;
            border-radius: 30px;
            transition: all 0.4s ease;
            height: 100%;
            border: 1px solid #edf2f7;
            overflow: hidden;
        }
        .seni-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px -12px rgba(233, 30, 99, 0.2);
        }
        .indicator-pill {
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 100px;
            font-weight: 700;
        }
        .navbar-custom {
            background: rgba(194, 24, 91, 0.9) !important;
            backdrop-filter: blur(15px);
        }
        .btn-seni {
            background-color: var(--primary-pink);
            color: white;
            border-radius: 15px;
            font-weight: 800;
            border: none;
        }
        .status-box {
            background: #f8f9fa;
            border: 1px dashed #e91e63;
            border-radius: 15px;
            padding: 10px;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .animate-pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-palette me-2 text-warning"></i> RUANG SENI
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="text-white text-end d-none d-md-block">
                    <span class="fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span>
                </div>
                <a href="../logout.php" class="btn btn-outline-light rounded-circle"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-seni text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Halo, Seniman Muda! 🎨</h1>
            <p class="opacity-75 lead">Materi Seni Rupa Kelas <?= htmlspecialchars($level_kelas) ?></p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        
        <div class="row g-3 mb-5 text-center">
            <div class="col-4"><div class="stats-glass"><div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div><small>Total</small></div></div>
            <div class="col-4"><div class="stats-glass"><div class="h4 fw-bold mb-0"><?= $stats['selesai'] ?></div><small>Selesai</small></div></div>
            <div class="col-4"><div class="stats-glass"><div class="h4 fw-bold mb-0"><?= $stats['belum'] ?></div><small>Belum</small></div></div>
            
        </div>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a></div>
        <div class="d-flex justify-content-center mb-5">
            <div style="background: #fff; padding: 6px; border-radius: 100px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <a href="?status=semua" class="btn <?= $filter_status == 'semua' ? 'btn-seni shadow' : 'text-muted' ?> rounded-pill px-4">Semua</a>
                <a href="?status=belum" class="btn <?= $filter_status == 'belum' ? 'btn-seni shadow' : 'text-muted' ?> rounded-pill px-4">Belum</a>
                <a href="?status=selesai" class="btn <?= $filter_status == 'selesai' ? 'btn-seni shadow' : 'text-muted' ?> rounded-pill px-4">Selesai</a>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($materi_list as $materi): 
                $is_selesai = ($materi['ada_kuis'] > 0 || $materi['ada_praktek'] > 0);
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card seni-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <div class="d-flex gap-1">
                                <span class="indicator-pill <?= $materi['ada_kuis'] > 0 ? 'bg-success text-white' : 'bg-light text-muted' ?>">Kuis</span>
                                <span class="indicator-pill <?= $materi['ada_praktek'] > 0 ? 'bg-success text-white' : 'bg-light text-muted' ?>">Praktek</span>
                            </div>
                            <?php if($is_selesai): ?><i class="fas fa-check-circle text-success"></i><?php endif; ?>
                        </div>

                        <h4 class="fw-bold mb-3"><?= htmlspecialchars($materi['judul']) ?></h4>
                        
                        <div class="d-flex flex-column gap-2 mb-3">
    
    <?php if ($materi['ada_praktek'] > 0): ?>
        <div class="status-box">
            <small class="text-muted d-block" style="font-size: 0.7rem;">Praktek :</small>
            <?php if (!empty($materi['nilai_praktek']) && $materi['nilai_praktek'] > 0): ?>
                <span class="fw-bold text-success fs-5">
                    <i class="fas fa-award me-1"></i> Nilai: <?= $materi['nilai_praktek'] ?>
                </span>
            <?php else: ?>
                <span class="fw-bold text-warning animate-pulse">
                    <i class="fas fa-clock me-1"></i> Menunggu Dinilai
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($materi['rata_nilai'] > 0): ?>
        <div class="status-box" style="border-color: #00bcd4;"> <small class="text-muted d-block" style="font-size: 0.7rem;">Kuis :</small>
            <span class="fw-bold text-info fs-5">
                <i class="fas fa-check-double me-1"></i> Nilai: <?= round($materi['rata_nilai']) ?>
            </span>
        </div>
    <?php endif; ?>

</div>

                        <p class="text-muted small mb-4"><?= htmlspecialchars(substr($materi['deskripsi'], 0, 80)) ?>...</p>

                        <a href="materi_view.php?id=<?= $materi['id'] ?>" class="btn btn-seni w-100 p-3 shadow-sm">
                            Buka Materi <i class="fas fa-chevron-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="py-5 text-center mt-5 text-muted small">
        <p>&copy; 2026 PADI-SENI</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>