<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$kode_app = 'PADI_PORTAL';

/**
 * 1. LOGIKA UTAMA: HITUNG SKOR TOTAL & RATA-RATA PER INDIKATOR
 */
$q_total = "SELECT AVG(skor) as rata_total, COUNT(id) as jml_responden FROM hasil_uji_guru WHERE kode_aplikasi = '$kode_app'";
$res_total = mysqli_query($conn, $q_total)->fetch_assoc();

$persentase_total = $res_total['rata_total'] ?? 0;
$jml_guru = $res_total['jml_responden'] ?? 0;

$q_per_soal = "SELECT 
    AVG(q1) as avg_q1, AVG(q2) as avg_q2, AVG(q3) as avg_q3, AVG(q4) as avg_q4, 
    AVG(q5) as avg_q5, AVG(q6) as avg_q6, AVG(q7) as avg_q7, AVG(q8) as avg_q8, 
    AVG(q9) as avg_q9, AVG(q10) as avg_q10 
    FROM hasil_uji_guru WHERE kode_aplikasi = '$kode_app'";
$res_per_soal = mysqli_query($conn, $q_per_soal)->fetch_assoc();

function getKategoriPersentase($persentase) {
    if ($persentase >= 81) return ["Sangat Praktis", "success", "Sangat Praktis digunakan tanpa revisi"];
    if ($persentase >= 61) return ["Praktis", "primary", "Praktis digunakan dengan sedikit penyesuaian"];
    if ($persentase >= 41) return ["Kurang Praktis", "warning", "Perlu perbaikan pada fitur pendukung"];
    return ["Sangat Kurang Praktis", "danger", "Belum memenuhi standar praktisi"];
}
$status = getKategoriPersentase($persentase_total);

/**
 * 2. DATA SARAN & MASUKAN (Ditambahkan ID User untuk Link)
 */
$q_saran = "SELECT u.nama_lengkap, h.id_user, h.saran, h.tanggal_uji 
            FROM hasil_uji_guru h 
            JOIN users u ON h.id_user = u.id 
            WHERE h.kode_aplikasi = '$kode_app' 
            ORDER BY h.tanggal_uji DESC";
$res_saran = mysqli_query($conn, $q_saran);

