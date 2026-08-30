<?php
// FILE: siswa/start_kuis.php - VERSI PERBAIKAN PATH GAMBAR

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan hanya siswa yang bisa mengakses
if ($_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

// -----------------------------------------------------
// !!! KODE PERBAIKAN BASE URL DAN PATH GAMBAR !!!
// -----------------------------------------------------

// 1. Tentukan Root Domain
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// 2. Tentukan Path Subfolder sampai folder 'aset' secara manual
// Pastikan diakhiri dengan garis miring '/' agar file terbaca benar
$path_prefix = '/portal_belajar/mathfiction/sem2/aset/';

// 3. Gabungkan (CUKUP SATU KALI SAJA)
$base_url .= $path_prefix;

$id_materi = isset($_GET['materi_id']) ? intval($_GET['materi_id']) : 0;

// Cek validitas ID materi
if ($id_materi === 0) {
    header("Location: materi.php");
    exit();
}

// === Ambil Data Materi ===
$query_materi = "SELECT judul, level_kategori FROM materi WHERE id = ?";
$stmt = $db_mapel->prepare($query_materi);
$stmt->bind_param("i", $id_materi);
$stmt->execute();
$result = $stmt->get_result();
$materi_info = $result->fetch_assoc();
$stmt->close();

if (!$materi_info) {
    $_SESSION['siswa_message'] = "<div class='alert alert-danger'>Materi tidak ditemukan.</div>";
    header("Location: materi.php");
    exit();
}

// === Proses Submit Jawaban (KODE TIDAK BERUBAH) ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validasi sederhana (Anda mungkin perlu validasi lebih ketat di lingkungan produksi)
    if (empty($_POST['jawaban'])) {
        $_SESSION['siswa_message'] = "<div class='alert alert-warning'>Anda harus menjawab semua soal sebelum mengirim.</div>";
        header("Location: start_quiz.php?materi_id=$id_materi");
        exit();
    }

    $jawaban_siswa = $_POST['jawaban'];
    $soal_ids = array_keys($jawaban_siswa);
    $total_soal = count($soal_ids);
    $skor = 0;
    $id_user = $_SESSION['user_id'];
    $waktu_sekarang = date('Y-m-d H:i:s');

    // Ambil kunci jawaban
    $placeholders = implode(',', array_fill(0, count($soal_ids), '?'));
    $query_kunci = "SELECT id, jawaban_benar FROM soal WHERE id IN ($placeholders)";

    $types = str_repeat('i', count($soal_ids));
    $stmt_kunci = $db_mapel->prepare($query_kunci);
    $stmt_kunci->bind_param($types, ...$soal_ids);
    $stmt_kunci->execute();
    $result_kunci = $stmt_kunci->get_result();
    $jawaban_benar_db = [];

    while ($row = $result_kunci->fetch_assoc()) {
        $jawaban_benar_db[$row['id']] = $row['jawaban_benar'];
    }
    $stmt_kunci->close();


    // --- Hitung Skor dan Siapkan Data Insert ---
    $detail_data = [];
    foreach ($soal_ids as $soal_id) {
        $jawaban_sis = $jawaban_siswa[$soal_id];
        $kunci = $jawaban_benar_db[$soal_id] ?? null;
        $skor_soal = (strtoupper($jawaban_sis) === strtoupper($kunci)) ? 1 : 0;
        $skor += $skor_soal;

        $detail_data[] = [
            'soal_id' => $soal_id,
            'jawaban_siswa' => $jawaban_sis,
            'skor_soal' => $skor_soal
        ];
    }

    $persentase = ($total_soal > 0) ? ($skor / $total_soal) * 100 : 0;
    $status_lulus = ($persentase >= 70) ? 'LULUS' : 'GAGAL';

    // --- INSERT RIWAYAT KUIS BARU ---
    $query_action = "INSERT INTO riwayat_kuis (id_user, id_materi, skor, total_soal, persentase, status_lulus, tanggal_dikerjakan)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_action = $db_mapel->prepare($query_action);
    $stmt_action->bind_param("iiiidss", $id_user, $id_materi, $skor, $total_soal, $persentase, $status_lulus, $waktu_sekarang);
    $stmt_action->execute();

    // --- MENDAPATKAN ID RIWAYAT YANG BARU DIBUAT ---
    $riwayat_kuis_id_baru = mysqli_insert_id($db_mapel);
    $stmt_action->close();

    // --- SIMPAN DETAIL HASIL DENGAN ID RIWAYAT BARU ---
    $query_insert_detail = "INSERT INTO hasil_quiz (user_id, riwayat_kuis_id, soal_id, jawaban_siswa, skor, waktu_jawab) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_detail = $db_mapel->prepare($query_insert_detail);

    foreach ($detail_data as $data) {
        $stmt_detail->bind_param("iiisis",
            $id_user,
            $riwayat_kuis_id_baru,
            $data['soal_id'],
            $data['jawaban_siswa'],
            $data['skor_soal'],
            $waktu_sekarang
        );
        $stmt_detail->execute();
    }
    $stmt_detail->close();

    // Pesan hasil
    $pesan_hasil = ($status_lulus === 'LULUS')
        ? "<h2 class='text-success'>Selamat! Anda LULUS Bab ini! 🎉</h2><p>Kode Verifikasi Berhasil Dibuka!</p>"
        : "<h2 class='text-danger'>Sayang sekali! Perlu dicoba lagi. 😔</h2><p>Pelajari kembali materinya sebelum mencoba kuis.</p>";

    $_SESSION['quiz_result'] = [
        'materi' => $materi_info['judul'],
        'level' => $materi_info['level_kategori'],
        'skor' => $skor,
        'total' => $total_soal,
        'persentase' => $persentase,
        'pesan' => $pesan_hasil,
        'id_materi' => $id_materi
    ];

    header("Location: quiz_result.php");
    exit();
}

