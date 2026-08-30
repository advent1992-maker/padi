<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$tryout_id = isset($_GET['tryout_id']) ? (int)$_GET['tryout_id'] : 0;

// 1. Ambil Data Master Tryout
$stmt = $db_mapel->prepare("SELECT * FROM tryout_master WHERE id = ?");
$stmt->bind_param("i", $tryout_id);
$stmt->execute();
$master = $stmt->get_result()->fetch_assoc();

if (!$master) die("Data Try Out tidak ditemukan.");

// 2. LOGIKA DOWNLOAD WORD (Sesuai perbaikan)
if (isset($_GET['action']) && $_GET['action'] == 'download_word') {
    // Nama file harus diakhiri .doc secara manual di sini
    $filename = "Tryout_" . str_replace(' ', '_', $master['judul']) . ".doc";
    
    header("Content-Type: application/msword"); // Gunakan MIME type yang lebih umum
    header("Content-Disposition: attachment; filename=\"$filename\""); 
    header("Cache-Control: z-private, max-age=0, must-revalidate");
    header("Pragma: public");
    header("Expires: 0");
    
    // Penting: Hapus output buffer jika ada untuk mencegah file korup
    ob_clean();
    flush();
}

// 3. Ambil Semua Soal
$query_soal = "SELECT * FROM soal_tryout WHERE tryout_id = ? ORDER BY id ASC";
$stmt_s = $db_mapel->prepare($query_soal);
$stmt_s->bind_param("i", $tryout_id);
$stmt_s->execute();
$res_soal = $stmt_s->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Soal: <?= htmlspecialchars($master['judul']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$']] }
        };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <style>
        :root { --width-a4: 21cm; --height-a4: 29.7cm; }
        body { background: #e0e0e0; font-family: "Times New Roman", Times, serif; margin: 0; }
        
        /* Layout Kertas A4 */
        .page { 
            width: var(--width-a4); min-height: var(--height-a4); 
            padding: 1.5cm 2cm; margin: 1cm auto; background: white; 
            box-shadow: 0 0 10px rgba(0,0,0,0.2); position: relative;
        }

        .kop-surat { text-align: center; border-bottom: 3px double #000; margin-bottom: 20px; padding-bottom: 10px; }
        
        .table-identitas { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .table-identitas td { border: 1px solid #000; padding: 8px; font-size: 11pt; }
        
        .item-soal { margin-bottom: 25px; display: flex; gap: 10px; page-break-inside: avoid; }
        .img-soal { max-width: 300px; display: block; margin: 10px 0; border: 1px solid #eee; }
        
        /* Opsi Jawaban 2 Kolom */
        .opsi-container { margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .opsi-item { display: flex; gap: 8px; align-items: flex-start; }

        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 1.5cm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print d-flex justify-content-center py-3 bg-dark sticky-top gap-2">
    <button onclick="window.history.back()" class="btn btn-light rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Kembali</button>
    <button onclick="window.print()" class="btn btn-primary rounded-pill px-4"><i class="fas fa-print me-2"></i>Cetak / PDF</button>
    <a href="?tryout_id=<?= $tryout_id ?>&action=download_word" class="btn btn-success rounded-pill px-4"><i class="fas fa-file-word me-2"></i>Download Word</a>
</div>

<div class="page">
    <div class="kop-surat">
        <h4 style="margin:0; font-weight: bold;">LEMBAR SOAL TRY OUT</h4>
        <h3 style="margin:5px 0; text-transform: uppercase; font-weight: bold;"><?= htmlspecialchars($master['judul']) ?></h3>
        <p style="margin:0; font-size: 11pt; font-weight: bold;">Tahun Pelajaran: 2025/2026</p>
    </div>

    <table class="table-identitas">
        <tr>
            <td width="15%">Nama</td><td width="40%">: ...........................................</td>
            <td width="15%">Kelas</td><td width="15%">: <?= htmlspecialchars($master['kelas']) ?></td>
            <td width="15%">Waktu</td><td width="15%">: <?= htmlspecialchars($master['waktu_alokasi']) ?>'</td>
        </tr>
    </table>

    <div class="soal-container">
        <?php $no = 1; while($s = $res_soal->fetch_assoc()): ?>
            <div class="item-soal">
                <div style="font-weight:bold; min-width: 25px;"><?= $no++ ?>.</div>
                
                <div style="flex:1;">
                    <div class="pertanyaan">
                        <?= nl2br(htmlspecialchars($s['pertanyaan'])) ?>
                        <?php if(!empty($s['gambar_url'])): ?>
                            <img src="../aset/<?= $s['gambar_url'] ?>" class="img-soal">
                        <?php endif; ?>
                    </div>
                    
                    <div class="opsi-container">
                        <?php foreach(['a','b','c','d'] as $p): 
                            $img_col = "opsi_" . $p . "_gambar_url";
                        ?>
                            <div class="opsi-item">
                                <strong><?= strtoupper($p) ?>.</strong>
                                <div>
                                    <span><?= htmlspecialchars($s['opsi_'.$p]) ?></span>
                                    <?php if(!empty($s[$img_col])): ?>
                                        <img src="../aset/<?= $s[$img_col] ?>" style="max-width: 100px; display:block; margin-top:5px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>