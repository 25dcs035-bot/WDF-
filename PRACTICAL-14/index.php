<?php
declare(strict_types=1);

require_once __DIR__ . "/../PRACTICAL-10/auth.php";
requireLogin();

$role = (string) ($_SESSION["role"] ?? "student");
if ($role !== "admin") {
    header("Location: ../PRACTICAL-10/dashboard.php");
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

$error = "";
$stats = [
    "users" => 0,
    "admins" => 0,
    "students" => 0,
    "student_records" => 0,
    "events" => 0,
];
$registrations = [];

try {
    require_once __DIR__ . "/../PRACTICAL-9/db.php";

    $stmt = $conn->prepare(
        "SELECT
            (SELECT COUNT(*) FROM users),
            (SELECT COUNT(*) FROM users WHERE role = 'admin'),
            (SELECT COUNT(*) FROM users WHERE role = 'student'),
            (SELECT COUNT(*) FROM students),
            (SELECT COUNT(*) FROM managed_events)"
    );
    $stmt->execute();
    $stmt->bind_result($totalUsers, $adminUsers, $studentUsers, $studentRecords, $eventCount);
    $stmt->fetch();
    $stmt->close();
    $stats = [
        "users" => (int) $totalUsers,
        "admins" => (int) $adminUsers,
        "students" => (int) $studentUsers,
        "student_records" => (int) $studentRecords,
        "events" => (int) $eventCount,
    ];

    $stmt = $conn->prepare(
        "SELECT full_name, username, email, role, created_at
         FROM users
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );
    $stmt->execute();
    $stmt->bind_result($fullName, $username, $email, $userRole, $createdAt);
    while ($stmt->fetch()) {
        $registrations[] = [
            "full_name" => (string) $fullName,
            "username" => (string) $username,
            "email" => (string) $email,
            "role" => (string) $userRole,
            "created_at" => (string) $createdAt,
        ];
    }
    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log("Practical 14 dashboard database error: " . $exception->getMessage());
    http_response_code(500);
    $error = "Could not load dashboard statistics. Confirm Practical 9, 10, 11, and 12 database setup has been imported.";
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, Helvetica, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; color: #1a1a1a; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; align-items: center; gap: 8px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-size: .95rem; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { max-width: 1100px; margin: 36px auto; padding: 0 20px; }
        .welcome, .panel, .stat { border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .welcome { margin-bottom: 20px; padding: 25px; border-left: 5px solid #2196f3; }
        h1 { margin-bottom: 8px; font-size: 1.8rem; }
        p { line-height: 1.6; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat { padding: 20px; }
        .stat span { display: block; margin-bottom: 8px; color: #5b6470; font-weight: bold; }
        .stat strong { font-size: 2rem; color: #1a1a1a; }
        .panel { margin-bottom: 20px; padding: 24px; }
        h2 { margin-bottom: 14px; font-size: 1.3rem; }
        .quick-links { display: flex; flex-wrap: wrap; gap: 10px; }
        .quick-links a { padding: 10px 14px; border-radius: 5px; background: #4a4a4a; color: #fff; font-weight: bold; text-decoration: none; }
        .quick-links a:hover { background: #333; }
        .error { margin-bottom: 20px; padding: 12px 14px; border-radius: 6px; background: #fdecea; color: #a12622; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 10px; border-bottom: 1px solid #e1e5e9; text-align: left; }
        th { background: #f5f7f9; }
        .empty { padding: 16px; color: #5b6470; text-align: center; }
        .logout { padding: 9px 14px; border: 0; border-radius: 5px; background: #4a4a4a; color: #fff; font: inherit; font-weight: bold; cursor: pointer; }
        @media (max-width: 760px) {
            header { flex-direction: column; gap: 12px; padding: 15px; }
            nav { flex-wrap: wrap; justify-content: center; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="../logo.jpg" alt="Student Hub Logo">Student Hub Portal</div>
        <nav aria-label="Admin navigation">
            <?php if ($role === "admin"): ?>
                <a href="index.php" class="active" aria-current="page">Dashboard</a>
                <a href="../PRACTICAL-11/students.php">Students</a>
                <a href="../PRACTICAL-12/events.php">Manage events</a>
                <a href="../PRACTICAL-13/index.php">Fetch API</a>
                <a href="../home.html">Home</a>
            <?php else: ?>
                <a href="../PRACTICAL-10/dashboard.php">My dashboard</a>
                <a href="../home.html">Home</a>
            <?php endif; ?>
            <form method="post" action="../PRACTICAL-10/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e((string) ($_SESSION["csrf_token"] ?? "")) ?>">
                <button class="logout" type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <main>
        <section class="welcome">
            <h1>Admin Dashboard</h1>
            <p>Welcome, <?= e((string) ($_SESSION["full_name"] ?? "Administrator")) ?>. Here is the latest overview of Student Hub.</p>
        </section>

        <?php if ($error !== ""): ?>
            <div class="error" role="alert"><?= e($error) ?></div>
        <?php else: ?>
            <section class="stats" aria-label="Portal statistics">
                <article class="stat"><span>Total users</span><strong><?= (int) $stats["users"] ?></strong></article>
                <article class="stat"><span>Admin users</span><strong><?= (int) $stats["admins"] ?></strong></article>
                <article class="stat"><span>Student accounts</span><strong><?= (int) $stats["students"] ?></strong></article>
                <article class="stat"><span>Student records</span><strong><?= (int) $stats["student_records"] ?></strong></article>
                <article class="stat"><span>Events</span><strong><?= (int) $stats["events"] ?></strong></article>
            </section>

            <section class="panel">
                <h2>Admin tools</h2>
                <div class="quick-links">
                    <?php if ($role === "admin"): ?>
                        <a href="../PRACTICAL-11/students.php">Manage students</a>
                        <a href="../PRACTICAL-12/events.php">Manage events</a>
                        <a href="../PRACTICAL-13/index.php">Events Fetch API</a>
                    <?php else: ?>
                        <a href="../PRACTICAL-10/dashboard.php">Open my dashboard</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <h2>Latest registrations</h2>
                <?php if ($registrations === []): ?>
                    <p class="empty">No user registrations yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Registered</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $registration): ?>
                                    <tr>
                                        <td><?= e($registration["full_name"]) ?></td>
                                        <td><?= e($registration["username"]) ?></td>
                                        <td><?= e($registration["email"]) ?></td>
                                        <td><?= e(ucfirst($registration["role"])) ?></td>
                                        <td><?= e(date("M j, Y g:i A", strtotime($registration["created_at"]))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
