
<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "studenthub";
$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);


if ($conn->connect_error) {
    die("Database connection failed: " .
        $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8mb4");

?>
