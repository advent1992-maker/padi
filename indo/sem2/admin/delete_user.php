<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role

// Pengecekan Otorisasi: Hanya 'admin'
if ($current_user_role !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id > 0) {
    // Pastikan admin tidak menghapus dirinya sendiri
    if ($user_id == $current_user_id) {
        $_SESSION['admin_message'] = "<div class='alert alert-danger'>Anda tidak dapat menghapus akun Anda sendiri!</div>";
    } else {
        // Hapus menggunakan Prepared Statement
        $query = "DELETE FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("i", $user_id); 
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "<div class='alert alert-success'>Akun dengan ID $user_id berhasil dihapus.</div>";
            } else {
                $_SESSION['admin_message'] = "<div class='alert alert-danger'>Gagal menghapus akun.</div>";
            }
            $stmt->close();
        }
    }
}

// Arahkan kembali ke halaman kelola pengguna
header("Location: manage_users.php");
exit();
?>