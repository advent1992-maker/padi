<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// 1. Ambil Parameter Aplikasi
$kode_app = $_GET['app'] ?? 'PADI_PORTAL';

// 2. Definisi Instrumen Dinamis
if ($kode_app === 'MATHFICTION_SEM2') {
    $judul_halaman = "Rekap Penilaian Mathfiction";
    $sub_judul = "Hasil evaluasi konten materi oleh siswa";
    $instrumen_tampilan = [
        'A. Aspek Materi Matematika' => [
            'Materi matematika dalam aplikasi ini mudah saya pahami.',
            'Contoh soal yang diberikan membantu saya belajar mandiri.',
            'Bahasa yang digunakan dalam penjelasan materi tidak membingungkan.'
        ],
        'B. Aspek Kemenarikan & Media' => [
            'Tampilan animasi dan gambar materi menarik perhatian saya.',
            'Permainan atau kuis di dalam Mathfiction sangat menantang.',
            'Media ini membuat belajar matematika menjadi lebih menyenangkan.'
        ],
        'C. Aspek Manfaat' => [
            'Setelah menggunakan media ini, saya lebih mengerti rumus-rumus matematika.',
            'Saya ingin mempelajari bab selanjutnya menggunakan aplikasi ini.',
            'Saya merasa lebih percaya diri saat mengerjakan soal latihan.'
        ]
    ];
} else {
    $judul_halaman = "Rekap Penilaian Portal PADI";
    $sub_judul = "Hasil evaluasi antarmuka portal oleh siswa";
    $instrumen_tampilan = [
        'A. Kualitas Antarmuka (UI)' => [
            'Tampilan menu utama PADI mudah dipahami.',
            'Ikon dan warna aplikasi terlihat menarik dan ceria.',
            'Tulisan pada aplikasi mudah dibaca dengan jelas.'
        ],
        'B. Kemudahan Akses (UX)' => [
            'Saya mudah menemukan mata pelajaran yang ingin dipelajari.',
            'Proses pindah dari portal ke materi pelajaran sangat cepat.',
            'Aplikasi ini berjalan lancar tanpa kendala teknis.'
        ]
    ];
}

// 3. Fungsi Kategori
function getKategori($persen) {
    if ($persen >= 81) return ["Sangat Layak", "success"];
    if ($persen >= 61) return ["Layak", "primary"];
    if ($persen >= 41) return ["Cukup Layak", "warning"];
    return ["Tidak Layak", "danger"];
}

// 4. Hitung Statistik Global
$q_global = "SELECT AVG(skor_penilaian) as rata, COUNT(DISTINCT id_user) as resp FROM hasil_uji_siswa WHERE kode_aplikasi = '$kode_app'";
$res_global = $conn->query($q_global)->fetch_assoc();
$rata_persen = (($res_global['rata'] ?? 0) / 5) * 100;
$total_responden = $res_global['resp'] ?? 0;
$status = getKategori($rata_persen);

// 5. AMBIL DAFTAR NAMA SISWA (Termasuk ID User untuk Link Detail)
$q_siswa = "SELECT DISTINCT u.id, u.nama_lengkap, u.kelas 
            FROM hasil_uji_siswa h 
            JOIN users u ON h.id_user = u.id 
            WHERE h.kode_aplikasi = '$kode_app' 
            ORDER BY u.nama_lengkap ASC";
$res_siswa = mysqli_query($conn, $q_siswa);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $judul_halaman ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-main { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .stat-box { border-radius: 20px; padding: 30px; color: white; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .progress { height: 10px; border-radius: 10px; }
        .btn-detail { font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h2 class="fw-bold text-dark"><?= $judul_halaman ?></h2>
            <p class="text-muted"><?= $sub_judul ?></p>
        </div>
        <div class="col-md-5 text-md-end no-print">
            <button onclick="window.print()" class="btn btn-outline-dark px-4 rounded-pill me-2"><i class="fas fa-print me-2"></i>Cetak</button>
            <a href="dashboard.php" class="btn btn-dark px-4 rounded-pill">Kembali</a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-5">
            <div class="stat-box shadow text-center h-100 d-flex flex-column justify-content-center">
                <h6 class="text-uppercase opacity-75 small mb-3">Persentase Kelayakan (Siswa)</h6>
                <h1 class="display-2 fw-bold mb-0"><?= number_format($rata_persen, 1) ?>%</h1>
                <div class="mt-3">
                    <span class="badge bg-white text-dark px-3 py-2 rounded-pill fw-bold"><?= $status[0] ?></span>
                </div>
                <p class="mt-3 mb-0 small opacity-75">Berdasarkan data <b><?= $total_responden ?></b> Responden Siswa</p>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card card-main h-100 p-4">
                <h5 class="fw-bold mb-3">Kesimpulan Hasil:</h5>
                <p class="text-secondary">Berdasarkan persepsi siswa sebagai pengguna akhir, media ini mendapatkan nilai kelayakan sebesar <strong><?= number_format($rata_persen, 1) ?>%</strong>.</p>
                <div class="alert alert-<?= $status[1] ?> border-0 mt-3 rounded-4">
                    <h6 class="fw-bold mb-1"><i class="fas fa-info-circle me-2"></i> Keterangan:</h6>
                    <p class="mb-0 small">Aplikasi dinilai <strong><?= $status[0] ?></strong> untuk digunakan dalam proses pembelajaran di kelas.</p>
                </div>
            </div>
        </div>
    </div>

    <h4 class="fw-bold mb-4"><i class="fas fa-chart-line text-primary me-2"></i> Analisis Per Indikator</h4>
    <div class="card card-main p-4 mb-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-muted small">
                        <th>INDIKATOR PENILAIAN</th>
                        <th width="30%">VISUALISASI</th>
                        <th width="10%" class="text-center">SKOR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instrumen_tampilan as $aspek => $indikators): ?>
                        <tr class="table-light">
                            <td colspan="3" class="fw-bold text-primary"><?= $aspek ?></td>
                        </tr>
                        <?php foreach ($indikators as $ind): 
                            $q = "SELECT AVG(skor_penilaian) as skor FROM hasil_uji_siswa WHERE indikator = '$ind' AND kode_aplikasi = '$kode_app'";
                            $v_skor = $conn->query($q)->fetch_assoc()['skor'] ?? 0;
                            $p_ind = ($v_skor / 5) * 100;
                        ?>
                        <tr>
                            <td style="font-size: 0.9rem;"><?= $ind ?></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?= $p_ind ?>%; background: #764ba2;"></div>
                                </div>
                            </td>
                            <td class="text-center fw-bold"><?= number_format($p_ind, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h4 class="fw-bold mb-4"><i class="fas fa-users text-primary me-2"></i> Daftar Siswa Yang Menilai</h4>
    <div class="row">
        <?php if (mysqli_num_rows($res_siswa) > 0): ?>
            <?php while ($s = mysqli_fetch_assoc($res_siswa)): ?>
                <div class="col-md-4 mb-3">
                    <div class="card card-main p-3 border-start border-primary border-4 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0" style="font-size: 0.9rem;"><?= htmlspecialchars($s['nama_lengkap']) ?></h6>
                                    <small class="text-muted">Kelas: <?= htmlspecialchars($s['kelas']) ?></small>
                                </div>
                            </div>
                            <a href="detail_hasil_siswa.php?id_user=<?= $s['id'] ?>&app=<?= $kode_app ?>" class="btn btn-outline-primary btn-detail">
                                <i class="fas fa-search me-1"></i> Detail
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-4 bg-white rounded-4 shadow-sm">Belum ada siswa yang memberikan penilaian.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>