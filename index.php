<?php
session_start();

include "connection.php";
include "functions.php";

requireLoggedUser();

$sessionUserId = getLoggedUserId();
validateActiveAccount($database, $sessionUserId);

if (isPostRequest()) {
    handlePostActions($database, $sessionUserId);
}

$currentUserFullName = trim((string) (($_SESSION["imie"] ?? "") . " " . ($_SESSION["nazwisko"] ?? "")));

mysqli_close($database);

function validateActiveAccount($database, $userId)
{
    $sql = "SELECT status_konta FROM uzytkownicy WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user || $user["status_konta"] === "nieaktywne") {
        session_unset();
        session_destroy();
        redirect("login.php");
    }
}

function handlePostActions($database, $sessionUserId)
{
    if (isset($_POST["addProduct"])) {
        handleAddProduct($database, $sessionUserId);
        redirect("index.php");
        return;
    }

    if (isset($_POST["editProduct"])) {
        handleEditProduct($database, $sessionUserId);
        redirect("index.php");
        return;
    }

    if (isset($_POST["deleteProduct"])) {
        handleDeleteProduct($database, $sessionUserId);
        return;
    }

    if (isset($_POST["issue"])) {
        handleIssueProduct($database, $sessionUserId);
        return;
    }

    if (isset($_POST["inventory"])) {
        handleInventory($database, $sessionUserId);
        return;
    }

    if (isset($_POST["deleteInventoryPosition"])) {
        handleDeleteInventoryPosition($database, $sessionUserId);
        return;
    }

    if (isset($_POST["approveInventory"])) {
        handleApproveInventory($database, $sessionUserId);
        return;
    }

    if (isset($_POST["changePassword"])) {
        handleChangeOwnPassword($database, $sessionUserId);
        return;
    }

    if (isset($_POST["adminChangeUserLogin"])) {
        handleAdminChangeUserLogin($database, $sessionUserId);
        return;
    }

    if (isset($_POST["adminChangeUserPassword"])) {
        handleAdminChangeUserPassword($database, $sessionUserId);
        return;
    }

    if (isset($_POST["deactivateUser"])) {
        handleDeactivateUser($database, $sessionUserId);
        return;
    }
}

function addHistory($database, $userId, $productId, $operation, $dataBefore = null, $dataAfter = null)
{
    $sql = "
        INSERT INTO historia_operacji
        (id_uzytkownika, id_produktu, operacja, data_operacji, dane_przed, dane_po)
        VALUES (?, ?, ?, NOW(), ?, ?)
    ";

    $stmt = mysqli_prepare($database, $sql);

    if (!$stmt) {
        throw new Exception("Błąd prepare addHistory: " . mysqli_error($database));
    }

    $beforeJSON = $dataBefore !== null ? json_encode($dataBefore, JSON_UNESCAPED_UNICODE) : null;
    $afterJSON = $dataAfter !== null ? json_encode($dataAfter, JSON_UNESCAPED_UNICODE) : null;

    mysqli_stmt_bind_param($stmt, "iisss", $userId, $productId, $operation, $beforeJSON, $afterJSON);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Błąd execute addHistory: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
}

function getProductById($database, $productId)
{
    $sql = "SELECT * FROM magazyn WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $product;
}

function getUserById($database, $userId)
{
    $sql = "SELECT id, login, imie, nazwisko, status_konta, rola FROM uzytkownicy WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $user;
}

function getOpenInventoryByUserId($database, $userId)
{
    $sql = "SELECT id, numer_inwentaryzacji
            FROM inwentaryzacje
            WHERE id_uzytkownika = ? AND zatwierdzona = 0
            ORDER BY id ASC
            LIMIT 1";

    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $inventory = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $inventory;
}

function generateInventoryNumber($database)
{
    $sql = "SELECT COUNT(*) total FROM inwentaryzacje WHERE YEAR(data_utworzenia) = YEAR(CURDATE())";
    $result = mysqli_query($database, $sql);
    $row = mysqli_fetch_assoc($result);

    $nextNumber = (int) $row["total"] + 1;

    return "INW/" . date("Y") . "/" . date("m") . "/" . date("d") . "/" . str_pad((string) $nextNumber, 3, "0", STR_PAD_LEFT);
}

