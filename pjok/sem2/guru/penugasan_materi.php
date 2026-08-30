<?php
// ======================================================================
// PENUGASAN_MATERI.PHP (FINAL)
// ======================================================================

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// VALIDASI
if (!$conn || !$db_mapel) {
    die("Koneksi database gagal.");
}
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

// ======================================================================
// AMBIL DATA MATERI
// ======================================================================
$stmt = $db_mapel->prepare("SELECT judul, level_kategori FROM materi WHERE id=?");
$stmt->bind_param("i", $materi_id);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$judul_materi = $materi['judul'] ?? 'Materi';
$kelas_target = $materi['level_kategori'] ?? '';

// ======================================================================
// AKSI DELETE (DIPERBARUI: RESET NILAI JUGA)
// ======================================================================
if (isset($_GET['action'])) {

    // 1. Hapus Penugasan Individu
    if ($_GET['action'] === 'delete') {
        $pid = (int) $_GET['penugasan_id'];

        // Ambil ID siswa terlebih dahulu untuk meriset nilainya
        $res_sid = $db_mapel->query("SELECT ditugaskan_ke FROM penugasan_materi WHERE id=$pid");
        $data_sid = $res_sid->fetch_assoc();

        if ($data_sid) {
            $sid = $data_sid['ditugaskan_ke'];
            // RESET NILAI: Hapus riwayat kuis siswa ini khusus untuk materi ini
            $db_mapel->query("DELETE FROM riwayat_kuis WHERE id_materi=$materi_id AND id_user=$sid");
            // Hapus Penugasan
            $db_mapel->query("DELETE FROM penugasan_materi WHERE id=$pid AND id_guru=$id_guru");
            $_SESSION['pesan_sukses'] = "✅ Penugasan dan nilai siswa berhasil direset.";
        }
    }

    // 2. Hapus Semua Penugasan pada Materi ini
    if ($_GET['action'] === 'delete_all_materi') {
        // RESET NILAI MASSAL: Hapus semua riwayat kuis untuk materi ini
        $db_mapel->query("DELETE FROM riwayat_kuis WHERE id_materi=$materi_id");
        // Hapus Semua Penugasan
        $db_mapel->query("DELETE FROM penugasan_materi WHERE id_materi=$materi_id AND id_guru=$id_guru");
        $_SESSION['pesan_sukses'] = "✅ Semua penugasan dan nilai riwayat materi ini berhasil direset.";
    }

    header("Location: penugasan_materi.php?id_materi=$materi_id&mode=$mode");
    exit();
}

