<?php
// Include koneksi pusat
if (file_exists(__DIR__ . '/config/koneksi.php')) {
    include_once __DIR__ . '/config/koneksi.php';
} elseif (file_exists(__DIR__ . '/koneksi.php')) {
    include_once __DIR__ . '/koneksi.php';
}

// Fallback koneksi pusat
if (!isset($conn) || !$conn) {
    $conn = @mysqli_connect("localhost", "u906532356_admin", "Martapura06", "u906532356_db_portal");
}

$rekap_siswa = false;
$nama_dicari = "";
$detail_tabel_anak = []; 

if (isset($_GET['nama_anak']) && !empty($_GET['nama_anak']) && $conn) {
    $nama_dicari = mysqli_real_escape_string($conn, $_GET['nama_anak']);
    
    // Parameter Semester
    $semester = $_GET['sm'] ?? "2"; 
    $sufik_arsip = ($semester == "1") ? "_arsip" : "";

    // 1. Cari Data Siswa Langsung
    $q_user = "SELECT u.id, u.nama_lengkap, u.kelas, u.id_guru, g.nama_lengkap as nama_guru 
               FROM users u 
               LEFT JOIN users g ON u.id_guru = g.id 
               WHERE u.role = 'siswa' AND u.nama_lengkap LIKE '%$nama_dicari%' LIMIT 1";
    $res_user = mysqli_query($conn, $q_user);
    $siswa = $res_user ? mysqli_fetch_assoc($res_user) : null;

    if ($siswa) {
        $user_id_cari = $siswa['id'];
        $id_guru_siswa = $siswa['id_guru'];
        $nama_siswa = $siswa['nama_lengkap'];
        $kelas_siswa = $siswa['kelas'];
        $nama_guru = $siswa['nama_guru'];
        
        $mapels = [
            ['folder' => 'ipas', 'nama' => 'IPAS'],
            ['folder' => 'mtk', 'nama' => 'Matematika'],
            ['folder' => 'indo', 'nama' => 'B. Indonesia'],
            ['folder' => 'panca', 'nama' => 'Pancasila'],
            ['folder' => 'englis', 'nama' => 'B. Inggris'],
            ['folder' => 'pjok', 'nama' => 'PJOK'],
            ['folder' => 'pai', 'nama' => 'PAI'],
            ['folder' => 'mulok', 'nama' => 'B. Komering'],
            ['folder' => 'seni', 'nama' => 'Seni Rupa']
        ];

        $total_skor_s = 0; 
        $total_prog_s = 0; 
        $aktif_mp = 0; 
        $jumlah_mapel_ada_tugas = 0;

        foreach ($mapels as $m) {
            $prefix_tab = ($m['folder'] == 'panca') ? 'panca_' : '';

            // Koneksi Spesifik Mapel
            $conn_working = false;
            if (function_exists('get_mapel_connection')) {
                $conn_working = get_mapel_connection($m['folder']);
            }

            // Direct Fallback Load File Koneksi Mapel
            if (!$conn_working) {
                $path_koneksi_mapel = __DIR__ . '/' . $m['folder'] . '/config/koneksi.php';
                if (!file_exists($path_koneksi_mapel)) {
                    $path_koneksi_mapel = __DIR__ . '/' . $m['folder'] . '/koneksi.php';
                }

                if (file_exists($path_koneksi_mapel)) {
                    $old_conn = $conn; 
                    include $path_koneksi_mapel;
                    if (isset($conn) && $conn !== $old_conn) {
                        $conn_working = $conn;
                    }
                    $conn = $old_conn;
                }
            }

            if ($conn_working) {
                $tbl_kuis = "{$prefix_tab}riwayat_kuis" . $sufik_arsip;
                $tbl_tryout = "{$prefix_tab}riwayat_tryout" . $sufik_arsip;

                $cek_tbl = @mysqli_query($conn_working, "SELECT 1 FROM {$tbl_kuis} LIMIT 1");
                if (!$cek_tbl && !empty($sufik_arsip)) {
                    $tbl_kuis = "{$prefix_tab}riwayat_kuis";
                    $tbl_tryout = "{$prefix_tab}riwayat_tryout";
                }

                $rata_mapel_final = 0;
                if ($m['folder'] == 'seni') {
                    $tbl_praktek = "praktek_siswa" . $sufik_arsip;
                    $cek_praktek = @mysqli_query($conn_working, "SELECT 1 FROM {$tbl_praktek} LIMIT 1");
                    if (!$cek_praktek) { $tbl_praktek = "praktek_siswa"; }

                    $q_k_seni = mysqli_query($conn_working, "SELECT id_materi, ROUND(AVG(persentase)) as n FROM {$tbl_kuis} WHERE id_user = $user_id_cari GROUP BY id_materi");
                    $l_k = []; if($q_k_seni){ while($rk = mysqli_fetch_assoc($q_k_seni)){ $l_k[$rk['id_materi']] = $rk['n']; } }
                    
                    $q_p_seni = mysqli_query($conn_working, "SELECT materi_id, nilai_angka FROM {$tbl_praktek} WHERE id_siswa = $user_id_cari AND status_dinilai = 1");
                    $l_p = []; if($q_p_seni){ while($rp = mysqli_fetch_assoc($q_p_seni)){ $l_p[$rp['materi_id']] = $rp['nilai_angka']; } }
                    
                    $ids_m = array_unique(array_merge(array_keys($l_k), array_keys($l_p)));
                    $s_m = 0; $c_m = 0;
                    foreach($ids_m as $im){
                        $nk = $l_k[$im] ?? null; $np = $l_p[$im] ?? null;
                        $skor = ($nk !== null && $np !== null) ? round(($nk+$np)/2) : ($nk ?? $np);
                        $s_m += $skor; $c_m++;
                    }
                    $avg_m_seni = ($c_m > 0) ? round($s_m / $c_m) : 0;
                    $q_to_seni = mysqli_query($conn_working, "SELECT ROUND(AVG(persentase)) as n_to FROM {$tbl_tryout} WHERE id_user = $user_id_cari GROUP BY tryout_id");
                    $s_t = 0; $c_t = 0; if($q_to_seni){ while($rt = mysqli_fetch_assoc($q_to_seni)){ $s_t += $rt['n_to']; $c_t++; } }
                    $avg_to_seni = ($c_t > 0) ? round($s_t / $c_t) : 0;
                    
                    $vals = array_filter([$avg_m_seni, $avg_to_seni], function($v) { return $v !== null && $v !== 0; });
                    $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;
                } else {
                    $q_k = mysqli_query($conn_working, "SELECT ROUND(AVG(persentase)) as n FROM {$tbl_kuis} WHERE id_user = $user_id_cari GROUP BY id_materi");
                    $s_k = 0; $c_k = 0; if($q_k){ while($rk = mysqli_fetch_assoc($q_k)){ $s_k += $rk['n']; $c_k++; } }
                    $k_bulat = ($c_k > 0) ? round($s_k / $c_k) : null;
                    
                    $q_t = mysqli_query($conn_working, "SELECT ROUND(AVG(persentase)) as n FROM {$tbl_tryout} WHERE id_user = $user_id_cari GROUP BY tryout_id");
                    $s_t = 0; $c_t = 0; if($q_t){ while($rt = mysqli_fetch_assoc($q_t)){ $s_t += $rt['n']; $c_t++; } }
                    $t_bulat = ($c_t > 0) ? round($s_t / $c_t) : null;
                    
                    $vals = array_filter([$k_bulat, $t_bulat], function($v) { return !is_null($v); });
                    $rata_mapel_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;
                }

                $tbl_praktek_select = ($m['folder'] == 'seni') ? ($tbl_praktek ?? 'praktek_siswa') : '';
                $q_p = mysqli_query($conn_working, "SELECT (SELECT COUNT(id) FROM {$prefix_tab}materi WHERE id_guru = $id_guru_siswa) as tm, (SELECT COUNT(id) FROM {$prefix_tab}tryout_master WHERE id_guru = $id_guru_siswa) as tt, (SELECT COUNT(DISTINCT id_materi) FROM {$tbl_kuis} rk JOIN {$prefix_tab}materi m ON rk.id_materi = m.id WHERE rk.id_user = $user_id_cari AND m.id_guru = $id_guru_siswa) as ms, (SELECT COUNT(DISTINCT tryout_id) FROM {$tbl_tryout} rt JOIN {$prefix_tab}tryout_master tm ON rt.tryout_id = tm.id WHERE rt.id_user = $user_id_cari AND tm.id_guru = $id_guru_siswa) as ts" . ($m['folder'] == 'seni' ? ", (SELECT COUNT(DISTINCT materi_id) FROM {$tbl_praktek_select} WHERE id_siswa = $user_id_cari) as ps" : ""));
                $r_p = $q_p ? mysqli_fetch_assoc($q_p) : [];
                $total_tugas = ($r_p['tm'] ?? 0) + ($r_p['tt'] ?? 0);
                $total_selesai = ($r_p['ms'] ?? 0) + ($r_p['ts'] ?? 0) + ($r_p['ps'] ?? 0);
                if ($total_selesai > $total_tugas) $total_selesai = $total_tugas;
                $prog_mapel = ($total_tugas > 0) ? round(($total_selesai / $total_tugas) * 100) : 0;

                if ($total_tugas > 0) { $total_prog_s += $prog_mapel; $jumlah_mapel_ada_tugas++; }
                
                // HANYA HITUNG RATA-RATA DARI MAPEL YANG MEMILIKI NILAI
                if ($rata_mapel_final > 0) { 
                    $total_skor_s += $rata_mapel_final; 
                    $aktif_mp++; 
                }
                
                $detail_tabel_anak[$m['nama']] = ['p' => $prog_mapel, 'v' => $rata_mapel_final]; 

                mysqli_close($conn_working);
            } else {
                $detail_tabel_anak[$m['nama']] = ['p' => 0, 'v' => 0];
            }
        }

        $rata_rata_keseluruhan = ($aktif_mp > 0) ? ($total_skor_s / $aktif_mp) : 0;
        $rekap_siswa = true;

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
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PADI MONITOR ORTU</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 15px; color: #333; }
        .container { max-width: 500px; margin: auto; }
        .card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #1e52d1, #4c84ff); color: white; padding: 25px; text-align: center; }
        .search-box { padding: 15px; display: flex; gap: 8px; background: #fff; border-bottom: 1px solid #eee; }
        .search-box input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-blue { background: #1e52d1; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer; }
        .profile-section { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .score-val { font-size: 32px; font-weight: bold; color: #1e52d1; }
        .table-nilai { width: 100%; border-collapse: collapse; }
        .table-nilai th { background: #f8f9fa; padding: 12px; font-size: 12px; text-align: left; color: #666; }
        .table-nilai td { padding: 12px; border-top: 1px solid #f0f0f0; font-size: 14px; text-align: center; }
        .table-nilai td:first-child { text-align: left; font-weight: bold; }
        .badge-nilai { background: #eef2ff; color: #1e52d1; padding: 4px 8px; border-radius: 6px; font-weight: bold; }
        .btn-print { background: #28a745; color: white; border: none; padding: 15px; width: 100%; font-weight: bold; cursor: pointer; border-radius: 0 0 15px 15px; }

        #raport-cetak { display: none; }
        @media print {
            .no-print { display: none !important; }
            #raport-cetak { display: block !important; padding: 20px; }
            .tabel-raport { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .tabel-raport th, .tabel-raport td { border: 1px solid black; padding: 8px; text-align: center; }
            .tabel-raport td:nth-child(2) { text-align: left; }
        }
    </style>
</head>
<body>

<div class="container no-print">

    <div class="card">
        <div class="card-header">
            <h2 style="margin:0">PADI MONITOR ORANG TUA</h2>
            <small>Sistem Pantau Nilai Real-Time</small>
        </div>
        
        <form action="" method="GET" class="search-box">
            <input type="hidden" name="sm" value="<?= htmlspecialchars($semester ?? '2') ?>">
            <input type="text" name="nama_anak" placeholder="Masukkan nama anak" value="<?= htmlspecialchars($nama_dicari) ?>" required>
            <button type="submit" class="btn-blue">🔍</button>
        </form>

        <?php if($rekap_siswa): ?>
        <div class="profile-section">
            <div>
                <h3 style="margin:0; color:#1e52d1"><?= strtoupper($nama_siswa) ?></h3>
                <small>Kelas <?= $kelas_siswa ?> | Guru: <?= $nama_guru ?></small>
            </div>
            <div style="text-align:right">
                <small>RATA-RATA</small><br>
                <span class="score-val"><?= number_format($rata_rata_keseluruhan, 2) ?></span>
            </div>
        </div>

        <table class="table-nilai">
            <thead>
                <tr>
                    <th>Mata Pelajaran</th>
                    <th>Progres</th>
                    <th>Nilai</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            foreach($urutan_tampil as $key_asal => $nama_tampilan): 
                $data = $detail_tabel_anak[$key_asal] ?? ['p' => 0, 'v' => 0];
            ?>
            <tr>
                <td><?= $nama_tampilan ?></td>
                <td class="text-center"><?= $data['p'] ?>%</td>
                <td class="text-center"><span class="badge-nilai"><?= isset($data['v']) ? $data['v'] : '0' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn-print" onclick="window.print()">📥 Lihat Raport Sementara</button>
        <?php elseif($nama_dicari != ""): ?>
            <div style="padding:20px; text-align:center; color:red;">
                Siswa tidak ditemukan.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if($rekap_siswa): ?>
<div id="raport-cetak">
    <div style="text-align:center; margin-bottom: 20px;">
        <h3 style="text-decoration:underline; margin-bottom:5px; font-family: Arial, sans-serif;">LAPORAN HASIL BELAJAR (RAPOR)</h3>
    </div>
    
    <table style="width:100%; font-size:12px; font-family: Arial, sans-serif; line-height: 1.5;">
        <tr>
            <td width="15%">Nama Murid</td><td width="40%">: <b><?= $nama_siswa ?></b></td>
            <td width="15%">Kelas</td><td>: <?= $kelas_siswa ?></td>
        </tr>
        <tr>
            <td>Sekolah</td><td>: SD Negeri 06 Martapura</td>
            <td>Semester</td><td>: <?= $semester == '2' ? 'I (Ganjil)' : 'I (Ganjil)' ?></td>
        </tr>
        <tr>
            <td>Tahun Pelajaran</td><td>: 2025/2026</td>
            <td>Fase</td><td>: C</td>
        </tr>
    </table>

    <table style="width:100%; border-collapse: collapse; margin-top:15px; border: 1.5px solid black; font-family: Arial, sans-serif; font-size: 12px;">
        <thead>
            <tr>
                <th style="border:1px solid black; padding:8px;" width="5%">No</th>
                <th style="border:1px solid black; padding:8px;">Mata Pelajaran</th>
                <th style="border:1px solid black; padding:8px;" width="15%">Nilai Akhir</th>
                <th style="border:1px solid black; padding:8px;" width="40%">Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $no = 1;
        $total_nilai = 0;
        foreach ($urutan_tampil as $key_asal => $nama_tampilan): 
            $nilai_mapel = $detail_tabel_anak[$key_asal]['v'] ?? 0;
            $total_nilai += $nilai_mapel;
        ?>
        <tr>
            <td style="border:1px solid black; padding:8px; text-align:center;"><?= $no++ ?></td>
            <td style="border:1px solid black; padding:8px;"><?= $nama_tampilan ?></td>
            <td style="border:1px solid black; padding:8px; text-align:center; font-weight:bold;"><?= $nilai_mapel ?></td>
            <td style="border:1px solid black; padding:8px; font-size:10px; text-align:left;">
                Menunjukkan penguasaan yang baik dalam memahami materi pembelajaran semester ini.
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;">
                <td colspan="2" style="border:1px solid black; padding:6px; text-align:center;">Jumlah</td>
                <td style="border:1px solid black; padding:6px; text-align:center;"><?= $total_nilai ?></td>
                <td style="border:1px solid black; padding:6px;"></td>
            </tr>
            <tr style="font-weight:bold;">
                <td colspan="2" style="border:1px solid black; padding:6px; text-align:center;">Rata-rata</td>
                <td style="border:1px solid black; padding:6px; text-align:center;"><?= number_format($rata_rata_keseluruhan, 2) ?></td>
                <td style="border:1px solid black; padding:6px;"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top:15px; font-size:10px; font-style: italic; font-family: Arial, sans-serif; line-height: 1.4;">
        Catatan: Nilai raport ini masih terus dapat berubah sampai akhir semester. Perhitungan nilai akhir akan diakumulasikan kembali dengan aktivitas siswa di kelas, tugas portofolio, dan penilaian sikap harian. Namun, gambaran nilai raport asli tidak akan jauh berbeda dengan data di atas.
    </div>

    <div style="margin-top:30px; float:right; text-align:center; width:250px; font-family: Arial, sans-serif; font-size: 12px;">
        Martapura, <?= date('d F Y') ?><br>
        Guru Kelas,<br><br><br><br><br>
        ......................................................<br>
        <b><?= $nama_guru ?></b>
    </div>
</div>
<?php endif; ?>

</body>
</html>