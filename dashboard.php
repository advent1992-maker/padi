<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

// Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$namaUser = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];
$semester = $_SESSION['semester_aktif'] ?? '2';

/*
session = 2  -> Semester 1 (Aktif)
session = 1  -> Semester 2 (Arsip)
*/

$folder_sem = "sem2";
$tbl_suffix = ($semester == "1") ? "_arsip" : "";

$label_semester = ($semester == "2")
    ? "Semester 1"
    : "Semester 2 (Arsip)";

// Simpan DB Utama
$db_utama = "";
$res_db_utama = mysqli_query($conn, "SELECT DATABASE()");
if ($res_db_utama && $row_db = mysqli_fetch_row($res_db_utama)) {
    $db_utama = $row_db[0];
}

// Prefix Hostinger dari DB Utama (contoh: u906532356_)
$db_prefix = "u906532356_";
if (!empty($db_utama) && strpos($db_utama, '_') !== false) {
    $parts = explode('_', $db_utama);
    $db_prefix = $parts[0] . "_";
}

// 1. Ambil ID Guru Siswa
$id_guru_siswa = 0;
$stmt_check = $conn->prepare("SELECT id_guru FROM users{$tbl_suffix} WHERE id=?");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

if ($row_check = $res_check->fetch_assoc()) {
    $id_guru_siswa = (int)$row_check['id_guru'];
}
$stmt_check->close();

$nama_guru_pembimbing = "N/A";
if ($id_guru_siswa > 0) {
    $stmt_g = $conn->prepare("SELECT nama_lengkap FROM users{$tbl_suffix} WHERE id = ? AND role = 'guru'");
    $stmt_g->bind_param("i", $id_guru_siswa);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();
    if ($row_g = $res_g->fetch_assoc()) {
        $nama_guru_pembimbing = $row_g['nama_lengkap'];
    }
    $stmt_g->close();
}

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

$tugas_belum_selesai = [];
$res_kelas = mysqli_query($conn, "SELECT id FROM users{$tbl_suffix} WHERE role = 'siswa' AND id_guru = $id_guru_siswa");

$data_siswa_kelas = [];
if ($res_kelas && mysqli_num_rows($res_kelas) > 0) {
    while ($s_kelas = mysqli_fetch_assoc($res_kelas)) {
        $data_siswa_kelas[$s_kelas['id']] = [
            'total_skor_s' => 0, 'total_prog_s' => 0, 'aktif_mp' => 0, 'jumlah_mapel_ada_tugas' => 0,
            'n_pai' => 0, 'n_panca' => 0, 'n_indo' => 0
        ];
    }
} else {
    $data_siswa_kelas[$user_id] = [
        'total_skor_s' => 0, 'total_prog_s' => 0, 'aktif_mp' => 0, 'jumlah_mapel_ada_tugas' => 0,
        'n_pai' => 0, 'n_panca' => 0, 'n_indo' => 0
    ];
}

$semua_skor_kelas = [];

