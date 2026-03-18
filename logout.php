<?php
session_start();

$userLogin = $_SESSION["login"] ?? "";

session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=login.php">
  <title>Wylogowanie...</title>
</head>
<body>
<script>
  (function () {
    const userLogin = <?= json_encode($userLogin) ?>;

    if (userLogin) {
      localStorage.removeItem(`lastSection_${userLogin}`);
      localStorage.removeItem(`currentInventoryId_${userLogin}`);
      localStorage.removeItem(`sectionFilters_${userLogin}`);
    }

    localStorage.removeItem("lastSection_global");
    localStorage.removeItem("currentInventoryId_global");
    localStorage.removeItem("sectionFilters_global");

    window.location.replace("login.php");
  })();
</script>
</body>
</html>