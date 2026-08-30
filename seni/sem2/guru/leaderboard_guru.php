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
// 2. QUERY LEADERBOARD DENGAN LOGIKA SINKRON (Kuis + Praktek + TO)
// =======================================================================
$leaderboard_data = [];

if ($id_guru_login > 0 && $kelas_in_clause !== "NULL") {
    // Ambil data dasar siswa yang dibimbing
    $query_siswa = "SELECT id, nama_lengkap, kelas FROM users 
                    WHERE role = 'siswa' 
                    AND kelas IN ({$kelas_in_clause}) 
                    AND id_guru = $id_guru_login";
    
    $result_siswa = $db_mapel->query($query_siswa);

    if ($result_siswa) {
        while ($u = $result_siswa->fetch_assoc()) {
            $sid = $u['id'];

            // A. HITUNG NILAI MATERI (KUIS + PRAKTEK)
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
            $avg_materi_final = ($count_materi > 0) ? round($total_skor_materi_bulat / $count_materi) : 0;

            // B. HITUNG RATA-RATA TRYOUT
            $list_to = [];
            $q_to = $db_mapel->query("SELECT ROUND(AVG(persentase)) as nilai_to_bulat FROM riwayat_tryout WHERE id_user = $sid GROUP BY tryout_id");
            while($row = $q_to->fetch_assoc()) { $list_to[] = $row['nilai_to_bulat']; }
            
            $avg_tryout_final = (count($list_to) > 0) ? round(array_sum($list_to) / count($list_to)) : 0;

            // C. FINAL GABUNGAN
            $total_final = 0;
            $pembagi_final = 0;
            if ($avg_materi_final > 0) { $total_final += $avg_materi_final; $pembagi_final++; }
            if ($avg_tryout_final > 0) { $total_final += $avg_tryout_final; $pembagi_final++; }

            $final_score = ($pembagi_final > 0) ? round($total_final / $pembagi_final) : 0;

            // Hanya masukkan jika sudah ada nilai
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
