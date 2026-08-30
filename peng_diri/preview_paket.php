<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// 1. Ambil Parameter
$id_paket = $_GET['id'] ?? 0;
$kat = $_GET['kat'] ?? 'osn';

// 2. Koneksi ke Database Peng_diri
$conn_pusat = $conn;

// 3. Ambil Data Paket
$q_paket = mysqli_query($conn_pusat, "SELECT * FROM paket_peng_diri WHERE id = '$id_paket'");
$p = mysqli_fetch_assoc($q_paket);

if (!$p) { echo "Paket tidak ditemukan."; exit; }

// 4. Ambil Soal berdasarkan paket_id dan tabel kategori
$q_soal = mysqli_query($conn_pusat, "SELECT * FROM $kat WHERE paket_id = '$id_paket' ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Soal - <?= htmlspecialchars($p['nama_paket']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .paper { background: white; padding: 50px; min-height: 297mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin: 30px auto; max-width: 800px; }
        .soal-block { margin-bottom: 20px; page-break-inside: avoid; }
        .opsi { list-style-type: none; padding-left: 20px; }
        @media print {
            .no-print { display: none !important; }
            .paper { margin: 0; box-shadow: none; width: 100%; max-width: 100%; padding: 20px; }
            body { background: white; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4 text-center">
    <div class="btn-group shadow-sm">
        <a href="paket_list.php?kat=<?= $kat ?>" class="btn btn-dark"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Cetak / Download PDF</button>
    </div>
</div>

<div class="paper">
    <div class="text-center mb-4">
        <h4 class="fw-bold mb-0">NASKAH SOAL BIMBINGAN <?= strtoupper($kat) ?></h4>
        <h5 class="text-uppercase"><?= htmlspecialchars($p['mapel']) ?> - <?= htmlspecialchars($p['nama_paket']) ?></h5>
        <hr style="border: 2px solid black;">
    </div>

    <?php 
    $no = 1;
    if(mysqli_num_rows($q_soal) > 0) {
        while($s = mysqli_fetch_assoc($q_soal)) {
            echo '<div class="soal-block">';
            echo '<p class="mb-1"><strong>' . $no++ . '.</strong> ' . $s['pertanyaan'] . '</p>';
            
            // Cek jika ada gambar soal (asumsi nama kolom 'gambar')
            if(!empty($s['gambar'])) {
                echo '<img src="../assets/img/soal/' . $s['gambar'] . '" class="img-fluid mb-2" style="max-height:200px;">';
            }

            echo '<ul class="opsi">';
            echo '<li>A. ' . htmlspecialchars($s['opsi_a']) . '</li>';
            echo '<li>B. ' . htmlspecialchars($s['opsi_b']) . '</li>';
            echo '<li>C. ' . htmlspecialchars($s['opsi_c']) . '</li>';
            echo '<li>D. ' . htmlspecialchars($s['opsi_d']) . '</li>';
            echo '</ul>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-center text-muted mt-5">Belum ada soal dalam paket ini.</p>';
    }
    ?>
    
    <div class="mt-5 pt-4 text-end small border-top text-muted">
        Dicetak otomatis melalui Aplikasi PADI - <?= date('d/m/Y H:i') ?>
    </div>
</div>
<script>
  window.MathJax = {
    tex: {
      inlineMath: [['$', '$'], ['\\(', '\\)']],
      displayMath: [['$$', '$$'], ['\\[', '\\]']],
      processEscapes: true
    },
    svg: {
      fontCache: 'global'
    }
  };
</script>
<script type="text/javascript" id="MathJax-script" async
  src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
</script>

</body>
</html>