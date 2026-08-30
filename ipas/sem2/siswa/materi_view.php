<?php
// ... (Bagian require tetap sama) ...
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? 0;

$materi_id = $_GET['id'] ?? 0;
$materi = null;
$error_message = "";
$has_quiz = false;
$last_result = null;
$jumlah_percobaan = 0; 

if ($materi_id > 0) {
    // PERBAIKAN: Tambahkan tampilkan_kuis dalam SELECT
    $stmt = $db_mapel->prepare("SELECT id, judul, deskripsi, level_kategori, file_path, konten_materi, tampilkan_kuis FROM materi WHERE id = ?");
    $stmt->bind_param("i", $materi_id);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($materi) {
        $stmt_quiz = $db_mapel->prepare("SELECT COUNT(id) as total FROM soal WHERE materi_id = ?");
        $stmt_quiz->bind_param("i", $materi['id']);
        $stmt_quiz->execute();
        $res_q = $stmt_quiz->get_result()->fetch_assoc();
        $has_quiz = ($res_q['total'] > 0);
        $stmt_quiz->close();

        if ($has_quiz) {
            $stmt_count = $db_mapel->prepare("SELECT COUNT(id) as total_percobaan FROM riwayat_kuis WHERE id_user = ? AND id_materi = ?");
            $stmt_count->bind_param("ii", $user_id, $materi['id']);
            $stmt_count->execute();
            $jumlah_percobaan = $stmt_count->get_result()->fetch_assoc()['total_percobaan'] ?? 0;
            $stmt_count->close();

            $stmt_last = $db_mapel->prepare("SELECT skor, total_soal, status_lulus FROM riwayat_kuis WHERE id_user = ? AND id_materi = ? ORDER BY tanggal_dikerjakan DESC LIMIT 1");
            $stmt_last->bind_param("ii", $user_id, $materi['id']);
            $stmt_last->execute();
            $last_result = $stmt_last->get_result()->fetch_assoc();
            $stmt_last->close();
        }
    } else {
        $error_message = "Materi tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $materi ? htmlspecialchars($materi['judul']) : 'Materi'; ?> | IPAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; font-family: 'Segoe UI', sans-serif; }
        .materi-card { background: white; border-radius: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); border: none; overflow: hidden; }
        .materi-header { background: #198754; color: white; padding: 20px; }
        .materi-frame { width: 100%; height: 85vh; border: none; background: white; }
        .quiz-box { background: #e7f3ff; border-radius: 15px; padding: 25px; border: 2px dashed #0d6efd; }
        .limit-reached { background: #fff5f5; border-color: #dc3545; color: #dc3545; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark shadow-sm" style="background-color: #198754;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-book-reader"></i> IPAS DIGITAL</a>
        <a href="../logout.php" class="btn btn-sm btn-outline-light">Keluar</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <a href="materi.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message; ?></div>
    <?php elseif ($materi): ?>
        <div class="materi-card">
            <div class="materi-header text-center">
                <h2 class="fw-bold mb-1"><?= htmlspecialchars($materi['judul']); ?></h2>
                <p class="mb-0 opacity-75 small"><?= htmlspecialchars($materi['deskripsi']); ?></p>
                <button onclick="toggleFullScreen()" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-expand me-1"></i> Layar Penuh
        </button>
            </div>

            <?php if (!empty($materi['konten_materi'])): ?>
                <iframe id="aiFrame" class="materi-frame"></iframe>
                <script>
                    (function() {
                        function b64DecodeUnicode(str) {
                            return decodeURIComponent(atob(str).split('').map(function(c) {
                                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                            }).join(''));
                        }
                        try {
                            const base64Data = "<?= base64_encode($materi['konten_materi']); ?>";
                            const decodedHTML = b64DecodeUnicode(base64Data);
                            const frame = document.getElementById('aiFrame');
                            const htmlFull = `<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body { margin: 0; padding: 20px; overflow-x: hidden; font-family: sans-serif; } </style></head><body>${decodedHTML}</body></html>`;
                            const blob = new Blob([htmlFull], { type: 'text/html;charset=utf-8' });
                            frame.src = URL.createObjectURL(blob);
                        } catch (e) { console.error(e); }
                    })();
                </script>
            <?php else: ?>
                <iframe src="../materi/<?= htmlspecialchars($materi['file_path']); ?>" class="materi-frame"></iframe>
            <?php endif; ?>

            <div class="p-4 bg-light border-top text-center">
                <?php if ($has_quiz): ?>
                    
                    <?php if ($last_result): ?>
                        <div class="mb-3">
                            <?php 
                                $nilai_akhir = ($last_result['total_soal'] > 0) ? round(($last_result['skor'] / $last_result['total_soal']) * 100) : 0;
                                $badge_color = ($last_result['status_lulus'] == 'LULUS' ? 'success' : 'danger');
                            ?>
                            <span class="badge rounded-pill bg-<?= $badge_color; ?> p-2 px-3">
                                <i class="fas fa-chart-line me-1"></i> Nilai Terakhir: <?= $nilai_akhir; ?> / 100 (<?= $last_result['status_lulus']; ?>)
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($materi['tampilkan_kuis'] == 1): ?>
                        <div class="quiz-box shadow-sm <?= ($jumlah_percobaan >= 5) ? 'limit-reached' : ''; ?>">
                            <h4 class="fw-bold mb-2">Uji Pemahamanmu!</h4>
                            <p class="mb-1">Kesempatan mengerjakan: <b><?= $jumlah_percobaan; ?> / 5</b></p>
                            
                            <?php if ($jumlah_percobaan < 5): ?>
                                <p class="text-muted small mb-4">Kamu masih punya kesempatan untuk memperbaiki nilaimu.</p>
                                <a href="start_quiz.php?materi_id=<?= $materi['id']; ?>" class="btn btn-primary btn-lg px-5 fw-bold rounded-pill shadow">
                                    <i class="fas fa-edit me-2"></i> <?= $last_result ? 'Ulangi Kuis' : 'Mulai Kuis Sekarang'; ?>
                                </a>
                            <?php else: ?>
                                <div class="alert alert-danger d-inline-block rounded-pill px-4 mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Maaf, kesempatan mengerjakan sudah habis.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning border-warning d-inline-block px-5 py-3 rounded-pill shadow-sm">
                            <i class="fas fa-lock me-2"></i> <b>Kuis untuk materi ini sedang dinonaktifkan oleh guru.</b>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="text-muted italic small"><i class="fas fa-info-circle"></i> Kuis untuk materi ini belum tersedia.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFullScreen() {
    // Mencari iframe mana yang sedang aktif
    const frame = document.getElementById('aiFrame') || document.getElementById('fileFrame');
    
    if (!frame) return;

    if (frame.requestFullscreen) {
        frame.requestFullscreen();
    } else if (frame.webkitRequestFullscreen) { /* Safari */
        frame.webkitRequestFullscreen();
    } else if (frame.msRequestFullscreen) { /* IE11 */
        frame.msRequestFullscreen();
    }
}

// Opsional: Keluar fullscreen otomatis jika escape ditekan (sudah bawaan browser)
// Namun jika ingin menambah gaya CSS saat fullscreen:
document.addEventListener('fullscreenchange', () => {
    const frame = document.getElementById('aiFrame') || document.getElementById('fileFrame');
    if (document.fullscreenElement) {
        frame.style.borderRadius = "0"; // Hilangkan lengkungan saat layar penuh
    } else {
        frame.style.borderRadius = "10px"; // Kembalikan lengkungan saat normal
    }
});
</script>
</body>
</html>