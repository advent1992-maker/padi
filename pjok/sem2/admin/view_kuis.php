<?php
// Pastikan file konfigurasi sudah benar
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- KONFIGURASI BASE URL GAMBAR & FUNGSI BANTUAN ---
// BASE URL DARI PERSPEKTIF FILE INI (admin/view_kuis.php) KE FOLDER 'aset/'
$BASE_IMAGE_URL = "../aset/";

/**
 * Fungsi pembantu untuk menghasilkan URL gambar.
 */
function generateImageUrl($url_fragment, $base_url) {
    if (empty($url_fragment)) {
        return '';
    }
    // Jika input sudah merupakan URL lengkap, kembalikan langsung
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) {
        return $url_fragment;
    }
    // Jika input mengandung 'aset/' di awal (kasus aset/pp1.png), hapus 'aset/'
    if (strpos(strtolower($url_fragment), 'aset/') === 0) {
        $url_fragment = substr($url_fragment, 5);
    }
    // Gabungkan dengan base URL
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}
// --- AKHIR KONFIGURASI BASE URL GAMBAR & FUNGSI BANTUAN ---

// 1. Ambil ID Kuis (id) dan ID Guru (user_id) dari URL
$kuis_id = $_GET['id'] ?? null;
$guru_id = $_GET['user_id'] ?? null; // Digunakan untuk link kembali

// Validasi ID Kuis
if (!$kuis_id || !is_numeric($kuis_id)) {
    $_SESSION['pesan_gagal'] = "ID Kuis tidak valid.";
    header("Location: progres_guru.php");
    exit();
}

