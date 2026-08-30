<?php
require_once '../config/koneksi.php';
require_once '../config/fungsi_input.php';

$BASE_IMAGE_URL = "../aset/";

function generateImageUrl($url_fragment, $base_url) {
    if (empty($url_fragment)) {
        return '';
    }
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) {
        return $url_fragment;
    }
    if (strpos(strtolower($url_fragment), 'aset/') === 0) {
        $url_fragment = substr($url_fragment, 5);
    }
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}

if (!isset($db_mapel) || $db_mapel === null) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Koneksi database gagal dimuat.</div>';
    exit();
}

if (!isset($_GET['riwayat_id']) || !is_numeric($_GET['riwayat_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Parameter riwayat_id tidak valid.</div>';
    exit();
}

$session_id = mysqli_real_escape_string($db_mapel, $_GET['riwayat_id']);

$query_detail = "
    SELECT
        tj.jawaban_siswa,
        st.pertanyaan,
        st.gambar_url AS soal_gambar_url,
        st.jawaban_benar,
        st.opsi_a, st.opsi_b, st.opsi_c, st.opsi_d,
        st.opsi_a_gambar_url, st.opsi_b_gambar_url, st.opsi_c_gambar_url, st.opsi_d_gambar_url
    FROM tryout_jawaban tj
    JOIN soal_tryout st ON tj.soal_id = st.id
    WHERE tj.session_id = '$session_id'
    ORDER BY st.id ASC
";

$result_detail = mysqli_query($db_mapel, $query_detail);

if (!$result_detail) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error database Tryout: ' . mysqli_error($db_mapel) . '</div>';
    exit();
}

if (mysqli_num_rows($result_detail) == 0) {
    echo '<div class="alert alert-warning">Tidak ada detail jawaban yang ditemukan untuk sesi Tryout ini.</div>';
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
    .img-soal { max-width: 100%; height: auto; max-height: 200px; object-fit: contain; margin-top: 10px; }
    .img-opsi { max-height: 60px; object-fit: contain; display: inline-block; vertical-align: middle; }
</style>

<div class="row">
    <div class="col-12">
        <?php $no = 1;
        while($row = mysqli_fetch_assoc($result_detail)):
            $jawaban_siswa = strtoupper($row['jawaban_siswa'] ?? 'N/A');
            $jawaban_benar = strtoupper($row['jawaban_benar'] ?? 'N/A');
            $is_correct = ($jawaban_siswa == $jawaban_benar && $jawaban_siswa !== 'N/A');
        ?>
        <div class="jawaban-container">
            <h6>
                Soal No. <?= $no++ ?>
                <?= $is_correct
                    ? '<span class="status-benar ms-2"><i class="fas fa-check-circle"></i> Benar</span>'
                    : '<span class="status-salah ms-2"><i class="fas fa-times-circle"></i> Salah</span>'
                ?>
            </h6>
            <p><strong>Pertanyaan:</strong> <?= nl2br(htmlspecialchars($row['pertanyaan'] ?? '')) ?></p>

            <?php
            $soal_gambar_url = generateImageUrl($row['soal_gambar_url'], $BASE_IMAGE_URL);
            if (!empty($soal_gambar_url)): ?>
                <div class="text-center p-2 border rounded bg-light mb-3">
                    <img src="<?= htmlspecialchars($soal_gambar_url); ?>" alt="Gambar Soal"
                        class="img-fluid rounded img-soal"
                        onerror="this.onerror=null;this.src='https://placehold.co/200x150?text=Error+Gambar';">
                </div>
            <?php endif; ?>

            <div class="jawaban-guru">
                <p>Jawaban Siswa: <strong><?= htmlspecialchars($jawaban_siswa) ?></strong></p>
                <p>Jawaban Benar: <span class="badge bg-success"><?= htmlspecialchars($jawaban_benar) ?></span></p>
            </div>
            <hr>
            <p><strong>Opsi Pilihan Ganda:</strong></p>
            <ul class="list-unstyled">
                <?php
                $opsi_data = [
                    'A' => ['text' => $row['opsi_a'] ?? '', 'url' => $row['opsi_a_gambar_url'] ?? ''],
                    'B' => ['text' => $row['opsi_b'] ?? '', 'url' => $row['opsi_b_gambar_url'] ?? ''],
                    'C' => ['text' => $row['opsi_c'] ?? '', 'url' => $row['opsi_c_gambar_url'] ?? ''],
                    'D' => ['text' => $row['opsi_d'] ?? '', 'url' => $row['opsi_d_gambar_url'] ?? ''],
                ];

                foreach ($opsi_data as $opt => $data) {
                    $opsi_teks = $data['text'];
                    $opsi_gambar_url = generateImageUrl($data['url'], $BASE_IMAGE_URL);

                    $is_siswa = ($jawaban_siswa == $opt);
                    $is_benar = ($jawaban_benar == $opt);

                    $class = '';
                    if ($is_benar) { $class = 'text-success fw-bold'; }
                    elseif ($is_siswa && !$is_benar) { $class = 'text-danger fw-bold'; }

                    echo '<li class="' . $class . '"><strong>' . $opt . '.</strong> ';
                    if (!empty($opsi_gambar_url)) {
                        echo '<img src="' . htmlspecialchars($opsi_gambar_url) . '" alt="Opsi ' . $opt . '" class="img-fluid border rounded img-opsi me-2" onerror="this.onerror=null;this.src=\'https://placehold.co/60x40?text=Error\';">';
                    }
                    echo htmlspecialchars($opsi_teks);
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
            MathJax.typesetPromise();
        }
    });
</script>