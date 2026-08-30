<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit;
}

$paket_id = isset($_GET['paket_id']) ? intval($_GET['paket_id']) : 0;
if ($paket_id === 0) {
    header("Location: materi_list.php");
    exit;
}

$conn_pusat = $conn;

// Ambil Info Paket + durasi
$q_paket = mysqli_query($conn_pusat, "SELECT nama_paket, durasi_menit FROM paket_peng_diri WHERE id = '$paket_id'");
$info_paket = mysqli_fetch_assoc($q_paket);
$durasi_detik = ($info_paket['durasi_menit'] ?? 30) * 60;

// Ambil Soal
$q_soal = mysqli_query($conn_pusat, "SELECT * FROM osn WHERE paket_id = '$paket_id' ORDER BY id ASC");
$soal_data = [];
while($row = mysqli_fetch_assoc($q_soal)) {
    $soal_data[] = $row;
}
$jumlah_soal = count($soal_data);

// Proses Submit
$is_submitted = isset($_POST['submit_jawaban']);
$nilai_akhir = 0;
$skor_benar = 0;

if ($is_submitted) {
    $jawaban_siswa = $_POST['jawaban'] ?? [];
    $id_user = $_SESSION['user_id'];
    $waktu_sekarang = date('Y-m-d H:i:s');
    $detail_hasil = [];

    foreach ($soal_data as $s) {
        $id_s = $s['id'];
        $jw_siswa = $jawaban_siswa[$id_s] ?? '-';
        $kunci = trim($s['kunci_jawaban']);
        $is_match = (strtoupper(trim($jw_siswa)) === strtoupper($kunci));
        $poin = $is_match ? 1 : 0;
        $skor_benar += $poin;
        $detail_hasil[] = ['soal_id' => $id_s, 'jawaban' => $jw_siswa, 'poin' => $poin];
    }

    $persentase = ($jumlah_soal > 0) ? ($skor_benar / $jumlah_soal) * 100 : 0;
    $status_lulus = ($persentase >= 70) ? 'LULUS' : 'GAGAL';
    $nilai_akhir = round($persentase);

    $stmt = $conn_pusat->prepare("INSERT INTO riwayat_kuis (id_user, id_materi, skor, total_soal, persentase, status_lulus, tanggal_dikerjakan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiss", $id_user, $paket_id, $skor_benar, $jumlah_soal, $nilai_akhir, $status_lulus, $waktu_sekarang);
    $stmt->execute();
    $riwayat_id = $conn_pusat->insert_id;
    $stmt->close();

    $stmt_det = $conn_pusat->prepare("INSERT INTO hasil_quiz (user_id, riwayat_kuis_id, soal_id, jawaban_siswa, skor, waktu_jawab) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($detail_hasil as $dh) {
        $stmt_det->bind_param("iiisis", $id_user, $riwayat_id, $dh['soal_id'], $dh['jawaban'], $dh['poin'], $waktu_sekarang);
        $stmt_det->execute();
    }
    $stmt_det->close();
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url_aset = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))) . "/aset/";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Latihan OSN | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/core-js/3.30.2/minified.min.js"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true
            }
        };
    </script>
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; }

        /* STICKY HEADER */
        .sticky-header {
            position: sticky; top: 0; z-index: 1000;
            background: #fff; border-bottom: 1px solid #eee;
            padding: 10px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .timer-box {
            background: #0d6efd; color: #fff;
            border-radius: 10px; padding: 6px 14px;
            font-size: 1.1rem; font-weight: 700;
            min-width: 80px; text-align: center;
        }
        .timer-box.warning { background: #fd7e14; }
        .timer-box.danger  { background: #dc3545; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.6;} }

        /* NAVIGASI NOMOR SOAL */
        .nav-soal-wrapper {
    position: sticky;
    top: 57px; /* tinggi sticky-header */
    z-index: 999;
    background: #f8f9fa;
    overflow-x: auto;
    white-space: nowrap;
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
        .nav-soal-wrapper::-webkit-scrollbar { height: 4px; }
        .nav-soal-wrapper::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 2px; }

        .btn-nomor {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            border: 2px solid #dee2e6; background: #fff;
            font-size: 0.8rem; font-weight: 700; color: #495057;
            margin-right: 6px; cursor: pointer; transition: 0.2s;
            text-decoration: none;
        }
        .btn-nomor.dijawab { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .btn-nomor.aktif   { border-color: #fd7e14; box-shadow: 0 0 0 3px rgba(253,126,20,0.25); }

        /* SOAL */
        .card-soal { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; padding: 20px; background: #fff; }
        .img-soal  { width: 100%; border-radius: 12px; margin: 10px 0; border: 1px solid #f0f0f0; }
        .opt-label { display: flex; align-items: center; padding: 14px; background: #fff; border: 2px solid #f0f0f0; border-radius: 12px; cursor: pointer; margin-bottom: 8px; transition: 0.2s; }
        .opt-input { display: none; }
        .opt-input:checked + .opt-label { border-color: #0d6efd; background: #f0f7ff; box-shadow: 0 4px 10px rgba(13,110,253,0.1); }

        /* HASIL */
        .hasil-box { background: #fff; border-radius: 20px; padding: 30px; text-align: center; margin-bottom: 25px; border: 2px solid #0d6efd; }
        .pembahasan-box { background: #f8fff9; border: 1px solid #d1e7dd; border-radius: 15px; padding: 15px; margin-top: 10px; font-size: 0.9rem; }
    </style>
</head>
<body class="tex2jax_process">

<?php if (!$is_submitted): ?>

<!-- STICKY HEADER dengan Timer -->
<div class="sticky-header">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="materi_list.php?kat=osn" class="text-dark" onclick="return confirm('Yakin keluar? Jawaban tidak tersimpan.')">
            <i class="fas fa-times fa-lg"></i>
        </a>
        <h6 class="fw-bold mb-0 text-truncate px-2" style="max-width: 55%;">
            <?= htmlspecialchars($info_paket['nama_paket']) ?>
        </h6>
        <div class="timer-box" id="timerBox">
            <i class="fas fa-clock me-1"></i><span id="timerDisplay">--:--</span>
        </div>
    </div>
</div>

<!-- NAVIGASI NOMOR SOAL -->
<div class="nav-soal-wrapper" id="navSoal">
    <?php foreach($soal_data as $index => $s): ?>
        <a href="#soal-<?= $index+1 ?>" 
           class="btn-nomor" 
           id="btn-nomor-<?= $index+1 ?>"
           onclick="setAktif(<?= $index+1 ?>)">
            <?= $index+1 ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- INFO PROGRESS -->
<div class="container pt-3 pb-1">
    <small class="text-muted">
        <span id="jml_dijawab">0</span> dari <?= $jumlah_soal ?> soal dijawab
    </small>
</div>

<div class="container py-2">
    <form method="POST" id="formSoal">
        <?php foreach ($soal_data as $index => $s): ?>
            <div class="card-soal" id="soal-<?= $index+1 ?>">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge bg-primary">Soal <?= $index+1 ?></span>
                </div>
                <div class="fw-bold mb-3" style="font-size: 1.05rem; line-height: 1.7; white-space: pre-wrap;"><?= htmlspecialchars(trim($s['pertanyaan'])) ?>
                </div>

                <?php if(!empty($s['gambar_url'])): ?>
                    <img src="<?= $base_url_aset . $s['gambar_url'] ?>" class="img-soal">
                <?php endif; ?>

                <div class="options-group mt-3">
                    <?php if($s['tipe_soal'] == 'pg'): ?>
                        <?php 
                            $pilihan = ['A'=>$s['opsi_a'],'B'=>$s['opsi_b'],'C'=>$s['opsi_c'],'D'=>$s['opsi_d']];
                            $keys = array_keys($pilihan);
                            shuffle($keys);
                        ?>
                        <?php foreach($keys as $k): ?>
                            <input type="radio" 
                                   name="jawaban[<?= $s['id'] ?>]" 
                                   value="<?= $k ?>" 
                                   id="q<?= $s['id'].$k ?>" 
                                   class="opt-input"
                                   onchange="tandaiDijawab(<?= $index+1 ?>)">
                            <label for="q<?= $s['id'].$k ?>" class="opt-label">
                                <span class="me-2 text-primary fw-bold"><?= $k ?>.</span>
                                <?= htmlspecialchars($pilihan[$k]) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <input type="text" 
                               name="jawaban[<?= $s['id'] ?>]" 
                               class="form-control form-control-lg rounded-3" 
                               placeholder="Ketik jawaban singkat..."
                               oninput="tandaiDijawab(<?= $index+1 ?>)">
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" name="submit_jawaban" id="btnSubmit"
                class="btn btn-primary btn-lg w-100 rounded-pill shadow-lg fw-bold py-3 mb-5"
                onclick="return konfirmasiSubmit()">
            KIRIM JAWABAN <i class="fas fa-paper-plane ms-2"></i>
        </button>
    </form>
</div>

<script>
// ========== TIMER ==========
let sisaDetik = <?= $durasi_detik ?>;
const timerDisplay = document.getElementById('timerDisplay');
const timerBox = document.getElementById('timerBox');

function formatWaktu(detik) {
    const m = Math.floor(detik / 60).toString().padStart(2, '0');
    const s = (detik % 60).toString().padStart(2, '0');
    return m + ':' + s;
}

function jalankanTimer() {
    timerDisplay.textContent = formatWaktu(sisaDetik);

    if (sisaDetik <= 60) {
        timerBox.className = 'timer-box danger';
    } else if (sisaDetik <= 300) {
        timerBox.className = 'timer-box warning';
    }

    if (sisaDetik <= 0) {
    // Tambahkan input hidden agar server tahu ini auto-submit
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'submit_jawaban';
    hidden.value = '1';
    document.getElementById('formSoal').appendChild(hidden);
    
    // Tampilkan notifikasi sebelum submit
    alert('⏰ Waktu habis! Jawaban kamu otomatis dikirim.');
    document.getElementById('formSoal').submit();
    return;
}

    sisaDetik--;
    setTimeout(jalankanTimer, 1000);
}
jalankanTimer();

// ========== NAVIGASI NOMOR SOAL ==========
const dijawab = new Set();

function tandaiDijawab(nomor) {
    dijawab.add(nomor);
    const btn = document.getElementById('btn-nomor-' + nomor);
    if (btn) btn.classList.add('dijawab');
    document.getElementById('jml_dijawab').textContent = dijawab.size;
}

function setAktif(nomor) {
    document.querySelectorAll('.btn-nomor').forEach(b => b.classList.remove('aktif'));
    const btn = document.getElementById('btn-nomor-' + nomor);
    if (btn) btn.classList.add('aktif');
}

// Tandai aktif saat scroll
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.id; // soal-N
            const nomor = parseInt(id.split('-')[1]);
            setAktif(nomor);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('.card-soal').forEach(el => observer.observe(el));

// ========== KONFIRMASI SUBMIT ==========
function konfirmasiSubmit() {
    const belumDijawab = <?= $jumlah_soal ?> - dijawab.size;
    if (belumDijawab > 0) {
        return confirm('Masih ada ' + belumDijawab + ' soal belum dijawab. Yakin ingin kirim?');
    }
    return confirm('Yakin ingin mengirim semua jawaban?');
}
</script>

<?php else: ?>

<!-- HALAMAN HASIL -->
<div class="container py-4">
    <div class="hasil-box shadow-sm">
        <h5 class="fw-bold text-muted">Skor Latihan Anda</h5>
        <div class="display-1 fw-bold text-primary"><?= $nilai_akhir ?></div>
        <p>Status: <span class="badge bg-<?= ($status_lulus == 'LULUS') ? 'success' : 'danger' ?>"><?= $status_lulus ?></span></p>
        <div class="row mt-3 border-top pt-3">
            <div class="col-6 text-center border-end"><strong><?= $skor_benar ?></strong><br><small class="text-muted">Benar</small></div>
            <div class="col-6 text-center"><strong><?= $jumlah_soal ?></strong><br><small class="text-muted">Total Soal</small></div>
        </div>
    </div>

    <h6 class="fw-bold mb-3"><i class="fas fa-book-open me-2"></i>Review & Pembahasan</h6>
    <?php foreach ($soal_data as $index => $s):
        $jw_siswa = $_POST['jawaban'][$s['id']] ?? '-';
        $is_benar = (strtoupper(trim($jw_siswa)) === strtoupper(trim($s['kunci_jawaban'])));
    ?>
        <div class="card-soal" style="border-left: 6px solid <?= $is_benar ? '#198754' : '#dc3545' ?>;">
            <div class="d-flex justify-content-between mb-2">
                <span class="badge bg-secondary">Soal <?= $index+1 ?></span>
                <span class="text-<?= $is_benar ? 'success' : 'danger' ?> fw-bold small">
                    <i class="fas fa-<?= $is_benar ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= $is_benar ? 'BENAR' : 'SALAH' ?>
                </span>
            </div>
            <div class="small fw-bold mb-3" style="white-space: pre-wrap;"><?= htmlspecialchars(trim($s['pertanyaan'])) ?></div>
            <div class="pembahasan-box shadow-sm">
                <div class="mb-1 small">Jawaban Kamu: <span class="fw-bold <?= $is_benar ? 'text-success' : 'text-danger' ?>"><?= $jw_siswa ?></span></div>
                <div class="mb-2 small">Kunci Jawaban: <span class="fw-bold text-success"><?= $s['kunci_jawaban'] ?></span></div>
                <?php if(!empty($s['pembahasan'])): ?>
                    <hr class="my-2 opacity-25">
                    <div class="text-dark" style="font-size: 0.85rem;">
                        <strong class="text-primary"><i class="fas fa-lightbulb me-1"></i> Pembahasan:</strong><br>
                        <div style="white-space: pre-wrap;"><?= htmlspecialchars(trim($s['pembahasan'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="materi_list.php?kat=osn" class="btn btn-dark w-100 py-3 rounded-pill fw-bold mt-3 shadow mb-5">
        KEMBALI KE DAFTAR
    </a>
</div>

<script>
window.addEventListener('load', function() {
    if (window.MathJax) MathJax.typeset();
});
</script>

<?php endif; ?>
</body>
</html>