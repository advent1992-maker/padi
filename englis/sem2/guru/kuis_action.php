<?php
// File: mathfiction/guru/kuis_action.php

// Panggil file konfigurasi
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Hanya Role 'guru' atau 'admin' yang bisa mengakses
if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// Ambil parameter dari URL
$action = $_GET['action'] ?? null;
$id_materi = $_GET['id_materi'] ?? null;
$soal_id = $_GET['soal_id'] ?? null;

$role = $_SESSION['role'];
$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];

// =======================================================================
// 1. Dapatkan ID Pemilik Materi untuk Verifikasi
// =======================================================================
$materi_owner_id = null;
if ($id_materi && is_numeric($id_materi)) {
    $stmt_owner = $db_mapel->prepare("SELECT id_guru FROM materi WHERE id = ?");
    $stmt_owner->bind_param("i", $id_materi);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();
    $materi_data = $result_owner->fetch_assoc();
    $stmt_owner->close();

    if ($materi_data) {
        $materi_owner_id = $materi_data['id_guru'];
    }
}

// KRITIS: Hanya ADMIN atau PEMILIK MATERI yang boleh melakukan aksi HAPUS
$is_owner_or_admin = ($role === 'admin' || $user_id == $materi_owner_id);

if (!$is_owner_or_admin) {
    // Jika bukan pemilik materi DAN bukan admin, tolak akses dan kembali
    $_SESSION['pesan_gagal'] = "Akses ditolak. Anda hanya dapat menghapus soal pada materi yang Anda buat.";
    header("Location: kuis_form.php?id_materi=" . $id_materi);
    exit();
}
// =======================================================================


// Pastikan kita memiliki ID Materi dan ID Soal untuk proses HAPUS
if ($action === 'hapus' && $id_materi && is_numeric($id_materi) && $soal_id && is_numeric($soal_id)) {

    // Siapkan query untuk menghapus soal
    $query = "DELETE FROM soal WHERE id = ? AND materi_id = ?";

    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("ii", $soal_id, $id_materi); // Bind ID Soal dan ID Materi

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['pesan_sukses'] = "Soal ID: {$soal_id} berhasil dihapus dari materi.";
        } else {
            $_SESSION['pesan_gagal'] = "Gagal menghapus soal. Soal tidak ditemukan atau bukan milik materi ini.";
        }
    } else {
        $_SESSION['pesan_gagal'] = "Gagal menghapus soal. Error SQL: " . $stmt->error;
    }

    $stmt->close();

} else {
    $_SESSION['pesan_gagal'] = "Aksi tidak valid atau parameter kurang lengkap.";
}

// Selalu kembalikan pengguna ke halaman form kuis setelah aksi selesai
header("Location: kuis_form.php?id_materi=" . $id_materi);
exit();

?>