<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/ai_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paket_id = (int)$_POST['paket_id'];
    $topik = $_POST['topik']; // Belum perlu escape karena koneksi belum dipakai
    
    set_time_limit(120); 

    $model = GEMINI_MODEL; 
    $prompt = "Buatlah 5 soal olimpiade Pilihan Ganda dengan pilihan jebakan untuk jenjang SD dengan level sedang dan sulit tentang $topik. Berikan respon HANYA JSON array: [{\"pertanyaan\":\"...\",\"a\":\"...\",\"b\":\"...\",\"c\":\"...\",\"d\":\"...\",\"kunci\":\"A/B/C/D\",\"pembahasan\":\"...\"}]";

   // Panggil AI dulu (proses lama)
    $response = panggil_gemini($prompt, $model);
    
    // Pastikan koneksi segar kembali menggunakan variabel yang sudah ada di koneksi.php
    if (!isset($conn) || mysqli_ping($conn) == false) {
        require '../config/koneksi.php'; 
        $conn_pusat = $conn;
    } else {
        $conn_pusat = $conn;
    }

    $topik_db = mysqli_real_escape_string($conn_pusat, $topik);

    $response = str_replace(['```json', '```'], '', $response);
    $data_soal = json_decode(trim($response), true);

    echo "<html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>";

    if (is_array($data_soal)) {
        $berhasil = 0;
        foreach ($data_soal as $s) {
            $tanya = mysqli_real_escape_string($conn_pusat, $s['pertanyaan']);
            $a = mysqli_real_escape_string($conn_pusat, $s['a']);
            $b = mysqli_real_escape_string($conn_pusat, $s['b']);
            $c = mysqli_real_escape_string($conn_pusat, $s['c']);
            $d = mysqli_real_escape_string($conn_pusat, $s['d']);
            $kunci = mysqli_real_escape_string($conn_pusat, $s['kunci']);
            $pembahasan = mysqli_real_escape_string($conn_pusat, $s['pembahasan']);

            $sql = "INSERT INTO osn (paket_id, judul, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, pembahasan) 
                    VALUES ('$paket_id', '$topik_db', '$tanya', 'pg', '$a', '$b', '$c', '$d', '$kunci', '$pembahasan')";
            
            if (mysqli_query($conn_pusat, $sql)) $berhasil++;
        }
    
        echo "<script>
            Swal.fire('Berhasil!', '$berhasil soal telah masuk database.', 'success').then(() => {
                window.location.href = 'input_osn.php?paket_id=$paket_id';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire('Gagal', 'Server sibuk. Coba ulangi lagi.', 'error').then(() => {
                window.history.back();
            });
        </script>";
    }
    echo "</body></html>";
}