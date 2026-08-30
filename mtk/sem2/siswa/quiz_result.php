<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role
$db_mapel = null; // Tidak perlu koneksi database, cukup data sesi

// Tambahkan logic ini di bagian awal file quiz_result.php
$id_materi = $_GET['materi_id'] ?? null;

if ($id_materi === null) {
    // Jika tidak ada di URL, coba ambil dari sesi (jika Anda menyimpannya)
    $id_materi = $_SESSION['current_materi_id'] ?? null;
}
// Hanya Siswa yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'siswa' || !isset($_SESSION['quiz_result'])) {
    header("Location: dashboard.php");
    exit();
}

$result_data = $_SESSION['quiz_result'];
$id_materi = $result_data['id_materi'] ?? null;
unset($_SESSION['quiz_result']); // Bersihkan sesi setelah ditampilkan
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis | Mathfiction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <div class="card p-5 shadow-lg border-0" style="border-top: 5px solid <?php echo ($result_data['persentase'] >= 80 ? '#198754' : '#dc3545'); ?>;">
        <div class="text-center">
            <?php echo $result_data['pesan']; ?>

            <h1 class="display-3 fw-bold mt-4 mb-3" style="color: <?php echo ($result_data['persentase'] >= 80 ? '#198754' : '#dc3545'); ?>;">
                <?php echo round($result_data['persentase']); ?>
            </h1>

            <p class="fs-5">jumlah soal yang benar: <?php echo $result_data['skor']; ?> dari <?php echo $result_data['total']; ?>** soal.</p>
            <hr>

            <table class="table table-bordered table-striped text-start mt-4">
                <tr>
                    <td class="fw-bold">Level Cerita</td>
                    <td><?php echo htmlspecialchars($result_data['level']); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold">Bab Cerita</td>
                    <td><?php echo htmlspecialchars($result_data['materi']); ?></td>
                </tr>
            </table>

            <div class="mt-4">
                <a href="materi.php" class="btn btn-primary btn-lg me-2">Lanjut ke Bab Lain</a>
                <?php if ($result_data['persentase'] < 80): ?>
                   <a href="materi_view.php?id=<?= $id_materi ?? ''; ?>" class="btn btn-warning btn-lg">
    Ulangi Materi
</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>