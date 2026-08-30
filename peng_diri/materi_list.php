<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// Proteksi: Hanya Siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit;
}

$kat = $_GET['kat'] ?? 'osn';
$mapel_aktif = $_GET['mapel'] ?? 'IPA'; 
// Tab aktif (kuis atau modul)
$tab_aktif = $_GET['tab'] ?? 'kuis';
$user_id = $_SESSION['user_id'];

// Koneksi ke Database Pengembangan Diri
$conn_pusat = $conn;

// Ambil data berdasarkan Tab yang dipilih
if($tab_aktif == 'kuis') {
    $query = "SELECT * FROM paket_peng_diri WHERE kategori = '$kat' AND mapel = '$mapel_aktif' AND tampilkan = 1 ORDER BY id DESC";
} else {
    $query = "SELECT * FROM materi_peng_diri WHERE kategori = '$kat' AND mapel = '$mapel_aktif' ORDER BY id DESC";
}
$result = mysqli_query($conn_pusat, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bimbingan <?= strtoupper($kat) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        .mobile-header { position: sticky; top: 0; z-index: 1000; background: #fff; padding: 12px 15px; border-bottom: 1px solid #eee; }
        
        /* Navigasi Mapel */
        .nav-scroller { background: #fff; padding: 10px 0; border-bottom: 1px solid #eee; overflow-x: auto; white-space: nowrap; display: flex; gap: 8px; padding-left: 15px; }
        .nav-scroller::-webkit-scrollbar { display: none; }
        .nav-link-m { padding: 8px 20px; border-radius: 20px; background: #f1f3f5; color: #495057; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .nav-link-m.active { background: #0d6efd; color: #fff; }

        /* Tab Kuis vs Modul */
        .tab-container { background: #fff; padding: 5px; border-radius: 12px; display: flex; margin: 15px; border: 1px solid #eee; }
        .tab-item { flex: 1; text-align: center; padding: 10px; border-radius: 10px; text-decoration: none; color: #6c757d; font-weight: 600; font-size: 0.9rem; transition: 0.3s; }
        .tab-item.active { background: #6610f2; color: #fff; shadow: 0 4px 10px rgba(102, 16, 242, 0.2); }

        /* List Styling */
        .list-card { background: #fff; border: 1px solid #eee; border-radius: 16px; margin-bottom: 12px; padding: 16px; display: flex; align-items: center; text-decoration: none; color: inherit; transition: 0.2s; }
        .list-card:active { background: #f0f0f0; transform: scale(0.98); }
        
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px; flex-shrink: 0; }
        .bg-kuis { background: #e7f1ff; color: #0d6efd; }
        .bg-modul { background: #f3eaff; color: #6610f2; }

        .info h6 { font-weight: 700; margin-bottom: 2px; font-size: 0.95rem; line-height: 1.3; }
        .score-badge { font-size: 0.7rem; font-weight: bold; padding: 3px 10px; border-radius: 50px; display: inline-block; margin-top: 5px; }
    </style>
</head>
<body>

<div class="mobile-header">
    <div class="container d-flex align-items-center">
        <a href="pengembangan_diri.php" class="btn btn-light rounded-circle border me-3" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div>
            <span class="badge bg-primary mb-1" style="font-size: 0.6rem;"><?= strtoupper($kat) ?></span>
            <h5 class="fw-bold mb-0" style="font-size: 1.1rem;">Materi & Latihan</h5>
        </div>
    </div>
</div>

<div class="nav-scroller shadow-sm">
    <a href="?kat=<?= $kat ?>&mapel=IPA&tab=<?= $tab_aktif ?>" class="nav-link-m <?= $mapel_aktif == 'IPA' ? 'active' : '' ?>">IPA</a>
    <a href="?kat=<?= $kat ?>&mapel=Matematika&tab=<?= $tab_aktif ?>" class="nav-link-m <?= $mapel_aktif == 'Matematika' ? 'active' : '' ?>">Matematika</a>
    <a href="?kat=<?= $kat ?>&mapel=IPS&tab=<?= $tab_aktif ?>" class="nav-link-m <?= $mapel_aktif == 'IPS' ? 'active' : '' ?>">IPS</a>
</div>

<div class="tab-container">
    <a href="?kat=<?= $kat ?>&mapel=<?= $mapel_aktif ?>&tab=kuis" class="tab-item <?= $tab_aktif == 'kuis' ? 'active' : '' ?>">
        <i class="fas fa-tasks me-1"></i> Latihan Soal
    </a>
    <a href="?kat=<?= $kat ?>&mapel=<?= $mapel_aktif ?>&tab=modul" class="tab-item <?= $tab_aktif == 'modul' ? 'active' : '' ?>">
        <i class="fas fa-book-open me-1"></i> Modul Bacaan
    </a>
</div>

<div class="container">
    <div class="row">
        <div class="col-12 col-md-8 mx-auto">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($data = mysqli_fetch_assoc($result)): ?>
                    
                    <?php if($tab_aktif == 'kuis'): ?>
                        <?php 
                            $id_materi = $data['id'];
                            $q_riwayat = mysqli_query($conn_pusat, "SELECT persentase FROM riwayat_kuis WHERE id_user = '$user_id' AND id_materi = '$id_materi'");
                            $jumlah_percobaan = mysqli_num_rows($q_riwayat);
                            $total_persentase = 0;
                            while($r = mysqli_fetch_assoc($q_riwayat)) { $total_persentase += $r['persentase']; }
                            $rata_rata = ($jumlah_percobaan > 0) ? round($total_persentase / $jumlah_percobaan) : 0;
                            $is_limit = ($jumlah_percobaan >= 1);
                        ?>
                        <?php if($is_limit): ?>
    <div class="list-card shadow-sm opacity-75" style="cursor: not-allowed;">
        <div class="icon-box bg-kuis">
            <i class="fas fa-lock"></i>
        </div>
        <div class="info">
            <h6><?= htmlspecialchars($data['nama_paket']) ?></h6>
            <span class="score-badge bg-primary text-white">Nilai: <?= $rata_rata ?></span>
            <small class="text-muted ms-1" style="font-size: 0.7rem;">(<?= $jumlah_percobaan ?>/1) Sudah dikerjakan</small>
        </div>
        <div class="ms-auto"><i class="fas fa-lock text-muted fa-xs"></i></div>
    </div>
<?php else: ?>
    <a href="kerjakan_osn.php?paket_id=<?= $data['id'] ?>" class="list-card shadow-sm">
        <div class="icon-box bg-kuis">
            <i class="fas fa-play"></i>
        </div>
        <div class="info">
            <h6><?= htmlspecialchars($data['nama_paket']) ?></h6>
            <?php if($jumlah_percobaan > 0): ?>
                <span class="score-badge bg-primary text-white">Nilai: <?= $rata_rata ?></span>
                <small class="text-muted ms-1" style="font-size: 0.7rem;">(<?= $jumlah_percobaan ?>/1)</small>
            <?php else: ?>
                <p class="small text-muted mb-0">Belum dikerjakan</p>
            <?php endif; ?>
        </div>
        <div class="ms-auto"><i class="fas fa-chevron-right text-muted fa-xs"></i></div>
    </a>
<?php endif; ?>

                    <?php else: ?>
                        <a href="modul_view.php?id=<?= $data['id'] ?>" class="list-card shadow-sm">
                            <div class="icon-box bg-modul">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="info">
                                <h6><?= htmlspecialchars($data['judul_materi']) ?></h6>
                                <p class="small text-muted mb-0"><i class="far fa-clock me-1"></i> Materi Bacaan</p>
                            </div>
                            <div class="ms-auto">
                                <span class="badge rounded-pill bg-light text-primary border">Buka</span>
                            </div>
                        </a>
                    <?php endif; ?>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="60" class="opacity-25 mb-3">
                    <h6 class="text-muted">Belum ada <?= $tab_aktif ?> <?= $mapel_aktif ?></h6>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>