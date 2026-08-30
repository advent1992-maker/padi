<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$kode_app = 'PADI_PORTAL';

/**
 * Fungsi Kategori Kelayakan Berdasarkan Persentase (Standard R&D)
 */
function getKategoriPersentase($persentase) {
    if ($persentase >= 81) return ["Sangat Layak", "success", "Tanpa Revisi"];
    if ($persentase >= 61) return ["Layak", "primary", "Revisi Kecil"];
    if ($persentase >= 41) return ["Cukup Layak", "warning", "Revisi Besar"];
    return ["Tidak Layak", "danger", "Belum Dapat Digunakan"];
}

// 1. HITUNG REKAPITULASI PER KELOMPOK AHLI (Fungsi Helper)
function getRekapByBidang($conn, $bidang, $kode_app) {
    $q = "SELECT aspek, AVG(skor_penilaian) as rata_aspek 
          FROM hasil_validasi 
          WHERE bidang_ahli = ? AND kode_aplikasi = ? 
          GROUP BY aspek";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("ss", $bidang, $kode_app);
    $stmt->execute();
    return $stmt->get_result();
}

// 2. HITUNG TOTAL KESELURUHAN (GABUNGAN SEMUA AHLI)
$q_total = "SELECT AVG(skor_penilaian) as skor_akhir FROM hasil_validasi WHERE kode_aplikasi = ?";
$stmt_total = $conn->prepare($q_total);
$stmt_total->bind_param("s", $kode_app);
$stmt_total->execute();
$res_total = $stmt_total->get_result()->fetch_assoc();
$skor_akhir = $res_total['skor_akhir'] ?? 0;
$persentase_total = round(($skor_akhir / 4) * 100);
$status_total = getKategoriPersentase($persentase_total);

// 3. HITUNG RATA-RATA SPESIFIK PER BIDANG (UNTUK WIDGET)
$q_pb = "SELECT bidang_ahli, AVG(skor_penilaian) as rata_skor 
         FROM hasil_validasi 
         WHERE kode_aplikasi = ? 
         GROUP BY bidang_ahli";
$stmt_pb = $conn->prepare($q_pb);
$stmt_pb->bind_param("s", $kode_app);
$stmt_pb->execute();
$res_pb = $stmt_pb->get_result();

$skor_bidang = [];
while($row_pb = $res_pb->fetch_assoc()){
    $skor_bidang[$row_pb['bidang_ahli']] = [
        'skor' => number_format($row_pb['rata_skor'], 2),
        'persen' => round(($row_pb['rata_skor'] / 4) * 100)
    ];
}

// 4. AMBIL RIWAYAT VALIDATOR (Disesuaikan agar bisa memanggil detail)
$q_riwayat = "SELECT id, nama_ahli, bidang_ahli, instansi, kesimpulan_umum, tanggal_review
              FROM hasil_validasi 
              WHERE kode_aplikasi = ? 
              GROUP BY nama_ahli, bidang_ahli, instansi, kesimpulan_umum 
              ORDER BY tanggal_review DESC";
