<?php
header('Content-Type: application/json');
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/ai_helper.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paket_id = (int)($_POST['paket_id'] ?? 0); // Ambil paket_id
    $prompt_user = $_POST['prompt'] ?? '';
    $level = $_POST['level'] ?? 'HOTS';

    if ($paket_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Paket tidak valid.']);
        exit;
    }

    $model_osn = GEMINI_MODEL; 
    $prompt = "Buatlah 1 soal pilihan ganda OSN yang sangat menantang tentang: $prompt_user. 
    Tingkat kesulitan: $level. 
    WAJIB mengembalikan respon dalam format JSON saja (Array satu objek) dengan kunci: 
    topik, pertanyaan, a, b, c, d, kunci, pembahasan. 
    Contoh format: [{\"topik\":\"...\", \"pertanyaan\":\"...\", \"a\":\"...\", \"b\":\"...\", \"c\":\"...\", \"d\":\"...\", \"kunci\":\"A\", \"pembahasan\":\"...\"}]";

    $response = panggil_gemini($prompt, $model_osn);

    // Buka koneksi baru agar tidak "Gone Away"
    $conn_pusat = $conn; 

    $response_clean = str_replace(['```json', '```'], '', $response);
    $data_array = json_decode(trim($response_clean), true);

    if (isset($data_array[0])) {
        $s = $data_array[0];
        
        // Escape data sebelum masuk DB
        $judul = mysqli_real_escape_string($conn_pusat, $s['topik'] ?? $prompt_user);
        $tanya = mysqli_real_escape_string($conn_pusat, $s['pertanyaan']);
        $a = mysqli_real_escape_string($conn_pusat, $s['a']);
        $b = mysqli_real_escape_string($conn_pusat, $s['b']);
        $c = mysqli_real_escape_string($conn_pusat, $s['c']);
        $d = mysqli_real_escape_string($conn_pusat, $s['d']);
        $kunci = mysqli_real_escape_string($conn_pusat, $s['kunci']);
        $pembahasan = mysqli_real_escape_string($conn_pusat, $s['pembahasan']);

        // LANGSUNG INSERT KE DATABASE
        $sql = "INSERT INTO osn (paket_id, judul, pertanyaan, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, pembahasan) 
                VALUES ('$paket_id', '$judul', '$tanya', 'pg', '$a', '$b', '$c', '$d', '$kunci', '$pembahasan')";
        
        if (mysqli_query($conn_pusat, $sql)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal simpan ke database: ' . mysqli_error($conn_pusat)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Format AI tidak valid.']);
    }
}