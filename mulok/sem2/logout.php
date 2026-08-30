<?php
// 1. Mulai session
session_start();

// 2. Hancurkan SEMUA variabel session
$_SESSION = array();

// 3. Hancurkan session cookie (opsional tapi disarankan)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan session
session_destroy();

// 5. Arahkan pengguna kembali ke halaman login
header("Location: ../../login.php"); // Mengarah ke login.php di root
exit();
?>