$stmt_riwayat = $conn->prepare($q_riwayat);
$stmt_riwayat->bind_param("s", $kode_app);
$stmt_riwayat->execute();
$res_riwayat = $stmt_riwayat->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Rekap Validasi | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .nav-pills .nav-link.active { background-color: #0f172a; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .progress { height: 10px; border-radius: 20px; }
        .header-gradient { background: linear-gradient(135deg, #1e293b, #334155); color: white; padding: 30px; border-radius: 15px; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; border-left: 5px solid #0f172a; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="header-gradient mb-4 d-flex justify-content-between align-items-center shadow">
        <div>
            <h2 class="fw-bold mb-0">Dashboard Rekapitulasi Ahli</h2>
            <p class="mb-0 opacity-75">Hasil Validasi Aplikasi PADI (Sekolah Dasar)</p>
        </div>
        <div class="text-end">
            <h6 class="text-uppercase small opacity-75">Total Kelayakan</h6>
            <h1 class="display-5 fw-bold mb-0"><?= $persentase_total ?>%</h1>
            <span class="badge bg-<?= $status_total[1] ?> px-3 py-2 mt-1"><?= $status_total[0] ?></span>
        </div>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <div class="stat-card shadow-sm border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">Rata-rata Ahli Materi</h6>
                        <h2 class="fw-bold text-primary mb-0"><?= $skor_bidang['Ahli Materi']['skor'] ?? '0.00' ?> <small class="fs-6 text-muted">/ 4.00</small></h2>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary fs-6"><?= $skor_bidang['Ahli Materi']['persen'] ?? '0' ?>%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card shadow-sm border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">Rata-rata Ahli Media</h6>
                        <h2 class="fw-bold text-success mb-0"><?= $skor_bidang['Ahli Media']['skor'] ?? '0.00' ?> <small class="fs-6 text-muted">/ 4.00</small></h2>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success fs-6"><?= $skor_bidang['Ahli Media']['persen'] ?? '0' ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-4 justify-content-center" id="pills-tab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold px-4" id="materi-tab" data-bs-toggle="pill" data-bs-target="#materi">DETAIL MATERI</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold px-4" id="media-tab" data-bs-toggle="pill" data-bs-target="#media">DETAIL MEDIA</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold px-4" id="riwayat-tab" data-bs-toggle="pill" data-bs-target="#riwayat">RIWAYAT VALIDATOR</button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="materi">
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-book-open me-2"></i>Analisis Per Aspek (Materi)</h5>
                <?php renderRekapTable($conn, 'Ahli Materi', $kode_app); ?>
            </div>
        </div>

        <div class="tab-pane fade" id="media">
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-4 text-success"><i class="fas fa-clapperboard me-2"></i>Analisis Per Aspek (Media)</h5>
                <?php renderRekapTable($conn, 'Ahli Media', $kode_app); ?>
            </div>
        </div>

        <div class="tab-pane fade" id="riwayat">
            <div class="card card-custom p-4 shadow-sm">
                <h5 class="fw-bold mb-4"><i class="fas fa-history me-2"></i>Log Aktivitas Validator</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Nama Validator</th>
                                <th>Bidang</th>
                                <th>Instansi</th>
                                <th>Kesimpulan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($res_riwayat->num_rows > 0): ?>
                                <?php while($row = $res_riwayat->fetch_assoc()): ?>
                                <tr class="text-center">
    <td class="text-start fw-bold"><?= $row['nama_ahli'] ?></td>
    <td><span class="badge bg-outline-secondary border text-dark text-capitalize"><?= $row['bidang_ahli'] ?></span></td>
    <td><?= $row['instansi'] ?></td>
    <td class="small italic text-muted">"<?= $row['kesimpulan_umum'] ?>"</td>
    <td>
        <a href="detail_skor_ahli.php?nama=<?= urlencode($row['nama_ahli']) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
    <i class="fas fa-eye me-1"></i> Lihat Detail
</a>
    </td>
</tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted p-4">Belum ada riwayat pengisian.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Helper untuk render tabel rekap per bidang
 */
function renderRekapTable($conn, $bidang, $kode_app) {
    $data = getRekapByBidang($conn, $bidang, $kode_app);
    if ($data->num_rows == 0) {
        echo '<div class="alert alert-light text-center p-5">Belum ada data validasi untuk kelompok '.$bidang.'</div>';
        return;
    }
    echo '<div class="table-responsive"><table class="table align-middle">';
    echo '<thead class="text-uppercase small fw-bold text-muted"><tr><th>Aspek Penilaian</th><th class="text-center">Skor Rata-rata</th><th width="40%">Visualisasi Kelayakan</th></tr></thead><tbody>';
    while($row = $data->fetch_assoc()) {
        $p = round(($row['rata_aspek'] / 4) * 100);
        $color = ($p >= 81) ? 'bg-success' : (($p >= 61) ? 'bg-primary' : 'bg-warning');
        echo "<tr>
                <td class='fw-medium'>{$row['aspek']}</td>
                <td class='text-center fs-5 fw-bold text-dark'>".number_format($row['rata_aspek'], 2)."</td>
                <td>
                    <div class='d-flex justify-content-between mb-1 small fw-bold text-muted'><span>".$p."%</span></div>
                    <div class='progress'><div class='progress-bar $color' role='progressbar' style='width: $p%'></div></div>
                </td>
              </tr>";
    }
    echo '</tbody></table></div>';
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>