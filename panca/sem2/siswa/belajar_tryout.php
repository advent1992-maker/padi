<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$siswa_id = $_SESSION['user_id'];
$tryout_id = $_GET['tryout_id'] ?? null;

if (!$tryout_id || !is_numeric($tryout_id)) {
    header("Location: daftar_tryout.php");
    exit();
}

$BASE_IMAGE_URL = "../aset/";

function generateImageUrl($url_fragment, $base_url) {
    if (empty($url_fragment)) return '';
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) return $url_fragment;
    if (strpos(strtolower($url_fragment), 'aset/') === 0) {
        $url_fragment = substr($url_fragment, 5);
    }
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}

// Ambil info tryout
$stmt = $db_mapel->prepare("SELECT judul, waktu_alokasi FROM panca_tryout_master WHERE id = ?");
$stmt->bind_param("i", $tryout_id);
$stmt->execute();
$tryout_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tryout_data) {
    header("Location: daftar_tryout.php");
    exit();
}

// Ambil semua soal
$stmt_soal = $db_mapel->prepare("SELECT * FROM panca_soal_tryout WHERE tryout_id = ? ORDER BY id ASC");
$stmt_soal->bind_param("i", $tryout_id);
$stmt_soal->execute();
$soal_list = $stmt_soal->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_soal->close();
$jumlah_soal = count($soal_list);

// Proses jawaban mode belajar (POST)
$hasil_belajar = [];
$is_submitted = isset($_POST['submit_belajar']);

if ($is_submitted) {
    $jawaban_siswa = $_POST['jawaban'] ?? [];
    foreach ($soal_list as $s) {
        $id_s = $s['id'];
        $jw = $jawaban_siswa[$id_s] ?? '-';
        $kunci = strtoupper(trim($s['jawaban_benar']));
        $is_benar = (strtoupper(trim($jw)) === $kunci);
        $hasil_belajar[$id_s] = [
            'jawaban' => $jw,
            'kunci' => $kunci,
            'benar' => $is_benar
        ];
    }
}

