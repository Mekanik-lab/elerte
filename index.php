<?php
$database = mysqli_connect('localhost', 'root', '', 'magazyn');
if (!$database) {
    die("Błąd połączenia. " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["addProduct"]) && $_POST["addProduct"] === "addProduct") {
        $requiredFields = ["productName", "productCategory", "productQuantity", "productAdress", "productComments"];
        $allFilled = true;

        foreach ($requiredFields as $field) {
            if (empty(trim($_POST[$field] ?? ""))) {
                $allFilled = false;
                break;
            }
        }

        if ($allFilled) {
            $name = $_POST["productName"];
            $category = $_POST["productCategory"];
            $quantity = $_POST["productQuantity"];
            $adress = $_POST["productAdress"];
            $comment = $_POST["productComments"];

            $sql = "INSERT INTO magazyn (nazwa, kategoria, ilosc, lokalizacja, uwagi) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ssiss", $name, $category, $quantity, $adress, $comment);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: index.php");
            exit;
        }

    } else if (isset($_POST["editProduct"]) && $_POST["editProduct"] === "editProduct") {
        $requiredFields = ["productId", "productName", "productCategory", "productQuantity", "productAdress", "productComments"];
        $allFilled = true;

        foreach ($requiredFields as $field) {
            if (empty(trim($_POST[$field] ?? ""))) {
                $allFilled = false;
                break;
            }
        }

        if ($allFilled) {
            $id = $_POST["productId"];
            $name = $_POST["productName"];
            $category = $_POST["productCategory"];
            $quantity = $_POST["productQuantity"];
            $adress = $_POST["productAdress"];
            $comment = $_POST["productComments"];

            $sql = "UPDATE magazyn SET nazwa = ?, kategoria = ?, ilosc = ?, lokalizacja = ?, uwagi = ? WHERE id = ?";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ssissi", $name, $category, $quantity, $adress, $comment, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: index.php");
            exit;
        }

    } else if (isset($_POST["deleteProduct"]) && $_POST["deleteProduct"] === "deleteProduct") {
        $id = $_POST["productId"] ?? null;

        if (!empty($id)) {
            $sql = "DELETE FROM magazyn WHERE id = ?";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: index.php");
            exit;
        }
    } else if (isset($_POST["issue"]) && $_POST["issue"] === "issue") {
        $requiredFields = ["employee", "positionId", "issueQuantity", "issueComment"];
        $allFilled = true;

        foreach ($requiredFields as $field) {
            if (empty(trim($_POST[$field] ?? ""))) {
                $allFilled = false;
                break;
            }
        }

        if ($allFilled) {
            $employee = $_POST["employee"];
            $positionId = $_POST["positionId"];
            $issueQuantity = $_POST["issueQuantity"];
            $issueComment = $_POST["issueComment"];

            $sql = "INSERT INTO wydania (pracownik, id_pozycji, ilosc, powod) VALUES (?, ?, ?, ?);";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "siis", $employee, $positionId, $issueQuantity, $issueComment);
            try {
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: index.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                exit(header("Location: index.php"));
            }
        }
    } else if (isset($_POST["inventory"]) && $_POST["inventory"] === "inventory") {
        $requiredFields = ["inventoryEmployee", "inventoryProductId", "inventoryQuantity"];
        $allFilled = true;

        foreach ($requiredFields as $field) {
            if (empty(trim($_POST[$field] ?? ""))) {
                $allFilled = false;
                break;
            }
        }

        if ($allFilled) {
            $employee = $_POST["inventoryEmployee"];
            $productId = $_POST["inventoryProductId"];
            $quantity = $_POST["inventoryQuantity"];

            $sql = "INSERT INTO inwentaryzacja_sesja (data_sesji, stworzone_przez) VALUES (NOW(), ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "s", $employee);
            mysqli_stmt_execute($stmt);
            $sessionId = mysqli_insert_id($database);
            mysqli_stmt_close($stmt);

        
            $sql = "INSERT INTO inwentaryzacja (id_sesji, id_produktu, stan) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $sessionId, $productId, $quantity);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: index.php");
            exit;
        }
    } 
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magazynek IT</title>
    <link rel="shortcut icon" href="./images/favico.png" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header></header>
    <main>
        <aside>
            <section class="tab">
                <button onclick="loadSection('magazyn')" id="magazineButton">Magazyn</button>
            </section>
            <section class="tab">
                <button onclick="loadSection('wydania')" id="issueButton">Wydania</button>
            </section>
            <section class="tab">
                <button onclick="loadSection('inwentaryzacja')" id="inventoryButton">Inwentaryzacja</button>
            </section>
            <section class="tab">
                <button onclick="loadSection('inwentaryzacja_sesja')" id="inventorySessionButton">Inwentaryzacja sesja</button>
            </section>
        </aside>
        <section id="data"></section>
    </main>
    <aside id="formContainer"></aside>
    <script src="script.js" defer></script>
</body>
</html>