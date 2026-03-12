<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

include "connection.php";
include "functions.php";

requireAjaxAuthorization();

$table = getRequestedTable();
$userRole = getCurrentUserRole();

validateTableAccess($table, $userRole);

$page = getPageNumber($_GET["page"] ?? ($_POST["page"] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

handleTableRequest($database, $table, $page, $perPage, $offset);

mysqli_close($database);
exit;

function requireAjaxAuthorization()
{
    if (empty($_SESSION["login"]) || empty($_SESSION["userId"]) || empty($_SESSION["rola"])) {
        http_response_code(403);
        exit("Brak autoryzacji");
    }

    if (
        !isset($_SERVER["HTTP_X_REQUESTED_WITH"]) ||
        $_SERVER["HTTP_X_REQUESTED_WITH"] !== "XMLHttpRequest"
    ) {
        header("Location: index.php");
        exit;
    }
}

function getRequestedTable()
{
    return $_GET["table"] ?? ($_POST["table"] ?? "magazyn");
}

function getCurrentUserRole()
{
    return trim((string) $_SESSION["rola"]);
}

function getAllowedTables()
{
    return [
        "magazyn",
        "wydania",
        "inwentaryzacje",
        "inwentaryzacja_pozycja",
        "uzytkownicy",
        "historia_operacji"
    ];
}

function getTablePermissions()
{
    return [
        "magazyn" => ["admin", "user"],
        "wydania" => ["admin", "user"],
        "inwentaryzacje" => ["admin", "user"],
        "inwentaryzacja_pozycja" => ["admin", "user"],
        "uzytkownicy" => ["admin"],
        "historia_operacji" => ["admin"]
    ];
}

function validateTableAccess($table, $userRole)
{
    $allowedTables = getAllowedTables();
    $tablePermissions = getTablePermissions();

    if (!in_array($table, $allowedTables, true)) {
        http_response_code(403);
        exit("Nieprawidłowa tabela");
    }

    if (
        !isset($tablePermissions[$table]) ||
        !in_array($userRole, $tablePermissions[$table], true)
    ) {
        http_response_code(403);
        exit("Brak uprawnień do tej sekcji");
    }
}

function getPageNumber($value)
{
    $page = (int) $value;
    return $page > 0 ? $page : 1;
}

function getSortDirection($sort)
{
    $sort = strtoupper(trim((string) $sort));
    return in_array($sort, ["ASC", "DESC"], true) ? $sort : "ASC";
}

function prepareStatement($database, $sql, $errorPrefix = "Błąd prepare")
{
    $stmt = mysqli_prepare($database, $sql);

    if (!$stmt) {
        http_response_code(500);
        exit($errorPrefix . ": " . mysqli_error($database));
    }

    return $stmt;
}

function executeStatement($stmt, $errorPrefix = "Błąd execute")
{
    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);
        exit($errorPrefix . ": " . mysqli_stmt_error($stmt));
    }
}

function fetchPreparedResult($database, $sql, $types = "", $params = [], $prepareError = "Błąd prepare", $executeError = "Błąd execute")
{
    $stmt = prepareStatement($database, $sql, $prepareError);

    if ($types !== "" && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    executeStatement($stmt, $executeError);
    $result = mysqli_stmt_get_result($stmt);

    return [$stmt, $result];
}

function getTotalPages($database, $countSql, $types = "", $params = [], $perPage = 40)
{
    [$stmt, $result] = fetchPreparedResult(
        $database,
        $countSql,
        $types,
        $params,
        "Błąd prepare COUNT",
        "Błąd execute COUNT"
    );

    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $totalRows = (int) ($row["total"] ?? 0);
    return max(1, (int) ceil($totalRows / $perPage));
}

function renderPagination($currentPage, $totalPages, $table)
{
    if ($totalPages <= 1) {
        return;
    }

    echo '<div class="d-flex justify-content-center mt-3">';
    echo '<nav aria-label="Paginacja">';
    echo '<ul class="pagination mb-0 flex-wrap justify-content-center">';

    $prevDisabled = $currentPage <= 1 ? " disabled" : "";
    $prevPage = max(1, $currentPage - 1);

    echo '<li class="page-item' . $prevDisabled . '">';
    echo '<button class="page-link pagination-btn" data-table="' . escapeHtml($table) . '" data-page="' . $prevPage . '">Poprzednia</button>';
    echo '</li>';

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        echo '<li class="page-item"><button class="page-link pagination-btn" data-table="' . escapeHtml($table) . '" data-page="1">1</button></li>';

        if ($start > 2) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? " active" : "";
        echo '<li class="page-item' . $active . '">';
        echo '<button class="page-link pagination-btn" data-table="' . escapeHtml($table) . '" data-page="' . $i . '">' . $i . '</button>';
        echo '</li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        echo '<li class="page-item"><button class="page-link pagination-btn" data-table="' . escapeHtml($table) . '" data-page="' . $totalPages . '">' . $totalPages . '</button></li>';
    }

    $nextDisabled = $currentPage >= $totalPages ? " disabled" : "";
    $nextPage = min($totalPages, $currentPage + 1);

    echo '<li class="page-item' . $nextDisabled . '">';
    echo '<button class="page-link pagination-btn" data-table="' . escapeHtml($table) . '" data-page="' . $nextPage . '">Następna</button>';
    echo '</li>';

    echo '</ul>';
    echo '</nav>';
    echo '</div>';
}

