<?php
declare(strict_types=1);

require_once __DIR__ . "/../../PRACTICAL-10/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (expireSessionIfNeeded()) {
    respond(401, ["success" => false, "message" => "Your session expired. Please log in again."]);
}

try {
    if (!isset($_SESSION["user_id"]) && !restoreRememberedLogin()) {
        respond(401, ["success" => false, "message" => "Please log in to continue."]);
    }
} catch (mysqli_sql_exception $exception) {
    error_log("Practical 13 remembered login error: " . $exception->getMessage());
    respond(500, ["success" => false, "message" => "Could not verify your login. Try again."]);
}

if (($_SESSION["role"] ?? "") !== "admin") {
    respond(403, ["success" => false, "message" => "Admin access is required."]);
}

$_SESSION["last_activity"] = time();
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if (!in_array($method, ["GET", "POST", "PUT", "DELETE"], true)) {
    header("Allow: GET, POST, PUT, DELETE");
    respond(405, ["success" => false, "message" => "This request method is not supported."]);
}

$input = [];
if ($method !== "GET") {
    $csrf = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    if (
        !is_string($csrf)
        || !isset($_SESSION["csrf_token"])
        || !hash_equals($_SESSION["csrf_token"], $csrf)
    ) {
        respond(403, ["success" => false, "message" => "Invalid request token. Refresh the page and try again."]);
    }

    $raw = file_get_contents("php://input");
    $decoded = json_decode($raw === false ? "" : $raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        respond(400, ["success" => false, "message" => "Request body must be valid JSON."]);
    }
    $input = $decoded;
}

function validEvent(array $input): array
{
    $fields = [];
    foreach (["title", "category", "description", "location", "event_date"] as $key) {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            respond(422, ["success" => false, "message" => "Provide all event details as text."]);
        }
        $fields[$key] = trim($input[$key]);
    }

    if ($fields["title"] === "" || strlen($fields["title"]) > 150) {
        respond(422, ["success" => false, "message" => "Title is required (maximum 150 characters)."]);
    }
    if ($fields["category"] === "" || strlen($fields["category"]) > 60) {
        respond(422, ["success" => false, "message" => "Category is required (maximum 60 characters)."]);
    }
    if ($fields["description"] === "" || strlen($fields["description"]) > 5000) {
        respond(422, ["success" => false, "message" => "Description is required (maximum 5000 characters)."]);
    }
    if ($fields["location"] === "" || strlen($fields["location"]) > 150) {
        respond(422, ["success" => false, "message" => "Location is required (maximum 150 characters)."]);
    }

    $date = DateTime::createFromFormat("Y-m-d\\TH:i", $fields["event_date"]);
    $dateErrors = DateTime::getLastErrors();
    if (
        $date === false
        || ($dateErrors !== false && ($dateErrors["warning_count"] > 0 || $dateErrors["error_count"] > 0))
        || $date->format("Y-m-d\\TH:i") !== $fields["event_date"]
    ) {
        respond(422, ["success" => false, "message" => "Choose a valid event date and time."]);
    }

    $fields["event_date"] = $date->format("Y-m-d H:i:s");
    return $fields;
}

function validId(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        respond(422, ["success" => false, "message" => "Provide a valid event ID."]);
    }
    return $id;
}

try {
    require_once __DIR__ . "/../../PRACTICAL-9/db.php";

    if ($method === "GET") {
        $stmt = $conn->prepare(
            "SELECT id, title, category, description, location, event_date
             FROM managed_events ORDER BY event_date DESC"
        );
        $stmt->execute();
        $stmt->bind_result($id, $title, $category, $description, $location, $eventDate);
        $events = [];
        while ($stmt->fetch()) {
            $events[] = [
                "id" => (int) $id,
                "title" => (string) $title,
                "category" => (string) $category,
                "description" => (string) $description,
                "location" => (string) $location,
                "event_date" => (string) $eventDate,
            ];
        }
        $stmt->close();
        $conn->close();
        respond(200, ["success" => true, "events" => $events]);
    }

    if ($method === "POST") {
        $event = validEvent($input);
        $stmt = $conn->prepare(
            "INSERT INTO managed_events (title, category, description, location, event_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssss",
            $event["title"],
            $event["category"],
            $event["description"],
            $event["location"],
            $event["event_date"]
        );
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        respond(201, ["success" => true, "message" => "Event added successfully.", "id" => $id]);
    }

    $id = validId($input["id"] ?? null);

    if ($method === "PUT") {
        $event = validEvent($input);
        $check = $conn->prepare("SELECT id FROM managed_events WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows === 1;
        $check->close();
        if (!$exists) {
            $conn->close();
            respond(404, ["success" => false, "message" => "Event not found."]);
        }

        $stmt = $conn->prepare(
            "UPDATE managed_events
             SET title = ?, category = ?, description = ?, location = ?, event_date = ?
             WHERE id = ?"
        );
        $stmt->bind_param(
            "sssssi",
            $event["title"],
            $event["category"],
            $event["description"],
            $event["location"],
            $event["event_date"],
            $id
        );
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();
        $conn->close();

        respond(200, [
            "success" => true,
            "message" => $updated === 0
                ? "Event details were already up to date."
                : "Event updated successfully.",
        ]);
    }

    $stmt = $conn->prepare("DELETE FROM managed_events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    if ($deleted === 0) {
        respond(404, ["success" => false, "message" => "Event not found."]);
    }
    respond(200, ["success" => true, "message" => "Event deleted successfully."]);
} catch (mysqli_sql_exception $exception) {
    error_log("Practical 13 event API database error: " . $exception->getMessage());
    respond(500, [
        "success" => false,
        "message" => "Database operation failed. Import Practical 12 setup.sql and try again.",
    ]);
}
