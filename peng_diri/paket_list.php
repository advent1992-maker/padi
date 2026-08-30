<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// 1. PROTEKSI: Hanya Guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../index.php");
    exit;
}

$kat = $_GET['kat'] ?? 'osn'; // Default ke OSN jika parameter tidak ada
$my_id = $_SESSION['user_id'];
// LOGIKA TOGGLE TAMPILKAN/SEMBUNYIKAN
if (isset($_GET['toggle_id'])) {
    $tid = (int)$_GET['toggle_id'];
    $status_sekarang = (int)$_GET['status'];
    $status_baru = ($status_sekarang == 1) ? 0 : 1;

    $stmt = $conn->prepare("UPDATE paket_peng_diri SET tampilkan = ? WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("iii", $status_baru, $tid, $my_id);
    $stmt->execute();
    header("Location: paket_list.php?kat=$kat");
    exit();
}
// 2. KONEKSI KE DB PENG_DIRI
$conn_pusat = $conn;

// 3. AMBIL DAFTAR PAKET BERDASARKAN KATEGORI
$q_paket = "SELECT p.*, 
            (SELECT COUNT(*) FROM $kat WHERE paket_id = p.id) as jml_soal 
            FROM paket_peng_diri p 
            WHERE p.kategori = '$kat' AND p.id_guru = $my_id 
            ORDER BY p.id DESC";
$res_paket = mysqli_query($conn_pusat, $q_paket);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Paket <?= strtoupper($kat) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead { background-color: #f8f9fa; }
        .modal-content { border: none; border-radius: 15px; }
        /* Style Tambahan untuk Tombol Modul */
        .btn-purple { background: #6610f2; color: white; transition: 0.3s; }
        .btn-purple:hover { background: #520dc2; color: white; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark text-uppercase">Paket <?= $kat ?></h2>
            <p class="text-muted">Daftar bundel soal bimbingan Anda</p>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <a href="modul_list.php?kat=<?= $kat ?>" class="btn btn-purple rounded-pill px-4 shadow-sm">
                <i class="fas fa-book-open me-2"></i>Kelola Modul Bacaan
            </a>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus me-2"></i>Buat Paket Baru
            </button>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <strong>Berhasil!</strong> Data paket telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card card-custom p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="border-0">Nama Paket</th>
                        <th class="border-0 text-center">Mapel</th> 
                        <th class="border-0 text-center">Jumlah Soal</th>
                        <th class="border-0">Tanggal Dibuat</th>
                        <th class="border-0 text-center">Status</th>
                        <th class="border-0 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($res_paket) > 0): ?>
                        <?php while($p = mysqli_fetch_assoc($res_paket)): ?>
                        <tr>
                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($p['nama_paket']) ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill"><?= $p['mapel'] ?? '-' ?></span>
                            </td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info px-3"><?= $p['jml_soal'] ?> Soal</span></td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($p['tanggal_buat'])) ?></td>
                            <td class="text-center">
    <?php if($p['tampilkan'] == 1): ?>
        <a href="?kat=<?= $kat ?>&toggle_id=<?= $p['id'] ?>&status=1" 
           class="btn btn-sm btn-light border text-success fw-bold rounded-pill px-3 shadow-sm"
           title="Klik untuk sembunyikan">
            <i class="fas fa-eye me-1"></i> Tampil
        </a>
    <?php else: ?>
        <a href="?kat=<?= $kat ?>&toggle_id=<?= $p['id'] ?>&status=0" 
           class="btn btn-sm btn-secondary fw-bold rounded-pill px-3 shadow-sm"
           title="Klik untuk tampilkan">
            <i class="fas fa-eye-slash me-1"></i> Sembunyi
        </a>
    <?php endif; ?>
</td>
                            <td class="text-center">
                                <a href="input_<?= $kat ?>.php?paket_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary rounded-circle" title="Kelola Soal">
                                    <i class="fas fa-tasks"></i>
                                </a>
                                
                                <button class="btn btn-sm btn-warning text-white rounded-circle btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEdit" 
                                        data-id="<?= $p['id'] ?>" 
                                        data-nama="<?= htmlspecialchars($p['nama_paket']) ?>"
                                        data-mapel="<?= $p['mapel'] ?>"
                                        data-durasi="<?= $p['durasi_menit'] ?>"
                                        title="Edit Paket">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button class="btn btn-sm btn-danger rounded-circle" onclick="confirmDelete(<?= $p['id'] ?>, '<?= $kat ?>')" title="Hapus Paket">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <a href="preview_paket.php?id=<?= $p['id'] ?>&kat=<?= $kat ?>" class="btn btn-sm btn-info text-white rounded-circle" title="Preview & Cetak">
    <i class="fas fa-eye"></i>
</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted small">Belum ada paket dibuat untuk kategori ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="paket_proses.php" method="POST" class="modal-content">
            <div class="modal-header border-0 ps-4 pt-4">
                <h5 class="fw-bold">Buat Paket Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="kategori" value="<?= $kat ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Paket</label>
                    <input type="text" name="nama_paket" class="form-control" placeholder="Contoh: Paket OSN 1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Mata Pelajaran</label>
                    <select name="mapel" class="form-select" required>
                        <option value="IPA">IPA</option>
                        <option value="Matematika">Matematika</option>
                        <option value="IPS">IPS</option>
                    </select>
                </div>
                <div class="mb-3">
    <label class="form-label small fw-bold">Durasi Pengerjaan (menit)</label>
    <input type="number" name="durasi_menit" class="form-control" value="30" min="5" max="180" required>
</div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="submit" name="simpan_paket" class="btn btn-primary w-100 rounded-pill">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="paket_proses.php" method="POST" class="modal-content">
            <div class="modal-header border-0 ps-4 pt-4">
                <h5 class="fw-bold">Edit Paket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="kategori" value="<?= $kat ?>">
                <input type="hidden" name="id_paket" id="edit_id">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Paket Baru</label>
                    <input type="text" name="nama_paket" id="edit_nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Mata Pelajaran</label>
                    <select name="mapel" id="edit_mapel" class="form-select" required>
                        <option value="IPA">IPA</option>
                        <option value="Matematika">Matematika</option>
                        <option value="IPS">IPS</option>
                    </select>
                </div>
                <div class="mb-3">
    <label class="form-label small fw-bold">Durasi Pengerjaan (menit)</label>
    <input type="number" name="durasi_menit" id="edit_durasi" class="form-control" min="5" max="180" required>
</div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="submit" name="update_paket" class="btn btn-warning text-white w-100 rounded-pill">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.getAttribute('data-id');
        document.getElementById('edit_nama').value = this.getAttribute('data-nama');
        document.getElementById('edit_mapel').value = this.getAttribute('data-mapel');
        document.getElementById('edit_durasi').value = this.getAttribute('data-durasi');
    });
});

function confirmDelete(id, kat) {
    if(confirm('Apakah Anda yakin ingin menghapus paket ini? Seluruh soal di dalamnya juga akan terhapus.')) {
        window.location.href = 'paket_proses.php?action=delete&id=' + id + '&kat=' + kat;
    }
}
</script>

</body>
</html>