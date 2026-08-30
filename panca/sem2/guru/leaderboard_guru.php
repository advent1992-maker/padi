<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// =======================================================================
// 1. INISIALISASI VARIABEL DAN PENGAMANAN
// =======================================================================
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Guru';
$id_guru_login = $_SESSION['user_id'] ?? 0;

$kelas_diajar_str = $_SESSION['kelas'] ?? '';
$kelas_in_clause = "NULL"; 

if (!empty($kelas_diajar_str) && $id_guru_login > 0) {
    $kelas_array = explode(',', $kelas_diajar_str);
    $kelas_terfilter = array_map(function($k) use ($db_mapel) {
        $k = trim($k);
        return ctype_digit($k) ? $db_mapel->real_escape_string($k) : null;
    }, $kelas_array);

    $kelas_terfilter = array_filter($kelas_terfilter);

    if (!empty($kelas_terfilter)) {
        $kelas_in_clause = "'" . implode("','", $kelas_terfilter) . "'";
    }
}

// =======================================================================
// 2. QUERY LEADERBOARD SINKRON (KUIS + TRYOUT) - TANPA PRAKTEK
// =======================================================================
$leaderboard_data = [];

if ($id_guru_login > 0 && $kelas_in_clause !== "NULL") {
    // 1. Ambil data dasar siswa yang dibimbing oleh Guru ini di kelas yang sesuai
    $query_siswa = "SELECT id, nama_lengkap, kelas FROM " . tbl('users') . " 
                    WHERE role = 'siswa' 
                    AND kelas IN ({$kelas_in_clause}) 
                    AND id_guru = $id_guru_login";
    
    $result_siswa = $conn->query($query_siswa);

    if ($result_siswa) {
        while ($u = $result_siswa->fetch_assoc()) {
            $sid = $u['id'];

            // --- A. HITUNG RATA-RATA KUIS (Bulat per Materi) ---
            $list_nilai_kuis = [];
            $q_kuis = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_bulat_materi 
                                        FROM " . tbl('panca_riwayat_kuis') . " 
                                        WHERE id_user = $sid 
                                        GROUP BY id_materi");
            
            while($row_k = $q_kuis->fetch_assoc()) {
                $list_nilai_kuis[] = $row_k['nilai_bulat_materi'];
            }
            $avg_kuis_final = count($list_nilai_kuis) > 0 ? round(array_sum($list_nilai_kuis) / count($list_nilai_kuis)) : 0;

            // --- B. HITUNG RATA-RATA TRYOUT (Bulat per Judul) ---
            $list_nilai_to = [];
            $q_tryout = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_bulat_to 
                                          FROM " . tbl('panca_riwayat_tryout') . " 
                                          WHERE id_user = $sid 
                                          GROUP BY tryout_id");
            
            while($row_t = $q_tryout->fetch_assoc()) {
                $list_nilai_to[] = $row_t['nilai_bulat_to'];
            }
            $avg_to_final = count($list_nilai_to) > 0 ? round(array_sum($list_nilai_to) / count($list_nilai_to)) : 0;

            // --- C. FINAL GABUNGAN (Kuis + TO) ---
            $total_avg = 0;
            $count_valid = 0;

            if ($avg_kuis_final > 0) {
                $total_avg += $avg_kuis_final;
                $count_valid++;
            }
            if ($avg_to_final > 0) {
                $total_avg += $avg_to_final;
                $count_valid++;
            }

            $final_score = ($count_valid > 0) ? round($total_avg / $count_valid) : 0;

            // Hanya masukkan ke array jika siswa sudah memiliki nilai (skor > 0)
            if ($final_score > 0) {
                $leaderboard_data[] = [
                    'nama_lengkap' => $u['nama_lengkap'],
                    'kelas' => $u['kelas'],
                    'final_score' => $final_score
                ];
            }
        }
    }
}

// 3. Urutkan berdasarkan skor tertinggi
usort($leaderboard_data, function($a, $b) {
    return $b['final_score'] <=> $a['final_score'];
});

$db_mapel->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard Guru | Peringkat Siswa Bimbingan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; }
        .hero-card {
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white; padding: 40px; border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .peringkat-emas { background-color: #ffc10740; }
        .peringkat-perak { background-color: #adb5bd40; }
        .peringkat-perunggu { background-color: #8b451340; }
        .table-hover tbody tr:hover { background-color: #e9ecef; }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">

    <div class="hero-card text-center mb-4">
        <h1 class="display-5 fw-bold"><i class="fas fa-trophy"></i> Peringkat Siswa </h1>
        <p class="lead mt-3">Selamat datang, <?php echo htmlspecialchars($nama_pengguna); ?>! Daftar peringkat siswa yang Anda bimbing berdasarkan rata-rata skor gabungan.</p>
    </div>

    <div class="text-start mb-3">
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <h2 class="text-center text-primary mb-4"><i class="fas fa-users"></i> Peringkat Siswa Bimbingan Anda</h2>

    <div class="table-responsive mb-5">
        <table class="table table-striped table-hover table-bordered align-middle shadow">
            <thead class="bg-dark text-white">
                <tr>
                    <th style="width: 10%;">Rank</th>
                    <th>Nama Siswa</th>
                    <th style="width: 15%;">Kelas</th>
                    <th class="text-end" style="width: 25%;">Rata-Rata Skor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($leaderboard_data)): ?>
                    <?php 
                    $peringkat = 0;
                    $prev_score = -1;
                    $rank_counter = 1;
                    foreach ($leaderboard_data as $row): 
                        if ($row['final_score'] != $prev_score) {
                            $peringkat = $rank_counter;
                            $prev_score = $row['final_score'];
                        }
                        $rank_counter++;

                        $row_class = '';
                        if ($peringkat == 1) $row_class = 'peringkat-emas';
                        elseif ($peringkat == 2) $row_class = 'peringkat-perak';
                        elseif ($peringkat == 3) $row_class = 'peringkat-perunggu';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <?php
                                if ($peringkat == 1) echo '<i class="fas fa-trophy text-warning me-2"></i>';
                                elseif ($peringkat == 2) echo '<i class="fas fa-medal text-secondary me-2"></i>';
                                elseif ($peringkat == 3) echo '<i class="fas fa-medal me-2" style="color:#8b4513;"></i>';
                                echo $peringkat;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                            <td class="text-end fw-bold"><?php echo $row['final_score']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">Tidak ada data siswa bimbingan atau riwayat yang valid ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>