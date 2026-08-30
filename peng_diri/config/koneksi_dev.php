<?php
// Sesuaikan dengan data hosting Premium Bapak nantinya
$host_dev = "localhost";
$user_dev = "u815140682_tian"; // Sesuaikan user DB
$pass_dev = "Martapura06"; // Sesuaikan pass DB
$db_dev   = "u815140682_db_peng_diri"; // Nama DB baru tadi

$conn_dev = mysqli_connect($host_dev, $user_dev, $pass_dev, $db_dev);

if (!$conn_dev) {
    die("Koneksi Pengembangan Diri Gagal: " . mysqli_connect_error());
}
?>