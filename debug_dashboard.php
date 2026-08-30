<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    die("Silakan login terlebih dahulu untuk tes debug.");
}

$user_id = $_SESSION['user_id'];
$semester = $_SESSION['semester_aktif'] ?? '2';
$tbl_suffix = ($semester == "1") ? "_arsip" : "";

echo "<h2>--- HASIL DEBUGGING DASHBOARD ---</h2>";
echo "<b>User ID:</b> $user_id | <b>Semester Aktif:</b> $semester | <b>Suffix Tabel:</b> '" . ($tbl_suffix ?: 'Tanpa Suffix') . "'<br><br>";

$mapels = [
    ['folder' => 'ipas', 'nama' => 'IPAS'],
    ['folder' => 'mtk', 'nama' => 'Matematika'],
    ['folder' => 'indo', 'nama' => 'B. Indonesia'],
    ['folder' => 'panca', 'nama' => 'Pancasila'],
    ['folder' => 'englis', 'nama' => 'B. Inggris'],
    ['folder' => 'pjok', 'nama' => 'PJOK'],
    ['folder' => 'pai', 'nama' => 'PAI'],
    ['folder' => 'mulok', 'nama' => 'B. Komering'],
    ['folder' => 'seni', 'nama' => 'Seni Rupa']
];

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse; text-align:center;'>";
echo "<tr style='background:#f0f0f0;'>
        <th>Mapel</th>
        <th>Status Koneksi User Mapel</th>
        <th>Baris Kuis</th>
        <th>Rata Kuis</th>
        <th>Baris TO</th>
        <th>Rata TO</th>
        <th>Rata Akhir Mapel</th>
      </tr>";

foreach ($mapels as $m) {
    $prefix_tab = ($m['folder'] == 'panca') ? 'panca_' : '';
    
    // Buka koneksi dinamis berdasar mapping di koneksi.php
    $conn_m = get_mapel_connection($m['folder']);

    if ($conn_m && $conn_m !== $conn) {
        $status_db_html = "<span style='color:green;'>BERHASIL (User Khusus)</span>";

        // Hitung Kuis
        $q_k = mysqli_query($conn_m, "SELECT persentase FROM {$prefix_tab}riwayat_kuis{$tbl_suffix} WHERE id_user = $user_id");
        if (!$q_k) {
            $q_k = mysqli_query($conn_m, "SELECT persentase FROM {$prefix_tab}riwayat_kuis WHERE id_user = $user_id");
        }
        $cnt_k = $q_k ? mysqli_num_rows($q_k) : 0;
        $s_k = 0;
        if ($cnt_k > 0) {
            while ($rk = mysqli_fetch_assoc($q_k)) { $s_k += $rk['persentase']; }
            $avg_k = round($s_k / $cnt_k);
        } else {
            $avg_k = "-";
        }

        // Hitung Tryout
        $q_t = mysqli_query($conn_m, "SELECT persentase FROM {$prefix_tab}riwayat_tryout{$tbl_suffix} WHERE id_user = $user_id");
        if (!$q_t) {
            $q_t = mysqli_query($conn_m, "SELECT persentase FROM {$prefix_tab}riwayat_tryout WHERE id_user = $user_id");
        }
        $cnt_t = $q_t ? mysqli_num_rows($q_t) : 0;
        $s_t = 0;
        if ($cnt_t > 0) {
            while ($rt = mysqli_fetch_assoc($q_t)) { $s_t += $rt['persentase']; }
            $avg_t = round($s_t / $cnt_t);
        } else {
            $avg_t = "-";
        }

        $vals = array_filter([is_numeric($avg_k) ? $avg_k : null, is_numeric($avg_t) ? $avg_t : null], function($v) { return !is_null($v); });
        $rata_final = count($vals) > 0 ? round(array_sum($vals) / count($vals)) : 0;

        mysqli_close($conn_m);
    } else {
        $status_db_html = "<span style='color:red;'>GAGAL KONEKSI MAPEL</span>";
        $cnt_k = "-"; $avg_k = "-";
        $cnt_t = "-"; $avg_t = "-";
        $rata_final = 0;
    }

    echo "<tr>
            <td><b>{$m['nama']}</b></td>
            <td>$status_db_html</td>
            <td>$cnt_k</td>
            <td>$avg_k</td>
            <td>$cnt_t</td>
            <td>$avg_t</td>
            <td><b>$rata_final</b></td>
          </tr>";
}
echo "</table>";
?>