$instrumen_guru = [
    'Aspek Rekayasa Perangkat Lunak' => [
        ['teks' => 'Aplikasi PADI memiliki antarmuka yang ramah untuk anak SD.', 'key' => 'avg_q1'],
        ['teks' => 'Navigasi antar mata pelajaran sangat mudah dioperasikan.', 'key' => 'avg_q2'],
        ['teks' => 'Aplikasi berjalan dengan stabil pada perangkat IFP maupun mobile.', 'key' => 'avg_q3'],
        ['teks' => 'Keamanan data pengguna (login) sudah terjamin dengan baik.', 'key' => 'avg_q4']
    ],
    'Aspek Komunikasi Visual & Pembelajaran' => [
        ['teks' => 'Materi yang disajikan relevan dengan tujuan pembelajaran kurikulum.', 'key' => 'avg_q5'],
        ['teks' => 'Fitur Asisten AI Kak PADI memberikan jawaban yang edukatif.', 'key' => 'avg_q6'],
        ['teks' => 'Media ini mampu memotivasi siswa untuk belajar mandiri.', 'key' => 'avg_q7'],
        ['teks' => 'Bahasa yang digunakan dalam materi mudah dipahami siswa.', 'key' => 'avg_q8']
    ],
    'Aspek Kemanfaatan (Utility)' => [
        ['teks' => 'Fitur rekap nilai membantu guru dalam evaluasi berkala.', 'key' => 'avg_q9'],
        ['teks' => 'Secara umum, aplikasi PADI dapat diimplementasikan di sekolah.', 'key' => 'avg_q10']
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Kelayakan Guru | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-main { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .stat-box { border-radius: 20px; padding: 30px; color: white; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .progress { height: 12px; border-radius: 10px; background-color: #e9ecef; }
        .skor-badge { background: #764ba2; color: white; padding: 2px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: bold; }
        .btn-view { font-size: 0.75rem; padding: 4px 12px; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h2 class="fw-bold text-dark">Laporan Uji Praktisi (Guru)</h2>
            <p class="text-muted">Hasil perhitungan nilai akhir kepraktisan aplikasi PADI PORTAL.</p>
        </div>
        <div class="col-md-5 text-md-end">
            <button onclick="window.print()" class="btn btn-outline-dark px-4 rounded-pill me-2"><i class="fas fa-print me-2"></i>Cetak</button>
            <a href="dashboard.php" class="btn btn-dark px-4 rounded-pill">Kembali</a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-5">
            <div class="stat-box shadow text-center h-100 d-flex flex-column justify-content-center">
                <h6 class="text-uppercase opacity-75 small mb-3">Persentase Kepraktisan</h6>
                <h1 class="display-2 fw-bold mb-0"><?= number_format($persentase_total, 1) ?>%</h1>
                <div class="mt-3">
                    <span class="badge bg-white text-dark px-3 py-2 rounded-pill fw-bold"><?= $status[0] ?></span>
                </div>
                <p class="mt-3 mb-0 small opacity-75">Data <b><?= $jml_guru ?></b> Responden Guru</p>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card card-main h-100 p-4">
                <h5 class="fw-bold mb-3">Kesimpulan:</h5>
                <p class="text-secondary">Aplikasi dinyatakan <strong class="text-primary"><?= $status[0] ?></strong> dengan nilai akhir <strong><?= number_format($persentase_total, 1) ?>%</strong>.</p>
                <div class="alert alert-<?= $status[1] ?> border-0 mt-3 rounded-4">
                    <h6 class="fw-bold mb-1"><i class="fas fa-info-circle me-2"></i> Catatan:</h6>
                    <p class="mb-0 small"><?= $status[2] ?>.</p>
                </div>
            </div>
        </div>
    </div>

    <h4 class="fw-bold mb-4"><i class="fas fa-chart-pie text-primary me-2"></i> Distribusi Skor Indikator</h4>
    <div class="row mb-5">
        <?php foreach ($instrumen_guru as $aspek => $indikators): ?>
        <div class="col-md-6 mb-4">
            <div class="card card-main p-4 h-100">
                <h5 class="fw-bold text-primary mb-4 border-bottom pb-2"><?= $aspek ?></h5>
                <?php foreach ($indikators as $ind): 
                    $nilai_raw = $res_per_soal[$ind['key']] ?? 0;
                    $persen_ind = ($nilai_raw / 5) * 100;
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small fw-medium text-dark pe-3" style="font-size: 0.85rem;"><?= $ind['teks'] ?></span>
                        <span class="skor-badge"><?= number_format($persen_ind, 1) ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?= $persen_ind ?>%; background: linear-gradient(to right, #667eea, #764ba2);"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-12">
            <h4 class="fw-bold mb-4 text-dark"><i class="fas fa-comments text-primary me-2"></i> Daftar Saran & Masukan Guru</h4>
            <?php if (mysqli_num_rows($res_saran) > 0): ?>
                <div class="row">
                    <?php while ($s = mysqli_fetch_assoc($res_saran)): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card card-main p-4 h-100 border-start border-primary border-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <h6 class="fw-bold text-primary mb-0 me-3"><?= htmlspecialchars($s['nama_lengkap']) ?></h6>
                                        <a href="detail_hasil_guru.php?id_user=<?= $s['id_user'] ?>" class="btn btn-outline-primary btn-view rounded-pill">
                                            <i class="fas fa-search me-1"></i> Lihat Penilaian
                                        </a>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($s['tanggal_uji'])) ?></small>
                                </div>
                                <p class="fst-italic mb-0 text-secondary" style="font-size: 0.9rem;">"<?= htmlspecialchars($s['saran']) ?>"</p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center py-4 rounded-4">Belum ada saran dari Guru.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>