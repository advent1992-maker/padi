<?php
// ======================================================================================
// KUIS_LIST.PHP (SISI GURU) - PENDIDIKAN PANCASILA (Desain Standar)
// ======================================================================================
require_once '../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$kelas_diajar_str = $_SESSION['kelas'] ?? '';

// --- LOGIKA TOGGLE STATUS TAMPIL/SEMBUNYI ---
if (isset($_GET['toggle_id'])) {
    $tid = (int)$_GET['toggle_id'];
    $status_sekarang = (int)$_GET['status'];
    $status_baru = ($status_sekarang == 1) ? 0 : 1;
    
    $stmt = $db_mapel->prepare("UPDATE " . tbl('panca_materi') . " SET tampilkan_kuis = ? WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("iii", $status_baru, $tid, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: kuis_list.php");
    exit();
}

// --- FUNGSI HELPER UNTUK EKSEKUSI QUERY ---
function execute_query($db_mapel, $query, $params = [], $types = "") {
    $list = [];
    $stmt = $db_mapel->prepare($query);

    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($db_mapel->error));
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $list[] = $row; }
    } else {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }
    $stmt->close();
    return $list;
}

// --- LOGIKA FILTER KELAS ---
$kelas_filter_parts = [];
$kelas_params = [];
$kelas_types = "";

if (!empty($kelas_diajar_str)) {
    $kelas_array = array_map('trim', explode(',', $kelas_diajar_str));
    $placeholders = implode(',', array_fill(0, count($kelas_array), '?'));
    $kelas_filter_parts[] = "m.level_kategori IN ({$placeholders})";

    foreach ($kelas_array as $kelas) {
        $kelas_params[] = $kelas;
        $kelas_types .= "s";
    }
} else {
    $kelas_filter_parts[] = "1=1";
}

// ----------------------------------------------------
// 1. QUERY MATERI PRIBADI
// ----------------------------------------------------
$filter_pribadi = $kelas_filter_parts;
$filter_pribadi[] = "m.id_guru = ?";
$params_pribadi = array_merge($kelas_params, [$user_id]);
$types_pribadi = $kelas_types . "i";

$query_pribadi = "
    SELECT m.id, m.judul, m.level_kategori, m.tampilkan_kuis, COUNT(s.id) AS jumlah_soal
    FROM " . tbl('panca_materi') . " m
    LEFT JOIN " . tbl('panca_soal') . " s ON m.id = s.materi_id
    WHERE " . implode(" AND ", $filter_pribadi) . "
    GROUP BY m.id ORDER BY m.id DESC
";

$materi_pribadi = execute_query($db_mapel, $query_pribadi, $params_pribadi, $types_pribadi);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal Kuis | PENDIDIKAN PANCASILA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; } /* Mengembalikan ke Biru Muda standar Anda */
        .table-custom th { background-color: #0d6efd; color: white; }
        .card-table { border-radius: 15px; overflow: hidden; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark p-3">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><strong>PANCASILA | GURU</strong></a>
        <span class="text-white">Halo, <b class="text-warning"><?= htmlspecialchars($nama_pengguna) ?></b></span>
    </div>
</nav>

<div class="container mt-5">
    <header class="mb-4">
        <h1><i class="fas fa-flag text-primary"></i> Kelola Soal Kuis</h1>
        <p class="text-muted">Kelas Aktif: <b><?= htmlspecialchars($kelas_diajar_str) ?></b></p>
    </header>

    <a href="dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Dashboard</a>

    <h4 class="text-success mb-3"><i class="fas fa-user-check"></i> Materi Pribadi (Dapat Dikelola)</h4>
    <div class="card card-table mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>#</th><th>Judul Bab Cerita</th><th>Status Kuis</th><th class="text-center">Jumlah Soal</th><th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materi_pribadi)): ?>
                        <tr><td colspan="5" class="text-center p-4">Belum ada materi Pendidikan Pancasila pribadi.</td></tr>
                    <?php else: $no=1; foreach($materi_pribadi as $m): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><b><?= htmlspecialchars($m['judul']) ?></b> <br><small class="text-muted">Kelas <?= $m['level_kategori'] ?></small></td>
                            <td>
                                <?php if($m['tampilkan_kuis'] == 1): ?>
                                    <a href="?toggle_id=<?= $m['id'] ?>&status=1" class="btn btn-sm btn-light border text-success fw-bold rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-eye me-1"></i> Tampil
                                    </a>
                                <?php else: ?>
                                    <a href="?toggle_id=<?= $m['id'] ?>&status=0" class="btn btn-sm btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-eye-slash me-1"></i> Sembunyi
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge bg-info text-dark"><?= $m['jumlah_soal'] ?> Soal</span></td>
                            <td class="text-center">
                                <a href="kuis_form.php?id_materi=<?= $m['id'] ?>" class="btn btn-sm btn-success fw-bold">
                                    <i class="fas fa-edit"></i> Edit Soal Manual
                                </a>
                                <a href="ai_generator_page.php?id_materi=<?= $m['id'] ?>"
                                   class="btn btn-sm btn-success fw-bold"
                                   onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Menghubungi AI...'; this.classList.add('disabled');">
                                    <i class="fas fa-robot"></i> Buat Soal dengan AI
                                </a>
                                <a href="preview_kuis.php?id_materi=<?= $m['id'] ?>" class="btn btn-sm btn-info text-white fw-bold">
                                    <i class="fas fa-print"></i> Preview & Cetak
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>