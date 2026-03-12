<?php
session_start();
include "connection.php";
include "functions.php";

requireAdmin();

$error = "";

if (isPostRequest()) {
    $formData = getRegisterFormData();
    $error = validateRegisterFormData($formData);

    if ($error === "" && loginExists($database, $formData["login"])) {
        $error = "Taki login już istnieje.";
    }

    if ($error === "") {
        createUser($database, $formData);
        mysqli_close($database);
        header("Location: index.php");
        exit;
    }
}

mysqli_close($database);

function requireAdmin()
{
    if (empty($_SESSION["rola"]) || $_SESSION["rola"] !== "admin") {
        header("Location: login.php");
        exit;
    }
}

function isPostRequest()
{
    return $_SERVER["REQUEST_METHOD"] === "POST";
}

function getRegisterFormData()
{
    return [
        "login" => getPostText("login"),
        "password" => getPostText("password"),
        "repeatPassword" => getPostText("repeatPassword"),
        "imie" => getPostText("name"),
        "nazwisko" => getPostText("surname"),
        "rola" => getPostText("role")
    ];
}

function validateRegisterFormData($data)
{
    foreach ($data as $value) {
        if ($value === "") {
            return "Wszystkie pola są wymagane.";
        }
    }

    if ($data["password"] !== $data["repeatPassword"]) {
        return "Hasła nie są takie same.";
    }

    return "";
}

function loginExists($database, $login)
{
    $sql = "SELECT id FROM uzytkownicy WHERE login = ? LIMIT 1";
    $stmt = mysqli_prepare($database, $sql);

    mysqli_stmt_bind_param($stmt, "s", $login);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $exists = (bool) mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $exists;
}

function createUser($database, $data)
{
    $sql = "INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, status_konta, rola)
            VALUES (?, ?, ?, ?, 'aktywne', ?)";

    $stmt = mysqli_prepare($database, $sql);
    $hashedPassword = password_hash($data["password"], PASSWORD_DEFAULT);

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $data["login"],
        $hashedPassword,
        $data["imie"],
        $data["nazwisko"],
        $data["rola"]
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
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
            <a  id="backToUsers" href="index.php" class="btn btn-light" id="backToMagazine">Powrót</a>
        </div>
    </div>
</header>

<main class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
    <div class="card shadow border-0" style="max-width: 420px; width: 100%;">
        <div class="card-header bg-warning text-dark fw-semibold py-3">
            Zarejestruj użytkownika w systemie
        </div>
        <div class="card-body p-4">
            <form method="POST" action="register.php" class="d-grid gap-3">
                <div>
                    <label for="login" class="form-label fw-semibold">Login</label>
                    <input type="text" id="login" name="login" class="form-control" required>
                </div>
                <div>
                    <label for="imie" class="form-label fw-semibold">Imię</label>
                    <input type="text" id="imie" name="name" class="form-control" required>
                </div>
                <div>
                    <label for="nazwisko" class="form-label fw-semibold">Nazwisko</label>
                    <input type="text" id="nazwisko" name="surname" class="form-control" required>
                </div>
                <div>
                    <label for="password" class="form-label fw-semibold">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div>
                    <label for="repeatPassword" class="form-label fw-semibold">Powtórz hasło</label>
                    <input type="password" id="repeatPassword" name="repeatPassword" class="form-control" required>
                </div>
                <div>
                    <label for="selectRole" class="form-label fw-semibold">Wybierz role:</label>
                    <select id="selectRole" class="form-select" name="role">
                        <option value="user" selected>User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning fw-semibold">
                    Zarejestruj użytkownika
                </button>

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
  const backBtn = document.getElementById("backToUsers");

  if (backBtn) {
    backBtn.addEventListener("click", () => {
      localStorage.setItem("lastSection", "uzytkownicy");
    });
  }
});
</script>
</body>
</html>