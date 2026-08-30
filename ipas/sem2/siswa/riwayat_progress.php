<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';

// --- BAGIAN KRUSIAL: Memastikan id_guru_siswa terisi dengan benar ---
$kelas_siswa = $_SESSION['kelas'] ?? 'N/A';
$id_guru_siswa = $_SESSION['id_guru'] ?? 0;

// Jika id_guru atau kelas tidak terisi di sesi (seperti yang terjadi di kasus Anda), ambil dari DB
if ($id_guru_siswa == 0 || $kelas_siswa == 'N/A') {
    $stmt_user = $db_mapel->prepare("SELECT kelas, id_guru FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($row_user = $result_user->fetch_assoc()) {
        $kelas_siswa = $row_user['kelas'];
        $id_guru_siswa = $row_user['id_guru'];

        // Update Session untuk konsistensi
        $_SESSION['kelas'] = $kelas_siswa;
        $_SESSION['id_guru'] = $id_guru_siswa;
    }
    $stmt_user->close();
}
// --- AKHIR BAGIAN KRUSIAL ---

$all_progress_temp = [];

// Cek apakah data Guru dan Kelas berhasil diidentifikasi
if ($id_guru_siswa > 0 && $kelas_siswa !== 'N/A') {

    // --- A. RIWAYAT KUIS (Sudah Dikerjakan) ---
    $query_kuis_done = "
        SELECT hk.persentase AS skor_percent, hk.tanggal_dikerjakan AS waktu_pengerjaan, m.id AS master_id,
               m.judul AS judul, 'Kuis' AS tipe, 'Materi' AS jenis_ujian, hk.status_lulus, hk.id AS riwayat_id
        FROM riwayat_kuis hk
        JOIN materi m ON hk.id_materi = m.id
        WHERE hk.id_user = ? AND m.id_guru = ?
        ORDER BY hk.tanggal_dikerjakan DESC
    ";
    $stmt_kuis_done = $db_mapel->prepare($query_kuis_done);
    $stmt_kuis_done->bind_param("ii", $user_id, $id_guru_siswa);
    $stmt_kuis_done->execute();
    $result_kuis_done = $stmt_kuis_done->get_result();

    while ($row = $result_kuis_done->fetch_assoc()) {
        $row['status'] = 'Dikerjakan';
        $row['skor_tampil'] = round($row['skor_percent']);
        if (!isset($all_progress_temp['Kuis'][$row['master_id']])) {
            $all_progress_temp['Kuis'][$row['master_id']] = $row;
        }
    }
    $stmt_kuis_done->close();

    // --- B. RIWAYAT TRY OUT (Sudah Dikerjakan) ---
    $query_tryout_done = "
        SELECT rt.persentase AS skor_percent, rt.tanggal_dikerjakan AS waktu_pengerjaan, tm.id AS master_id,
               tm.judul, 'Try Out' AS tipe, tm.jenis_ujian, rt.status_lulus, rt.id AS riwayat_id
        FROM riwayat_tryout rt
        JOIN tryout_master tm ON rt.tryout_id = tm.id
        WHERE rt.id_user = ? AND tm.id_guru = ?
        ORDER BY rt.tanggal_dikerjakan DESC
    ";
    $stmt_tryout_done = $db_mapel->prepare($query_tryout_done);
    $stmt_tryout_done->bind_param("ii", $user_id, $id_guru_siswa);
    $stmt_tryout_done->execute();
    $result_tryout_done = $stmt_tryout_done->get_result();

    while ($row = $result_tryout_done->fetch_assoc()) {
        $row['status'] = 'Dikerjakan';
        $row['skor_tampil'] = round($row['skor_percent']);
        if (!isset($all_progress_temp['Try Out'][$row['master_id']])) {
            $all_progress_temp['Try Out'][$row['master_id']] = $row;
        }
    }
    $stmt_tryout_done->close();

    // --- C. MATERI YANG BELUM DIKERJAKAN (Kuis) ---
    $query_kuis_available = "
        SELECT id AS master_id, judul, 'Kuis' AS tipe, 'Materi' AS jenis_ujian, 0 AS riwayat_id
        FROM materi
        WHERE level_kategori = ? AND id_guru = ?
        AND id NOT IN (
            SELECT DISTINCT id_materi FROM riwayat_kuis WHERE id_user = ?
        )
    ";
    $stmt_kuis_available = $db_mapel->prepare($query_kuis_available);
    $stmt_kuis_available->bind_param("iii", $kelas_siswa, $id_guru_siswa, $user_id);
    $stmt_kuis_available->execute();
    $result_kuis_available = $stmt_kuis_available->get_result();

    while ($row = $result_kuis_available->fetch_assoc()) {
        $row['status'] = 'Belum Dikerjakan';
        $row['waktu_pengerjaan'] = 'N/A';
        $row['skor_tampil'] = 'N/A';
        $row['status_lulus'] = 'N/A';
        $all_progress_temp['Kuis'][$row['master_id']] = $row;
    }
    $stmt_kuis_available->close();

    // --- D. TRY OUT YANG BELUM DIKERJAKAN ---
    $query_tryout_available = "
        SELECT id AS master_id, judul, 'Try Out' AS tipe, jenis_ujian, 0 AS riwayat_id
        FROM tryout_master
        WHERE kelas = ? AND id_guru = ?
        AND id NOT IN (
            SELECT DISTINCT tryout_id FROM riwayat_tryout WHERE id_user = ?
        )
    ";
    $stmt_tryout_available = $db_mapel->prepare($query_tryout_available);
    $stmt_tryout_available->bind_param("iii", $kelas_siswa, $id_guru_siswa, $user_id);
    $stmt_tryout_available->execute();
    $result_tryout_available = $stmt_tryout_available->get_result();

    while ($row = $result_tryout_available->fetch_assoc()) {
        $row['status'] = 'Belum Dikerjakan';
        $row['waktu_pengerjaan'] = 'N/A';
        $row['skor_tampil'] = 'N/A';
        $row['status_lulus'] = 'N/A';
        $all_progress_temp['Try Out'][$row['master_id']] = $row;
    }
    $stmt_tryout_available->close();

} // Tutup if ($id_guru_siswa > 0 && $kelas_siswa !== 'N/A')

