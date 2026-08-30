<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$type = $_GET['type'] ?? '';
$id_konten = $_GET['id'] ?? 0;
$guru_id = $_GET['user_id'] ?? 0;

if (empty($type) || $id_konten == 0 || $guru_id == 0) {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">Parameter penghapusan tidak lengkap.</div>';
    header("Location: progres_detail_guru.php?user_id=" . $guru_id);
    exit();
}

$table = '';
$redirect_to = "progres_detail_guru.php?user_id=" . $guru_id;

if ($type === 'materi') {
    $table = 'materi';
} elseif ($type === 'kuis') {
    $table = 'tryout_master';
    // PENTING: Jika menghapus kuis (tryout_master), Anda juga HARUS menghapus
    // data terkait di tabel tryout_jawaban, soal, dan riwayat_tryout

    // Contoh untuk menghapus soal terkait:
    // $stmt_soal = $conn->prepare("DELETE FROM soal WHERE tryout_id = ?");
    // $stmt_soal->bind_param("i", $id_konten);
    // $stmt_soal->execute();
    // $stmt_soal->close();

} else {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">Jenis konten tidak valid.</div>';
    header("Location: " . $redirect_to);
    exit();
}

try {
    // Jalankan Query Hapus Konten Utama
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id_konten);

    if ($stmt->execute()) {
        $_SESSION['progres_guru_message'] = '<div class="alert alert-success">Konten berhasil dihapus.</div>';
    } else {
        throw new Exception("Gagal menghapus konten: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}

header("Location: " . $redirect_to);
exit();
?>