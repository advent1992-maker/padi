<?php
// FILE: siswa/daftar_tryout.php - Menampilkan daftar try out, riwayat singkat, dan sisa kesempatan

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$kelas_siswa = $_SESSION['kelas'] ?? 5;
$user_id = $_SESSION['user_id'];
// --- KRUSIAL: Ambil ID Guru Pembimbing dari Sesi ---
$id_guru_siswa = $_SESSION['id_guru'] ?? 0;
// --- Akhir KRUSIAL ---
$MAX_KESEMPATAN = 2;

$nama_guru_pembimbing="N/A";
if ($id_guru_siswa > 0) {
    $query_guru = "SELECT nama_lengkap FROM users WHERE id = ? AND role = 'guru'";
    $stmt_guru = $conn->prepare($query_guru);
    $stmt_guru->bind_param("i", $id_guru_siswa);
    $stmt_guru->execute();
    $result_guru = $stmt_guru->get_result();

    if ($row_guru = $result_guru->fetch_assoc()) {
        $nama_guru_pembimbing = $row_guru['nama_lengkap'];
    }
    $stmt_guru->close();
}

// Query untuk mengambil daftar Try Out, termasuk riwayat singkat, DENGAN FILTER GURU
// SEKARANG: Ambil RATA-RATA skor dan persentase dari semua pengerjaan
$query = "
    SELECT 
        tm.id, 
        tm.judul, 
        tm.jenis_ujian, 
        tm.waktu_alokasi,
        (SELECT COUNT(rt.id) FROM riwayat_tryout rt WHERE rt.tryout_id = tm.id AND rt.id_user = ?) AS riwayat_count,
        (SELECT AVG(rt.skor) FROM riwayat_tryout rt WHERE rt.tryout_id = tm.id AND rt.id_user = ?) AS avg_skor,
        (SELECT AVG(rt.persentase) FROM riwayat_tryout rt WHERE rt.tryout_id = tm.id AND rt.id_user = ?) AS avg_persentase
    FROM 
        tryout_master tm
    WHERE 
        tm.kelas = ? 
        AND tm.id_guru = ?
        AND tm.tampilkan = 1
    ORDER BY 
        tm.id DESC
";

