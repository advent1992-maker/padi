<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// 1. PROTEKSI: Hanya Guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../index.php");
    exit;
}

// 2. KONEKSI KE DB PENG_DIRI
$conn_pusat = $conn;

$kat = $_GET['kat'] ?? 'osn'; 
$my_id = $_SESSION['user_id'];

// 3. AMBIL DAFTAR MODUL
$q = "SELECT * FROM materi_peng_diri WHERE kategori = '$kat' AND id_guru = $my_id ORDER BY id DESC";
$res = mysqli_query($conn_pusat, $q);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Modul <?= strtoupper($kat) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-modul { border: none; border-radius: 15px; border-top: 5px solid #6610f2; transition: 0.3s; }
        .card-modul:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .btn-purple { background: #6610f2; color: white; border-radius: 50px; }
        .btn-purple:hover { background: #520dc2; color: white; }
        .badge-kat { background-color: rgba(102, 16, 242, 0.1); color: #6610f2; border: 1px solid #6610f2; }
        
        /* Style Filter */
        .filter-btn { border-radius: 50px; padding: 8px 25px; font-weight: 600; cursor: pointer; transition: 0.3s; border: 1px solid #ddd; background: white; color: #666; }
        .filter-btn.active { background: #6610f2; color: white; border-color: #6610f2; }
        .filter-btn:hover:not(.active) { background: #f0f0f0; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark text-uppercase"><i class="fas fa-book-reader me-2"></i> Modul <?= $kat ?></h2>
            <p class="text-muted">Kelola materi bacaan bimbingan Anda</p>
        </div>
        <div>
            <a href="paket_list.php?kat=<?= $kat ?>" class="btn btn-outline-secondary rounded-pill px-4 me-2">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <button class="btn btn-purple px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahModul">
                <i class="fas fa-plus me-2"></i>Tambah Modul Baru
            </button>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4 overflow-auto pb-2">
        <div class="filter-btn active" onclick="filterMapel('all', this)">Semua</div>
        <div class="filter-btn" onclick="filterMapel('Matematika', this)">Matematika</div>
        <div class="filter-btn" onclick="filterMapel('IPA', this)">IPA</div>
        <div class="filter-btn" onclick="filterMapel('IPS', this)">IPS</div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> Operasi berhasil dilakukan.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row" id="containerModul">
        <?php if(mysqli_num_rows($res) > 0): ?>
            <?php while($m = mysqli_fetch_assoc($res)): ?>
                <div class="col-md-4 mb-4 item-modul" data-mapel="<?= $m['mapel'] ?>">
                    <div class="card h-100 card-modul shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-center" style="min-height: 150px;">
                            <div class="d-flex justify-content-between align-items-start mb-auto">
                                <span class="badge badge-kat px-3 py-2"><?= $m['mapel'] ?></span>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($m['tanggal_buat'])) ?></small>
                            </div>
                            
                            <div class="text-left py-3">
                                <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($m['judul_materi']) ?></h4>
                            </div>
                            
                            <div class="mt-auto pt-3 border-top d-flex justify-content-between">
                               <a href="modul_view.php?id=<?= $m['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white rounded-pill px-3" title="Buka Materi di IFP">
            <i class="fas fa-eye me-1"></i> Buka
        </a>
                                <a href="modul_editor.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-edit me-1"></i> Edit Konten
                                </a>
                                <button class="btn btn-sm btn-link text-danger text-decoration-none p-0" onclick="confirmDelete(<?= $m['id'] ?>, '<?= $kat ?>')">
                                    <i class="fas fa-trash me-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="card border-0 shadow-sm p-5 rounded-4">
                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="80" class="mx-auto mb-3 opacity-25">
                    <h5 class="text-muted">Belum ada modul yang dibuat.</h5>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalTambahModul" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="modul_proses.php" method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 ps-4 pt-4">
                <h5 class="fw-bold">Buat Modul Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="kategori" value="<?= $kat ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Mata Pelajaran</label>
                    <select name="mapel" class="form-select border-2" required>
                        <option value="IPA">IPA</option>
                        <option value="Matematika">Matematika</option>
                        <option value="IPS">IPS</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Judul Modul</label>
                    <input type="text" name="judul_materi" class="form-control border-2" placeholder="Contoh: Bab 1 - Struktur Atom" required>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="submit" name="simpan_modul" class="btn btn-purple w-100 py-2 fw-bold">Buat Modul Sekarang</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fungsi Filter Mapel
    function filterMapel(mapel, btn) {
        // Ganti class active tombol
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Filter kartu
        const items = document.querySelectorAll('.item-modul');
        items.forEach(item => {
            if (mapel === 'all' || item.getAttribute('data-mapel') === mapel) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function confirmDelete(id, kat) {
        if(confirm('Hapus modul ini? Seluruh isi teks akan hilang.')) {
            window.location.href = 'modul_proses.php?action=delete&id=' + id + '&kat=' + kat;
        }
    }
</script>

</body>
</html>