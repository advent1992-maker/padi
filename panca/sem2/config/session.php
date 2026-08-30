<?php
// Tentukan waktu hidup cookie (dalam detik, misal 2 jam = 7200)
// Penting: Nilai ini harus SAMA dengan SESSION_TIMEOUT di auth_check.php,
// atau setidaknya cukup panjang (misal 1 hari). Mari kita tetapkan ke 1 hari.
date_default_timezone_set('Asia/Jakarta');
$lifetime = 86400; // 24 jam untuk waktu hidup cookie browser

if (session_status() === PHP_SESSION_NONE) {
    // 1. Set waktu hidup sampah sesi server (minimal sama dengan lifetime cookie)
    ini_set('session.gc_maxlifetime', $lifetime);

    // 2. Set parameter cookie sesi untuk memastikan ketersediaan
    session_set_cookie_params(
        $lifetime, // lifetime cookie (waktu kadaluarsa di browser)
        '/',       // path: Tersedia di SELURUH domain aplikasi Anda (PENTING!)
        '',        // domain: Biarkan kosong untuk localhost
        false,     // secure: true jika menggunakan HTTPS
        true       // httponly: cookie hanya dapat diakses melalui HTTP, bukan JavaScript (keamanan)
    );

    // 3. Mulai sesi
    session_start();
}
// JANGAN ADA SPASI, BARIS BARU, ATAU KARAKTER LAIN DI LUAR TAG PHP
?>