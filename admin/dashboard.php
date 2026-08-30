<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// 1. Proteksi Halaman Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/** * 2. PASTIKAN VARIABEL GLOBAL TERSEDIA
 * Jika di config/koneksi.php menggunakan nama variabel berbeda, sesuaikan di sini.
 */
$conn = $conn ?? $db_pusat; // Sesuaikan dengan variabel di koneksi.php

if (!$conn) {
    die("Koneksi Database Pusat Bermasalah: " . mysqli_connect_error());
}

// Fungsi Mapping User (Tetap gunakan kristian untuk panca/mulok)
function getMappingUserAdmin($folder, $prefix) {
    $map = [
        'ipas'   => 'hari', 'mtk'    => 'advent', 'indo'   => 'harrieya',
        'panca'  => 'kristian', 'englis' => 'kris', 'pjok'   => 'derry',
        'pai'    => 'arq', 'mulok'  => 'kristian', 'seni'   => 'senirupa'
    ];
    return isset($map[$folder]) ? $prefix . $map[$folder] : $prefix . "admin";
}

// --- STATISTIK TOTAL ---
$total_osn = $conn->query("SELECT COUNT(*) as jml FROM users WHERE akses_osn = 1")->fetch_assoc()['jml'] ?? 0;
$total_stem = $conn->query("SELECT COUNT(*) as jml FROM users WHERE akses_stem = 1")->fetch_assoc()['jml'] ?? 0;
$total_pembimbing = $conn->query("SELECT COUNT(*) as jml FROM users WHERE pembimbing_osn = 1 OR pembimbing_stem = 1")->fetch_assoc()['jml'] ?? 0;
$total_guru = $conn->query("SELECT COUNT(*) as jml FROM users WHERE role = 'guru'")->fetch_assoc()['jml'] ?? 0;
$total_siswa = $conn->query("SELECT COUNT(*) as jml FROM users WHERE role = 'siswa'")->fetch_assoc()['jml'] ?? 0;

$kode_padi = 'PADI_PORTAL';
$total_uji_siswa = $conn->query("SELECT COUNT(DISTINCT id_user) as jml FROM hasil_uji_siswa WHERE kode_aplikasi = '$kode_padi'")->fetch_assoc()['jml'] ?? 0;
$total_uji_guru = $conn->query("SELECT COUNT(DISTINCT id_user) as jml FROM hasil_uji_guru WHERE kode_aplikasi = '$kode_padi'")->fetch_assoc()['jml'] ?? 0;
$total_uji_ahli = $conn->query("SELECT COUNT(DISTINCT nama_ahli) as jml FROM hasil_validasi WHERE kode_aplikasi = '$kode_padi'")->fetch_assoc()['jml'] ?? 0;

/** * 3. LOGIKA REKAP PROGRES GURU (SINKRON DENGAN REKAP_GURU.PHP) */
$mapels = [
    ['folder' => 'ipas', 'user' => 'hari'],
    ['folder' => 'mtk', 'user' => 'advent'],
    ['folder' => 'indo', 'user' => 'harrieya'],
    ['folder' => 'panca', 'user' => 'adventgool'],
    ['folder' => 'englis', 'user' => 'kris'],
    ['folder' => 'pjok', 'user' => 'derry'],
    ['folder' => 'pai', 'user' => 'arq'],
    ['folder' => 'mulok', 'user' => 'kristian'],
    ['folder' => 'seni', 'user' => 'senirupa']
];
$semester = $_SESSION['semester_aktif'] ?? '2';

$res_guru_list = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");
$rekap_guru = [];

