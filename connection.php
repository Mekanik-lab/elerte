<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = connectDatabase();

function connectDatabase(): mysqli
{
    $database = mysqli_connect("localhost", "root", "", "magazyn");
    mysqli_set_charset($database, "utf8mb4");

    return $database;
}