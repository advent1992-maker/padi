<?php
$password_mentah = '123456';
$password_hash = password_hash($password_mentah, PASSWORD_DEFAULT);
echo "Password Hash untuk 'Martapura06' adalah: <br><strong>" . $password_hash . "</strong>";
?>