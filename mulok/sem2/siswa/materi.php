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
    // 1. QUERY GABUNGAN DENGAN RATA-RATA NILAI KUIS
    $query_pribadi = "
        SELECT m.id, m.judul, m.deskripsi, m.level_kategori, 
               MAX(rk.id) AS riwayat_ada,
               (SELECT AVG(persentase) FROM riwayat_kuis WHERE id_materi = m.id AND id_user = ?) as rata_nilai
        FROM materi m
        LEFT JOIN riwayat_kuis rk ON m.id = rk.id_materi AND rk.id_user = ?
        WHERE m.level_kategori = ? AND m.id_guru = ?
        GROUP BY m.id
    ";

    $query_adopsi = "
        SELECT m.id, m.judul, m.deskripsi, m.level_kategori, 
               MAX(rk.id) AS riwayat_ada,
               (SELECT AVG(persentase) FROM riwayat_kuis WHERE id_materi = m.id AND id_user = ?) as rata_nilai
        FROM penugasan_materi p
        JOIN materi m ON p.id_materi = m.id
        LEFT JOIN riwayat_kuis rk ON m.id = rk.id_materi AND rk.id_user = ?
        WHERE p.ditugaskan_ke = ? AND m.level_kategori = ? AND m.id_guru != ?
        GROUP BY m.id
    ";

    $query_gabungan = "({$query_pribadi}) UNION ({$query_adopsi}) ORDER BY id DESC";
    $stmt = $db_mapel->prepare($query_gabungan);
    
    // 2. BIND PARAMETER (TOTAL 9)
    $params = [
        $user_id, $user_id, $level_kelas, $id_guru_pembimbing,
        $user_id, $user_id, $user_id, $level_kelas, $id_guru_pembimbing
    ];
    $stmt->bind_param("iiiiisiii", ...$params);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $is_selesai = !empty($row['riwayat_ada']);
            
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
    <title>Peta Materi | B.Komering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-komering: #2c2c2c;
            --light-bg: #f8fafc;
            --accent-gold: #ffc107;
        }
        body { 
            background-color: var(--light-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .hero-mulok {
            background: linear-gradient(135deg, #1a1a1a 0%, #4b4b4b 100%);
            padding: 80px 0 120px 0;
            color: white;
            border-radius: 0 0 50px 50px;
            margin-bottom: -60px;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-tab.active {
            background: var(--dark-komering);
            color: white !important;
        }

        .materi-card {
            background: white;
            border-radius: 30px;
            transition: all 0.4s ease;
            border: 1px solid #e2e8f0;
        }
        .materi-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 40px -15px rgba(0,0,0,0.1);
        }

        /* Badge Nilai Khas Mulok */
        .nilai-box {
            background: #fffdf5;
            border: 1px solid #ffeeba;
            padding: 8px 15px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            color: #856404;
        }

        .btn-action {
            border-radius: 15px;
            padding: 12px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top py-3" style="background: rgba(26,26,26,0.9); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-map-marked-alt me-2 text-warning"></i> B.KOMERING
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="text-white text-end d-none d-md-block">
                    <small class="d-block opacity-75">Siswa Aktif</small>
                    <span class="fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span>
                </div>
                <a href="../logout.php" class="btn btn-outline-light rounded-circle"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-mulok text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Halo, <?= explode(' ', $nama_pengguna)[0] ?>! 👋</h1>
            <p class="opacity-75 lead">Lestarikan Budaya Komering Kelas <?= htmlspecialchars($level_kelas) ?></p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        <div class="row g-3 mb-5 text-center">
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-dark mb-1"><i class="fas fa-layer-group"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-success mb-1"><i class="fas fa-check-circle"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['selesai'] ?></div>
                    <small class="text-muted">Selesai</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-warning mb-1"><i class="fas fa-hourglass-half"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['belum'] ?></div>
                    <small class="text-muted">Belum</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <div class="bg-white p-2 rounded-pill shadow-sm">
                <a href="?status=semua" class="btn btn-sm rounded-pill px-3 <?= $filter_status == 'semua' ? 'btn-dark' : 'text-muted' ?>">Semua</a>
                <a href="?status=belum" class="btn btn-sm rounded-pill px-3 <?= $filter_status == 'belum' ? 'btn-dark' : 'text-muted' ?>">Belum</a>
                <a href="?status=selesai" class="btn btn-sm rounded-pill px-3 <?= $filter_status == 'selesai' ? 'btn-dark' : 'text-muted' ?>">Selesai</a>
            </div>
        </div>

        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book-open fa-4x text-muted opacity-25 mb-4"></i>
                <h3 class="fw-bold text-muted">Materi Tidak Ditemukan</h3>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($materi_list as $materi): 
                    $is_selesai = !empty($materi['riwayat_ada']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card materi-card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill px-3 py-2">
                                    <i class="fas fa-landmark me-1"></i> Mulok
                                </span>
                                <?php if($is_selesai): ?>
                                    <i class="fas fa-check-double text-success fs-4"></i>
                                <?php endif; ?>
                            </div>

                            <h4 class="fw-bold mb-2 text-dark"><?= htmlspecialchars($materi['judul']) ?></h4>
                            
                            <div class="mb-3">
                                <?php if (!empty($materi['rata_nilai'])): ?>
                                    <div class="nilai-box">
                                         <small class="ms-1 opacity-75">Nilai : </small>
                                        <span class="fw-800"><?= round($materi['rata_nilai']) ?></span>
                                       
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted italic"><i class="far fa-clock me-1"></i> Belum ada nilai</small>
                                <?php endif; ?>
                            </div>

                            <p class="text-muted small mb-4">
                                <?= htmlspecialchars(substr($materi['deskripsi'], 0, 90)) ?>...
                            </p>

                            <a href="materi_view.php?id=<?= $materi['id'] ?>" 
                               class="btn btn-action w-100 <?= $is_selesai ? 'btn-outline-dark' : 'btn-dark' ?>">
                                Pelajari <i class="fas fa-chevron-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 text-center mt-5 text-muted small">
        <p>&copy; 2025 PADI-MULOK • Mulok Budaya Komering</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>