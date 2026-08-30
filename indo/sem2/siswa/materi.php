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
    // 1. LOGIKA QUERY DENGAN SUBQUERY RATA-RATA NILAI
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
    
    // 2. BIND PARAMETER (TOTAL 9 PARAMETER)
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
    <title>Peta Pembelajaran Bahasa Indonesia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --marun-utama: #8b0000;
            --marun-muda: #a52a2a;
            --aksen-emas: #ffc107;
            --bg-hal: #fffafa;
        }
        body { 
            background-color: var(--bg-hal); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #2d3436;
        }

        /* Hero Section */
        .hero-indo {
            background: linear-gradient(135deg, #5c0000 0%, #8b0000 100%);
            padding: 80px 0 120px 0;
            color: white;
            border-radius: 0 0 50px 50px;
            margin-bottom: -60px;
            box-shadow: 0 10px 30px rgba(139, 0, 0, 0.2);
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid rgba(139, 0, 0, 0.1);
        }

        .filter-container {
            background: #fff;
            padding: 6px;
            border-radius: 100px;
            display: inline-flex;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .filter-btn {
            padding: 10px 25px;
            border-radius: 100px;
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .filter-btn.active {
            background: var(--marun-utama);
            color: white;
        }

        .materi-card {
            background: white;
            border: none;
            border-radius: 30px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            border: 1px solid #f1f1f1;
        }
        .materi-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px -12px rgba(139, 0, 0, 0.15);
        }
        .card-top-line {
            height: 8px;
            background: var(--marun-utama);
        }
        .card-top-line.selesai { background: #28a745; }

        .btn-buka {
            border-radius: 15px;
            padding: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .navbar-blur {
            background: rgba(139, 0, 0, 0.9) !important;
            backdrop-filter: blur(15px);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-blur py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-book-open me-2 text-warning"></i> BAHASA INDONESIA
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

    <div class="hero-indo text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Halo, <?= explode(' ', $nama_pengguna)[0] ?>! 👋</h1>
            <p class="opacity-75 lead">Mari kembangkan kemampuan berbahasa di kelas <?= htmlspecialchars($level_kelas) ?></p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        <div class="row g-3 mb-5 text-center">
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-marun mb-1"><i class="fas fa-layer-group"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div>
                    <small class="text-muted small">Total</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-success mb-1"><i class="fas fa-check-circle"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['selesai'] ?></div>
                    <small class="text-muted small">Selesai</small>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card">
                    <div class="text-warning mb-1"><i class="fas fa-hourglass-half"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['belum'] ?></div>
                    <small class="text-muted small">Belum</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <div class="filter-container">
                <a href="?status=semua" class="filter-btn <?= $filter_status == 'semua' ? 'active' : '' ?>">Semua</a>
                <a href="?status=belum" class="filter-btn <?= $filter_status == 'belum' ? 'active' : '' ?>">Belum</a>
                <a href="?status=selesai" class="filter-btn <?= $filter_status == 'selesai' ? 'active' : '' ?>">Selesai</a>
            </div>
        </div>

        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <i class="fas fa-feather fa-4x text-muted opacity-25 mb-4"></i>
                <h3 class="fw-bold text-muted">Materi Kosong</h3>
                <p class="text-muted">Belum ada materi Bahasa Indonesia tersedia.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($materi_list as $materi): 
                    $is_selesai = !empty($materi['riwayat_ada']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card materi-card">
                        <div class="card-top-line <?= $is_selesai ? 'selesai' : '' ?>"></div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2">
                                    <i class="fas fa-language me-1"></i> B. Indonesia
                                </span>
                                <?php if($is_selesai): ?>
                                    <i class="fas fa-check-double text-success fs-4"></i>
                                <?php endif; ?>
                            </div>
                            
                            <h4 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($materi['judul']) ?></h4>

                            <div class="mb-3">
                                <?php if (!empty($materi['rata_nilai'])): ?>
                                    <div class="d-inline-block border border-danger border-opacity-50 rounded-3 px-3 py-1 bg-white shadow-sm">
                                        <span class="text-muted small me-1">Nilai:</span>
                                        <span class="fw-bold text-danger" style="font-size: 1.1rem;">
                                            <?= round($materi['rata_nilai']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-inline-block border border-secondary border-opacity-25 rounded-3 px-3 py-1 bg-light">
                                        <span class="text-muted small">Belum ada upaya</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <p class="text-muted small mb-4" style="line-height: 1.5;">
                                <?= htmlspecialchars(substr($materi['deskripsi'], 0, 90)) ?>...
                            </p>
                            
                            <a href="materi_view.php?id=<?= $materi['id'] ?>" 
                               class="btn btn-buka w-100 <?= $is_selesai ? 'btn-outline-danger' : 'btn-danger' ?>"
                               style="<?= !$is_selesai ? 'background-color: var(--marun-utama); border:none;' : 'color: var(--marun-utama); border-color: var(--marun-utama);' ?>">
                                Pelajari Materi <i class="fas fa-chevron-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 text-center mt-5 text-muted small">
        <p>&copy; 2025 Padi-B.Indonesia Portal</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>