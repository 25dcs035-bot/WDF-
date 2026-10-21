<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
requireLogin();

if (($_SESSION["role"] ?? "student") === "admin") {
    header("Location: admin_dashboard.php");
    exit;
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
    <title>Dashboard - Student Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; align-items: center; gap: 12px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .card { padding: 28px; border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .welcome { margin-bottom: 18px; border-left: 5px solid #2196f3; }
        h1 { margin-bottom: 8px; font-size: 1.8rem; }
        p { line-height: 1.6; }
        button { padding: 10px 18px; border: 0; border-radius: 5px; background: #4a4a4a; color: #fff; font-weight: bold; cursor: pointer; }
        @media (max-width: 700px) { header { flex-direction: column; gap: 12px; padding: 15px; } nav { flex-wrap: wrap; justify-content: center; } }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="../logo.jpg" alt="Student Hub Logo">Student Hub Portal</div>
        <nav aria-label="Main navigation">
            <a href="../home.html">Home</a>
            <a href="dashboard.php" class="active" aria-current="page">Dashboard</a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>
    <main>
        <section class="card welcome">
            <h1>Welcome, <?= e((string) $_SESSION["full_name"]) ?>!</h1>
            <p>You are logged in as a student. This page is available only to signed-in users.</p>
        </section>
        <section class="card">
            <h2>Student Dashboard</h2>
            <p>Your protected Student Hub dashboard is ready.</p>
        </section>
    </main>
</body>
</html>
