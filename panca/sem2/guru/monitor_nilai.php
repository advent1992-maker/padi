<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

/* =======================================================================
   1. IDENTITAS & FILTER (LOGIKA SINKRON LAPORAN.PHP)
   ======================================================================= */
$id_pemilik_ruang = (int) ($_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id']);
$filter_materi = $_GET['materi_id'] ?? '';
$nama_filter_aktif = "Semua Materi (Rata-rata Gabungan)";

// Gabungkan Materi dan Tryout ke dalam satu daftar filter
$filter_options = [];
$q_materi = $db_mapel->query("SELECT id, judul FROM panca_materi WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($m = $q_materi->fetch_assoc()) { 
    $filter_options[] = ['id' => 'kuis_'.$m['id'], 'judul' => '[KUIS] '.$m['judul']]; 
    if ($filter_materi == 'kuis_'.$m['id']) $nama_filter_aktif = "[KUIS] " . $m['judul'];
}
$q_tryout = $db_mapel->query("SELECT id, judul FROM panca_tryout_master WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($t = $q_tryout->fetch_assoc()) { 
    $filter_options[] = ['id' => 'to_'.$t['id'], 'judul' => '[TRYOUT] '.$t['judul']]; 
    if ($filter_materi == 'to_'.$t['id']) $nama_filter_aktif = "[TRYOUT] " . $t['judul'];
}

// Ambil Daftar Siswa
$query_siswa = "SELECT id, nama_lengkap, kelas FROM users WHERE role = 'siswa' AND id_guru = $id_pemilik_ruang ORDER BY nama_lengkap ASC";
$result_siswa = $conn->query($query_siswa);

$laporan_siswa = [];
if ($result_siswa && $result_siswa->num_rows > 0) {
    while ($row_s = $result_siswa->fetch_assoc()) {
        $user_id = (int)$row_s['id'];
        $nilai_final = 0;

        if (!empty($filter_materi)) {
            // --- LOGIKA FILTER AKTIF (Kuis atau TO Spesifik) ---
            if (strpos($filter_materi, 'kuis_') === 0) {
                $real_id = str_replace('kuis_', '', $filter_materi);
                $res_n = $db_mapel->query("SELECT ROUND(AVG(persentase)) as n FROM panca_riwayat_kuis WHERE id_user = $user_id AND id_materi = $real_id")->fetch_assoc();
            } else {
                $real_id = str_replace('to_', '', $filter_materi);
                $res_n = $db_mapel->query("SELECT ROUND(AVG(persentase)) as n FROM panca_riwayat_tryout WHERE id_user = $user_id AND tryout_id = $real_id")->fetch_assoc();
            }
            $nilai_final = $res_n['n'] ?? 0;

        } else {
            // --- LOGIKA GLOBAL (Double Rounding Sinkron Dashboard) ---
            
            // 1. Kuis per Materi
            $q_k = $db_mapel->query("SELECT ROUND(AVG(persentase)) as n_materi FROM panca_riwayat_kuis WHERE id_user = $user_id GROUP BY id_materi");
            $sum_k = 0; $cnt_k = 0;
            while($rk = $q_k->fetch_assoc()){ $sum_k += $rk['n_materi']; $cnt_k++; }
            $avg_kuis = ($cnt_k > 0) ? round($sum_k / $cnt_k) : null;

            // 2. Tryout per Judul
            $q_t = $db_mapel->query("SELECT ROUND(AVG(persentase)) as n_to FROM panca_riwayat_tryout WHERE id_user = $user_id GROUP BY tryout_id");
            $sum_t = 0; $cnt_t = 0;
            while($rt = $q_t->fetch_assoc()){ $sum_t += $rt['n_to']; $cnt_t++; }
            $avg_to = ($cnt_t > 0) ? round($sum_t / $cnt_t) : null;

            // 3. Gabungkan
            $koleksi = array_filter([$avg_kuis, $avg_to], function($v) { return !is_null($v); });
            $nilai_final = $koleksi ? round(array_sum($koleksi) / count($koleksi)) : 0;
        }

        // --- HITUNG PROGRES (Jumlah kuis + TO yang dikerjakan) ---
        $res_p = $db_mapel->query("SELECT 
            (SELECT COUNT(DISTINCT id_materi) FROM panca_riwayat_kuis WHERE id_user = $user_id) as m_done,
            (SELECT COUNT(DISTINCT tryout_id) FROM panca_riwayat_tryout WHERE id_user = $user_id) as t_done
        ")->fetch_assoc();
        $progres_total = ($res_p['m_done'] ?? 0) + ($res_p['t_done'] ?? 0);

        $laporan_siswa[] = [
            'nama' => $row_s['nama_lengkap'],
            'kelas' => $row_s['kelas'],
            'nilai' => $nilai_final,
            'progres_count' => $progres_total
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Monitor & Cetak Nilai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .main-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
        .table-primary { background-color: #198754 !important; color: white; }
        .filter-box { background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 10px; padding: 15px; }
        
        @media print {
            .btn, .filter-box, .no-print { display: none !important; }
            body { background-color: white !important; padding: 0 !important; }
            .main-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; pb-2; }
            table { border: 1px solid #000 !important; }
            th, td { border: 1px solid #000 !important; color: black !important; }
        }
        .print-header { display: none; }
    </style>
</head>
<body class="p-4">

<div class="container main-card">
    <div class="print-header">
        <h2 class="fw-bold mb-0">LAPORAN HASIL BELAJAR SISWA</h2>
        <p class="mb-1">Mata Pelajaran: Bahasa Indonesia</p>
        <p class="small text-uppercase">FILTER: <?= $nama_filter_aktif ?></p>
        <p class="small">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 no-print">
        <div>
            <h3 class="fw-bold text-success mb-0"><i class="fas fa-chart-bar me-2"></i> Monitor Nilai</h3>
            <p class="text-muted small mb-0">PADI PORTAL - SDN 06 Martapura</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-print me-1"></i> Cetak Dokumen
            </button>
            <a href="../../../dashboard_guru.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="filter-box no-print mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small fw-bold text-muted">Pilih Kategori Nilai:</label>
                <select name="materi_id" class="form-select border-success shadow-sm">
                    <option value="">— Rata-rata Global (Semua Kuis & Tryout) —</option>
                    <?php foreach($filter_options as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= ($filter_materi == $opt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['judul']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100 shadow-sm fw-bold">
                    <i class="fas fa-filter me-1"></i> Terapkan
                </button>
            </div>
        </form>
    </div>

    <div class="alert alert-info py-2 mb-3 shadow-sm border-0 no-print">
        <i class="fas fa-info-circle me-2"></i> Menampilkan: <strong><?= $nama_filter_aktif ?></strong>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-primary text-center">
                <tr>
                    <th style="width: 5%;">No</th>
                    <th class="text-start">Nama Siswa</th>
                    <th style="width: 10%;">Kelas</th>
                    <th style="width: 15%;">Nilai</th>
                    <th style="width: 20%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporan_siswa)): ?>
                    <tr><td colspan="5" class="text-center py-5">Belum ada data nilai.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($laporan_siswa as $s): ?>
                    <tr class="text-center">
                        <td><?= $no++ ?></td>
                        <td class="text-start fw-bold"><?= htmlspecialchars($s['nama']) ?></td>
                        <td><?= htmlspecialchars($s['kelas']) ?></td>
                        <td class="fw-bold fs-5 <?= $s['nilai'] >= 75 ? 'text-success' : 'text-danger' ?>">
                            <?= $s['nilai'] ?>
                        </td>
                        <td>
                            <?php if(empty($filter_materi)): ?>
                                <span class="badge bg-light text-dark border"><?= $s['progres_count'] ?> Tugas Selesai</span>
                            <?php else: ?>
                                <span class="badge <?= $s['nilai'] >= 75 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $s['nilai'] >= 75 ? 'TUNTAS' : 'REMIDIAL' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="print-header mt-5">
        <div class="row">
            <div class="col-8"></div>
            <div class="col-4 text-center">
                <p>Mengetahui,</p>
                <p>Guru Mata Pelajaran</p>
                <br><br><br>
                <p class="fw-bold">( .......................... )</p>
                <p>NIP. ...........................</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>