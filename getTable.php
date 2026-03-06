<?php
session_start();
include "connection.php";
include "functions.php";

if (empty($_SESSION["login"]) || empty($_SESSION["userId"])) {
    http_response_code(403);
    exit("Brak autoryzacji");
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    header("Location: index.php");
    exit;
}

$table = $_GET["table"] ?? "magazyn";
$allowed = ["magazyn", "wydania", "inwentaryzacja", "inwentaryzacja_sesja", "uzytkownicy"];

if (!in_array($table, $allowed, true)) {
    echo "Nieprawidłowa tabela";
    exit;
}

if (in_array($table, $allowed, true) && !empty($_SESSION["role"])) {
    http_response_code(403);
    exit("Brak uprawnień");
}

if (isset($_POST["searchProductByName"]) && $_POST["searchProductByName"] === "searchProductByName") {
    $name = getPostText("productName");

    if ($name !== "") {
        $sql = "SELECT 
                    magazyn.id,
                    magazyn.id_uzytkownika,
                    magazyn.nazwa,
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS `Dodał`,
                    magazyn.kategoria,
                    magazyn.jednostka,
                    magazyn.ilosc,
                    magazyn.lokalizacja,
                    magazyn.uwagi
                FROM magazyn
                INNER JOIN uzytkownicy ON magazyn.id_uzytkownika = uzytkownicy.id
                WHERE magazyn.nazwa LIKE ?;";
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

if ($table === "uzytkownicy") {
    $sql = "SELECT 
                id,
                CONCAT(imie, ' ', nazwisko) AS 'imię i nazwisko',
                status_konta AS 'status konta',
                rola AS `rola`
            FROM uzytkownicy";
} elseif ($table === "magazyn") {
    $sql = "SELECT 
                magazyn.id,
                magazyn.id_uzytkownika,
                CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'dodał',
                magazyn.nazwa AS 'nazwa produktu',
                magazyn.kategoria,
                magazyn.jednostka,
                magazyn.ilosc,
                magazyn.lokalizacja,
                magazyn.uwagi
            FROM magazyn
            INNER JOIN uzytkownicy ON magazyn.id_uzytkownika = uzytkownicy.id;";
} elseif ($table === "wydania") {
    $sql = "SELECT 
                wydania.id,
                wydania.id_produktu,
                wydania.id_uzytkownika,
                CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'wydał',
                wydania.data_i_godzina,
                magazyn.nazwa AS 'nazwa produktu',
                magazyn.jednostka,
                wydania.ilosc,
                wydania.powod
            FROM wydania
            INNER JOIN uzytkownicy ON wydania.id_uzytkownika = uzytkownicy.id
            INNER JOIN magazyn ON magazyn.id = wydania.id_produktu;";
} else if ($table === "inwentaryzacja") {
    $sql = "SELECT
            inwentaryzacja.id,
            inwentaryzacja.id_produktu,
            inwentaryzacja.id_sesji,
            inwentaryzacja_sesja.id_uzytkownika,
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'zinwentaryzował',
            magazyn.nazwa AS 'nazwa produktu',
            magazyn.jednostka,
            inwentaryzacja.stan,
            inwentaryzacja.roznica
        FROM inwentaryzacja
        INNER JOIN inwentaryzacja_sesja
            ON inwentaryzacja.id_sesji = inwentaryzacja_sesja.id
        INNER JOIN uzytkownicy
            ON inwentaryzacja_sesja.id_uzytkownika = uzytkownicy.id
        INNER JOIN magazyn
            ON inwentaryzacja.id_produktu = magazyn.id;";
} elseif ($table === "inwentaryzacja_sesja") {
    $sql = "SELECT 
                inwentaryzacja_sesja.id,
                inwentaryzacja_sesja.id_uzytkownika,
                CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'imię i nazwisko',
                inwentaryzacja_sesja.data_sesji 
            FROM inwentaryzacja_sesja
            INNER JOIN uzytkownicy ON inwentaryzacja_sesja.id_uzytkownika = uzytkownicy.id";
}

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