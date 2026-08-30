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
$q_materi = $db_mapel->query("SELECT id, judul, pakai_kuis, pakai_praktek FROM " . tbl('materi') . " WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($m = $q_materi->fetch_assoc()) { 
    $label = ($m['pakai_kuis'] == 1 && $m['pakai_praktek'] == 1) ? "[KUIS/PRAKTEK]" : ($m['pakai_kuis'] == 1 ? "[KUIS]" : "[PRAKTEK]");
    $filter_options[] = ['id' => 'kuis_'.$m['id'], 'judul' => $label . ' ' . $m['judul']]; 
    if ($filter_materi == 'kuis_'.$m['id']) $nama_filter_aktif = $label . " " . $m['judul'];
}

$q_tryout = $db_mapel->query("SELECT id, judul FROM " . tbl('tryout_master') . " WHERE id_guru = $id_pemilik_ruang ORDER BY judul ASC");
while($t = $q_tryout->fetch_assoc()) { 
    $filter_options[] = ['id' => 'to_'.$t['id'], 'judul' => '[TRYOUT] '.$t['judul']]; 
    if ($filter_materi == 'to_'.$t['id']) $nama_filter_aktif = "[TRYOUT] " . $t['judul'];
}

/* ===============================
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
    $sudah_kumpul = false; 

    if (!empty($filter_materi)) {
        if (strpos($filter_materi, 'kuis_') === 0) {
            $real_id = str_replace('kuis_', '', $filter_materi);
            
            // 1. Cek Kuis
            $q_nk = $db_mapel->prepare("SELECT AVG(persentase), COUNT(*) FROM " . tbl('riwayat_kuis') . " WHERE id_user = ? AND id_materi = ?");
            $q_nk->bind_param("ii", $user_id, $real_id);
            $q_nk->execute(); $q_nk->bind_result($res_nk, $count_k); $q_nk->fetch(); $q_nk->close();
            
            // 2. Cek Praktek (PENTING: Cek keberadaan data, bukan cuma nilai_angka)
            $q_np = $db_mapel->prepare("SELECT nilai_angka, id FROM " . tbl('praktek_siswa') . " WHERE id_siswa = ? AND materi_id = ?");
            $q_np->bind_param("ii", $user_id, $real_id);
            $q_np->execute(); $q_np->bind_result($res_np, $id_praktek); $q_np->fetch(); $q_np->close();

            // LOGIKA PERBAIKAN: Jika ada record di kuis atau praktek, maka sudah_kumpul = true
            if ($count_k > 0 || !empty($id_praktek)) {
                $sudah_kumpul = true;
            }

            $komponen = array_filter([$res_nk, $res_np], function($v) { return !is_null($v); });
            $nilai_tampil = $komponen ? (array_sum($komponen) / count($komponen)) : 0;

        } else {
            $real_id = str_replace('to_', '', $filter_materi);
            $q_n = $db_mapel->prepare("SELECT ROUND(AVG(persentase)), COUNT(*) FROM " . tbl('riwayat_tryout') . " WHERE id_user = ? AND tryout_id = ?");
            $q_n->bind_param("ii", $user_id, $real_id);
            $q_n->execute(); $q_n->bind_result($res_n, $count_to); $q_n->fetch(); $q_n->close();
            
            $nilai_tampil = $res_n ?? 0;
            // Jika ada riwayat tryout, maka sudah kumpul
            if ($count_to > 0) $sudah_kumpul = true;
        }
    } else {
        // --- LOGIKA GLOBAL (Rata-rata semua materi) ---
        $all_materi = $db_mapel->query("SELECT id FROM " . tbl('materi') . " WHERE id_guru = $id_pemilik_ruang");
        $sum_materi = 0; $cnt_materi = 0;

        while($m = $all_materi->fetch_assoc()) {
            $mid = $m['id'];
            $q_rk = $db_mapel->query("SELECT AVG(persentase) as avg_k, COUNT(*) as jml_k FROM " . tbl('riwayat_kuis') . " WHERE id_user = $user_id AND id_materi = $mid");
            $rk = ($q_rk) ? $q_rk->fetch_assoc() : null;

            $q_rp = $db_mapel->query("SELECT nilai_angka, id FROM " . tbl('praktek_siswa') . " WHERE id_siswa = $user_id AND materi_id = $mid");
            $rp = ($q_rp) ? $q_rp->fetch_assoc() : null;

            if (($rk['jml_k'] ?? 0) > 0 || !empty($rp['id'])) {
                $sudah_kumpul = true; // Tandai sudah pernah mengerjakan salah satu
                
                $val_k = $rk['avg_k'] ?? null;
                $val_p = $rp['nilai_angka'] ?? null;
                $komp = array_filter([$val_k, $val_p], function($v) { return !is_null($v); });
                
                if($komp) {
                    $sum_materi += round(array_sum($komp) / count($komp));
                    $cnt_materi++;
                }
            }
        }
        $avg_materi_final = ($cnt_materi > 0) ? round($sum_materi / $cnt_materi) : null;

        $q_t = $db_mapel->prepare("SELECT ROUND(AVG(rt.persentase)) as n_to FROM " . tbl('riwayat_tryout') . " rt JOIN " . tbl('tryout_master') . " tm ON rt.tryout_id = tm.id WHERE rt.id_user = ? AND tm.id_guru = ? GROUP BY rt.tryout_id");
        $q_t->bind_param("ii", $user_id, $id_pemilik_ruang);
        $q_t->execute(); $res_t = $q_t->get_result();
        $sum_t = 0; $cnt_t = 0;
        while($rt = $res_t->fetch_assoc()){ $sum_t += $rt['n_to']; $cnt_t++; }
        $avg_to_final = ($cnt_t > 0) ? round($sum_t / $cnt_t) : null;

        $koleksi = array_filter([$avg_materi_final, $avg_to_final], function($v) { return !is_null($v); });
        $nilai_tampil = $koleksi ? round(array_sum($koleksi) / count($koleksi)) : 0;
    }

    $laporan_siswa[] = [
        'user_id' => $user_id,
        'nama_lengkap' => $s['nama_lengkap'],
        'kelas' => $s['kelas'],
        'nilai' => round($nilai_tampil),
        'sudah_kumpul' => $sudah_kumpul, 
        'progres' => 0 
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Nilai Seni Rupa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* TAMPILAN LAYAR */
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .main-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); padding: 2rem; margin-top: 2rem; }
        .table-header { background-color: #059669 !important; color: white; }
        .print-only { display: none; }

        /* TAMPILAN CETAK (PRINT) */
        @media print {
            @page { size: A4; margin: 1cm 1.5cm; }
            body { background: white !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .main-card { box-shadow: none !important; border: none !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
            
            table { width: 100% !important; border: 1px solid #000 !important; border-collapse: collapse !important; }
            th { border: 1px solid #000 !important; background-color: #f2f2f2 !important; color: black !important; padding: 8px !important; text-align: center !important; }
            td { border: 1px solid #000 !important; padding: 6px !important; color: black !important; }
            
            .badge { background: transparent !important; color: black !important; border: none !important; padding: 0 !important; font-weight: bold; }
            .text-muted { color: black !important; }
        }
    </style>
</head>
<body class="p-4">

<div class="container main-card">
    <div class="print-only text-center mb-4">
        <h2 style="margin: 0; font-weight: bold; text-transform: uppercase;">Daftar Nilai Hasil Belajar Siswa</h2>
        <h3 style="margin: 5px 0; text-transform: uppercase; font-size: 1.2rem;"><?= htmlspecialchars($nama_filter_aktif) ?></h3>
        <p style="margin: 0;">Mata Pelajaran: Seni Rupa | Semester: Genap 2025/2026</p>
        <hr style="border: 1px solid black; margin-top: 10px; opacity: 1;">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="fw-bold text-success mb-0">Laporan Seni Rupa</h2>
            <p class="text-muted small mb-0"><?= htmlspecialchars($nama_filter_aktif) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Cetak A4
            </button>
        </div>
    </div>

    <div class="card bg-light border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-9">
                    <select name="materi_id" class="form-select shadow-sm">
                        <option value="">— Tampilkan Rata-rata Gabungan Global —</option>
                        <?php foreach($filter_options as $opt): ?>
                            <option value="<?= $opt['id'] ?>" <?= ($filter_materi == $opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['judul']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100 shadow-sm">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle border">
            <thead class="table-header text-center">
                <tr>
                    <th width="50">No</th>
                    <th class="text-start">Nama Lengkap Siswa</th>
                    <th width="80">Kelas</th>
                    <th width="120">Nilai Akhir</th>
                    <th class="no-print" width="220">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach ($laporan_siswa as $siswa): ?>
                    <tr class="text-center">
                        <td><?= $no++ ?></td>
                        <td class="text-start fw-semibold"><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($siswa['kelas']) ?></td>
<td>
    <?php if ($siswa['sudah_kumpul'] && ($siswa['nilai'] == 0 || $siswa['nilai'] === null)): ?>
        <span class="badge bg-warning text-dark rounded-pill px-3 shadow-sm">
            <i class="fas fa-clock me-1"></i> Menunggu Dikoreksi
        </span>
    <?php elseif ($siswa['sudah_kumpul'] && $siswa['nilai'] > 0): ?>
        <span class="badge rounded-pill fs-6 px-3 <?= $siswa['nilai'] >= 70 ? 'bg-success' : 'bg-danger' ?>">
            <?= $siswa['nilai'] ?>
        </span>
    <?php else: ?>
        <span class="text-muted fw-bold">-</span>
    <?php endif; ?>
</td>
                        <td class="no-print">
                            <div class="d-grid gap-1">
                                <a href="laporan_siswa_detail.php?user_id=<?= $siswa['user_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                                <?php if (!empty($filter_materi) && strpos($filter_materi, 'kuis_') === 0): ?>
                                    <?php if ($siswa['sudah_kumpul']): ?>
                                        <a href="praktek_kurasi.php?id_siswa=<?= $siswa['user_id'] ?>&id_materi=<?= str_replace('kuis_', '', $filter_materi) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check-circle me-1"></i> Periksa
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-2">Belum Mengumpulkan Tugas</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
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
                <p>........, ..........<br>Mengetahui,<br>Guru Mata Pelajaran</p>
                <br><br><br>
                <p><strong>( <?= htmlspecialchars($nama_guru_aktif ?? '........................') ?> )</strong></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>