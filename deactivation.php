<?php
include "connection.php";
include "functions.php";
session_start();

if (empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['userId'];

$sql = "UPDATE uzytkownicy SET status_konta = 'nieaktywne' WHERE id = ?";
$stmt = mysqli_prepare($database, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

session_unset();
session_destroy();

header("Location: login.php");
exit;
?>