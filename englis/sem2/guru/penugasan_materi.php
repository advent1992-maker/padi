<?php
// ======================================================================
// PENUGASAN_MATERI.PHP (VERSI MANUAL - ANTI ERROR 500)
// ======================================================================

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Proteksi Guru
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru   = (int) $_SESSION['user_id'];
$materi_id = (int) ($_GET['id_materi'] ?? 0);
$mode      = $_GET['mode'] ?? 'pribadi';

if ($materi_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// 1. AMBIL DATA MATERI
$stmt = $db_mapel->prepare("SELECT judul, level_kategori FROM materi WHERE id=?");
$stmt->bind_param("i", $materi_id);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$judul_materi = $materi['judul'] ?? 'Materi';
$kelas_target = $materi['level_kategori'] ?? '';

// 2. AKSI DELETE
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete') {
        $pid = (int) $_GET['penugasan_id'];
        $db_mapel->query("DELETE FROM penugasan_materi WHERE id=$pid AND id_guru=$id_guru");
        $_SESSION['pesan_sukses'] = "✅ Penugasan berhasil dihapus.";
    }
    if ($_GET['action'] === 'delete_all_materi') {
        $db_mapel->query("DELETE FROM penugasan_materi WHERE id_materi=$materi_id AND id_guru=$id_guru");
        $_SESSION['pesan_sukses'] = "✅ Semua penugasan berhasil direset.";
    }
    header("Location: penugasan_materi.php?id_materi=$materi_id&mode=$mode");
    exit();
}

// 3. SIMPAN PENUGASAN (FIX MASSAL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_penugasan'])) {
    $judul_tgs    = mysqli_real_escape_string($db_mapel, $_POST['judul']);
    $deskripsi    = mysqli_real_escape_string($db_mapel, $_POST['deskripsi']);
    $tgl_mulai    = $_POST['tgl_mulai'];
    $tgl_deadline = $_POST['tgl_deadline'];
    $target_type  = $_POST['target_type'];

    $siswa_ids = [];
    if ($target_type === 'massal') {
        $res = $conn->query("SELECT id FROM users WHERE role='siswa' AND kelas='$kelas_target'");
        while ($r = $res->fetch_assoc()) { $siswa_ids[] = (int) $r['id']; }
    } else {
        $siswa_ids[] = (int) $_POST['ditugaskan_ke'];
    }

    if (!empty($siswa_ids)) {
        $berhasil = 0;
        foreach ($siswa_ids as $sid) {
            $sql = "INSERT INTO penugasan_materi (id_materi, id_guru, judul, deskripsi, tanggal_mulai, deadline, ditugaskan_ke, status) 
                    VALUES ($materi_id, $id_guru, '$judul_tgs', '$deskripsi', '$tgl_mulai', '$tgl_deadline', $sid, 'Belum Dimulai')
                    ON DUPLICATE KEY UPDATE deadline='$tgl_deadline'";
            if ($db_mapel->query($sql)) { $berhasil++; }
        }
        $_SESSION['pesan_sukses'] = "✅ Berhasil menugaskan kepada $berhasil siswa.";
    }
    header("Location: penugasan_materi.php?id_materi=$materi_id&mode=$mode");
    exit();
}

// 4. AMBIL DATA TAMPILAN (SAYA PISAH QUERY AGAR TIDAK ERROR JOIN)
$list_tugas_raw = $db_mapel->query("SELECT * FROM penugasan_materi WHERE id_materi=$materi_id AND id_guru=$id_guru ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$list_tugas = [];
foreach($list_tugas_raw as $t) {
    $sid = $t['ditugaskan_ke'];
    $u = $conn->query("SELECT nama_lengkap FROM users WHERE id=$sid")->fetch_assoc();
    $t['nama_lengkap'] = $u['nama_lengkap'] ?? 'Siswa Keluar';
    $list_tugas[] = $t;
}

$dropdown_siswa = $conn->query("SELECT id, nama_lengkap FROM users WHERE role='siswa' AND kelas='$kelas_target' ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penugasan Materi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="py-4">
<div class="container">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 p-4" style="border-radius: 20px;">
                <a href="materi_list.php" class="btn btn-outline-secondary btn-sm mb-3">Kembali</a>
                <h4 class="fw-bold text-primary">Kirim Tugas</h4>
                <p class="small text-muted"><?= htmlspecialchars($judul_materi) ?></p>

                <?php if(isset($_SESSION['pesan_sukses'])): ?>
                    <div class="alert alert-success small"><?= $_SESSION['pesan_sukses']; unset($_SESSION['pesan_sukses']); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="text" name="judul" class="form-control mb-3" placeholder="Judul Tugas" required>
                    <textarea name="deskripsi" class="form-control mb-3" placeholder="Instruksi..." required></textarea>
                    <div class="row mb-3">
                        <div class="col"><label class="small fw-bold">Mulai</label><input type="date" name="tgl_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="col"><label class="small fw-bold">Deadline</label><input type="date" name="tgl_deadline" class="form-control" required></div>
                    </div>
                    <select name="target_type" class="form-select mb-3" onchange="document.getElementById('indiv').style.display=this.value=='individu'?'block':'none'">
                        <option value="massal">Semua Siswa Kelas <?= $kelas_target ?></option>
                        <option value="individu">Pilih Individu</option>
                    </select>
                    <div id="indiv" class="mb-3" style="display:none">
                        <select name="ditugaskan_ke" class="form-select">
                            <?php foreach($dropdown_siswa as $s): ?><option value="<?= $s['id'] ?>"><?= $s['nama_lengkap'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button name="submit_penugasan" class="btn btn-primary w-100 fw-bold py-2" style="border-radius: 50px;">KIRIM TUGAS</button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0 p-4" style="border-radius: 20px;">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="fw-bold">Daftar Terkirim</h5>
                    <a href="?id_materi=<?= $materi_id ?>&action=delete_all_materi&mode=<?= $mode ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus semua?')">Reset</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <?php foreach($list_tugas as $t): ?>
                        <tr>
                            <td><strong><?= $t['nama_lengkap'] ?></strong><br><span class="badge bg-warning text-dark" style="font-size: 0.6rem;"><?= $t['status'] ?></span></td>
                            <td class="text-end"><a href="?id_materi=<?= $materi_id ?>&action=delete&penugasan_id=<?= $t['id'] ?>&mode=<?= $mode ?>" class="text-danger"><i class="fa fa-trash"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>