function renderTable($result, $table, $currentPage = 1, $totalPages = 1)
{
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover align-middle table-sm mb-0">';
    echo '<thead><tr>';

    $fields = mysqli_fetch_fields($result);

    foreach ($fields as $field) {
        echo "<th class='text-center'>" . escapeHtml($field->name) . "</th>";
    }

    if ($table === "magazyn" || $table === "uzytkownicy" || $table === "inwentaryzacja_pozycja") {
        echo "<th class='text-center'>Akcje</th>";
    }

    echo '</tr></thead><tbody>';

    while ($row = mysqli_fetch_assoc($result)) {
        renderTableRow($row, $table);
    }

    echo '</tbody></table></div>';

    renderPagination($currentPage, $totalPages, $table);
}

function renderTableRow($row, $table)
{
    $id = reset($row);

    if ($table === "magazyn" || $table === "historia_operacji" || $table === "uzytkownicy" || $table === "inwentaryzacja_pozycja") {
        echo "<tr data-id='" . escapeHtml((string) $id) . "'>";
    } elseif ($table === "inwentaryzacje") {
        echo "<tr data-id='" . escapeHtml((string) $id) . "' data-section='inwentaryzacja_pozycja' style='cursor:pointer;'>";
    } else {
        echo "<tr>";
    }

    foreach ($row as $key => $value) {
        $dataAttr = "";

        if ($table === "uzytkownicy") {
            if ($key === "Login") {
                $dataAttr = " data-login='" . escapeHtml((string) $value) . "'";
            }

            if ($key === "Status konta") {
                $dataAttr = " data-status='" . escapeHtml((string) $value) . "'";
            }

            if ($key === "Rola") {
                $dataAttr = " data-role='" . escapeHtml((string) $value) . "'";
            }
        }

        echo "<td class='text-center'{$dataAttr}>" . escapeHtml((string) $value) . "</td>";
    }

    if ($table === "magazyn") {
        renderMagazineActions();
    }

    if ($table === "uzytkownicy") {
        renderUsersActions($row);
    }

    if ($table === "inwentaryzacja_pozycja") {
        renderInventoryPositionActions($row);
    }

    echo "</tr>";
}

function renderMagazineActions()
{
    echo "<td class='text-center'>";
    echo "<button class='editBtn btn btn-warning btn-sm me-1'>Edytuj</button>";

    if (canDeleteProducts()) {
        echo "<button class='deleteBtn btn btn-danger btn-sm me-1'>Usuń</button>";
    }

    echo "<button class='issueBtn btn btn-secondary btn-sm me-1'>Wydanie</button>";
    echo "<button class='inventoryBtn btn btn-info btn-sm'>Inwentaryzacja</button>";
    echo "</td>";
}

function renderUsersActions($row)
{
    $targetUser = [
        "id" => $row["Id"] ?? 0,
        "login" => $row["Login"] ?? "",
        "status_konta" => $row["Status konta"] ?? "",
        "rola" => $row["Rola"] ?? ""
    ];

    $hasActions = false;

    echo "<td class='text-center'>";

    if (canChangeTargetLogin($targetUser)) {
        echo "<button class='changeUserLoginBtn btn btn-warning btn-sm me-1'>Zmień login</button>";
        $hasActions = true;
    }

    if (canChangeTargetPassword($targetUser)) {
        echo "<button class='changeUserPasswordBtn btn btn-secondary btn-sm me-1'>Zmień hasło</button>";
        $hasActions = true;
    }

    if (canDeactivateTargetUser($targetUser)) {
        echo "<button class='deactivateUserBtn btn btn-danger btn-sm'>Dezaktywuj</button>";
        $hasActions = true;
    }

    if (!$hasActions) {
        echo "-";
    }

    echo "</td>";
}

function renderInventoryPositionActions($row)
{
    $isApproved = (int) ($row["Zatwierdzona"] ?? 0);

    echo "<td class='text-center'>";

    if ($isApproved === 0) {
        echo "<button class='deleteInventoryPositionBtn btn btn-danger btn-sm'>Usuń</button>";
    } else {
        echo "-";
    }

    echo "</td>";
}

function renderAndExit($database, $stmt, $result, $table, $page, $totalPages)
{
    renderTable($result, $table, $page, $totalPages);
    mysqli_stmt_close($stmt);
    mysqli_close($database);
    exit;
}

function handleTableRequest($database, $table, $page, $perPage, $offset)
{
    if ($table === "magazyn" && isPostRequest() && hasMagazineFilters()) {
        handleMagazineSearch($database, $page, $perPage, $offset);
    }

    if ($table === "historia_operacji" && isPostRequest() && hasHistoryFilters()) {
        handleHistoryFilter($database, $page, $perPage, $offset);
    }

    if ($table === "wydania" && isPostRequest() && hasIssuesFilters()) {
        handleIssuesFilter($database, $page, $perPage, $offset);
    }

    if ($table === "uzytkownicy" && isPostRequest() && hasUsersFilters()) {
        handleUsersFilter($database, $page, $perPage, $offset);
    }

    if ($table === "inwentaryzacje" && isPostRequest() && hasInventoriesFilters()) {
        handleInventoriesFilter($database, $page, $perPage, $offset);
    }

    if (
        $table === "inwentaryzacja_pozycja" &&
        isPostRequest() &&
        isset($_POST["inventoryId"]) &&
        hasInventoryPositionFilters()
    ) {
        handleInventoryPositionsFilter($database, $page, $perPage, $offset);
    }

    if ($table === "inwentaryzacja_pozycja" && isset($_POST["inventoryId"])) {
        handleInventoryPositionsDetails($database, $page, $perPage, $offset);
    }

    handleDefaultTable($database, $table, $page, $perPage, $offset);
}

