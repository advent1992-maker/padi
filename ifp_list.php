<?php
require_once 'config/koneksi.php';

// Ambil parameter kelas dari dashboard sebelumnya
$kelas_dipilih = isset($_GET['kelas']) ? $_GET['kelas'] : '4';

// Daftar mata pelajaran yang tersedia sesuai struktur database Anda
$daftar_mapel = [
    'panca'  => ['nama' => 'Pancasila', 'warna' => 'btn-danger', 'icon' => 'fa-book'],
    'ipas'   => ['nama' => 'IPAS', 'warna' => 'btn-success', 'icon' => 'fa-flask'],
    'mtk'    => ['nama' => 'Matematika', 'warna' => 'btn-primary', 'icon' => 'fa-calculator'],
    'indo'   => ['nama' => 'Bahasa Indonesia', 'warna' => 'btn-warning', 'icon' => 'fa-language'],
    'englis' => ['nama' => 'English', 'warna' => 'btn-info', 'icon' => 'fa-font'],
    'pjok'   => ['nama' => 'PJOK', 'warna' => 'btn-secondary', 'icon' => 'fa-running'],
    'pai'    => ['nama' => 'PAI', 'warna' => 'btn-dark', 'icon' => 'fa-mosque'],
    'mulok'  => ['nama' => 'Mulok', 'warna' => 'btn-dark', 'icon' => 'fa-map-marked-alt'],
    'seni'   => ['nama' => 'Seni Rupa', 'warna' => 'btn-info', 'icon' => 'fa-palette']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pilih Mata Pelajaran | IFP PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
        .header-box { background: #764ba2; color: white; padding: 40px 0; border-radius: 0 0 30px 30px; }
        .btn-mapel { 
            height: 120px; display: flex; align-items: center; justify-content: start; 
            font-size: 1.2rem; font-weight: 700; border-radius: 15px; margin-bottom: 15px;
            transition: 0.3s;
        }
        .btn-mapel:hover { transform: scale(1.03); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn-mapel i { font-size: 2.5rem; margin-right: 20px; opacity: 0.8; }
    </style>
</head>
<body>

<div class="header-box text-center shadow mb-5">
    <div class="container">
        <h1 class="fw-bold text-uppercase">KELAS <?= htmlspecialchars($kelas_dipilih) ?></h1>
        <p class="mb-0">Silakan pilih mata pelajaran untuk melihat materi</p>
    </div>
    <div class="text-center mt-5">
        <a href="ifp.php" class="btn btn-outline-dark">
            <i class="fas fa-arrow-left"></i> Kembali Pilih Kelas
        </a>
    </div>
</div>

<div class="container">
    <div class="row justify-content-center">
        <?php 
        $ada_mapel_tampil = false; 

        foreach ($daftar_mapel as $key => $mapel): 
            // --- LOGIKA PENGGABUNGAN DATABASE (Panca ke Mulok) ---
            $folder_koneksi = ($key == 'panca') ? 'mulok' : $key;
            $db_name = $prefix . "db_" . $folder_koneksi . "_sm2"; 
            
            // Tentukan user database
            $user_mapel = $prefix . (
                $key == 'panca' ? 'kristian' : // Pakai user mulok untuk panca
                ($key == 'ipas' ? 'hari' : 
                ($key == 'mtk' ? 'advent' : 
                ($key == 'indo' ? 'harrieya' : 
                ($key == 'englis' ? 'kris' : 
                ($key == 'pjok' ? 'derry' : 
                ($key == 'pai' ? 'arq' :
                ($key == 'seni' ? 'senirupa' : 'kristian')))))))
            );

            // Tentukan nama tabel (panca_materi atau materi)
            $nama_tabel = ($key == 'panca') ? 'panca_materi' : 'materi';

            $conn_cek = @mysqli_connect($host, $user_mapel, $pass, $db_name);
            $punya_materi = false;

            if ($conn_cek) {
                // Gunakan variabel $nama_tabel di query
                $q_cek = mysqli_query($conn_cek, "SELECT id FROM $nama_tabel WHERE level_kategori = '$kelas_dipilih' LIMIT 1");
                if ($q_cek && mysqli_num_rows($q_cek) > 0) {
                    $punya_materi = true;
                    $ada_mapel_tampil = true; 
                }
                mysqli_close($conn_cek);
            }

            if ($punya_materi): 
        ?>
            <div class="col-md-6 col-lg-4">
                <a href="ifp_materi.php?mapel=<?= $key ?>&kelas=<?= $kelas_dipilih ?>" 
                   class="btn <?= $mapel['warna'] ?> btn-mapel w-100 shadow-sm text-white">
                    <i class="fas <?= $mapel['icon'] ?>"></i>
                    <?= $mapel['nama'] ?>
                </a>
            </div>
        <?php 
            endif; 
        endforeach; 
        ?>

        <div class="col-12 mt-4">
            <hr class="mb-5" style="border-top: 2px dashed #764ba2; opacity: 0.3;">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <a href="ifp_game.php?kelas=<?= $kelas_dipilih ?>" 
                       class="btn btn-warning btn-mapel w-100 shadow text-white" 
                       style="background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <i class="fas fa-gamepad"></i>
                        Game Interaktif
                    </a>
                </div>
            </div>
        </div>

        <?php 
        // TAMPILKAN PESAN INI JIKA TIDAK ADA MAPEL YANG MUNCUL
        if (!$ada_mapel_tampil): 
        ?>
            <div class="col-md-8 text-center py-5">
                <div class="card border-0 shadow-sm p-5 rounded-4">
                    <i class="fas fa-tools fa-4x text-muted mb-3"></i>
                    <h4 class="fw-bold text-secondary">Mohon Maaf</h4>
                    <p class="text-muted mb-0">Materi untuk Kelas <?= htmlspecialchars($kelas_dipilih) ?> masih dalam proses pengembangan.</p>
                    <div class="mt-4">
                        <a href="ifp.php" class="btn btn-primary rounded-pill px-4">Kembali Pilih Kelas</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>