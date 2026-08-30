<?php
// 1. DATA INSTRUMEN
$instrumen_materi = [
    'Kelayakan Isi & Materi' => [
        'Kesesuaian materi dengan tujuan pembelajaran dan kurikulum yang berlaku.',
        'Keakuratan fakta, konsep, dan prinsip dalam konten pembelajaran.',
        'Kemutakhiran materi yang disajikan dalam 8 mata pelajaran utama.',
        'Kedalaman materi sudah sesuai dengan tingkat kognitif siswa Sekolah Dasar.'
    ],
    'Aspek Kebahasaan' => [
        'Ketepatan penggunaan istilah dan struktur bahasa Indonesia yang baik.',
        'Kejelasan instruksi agar tidak menimbulkan makna ganda bagi siswa.',
        'Kesesuaian bahasa dengan tingkat perkembangan psikologis anak SD.'
    ]
];

$instrumen_media = [
    'Aspek Penyajian & Desain' => [
        'Kualitas estetika antarmuka (warna, tata letak, dan tipografi).',
        'Kemudahan navigasi menu pada dashboard siswa, guru, dan admin.',
        'Kesesuaian visualisasi ikon mata pelajaran dengan karakteristik anak digital.'
    ],
    'Aspek Teknis & Responsivitas' => [
        'Ketepatan adaptasi tampilan (responsive) saat berpindah dari mode IFP ke Mobile.',
        'Kecepatan respon sistem (loading time) saat mengakses modul pembelajaran.',
        'Akurasi integrasi data nilai antara akun siswa, guru, dan pemantauan orang tua.'
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Validasi PADI - Format Cetak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS KHUSUS TAMPILAN LAYAR & CETAK */
        body { background: #fff; font-family: 'Times New Roman', serif; color: #000; font-size: 12pt; }
        .container { max-width: 900px; }
        
        .header-section { 
            border-bottom: 3px double #000; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
            text-align: center;
        }

        /* Perbaikan agar tabel tidak terpotong */
        .keep-together {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 20px;
        }

        .section-title { 
            background: #f0f0f0 !important; 
            padding: 8px 12px; 
            margin: 15px 0 10px 0; 
            font-weight: bold; 
            border: 1px solid #000;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact; 
        }

        .line-input {
            border-bottom: 1px dotted #000;
            display: inline-block;
            width: 70%;
            height: 18px;
        }

        .score-box-empty {
            width: 28px;
            height: 28px;
            border: 1px solid #000;
            display: inline-block;
            text-align: center;
            line-height: 28px;
            margin: 0 2px;
            font-weight: bold;
        }

        .box-saran {
            border: 1px solid #000;
            min-height: 80px;
            width: 100%;
            margin-top: 5px;
        }

        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; text-align: center; }

        @media print {
            @page { size: A4; margin: 1.5cm; }
            .btn-print { display: none; }
            body { padding: 0; margin: 0; }
            .container { width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="text-end mb-4 btn-print">
        <button onclick="window.print()" class="btn btn-primary shadow-sm">
            <i class="fas fa-print me-2"></i> Cetak ke PDF / Printer
        </button>
    </div>

    <div class="header-section">
        <h3 class="fw-bold mb-1">INSTRUMEN VALIDASI AHLI</h3>
        <h5 class="mb-0">Pengembangan Aplikasi Pembelajaran Anak Digital (PADI)</h5>
    </div>

    <div class="row mb-4">
        <div class="col-12 mb-2">Nama Ahli : <span class="line-input"></span></div>
        <div class="col-12 mb-2">Instansi : <span class="line-input"></span></div>
        <div class="col-12 mb-2">Bidang Keahlian : [ ] Ahli Materi &nbsp;&nbsp;&nbsp; [ ] Ahli Media</div>
        <div class="col-12 mb-2">Tanggal : <span class="line-input" style="width: 30%;"></span></div>
    </div>

    <p class="small"><em>* Berilah tanda centang (✓) pada kolom skor: 4 (Sangat Baik), 3 (Baik), 2 (Cukup), 1 (Kurang).</em></p>

    <h5 class="fw-bold text-decoration-underline mt-4">I. ASPEK AHLI MATERI</h5>
    <?php foreach ($instrumen_materi as $aspek => $indikator_list): ?>
    <div class="keep-together">
        <div class="section-title"><?= $aspek ?></div>
        <table class="table table-bordered border-dark">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th>Indikator Penilaian</th>
                    <th width="25%">Skor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($indikator_list as $idx => $txt): ?>
                <tr>
                    <td class="text-center"><?= $idx+1 ?></td>
                    <td><?= $txt ?></td>
                    <td class="text-center">
                        <span class="score-box-empty">1</span>
                        <span class="score-box-empty">2</span>
                        <span class="score-box-empty">3</span>
                        <span class="score-box-empty">4</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="mb-1 fw-bold small">Saran Perbaikan Aspek <?= $aspek ?>:</p>
        <div class="box-saran"></div>
    </div>
    <?php endforeach; ?>

    <h5 class="fw-bold text-decoration-underline mt-5">II. ASPEK AHLI MEDIA</h5>
    <?php foreach ($instrumen_media as $aspek => $indikator_list): ?>
    <div class="keep-together">
        <div class="section-title"><?= $aspek ?></div>
        <table class="table table-bordered border-dark">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th>Indikator Penilaian</th>
                    <th width="25%">Skor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($indikator_list as $idx => $txt): ?>
                <tr>
                    <td class="text-center"><?= $idx+1 ?></td>
                    <td><?= $txt ?></td>
                    <td class="text-center">
                        <span class="score-box-empty">1</span>
                        <span class="score-box-empty">2</span>
                        <span class="score-box-empty">3</span>
                        <span class="score-box-empty">4</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="mb-1 fw-bold small">Saran Perbaikan Aspek <?= $aspek ?>:</p>
        <div class="box-saran"></div>
    </div>
    <?php endforeach; ?>

    <div class="keep-together mt-5 p-3 border border-dark">
        <p class="fw-bold mb-2">KESIMPULAN KELAYAKAN SECARA UMUM:</p>
        <p>[ ] Sangat Layak (Tanpa Revisi)</p>
        <p>[ ] Layak (Revisi Kecil)</p>
        <p>[ ] Cukup Layak (Revisi Besar)</p>
        <p>[ ] Tidak Layak</p>
        <p>Komentar Tambahan: ____________________________________________________________________</p>
    </div>

    <div class="row mt-4 keep-together">
        <div class="col-8"></div>
        <div class="col-4 text-center">
            <p>Validator,</p>
            <br><br><br>
            <p>( ________________________ )</p>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>