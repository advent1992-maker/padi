<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) { exit; }

$user_id_cari = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama_lengkap'];
$kelas_siswa = $_SESSION['kelas'] ?? '5';
$semester = $_SESSION['semester_aktif'] ?? '2';
$id_guru_siswa = $_SESSION['id_guru'] ?? 0;

$db_host = $host;
$db_pass = $pass;
$db_pref = $prefix;

$mapels = [
    ['folder' => 'ipas', 'nama' => 'IPAS', 'user' => 'hari'],
    ['folder' => 'mtk', 'nama' => 'Matematika', 'user' => 'advent'],
    ['folder' => 'indo', 'nama' => 'B. Indonesia', 'user' => 'harrieya'],
    ['folder' => 'panca', 'nama' => 'Pancasila', 'user' => 'adventgool'],
    ['folder' => 'englis', 'nama' => 'B. Inggris', 'user' => 'kris'],
    ['folder' => 'pjok', 'nama' => 'PJOK', 'user' => 'derry'],
    ['folder' => 'pai', 'nama' => 'PAI', 'user' => 'arq'],
    ['folder' => 'mulok', 'nama' => 'B. Komering', 'user' => 'kristian'],
    ['folder' => 'seni', 'nama' => 'Seni Rupa', 'user' => 'senirupa']
];

$semua_skor_kelas = [];
$detail_tabel_anak = [];
$res_kelas = mysqli_query($conn, "SELECT id FROM users WHERE role = 'siswa' AND id_guru = $id_guru_siswa");

