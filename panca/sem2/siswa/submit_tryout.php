<?php
// FILE: siswa/submit_tryout.php - FIX FINAL V5 (Memperbaiki BINDING PARAMETER String)

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$siswa_id = $_SESSION['user_id'];
$session_id = $_GET['session_id'] ?? null;
$is_auto_submit = isset($_GET['auto']);

if (!$session_id || !is_numeric($session_id)) {
    $_SESSION['error_message'] = "ID Sesi tidak valid.";
    header("Location: dashboard.php");
    exit();
}

// Mulai transaksi database
$db_mapel->begin_transaction();

try {
    // 1. Ambil Data Sesi, Master Try Out, dan Jawaban Siswa
    $query_session = "
    SELECT
        ts.tryout_id, ts.status, tm.waktu_alokasi, tm.jenis_ujian, tm.judul
    FROM panca_tryout_session ts
    JOIN panca_tryout_master tm ON ts.tryout_id = tm.id
    WHERE ts.id = ? AND ts.siswa_id = ?
";
    $stmt_session = $db_mapel->prepare($query_session);
    $stmt_session->bind_param("ii", $session_id, $siswa_id);
    $stmt_session->execute();
    $session_data = $stmt_session->get_result()->fetch_assoc();
    $stmt_session->close();

    // Validasi Sesi
    if (!$session_data) {
        throw new Exception("Sesi Try Out tidak ditemukan.");
    }

    // Jika status sudah selesai, langsung redirect ke review
    if ($session_data['status'] === 'completed') {
        header("Location: review_tryout.php?session_id=" . $session_id);
        exit();
    }

    $tryout_id = $session_data['tryout_id'];
    $jenis_tryout = $session_data['jenis_ujian'];
    $judul_tryout = $session_data['judul'];

    // 2. Ambil semua soal, kunci jawaban, dan jawaban siswa untuk perhitungan
    $query_soal = "
    SELECT
        st.id AS soal_id, st.jawaban_benar,
        tj.jawaban_siswa
    FROM panca_soal_tryout st
    LEFT JOIN panca_tryout_jawaban tj ON st.id = tj.soal_id AND tj.session_id = ?
    WHERE st.tryout_id = ?
";
    $stmt_soal = $db_mapel->prepare($query_soal);
    $stmt_soal->bind_param("ii", $session_id, $tryout_id);
    $stmt_soal->execute();
    $soal_list = $stmt_soal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_soal->close();

    $total_soal = count($soal_list);
    $jawaban_benar = 0;

    // 3. Hitung Skor
    foreach ($soal_list as $soal) {
        $siswa_answer = strtoupper($soal['jawaban_siswa'] ?? '');
        $correct_answer = strtoupper($soal['jawaban_benar'] ?? '');

        if (!empty($siswa_answer) && $siswa_answer === $correct_answer) {
            $jawaban_benar++;
        }
    }

    // Perhitungan skor (asumsi: 100 poin maksimal)
    $skor_diperoleh = ($total_soal > 0) ? round(($jawaban_benar / $total_soal) * 100) : 0;
    $persentase = ($total_soal > 0) ? ($jawaban_benar / $total_soal) * 100 : 0;
    // Penting: Jika kolom 'persentase' di DB adalah DECIMAL(5,2), pastikan formatnya benar
    $persentase = number_format($persentase, 2, '.', ''); // Output: string (misalnya "100.00")

    // Status kelulusan (asumsi: lulus jika skor >= 70)
    $status_lulus = ($skor_diperoleh >= 70) ? 'LULUS' : 'GAGAL';
    $waktu_selesai = date("Y-m-d H:i:s");

    // 4. Update Status Sesi Try Out
   $query_update_session = "
    UPDATE panca_tryout_session
    SET
        status = 'completed',
        skor_diperoleh = ?,
        waktu_selesai = ?
    WHERE id = ? AND siswa_id = ?
";
    $stmt_update = $db_mapel->prepare($query_update_session);
    $stmt_update->bind_param("isii", $skor_diperoleh, $waktu_selesai, $session_id, $siswa_id);
    $stmt_update->execute();
    $stmt_update->close();

    // 5. Simpan ke Riwayat (Tabel riwayat_tryout)
    // FIX SQL dan Binding Parameter
   $query_insert_riwayat = "
    INSERT INTO panca_riwayat_tryout
    (id_user, tryout_id, skor, total_soal, persentase, status_lulus, tanggal_dikerjakan)
    VALUES (?, ?, ?, ?, ?, ?, ?)
";
    $stmt_riwayat = $db_mapel->prepare($query_insert_riwayat);

    // FIX BINDING: type definition string diubah menjadi "iiiisss" (4x int, 3x string)
    // Variabel: siswa_id (i), tryout_id (i), skor_diperoleh (i), total_soal (i), persentase (s), status_lulus (s), waktu_selesai (s)
    $stmt_riwayat->bind_param("iiiisss",
        $siswa_id,
        $tryout_id,
        $skor_diperoleh,
        $total_soal,
        $persentase, // Sebagai String
        $status_lulus,
        $waktu_selesai
    );
    $stmt_riwayat->execute();
    $stmt_riwayat->close();

    // Ambil ID yang baru saja dimasukkan ke tabel riwayat_tryout
$riwayat_id = $db_mapel->insert_id;

// Commit transaksi
$db_mapel->commit();

// Redirect ke halaman review hasil menggunakan ID Riwayat
$_SESSION['success_message'] = "Try Out '" . htmlspecialchars($judul_tryout) . "' berhasil diselesaikan.";
header("Location: review_tryout.php?session_id=" . $riwayat_id); 
exit();

} catch (Exception $e) {
    // Rollback jika ada error
    $db_mapel->rollback();

    // Untuk debugging, gunakan:
    // echo "<h1>ERROR SUBMIT:</h1><p>Pesan Error: " . $e->getMessage() . "</p>"; exit();

    $error_msg = "Gagal menyelesaikan Try Out. Terjadi kesalahan sistem. (" . $e->getMessage() . ")";

    $_SESSION['error_message'] = $error_msg;
    header("Location: dashboard.php");
    exit();
}
?>