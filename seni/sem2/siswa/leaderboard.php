<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// 1. Proteksi Halaman (Hanya Siswa)
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

// 2. Inisialisasi Variabel dari Session
$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? 0;
$id_guru_siswa = $_SESSION['id_guru'] ?? 0;

// 3. Ambil Nama Guru Pembimbing dari Database Portal ($conn)
$nama_guru_pembimbing = 'N/A';
if ($id_guru_siswa > 0) {
    $stmt_guru = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ? AND role = 'guru'");
    $stmt_guru->bind_param("i", $id_guru_siswa);
    $stmt_guru->execute();
    $result_guru = $stmt_guru->get_result();
    if ($row_guru = $result_guru->fetch_assoc()) {
        $nama_guru_pembimbing = $row_guru['nama_lengkap'];
    }
    $stmt_guru->close();
}

// 4. Hitung Total Siswa Terfilter (Portal Database)
$total_siswa_filtered = 0;
if ($level_kelas > 0 && $id_guru_siswa > 0) {
    $stmt_total = $conn->prepare("SELECT COUNT(id) AS total FROM users WHERE role = 'siswa' AND kelas = ? AND id_guru = ?");
    $stmt_total->bind_param("ii", $level_kelas, $id_guru_siswa);
    $stmt_total->execute();
    $total_siswa_filtered = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_total->close();
}

// 5. Ambil Data Leaderboard (LOGIKA KHUSUS SENI: KUIS + PRAKTEK + TRYOUT)
$leaderboard_data = [];
$current_rank = 'N/A';

if ($id_guru_siswa > 0 && $level_kelas > 0) {
    // Ambil daftar siswa dari Portal ($conn)
    $query_siswa = "SELECT id, nama_lengkap, kelas FROM users WHERE role = 'siswa' AND kelas = ? AND id_guru = ?";
    $stmt_lb = $conn->prepare($query_siswa);
    $stmt_lb->bind_param("ii", $level_kelas, $id_guru_siswa);
    $stmt_lb->execute();
    $res_siswa = $stmt_lb->get_result();

    while ($u = $res_siswa->fetch_assoc()) {
        $sid = $u['id'];

        // --- A. HITUNG NILAI MATERI (KUIS + PRAKTEK) ---
        $list_bab_kuis = [];
        $q_k = $db_mapel->query("SELECT id_materi, ROUND(AVG(persentase)) as nilai_bulat FROM riwayat_kuis WHERE id_user = $sid GROUP BY id_materi");
        while($row = $q_k->fetch_assoc()) { $list_bab_kuis[$row['id_materi']] = $row['nilai_bulat']; }

        $list_bab_praktek = [];
        $q_p = $db_mapel->query("SELECT materi_id, nilai_angka FROM praktek_siswa WHERE id_siswa = $sid AND status_dinilai = 1");
        while($row = $q_p->fetch_assoc()) { $list_bab_praktek[$row['materi_id']] = $row['nilai_angka']; }

        $total_skor_materi_bulat = 0;
        $count_materi = 0;
        $all_materi_ids = array_unique(array_merge(array_keys($list_bab_kuis), array_keys($list_bab_praktek)));

        foreach ($all_materi_ids as $id_m) {
            $n_kuis = $list_bab_kuis[$id_m] ?? null;
            $n_praktek = $list_bab_praktek[$id_m] ?? null;

            if ($n_kuis !== null && $n_praktek !== null) {
                $skor_bab = round(($n_kuis + $n_praktek) / 2);
            } else {
                $skor_bab = $n_kuis ?? $n_praktek;
            }

            if ($skor_bab !== null) {
                $total_skor_materi_bulat += $skor_bab;
                $count_materi++;
            }
        }
        $avg_materi_seni = ($count_materi > 0) ? round($total_skor_materi_bulat / $count_materi) : 0;

        // --- B. HITUNG RATA-RATA TRYOUT ---
        $list_to = [];
        $q_to = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_to_bulat FROM riwayat_tryout WHERE id_user = $sid GROUP BY tryout_id");
        while($row = $q_to->fetch_assoc()) { $list_to[] = $row['nilai_to_bulat']; }
        
        $avg_tryout_final = (count($list_to) > 0) ? round(array_sum($list_to) / count($list_to)) : 0;

        // --- C. FINAL GABUNGAN ---
        $total_final = 0;
        $pembagi_final = 0;
        if ($avg_materi_seni > 0) { $total_final += $avg_materi_seni; $pembagi_final++; }
        if ($avg_tryout_final > 0) { $total_final += $avg_tryout_final; $pembagi_final++; }

        $final_score = ($pembagi_final > 0) ? round($total_final / $pembagi_final) : 0;

        if ($final_score > 0) {
            $leaderboard_data[] = [
                'id' => $sid,
                'nama_lengkap' => $u['nama_lengkap'],
                'kelas' => $u['kelas'],
                'final_score' => $final_score
            ];
        }
    }
    $stmt_lb->close();
}

