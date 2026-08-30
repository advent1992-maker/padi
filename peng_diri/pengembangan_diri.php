<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

$user_id = $_SESSION['user_id'];
$namaSiswa = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];

// Ambil status akses OSN dan STEM
$q_akses = "SELECT akses_osn, akses_stem FROM users WHERE id = ?";
$stmt = $conn->prepare($q_akses);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$punya_osn  = $user_data['akses_osn'] ?? 0;
$punya_stem = $user_data['akses_stem'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Pengembangan Diri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .header-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 50px 0; border-radius: 0 0 40px 40px; }
        .menu-card { border: none; border-radius: 20px; transition: 0.3s; cursor: pointer; height: 100%; position: relative; overflow: hidden; }
        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .icon-circle { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; font-size: 1.5rem; color: white; }
        .locked-card { background: #e9ecef !important; cursor: not-allowed !important; opacity: 0.8; }
        .lock-icon { position: absolute; top: 15px; right: 15px; color: #6c757d; }
    </style>
</head>
<body>

<div class="header-section text-center mb-4">
    <div class="container position-relative">
        <a href="../dashboard.php" class="btn btn-sm btn-light rounded-pill position-absolute start-0 top-0">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h3 class="fw-bold mb-1">Halo, <?= htmlspecialchars($namaSiswa) ?>!</h3>
        <p class="small opacity-75">Hanya yang terpilih yang bisa ikut OSN & STEM!</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-3">
        <div class="col-6 col-md-4">
            <div class="card menu-card p-3 shadow-sm" onclick="location.href='materi_list.php?kat=literasi'">
                <div class="icon-circle bg-primary"><i class="fas fa-book-open"></i></div>
                <h6 class="fw-bold mb-1">Literasi</h6>
                <small class="text-muted" style="font-size: 0.7rem;">Umum</small>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card menu-card p-3 shadow-sm" onclick="location.href='materi_list.php?kat=numerasi'">
                <div class="icon-circle bg-success"><i class="fas fa-percentage"></i></div>
                <h6 class="fw-bold mb-1">Numerasi</h6>
                <small class="text-muted" style="font-size: 0.7rem;">Umum</small>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <?php if ($punya_stem == 1): ?>
                <div class="card menu-card p-3 shadow-sm" onclick="location.href='materi_list.php?kat=stem'">
                    <div class="icon-circle bg-info"><i class="fas fa-microscope"></i></div>
                    <h6 class="fw-bold mb-1">STEM</h6>
                    <span class="badge bg-info position-absolute top-0 end-0 m-2" style="font-size: 0.5rem;">Terbuka</span>
                </div>
            <?php else: ?>
                <div class="card menu-card p-3 locked-card" onclick="alert('Tiket STEM belum aktif. Hubungi Pak Hari!')">
                    <i class="fas fa-lock lock-icon"></i>
                    <div class="icon-circle bg-secondary"><i class="fas fa-microscope"></i></div>
                    <h6 class="fw-bold mb-1 text-muted">STEM</h6>
                    <small class="text-muted" style="font-size: 0.6rem;">Perlu Tiket</small>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-6 col-md-4">
            <?php if ($punya_osn == 1): ?>
                <div class="card menu-card p-3 shadow-sm" onclick="location.href='materi_list.php?kat=osn'">
                    <div class="icon-circle bg-warning"><i class="fas fa-trophy"></i></div>
                    <h6 class="fw-bold mb-1">OSN</h6>
                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2" style="font-size: 0.5rem;">Terbuka</span>
                </div>
            <?php else: ?>
                <div class="card menu-card p-3 locked-card" onclick="alert('Tiket OSN belum aktif. Hubungi Pak Hari!')">
                    <i class="fas fa-lock lock-icon"></i>
                    <div class="icon-circle bg-secondary"><i class="fas fa-trophy"></i></div>
                    <h6 class="fw-bold mb-1 text-muted">OSN</h6>
                    <small class="text-muted" style="font-size: 0.6rem;">Perlu Tiket</small>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-6 col-md-4">
            <div class="card menu-card p-3 shadow-sm" onclick="location.href='materi_list.php?kat=coding'">
                <div class="icon-circle bg-dark"><i class="fas fa-code"></i></div>
                <h6 class="fw-bold mb-1">Coding</h6>
                <small class="text-muted" style="font-size: 0.7rem;">Umum</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>