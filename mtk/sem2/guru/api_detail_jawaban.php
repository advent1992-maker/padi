<?php
// FILE: guru/api_detail_jawaban.php

require_once dirname(__FILE__) . '/../../../config/session.php';
require_once dirname(__FILE__) . '/../../../config/koneksi.php';

// --- 0. KONFIGURASI BASE URL OTOMATIS ---
// Mengambil protokol (http/https) dan host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Mengambil path sampai ke root project (asumsi folder project adalah 'padi' atau sejenisnya)
// Kode ini akan membantu merender gambar dari folder /assets/
$base_url = $protocol . $host . '/';
// ------------------------------------

// --- 1. Validasi Koneksi & Input ---
if (!isset($db_mapel) || $db_mapel === null) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Koneksi database mapel gagal dimuat.</div>';
    exit();
}

if (!isset($_GET['riwayat_id']) || !is_numeric($_GET['riwayat_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Parameter ID Riwayat tidak valid.</div>';
    exit();
}

// Sanitasi input
$riwayat_id = mysqli_real_escape_string($db_mapel, $_GET['riwayat_id']);

// --- 2. Ambil Detail Soal + Jawaban dari Tabel hasil_quiz ---
$query_detail = "
    SELECT
        hq.jawaban_siswa,
        s.pertanyaan,
        s.gambar_url,
        s.jawaban_benar,
        s.opsi_a, s.opsi_a_gambar_url,
        s.opsi_b, s.opsi_b_gambar_url,
        s.opsi_c, s.opsi_c_gambar_url,
        s.opsi_d, s.opsi_d_gambar_url
    FROM hasil_quiz hq
    JOIN soal s ON hq.soal_id = s.id
    WHERE hq.riwayat_kuis_id = '$riwayat_id'
    ORDER BY s.id ASC
";

$result_detail = mysqli_query($db_mapel, $query_detail);

if (!$result_detail) {
    echo '<div class="alert alert-danger">Error database: ' . mysqli_error($db_mapel) . '</div>';
    exit();
}

if (mysqli_num_rows($result_detail) == 0) {
    echo '<div class="alert alert-warning text-center">Detail jawaban tidak ditemukan. Mungkin data hasil_quiz sudah dibersihkan.</div>';
    exit();
}
?>

<style>
    .jawaban-container {
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        background-color: #fff;
    }
    .status-badge-benar { color: #198754; background: #e8f5e9; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
    .status-badge-salah { color: #dc3545; background: #ffebee; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }

    .panel-jawaban {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }
    .img-soal-modal {
        max-width: 100%;
        max-height: 250px;
        object-fit: contain;
        border-radius: 8px;
        margin: 10px 0;
        border: 1px solid #eee;
    }
    .opsi-box {
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 5px;
        border: 1px solid #f0f0f0;
    }
    .opsi-benar { background-color: #e8f5e9; border-color: #c8e6c9; color: #2e7d32; font-weight: 600; }
    .opsi-salah { background-color: #ffebee; border-color: #ffcdd2; color: #c62828; font-weight: 600; }
</style>

<div class="row">
    <div class="col-12">
        <?php $no = 1; while($row = mysqli_fetch_assoc($result_detail)):
            $js = strtoupper($row['jawaban_siswa']);
            $jb = strtoupper($row['jawaban_benar']);
            $is_correct = ($js === $jb);
        ?>
        <div class="jawaban-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Pertanyaan <?= $no++ ?></h6>
                <span class="<?= $is_correct ? 'status-badge-benar' : 'status-badge-salah' ?>">
                    <i class="fas <?= $is_correct ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                    <?= $is_correct ? 'Benar' : 'Salah' ?>
                </span>
            </div>

            <div class="mb-3 tex2jax_process">
                <?= $row['pertanyaan'] ?>
            </div>

            <?php if (!empty($row['gambar_url'])): ?>
                <div class="text-center">
                    <img src="<?= $base_url . $row['gambar_url'] ?>" class="img-soal-modal" alt="Gambar Soal">
                </div>
            <?php endif; ?>

            <div class="row mt-3 g-2">
                <?php
                $options = ['A', 'B', 'C', 'D'];
                foreach ($options as $key):
                    $opt_label = strtolower($key);
                    $is_this_correct_answer = ($jb === $key);
                    $is_this_student_answer = ($js === $key);

                    $box_class = "";
                    if ($is_this_correct_answer) $box_class = "opsi-benar";
                    elseif ($is_this_student_answer && !$is_correct) $box_class = "opsi-salah";
                ?>
                <div class="col-md-6">
                    <div class="opsi-box <?= $box_class ?>">
                        <small class="fw-bold"><?= $key ?>.</small>
                        <span class="tex2jax_process">
                            <?php if (!empty($row['opsi_'.$opt_label.'_gambar_url'])): ?>
                                <img src="<?= $base_url . $row['opsi_'.$opt_label.'_gambar_url'] ?>" style="max-height: 40px;">
                            <?php else: ?>
                                <?= $row['opsi_'.$opt_label] ?>
                            <?php endif; ?>
                        </span>

                        <?php if ($is_this_student_answer): ?>
                            <small class="badge bg-secondary ms-1" style="font-size: 0.6rem;">Pilihan Siswa</small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php mysqli_close($db_mapel); ?>