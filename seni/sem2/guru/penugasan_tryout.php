<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$id_tryout = (int)($_GET['id_tryout'] ?? 0);

$tabel_master     = 'tryout_master';
$tabel_penugasan  = 'riwayat_tryout';
$foreign_key      = 'tryout_id';
$tipe_konten      = 'Try Out';
$kembali_ke       = 'manajemen_tryout.php';

if ($id_tryout === 0) {
    $_SESSION['error_message'] = "ID Try Out tidak valid.";
    header("Location: {$kembali_ke}");
    exit();
}

/* ---------------------- Ambil Detail Try Out ---------------------- */
$query_konten = "
    SELECT tm.judul, tm.kelas, tm.id_guru, u.nama AS nama_guru
    FROM {$tabel_master} tm
    JOIN users u ON tm.id_guru = u.id
    WHERE tm.id = ?
";

$stmt = $db_mapel->prepare($query_konten);
$stmt->bind_param("i", $id_tryout);
$stmt->execute();
$konten = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$konten) {
    $_SESSION['error_message'] = "Try Out tidak ditemukan.";
    header("Location: {$kembali_ke}");
    exit();
}

$kelas_target           = $konten['kelas'];
$konten_milik_guru_id   = $konten['id_guru'];
$status_kepemilikan     = ($konten_milik_guru_id != $user_id) ? "Adopsi (Guru ID: {$konten_milik_guru_id})" : "Pribadi";
$judul_konten_display   = "{$konten['judul']} ({$tipe_konten} | Kelas {$konten['kelas']}) - {$status_kepemilikan}";

/* ---------------------- Ambil Daftar Siswa ---------------------- */
$siswa_list = [];
$query_siswa = "SELECT id, nama, email FROM users WHERE role='siswa' AND kelas=? ORDER BY nama ASC";

$stmt = $db_mapel->prepare($query_siswa);
$stmt->bind_param("i", $kelas_target);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $siswa_list[] = $row;
}
$stmt->close();

$total_siswa = count($siswa_list);

/* ---------------------- Simpan Penugasan ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_penugasan'])) {

    $judul_penugasan = $_POST['judul_penugasan'];
    $deskripsi       = $_POST['deskripsi'];
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $batas_waktu     = $_POST['batas_waktu'];
    $target_tipe     = $_POST['target_tipe'];
    $target_siswa_id = (int)($_POST['pilih_siswa_id'] ?? 0);

    if (empty($judul_penugasan) || empty($tanggal_mulai) || empty($batas_waktu)) {
        $_SESSION['error_message'] = "Judul, tanggal mulai, dan batas waktu wajib diisi.";
        header("Location: penugasan_tryout.php?id_tryout={$id_tryout}");
        exit();
    }

    $tanggal_mulai_db = date('Y-m-d 00:00:00', strtotime($tanggal_mulai));
    $batas_waktu_db   = date('Y-m-d 23:59:59', strtotime($batas_waktu));

    if ($target_tipe == 'massal') {

        $target_ids = array_column($siswa_list, 'id');

    } elseif ($target_tipe == 'individu') {

        if ($target_siswa_id === 0) {
            $_SESSION['error_message'] = "Pilih siswa pada mode penugasan individu.";
            header("Location: penugasan_tryout.php?id_tryout={$id_tryout}");
            exit();
        }

        $target_ids = [$target_siswa_id];

    } else {
        $_SESSION['error_message'] = "Tipe target tidak valid.";
        header("Location: penugasan_tryout.php?id_tryout={$id_tryout}");
        exit();
    }

    $insert_query = "
        INSERT INTO {$tabel_penugasan}
        ({$foreign_key}, id_siswa, id_guru, tanggal_mulai, batas_waktu, status, judul_penugasan, deskripsi)
        VALUES (?, ?, ?, ?, ?, 'DIBUAT', ?, ?)
    ";

    $stmt_insert = $db_mapel->prepare($insert_query);
    $berhasil = 0;

    foreach ($target_ids as $siswa_id) {

        $check_q = "
            SELECT COUNT(*)
            FROM {$tabel_penugasan}
            WHERE {$foreign_key}=? AND id_siswa=? AND status='DIBUAT'
            LIMIT 1
        ";

        $stmt_check = $db_mapel->prepare($check_q);
        $stmt_check->bind_param("ii", $id_tryout, $siswa_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) continue;

        $stmt_insert->bind_param(
            "iiissss",
            $id_tryout,
            $siswa_id,
            $user_id,
            $tanggal_mulai_db,
            $batas_waktu_db,
            $judul_penugasan,
            $deskripsi
        );

        if ($stmt_insert->execute()) $berhasil++;
    }

    $stmt_insert->close();

    if ($berhasil > 0) {
        $_SESSION['success_message'] = "Berhasil menugaskan Try Out kepada {$berhasil} siswa.";
    } else {
        $_SESSION['error_message'] = "Tidak ada penugasan baru. Semua siswa telah memiliki penugasan aktif.";
    }

    header("Location: penugasan_tryout.php?id_tryout={$id_tryout}");
    exit();
}

/* ---------------------- Daftar Penugasan Aktif ---------------------- */
$penugasan_aktif = [];
$query_aktif = "
    SELECT tp.id, tp.judul_penugasan, tp.batas_waktu, tp.id_siswa, u.nama AS nama_siswa
    FROM {$tabel_penugasan} tp
    JOIN users u ON tp.id_siswa = u.id
    WHERE tp.{$foreign_key}=? AND tp.id_guru=? AND tp.status='DIBUAT'
    ORDER BY tp.batas_waktu ASC, u.nama ASC
";

$stmt = $db_mapel->prepare($query_aktif);
$stmt->bind_param("ii", $id_tryout, $user_id);
$stmt->execute();

$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['batas_waktu_format'] = date('d M Y', strtotime($row['batas_waktu']));
    $penugasan_aktif[] = $row;
}

$stmt->close();
$db_mapel->close();
?>