if ($res_guru_list) {
    while ($guru = $res_guru_list->fetch_assoc()) {
        $id_g = $guru['id'];
        $total_p_bim = 0;
        
        $res_s = $conn->query("SELECT id FROM users WHERE role = 'siswa' AND id_guru = $id_g");
        $jml_s = $res_s->num_rows;

        if ($jml_s > 0) {
            while ($siswa = $res_s->fetch_assoc()) {
                $id_s = $siswa['id'];
                $total_progres_kumulatif_siswa = 0;
                $jumlah_mapel_aktif_progres = 0;

                foreach ($mapels as $m) {
                    // --- LOGIKA PENYESUAIAN DATABASE (Panca ke Mulok) ---
                    $mapel_folder = $m['folder'];
                    $db_alias = ($mapel_folder == 'panca') ? 'mulok' : $mapel_folder;
                    $user_target = getMappingUserAdmin($mapel_folder, $prefix);
                    $t_pref = ($mapel_folder == 'panca') ? 'panca_' : '';
                    
                    $db_target = $prefix . "db_" . $db_alias . "_sm" . $semester;
                    
                    $temp_db = @mysqli_connect($host, $user_target, $pass, $db_target);
                    if ($temp_db) {
                        // Gunakan prefix {$t_pref} untuk tabel jika mapelnya Pancasila
                        $q_prog = "SELECT  
                                    (SELECT COUNT(id) FROM {$t_pref}materi WHERE id_guru = $id_g) as total_m,
                                    (SELECT COUNT(id) FROM {$t_pref}tryout_master WHERE id_guru = $id_g) as total_t,
                                    (SELECT COUNT(DISTINCT rk.id_materi) FROM {$t_pref}riwayat_kuis rk WHERE rk.id_user = $id_s) as m_selesai,
                                    (SELECT COUNT(DISTINCT rt.tryout_id) FROM {$t_pref}riwayat_tryout rt WHERE rt.id_user = $id_s) as t_selesai";
                        
                        if ($mapel_folder == 'seni') {
                            $q_prog .= ", (SELECT COUNT(id) FROM praktek_siswa WHERE id_siswa = $id_s) as p_selesai";
                        } else {
                            $q_prog .= ", 0 as p_selesai";
                        }

                        $res_p = mysqli_query($temp_db, $q_prog);
                        if ($res_p) {
                            $row_p = mysqli_fetch_assoc($res_p);
                            $tersedia = ($row_p['total_m'] ?? 0) + ($row_p['total_t'] ?? 0);
                            $dikerjakan = ($row_p['m_selesai'] ?? 0) + ($row_p['t_selesai'] ?? 0) + ($row_p['p_selesai'] ?? 0);
                            
                            if ($tersedia > 0) {
                                $persen_mapel = ($dikerjakan / $tersedia) * 100;
                                if ($persen_mapel > 100) $persen_mapel = 100;
                                
                                $total_progres_kumulatif_siswa += round($persen_mapel);
                                $jumlah_mapel_aktif_progres++;
                            }
                        }
                        mysqli_close($temp_db);
                    }
                }
                
                $progres_akhir_siswa = ($jumlah_mapel_aktif_progres > 0) 
                    ? round($total_progres_kumulatif_siswa / $jumlah_mapel_aktif_progres) : 0;
                
                $total_p_bim += $progres_akhir_siswa;
            }
            $rata_prog_bim = round($total_p_bim / $jml_s);
        } else {
            $rata_prog_bim = 0;
        }

        $rekap_guru[] = [
            'id_asli' => $id_g,
            'nama' => $guru['nama_lengkap'],
            'progres' => $rata_prog_bim,
            'total_siswa' => $jml_s
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin | Portal PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; }
        .stat-card { border: none; border-radius: 15px; color: white; position: relative; overflow: hidden; }
        .guru-card { border: none; border-radius: 12px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .btn-rekap { border-radius: 12px; padding: 15px; border: 1px solid #eee; background: white; display: block; text-decoration: none; color: #333; transition: 0.2s; }
        .btn-rekap:hover { border-color: #0d6efd; background: #f8faff; }
        .akses-widget { border: none; border-radius: 12px; padding: 15px; color: white; margin-bottom: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-user-shield me-2"></i> ADMIN PUSAT</a>
        <a href="../logout.php" class="btn btn-danger btn-sm">Keluar</a>
    </div>
</nav>

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard Kendali</h2>
        <a href="pusat_kendali.php" class="btn btn-primary btn-lg rounded-pill shadow">
            <i class="fas fa-key me-2"></i> KELOLA AKSES OSN & STEM
        </a>
    </div>

    <div class="row g-3 mb-4 text-center">
        <div class="col-md-4">
            <div class="akses-widget bg-warning shadow-sm">
                <small class="fw-bold opacity-75">TIKET OSN</small>
                <h3 class="fw-bold m-0"><?= $total_osn ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="akses-widget bg-info shadow-sm">
                <small class="fw-bold opacity-75">TIKET STEM</small>
                <h3 class="fw-bold m-0"><?= $total_stem ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="akses-widget bg-secondary shadow-sm">
                <small class="fw-bold opacity-75">GURU PEMBIMBING</small>
                <h3 class="fw-bold m-0"><?= $total_pembimbing ?></h3>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card stat-card bg-primary shadow p-4">
                <h6 class="text-uppercase opacity-75 small fw-bold">Total Guru</h6>
                <h1 class="display-4 fw-bold"><?= $total_guru ?></h1>
                <hr>
                <a href="users.php?role=guru" class="text-white text-decoration-none small fw-bold">Kelola Data Guru <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card stat-card bg-success shadow p-4">
                <h6 class="text-uppercase opacity-75 small fw-bold">Total Siswa</h6>
                <h1 class="display-4 fw-bold"><?= $total_siswa ?></h1>
                <hr>
                <a href="users.php?role=siswa" class="text-white text-decoration-none small fw-bold">Kelola Data Siswa <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3"><i class="fas fa-chart-line text-success me-2"></i> Progres Penuntasan Materi (Per Guru)</h5>
<div class="row mb-5">
    <?php foreach ($rekap_guru as $rg): ?>
    <div class="col-md-4 col-lg-3 mb-3">
        <div class="card guru-card h-100 border-0 shadow-sm" style="border-radius: 15px;">
            <div class="card-body p-4 d-flex flex-column text-center">
                <div class="mb-3">
                    <i class="fas fa-user-tie fa-2x text-secondary opacity-50"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($rg['nama']) ?></h6>
                <p class="small text-muted mb-3"><?= $rg['total_siswa'] ?> Siswa Bimbingan</p>
                
                <div class="mt-auto">
                    <div class="d-flex justify-content-between mb-1 small fw-bold">
                        <span>Progres</span>
                        <span class="text-success"><?= $rg['progres'] ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height: 8px; border-radius: 10px; background-color: #e9ecef;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             style="width: <?= $rg['progres'] ?>%"></div>
                    </div>
                    <a href="rekap_guru.php?id_guru=<?= $rg['id_asli'] ?>" 
                       class="btn btn-sm btn-success w-100 rounded-pill shadow-sm py-2">
                       <i class="fas fa-search me-1"></i> Lihat Detail
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
    
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-chart-bar me-2"></i> Laporan Validasi Portal PADI</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <a href="rekap_siswa_padi.php?app=PADI_PORTAL" class="btn-rekap shadow-sm">
                        <h6 class="fw-bold mb-1 text-warning">Penilaian Siswa</h6>
                        <p class="small text-muted mb-0"><?= $total_uji_siswa ?> Responden</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="rekap_guru_padi.php" class="btn-rekap shadow-sm">
                        <h6 class="fw-bold mb-1 text-success">Penilaian Guru</h6>
                        <p class="small text-muted mb-0"><?= $total_uji_guru ?> Responden</p>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="rekap_ahli_padi.php" class="btn-rekap shadow-sm">
                        <h6 class="fw-bold mb-1 text-info">Validasi Ahli</h6>
                        <p class="small text-muted mb-0"><?= $total_uji_ahli ?> Validator</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>