foreach ($mapels as $m) {
    $prefix_tab = ($m['folder'] == 'panca') ? 'panca_' : '';
    
    // Dapatkan koneksi khusus mapel melalui helper function
    $conn_m = get_mapel_connection($m['folder']);
    if (!$conn_m || $conn_m === $conn) {
        continue; // Lewati jika gagal terkoneksi ke database mapel
    }

    // =========================================================================
    // LOGIKA PERBAIKAN: Penarikan Tugas/Materi/Tryout Belum Selesai (Khusus User Login)
    // =========================================================================
    if ($id_guru_siswa > 0) {
        // 1. Cek Materi / Kuis yang Belum Dikerjakan
        $sql_materi_undone = "
            SELECT id, judul FROM {$prefix_tab}materi{$tbl_suffix} 
            WHERE id_guru = $id_guru_siswa 
            AND id NOT IN (
                SELECT DISTINCT id_materi FROM {$prefix_tab}riwayat_kuis{$tbl_suffix} WHERE id_user = $user_id
            )
        ";
        $q_materi_undone = @mysqli_query($conn_m, $sql_materi_undone);
        
        // Fallback jika tabel materi/riwayat tidak menggunakan $tbl_suffix
        if (!$q_materi_undone) {
            $sql_materi_undone_fb = "
                SELECT id, judul FROM {$prefix_tab}materi 
                WHERE id_guru = $id_guru_siswa 
                AND id NOT IN (
                    SELECT DISTINCT id_materi FROM {$prefix_tab}riwayat_kuis WHERE id_user = $user_id
                )
            ";
            $q_materi_undone = @mysqli_query($conn_m, $sql_materi_undone_fb);
        }

        if ($q_materi_undone) {
            while ($rm = mysqli_fetch_assoc($q_materi_undone)) {
                $tugas_belum_selesai[] = [
                    'mapel'  => $m['nama'],
                    'jenis'  => 'Kuis / Materi',
                    'judul'  => $rm['judul'],
                    'folder' => $m['folder']
                ];
            }
        }

        // 2. Cek Tryout yang Belum Dikerjakan
        $sql_to_undone = "
            SELECT id, judul FROM {$prefix_tab}tryout_master{$tbl_suffix} 
            WHERE id_guru = $id_guru_siswa 
            AND id NOT IN (
                SELECT DISTINCT tryout_id FROM {$prefix_tab}riwayat_tryout{$tbl_suffix} WHERE id_user = $user_id
            )
        ";
        $q_to_undone = @mysqli_query($conn_m, $sql_to_undone);

        // Fallback jika tabel tryout_master/riwayat_tryout tidak menggunakan $tbl_suffix
        if (!$q_to_undone) {
            $sql_to_undone_fb = "
                SELECT id, judul FROM {$prefix_tab}tryout_master 
                WHERE id_guru = $id_guru_siswa 
                AND id NOT IN (
                    SELECT DISTINCT tryout_id FROM {$prefix_tab}riwayat_tryout WHERE id_user = $user_id
                )
            ";
            $q_to_undone = @mysqli_query($conn_m, $sql_to_undone_fb);
        }

        if ($q_to_undone) {
            while ($rt = mysqli_fetch_assoc($q_to_undone)) {
                $tugas_belum_selesai[] = [
                    'mapel'  => $m['nama'],
                    'jenis'  => 'Tryout',
                    'judul'  => $rt['judul'],
                    'folder' => $m['folder']
                ];
            }
        }
    }

    foreach ($data_siswa_kelas as $id_temp => &$ds) {
        $rata_mapel_final = 0;

        if ($m['folder'] == 'seni') {
            $q_k_seni = @mysqli_query($conn_m, "SELECT id_materi, ROUND(AVG(persentase)) as n FROM riwayat_kuis{$tbl_suffix} WHERE id_user = $id_temp GROUP BY id_materi");
            if (!$q_k_seni) {
                $q_k_seni = mysqli_query($conn_m, "SELECT id_materi, ROUND(AVG(persentase)) as n FROM riwayat_kuis WHERE id_user = $id_temp GROUP BY id_materi");
            }
            $l_k = []; 
            if ($q_k_seni) { 
                while($rk = mysqli_fetch_assoc($q_k_seni)){ if ($rk['n'] !== null) $l_k[$rk['id_materi']] = (float)$rk['n']; } 
            }
            
            $q_p_seni = @mysqli_query($conn_m, "SELECT materi_id, nilai_angka FROM praktek_siswa{$tbl_suffix} WHERE id_siswa = $id_temp AND status_dinilai = 1");
            if (!$q_p_seni) {
                $q_p_seni = mysqli_query($conn_m, "SELECT materi_id, nilai_angka FROM praktek_siswa WHERE id_siswa = $id_temp AND status_dinilai = 1");
            }
            $l_p = []; 
            if ($q_p_seni) { 
                while($rp = mysqli_fetch_assoc($q_p_seni)){ if ($rp['nilai_angka'] !== null) $l_p[$rp['materi_id']] = (float)$rp['nilai_angka']; } 
            }
            
            $ids_m = array_unique(array_merge(array_keys($l_k), array_keys($l_p)));
            $s_m = 0; $c_m = 0;
            foreach($ids_m as $im){
                $nk = $l_k[$im] ?? null; 
                $np = $l_p[$im] ?? null;
                $skor = ($nk !== null && $np !== null) ? round(($nk+$np)/2) : ($nk ?? $np);
                if ($skor !== null) { $s_m += $skor; $c_m++; }
            }
            $avg_m_seni = ($c_m > 0) ? round($s_m / $c_m) : 0;
            
            $q_to_seni = @mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n_to FROM riwayat_tryout{$tbl_suffix} WHERE id_user = $id_temp GROUP BY tryout_id");
            if (!$q_to_seni) {
                $q_to_seni = mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n_to FROM riwayat_tryout WHERE id_user = $id_temp GROUP BY tryout_id");
            }
            $s_t = 0; $c_t = 0; 
            if ($q_to_seni) { 
                while($rt = mysqli_fetch_assoc($q_to_seni)){ if ($rt['n_to'] !== null) { $s_t += $rt['n_to']; $c_t++; } } 
            }
            $avg_to_seni = ($c_t > 0) ? round($s_t / $c_t) : 0;
            
            $v_seni = array_filter([$avg_m_seni, $avg_to_seni], function($v) { return $v > 0; });
            $rata_mapel_final = count($v_seni) > 0 ? round(array_sum($v_seni) / count($v_seni)) : 0;

        } else {
            $q_k = @mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_kuis{$tbl_suffix} WHERE id_user = $id_temp GROUP BY id_materi");
            if (!$q_k) {
                $q_k = mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_kuis WHERE id_user = $id_temp GROUP BY id_materi");
            }
            $s_k = 0; $c_k = 0; 
            if ($q_k) { 
                while($rk = mysqli_fetch_assoc($q_k)){ if ($rk['n'] !== null) { $s_k += $rk['n']; $c_k++; } } 
            }
            $k_bulat = ($c_k > 0) ? round($s_k / $c_k) : null;
            
            $q_t = @mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_tryout{$tbl_suffix} WHERE id_user = $id_temp GROUP BY tryout_id");
            if (!$q_t) {
                $q_t = mysqli_query($conn_m, "SELECT ROUND(AVG(persentase)) as n FROM {$prefix_tab}riwayat_tryout WHERE id_user = $id_temp GROUP BY tryout_id");
            }
            $s_t = 0; $c_t = 0; 
            if ($q_t) { 
                while($rt = mysqli_fetch_assoc($q_t)){ if ($rt['n'] !== null) { $s_t += $rt['n']; $c_t++; } } 
            }
            $t_bulat = ($c_t > 0) ? round($s_t / $c_t) : null;
            
            $v_umum = array_filter([$k_bulat, $t_bulat], function($v) { return !is_null($v); });
            $rata_mapel_final = count($v_umum) > 0 ? round(array_sum($v_umum) / count($v_umum)) : 0;
        }

        // Progres Tugas
        $q_p = @mysqli_query($conn_m, "SELECT 
            (SELECT COUNT(id) FROM {$prefix_tab}materi{$tbl_suffix} WHERE id_guru = $id_guru_siswa) as tm, 
            (SELECT COUNT(id) FROM {$prefix_tab}tryout_master{$tbl_suffix} WHERE id_guru = $id_guru_siswa) as tt, 
            (SELECT COUNT(DISTINCT id_materi) FROM {$prefix_tab}riwayat_kuis{$tbl_suffix} WHERE id_user = $id_temp) as ms, 
            (SELECT COUNT(DISTINCT tryout_id) FROM {$prefix_tab}riwayat_tryout{$tbl_suffix} WHERE id_user = $id_temp) as ts" . 
            ($m['folder'] == 'seni' ? ", (SELECT COUNT(DISTINCT materi_id) FROM praktek_siswa{$tbl_suffix} WHERE id_siswa = $id_temp AND status_dinilai = 1) as ps" : ""));
        
        $r_p = ($q_p) ? mysqli_fetch_assoc($q_p) : [];
        $total_tugas = ($r_p['tm'] ?? 0) + ($r_p['tt'] ?? 0);
        $total_selesai = ($r_p['ms'] ?? 0) + ($r_p['ts'] ?? 0) + ($r_p['ps'] ?? 0);
        if ($total_selesai > $total_tugas) $total_selesai = $total_tugas;
        $prog_mapel = ($total_tugas > 0) ? round(($total_selesai / $total_tugas) * 100) : 0;

        if ($total_tugas > 0) { $ds['total_prog_s'] += $prog_mapel; $ds['jumlah_mapel_ada_tugas']++; }
        if ($rata_mapel_final > 0) { $ds['total_skor_s'] += $rata_mapel_final; $ds['aktif_mp']++; }
        if ($m['folder'] == 'pai') $ds['n_pai'] = $rata_mapel_final;
        if ($m['folder'] == 'panca') $ds['n_panca'] = $rata_mapel_final;
        if ($m['folder'] == 'indo') $ds['n_indo'] = $rata_mapel_final;
    }
    unset($ds);

    // Tutup koneksi mapel setelah pengolahan data selesai untuk mapel ini
    mysqli_close($conn_m);
}

