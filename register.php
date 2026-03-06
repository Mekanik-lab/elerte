<?php
session_start();
include "connection.php";
include "functions.php";

if (!empty($_SESSION["login"]) && !empty($_SESSION["userId"])) {
    header('Location: index.php');
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = getPostText('login');
    $password = getPostText('password');
    $repeatPassword = getPostText('repeatPassword');
    $imie = getPostText('imie');
    $nazwisko = getPostText('nazwisko');

    if (empty($login) || empty($password) || empty($repeatPassword) empty($imie) || empty($nazwisko)) {
        $error = "Wszystkie pola są wymagane.";
    } else if ($password !== $repeatPassword) {
        $error = "Hasła nie są takie same.";
    } else {
        $sqlCheck = "SELECT id FROM uzytkownicy WHERE login = ? LIMIT 1";
        $stmtCheck = mysqli_prepare($database, $sqlCheck);
        mysqli_stmt_bind_param($stmtCheck, "s", $login);
        mysqli_stmt_execute($stmtCheck);
        $resultCheck = mysqli_stmt_get_result($stmtCheck);

        if (mysqli_fetch_assoc($resultCheck)) {
            $error = "Taki login już istnieje.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sqlInsert = "INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, status_konta, rola)
                          VALUES (?, ?, ?, ?, 'aktywne', 'user')";
            $stmtInsert = mysqli_prepare($database, $sqlInsert);
            mysqli_stmt_bind_param($stmtInsert, "ssss", $login, $hashedPassword, $imie, $nazwisko);
            mysqli_stmt_execute($stmtInsert);

            $_SESSION['login'] = $login;
            $_SESSION['userId'] = mysqli_insert_id($database);
            $_SESSION['rola'] = 'user';

            mysqli_stmt_close($stmtInsert);
            mysqli_stmt_close($stmtCheck);
            mysqli_close($database);

            header('Location: index.php');
            exit;
        }

        mysqli_stmt_close($stmtCheck);
    }
}

mysqli_close($database);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - Magazynek IT</title>
    <link rel="stylesheet" href="./assets/bootstrap/css/bootstrap.min.css">
    <link rel="shortcut icon" href="./images/favico.png" type="image/x-icon">
</head>
<body class="bg-light min-vh-100 d-flex flex-column">

<header class="bg-warning border-bottom shadow-sm">
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
            <h1 class="h4 m-0 fw-bold text-dark">Magazynek IT</h1>
            <span class="badge text-bg-dark px-3 py-2">Rejestracja</span>
        </div>
    </div>
</header>

<main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow border-0" style="max-width: 420px; width: 100%;">
        <div class="card-header bg-warning text-dark fw-semibold py-3">
            Zarejestruj się do systemu
        </div>
        <div class="card-body p-4">
            <form method="POST" action="register.php" class="d-grid gap-3">
                <div>
                    <label for="login" class="form-label fw-semibold">Login</label>
                    <input type="text" id="login" name="login" class="form-control" required>
                </div>

                <div>
                    <label for="imie" class="form-label fw-semibold">Imię</label>
                    <input type="text" id="imie" name="imie" class="form-control" required>
                </div>

                <div>
                    <label for="nazwisko" class="form-label fw-semibold">Nazwisko</label>
                    <input type="text" id="nazwisko" name="nazwisko" class="form-control" required>
                </div>

                <div>
                    <label for="password" class="form-label fw-semibold">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div>
                    <label for="repeatPassword" class="form-label fw-semibold">Powtórz hasło</label>
                    <input type="password" id="repeatPassword" name="repeatPassword" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-warning fw-semibold">
                    Zarejestruj się
                </button>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-0"><?= htmlspecialcharEscapeFunction($error) ?></div>
                <?php endif; ?>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted">Masz już konto?</span>
                <a href="login.php" class="fw-semibold text-decoration-none ms-1">Zaloguj się</a>
            </div>
        </div>
    </div>
</main>

<script src="./assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>