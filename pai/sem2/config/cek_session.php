<?php
require_once 'session.php';

if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = time();
}

echo "Session test value: " . $_SESSION['test'];