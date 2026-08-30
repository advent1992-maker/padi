<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

/* =======================================================================
   1. IDENTITAS & FILTER (LOGIKA SENI RUPA)
   ======================================================================= */
$id_pemilik_ruang = (int) ($_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id']);
$filter_materi = $_GET['materi_id'] ?? '';
$nama_filter_aktif = "Semua Materi (Rata-rata Gabungan)";

// Ambil daftar materi dengan label Kuis/Praktek
$filter_options = [];
$q_materi = $db_mapel->query("SELECT id, judul, pakai_kuis, pakai_praktek FROM materi WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($m = $q_materi->fetch_assoc()) { 
    $label = ($m['pakai_kuis'] == 1 && $m['pakai_praktek'] == 1) ? "[KUIS/PRAKTEK]" : ($m['pakai_kuis'] == 1 ? "[KUIS]" : "[PRAKTEK]");
    $filter_options[] = ['id' => 'kuis_'.$m['id'], 'judul' => $label . ' ' . $m['judul']]; 
    if ($filter_materi == 'kuis_'.$m['id']) $nama_filter_aktif = $label . " " . $m['judul'];
}

$q_tryout = $db_mapel->query("SELECT id, judul FROM tryout_master WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
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
        $nilai_tampil = 0;
        $sudah_kumpul = false;

        if (!empty($filter_materi)) {
            // --- LOGIKA FILTER AKTIF ---
            if (strpos($filter_materi, 'kuis_') === 0) {
                $real_id = str_replace('kuis_', '', $filter_materi);
                
                // Cek Kuis
                $q_nk = $db_mapel->query("SELECT AVG(persentase) as avg_k, COUNT(*) as jml_k FROM riwayat_kuis WHERE id_user = $user_id AND id_materi = $real_id");
                $nk = $q_nk->fetch_assoc();

                // Cek Praktek
                $q_np = $db_mapel->query("SELECT nilai_angka, id FROM praktek_siswa WHERE id_siswa = $user_id AND materi_id = $real_id");
                $np = $q_np->fetch_assoc();

                if (($nk['jml_k'] ?? 0) > 0 || !empty($np['id'])) $sudah_kumpul = true;

                $komponen = array_filter([$nk['avg_k'] ?? null, $np['nilai_angka'] ?? null], function($v) { return !is_null($v); });
                $nilai_tampil = $komponen ? (array_sum($komponen) / count($komponen)) : 0;
            } else {
                $real_id = str_replace('to_', '', $filter_materi);
                $q_to = $db_mapel->query("SELECT AVG(persentase) as n, COUNT(*) as jml FROM riwayat_tryout WHERE id_user = $user_id AND tryout_id = $real_id");
                $rto = $q_to->fetch_assoc();
                $nilai_tampil = $rto['n'] ?? 0;
                if (($rto['jml'] ?? 0) > 0) $sudah_kumpul = true;
            }
        } else {
            // --- LOGIKA GLOBAL (RATA-RATA SEMUA MATERI) ---
            $all_materi = $db_mapel->query("SELECT id FROM materi WHERE id_guru = $id_pemilik_ruang");
            $sum_materi = 0; $cnt_materi = 0;

            while($m = $all_materi->fetch_assoc()) {
                $mid = $m['id'];
                $rk = $db_mapel->query("SELECT AVG(persentase) as avg_k, COUNT(*) as jml FROM riwayat_kuis WHERE id_user = $user_id AND id_materi = $mid")->fetch_assoc();
                $rp = $db_mapel->query("SELECT nilai_angka, id FROM praktek_siswa WHERE id_siswa = $user_id AND materi_id = $mid")->fetch_assoc();

                if (($rk['jml'] ?? 0) > 0 || !empty($rp['id'])) {
                    $sudah_kumpul = true;
                    $komp = array_filter([$rk['avg_k'] ?? null, $rp['nilai_angka'] ?? null], function($v) { return !is_null($v); });
                    if($komp) {
                        $sum_materi += round(array_sum($komp) / count($komp));
                        $cnt_materi++;
                    }
                }
            }
            $avg_materi = ($cnt_materi > 0) ? round($sum_materi / $cnt_materi) : null;

            // Rata-rata Tryout
            $q_at = $db_mapel->query("SELECT ROUND(AVG(persentase)) as n FROM riwayat_tryout WHERE id_user = $user_id GROUP BY tryout_id");
            $sum_t = 0; $cnt_t = 0;
            while($rt = $q_at->fetch_assoc()){ $sum_t += $rt['n']; $cnt_t++; }
            $avg_to = ($cnt_t > 0) ? round($sum_t / $cnt_t) : null;

            $koleksi = array_filter([$avg_materi, $avg_to], function($v) { return !is_null($v); });
            $nilai_tampil = $koleksi ? round(array_sum($koleksi) / count($koleksi)) : 0;
        }

        $laporan_siswa[] = [
            'nama' => $row_s['nama_lengkap'],
            'kelas' => $row_s['kelas'],
            'nilai' => round($nilai_tampil),
            'sudah_kumpul' => $sudah_kumpul
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Monitor Nilai Seni</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .main-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); padding: 2rem; }
        .table-primary { background-color: #059669 !important; color: white; }
        
        @media print {
            .btn, .filter-box, .no-print { display: none !important; }
            body { background-color: white !important; padding: 0 !important; }
            .main-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            table { border: 1px solid #000 !important; width: 100% !important; }
            th, td { border: 1px solid #000 !important; color: black !important; padding: 8px !important; }
        }
        .print-header { display: none; }
    </style>
</head>
<body class="p-4">

<div class="container main-card">
    <div class="print-header">
        <h2 class="fw-bold mb-0">DAFTAR NILAI SENI RUPA</h2>
        <p class="mb-1">Semester Genap 2025/2026</p>
        <p class="small text-uppercase">FILTER: <?= $nama_filter_aktif ?></p>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 no-print">
        <div>
            <h3 class="fw-bold text-success mb-0"><i class="fas fa-palette me-2"></i> Monitor Nilai Seni</h3>
            <p class="text-muted small mb-0"><?= $nama_filter_aktif ?></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-3">
                <i class="fas fa-print me-1"></i> Cetak A4
            </button>
            <a href="../../../dashboard_guru.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card bg-light border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label small fw-bold text-muted">Pilih Kuis / Praktek:</label>
                    <select name="materi_id" class="form-select border-success shadow-sm">
                        <option value="">— Rata-rata Gabungan Global —</option>
                        <?php foreach($filter_options as $opt): ?>
                            <option value="<?= $opt['id'] ?>" <?= ($filter_materi == $opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['judul']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100 shadow-sm fw-bold">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-primary text-center">
                <tr>
                    <th style="width: 5%;">No</th>
                    <th class="text-start">Nama Siswa</th>
                    <th style="width: 10%;">Kelas</th>
                    <th style="width: 25%;">Status / Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($laporan_siswa as $s): ?>
                <tr class="text-center">
                    <td><?= $no++ ?></td>
                    <td class="text-start fw-semibold"><?= htmlspecialchars($s['nama']) ?></td>
                    <td><?= htmlspecialchars($s['kelas']) ?></td>
                    <td>
                        <?php if ($s['sudah_kumpul'] && ($s['nilai'] == 0)): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-3">
                                <i class="fas fa-clock me-1"></i> Menunggu Koreksi
                            </span>
                        <?php elseif ($s['sudah_kumpul'] && $s['nilai'] > 0): ?>
                            <span class="fw-bold fs-5 <?= $s['nilai'] >= 70 ? 'text-success' : 'text-danger' ?>">
                                <?= $s['nilai'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted small">- Belum Kumpul -</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="print-header mt-5">
        <div class="row">
            <div class="col-8"></div>
            <div class="col-4 text-center">
                <p>Mengetahui,<br>Guru Mata Pelajaran</p>
                <br><br><br>
                <p class="fw-bold">( ............................ )</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>