function hasInventoryPositionFilters()
{
    $filterFields = [
        "surname",
        "productName",
        "approved",
        "stateFrom",
        "stateTo",
        "differenceFrom",
        "differenceTo",
        "sortColumn",
        "sortOrder"
    ];

    foreach ($filterFields as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== "") {
            return true;
        }
    }

    return false;
}

function hasAnyPostedFilterValue(array $fields): bool
{
    foreach ($fields as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== "") {
            return true;
        }
    }

    return false;
}

function hasMagazineFilters(): bool
{
    return hasAnyPostedFilterValue([
        "searchProduct",
        "productName",
        "addedBy",
        "productCategory",
        "productAdress",
        "productComments",
        "quantityFrom",
        "quantityTo",
        "sortColumn",
        "sortOrder"
    ]);
}

function hasHistoryFilters(): bool
{
    return hasAnyPostedFilterValue([
        "login",
        "productName",
        "operationName",
        "operationDate",
        "sortColumn",
        "sortOrder"
    ]);
}

function hasIssuesFilters(): bool
{
    return hasAnyPostedFilterValue([
        "surname",
        "productName",
        "reason",
        "issueDate",
        "quantityFrom",
        "quantityTo",
        "sortColumn",
        "sortOrder"
    ]);
}

function hasUsersFilters(): bool
{
    return hasAnyPostedFilterValue([
        "name",
        "surname",
        "login",
        "status",
        "role",
        "sortColumn",
        "sortOrder"
    ]);
}

function hasInventoriesFilters(): bool
{
    return hasAnyPostedFilterValue([
        "inventoryNumber",
        "surname",
        "createdDate",
        "approvedDate",
        "approved",
        "sortColumn",
        "sortOrder"
    ]);
}

function buildMagazineFilterData()
{
    $where = [];
    $params = [];
    $types = "";

    if (getPostText("productName") !== "") {
        $where[] = "magazyn.nazwa LIKE ?";
        $params[] = "%" . getPostText("productName") . "%";
        $types .= "s";
    }

    if (getPostText("addedBy") !== "") {
        $where[] = "CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) LIKE ?";
        $params[] = "%" . getPostText("addedBy") . "%";
        $types .= "s";
    }

    if (getPostText("productCategory") !== "") {
        $where[] = "magazyn.kategoria LIKE ?";
        $params[] = "%" . getPostText("productCategory") . "%";
        $types .= "s";
    }

    if (getPostText("productAdress") !== "") {
        $where[] = "magazyn.lokalizacja LIKE ?";
        $params[] = "%" . getPostText("productAdress") . "%";
        $types .= "s";
    }

    if (getPostText("productComments") !== "") {
        $where[] = "magazyn.uwagi LIKE ?";
        $params[] = "%" . getPostText("productComments") . "%";
        $types .= "s";
    }

    if (isset($_POST["quantityFrom"]) && $_POST["quantityFrom"] !== "") {
        $where[] = "magazyn.ilosc >= ?";
        $params[] = getPostNumber("quantityFrom");
        $types .= "i";
    }

    if (isset($_POST["quantityTo"]) && $_POST["quantityTo"] !== "") {
        $where[] = "magazyn.ilosc <= ?";
        $params[] = getPostNumber("quantityTo");
        $types .= "i";
    }

    $allowedSortColumns = [
        "Id" => "magazyn.id",
        "Nazwa produktu" => "magazyn.nazwa",
        "Kategoria" => "magazyn.kategoria",
        "Ilość" => "magazyn.ilosc",
        "Jednostka" => "magazyn.jednostka",
        "Lokalizacja" => "magazyn.lokalizacja",
        "Uwagi" => "magazyn.uwagi",
        "Dodał" => "uzytkownicy.nazwisko"
    ];

    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Id";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "magazyn.id";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "ASC");

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleMagazineSearch($database, $page, $perPage, $offset)
{
    $filterData = buildMagazineFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(*) total
        FROM magazyn
        INNER JOIN uzytkownicy
            ON magazyn.id_uzytkownika = uzytkownicy.id
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            magazyn.id AS 'Id',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Dodał',
            magazyn.nazwa AS 'Nazwa produktu',
            magazyn.kategoria AS 'Kategoria',
            magazyn.jednostka AS 'Jednostka',
            magazyn.ilosc AS 'Ilość',
            magazyn.lokalizacja AS 'Lokalizacja',
            magazyn.uwagi AS 'Uwagi'
        FROM magazyn
        INNER JOIN uzytkownicy
            ON magazyn.id_uzytkownika = uzytkownicy.id
        $whereSql
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $result] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania magazynu",
        "Błąd execute filtrowania magazynu"
    );

    renderAndExit($database, $stmt, $result, "magazyn", $page, $totalPages);
}

function getHistorySortColumns()
{
    return [
        "Id" => "historia_operacji.id",
        "Login" => "uzytkownicy.login",
        "Id produktu" => "historia_operacji.id_produktu",
        "Imię i nazwisko" => "uzytkownicy.nazwisko",
        "Nazwa produktu" => "nazwa_sort",
        "Operacja" => "historia_operacji.operacja",
        "Data operacji" => "historia_operacji.data_operacji"
    ];
}

