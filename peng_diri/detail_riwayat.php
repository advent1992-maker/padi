<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../index.php"); 
    exit;
}

// HAPUS baris mysqli_connect yang lama karena database sudah digabung
// Cukup gunakan $conn yang berasal dari koneksi.php

$id_user = mysqli_real_escape_string($conn, $_GET['id_user']);
$id_materi = mysqli_real_escape_string($conn, $_GET['id_materi']);

// 1. Ambil info siswa dari tabel users
$q_user = mysqli_query($conn, "SELECT nama_lengkap FROM users WHERE id = '$id_user'");
$user = mysqli_fetch_assoc($q_user);

// 2. Ambil info paket (Pastikan tabel ini ada di database yang sama)
$q_paket = mysqli_query($conn, "SELECT nama_paket FROM paket_peng_diri WHERE id = '$id_materi'");
$paket = mysqli_fetch_assoc($q_paket);

// 3. Ambil riwayat lengkap
$sql_history = "SELECT * FROM riwayat_kuis WHERE id_user = '$id_user' AND id_materi = '$id_materi' ORDER BY tanggal_dikerjakan DESC";
$res_history = mysqli_query($conn, $sql_history);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Riwayat Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow border-0 rounded-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-primary">
                    <i class="fas fa-user-graduate me-2"></i> Riwayat: <?= htmlspecialchars($user['nama_lengkap'] ?? 'Tidak Ditemukan') ?>
                </h5>
                <a href="javascript:history.back()" class="btn btn-sm btn-secondary">Kembali</a>
            </div>
            <p class="text-muted small mb-0 mt-1">Paket Soal: <?= htmlspecialchars($paket['nama_paket'] ?? 'Umum') ?></p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal Dikerjakan</th>
                            <th class="text-center">Skor</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($res_history) > 0): ?>
                            <?php while($h = mysqli_fetch_assoc($res_history)): ?>
                            <tr>
                                <td><?= date('d M Y - H:i', strtotime($h['tanggal_dikerjakan'])) ?> WIB</td>
                                <td class="text-center fw-bold text-primary">
                                    <?= round($h['persentase']) ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $status = strtoupper($h['status_lulus'] ?? '');
                                    $bg_color = ($status == 'LULUS') ? 'success' : 'danger';
                                    ?>
                                    <span class="badge bg-<?= $bg_color ?>">
                                        <?= $status ?: 'SELESAI' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted italic">Belum ada riwayat pengerjaan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>