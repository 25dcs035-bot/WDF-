<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

$csrf = $_POST["csrf_token"] ?? "";
if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
    || !is_string($csrf)
    || !isset($_SESSION["csrf_token"])
    || !hash_equals($_SESSION["csrf_token"], $csrf)
) {
    http_response_code(400);
    exit("Invalid logout request.");
}

$cookie = $_COOKIE[REMEMBER_COOKIE] ?? "";
$parts = is_string($cookie) ? explode(":", $cookie, 2) : [];
if (count($parts) === 2 && ctype_xdigit($parts[0])) {
    try {
        require_once __DIR__ . "/../PRACTICAL-9/db.php";
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->bind_param("s", $parts[0]);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (mysqli_sql_exception $exception) {
        error_log("Student Hub logout database error: " . $exception->getMessage());
    }
}

clearRememberCookie();
$_SESSION = [];
session_destroy();
header("Location: login.php?logged_out=1");
exit;
