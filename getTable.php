<?php
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
        exit(header("Location:index.php"));
    }

    $database = mysqli_connect("localhost", "root", "", "magazyn");
    if(!$database) {
        die("Błąd połączenia." . mysqli_connect_error());
    }

    if(isset($_GET["table"])) {
        $table = $_GET["table"];
    } else {
        $table = "magazyn";
    }

    $allowed = ["magazyn", "wydania", "inwentaryzacja"];
    if (!in_array($table, $allowed)) {
        exit("Nieprawidłowa tabela");
    }
    
    $sql = "SELECT * FROM $table;";
    $result = mysqli_query($database, $sql);

    echo "<table><thead><tr>";

    while($row = mysqli_fetch_field($result)) {
        echo "<th>" . $row->name . "</th>";
    }

    echo "</tr></thead><tbody>";

    while($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach($row as $value) {
            echo "<td>" . $value ."</td>";
        }
        echo "</tr>";
    }

    echo "</tbody></table>";
    mysqli_close($database);
?>