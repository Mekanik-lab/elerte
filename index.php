<?php
    $database = mysqli_connect('localhost', 'root', '', 'magazyn');
    if (!$database) {
        die("Błąd połączenia. " . mysqli_connect_error());
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
    <table>
        <?php
            if($_SERVER['REQUEST_METHOD'] === "POST") {
                if ($_POST['addProduct'] == "addProduct") {
                    $stmt = mysqli_prepare($database, "INSERT INTO magazyn (nazwa, kategoria, ilosc, lokalizacja, uwagi) VALUES (?, ?, ?, ?, ?);");
                    $name = $_POST["productName"];
                    $category = $_POST["productCategory"];
                    $quantity = $_POST["productQuantity"];
                    $adress = $_POST["productAdress"];
                    $comments = $_POST["productComments"];
                    mysqli_stmt_bind_param($stmt, "ssiss", $name, $category, $quantity, $adress, $comments);
                    mysqli_stmt_execute($stmt);
                }
            }
        ?>
    </table>
    </section>
    </main>
    <aside id="formContainer">
    </aside>
    <script src="script.js" defer></script>
</body>
</html>