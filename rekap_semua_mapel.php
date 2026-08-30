<?php
require_once 'config/session.php';
require_once 'config/koneksi.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: index.php");
    exit();
}

$id_guru_login = $_SESSION['user_id'];
$semester = $_SESSION['semester_aktif'] ?? '2';
$filter_tuntas = isset($_GET['tuntas']) ? true : false; 
$mode_arsip = isset($_GET['arsip']) ? true : false;
$tbl_suffix = $mode_arsip ? '_arsip' : '';

$mapels = [
    ['folder' => 'ipas', 'nama' => 'IPAS'],
    ['folder' => 'mtk', 'nama' => 'Matematika'],
    ['folder' => 'indo', 'nama' => 'B. Indonesia'],
    ['folder' => 'panca', 'nama' => 'Pancasila'],
    ['folder' => 'englis', 'nama' => 'B. Inggris'],
    ['folder' => 'pjok', 'nama' => 'PJOK'],
    ['folder' => 'pai', 'nama' => 'PAI'],
    ['folder' => 'mulok', 'nama' => 'B. Komering'],
    ['folder' => 'seni', 'nama' => 'Seni']
];

function getMappingUserRekap($folder, $prefix) {
    $map = [
        'ipas'   => 'hari', 'mtk'    => 'advent', 'indo'   => 'harrieya',
        'panca'  => 'kristian', 'englis' => 'kris', 'pjok'   => 'derry',
        'pai'    => 'arq', 'mulok'  => 'kristian', 'seni'   => 'senirupa'
    ];
    return isset($map[$folder]) ? $prefix . $map[$folder] : $prefix . "admin";
}

// 1. Dapatkan Data Siswa
$query_siswa = "SELECT id, nama_lengkap, kelas FROM users{$tbl_suffix} WHERE role = 'siswa' AND id_guru = ? ORDER BY nama_lengkap ASC";
$stmt = $conn->prepare($query_siswa);
$stmt->bind_param("i", $id_guru_login);
$stmt->execute();
$result_siswa = $stmt->get_result();

$rekap_data = [];
$total_per_mapel = [];
foreach ($mapels as $m) {
    $total_per_mapel[$m['folder']] = ['nilai' => 0, 'count' => 0];
}

// 2. KONEKSI CACHE
$koneksi_mapel_cache = [];
foreach ($mapels as $m) {
    $folder_koneksi = ($m['folder'] == 'panca') ? 'mulok' : $m['folder'];
    
    if (!isset($koneksi_mapel_cache[$folder_koneksi])) {
        $db_name = $prefix . "db_" . $folder_koneksi . "_sm" . $semester;
        $user_mapel = getMappingUserRekap($folder_koneksi, $prefix);
        
        $conn_db = @mysqli_connect($host, $user_mapel, $pass, $db_name);
        if ($conn_db) {
            $koneksi_mapel_cache[$folder_koneksi] = $conn_db;
        }
    }
}