while ($s_kelas = mysqli_fetch_assoc($res_kelas)) {
    $id_temp = $s_kelas['id'];
    $total_skor_s = 0; $total_prog_s = 0; $aktif_mp = 0; $jumlah_mapel_ada_tugas = 0;
    $n_pai = 0; $n_panca = 0; $n_indo = 0;

    foreach ($mapels as $m) {
        $prefix_tab = ($m['folder'] == 'panca') ? 'panca_' : '';
        if ($m['folder'] == 'panca') {
            $db_target = $db_pref . "db_mulok_sm" . $semester;
            $user_target = $db_pref . "kristian";
        } else {
            $db_target = $db_pref . "db_" . $m['folder'] . "_sm" . $semester;
            $user_target = $db_pref . $m['user'];
        }

        $conn_temp = @mysqli_connect($db_host, $user_target, $db_pass, $db_target);

        if ($conn_temp) {
            $rata_mapel_final = 0;

            if ($m['folder'] == 'seni') {
                // LOGIKA BERJENJANG SENI (Kuis per bab + Praktek per bab)
                $q_k_seni = mysqli_query($conn_temp, "SELECT id_materi, ROUND(AVG(persentase)) as n FROM riwayat_kuis WHERE id_user = $id_temp GROUP BY id_materi");
                $l_k = []; while($rk = mysqli_fetch_assoc($q_k_seni)){ $l_k[$rk['id_materi']] = $rk['n']; }
                
                $q_p_seni = mysqli_query($conn_temp, "SELECT materi_id, nilai_angka FROM praktek_siswa WHERE id_siswa = $id_temp AND status_dinilai = 1");
                $l_p = []; while($rp = mysqli_fetch_assoc($q_p_seni)){ $l_p[$rp['materi_id']] = $rp['nilai_angka']; }

                $ids_m = array_unique(array_merge(array_keys($l_k), array_keys($l_p)));
                $s_m = 0; $c_m = 0;
                foreach($ids_m as $im){
                    $nk = $l_k[$im] ?? null; $np = $l_p[$im] ?? null;
                    $skor = ($nk !== null && $np !== null) ? round(($nk+$np)/2) : ($nk ?? $np);
                    $s_m += $skor; $c_m++;
                }
                $avg_m_seni = ($c_m > 0) ? round($s_m / $c_m) : 0;

                $q_to_seni = mysqli_query($conn_temp, "SELECT ROUND(AVG(persentase)) as n_to FROM riwayat_tryout WHERE id_user = $id_temp GROUP BY tryout_id");
                $s_t = 0; $c_t = 0; while($rt = mysqli_fetch_assoc($q_to_seni)){ $s_t += $rt['n_to']; $c_t++; }
                $avg_to_seni = ($c_t > 0) ? round($s_t / $c_t) : 0;

                $vals = array_filter([$avg_m_seni, $avg_to_seni]);
                $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;

            } else {
                // LOGIKA BERJENJANG UMUM (Kuis per bab & TO per judul)
                $q_k = mysqli_query($conn_temp, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_kuis WHERE id_user = $id_temp GROUP BY id_materi");
                $s_k = 0; $c_k = 0; while($rk = mysqli_fetch_assoc($q_k)){ $s_k += $rk['n']; $c_k++; }
                $k_bulat = ($c_k > 0) ? round($s_k / $c_k) : null;

                $q_t = mysqli_query($conn_temp, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_tryout WHERE id_user = $id_temp GROUP BY tryout_id");
                $s_t = 0; $c_t = 0; while($rt = mysqli_fetch_assoc($q_t)){ $s_t += $rt['n']; $c_t++; }
                $t_bulat = ($c_t > 0) ? round($s_t / $c_t) : null;

                $vals = array_filter([$k_bulat, $t_bulat], function($v) { return !is_null($v); });
                $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;
            }

            // HITUNG PROGRES
            $q_p = mysqli_query($conn_temp, "SELECT (SELECT COUNT(id) FROM {$prefix_tab}materi WHERE id_guru = $id_guru_siswa) as tm, (SELECT COUNT(id) FROM {$prefix_tab}tryout_master WHERE id_guru = $id_guru_siswa) as tt, (SELECT COUNT(DISTINCT id_materi) FROM {$prefix_tab}riwayat_kuis rk JOIN {$prefix_tab}materi m ON rk.id_materi = m.id WHERE rk.id_user = $id_temp AND m.id_guru = $id_guru_siswa) as ms, (SELECT COUNT(DISTINCT tryout_id) FROM {$prefix_tab}riwayat_tryout rt JOIN {$prefix_tab}tryout_master tm ON rt.tryout_id = tm.id WHERE rt.id_user = $id_temp AND tm.id_guru = $id_guru_siswa) as ts" . ($m['folder'] == 'seni' ? ", (SELECT COUNT(DISTINCT materi_id) FROM praktek_siswa WHERE id_siswa = $id_temp) as ps" : ""));
            $r_p = mysqli_fetch_assoc($q_p);
            $total_tugas = ($r_p['tm'] ?? 0) + ($r_p['tt'] ?? 0);
            $total_selesai = ($r_p['ms'] ?? 0) + ($r_p['ts'] ?? 0) + ($r_p['ps'] ?? 0);
            if ($total_selesai > $total_tugas) $total_selesai = $total_tugas;
            $prog_mapel = ($total_tugas > 0) ? round(($total_selesai / $total_tugas) * 100) : 0;

            if ($total_tugas > 0) { $total_prog_s += $prog_mapel; $jumlah_mapel_ada_tugas++; }
            if ($rata_mapel_final > 0 || $prog_mapel > 0) { $total_skor_s += $rata_mapel_final; $aktif_mp++; }
            
            // Simpan detail hanya untuk siswa yang dicetak raportnya
            if ($id_temp == $user_id_cari) { $detail_tabel_anak[] = ['n' => $m['nama'], 'v' => $rata_mapel_final]; }
            
            if ($m['folder'] == 'pai') $n_pai = $rata_mapel_final;
            if ($m['folder'] == 'panca') $n_panca = $rata_mapel_final;
            if ($m['folder'] == 'indo') $n_indo = $rata_mapel_final;
            mysqli_close($conn_temp);
        }
    }
    $semua_skor_kelas[$id_temp] = ['skor' => ($aktif_mp > 0 ? ($total_skor_s / $aktif_mp) : 0), 'prog' => ($jumlah_mapel_ada_tugas > 0 ? round($total_prog_s / $jumlah_mapel_ada_tugas) : 0), 'pai' => $n_pai, 'panca' => $n_panca, 'indo' => $n_indo];
}

// URUTAN PERINGKAT TIE-BREAKER
uasort($semua_skor_kelas, function($a, $b) {
    if (round($b['skor'], 2) != round($a['skor'], 2)) return $b['skor'] <=> $a['skor'];
    if ($b['pai'] != $a['pai']) return $b['pai'] <=> $a['pai'];
    if ($b['panca'] != $a['panca']) return $b['panca'] <=> $a['panca'];
    return $b['indo'] <=> $a['indo'];
});

$rank_display = "-"; $temp_rank = 1;
$total_lulus_100 = 0;
foreach ($semua_skor_kelas as $sid => $val) {
    if ($val['prog'] >= 100) {
        if ($sid == $user_id_cari) $rank_display = $temp_rank;
        $temp_rank++;
        $total_lulus_100++;
    }
}
$my_stats = $semua_skor_kelas[$user_id_cari] ?? ['skor' => 0, 'prog' => 0];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Raport_<?= $nama_siswa ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; background: #eee; }
        .paper { width: 210mm; min-height: 297mm; margin: 10px auto; background: white; padding: 10mm 15mm; box-sizing: border-box; border: 1px solid #ccc; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 7px; }
        .text-center { text-align: center; }
        .bg-light { background-color: #f2f2f2; font-weight: bold; }
        @media print { body { background: white; } .paper { margin: 0; box-shadow: none; border: none; width: 100%; } }
    </style>
</head>
<body>
    <div class="paper">
        <h3 class="text-center" style="text-decoration: underline; margin-bottom: 20px;">LAPORAN HASIL BELAJAR (RAPOR)</h3>
        
        <table style="border:none; margin-bottom: 15px;">
            <tr style="border:none;">
                <td style="border:none;" width="15%">Nama Murid</td><td style="border:none;" width="45%">: <b><?= $nama_siswa ?></b></td>
                <td style="border:none;" width="12%">Kelas</td><td style="border:none;">: <?= $kelas_siswa ?></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Sekolah</td><td style="border:none;">: SD Negeri 06 Martapura</td>
                <td style="border:none;">Semester</td><td style="border:none;">: II (Genap)</td>
            </tr>
        </table>

        <table>
            <thead>
                <tr class="bg-light text-center">
                    <th width="35px">No</th>
                    <th>Mata Pelajaran</th>
                    <th width="70px">Nilai Akhir</th>
                    <th width="350px">Capaian Kompetensi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $urutan_tampil = [
                    'PAI' => 'Pendidikan Agama',
                    'Pancasila' => 'Pendidikan Pancasila',
                    'B. Indonesia' => 'Bahasa Indonesia',
                    'Matematika' => 'Matematika',
                    'IPAS' => 'Ilmu Pengetahuan Alam dan Sosial',
                    'Seni Rupa' => 'Seni Rupa',
                    'PJOK' => 'Pendidikan Jasmani dan Olahraga',
                    'B. Inggris' => 'Bahasa Inggris *',
                    'B. Komering' => 'Budaya Komering'
                ];

                $no = 1; $total_jumlah_tabel = 0;
                foreach ($urutan_tampil as $key_asal => $nama_tampilan): 
                    $nilai_mapel = 0;
                    foreach($detail_tabel_anak as $d) {
                        if($d['n'] == $key_asal) { $nilai_mapel = $d['v']; break; }
                    }
                    $total_jumlah_tabel += $nilai_mapel;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $nama_tampilan ?></td>
                    <td class="text-center"><b><?= $nilai_mapel ?: '0' ?></b></td>
                    <td style="font-size: 10px; line-height: 1.3;">Menunjukkan penguasaan yang baik dalam memahami materi pembelajaran semester ini.</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light"><td colspan="2" class="text-center">Jumlah</td><td class="text-center"><?= $total_jumlah_tabel ?></td><td></td></tr>
                <tr class="bg-light"><td colspan="2" class="text-center">Rata-rata</td><td class="text-center"><?= number_format($my_stats['skor'], 2) ?></td><td></td></tr>
                <?php if($my_stats['prog'] >= 100): ?>
                <tr class="bg-light"><td colspan="2" class="text-center">Peringkat</td><td class="text-center"><?= $rank_display ?></td><td class="text-center">Dari <?= ($temp_rank-1) ?> Siswa</td></tr>
                <?php endif; ?>
            </tfoot>
        </table>
        
        <div style="margin-top: 30px; float: right; width: 250px; text-align: center;">
            Martapura, <?= date('d F Y') ?><br> Guru Kelas,<br><br><br><br><br><b>...........................................</b>
        </div>
    </div>
</body>
</html>