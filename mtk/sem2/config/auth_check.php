<?php
// Pastikan zona waktu sudah diatur di file config Anda
require_once __DIR__ . '/session.php';

// Tentukan waktu maksimum tanpa aktivitas (30 menit = 1800 detik)
define('SESSION_TIMEOUT', 1800);

// =================================================================
// Debug Waktu AKTIF (JANGAN HAPUS SEBELUM MASALAH SELESAI)
// =================================================================
$elapsed_time = 0;
$last_activity_time_readable = "N/A";

if (isset($_SESSION['LAST_ACTIVITY'])) {
    $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
    // Gunakan date_default_timezone_set('Asia/Jakarta') di file config Anda agar ini benar
    $last_activity_time_readable = date('Y-m-d H:i:s', $_SESSION['LAST_ACTIVITY']);
} else {
    // Jika LAST_ACTIVITY tidak ada, inisialisasi agar logic tidak gagal
    $_SESSION['LAST_ACTIVITY'] = time();
    $elapsed_time = 0;
}

// Tampilkan elapsed time di komentar HTML (WAJIB dilihat di Inspect Element)
echo "";
// =================================================================


// 1. Cek Timeout Aktivitas
// Logika ini akan terpicu jika elapsed_time > 1800 detik
if (isset($_SESSION['LAST_ACTIVITY']) && ($elapsed_time > SESSION_TIMEOUT)) {
    // Session telah kadaluarsa karena tidak ada aktivitas
    session_unset();
    session_destroy();

    // Simpan pesan error untuk ditampilkan di halaman login
    $_SESSION['login_message'] = "<div class='alert alert-warning'>⏳ Sesi Anda telah berakhir karena tidak ada aktivitas selama " . (SESSION_TIMEOUT / 60) . " menit. Silakan login kembali.</div>";

    header("Location: ../../../login.php");
    exit();
}

// 2. Cek Kehadiran Sesi & Redirect Jika Tidak Ada
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    session_unset();
    session_destroy();

    // Pastikan pesan yang dibawa adalah pesan yang tepat untuk login paksa
    if (!isset($_SESSION['login_message'])) {
        $_SESSION['login_message'] = "<div class='alert alert-danger'>❌ Anda harus login untuk mengakses halaman ini.</div>";
    }

    header("Location: ../login.php");
    exit();
}

// 3. Update Aktivitas (Mencegah Logout Otomatis)
$_SESSION['LAST_ACTIVITY'] = time(); // Update timestamp aktivitas terakhir
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
?>