// === Tampilkan Soal (KODE TIDAK BERUBAH) ===
$query_soal = "SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, gambar_url
                 FROM soal WHERE materi_id = ? ORDER BY RAND() LIMIT 50";
$stmt = $db_mapel->prepare($query_soal);
$stmt->bind_param("i", $id_materi);
$stmt->execute();
$result = $stmt->get_result();
$soal_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($soal_list)) {
    $_SESSION['siswa_message'] = "<div class='alert alert-warning'>Belum ada soal kuis yang disiapkan untuk bab ini.</div>";
    header("Location: materi.php");
    exit();
}

$page_title = "Quiz: " . $materi_info['judul'];
$message = $_SESSION['siswa_message'] ?? '';
unset($_SESSION['siswa_message']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?> | Mathfiction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .quiz-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .quiz-card div {
            line-height: 1.8;
        }
        /* Style untuk gambar */
        .quiz-card img {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 5px;
        }
        /* Style untuk MathJax */
        .tex2jax_process p, .tex2jax_process li { margin-bottom: 0.5rem; }
    </style>

    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
      window.MathJax = {
        tex: {
          // Tambahkan '$' sebagai delimiter untuk rumus inline
          inlineMath: [['$', '$'], ['\\(', '\\)']]
        },
        options: {
            // Gunakan class ini pada elemen yang mengandung rumus
            ignoreHtmlClass: 'tex2jax_ignore',
        }
      };
    </script>
    </head>
<body>
<div class="container mt-5">
    <h1 class="text-primary">KUIS</h1>
    <span class="badge bg-warning text-dark fs-5"><?= htmlspecialchars($materi_info['level_kategori']); ?></span>
    <h2 class="mb-4"><?= htmlspecialchars($materi_info['judul']); ?></h2>

    <p><a href="materi.php" class="btn btn-secondary btn-sm">← Kembali ke Daftar Materi</a></p>
    <hr>
    <?= $message; ?>

    <form method="POST" action="start_quiz.php?materi_id=<?= $id_materi; ?>">
        <?php foreach ($soal_list as $index => $soal): ?>

            <div class="quiz-card">
                <h5>Soal <?= $index + 1; ?>.</h5>

                <div class="fs-5 tex2jax_process mb-2">
                    <?= $soal['pertanyaan']; ?>
                </div>

                <?php if (!empty($soal['gambar_url'])): ?>
                    <?php
                        // --- KODE PERBAIKAN TAMPILAN GAMBAR ---
                        $image_path = htmlspecialchars($soal['gambar_url']);

                        // 1. Pastikan Path Gambar tidak mengandung http(s):// di depannya (hanya path relatif)
                        if (strpos($image_path, 'http') === 0) {
                            // Jika sudah URL lengkap, biarkan
                            $final_image_url = $image_path;
                        } else {
                            // 2. Jika path masih relatif (misal: 'aset/pp1.png'), gabungkan dengan $base_url
                            // Pastikan tidak ada double slash
                            $final_image_url = rtrim($base_url, '/') . '/' . ltrim($image_path, '/');
                        }
                    ?>
                    <div class="text-center my-3">
                        <img src="<?= $final_image_url; ?>"
                             alt="Gambar Soal <?= $index + 1; ?>"
                             class="img-fluid"
                             style="max-width: 80%;"
                             onerror="this.onerror=null;this.src='/assets/default-error.png';"> </div>
                <?php endif; ?>

                <?php foreach (['a','b','c','d'] as $opt): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="radio"
                               name="jawaban[<?= $soal['id']; ?>]"
                               id="soal_<?= $soal['id'].'_'.$opt; ?>"
                               value="<?= strtoupper($opt); ?>" required>
                        <label class="form-check-label tex2jax_process" for="soal_<?= $soal['id'].'_'.$opt; ?>">
                            <?= strtoupper($opt) . '. ' . $soal['opsi_'.$opt]; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-success btn-lg w-100 mt-4 mb-5">
            Kirim Jawaban 🚀
        </button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>