// 6. Pengurutan Data (Skor Tertinggi ke Terendah)
usort($leaderboard_data, function($a, $b) {
    return $b['final_score'] <=> $a['final_score'];
});

// 7. Pemberian Nomor Peringkat
$rank_data = [];
$rank = 0;
$prev_score = -1;

foreach ($leaderboard_data as $index => $item) {
    if ($item['final_score'] != $prev_score) {
        $rank = $index + 1;
        $prev_score = $item['final_score'];
    }
    $item['rank'] = $rank;
    $item['is_current_user'] = ($item['id'] == $user_id);

    if ($item['is_current_user']) {
        $current_rank = $rank;
    }
    $rank_data[] = $item;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard | B.inggris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; font-family: 'Poppins', sans-serif; }
        .hero-card-leaderboard {
            background: linear-gradient(135deg, #6f42c1, #6f42c1);
            color: white; padding: 40px 20px; border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .table-custom { background: white; border-radius: 15px; overflow: hidden; }
        .table-custom tbody tr.current-user {
            background-color: #e3f2fd !important;
            border-left: 5px solid #0d6efd;
            font-weight: bold;
        }
        .rank-icon { font-size: 1.2rem; margin-right: 5px; }
        .score-badge { font-size: 1.1rem; color: #dc3545; font-weight: 700; }
    </style>
</head>
<body>

<div class="hero-card-leaderboard mb-5">
    <div class="container">
        <h1 class="display-6 fw-bold"><i class="fas fa-trophy text-warning"></i> Peringkat Kelas <?php echo htmlspecialchars($level_kelas); ?></h1>
        <p class="opacity-75">Bimbingan Guru: <strong><?php echo htmlspecialchars($nama_guru_pembimbing); ?></strong></p>

        <div class="bg-white text-dark p-3 rounded-4 shadow-sm mt-4 mx-auto" style="max-width: 450px;">
            <h5 class="mb-1 text-secondary">Statistik Anda</h5>
            <p class="fs-5 mb-0">
                Peringkat: <span class="badge bg-danger"><?php echo $current_rank; ?></span>
                <small class="text-muted"> dari <?php echo $total_siswa_filtered; ?> Siswa</small>
            </p>
        </div>
    </div>
</div>

<div class="container mt-2 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (empty($rank_data)): ?>
        <div class="alert alert-light text-center border-0 shadow-sm p-5">
            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
            <h4>Belum Ada Data Nilai</h4>
            <p class="text-muted">Selesaikan kuis atau tryout pertama Anda untuk muncul di papan peringkat!</p>
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4">
            <table class="table table-hover table-custom mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3 ps-4">Rank</th>
                        <th class="py-3">Nama Siswa</th>
                        <th class="py-3 text-center">Kelas</th>
                        <th class="py-3 text-end pe-4">Rata-Rata Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rank_data as $item): ?>
                    <tr class="<?php echo $item['is_current_user'] ? 'current-user' : ''; ?>">
                        <td class="ps-4">
                            <?php
                                if ($item['rank'] == 1) echo '<span class="text-warning"><i class="fas fa-crown"></i> 1</span>';
                                elseif ($item['rank'] == 2) echo '<span class="text-secondary"><i class="fas fa-medal"></i> 2</span>';
                                elseif ($item['rank'] == 3) echo '<span class="text-info"><i class="fas fa-medal"></i> 3</span>';
                                else echo $item['rank'];
                            ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['nama_lengkap']); ?>
                            <?php if($item['is_current_user']) echo ' <span class="badge bg-primary ms-1">Anda</span>'; ?>
                        </td>
                        <td class="text-center"><?php echo htmlspecialchars($item['kelas']); ?></td>
                        <td class="text-end pe-4 score-badge"><?php echo $item['final_score']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>