$stmt = $db_mapel->prepare($query);
// Perubahan di sini: menambah satu 'i' untuk id_guru_siswa, dan memasukkan $id_guru_siswa
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $kelas_siswa, $id_guru_siswa);
$stmt->execute();
$result = $stmt->get_result();
$tryout_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db_mapel->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Try Out | PJOK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; }
        .header-blue { background-color: #fd7e14; color: white; padding: 30px 0; }
        .card-tryout { transition: transform 0.2s; }
        .card-tryout:hover { transform: translateY(-3px); }
        .status-badge { font-size: 0.9rem; padding: 0.4rem 0.8rem; }
        .text-kuota { font-weight: bold; font-size: 0.85rem; }
    .score-badge-box {
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    margin: 10px 0;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
}

.score-label {
    font-size: 0.65rem;
    font-weight: 800;
    color: #6c757d;
    letter-spacing: 1px;
    margin-bottom: 2px;
}

.score-number {
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1;
    color: #e65100; /* Warna oranye khas B.Indo */
}
    </style>
</head>
<body>
<div class="header-blue text-center shadow-sm">
    <div class="container">
        <h1 class="display-5 fw-bold"><i class="fas fa-list-ul"></i> Daftar Try Out (Kelas <?php echo htmlspecialchars($kelas_siswa); ?>)</h1>
        <p class="lead">Pilih ujian yang ingin Anda kerjakan. Ujian ini disiapkan oleh Guru <?php echo htmlspecialchars($nama_guru_pembimbing) ?> memiliki maksimal <?php echo $MAX_KESEMPATAN; ?> kesempatan pengerjaan per Try Out.</p>
    </div>
</div>

<div class="container mt-5 mb-5">
    <a href="dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>

    <div class="row">
        <?php if (empty($tryout_list)): ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle"></i> Belum ada Try Out yang disiapkan oleh Guru <?php echo htmlspecialchars($nama_guru_pembimbing) ?> untuk Kelas <?php echo htmlspecialchars($kelas_siswa); ?>.
            </div>
        <?php else: ?>
            <?php foreach ($tryout_list as $tryout): ?>
            <?php
                // Tentukan warna header card berdasarkan jenis ujian
                $header_class = 'primary';
                if ($tryout['jenis_ujian'] == 'US') {
                    $header_class = 'danger';
                } elseif ($tryout['jenis_ujian'] == 'UTS') {
                    $header_class = 'warning';
                }

                $riwayat_count = (int)$tryout['riwayat_count'];
                $sisa_kesempatan = $MAX_KESEMPATAN - $riwayat_count;
                $is_done = $riwayat_count > 0;
                $can_start = $sisa_kesempatan > 0;

                $skor = round($tryout['avg_skor'] ?? 0);
$persentase = round($tryout['avg_persentase'] ?? 0);
                // Logika penentuan Status Kelulusan (KKM 70)
                $status_text = '';
                $status_badge_class = '';

                if ($is_done) {
                    // KKM diasumsikan 70
                    if ($persentase >= 70) {
                        $status_text = 'LULUS';
                        $status_badge_class = 'bg-success';
                    } else {
                        $status_text = 'GAGAL';
                        $status_badge_class = 'bg-danger';
                    }
                }

                // Tentukan teks, ikon, kelas, dan link tombol berdasarkan kuota
                if (!$can_start) {
                    // Kesempatan habis
                    $button_text = 'Kesempatan Habis';
                    $button_class = 'btn-secondary disabled';
                    $button_icon = 'fas fa-times-circle';
                    $button_link = '#';
                    $info_kuota = '<span class="text-danger"><i class="fas fa-ban"></i> Kuota pengerjaan habis</span>';
                } else if ($is_done) {
                    // Sudah pernah mengerjakan, tapi masih ada sisa
                    $button_text = 'Mulai Ujian Lagi';
                    $button_class = 'btn-warning';
                    $button_icon = 'fas fa-redo';
                    $button_link = 'tryout.php?tryout_id=' . $tryout['id'];
                    $info_kuota = '<span class="text-warning">Sisa **' . $sisa_kesempatan . 'x** kesempatan</span>';
                } else {
                    // Belum pernah mengerjakan (sisa = MAX_KESEMPATAN)
                    $button_text = 'Mulai Ujian';
                    $button_class = 'btn-primary';
                    $button_icon = 'fas fa-play';
                    $button_link = 'tryout.php?tryout_id=' . $tryout['id'];
                    $info_kuota = '<span class="text-success">Sisa **' . $sisa_kesempatan . 'x** kesempatan</span>';
                }

                $riwayat_link = 'riwayat_progress.php';
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card card-tryout shadow h-100 border-<?php echo $header_class; ?>">
                    <div class="card-header bg-<?php echo $header_class; ?> text-white fw-bold">
                        <?php echo htmlspecialchars(strtoupper($tryout['jenis_ujian'])); ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($tryout['judul']); ?></h5>
                        <p class="card-text small text-muted">Waktu: <?php echo htmlspecialchars($tryout['waktu_alokasi']); ?> Menit</p>

                        <?php if ($is_done): ?>
                            <div class="p-2 mt-2 mb-3 border rounded border-success bg-light">
                                <div class="score-badge-box">
    <div class="score-label">NILAI : </div>
    <div class="score-number"><?php echo htmlspecialchars($skor); ?></div>
</div>
                                <hr class="my-1">
                                <p class="mb-0 small">
                                    <i class="fas fa-info-circle me-1"></i> Status:
                                    <span class="badge status-badge rounded-pill <?php echo $status_badge_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </p>
                                <hr class="my-1">
                                <a href="<?php echo $riwayat_link; ?>" class="btn btn-sm btn-info w-100 mt-2">
                                    <i class="fas fa-history"></i> Lihat Riwayat
                                </a>
                                <a href="belajar_tryout.php?tryout_id=<?= $tryout['id'] ?>" class="btn btn-sm btn-success w-100 mt-2">
    <i class="fas fa-book-open me-1"></i> Mode Belajar
</a>
                            </div>
                        <?php endif; ?>

                        <p class="text-kuota text-center mt-3 mb-2">
                            <?php echo $info_kuota; ?>
                        </p>

                        <a href="<?php echo $button_link; ?>" class="btn <?php echo $button_class; ?> w-100 mt-2 <?php echo $can_start ? '' : 'disabled'; ?>">
                            <i class="<?php echo $button_icon; ?>"></i> <?php echo $button_text; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>