// Rekap Rata-rata Akhir
foreach ($data_siswa_kelas as $id_temp => $ds) {
    $semua_skor_kelas[$id_temp] = [
        'skor' => $ds['aktif_mp'] > 0 ? round($ds['total_skor_s'] / $ds['aktif_mp'], 2) : 0,
        'prog' => $ds['jumlah_mapel_ada_tugas'] > 0 ? round($ds['total_prog_s'] / $ds['jumlah_mapel_ada_tugas']) : 0,
        'pai' => $ds['n_pai'],
        'panca' => $ds['n_panca'],
        'indo' => $ds['n_indo']
    ];
}

// Sorting Peringkat
uasort($semua_skor_kelas, function($a, $b) {
    if ($b['skor'] != $a['skor']) return $b['skor'] <=> $a['skor'];
    if ($b['pai'] != $a['pai']) return $b['pai'] <=> $a['pai'];
    if ($b['panca'] != $a['panca']) return $b['panca'] <=> $a['panca'];
    return $b['indo'] <=> $a['indo'];
});

// Penentuan Peringkat
$rank_display = "-"; 
$temp_rank = 1;
$total_lulus_100 = 0;

foreach ($semua_skor_kelas as $sid => $val) {
    if ($val['prog'] >= 100) {
        $total_lulus_100++;
        if ($sid == $user_id) {
            $rank_display = "Peringkat " . $temp_rank;
        }
        $temp_rank++;
    } else {
        if ($sid == $user_id) {
            $rank_display = "Selesaikan progres 100% untuk melihat peringkat";
        }
    }
}