function buildHistoryFilterData()
{
    $where = [];
    $params = [];
    $types = "";

    if (getPostText("login") !== "") {
        $where[] = "uzytkownicy.login LIKE ?";
        $params[] = "%" . getPostText("login") . "%";
        $types .= "s";
    }

    if (getPostText("productName") !== "") {
        $where[] = "COALESCE(
            magazyn.nazwa,
            JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_przed, '$.nazwa')),
            JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_po, '$.nazwa'))
        ) LIKE ?";
        $params[] = "%" . getPostText("productName") . "%";
        $types .= "s";
    }

    if (getPostText("operationName") !== "") {
        $where[] = "historia_operacji.operacja LIKE ?";
        $params[] = "%" . getPostText("operationName") . "%";
        $types .= "s";
    }

    if (getPostText("operationDate") !== "") {
        $where[] = "DATE(historia_operacji.data_operacji) = ?";
        $params[] = getPostText("operationDate");
        $types .= "s";
    }

    $allowedSortColumns = getHistorySortColumns();
    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Data operacji";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "historia_operacji.data_operacji";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "DESC");
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleHistoryFilter($database, $page, $perPage, $offset)
{
    $filterData = buildHistoryFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(*) total
        FROM historia_operacji
        INNER JOIN uzytkownicy
            ON historia_operacji.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON historia_operacji.id_produktu = magazyn.id
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            historia_operacji.id AS 'Id',
            uzytkownicy.login AS 'Login',
            historia_operacji.id_produktu AS 'Id produktu',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Imię i nazwisko',
            COALESCE(
                magazyn.nazwa,
                JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_przed, '$.nazwa')),
                JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_po, '$.nazwa'))
            ) AS 'Nazwa produktu',
            historia_operacji.operacja AS 'Operacja',
            historia_operacji.data_operacji AS 'Data operacji',
            historia_operacji.dane_przed AS 'Dane przed operacją',
            historia_operacji.dane_po AS 'Dane po operacji',
            COALESCE(
                magazyn.nazwa,
                JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_przed, '$.nazwa')),
                JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_po, '$.nazwa'))
            ) AS nazwa_sort
        FROM historia_operacji
        INNER JOIN uzytkownicy
            ON historia_operacji.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON historia_operacji.id_produktu = magazyn.id
        $whereSql
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $resultWithSort] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania historii",
        "Błąd execute filtrowania historii"
    );

    $cleanRows = [];
    while ($row = mysqli_fetch_assoc($resultWithSort)) {
        unset($row["nazwa_sort"]);
        $cleanRows[] = $row;
    }

    mysqli_stmt_close($stmt);

    renderArrayTableAndExit($database, $cleanRows, "historia_operacji", $page, $totalPages);
}

function getIssuesSortColumns()
{
    return [
        "Id" => "wydania.id",
        "Wydał" => "uzytkownicy.nazwisko",
        "Data i godzina" => "wydania.data_i_godzina",
        "Nazwa produktu" => "nazwa_sort",
        "Jednostka" => "jednostka_sort",
        "Ilość" => "wydania.ilosc",
        "Powód" => "wydania.powod"
    ];
}

function buildIssuesFilterData()
{
    $where = [];
    $params = [];
    $types = "";

    if (getPostText("surname") !== "") {
        $where[] = "uzytkownicy.nazwisko LIKE ?";
        $params[] = "%" . getPostText("surname") . "%";
        $types .= "s";
    }

    if (getPostText("productName") !== "") {
        $where[] = "COALESCE(magazyn.nazwa, wydania.nazwa_produktu_snapshot) LIKE ?";
        $params[] = "%" . getPostText("productName") . "%";
        $types .= "s";
    }

    if (getPostText("reason") !== "") {
        $where[] = "wydania.powod LIKE ?";
        $params[] = "%" . getPostText("reason") . "%";
        $types .= "s";
    }

    if (getPostText("issueDate") !== "") {
        $where[] = "DATE(wydania.data_i_godzina) = ?";
        $params[] = getPostText("issueDate");
        $types .= "s";
    }

    if (isset($_POST["quantityFrom"]) && $_POST["quantityFrom"] !== "") {
        $where[] = "wydania.ilosc >= ?";
        $params[] = getPostNumber("quantityFrom");
        $types .= "i";
    }

    if (isset($_POST["quantityTo"]) && $_POST["quantityTo"] !== "") {
        $where[] = "wydania.ilosc <= ?";
        $params[] = getPostNumber("quantityTo");
        $types .= "i";
    }

    $allowedSortColumns = getIssuesSortColumns();
    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Id";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "wydania.id";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "DESC");
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleIssuesFilter($database, $page, $perPage, $offset)
{
    $filterData = buildIssuesFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(*) total
        FROM wydania
        INNER JOIN uzytkownicy
            ON wydania.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON magazyn.id = wydania.id_produktu
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            wydania.id AS 'Id',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Wydał',
            wydania.data_i_godzina AS 'Data i godzina',
            COALESCE(magazyn.nazwa, wydania.nazwa_produktu_snapshot) AS 'Nazwa produktu',
            COALESCE(magazyn.jednostka, wydania.jednostka_snapshot) AS 'Jednostka',
            wydania.ilosc AS 'Ilość',
            wydania.powod AS 'Powód',
            COALESCE(magazyn.nazwa, wydania.nazwa_produktu_snapshot) AS nazwa_sort,
            COALESCE(magazyn.jednostka, wydania.jednostka_snapshot) AS jednostka_sort
        FROM wydania
        INNER JOIN uzytkownicy
            ON wydania.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON magazyn.id = wydania.id_produktu
        $whereSql
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $resultWithSort] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania wydań",
        "Błąd execute filtrowania wydań"
    );

    $cleanRows = [];
    while ($row = mysqli_fetch_assoc($resultWithSort)) {
        unset($row["nazwa_sort"], $row["jednostka_sort"]);
        $cleanRows[] = $row;
    }

    mysqli_stmt_close($stmt);

    renderArrayTableAndExit($database, $cleanRows, "wydania", $page, $totalPages);
}

