<?php
session_start();
include "connection.php";
include "functions.php";

if (empty($_SESSION["login"]) || empty($_SESSION["userId"]) || (!empty($_SESSION["role"]))) {
    http_response_code(403);
    exit("Brak autoryzacji");
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    header("Location: index.php");
    exit;
}

$table = $_GET["table"] ?? "magazyn";

$allowedTables = [
    "magazyn",
    "wydania",
    "inwentaryzacja",
    "inwentaryzacja_sesja",
    "uzytkownicy"
];

if (!in_array($table, $allowedTables, true)) {
    exit("Nieprawidłowa tabela");
}

function renderTable($result, $table)
{
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover align-middle table-sm mb-0">';
    echo "<thead><tr>";

    $fields = mysqli_fetch_fields($result);

    foreach ($fields as $field) {
        echo "<th class='text-center'>" . htmlspecialchars($field->name) . "</th>";
    }

    if ($table === "magazyn") {
        echo "<th class='text-center'>Akcje</th>";
    }

    echo "</tr></thead><tbody>";

    while ($row = mysqli_fetch_assoc($result)) {
        $id = reset($row);

        echo "<tr data-id='" . htmlspecialchars($id) . "'>";
        foreach ($row as $value) {
            echo "<td class='text-center'>" . htmlspecialchars($value) . "</td>";
        }

        if ($table === "magazyn") {
            echo "<td>
                    <button class='editBtn btn btn-warning btn-sm'>Edytuj</button>
                    <button class='deleteBtn btn btn-danger btn-sm'>Usuń</button>
                    <button class='issueBtn btn btn-secondary btn-sm'>Wydanie</button>
                    <button class='inventoryBtn btn btn-info btn-sm'>Inwentaryzacja</button>
                  </td>";
        }

        echo "</tr>";
    }

    echo "</tbody></table></div>";
}

if (isset($_POST["searchProductByName"]) && $_POST["searchProductByName"] === "searchProductByName") {

    $name = getPostText("productName");

    if ($name !== "") {

        $sql = "SELECT 
                    magazyn.id,
                    magazyn.id_uzytkownika,
                    magazyn.nazwa,
                    CONCAT(uzytkownicy.imie,' ',uzytkownicy.nazwisko) AS `Dodał`,
                    magazyn.kategoria,
                    magazyn.jednostka,
                    magazyn.ilosc,
                    magazyn.lokalizacja,
                    magazyn.uwagi
                FROM magazyn
                INNER JOIN uzytkownicy 
                    ON magazyn.id_uzytkownika = uzytkownicy.id
                WHERE magazyn.nazwa LIKE ?";

        $stmt = mysqli_prepare($database, $sql);
        $param = "%{$name}%";

        mysqli_stmt_bind_param($stmt, "s", $param);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        renderTable($result, "magazyn");

        mysqli_stmt_close($stmt);
        mysqli_close($database);

        exit;
    }
}

$queries = [
    "uzytkownicy" => "
        SELECT 
            id,
            CONCAT(imie,' ',nazwisko) AS 'imię i nazwisko',
            status_konta AS 'status konta',
            rola
        FROM uzytkownicy
    ",
    "magazyn" => "
        SELECT 
            magazyn.id,
            magazyn.id_uzytkownika,
            CONCAT(uzytkownicy.imie,' ',uzytkownicy.nazwisko) AS 'dodał',
            magazyn.nazwa AS 'nazwa produktu',
            magazyn.kategoria,
            magazyn.jednostka,
            magazyn.ilosc,
            magazyn.lokalizacja,
            magazyn.uwagi
        FROM magazyn
        INNER JOIN uzytkownicy 
            ON magazyn.id_uzytkownika = uzytkownicy.id
    ",
    "wydania" => "
        SELECT 
            wydania.id,
            wydania.id_produktu,
            wydania.id_uzytkownika,
            CONCAT(uzytkownicy.imie,' ',uzytkownicy.nazwisko) AS 'wydał',
            wydania.data_i_godzina,
            magazyn.nazwa AS 'nazwa produktu',
            magazyn.jednostka,
            wydania.ilosc,
            wydania.powod
        FROM wydania
        INNER JOIN uzytkownicy 
            ON wydania.id_uzytkownika = uzytkownicy.id
        INNER JOIN magazyn 
            ON magazyn.id = wydania.id_produktu
    ",
    "inwentaryzacja" => "
        SELECT
            inwentaryzacja.id,
            inwentaryzacja.id_produktu,
            inwentaryzacja.id_sesji,
            inwentaryzacja_sesja.id_uzytkownika,
            CONCAT(uzytkownicy.imie,' ',uzytkownicy.nazwisko) AS 'zinwentaryzował',
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
            ON inwentaryzacja.id_produktu = magazyn.id
    ",
    "inwentaryzacja_sesja" => "
        SELECT 
            inwentaryzacja_sesja.id,
            inwentaryzacja_sesja.id_uzytkownika,
            CONCAT(uzytkownicy.imie,' ',uzytkownicy.nazwisko) AS 'imię i nazwisko',
            inwentaryzacja_sesja.data_sesji
        FROM inwentaryzacja_sesja
        INNER JOIN uzytkownicy 
            ON inwentaryzacja_sesja.id_uzytkownika = uzytkownicy.id
    "
];

$result = mysqli_query($database, $queries[$table]);

renderTable($result, $table);

mysqli_close($database);
exit;
?>
