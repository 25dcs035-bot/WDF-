<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv("DB_HOST") ?: "localhost";
$dbUser = getenv("DB_USER") ?: "root";
$dbPassword = getenv("DB_PASSWORD") ?: "";
$dbName = getenv("DB_NAME") ?: "studenthub";

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
$conn->set_charset("utf8mb4");
