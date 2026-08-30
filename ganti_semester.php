<?php
session_start();

if(isset($_GET['semester'])){

    $_SESSION['semester_aktif'] = $_GET['semester'];

}

$back = $_SERVER['HTTP_REFERER'] ?? 'dashboard_guru.php';

header("Location: ".$back);
exit;