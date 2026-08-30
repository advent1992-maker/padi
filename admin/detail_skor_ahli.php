<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$nama_ahli = $_GET['nama'] ?? '';
$kode_app = 'PADI_PORTAL';

// 1. Ambil Profil dan Kesimpulan Umum
$q_profil = "SELECT bidang_ahli, instansi, kesimpulan_umum, MAX(tanggal_review) as tgl 
             FROM hasil_validasi 
             WHERE nama_ahli = ? AND kode_aplikasi = ?
             GROUP BY nama_ahli, bidang_ahli, instansi, kesimpulan_umum";
$stmt = $conn->prepare($q_profil);
$stmt->bind_param("ss", $nama_ahli, $kode_app);
$stmt->execute();
$ahli = $stmt->get_result()->fetch_assoc();

// 2. Ambil Rincian Indikator, Skor, dan Catatan
$q_detail = "SELECT aspek, indikator, skor_penilaian, catatan_saran 
             FROM hasil_validasi 
             WHERE nama_ahli = ? AND kode_aplikasi = ? 
             ORDER BY id ASC";
$stmt_d = $conn->prepare($q_detail);
$stmt_d->bind_param("ss", $nama_ahli, $kode_app);
$stmt_d->execute();
$res_detail = $stmt_d->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lampiran Validasi - <?= htmlspecialchars($nama_ahli) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; padding: 20px; font-family: 'Times New Roman', serif; }
        .container-main { width: 100%; margin: 0; padding: 0; }
        .table-bordered th, .table-bordered td { border: 1px solid #000 !important; padding: 10px !important; }
        .header-title { text-align: center; border-bottom: 3px double #000; margin-bottom: 20px; padding-bottom: 10px; }
        .comment-box { border: 1px solid #000; padding: 15px; margin-top: 10px; min-height: 60px; }
        
        /* TAMBAHAN CSS AGAR RATA-RATA TIDAK MUNCUL DI TIAP HALAMAN */
        tfoot { display: table-row-group; } 
        
        @media print { 
            .no-print { display: none; } 
            body { padding: 0; margin: 0; }
            .container-main { width: 100%; }
            @page { size: A4 portrait; margin: 1.5cm; }
        }
    </style>
</head>
<body>

<div class="no-print mb-4 text-end">
    <button onclick="window.print()" class="btn btn-success px-4">Cetak / Simpan PDF</button>
    <a href="rekap_ahli_padi.php" class="btn btn-secondary">Kembali</a>
</div>

<div class="container-main">
    <div class="header-title">
        <h3 class="fw-bold mb-1">LAMPIRAN INSTRUMEN PENILAIAN AHLI</h3>
        <h5 class="text-uppercase">APLIKASI: <?= str_replace('_', ' ', $kode_app) ?></h5>
    </div>

    <table class="table table-borderless mb-4">
        <tr><td width="180">Nama Validator</td><td>: <strong><?= htmlspecialchars($nama_ahli) ?></strong></td></tr>
        <tr><td>Bidang / Instansi</td><td>: <?= htmlspecialchars($ahli['bidang_ahli']) ?> / <?= htmlspecialchars($ahli['instansi']) ?></td></tr>
        <tr><td>Waktu Validasi</td><td>: 12 Desember 2025</td></tr>
    </table>

    <table class="table table-bordered align-middle w-100">
        <thead class="text-center bg-light">
            <tr>
                <th width="5%">No</th>
                <th width="15%">Aspek</th>
                <th width="40%">Indikator Penilaian (Butir Instrumen)</th>
                <th width="8%">Skor</th>
                <th>Catatan / Saran Perbaikan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $total_skor = 0; $jumlah_item = 0;
            while($row = $res_detail->fetch_assoc()): 
                $total_skor += $row['skor_penilaian']; $jumlah_item++;
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center fw-bold small"><?= strtoupper($row['aspek']) ?></td>
                <td><?= htmlspecialchars($row['indikator']) ?></td>
                <td class="text-center fw-bold fs-5"><?= $row['skor_penilaian'] ?></td>
                <td class="small italic text-danger"><?= htmlspecialchars($row['catatan_saran'] ?: '-') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <?php 
                $rata_rata = ($jumlah_item > 0) ? ($total_skor / $jumlah_item) : 0; 
                $persentase = ($rata_rata / 4) * 100;
            ?>
            <tr>
                <td colspan="3" class="text-end">TOTAL SKOR</td>
                <td class="text-center"><?= $total_skor ?></td>
                <td rowspan="2" class="text-center align-middle bg-white">
                    <span class="fs-6">PRESENTASE KELAYAKAN:</span><br>
                    <span class="fs-4 text-success"><?= number_format($persentase, 1) ?>%</span>
                </td>
            </tr>
            <tr>
                <td colspan="3" class="text-end">RATA-RATA SKOR (Skala 4)</td>
                <td class="text-center text-primary fs-5"><?= number_format($rata_rata, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-4">
        <strong>Kesimpulan / Saran Umum Validator:</strong>
        <div class="comment-box">
            <em>"<?= htmlspecialchars($ahli['kesimpulan_umum'] ?: 'Layak digunakan sesuai revisi.') ?>"</em>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-8"></div>
        <div class="col-4 text-center">
            <p>Martapura, 12 Desember 2025<br>Validator</p>
            <br><br><br>
            <p><strong>( <?= htmlspecialchars($nama_ahli) ?> )</strong></p>
        </div>
    </div>
</div>

</body>
</html>