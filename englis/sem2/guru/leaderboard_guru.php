<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

/* ======================================================
   1. VALIDASI AKSES
====================================================== */
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Guru';

/* ======================================================
   2. ID GURU YANG SAH (INI KUNCI UTAMA)
   - Jika guru mapel → pakai id_guru_pilihan
   - Jika wali kelas → pakai user_id
====================================================== */
$id_guru_login = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$id_guru_login = (int)$id_guru_login;

if ($id_guru_login <= 0) {
    die("ID guru tidak valid");
}

/* ======================================================
   3. KELAS YANG DIAJAR
====================================================== */
$kelas_diajar_str = $_SESSION['kelas'] ?? '';
$kelas_in_clause = '';

if (!empty($kelas_diajar_str)) {
    $kelas_array = explode(',', $kelas_diajar_str);
    $kelas_valid = [];

    foreach ($kelas_array as $k) {
        $k = trim($k);
        if (ctype_digit($k)) {
            $kelas_valid[] = "'" . $db_mapel->real_escape_string($k) . "'";
        }
    }

    if (!empty($kelas_valid)) {
        $kelas_in_clause = implode(',', $kelas_valid);
    }
}

if ($kelas_in_clause === '') {
    die("Kelas tidak ditemukan");
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
                                        FROM " . tbl('riwayat_kuis') . " 
                                        WHERE id_user = $sid 
                                        GROUP BY id_materi");
            
            while($row_k = $q_kuis->fetch_assoc()) {
                $list_nilai_kuis[] = $row_k['nilai_bulat_materi'];
            }
            $avg_kuis_final = count($list_nilai_kuis) > 0 ? round(array_sum($list_nilai_kuis) / count($list_nilai_kuis)) : 0;

            // --- B. HITUNG RATA-RATA TRYOUT (Bulat per Judul) ---
            $list_nilai_to = [];
            $q_tryout = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_bulat_to 
                                          FROM " . tbl('riwayat_tryout') . " 
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

/* ======================================================
   5. URUTKAN PERINGKAT
====================================================== */
usort($leaderboard_data, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">

    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>

    <h3 class="mb-4">Leaderboard Siswa Bimbingan</h3>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Rank</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Skor</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($leaderboard_data)): ?>
            <?php foreach ($leaderboard_data as $i => $siswa): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                    <td class="fw-bold"><?= $siswa['final_score'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">Belum ada data</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
