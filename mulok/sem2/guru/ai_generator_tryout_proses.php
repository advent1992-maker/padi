<?php
require_once '../config/koneksi.php';
require_once '../../../config/ai_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tryout_id = (int)$_POST['tryout_id'];
    $keyword = $_POST['keyword'];
    $jumlah = (int)$_POST['jumlah'];
    $kesulitan = $_POST['kesulitan'];

    // Ambil info tryout
    $q = $db_mapel->query("SELECT judul, kelas FROM tryout_master WHERE id = $tryout_id");
    $tm = $q->fetch_assoc();
    $judul_ujian = $tm['judul'];
    $kelas = $tm['kelas'];

    $prompt = "Kamu adalah spesialis pembuat soal ujian. Buatkan $jumlah soal pilihan ganda untuk ujian '$judul_ujian' kelas $kelas SD.
               Cakupan Materi: $keyword.
               Tingkat Kesulitan: $kesulitan.
               Hasil HARUS berupa JSON array murni: [{\"pertanyaan\":\"\",\"opsi_a\":\"\",\"opsi_b\":\"\",\"opsi_c\":\"\",\"opsi_d\":\"\",\"jawaban_benar\":\"A/B/C/D\"}]";

    $hasil_ai = panggil_gemini($prompt);
    $data_soal = json_decode($hasil_ai, true);

    if ($data_soal) {
        foreach ($data_soal as $s) {
            $pertanyaan = $db_mapel->real_escape_string($s['pertanyaan']);
            $a = $db_mapel->real_escape_string($s['opsi_a']);
            $b = $db_mapel->real_escape_string($s['opsi_b']);
            $c = $db_mapel->real_escape_string($s['opsi_c']);
            $d = $db_mapel->real_escape_string($s['opsi_d']);
            $kunci = strtoupper($s['jawaban_benar']);

            // SIMPAN KE TABEL soal_tryout (Kolom: tryout_id)
            $db_mapel->query("INSERT INTO soal_tryout (tryout_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar)
                              VALUES ('$tryout_id', '$pertanyaan', '$a', '$b', '$c', '$d', '$kunci')");
        }

        // Tampilan SweetAlert Sukses (Tanpa Spongebob sesuai permintaan Bapak)
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Try Out Siap!',
                    text: '$jumlah soal berhasil dirakit oleh AI.',
                    icon: 'success',
                    confirmButtonColor: '#0dcaf0'
                }).then(() => { window.location = 'manajemen_tryout.php'; });
            }, 100);
        </script>";
    } else {
        echo "Gagal memproses AI. Respon: " . htmlspecialchars($hasil_ai);
    }
}