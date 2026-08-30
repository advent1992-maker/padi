<?php
// 1. Paksa PHP menampilkan error jika terjadi sesuatu (Sangat penting untuk debug)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/koneksi.php';
require_once 'config/session.php';

// Pastikan user adalah guru
if (($_SESSION['role'] ?? '') !== 'guru') {
    die("Akses ditolak. Anda bukan guru.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Gunakan $db_mapel atau $conn (Sesuaikan dengan file koneksi Anda)
    // Jika di koneksi.php namanya $db_mapel, maka baris ini memastikan $conn bisa jalan
    if (!isset($conn) && isset($db_mapel)) {
        $conn = $db_mapel;
    }

    $id_user = $_SESSION['user_id'];
    $kode_app = 'PADI_PORTAL';

    // Ambil data saran dan tanggal
    $saran = isset($_POST['saran']) ? mysqli_real_escape_string($conn, $_POST['saran']) : '';
    $tgl = date("Y-m-d H:i:s");

    // 3. Ambil nilai q1-q10
    $vals = [];
    $total = 0;
    for($i=1; $i<=10; $i++) {
        $v = isset($_POST["q$i"]) ? (int)$_POST["q$i"] : 0;
        $vals[] = $v;
        $total += $v;
    }
    
    // Hitung skor (Total maksimal 50, dikali 2 jadi 100)
    $skor = ($total / 50) * 100;

    // 4. Jalankan Query
    $sql = "INSERT INTO hasil_uji_guru 
            (id_user, kode_aplikasi, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, skor, saran, tanggal_uji) 
            VALUES (
                '$id_user', 
                '$kode_app', 
                '{$vals[0]}', '{$vals[1]}', '{$vals[2]}', '{$vals[3]}', '{$vals[4]}', 
                '{$vals[5]}', '{$vals[6]}', '{$vals[7]}', '{$vals[8]}', '{$vals[9]}', 
                '$skor', 
                '$saran', 
                '$tgl'
            )";

    if (mysqli_query($conn, $sql)) {
        // Jika sukses, lempar ke dashboard
        header("Location: dashboard_guru.php?status=sukses_nilai");
        exit();
    } else {
        // Jika gagal di database, munculkan pesan ini
        die("Gagal simpan ke database: " . mysqli_error($conn));
    }
} else {
    header("Location: dashboard_guru.php");
    exit();
}