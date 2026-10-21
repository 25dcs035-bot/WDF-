<?php
declare(strict_types=1);

ini_set("session.use_strict_mode", "1");
session_name("studenthub_session");
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax",
]);
session_start();

const SESSION_TIMEOUT = 1800;
const REMEMBER_COOKIE = "studenthub_remember";

function redirectByRole(): never
{
    $page = ($_SESSION["role"] ?? "student") === "admin"
        ? "admin_dashboard.php"
        : "dashboard.php";
    header("Location: " . $page);
    exit;
}

function clearRememberCookie(): void
{
    setcookie(REMEMBER_COOKIE, "", [
        "expires" => time() - 3600,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

function createRememberToken(mysqli $conn, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $validator);
    $expiresAt = date("Y-m-d H:i:s", time() + 30 * 24 * 60 * 60);

    $stmt = $conn->prepare(
        "INSERT INTO remember_tokens (selector, token_hash, user_id, expires_at)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssis", $selector, $tokenHash, $userId, $expiresAt);
    $stmt->execute();
    $stmt->close();

    setcookie(REMEMBER_COOKIE, $selector . ":" . $validator, [
        "expires" => time() + 30 * 24 * 60 * 60,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

function restoreRememberedLogin(): bool
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? "";
    $parts = is_string($cookie) ? explode(":", $cookie, 2) : [];
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        return false;
    }

    require_once __DIR__ . "/../PRACTICAL-9/db.php";
    $stmt = $conn->prepare(
        "SELECT t.token_hash, u.id, u.full_name, u.username, u.role
         FROM remember_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector = ? AND t.expires_at > CURRENT_TIMESTAMP
         LIMIT 1"
    );
    $stmt->bind_param("s", $parts[0]);
    $stmt->execute();
    $stmt->bind_result($storedHash, $userId, $fullName, $username, $role);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !hash_equals((string) $storedHash, hash("sha256", $parts[1]))) {
        $conn->close();
        clearRememberCookie();
        return false;
    }

    $delete = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
    $delete->bind_param("s", $parts[0]);
    $delete->execute();
    $delete->close();

    session_regenerate_id(true);
    $_SESSION["user_id"] = (int) $userId;
    $_SESSION["full_name"] = (string) $fullName;
    $_SESSION["username"] = (string) $username;
    $_SESSION["role"] = (string) $role;
    $_SESSION["last_activity"] = time();
    createRememberToken($conn, (int) $userId);
    $conn->close();
    return true;
}

function expireSessionIfNeeded(): bool
{
    if (!isset($_SESSION["user_id"])) {
        return false;
    }

    if (time() - (int) ($_SESSION["last_activity"] ?? 0) > SESSION_TIMEOUT) {
        clearRememberCookie();
        $_SESSION = [];
        session_destroy();
        return true;
    }

    return false;
}

function requireLogin(): void
{
    if (expireSessionIfNeeded()) {
        header("Location: login.php?timeout=1");
        exit;
    }

    if (!isset($_SESSION["user_id"]) && !restoreRememberedLogin()) {
        header("Location: login.php");
        exit;
    }

    $_SESSION["last_activity"] = time();
}