// 3. Olah Data Siswa & Nilai
while ($siswa = $result_siswa->fetch_assoc()) {
    $id_s = $siswa['id'];
    $data_baris = [
        'nama' => $siswa['nama_lengkap'],
        'kelas' => $siswa['kelas'],
        'nilai_mapel' => [],
        'progres_mapel' => [],
        'total_skor_untuk_rata' => 0,
        'total_progres_kumulatif' => 0,
        'jumlah_mapel_aktif_nilai' => 0,
        'jumlah_mapel_aktif_progres' => 0 
    ];

    foreach ($mapels as $m) {
        $folder_koneksi = ($m['folder'] == 'panca') ? 'mulok' : $m['folder'];
        $temp_db = $koneksi_mapel_cache[$folder_koneksi] ?? null;
        
        $rata_mapel_final = 0;
        $persen_progres_final = 0;

        if ($temp_db) {
            $table_prefix = ($m['folder'] == 'panca') ? 'panca_' : '';
            
            if ($m['folder'] == 'seni') {
                // --- LOGIKA KHUSUS SENI ---
                $q_kuis_seni = mysqli_query($temp_db, "SELECT id_materi, ROUND(AVG(persentase)) as n FROM riwayat_kuis{$tbl_suffix} WHERE id_user = $id_s GROUP BY id_materi");
                $list_k = []; while($rk = mysqli_fetch_assoc($q_kuis_seni)) { $list_k[$rk['id_materi']] = $rk['n']; }

                $q_prak_seni = mysqli_query($temp_db, "SELECT materi_id, nilai_angka FROM praktek_siswa{$tbl_suffix} WHERE id_siswa = $id_s AND status_dinilai = 1");
                $list_p = []; while($rp = mysqli_fetch_assoc($q_prak_seni)) { $list_p[$rp['materi_id']] = $rp['nilai_angka']; }

                $all_ids = array_unique(array_merge(array_keys($list_k), array_keys($list_p)));
                $sum_materi = 0; $cnt_materi = 0;
                foreach($all_ids as $id_m) {
                    $nk = $list_k[$id_m] ?? null; $np = $list_p[$id_m] ?? null;
                    if($nk !== null && $np !== null) { $skor = round(($nk + $np)/2); }
                    else { $skor = $nk ?? $np; }
                    $sum_materi += $skor; $cnt_materi++;
                }
                $avg_materi_final = ($cnt_materi > 0) ? round($sum_materi / $cnt_materi) : 0;

                $q_to_seni = mysqli_query($temp_db, "SELECT ROUND(AVG(persentase)) as n_to FROM riwayat_tryout{$tbl_suffix} WHERE id_user = $id_s GROUP BY tryout_id");
                $sum_to = 0; $cnt_to = 0;
                while($rt = mysqli_fetch_assoc($q_to_seni)) { $sum_to += $rt['n_to']; $cnt_to++; }
                $avg_to_final = ($cnt_to > 0) ? round($sum_to / $cnt_to) : 0;

                $vals = array_filter([$avg_materi_final, $avg_to_final]);
                $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;

            } else {
                // --- LOGIKA MAPEL UMUM ---
                $q_k = mysqli_query($temp_db, "SELECT ROUND(AVG(persentase)) as n FROM {$table_prefix}riwayat_kuis{$tbl_suffix} WHERE id_user = $id_s GROUP BY id_materi");
                $sum_k = 0; $cnt_k = 0;
                while($rk = mysqli_fetch_assoc($q_k)) { $sum_k += $rk['n']; $cnt_k++; }
                $k_bulat = ($cnt_k > 0) ? round($sum_k / $cnt_k) : null;

                $q_t = mysqli_query($temp_db, "SELECT ROUND(AVG(persentase)) as n FROM {$table_prefix}riwayat_tryout{$tbl_suffix} WHERE id_user = $id_s GROUP BY tryout_id");
                $sum_t = 0; $cnt_t = 0;
                while($rt = mysqli_fetch_assoc($q_t)) { $sum_t += $rt['n']; $cnt_t++; }
                $t_bulat = ($cnt_t > 0) ? round($sum_t / $cnt_t) : null;

                $vals = array_filter([$k_bulat, $t_bulat], function($v) { return !is_null($v); });
                $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;
            }

            // --- LOGIKA PROGRES ---
            $q_prog = "SELECT 
                (SELECT COUNT(id) FROM {$table_prefix}materi{$tbl_suffix} WHERE id_guru = $id_guru_login) as total_m,
                (SELECT COUNT(id) FROM {$table_prefix}tryout_master{$tbl_suffix} WHERE id_guru = $id_guru_login) as total_t,
                (SELECT COUNT(DISTINCT rk.id_materi) FROM {$table_prefix}riwayat_kuis{$tbl_suffix} rk 
                    JOIN {$table_prefix}materi{$tbl_suffix} m ON rk.id_materi = m.id 
                    WHERE rk.id_user = $id_s AND m.id_guru = $id_guru_login) as m_selesai,
                (SELECT COUNT(DISTINCT rt.tryout_id) FROM {$table_prefix}riwayat_tryout{$tbl_suffix} rt 
                    JOIN {$table_prefix}tryout_master{$tbl_suffix} tm ON rt.tryout_id = tm.id 
                    WHERE rt.id_user = $id_s AND tm.id_guru = $id_guru_login) as t_selesai";
            
            if ($m['folder'] == 'seni') {
                $q_prog .= ", (SELECT COUNT(DISTINCT materi_id) FROM praktek_siswa{$tbl_suffix} WHERE id_siswa = $id_s AND status_dinilai = 1) as p_selesai";
            } else {
                $q_prog .= ", 0 as p_selesai";
            }

            $res_p = mysqli_query($temp_db, $q_prog);
            $row_p = mysqli_fetch_assoc($res_p);
            $tersedia = ($row_p['total_m'] ?? 0) + ($row_p['total_t'] ?? 0);
            
            if ($m['folder'] == 'seni') {
                $dikerjakan = ($row_p['m_selesai'] ?? 0) + ($row_p['t_selesai'] ?? 0) + ($row_p['p_selesai'] ?? 0);
                if ($dikerjakan > $tersedia) $dikerjakan = $tersedia; 
            } else {
                $dikerjakan = ($row_p['m_selesai'] ?? 0) + ($row_p['t_selesai'] ?? 0);
            }
            
            $persen_progres_final = ($tersedia > 0) ? round(($dikerjakan / $tersedia) * 100) : 0;

            if ($rata_mapel_final > 0 || $persen_progres_final > 0) {
                $data_baris['total_skor_untuk_rata'] += $rata_mapel_final; 
                $data_baris['jumlah_mapel_aktif_nilai']++;
                $total_per_mapel[$m['folder']]['nilai'] += $rata_mapel_final;
                $total_per_mapel[$m['folder']]['count']++;
            }

            if ($tersedia > 0) {
                $data_baris['total_progres_kumulatif'] += $persen_progres_final;
                $data_baris['jumlah_mapel_aktif_progres']++;
            }
        }
        $data_baris['nilai_mapel'][$m['folder']] = $rata_mapel_final;
        $data_baris['progres_mapel'][$m['folder']] = $persen_progres_final;
    }

    $data_baris['rata_akhir'] = $data_baris['jumlah_mapel_aktif_nilai'] > 0 
        ? $data_baris['total_skor_untuk_rata'] / $data_baris['jumlah_mapel_aktif_nilai'] : 0;
    
    $data_baris['progres_akhir'] = $data_baris['jumlah_mapel_aktif_progres'] > 0 
        ? round($data_baris['total_progres_kumulatif'] / $data_baris['jumlah_mapel_aktif_progres']) : 0;

    if (!$filter_tuntas || ($filter_tuntas && $data_baris['progres_akhir'] >= 100)) {
        $rekap_data[] = $data_baris;
    }
}

