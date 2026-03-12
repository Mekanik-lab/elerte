<?php
session_start();
include "connection.php";
include "functions.php";

redirectIfLoggedIn();

$error = "";

if (isPostRequest()) {
    $formData = getLoginFormData();
    $error = validateLoginFormData($formData);

    if ($error === "") {
        $user = findUserByLogin($database, $formData["login"]);
        $error = tryLoginUser($formData, $user);
    }
}

mysqli_close($database);

function redirectIfLoggedIn()
{
    if (!empty($_SESSION["login"]) && !empty($_SESSION["userId"])) {
        redirect("index.php");
    }
}

function isPostRequest()
{
    return $_SERVER["REQUEST_METHOD"] === "POST";
}

function redirect($url)
{
    header("Location: " . $url);
    exit;
}

function getLoginFormData()
{
    return [
        "login" => getPostText("login"),
        "password" => getPostText("password"),
    ];
}

function validateLoginFormData($data)
{
    if ($data["login"] === "" || $data["password"] === "") {
        return "Wszystkie pola są wymagane.";
    }

    return "";
}

function findUserByLogin($database, $login)
{
    $sql = "SELECT id, haslo, rola, status_konta FROM uzytkownicy WHERE login = ? LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);

    mysqli_stmt_bind_param($stmt, "s", $login);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $user;
}

function tryLoginUser($formData, $user)
{
    if (!$user || !password_verify($formData["password"], $user["haslo"])) {
        return "Niepoprawny login lub hasło.";
    }

    if ($user["status_konta"] === "nieaktywne") {
        return "Konto nieaktywne.";
    }

    loginUser($formData["login"], $user);
    redirect("index.php");
}

function loginUser($login, $user)
{
    $_SESSION["login"] = $login;
    $_SESSION["userId"] = $user["id"];
    $_SESSION["rola"] = $user["rola"];
    $_SESSION["inventorySession"] = null;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Magazynek IT</title>
    <link rel="stylesheet" href="./assets/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light min-vh-100 d-flex flex-column">
<header class="bg-warning border-bottom shadow-sm">
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
            <h1 class="h4 m-0 fw-bold text-dark">Magazynek IT</h1>
            <span class="badge text-bg-dark px-3 py-2">Logowanie</span>
        </div>
    </div>
</header>
<main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow border-0" style="max-width: 420px; width: 100%;">
        <div class="card-header bg-warning text-dark fw-semibold py-3">
            Zaloguj się do systemu
        </div>
        <div class="card-body p-4">
            <form method="POST" action="login.php" class="d-grid gap-3">
                <div>
                    <label for="login" class="form-label fw-semibold">Login</label>
                    <input type="text" id="login" name="login" class="form-control" required>
                </div>
                <div>
                    <label for="password" class="form-label fw-semibold">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning fw-semibold">Zaloguj się</button>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-0"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</main>
<script src="./assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>