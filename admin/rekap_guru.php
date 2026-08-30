<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// 1. Proteksi Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_GET['id_guru'] ?? 0;
$semester = $_SESSION['semester_aktif'] ?? '2';
$filter_tuntas = isset($_GET['tuntas']) ? true : false; 

// 2. Ambil Data Guru Pembimbing
$stmt_g = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ? AND role = 'guru'");
$stmt_g->bind_param("i", $id_guru);
$stmt_g->execute();
$guru = $stmt_g->get_result()->fetch_assoc();

if (!$guru) {
    die("<div class='alert alert-danger'>Data Guru tidak ditemukan.</div>");
}

// 3. Daftar Mata Pelajaran (Sinkron dengan Rekap Semua Mapel)
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

function getMappingUserAdmin($folder, $prefix) {
    $map = [
        'ipas'   => 'hari', 'mtk'    => 'advent', 'indo'   => 'harrieya',
        'panca'  => 'adventgool', 'englis' => 'kris', 'pjok'   => 'derry',
        'pai'    => 'arq', 'mulok'  => 'kristian', 'seni'   => 'senirupa'
    ];
    return isset($map[$folder]) ? $prefix . $map[$folder] : $prefix . "admin";
}

// 4. Ambil Siswa bimbingan guru ini
$query_siswa = "SELECT id, nama_lengkap, kelas FROM users WHERE role = 'siswa' AND id_guru = ? ORDER BY nama_lengkap ASC";
$stmt_s = $conn->prepare($query_siswa);
$stmt_s->bind_param("i", $id_guru);
$stmt_s->execute();
$result_siswa = $stmt_s->get_result();

$rekap_data = [];
$total_per_mapel = [];
foreach ($mapels as $m) {
    $total_per_mapel[$m['folder']] = ['nilai' => 0, 'count' => 0];
}

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
        $db_name = $prefix . "db_" . $m['folder'] . "_sm" . $semester;
        $user_mapel = getMappingUserAdmin($m['folder'], $prefix);
        $temp_db = @mysqli_connect($host, $user_mapel, $pass, $db_name);

        $rata_mapel_raw = 0;
        $persen_progres_raw = 0;

        if ($temp_db) {
            // A. QUERY NILAI
            $q_nilai = "SELECT 
                        (SELECT AVG(persentase) FROM riwayat_kuis WHERE id_user = $id_s) as avg_k,
                        (SELECT AVG(persentase) FROM riwayat_tryout WHERE id_user = $id_s) as avg_t";
            
            if ($m['folder'] == 'seni') {
                $q_nilai .= ", (SELECT AVG(nilai_angka) FROM praktek_siswa WHERE id_siswa = $id_s AND status_dinilai = 1) as avg_p";
            } else {
                $q_nilai .= ", NULL as avg_p";
            }

            $res_n = mysqli_query($temp_db, $q_nilai);
            $row_n = mysqli_fetch_assoc($res_n);
            $vals = array_filter([$row_n['avg_k'], $row_n['avg_t'], $row_n['avg_p']]);
            $rata_mapel_raw = count($vals) > 0 ? array_sum($vals) / count($vals) : 0;

            // B. QUERY PROGRES
            $q_prog = "SELECT 
                        (SELECT COUNT(id) FROM materi WHERE id_guru = $id_guru) as total_m,
                        (SELECT COUNT(id) FROM tryout_master WHERE id_guru = $id_guru) as total_t,
                        (SELECT COUNT(DISTINCT rk.id_materi) FROM riwayat_kuis rk JOIN materi m ON rk.id_materi = m.id WHERE rk.id_user = $id_s AND m.id_guru = $id_guru) as m_selesai,
                        (SELECT COUNT(DISTINCT rt.tryout_id) FROM riwayat_tryout rt JOIN tryout_master tm ON rt.tryout_id = tm.id WHERE rt.id_user = $id_s AND tm.id_guru = $id_guru) as t_selesai";
            
            if ($m['folder'] == 'seni') {
                $q_prog .= ", (SELECT COUNT(id) FROM praktek_siswa WHERE id_siswa = $id_s) as p_selesai";
            } else {
                $q_prog .= ", 0 as p_selesai";
            }

            $res_p = mysqli_query($temp_db, $q_prog);
            $row_p = mysqli_fetch_assoc($res_p);

            $tersedia = ($row_p['total_m'] ?? 0) + ($row_p['total_t'] ?? 0);
            $dikerjakan = ($row_p['m_selesai'] ?? 0) + ($row_p['t_selesai'] ?? 0) + ($row_p['p_selesai'] ?? 0);
            
            if ($dikerjakan > $tersedia && $tersedia > 0) $dikerjakan = $tersedia;
            $persen_progres_raw = ($tersedia > 0) ? ($dikerjakan / $tersedia) * 100 : 0;

            $n_bulat = round($rata_mapel_raw);
            $p_bulat = round($persen_progres_raw);

            if ($n_bulat > 0 || $p_bulat > 0) {
                $data_baris['total_skor_untuk_rata'] += $n_bulat;
                $data_baris['jumlah_mapel_aktif_nilai']++;
                $total_per_mapel[$m['folder']]['nilai'] += $n_bulat;
                $total_per_mapel[$m['folder']]['count']++;
            }

            if ($tersedia > 0) {
                $data_baris['total_progres_kumulatif'] += $p_bulat;
                $data_baris['jumlah_mapel_aktif_progres']++;
            }
            mysqli_close($temp_db);
        }
        $data_baris['nilai_mapel'][$m['folder']] = round($rata_mapel_raw);
        $data_baris['progres_mapel'][$m['folder']] = round($persen_progres_raw);
    }

    $data_baris['rata_akhir'] = $data_baris['jumlah_mapel_aktif_nilai'] > 0 
        ? $data_baris['total_skor_untuk_rata'] / $data_baris['jumlah_mapel_aktif_nilai'] : 0;
    
    $data_baris['progres_akhir'] = $data_baris['jumlah_mapel_aktif_progres'] > 0 
        ? round($data_baris['total_progres_kumulatif'] / $data_baris['jumlah_mapel_aktif_progres']) : 0;

    if (!$filter_tuntas || ($filter_tuntas && $data_baris['progres_akhir'] >= 100)) {
        $rekap_data[] = $data_baris;
    }
}

