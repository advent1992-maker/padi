<?php
require_once 'config/koneksi.php';

// Konfigurasi Identitas Aplikasi
$kode_app = 'PADI_PORTAL';

// 1. INSTRUMEN AHLI MATERI (Fokus pada Substansi Pendidikan)
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

// 2. INSTRUMEN AHLI MEDIA (Fokus pada Teknis & UI/UX)
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

$pesan_status = '';
$show_form = true;

// LOGIKA PENYIMPANAN DATA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_validasi'])) {
    try {
        $conn->begin_transaction();

        $query = "INSERT INTO hasil_validasi (nama_ahli, bidang_ahli, instansi, aspek, indikator, skor_penilaian, catatan_saran, kesimpulan_umum, kode_aplikasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        $nama_ahli = htmlspecialchars($_POST['nama_ahli']);
        $bidang_ahli = htmlspecialchars($_POST['bidang_ahli']);
        $instansi = htmlspecialchars($_POST['instansi']);
        $kesimpulan = htmlspecialchars($_POST['kesimpulan_umum']);

        // Pilih array instrumen berdasarkan pilihan bidang
        $data_instrumen = ($bidang_ahli == 'Ahli Materi') ? $instrumen_materi : $instrumen_media;

        foreach ($data_instrumen as $aspek => $indikator_list) {
            $aspek_clean = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
            $saran_per_aspek = htmlspecialchars($_POST['saran_' . $aspek_clean] ?? '');

            foreach ($indikator_list as $index => $indikator) {
                $input_name = "skor_" . $aspek_clean . "_" . $index;
                $skor = $_POST[$input_name] ?? 0;

                if ($skor > 0) {
                    $stmt->bind_param("sssssisss", 
                        $nama_ahli, $bidang_ahli, $instansi, $aspek, $indikator, $skor, $saran_per_aspek, $kesimpulan, $kode_app
                    );
                    $stmt->execute();
                }
            }
        }

        $conn->commit();
        $pesan_status = '
            <div class="alert alert-success text-center shadow border-0 p-4">
                <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                <h4 class="fw-bold">Data Validasi Berhasil Dikirim</h4>
                <p class="mb-0">Terima kasih atas kontribusi Bapak/Ibu dalam pengembangan aplikasi PADI.</p>
            </div>';
        $show_form = false;

    } catch (Exception $e) {
        $conn->rollback();
        $pesan_status = '<div class="alert alert-danger">Terjadi kesalahan sistem: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Validasi Ahli - PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; padding: 50px 0; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 20px; border: none; overflow: hidden; }
        .header-section { background: linear-gradient(135deg, #0f172a, #334155); color: white; padding: 40px; }
        .section-title { border-left: 6px solid #10b981; padding-left: 15px; margin: 35px 0 20px 0; color: #1e293b; font-weight: 700; }
        .score-box { width: 48px; height: 48px; display: inline-block; line-height: 48px; text-align: center; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: 0.2s; font-weight: bold; }
        .form-check-input:checked + .form-check-label .score-box { background: #10b981; color: white; border-color: #10b981; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg">
                <div class="header-section text-center">
                    <h2 class="fw-bold mb-1">INSTRUMEN VALIDASI AHLI</h2>
                    <p class="mb-0 opacity-75">Pengembangan Aplikasi Pembelajaran Anak Digital (PADI)</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?= $pesan_status ?>

                    <?php if ($show_form): ?>
                    <form method="POST" id="formValidasi">
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary">Nama Lengkap & Gelar</label>
                                <input type="text" name="nama_ahli" class="form-control form-control-lg" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">Bidang Keahlian</label>
                                <select name="bidang_ahli" id="pilihBidang" class="form-select form-select-lg border-primary" onchange="filterInstrumen()" required>
                                    <option value="">-- Pilih Bidang --</option>
                                    <option value="Ahli Materi">Ahli Materi</option>
                                    <option value="Ahli Media">Ahli Media</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary">Instansi/Lembaga</label>
                                <input type="text" name="instansi" class="form-control form-control-lg" required>
                            </div>
                        </div>

                        <div id="wrapperMateri" style="display:none;">
                            <?php foreach ($instrumen_materi as $aspek => $indikator_list): 
                                $aspek_id = preg_replace('/[^A-Za-z0-9]/', '', $aspek); ?>
                                <h5 class="section-title"><?= $aspek ?></h5>
                                <?php renderTable($aspek_id, $indikator_list); ?>
                            <?php endforeach; ?>
                        </div>

                        <div id="wrapperMedia" style="display:none;">
                            <?php foreach ($instrumen_media as $aspek => $indikator_list): 
                                $aspek_id = preg_replace('/[^A-Za-z0-9]/', '', $aspek); ?>
                                <h5 class="section-title"><?= $aspek ?></h5>
                                <?php renderTable($aspek_id, $indikator_list); ?>
                            <?php endforeach; ?>
                        </div>

                        <div id="wrapperAkhir" style="display:none;">
                            <div class="p-4 rounded-4 border-2 border-primary border my-5 bg-light">
                                <label class="form-label fw-bold h5">Kesimpulan Kelayakan Secara Umum:</label>
                                <select name="kesimpulan_umum" class="form-select form-select-lg" required>
                                    <option value="">-- Pilih Kesimpulan --</option>
                                    <option value="Sangat Layak">Sangat Layak (Tanpa Revisi)</option>
                                    <option value="Layak">Layak (Revisi Kecil)</option>
                                    <option value="Cukup Layak">Cukup Layak (Revisi Besar)</option>
                                    <option value="Tidak Layak">Tidak Layak</option>
                                </select>
                            </div>
                            <div class="text-center">
                                <button type="submit" name="submit_validasi" class="btn btn-primary btn-xl px-5 py-3 fw-bold rounded-pill shadow">
                                    KIRIM HASIL PENILAIAN <i class="fas fa-paper-plane ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterInstrumen() {
    const bidang = document.getElementById('pilihBidang').value;
    const wMateri = document.getElementById('wrapperMateri');
    const wMedia = document.getElementById('wrapperMedia');
    const wAkhir = document.getElementById('wrapperAkhir');

    // 1. Sembunyikan semua kontainer terlebih dahulu
    wMateri.style.display = 'none';
    wMedia.style.display = 'none';
    wAkhir.style.display = 'none';

    // 2. Nonaktifkan 'required' untuk SEMUA radio button terlebih dahulu
    // Ini penting agar tidak ada input tersembunyi yang menahan form
    const allRadios = document.querySelectorAll('input[type="radio"]');
    allRadios.forEach(radio => {
        radio.required = false;
    });

    // 3. Tampilkan kontainer yang sesuai dan aktifkan 'required' hanya untuk yang tampil
    if (bidang === 'Ahli Materi') {
        wMateri.style.display = 'block';
        wAkhir.style.display = 'block';
        const materiRadios = wMateri.querySelectorAll('input[type="radio"]');
        materiRadios.forEach(r => r.required = true);
        
    } else if (bidang === 'Ahli Media') {
        wMedia.style.display = 'block';
        wAkhir.style.display = 'block';
        const mediaRadios = wMedia.querySelectorAll('input[type="radio"]');
        mediaRadios.forEach(r => r.required = true);
    }
}
</script>

<?php
// Fungsi Helper untuk Render Tabel agar kode tidak redundan
function renderTable($aspek_id, $list) {
    echo '<div class="table-responsive"><table class="table table-bordered align-middle bg-white">';
    echo '<thead class="table-light text-center"><tr><th width="50">No</th><th>Indikator Penilaian</th><th width="320">Skor (1-4)</th></tr></thead><tbody>';
    foreach ($list as $idx => $txt) {
        $name = "skor_" . $aspek_id . "_" . $idx;
        echo "<tr><td class='text-center fw-bold'>".($idx+1)."</td><td>$txt</td><td class='text-center'>";
        for ($i=1; $i<=4; $i++) {
            echo "<div class='form-check form-check-inline'>
                    <input class='form-check-input d-none' type='radio' name='$name' id='{$name}{$i}' value='$i'>
                    <label class='form-check-label' for='{$name}{$i}'><span class='score-box'>$i</span></label>
                  </div>";
        }
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
    echo "<div class='mb-4'><textarea name='saran_$aspek_id' class='form-control' rows='2' placeholder='Catatan saran perbaikan khusus aspek ini...'></textarea></div>";
}
?>

</body>
</html>