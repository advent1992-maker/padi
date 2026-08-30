<?php
// File ini bertujuan untuk mencatat hasil penilaian kelayakan platform MathFaction
// berdasarkan fitur-fitur yang tersedia di Area Kerja Guru/Admin Sekolah.

// Sesuaikan path ini dengan lokasi file koneksi Anda
require_once '../config/koneksi.php';

// --- DEFINISI INSTRUMEN PENILAIAN SPESIFIK PLATFORM MATHFACTION (VERSI REVISI) ---
$instrumen_guru = [
    'A. Aspek Manajemen Konten & Materi' => [
        'Kemudahan dalam mencari dan memfilter materi/soal yang tersedia di bank konten MathFaction.',
        'Fleksibilitas untuk mengedit atau menambahkan konten kustom (materi/soal) oleh guru sendiri.'
    ],
    'B. Analisis Data' => [
        'Kejelasan dan kelengkapan visualisasi laporan kemajuan siswa (Progress Report).',
        'Tersedianya data analisis per indikator/soal untuk identifikasi kesulitan belajar spesifik siswa.',
        'Kemudahan mengunduh (export) data hasil penilaian ke format yang mudah diolah (misalnya, Excel/CSV).'
    ],
    'C. Aspek Antarmuka Pengguna (UI/UX) dan Aksesibilitas' => [
        'Tampilan dashboard Area Kerja Guru terorganisir, intuitif, dan mudah dinavigasi.',
        'Kecepatan loading dan responsivitas fitur-fitur saat diakses di berbagai perangkat (PC, Tablet).'
    ]
];

// Skala Likert 5 poin untuk penilaian
$skala_likert = [
    5 => 'Sangat Baik',
    4 => 'Baik',
    3 => 'Cukup',
    2 => 'Kurang',
    1 => 'Sangat Kurang'
];

$success_message = '';
$error_message = '';

