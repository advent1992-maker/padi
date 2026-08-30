<?php
// FILE: siswa/materi_view.php
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
$materi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$materi = null;
$error_message = "";
$has_quiz = false;
$status_praktek = null;
$jumlah_percobaan = 0; 
$last_result = null;

if ($materi_id > 0) {
    // 1. Ambil Data Materi
    $stmt_materi = $db_mapel->prepare("SELECT * FROM materi WHERE id = ?");
    $stmt_materi->bind_param("i", $materi_id);
    $stmt_materi->execute();
    $materi = $stmt_materi->get_result()->fetch_assoc();
    $stmt_materi->close();

    if ($materi) {
        // 2. Logika Kuis
        $stmt_quiz = $db_mapel->prepare("SELECT COUNT(id) as total_soal FROM soal WHERE materi_id = ?");
        $stmt_quiz->bind_param("i", $materi_id);
        $stmt_quiz->execute();
        $has_quiz = ($stmt_quiz->get_result()->fetch_assoc()['total_soal'] > 0);
        $stmt_quiz->close();

        if ($has_quiz) {
            $stmt_count = $db_mapel->prepare("SELECT COUNT(id) as total FROM riwayat_kuis WHERE id_user = ? AND id_materi = ?");
            $stmt_count->bind_param("ii", $user_id, $materi_id);
            $stmt_count->execute();
            $jumlah_percobaan = $stmt_count->get_result()->fetch_assoc()['total'];
            $stmt_count->close();

            $stmt_last = $db_mapel->prepare("SELECT * FROM riwayat_kuis WHERE id_user = ? AND id_materi = ? ORDER BY tanggal_dikerjakan DESC LIMIT 1");
            $stmt_last->bind_param("ii", $user_id, $materi_id);
            $stmt_last->execute();
            $last_result = $stmt_last->get_result()->fetch_assoc();
            $stmt_last->close();
        }

        // 3. Logika Praktek
        if (($materi['pakai_praktek'] ?? 0) == 1) {
            $stmt_praktek = $db_mapel->prepare("SELECT * FROM praktek_siswa WHERE id_siswa = ? AND materi_id = ?");
            $stmt_praktek->bind_param("ii", $user_id, $materi_id);
            $stmt_praktek->execute();
            $status_praktek = $stmt_praktek->get_result()->fetch_assoc();
            $stmt_praktek->close();
        }
    }
}
// --- LOGIKA HAPUS TUGAS PRAKTEK ---
if (isset($_POST['hapus_praktek'])) {
    $id_praktek = (int)$_POST['id_praktek'];
    
    $stmt_cek = $db_mapel->prepare("SELECT foto_karya FROM praktek_siswa WHERE id = ? AND id_siswa = ? AND status_dinilai = 0");
    $stmt_cek->bind_param("ii", $id_praktek, $user_id);
    $stmt_cek->execute();
    $data_p = $stmt_cek->get_result()->fetch_assoc();

    if ($data_p) {
        $file_path = "../uploads/" . $data_p['foto_karya'];
        if (file_exists($file_path)) { unlink($file_path); }

        $stmt_del = $db_mapel->prepare("DELETE FROM praktek_siswa WHERE id = ?");
        $stmt_del->bind_param("i", $id_praktek);
        
        if ($stmt_del->execute()) {
            // Set session untuk trigger SweetAlert
            $_SESSION['alert_success'] = "Karya berhasil dihapus. Silakan unggah ulang!";
            header("Location: materi_view.php?id=$materi_id");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $materi ? htmlspecialchars($materi['judul']) : 'Materi'; ?> | SENI </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: #6f42c1; color: white; }
        .materi-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .materi-frame { width: 100%; height: 80vh; border: none; background: #fff; }
        .btn-purple { background: #6f42c1; color: white; }
        .btn-purple:hover { background: #5a32a3; color: white; }
        .section-title { border-left: 4px solid #6f42c1; padding-left: 15px; margin-bottom: 20px; font-weight: 700; color: #333; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-graduation-cap me-2"></i> SENI</a>
        <div class="ms-auto text-white d-none d-md-block">
            Halo, <span class="fw-bold text-warning"><?= htmlspecialchars($nama_pengguna); ?></span>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <a href="materi.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

    <?php if (!$materi): ?>
        <div class="alert alert-danger shadow-sm"><?= $error_message ?: 'Materi tidak ditemukan.'; ?></div>
    <?php else: ?>
        <div class="materi-card p-4">
            <div class="mb-4">
                <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($materi['judul']); ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($materi['deskripsi']); ?></p>
                <button onclick="toggleFullScreen()" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm mt-2">
    <i class="fas fa-expand me-1"></i> Layar Penuh
</button>
            </div>

            <div class="mb-4" style="min-height: 50vh;">
    <?php 
    $konten = $materi['konten_materi'];
    
// --- LOGIKA SMART DETECTOR VIDEO & PLAYLIST YOUTUBE (VERSI NO-COOKIE) ---
$is_youtube = false;
$embed_url = "";

// 1. Cek apakah ini Link Playlist
if (preg_match('/list=([a-zA-Z0-9_-]+)/i', $konten, $match_list)) {
    $playlist_id = $match_list[1];
    // Menggunakan youtube-nocookie agar lolos blokir kuki di HP
    $embed_url = "https://www.youtube-nocookie.com/embed/videoseries?list=" . $playlist_id;
    $is_youtube = true;
} 
// 2. Cek apakah ini Link Video Tunggal
elseif (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $konten, $match_video)) {
    $video_id = $match_video[1];
    // Menggunakan youtube-nocookie
    $embed_url = "https://www.youtube-nocookie.com/embed/" . $video_id;
    $is_youtube = true;
}

    // 1. TAMPILAN VIDEO YOUTUBE
    if ($is_youtube): ?>
        <div class="ratio ratio-16x9 shadow-sm rounded overflow-hidden border">
            <iframe src="<?= $embed_url; ?>" id="videoFrame" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        </div>

    <!--// 2. TAMPILAN GAMBAR (JPG, PNG, WEBP, DLL)-->
    <?php elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $konten)): ?>
        <div class="text-center bg-white p-2 rounded shadow-sm border">
            <img src="../uploads/materi/<?= htmlspecialchars($konten); ?>" id="imageFrame" class="img-fluid rounded" style="max-height: 80vh; width: auto;" alt="Materi Seni">
            <div class="mt-3 pb-2">
                <a href="../uploads/materi/<?= htmlspecialchars($konten); ?>" target="_blank" class="btn btn-sm btn-outline-purple rounded-pill">
                    <i class="fas fa-search-plus me-1"></i> Perbesar Gambar
                </a>
            </div>
        </div>

    <!--// 3. TAMPILAN HTML / RAKIT AI (DEFAULT)-->
    <?php else: ?>
        <div class="ratio ratio-16x9" style="height: 75vh;">
            <iframe id="aiFrame" class="materi-frame border rounded shadow-sm"></iframe>
        </div>
        <script>
            // KODE PERBAIKAN
(function() {
    try {
        // Menggunakan json_encode agar semua karakter kutip dan enter aman
        const rawData = <?= json_encode($materi['konten_materi']); ?>; 
        const frame = document.getElementById('aiFrame');
        if (!rawData || !frame) return;

        const fullHTML = `
            <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { padding: 20px; font-family: 'Inter', sans-serif; background: #fff; color: #333; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>${rawData}</body>
            </html>`;
        
        const blob = new Blob([fullHTML], { type: 'text/html' });
        frame.src = URL.createObjectURL(blob);
    } catch (e) { 
        console.error("Gagal memuat materi HTML:", e); 
    }
})();
        </script>
    <?php endif; ?>
</div>

            <hr class="my-5">

            <div class="row g-4">
                <?php if ($has_quiz): ?>
                <div class="col-md-6">
                    <h5 class="section-title">Evaluasi Kuis</h5>
                    <div class="card border-0 bg-light p-4 text-center rounded-4">
                        <?php if ($last_result): ?>
                            <div class="mb-3">
                                <span class="text-muted d-block small">Skor Terakhir</span>
                                <h3 class="fw-bold <?= $last_result['status_lulus'] == 'LULUS' ? 'text-success' : 'text-danger' ?>">
                                    <?= round($last_result['persentase']); ?>%
                                </h3>
                            </div>
                        <?php endif; ?>
                        
                        <p class="small text-muted mb-4">Kesempatan: <?= $jumlah_percobaan; ?> / 5</p>

                        <?php if ($jumlah_percobaan < 5 && ($materi['tampilkan_kuis'] ?? 0) == 1): ?>
                            <a href="start_quiz.php?materi_id=<?= $materi_id ?>" class="btn btn-purple rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fas fa-pen-nib me-2"></i> <?= $last_result ? 'Ulangi Kuis' : 'Kerjakan Kuis' ?>
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 rounded-pill small">
                                <i class="fas fa-lock me-2"></i> Kuis tidak dapat diakses
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (($materi['pakai_praktek'] ?? 0) == 1): ?>
                <div class="col-md-6">
                    <h5 class="section-title">Tugas Praktek</h5>
                    <div class="card border-0 bg-white shadow-sm p-4 text-center rounded-4 border-top border-warning border-4">
                        <?php if ($status_praktek): ?>
    <div class="alert alert-success border-0 mb-3">
        <i class="fas fa-check-circle me-2"></i> Karya Berhasil Dikumpulkan
    </div>
    
    <?php if ($status_praktek['status_dinilai']): ?>
        <span class="text-muted d-block small">Nilai Guru:</span>
        <h2 class="text-primary fw-bold"><?= $status_praktek['nilai_angka'] ?></h2>
        <p class="text-muted small italic">"<?= htmlspecialchars($status_praktek['catatan_guru']) ?>"</p>
    <?php else: ?>
        <div class="d-grid gap-2">
            <span class="badge bg-secondary py-2 px-3 rounded-pill">Menunggu Koreksi Guru</span>
            
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dan mengunggah ulang karya ini?');">
                <input type="hidden" name="id_praktek" value="<?= $status_praktek['id'] ?>">
                <button type="submit" name="hapus_praktek" class="btn btn-sm btn-outline-danger border-0 mt-2">
                    <i class="fas fa-trash-alt me-1"></i> Hapus & Unggah Ulang
                </button>
            </form>
        </div>
    <?php endif; ?>
<?php else: ?>
    <p class="text-muted mb-4">Silakan unggah foto atau file hasil karya praktek Anda di sini.</p>
    <a href="praktek_upload.php?materi_id=<?= $materi_id ?>" class="btn btn-warning rounded-pill px-5 fw-bold shadow-sm text-dark">
        <i class="fas fa-upload me-2"></i> Unggah Karya
    </a>
<?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 1. Notifikasi Sukses setelah Refresh
<?php if (isset($_SESSION['alert_success'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '<?= $_SESSION['alert_success'] ?>',
        confirmButtonColor: '#6f42c1', // Warna ungu tema Anda
        timer: 3000
    });
<?php unset($_SESSION['alert_success']); endif; ?>

// 2. Konfirmasi Hapus yang Cantik
function konfirmasiHapus(form) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Karya yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}
function toggleFullScreen() {
    // Mencari elemen mana yang ada di halaman saat ini
    const frame = document.getElementById('aiFrame') || 
                  document.getElementById('videoFrame') || 
                  document.getElementById('imageFrame');
    
    if (!frame) return;

    if (frame.requestFullscreen) {
        frame.requestFullscreen();
    } else if (frame.webkitRequestFullscreen) { /* Safari */
        frame.webkitRequestFullscreen();
    } else if (frame.msRequestFullscreen) { /* IE11 */
        frame.msRequestFullscreen();
    }
}
</script>
</body>
</html>