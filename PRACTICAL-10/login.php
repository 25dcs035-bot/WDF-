<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

if (expireSessionIfNeeded()) {
    $timedOut = true;
} else {
    $timedOut = isset($_GET["timeout"]);
}

if (isset($_SESSION["user_id"])) {
    redirectByRole();
}

if (!$timedOut && restoreRememberedLogin()) {
    redirectByRole();
}

$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
        http_response_code(400);
        $error = "Invalid request. Please reload the page and try again.";
    } else {
        $email = strtolower(trim(is_string($_POST["email"] ?? null) ? $_POST["email"] : ""));
        $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === "") {
            $error = "Enter a valid email and password.";
        } else {
            try {
                require_once __DIR__ . "/../PRACTICAL-9/db.php";
                $stmt = $conn->prepare(
                    "SELECT id, full_name, username, password_hash, role
                     FROM users WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->bind_result($userId, $fullName, $username, $passwordHash, $role);
                $found = $stmt->fetch();
                $stmt->close();

                if ($found && password_verify($password, (string) $passwordHash)) {
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = (int) $userId;
                    $_SESSION["full_name"] = (string) $fullName;
                    $_SESSION["username"] = (string) $username;
                    $_SESSION["role"] = (string) $role;
                    $_SESSION["last_activity"] = time();
                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                    if (($_POST["remember"] ?? "") === "1") {
                        createRememberToken($conn, (int) $userId);
                    }

                    $conn->close();
                    redirectByRole();
                }

                $conn->close();
                $error = "Email or password is incorrect.";
            } catch (mysqli_sql_exception $exception) {
                error_log("Student Hub login database error: " . $exception->getMessage());
                http_response_code(500);
                $error = "Login is unavailable. Check the Practical 10 database setup.";
            }
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Hub Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; gap: 10px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { width: min(100% - 32px, 460px); margin: 40px auto; }
        .card { padding: 32px; border-radius: 12px; background: #fff; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        h1, .subtitle { text-align: center; }
        h1 { margin-bottom: 8px; }
        .subtitle { margin-bottom: 24px; color: #666; }
        label { display: block; margin: 14px 0 6px; font-weight: bold; }
        input[type=email], input[type=password] { width: 100%; padding: 11px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; }
        .remember { display: flex; align-items: center; gap: 8px; margin: 16px 0; font-size: 14px; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 6px; background: #4a4a4a; color: white; font-weight: bold; cursor: pointer; }
        button:hover { background: #333; }
        .message { margin-bottom: 16px; color: #b42318; line-height: 1.5; }
        .notice { color: #16733c; }
        .register { margin-top: 18px; text-align: center; }
        .register a { color: #4a4a4a; font-weight: bold; }
        @media (max-width: 700px) { header { flex-direction: column; gap: 12px; padding: 15px; } nav { flex-wrap: wrap; justify-content: center; } }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="../logo.jpg" alt="Student Hub Logo">Student Hub Portal</div>
        <nav aria-label="Main navigation">
            <a href="../home.html">Home</a>
            <a href="../About.html">About</a>
            <a href="login.php" class="active" aria-current="page">Login</a>
            <a href="../PRACTICAL-9/Register.html">Register</a>
        </nav>
    </header>
    <main>
        <section class="card">
            <h1>Login to Student Hub</h1>
            <p class="subtitle">Use your registered email and password.</p>
            <?php if ($error !== ""): ?>
                <p class="message" role="alert"><?= e($error) ?></p>
            <?php elseif (isset($_GET["logged_out"])): ?>
                <p class="message notice">You have been logged out.</p>
            <?php elseif ($timedOut): ?>
                <p class="message">Your session expired. Please log in again.</p>
            <?php endif; ?>
            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="254" autocomplete="email">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me for 30 days</label>
                <button type="submit">Login</button>
            </form>
            <p class="register">Don't have an account? <a href="../PRACTICAL-9/Register.html">Register here</a></p>
        </section>
    </main>
</body>
</html>
