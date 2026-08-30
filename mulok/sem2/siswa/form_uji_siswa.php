<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengamanan Role
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? 0;
$sekolah_default = 'Mathfiction School'; // Default jika kolom sekolah tidak ada di DB User

// Definisikan Instrumen/Butir Penilaian Siswa
$instrumen_siswa = [
    'A. Aspek Kemenarikan Media' => [
        'Tampilan media ini menarik perhatian saya.',
        'Media ini membuat belajar matematika menjadi lebih menyenangkan.',
        'Saya senang mencoba fitur-fitur yang ada di dalam media.'
    ],
    'B. Aspek Kemudahan Penggunaan (Usability)' => [
        'Saya mudah memahami cara menggunakan media ini.',
        'Tombol dan menu di media ini mudah ditemukan.',
        'Media ini membantu saya memahami materi pelajaran.'
    ]
];

$pesan_status = '';
$sudah_submit_ulasan = false;

// --- KODE PERBAIKAN: CEK APAKAH SISWA SUDAH SUBMIT ---
if (isset($user_id)) {
    $query_check = "SELECT COUNT(*) FROM hasil_uji_siswa WHERE id_user = ?";
    $stmt_check = $db_mapel->prepare($query_check);
    // Asumsi id_user adalah integer
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count_submissions);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_submissions > 0) {
        $sudah_submit_ulasan = true;
        $pesan_status = '<div class="alert alert-info text-center"><i class="fas fa-check-circle me-2"></i> Anda telah menyelesaikan pengujian media ini. Terima kasih atas kontribusi Anda!</div>';
    }
}
// ----------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_siswa']) && !$sudah_submit_ulasan) {

    // Data Responden diambil dari Sesi
    $kelas = $level_kelas;
    $tanggal_uji = date("Y-m-d");

    if (empty($kelas) || empty($user_id)) {
        $pesan_status = '<div class="alert alert-danger">Error: Data Kelas atau User ID tidak ditemukan di sesi Anda.</div>';
    } else {

        try {
            $db_mapel->begin_transaction();
            // --- KODE PERBAIKAN: TAMBAH id_user KE QUERY INSERT ---
            // Kolom INSERT: (id_user, kelas, sekolah, indikator, skor_penilaian, tanggal_uji)
            $query = "INSERT INTO hasil_uji_siswa (id_user, kelas, sekolah, indikator, skor_penilaian, tanggal_uji) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_insert_siswa = $db_mapel->prepare($query);
            // ----------------------------------------------------

            $semua_dinilai = true;
            $data_tersimpan = false;

            foreach ($instrumen_siswa as $aspek => $indikator_list) {
                foreach ($indikator_list as $index => $indikator) {

                    $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                    $input_skor_name = "skor_siswa_" . $aspek_key . "_" . $index;

                    $skor = $_POST[$input_skor_name] ?? 0;

                    if ($skor > 0) {
                        // --- KODE PERBAIKAN: TAMBAH $user_id KE BIND_PARAM ---
                        // Binding parameters: i s s s i s
                        $stmt_insert_siswa->bind_param("isssis",
                            $user_id, // Tambahan
                            $kelas,
                            $sekolah_default,
                            $indikator,
                            $skor,
                            $tanggal_uji
                        );
                        // ----------------------------------------------------

                        $stmt_insert_siswa->execute();
                        $data_tersimpan = true;
                    } else {
                        $semua_dinilai = false;
                    }
                }
            }
            $stmt_insert_siswa->close();

            if ($semua_dinilai && $data_tersimpan) {
                $db_mapel->commit();
                $pesan_status = '<div class="alert alert-success">Terima kasih! Ulasan media Anda telah berhasil direkam.</div>';
                $sudah_submit_ulasan = true;
                // Tidak perlu redirect, cukup set flag $sudah_submit_ulasan = true
            } else {
                 $db_mapel->rollback();
                 $pesan_status = '<div class="alert alert-warning">Gagal menyimpan. Mohon pastikan semua indikator sudah dinilai (Skor 1-5).</div>';
            }

        } catch (Exception $e) {
            $db_mapel->rollback();
            $pesan_status = '<div class="alert alert-danger">Terjadi kesalahan saat menyimpan data: ' . $e->getMessage() . '</div>';
        }
    }
}

// Hapus blok pengecekan status dari redirect, karena sudah ditangani di atas.
// if (isset($_GET['status']) && $_GET['status'] == 'success') { ... }

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Uji Coba Siswa | B.Komering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <header class="text-center mb-4">
                <h1 class="text-warning"><i class="fas fa-user-graduate me-2"></i> Uji Coba Media Oleh Siswa</h1>
                <p class="lead">Berikan penilaian Anda secara jujur mengenai media yang telah digunakan.</p>
                <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a></p>
            </header>

            <?php echo $pesan_status; ?>

            <div class="form-container">
                <?php if (!$sudah_submit_ulasan): ?>
                    <form method="POST">
                        <h4 class="mb-3 text-secondary"><i class="fas fa-school me-2"></i> Data Responden</h4>
                        <p class="text-muted">Kelas: <strong><?php echo htmlspecialchars($level_kelas); ?></strong>, Sekolah: <strong><?php echo htmlspecialchars($sekolah_default); ?></strong></p>

                        <h4 class="mb-3 mt-4 text-secondary border-top pt-3"><i class="fas fa-thumbs-up me-2"></i> Instrumen Penilaian (Skala Likert)</h4>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="bg-light text-center">
                                    <tr>
                                        <th rowspan="2" style="width: 5%;">No.</th>
                                        <th rowspan="2" style="width: 45%;">Pernyataan / Indikator</th>
                                        <th colspan="5" style="width: 50%;">Skor Penilaian (1-5)</th>
                                    </tr>
                                    <tr>
                                        <th style="width: 10%;">5 (SS)</th>
                                        <th style="width: 10%;">4 (S)</th>
                                        <th style="width: 10%;">3 (C)</th>
                                        <th style="width: 10%;">2 (TS)</th>
                                        <th style="width: 10%;">1 (STS)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; ?>
                                    <?php foreach ($instrumen_siswa as $aspek => $indikator_list): ?>
                                        <tr>
                                            <td colspan="7" class="bg-warning bg-opacity-25 text-dark fw-bold"><?php echo htmlspecialchars($aspek); ?></td>
                                        </tr>
                                        <?php foreach ($indikator_list as $index => $indikator): ?>
                                            <?php
                                                $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                                                $input_skor_name = "skor_siswa_" . $aspek_key . "_" . $index;
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?>.</td>
                                                <td><?php echo htmlspecialchars($indikator); ?></td>

                                                <?php for ($skor = 5; $skor >= 1; $skor--): ?>
                                                    <td class="text-center">
                                                        <input type="radio"
                                                                name="<?php echo $input_skor_name; ?>"
                                                                value="<?php echo $skor; ?>"
                                                                required>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="mt-3">Keterangan: 5=Sangat Setuju (SS), 1=Sangat Tidak Setuju (STS)</p>

                        <div class="d-grid mt-4">
                            <button type="submit" name="submit_siswa" class="btn btn-warning btn-lg text-white"><i class="fas fa-save me-2"></i> Kirim Ulasan Saya</button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>