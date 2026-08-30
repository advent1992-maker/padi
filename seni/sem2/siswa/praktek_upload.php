<?php
// FILE: siswa/praktek_upload.php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

$user_id = $_SESSION['user_id'];
$materi_id = isset($_GET['materi_id']) ? (int)$_GET['materi_id'] : 0;

// Ambil info materi
$stmt_title = $db_mapel->prepare("SELECT judul FROM materi WHERE id = ?");
$stmt_title->bind_param("i", $materi_id);
$stmt_title->execute();
$m_data = $stmt_title->get_result()->fetch_assoc();
$judul_materi = $m_data['judul'] ?? "Materi Praktek";

// Cek status pengumpulan
$q_cek = $db_mapel->prepare("SELECT * FROM praktek_siswa WHERE id_siswa = ? AND materi_id = ?");
$q_cek->bind_param("ii", $user_id, $materi_id);
$q_cek->execute();
$data_lama = $q_cek->get_result()->fetch_assoc();

// PROSES UPLOAD FILE (DIPANGGIL OLEH FETCH JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['karya_kompres'])) {
    $target_dir = "../uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $nama_file_baru = "KARYA_" . $materi_id . "_" . $user_id . "_" . time() . ".jpg";
    $target_file = $target_dir . $nama_file_baru;

    if (move_uploaded_file($_FILES["karya_kompres"]["tmp_name"], $target_file)) {
        $catatan = $_POST['catatan_siswa'] ?? '';
        
        // Simpan ke database
        $q_ins = $db_mapel->prepare("INSERT INTO praktek_siswa (materi_id, id_siswa, foto_karya, deskripsi_siswa) VALUES (?, ?, ?, ?)");
        $q_ins->bind_param("iiss", $materi_id, $user_id, $nama_file_baru, $catatan);
        
        if ($q_ins->execute()) {
            echo "success";
        } else {
            echo "Database Error: " . $db_mapel->error;
        }
    } else {
        echo "Error: Gagal simpan file ke server.";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Tugas - <?= htmlspecialchars($judul_materi) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7fc; font-family: 'Segoe UI', sans-serif; }
        .upload-card { max-width: 480px; margin: 30px auto; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header-custom { background: #6f42c1; color: white; padding: 25px; text-align: center; }
        .preview-container { width: 100%; min-height: 250px; border: 2px dashed #dee2e6; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; margin-bottom: 20px; overflow: hidden; }
        #imagePreview { width: 100%; height: auto; display: none; }
        .btn-purple { background: #6f42c1; color: white; border-radius: 10px; padding: 12px; font-weight: 600; width: 100%; border: none; transition: 0.3s; }
        .btn-purple:hover { background: #5a32a3; }
        .btn-purple:disabled { background: #ccc; }
        #loader { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.9); z-index: 10; flex-direction: column; align-items: center; justify-content: center; border-radius: 20px; }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="upload-card position-relative">
        <div id="loader">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <strong class="text-primary">Mengirim Karya...</strong>
        </div>

        <div class="card-header-custom">
            <h5 class="mb-0 fw-bold">Upload Karya Praktek</h5>
            <small class="opacity-75"><?= htmlspecialchars($judul_materi) ?></small>
        </div>

        <div class="card-body p-4">
            <?php if ($data_lama): ?>
                <div class="alert alert-success text-center py-4 border-0 shadow-sm">
                    <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                    <h6 class="fw-bold">Karya Sudah Dikirim</h6>
                    <p class="small text-muted">Anda sudah mengunggah tugas untuk materi ini.</p>
                    <a href="materi_view.php?id=<?= $materi_id ?>" class="btn btn-sm btn-purple px-4 mt-2">Kembali ke Materi</a>
                </div>
            <?php else: ?>
                <div class="preview-container">
                    <i id="phIcon" class="fas fa-camera fa-3x text-muted"></i>
                    <img id="imagePreview" src="#" alt="Preview Karya">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">AMBIL FOTO KARYA</label>
                    <input type="file" id="camInput" class="form-control" accept="image/*" capture="environment">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">CATATAN UNTUK GURU</label>
                    <textarea id="catatanInput" class="form-control" rows="2" placeholder="Tuliskan keterangan singkat..."></textarea>
                </div>

                <button id="btnKirim" class="btn btn-purple shadow-sm" disabled>
                    <i class="fas fa-cloud-upload-alt me-2"></i> KIRIM SEKARANG
                </button>
                
                <div class="text-center mt-3">
                    <a href="materi_view.php?id=<?= $materi_id ?>" class="text-decoration-none small text-muted">Batal</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const camInput = document.getElementById('camInput');
    const imagePreview = document.getElementById('imagePreview');
    const phIcon = document.getElementById('phIcon');
    const btnKirim = document.getElementById('btnKirim');
    const loader = document.getElementById('loader');
    let blobFinal = null;

    // Logika Preview dan Kompresi Gambar
    camInput.onchange = (e) => {
        const file = e.target.files[0];
        if(!file) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 800;
                const scale = MAX_WIDTH / img.width;
                
                canvas.width = MAX_WIDTH;
                canvas.height = img.height * scale;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                canvas.toBlob((blob) => {
                    blobFinal = blob;
                    imagePreview.src = URL.createObjectURL(blob);
                    imagePreview.style.display = 'block';
                    phIcon.style.display = 'none';
                    btnKirim.disabled = false;
                }, 'image/jpeg', 0.8);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    };

    // Logika Kirim dengan SweetAlert2
    btnKirim.onclick = () => {
        loader.style.display = 'flex';
        btnKirim.disabled = true;

        const fd = new FormData();
        fd.append('karya_kompres', blobFinal, 'karya.jpg');
        fd.append('catatan_siswa', document.getElementById('catatanInput').value);

        fetch('', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(res => {
            if(res.trim() === 'success') {
                // Notifikasi Sukses SweetAlert2
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Terkirim!',
                    text: 'Karya Anda telah berhasil diunggah.',
                    confirmButtonColor: '#6f42c1',
                    timer: 2500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'materi_view.php?id=<?= $materi_id ?>';
                });
            } else {
                // Notifikasi Gagal SweetAlert2
                Swal.fire({
                    icon: 'error',
                    title: 'Waduh...',
                    text: 'Gagal mengirim: ' + res,
                    confirmButtonColor: '#6f42c1'
                });
                loader.style.display = 'none';
                btnKirim.disabled = false;
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Terputus',
                text: 'Pastikan internet Anda stabil lalu coba lagi.',
                confirmButtonColor: '#6f42c1'
            });
            loader.style.display = 'none';
            btnKirim.disabled = false;
        });
    };
</script>

</body>
</html>