function getUsersSortColumns()
{
    return [
        "Id" => "uzytkownicy.id",
        "Login" => "uzytkownicy.login",
        "Imię" => "uzytkownicy.imie",
        "Nazwisko" => "uzytkownicy.nazwisko",
        "Imię i nazwisko" => "uzytkownicy.nazwisko",
        "Status konta" => "uzytkownicy.status_konta",
        "Rola" => "uzytkownicy.rola"
    ];
}

function buildUsersFilterData()
{
    $where = [];
    $params = [];
    $types = "";

    if (getPostText("name") !== "") {
        $where[] = "uzytkownicy.imie LIKE ?";
        $params[] = "%" . getPostText("name") . "%";
        $types .= "s";
    }

    if (getPostText("surname") !== "") {
        $where[] = "uzytkownicy.nazwisko LIKE ?";
        $params[] = "%" . getPostText("surname") . "%";
        $types .= "s";
    }

    if (getPostText("login") !== "") {
        $where[] = "uzytkownicy.login LIKE ?";
        $params[] = "%" . getPostText("login") . "%";
        $types .= "s";
    }

    if (getPostText("status") !== "") {
        $where[] = "uzytkownicy.status_konta = ?";
        $params[] = getPostText("status");
        $types .= "s";
    }

    if (getPostText("role") !== "") {
        $where[] = "uzytkownicy.rola = ?";
        $params[] = getPostText("role");
        $types .= "s";
    }

    $allowedSortColumns = getUsersSortColumns();
    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Id";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "uzytkownicy.id";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "DESC");
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleUsersFilter($database, $page, $perPage, $offset)
{
    $filterData = buildUsersFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(*) total
        FROM uzytkownicy
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            id AS 'Id',
            login AS 'Login',
            CONCAT(imie, ' ', nazwisko) AS 'Imię i nazwisko',
            status_konta AS 'Status konta',
            rola AS 'Rola'
        FROM uzytkownicy
        $whereSql
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $result] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania użytkowników",
        "Błąd execute filtrowania użytkowników"
    );

    renderAndExit($database, $stmt, $result, "uzytkownicy", $page, $totalPages);
}

function getInventoriesSortColumns()
{
    return [
        "Id" => "inwentaryzacje.id",
        "Numer inwentaryzacji" => "inwentaryzacje.numer_inwentaryzacji",
        "Zinwentaryzował" => "uzytkownicy.nazwisko",
        "Data utworzenia" => "inwentaryzacje.data_utworzenia",
        "Data zatwierdzenia" => "inwentaryzacje.data_zatwierdzenia",
        "Zatwierdzona" => "inwentaryzacje.zatwierdzona",
        "Liczba pozycji" => "Liczba pozycji"
    ];
}

function buildInventoriesFilterData()
{
    $where = [];
    $params = [];
    $types = "";

    if (getPostText("inventoryNumber") !== "") {
        $where[] = "inwentaryzacje.numer_inwentaryzacji LIKE ?";
        $params[] = "%" . getPostText("inventoryNumber") . "%";
        $types .= "s";
    }

    if (getPostText("surname") !== "") {
        $where[] = "uzytkownicy.nazwisko LIKE ?";
        $params[] = "%" . getPostText("surname") . "%";
        $types .= "s";
    }

    if (getPostText("createdDate") !== "") {
        $where[] = "DATE(inwentaryzacje.data_utworzenia) = ?";
        $params[] = getPostText("createdDate");
        $types .= "s";
    }

    if (getPostText("approvedDate") !== "") {
        $where[] = "DATE(inwentaryzacje.data_zatwierdzenia) = ?";
        $params[] = getPostText("approvedDate");
        $types .= "s";
    }

    if (isset($_POST["approved"]) && $_POST["approved"] !== "") {
        $where[] = "inwentaryzacje.zatwierdzona = ?";
        $params[] = getPostNumber("approved");
        $types .= "i";
    }

    $allowedSortColumns = getInventoriesSortColumns();
    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Id";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "inwentaryzacje.id";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "DESC");
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleInventoriesFilter($database, $page, $perPage, $offset)
{
    $filterData = buildInventoriesFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(DISTINCT inwentaryzacje.id) total
        FROM inwentaryzacje
        INNER JOIN uzytkownicy
            ON inwentaryzacje.id_uzytkownika = uzytkownicy.id
        LEFT JOIN inwentaryzacja_pozycja
            ON inwentaryzacje.id = inwentaryzacja_pozycja.id_inwentaryzacji
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            inwentaryzacje.id AS 'Id',
            inwentaryzacje.numer_inwentaryzacji AS 'Numer inwentaryzacji',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Zinwentaryzował',
            inwentaryzacje.data_utworzenia AS 'Data utworzenia',
            inwentaryzacje.data_zatwierdzenia AS 'Data zatwierdzenia',
            inwentaryzacje.zatwierdzona AS 'Zatwierdzona',
            COUNT(inwentaryzacja_pozycja.id) AS 'Liczba pozycji'
        FROM inwentaryzacje
        INNER JOIN uzytkownicy
            ON inwentaryzacje.id_uzytkownika = uzytkownicy.id
        LEFT JOIN inwentaryzacja_pozycja
            ON inwentaryzacje.id = inwentaryzacja_pozycja.id_inwentaryzacji
        $whereSql
        GROUP BY
            inwentaryzacje.id,
            inwentaryzacje.numer_inwentaryzacji,
            uzytkownicy.imie,
            uzytkownicy.nazwisko,
            inwentaryzacje.data_utworzenia,
            inwentaryzacje.data_zatwierdzenia,
            inwentaryzacje.zatwierdzona
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $result] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania inwentaryzacji",
        "Błąd execute filtrowania inwentaryzacji"
    );

    renderAndExit($database, $stmt, $result, "inwentaryzacje", $page, $totalPages);
}

