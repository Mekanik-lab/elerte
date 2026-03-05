<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    header("Location:index.php");
    exit;
}

$database = mysqli_connect("localhost", "root", "", "magazyn");
mysqli_set_charset($database, 'utf8mb4');

function htmlspecialcharEscapeFunction($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$table = $_GET["table"] ?? "magazyn";
$allowed = ["magazyn", "wydania", "inwentaryzacja", "inwentaryzacja_sesja"];
if (!in_array($table, $allowed, true)) {
    echo "Nieprawidłowa tabela";
    exit;
}

// FILTER: searchProductByName (magazyn)
if (isset($_POST["searchProductByName"]) && $_POST["searchProductByName"] === "searchProductByName") {
    $name = trim((string)($_POST["productName"] ?? ""));

    if ($name !== "") {
        $sql = "SELECT * FROM magazyn WHERE nazwa LIKE ?;";
        $stmt = mysqli_prepare($database, $sql);
        $nameParam = "%" . $name . "%";
        mysqli_stmt_bind_param($stmt, "s", $nameParam);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover align-middle table-sm mb-0">';
        echo "<thead><tr>";
        while ($field = mysqli_fetch_field($result)) {
            echo "<th>" . htmlspecialcharEscapeFunction($field->name) . "</th>";
        }
        echo "</tr></thead><tbody>";

        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialcharEscapeFunction($value) . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table></div>";

        mysqli_stmt_close($stmt);
        mysqli_close($database);
        exit;
    }
}

// Default: select *
$sql = "SELECT * FROM `$table`";
$result = mysqli_query($database, $sql);

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover align-middle table-sm mb-0">';
echo "<thead><tr>";
while ($field = mysqli_fetch_field($result)) {
    echo "<th>" . htmlspecialcharEscapeFunction($field->name) . "</th>";
}
echo "</tr></thead><tbody>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialcharEscapeFunction($value) . "</td>";
    }
    echo "</tr>";
}

echo "</tbody></table></div>";

mysqli_close($database);
exit;
?>