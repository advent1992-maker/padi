<?php
require_once 'config/koneksi.php'; // Mengambil $conn pusat, $host, $pass, $prefix

// 1. Ambil Parameter
$kelas_dipilih = isset($_GET['kelas']) ? mysqli_real_escape_string($conn, $_GET['kelas']) : '4';
$mapel_dipilih = isset($_GET['mapel']) ? $_GET['mapel'] : '';

// 2. Konfigurasi Mapel & Database
$daftar_mapel = [
    'panca'  => ['nama' => 'Pancasila', 'warna' => 'btn-danger', 'icon' => 'fa-book', 'user' => 'adventgool'],
    'ipas'   => ['nama' => 'IPAS', 'warna' => 'btn-success', 'icon' => 'fa-flask', 'user' => 'hari'],
    'mtk'    => ['nama' => 'Matematika', 'warna' => 'btn-primary', 'icon' => 'fa-calculator', 'user' => 'advent'],
    'indo'   => ['nama' => 'Bahasa Indonesia', 'warna' => 'btn-warning', 'icon' => 'fa-language', 'user' => 'harrieya'],
    'englis' => ['nama' => 'English', 'warna' => 'btn-info', 'icon' => 'fa-font', 'user' => 'kris'],
    'pjok'   => ['nama' => 'PJOK', 'warna' => 'btn-secondary', 'icon' => 'fa-running', 'user' => 'derry'],
    'pai'    => ['nama' => 'PAI', 'warna' => 'btn-dark', 'icon' => 'fa-mosque', 'user' => 'arq'],
    'mulok'  => ['nama' => 'Mulok', 'warna' => 'btn-outline-dark', 'icon' => 'fa-map-marked-alt', 'user' => 'kristian'],
    'seni'   => ['nama' => 'Seni Rupa', 'warna' => 'btn-info', 'icon' => 'fa-palette', 'user' => 'senirupa']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IFP PADI - Mode Layar Interaktif</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Poppins', sans-serif; }
        .header-ifp { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 0; border-radius: 0 0 30px 30px; }
        .card-custom { border: none; border-radius: 15px; transition: 0.3s; height: 100%; }
        .card-custom:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn-mapel { height: 100px; display: flex; align-items: center; font-weight: bold; font-size: 1.1rem; border-radius: 15px; color: white !important; }
    </style>
</head>
<body>

<div class="header-ifp text-center shadow mb-5">
    <div class="container">
        <h1 class="fw-bold">KELAS <?= htmlspecialchars($kelas_dipilih) ?></h1>
        <p class="opacity-75"><?= empty($mapel_dipilih) ? 'Pilih Mata Pelajaran' : 'Pilih Materi Pembelajaran' ?></p>
        <a href="ifp_list.php?kelas=<?= $kelas_dipilih ?>" class="btn btn-sm btn-light rounded-pill px-3">Ganti Mata Pelajaran</a>
    </div>
</div>

<div class="container pb-5">
    <?php if (empty($mapel_dipilih)): ?>
        <div class="row g-3">
            <?php foreach ($daftar_mapel as $key => $m): ?>
                <div class="col-md-6 col-lg-3">
                    <a href="?kelas=<?= $kelas_dipilih ?>&mapel=<?= $key ?>" class="btn <?= $m['warna'] ?> btn-mapel w-100 shadow-sm">
                        <i class="fas <?= $m['icon'] ?> fa-2x me-3"></i> <?= $m['nama'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <?php
        // --- LOGIKA PENGGABUNGAN DATABASE (Redirect Panca ke Mulok) ---
        $folder_koneksi = ($mapel_dipilih == 'panca') ? 'mulok' : $mapel_dipilih;
        $db_name = $prefix . "db_" . $folder_koneksi . "_sm2"; 
        
        // Gunakan user mulok jika mapelnya panca
        $user_db = ($mapel_dipilih == 'panca') 
                   ? $prefix . $daftar_mapel['mulok']['user'] 
                   : $prefix . $daftar_mapel[$mapel_dipilih]['user'];
        
        $db_target = @mysqli_connect($host, $user_db, $pass, $db_name);

        if (!$db_target) {
            echo "<div class='alert alert-danger'>Database mapel tidak ditemukan.</div>";
        } else {
            // Tentukan prefix tabel jika panca
            $table_prefix = ($mapel_dipilih == 'panca') ? 'panca_' : '';

            // Query Materi TANPA Join ke Users
$query = "SELECT id, judul, deskripsi 
          FROM {$table_prefix}materi 
          WHERE level_kategori = '$kelas_dipilih' 
          GROUP BY judul 
          ORDER BY id DESC";

$result = mysqli_query($db_target, $query);
        ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold">Materi <?= htmlspecialchars($daftar_mapel[$mapel_dipilih]['nama']) ?></h3>
                <a href="ifp_list.php?kelas=<?= $kelas_dipilih ?>" class="btn btn-secondary btn-sm">Kembali ke Mapel</a>
            </div>

            <div class="row g-4">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <div class="col-md-4">
                            <div class="card card-custom shadow-sm p-3">
                                <div class="card-body d-flex flex-column text-center">
                                    <h5 class="fw-bold"><?= htmlspecialchars($row['judul']) ?></h5>
                                    <p class="text-muted small flex-grow-1"><?= htmlspecialchars(substr($row['deskripsi'], 0, 80)) ?>...</p>
                                    <hr>
                                    <a href="<?= $mapel_dipilih ?>/sem2/guru/view_ifp.php?id=<?= $row['id'] ?>" class="btn btn-primary w-100 rounded-pill" target="_blank">
                                        <i class="fas fa-play-circle me-1"></i> Buka Materi
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5 bg-white rounded-4">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <p>Belum ada materi untuk kelas dan mata pelajaran ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php } ?>
    <?php endif; ?>
</div>

</body>
</html>