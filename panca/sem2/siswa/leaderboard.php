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

// 5. Ambil Data Leaderboard (Logika Sinkron dengan Dashboard)
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

        // A. SINKRONISASI KUIS (Sesuai Dashboard: Bulat per Materi dulu)
        $list_nilai_kuis = [];
        $q_kuis = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_bulat_materi FROM panca_riwayat_kuis WHERE id_user = $sid GROUP BY id_materi");
        while($row_k = $q_kuis->fetch_assoc()) {
            $list_nilai_kuis[] = $row_k['nilai_bulat_materi'];
        }
        $avg_kuis_final = count($list_nilai_kuis) > 0 ? round(array_sum($list_nilai_kuis) / count($list_nilai_kuis)) : 0;

        // B. SINKRONISASI TRYOUT (Sesuai Dashboard: Bulat per Judul dulu)
        $list_nilai_to = [];
        $q_tryout = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_bulat_to FROM panca_riwayat_tryout WHERE id_user = $sid GROUP BY tryout_id");
        while($row_t = $q_tryout->fetch_assoc()) {
            $list_nilai_to[] = $row_t['nilai_bulat_to'];
        }
        $avg_to_final = count($list_nilai_to) > 0 ? round(array_sum($list_nilai_to) / count($list_nilai_to)) : 0;

        // C. RATA-RATA GABUNGAN (Final Score)
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

        // Hanya masukkan ke leaderboard jika skor > 0
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
    <title>Leaderboard | PANCASILA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }
        .hero-leaderboard {
            background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%);
            color: white; padding: 50px 0; border-radius: 0 0 40px 40px;
        }
        .table-custom { background: white; border-radius: 15px; overflow: hidden; border: none; }
        .current-user { background-color: #fff4f5 !important; font-weight: bold; border-left: 5px solid #dc3545; }
        .rank-gold { color: #ffd700; font-size: 1.5rem; }
        .rank-silver { color: #c0c0c0; font-size: 1.3rem; }
        .rank-bronze { color: #cd7f32; font-size: 1.2rem; }
    </style>
</head>
<body>

<div class="hero-leaderboard text-center shadow">
    <div class="container">
        <h1 class="fw-bold"><i class="fas fa-award text-warning"></i> Klasemen Kelas <?php echo htmlspecialchars($level_kelas); ?></h1>
        <p class="lead opacity-75">Pendidikan Pancasila - Guru <?php echo htmlspecialchars($nama_guru_pembimbing); ?></p>
        
        <div class="card d-inline-block p-3 mt-3 border-0 shadow-sm" style="min-width: 250px; border-radius: 20px;">
            <div class="text-dark small text-uppercase fw-bold text-muted">Peringkat Anda</div>
            <div class="text-danger h2 fw-bold mb-0"><?php echo $current_rank; ?> <small class="text-muted fs-6">/ <?php echo $total_siswa_filtered; ?></small></div>
        </div>
    </div>
</div>

<div class="container mt-4 mb-5">
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-outline-danger btn-sm rounded-pill"><i class="fas fa-chevron-left"></i> Kembali ke Dashboard</a>
    </div>

    <?php if (empty($rank_data)): ?>
        <div class="text-center p-5 bg-white rounded-4 shadow-sm">
            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="mb-3 opacity-50">
            <h5 class="text-muted">Belum ada aktivitas nilai di kelas ini.</h5>
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4">
            <table class="table table-hover table-custom align-middle mb-0">
                <thead class="table-danger text-white">
                    <tr>
                        <th class="py-3 ps-4" width="100">Posisi</th>
                        <th class="py-3">Nama Lengkap</th>
                        <th class="py-3 text-end pe-4">Rata-Rata Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rank_data as $item): ?>
                    <tr class="<?php echo $item['is_current_user'] ? 'current-user' : ''; ?>">
                        <td class="ps-4 fw-bold">
                            <?php 
                                if ($item['rank'] == 1) echo '<i class="fas fa-crown rank-gold"></i>';
                                elseif ($item['rank'] == 2) echo '<i class="fas fa-medal rank-silver"></i>';
                                elseif ($item['rank'] == 3) echo '<i class="fas fa-medal rank-bronze"></i>';
                                else echo "#".$item['rank'];
                            ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['nama_lengkap']); ?>
                            <?php if($item['is_current_user']) echo ' <span class="badge bg-danger ms-1">Anda</span>'; ?>
                        </td>
                        <td class="text-end pe-4 fw-bold text-danger fs-5"><?php echo $item['final_score']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>