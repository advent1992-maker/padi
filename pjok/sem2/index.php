<?php
/**
 * mathfiction - Titik Masuk Utama Aplikasi
 * * Fungsi:
 * 1. Memulai sesi.
 * 2. Memeriksa apakah pengguna sudah login.
 * 3. Mengarahkan pengguna ke dashboard yang sesuai (admin/guru/siswa) jika sudah login.
 * 4. Mengarahkan pengguna ke halaman login jika belum login.
 * * Catatan: File ini berasumsi bahwa:
 * - File '/config/session.php' sudah ada dan memulai/melanjutkan sesi.
 * - Variabel $current_user_role dan $current_user_id diatur di session.php/auth_check.php
 * jika sesi valid.
 */

// Sertakan file sesi dan konfigurasi (Asumsi file ini ada di root folder)
require_once 'config/session.php';

// Periksa apakah ada sesi pengguna yang valid (user_id dan role harus ada)
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

    $role = $_SESSION['role'];

    // Menentukan halaman dashboard berdasarkan peran (role) pengguna
    switch ($role) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'guru':
            header("Location: guru/dashboard.php");
            exit();
        case 'siswa':
            header("Location: siswa/dashboard.php");
            exit();
        default:
            // Jika peran tidak dikenali, arahkan ke login
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit();
    }
} else {
    // Jika tidak ada sesi yang valid, arahkan ke halaman login
    header("Location: login.php");
    exit();
}

// Tidak ada output HTML yang diperlukan karena semua akan di-redirect.
?>