<?php
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

if ($materi_id > 0 && $level_kelas > 0) {
    // 1. Ambil Data Materi (Sertakan konten_materi dan tampilkan_kuis)
    $stmt_materi = $db_mapel->prepare("SELECT id, judul, deskripsi, level_kategori, file_path, konten_materi, tampilkan_kuis FROM materi WHERE id = ? AND level_kategori = ?");
    $stmt_materi->bind_param("ii", $materi_id, $level_kelas);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();

    if ($result_materi->num_rows > 0) {
        $materi = $result_materi->fetch_assoc();
    } else {
        $error_message = "Materi tidak ditemukan atau tidak sesuai dengan level kelas Anda.";
    }
    $stmt_materi->close();

    if ($materi) {
        // 2. Cek Apakah Ada Kuis
        $stmt_quiz = $db_mapel->prepare("SELECT COUNT(id) as total_soal FROM soal WHERE materi_id = ?");
        $stmt_quiz->bind_param("i", $materi_id);
        $stmt_quiz->execute();
        $quiz_data = $stmt_quiz->get_result()->fetch_assoc();
        $has_quiz = ($quiz_data['total_soal'] > 0);
        $stmt_quiz->close();

        if ($has_quiz) {
            // 3. HITUNG JUMLAH PERCOBAAN KUIS
            $stmt_count = $db_mapel->prepare("SELECT COUNT(id) as total_percobaan FROM riwayat_kuis WHERE id_user = ? AND id_materi = ?");
            $stmt_count->bind_param("ii", $user_id, $materi_id);
            $stmt_count->execute();
            $jumlah_percobaan = $stmt_count->get_result()->fetch_assoc()['total_percobaan'] ?? 0;
            $stmt_count->close();

            // 4. Ambil Nilai Terakhir
            $query_last = "SELECT skor, total_soal, persentase, status_lulus, tanggal_dikerjakan FROM riwayat_kuis WHERE id_user = ? AND id_materi = ? ORDER BY tanggal_dikerjakan DESC LIMIT 1";
            if ($stmt_last = $db_mapel->prepare($query_last)) {
                $stmt_last->bind_param("ii", $user_id, $materi_id);
                $stmt_last->execute();
                $last_result = $stmt_last->get_result()->fetch_assoc();
                $stmt_last->close();
            }
        }
    }
} else {
    $error_message = "ID Materi tidak valid atau Level Kelas belum terdeteksi.";
}

