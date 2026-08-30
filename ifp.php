<?php
require_once 'config/koneksi.php'; // Mengambil $host, $pass, $prefix

// Daftar mapel dan user database-nya untuk pengecekan materi
$cek_mapel = [
    'panca'  => 'kristian',
    'ipas'   => 'hari',
    'mtk'    => 'advent',
    'indo'   => 'harrieya',
    'englis' => 'kris',
    'pjok'   => 'derry',
    'pai'    => 'arq',
    'mulok'  => 'kristian'
];

// Fungsi untuk cek apakah sebuah kelas punya minimal 1 materi di salah satu mapel
// Fungsi untuk cek apakah sebuah kelas punya minimal 1 materi di salah satu mapel
function cek_materi_kelas($kelas, $cek_mapel, $host, $pass, $prefix) {
    foreach ($cek_mapel as $mapel => $user) {
        // --- LOGIKA PENGGABUNGAN DATABASE ---
        // Jika mapel panca, belokkan ke database mulok
        $folder_koneksi = ($mapel == 'panca') ? 'mulok' : $mapel;
        $db_name = $prefix . "db_" . $folder_koneksi . "_sm2";
        
        // Gunakan user mulok jika mapelnya panca (karena panca numpang di DB mulok)
        $db_user = ($mapel == 'panca') ? $prefix . $cek_mapel['mulok'] : $prefix . $user;
        
        // Tentukan prefix tabel (panca_materi atau materi)
        $table_name = ($mapel == 'panca') ? 'panca_materi' : 'materi';
        
        $conn_cek = @mysqli_connect($host, $db_user, $pass, $db_name);
        
        if ($conn_cek) {
            // Gunakan $table_name yang dinamis
            $q = mysqli_query($conn_cek, "SELECT id FROM $table_name WHERE level_kategori = '$kelas' LIMIT 1");
            
            if ($q && mysqli_num_rows($q) > 0) {
                mysqli_close($conn_cek);
                return true; 
            }
            mysqli_close($conn_cek);
        }
    }
    return false;
}

// Konfigurasi tampilan tombol kelas
$list_kelas = [
    '1' => ['warna' => 'btn-primary'],
    '2' => ['warna' => 'btn-success'],
    '3' => ['warna' => 'btn-info'],
    '4' => ['warna' => 'btn-primary'],
    '5' => ['warna' => 'btn-success'],
    '6' => ['warna' => 'btn-warning text-white']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard IFP | PADI Interaktif</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            font-family: 'Poppins', sans-serif;
            padding: 20px 0;
        }
        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .btn-kelas {
            height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            border-radius: 15px;
            transition: 0.3s;
            border: 4px solid transparent;
            margin-bottom: 20px;
        }
        .btn-kelas:hover {
            transform: scale(1.05);
            border-color: #fff;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center text-center">
        <div class="col-lg-10 dashboard-card">
            <h1 class="fw-bold text-dark mb-2">SELAMAT MENGAJAR</h1>
            <p class="text-muted mb-5">Pilih Jenjang Kelas untuk Memulai Presentasi Interaktif</p>

            <div class="row g-4 justify-content-center">
                <?php 
                $ada_kelas_aktif = false;
                foreach ($list_kelas as $kls => $tampilan): 
                    // Panggil fungsi cek materi
                    if (cek_materi_kelas($kls, $cek_mapel, $host, $pass, $prefix)):
                        $ada_kelas_aktif = true;
                ?>
                    <div class="col-md-4">
                        <a href="ifp_list.php?kelas=<?= $kls ?>" class="btn <?= $tampilan['warna'] ?> btn-kelas w-100 shadow">
                            <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                            KELAS <?= $kls ?>
                        </a>
                    </div>
                <?php 
                    endif;
                endforeach; 

                if (!$ada_kelas_aktif):
                ?>
                    <div class="col-12 py-5 text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <p>Belum ada media pembelajaran yang tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-5 pt-4 border-top">
                <p class="text-muted small">Materi diambil dari aplikasi PADI</p>
                <a href="https://hariadventapps.my.id/" class="btn btn-sm btn-outline-secondary">Kembali ke HaryApps</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>