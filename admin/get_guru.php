<?php
require_once '../config/koneksi.php';

// Pastikan variabel koneksi menggunakan $conn sesuai database portal
$kelas = $_GET['kelas'] ?? '';

if (!empty($kelas)) {
    // Cari guru yang memiliki string kelas tersebut (misal: '1,5,6')
    // Menggunakan LIKE agar jika guru mengajar banyak kelas tetap ketemu
    $query = "SELECT id, nama_lengkap FROM users
              WHERE role = 'guru'
              AND kelas LIKE '%$kelas%'
              ORDER BY nama_lengkap ASC";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        echo '<option value="0">-- Pilih Guru Kelas '.$kelas.' --</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="'.$row['id'].'">'.$row['nama_lengkap'].'</option>';
        }
    } else {
        echo '<option value="0">TIDAK ADA GURU DI KELAS INI</option>';
    }
}
?>