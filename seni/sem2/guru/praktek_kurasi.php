<?php
// FILE: guru/praktek_kurasi.php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Ambil parameter dari URL
$materi_id = isset($_GET['id_materi']) ? (int)$_GET['id_materi'] : 0;
$siswa_id = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;

if ($materi_id == 0 || $siswa_id == 0) {
    die("Parameter tidak lengkap.");
}

// 1. AMBIL DATA KARYA & NAMA SISWA
$q = $db_mapel->prepare("
    SELECT p.*, m.judul 
    FROM praktek_siswa p 
    JOIN materi m ON p.materi_id = m.id 
    WHERE p.materi_id = ? AND p.id_siswa = ?
");
$q->bind_param("ii", $materi_id, $siswa_id);
$q->execute();
$data = $q->get_result()->fetch_assoc();

if (!$data) {
    die("Karya tidak ditemukan di database.");
}

// Ambil nama siswa dari koneksi pusat
$q_siswa = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$q_siswa->bind_param("i", $siswa_id);
$q_siswa->execute();
$res_siswa = $q_siswa->get_result()->fetch_assoc();
$nama_siswa = $res_siswa['nama_lengkap'] ?? "Siswa Tidak Dikenal";

// 2. PROSES UPDATE NILAI
$show_success = false;
if (isset($_POST['simpan_nilai'])) {
    $nilai = (int)$_POST['nilai_angka'];
    $catatan = $_POST['catatan_guru'];
    
    $upd = $db_mapel->prepare("UPDATE praktek_siswa SET nilai_angka = ?, catatan_guru = ?, status_dinilai = 1 WHERE materi_id = ? AND id_siswa = ?");
    $upd->bind_param("isii", $nilai, $catatan, $materi_id, $siswa_id);
    
    if ($upd->execute()) {
        $show_success = true; // Trigger untuk SweetAlert
    } else {
        $error_msg = "Gagal menyimpan: " . $db_mapel->error;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koreksi Karya - <?= htmlspecialchars($nama_siswa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .kurasi-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .img-preview { width: 100%; border-radius: 10px; border: 1px solid #ddd; cursor: pointer; transition: 0.3s; }
        .img-preview:hover { transform: scale(1.01); }
        .header-koreksi { background: #0d6efd; color: white; padding: 20px; }
        .btn-primary { background: #0d6efd; border: none; }
    </style>
</head>
<body class="p-3 p-md-5">

<div class="container kurasi-card">
    <div class="header-koreksi d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fas fa-palette me-2"></i> Koreksi Karya</h4>
            <small class="opacity-75">Materi: <?= htmlspecialchars($data['judul']) ?></small>
        </div>
        <a href="laporan.php?materi_id=kuis_<?= $materi_id ?>" class="btn btn-light btn-sm rounded-pill px-3">Kembali</a>
    </div>

    <div class="row g-0">
        <div class="col-md-7 p-4 border-end">
            <h6 class="fw-bold text-muted mb-3">HASIL KARYA SISWA (<?= htmlspecialchars($nama_siswa) ?>)</h6>
            
            <?php 
                $path_foto = "../uploads/" . $data['foto_karya']; 
                if (file_exists($path_foto) && !empty($data['foto_karya'])):
            ?>
                <a href="<?= $path_foto ?>" target="_blank">
                    <img src="<?= $path_foto ?>" class="img-preview mb-3">
                </a>
            <?php else: ?>
                <div class="alert alert-warning">File gambar tidak ditemukan di folder uploads.</div>
            <?php endif; ?>

            <div class="p-3 bg-light rounded border">
                <small class="fw-bold text-primary d-block mb-1 text-uppercase">Deskripsi Siswa:</small>
                <p class="mb-0 italic"><?= nl2br(htmlspecialchars($data['deskripsi_siswa'] ?? '')) ?></p>
            </div>
        </div>

        <div class="col-md-5 p-4">
            <h6 class="fw-bold text-muted mb-4 text-uppercase">Formulir Penilaian</h6>
            
            <form method="POST" id="formNilai">
               <div class="mb-4">
    <label class="form-label fw-bold">Nilai Angka (0-100)</label>
    <input type="number" name="nilai_angka" class="form-control form-control-lg border-primary" 
           placeholder="Masukkan nilai..."
           value="<?= $data['status_dinilai'] ? $data['nilai_angka'] : '' ?>" 
           min="0" max="100" required>
    <?php if (!$data['status_dinilai']): ?>
        <small class="text-muted italic">* Masih kosong (Menunggu koreksi)</small>
    <?php endif; ?>
</div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Catatan / Feedback Guru</label>
                    <textarea name="catatan_guru" class="form-control" rows="6" 
                              placeholder="Berikan apresiasi atau masukan agar siswa lebih semangat..."><?= htmlspecialchars($data['catatan_guru'] ?? '') ?></textarea>
                </div>

                <button type="submit" name="simpan_nilai" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">
                    <i class="fas fa-save me-2"></i> SIMPAN PENILAIAN
                </button>
            </form>
            
            <div class="mt-4 small text-muted text-center">
                <i class="fas fa-info-circle"></i> Status Saat Ini: 
                <span class="badge <?= $data['status_dinilai'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= $data['status_dinilai'] ? 'Sudah Dinilai' : 'Menunggu Koreksi' ?>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
// Tampilkan SweetAlert jika data berhasil disimpan
<?php if ($show_success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil Disimpan!',
        text: 'Nilai <?= htmlspecialchars($nama_siswa) ?> telah diperbarui.',
        confirmButtonColor: '#0d6efd',
    }).then((result) => {
        window.location.href = 'laporan.php?materi_id=<?= $materi_id ?>';
    });
<?php endif; ?>

// Tampilkan SweetAlert jika ada error
<?php if (isset($error_msg)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: '<?= $error_msg ?>',
        confirmButtonColor: '#d33'
    });
<?php endif; ?>
</script>

</body>
</html>