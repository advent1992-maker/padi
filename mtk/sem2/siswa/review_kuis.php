<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan user adalah siswa
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit;
}

// --- LOGIKA PATH GAMBAR ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_dir = dirname($current_dir); 
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $parent_dir . "/aset/";

$user_id = $_SESSION['user_id'] ?? 0;
$materi_id = $_GET['materi_id'] ?? null;
$waktu_pengerjaan = $_GET['waktu'] ?? null;

// Mengarahkan ke riwayat_progress.php jika parameter tidak lengkap
if (!$materi_id || !$waktu_pengerjaan) {
    header("Location: riwayat_progress.php");
    exit;
}

// Ambil Judul Materi
$judul_materi = 'Materi Tidak Ditemukan';
$query_materi = "SELECT judul FROM materi WHERE id = ?";
if ($stmt_materi = $db_mapel->prepare($query_materi)) {
    $stmt_materi->bind_param("i", $materi_id);
    $stmt_materi->execute();
    $result = $stmt_materi->get_result();
    if ($row = $result->fetch_assoc()) {
        $judul_materi = $row['judul'];
    }
    $stmt_materi->close();
}

// Ambil data review (DIPERBAIKI: Menambahkan kolom-kolom gambar_url)
$review_list = [];
$query_review = "
    SELECT
        s.pertanyaan, s.gambar_url,
        s.opsi_a, s.opsi_a_gambar_url,
        s.opsi_b, s.opsi_b_gambar_url,
        s.opsi_c, s.opsi_c_gambar_url,
        s.opsi_d, s.opsi_d_gambar_url,
        s.jawaban_benar, s.pembahasan, hq.jawaban_siswa, hq.skor
    FROM soal s
    JOIN hasil_quiz hq ON s.id = hq.soal_id
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
    <title>Review Kuis <?php echo htmlspecialchars($judul_materi); ?> | IPAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']]
            }
        };
    </script>

    <style>
        body { background-color: #f0f8ff; }
        .header-blue { background-color: #0d6efd; color: white; padding: 30px 0; }
        .jawaban-benar { border-left: 5px solid #198754; padding-left: 15px; background-color: rgba(25, 135, 84, 0.05); }
        .jawaban-siswa-salah { border-left: 5px solid #dc3545; padding-left: 15px; background-color: rgba(220, 53, 69, 0.05); }
        .jawaban-netral { border-left: 5px solid #dee2e6; padding-left: 15px; }
        .box-pembahasan { border-top: 2px dashed #0dcaf0; background-color: #f8ffff; }
        .question-content p { margin-bottom: 0.5rem; }
        /* Style Tambahan untuk Gambar */
        .img-review { max-height: 150px; border-radius: 8px; margin: 10px 0; border: 1px solid #ddd; }
        .img-opsi { height: 35px; margin-left: 10px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="header-blue text-center shadow-sm">
    <div class="container">
        <h1 class="display-5 fw-bold"><i class="fas fa-search"></i> Detail Review Kuis</h1>
        <p class="lead">Materi: <strong><?php echo htmlspecialchars($judul_materi); ?></strong></p>
    </div>
</div>

<div class="container mt-5 mb-5">
    <a href="riwayat_progress.php" class="btn btn-outline-secondary mb-4 shadow-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
    </a>

    <div class="card shadow-lg p-4 border-0 rounded-4">
        <?php
        $formatted_time = strtotime($waktu_pengerjaan)
            ? date('d M Y H:i', strtotime($waktu_pengerjaan))
            : 'Data Waktu Tidak Tersedia';
        ?>
        <div class="alert alert-info border-0 shadow-sm">
            <i class="fas fa-calendar-alt"></i> Waktu Pengerjaan: <strong><?php echo htmlspecialchars($formatted_time); ?></strong>
        </div>

        <?php if (empty($review_list)): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle"></i> Data review tidak ditemukan untuk sesi ini.
            </div>
        <?php else: ?>
            <?php $no = 1; foreach ($review_list as $review):
                $is_correct = ($review['skor'] == 1);
                $status_color = $is_correct ? 'success' : 'danger';
            ?>
                <div class="mb-5 p-4 border rounded-3 bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-primary">Soal #<?php echo $no++; ?></h5>
                        <span class="badge bg-<?php echo $status_color; ?> p-2 px-3 rounded-pill">
                            <i class="fas <?php echo $is_correct ? 'fa-check' : 'fa-times'; ?>"></i> 
                            <?php echo $is_correct ? 'Benar' : 'Salah'; ?>
                        </span>
                    </div>

                    <div class="fw-bold fs-5 mb-3 question-content tex2jax_process">
                        <?php echo $review['pertanyaan']; ?>
                    </div>

                    <?php if (!empty($review['gambar_url'])): ?>
                        <img src="<?php echo $base_url . $review['gambar_url']; ?>" class="img-review d-block" onerror="this.style.display='none'">
                    <?php endif; ?>

                    <ul class="list-unstyled mt-3">
                        <?php
                        $opsi_keys = ['A' => 'opsi_a', 'B' => 'opsi_b', 'C' => 'opsi_c', 'D' => 'opsi_d'];
                        foreach ($opsi_keys as $label => $key):
                            $is_siswa_choice = ($review['jawaban_siswa'] === $label);
                            
                            // // Logika: Hanya beri warna pada pilihan siswa (Merah jika salah, Hijau jika benar)
                            // // Kunci jawaban lain tidak diberi warna agar siswa belajar dari pembahasan
                            // $li_class = 'jawaban-netral';
                            // $icon = '<i class="far fa-circle text-muted me-2"></i>';

                            // if ($is_siswa_choice) {
                            //     if ($is_correct) {
                            //         $li_class = 'jawaban-benar text-success fw-bold';
                            //         $icon = '<i class="fas fa-check-circle text-success me-2"></i>';
                            //     } else {
                            //         $li_class = 'jawaban-siswa-salah text-danger fw-bold';
                            //         $icon = '<i class="fas fa-times-circle text-danger me-2"></i>';
                            //     }
                            // }
                        ?>
                            <li class="mb-2 p-3 rounded <?php echo $li_class; ?> tex2jax_process d-flex align-items-center">
                                <div>
                                    <?php echo $icon; ?>
                                    <strong><?php echo $label; ?>.</strong> <?php echo $review[$key]; ?>
                                </div>
                                <?php if (!empty($review[$key.'_gambar_url'])): ?>
                                    <img src="<?php echo $base_url . $review[$key.'_gambar_url']; ?>" class="img-opsi" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($review['pembahasan'])): ?>
                        <div class="mt-4 p-3 box-pembahasan rounded-bottom border-info border-start border-4">
                            <h6 class="text-info fw-bold"><i class="fas fa-lightbulb"></i> Pembahasan:</h6>
                            <div class="tex2jax_process text-dark mt-2">
                                <?php echo $review['pembahasan']; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 p-2 text-muted fst-italic">
                            <small><i class="fas fa-info-circle"></i> Pembahasan tidak tersedia.</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>