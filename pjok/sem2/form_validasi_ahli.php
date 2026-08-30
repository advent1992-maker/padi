<?php
// Pastikan path koneksi ke database sudah benar
// Ganti 'config/koneksi.php' jika lokasi file koneksi Anda berbeda
require_once 'config/koneksi.php';

// Definisikan Instrumen/Butir Penilaian Ahli (Sesuai dengan Form)
$instrumen = [
    'A. Aspek Kelayakan Media' => [
        'Ketersediaan fitur interaktif yang menunjang pembelajaran.',
        'Kualitas tampilan visual (warna, font, tata letak) yang menarik dan jelas.',
        'Kemudahan navigasi antar halaman/menu (user-friendly).',
        'Stabilitas dan kinerja media saat diakses.'
    ],
    'B. Aspek Kelayakan Materi' => [
        'Kesesuaian materi dengan Kurikulum dan Kompetensi Dasar (KD) yang ditargetkan.',
        'Ketepatan dan kebenaran konsep-konsep matematika yang disajikan.',
        'Adanya contoh dan latihan soal yang relevan dengan kehidupan nyata (kontekstual).'
    ],
    'C. Aspek Kebahasaan' => [
        'Penggunaan bahasa yang baku dan mudah dipahami oleh siswa.',
        'Ketepatan ejaan, tata bahasa, dan istilah matematika.'
    ]
];

// Inisialisasi variabel untuk pesan feedback
$pesan_status = '';

