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
if (!isset($conn) && isset($db_pusat)) {
    $conn = $db_pusat;
}

// 4. Ambil nama folder dan tetapkan nama mata pelajaran
$nama_folder = basename(dirname(__DIR__, 2));
$nama_mapel  = "Pancasila";

// 5. Ambil semester aktif dari session (default: 2)
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

if (!function_exists('tbl')) {
    function tbl($nama)
    {
        global $is_arsip;
        return $is_arsip ? $nama . "_arsip" : $nama;
    }
}

if (!function_exists('tbl_portal')) {
    function tbl_portal($nama_tabel) {
        return $nama_tabel;
    }
}

// 6. Pastikan $prefix sudah ada
$prefix_db = $prefix ?? '';

// 7. Database SELALU memakai SM2 (Tetap mempertahankan pengarahan db_mulok_sm2 jika folder "panca")
if ($nama_folder == "panca") {
    $db_name = $prefix_db . "db_mulok_sm2";
} else {
    $db_name = $prefix_db . "db_" . $nama_folder . "_sm2";
}

// 8. Hubungkan ke Database Mapel
// KHUSUS PANCA: Paksa gunakan user kristian
$user_koneksi = $prefix_db . "kristian"; 

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

// 10. Generasi Base URL Otomatis
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host_url = $_SERVER['HTTP_HOST'];

$current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url_path = str_replace('/guru', '/', $current_path);
$base_url_path = rtrim($base_url_path, '/') . '/';

$base_url = $protocol . $host_url . $base_url_path;
?>