<?php
// FILE: guru/kuis_action.php - VERSI PANCASILA

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$action = $_GET['action'] ?? null;
$id_materi = $_GET['id_materi'] ?? null;
$soal_id = $_GET['soal_id'] ?? null;

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'] ?? 0;

// =======================================================================
// 1. Verifikasi Pemilik Materi (Menggunakan panca_materi)
// =======================================================================
$materi_owner_id = null;
if ($id_materi && is_numeric($id_materi)) {
    // Sesuaikan nama tabel ke panca_materi
    $stmt_owner = $db_mapel->prepare("SELECT id_guru FROM panca_materi WHERE id = ?");
    $stmt_owner->bind_param("i", $id_materi);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();
    $materi_data = $result_owner->fetch_assoc();
    $stmt_owner->close();

    if ($materi_data) {
        $materi_owner_id = $materi_data['id_guru'];
    }
}

// Proteksi: Admin atau Pemilik Materi saja
$is_owner_or_admin = ($role === 'admin' || $user_id == $materi_owner_id);

if (!$is_owner_or_admin) {
    $_SESSION['pesan_gagal'] = "Akses ditolak. Anda tidak memiliki izin mengelola soal di materi ini.";
    header("Location: kuis_form.php?id_materi=" . $id_materi);
    exit();
}

// =======================================================================
// 2. Logika Hapus Soal (Menggunakan panca_soal)
// =======================================================================
if ($action === 'hapus' && $id_materi && $soal_id) {

    // Pastikan menggunakan panca_soal dan kolom id_materi yang konsisten
    $query = "DELETE FROM panca_soal WHERE id = ? AND materi_id = ?";

    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("ii", $soal_id, $id_materi);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['pesan_sukses'] = "Soal berhasil dihapus dari sistem.";
        } else {
            $_SESSION['pesan_gagal'] = "Soal tidak ditemukan atau sudah dihapus.";
        }
    } else {
        $_SESSION['pesan_gagal'] = "Kesalahan sistem: " . $stmt->error;
    }
    $stmt->close();

} else {
    $_SESSION['pesan_gagal'] = "Parameter aksi tidak lengkap.";
}

// Kembali ke form kuis materi terkait
header("Location: kuis_form.php?id_materi=" . $id_materi);
exit();
?>