// --- LOGIKA PENYIMPANAN DATA (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- SIMULASI PENGAMBILAN DATA PENGGUNA TERAUTENTIKASI ---
    // Dalam implementasi nyata, nilai-nilai ini diambil dari SESSION atau data pengguna yang login
    $id_user = uniqid('GURU_AUTH_'); // ID unik untuk sesi pengujian
    $nama_guru = 'Guru Terautentikasi (ID: ' . $id_user . ')'; // Placeholder
    $sekolah = 'Sekolah Terautentikasi'; // Placeholder
    $tanggal_uji = date('Y-m-d H:i:s');

    $db_mapel->begin_transaction(); // Mulai transaksi database

    try {
        // Loop melalui semua indikator untuk mengambil dan menyimpan skor
        foreach ($instrumen_guru as $aspek => $indikator_list) {
            foreach ($indikator_list as $indikator) {
                // Gunakan hash MD5 dari indikator sebagai kunci POST yang aman
                $post_key = 'skor_' . md5($indikator);
                $skor = $_POST[$post_key] ?? null;

                // Cek kelengkapan dan validitas skor
                if ($skor === null || !is_numeric($skor) || $skor < 1 || $skor > 5) {
                    // Batalkan jika ada satu poin saja yang belum terisi
                    throw new Exception("Mohon lengkapi semua poin penilaian (Skor 1-5) sebelum mengirim formulir.");
                }

                // 3. Siapkan Query INSERT (Gunakan prepared statement untuk keamanan)
                $query = "INSERT INTO hasil_uji_guru (id_user, nama_guru, sekolah, indikator, skor_penilaian, tanggal_uji)
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db_mapel->prepare($query);

                // Binding parameters: s s s s i s
                $stmt->bind_param("ssssis", $id_user, $nama_guru, $sekolah, $indikator, $skor, $tanggal_uji);

                if (!$stmt->execute()) {
                    throw new Exception("Gagal menyimpan data.");
                }
                $stmt->close();
            }
        }

        // Jika semua berhasil, commit transaksi
        $db_mapel->commit();
        $success_message = "Terima kasih! Penilaian MathFaction Anda berhasil disimpan.";
        // Kosongkan POST agar form tidak terisi data lama
        $_POST = array();

    } catch (Exception $e) {
        $db_mapel->rollback(); // Batalkan jika terjadi error
        $error_message = "Gagal menyimpan data. " . $e->getMessage();
    }
}
$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Platform MathFaction (Guru)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .form-container {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }
        .table th, .table td { vertical-align: middle; }
        .form-check-input[type="radio"] {
            transform: scale(1.3);
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #ffc107; /* Warna kuning warning */
            border-color: #ffc107;
        }
        .table-custom-header {
            background-color: #fff3cd !important; /* light yellow background */
            font-weight: bold;
        }
        .btn-warning-custom {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            transition: all 0.3s;
        }
        .btn-warning-custom:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="form-container">
                <header class="text-center mb-5">
                    <h1 class="text-warning fw-bolder"><i class="fas fa-desktop me-2"></i> Formulir Penilaian Platform MathFaction</h1>
                    <p class="lead text-muted">Instrumen Penilaian Khusus Fitur Area Kerja Guru/Ahli Lapangan.</p>
                    <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a></p>
                </header>

                <!-- Pesan Notifikasi (Sukses/Error) -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Berhasil!</strong> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- Bagian Identitas (Dihapus/Otomatis) -->
                    <h4 class="mb-3 text-secondary border-bottom pb-2"><i class="fas fa-user-check me-1"></i> Status Pengguna</h4>
                    <div class="alert alert-success mb-5">
                        <i class="fas fa-info-circle me-1"></i> Data Identitas Anda telah diambil secara otomatis sebagai pengguna terautentikasi. Silakan lanjutkan ke penilaian.
                    </div>

                    <!-- Keterangan Skala Likert -->
                    <div class="alert alert-info py-3" role="alert">
                        <h6 class="fw-bold text-dark"><i class="fas fa-info-circle me-1"></i> Petunjuk Penilaian</h6>
                        <p class="mb-0">Mohon nilai fungsionalitas MathFaction berdasarkan fitur yang telah Anda coba. Skala penilaian adalah:</p>
                        <ul class="list-inline mt-2 mb-0 fw-bold">
                            <li class="list-inline-item text-success">5: Sangat Baik (Fitur bekerja sempurna dan sangat efisien)</li>
                            <li class="list-inline-item text-primary">4: Baik (Fitur berfungsi baik dengan sedikit kekurangan)</li>
                            <li class="list-inline-item text-secondary">3: Cukup (Fitur bisa digunakan, tetapi butuh perbaikan)</li>
                            <li class="list-inline-item text-warning">2: Kurang (Fitur sulit digunakan atau banyak kendala)</li>
                            <li class="list-inline-item text-danger">1: Sangat Kurang (Fitur tidak berfungsi atau tidak dapat digunakan)</li>
                        </ul>
                    </div>

                    <!-- Bagian Instrumen Penilaian -->
                    <h4 class="mt-5 mb-3 text-secondary border-bottom pb-2"><i class="fas fa-tools me-1"></i> Instrumen Penilaian Fitur MathFaction</h4>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="bg-warning text-dark text-center">
                                <tr>
                                    <th style="width: 5%;">No.</th>
                                    <th style="width: 40%;">Pernyataan / Indikator Penilaian Fitur</th>
                                    <!-- Header Skor Likert -->
                                    <?php foreach ($skala_likert as $skor => $label): ?>
                                        <th style="width: 11%;"><small class="fw-bold"><?php echo $skor; ?></small></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($instrumen_guru as $aspek => $indikator_list): ?>
                                    <!-- Header Aspek -->
                                    <tr>
                                        <td colspan="2" class="table-custom-header border-top border-bottom border-secondary"><?php echo htmlspecialchars($aspek); ?></td>
                                        <td colspan="<?php echo count($skala_likert); ?>" class="table-custom-header"></td>
                                    </tr>
                                    <!-- Baris Indikator -->
                                    <?php foreach ($indikator_list as $indikator): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?>.</td>
                                            <td><?php echo htmlspecialchars($indikator); ?></td>
                                            <?php
                                                // Kunci unik untuk setiap indikator
                                                $post_key = 'skor_' . md5($indikator);
                                                $selected_score = $_POST[$post_key] ?? null;
                                            ?>
                                            <?php foreach ($skala_likert as $skor => $label): ?>
                                                <td class="text-center">
                                                    <div class="form-check d-inline-block">
                                                        <input
                                                            class="form-check-input"
                                                            type="radio"
                                                            name="<?php echo $post_key; ?>"
                                                            id="<?php echo $post_key . $skor; ?>"
                                                            value="<?php echo $skor; ?>"
                                                            required
                                                            <?php echo ($selected_score == $skor) ? 'checked' : ''; ?>
                                                        >
                                                        <label class="form-check-label visually-hidden" for="<?php echo $post_key . $skor; ?>">
                                                            <?php echo $label; ?> (Skor <?php echo $skor; ?>)
                                                        </label>
                                                        <!-- Tidak perlu invalid-feedback, karena sudah ada pengecekan di sisi PHP -->
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-5">
                        <button type="submit" class="btn btn-warning-custom btn-lg px-5 fw-bold shadow-lg">
                            <i class="fas fa-paper-plane me-2"></i> Kirim Penilaian
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>