function createInventory($database, $inventoryNumber, $userId)
{
    $sql = "INSERT INTO inwentaryzacje
            (numer_inwentaryzacji, id_uzytkownika, data_utworzenia, zatwierdzona)
            VALUES (?, ?, NOW(), 0)";

    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "si", $inventoryNumber, $userId);
    mysqli_stmt_execute($stmt);

    $inventoryId = mysqli_insert_id($database);

    mysqli_stmt_close($stmt);

    return $inventoryId;
}

function getInventoryPosition($database, $inventoryId, $productId)
{
    $sql = "SELECT id, stan
            FROM inwentaryzacja_pozycja
            WHERE id_produktu = ? AND id_inwentaryzacji = ?";

    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $productId, $inventoryId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $position = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $position;
}

function handleAddProduct($database, $sessionUserId)
{
    $name = getPostText("productName");
    $category = getPostText("productCategory");
    $quantity = getPostNumber("productQuantity");
    $unit = getPostText("productUnit");
    $adress = getPostText("productAdress");
    $comment = getPostText("productComments");

    if ($name === "" || $quantity < 0) {
        setFlashMessage("Podaj poprawne dane produktu.", "danger");
        return;
    }

    $sql = "
        INSERT INTO magazyn
        (id_uzytkownika, nazwa, kategoria, ilosc, jednostka, lokalizacja, uwagi)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($database, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ississs",
        $sessionUserId,
        $name,
        $category,
        $quantity,
        $unit,
        $adress,
        $comment
    );

    mysqli_stmt_execute($stmt);
    $productId = mysqli_insert_id($database);
    mysqli_stmt_close($stmt);

    $after = [
        "id" => $productId,
        "id_produktu" => $productId,
        "id_uzytkownika" => $sessionUserId,
        "nazwa" => $name,
        "kategoria" => $category,
        "ilosc" => $quantity,
        "jednostka" => $unit,
        "lokalizacja" => $adress,
        "uwagi" => $comment
    ];

    addHistory($database, $sessionUserId, $productId, "dodanie", null, $after);
    setFlashMessage("Produkt został dodany.", "success");
}

function handleEditProduct($database, $sessionUserId)
{
    $id = getPostNumber("productId");
    $name = getPostText("productName");
    $category = getPostText("productCategory");
    $quantity = getPostNumber("productQuantity");
    $unit = getPostText("productUnit");
    $adress = getPostText("productAdress");
    $comment = getPostText("productComments");

    if ($id <= 0) {
        setFlashMessage("Nieprawidłowe ID produktu.", "danger");
        return;
    }

    $before = getProductById($database, $id);

    if (!$before) {
        setFlashMessage("Nie znaleziono produktu.", "danger");
        return;
    }

    $sql = "
        UPDATE magazyn
        SET nazwa = ?,
            kategoria = ?,
            ilosc = ?,
            jednostka = ?,
            lokalizacja = ?,
            uwagi = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssisssi",
        $name,
        $category,
        $quantity,
        $unit,
        $adress,
        $comment,
        $id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $after = getProductById($database, $id);

    addHistory($database, $sessionUserId, $id, "edycja", $before, $after);
    setFlashMessage("Produkt został zaktualizowany.", "success");
}

function handleDeleteProduct($database, $sessionUserId)
{
    if (!canDeleteProducts()) {
        setFlashMessage("Nie masz uprawnień do usuwania produktów.", "danger");
        redirect("index.php");
    }

    $id = getPostNumber("productId");

    if ($id <= 0) {
        setFlashMessage("Nieprawidłowe ID produktu.", "danger");
        redirect("index.php");
    }

    mysqli_begin_transaction($database);

    try {
        $before = getProductById($database, $id);

        if (!$before) {
            throw new Exception("Nie znaleziono produktu.");
        }

        addHistory($database, $sessionUserId, $id, "usunięcie", $before, null);

        $sql = "DELETE FROM magazyn WHERE id = ?";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($database);
        setFlashMessage("Produkt został usunięty.", "success");
    } catch (Throwable $e) {
        mysqli_rollback($database);
        setFlashMessage("Błąd usuwania produktu: " . $e->getMessage(), "danger");
    }

    redirect("index.php");
}