// ======================================================================
// SIMPAN PENUGASAN
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_penugasan'])) {

    $judul        = trim($_POST['judul']);
    $deskripsi    = trim($_POST['deskripsi']);
    $tgl_mulai    = $_POST['tgl_mulai'];
    $tgl_deadline = $_POST['tgl_deadline'];
    $target_type  = $_POST['target_type'];

    $siswa_ids = [];

    if ($target_type === 'massal') {
        $res = $conn->query("SELECT id FROM users WHERE role='siswa' AND kelas='$kelas_target'");
        while ($r = $res->fetch_assoc()) {
            $siswa_ids[] = (int) $r['id'];
        }
    } else {
        $siswa_ids[] = (int) $_POST['ditugaskan_ke'];
    }

    if (empty($siswa_ids)) {
        $_SESSION['pesan_error'] = "❌ Tidak ada siswa ditemukan.";
        header("Location: penugasan_materi.php?id_materi=$materi_id&mode=$mode");
        exit();
    }

    $stmt = $db_mapel->prepare("
        INSERT INTO penugasan_materi
        (id_materi, id_guru, judul, deskripsi, tanggal_mulai, deadline, ditugaskan_ke, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Belum Dimulai')
        ON DUPLICATE KEY UPDATE status=status
    ");

    $berhasil = 0;
    foreach ($siswa_ids as $sid) {
        $stmt->bind_param(
            "iiisssi",
            $materi_id,
            $id_guru,
            $judul,
            $deskripsi,
            $tgl_mulai,
            $tgl_deadline,
            $sid
        );
        $stmt->execute();
        if ($stmt->affected_rows > 0) $berhasil++;
    }
    $stmt->close();

    $_SESSION['pesan_sukses'] = "✅ Berhasil menugaskan kepada $berhasil siswa.";
    header("Location: penugasan_materi.php?id_materi=$materi_id&mode=$mode");
    exit();
}

// ======================================================================
// DATA TAMPILAN
// ======================================================================
$list_tugas = $db_mapel->query("
    SELECT t.id, t.judul, t.deskripsi, t.status, u.nama_lengkap
    FROM penugasan_materi t
    JOIN db_portal_pusat.users u ON t.ditugaskan_ke = u.id
    WHERE t.id_materi=$materi_id AND t.id_guru=$id_guru
    ORDER BY u.nama_lengkap ASC
")->fetch_all(MYSQLI_ASSOC);

$dropdown_siswa = $conn->query("
    SELECT id, nama_lengkap
    FROM users
    WHERE role='siswa' AND kelas='$kelas_target'
    ORDER BY nama_lengkap ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Penugasan Materi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light py-4">
<div class="container">
<div class="row g-4">

<!-- FORM KIRI -->
<div class="col-md-5">
<div class="card shadow-sm border-0 p-4">

<a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-3">
<i class="fa fa-arrow-left"></i> Kembali ke Dashboard
</a>

<h4 class="fw-bold text-primary mb-3">Penugasan Massal</h4>

<?php if(isset($_SESSION['pesan_sukses'])): ?>
<div class="alert alert-success"><?= $_SESSION['pesan_sukses']; unset($_SESSION['pesan_sukses']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['pesan_error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['pesan_error']; unset($_SESSION['pesan_error']); ?></div>
<?php endif; ?>

<form method="POST">
<input type="text" name="judul" class="form-control mb-3" placeholder="Judul Tugas" required>
<textarea name="deskripsi" class="form-control mb-3" placeholder="Instruksi Tugas" required></textarea>

<div class="row mb-3">
<div class="col">
<label class="small fw-bold">Tgl Mulai</label>
<input type="date" name="tgl_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required>
</div>
<div class="col">
<label class="small fw-bold">Deadline</label>
<input type="date" name="tgl_deadline" class="form-control" required>
</div>
</div>

<select name="target_type" class="form-select mb-3"
onchange="document.getElementById('indiv').style.display=this.value=='individu'?'block':'none'">
<option value="massal">Semua Siswa Kelas <?= $kelas_target ?> (<?= count($dropdown_siswa) ?>)</option>
<option value="individu">Siswa Tertentu</option>
</select>

<div id="indiv" style="display:none">
<select name="ditugaskan_ke" class="form-select mb-3">
<?php foreach($dropdown_siswa as $s): ?>
<option value="<?= $s['id'] ?>"><?= $s['nama_lengkap'] ?></option>
<?php endforeach; ?>
</select>
</div>

<button name="submit_penugasan" class="btn btn-primary w-100 fw-bold">
KIRIM TUGAS
</button>
</form>
</div>
</div>

<!-- PANEL KANAN -->
<div class="col-md-7">
<div class="card shadow-sm border-0 p-4">

<div class="d-flex justify-content-between mb-3">
<h5 class="fw-bold">Status Penugasan</h5>
<a href="?id_materi=<?= $materi_id ?>&action=delete_all_materi&mode=<?= $mode ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Reset semua penugasan?')">
Reset
</a>
</div>

<?php if(!empty($list_tugas)): ?>
<div class="mb-3 p-3 bg-light rounded">
<strong>Judul Tugas:</strong> <?= htmlspecialchars($list_tugas[0]['judul']) ?><br>
<strong>Instruksi:</strong> <?= htmlspecialchars($list_tugas[0]['deskripsi']) ?>
</div>
<?php endif; ?>

<table class="table table-hover">
<?php foreach($list_tugas as $t): ?>
<tr>
<td>
<strong><?= $t['nama_lengkap'] ?></strong><br>
<span class="badge bg-warning"><?= $t['status'] ?></span>
</td>
<td width="40">
<a class="text-danger"
href="?id_materi=<?= $materi_id ?>&action=delete&penugasan_id=<?= $t['id'] ?>&mode=<?= $mode ?>">
<i class="fa fa-trash"></i>
</a>
</td>
</tr>
<?php endforeach; ?>
</table>

</div>
</div>

</div>
</div>
</body>
</html>