<?php
declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function showResult(
    string $heading,
    string $message,
    array $errors = [],
    bool $success = false
): void {
    $status = $success ? "success" : "error";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escape($heading) ?></title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                min-height: 100vh;
                background-color: #eef2f6;
                color: #333333;
                font-family: Arial, Helvetica, sans-serif;
            }

            header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 40px;
                background: #fff;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .logo-container {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .logo-container img {
                border-radius: 8px;
                object-fit: cover;
            }

            .logo-container strong {
                color: #1a1a1a;
                font-size: 1.5rem;
            }

            nav {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            nav a {
                padding: 8px 14px;
                border-radius: 6px;
                color: #4a4a4a;
                font-size: 0.95rem;
                font-weight: bold;
                text-decoration: none;
            }

            nav a:hover,
            nav a.active {
                background-color: #4a4a4a;
                color: #fff;
            }

            main {
                max-width: 1000px;
                margin: 40px auto;
                padding: 0 20px;
            }

            .result-card {
                padding: 32px;
                border-radius: 10px;
                background: #fff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }

            h1 {
                margin-bottom: 12px;
                font-size: 1.8rem;
            }

            p {
                line-height: 1.6;
            }

            .success { color: #16733c; }
            .error { color: #b42318; }

            ul {
                margin: 12px 0 12px 22px;
                line-height: 1.8;
            }

            .return-link {
                display: inline-block;
                margin-top: 18px;
                color: #333333;
                font-weight: bold;
            }

            @media (max-width: 768px) {
                header {
                    padding: 15px 20px;
                }

                nav {
                    flex-wrap: wrap;
                    justify-content: flex-end;
                    gap: 6px;
                }

                nav a {
                    padding: 8px;
                }

                .logo-container strong {
                    font-size: 1.1rem;
                }
            }

            @media (max-width: 520px) {
                header {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 12px;
                }

                nav {
                    justify-content: flex-start;
                }

                .result-card {
                    padding: 22px;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="logo-container">
                <img src="../logo.jpg" alt="Student Hub Logo" width="50" height="50">
                <strong>Student Hub Portal</strong>
            </div>
            <nav aria-label="Main navigation">
                <a href="../login.html">Login</a>
                <a href="../Dashboard.html">Dashboard</a>
                <a href="../Events.html">Events</a>
                <a href="../Contact.html">Contact</a>
                <a href="../About.html">About</a>
                <a href="../home.html">Home</a>
                <a href="Register.html" class="active" aria-current="page">Register</a>
            </nav>
        </header>
        <main>
            <section class="result-card">
                <h1 class="<?= $status ?>"><?= escape($heading) ?></h1>
                <p><?= escape($message) ?></p>
                <?php if ($errors !== []): ?>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="return-link" href="Register.html">Return to registration</a>
            </section>
        </main>
    </body>
    </html>
    <?php
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    showResult("Invalid request", "Please submit the registration form.");
    exit;
}

function postString(string $key): string
{
    $value = $_POST[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

$fullName = postString("full_name");
$username = strtolower(postString("username"));
$email = strtolower(postString("email"));
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";
$errors = [];

if (!preg_match("/^[\\p{L}\\p{M} .'-]{2,100}$/u", $fullName)) {
    $errors[] = "Enter a valid full name using 2–100 letters, spaces, periods, apostrophes, or hyphens.";
}

if (!preg_match("/^[a-z0-9_]{3,30}$/", $username)) {
    $errors[] = "Username must be 3–30 characters and contain only letters, numbers, or underscores.";
}

if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = "Enter a valid email address.";
}

if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
    $errors[] = "Password must be between 8 and 72 UTF-8 bytes.";
}

if (!is_string($confirmPassword) || $password !== $confirmPassword) {
    $errors[] = "Passwords do not match.";
}

if ($errors !== []) {
    http_response_code(422);
    showResult("Registration failed", "Please correct the following:", $errors);
    exit;
}

try {
    require_once __DIR__ . "/db.php";

    $check = $conn->prepare(
        "SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1"
    );
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->bind_result($existingUsername, $existingEmail);
    $hasDuplicate = $check->fetch();
    $check->close();

    if ($hasDuplicate) {
        if (strcasecmp((string) $existingUsername, $username) === 0) {
            $errors[] = "That username is already registered.";
        }
        if (strcasecmp((string) $existingEmail, $email) === 0) {
            $errors[] = "That email address is already registered.";
        }

        http_response_code(409);
        showResult("Registration failed", "Please use different account details.", $errors);
        $conn->close();
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare(
        "INSERT INTO users (full_name, username, email, password_hash) VALUES (?, ?, ?, ?)"
    );
    $insert->bind_param("ssss", $fullName, $username, $email, $passwordHash);
    $insert->execute();
    $insert->close();
    $conn->close();

    showResult(
        "Registration successful",
        "Your account has been created. Welcome, " . $fullName . "!",
        [],
        true
    );
} catch (mysqli_sql_exception $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    if ((int) $exception->getCode() === 1062) {
        http_response_code(409);
        showResult(
            "Registration failed",
            "That username or email address is already registered. Please use different details."
        );
        exit;
    }

    error_log("Registration database error: " . $exception->getMessage());
    http_response_code(500);
    showResult(
        "Registration unavailable",
        "Your registration could not be completed. Check the database setup and try again."
    );
}
