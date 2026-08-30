<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role
// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. Ambil ID Pengguna dari URL (GET Request)
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";

if ($user_id > 0) {
    // 2. Gunakan Prepared Statement untuk update
    $query = "UPDATE users SET is_verified = 1 WHERE id = ?";

    if ($stmt = $db_mapel->prepare($query)) {
        $stmt->bind_param("i", $user_id); // 'i' untuk integer

        if ($stmt->execute()) {
            // Update berhasil
            $message = "Akun dengan ID $user_id berhasil diverifikasi.";
        } else {
            // Error saat eksekusi
            $message = "Gagal memverifikasi akun. Error database.";
        }
        $stmt->close();
    } else {
        $message = "Error sistem: Gagal mempersiapkan statement.";
    }
} else {
    $message = "ID pengguna tidak valid.";
}

// 3. Simpan pesan ke session dan arahkan kembali ke dashboard
session_start();
$_SESSION['admin_message'] = "<div class='alert alert-success'>🎉 " . $message . "</div>";

// Arahkan kembali ke dashboard admin
header("Location: dashboard.php");
exit();
?>