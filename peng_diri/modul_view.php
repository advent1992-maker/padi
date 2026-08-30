<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// 1. KONEKSI KE DB PENG_DIRI
$conn_pusat = $conn;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("ID Materi Tidak Valid."); }

$query = "SELECT judul_materi, isi_materi, mapel FROM materi_peng_diri WHERE id = $id";
$result = mysqli_query($conn_pusat, $query);
$m = mysqli_fetch_assoc($result);

if (!$m) { die("Materi tidak ditemukan."); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>IFP: <?= htmlspecialchars($m['judul_materi']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Lora:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; font-family: 'Poppins', sans-serif; }
        
        /* Header Mode */
        .ifp-header { 
            position: absolute; top: 0; left: 0; right: 0; height: 65px;
            background: #ffffff; display: flex; justify-content: space-between; 
            align-items: center; padding: 0 25px; z-index: 1000; 
            border-bottom: 4px solid #6610f2;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Container Materi */
        .materi-container { 
            width: 100%; height: calc(100% - 65px); 
            margin-top: 65px; background: #fff;
            overflow-y: auto; scroll-behavior: smooth;
        }

        /* Konten di dalamnya */
        .materi-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 60px 40px;
            font-family: 'Lora', serif; /* Font Serif agar nyaman dibaca lama */
            font-size: 1.4rem; /* Ukuran teks besar untuk IFP */
            line-height: 1.8;
            color: #1a1a1a;
        }

        /* Styling elemen di dalam konten */
        .materi-content h1, .materi-content h2, .materi-content h3 { font-family: 'Poppins', sans-serif; font-weight: 700; color: #6610f2; margin-top: 40px; }
        .materi-content img { max-width: 100%; height: auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin: 30px 0; }
        .materi-content table { width: 100% !important; border-collapse: collapse; margin: 25px 0; }
        .materi-content table td, .materi-content table th { border: 2px solid #dee2e6; padding: 12px; }

        /* Floating Nav */
        .floating-nav { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; gap: 15px; }
        .btn-circle {
            width: 65px; height: 65px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            border: none; transition: 0.3s; color: white;
        }
        .btn-fs { background: #6610f2; }
        .btn-close-ifp { background: #dc3545; }
        .btn-circle:hover { transform: translateY(-5px) scale(1.05); }

        /* Fullscreen Effect */
        .fullscreen-active .ifp-header { display: none; }
        .fullscreen-active .materi-container { height: 100%; margin-top: 0; }

        /* Scrollbar cantik */
        .materi-container::-webkit-scrollbar { width: 10px; }
        .materi-container::-webkit-scrollbar-track { background: #f1f1f1; }
        .materi-container::-webkit-scrollbar-thumb { background: #6610f2; border-radius: 10px; }
    </style>
</head>
<body>

<div class="ifp-header">
    <div class="d-flex align-items-center">
        <span class="badge bg-purple-light text-purple me-3 py-2 px-3" style="background: #e0d0ff; color: #6610f2; border-radius: 8px; font-weight: 700;">
            <?= strtoupper($m['mapel']) ?>
        </span>
        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($m['judul_materi']) ?></h4>
    </div>
    <div class="small text-muted fw-bold"><i class="fas fa-desktop me-2"></i>MODE PANEL INTERAKTIF</div>
</div>

<div class="materi-container" id="scrollArea">
    <div class="materi-content">
        <?= $m['isi_materi'] ?>
    </div>
</div>

<div class="floating-nav">
    <button onclick="toggleFullScreen()" class="btn btn-fs btn-circle" id="btnFS" title="Layar Penuh">
        <i class="fas fa-expand"></i>
    </button>
    <button onclick="window.close()" class="btn btn-close-ifp btn-circle" title="Keluar">
        <i class="fas fa-times"></i>
    </button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML"></script>
<script>
    function toggleFullScreen() {
        const doc = window.document;
        const docEl = doc.documentElement;

        const requestFS = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullScreen || docEl.msRequestFullscreen;
        const exitFS = doc.exitFullscreen || doc.mozCancelFullScreen || doc.webkitExitFullscreen || doc.msExitFullscreen;

        if (!doc.fullscreenElement && !doc.mozFullScreenElement && !doc.webkitFullscreenElement && !doc.msFullscreenElement) {
            requestFS.call(docEl);
            document.body.classList.add('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-compress"></i>';
        } else {
            exitFS.call(doc);
            document.body.classList.remove('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-expand"></i>';
        }
    }

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            document.body.classList.remove('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-expand"></i>';
        }
    });
</script>

</body>
</html>