// --- FUNGSI PROSES SUBMIT FORM ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Ambil Data Ahli & Kesimpulan
    $nama_ahli = $db_mapel->real_escape_string($_POST['nama_ahli'] ?? '');
    $bidang_ahli = $db_mapel->real_escape_string($_POST['bidang_ahli'] ?? '');
    $instansi = $db_mapel->real_escape_string($_POST['instansi'] ?? '');
    $kesimpulan_ahli = $db_mapel->real_escape_string($_POST['kesimpulan_ahli'] ?? ''); // Data Kesimpulan Akhir

    // Validasi dasar
    if (empty($nama_ahli) || empty($bidang_ahli)) {
        $pesan_status = '<div class="alert alert-danger">Nama Ahli dan Bidang Ahli wajib diisi.</div>';
    } else {

        try {
            // Mulai Transaksi Database
            $db_mapel->begin_transaction();

            // 2. Simpan Hasil Penilaian Butir per Butir ke Tabel hasil_validasi
            foreach ($instrumen as $aspek => $indikator_list) {
                foreach ($indikator_list as $index => $indikator) {

                    // Format nama input untuk skor dan catatan
                    $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                    $input_skor_name = "skor_" . $aspek_key . "_" . $index;
                    $input_catatan_name = "catatan_" . $aspek_key . "_" . $index;

                    $skor = $_POST[$input_skor_name] ?? 0;
                    $catatan = $db_mapel->real_escape_string($_POST[$input_catatan_name] ?? '');

                    if ($skor > 0) { // Hanya simpan jika skor > 0 (telah dinilai)

                        // KOREKSI FINAL QUERY: Memasukkan 8 kolom
                        $stmt_hasil = $db_mapel->prepare("
                            INSERT INTO hasil_validasi
                            (nama_ahli, bidang_ahli, instansi, aspek, indikator, skor_penilaian, catatan_saran, kesimpulan_umum)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        // Binding parameters: sssssiss (5 string, 1 integer skor, 2 string catatan/kesimpulan)
                        // Pastikan urutan dan jumlah (8) variabel cocok dengan 8 placeholder (?) dan 8 tipe data (sssssiss)
                        $stmt_hasil->bind_param("sssssiss",
                            $nama_ahli,        // s (1)
                            $bidang_ahli,      // s (2)
                            $instansi,         // s (3)
                            $aspek,            // s (4)
                            $indikator,        // s (5)
                            $skor,             // i (6)
                            $catatan,          // s (7)
                            $kesimpulan_ahli   // s (8)
                        );

                        $stmt_hasil->execute();
                        $stmt_hasil->close();
                    }
                }
            }

            // Commit transaksi jika semua berhasil
            $db_mapel->commit();
            $pesan_status = '<div class="alert alert-success">Terima kasih! Hasil validasi Anda telah berhasil direkam.</div>';

            // Bersihkan variabel POST agar form tidak terisi lagi setelah submit
            $_POST = array();

        } catch (Exception $e) {
            // Rollback jika terjadi kesalahan
            $db_mapel->rollback();
            $pesan_status = '<div class="alert alert-danger">Terjadi kesalahan saat menyimpan data: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Validasi Ahli Media Pembelajaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .rating-option { width: 50px; text-align: center; }
        .rating-row th, .rating-row td { vertical-align: middle; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <header class="text-center mb-4">
                <h1 class="text-primary"><i class="fas fa-flask me-2"></i> Validasi Ahli Media Mathfiction</h1>
                <p class="lead">Mohon Bapak/Ibu Ahli memberikan penilaian berdasarkan instrumen yang tersedia.</p>
            </header>

            <?php echo $pesan_status; ?>

            <div class="form-container">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">

                    <h4 class="mb-3 text-secondary"><i class="fas fa-user-tie me-2"></i> Data Diri Validator</h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="nama_ahli" class="form-label">Nama Lengkap Ahli <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_ahli" name="nama_ahli" required value="<?php echo $_POST['nama_ahli'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bidang_ahli" class="form-label">Bidang Keahlian <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bidang_ahli" name="bidang_ahli" required value="<?php echo $_POST['bidang_ahli'] ?? ''; ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="instansi" class="form-label">Instansi/Lembaga</label>
                            <input type="text" class="form-control" id="instansi" name="instansi" value="<?php echo $_POST['instansi'] ?? ''; ?>">
                        </div>
                    </div>

                    <h4 class="mb-3 text-secondary border-top pt-3"><i class="fas fa-clipboard-list me-2"></i> Penilaian Kelayakan Media</h4>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light text-center">
                                <tr>
                                    <th rowspan="2" style="width: 5%;">No.</th>
                                    <th rowspan="2" style="width: 35%;">Indikator Penilaian</th>
                                    <th colspan="5" style="width: 30%;">Skor Penilaian (1-5)</th>
                                    <th rowspan="2" style="width: 30%;">Catatan/Saran Perbaikan</th>
                                </tr>
                                <tr>
                                    <th class="rating-option">5 (SB)</th>
                                    <th class="rating-option">4 (B)</th>
                                    <th class="rating-option">3 (C)</th>
                                    <th class="rating-option">2 (K)</th>
                                    <th class="rating-option">1 (SK)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($instrumen as $aspek => $indikator_list): ?>
                                    <tr>
                                        <td colspan="8" class="bg-info text-white fw-bold"><?php echo htmlspecialchars($aspek); ?></td>
                                    </tr>
                                    <?php foreach ($indikator_list as $index => $indikator): ?>
                                        <?php
                                            // Menghilangkan karakter non-alfanumerik untuk nama input
                                            $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                                            $input_skor_name = "skor_" . $aspek_key . "_" . $index;
                                            $input_catatan_name = "catatan_" . $aspek_key . "_" . $index;
                                            $current_score = $_POST[$input_skor_name] ?? '';
                                        ?>
                                        <tr class="rating-row">
                                            <td><?php echo $no++; ?>.</td>
                                            <td><?php echo htmlspecialchars($indikator); ?></td>

                                            <?php for ($skor = 5; $skor >= 1; $skor--): ?>
                                                <td class="text-center">
                                                    <input type="radio"
                                                           name="<?php echo $input_skor_name; ?>"
                                                           value="<?php echo $skor; ?>"
                                                           required
                                                           <?php echo ($current_score == $skor) ? 'checked' : ''; ?>>
                                                </td>
                                            <?php endfor; ?>

                                            <td>
                                                <textarea name="<?php echo $input_catatan_name; ?>" class="form-control form-control-sm" rows="1" placeholder="Catatan/Saran"><?php echo $_POST[$input_catatan_name] ?? ''; ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mb-3 mt-5 text-secondary border-top pt-3"><i class="fas fa-edit me-2"></i> Kesimpulan dan Saran Umum</h4>
                    <div class="mb-3">
                        <label for="kesimpulan_ahli" class="form-label">Catatan dan Kesimpulan Umum Terhadap Media (Opsional)</label>
                        <textarea class="form-control" id="kesimpulan_ahli" name="kesimpulan_ahli" rows="5" placeholder="Tuliskan kesimpulan kelayakan dan saran umum Anda di sini..."><?php echo $_POST['kesimpulan_ahli'] ?? ''; ?></textarea>
                    </div>
                    <p class="mt-3">Keterangan Skor: 5=Sangat Baik (SB), 4=Baik (B), 3=Cukup (C), 2=Kurang (K), 1=Sangat Kurang (SK)</p>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i> Kirim Hasil Validasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>