function handleIssueProduct($database, $sessionUserId)
{
    $productId = getPostNumber("productId");
    $quantity = getPostNumber("issueQuantity");
    $comment = getPostText("issueComment");

    if ($productId <= 0 || $quantity <= 0) {
        setFlashMessage("Podaj poprawne dane wydania.", "danger");
        redirect("index.php");
    }

    mysqli_begin_transaction($database);

    try {
        $before = getProductById($database, $productId);

        if (!$before) {
            throw new Exception("Nie znaleziono produktu.");
        }

        $sql = "INSERT INTO wydania (id_uzytkownika, id_produktu, ilosc, powod, nazwa_produktu_snapshot, jednostka_snapshot)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "iiisss",
            $sessionUserId,
            $productId,
            $quantity,
            $comment,
            $before["nazwa"],
            $before["jednostka"]
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $after = getProductById($database, $productId);

        addHistory($database, $sessionUserId, $productId, "wydanie", $before, $after);

        mysqli_commit($database);
        setFlashMessage("Wydanie zostało zapisane.", "success");
    } catch (Throwable $e) {
        mysqli_rollback($database);

        $message = $e->getMessage();

        if (stripos($message, "Nie można wydać więcej niż jest na stanie") !== false) {
            setFlashMessage("Nie można wydać większej ilości niż aktualny stan magazynowy.", "danger");
        } else {
            setFlashMessage("Błąd wydania: " . $message, "danger");
        }
    }

    redirect("index.php");
}

function handleInventory($database, $sessionUserId)
{
    $productId = getPostNumber("inventoryProductId");
    $quantity = getPostNumber("inventoryQuantity");

    if ($productId <= 0 || $quantity < 0) {
        setFlashMessage("Podaj poprawne dane inwentaryzacji.", "danger");
        redirect("index.php");
    }

    mysqli_begin_transaction($database);

    try {
        $inventory = getOpenInventoryByUserId($database, $sessionUserId);

        if ($inventory) {
            $inventoryId = (int) $inventory["id"];
            $inventoryNumber = $inventory["numer_inwentaryzacji"];
        } else {
            $inventoryNumber = generateInventoryNumber($database);
            $inventoryId = createInventory($database, $inventoryNumber, $sessionUserId);
        }

        $existingPosition = getInventoryPosition($database, $inventoryId, $productId);
        $productData = getProductById($database, $productId);

        if (!$productData) {
            throw new Exception("Nie znaleziono produktu.");
        }

        if ($existingPosition) {
            $before = [
                "id_inwentaryzacji" => $inventoryId,
                "numer_inwentaryzacji" => $inventoryNumber,
                "id_produktu" => $productId,
                "stan" => (int) $existingPosition["stan"]
            ];

            $sql = "UPDATE inwentaryzacja_pozycja
                    SET stan = ?, id_uzytkownika = ?, nazwa_produktu_snapshot = ?, jednostka_snapshot = ?
                    WHERE id = ?";

            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iissi",
                $quantity,
                $sessionUserId,
                $productData["nazwa"],
                $productData["jednostka"],
                $existingPosition["id"]
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $after = [
                "id_inwentaryzacji" => $inventoryId,
                "numer_inwentaryzacji" => $inventoryNumber,
                "id_produktu" => $productId,
                "produkt" => $productData["nazwa"] ?? null,
                "stan" => $quantity
            ];

            addHistory($database, $sessionUserId, $productId, "inwentaryzacja", $before, $after);
        } else {
            $sql = "INSERT INTO inwentaryzacja_pozycja
                    (id_inwentaryzacji, id_produktu, id_uzytkownika, stan, zatwierdzona, nazwa_produktu_snapshot, jednostka_snapshot)
                    VALUES (?, ?, ?, ?, 0, ?, ?)";

            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "iiiiss",
                $inventoryId,
                $productId,
                $sessionUserId,
                $quantity,
                $productData["nazwa"],
                $productData["jednostka"]
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $after = [
                "id_inwentaryzacji" => $inventoryId,
                "numer_inwentaryzacji" => $inventoryNumber,
                "id_produktu" => $productId,
                "produkt" => $productData["nazwa"] ?? null,
                "stan" => $quantity
            ];

            addHistory($database, $sessionUserId, $productId, "inwentaryzacja", null, $after);
        }

        mysqli_commit($database);
        setFlashMessage("Pozycja została dodana do inwentaryzacji {$inventoryNumber}.", "success");
    } catch (Throwable $e) {
        mysqli_rollback($database);
        setFlashMessage("Błąd podczas zapisu inwentaryzacji: " . $e->getMessage(), "danger");
    }

    redirect("index.php");
}

