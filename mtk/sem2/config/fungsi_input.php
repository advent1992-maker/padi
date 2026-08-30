<?php
// FILE: config/fungsi_input.php
// -------------------------------
// Fungsi sanitasi input untuk mencegah SQL injection & XSS
// Bisa digunakan untuk semua input dari GET / POST

function clean_input($koneksi, $data) {
    if (!isset($data)) {
        return '';
    }

    // Trim spasi depan belakang
    $data = trim($data);

    // Hilangkan tag HTML berbahaya
    $data = strip_tags($data);

    // Escape tanda kutip ke aman untuk SQL
    if ($koneksi) {
        $data = mysqli_real_escape_string($koneksi, $data);
    }

    return $data;
}
