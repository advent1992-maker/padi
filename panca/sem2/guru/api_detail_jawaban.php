<?php
// FILE: guru/api_detail_jawaban.php
// PERBAIKAN: Menambahkan logika Base URL dan kolom gambar untuk merender gambar dan LaTeX

require_once '../config/koneksi.php';
// require_once '../config/fungsi_input.php'; // Ini tidak diperlukan jika hanya menggunakan mysqli_real_escape_string

// --- 0. KONFIGURASI BASE URL ---
// !!! PENTING: Sesuaikan '/mathfiction/' dengan folder root proyek Anda !!!
$base_url = 'http://' . $_SERVER['HTTP_HOST'];
$path_prefix = '/mathfiction/';
$base_url .= $path_prefix;
// ------------------------------------

// --- 1. Validasi Koneksi & Input ---
if (!isset($db_mapel) || $db_mapel === null) {
     http_response_code(500);
     echo '<div class="alert alert-danger">Koneksi database gagal dimuat. Cek ../config/koneksi.php!</div>';
     exit();
}

if (!isset($_GET['riwayat_id']) || !is_numeric($_GET['riwayat_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Parameter riwayat_id tidak valid.</div>';
    exit();
}

// Sanitasi input
$riwayat_kuis_id = mysqli_real_escape_string($db_mapel, $_GET['riwayat_id']);

// --- 2. Ambil Detail Soal + Jawaban ---
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
    FROM panca_hasil_quiz hq
    JOIN panca_soal s ON hq.soal_id = s.id
    WHERE hq.riwayat_kuis_id = '$riwayat_kuis_id'
    ORDER BY s.id ASC
";

$result_detail = mysqli_query($db_mapel, $query_detail);

if (!$result_detail) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error database: ' . mysqli_error($db_mapel) . '</div>';
    exit();
}

if (mysqli_num_rows($result_detail) == 0) {
    echo '<div class="alert alert-warning">Tidak ada detail jawaban yang ditemukan untuk riwayat ini.</div>';
    exit();
}
?>

<style>
    .jawaban-container {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        background-color: #ffffff;
    }
    .status-benar {
        color: #155724;
        background-color: #d4edda;
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: bold;
    }
    .status-salah {
        color: #721c24;
        background-color: #f8d7da;
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: bold;
    }
    .jawaban-guru {
        background-color: #f0f8ff;
        border-left: 5px solid #007bff;
        padding: 10px;
        margin-top: 10px;
    }
    /* Style untuk gambar di modal */
    .img-modal {
        max-width: 100%;
        height: auto;
        max-height: 200px;
        object-fit: contain;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 5px;
    }
</style>

<div class="row">
    <div class="col-12">
        <?php $no = 1;
        while($row = mysqli_fetch_assoc($result_detail)):
            $jawaban_siswa = strtoupper($row['jawaban_siswa'] ?? 'N/A');
            $jawaban_benar = strtoupper($row['jawaban_benar'] ?? 'N/A');
            $is_correct = ($jawaban_siswa == $jawaban_benar && $jawaban_siswa !== 'N/A');

            // Logika Gambar Soal
            $gambar_soal_path = $row['gambar_url'] ?? null;
            $gambar_soal_url_fixed = !empty($gambar_soal_path) ? $base_url . $gambar_soal_path : null;
        ?>
        <div class="jawaban-container">
            <h6>
                Soal No. <?= $no++ ?>
                <?= $is_correct
                    ? '<span class="status-benar ms-2"><i class="fas fa-check-circle"></i> Benar</span>'
                    : '<span class="status-salah ms-2"><i class="fas fa-times-circle"></i> Salah</span>'
                ?>
            </h6>

            <p class="mb-3 tex2jax_process">
                <strong>Pertanyaan:</strong>
                <?= htmlspecialchars($row['pertanyaan'] ?? '') ?>
            </p>

            <?php if (!empty($gambar_soal_url_fixed)): ?>
                <div class="mb-3 text-center">
                    <img src="<?= htmlspecialchars($gambar_soal_url_fixed) ?>"
                        alt="Gambar Soal" class="img-modal"
                        onerror="this.onerror=null;this.src='https://placehold.co/200x150/f0f8ff/444?text=Gambar+Error';"
                    >
                </div>
            <?php endif; ?>

            <div class="jawaban-guru">
                <p>Jawaban Siswa: <strong><?= htmlspecialchars($jawaban_siswa) ?></strong></p>
                <p>Jawaban Benar: <span class="badge bg-success"><?= htmlspecialchars($jawaban_benar) ?></span></p>
            </div>

            <hr>

            <p><strong>Opsi Pilihan Ganda:</strong></p>
            <ul class="list-unstyled row g-2 tex2jax_process">
                <?php
                $opsi = ['A', 'B', 'C', 'D'];
                foreach ($opsi as $opt) {
                    $opsi_teks = $row['opsi_'.strtolower($opt)] ?? '';
                    $opsi_gambar_path = $row['opsi_'.strtolower($opt).'_gambar_url'] ?? null;
                    $opsi_gambar_url_fixed = !empty($opsi_gambar_path) ? $base_url . $opsi_gambar_path : null;

                    $is_siswa = ($jawaban_siswa == $opt);
                    $is_benar = ($jawaban_benar == $opt);

                    $class = 'col-md-6';
                    $style = '';
                    if ($is_benar) {
                        $class .= ' text-success fw-bold';
                        $style = 'border: 2px solid #198754; background-color: #f0fff0; padding: 5px; border-radius: 5px;';
                    } elseif ($is_siswa && !$is_benar) {
                        $class .= ' text-danger fw-bold';
                        $style = 'border: 2px solid #dc3545; background-color: #fff0f0; padding: 5px; border-radius: 5px;';
                    }

                    echo '<li class="' . $class . '" style="' . $style . '">';
                    echo '<strong>' . $opt . '.</strong> ';

                    if (!empty($opsi_gambar_url_fixed)) {
                        // TAMPILAN GAMBAR OPSI
                        echo '<img src="' . htmlspecialchars($opsi_gambar_url_fixed) . '" alt="Opsi ' . $opt . '" style="max-height: 50px; display: inline-block; object-fit: contain;">';
                    } else {
                        // TAMPILAN TEKS/LATEX OPSI
                        echo htmlspecialchars($opsi_teks);
                    }
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
mysqli_close($db_mapel);
?>