function getInventoryPositionsSortColumns()
{
    return [
        "Id" => "inwentaryzacja_pozycja.id",
        "Id inwentaryzacji" => "inwentaryzacja_pozycja.id_inwentaryzacji",
        "Zinwentaryzował" => "uzytkownicy.nazwisko",
        "Nazwa produktu" => "nazwa_sort",
        "Jednostka" => "jednostka_sort",
        "Stan" => "inwentaryzacja_pozycja.stan",
        "Różnica" => "inwentaryzacja_pozycja.roznica",
        "Zatwierdzona" => "inwentaryzacja_pozycja.zatwierdzona"
    ];
}

function buildInventoryPositionsFilterData()
{
    $where = ["inwentaryzacja_pozycja.id_inwentaryzacji = ?"];
    $params = [getPostNumber("inventoryId")];
    $types = "i";

    if (getPostText("surname") !== "") {
        $where[] = "uzytkownicy.nazwisko LIKE ?";
        $params[] = "%" . getPostText("surname") . "%";
        $types .= "s";
    }

    if (getPostText("productName") !== "") {
        $where[] = "COALESCE(magazyn.nazwa, inwentaryzacja_pozycja.nazwa_produktu_snapshot) LIKE ?";
        $params[] = "%" . getPostText("productName") . "%";
        $types .= "s";
    }

    if (isset($_POST["approved"]) && $_POST["approved"] !== "") {
        $where[] = "inwentaryzacja_pozycja.zatwierdzona = ?";
        $params[] = getPostNumber("approved");
        $types .= "i";
    }

    if (isset($_POST["stateFrom"]) && $_POST["stateFrom"] !== "") {
        $where[] = "inwentaryzacja_pozycja.stan >= ?";
        $params[] = getPostNumber("stateFrom");
        $types .= "i";
    }

    if (isset($_POST["stateTo"]) && $_POST["stateTo"] !== "") {
        $where[] = "inwentaryzacja_pozycja.stan <= ?";
        $params[] = getPostNumber("stateTo");
        $types .= "i";
    }

    if (isset($_POST["differenceFrom"]) && $_POST["differenceFrom"] !== "") {
        $where[] = "COALESCE(inwentaryzacja_pozycja.roznica, 0) >= ?";
        $params[] = getPostNumber("differenceFrom");
        $types .= "i";
    }

    if (isset($_POST["differenceTo"]) && $_POST["differenceTo"] !== "") {
        $where[] = "COALESCE(inwentaryzacja_pozycja.roznica, 0) <= ?";
        $params[] = getPostNumber("differenceTo");
        $types .= "i";
    }

    $allowedSortColumns = getInventoryPositionsSortColumns();
    $sortColumnKey = getPostText("sortColumn") !== "" ? getPostText("sortColumn") : "Id";
    $sortColumn = $allowedSortColumns[$sortColumnKey] ?? "inwentaryzacja_pozycja.id";
    $sortOrder = getSortDirection(getPostText("sortOrder") !== "" ? getPostText("sortOrder") : "DESC");
    $whereSql = "WHERE " . implode(" AND ", $where);

    return [
        "whereSql" => $whereSql,
        "params" => $params,
        "types" => $types,
        "sortColumn" => $sortColumn,
        "sortOrder" => $sortOrder
    ];
}

function handleInventoryPositionsFilter($database, $page, $perPage, $offset)
{
    $filterData = buildInventoryPositionsFilterData();

    $whereSql = $filterData["whereSql"];
    $params = $filterData["params"];
    $types = $filterData["types"];
    $sortColumn = $filterData["sortColumn"];
    $sortOrder = $filterData["sortOrder"];

    $countSql = "
        SELECT COUNT(*) total
        FROM inwentaryzacja_pozycja
        INNER JOIN uzytkownicy
            ON inwentaryzacja_pozycja.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON inwentaryzacja_pozycja.id_produktu = magazyn.id
        $whereSql
    ";

    $totalPages = getTotalPages($database, $countSql, $types, $params, $perPage);

    $sql = "
        SELECT
            inwentaryzacja_pozycja.id AS 'Id',
            inwentaryzacja_pozycja.id_inwentaryzacji AS 'Id inwentaryzacji',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Zinwentaryzował',
            COALESCE(magazyn.nazwa, inwentaryzacja_pozycja.nazwa_produktu_snapshot) AS 'Nazwa produktu',
            COALESCE(magazyn.jednostka, inwentaryzacja_pozycja.jednostka_snapshot) AS 'Jednostka',
            inwentaryzacja_pozycja.stan AS 'Stan',
            inwentaryzacja_pozycja.roznica AS 'Różnica',
            inwentaryzacja_pozycja.zatwierdzona AS 'Zatwierdzona',
            COALESCE(magazyn.nazwa, inwentaryzacja_pozycja.nazwa_produktu_snapshot) AS nazwa_sort,
            COALESCE(magazyn.jednostka, inwentaryzacja_pozycja.jednostka_snapshot) AS jednostka_sort
        FROM inwentaryzacja_pozycja
        INNER JOIN uzytkownicy
            ON inwentaryzacja_pozycja.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON inwentaryzacja_pozycja.id_produktu = magazyn.id
        $whereSql
        ORDER BY $sortColumn $sortOrder
        LIMIT ? OFFSET ?
    ";

    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryTypes = $types . "ii";

    [$stmt, $resultWithSort] = fetchPreparedResult(
        $database,
        $sql,
        $queryTypes,
        $queryParams,
        "Błąd prepare filtrowania pozycji inwentaryzacji",
        "Błąd execute filtrowania pozycji inwentaryzacji"
    );

    $cleanRows = [];
    while ($row = mysqli_fetch_assoc($resultWithSort)) {
        unset($row["nazwa_sort"], $row["jednostka_sort"]);
        $cleanRows[] = $row;
    }

    mysqli_stmt_close($stmt);

    renderArrayTableAndExit($database, $cleanRows, "inwentaryzacja_pozycja", $page, $totalPages);
}

