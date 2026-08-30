<?php
// FILE: detail_siswa.php
require_once dirname(__FILE__) . '/../../../config/session.php';
require_once dirname(__FILE__) . '/../../../config/koneksi.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../../../index.php");
    exit();
}

$id_siswa = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_siswa) {
    echo "<script>alert('ID Siswa tidak valid.'); window.location.href='monitor_nilai.php';</script>";
    exit();
}

/* ==============================
    1. DATA PROFIL SISWA (DB PUSAT)
============================== */
$stmt_siswa = $conn->prepare("SELECT nama_lengkap, kelas FROM db_portal_pusat.users WHERE id = ?");
$stmt_siswa->bind_param("i", $id_siswa);
$stmt_siswa->execute();
$siswa_data = $stmt_siswa->get_result()->fetch_assoc();
if (!$siswa_data) { die("Siswa tidak ditemukan."); }

/* ==============================
    2. STATISTIK RINGKASAN
============================== */
$stmt_stats = $db_mapel->prepare("
    SELECT
        COUNT(DISTINCT id_materi) AS total_materi_dicoba,
        COUNT(DISTINCT CASE WHEN status_lulus = 'LULUS' THEN id_materi ELSE NULL END) AS total_lulus,
        AVG(persentase) AS avg_kuis
    FROM riwayat_kuis WHERE id_user = ?
");
$stmt_stats->bind_param("i", $id_siswa);
$stmt_stats->execute();
$res_stats = $stmt_stats->get_result()->fetch_assoc();

$stmt_to_stats = $db_mapel->prepare("SELECT AVG(persentase) AS avg_to, COUNT(DISTINCT tryout_id) AS total_to FROM riwayat_tryout WHERE id_user = ?");
$stmt_to_stats->bind_param("i", $id_siswa);
$stmt_to_stats->execute();
$res_to_stats = $stmt_to_stats->get_result()->fetch_assoc();

$total_avg = 0; $count_valid = 0;
if (($res_stats['avg_kuis'] ?? 0) > 0) { $total_avg += $res_stats['avg_kuis']; $count_valid++; }
if (($res_to_stats['avg_to'] ?? 0) > 0) { $total_avg += $res_to_stats['avg_to']; $count_valid++; }
$rata_rata_gabungan = ($count_valid > 0) ? round($total_avg / $count_valid) : 0;
$total_pengerjaan = ($res_stats['total_materi_dicoba'] ?? 0) + ($res_to_stats['total_to'] ?? 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Progres Siswa | <?= htmlspecialchars($siswa_data['nama_lengkap']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <style>
        body { background: #f4f7fe; }
        .hero-mini { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 30px; border-radius: 0 0 30px 30px; }
        .stat-card { border-radius: 15px; border: none; transition: 0.3s; }
        .section-title { color: #1a4f8f; font-weight: 700; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-top: 40px; }
    </style>
</head>
<body>

<div class="hero-mini shadow-sm mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0"><?= htmlspecialchars($siswa_data['nama_lengkap']); ?></h2>
            <p class="mb-0 opacity-75">Kelas <?= htmlspecialchars($siswa_data['kelas']); ?> | Monitoring Akademik</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success fw-bold rounded-pill px-4" onclick="exportToCSV('Laporan_<?= str_replace(' ', '_', $siswa_data['nama_lengkap']); ?>.csv')">
                <i class="fas fa-file-excel me-2"></i> Ekspor CSV
            </button>
            <a href="monitor_nilai.php" class="btn btn-light fw-bold rounded-pill px-4 text-primary">Kembali</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4 text-center mb-5">
        <div class="col-md-4"><div class="card stat-card shadow-sm p-4"><h6 class="text-muted small fw-bold">MATERI LULUS</h6><h1 class="display-4 fw-bold text-success"><?= $res_stats['total_lulus'] ?></h1></div></div>
        <div class="col-md-4"><div class="card stat-card shadow-sm p-4"><h6 class="text-muted small fw-bold">TOTAL PENGERJAAN</h6><h1 class="display-4 fw-bold text-primary"><?= $total_pengerjaan ?></h1></div></div>
        <div class="col-md-4"><div class="card stat-card shadow-sm p-4"><h6 class="text-muted small fw-bold">SKOR RATA-RATA</h6><h1 class="display-4 fw-bold text-danger"><?= $rata_rata_gabungan ?></h1></div></div>
    </div>

    <h4 class="section-title mb-3"><i class="fas fa-book-open me-2"></i> Riwayat Kuis Bab</h4>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0" id="kuis-table">
            <thead class="table-info">
                <tr><th>#</th><th>Judul Bab</th><th class="text-center">Nilai</th><th>Waktu</th><th class="text-center">Aksi</th></tr>
            </thead>
            <tbody>
                <?php
                $stmt_rk = $db_mapel->prepare("SELECT rk.id, rk.persentase, rk.tanggal_dikerjakan, m.judul FROM riwayat_kuis rk JOIN materi m ON rk.id_materi = m.id WHERE rk.id_user = ? ORDER BY rk.tanggal_dikerjakan DESC");
                $stmt_rk->bind_param("i", $id_siswa); $stmt_rk->execute();
                $res_rk = $stmt_rk->get_result();
                $i = 1; if($res_rk->num_rows > 0): while($r = $res_rk->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td><?= htmlspecialchars($r['judul']); ?></td>
                    <td class="text-center"><span class="badge bg-<?= $r['persentase'] >= 70 ? 'success' : 'danger' ?> fs-6"><?= round($r['persentase']); ?>%</span></td>
                    <td class="small"><?= $r['tanggal_dikerjakan']; ?></td>
                    <td class="text-center"><button class="btn btn-outline-primary btn-sm rounded-pill btn-detail" data-type="kuis" data-id="<?= $r['id'] ?>" data-bs-toggle="modal" data-bs-target="#modalDetail">Detail</button></td>
                </tr>
                <?php endwhile; else: echo "<tr><td colspan='5' class='text-center py-4'>Belum ada riwayat kuis.</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>

    <h4 class="section-title mb-3"><i class="fas fa-rocket me-2"></i> Riwayat Tryout</h4>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0" id="tryout-table">
            <thead class="table-primary">
                <tr><th>#</th><th>Nama Tryout</th><th class="text-center">Nilai</th><th>Waktu</th><th class="text-center">Aksi</th></tr>
            </thead>
            <tbody>
                <?php
                $stmt_rt = $db_mapel->prepare("SELECT rt.id, rt.persentase, rt.tanggal_dikerjakan, tm.judul FROM riwayat_tryout rt JOIN tryout_master tm ON rt.tryout_id = tm.id WHERE rt.id_user = ? ORDER BY rt.tanggal_dikerjakan DESC");
                $stmt_rt->bind_param("i", $id_siswa); $stmt_rt->execute();
                $res_rt = $stmt_rt->get_result();
                $j = 1; if($res_rt->num_rows > 0): while($rt = $res_rt->fetch_assoc()): ?>
                <tr>
                    <td><?= $j++; ?></td>
                    <td><?= htmlspecialchars($rt['judul']); ?></td>
                    <td class="text-center"><span class="badge bg-primary fs-6"><?= round($rt['persentase']); ?>%</span></td>
                    <td class="small"><?= $rt['tanggal_dikerjakan']; ?></td>
                    <td class="text-center"><button class="btn btn-outline-info btn-sm rounded-pill btn-detail" data-type="tryout" data-id="<?= $rt['id'] ?>" data-bs-toggle="modal" data-bs-target="#modalDetail">Detail</button></td>
                </tr>
                <?php endwhile; else: echo "<tr><td colspan='5' class='text-center py-4'>Belum ada riwayat tryout.</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-search me-2"></i> Analisis Jawaban Siswa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="content-detail">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2">Memuat data...</p></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fungsi AJAX Universal untuk Kuis & Tryout
document.querySelectorAll('.btn-detail').forEach(button => {
    button.addEventListener('click', function() {
        const type = this.getAttribute('data-type');
        const id = this.getAttribute('data-id');
        const content = document.getElementById('content-detail');
        const targetApi = (type === 'kuis') ? 'api_detail_jawaban.php' : 'api_detail_tryout.php';

        content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2">Mengambil detail ' + type + '...</p></div>';

        fetch(`${targetApi}?riwayat_id=${id}`)
            .then(response => response.text())
            .then(data => {
                content.innerHTML = data;
                if (window.MathJax) { MathJax.typesetPromise([content]); }
            })
            .catch(err => { content.innerHTML = '<div class="alert alert-danger">Error: Gagal memuat data.</div>'; });
    });
});

// Fungsi Export CSV
window.exportToCSV = function(filename) {
    let csv = [];
    const delimiter = ";";
    csv.push("LAPORAN DETAIL SISWA PADI");
    csv.push(`Nama:;<?= addslashes($siswa_data['nama_lengkap']); ?>`);
    csv.push(`Kelas:;<?= addslashes($siswa_data['kelas']); ?>`);
    csv.push("");

    function tableToCSV(tableId, title) {
        const table = document.getElementById(tableId);
        if (!table) return;
        csv.push(title);
        const rows = table.querySelectorAll("tr");
        rows.forEach(row => {
            const cols = row.querySelectorAll("th, td:not(:last-child)");
            csv.push([...cols].map(col => col.innerText.trim()).join(delimiter));
        });
        csv.push("");
    }
    tableToCSV("kuis-table", "RIWAYAT KUIS");
    tableToCSV("tryout-table", "RIWAYAT TRYOUT");

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
};
</script>
</body>
</html>