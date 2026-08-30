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
    $error_message = "Data profil tidak lengkap. Silakan lapor ke admin.";
}

if (empty($error_message)) {
    // Query Dasar dengan tambahan Rata-rata Nilai (Semua Upaya)
    // Kita ambil AVG dari persentase di tabel riwayat_kuis untuk setiap materi
    $query_pribadi = "
        SELECT m.id, m.judul, m.deskripsi, m.level_kategori, 
               MAX(rk.id) AS riwayat_ada,
               (SELECT AVG(persentase) FROM " . tbl('riwayat_kuis') . " WHERE id_materi = m.id AND id_user = ?) as rata_nilai
        FROM " . tbl('materi') . " m
        LEFT JOIN " . tbl('riwayat_kuis') . " rk ON m.id = rk.id_materi AND rk.id_user = ?
        WHERE m.level_kategori = ? AND m.id_guru = ?
        GROUP BY m.id
    ";

    $query_adopsi = "
        SELECT m.id, m.judul, m.deskripsi, m.level_kategori, 
               MAX(rk.id) AS riwayat_ada,
               (SELECT AVG(persentase) FROM " . tbl('riwayat_kuis') . " WHERE id_materi = m.id AND id_user = ?) as rata_nilai
        FROM " . tbl('penugasan_materi') . " p
        JOIN " . tbl('materi') . " m ON p.id_materi = m.id
        LEFT JOIN " . tbl('riwayat_kuis') . " rk ON m.id = rk.id_materi AND rk.id_user = ?
        WHERE p.ditugaskan_ke = ? AND m.level_kategori = ? AND m.id_guru != ?
        GROUP BY m.id
    ";

    $query_gabungan = "({$query_pribadi}) UNION ({$query_adopsi}) ORDER BY id DESC";
    $stmt = $db_mapel->prepare($query_gabungan);
    
    // Sesuaikan params karena ada penambahan ? untuk rata_nilai
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
    <title>Peta Pembelajaran IPAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #198754;
            --accent-color: #f59e0b;
            --bg-body: #f1f5f9;
        }
        body { 
            background-color: var(--bg-body); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #115e3b 0%, #198754 100%);
            padding: 80px 0 120px 0;
            color: white;
            border-radius: 0 0 50px 50px;
            margin-bottom: -60px;
        }

        /* Stats Card */
        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-icon { font-size: 1.5rem; margin-bottom: 5px; opacity: 0.8; }

        /* Filter Tab */
        .filter-wrapper {
            background: #fff;
            padding: 6px;
            border-radius: 100px;
            display: inline-flex;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .filter-tab {
            padding: 10px 25px;
            border-radius: 100px;
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }

        /* Materi Card */
        .materi-card {
            background: white;
            border: none;
            border-radius: 30px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            border: 1px solid #e2e8f0;
        }
        .materi-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 40px -15px rgba(0,0,0,0.15);
        }
        .card-cover {
            height: 10px;
            background: var(--primary-color);
            width: 100%;
        }
        .card-cover.selesai { background: var(--accent-color); }

        .btn-action {
            border-radius: 15px;
            padding: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
        }

        /* Navbar Custom */
        .navbar-blur {
            background: rgba(25, 135, 84, 0.9) !important;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-blur py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-leaf me-2 text-warning"></i> IPAS PORTAL
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="text-white text-end d-none d-md-block">
                    <small class="d-block opacity-75">Peserta Didik</small>
                    <span class="fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span>
                </div>
                <div class="vr text-white opacity-50 d-none d-md-block"></div>
                <a href="../logout.php" class="btn btn-outline-light rounded-circle"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-section text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Semangat Belajar, <?= explode(' ', $nama_pengguna)[0] ?>!</h1>
            <p class="opacity-75 lead">Lanjutkan petualangan belajarmu di kelas <?= htmlspecialchars($level_kelas) ?></p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        <div class="row g-3 mb-5">
            <div class="col-4 col-md-4">
                <div class="stats-card">
                    <div class="stats-icon text-primary"><i class="fas fa-book-open"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['total'] ?></div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
            <div class="col-4 col-md-4">
                <div class="stats-card">
                    <div class="stats-icon text-success"><i class="fas fa-check-circle"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['selesai'] ?></div>
                    <small class="text-muted">Selesai</small>
                </div>
            </div>
            <div class="col-4 col-md-4">
                <div class="stats-card">
                    <div class="stats-icon text-warning"><i class="fas fa-clock"></i></div>
                    <div class="h4 fw-bold mb-0"><?= $stats['belum'] ?></div>
                    <small class="text-muted">Belum</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
            <div class="filter-wrapper">
                <a href="?status=semua" class="filter-tab <?= $filter_status == 'semua' ? 'active' : '' ?>">Semua</a>
                <a href="?status=belum" class="filter-tab <?= $filter_status == 'belum' ? 'active' : '' ?>">Belum Selesai</a>
                <a href="?status=selesai" class="filter-tab <?= $filter_status == 'selesai' ? 'active' : '' ?>">Selesai</a>
            </div>
        </div>

        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <img src="https://illustrations.popsy.co/green/abstract-art-4.svg" style="width: 200px;" alt="Empty" class="mb-4">
                <h3 class="fw-bold">Ops! Tidak Ada Materi</h3>
                <p class="text-muted">Kategori <b><?= $filter_status ?></b> belum tersedia untukmu.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($materi_list as $materi): 
                    $is_selesai = !empty($materi['riwayat_ada']);
                ?>
                <div class="col-md-6 col-lg-4">
    <div class="card materi-card">
        <div class="card-cover <?= $is_selesai ? 'selesai' : '' ?>"></div>
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge bg-light text-success rounded-pill px-3 py-2">
                    <i class="fas fa-tag me-1"></i> IPAS
                </span>
                <?php if($is_selesai): ?>
                    <i class="fas fa-check-circle text-success fs-3"></i>
                <?php endif; ?>
            </div>
            
            <h4 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($materi['judul']) ?></h4>

<div class="mb-3">
    <?php if (!empty($materi['rata_nilai'])): ?>
        <div class="d-inline-block border border-success rounded-3 px-3 py-1 bg-white shadow-sm">
            <span class="text-muted small me-1">Nilai:</span>
            <span class="fw-bold text-success" style="font-size: 1.1rem;">
                <?= round($materi['rata_nilai']) ?>
            </span>
        </div>
    <?php else: ?>
        <div class="d-inline-block border border-secondary border-opacity-25 rounded-3 px-3 py-1 bg-light">
            <span class="text-muted small">Belum ada upaya</span>
        </div>
    <?php endif; ?>
</div>

<p class="text-muted small mb-4" style="line-height: 1.6;">
    <?= htmlspecialchars(substr($materi['deskripsi'], 0, 100)) ?>...
</p>
            
            <a href="materi_view.php?id=<?= $materi['id'] ?>" 
               class="btn btn-action w-100 <?= $is_selesai ? 'btn-outline-success' : 'btn-success shadow-success' ?>">
                Pelajari Sekarang <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 text-center mt-5">
        <p class="text-muted small">&copy; 2025 PADI-IPAS Portal • Media Pembelajaran</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>