$my_stats = $semua_skor_kelas[$user_id] ?? ['skor' => 0, 'prog' => 0];
$cek_uji_siswa = mysqli_query($conn, "SELECT id FROM hasil_uji_siswa WHERE id_user = '$user_id' AND kode_aplikasi = 'PADI_PORTAL' LIMIT 1");
$sudah_isi_padi = ($cek_uji_siswa && mysqli_num_rows($cek_uji_siswa) > 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PADI | Dashboard Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #764ba2; --bg-light: #f4f7fe; }
        body { background-color: var(--bg-light); font-family: 'Poppins', sans-serif; margin: 0; }

        .navbar-custom { padding: 15px 0; position: absolute; width: 100%; z-index: 100; }
        
        .dropdown-menu { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .dropdown-item { padding: 10px 20px; font-weight: 500; transition: 0.2s; }
        .dropdown-item:hover { background-color: #f8f9fa; color: var(--primary-color); }

        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 100px 0 70px 0; border-radius: 0 0 50px 50px;
            margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-card-siswa {
            background: white; border-radius: 20px; border: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05); margin-top: -50px;
        }
        .stat-val { font-size: 1.5rem; font-weight: 700; color: #764ba2; }
        .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px; }

        .subject-card {
            border: none; border-radius: 25px; transition: all 0.3s ease;
            cursor: pointer; background: white; overflow: hidden; height: 100%;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05); text-align: center;
        }
        .subject-card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); }

        .icon-box { height: 120px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; }

        .bg-ipas { background: linear-gradient(45deg, #20c997, #009688); }
        .bg-math { background: linear-gradient(45deg, #0d6efd, #004db7); }
        .bg-indo { background: linear-gradient(45deg, #e65100, #ff8c00); }
        .bg-pancasila { background: linear-gradient(45deg, #dc3545, #b71c1c); }
        .bg-english { background: linear-gradient(45deg, #6f42c1, #4527a0); }
        .bg-pjok { background: linear-gradient(45deg, #fd7e14, #ffb300); }
        .bg-pai { background: linear-gradient(45deg, #0dcaf0, #0097a7); }
        .bg-mulok { background: linear-gradient(45deg, #6c757d, #424242); }

        .semester-badge { background: rgba(255,255,255,0.2); padding: 5px 20px; border-radius: 50px; display: inline-block; margin-top: 15px; }
        
        .guide-banner { border-radius: 25px; border-left: 6px solid #764ba2; background: #fff; }

        /* Gaya Mewah List Hutang */
        .list-mewah { background: white; border-radius: 25px; border-left: 8px solid #dc3545; box-shadow: 0 15px 35px rgba(220, 53, 69, 0.1); }
        .materi-item { border-bottom: 1px solid #f8f8f8; padding: 10px 5px; }
        .materi-item:last-child { border-bottom: none; }

        #ai-chat-container {
            position: fixed; bottom: 20px; right: 20px; z-index: 9999;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        #ai-chat-button {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none; color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center;
        }
        #ai-chat-window {
            width: 350px; height: 450px; background: white; border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: none;
            flex-direction: column; overflow: hidden; position: absolute;
            bottom: 100px; right: 0;
        }
        .chat-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .chat-body { flex: 1; padding: 15px; overflow-y: auto; background: #f8f9fa; display: flex; flex-direction: column; gap: 10px; }
        .chat-footer { padding: 10px; background: white; border-top: 1px solid #eee; display: flex; gap: 5px; }
        .msg { max-width: 80%; padding: 10px 15px; border-radius: 15px; font-size: 0.9rem; line-height: 1.4; }
        .msg-ai { background: #e9ecef; align-self: flex-start; border-bottom-left-radius: 2px; color: #333; }
        .msg-user { background: #764ba2; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .typing { font-style: italic; font-size: 0.8rem; color: #888; margin-bottom: 5px; display: none; }
        
        .btn-eye { border-left: none; background: transparent; color: #bbb; }
        .input-group-text { background: white; border-left: none; color: #bbb; cursor: pointer; }
        .bg-seni { background: linear-gradient(45deg, #f06292, #e91e63); }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="container d-flex justify-content-end align-items-center">
        <a href="tentang.php" class="btn btn-sm btn-light rounded-pill px-3 me-2 fw-bold text-primary shadow-sm"><i class="fas fa-info-circle me-1"></i>Tentang</a>
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle rounded-pill px-3 fw-bold shadow-sm" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($namaUser) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li><a class="dropdown-item" href="profil.php"><i class="fas fa-id-card me-2 text-primary"></i> Profil</a></li>
                <li><a class="dropdown-item" href="cetak_raport.php" target="_blank"><i class="fas fa-print me-2 text-success"></i> Lihat Raport</a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalGantiPass"><i class="fas fa-key me-2 text-warning"></i> Ganti Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger fw-bold" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-5 fw-bold">Halo, <?= htmlspecialchars($namaUser) ?>!</h1>
        <div class="semester-badge">
            <i class="fas fa-calendar-check me-2"></i> Status : <strong><?= $label_semester ?></strong>
            <p class="lead mt-2 text-white-50">Guru Pembimbing Anda: <strong><?= htmlspecialchars($nama_guru_pembimbing) ?></strong></p>
        </div>
    </div>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card stat-card-siswa p-4 mb-4 text-center">
                <div class="row">
                    <div class="col-4 border-end">
                        <div class="stat-label">Rata-rata Nilai Raport</div>
                        <div class="stat-val"><?= number_format($my_stats['skor'], 2, ',', '.') ?></div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="stat-label">Total Progres</div>
                        <div class="stat-val text-success"><?= $my_stats['prog'] ?>%</div>
                    </div>
                    <div class="col-4">
                        <div class="stat-value">
   <?php if ($my_stats['prog'] >= 100): ?>
    <i class="fas fa-medal text-warning" style="font-size: 1.2rem;"></i> 
    <span style="font-size: 1.0rem; font-weight: 900; vertical-align: middle; margin: 0 5px; background: linear-gradient(45deg, #FFD700, #FF8C00); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        <?php echo $rank_display; ?>
    </span> 
    <small class="text-muted">dari <?php echo $total_lulus_100; ?> Siswa</small>
<?php else: ?>
    <span style="font-size: 0.8rem; font-weight: 800;" class="text-danger">
        <?php echo $rank_display; ?>
    </span>
<?php endif; ?>
</div>
</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(!empty($tugas_belum_selesai)): ?>
<div class="container mb-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card list-mewah p-4">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-bullhorn fa-2x text-danger me-3"></i>
                    <div>
                        <h5 class="fw-bold text-danger mb-0">Materi & Tugas yang belum selesai</h5>
                        <p class="text-muted small mb-0">Segera selesaikan materi berikut agar progresmu 100%!</p>
                    </div>
                </div>
                <div class="row px-2" style="max-height: 250px; overflow-y: auto;">
                    <?php foreach($tugas_belum_selesai as $t): ?>
                    <div class="col-md-6 materi-item">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger rounded-pill me-2" style="font-size: 0.6rem;"><?= $t['mapel'] ?></span>
                            <span class="text-dark fw-bold small"><?= $t['judul'] ?></span>
                            <span class="badge bg-light text-danger border ms-1" style="font-size: 0.6rem;"><?= $t['jenis'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container mb-5">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card guide-banner shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #764ba2;"><i class="fas fa-rocket me-2"></i> Mau Belajar Apa Hari Ini?</h4>
                        <p class="text-muted mb-0 small">Ikuti petunjuk singkat untuk mulai menjelajahi mata pelajaran dan fitur Asisten AI.</p>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 rounded-pill mt-3 mt-md-0 shadow-sm" style="background: #764ba2; border: none;" data-bs-toggle="modal" data-bs-target="#modalPanduanSiswa">
                        <i class="fas fa-map-signs me-2"></i> CARA PENGGUNAAN
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='ipas/<?= $folder_sem ?>/siswa/dashboard.php'">
                <div class="icon-box bg-ipas"><i class="fas fa-flask"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">IPAS</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='mtk/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-math"><i class="fas fa-calculator"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0 text-truncate">Matematika</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='indo/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-indo"><i class="fas fa-book-open"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">B. Indonesia</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='panca/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-pancasila"><i class="fas fa-shield-halved"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">Pancasila</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='englis/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-english"><i class="fas fa-language"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">B. Inggris</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='pjok/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-pjok"><i class="fas fa-volleyball"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">PJOK</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='pai/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-pai"><i class="fas fa-mosque"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">PAI</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card subject-card text-center" onclick="location.href='mulok/<?= $folder_sem ?>/index.php'">
                <div class="icon-box bg-mulok"><i class="fas fa-map-location-dot"></i></div>
                <div class="card-body"><h5 class="fw-bold mb-0">Mulok</h5><small class="text-muted">Arsip Sem <?= $semester ?></small></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
    <div class="card subject-card text-center" onclick="location.href='seni/<?= $folder_sem ?>/index.php'">
        <div class="icon-box bg-seni"><i class="fas fa-palette"></i></div>
        <div class="card-body">
            <h5 class="fw-bold mb-0">Seni</h5>
            <small class="text-muted">Arsip Sem <?= $semester ?></small>
        </div>
    </div>
</div>
    </div>
</div>


<div class="row justify-content-center mt-4">
    <div class="col-12 col-md-10">
        <div class="card subject-card shadow-sm border-primary" onclick="location.href='peng_diri/index.php'">
            <div class="row g-0 align-items-center">
                <div class="col-3 col-md-2 bg-primary py-4 text-white text-center">
                    <i class="fas fa-rocket fa-3x"></i>
                </div>
                <div class="col-9 col-md-10 text-start ps-4">
                    <h4 class="fw-bold mb-1" style="color: #764ba2;"> Pengembangan Diri</h4>
                    <p class="text-muted mb-0">Eksplorasi materi Literasi, Numerasi, STEM, OSN, dan Coding di sini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!--<?php if (!$sudah_isi_padi): ?>-->
<!--<div class="container mt-5 mb-3">-->
<!--    <div class="row justify-content-center">-->
<!--        <div class="col-12 col-md-10">-->
<!--            <a href="penilaian_padi.php" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm" style="background: #0d6efd; border: none;">-->
<!--                <i class="fas fa-edit me-2"></i> Isi Instrumen Penilaian PADI-->
<!--            </a>-->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->
<!--<?php endif; ?>-->

<div class="text-center mb-5 text-muted small">
    <p>© 2026 Portal PADI</p>
</div>

<div class="modal fade" id="modalGantiPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0"><h5 class="fw-bold"><i class="fas fa-key me-2"></i> Ganti Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="proses_ganti_pass.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small fw-bold">Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="n_pass" id="pass_baru" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                            <span class="input-group-text" onclick="togglePass('pass_baru', 'eye_baru')">
                                <i class="fas fa-eye" id="eye_baru"></i>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="c_pass" id="pass_konf" class="form-control" placeholder="Ulangi password" required>
                            <span class="input-group-text" onclick="togglePass('pass_konf', 'eye_konf')">
                                <i class="fas fa-eye" id="eye_konf"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button><button type="submit" name="submit_pass" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPanduanSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; overflow: hidden;">
            <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px;">
                <h5 class="modal-title fw-bold"><i class="fas fa-map-signs me-2"></i> Cara Menggunakan Portal PADI</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start mb-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-th-large text-primary"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Pilih Mata Pelajaran</h6>
                                <p class="text-muted small">Klik pada kartu mata pelajaran (IPAS, Matematika, dll) untuk masuk ke materi semester aktif.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-4">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-tasks text-success"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Pantau Progres</h6>
                                <p class="text-muted small">Pastikan indikator progres mencapai <strong>100%</strong> dengan menyelesaikan semua materi dan kuis yang tersedia.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start mb-4">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-trophy text-warning"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Buka Peringkat</h6>
                                <p class="text-muted small">Peringkat kelas hanya akan muncul jika kamu sudah menyelesaikan semua tugas (Progres 100%).</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-4">
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-robot text-info"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Asisten Belajar AI</h6>
                                <p class="text-muted small">Gunakan tombol melayang di pojok kanan bawah untuk bertanya tentang materi yang sulit kepada AI.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="opacity-50">
                <div class="alert alert-info border-0 rounded-4 mb-0">
                    <small><strong>Tips:</strong> Jika ingin melihat hasil belajar keseluruhan, klik menu <strong>Lihat Raport</strong> pada bagian profil di pojok kanan atas.</small>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary w-100 rounded-pill fw-bold py-2" data-bs-dismiss="modal" style="background: #764ba2; border: none;">SAYA MENGERTI, MULAI BELAJAR!</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePass(idInput, idEye) {
        const input = document.getElementById(idInput);
        const eye = document.getElementById(idEye);
        if (input.type === "password") {
            input.type = "text";
            eye.classList.remove('fa-eye');
            eye.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            eye.classList.remove('fa-eye-slash');
            eye.classList.add('fa-eye');
        }
    }
</script>
</body>
</html>