if ($materi) {
    $stmt_log = $db_mapel->prepare("INSERT INTO log_aktivitas (user_id, materi_id, status) VALUES (?, ?, 'view')");
    $stmt_log->bind_param("ii", $user_id, $materi_id);
    $stmt_log->execute();
    $stmt_log->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $materi ? htmlspecialchars($materi['judul']) : 'Materi'; ?> | MULOK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .materi-container { background: white; padding: 15px; border-radius: 12px; box-shadow: 0px 4px 15px rgba(0,0,0,0.05); }
        iframe.materi-frame { width: 100%; height: 85vh; border: 1px solid #eee; border-radius: 10px; background: white; }
        .result-card { border: 1px solid #dee2e6; border-left: 5px solid #4b4b4b; padding: 15px; border-radius: 8px; background-color: #f8f9fa; }
        .limit-reached { background-color: #fff5f5; border: 2px dashed #dc3545 !important; }
        @media (max-width: 576px) { iframe.materi-frame { height: 80vh; } .materi-container { padding: 10px; } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color:#4b4b4b;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">B.Komering</a>
        <div class="navbar-text text-white d-none d-md-block">
            Halo, <b class="text-warning"><?php echo htmlspecialchars($nama_pengguna); ?></b>
        </div>
        <a href="../logout.php" class="btn btn-sm btn-light fw-bold" style="color:#4b4b4b;">Keluar</a>
    </div>
</nav>

<div class="container mt-3 mb-5">
    <a href="materi.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>

    <?php if ($error_message): ?>
        <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($materi): ?>
        <div class="materi-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 style="color: #4b4b4b;">
            <?= htmlspecialchars($materi['judul']); ?>
        </h4>
        <p class="text-muted small mb-0">
            <?= htmlspecialchars($materi['deskripsi']); ?>
        </p>
    </div>
    <div>
        <button onclick="toggleFullScreen()" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-expand me-1"></i> Layar Penuh
        </button>
    </div>
</div>
            <?php if (!empty($materi['konten_materi'])): ?>
    <iframe id="aiFrame" class="materi-frame"></iframe>
<script>
(function() {
    function b64ToUtf8(str) {
        return decodeURIComponent(atob(str).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
    }

    try {
        const b64 = "<?= base64_encode($materi['konten_materi']); ?>";
        let html = b64ToUtf8(b64);

        // --- LOGIKA PERBAIKAN GAMBAR OTOMATIS ---
        // Mencari alamat dasar folder sem2
        const currentPath = window.location.origin + window.location.pathname;
        const basePath = currentPath.substring(0, currentPath.lastIndexOf('/siswa/')) + '/';

        // Paksa semua alamat "../aset/" menjadi alamat lengkap (URL Absolut)
        // Ini yang membuat gambar muncul di HP siswa meskipun foldernya dalam
        html = html.replace(/\.\.\/aset\//g, basePath + 'aset/');
        // ----------------------------------------

        const frame = document.getElementById('aiFrame');
        const fullHTML = `
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { margin:0; padding:0; overflow-x:hidden; }
                    /* Tambahan agar materi tidak terpotong di HP */
                    .container { height: 100vh !important; border-radius: 0 !important; border:none !important; }
                </style>
            </head>
            <body>${html}</body>
            </html>`;
        
        frame.srcdoc = fullHTML;
    } catch (e) { 
        console.error("Gagal Memuat Materi:", e); 
    }
})();
</script>
<?php else: ?>
                <iframe src="../materi/<?php echo htmlspecialchars($materi['file_path']); ?>" class="materi-frame"></iframe>
            <?php endif; ?>

            <hr class="my-4">

            <?php if ($has_quiz): ?>
                
                <?php if ($last_result): ?>
                    <div class="result-card mb-4" style="border-left-color: <?= ($last_result['status_lulus'] == 'LULUS' ? '#198754' : '#dc3545'); ?>;">
                        <h6 class="fw-bold mb-1">Hasil Kuis Terakhir:</h6>
                        <p class="mb-1 small">
                            Skor: <b><?= htmlspecialchars($last_result['skor']); ?>/<?= htmlspecialchars($last_result['total_soal']); ?></b> 
                            (<?= round($last_result['persentase']); ?>%) - 
                            <span class="badge bg-<?= ($last_result['status_lulus'] == 'LULUS' ? 'success' : 'danger'); ?>">
                                <?= htmlspecialchars($last_result['status_lulus']); ?>
                            </span>
                        </p>
                        <small class="text-muted italic"><?= date('d M Y', strtotime($last_result['tanggal_dikerjakan'])); ?></small>
                    </div>
                <?php endif; ?>

                <?php if ($materi['tampilkan_kuis'] == 1): ?>
                    <div class="alert alert-info text-center py-4 <?= ($jumlah_percobaan >= 5) ? 'limit-reached' : ''; ?>">
                        <h6 class="fw-bold">Sudah paham materinya?</h6>
                        <p class="mb-2">Kesempatan mengerjakan: <b><?= $jumlah_percobaan; ?> / 5</b></p>
                        
                        <?php if ($jumlah_percobaan < 5): ?>
                            <p class="small">Ayo uji kemampuanmu dengan kuis!</p>
                            <a href="start_quiz.php?materi_id=<?php echo $materi['id']; ?>" class="btn btn-primary px-5 fw-bold shadow" style="background-color: #4b4b4b; border-color: #4b4b4b;">
                                <i class="fas fa-edit me-2"></i>
                                <?php echo $last_result ? 'Ulangi Kuis' : 'Mulai Kuis Sekarang'; ?>
                            </a>
                        <?php else: ?>
                            <div class="alert alert-danger d-inline-block rounded-pill px-4 mt-2">
                                <i class="fas fa-exclamation-triangle me-2"></i> Maaf, kesempatan mengerjakan sudah habis.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center border-warning py-3 rounded-pill shadow-sm">
                        <i class="fas fa-lock me-2"></i> <b>Kuis untuk materi ini sedang dinonaktifkan oleh guru.</b>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-light text-center border">Kuis belum tersedia untuk materi ini.</div>
            <?php endif; ?>
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