<?php
// Matikan error reporting jika sudah live, tapi aktifkan untuk debug (hapus baris bawah jika sudah fix)
error_reporting(E_ALL); ini_set('display_errors', 1);

// Sesuaikan path ini dengan posisi folder 'padi' Anda
require_once '../../../config/koneksi.php'; 
require_once '../../../config/ai_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_materi = (int)$_POST['id_materi'];
    $keyword = $_POST['keyword'];
    $jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 5;
    $kesulitan = $_POST['kesulitan'];

    // Gunakan variabel koneksi yang sesuai (cek apakah $db_mapel atau $conn)
    $q = $db_mapel->query("SELECT judul FROM materi WHERE id = $id_materi");
    if (!$q) { die("Materi tidak ditemukan!"); }
    
    $m = $q->fetch_assoc();
    $topik = $m['judul'];

    $prompt = "Kamu adalah pakar pembuat soal pendidikan. Buatkan $jumlah soal pilihan ganda tentang '$topik' untuk siswa kelas 5 SD.
               Tingkat kesulitan soal harus: $kesulitan. ";

    if(!empty($keyword)) {
        $prompt .= "Fokus pembahasan spesifik: $keyword. ";
    }

    $prompt .= "Aturan:
                1. Gunakan bahasa Indonesia yang baku namun mudah dimengerti anak SD.
                2. Berikan hasil hanya dalam format JSON array murni tanpa penjelasan.
                3. Format JSON: [{\"pertanyaan\":\"\",\"opsi_a\":\"\",\"opsi_b\":\"\",\"opsi_c\":\"\",\"opsi_d\":\"\",\"jawaban_benar\":\"A/B/C/D\"}]";

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

            $db_mapel->query("INSERT INTO soal (materi_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar)
                              VALUES ('$id_materi', '$pertanyaan', '$a', '$b', '$c', '$d', '$kunci')");
        }

        // Output SweetAlert
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap' rel='stylesheet'>
            <style>body { font-family: 'Poppins', sans-serif; }</style>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'Berhasil!',
                    text: '$jumlah soal telah ditambahkan.',
                    icon: 'success',
                    confirmButtonText: 'Selesai'
                }).then(() => { window.location = 'kuis_list.php'; });
            </script>
        </body>
        </html>";
        exit();
    } else {
        echo "Gagal memproses soal dari AI. Hasil: " . htmlspecialchars($hasil_ai);
    }
}