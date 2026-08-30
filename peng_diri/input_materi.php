<?php
require_once '../config/session.php';
require_once 'config/koneksi_dev.php';

if ($_SESSION['role'] !== 'guru' && $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

if (isset($_POST['simpan'])) {
    $kategori = $_POST['kategori'];
    $jenis    = $_POST['jenis_soal'];
    $tanya    = mysqli_real_escape_string($conn_dev, $_POST['pertanyaan']);
    
    // Logika menyusun JSON untuk Opsi Jawaban
    $opsi = "";
    if ($jenis == 'pg' || $jenis == 'pgk') {
        $arr_opsi = [
            'A' => $_POST['opsi_a'],
            'B' => $_POST['opsi_b'],
            'C' => $_POST['opsi_c'],
            'D' => $_POST['opsi_d']
        ];
        $opsi = json_encode($arr_opsi);
    } elseif ($jenis == 'jodoh') {
        $arr_jodoh = [
            'kiri' => explode("\n", $_POST['jodoh_kiri']),
            'kanan' => explode("\n", $_POST['jodoh_kanan'])
        ];
        $opsi = json_encode($arr_jodoh);
    }

    $kunci = mysqli_real_escape_string($conn_dev, $_POST['kunci_jawaban']);

    $q = "INSERT INTO soal_anbk (kategori, jenis_soal, pertanyaan, opsi_json, kunci_jawaban) 
          VALUES ('$kategori', '$jenis', '$tanya', '$opsi', '$kunci')";
    
    if (mysqli_query($conn_dev, $q)) { $msg = "success"; }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Soal ANBK - PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .form-card { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .hidden-form { display: none; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card form-card p-4">
                <h4 class="fw-bold mb-4 text-primary">Buat Soal Latihan Baru</h4>
                <form action="" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Kategori</label>
                            <select name="kategori" class="form-select" required>
                                <option value="literasi">Literasi</option>
                                <option value="numerasi">Numerasi</option>
                                <option value="stem">STEM</option>
                                <option value="coding">Coding</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Jenis Soal (Format ANBK)</label>
                            <select name="jenis_soal" id="jenis_soal" class="form-select" onchange="updateForm()" required>
                                <option value="pg">Pilihan Ganda</option>
                                <option value="pgk">Pilihan Ganda Kompleks (Centang Banyak)</option>
                                <option value="jodoh">Menjodohkan</option>
                                <option value="isian">Isian Singkat</option>
                                <option value="uraian">Uraian / Esai</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Pertanyaan / Stimulus</label>
                        <textarea name="pertanyaan" class="form-control" rows="4" required></textarea>
                    </div>

                    <div id="area_opsi">
                        <div id="form_pg" class="row">
                            <div class="col-md-6 mb-2"><input type="text" name="opsi_a" class="form-control" placeholder="Opsi A"></div>
                            <div class="col-md-6 mb-2"><input type="text" name="opsi_b" class="form-control" placeholder="Opsi B"></div>
                            <div class="col-md-6 mb-2"><input type="text" name="opsi_c" class="form-control" placeholder="Opsi C"></div>
                            <div class="col-md-6 mb-2"><input type="text" name="opsi_d" class="form-control" placeholder="Opsi D"></div>
                        </div>

                        <div id="form_jodoh" class="hidden-form row">
                            <div class="col-md-6">
                                <label class="small">Pernyataan (Kiri) - Pisahkan dengan baris baru</label>
                                <textarea name="jodoh_kiri" class="form-control"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="small">Jawaban (Kanan) - Pisahkan dengan baris baru</label>
                                <textarea name="jodoh_kanan" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 mb-4">
                        <label class="fw-bold text-danger">Kunci Jawaban</label>
                        <input type="text" name="kunci_jawaban" class="form-control" placeholder="Contoh: A (untuk PG) atau Kata Kunci (untuk Isian)" required>
                        <small class="text-muted">Untuk PG Kompleks: A,B,C. Untuk Menjodohkan: 1-A,2-B.</small>
                    </div>

                    <button type="submit" name="simpan" class="btn btn-primary w-100 fw-bold py-3 rounded-pill">SIMPAN KE GUDANG SOAL</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateForm() {
    var jenis = document.getElementById("jenis_soal").value;
    document.getElementById("form_pg").style.display = (jenis == 'pg' || jenis == 'pgk') ? "flex" : "none";
    document.getElementById("form_jodoh").style.display = (jenis == 'jodoh') ? "flex" : "none";
}
</script>
</body>
</html>