// Urutkan Ranking
usort($rekap_data, function($a, $b) {
    return $b['rata_akhir'] <=> $a['rata_akhir'];
});

// Statistik Bimbingan
$total_n_bim = array_sum(array_column($rekap_data, 'rata_akhir'));
$total_p_bim = array_sum(array_column($rekap_data, 'progres_akhir'));
$jml_s = count($rekap_data);
$rata_nilai_bim = $jml_s > 0 ? $total_n_bim / $jml_s : 0;
$rata_prog_bim = $jml_s > 0 ? round($total_p_bim / $jml_s) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Admin | <?= htmlspecialchars($guru['nama_lengkap']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-size: 0.85rem; font-family: 'Poppins', sans-serif; }
        .table-rekap thead th { vertical-align: middle; text-align: center; font-size: 0.75rem; }
        .progress { height: 6px; border-radius: 5px; margin-top: 4px; }
        .sticky-col { position: sticky; left: 0; background: white !important; z-index: 5; border-right: 2px solid #dee2e6 !important; }
        .rank-badge { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: auto; font-size: 0.7rem; }
        .bg-stats { background: #fff; border-radius: 15px; padding: 10px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="p-4">

<div class="row mb-4 g-3 align-items-center">
    <div class="col-md-4">
        <h4 class="fw-bold text-primary mb-0"><i class="fas fa-layer-group me-2"></i> Rekap Bimbingan</h4>
        <p class="text-muted mb-0">Guru: <b><?= htmlspecialchars($guru['nama_lengkap']) ?></b></p>
    </div>
    <div class="col-md-3">
        <div class="bg-stats border-start border-primary border-4">
            <small class="text-muted d-block small">RATA NILAI BIMBINGAN</small>
            <span class="fw-bold text-primary fs-5"><?= number_format($rata_nilai_bim, 2, ',', '.') ?></span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-stats border-start border-success border-4">
            <small class="text-muted d-block small">RATA PROGRES BIMBINGAN</small>
            <span class="fw-bold text-success fs-5"><?= $rata_prog_bim ?>%</span>
        </div>
    </div>
    <div class="col-md-2 text-end">
        <a href="dashboard.php" class="btn btn-sm btn-secondary rounded-pill px-4">Kembali</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th rowspan="2">Rank</th>
                    <th rowspan="2" class="sticky-col">Nama Siswa</th>
                    <?php foreach($mapels as $m): ?> <th><?= $m['nama'] ?></th> <?php endforeach; ?>
                    <th rowspan="2" class="bg-primary">Rata Akhir</th>
                    <th rowspan="2" class="bg-success">Progres</th>
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
                    <td class="sticky-col fw-bold">
                        <?= htmlspecialchars($d['nama']) ?>
                        <div class="text-muted fw-normal" style="font-size: 0.7rem;">Kelas <?= $d['kelas'] ?></div>
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
                    <td class="text-center bg-primary text-white"><?= number_format($rata_nilai_bim, 2, ',', '.') ?></td>
                    <td class="text-center bg-success text-white"><?= $rata_prog_bim ?>%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>