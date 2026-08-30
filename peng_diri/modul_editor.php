<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

$conn_pusat = $conn;

$id = $_GET['id'] ?? 0;
$q = mysqli_query($conn_pusat, "SELECT * FROM materi_peng_diri WHERE id = '$id'");
$data = mysqli_fetch_assoc($q);

if (!$data) { die("Data tidak ditemukan."); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Editor Modul HTML</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .full-height { height: calc(100vh - 120px); }
        .code-input { 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 14px; 
            resize: none; 
            background: #282c34; 
            color: #abb2bf; 
            border-radius: 8px;
            padding: 15px;
        }
        #preview-screen { 
            background: white; 
            border-radius: 8px; 
            padding: 25px; 
            overflow-y: auto; 
            border: 1px solid #dee2e6;
        }
        .header-bar { background: white; border-bottom: 2px solid #ddd; padding: 10px 20px; }
    </style>
</head>
<body>

<div class="header-bar d-flex justify-content-between align-items-center mb-3 shadow-sm">
    <div class="d-flex align-items-center">
        <a href="modul_list.php?kat=<?= $data['kategori'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h5 class="mb-0 fw-bold">Editor Modul: <span class="text-primary"><?= htmlspecialchars($data['judul_materi']) ?></span></h5>
    </div>
    <form action="modul_proses.php" method="POST" id="formModul">
        <input type="hidden" name="id_modul" value="<?= $id ?>">
        <input type="hidden" name="kategori" value="<?= $data['kategori'] ?>">
        <input type="hidden" name="judul_materi" value="<?= htmlspecialchars($data['judul_materi']) ?>">
        
        <textarea name="isi_materi" id="data_asli" style="display:none;"></textarea>
        
        <button type="button" onclick="kirimData()" class="btn btn-success px-4 rounded-pill fw-bold">
            <i class="fas fa-save me-2"></i>Simpan Modul
        </button>
    </form>
</div>

<div class="container-fluid">
    <div class="row full-height px-3">
        <div class="col-md-6 d-flex flex-column">
            <label class="fw-bold mb-2"><i class="fas fa-code me-1"></i> Input Kode HTML</label>
            <textarea id="input_html" class="form-control flex-grow-1 code-input" placeholder="Ketik atau tempel kode HTML di sini..."><?= $data['isi_materi'] ?></textarea>
        </div>

        <div class="col-md-6 d-flex flex-column">
            <label class="fw-bold mb-2"><i class="fas fa-eye me-1"></i> Preview Tampilan</label>
            <div id="preview-screen" class="flex-grow-1">
                <?= $data['isi_materi'] ?>
            </div>
        </div>
    </div>
</div>

<script>
    const inputHtml = document.getElementById('input_html');
    const previewScreen = document.getElementById('preview-screen');
    const dataAsli = document.getElementById('data_asli');

    // Fungsi Preview Otomatis saat Mengetik
    inputHtml.addEventListener('input', function() {
        previewScreen.innerHTML = this.value;
    });

    // Fungsi Kirim Data
    function kirimData() {
        dataAsli.value = inputHtml.value; // Salin hasil ketikan ke form tersembunyi
        
        // Tambahkan input penanda agar diproses oleh modul_proses.php
        const hiddenInput = document.createElement("input");
        hiddenInput.setAttribute("type", "hidden");
        hiddenInput.setAttribute("name", "update_isi_modul");
        hiddenInput.setAttribute("value", "1");
        
        document.getElementById('formModul').appendChild(hiddenInput);
        document.getElementById('formModul').submit();
    }
</script>

</body>
</html>