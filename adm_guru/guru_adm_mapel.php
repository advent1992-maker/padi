<?php
// Matikan error reporting agar tidak muncul warning yang merusak header, 
// tapi jika masih error 500, ubah ke 1 untuk melihat pesan aslinya.
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/session.php';
// Kita ambil file koneksi, tapi kita akan buat variabel koneksi sendiri di sini
require_once '../config/koneksi.php'; 

// Data Konfigurasi (Sesuaikan dengan file koneksi.php Anda)
$host = "localhost";
$pass = "Martapura06"; 
$prefix = "u815140682_";

$folder = $_GET['folder'] ?? '';
$mapel_nama = $_GET['mapel'] ?? 'Mata Pelajaran';
$id_guru = $_SESSION['user_id'] ?? 0;

// 1. KONEKSI KE DATABASE PUSAT (Untuk cek status ADM)
$db_portal_name = $prefix . "db_portal";
$user_admin = $prefix . "admin";
$conn_pusat = mysqli_connect($host, $user_admin, $pass, $db_portal_name);

// 2. KONEKSI KE DATABASE MAPEL (Untuk ambil materi)
$db_target_name = $prefix . "db_" . $folder . "_sm2";

// Tentukan user database berdasarkan folder
$user_mapel = "";
switch ($folder) {
    case 'ipas':   $user_mapel = $prefix . "hari"; break;
    case 'mtk':    $user_mapel = $prefix . "advent"; break;
    case 'indo':   $user_mapel = $prefix . "harrieya"; break;
    case 'panca':  $user_mapel = $prefix . "adventgool"; break;
    case 'englis': $user_mapel = $prefix . "kris"; break;
    case 'pjok':   $user_mapel = $prefix . "derry"; break;
    case 'pai':    $user_mapel = $prefix . "arq"; break;
    default:       $user_mapel = $prefix . "admin"; break;
}

$conn_mapel = mysqli_connect($host, $user_mapel, $pass, $db_target_name);

if (!$conn_mapel) {
    die("Gagal konek ke DB Mapel: " . mysqli_connect_error());
}

// 3. AMBIL DAFTAR MATERI (Hanya milik guru yang sedang login)
// Kita tambahkan WHERE id_guru = '$id_guru'
$query_materi = "SELECT id, judul, konten_materi FROM materi WHERE id_guru = '$id_guru' ORDER BY id ASC";
$res_materi = mysqli_query($conn_mapel, $query_materi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ADM - <?= htmlspecialchars($mapel_nama) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; padding-top: 30px; }
        .table-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>ADM: <?= htmlspecialchars($mapel_nama) ?></h4>
        <a href="../dashboard_guru.php" class="btn btn-sm btn-secondary">Kembali</a>
    </div>

    <div class="table-container">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Materi</th>
                    <th>RPP</th>
                    <th>LKPD</th>
                </tr>
            </thead>
            <tbody>
    <?php if ($res_materi): ?>
        <?php while($m = mysqli_fetch_assoc($res_materi)): ?>
        <tr>
            <td>
                <div class="fw-bold text-primary"><?= htmlspecialchars($m['judul']) ?></div>
                <small class="text-muted" style="font-size: 0.7rem;">ID MATERI: <?= $m['id'] ?></small>
            </td>
            
            <td class="text-center">
                <?php 
                $id_m = $m['id'];
                // Pengecekan status: harus cocok ID Guru, Folder, dan ID Materi PADI
                $cek_rpp = mysqli_query($conn_pusat, "SELECT id FROM guru_administrasi 
                                        WHERE id_materi_padi='$id_m' 
                                        AND jenis_adm='rpp' 
                                        AND id_guru='$id_guru' 
                                        AND mapel_folder='$folder'");
                
                if($cek_rpp && mysqli_num_rows($cek_rpp) > 0): 
                    $data_rpp = mysqli_fetch_assoc($cek_rpp);
                ?>
                    <div class="btn-group">
                        <a href="cetak_rpp.php?id=<?= $data_rpp['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white px-3">
                            <i class="fas fa-eye"></i> Preview
                        </a>
                        <a href="download_rpp.php?id=<?= $data_rpp['id'] ?>" class="btn btn-sm btn-dark">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <button class="btn btn-sm btn-primary px-3 rounded-pill" onclick="bukaModal('rpp', '<?= addslashes($m['judul']) ?>', '<?= $m['id'] ?>')">
                        <i class="fas fa-plus"></i> Buat RPP
                    </button>
                <?php endif; ?>
            </td>

            <td class="text-center">
                <?php 
                $cek_lkpd = mysqli_query($conn_pusat, "SELECT id FROM guru_administrasi 
                                         WHERE id_materi_padi='$id_m' 
                                         AND jenis_adm='lkpd' 
                                         AND id_guru='$id_guru' 
                                         AND mapel_folder='$folder'");
                
                if($cek_lkpd && mysqli_num_rows($cek_lkpd) > 0): 
                    $data_lkpd = mysqli_fetch_assoc($cek_lkpd);
                ?>
                    <div class="btn-group">
                        <a href="cetak_lkpd.php?id=<?= $data_lkpd['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info px-3">
                            <i class="fas fa-eye"></i> Preview
                        </a>
                        <button class="btn btn-sm btn-outline-dark"><i class="fas fa-file-pdf"></i></button>
                    </div>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-success px-3 rounded-pill" onclick="bukaModal('lkpd', '<?= addslashes($m['judul']) ?>', '<?= $m['id'] ?>')">
                        <i class="fas fa-plus"></i> Buat LKPD
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="3" class="text-center">Belum ada materi buatan sendiri.</td></tr>
    <?php endif; ?>
</tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalAdm" tabindex="-1">
    <div class="modal-dialog">
        <form action="proses_adm.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 id="judulModal">Input</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="folder" value="<?= $folder ?>">
                <input type="hidden" name="id_materi_padi" id="idMateri">
                <input type="hidden" name="jenis" id="inputJenis">
                <input type="text" id="inputJudul" class="form-control mb-3" readonly>
                <textarea name="konten" class="form-control" rows="5"></textarea>
            </div>
            <div class="modal-footer">
                <button type="submit" name="simpan_adm" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function bukaModal(jenis, judul, id) {
    document.getElementById('inputJenis').value = jenis;
    document.getElementById('idMateri').value = id;
    document.getElementById('inputJudul').value = judul;
    document.getElementById('judulModal').innerText = "Buat " + jenis.toUpperCase();
    new bootstrap.Modal(document.getElementById('modalAdm')).show();
}
</script>
</body>
</html>