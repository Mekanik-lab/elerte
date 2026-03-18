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
        if ($formData["rfid"] !== "") {
            $user = findUserByRfid($database, $formData["rfid"]);
            $error = tryLoginUserByRfid($user);
        } else {
            $user = findUserByLogin($database, $formData["login"]);
            $error = tryLoginUserByPassword($formData, $user);
        }
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
        "rfid" => getPostText("rfid"),
    ];
}

function validateLoginFormData($data)
{
    if ($data["rfid"] !== "") {
        return "";
    }

    if ($data["login"] === "" || $data["password"] === "") {
        return "Wszystkie pola są wymagane.";
    }

    return "";
}

function findUserByLogin($database, $login)
{
    $sql = "SELECT id, login, haslo, rola, status_konta, imie, nazwisko, rfid
            FROM uzytkownicy
            WHERE login = ?
            LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);

    mysqli_stmt_bind_param($stmt, "s", $login);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $user;
}

function findUserByRfid($database, $rfid)
{
    $sql = "SELECT id, login, haslo, rola, status_konta, imie, nazwisko, rfid
            FROM uzytkownicy
            WHERE rfid = ?
            LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);

    mysqli_stmt_bind_param($stmt, "s", $rfid);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $user;
}

function tryLoginUserByPassword($formData, $user)
{
    if (!$user || !password_verify($formData["password"], $user["haslo"])) {
        return "Niepoprawny login lub hasło.";
    }

    if ($user["status_konta"] === "nieaktywne") {
        return "Konto nieaktywne.";
    }

    loginUser($user);
    redirect("index.php");
}

function tryLoginUserByRfid($user)
{
    if (!$user) {
        return "Nieznana karta RFID.";
    }

    if ($user["status_konta"] === "nieaktywne") {
        return "Konto nieaktywne.";
    }

    loginUser($user);
    redirect("index.php");
}

function loginUser($user)
{
    $_SESSION["login"] = $user["login"];
    $_SESSION["userId"] = $user["id"];
    $_SESSION["rola"] = $user["rola"];
    $_SESSION["imie"] = $user["imie"] ?? "";
    $_SESSION["nazwisko"] = $user["nazwisko"] ?? "";
    $_SESSION["inventorySession"] = null;
    $_SESSION["last_activity"] = time();
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
            <form method="POST" action="login.php" class="d-grid gap-3" id="loginForm">
                <input type="hidden" id="rfid" name="rfid">

                <div>
                    <label for="login" class="form-label fw-semibold">Login</label>
                    <input type="text" id="login" name="login" class="form-control" autocomplete="username">
                </div>
                <div>
                    <label for="password" class="form-label fw-semibold">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="current-password">
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
<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    const rfidInput = document.getElementById("rfid");
    const loginInput = document.getElementById("login");
    const passwordInput = document.getElementById("password");

    let buffer = "";
    let timer = null;

    document.addEventListener("keydown", (e) => {
        const userIsTypingManually =
            document.activeElement === loginInput ||
            document.activeElement === passwordInput;

        if (userIsTypingManually) {
            return;
        }

        if (e.key === "Enter") {
            const code = buffer.trim();

            if (code !== "") {
                rfidInput.value = code;
                form.submit();
            }

            buffer = "";
            clearTimeout(timer);
            return;
        }

        if (e.key.length === 1) {
            buffer += e.key;
        }

        clearTimeout(timer);
        timer = setTimeout(() => {
            buffer = "";
        }, 100);
    });
});
</script>
</body>
</html>