function handleDeleteInventoryPosition($database, $sessionUserId)
{
    $positionId = getPostNumber("inventoryPositionId");

    if ($positionId <= 0) {
        setFlashMessage("Nieprawidłowe ID pozycji inwentaryzacji.", "danger");
        redirect("index.php");
    }

    mysqli_begin_transaction($database);

    try {
        $sql = "
            SELECT 
                ip.id,
                ip.id_inwentaryzacji,
                ip.id_produktu,
                ip.stan,
                ip.zatwierdzona,
                i.numer_inwentaryzacji,
                i.id_uzytkownika,
                i.zatwierdzona AS inwentaryzacja_zatwierdzona,
                ip.nazwa_produktu_snapshot,
                ip.jednostka_snapshot
            FROM inwentaryzacja_pozycja ip
            INNER JOIN inwentaryzacje i
                ON i.id = ip.id_inwentaryzacji
            WHERE ip.id = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $positionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $position = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$position) {
            throw new Exception("Nie znaleziono pozycji inwentaryzacji.");
        }

        if ((int) $position["id_uzytkownika"] !== (int) $sessionUserId) {
            throw new Exception("Nie możesz usunąć pozycji z cudzej inwentaryzacji.");
        }

        if ((int) $position["inwentaryzacja_zatwierdzona"] === 1 || (int) $position["zatwierdzona"] === 1) {
            throw new Exception("Nie można usunąć pozycji z zatwierdzonej inwentaryzacji.");
        }

        $before = [
            "id" => (int) $position["id"],
            "id_inwentaryzacji" => (int) $position["id_inwentaryzacji"],
            "numer_inwentaryzacji" => $position["numer_inwentaryzacji"],
            "id_produktu" => $position["id_produktu"] !== null ? (int) $position["id_produktu"] : null,
            "nazwa_produktu" => $position["nazwa_produktu_snapshot"],
            "jednostka" => $position["jednostka_snapshot"],
            "stan" => (int) $position["stan"]
        ];

        $sql = "DELETE FROM inwentaryzacja_pozycja WHERE id = ?";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $positionId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        addHistory(
            $database,
            $sessionUserId,
            $position["id_produktu"] !== null ? (int) $position["id_produktu"] : null,
            "inwentaryzacja",
            $before,
            null
        );

        mysqli_commit($database);
        setFlashMessage("Pozycja została usunięta z inwentaryzacji.", "success");
    } catch (Throwable $e) {
        mysqli_rollback($database);
        setFlashMessage("Błąd usuwania pozycji inwentaryzacji: " . $e->getMessage(), "danger");
    }

    redirect("index.php");
}

function handleApproveInventory($database, $sessionUserId)
{
    $inventoryId = getPostNumber("inventoryId");

    if ($inventoryId <= 0) {
        setFlashMessage("Nie przekazano ID inwentaryzacji.", "danger");
        redirect("index.php");
    }

    mysqli_begin_transaction($database);

    try {
        $sql = "SELECT id, numer_inwentaryzacji, zatwierdzona
                FROM inwentaryzacje
                WHERE id = ? AND id_uzytkownika = ?";

        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $inventoryId, $sessionUserId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $inventory = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$inventory) {
            throw new Exception("Nie znaleziono inwentaryzacji.");
        }

        if ((int) $inventory["zatwierdzona"] === 1) {
            throw new Exception("Ta inwentaryzacja została już zatwierdzona.");
        }

        $sql = "SELECT 
                    ip.id,
                    ip.id_produktu,
                    ip.stan,
                    m.ilosc aktualny_stan_magazynu
                FROM inwentaryzacja_pozycja ip
                INNER JOIN magazyn m ON m.id = ip.id_produktu
                WHERE ip.id_inwentaryzacji = ?
                  AND ip.id_produktu IS NOT NULL";

        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $inventoryId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $positions = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $positions[] = $row;
        }

        mysqli_stmt_close($stmt);

        if (!$positions) {
            throw new Exception("Brak pozycji do zatwierdzenia.");
        }

        foreach ($positions as $position) {
            $difference = (int) $position["stan"] - (int) $position["aktualny_stan_magazynu"];

            $sql = "UPDATE inwentaryzacja_pozycja
                    SET roznica = ?, zatwierdzona = 1
                    WHERE id = ?";

            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $difference, $position["id"]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $sql = "UPDATE magazyn
                    SET ilosc = ?
                    WHERE id = ?";

            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $position["stan"], $position["id_produktu"]);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $sql = "UPDATE inwentaryzacje
                SET zatwierdzona = 1,
                    data_zatwierdzenia = NOW()
                WHERE id = ?";

        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $inventoryId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($database);
        setFlashMessage("Inwentaryzacja {$inventory["numer_inwentaryzacji"]} została zatwierdzona.", "success");
    } catch (Throwable $e) {
        mysqli_rollback($database);
        setFlashMessage("Błąd zatwierdzania inwentaryzacji: " . $e->getMessage(), "danger");
    }

    redirect("index.php");
}

