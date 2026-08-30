<?php
// 1. Ambil path absolut agar tidak peduli dari mana file ini dipanggil
$root_path = dirname(__DIR__, 3);
$file_pusat = $root_path . '/config/koneksi.php';

if (file_exists($file_pusat)) {
    require_once $file_pusat;
} else {
    die("Fatal Error: File config pusat tidak ditemukan di $file_pusat");
}

// 2. Memulai session jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Gunakan $conn dari portal pusat jika variabel $db_pusat tidak ada
// (Menyesuaikan dengan info Anda sebelumnya bahwa variabelnya $conn)
if (!isset($conn) && isset($db_pusat)) {
    $conn = $db_pusat;
}

// 4. Ambil nama folder ("mathfiction")
$nama_folder = basename(dirname(__DIR__, 2));
$nama_mapel = "IPAS";

// 5. Ambil semester aktif (tetap disimpan untuk menentukan tabel)
$semester = $_SESSION['semester_aktif'] ?? '2';

/*
==================================================
INFORMASI SEMESTER
1 = Database Arsip (TP 2025/2026)
2 = Database Aktif (TP 2026/2027)
==================================================
*/

$is_arsip = ($semester == "1");

$info_semester = [
    'kode'   => $semester,
    'arsip'  => $is_arsip,
    'nama'   => $is_arsip ? 'Semester 2' : 'Semester 1',
    'tahun'  => $is_arsip ? '2025/2026' : '2026/2027'
];
// ==================================================
// PENENTU TABEL AKTIF / ARSIP
// ==================================================

function tbl($nama)
{
    global $is_arsip;
    return $is_arsip ? $nama . "_arsip" : $nama;
}

// 6. Pastikan $prefix sudah ada
$prefix_db = isset($prefix) ? $prefix : '';

// 7. Database SELALU memakai SM2
$db_name = $prefix_db . "db_" . $nama_folder . "_sm2";

// 8. Hubungkan ke Database Mapel
// Perhatikan: variabel $host, $user, $pass harus ada di config/koneksi.php pusat
$user_koneksi = isset($user_target) && !empty($user_target) ? $user_target : $user;
$db_mapel = @mysqli_connect($host, $user_koneksi, $pass, $db_name);

// 9. Cek Koneksi dengan pesan yang lebih informatif
if (!$db_mapel) {
    echo "<div style='color:red; background:#fff; padding:10px; border:1px solid red;'>";
    echo "<strong>Error Koneksi Database Mapel:</strong><br>";
    echo "Database: $db_name <br>";
    echo "Pesan: " . mysqli_connect_error();
    echo "</div>";
    exit();
}
// --- TAMBAHKAN MULAI DARI SINI ---

// Mendeteksi protokol (http atau https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

// Mendeteksi host (localhost)
$host_url = $_SERVER['HTTP_HOST'];

// Mendeteksi path folder sampai ke sem2 secara otomatis
// Ini akan menghasilkan: /portal_belajar/mathfiction/sem2/
$current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url_path = str_replace('/guru', '/', $current_path);
$base_url_path = rtrim($base_url_path, '/') . '/';

// Variabel inilah yang dipanggil di kuis_form.php
$base_url = $protocol . $host_url . $base_url_path;

// --- SAMPAI DI SINI ---
?>