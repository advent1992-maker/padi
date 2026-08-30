<?php
// Pastikan path ke file koneksi Anda sudah benar
require_once '../config/koneksi.php';

// --- 1. LOGIKA DATA REKAP GURU ---

// Definisikan Instrumen Penilaian Guru/Ahli (DISAMAKAN DENGAN form_uji_guru.php)
// Revisi: Menggunakan instrumen spesifik MathFaction (Manajemen Konten, Analisis Data, UI/UX)
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

$rata_rata_per_indikator = [];
$no_indikator = 1;

/**
 * Menentukan Kategori Penilaian berdasarkan Skor Rata-rata (Skala 5)
 * Skala Kriteria: >4.5=Sangat Baik, >3.5=Baik, >2.5=Cukup, >1.5=Kurang
 * @param float $skor Nilai rata-rata skor
 * @return string Kategori (Sangat Baik, Baik, Cukup, dll.)
 */
function getKategori($skor) {
    if ($skor >= 4.5) return "Sangat Baik";
    if ($skor >= 3.5) return "Baik";
    if ($skor >= 2.5) return "Cukup";
    if ($skor >= 1.5) return "Kurang";
    return "Sangat Kurang";
}

// HITUNG RATA-RATA SKOR PER INDIKATOR
foreach ($instrumen_guru as $aspek => $indikator_list) {
    foreach ($indikator_list as $indikator) {
        // Query disesuaikan untuk mengambil data dari tabel guru/ahli
        $query_rata_indikator = "SELECT AVG(skor_penilaian) as rata_rata FROM hasil_uji_guru WHERE indikator = ?";

        $stmt = $db_mapel->prepare($query_rata_indikator);
        $stmt->bind_param("s", $indikator);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $skor = $data['rata_rata'] ?? 0;

        $rata_rata_per_indikator[] = [
            'no' => $no_indikator++,
            'aspek' => $aspek,
            'indikator' => $indikator,
            'rata_rata' => $skor > 0 ? number_format($skor, 2) : '0.00',
            'kategori' => $skor > 0 ? getKategori($skor) : '-'
        ];
        $stmt->close();
    }
}

// HITUNG RATA-RATA SKOR KESELURUHAN & TOTAL RESPONDEN
// Menggunakan id_user untuk menghitung responden unik (sesuai rekomendasi perbaikan tabel)
$query_rata_global = "SELECT
                        AVG(skor_penilaian) as rata_rata_skor,
                        COUNT(DISTINCT id_user) as total_responden_unik,
                        COUNT(id) as total_entri
                      FROM hasil_uji_guru";

$data_rata_global = $db_mapel->query($query_rata_global)->fetch_assoc();
$rata_rata_skor_global = $data_rata_global['rata_rata_skor'] ?? 0;
$total_entri = $data_rata_global['total_entri'] ?? 0;
$total_responden_unik = $data_rata_global['total_responden_unik'] ?? 0;
// Menghitung kategori kelayakan untuk skor global
$kategori_kelayakan = getKategori($rata_rata_skor_global);

// --- AKHIR LOGIKA DATA ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Uji Coba Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .rekap-container { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .category-text {
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <header class="text-center mb-4">
                <h1 class="text-primary"><i class="fas fa-chalkboard-teacher me-2"></i> Rekapitulasi Uji Coba Guru/Ahli</h1>
                <p class="lead">Laporan hasil penilaian kelayakan fitur platform MathFaction (Area Kerja Guru).</p>
                <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin</a></p>
            </header>

            <div class="rekap-container mb-5">

                <h3 class="text-primary mb-3">Ringkasan Global</h3>
                <div class="row mb-4">
                    <!-- Tambah Total Entri Penilaian -->
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Total Entri Penilaian</h5>
                            <p class="fs-2 fw-bold text-warning"><?php echo $total_entri; ?></p>
                        </div>
                    </div>
                    <!-- Ganti label Total Guru menjadi Total Responden Unik -->
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Total Responden Unik</h5>
                            <p class="fs-2 fw-bold text-info"><?php echo $total_responden_unik; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Rating Global</h5>
                            <p class="fs-2 fw-bold text-primary mb-1">
                                <?php echo number_format($rata_rata_skor_global, 2); ?> / 5.00
                            </p>
                            <p class="category-text text-success">
                                (<?php echo htmlspecialchars($kategori_kelayakan); ?>)
                            </p>
                        </div>
                    </div>
                </div>

                <h3 class="text-primary mb-3 border-top pt-4">Rata-rata Penilaian Per Indikator</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-primary text-white text-center">
                            <tr>
                                <th style="width: 5%;">No.</th>
                                <th style="width: 55%;">Pernyataan / Indikator</th>
                                <th style="width: 20%;">Rata-rata Skor</th>
                                <th style="width: 20%;">Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $current_aspek = ''; ?>
                            <?php foreach ($rata_rata_per_indikator as $item): ?>
                                <?php if ($current_aspek != $item['aspek']): $current_aspek = $item['aspek']; ?>
                                    <tr>
                                        <!-- Ubah warna header aspek menjadi primary agar seragam dengan thead -->
                                        <td colspan="4" class="bg-primary text-white fw-bold"><?php echo htmlspecialchars($item['aspek']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-center"><?php echo $item['no']; ?>.</td>
                                    <td><?php echo htmlspecialchars($item['indikator']); ?></td>
                                    <td class="text-center fw-bold"><?php echo $item['rata_rata']; ?></td>
                                    <td class="text-center"><?php echo $item['kategori']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Catatan: Bagian "Catatan dan Saran Umum Guru" dihapus karena kolom 'saran_umum' tidak lagi dikumpulkan oleh form terbaru (form_uji_guru.php) -->

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>