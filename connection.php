<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = mysqli_connect('localhost', 'root', '', 'magazyn');
mysqli_set_charset($database, 'utf8mb4');

if (!$database) {
    die("Błąd połączenia z bazą.");
}
?>