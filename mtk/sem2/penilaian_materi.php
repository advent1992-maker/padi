<?php
require_once '../../config/koneksi.php';
require_once '../../config/session.php';

// Pengamanan Role
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? 0;
$sekolah_default = 'Mathfiction School';

$kode_app = 'MATHFICTION_SEM2';

$instrumen_siswa = [
    'A. Aspek Materi Matematika' => [
        'Materi matematika dalam aplikasi ini mudah saya pahami.',
        'Contoh soal yang diberikan membantu saya belajar mandiri.',
        'Bahasa yang digunakan dalam penjelasan materi tidak membingungkan.'
    ],
    'B. Aspek Kemenarikan & Media' => [
        'Tampilan animasi dan gambar materi menarik perhatian saya.',
        'Permainan atau kuis di dalam Mathfiction sangat menantang.',
        'Media ini membuat belajar matematika menjadi lebih menyenangkan.'
    ],
    'C. Aspek Manfaat' => [
        'Setelah menggunakan media ini, saya lebih mengerti rumus-rumus matematika.',
        'Saya ingin mempelajari bab selanjutnya menggunakan aplikasi ini.',
        'Saya merasa lebih percaya diri saat mengerjakan soal latihan.'
    ]
];

$pesan_status = '';
$sudah_submit_ulasan = false;

if (isset($user_id)) {
    $query_check = "SELECT COUNT(*) FROM hasil_uji_siswa WHERE id_user = ? AND kode_aplikasi = ?";
    $stmt_check = $db_mapel->prepare($query_check);
    $stmt_check->bind_param("is", $user_id, $kode_app);
    $stmt_check->execute();
    $stmt_check->bind_result($count_submissions);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_submissions > 0) {
        $sudah_submit_ulasan = true;
        $pesan_status = '<div class="alert alert-info text-center shadow-sm"><i class="fas fa-check-circle me-2"></i> Anda telah menyelesaikan evaluasi materi <strong>Mathfiction Semester 2</strong>. Terima kasih!</div>';
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_siswa']) && !$sudah_submit_ulasan) {
    $kelas = $level_kelas;
    $tanggal_uji = date("Y-m-d");

    try {
        $db_mapel->begin_transaction();
        $query = "INSERT INTO hasil_uji_siswa (id_user, kelas, sekolah, indikator, skor_penilaian, tanggal_uji, kode_aplikasi) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_siswa = $db_mapel->prepare($query);

        $semua_dinilai = true;

        foreach ($instrumen_siswa as $aspek => $indikator_list) {
            foreach ($indikator_list as $index => $indikator) {
                $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                $input_skor_name = "skor_siswa_" . $aspek_key . "_" . $index;
                $skor = $_POST[$input_skor_name] ?? 0;

                if ($skor > 0) {
                    $stmt_insert_siswa->bind_param("isssiss",
                        $user_id, $kelas, $sekolah_default, $indikator, $skor, $tanggal_uji, $kode_app
                    );
                    $stmt_insert_siswa->execute();
                } else {
                    $semua_dinilai = false;
                }
            }
        }
        $stmt_insert_siswa->close();

        if ($semua_dinilai) {
            $db_mapel->commit();
            $pesan_status = '<div class="alert alert-success shadow-sm">Berhasil! Penilaian materi Semester 2 Anda telah tersimpan.</div>';
            $sudah_submit_ulasan = true;
        } else {
            $db_mapel->rollback();
            $pesan_status = '<div class="alert alert-warning">Mohon isi semua penilaian yang tersedia.</div>';
        }
    } catch (Exception $e) {
        $db_mapel->rollback();
        $pesan_status = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Evaluasi Materi | MATHFICTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .form-container { background: white; border-radius: 20px; border: none; }
        .bg-aspek { background-color: #f8f9fc; border-left: 5px solid #4e73df; }

        /* Style untuk pilihan skor vertikal */
        .score-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .score-option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background-color: #ffffff;
            border: 2px solid #edeff2;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .score-option:hover {
            background-color: #f1f3f9;
            border-color: #4e73df;
        }
        .score-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }
        .score-text {
            font-size: 1rem;
            color: #4e5e7a;
            font-weight: 500;
        }
        /* Style saat radio dipilih */
        .score-option input[type="radio"]:checked + .score-text {
            color: #4e73df;
            font-weight: bold;
        }
        .score-option input[type="radio"]:checked {
            accent-color: #4e73df;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">Evaluasi Pembelajaran</h2>
                <p class="text-muted">Mathfiction Semester 2 - Matematika Menyenangkan</p>
                <a href="siswa/dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Materi
                </a>
            </div>

            <?php echo $pesan_status; ?>

            <?php if (!$sudah_submit_ulasan): ?>
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px; border-left: 6px solid #ffc107 !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark"><i class="fas fa-info-circle me-2 text-warning"></i> Petunjuk Pengisian</h5>
                    <p class="text-muted mb-0">Klik pada salah satu jawaban yang menurutmu paling tepat untuk setiap pernyataan di bawah ini.</p>
                </div>
            </div>

            <div class="card form-container shadow-sm p-4">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($instrumen_siswa as $aspek => $indikator_list): ?>
                                    <tr class="bg-aspek">
                                        <td colspan="2" class="fw-bold text-primary p-3 rounded">
                                            <i class="fas fa-layer-group me-2"></i> <?php echo $aspek; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($indikator_list as $index => $indikator):
                                        $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                                        $input_name = "skor_siswa_" . $aspek_key . "_" . $index;
                                    ?>
                                    <tr>
                                        <td width="5%" class="text-center fw-bold" style="vertical-align: top; padding-top: 25px;"><?php echo $no++; ?>.</td>
                                        <td class="pb-5">
                                            <label class="fw-bold text-dark mb-3" style="font-size: 1.1rem;"><?php echo $indikator; ?></label>

                                            <div class="score-container">
                                                <?php
                                                $pilihan = [
                                                    5 => "Sangat Setuju (SS)",
                                                    4 => "Setuju (S)",
                                                    3 => "Ragu-ragu / Cukup (C)",
                                                    2 => "Tidak Setuju (TS)",
                                                    1 => "Sangat Tidak Setuju (STS)"
                                                ];
                                                foreach ($pilihan as $skor_val => $skor_teks): ?>
                                                <label class="score-option">
                                                    <input type="radio" name="<?= $input_name ?>" value="<?= $skor_val ?>" required>
                                                    <span class="score-text"><?= $skor_val ?> - <?= $skor_teks ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="submit_siswa" class="btn btn-primary btn-lg shadow py-3">
                            <i class="fas fa-paper-plane me-2"></i> Kirim Penilaian Materi
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>