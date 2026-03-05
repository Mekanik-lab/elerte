<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
    exit(header("Location:index.php"));
}

$database = mysqli_connect("localhost", "root", "", "magazyn");
if (!$database) {
    die("Błąd połączenia." . mysqli_connect_error());
}

$table = $_GET["table"] ?? "magazyn";

$allowed = ["magazyn", "wydania", "inwentaryzacja", "inwentaryzacja_sesja"];
if (!in_array($table, $allowed)) {
    exit("Nieprawidłowa tabela");
}

if (isset($_POST["searchProductByName"]) && $_POST["searchProductByName"] === "searchProductByName") {
    $name = $_POST["productName"] ?? "";

    if ($name !== "") {
        $sql = "SELECT * FROM magazyn WHERE nazwa = ?";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "s", $name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt); 

        echo "<table><thead><tr>";
        while ($field = mysqli_fetch_field($result)) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr></thead><tbody>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";

        mysqli_stmt_close($stmt);
        mysqli_close($database);
        exit;
    }
}

$sql = "SELECT * FROM $table";
$result = mysqli_query($database, $sql);

echo "<table><thead><tr>";
while ($row = mysqli_fetch_field($result)) {
    echo "<th>" . $row->name . "</th>";
}
echo "</tr></thead><tbody>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . $value . "</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";
mysqli_close($database);
?>