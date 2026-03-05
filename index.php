<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = mysqli_connect('localhost', 'root', '', 'magazyn');
mysqli_set_charset($database, 'utf8mb4');

if (!$database) {
    die("Błąd połączenia. " . mysqli_connect_error());
}

function getPostText(string $fieldName): string {
    return trim((string)($_POST[$fieldName] ?? ""));
}
function getPostNumber(string $fieldName): int {
    return (int)($_POST[$fieldName] ?? 0);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ADD PRODUCT
    if (isset($_POST["addProduct"]) && $_POST["addProduct"] === "addProduct") {
        $name = getPostText("productName");
        $category = getPostText("productCategory");
        $quantity = getPostNumber("productQuantity");
        $adress = getPostText("productAdress");
        $comment = getPostText("productComments");

        if ($name !== "" && $quantity >= 0) {
            $sql = "INSERT INTO magazyn (nazwa, kategoria, ilosc, lokalizacja, uwagi) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ssiss", $name, $category, $quantity, $adress, $comment);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        header("Location: index.php");
        exit;
    }

    // EDIT PRODUCT
    if (isset($_POST["editProduct"]) && $_POST["editProduct"] === "editProduct") {
        $id = getPostNumber("productId");
        $name = getPostText("productName");
        $category = getPostText("productCategory");
        $quantity = getPostNumber("productQuantity");
        $adress = getPostText("productAdress");
        $comment = getPostText("productComments");

        if ($id > 0 && $name !== "" && $quantity >= 0) {
            $sql = "UPDATE magazyn
                    SET nazwa = ?, kategoria = ?, ilosc = ?, lokalizacja = ?, uwagi = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "ssissi", $name, $category, $quantity, $adress, $comment, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        header("Location: index.php");
        exit;
    }

    // DELETE PRODUCT (fizycznie)
    if (isset($_POST["deleteProduct"]) && $_POST["deleteProduct"] === "deleteProduct") {
        $id = getPostNumber("productId");

        if ($id > 0) {
            $sql = "DELETE FROM magazyn WHERE id = ?";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);

            try {
                mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                // Przy CASCADE raczej nie poleci, ale zostawiamy na przyszłość
            } finally {
                mysqli_stmt_close($stmt);
            }
        }

        header("Location: index.php");
        exit;
    }

    // ISSUE (wydanie)
    if (isset($_POST["issue"]) && $_POST["issue"] === "issue") {
        $employee = getPostText("employee");
        $positionId = getPostNumber("positionId");
        $issueQuantity = getPostNumber("issueQuantity");
        $issueComment = getPostText("issueComment");

        if ($employee !== "" && $positionId > 0 && $issueQuantity > 0) {
            $sql = "INSERT INTO wydania (pracownik, id_pozycji, ilosc, powod) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "siis", $employee, $positionId, $issueQuantity, $issueComment);

            try {
                mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                // trigger może zablokować (za mało na stanie)
            } finally {
                mysqli_stmt_close($stmt);
            }
        }

        header("Location: index.php");
        exit;
    }

    // INVENTORY (sesja + wpis) => stan magazynu nadpisuje trigger
    if (isset($_POST["inventory"]) && $_POST["inventory"] === "inventory") {
        $employee = getPostText("inventoryEmployee");
        $productId = getPostNumber("inventoryProductId");
        $quantity = getPostNumber("inventoryQuantity");

        if ($employee !== "" && $productId > 0 && $quantity >= 0) {
            $sql = "INSERT INTO inwentaryzacja_sesja (data_sesji, stworzone_przez) VALUES (NOW(), ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "s", $employee);
            mysqli_stmt_execute($stmt);
            $sessionId = mysqli_insert_id($database);
            mysqli_stmt_close($stmt);

            $sql = "INSERT INTO inwentaryzacja (id_sesji, id_produktu, stan) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($database, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $sessionId, $productId, $quantity);

            try {
                mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                // np. brak produktu
            } finally {
                mysqli_stmt_close($stmt);
            }
        }

        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Magazynek IT</title>
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
</head>
<body class="min-vh-100 d-flex flex-column">

<header class="bg-warning border-bottom">
  <div class="container-fluid py-3">
    <div id="headerContent" class="d-flex flex-wrap gap-2 align-items-center justify-content-between"></div>
  </div>
</header>

<main class="flex-grow-1 d-flex overflow-hidden">
  <aside class="bg-warning border-end p-2" style="width: 280px;">
    <div class="d-grid gap-2">
      <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="magazyn" id="magazineButton">Magazyn</button>
      <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="wydania" id="issueButton">Wydania</button>
      <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="inwentaryzacja" id="inventoryButton">Inwentaryzacja</button>
      <button class="btn btn-outline-light text-start fw-semibold tab-btn" data-section="inwentaryzacja_sesja" id="inventorySessionButton">Inwentaryzacja sesja</button>
    </div>
  </aside>

  <section id="data" class="flex-grow-1 overflow-auto p-3 bg-light"></section>
</main>

<aside>
  <div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="script.js" defer></script>
</body>
</html>