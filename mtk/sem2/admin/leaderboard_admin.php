<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// =======================================================================
// 1. INISIALISASI VARIABEL DAN PENGAMANAN
// =======================================================================
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Admin';

// =======================================================================
// 2. LOGIKA FILTER (BARU)
// =======================================================================
$filter_kelas = $_GET['filter_kelas'] ?? 'semua'; // Default 'semua'
$where_clause = "u.role = 'siswa' AND u.kelas IS NOT NULL AND u.kelas != ''";

// Ambil daftar kelas untuk dropdown filter
$query_classes = "SELECT DISTINCT kelas FROM users WHERE role = 'siswa' AND kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
$result_classes = $db_mapel->query($query_classes);
$classes = [];
if ($result_classes) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row['kelas'];
    }
}

// Tambahkan kondisi filter jika kelas tertentu dipilih
if (!empty($filter_kelas) && $filter_kelas !== 'semua') {
    // Pastikan input aman dari SQL Injection
    $safe_filter_kelas = $db_mapel->real_escape_string($filter_kelas);
    $where_clause .= " AND u.kelas = '{$safe_filter_kelas}'";
}


// =======================================================================
// 3. QUERY LEADERBOARD GLOBAL SISWA (Diperbarui dengan $where_clause)
// =======================================================================
$query_global_siswa = "
    SELECT
        u.nama_lengkap,
        u.kelas,
        u.id AS user_id,
        COALESCE(
            ROUND((
                COALESCE(avg_kuis.avg_kuis, 0) + COALESCE(avg_tryout.avg_tryout, 0)
            ) /
            (
                (CASE WHEN avg_kuis.avg_kuis IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN avg_tryout.avg_tryout IS NOT NULL THEN 1 ELSE 0 END)
            ), 0)
        , 0) AS RataRataGabungan
    FROM users u
    LEFT JOIN (SELECT id_user, AVG(persentase) AS avg_kuis FROM riwayat_kuis GROUP BY id_user) AS avg_kuis ON u.id = avg_kuis.id_user
    LEFT JOIN (SELECT id_user, AVG(persentase) AS avg_tryout FROM riwayat_tryout GROUP BY id_user) AS avg_tryout ON u.id = avg_tryout.id_user
    WHERE
        {$where_clause}
    ORDER BY
        RataRataGabungan DESC, u.nama_lengkap ASC;
";

$result_global_siswa = $db_mapel->query($query_global_siswa);


// =======================================================================
// 4. QUERY LEADERBOARD ANTAR KELAS (Diperbarui dengan $where_clause)
// =======================================================================
$query_antar_kelas = "
    SELECT
        u.kelas,
        COUNT(u.id) AS total_siswa_aktif,
        COALESCE(
            ROUND(AVG(
                COALESCE(avg_kuis.avg_kuis, 0) + COALESCE(avg_tryout.avg_tryout, 0)
            ) /
            AVG(
                (CASE WHEN avg_kuis.avg_kuis IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN avg_tryout.avg_tryout IS NOT NULL THEN 1 ELSE 0 END)
            ), 0)
        , 0) AS RataRataKelas
    FROM
        users u
    LEFT JOIN (
        SELECT id_user, AVG(persentase) AS avg_kuis FROM riwayat_kuis GROUP BY id_user
    ) AS avg_kuis ON u.id = avg_kuis.id_user
    LEFT JOIN (
        SELECT id_user, AVG(persentase) AS avg_tryout FROM riwayat_tryout GROUP BY id_user
    ) AS avg_tryout ON u.id = avg_tryout.id_user
    WHERE
        {$where_clause}
    GROUP BY
        u.kelas
    ORDER BY
        RataRataKelas DESC, total_siswa_aktif DESC;
";

