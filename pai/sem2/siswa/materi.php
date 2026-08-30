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
    <title>Peta Materi | PAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-toska: #00acc1;
            --dark-toska: #00838f;
            --bg-light: #e0f7fa;
        }
        body { 
            background-color: #f8fafc; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
        }

        .hero-pai {
            background: linear-gradient(135deg, #006064 0%, #00acc1 100%);
            padding: 80px 0 130px 0;
            color: white;
            border-radius: 0 0 60px 60px;
            margin-bottom: -70px;
            box-shadow: 0 15px 30px rgba(0, 172, 193, 0.2);
        }

        .glass-stats {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: 0.3s ease;
        }

        .filter-nav {
            background: #fff;
            padding: 7px;
            border-radius: 100px;
            display: inline-flex;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .filter-pill {
            padding: 10px 28px;
            border-radius: 100px;
            text-decoration: none;
            color: #64748b;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .filter-pill.active {
            background: var(--primary-toska);
            color: white;
            box-shadow: 0 5px 15px rgba(0, 172, 193, 0.3);
        }

        .pai-card {
            background: white;
            border: none;
            border-radius: 35px;
            overflow: hidden;
            transition: all 0.4s ease;
            height: 100%;
            border: 1px solid #e2e8f0;
        }
        .pai-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px -15px rgba(0, 172, 193, 0.2);
        }

        /* Badge Nilai Template IPAS */
        .nilai-badge {
            background: #f0fdfe;
            border: 1px solid #cffafe;
            padding: 10px 18px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            color: #0891b2;
        }

        .btn-learn {
            border-radius: 18px;
            padding: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .navbar-custom {
            background: rgba(0, 96, 100, 0.85) !important;
            backdrop-filter: blur(15px);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-mosque me-2 text-warning"></i> PAI SISWA
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="text-white text-end d-none d-md-block">
                    <small class="d-block opacity-75">Peserta Didik</small>
                    <span class="fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span>
                </div>
                <a href="../logout.php" class="btn btn-outline-light rounded-circle"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-pai text-center">
        <div class="container mt-5">
            <h1 class="display-4 fw-800 mb-2">Assalamu'alaikum, <?= explode(' ', $nama_pengguna)[0] ?>! ✨</h1>
            <p class="opacity-75 lead">Mari perkuat iman dan ilmu di kelas <?= htmlspecialchars($level_kelas) ?></p>
        </div>
    </div>

    <div class="container" style="position: relative; z-index: 10;">
        <div class="row g-4 mb-5 text-center">
            <div class="col-4">
                <div class="glass-stats">
                    <div class="text-info mb-1"><i class="fas fa-book-open"></i></div>
                    <div class="h3 fw-bold mb-0"><?= $stats['total'] ?></div>
                    <small class="text-muted fw-600">Total</small>
                </div>
            </div>
            <div class="col-4">
                <div class="glass-stats">
                    <div class="text-success mb-1"><i class="fas fa-check-circle"></i></div>
                    <div class="h3 fw-bold mb-0"><?= $stats['selesai'] ?></div>
                    <small class="text-muted fw-600">Selesai</small>
                </div>
            </div>
            <div class="col-4">
                <div class="glass-stats">
                    <div class="text-warning mb-1"><i class="fas fa-clock"></i></div>
                    <div class="h3 fw-bold mb-0"><?= $stats['belum'] ?></div>
                    <small class="text-muted fw-600">Belum</small>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 gap-3">
            <a href="dashboard.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-lg">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <div class="filter-nav">
                <a href="?status=semua" class="filter-pill <?= $filter_status == 'semua' ? 'active' : '' ?>">Semua</a>
                <a href="?status=belum" class="filter-pill <?= $filter_status == 'belum' ? 'active' : '' ?>">Belum</a>
                <a href="?status=selesai" class="filter-pill <?= $filter_status == 'selesai' ? 'active' : '' ?>">Selesai</a>
            </div>
        </div>

        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <i class="fas fa-star-and-crescent fa-4x text-muted opacity-25 mb-4"></i>
                <h3 class="fw-bold text-muted">Materi Belum Tersedia</h3>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($materi_list as $materi): 
                    $is_selesai = !empty($materi['riwayat_ada']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card pai-card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-2">
                                    <i class="fas fa-book-reader me-1"></i> PAI
                                </span>
                                <?php if($is_selesai): ?>
                                    <i class="fas fa-check-double text-success fs-3"></i>
                                <?php endif; ?>
                            </div>

                            <h4 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($materi['judul']) ?></h4>

                            <div class="mb-4">
                                <?php if (!empty($materi['rata_nilai'])): ?>
                                    <div class="nilai-badge">
                                        <small class="ms-1 opacity-75">Nilai : </small>
                                        <span class="fw-800 fs-5"><?= round($materi['rata_nilai']) ?></span>
                                        
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small italic">
                                        <i class="far fa-edit me-1"></i> Kuis belum dikerjakan
                                    </div>
                                <?php endif; ?>
                            </div>

                            <p class="text-muted small mb-4">
                                <?= htmlspecialchars(substr($materi['deskripsi'], 0, 100)) ?>...
                            </p>

                            <a href="materi_view.php?id=<?= $materi['id'] ?>" 
                               class="btn btn-learn w-100 <?= $is_selesai ? 'btn-outline-info' : 'btn-info text-white' ?>"
                               style="<?= !$is_selesai ? 'background-color: var(--primary-toska); border:none;' : 'color: var(--dark-toska); border-color: var(--primary-toska);' ?>">
                                Pelajari <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 text-center mt-5 text-muted small">
        <p>&copy; 2025 PADI-PAI • Portal Belajar PAI Interaktif</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>