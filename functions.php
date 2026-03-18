<?php

if (!function_exists('getPostText')) {
    function getPostText(string $fieldName): string
    {
        return trim((string) ($_POST[$fieldName] ?? ""));
    }
}

if (!function_exists('getPostNumber')) {
    function getPostNumber(string $fieldName): int
    {
        return (int) ($_POST[$fieldName] ?? 0);
    }
}

if (!function_exists('getGetText')) {
    function getGetText(string $fieldName): string
    {
        return trim((string) ($_GET[$fieldName] ?? ""));
    }
}

if (!function_exists('escapeHtml')) {
    function escapeHtml($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }
}

if (!function_exists('isPostRequest')) {
    function isPostRequest(): bool
    {
        return ($_SERVER["REQUEST_METHOD"] ?? "") === "POST";
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: " . $url);
        exit;
    }
}

if (!function_exists('requireLoggedUser')) {
    function requireLoggedUser(): void
    {
        if (empty($_SESSION["login"]) || empty($_SESSION["userId"])) {
            redirect("login.php");
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        if (empty($_SESSION["rola"]) || $_SESSION["rola"] !== "admin") {
            redirect("index.php");
        }
    }
}

if (!function_exists('redirectIfLoggedIn')) {
    function redirectIfLoggedIn(): void
    {
        if (!empty($_SESSION["login"]) && !empty($_SESSION["userId"])) {
            redirect("index.php");
        }
    }
}

if (!function_exists('getLoggedUserId')) {
    function getLoggedUserId(): int
    {
        return (int) ($_SESSION["userId"] ?? 0);
    }
}

if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $message, string $type = "info"): void
    {
        $_SESSION["message"] = $message;
        $_SESSION["message_type"] = $type;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return isset($_SESSION["rola"]) && $_SESSION["rola"] === "admin";
    }
}

if (!function_exists('isSystemAdmin')) {
    function isSystemAdmin(): bool
    {
        return isset($_SESSION["login"]) && $_SESSION["login"] === "AdminSystemu";
    }
}

if (!function_exists('canDeleteProducts')) {
    function canDeleteProducts(): bool
    {
        return isAdmin();
    }
}

if (!function_exists('canManageUsers')) {
    function canManageUsers(): bool
    {
        return isAdmin();
    }
}

if (!function_exists('canDeactivateTargetUser')) {
    function canDeactivateTargetUser(array $targetUser): bool
    {
        if (!isAdmin()) {
            return false;
        }

        $sessionUserId = getLoggedUserId();
        $targetId = (int) ($targetUser["id"] ?? 0);
        $targetRole = (string) ($targetUser["rola"] ?? "");
        $targetLogin = (string) ($targetUser["login"] ?? "");
        $targetStatus = (string) ($targetUser["status_konta"] ?? "");

        if ($targetId <= 0 || $targetId === $sessionUserId) {
            return false;
        }

        if ($targetLogin === "AdminSystemu") {
            return false;
        }

        if ($targetStatus !== "aktywne") {
            return false;
        }

        if (isSystemAdmin()) {
            return true;
        }

        return $targetRole !== "admin";
    }
}

if (!function_exists('canChangeTargetLogin')) {
    function canChangeTargetLogin(array $targetUser): bool
    {
        if (!isAdmin()) {
            return false;
        }

        $sessionUserId = getLoggedUserId();
        $targetId = (int) ($targetUser["id"] ?? 0);
        $targetRole = (string) ($targetUser["rola"] ?? "");
        $targetLogin = (string) ($targetUser["login"] ?? "");
        $targetStatus = (string) ($targetUser["status_konta"] ?? "");

        if ($targetId <= 0 || $targetId === $sessionUserId) {
            return false;
        }

        if ($targetStatus !== "aktywne") {
            return false;
        }

        if ($targetLogin === "AdminSystemu") {
            return false;
        }

        if (isSystemAdmin()) {
            return in_array($targetRole, ["admin", "user"], true);
        }

        return $targetRole === "user";
    }
}

if (!function_exists('canChangeTargetPassword')) {
    function canChangeTargetPassword(array $targetUser): bool
    {
        if (!isAdmin()) {
            return false;
        }

        $sessionUserId = getLoggedUserId();
        $targetId = (int) ($targetUser["id"] ?? 0);
        $targetRole = (string) ($targetUser["rola"] ?? "");
        $targetLogin = (string) ($targetUser["login"] ?? "");
        $targetStatus = (string) ($targetUser["status_konta"] ?? "");

        if ($targetId <= 0 || $targetId === $sessionUserId) {
            return false;
        }

        if ($targetStatus !== "aktywne") {
            return false;
        }

        if ($targetLogin === "AdminSystemu") {
            return false;
        }

        if (isSystemAdmin()) {
            return in_array($targetRole, ["admin", "user"], true);
        }

        return $targetRole === "user";
    }
}

if (!function_exists('canChangeTargetRfid')) {
    function canChangeTargetRfid(array $targetUser): bool
    {
        if (!isSystemAdmin()) {
            return false;
        }

        $targetLogin = (string) ($targetUser["login"] ?? "");

        if ($targetLogin === "AdminSystemu") {
            return false;
        }

        return true;
    }
}

if (!function_exists('saveDictionaryValue')) {
    function saveDictionaryValue(mysqli $database, string $tableName, string $value): bool
    {
        $value = trim($value);

        if ($value === "") {
            return false;
        }

        $allowedTables = ["slownik_kategorie", "slownik_lokalizacje"];

        if (!in_array($tableName, $allowedTables, true)) {
            return false;
        }

        $checkSql = "SELECT id FROM {$tableName} WHERE nazwa = ? LIMIT 1";
        $checkStmt = mysqli_prepare($database, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $value);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $exists = (bool) mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);

        if ($exists) {
            return false;
        }

        $insertSql = "INSERT INTO {$tableName} (nazwa, aktywna, data_utworzenia) VALUES (?, 1, NOW())";
        $insertStmt = mysqli_prepare($database, $insertSql);
        mysqli_stmt_bind_param($insertStmt, "s", $value);
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);

        return true;
    }
}