$result_antar_kelas = $db_mapel->query($query_antar_kelas);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard Admin | Analisis Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; }
        .hero-card {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            color: white; padding: 40px; border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .peringkat-emas { background-color: #ffc10740; }
        .peringkat-perak { background-color: #adb5bd40; }
        .peringkat-perunggu { background-color: #8b451340; }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">

    <div class="hero-card text-center mb-4">
        <h1 class="display-5 fw-bold"><i class="fas fa-chart-bar"></i> Leaderboard Administrasi</h1>
        <p class="lead mt-3">Selamat datang, <?php echo htmlspecialchars($nama_pengguna); ?>! Pantau kinerja seluruh sekolah di sini.</p>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <form method="GET" class="mb-4 bg-white p-3 rounded shadow-sm border">
        <div class="row align-items-end">
            <div class="col-md-3 col-sm-6">
                <label for="filter_kelas" class="form-label fw-bold"><i class="fas fa-filter me-1"></i> Filter Kelas:</label>
                <select name="filter_kelas" id="filter_kelas" class="form-select">
                    <option value="semua" <?= $filter_kelas === 'semua' ? 'selected' : '' ?>>Semua Kelas</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= htmlspecialchars($class) ?>" <?= $filter_kelas === $class ? 'selected' : '' ?>>
                            Kelas <?= htmlspecialchars($class) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto col-sm-6 mt-3 mt-sm-0">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Terapkan</button>
                <?php if ($filter_kelas !== 'semua'): ?>
                    <a href="leaderboard_admin.php" class="btn btn-outline-danger ms-2"><i class="fas fa-times"></i> Hapus Filter</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <ul class="nav nav-tabs mb-4" id="leaderboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="siswa-tab" data-bs-toggle="tab" data-bs-target="#globalSiswa" type="button" role="tab" aria-controls="globalSiswa" aria-selected="true"><i class="fas fa-users"></i> Peringkat Siswa</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kelas-tab" data-bs-toggle="tab" data-bs-target="#antarKelas" type="button" role="tab" aria-controls="antarKelas" aria-selected="false"><i class="fas fa-layer-group"></i> Peringkat Antar Kelas</button>
        </li>
    </ul>

    <div class="tab-content" id="leaderboardTabsContent">

        <div class="tab-pane fade show active" id="globalSiswa" role="tabpanel" aria-labelledby="siswa-tab">
            <h3 class="text-primary mb-3">Daftar Peringkat Siswa <?php echo ($filter_kelas !== 'semua' ? 'Kelas ' . htmlspecialchars($filter_kelas) : 'Keseluruhan'); ?></h3>

            <div class="table-responsive mb-5">
                <table class="table table-striped table-bordered align-middle shadow">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th style="width: 10%;">Rank</th>
                            <th>Nama Siswa</th>
                            <th style="width: 15%;">Kelas</th>
                            <th class="text-end" style="width: 25%;">Rata-Rata Skor (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $peringkat = 1; ?>
                        <?php while ($row = $result_global_siswa->fetch_assoc()): ?>
                        <?php
                            $row_class = '';
                            if ($peringkat == 1) {
                                $row_class = 'peringkat-emas';
                            } elseif ($peringkat == 2) {
                                $row_class = 'peringkat-perak';
                            } elseif ($peringkat == 3) {
                                $row_class = 'peringkat-perunggu';
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <?php
                                if ($peringkat <= 3) {
                                    echo '<i class="fas fa-trophy text-warning me-2"></i>';
                                }
                                echo $peringkat++;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['kelas'] ?? 'N/A'); ?></td>
                            <td class="text-end fw-bold"><?php echo htmlspecialchars($row['RataRataGabungan'] ?? '0'); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="antarKelas" role="tabpanel" aria-labelledby="kelas-tab">
            <h3 class="text-primary mb-3">Daftar Peringkat Rata-Rata Antar Kelas <?php echo ($filter_kelas !== 'semua' ? '(Terfilter: Kelas ' . htmlspecialchars($filter_kelas) . ')' : ''); ?></h3>

            <div class="table-responsive mb-5">
                <table class="table table-striped table-bordered align-middle shadow">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th style="width: 10%;">Rank</th>
                            <th>Nama Kelas</th>
                            <th class="text-end" style="width: 25%;">Rata-Rata Skor Kelas (%)</th>
                            <th class="text-end" style="width: 20%;">Total Siswa Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank_kelas = 1; ?>
                        <?php while ($row = $result_antar_kelas->fetch_assoc()): ?>
                        <tr class="<?php echo ($rank_kelas == 1) ? 'peringkat-emas fw-bold' : ''; ?>">
                            <td>
                                <?php
                                if ($rank_kelas == 1) echo '<i class="fas fa-crown text-warning me-2"></i>';
                                echo $rank_kelas++;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['kelas'] ?? 'N/A'); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($row['RataRataKelas'] ?? '0'); ?>%</td>
                            <td class="text-end"><?php echo htmlspecialchars($row['total_siswa_aktif'] ?? '0'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div> </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>