// 2. Ambil Data Master Kuis dari tryout_master
$kuis_data = null;
$stmt_master = $conn->prepare("
    SELECT
        tm.*,
        u.nama_lengkap AS nama_guru
    FROM tryout_master tm
    JOIN users u ON tm.id_guru = u.id
    WHERE tm.id = ?
");
$stmt_master->bind_param("i", $kuis_id);
$stmt_master->execute();
$result_master = $stmt_master->get_result();

if ($result_master->num_rows > 0) {
    $kuis_data = $result_master->fetch_assoc();
} else {
    $_SESSION['pesan_gagal'] = "Data Kuis tidak ditemukan.";
    // Redirect kembali ke detail guru jika guru_id tersedia, atau ke daftar progres guru
    $redirect_url = $guru_id ? "progres_detail_guru.php?user_id=" . $guru_id : "progres_guru.php";
    header("Location: " . $redirect_url);
    exit();
}
$stmt_master->close();

// 3. Ambil Daftar Soal dari soal_tryout
$soal_list = [];
$stmt_soal = $conn->prepare("
    SELECT
        * FROM soal_tryout
    WHERE tryout_id = ?
    ORDER BY id ASC
");
$stmt_soal->bind_param("i", $kuis_id);
$stmt_soal->execute();
$result_soal = $stmt_soal->get_result();

while ($row = $result_soal->fetch_assoc()) {
    $soal_list[] = $row;
}
$stmt_soal->close();
$total_soal = count($soal_list);

// Definisikan link kembali
$back_url = $guru_id ? "progres_detail_guru.php?user_id=" . $guru_id : "progres_guru.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Kuis: <?php echo htmlspecialchars($kuis_data['judul']); ?> | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .soal-container { border-left: 5px solid #0d6efd; padding-left: 15px; margin-bottom: 25px; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .jawaban-benar { background-color: #d1e7dd; border-color: #badbcc; color: #0f5132; font-weight: bold; }
        .img-soal { max-height: 200px; object-fit: contain; }
        .img-opsi { max-height: 50px; object-fit: contain; display: inline-block; vertical-align: middle; }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <header class="mb-4 pb-3 border-bottom">
        <h1><i class="fas fa-eye me-2"></i> Review Kuis (Admin)</h1>
        <p class="lead">Judul: <strong class="text-primary"><?php echo htmlspecialchars($kuis_data['judul']); ?></strong></p>
        <p class="text-muted">Dibuat oleh: <?php echo htmlspecialchars($kuis_data['nama_guru']); ?></p>
    </header>

    <a href="<?php echo $back_url; ?>" class="btn btn-outline-secondary mb-4 rounded-pill">
        <i class="fas fa-arrow-left"></i> Kembali ke Progres Guru
    </a>

    <div class="card shadow mb-5">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Detail Kuis</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Jenis Ujian:</strong> <?php echo htmlspecialchars($kuis_data['jenis_ujian']); ?></p>
                    <p><strong>Kelas Target:</strong> <?php echo htmlspecialchars($kuis_data['kelas']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Total Soal:</strong> <span class="badge bg-success fs-6"><?php echo $total_soal; ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-4 text-secondary"><i class="fas fa-list-ol me-2"></i> Daftar Soal</h2>

    <?php if ($total_soal > 0): ?>
        <?php $no = 1; foreach ($soal_list as $soal): ?>
            <div class="soal-container">
                <p class="fw-bold text-primary mb-3">Soal No. <?php echo $no++; ?> (ID Soal: <?php echo $soal['id']; ?>)</p>

                <div class="mb-3">
                    <label class="form-label fw-bold">Pertanyaan:</label>
                    <div class="alert alert-light border p-3"><?php echo $soal['pertanyaan']; ?></div>
                </div>

                <?php
                $soal_gambar_url = generateImageUrl($soal['gambar_url'] ?? '', $BASE_IMAGE_URL);
                if (!empty($soal_gambar_url)): ?>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($soal_gambar_url); ?>" alt="Gambar Soal"
                             class="img-fluid rounded shadow-sm img-soal"
                             onerror="this.onerror=null;this.src='https://placehold.co/200x150?text=Error+Gambar';">
                        <small class="d-block text-muted">Gambar Soal</small>
                    </div>
                <?php endif; ?>

                <div class="row g-2">
                    <?php
                    $opsi_keys = ['A', 'B', 'C', 'D'];
                    $jawaban_benar = $soal['jawaban_benar'];

                    foreach ($opsi_keys as $key):
                        $opsi_text_key = 'opsi_' . strtolower($key);
                        $opsi_url_key = 'opsi_' . strtolower($key) . '_gambar_url';
                        $is_correct = ($key == $jawaban_benar);

                        $opsi_gambar_url = generateImageUrl($soal[$opsi_url_key] ?? '', $BASE_IMAGE_URL);

                        $opsi_content = $soal[$opsi_text_key] ?? '';

                        if (!empty($opsi_gambar_url)) {
                            $opsi_content = '<img src="' . htmlspecialchars($opsi_gambar_url) . '" alt="Opsi ' . $key . '" class="img-fluid img-opsi me-2" onerror="this.onerror=null;this.src=\'https://placehold.co/60x40?text=Error\';">' . $opsi_content;
                        } elseif (empty($opsi_content)) {
                             $opsi_content = '<small class="text-danger">Kosong</small>';
                        }
                    ?>
                        <div class="col-md-6">
                            <div class="p-2 border rounded <?php echo $is_correct ? 'jawaban-benar' : 'bg-light'; ?>">
                                <span class="fw-bold me-2"><?php echo $key; ?>.</span>
                                <?php echo $opsi_content; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="col-12 mt-3">
                        <p class="mb-0"><strong>Kunci Jawaban:</strong> <span class="badge bg-danger fs-6"><?php echo htmlspecialchars($jawaban_benar); ?></span></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle"></i> Kuis ini belum memiliki soal.
        </div>
    <?php endif; ?>

    <a href="<?php echo $back_url; ?>" class="btn btn-outline-secondary mb-5 rounded-pill">
        <i class="fas fa-arrow-left"></i> Kembali ke Progres Guru
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Memuat ulang MathJax untuk merender rumus LaTeX jika ada
    document.addEventListener('DOMContentLoaded', function () {
        if (window.MathJax) {
            MathJax.typesetPromise();
        }
    });
</script>
</body>
</html>