<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Ambil riwayat_id dari URL (yang sebelumnya kita perbaiki di submit_tryout.php)
$riwayat_id = $_GET['session_id'] ?? null;
$siswa_id = $_SESSION['user_id'];

if (!$riwayat_id) {
    header("Location: dashboard.php");
    exit();
}

// 1. Ambil data nilai dari tabel riwayat_tryout
$query_riwayat = "
    SELECT r.*, t.judul 
    FROM riwayat_tryout r
    JOIN tryout_master t ON r.tryout_id = t.id
    WHERE r.id = ? AND r.id_user = ?
";
$stmt = $db_mapel->prepare($query_riwayat);
$stmt->bind_param("ii", $riwayat_id, $siswa_id);
$stmt->execute();
$result_data = $stmt->get_result()->fetch_assoc();

if (!$result_data) {
    die("Data riwayat tidak ditemukan di database untuk user ini.");
}

// Menentukan warna dan pesan berdasarkan kelulusan
$is_lulus = ($result_data['status_lulus'] === 'LULUS');
$warna_tema = $is_lulus ? '#198754' : '#dc3545';
$pesan = $is_lulus ? "Selamat! Kamu Lulus." : "Tetap Semangat! Coba Lagi Ya.";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Try Out | Mathfiction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-result {
            border-top: 5px solid <?php echo $warna_tema; ?>;
            border-radius: 15px;
        }
        .score-display {
            color: <?php echo $warna_tema; ?>;
            font-size: 5rem;
            font-weight: 800;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-5 shadow-lg border-0 card-result">
                <div class="text-center">
                    <h2 class="fw-bold"><?php echo $pesan; ?></h2>

                    <div class="score-display mt-4 mb-3">
                        <?php echo round($result_data['skor']); ?>
                    </div>

                    <p class="fs-5 text-muted">
                        Kamu berhasil menjawab benar dengan persentase 
                        <strong><?php echo $result_data['persentase']; ?>%</strong>
                    </p>
                    
                    <hr class="my-4">

                    <table class="table table-borderless text-start">
                        <tr>
                            <td class="fw-bold text-muted" width="40%">Judul Try Out</td>
                            <td>: <?php echo htmlspecialchars($result_data['judul']); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Status Kelulusan</td>
                            <td>: <span class="badge bg-<?php echo $is_lulus ? 'success' : 'danger'; ?>">
                                <?php echo $result_data['status_lulus']; ?>
                            </span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Tanggal Pengerjaan</td>
                            <td>: <?php echo date('d M Y, H:i', strtotime($result_data['tanggal_dikerjakan'])); ?></td>
                        </tr>
                    </table>

                    <div class="mt-5">
                        <a href="daftar_tryout.php" class="btn btn-outline-secondary btn-lg me-2">Kembali</a>
                        <a href="riwayat_progress.php" class="btn btn-primary btn-lg">Lihat Riwayat Semua Ujian</a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p>Ingin melihat detail jawaban per soal? 
                    <a href="pembahasan_tryout.php?id=<?php echo $riwayat_id; ?>" class="text-decoration-none">Klik di sini untuk Pembahasan</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>