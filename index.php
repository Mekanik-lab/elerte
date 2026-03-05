<?php
    $database = mysqli_connect('localhost', 'root', '', 'magazyn');
    if (!$database) {
        die("Błąd połączenia. " . mysqli_connect_error());
    }

    if($_SERVER["REQUEST_METHOD"] === "POST") {
        if(isset($_POST["addProduct"]) && $_POST["addProduct"] === "addProduct")  {
            $requiredFields = ['productName', 'productCategory', 'productQuantity', 'productAdress', 'productComments'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                
            }
        } else {
            $name = $_POST["productName"];
            $category = $_POST["productCategory"];
            $quantity = $_POST["productQuantity"];
            $adress = $_POST["productAdress"];
            $comment = $_POST["productComments"];

            $sql = "INSERT INTO magazyn (nazwa, kategoria, ilosc, lokalizacja, uwagi) VALUES (?, ?, ?, ?, ?);";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_bind_param("ssiss", $name, $category, $quantity, $adress, $comment);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
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
    <header>
    </header>
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
    </aside>
    <section id="data">
    </section>
    </main>
    <aside id="formContainer">
    </aside>
    <script src="script.js" defer></script>
</body>
</html>