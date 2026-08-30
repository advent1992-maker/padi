<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

if (!isset($db_mapel)) {
    die("DB Mapel tidak terdeteksi.");
}

/* ===============================
   1. IDENTITAS & LOGIKA FILTER
================================ */
$id_pemilik_ruang = (int) ($_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'] ?? 0);
$filter_materi = $_GET['materi_id'] ?? '';
$nama_filter_aktif = "Semua Materi (Rata-rata Gabungan)";

$filter_options = [];
$q_materi = $db_mapel->query("SELECT id, judul FROM " . tbl('materi') . " WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($m = $q_materi->fetch_assoc()) { 
    $filter_options[] = ['id' => 'kuis_'.$m['id'], 'judul' => '[KUIS] '.$m['judul']]; 
    if ($filter_materi == 'kuis_'.$m['id']) $nama_filter_aktif = "[KUIS] " . $m['judul'];
}
$q_tryout = $db_mapel->query("SELECT id, judul FROM " . tbl('tryout_master') . " WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($t = $q_tryout->fetch_assoc()) { 
    $filter_options[] = ['id' => 'to_'.$t['id'], 'judul' => '[TRYOUT] '.$t['judul']]; 
    if ($filter_materi == 'to_'.$t['id']) $nama_filter_aktif = "[TRYOUT] " . $t['judul'];
}

/* ===============================
   2. AMBIL DATA SISWA & NILAI
================================ */
$laporan_siswa = [];
$q_siswa = $conn->prepare("SELECT id, nama_lengkap, kelas FROM " . tbl('users') . " WHERE role = 'siswa' AND id_guru = ? ORDER BY nama_lengkap ASC");
$q_siswa->bind_param("i", $id_pemilik_ruang);
$q_siswa->execute();
$res_siswa = $q_siswa->get_result();

while ($s = $res_siswa->fetch_assoc()) {
    $user_id = (int)$s['id'];
    $nilai_tampil = 0;

    if (!empty($filter_materi)) {
        // --- LOGIKA JIKA FILTER AKTIF (Spesifik 1 Kuis/Tryout) ---
        if (strpos($filter_materi, 'kuis_') === 0) {
            $real_id = str_replace('kuis_', '', $filter_materi);
            $q_n = $db_mapel->prepare("SELECT ROUND(AVG(persentase)) FROM " . tbl('riwayat_kuis') . " WHERE id_user = ? AND id_materi = ?");
        } else {
            $real_id = str_replace('to_', '', $filter_materi);
            $q_n = $db_mapel->prepare("SELECT ROUND(AVG(persentase)) FROM " . tbl('riwayat_tryout') . " WHERE id_user = ? AND tryout_id = ?");
        }
        $q_n->bind_param("ii", $user_id, $real_id);
        $q_n->execute(); $q_n->bind_result($res_n); $q_n->fetch(); $q_n->close();
        $nilai_tampil = $res_n ?? 0;

    } else {
        // --- LOGIKA IDENTIK DASHBOARD: PEMBULATAN BERJENJANG ---
        
        // 1. Rata-rata Kuis per Materi (dibulatkan per materi dulu)
        $q_k = $db_mapel->prepare("SELECT ROUND(AVG(rk.persentase)) as n_materi 
                                   FROM " . tbl('riwayat_kuis') . " rk 
                                   JOIN " . tbl('materi') . " m ON rk.id_materi = m.id 
                                   WHERE rk.id_user = ? AND m.id_guru = ? 
                                   GROUP BY rk.id_materi");
        $q_k->bind_param("ii", $user_id, $id_pemilik_ruang);
        $q_k->execute(); $res_k = $q_k->get_result();
        $sum_k = 0; $cnt_k = 0;
        while($rk = $res_k->fetch_assoc()){ $sum_k += $rk['n_materi']; $cnt_k++; }
        
        // Rata-rata kuis akhir dibulatkan
        $avg_kuis_final = ($cnt_k > 0) ? round($sum_k / $cnt_k) : null;

        // 2. Rata-rata Tryout per ID (dibulatkan per tryout dulu)
        $q_t = $db_mapel->prepare("SELECT ROUND(AVG(rt.persentase)) as n_to 
                                   FROM " . tbl('riwayat_tryout') . " rt 
                                   JOIN " . tbl('tryout_master') . " tm ON rt.tryout_id = tm.id 
                                   WHERE rt.id_user = ? AND tm.id_guru = ? 
                                   GROUP BY rt.tryout_id");
        $q_t->bind_param("ii", $user_id, $id_pemilik_ruang);
        $q_t->execute(); $res_t = $q_t->get_result();
        $sum_t = 0; $cnt_t = 0;
        while($rt = $res_t->fetch_assoc()){ $sum_t += $rt['n_to']; $cnt_t++; }
        
        // Rata-rata tryout akhir dibulatkan
        $avg_to_final = ($cnt_t > 0) ? round($sum_t / $cnt_t) : null;

        // 3. Gabungkan hasil kuis & tryout yang sudah bulat
        $koleksi = array_filter([$avg_kuis_final, $avg_to_final], function($v) { return !is_null($v); });
        $nilai_tampil = $koleksi ? round(array_sum($koleksi) / count($koleksi)) : 0;
    }

    // Hitung Progres (Jumlah materi/tryout yang sudah diselesaikan)
    $q_p1 = $db_mapel->prepare("SELECT COUNT(DISTINCT rk.id_materi) FROM " . tbl('riwayat_kuis') . " rk JOIN " . tbl('materi') . " m ON rk.id_materi = m.id WHERE rk.id_user = ? AND m.id_guru = ?");
    $q_p1->bind_param("ii", $user_id, $id_pemilik_ruang);
    $q_p1->execute(); $q_p1->bind_result($ml); $q_p1->fetch(); $q_p1->close();

    $q_p2 = $db_mapel->prepare("SELECT COUNT(DISTINCT rt.tryout_id) FROM " . tbl('riwayat_tryout') . " rt JOIN " . tbl('tryout_master') . " tm ON rt.tryout_id = tm.id WHERE rt.id_user = ? AND tm.id_guru = ?");
    $q_p2->bind_param("ii", $user_id, $id_pemilik_ruang);
    $q_p2->execute(); $q_p2->bind_result($tl); $q_p2->fetch(); $q_p2->close();

    $laporan_siswa[] = [
        'user_id' => $user_id,
        'nama_lengkap' => $s['nama_lengkap'],
        'kelas' => $s['kelas'],
        'nilai' => round($nilai_tampil),
        'progres' => $ml + $tl
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Nilai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* TAMPILAN LAYAR */
        body { background-color: #f0fdfa; font-family: 'Inter', sans-serif; }
        .main-card { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
        .table-header { background-color: #10b981 !important; color: white; }
        .filter-box { background: #f0fdf4; border: 1px solid #ccfbf1; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .print-only { display: none; }

        /* TAMPILAN CETAK (PRINT) */
        @media print {
            @page { size: A4; margin: 1cm 1.5cm; }
            body { background: white !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .main-card { box-shadow: none !important; border: none !important; width: 100% !important; padding: 0 !important; }
            
            table { width: 100% !important; border: 1px solid #000 !important; border-collapse: collapse !important; }
            th { border: 1px solid #000 !important; background-color: #eee !important; color: black !important; padding: 8px !important; }
            td { border: 1px solid #000 !important; padding: 6px !important; color: black !important; }
            
            .text-success, .text-danger { color: black !important; font-weight: bold; }
            .badge { background: transparent !important; color: black !important; border: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="p-4">

<div class="container main-card">
    <div class="print-only text-center mb-4">
        <h2 style="margin: 0; font-weight: bold;">DAFTAR NILAI HASIL BELAJAR SISWA</h2>
        <h3 style="margin: 5px 0; text-transform: uppercase;"><?= htmlspecialchars($nama_filter_aktif) ?></h3>
        <p style="margin: 0;">Mata Pelajaran: Matematika | Semester: Genap 2025/2026</p>
        <hr style="border: 1px solid black; margin-top: 10px;">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 no-print">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill me-3">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <div>
                <h3 class="fw-bold text-success mb-0">Laporan Hasil Belajar</h3>
                <p class="text-muted small mb-0">ID Guru: <strong><?= $id_pemilik_ruang ?></strong></p>
            </div>
        </div>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Cetak Dokumen A4
        </button>
    </div>

    <div class="filter-box no-print">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-9">
                <label class="form-label small fw-bold">Pilih Kuis atau Tryout:</label>
                <select name="materi_id" class="form-select shadow-sm border-teal">
                    <option value="">— Semua (Rata-rata Gabungan) —</option>
                    <?php foreach($filter_options as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= ($filter_materi == $opt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['judul']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100 shadow-sm">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-header text-center">
                <tr>
                    <th width="50">No</th>
                    <th class="text-start">Nama Lengkap Siswa</th>
                    <th width="80">Kelas</th>
                    <th width="120">Nilai Akhir</th>
                    <th width="150">Capaian</th>
                    <th class="no-print" width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach ($laporan_siswa as $siswa): ?>
                    <tr class="text-center">
                        <td><?= $no++ ?></td>
                        <td class="text-start fw-bold"><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                        <td class="fs-5 fw-bold <?= $siswa['nilai'] >= 69 ? 'text-success' : 'text-danger' ?>">
                            <?= $siswa['nilai'] ?>
                        </td>
                        <td>
                            <span class="badge <?= $siswa['nilai'] >= 69 ? 'text-success' : 'text-danger' ?>">
                                <?= empty($filter_materi) ? $siswa['progres'].' Selesai' : ($siswa['nilai'] >= 69 ? 'LULUS' : 'REMIDIAL') ?>
                            </span>
                        </td>
                        <td class="no-print">
                            <a href="laporan_siswa_detail.php?user_id=<?= $siswa['user_id'] ?>" class="btn btn-sm btn-outline-success rounded-pill w-100">
                                <i class="fas fa-chart-line"></i> Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="print-only mt-5">
        <div class="row">
            <div class="col-8"></div>
            <div class="col-4 text-center">
                <p>................, ........<br>Mengetahui,<br>Guru Mata Pelajaran</p>
                <br><br><br>
                <p><strong>( ................ )</strong></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>