<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/session.php';
require_once '../config/koneksi.php';

// 1. PROTEKSI: Hanya Guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../index.php");
    exit;
}

// 2. KONEKSI DATABASE
$conn_pusat = $conn;

// 3. LOGIKA FILTER MAPEL & PAKET
$mapel_filter = isset($_GET['mapel']) ? $_GET['mapel'] : 'IPA'; // Default ke IPA
$id_materi_filter = isset($_GET['id_materi']) ? $_GET['id_materi'] : '';

// Ambil daftar paket yang sesuai dengan mapel yang dipilih untuk dropdown
$q_paket = mysqli_query($conn, "SELECT id, nama_paket, kategori FROM paket_peng_diri WHERE mapel = '$mapel_filter' ORDER BY nama_paket ASC");

// 4. LOGIKA PENGAMBILAN DATA PERINGKAT
$data_peringkat = [];

if ($id_materi_filter) {
    $sql_nilai = "SELECT id_user, 
                         AVG(persentase) as skor_rata_rata, 
                         COUNT(id) as total_coba, 
                         MAX(tanggal_dikerjakan) as tgl
                  FROM riwayat_kuis 
                  WHERE id_materi = '$id_materi_filter' 
                  GROUP BY id_user 
                  ORDER BY skor_rata_rata DESC, tgl ASC";
    
    $res_nilai = mysqli_query($conn, $sql_nilai);

    while($row = mysqli_fetch_assoc($res_nilai)) {
        $id_u = $row['id_user'];
        $q_user = mysqli_query($conn, "SELECT nama_lengkap, kelas FROM users WHERE id = '$id_u'");
        $user_info = mysqli_fetch_assoc($q_user);

        if($user_info) {
            $data_peringkat[] = [
                'id_user' => $id_u, // TAMBAHKAN BARIS INI
                'nama'    => $user_info['nama_lengkap'],
                'kelas'   => $user_info['kelas'],
                'skor'    => round($row['skor_rata_rata']),
                'coba'    => $row['total_coba']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringkat Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .nav-pills .nav-link { border-radius: 50px; padding: 10px 25px; font-weight: 600; color: #6c757d; }
        .nav-pills .nav-link.active { background-color: #007bff; color: white; box-shadow: 0 4px 10px rgba(0,123,255,0.3); }
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-card { border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .rank-badge { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Peringkat Hasil Latihan</h3>
            <p class="text-muted small">Monitor pencapaian rata-rata nilai siswa secara berkala.</p>
        </div>
        <a href="dashboard.php" class="btn btn-light rounded-pill px-4 border shadow-sm">Kembali</a>
    </div>

    <ul class="nav nav-pills justify-content-center mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($mapel_filter == 'IPA') ? 'active' : '' ?>" href="?mapel=IPA">IPA</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($mapel_filter == 'Matematika') ? 'active' : '' ?>" href="?mapel=Matematika">Matematika</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($mapel_filter == 'IPS') ? 'active' : '' ?>" href="?mapel=IPS">IPS</a>
        </li>
    </ul>

    <div class="card filter-card p-3 mb-4 bg-white">
        <form method="GET" action="" class="row g-2 align-items-center">
            <input type="hidden" name="mapel" value="<?= $mapel_filter ?>">
            <div class="col-md-9">
                <select name="id_materi" class="form-select border-0 bg-light p-3 rounded-4" onchange="this.form.submit()" required>
                    <option value="">-- Pilih Paket <?= $mapel_filter ?> --</option>
                    <?php while($p = mysqli_fetch_assoc($q_paket)): ?>
                        <option value="<?= $p['id'] ?>" <?= ($id_materi_filter == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nama_paket']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 p-3 rounded-4 fw-bold">
                    <i class="fas fa-search me-2"></i>Cari
                </button>
            </div>
        </form>
    </div>

    <?php if ($id_materi_filter): ?>
    <div class="card table-card bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-secondary small text-uppercase">
                        <th class="ps-4 py-3" style="width: 70px;">Rank</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Kelas</th>
                        <th class="text-center">Percobaan</th>
                        <th class="text-center">Skor Rata-rata</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($data_peringkat as $row): 
                        $skor = $row['skor'];
                        $color = ($skor >= 75) ? 'success' : (($skor >= 50) ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="rank-badge bg-<?= ($no == 1) ? 'warning' : (($no == 2) ? 'light border' : 'light') ?> text-dark">
                                <?= $no ?>
                            </div>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($row['nama']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">
                                Kelas <?= $row['kelas'] ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $row['coba'] ?>x</td>
                        <td class="text-center">
                            <h5 class="fw-bold text-<?= $color ?> mb-0"><?= $skor ?></h5>
                        </td>
                        <td class="text-center">
    <a href="detail_riwayat.php?id_user=<?= $row['id_user'] ?>&id_materi=<?= $id_materi_filter ?>" class="btn btn-sm btn-outline-primary rounded-pill">
        <i class="fas fa-history me-1"></i> Riwayat
    </a>
</td>
                    </tr>
                    <?php $no++; endforeach; ?>
                </tbody>
            </table>
            <?php if(empty($data_peringkat)): ?>
                <div class="py-5 text-center text-muted">Belum ada siswa yang mengerjakan paket ini.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-5 opacity-50">
        <i class="fas fa-list-check fa-3x mb-3 text-primary"></i>
        <h6>Silakan pilih paket soal dari daftar di atas</h6>
    </div>
    <?php endif; ?>
</div>

</body>
</html>