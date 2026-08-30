<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/session.php'; // Pastikan path benar (naik 2 tingkat)
require_once '../config/koneksi.php'; // Menggunakan koneksi utama ($conn)

// 1. PROTEKSI: Hanya Guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../index.php");
    exit;
}

// 2. KONEKSI DB (SEKARANG SATU JALUR)
// Kita tidak perlu lagi $conn_pd karena tabel sudah digabung ke $conn (portal)
$conn_pusat = $conn; 

// Fungsi untuk menghitung jumlah PAKET di tiap kategori
function hitungPaket($conn, $kategori) {
    if (!$conn) return 0;
    // Mengambil dari tabel yang sekarang sudah ada di database portal
    $query = mysqli_query($conn, "SELECT COUNT(*) as total FROM paket_peng_diri WHERE kategori = '$kategori'");
    if ($query) {
        return mysqli_fetch_assoc($query)['total'];
    }
    return 0;
}

$stats = [
    'literasi' => hitungPaket($conn_pusat, 'literasi'),
    'numerasi' => hitungPaket($conn_pusat, 'numerasi'),
    'osn'      => hitungPaket($conn_pusat, 'osn'),
    'stem'     => hitungPaket($conn_pusat, 'stem'),
    'coding'   => hitungPaket($conn_pusat, 'coding'),
];

// 3. QUERY SISWA AKTIF (Tetap menggunakan koneksi pusat)
$q_siswa = "SELECT id, nama_lengkap, kelas, akses_osn, akses_stem 
            FROM users 
            WHERE role='siswa' 
            AND (akses_osn = 1 OR akses_stem = 1) 
            ORDER BY kelas ASC, nama_lengkap ASC";

$res_siswa = mysqli_query($conn_pusat, $q_siswa);
$jumlah_siswa_aktif = mysqli_num_rows($res_siswa);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pengembangan Diri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-stat { border: none; border-radius: 15px; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .table-card { border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .btn-toggle { width: 90px; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Manajemen Pengembangan Diri</h3>
            <p class="text-muted small">Kelola paket soal dan kontrol akses bimbingan.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="peringkat.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-chart-bar me-2"></i>Nilai & Peringkat
            </a>
            <a href="../dashboard_guru.php" class="btn btn-outline-secondary rounded-pill px-4">Kembali</a>
        </div>
    </div>

    <div class="row g-3 mb-5">
        <?php 
        $colors = ['literasi' => 'primary', 'numerasi' => 'success', 'osn' => 'warning', 'stem' => 'info', 'coding' => 'dark'];
        foreach($stats as $label => $jumlah): 
        ?>
        <div class="col-6 col-md">
            <div class="card card-stat text-center p-3 h-100">
                <div class="small text-uppercase fw-bold text-<?= $colors[$label] ?> mb-1"><?= $label ?></div>
                <h2 class="fw-bold mb-2"><?= $jumlah ?></h2>
                <p class="small text-muted mb-2">Paket Dibuat</p>
                <a href="paket_list.php?kat=<?= $label ?>" class="btn btn-<?= $colors[$label] ?> btn-sm rounded-pill py-1 text-white">Kelola Paket</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card table-card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Siswa Terdaftar Bimbingan</h6>
            <span class="badge bg-primary rounded-pill"><?= $jumlah_siswa_aktif ?> Siswa</span>
        </div>
        <div class="table-responsive">
            <?php if ($jumlah_siswa_aktif > 0): ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nama Siswa</th>
                        <th>Kelas</th>
                        <th class="text-center">Akses OSN</th>
                        <th class="text-center">Akses STEM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($s = mysqli_fetch_assoc($res_siswa)): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                        <td><span class="badge bg-secondary rounded-pill">Kelas <?= $s['kelas'] ?></span></td>
                        <td class="text-center">
                            <?php if($s['akses_osn'] == 1): ?>
                                <a href="" class="btn btn-sm btn-toggle btn-success rounded-pill">
                                    <i class="fas fa-check-circle"></i> Aktif
                                </a>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-times-circle"></i> Mati</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($s['akses_stem'] == 1): ?>
                                <a href="update_tiket.php?id=<?= $s['id'] ?>&tipe=stem&status=0" class="btn btn-sm btn-toggle btn-info text-white rounded-pill">
                                    <i class="fas fa-check-circle"></i> Aktif
                                </a>
                            <?php else: ?>
                                <span class="text-muted small"><i class="fas fa-times-circle"></i> Mati</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="py-5 text-center text-muted">Belum ada siswa yang diberi akses khusus.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>