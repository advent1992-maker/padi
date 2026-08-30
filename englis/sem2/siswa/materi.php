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
    // 1. LOGIKA QUERY DENGAN RATA-RATA NILAI
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
    
    // 2. BIND PARAMETER (9 PARAMETER)
    $params = [
        $user_id, $user_id, $level_kelas, $id_guru_pembimbing, // Pribadi
        $user_id, $user_id, $user_id, $level_kelas, $id_guru_pembimbing // Adopsi
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
    <title>English Journey | Learning Map</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #59359a;
            --accent-color: #ffd43b;
            --bg-body: #f8f7ff;
        }
        body { 
            background-color: var(--bg-body); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #2d3436;
        }

        /* Hero Section */
        .hero-english {
            background: linear-gradient(135deg, #4b2a85 0%, #6f42c1 100%);
            padding: 80px 0 120px 0;
            color: white;
            border-radius: 0 0 50px 50px;
            margin-bottom: -60px;
        }

        .stats-box {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(111, 66, 193, 0.1);
        }

        .pills-nav {
            background: #fff;
            padding: 7px;
            border-radius: 100px;
            display: inline-flex;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .pill-item {
            padding: 10px 25px;
            border-radius: 100px;
            text-decoration: none;
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .pill-item.active {
            background: var(--primary-color);
            color: white;
        }

        .english-card {
            background: white;
            border: none;
            border-radius: 30px;
            overflow: hidden;
            transition: all 0.4s;
            height: 100%;
            border: 1px solid #edf2f7;
        }
        .english-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 30px 50px -20px rgba(111, 66, 193, 0.2);
        }
        .card-line {
            height: 8px;
            background: var(--primary-color);
        }
        .card-line.completed { background: #20c997; }

        .btn-open {
            border-radius: 15px;
            padding: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .navbar-custom {
            background: rgba(75, 42, 133, 0.85) !important;
            backdrop-filter: blur(15px);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-globe-americas me-2 text-warning"></i> ENGLISH JOURNEY
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="text-white text-end d-none d-md-block">
                    <small class="d-block opacity-75">Student</small>
                    <span class="fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span>
                </div>
                <a href="../logout.php" class="btn btn-outline-light rounded-circle shadow-sm"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-english text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Keep up the good work, <?= explode(' ', $nama_pengguna)[0] ?>!</h1>
            <p class="opacity-75 lead">Ready to improve your English skills in Level <?= htmlspecialchars($level_kelas) ?>?</p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        <div class="row g-3 mb-5 text-center">
            <div class="col-4">
                <div class="stats-box">
                    <div class="text-primary mb-1"><i class="fas fa-layer-group fa-lg"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-box">
                    <div class="text-success mb-1"><i class="fas fa-check-double fa-lg"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['selesai'] ?></div>
                    <small class="text-muted">Finished</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-box">
                    <div class="text-warning mb-1"><i class="fas fa-hourglass-half fa-lg"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['belum'] ?></div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <div class="pills-nav">
                <a href="?status=semua" class="pill-item <?= $filter_status == 'semua' ? 'active' : '' ?>">All Topics</a>
                <a href="?status=belum" class="pill-item <?= $filter_status == 'belum' ? 'active' : '' ?>">To Do</a>
                <a href="?status=selesai" class="pill-item <?= $filter_status == 'selesai' ? 'active' : '' ?>">Completed</a>
            </div>
        </div>

        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-muted opacity-25 mb-4"></i>
                <h3 class="fw-bold">No Lessons Found</h3>
                <p class="text-muted">Currently, no English materials available.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($materi_list as $materi): 
                    $is_selesai = !empty($materi['riwayat_ada']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card english-card">
                        <div class="card-line <?= $is_selesai ? 'completed' : '' ?>"></div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">
                                    <i class="fas fa-language me-1"></i> English
                                </span>
                                <?php if($is_selesai): ?>
                                    <div class="text-success"><i class="fas fa-check-circle fs-3"></i></div>
                                <?php endif; ?>
                            </div>
                            
                            <h4 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($materi['judul']) ?></h4>

                            <div class="mb-3">
                                <?php if (!empty($materi['rata_nilai'])): ?>
                                    <div class="d-inline-block border border-primary rounded-3 px-3 py-1 bg-white shadow-sm">
                                        <span class="text-muted small me-1">Score:</span>
                                        <span class="fw-bold text-primary" style="font-size: 1.1rem;">
                                            <?= round($materi['rata_nilai']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-inline-block border border-secondary border-opacity-25 rounded-3 px-3 py-1 bg-light">
                                        <span class="text-muted small">No Attempts</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <p class="text-muted small mb-4">
                                <?= htmlspecialchars(substr($materi['deskripsi'], 0, 90)) ?>...
                            </p>
                            
                            <a href="materi_view.php?id=<?= $materi['id'] ?>" 
                               class="btn btn-open w-100 <?= $is_selesai ? 'btn-outline-primary' : 'btn-primary shadow-purple' ?>"
                               style="<?= !$is_selesai ? 'background-color: var(--primary-color); border:none;' : '' ?>">
                                Open Lesson <i class="fas fa-chevron-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 text-center mt-5">
        <p class="text-muted small">&copy; 2025 PADI-English Portal</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>