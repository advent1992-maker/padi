<?php
// ======================================================================================
// KUIS_LIST.PHP (SISI GURU) - SENI (Fitur Tampilkan/Sembunyikan Kuis)
// ======================================================================================
require_once '../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$kelas_diajar_str = $_SESSION['kelas'] ?? '';

// --- LOGIKA TOGGLE STATUS TAMPIL/SEMBUNYI ---
if (isset($_GET['toggle_id'])) {
    $tid = (int)$_GET['toggle_id'];
    $status_sekarang = (int)$_GET['status'];
    $status_baru = ($status_sekarang == 1) ? 0 : 1;
    
    $stmt = $db_mapel->prepare("UPDATE materi SET tampilkan_kuis = ? WHERE id = ? AND id_guru = ?");
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

// 1. QUERY MATERI PRIBADI
$filter_pribadi = $kelas_filter_parts;
$filter_pribadi[] = "m.id_guru = ?";
$params_pribadi = array_merge($kelas_params, [$user_id]);
$types_pribadi = $kelas_types . "i";

$query_pribadi = "
    SELECT m.id, m.judul, m.level_kategori, m.tampilkan_kuis, COUNT(s.id) AS jumlah_soal
    FROM " . tbl('materi') . " m
    LEFT JOIN " . tbl('soal') . " s ON m.id = s.materi_id
    WHERE " . implode(" AND ", $filter_pribadi) . "
    GROUP BY m.id ORDER BY m.id DESC
";

$materi_pribadi = execute_query($db_mapel, $query_pribadi, $params_pribadi, $types_pribadi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kuis Seni | Kreativitas Tanpa Batas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #fff5f5; font-family: 'Poppins', sans-serif; }
        .navbar-seni { background: linear-gradient(45deg, #e91e63, #ff9800); border-bottom: 5px solid #c2185b; }
        .card-table { border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(233, 30, 99, 0.1); }
        .btn-seni { background-color: #e91e63; color: white; border-radius: 50px; transition: 0.3s; }
        .btn-seni:hover { background-color: #c2185b; color: white; transform: translateY(-2px); }
        .btn-toggle { transition: 0.3s; border-radius: 50px; }
        .table-seni { background-color: #fce4ec; color: #ad1457; }
        .badge-soal { background-color: #ff9800; color: white; border-radius: 10px; }
        .header-title { color: #880e4f; font-weight: 700; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-seni p-3 shadow">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-palette me-2"></i><strong>SENI | PANEL GURU</strong>
        </a>
        <span class="text-white"><b class="text-white border-bottom"><?= htmlspecialchars($nama_pengguna) ?></b></span>
    </div>
</nav>

<div class="container mt-5 pb-5">
    <header class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h1 class="header-title"><i class="fas fa-paint-brush text-danger"></i> Kelola Pertanyaan Kuis</h1>
            <p class="text-muted mb-0">Kelas Aktif: <span class="badge bg-danger"><?= htmlspecialchars($kelas_diajar_str) ?></span></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-danger rounded-pill"><i class="fas fa-arrow-left"></i> Kembali</a>
    </header>

    <div class="alert alert-info border-0 shadow-sm rounded-4" style="background-color: #fce4ec; color: #880e4f;">
        <i class="fas fa-info-circle me-2"></i> <b>Info:</b> Gunakan fitur AI untuk membantu merancang soal seni berdasarkan teori warna, sejarah seni, atau teknik vokal/musik.
    </div>

    <h4 class="text-danger mb-3 mt-4"><i class="fas fa-folder-open"></i> Galeri Materi Anda</h4>
    <div class="card card-table">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-seni">
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Topik Karya/Materi</th>
                        <th>Status Kuis</th>
                        <th class="text-center">Jumlah Soal</th>
                        <th class="text-center">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materi_pribadi)): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted">Belum ada materi seni yang dibuat. Mulailah berkarya!</td></tr>
                    <?php else: $no=1; foreach($materi_pribadi as $m): ?>
                        <tr>
                            <td class="ps-4"><?= $no++ ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($m['judul']) ?></span>
                                <br><small class="badge bg-light text-danger border border-danger-subtle">Kelas <?= $m['level_kategori'] ?></small>
                            </td>
                            <td>
                                <?php if($m['tampilkan_kuis'] == 1): ?>
                                    <a href="?toggle_id=<?= $m['id'] ?>&status=1" class="btn btn-sm btn-success btn-toggle px-3 shadow-sm">
                                        <i class="fas fa-check-circle me-1"></i> Ditampilkan
                                    </a>
                                <?php else: ?>
                                    <a href="?toggle_id=<?= $m['id'] ?>&status=0" class="btn btn-sm btn-secondary btn-toggle px-3">
                                        <i class="fas fa-times-circle me-1"></i> Disembunyikan
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-soal px-3 py-2"><?= $m['jumlah_soal'] ?> Butir</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group shadow-sm" style="border-radius: 50px; overflow: hidden;">
                                    <a href="kuis_form.php?id_materi=<?= $m['id'] ?>" class="btn btn-sm btn-seni px-3">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="ai_generator_page.php?id_materi=<?= $m['id'] ?>" class="btn btn-sm btn-warning text-dark px-3 fw-bold" onclick="this.innerHTML='<i class=\'fas fa-magic fa-spin\'></i> AI Meracik...';">
                                        <i class="fas fa-robot"></i> AI Soal
                                    </a>
                                    <a href="preview_kuis.php?id_materi=<?= $m['id'] ?>" class="btn btn-sm btn-info text-white px-3">
                                        <i class="fas fa-eye"></i> Preview
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="text-center text-muted mt-5 small">
    <p>&copy; 2026 Portal PADI Seni - Menginspirasi Melalui Edukasi</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>