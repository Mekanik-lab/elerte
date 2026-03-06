<?php
session_start();
include "connection.php";
include "functions.php";

if (!empty($_SESSION['login']) && !empty($_SESSION['userId'])) {
    header('Location: index.php');
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = getPostText('login');
    $password = getPostText('password');

    if ($login === '' || $password === '') {
        $error = "Wszystkie pola są wymagane.";
    } else {
        $sql = "SELECT id, login, haslo, status_konta, rola
                FROM uzytkownicy
                WHERE login = ?
                LIMIT 1";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "s", $login);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            if ($user['status_konta'] !== 'aktywne') {
                $error = "To konto jest dezaktywowane.";
            } elseif (password_verify($password, $user['haslo'])) {
                $_SESSION["login"] = $user["login"];
                $_SESSION["userId"] = (int)$user["id"];
                $_SESSION["rola"] = $user["rola"];

                mysqli_stmt_close($stmt);
                mysqli_close($database);

                header('Location: index.php');
                exit;
            } else {
                $error = "Nieprawidłowy login lub hasło.";
            }
        } else {
            $error = "Nieprawidłowy login lub hasło.";
        }

        mysqli_stmt_close($stmt);
    }
}

mysqli_close($database);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Magazynek IT</title>
    <link rel="stylesheet" href="./assets/bootstrap/css/bootstrap.min.css">
    <link rel="shortcut icon" href="./images/favico.png" type="image/x-icon">
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
                    <input type="text" id="login" name="login" class="form-control" placeholder="Wpisz login" required>
                </div>

                <div>
                    <label for="password" class="form-label fw-semibold">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Wpisz hasło" required>
                </div>

                <button type="submit" class="btn btn-warning fw-semibold">
                    Zaloguj się
                </button>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-0"><?= htmlspecialcharEscapeFunction($error) ?></div>
                <?php endif; ?>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted">Nie masz konta?</span>
                <a href="register.php" class="fw-semibold text-decoration-none ms-1">Zarejestruj się</a>
            </div>
        </div>
    </div>
</main>

<script src="./assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>