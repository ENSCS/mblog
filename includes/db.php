<?php
// Single PDO connection point — every other file gets the connection through
// db(), never by instantiating PDO itself. Exceptions thrown here (bad
// credentials, DB down) propagate to set_exception_handler() in
// error-handling.php, which already logs and shows the friendly 500 page.
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    return $pdo;
}
