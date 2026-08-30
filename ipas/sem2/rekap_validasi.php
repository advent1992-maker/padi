<?php
// File: rekap_validasi_ahli.php

// Koneksi ke database
require_once 'config/koneksi.php'; // Sesuaikan path ini jika perlu

// Definisikan Instrumen/Butir Penilaian Ahli (Harus sama dengan di form_validasi_ahli.php)
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

// --- FUNGSI PENGHITUNGAN REKAPITULASI ---

// 1. Ambil semua data review unik (berdasarkan nama ahli, karena semua baris dari 1 ahli memiliki data diri yang sama)
$sql_ahli = "SELECT DISTINCT nama_ahli, bidang_ahli, instansi, kesimpulan_umum FROM hasil_validasi ORDER BY tanggal_review ASC";
$result_ahli = $db_mapel->query($sql_ahli);

// 2. Ambil data rata-rata penilaian per indikator
$sql_rekap = "
    SELECT
        aspek,
        indikator,
        AVG(skor_penilaian) as rata_skor,
        COUNT(skor_penilaian) as total_responden
    FROM hasil_validasi
    GROUP BY aspek, indikator
    ORDER BY aspek, indikator
";
$result_rekap = $db_mapel->query($sql_rekap);

$rekap_data = [];
$total_skor_semua_indikator = 0;
$total_jumlah_indikator = 0;
$total_responden_max = 0;

if ($result_rekap->num_rows > 0) {
    while ($row = $result_rekap->fetch_assoc()) {
        $rekap_data[$row['aspek']][$row['indikator']] = $row;
        $total_skor_semua_indikator += $row['rata_skor'];
        $total_jumlah_indikator++;
        if ($row['total_responden'] > $total_responden_max) {
            $total_responden_max = $row['total_responden'];
        }
    }
}

// 3. Hitung Rekapitulasi Akhir
$rata_rata_keseluruhan = ($total_jumlah_indikator > 0) ? ($total_skor_semua_indikator / $total_jumlah_indikator) : 0;
$persentase_kelayakan = ($rata_rata_keseluruhan / 5) * 100;

// Konversi Rata-rata Keseluruhan ke Kategori Kelayakan (Skala 1-5)
function getKategori($rata) {
    if ($rata >= 4.21) return "Sangat Baik";
    if ($rata >= 3.41) return "Baik";
    if ($rata >= 2.61) return "Cukup";
    if ($rata >= 1.81) return "Kurang";
    return "Sangat Kurang";
}

$kategori_kelayakan = getKategori($rata_rata_keseluruhan);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Hasil Validasi Ahli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="mb-4 text-end">
    <a href="export_pdf_validasi.php" target="_blank" class="btn btn-danger btn-lg">
        <i class="fas fa-file-pdf me-2"></i> Download Laporan PDF
    </a>
</div>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <h1 class="text-center text-success mb-4"><i class="fas fa-chart-bar me-2"></i> Rekapitulasi Validasi Media Mathfiction</h1>

            <div class="card mb-5 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4><i class="fas fa-medal me-2"></i> Ringkasan Kelayakan</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h5 class="text-muted">Jumlah Ahli</h5>
                            <h2 class="text-primary"><?php echo $total_responden_max; ?></h2>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-muted">Rata-rata Skor Keseluruhan (Maks. 5)</h5>
                            <h2 class="<?php echo ($rata_rata_keseluruhan >= 4.0) ? 'text-success' : (($rata_rata_keseluruhan >= 3.0) ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo number_format($rata_rata_keseluruhan, 2); ?>
                            </h2>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-muted">Kategori Kelayakan</h5>
                            <h2 class="text-info"><?php echo $kategori_kelayakan; ?></h2>
                        </div>
                    </div>
                    <hr>
                    <p class="text-center small text-muted">Persentase Kelayakan Media: **<?php echo number_format($persentase_kelayakan, 2); ?>%**</p>
                </div>
            </div>

            <h3 class="mb-3 text-secondary"><i class="fas fa-list-alt me-2"></i> Rata-rata Penilaian Per Indikator</h3>
            <div class="table-responsive mb-5">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 55%;">Indikator Penilaian</th>
                            <th style="width: 20%;" class="text-center">Rata-rata Skor</th>
                            <th style="width: 20%;" class="text-center">Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no_ind = 1; ?>
                        <?php foreach ($instrumen as $aspek => $indikator_list): ?>
                            <tr>
                                <td colspan="4" class="bg-primary text-white fw-bold"><?php echo htmlspecialchars($aspek); ?></td>
                            </tr>
                            <?php foreach ($indikator_list as $indikator): ?>
                                <?php $data = $rekap_data[$aspek][$indikator] ?? null; ?>
                                <tr>
                                    <td><?php echo $no_ind++; ?>.</td>
                                    <td><?php echo htmlspecialchars($indikator); ?></td>
                                    <td class="text-center">
                                        <?php if ($data): ?>
                                            <strong><?php echo number_format($data['rata_skor'], 2); ?></strong>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($data): ?>
                                            <span class="badge bg-secondary"><?php echo getKategori($data['rata_skor']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="mb-3 text-secondary"><i class="fas fa-comments me-2"></i> Saran dan Kesimpulan Umum Ahli</h3>
            <div class="row">
                <?php
                $result_ahli->data_seek(0); // Reset pointer
                if ($result_ahli->num_rows > 0):
                ?>
                    <?php while ($ahli = $result_ahli->fetch_assoc()): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-warning shadow-sm">
                                <div class="card-header bg-warning text-dark fw-bold">
                                    <?php echo htmlspecialchars($ahli['nama_ahli']); ?>
                                    <small class="badge bg-dark float-end"><?php echo htmlspecialchars($ahli['bidang_ahli']); ?></small>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-1">Kesimpulan Umum:</p>
                                    <p><?php echo nl2br(htmlspecialchars($ahli['kesimpulan_umum'] ?? 'Tidak ada kesimpulan umum.')); ?></p>
                                    <hr>
                                    <p class="small text-muted mb-1">Semua Catatan (Saran Spesifik):</p>
                                    <?php
                                        // Ambil semua catatan spesifik dari ahli ini
                                        $sql_catatan = "SELECT aspek, indikator, catatan_saran FROM hasil_validasi WHERE nama_ahli = ? AND catatan_saran IS NOT NULL AND catatan_saran != ''";
                                        $stmt_catatan = $db_mapel->prepare($sql_catatan);
                                        $stmt_catatan->bind_param("s", $ahli['nama_ahli']);
                                        $stmt_catatan->execute();
                                        $result_catatan = $stmt_catatan->get_result();

                                        if ($result_catatan->num_rows > 0) {
                                            echo '<ul>';
                                            while ($catatan_row = $result_catatan->fetch_assoc()) {
                                                echo '<li><small><strong>['. substr(htmlspecialchars($catatan_row['aspek']), 0, 1) .']</strong> '. htmlspecialchars($catatan_row['indikator']) . ': '. nl2br(htmlspecialchars($catatan_row['catatan_saran'])) .'</small></li>';
                                            }
                                            echo '</ul>';
                                        } else {
                                            echo '<p class="text-center small text-muted">Tidak ada catatan spesifik dari ahli ini.</p>';
                                        }
                                        $stmt_catatan->close();
                                    ?>
                                </div>
                                <div class="card-footer small text-muted">
                                    Instansi: <?php echo htmlspecialchars($ahli['instansi']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center">Belum ada data validasi ahli yang masuk.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>