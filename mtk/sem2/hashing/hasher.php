<?php
// Ganti koma (,) menjadi TITIK KOMA (;)
$file = fopen("data6.csv", "r");
fgetcsv($file); // Lewati header

while (($data = fgetcsv($file, 1000, ";")) !== FALSE) {

    // Ini akan menampilkan data yang terpisah (harus ada [0] dan [1])
    echo "Data yang dibaca: ";
    print_r($data);
    echo "<br>";

    // Pastikan kedua kolom ada sebelum diproses
    if (isset($data[0]) && isset($data[1])) {
        $username = $data[0];
        $password_mentah = $data[1];

        // --- PROSES HASHING ---
        $password_hash = password_hash($password_mentah, PASSWORD_BCRYPT);

        // --- TAMPILKAN HASIL HASH ---
        echo "Username: " . $username . " | Password Hash: " . $password_hash . "<br>";

    } else {
        // Ini akan muncul jika data tidak terpisah menjadi 2 kolom
        echo "⚠️ Error: Data tidak terpisah dengan benar. Periksa pemisah CSV.<br>";
    }
}

fclose($file);
?>