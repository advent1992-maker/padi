<?php
// FILE: guru/api_detail_tryout.php

require_once dirname(__FILE__) . '/../../../config/session.php';
require_once dirname(__FILE__) . '/../../../config/koneksi.php';

// --- 0. KONFIGURASI URL GAMBAR ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';

function fixImageUrl($url, $base) {
    if (empty($url)) return '';
    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    // Jika path mengandung 'aset/', bersihkan agar tidak double
    $url = str_replace('../', '', $url);
    return $base . ltrim($url, '/');
}

// --- 1. Validasi Koneksi & Input ---
if (!isset($db_mapel) || $db_mapel === null) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Koneksi database mapel gagal.</div>';
    exit();
}

if (!isset($_GET['riwayat_id']) || !is_numeric($_GET['riwayat_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID Sesi Tryout tidak valid.</div>';
    exit();
}

$session_id = mysqli_real_escape_string($db_mapel, $_GET['riwayat_id']);

// --- 2. Query Detail Jawaban Tryout ---
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
    echo '<div class="alert alert-danger">Error: ' . mysqli_error($db_mapel) . '</div>';
    exit();
}

if (mysqli_num_rows($result_detail) == 0) {
    echo '<div class="alert alert-warning text-center">Detail jawaban tryout tidak ditemukan.</div>';
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
    .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
    .bg-benar { color: #198754; background: #e8f5e9; }
    .bg-salah { color: #dc3545; background: #ffebee; }
    .panel-info-jawaban { background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
    .img-detail { max-width: 100%; max-height: 200px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #eee; }
    .opsi-item { padding: 10px; border-radius: 8px; border: 1px solid #f0f0f0; margin-bottom: 8px; transition: 0.2s; }
    .opsi-item.benar { background-color: #e8f5e9; border-color: #c8e6c9; color: #2e7d32; font-weight: bold; }
    .opsi-item.salah { background-color: #ffebee; border-color: #ffcdd2; color: #c62828; font-weight: bold; }
</style>

<div class="row">
    <div class="col-12">
        <?php $no = 1; while($row = mysqli_fetch_assoc($result_detail)):
            $js = strtoupper($row['jawaban_siswa'] ?? '');
            $jb = strtoupper($row['jawaban_benar'] ?? '');
            $is_correct = ($js === $jb && $js !== '');
        ?>
        <div class="jawaban-container shadow-sm">
            <div class="d-flex justify-content-between mb-3">
                <h6 class="fw-bold">No. <?= $no++ ?> (Try Out)</h6>
                <span class="badge-status <?= $is_correct ? 'bg-benar' : 'bg-salah' ?>">
                    <i class="fas <?= $is_correct ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                    <?= $is_correct ? 'Benar' : 'Salah' ?>
                </span>
            </div>

            <div class="tex2jax_process mb-3"><?= $row['pertanyaan'] ?></div>

            <?php if (!empty($row['soal_gambar_url'])): ?>
                <div class="text-center mb-3">
                    <img src="<?= fixImageUrl($row['soal_gambar_url'], $base_url) ?>" class="img-detail shadow-sm">
                </div>
            <?php endif; ?>

            <div class="panel-info-jawaban small">
                <div>Jawaban Siswa: <strong><?= $js ?: 'Tidak Menjawab' ?></strong></div>
                <div>Kunci Jawaban: <strong class="text-success"><?= $jb ?></strong></div>
            </div>

            <div class="row g-2">
                <?php
                $opsi = ['A', 'B', 'C', 'D'];
                foreach ($opsi as $o):
                    $label = strtolower($o);
                    $is_kunci = ($jb === $o);
                    $is_pilihan = ($js === $o);
                    $state_class = "";
                    if ($is_kunci) $state_class = "benar";
                    elseif ($is_pilihan && !$is_correct) $state_class = "salah";
                ?>
                <div class="col-md-6">
                    <div class="opsi-item <?= $state_class ?>">
                        <span class="me-2"><?= $o ?>.</span>
                        <span class="tex2jax_process">
                            <?php if (!empty($row["opsi_{$label}_gambar_url"])): ?>
                                <img src="<?= fixImageUrl($row["opsi_{$label}_gambar_url"], $base_url) ?>" style="max-height: 40px;">
                            <?php else: ?>
                                <?= $row["opsi_{$label}"] ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($is_pilihan): ?>
                            <i class="fas fa-user-edit ms-2 small" title="Pilihan Siswa"></i>
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