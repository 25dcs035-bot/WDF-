
<?php

require_once "db.php";

echo "<h2>StudentHub Database Connection</h2>";
echo "<p>Database connected successfully!</p>";

$result = $conn->query("SHOW TABLES");

echo "<h3>Tables in studenthub:</h3>";
echo "<ul>";

while ($row = $result->fetch_array()) {
    echo "<li>" . htmlspecialchars($row[0]) . "</li>";
}

echo "</ul>";

$conn->close();

?>