function handleInventoryPositionsDetails($database, $page, $perPage, $offset)
{
    $inventoryId = getPostNumber("inventoryId");

    $countSql = "
        SELECT COUNT(*) total
        FROM inwentaryzacja_pozycja
        WHERE id_inwentaryzacji = ?
    ";

    $totalPages = getTotalPages($database, $countSql, "i", [$inventoryId], $perPage);

    $sql = "
        SELECT
            inwentaryzacja_pozycja.id AS 'Id',
            inwentaryzacja_pozycja.id_inwentaryzacji AS 'Id inwentaryzacji',
            CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Zinwentaryzował',
            COALESCE(magazyn.nazwa, inwentaryzacja_pozycja.nazwa_produktu_snapshot) AS 'Nazwa produktu',
            COALESCE(magazyn.jednostka, inwentaryzacja_pozycja.jednostka_snapshot) AS 'Jednostka',
            inwentaryzacja_pozycja.stan AS 'Stan',
            inwentaryzacja_pozycja.roznica AS 'Różnica',
            inwentaryzacja_pozycja.zatwierdzona AS 'Zatwierdzona'
        FROM inwentaryzacja_pozycja
        INNER JOIN uzytkownicy
            ON inwentaryzacja_pozycja.id_uzytkownika = uzytkownicy.id
        LEFT JOIN magazyn
            ON inwentaryzacja_pozycja.id_produktu = magazyn.id
        WHERE inwentaryzacja_pozycja.id_inwentaryzacji = ?
        ORDER BY inwentaryzacja_pozycja.id ASC
        LIMIT ? OFFSET ?
    ";

    [$stmt, $result] = fetchPreparedResult(
        $database,
        $sql,
        "iii",
        [$inventoryId, $perPage, $offset],
        "Błąd prepare szczegółów inwentaryzacji",
        "Błąd execute szczegółów inwentaryzacji"
    );

    renderAndExit($database, $stmt, $result, "inwentaryzacja_pozycja", $page, $totalPages);
}

