<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan user adalah siswa
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$materi_id = $_GET['materi_id'] ?? null;
$waktu_pengerjaan = $_GET['waktu'] ?? null;

if (!$materi_id || !$waktu_pengerjaan) {
    header("Location: riwayat_progress.php");
    exit;
}

// 1. Ambil Judul Materi dari panca_materi
$judul_materi = 'Materi Tidak Ditemukan';
$query_materi = "SELECT judul FROM panca_materi WHERE id = ?";
if ($stmt_materi = $db_mapel->prepare($query_materi)) {
    $stmt_materi->bind_param("i", $materi_id);
    $stmt_materi->execute();
    $result = $stmt_materi->get_result();
    if ($row = $result->fetch_assoc()) {
        $judul_materi = $row['judul'];
    }
    $stmt_materi->close();
}

// 2. Ambil data review (panca_soal & panca_hasil_quiz)
$review_list = [];
$query_review = "
    SELECT 
        s.pertanyaan, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, 
        s.pembahasan, hq.jawaban_siswa
    FROM panca_soal s
    JOIN panca_hasil_quiz hq ON s.id = hq.soal_id
    WHERE s.materi_id = ? AND hq.user_id = ? AND hq.waktu_jawab = ?
    ORDER BY s.id ASC
";

if ($stmt_review = $db_mapel->prepare($query_review)) {
    $stmt_review->bind_param("iis", $materi_id, $user_id, $waktu_pengerjaan);
    $stmt_review->execute();
    $review_list = $stmt_review->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_review->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Kuis <?php echo htmlspecialchars($judul_materi); ?> | PANCASILA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #fff5f5; font-family: 'Poppins', sans-serif; }
        .header-red { background-color: #dc3545; color: white; padding: 30px 0; }
        .jawaban-netral { border-left: 5px solid #dee2e6; background-color: #ffffff; transition: 0.3s; }
        .is-selected { border-left: 5px solid #6c757d; background-color: #f8f9fa; font-weight: 600; }
        .box-pembahasan { border-top: 2px dashed #dc3545; background-color: #fff9f9; border-radius: 0 0 10px 10px; }
    </style>
</head>
<body>

<div class="header-red text-center shadow-sm">
    <div class="container">
        <h1 class="display-6 fw-bold"><i class="fas fa-microscope"></i> Review Mandiri</h1>
        <p class="mb-0">Materi: <?php echo htmlspecialchars($judul_materi); ?></p>
    </div>
</div>

<div class="container mt-4 mb-5">
    <a href="riwayat_progress.php" class="btn btn-sm btn-outline-danger mb-4"><i class="fas fa-arrow-left"></i> Kembali</a>

    <div class="card shadow-sm p-4 border-0 rounded-4">
        <?php if (empty($review_list)): ?>
            <div class="alert alert-warning text-center">Data tidak ditemukan.</div>
        <?php else: ?>
            <?php $no = 1; foreach ($review_list as $review): ?>
                <div class="mb-5 p-3 border-bottom">
                    <h5 class="text-danger mb-3">Soal #<?php echo $no++; ?></h5>
                    <div class="fw-bold mb-4"><?php echo $review['pertanyaan']; ?></div>

                    <ul class="list-unstyled">
                        <?php 
                        $opsi_keys = ['A' => 'opsi_a', 'B' => 'opsi_b', 'C' => 'opsi_c', 'D' => 'opsi_d'];
                        foreach ($opsi_keys as $label => $key): 
                            $is_siswa_choice = ($review['jawaban_siswa'] === $label);
                        ?>
                            <li class="mb-2 p-3 rounded jawaban-netral <?php echo $is_siswa_choice ? 'is-selected' : ''; ?>">
                                <strong><?php echo $label; ?>.</strong> <?php echo $review[$key]; ?>
                                <?php if($is_siswa_choice): ?> 
                                    <span class="badge bg-dark ms-2"><i class="fas fa-user-check"></i> Pilihan Anda</span> 
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($review['pembahasan'])): ?>
                        <div class="mt-3 p-3 box-pembahasan">
                            <h6 class="text-danger fw-bold"><i class="fas fa-chalkboard-teacher"></i> Penjelasan:</h6>
                            <div class="text-dark small">
                                <?php echo $review['pembahasan']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>