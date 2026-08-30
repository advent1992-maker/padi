<?php
require_once 'config/koneksi.php';

$kelas_dipilih = isset($_GET['kelas']) ? mysqli_real_escape_string($conn, $_GET['kelas']) : '4';

// Ambil data game dari database pusat
$query = "SELECT * FROM ifp_games WHERE kelas = '$kelas_dipilih' ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Game Interaktif Kelas <?= $kelas_dipilih ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; }
        .header-game { background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%); color: white; padding: 40px 0; border-radius: 0 0 30px 30px; }
        .card-game { border: none; border-radius: 20px; transition: 0.3s; height: 100%; }
        .card-game:hover { transform: translateY(-10px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .icon-game { font-size: 3.5rem; color: #f5576c; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="header-game text-center shadow mb-5">
    <div class="container">
        <h1 class="fw-bold">GAME INTERAKTIF</h1>
        <p>Pilih permainan seru untuk Kelas <?= $kelas_dipilih ?></p>
        <a href="ifp_list.php?kelas=<?= $kelas_dipilih ?>" class="btn btn-light btn-sm rounded-pill px-4">Kembali</a>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): 
                // Tentukan link tujuan
                $target_link = ($row['tipe_game'] == 'upload') ? "games_storage/" . $row['file_path'] : $row['link_url'];
            ?>
            <div class="col-md-4">
                <div class="card card-game shadow-sm p-4 text-center">
                    <div class="card-body">
                        <i class="fas fa-gamepad icon-game"></i>
                        <h5 class="fw-bold"><?= htmlspecialchars($row['judul']) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars($row['deskripsi']) ?></p>
                        <a href="<?= $target_link ?>" target="_blank" class="btn btn-primary w-100 rounded-pill">
                            Mainkan Game
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="opacity-50 mb-3">
                <p class="text-muted">Belum ada game untuk kelas ini. Guru sedang menyiapkan kejutan!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>