// 4. Tutup seluruh koneksi database
foreach ($koneksi_mapel_cache as $conn_m) {
    if ($conn_m) mysqli_close($conn_m);
}

// 5. Logika Sorting Ranking
usort($rekap_data, function($a, $b) {
    if ($b['rata_akhir'] != $a['rata_akhir']) {
        return $b['rata_akhir'] <=> $a['rata_akhir'];
    }
    $prio = ['pai', 'panca', 'indo'];
    foreach($prio as $p) {
        $val_a = $a['nilai_mapel'][$p] ?? 0;
        $val_b = $b['nilai_mapel'][$p] ?? 0;
        if ($val_b != $val_a) return $val_b <=> $val_a;
    }
    return 0;
});

// 6. Statistik Kelas
$jml_siswa = count($rekap_data);
$nilai_rata_rata_kelas = $jml_siswa > 0 ? array_sum(array_column($rekap_data, 'rata_akhir')) / $jml_siswa : 0;
$progres_rata_rata_kelas = $jml_siswa > 0 ? round(array_sum(array_column($rekap_data, 'progres_akhir')) / $jml_siswa) : 0;

// URL Generator untuk tombol
$url_tuntas = "?tuntas=1" . ($mode_arsip ? "&arsip=1" : "");
$url_semua = "?" . ($mode_arsip ? "arsip=1" : "");
$url_arsip = "?arsip=1" . ($filter_tuntas ? "&tuntas=1" : "");
$url_aktif = "?" . ($filter_tuntas ? "tuntas=1" : "");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Nilai & Progres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-size: 0.85rem; font-family: 'Poppins', sans-serif; }
        .table-rekap thead th { vertical-align: middle; text-align: center; font-size: 0.75rem; }
        .progress { height: 6px; border-radius: 5px; margin-top: 4px; }
        .sticky-col { position: sticky; left: 0; background: white !important; z-index: 5; border-right: 2px solid #dee2e6 !important; }
        .rank-badge { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: auto; font-size: 0.7rem; }
        .bg-avg-kelas { background: #e9ecef; border-radius: 10px; padding: 8px 15px; }
    </style>
</head>
<body class="p-4">

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <h5 class="fw-bold mb-0 <?= $mode_arsip ? 'text-warning' : 'text-primary' ?> me-4">
                REKAP NILAI <?= $mode_arsip ? '(ARSIP)' : '' ?>
            </h5>
            <div class="bg-avg-kelas border <?= $mode_arsip ? 'border-warning' : 'border-primary' ?> me-2">
                <small class="text-muted d-block" style="font-size: 0.65rem;">RATA NILAI KELAS</small>
                <span class="fw-bold <?= $mode_arsip ? 'text-warning' : 'text-primary' ?>"><?= number_format($nilai_rata_rata_kelas, 2, ',', '.') ?></span>
            </div>
            <div class="bg-avg-kelas border border-success">
                <small class="text-muted d-block" style="font-size: 0.65rem;">RATA PROGRES KELAS</small>
                <span class="fw-bold text-success"><?= $progres_rata_rata_kelas ?>%</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <!-- Tombol Toggle Arsip -->
            <a href="rekap_semua_mapel.php<?= $mode_arsip ? $url_aktif : $url_arsip ?>" class="btn btn-sm <?= $mode_arsip ? 'btn-warning' : 'btn-outline-warning' ?> rounded-pill px-3 fw-bold">
                <i class="fas fa-archive"></i> <?= $mode_arsip ? 'Kembali ke Aktif' : 'Lihat Arsip' ?>
            </a>

            <!-- Tombol Filter Tuntas -->
            <a href="rekap_semua_mapel.php<?= $filter_tuntas ? $url_semua : $url_tuntas ?>" class="btn btn-sm <?= $filter_tuntas ? 'btn-outline-primary' : 'btn-success' ?> rounded-pill px-3">
                <?= $filter_tuntas ? 'Semua Siswa' : 'Progres 100%' ?>
            </a>
            
            <a href="dashboard_guru.php" class="btn btn-sm btn-secondary rounded-pill px-3">Kembali</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0">
            <thead class="<?= $mode_arsip ? 'table-warning' : 'table-dark' ?>">
                <tr>
                    <th rowspan="2">Rank</th>
                    <th rowspan="2" class="sticky-col">Nama Siswa</th>
                    <?php foreach($mapels as $m): ?> <th><?= $m['nama'] ?></th> <?php endforeach; ?>
                    <th rowspan="2" class="<?= $mode_arsip ? 'bg-warning text-dark' : 'bg-primary' ?>">Rata-rata Akhir</th>
                    <th rowspan="2" class="bg-success text-white">Total Progres</th>
                </tr>
                <tr>
                    <?php foreach($mapels as $m): ?> <th style="font-size: 0.65rem;">Nilai | Prog</th> <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rekap_data as $i => $d): $rank = $i + 1; ?>
                <tr>
                    <td class="text-center fw-bold">
                        <?= $rank <= 3 ? '<div class="rank-badge bg-warning text-dark"><i class="fas fa-trophy"></i></div>' : $rank ?>
                    </td>
                    <td class="sticky-col">
                        <div class="fw-bold text-nowrap"><?= htmlspecialchars($d['nama']) ?></div>
                        <small class="text-muted" style="font-size: 0.7rem;">Kelas <?= $d['kelas'] ?></small>
                    </td>
                    <?php foreach($mapels as $m): 
                        $n = $d['nilai_mapel'][$m['folder']]; 
                        $p = $d['progres_mapel'][$m['folder']];
                    ?>
                    <td class="text-center">
                        <span class="fw-bold <?= ($n < 70 && $n > 0) ? 'text-danger' : '' ?>"><?= $n ?: '-' ?></span>
                        <div class="progress"><div class="progress-bar <?= $p >= 100 ? 'bg-success' : 'bg-info' ?>" style="width: <?= $p ?>%"></div></div>
                        <small style="font-size: 0.6rem;"><?= $p ?>%</small>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-center bg-light fw-bold text-primary"><?= number_format($d['rata_akhir'], 2, ',', '.') ?></td>
                    <td class="text-center bg-light fw-bold text-success"><?= $d['progres_akhir'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2" class="text-center sticky-col">RATA PER MAPEL</td>
                    <?php foreach($mapels as $m): 
                        $avg = ($total_per_mapel[$m['folder']]['count'] > 0) ? round($total_per_mapel[$m['folder']]['nilai'] / $total_per_mapel[$m['folder']]['count']) : 0;
                    ?>
                    <td class="text-center text-primary"><?= $avg ?: '-' ?></td>
                    <?php endforeach; ?>
                    <td class="text-center bg-primary text-white"><?= number_format($nilai_rata_rata_kelas, 2, ',', '.') ?></td>
                    <td class="text-center bg-success text-white"><?= $progres_rata_rata_kelas ?>%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>