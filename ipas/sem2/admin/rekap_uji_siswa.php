<?php
// Pastikan path ke file koneksi Anda sudah benar
require_once '../config/koneksi.php';

// --- 1. LOGIKA DATA REKAP SISWA ---

// Definisikan ulang Instrumen Siswa (HARUS SAMA DENGAN FORMULIR)
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
foreach ($instrumen_siswa as $aspek => $indikator_list) {
    foreach ($indikator_list as $indikator) {
        $query_rata_indikator = "SELECT AVG(skor_penilaian) as rata_rata FROM hasil_uji_siswa WHERE indikator = ?";

        // Catatan: Pastikan kolom 'indikator' sudah menyimpan string pernyataan
        // Jika indikator masih 0, cek kembali perbaikan binding parameter sebelumnya.

        $stmt = $conn->prepare($query_rata_indikator);
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
// Catatan: Asumsi COUNT(DISTINCT id_user) digunakan untuk Total Responden Unik
$query_rata_global = "SELECT
                        AVG(skor_penilaian) as rata_rata_skor,
                        COUNT(DISTINCT id_user) as total_responden_unik,
                        COUNT(id) as total_entri
                      FROM hasil_uji_siswa";

$data_rata_global = $conn->query($query_rata_global)->fetch_assoc();
$rata_rata_skor_global = $data_rata_global['rata_rata_skor'] ?? 0;
$total_entri = $data_rata_global['total_entri'] ?? 0;
// Mengganti total_kelas dengan total_responden_unik agar lebih akurat
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
    <title>Rekapitulasi Uji Coba Siswa</title>
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
                <h1 class="text-warning"><i class="fas fa-poll me-2"></i> Rekapitulasi Uji Coba Siswa</h1>
                <p class="lead">Laporan hasil penilaian kemenarikan dan kegunaan media dari sisi siswa.</p>
                <p><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin</a></p>
            </header>

            <div class="rekap-container mb-5">

                <h3 class="text-primary mb-3">Ringkasan Global</h3>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Total Entri Penilaian</h5>
                            <p class="fs-2 fw-bold text-warning"><?php echo $total_entri; ?></p>
                        </div>
                    </div>
                    <!-- Mengganti Total Kelas dengan Total Responden Unik -->
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Total Responden</h5>
                            <p class="fs-2 fw-bold text-info"><?php echo $total_responden_unik; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center bg-light p-3">
                            <h5>Rating Global</h5>
                            <p class="fs-2 fw-bold text-primary mb-1">
                                <?php echo number_format($rata_rata_skor_global, 2); ?> / 5.00
                            </p>
                            <!-- KODE PERBAIKAN: Menambahkan Kategori Global -->
                            <p class="category-text text-success">
                                (<?php echo htmlspecialchars($kategori_kelayakan); ?>)
                            </p>
                            <!-- AKHIR KODE PERBAIKAN -->
                        </div>
                    </div>
                </div>

                <h3 class="text-primary mb-3 border-top pt-4">Rata-rata Penilaian Per Indikator</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-warning text-dark text-center">
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
                                        <td colspan="4" class="bg-warning text-dark fw-bold"><?php echo htmlspecialchars($item['aspek']); ?></td>
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
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>