$db_mapel->close();

// --- Menggabungkan dan Finalisasi ---
$all_progress = [];
if (isset($all_progress_temp['Kuis'])) {
    foreach ($all_progress_temp['Kuis'] as $item) {
        $all_progress[] = $item;
    }
}
if (isset($all_progress_temp['Try Out'])) {
    foreach ($all_progress_temp['Try Out'] as $item) {
        $all_progress[] = $item;
    }
}

// Urutkan: Dikerjakan Terbaru -> Belum Dikerjakan
usort($all_progress, function($a, $b) {
    if ($a['status'] === 'Belum Dikerjakan' && $b['status'] !== 'Belum Dikerjakan') {
        return 1;
    }
    if ($a['status'] !== 'Belum Dikerjakan' && $b['status'] === 'Belum Dikerjakan') {
        return -1;
    }

    if ($a['status'] !== 'Belum Dikerjakan' && $b['status'] !== 'Belum Dikerjakan') {
        $time_a = strtotime($a['waktu_pengerjaan'] ?? '1970-01-01');
        $time_b = strtotime($b['waktu_pengerjaan'] ?? '1970-01-01');
        return $time_b - $time_a;
    }
    return strcmp($a['judul'], $b['judul']);
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Progress Siswa | IPAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; }
        .header-blue { background-color: #198754; color: white; padding: 30px 0; }
        .progress-card { border-left: 5px solid; margin-bottom: 15px; }
        .card-done { border-color: #198754; }
        .card-not-done { border-color: #ffc107; }
        .score-badge { font-size: 1.1rem; padding: 0.5rem 0.8rem; }
    </style>
</head>
<body>

<div class="header-blue text-center shadow-sm">
    <div class="container">
        <h1 class="display-5 fw-bold"><i class="fas fa-chart-line"></i> Riwayat Progress Siswa</h1>
        <p class="lead">Semua upaya Try Out dan Kuis yang tersedia/dikerjakan, "<?php echo htmlspecialchars($nama_pengguna); ?>"</p>
    </div>
</div>

<div class="container mt-4 mb-5">
    <a href="dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>

    <h4 class="text-green mb-3"><i class="fas fa-list-alt"></i> Progress & Riwayat Anda (Kelas <?php echo htmlspecialchars($kelas_siswa); ?>)</h4>

    <?php if ($id_guru_siswa == 0 || $kelas_siswa === 'N/A'): ?>
         <div class="alert alert-danger text-center" role="alert">
            <i class="fas fa-exclamation-triangle"></i> **KESALAHAN KONFIGURASI:** Level kelas atau Guru Pembimbing Anda tidak teridentifikasi. Silakan hubungi Guru atau Admin untuk mengatur data Anda.
        </div>
    <?php elseif (empty($all_progress)): ?>
        <div class="alert alert-info text-center" role="alert">
            <i class="fas fa-info-circle"></i> Belum ada Try Out atau Kuis yang tersedia dari Guru Pembimbing Anda.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($all_progress as $progress): ?>
            <?php
                $is_done = $progress['status'] === 'Dikerjakan';
                $is_tryout = $progress['tipe'] === 'Try Out';

                if ($is_done) {
                    $card_class = 'card-done';
                    $waktu_tampil = date('d M Y H:i', strtotime($progress['waktu_pengerjaan']));
                    $status_db = trim($progress['status_lulus'] ?? '');

                    if (!empty($status_db) && ($status_db === 'LULUS' || $status_db === 'GAGAL')) {
                        $status_teks = htmlspecialchars($status_db);
                    } else {
                        $persentase = $progress['skor_percent'] ?? 0;
                        $status_teks = $persentase >= 70 ? 'LULUS' : 'GAGAL';
                    }
                    $skor_color = ($status_teks === 'LULUS') ? 'success' : 'danger';

                    if ($is_tryout) {
                        $detail_link = "review_tryout.php?session_id=" . ($progress['riwayat_id']);
                    } else {
                        $waktu_param = urlencode($progress['waktu_pengerjaan']);
                        $detail_link = "review_kuis.php?materi_id=" . $progress['master_id'] . "&waktu=" . $waktu_param;
                    }
                    $button_html = '<a href="' . $detail_link . '" class="btn btn-sm btn-primary w-100 mt-2"><i class="fas fa-eye"></i> Lihat Pembahasan</a>';

                } else {
                    $card_class = 'card-not-done';
                    $skor_color = 'secondary';
                    $status_teks = 'Belum Dikerjakan';
                    $waktu_tampil = 'N/A';

                    $start_link = $is_tryout ? "tryout.php?tryout_id=" . $progress['master_id'] : "materi_view.php?id=" . $progress['master_id'];
                    $button_html = '<a href="' . $start_link . '" class="btn btn-sm btn-success w-100 mt-2"><i class="fas fa-play"></i> Mulai Sekarang</a>';
                }
            ?>
            <div class="col-12">
                <div class="card shadow progress-card <?php echo $card_class; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($progress['judul']); ?>
                                <br><small class="text-muted">(Jenis: <?php echo htmlspecialchars($progress['jenis_ujian']); ?>)</small>
                            </h5>
                            <span class="badge <?php echo $is_tryout ? 'bg-primary' : 'bg-info text-dark'; ?>">
                                <?php echo htmlspecialchars($progress['tipe']); ?>
                            </span>
                        </div>

                        <hr class="my-2">

                        <div class="row">
                            <div class="col-6">
                                <p class="mb-1 small text-muted">Skor Anda:</p>
                                <span class="badge score-badge rounded-pill bg-<?php echo $skor_color; ?>">
                                    <?php echo htmlspecialchars($progress['skor_tampil']); ?>
                                </span>
                            </div>
                            <div class="col-6">
                                <p class="mb-1 small text-muted">Status:</p>
                                <span class="badge score-badge rounded-pill bg-<?php echo $skor_color; ?>" style="font-size: 1em; padding: 0.6em 0.6em;">
                                    <?php echo $status_teks; ?>
                                </span>
                            </div>
                        </div>

                        <p class="mt-2 mb-1 small text-muted">Waktu Terakhir Dikerjakan: **<?php echo $waktu_tampil; ?>**</p>

                        <?php echo $button_html; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>