function getDefaultTableConfig($table)
{
    $configs = [
        "uzytkownicy" => [
            "countSql" => "SELECT COUNT(*) total FROM uzytkownicy",
            "querySql" => "
                SELECT
                    id AS 'Id',
                    login AS 'Login',
                    CONCAT(imie, ' ', nazwisko) AS 'Imię i nazwisko',
                    status_konta AS 'Status konta',
                    rola AS 'Rola'
                FROM uzytkownicy
                ORDER BY id ASC
                LIMIT ? OFFSET ?
            "
        ],
        "magazyn" => [
            "countSql" => "SELECT COUNT(*) total FROM magazyn",
            "querySql" => "
                SELECT
                    magazyn.id AS 'Id',
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Dodał',
                    magazyn.nazwa AS 'Nazwa produktu',
                    magazyn.kategoria AS 'Kategoria',
                    magazyn.jednostka AS 'Jednostka',
                    magazyn.ilosc AS 'Ilość',
                    magazyn.lokalizacja AS 'Lokalizacja',
                    magazyn.uwagi AS 'Uwagi'
                FROM magazyn
                INNER JOIN uzytkownicy
                    ON magazyn.id_uzytkownika = uzytkownicy.id
                ORDER BY magazyn.id ASC
                LIMIT ? OFFSET ?
            "
        ],
        "wydania" => [
            "countSql" => "SELECT COUNT(*) total FROM wydania",
            "querySql" => "
                SELECT
                    wydania.id AS 'Id',
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Wydał',
                    wydania.data_i_godzina AS 'Data i godzina',
                    COALESCE(magazyn.nazwa, wydania.nazwa_produktu_snapshot) AS 'Nazwa produktu',
                    COALESCE(magazyn.jednostka, wydania.jednostka_snapshot) AS 'Jednostka',
                    wydania.ilosc AS 'Ilość',
                    wydania.powod AS 'Powód'
                FROM wydania
                INNER JOIN uzytkownicy
                    ON wydania.id_uzytkownika = uzytkownicy.id
                LEFT JOIN magazyn
                    ON magazyn.id = wydania.id_produktu
                ORDER BY wydania.id ASC
                LIMIT ? OFFSET ?
            "
        ],
        "inwentaryzacje" => [
            "countSql" => "SELECT COUNT(*) total FROM inwentaryzacje",
            "querySql" => "
                SELECT
                    inwentaryzacje.id AS 'Id',
                    inwentaryzacje.numer_inwentaryzacji AS 'Numer inwentaryzacji',
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Zinwentaryzował',
                    inwentaryzacje.data_utworzenia AS 'Data utworzenia',
                    inwentaryzacje.data_zatwierdzenia AS 'Data zatwierdzenia',
                    inwentaryzacje.zatwierdzona AS 'Zatwierdzona',
                    COUNT(inwentaryzacja_pozycja.id) AS 'Liczba pozycji'
                FROM inwentaryzacje
                INNER JOIN uzytkownicy
                    ON inwentaryzacje.id_uzytkownika = uzytkownicy.id
                LEFT JOIN inwentaryzacja_pozycja
                    ON inwentaryzacje.id = inwentaryzacja_pozycja.id_inwentaryzacji
                GROUP BY
                    inwentaryzacje.id,
                    inwentaryzacje.numer_inwentaryzacji,
                    uzytkownicy.imie,
                    uzytkownicy.nazwisko,
                    inwentaryzacje.data_utworzenia,
                    inwentaryzacje.data_zatwierdzenia,
                    inwentaryzacje.zatwierdzona
                ORDER BY inwentaryzacje.id ASC
                LIMIT ? OFFSET ?
            "
        ],
        "inwentaryzacja_pozycja" => [
            "countSql" => "SELECT COUNT(*) total FROM inwentaryzacja_pozycja",
            "querySql" => "
                SELECT
                    inwentaryzacja_pozycja.id AS 'Id',
                    inwentaryzacja_pozycja.id_inwentaryzacji AS 'Id inwentaryzacji',
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Zinwentaryzował',
                    COALESCE(magazyn.nazwa, inwentaryzacja_pozycja.nazwa_produktu_snapshot) AS 'Nazwa produktu',
                    COALESCE(magazyn.jednostka, inwentaryzacja_pozycja.jednostka_snapshot) AS 'Jednostka',
                    inwentaryzacja_pozycja.stan AS 'Stan',
                    inwentaryzacja_pozycja.roznica AS 'Różnica',
                    inwentaryzacja_pozycja.zatwierdzona AS 'Zatwierdzona'
                FROM inwentaryzacja_pozycja
                INNER JOIN uzytkownicy
                    ON inwentaryzacja_pozycja.id_uzytkownika = uzytkownicy.id
                LEFT JOIN magazyn
                    ON inwentaryzacja_pozycja.id_produktu = magazyn.id
                ORDER BY inwentaryzacja_pozycja.id ASC
                LIMIT ? OFFSET ?
            "
        ],
        "historia_operacji" => [
            "countSql" => "SELECT COUNT(*) total FROM historia_operacji",
            "querySql" => "
                SELECT
                    historia_operacji.id AS 'Id',
                    uzytkownicy.login AS 'Login',
                    historia_operacji.id_produktu AS 'Id produktu',
                    CONCAT(uzytkownicy.imie, ' ', uzytkownicy.nazwisko) AS 'Imię i nazwisko',
                    COALESCE(
                        magazyn.nazwa,
                        JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_przed, '$.nazwa')),
                        JSON_UNQUOTE(JSON_EXTRACT(historia_operacji.dane_po, '$.nazwa'))
                    ) AS 'Nazwa produktu',
                    historia_operacji.operacja AS 'Operacja',
                    historia_operacji.data_operacji AS 'Data operacji',
                    historia_operacji.dane_przed AS 'Dane przed operacją',
                    historia_operacji.dane_po AS 'Dane po operacji'
                FROM historia_operacji
                INNER JOIN uzytkownicy
                    ON historia_operacji.id_uzytkownika = uzytkownicy.id
                LEFT JOIN magazyn
                    ON historia_operacji.id_produktu = magazyn.id
                ORDER BY historia_operacji.id ASC
                LIMIT ? OFFSET ?
            "
        ]
    ];

    return $configs[$table] ?? null;
}

function renderArrayTableAndExit($database, array $rows, $table, $page, $totalPages)
{
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover align-middle table-sm mb-0">';

    if (!empty($rows)) {
        echo '<thead><tr>';
        foreach (array_keys($rows[0]) as $fieldName) {
            echo "<th class='text-center'>" . escapeHtml($fieldName) . "</th>";
        }
        if ($table === "magazyn" || $table === "uzytkownicy" || $table === "inwentaryzacja_pozycja") {
            echo "<th class='text-center'>Akcje</th>";
        }
        echo '</tr></thead>';
    }

    echo '<tbody>';

    foreach ($rows as $row) {
        renderTableRow($row, $table);
    }

    echo '</tbody></table></div>';

    renderPagination($page, $totalPages, $table);

    mysqli_close($database);
    exit;
}

function handleDefaultTable($database, $table, $page, $perPage, $offset)
{
    $config = getDefaultTableConfig($table);

    if (!$config) {
        http_response_code(404);
        exit("Brak konfiguracji dla tabeli");
    }

    $totalPages = getTotalPages($database, $config["countSql"], "", [], $perPage);

    [$stmt, $result] = fetchPreparedResult(
        $database,
        $config["querySql"],
        "ii",
        [$perPage, $offset],
        "Błąd prepare tabeli " . $table,
        "Błąd execute tabeli " . $table
    );

    renderAndExit($database, $stmt, $result, $table, $page, $totalPages);
}
?>