function handleChangeOwnPassword($database, $sessionUserId)
{
    $user = getUserById($database, $sessionUserId);

    if (!$user || $user["status_konta"] !== "aktywne") {
        setFlashMessage("Nie można zmienić hasła dla nieaktywnego konta.", "danger");
        redirect("index.php");
    }

    $newPassword = getPostText("newPassword");
    $confirmPassword = getPostText("confirmPassword");

    if ($newPassword === "" || $newPassword !== $confirmPassword) {
        setFlashMessage("Hasła są puste albo nie są zgodne.", "danger");
        redirect("index.php");
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE uzytkownicy SET haslo = ? WHERE id = ?";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "si", $hash, $sessionUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setFlashMessage("Hasło zostało zmienione.", "success");
    redirect("index.php");
}

function handleAdminChangeUserLogin($database, $sessionUserId)
{
    if (!canManageUsers()) {
        setFlashMessage("Nie masz uprawnień do tej operacji.", "danger");
        redirect("index.php");
    }

    $targetUserId = getPostNumber("targetUserId");
    $newLogin = getPostText("newLogin");
    $targetUser = getUserById($database, $targetUserId);

    if (!$targetUser) {
        setFlashMessage("Nie znaleziono użytkownika.", "danger");
        redirect("index.php");
    }

    if (!canChangeTargetLogin($targetUser)) {
        setFlashMessage("Nie możesz zmienić loginu temu użytkownikowi.", "danger");
        redirect("index.php");
    }

    if ($newLogin === "") {
        setFlashMessage("Nowy login nie może być pusty.", "danger");
        redirect("index.php");
    }

    $checkSql = "SELECT id FROM uzytkownicy WHERE login = ? AND id <> ? LIMIT 1";
    $checkStmt = mysqli_prepare($database, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "si", $newLogin, $targetUserId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $exists = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    if ($exists) {
        setFlashMessage("Taki login już istnieje.", "danger");
        redirect("index.php");
    }

    $sql = "UPDATE uzytkownicy SET login = ? WHERE id = ?";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "si", $newLogin, $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setFlashMessage("Login użytkownika został zmieniony.", "success");
    redirect("index.php");
}

function handleAdminChangeUserPassword($database, $sessionUserId)
{
    if (!canManageUsers()) {
        setFlashMessage("Nie masz uprawnień do tej operacji.", "danger");
        redirect("index.php");
    }

    $targetUserId = getPostNumber("targetUserId");
    $newPassword = getPostText("newPassword");
    $confirmPassword = getPostText("confirmPassword");
    $targetUser = getUserById($database, $targetUserId);

    if (!$targetUser) {
        setFlashMessage("Nie znaleziono użytkownika.", "danger");
        redirect("index.php");
    }

    if (!canChangeTargetPassword($targetUser)) {
        setFlashMessage("Nie możesz zmienić hasła temu użytkownikowi.", "danger");
        redirect("index.php");
    }

    if ($newPassword === "" || $newPassword !== $confirmPassword) {
        setFlashMessage("Hasła są puste albo nie są zgodne.", "danger");
        redirect("index.php");
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE uzytkownicy SET haslo = ? WHERE id = ?";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "si", $hash, $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setFlashMessage("Hasło użytkownika zostało zmienione.", "success");
    redirect("index.php");
}

function handleDeactivateUser($database, $sessionUserId)
{
    if (!canManageUsers()) {
        setFlashMessage("Nie masz uprawnień do tej operacji.", "danger");
        redirect("index.php");
    }

    $targetUserId = getPostNumber("targetUserId");
    $targetUser = getUserById($database, $targetUserId);

    if (!$targetUser) {
        setFlashMessage("Nie znaleziono użytkownika.", "danger");
        redirect("index.php");
    }

    if (!canDeactivateTargetUser($targetUser)) {
        setFlashMessage("Nie możesz dezaktywować tego użytkownika.", "danger");
        redirect("index.php");
    }

    $sql = "UPDATE uzytkownicy SET status_konta = 'nieaktywne' WHERE id = ?";
    $stmt = mysqli_prepare($database, $sql);
    mysqli_stmt_bind_param($stmt, "i", $targetUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    setFlashMessage("Konto użytkownika zostało dezaktywowane.", "success");
    redirect("index.php");
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Magazynek IT</title>
  <link rel="stylesheet" href="./assets/bootstrap/css/bootstrap.min.css">
  <link rel="shortcut icon" href="./images/favico.png" type="image/x-icon">
  <style>
    :root {
      --app-header-height: 76px;
    }

    html, body {
      height: 100%;
      overflow: hidden;
    }

    body {
      margin: 0;
    }

    header.app-header {
      z-index: 1040;
    }

    aside.app-sidebar {
      width: 280px;
      position: sticky;
      top: 0;
      height: calc(100vh - var(--app-header-height));
      overflow-y: auto;
      flex-shrink: 0;
    }

    #data {
  overflow: auto;
  height: calc(100vh - var(--app-header-height));
}

#data .table-responsive {
  overflow: visible;
}

#data table {
  border-collapse: separate;
  border-spacing: 0;
}

#data .table thead th {
  position: sticky;
  top: -16px;
  z-index: 20;
  background: #f8f9fa;
  box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
}

    .header-title-wrap,
    .header-actions-wrap,
    .header-user-wrap {
      min-width: 220px;
    }

    .header-user-wrap {
      text-align: center;
      font-weight: 600;
    }

    .modal-draggable .modal-header {
      cursor: move;
      user-select: none;
    }

    .modal-draggable .modal-dialog {
      margin: 0;
      position: fixed;
      top: 10vh;
      left: calc(50% - 250px);
      width: min(500px, calc(100vw - 24px));
      max-width: 500px;
    }

    .modal-draggable .modal-content {
      max-height: 85vh;
      overflow: hidden;
    }

    .modal-draggable .modal-body {
      overflow-y: auto;
      max-height: calc(85vh - 70px);
    }

    @media (max-width: 991.98px) {
      aside.app-sidebar {
        width: 220px;
      }
    }
  </style>
</head>
<body data-user-fullname="<?= escapeHtml($currentUserFullName) ?>">

  <header class="app-header bg-warning border-bottom top-0 position-sticky">
    <div class="container-fluid py-3">
      <div id="headerContent" class="d-flex flex-wrap gap-2 align-items-center justify-content-between"></div>
    </div>
  </header>

  <?php if (!empty($_SESSION["message"])): ?>
    <div id="flashMessageWrapper" class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 2000; width:min(700px, calc(100vw - 24px));">
      <div id="flashMessageAlert" class="alert alert-<?= htmlspecialchars($_SESSION["message_type"] ?? "info") ?> alert-dismissible fade show m-0" role="alert">
        <?= htmlspecialchars($_SESSION["message"]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
      </div>
    </div>
    <?php unset($_SESSION["message"], $_SESSION["message_type"]); ?>
  <?php endif; ?>

  <main class="d-flex overflow-hidden">
    <aside class="app-sidebar bg-warning border-end p-2">
      <div class="d-grid gap-2">
        <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="magazyn">Magazyn</button>
        <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="wydania">Wydania</button>
        <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="inwentaryzacje">Inwentaryzacje</button>
        <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="ustawienia_konta">Ustawienia konta</button>

        <?php if ($_SESSION["rola"] === "admin"): ?>
          <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="uzytkownicy">Użytkownicy</button>
          <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="historia_operacji">Historia operacji</button>
        <?php endif; ?>

        <a href="logout.php" class="btn btn-dark text-start fw-semibold mt-3">Wyloguj</a>
      </div>
    </aside>

    <section id="data" class="flex-grow-1 p-3 bg-light"></section>
  </main>

  <aside>
    <div class="modal fade modal-draggable" id="formModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="formModalTitle">Formularz</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
          </div>
          <div class="modal-body" id="formContainer"></div>
        </div>
      </div>
    </div>
  </aside>

  <script src="./assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="script.js" defer></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const alertEl = document.getElementById("flashMessageAlert");

      if (alertEl) {
        setTimeout(() => {
          const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
          bsAlert.close();
        }, 1500);
      }

      document.addEventListener("closed.bs.alert", function (e) {
        const wrapper = e.target.closest("#flashMessageWrapper");
        if (wrapper) {
          wrapper.remove();
        }
      });
    });
  </script>
</body>
</html>