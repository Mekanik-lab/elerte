<?php
function getPostText(string $fieldName): string {
    return trim((string)($_POST[$fieldName] ?? ""));
}

function getPostNumber(string $fieldName): int {
    return (int)($_POST[$fieldName] ?? 0);
}

function htmlspecialcharEscapeFunction($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>