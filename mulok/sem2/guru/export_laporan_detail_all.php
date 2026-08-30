<?php
// FILE: guru/export_laporan_detail_all.php - V1 (UNTUK MENGUNDUH SEMUA DATA DETAIL SISWA)

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan hanya role 'guru' yang bisa mengakses
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

// Set header untuk memberitahu browser bahwa ini adalah file CSV yang akan didownload
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=laporan_detail_siswa_komprehensif_' . date('Ymd') . '.csv');

// Gunakan output buffer untuk menyimpan data CSV
$output = fopen('php://output', 'w');

// Gunakan delimiter titik koma (;) yang lebih umum di software spreadsheet Indonesia/Eropa
$delimiter = ';';

// Tulis Baris Header CSV
fputcsv($output, [
    'Laporan Detail Komprehensif Siswa',
    'Tanggal Ekspor: ' . date('Y-m-d H:i:s')
], $delimiter);
fputcsv($output, [''], $delimiter); // Baris kosong

// ===================================================================
// Dapatkan Kelas yang Diajar
// ===================================================================
$kelas_diajar_str = $_SESSION['kelas'] ?? '';
$kelas_in_clause = "NULL";

if (!empty($kelas_diajar_str)) {
    $kelas_array = explode(',', $kelas_diajar_str);
    $kelas_terfilter = array_map(function($k) use ($db_mapel) {
        return $db_mapel->real_escape_string($k);
    }, $kelas_array);

    $kelas_in_clause = "'" . implode("','", $kelas_terfilter) . "'";
}

// ===================================================================
// Dapatkan Data Siswa yang Relevan
// ===================================================================

$query_siswa = "
    SELECT id, nama_lengkap, kelas
    FROM users
    WHERE role = 'siswa' AND kelas IN ({$kelas_in_clause})
    ORDER BY kelas ASC, nama_lengkap ASC
";
$result_siswa = $db_mapel->query($query_siswa);

if ($result_siswa && $result_siswa->num_rows > 0) {
    while ($siswa = $result_siswa->fetch_assoc()) {
        $user_id = $siswa['id'];

        // Tulis Header Siswa
        fputcsv($output, ['=== SISWA BARU ==='], $delimiter);
        fputcsv($output, ['Nama Siswa', 'Kelas', 'ID Pengguna'], $delimiter);
        fputcsv($output, [
            $siswa['nama_lengkap'],
            $siswa['kelas'],
            $user_id
        ], $delimiter);
        fputcsv($output, [''], $delimiter); // Baris kosong

        // ----------------------------------------------------
        // A. Riwayat Kuis per Bab Materi
        // ----------------------------------------------------
        fputcsv($output, ['RIWAYAT KUIS MATERI'], $delimiter);
        fputcsv($output, ['Jenis Aktivitas', 'Judul Materi/Kuis', 'Persentase Nilai (%)', 'Tanggal Selesai'], $delimiter);

        $query_kuis = "
            SELECT
                m.judul,
                rk.persentase,
                rk.tanggal_dikerjakan
            FROM riwayat_kuis rk
            JOIN materi m ON rk.id_materi = m.id
            WHERE rk.id_user = ?
            ORDER BY rk.tanggal_dikerjakan DESC
        ";
        $stmt_kuis = $db_mapel->prepare($query_kuis);
        $stmt_kuis->bind_param("i", $user_id);
        $stmt_kuis->execute();
        $result_kuis = $stmt_kuis->get_result();

        if ($result_kuis->num_rows > 0) {
            while ($row = $result_kuis->fetch_assoc()) {
                fputcsv($output, [
                    'Kuis Materi',
                    $row['judul'],
                    number_format($row['persentase'], 0),
                    $row['tanggal_dikerjakan']
                ], $delimiter);
            }
        } else {
            fputcsv($output, ['Kuis Materi', 'Tidak ada data kuis materi.'], $delimiter);
        }
        $stmt_kuis->close();
        fputcsv($output, [''], $delimiter); // Baris kosong


        // ----------------------------------------------------
        // B. Riwayat Tryout / Ujian
        // ----------------------------------------------------
        fputcsv($output, ['RIWAYAT TRYOUT / UJIAN'], $delimiter);
        fputcsv($output, ['Jenis Aktivitas', 'Judul Tryout/Ujian', 'Persentase Nilai (%)', 'Tanggal Selesai'], $delimiter);

        $query_tryout = "
            SELECT
                tm.judul AS nama_tryout,
                rt.persentase,
                rt.tanggal_dikerjakan
            FROM riwayat_tryout rt
            JOIN tryout_master tm ON rt.tryout_id = tm.id
            WHERE rt.id_user = ?
            ORDER BY rt.tanggal_dikerjakan DESC
        ";
        $stmt_tryout = $db_mapel->prepare($query_tryout);
        $stmt_tryout->bind_param("i", $user_id);
        $stmt_tryout->execute();
        $result_tryout = $stmt_tryout->get_result();

        if ($result_tryout->num_rows > 0) {
            while ($row = $result_tryout->fetch_assoc()) {
                fputcsv($output, [
                    'Tryout/Ujian',
                    $row['nama_tryout'],
                    number_format($row['persentase'], 0),
                    $row['tanggal_dikerjakan']
                ], $delimiter);
            }
        } else {
            fputcsv($output, ['Tryout/Ujian', 'Tidak ada data tryout/ujian.'], $delimiter);
        }
        $stmt_tryout->close();
        fputcsv($output, [''], $delimiter); // Baris kosong
        fputcsv($output, ['***'], $delimiter); // Pembatas antar siswa
        fputcsv($output, [''], $delimiter); // Baris kosong

    }
} else {
    fputcsv($output, ['Tidak ada siswa yang ditemukan di kelas yang Anda ampu.'], $delimiter);
}

// Tutup koneksi dan output
$db_mapel->close();
fclose($output);
exit();
?>