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

if (!function_exists('canManageDictionaries')) {
    function canManageDictionaries(): bool
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

if (!function_exists('getDictionaryConfig')) {
    function getDictionaryConfig(string $dictionaryType): ?array
    {
        $map = [
            "kategorie" => [
                "table" => "slownik_kategorie",
                "label" => "kategoria",
                "productColumn" => "kategoria"
            ],
            "lokalizacje" => [
                "table" => "slownik_lokalizacje",
                "label" => "lokalizacja",
                "productColumn" => "lokalizacja"
            ]
        ];

        return $map[$dictionaryType] ?? null;
    }
}

if (!function_exists('getDictionaryRowById')) {
    function getDictionaryRowById(mysqli $database, string $tableName, int $id): ?array
    {
        $allowedTables = ["slownik_kategorie", "slownik_lokalizacje"];

        if (!in_array($tableName, $allowedTables, true) || $id <= 0) {
            return null;
        }

        $sql = "SELECT id, nazwa, aktywna, data_utworzenia FROM {$tableName} WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}

if (!function_exists('dictionaryValueExists')) {
    function dictionaryValueExists(mysqli $database, string $tableName, string $value, int $excludeId = 0): bool
    {
        $allowedTables = ["slownik_kategorie", "slownik_lokalizacje"];

        if (!in_array($tableName, $allowedTables, true)) {
            return false;
        }

        $value = trim($value);
        if ($value === "") {
            return false;
        }

        $sql = "SELECT id FROM {$tableName} WHERE nazwa = ? AND id <> ? LIMIT 1";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "si", $value, $excludeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = (bool) mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $exists;
    }
}

if (!function_exists('isDictionaryValueUsedInProducts')) {
    function isDictionaryValueUsedInProducts(mysqli $database, string $productColumn, string $value): bool
    {
        $allowedColumns = ["kategoria", "lokalizacja"];

        if (!in_array($productColumn, $allowedColumns, true)) {
            return false;
        }

        $sql = "SELECT id FROM magazyn WHERE {$productColumn} = ? LIMIT 1";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "s", $value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $used = (bool) mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $used;
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

        $sql = "SELECT id, aktywna FROM {$tableName} WHERE nazwa = ? LIMIT 1";
        $stmt = mysqli_prepare($database, $sql);
        mysqli_stmt_bind_param($stmt, "s", $value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($existing) {
            if ((int) $existing["aktywna"] === 0) {
                $updateSql = "UPDATE {$tableName} SET aktywna = 1 WHERE id = ?";
                $updateStmt = mysqli_prepare($database, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "i", $existing["id"]);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                return true;
            }

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