$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Belajar | <?= htmlspecialchars($tryout_data['judul']) ?></title>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] },
            svg: { fontCache: 'global' }
        };
    </script>
    <style>
        body { background-color: #f0f8ff; }

        /* STICKY HEADER */
        .sticky-top-bar {
            position: sticky; top: 0; z-index: 1000;
            background: #fff; border-bottom: 2px solid #0d6efd;
            padding: 10px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        /* NAVIGASI NOMOR SOAL */
        .nav-soal-wrapper {
            position: sticky; top: 57px; z-index: 999;
            background: #f8f9fa; overflow-x: auto;
            white-space: nowrap; padding: 10px 15px;
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .nav-soal-wrapper::-webkit-scrollbar { height: 4px; }
        .btn-nomor {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            border: 2px solid #dee2e6; background: #fff;
            font-size: 0.8rem; font-weight: 700; color: #495057;
            margin-right: 6px; cursor: pointer; transition: 0.2s;
            text-decoration: none;
        }
        .btn-nomor.dijawab { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .btn-nomor.benar   { background: #198754; border-color: #198754; color: #fff; }
        .btn-nomor.salah   { background: #dc3545; border-color: #dc3545; color: #fff; }

        /* SOAL */
        .card-soal { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; background: #fff; }
        .opt-label { display: flex; align-items: center; padding: 12px; background: #fff; border: 2px solid #f0f0f0; border-radius: 10px; cursor: pointer; margin-bottom: 8px; transition: 0.2s; }
        .opt-input { display: none; }
        .opt-input:checked + .opt-label { border-color: #0d6efd; background: #f0f7ff; }

        /* HASIL per soal */
        .opt-benar  { border-color: #198754 !important; background: #d1e7dd !important; }
        .opt-salah  { border-color: #dc3545 !important; background: #f8d7da !important; }
        .opt-kunci  { border-color: #198754 !important; background: #d1e7dd !important; }

        .pembahasan-box { background: #fffdf0; border: 1px solid #ffeeba; border-radius: 10px; padding: 15px; margin-top: 10px; }

        /* HASIL AKHIR */
        .hasil-box { background: #fff; border-radius: 20px; padding: 30px; text-align: center; border: 2px solid #0d6efd; margin-bottom: 25px; }
    </style>
</head>
<body>

<!-- STICKY HEADER -->
<div class="sticky-top-bar">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="daftar_tryout.php" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <div class="text-center">
            <span class="badge bg-success px-3 py-2">
                <i class="fas fa-book-open me-1"></i> Mode Belajar
            </span>
            <div class="small text-muted fw-bold text-truncate" style="max-width: 200px;">
                <?= htmlspecialchars($tryout_data['judul']) ?>
            </div>
        </div>
        <div class="text-end">
            <small class="text-muted" id="info_dijawab">0/<?= $jumlah_soal ?> dijawab</small>
        </div>
    </div>
</div>

<!-- NAVIGASI NOMOR SOAL -->
<div class="nav-soal-wrapper">
    <?php foreach($soal_list as $index => $s): ?>
        <?php
            $kelas_nomor = '';
            if ($is_submitted) {
                $kelas_nomor = $hasil_belajar[$s['id']]['benar'] ? 'benar' : 'salah';
            }
        ?>
        <a href="#soal-<?= $index+1 ?>"
           class="btn-nomor <?= $kelas_nomor ?>"
           id="btn-nomor-<?= $index+1 ?>">
            <?= $index+1 ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="container py-3">

    <?php if ($is_submitted): ?>
    <!-- KOTAK HASIL AKHIR -->
    <?php
        $total_benar = count(array_filter($hasil_belajar, fn($h) => $h['benar']));
        $total_salah = $jumlah_soal - $total_benar;
        $nilai = round(($total_benar / $jumlah_soal) * 100);
    ?>
    <div class="hasil-box shadow-sm">
        <h5 class="fw-bold text-muted">Hasil Latihan Belajar</h5>
        <div class="display-1 fw-bold text-primary"><?= $nilai ?></div>
        <div class="row mt-3 border-top pt-3">
            <div class="col-4 text-center border-end">
                <strong class="text-success"><?= $total_benar ?></strong><br>
                <small class="text-muted">Benar</small>
            </div>
            <div class="col-4 text-center border-end">
                <strong class="text-danger"><?= $total_salah ?></strong><br>
                <small class="text-muted">Salah</small>
            </div>
            <div class="col-4 text-center">
                <strong><?= $jumlah_soal ?></strong><br>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="mt-3">
            <a href="belajar_tryout.php?tryout_id=<?= $tryout_id ?>" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-redo me-1"></i> Coba Lagi
            </a>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="formBelajar">
        <?php foreach($soal_list as $index => $s): ?>
        <?php
            $hasil = $hasil_belajar[$s['id']] ?? null;
            $border_color = '#dee2e6';
            if ($hasil) {
                $border_color = $hasil['benar'] ? '#198754' : '#dc3545';
            }
        ?>
        <div class="card-soal p-4" id="soal-<?= $index+1 ?>" style="border-left: 5px solid <?= $border_color ?>;">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary">Soal <?= $index+1 ?></span>
                <?php if ($hasil): ?>
                    <span class="badge bg-<?= $hasil['benar'] ? 'success' : 'danger' ?> px-3">
                        <i class="fas fa-<?= $hasil['benar'] ? 'check' : 'times' ?>-circle me-1"></i>
                        <?= $hasil['benar'] ? 'BENAR' : 'SALAH' ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="fw-bold mb-3" style="font-size: 1rem; line-height: 1.7; white-space: pre-wrap;"><?= htmlspecialchars(trim($s['pertanyaan'])) ?></div>

            <?php $soal_gambar_url = generateImageUrl($s['gambar_url'] ?? '', $BASE_IMAGE_URL); ?>
            <?php if (!empty($soal_gambar_url)): ?>
                <div class="text-center mb-3">
                    <img src="<?= htmlspecialchars($soal_gambar_url) ?>" class="img-fluid rounded border" style="max-height: 200px;">
                </div>
            <?php endif; ?>

            <!-- OPSI JAWABAN -->
            <div class="mt-3">
                <?php
                $opsi_list = ['A'=>$s['opsi_a'],'B'=>$s['opsi_b'],'C'=>$s['opsi_c'],'D'=>$s['opsi_d']];
                $opsi_gambar = [
                    'A'=>$s['opsi_a_gambar_url']??'',
                    'B'=>$s['opsi_b_gambar_url']??'',
                    'C'=>$s['opsi_c_gambar_url']??'',
                    'D'=>$s['opsi_d_gambar_url']??''
                ];
                foreach($opsi_list as $k => $teks):
                    $extra_class = '';
                    if ($hasil) {
                        if ($k === $hasil['kunci']) $extra_class = 'opt-kunci';
                        elseif ($k === $hasil['jawaban'] && !$hasil['benar']) $extra_class = 'opt-salah';
                    }
                    $opsi_img_url = generateImageUrl($opsi_gambar[$k], $BASE_IMAGE_URL);
                ?>
                    <input type="radio"
                           name="jawaban[<?= $s['id'] ?>]"
                           value="<?= $k ?>"
                           id="q<?= $s['id'].$k ?>"
                           class="opt-input"
                           <?= $is_submitted ? 'disabled' : '' ?>
                           <?= (isset($hasil) && $hasil['jawaban'] === $k) ? 'checked' : '' ?>
                           onchange="tandaiDijawab(<?= $index+1 ?>)">
                    <label for="q<?= $s['id'].$k ?>" class="opt-label <?= $extra_class ?>">
                        <span class="me-2 fw-bold text-primary"><?= $k ?>.</span>
                        <?php if (!empty($opsi_img_url)): ?>
                            <img src="<?= htmlspecialchars($opsi_img_url) ?>" class="img-fluid rounded" style="max-height: 60px;">
                        <?php else: ?>
                            <span style="white-space: pre-wrap;"><?= htmlspecialchars(trim($teks)) ?></span>
                        <?php endif; ?>
                        <?php if ($hasil && $k === $hasil['kunci']): ?>
                            <span class="ms-auto badge bg-success"><i class="fas fa-check"></i> Kunci</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- PEMBAHASAN (tampil setelah submit) -->
            <?php if ($is_submitted && !empty($s['pembahasan'] ?? '')): ?>
            <div class="pembahasan-box mt-3">
                <strong class="text-warning"><i class="fas fa-lightbulb me-1"></i> Pembahasan:</strong>
                <div class="mt-2" style="white-space: pre-wrap;"><?= htmlspecialchars(trim($s['pembahasan'])) ?></div>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <?php if (!$is_submitted): ?>
        <button type="submit" name="submit_belajar"
                class="btn btn-success btn-lg w-100 rounded-pill shadow-lg fw-bold py-3 mb-5"
                onclick="return confirm('Cek jawaban sekarang?')">
            <i class="fas fa-check-circle me-2"></i> CEK JAWABAN
        </button>
        <?php else: ?>
        <a href="daftar_tryout.php" class="btn btn-dark w-100 py-3 rounded-pill fw-bold mt-3 shadow mb-5">
            KEMBALI KE DAFTAR
        </a>
        <?php endif; ?>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const dijawab = new Set();

function tandaiDijawab(nomor) {
    dijawab.add(nomor);
    const btn = document.getElementById('btn-nomor-' + nomor);
    if (btn && !btn.classList.contains('benar') && !btn.classList.contains('salah')) {
        btn.classList.add('dijawab');
    }
    document.getElementById('info_dijawab').textContent = dijawab.size + '/<?= $jumlah_soal ?> dijawab';
}

window.addEventListener('load', function() {
    if (window.MathJax) MathJax.typeset();
});
</script>
</body>
</html>