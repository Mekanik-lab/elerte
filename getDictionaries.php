<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

include "connection.php";
include "functions.php";

requireLoggedUser();

$type = trim((string) ($_GET["type"] ?? ""));

function fetchDictionaryValues(mysqli $database, string $sql): array
{
    $result = mysqli_query($database, $sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Błąd pobierania słownika"]);
        mysqli_close($database);
        exit;
    }

    $items = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $value = trim((string) ($row["value"] ?? ""));
        if ($value !== "") {
            $items[] = $value;
        }
    }

    return array_values(array_unique($items));
}

switch ($type) {
    case "kategorie":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT nazwa AS value
             FROM slownik_kategorie
             WHERE aktywna = 1
             ORDER BY nazwa ASC"
        ));
        break;

    case "lokalizacje":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT nazwa AS value
             FROM slownik_lokalizacje
             WHERE aktywna = 1
             ORDER BY nazwa ASC"
        ));
        break;

    case "magazyn_dodal":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT CONCAT(imie, ' ', nazwisko) AS value
             FROM uzytkownicy
             WHERE TRIM(CONCAT(imie, ' ', nazwisko)) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "magazyn_nazwa_produktu":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT nazwa AS value
             FROM magazyn
             WHERE TRIM(nazwa) <> ''
             ORDER BY nazwa ASC"
        ));
        break;

    case "wydania_wydal":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT CONCAT(imie, ' ', nazwisko) AS value
             FROM uzytkownicy
             WHERE TRIM(CONCAT(imie, ' ', nazwisko)) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "wydania_nazwa_produktu":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT COALESCE(nazwa_produktu_snapshot, '') AS value
             FROM wydania
             WHERE TRIM(COALESCE(nazwa_produktu_snapshot, '')) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "inwentaryzacje_zinwentaryzowal":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT CONCAT(u.imie, ' ', u.nazwisko) AS value
             FROM inwentaryzacje i
             INNER JOIN uzytkownicy u ON i.id_uzytkownika = u.id
             WHERE TRIM(CONCAT(u.imie, ' ', u.nazwisko)) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "inwentaryzacje_numer":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT numer_inwentaryzacji AS value
             FROM inwentaryzacje
             WHERE TRIM(COALESCE(numer_inwentaryzacji, '')) <> ''
             ORDER BY numer_inwentaryzacji ASC"
        ));
        break;

    case "inwentaryzacja_pozycja_nazwa_produktu":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT COALESCE(ip.nazwa_produktu_snapshot, m.nazwa) AS value
             FROM inwentaryzacja_pozycja ip
             LEFT JOIN magazyn m ON ip.id_produktu = m.id
             WHERE TRIM(COALESCE(ip.nazwa_produktu_snapshot, m.nazwa, '')) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "uzytkownicy_login":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT login AS value
             FROM uzytkownicy
             WHERE TRIM(login) <> ''
             ORDER BY login ASC"
        ));
        break;

    case "uzytkownicy_fullname":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT CONCAT(imie, ' ', nazwisko) AS value
             FROM uzytkownicy
             WHERE TRIM(CONCAT(imie, ' ', nazwisko)) <> ''
             ORDER BY value ASC"
        ));
        break;

    case "historia_nazwa_produktu":
        echo json_encode(fetchDictionaryValues(
            $database,
            "SELECT DISTINCT COALESCE(
                m.nazwa,
                JSON_UNQUOTE(JSON_EXTRACT(h.dane_przed, '$.nazwa')),
                JSON_UNQUOTE(JSON_EXTRACT(h.dane_po, '$.nazwa'))
             ) AS value
             FROM historia_operacji h
             LEFT JOIN magazyn m ON h.id_produktu = m.id
             WHERE TRIM(COALESCE(
                m.nazwa,
                JSON_UNQUOTE(JSON_EXTRACT(h.dane_przed, '$.nazwa')),
                JSON_UNQUOTE(JSON_EXTRACT(h.dane_po, '$.nazwa')),
                ''
             )) <> ''
             ORDER BY value ASC"
        ));
        break;

    default:
        http_response_code(400);
        echo json_encode(["error" => "Nieprawidłowy typ słownika"]);
        break;
}

mysqli_close($database);