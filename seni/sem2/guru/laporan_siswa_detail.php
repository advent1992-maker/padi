<?php
// FILE: guru/laporan_siswa_detail.php

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan variabel koneksi menggunakan $db_mapel
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_GET['user_id'] ?? 0;
if (!$user_id) {
    echo "<script>alert('ID pengguna tidak valid.'); window.location.href='laporan_progres.php';</script>";
    exit();
}

/* ==============================
    1. DATA SISWA
============================== */
// Menggunakan $db_mapel
$stmt_siswa = $conn->prepare("
    SELECT nama_lengkap, kelas
    FROM users
    WHERE id = ? AND role = 'siswa'
");
$stmt_siswa->bind_param("i", $user_id);
$stmt_siswa->execute();
$result_siswa = $stmt_siswa->get_result();

if ($result_siswa->num_rows === 0) {
    echo "<script>alert('Siswa tidak ditemukan.'); window.location.href='laporan_progres.php';</script>";
    exit();
}
$siswa_data = $result_siswa->fetch_assoc();
$stmt_siswa->close();

/* ==============================
    2. RIWAYAT KUIS
============================== */
$riwayat_kuis = [];
// Menggunakan $db_mapel
$stmt_kuis = $db_mapel->prepare("
    SELECT rk.id, m.judul, rk.persentase, rk.tanggal_dikerjakan
    FROM riwayat_kuis rk
    JOIN materi m ON rk.id_materi = m.id
    WHERE rk.id_user = ?
    ORDER BY rk.tanggal_dikerjakan DESC
");
$stmt_kuis->bind_param("i", $user_id);
$stmt_kuis->execute();
$res_kuis = $stmt_kuis->get_result();

while ($row = $res_kuis->fetch_assoc()) {
    $riwayat_kuis[] = $row;
}
$stmt_kuis->close();

/* ==============================
    3. RIWAYAT TRYOUT
============================== */
$riwayat_tryout = [];
$stmt_tryout = $db_mapel->prepare("
    SELECT rt.id, tm.judul AS nama_tryout, rt.persentase, rt.tanggal_dikerjakan
    FROM riwayat_tryout rt
    JOIN tryout_master tm ON rt.tryout_id = tm.id
    WHERE rt.id_user = ?
    ORDER BY rt.tanggal_dikerjakan DESC
");
$stmt_tryout->bind_param("i", $user_id);
$stmt_tryout->execute();
$res_tryout = $stmt_tryout->get_result();

while ($row = $res_tryout->fetch_assoc()) {
    $riwayat_tryout[] = $row;
}
$stmt_tryout->close();

/* ==============================
    4. RIWAYAT PRAKTEK (SESUAI DATABASE)
============================== */
$riwayat_praktek = [];
// Query ini menggabungkan tabel praktek_siswa dengan tabel materi
$stmt_praktek = $db_mapel->prepare("
    SELECT ps.id, m.judul AS nama_praktek, ps.nilai_angka, ps.status_dinilai, ps.tanggal_upload
    FROM praktek_siswa ps
    JOIN materi m ON ps.materi_id = m.id
    WHERE ps.id_siswa = ?
    ORDER BY ps.tanggal_upload DESC
");

if ($stmt_praktek) {
    $stmt_praktek->bind_param("i", $user_id);
    $stmt_praktek->execute();
    $res_praktek = $stmt_praktek->get_result();
    while ($row = $res_praktek->fetch_assoc()) {
        $riwayat_praktek[] = $row;
    }
    $stmt_praktek->close();
}

$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Progres Siswa | <?= htmlspecialchars($siswa_data['nama_lengkap']); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']]
            },
            svg: {
                fontCache: 'global'
            }
        };
    </script>
    <style>
        body { background: #f8f9fa; }
        .container-custom { padding: 40px; }
        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #fff 100%);
            padding: 25px; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 5px solid #0d6efd;
            margin-bottom: 30px;
        }
        .section-title {
            color: #1a4f8f; font-weight: 700;
            margin: 30px 0 20px;
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }
        .table-rounded { border-radius: 10px; overflow: hidden; }

        /* Gaya untuk MathJax di modal */
        .MathJax_Display { overflow-x: auto; overflow-y: hidden; padding: 5px 0; }
        .modal-body .img-fluid { max-width: 100%; height: auto; object-fit: contain; }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-primary shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-chalkboard-teacher me-2"></i> Dashboard Guru
        </a>
    </div>
</nav>

<div class="container container-custom">

    <a href="laporan.php" class="btn btn-secondary mb-4">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>

    <h2 class="text-primary fw-bold mb-4">
        <i class="fas fa-user-check me-2"></i> Detail Progres Siswa
    </h2>

    <div class="info-card d-flex justify-content-between align-items-center">
        <div>
            <p class="text-muted mb-1">Nama Lengkap:</p>

            <h3 class="fw-bold mb-3"><?= htmlspecialchars($siswa_data['nama_lengkap']); ?></h3>

            <p class="text-muted mb-1">Kelas:</p>
            <span class="badge bg-info text-dark fs-5"><?= htmlspecialchars($siswa_data['kelas']); ?></span>
        </div>

        <button class="btn btn-success btn-lg"
            onclick="exportToCSV('detail_progres_<?= htmlspecialchars($siswa_data['nama_lengkap']); ?>.csv')">
            <i class="fas fa-file-excel me-2"></i> Download CSV
        </button>
    </div>

    <div class="section-title"><i class="fas fa-book-open me-2"></i> Riwayat Kuis</div>

    <div class="table-responsive">
        <?php if ($riwayat_kuis): ?>
        <table class="table table-bordered table-striped table-rounded" id="kuis-table">
            <thead class="table-info">
                <tr>
                    <th>#</th>
                    <th>Bab Materi</th>
                    <th>Nilai</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php $i = 1; foreach ($riwayat_kuis as $r): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td><?= htmlspecialchars($r['judul']); ?></td>
                    <td><span class="badge bg-success"><?= round($r['persentase']); ?></span></td>
                    <td><?= htmlspecialchars($r['tanggal_dikerjakan']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-detail-kuis"
                            data-id-riwayat="<?= $r['id']; ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#detailKuisModal">
                            Detail
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
        <div class="alert alert-warning">Belum ada riwayat kuis.</div>
        <?php endif; ?>
    </div>

    <div class="section-title"><i class="fas fa-flask me-2"></i> Riwayat Tryout</div>

    <div class="table-responsive">
        <?php if ($riwayat_tryout): ?>
        <table class="table table-bordered table-striped table-rounded" id="tryout-table">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Nama Tryout</th>
                    <th>Nilai</th>
                    <th>Waktu</th>
                    <th>Aksi</th> </tr>
            </thead>

            <tbody>
                <?php $i = 1; foreach ($riwayat_tryout as $r): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td><?= htmlspecialchars($r['nama_tryout']); ?></td>
                    <td><span class="badge bg-primary"><?= round($r['persentase']); ?></span></td>
                    <td><?= htmlspecialchars($r['tanggal_dikerjakan']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-detail-tryout"
                            data-id-riwayat="<?= $r['id']; ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#detailTryoutModal">
                            Detail
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
        <div class="alert alert-warning">Belum ada riwayat tryout.</div>
        <?php endif; ?>
    </div>
    <div class="section-title"><i class="fas fa-tools me-2"></i> Riwayat Tugas Praktek</div>

<div class="table-responsive">
    <?php if ($riwayat_praktek): ?>
    <table class="table table-bordered table-striped table-rounded" id="praktek-table">
        <thead class="table-warning">
            <tr>
                <th width="50">#</th>
                <th>Nama Tugas Praktek</th>
                <th>Nilai</th>
                <th>Status</th>
                <th>Waktu Upload</th>
                <th class="no-print">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($riwayat_praktek as $r): ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($r['nama_praktek']); ?></td>
                <td>
                    <span class="badge bg-dark fs-6">
                        <?= ($r['nilai_angka'] > 0) ? $r['nilai_angka'] : '-'; ?>
                    </span>
                </td>
                <td>
                    <?php if($r['status_dinilai'] == 1): ?>
                        <span class="badge bg-success">Sudah Dinilai</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Belum Diperiksa</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['tanggal_upload']); ?></td>
                <td class="no-print">
                    
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">Belum ada tugas praktek yang dikirim oleh siswa ini.</div>
    <?php endif; ?>
</div>


<div class="modal fade" id="detailKuisModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i> Detail Jawaban Kuis</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="detail-kuis-content" class="text-center p-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2">Memuat detail...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailTryoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-flask me-2"></i> Detail Jawaban Tryout</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="detail-tryout-content" class="text-center p-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2">Memuat detail...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* DETAIL KUIS (AJAX) */
    document.querySelectorAll('.btn-detail-kuis').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.idRiwayat;
            const content = document.getElementById('detail-kuis-content');

            content.innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Memuat detail jawaban Kuis...</p>
                </div>
            `;

            fetch(`api_detail_jawaban.php?riwayat_id=${id}`)
                .then(r => r.text())
                .then(html => {
                    content.innerHTML = html;
                    // BARIS PENTING: Panggil ulang MathJax setelah konten baru dimuat
                    if (window.MathJax) {
                        MathJax.typesetPromise([content]);
                    }
                })
                .catch(e => content.innerHTML = `<div class="alert alert-danger">Gagal memuat detail Kuis: ${e}</div>`);
        });
    });

    /* DETAIL TRYOUT (AJAX) - LOGIC BARU */
    document.querySelectorAll('.btn-detail-tryout').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.idRiwayat;
            const content = document.getElementById('detail-tryout-content'); // Target modal content Tryout

            content.innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Memuat detail jawaban Tryout...</p>
                </div>
            `;

            // Memanggil API baru: api_detail_tryout.php
            fetch(`api_detail_tryout.php?riwayat_id=${id}`)
                .then(r => r.text())
                .then(html => {
                    content.innerHTML = html;
                    // BARIS PENTING: Panggil ulang MathJax setelah konten baru dimuat
                    if (window.MathJax) {
                        MathJax.typesetPromise([content]);
                    }
                })
                .catch(e => content.innerHTML = `<div class="alert alert-danger">Gagal memuat detail Tryout: ${e}</div>`);
        });
    });

    /* EXPORT CSV (Tetap sama) */
    window.exportToCSV = function(filename) {
        let csv = [];
        const delimiter = ";";

        csv.push("Detail Laporan Progres Siswa");
        csv.push(`Nama${delimiter}<?= addslashes($siswa_data['nama_lengkap']); ?>`);
        csv.push(`Kelas${delimiter}<?= addslashes($siswa_data['kelas']); ?>`);
        csv.push("");

        function tableToCSV(table, title) {
            if (!table) return;

            csv.push(title);
            const rows = table.querySelectorAll("tr");

            rows.forEach(row => {
                // Jangan sertakan kolom Aksi (last-child)
                const cols = row.querySelectorAll("th, td:not(:last-child)");
                const rowData = [...cols].map(col =>
                    col.innerText.trim().replace(/%/g, "")
                );
                csv.push(rowData.join(delimiter));
            });
            csv.push("");
        }

        tableToCSV(document.getElementById("kuis-table"), "RIWAYAT KUIS");
        tableToCSV(document.getElementById("tryout-table"), "RIWAYAT TRYOUT"); // Menggunakan tryout-table
        tableToCSV(document.getElementById("praktek-table"), "RIWAYAT PRAKTEK");

        const blob = new Blob([csv.join("\n")], { type: "text/csv" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    };
});
</script>

</body>
</html>