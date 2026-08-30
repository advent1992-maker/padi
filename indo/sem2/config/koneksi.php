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

// 4. Ambil nama folder dinamis (contoh: "bahasaindonesia", "mathfiction", dll)
$nama_folder = basename(dirname(__DIR__, 2));

// 5. Ambil semester aktif (tetap disimpan untuk menentukan tabel)
$semester = isset($_SESSION['semester_aktif']) ? $_SESSION['semester_aktif'] : '2';

// 6. Pastikan $prefix sudah ada
$prefix_db = isset($prefix) ? $prefix : '';

// 7. Database SELALU memakai SM2 (Semester 2 / Utama)
$db_name = $prefix_db . "db_" . $nama_folder . "_sm2";

// 8. Hubungkan ke Database Mapel
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

// --- PENGATURAN BASE URL ---

// Mendeteksi protokol (http atau https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

// Mendeteksi host (localhost)
$host_url = $_SERVER['HTTP_HOST'];

// Mendeteksi path folder sampai ke sem2 secara otomatis
$current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url_path = str_replace('/guru', '/', $current_path);
$base_url_path = rtrim($base_url_path, '/') . '/';

// Variabel inilah yang dipanggil di kuis_form.php
$base_url = $protocol . $host_url . $base_url_path;

// --- DYNAMIC TABLE HELPER (SAMA DENGAN IPAS & MATEMATIKA) ---
// Fungsi ini memastikan dashboard secara otomatis membaca tabel _arsip jika semester = 1

if (!function_exists('tbl')) {
    function tbl($table_name) {
        global $semester;
        // Jika sedang memuat semester 1 (Arsip), tambahkan _arsip pada nama tabel
        if (trim($semester) == "1") {
            return $table_name . "_arsip";
        }
        // Jika semester 2 (Aktif), kembalikan tabel normal
        return $table_name;
    }
}

if (!function_exists('tbl_portal')) {
    function tbl_portal($table_name) {
        global $semester;
        if (trim($semester) == "1") {
            return $table_name . "_arsip";
        }
        return $table_name;
    }
}
?>