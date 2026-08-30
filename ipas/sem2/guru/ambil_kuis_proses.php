<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$tryout_id = $_GET['tryout_id'] ?? null;
$user_id = $_SESSION['user_id'];
$materi_id_terpilih = $_POST['materi_id'] ?? null;

// Cek keamanan agar ID tidak kosong
if (!$tryout_id) {
    die("Error: ID Tryout tidak terbaca. Pastikan Anda masuk melalui halaman Manajemen Tryout.");
}

// PROSES PINDAHKAN SOAL
if (isset($_POST['proses_pindah_soal'])) {
    $selected_soal = $_POST['soal_ids'] ?? [];
    if (!empty($selected_soal)) {
        $ids = implode(',', array_map('intval', $selected_soal));
        
        // Query lengkap menyalin semua kolom termasuk gambar_url
        $sql_import = "INSERT INTO soal_tryout (tryout_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar) 
                       SELECT $tryout_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar 
                       FROM soal WHERE id IN ($ids)";
        
        if ($db_mapel->query($sql_import)) {
            echo "<script>alert('Berhasil memindahkan " . count($selected_soal) . " soal!'); window.location='form_soal_tryout.php?tryout_id=$tryout_id';</script>";
            exit();
        } else {
            die("Gagal memindahkan soal: " . $db_mapel->error);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Pilih Soal dari Kuis Materi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-file-import me-2"></i>Pilih Soal dari Materi</h5>
            <a href="manajemen_tryout.php" class="btn btn-sm btn-light">Batal</a>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 mb-4">
                <div class="col-md-9">
                    <select name="materi_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Pilih Materi untuk Lihat Soal --</option>
                        <?php
                        $materi_list = $db_mapel->query("SELECT id, judul FROM materi WHERE id_guru = $user_id");
                        while($m = $materi_list->fetch_assoc()):
                        ?>
                        <option value="<?= $m['id']; ?>" <?= ($materi_id_terpilih == $m['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($m['judul']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100">Tampilkan Soal</button>
                </div>
            </form>

            <?php if ($materi_id_terpilih): ?>
            <form method="POST">
                <input type="hidden" name="materi_id" value="<?= $materi_id_terpilih; ?>">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="50" class="text-center">Pilih</th>
                                <th>Pertanyaan & Opsi Jawaban</th>
                                <th width="80" class="text-center">Kunci</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $soal_list = $db_mapel->query("SELECT * FROM soal WHERE materi_id = $materi_id_terpilih");
                            while($s = $soal_list->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="soal_ids[]" value="<?= $s['id']; ?>" class="form-check-input" style="transform: scale(1.5);">
                                </td>
                                <td>
                                    <strong><?= strip_tags($s['pertanyaan']); ?></strong>
                                    <div class="row mt-2 small">
                                        <div class="col-md-6 text-muted">A. <?= htmlspecialchars($s['opsi_a']); ?></div>
                                        <div class="col-md-6 text-muted">B. <?= htmlspecialchars($s['opsi_b']); ?></div>
                                        <div class="col-md-6 text-muted">C. <?= htmlspecialchars($s['opsi_c']); ?></div>
                                        <div class="col-md-6 text-muted">D. <?= htmlspecialchars($s['opsi_d']); ?></div>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-info"><?= $s['jawaban_benar']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" name="proses_pindah_soal" class="btn btn-success fw-bold">
                        <i class="fas fa-check-circle me-1"></i> Pindahkan Soal Terpilih
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>