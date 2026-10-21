<?php
declare(strict_types=1);

require_once __DIR__ . "/../PRACTICAL-10/auth.php";
requireLogin();

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../PRACTICAL-10/dashboard.php");
    exit;
}

$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function postValue(string $key): string
{
    $value = $_POST[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

function posterPath(string $filename): string
{
    if (!preg_match("/^[a-f0-9]{32}\\.(jpg|png|webp)$/", $filename)) {
        throw new RuntimeException("Invalid stored poster filename.");
    }
    return __DIR__ . DIRECTORY_SEPARATOR . "posters" . DIRECTORY_SEPARATOR . $filename;
}

function savePoster(array $upload): ?string
{
    if (!isset($upload["error"]) || is_array($upload["error"])) {
        throw new RuntimeException("Invalid poster upload.");
    }
    if ($upload["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($upload["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Poster upload failed. Choose the image again.");
    }
    if (!isset($upload["tmp_name"], $upload["size"]) || !is_uploaded_file($upload["tmp_name"])) {
        throw new RuntimeException("The uploaded poster could not be verified.");
    }
    if ((int) $upload["size"] > 2 * 1024 * 1024) {
        throw new RuntimeException("Poster must be 2 MB or smaller.");
    }

    $image = @getimagesize($upload["tmp_name"]);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($upload["tmp_name"]);
    $extensions = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
    ];

    if (
        $image === false
        || !isset($extensions[$mime])
        || ($image["mime"] ?? "") !== $mime
    ) {
        throw new RuntimeException("Poster must be a valid JPG, PNG, or WebP image.");
    }

    $filename = bin2hex(random_bytes(16)) . "." . $extensions[$mime];
    $directory = __DIR__ . DIRECTORY_SEPARATOR . "posters";
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("The poster upload folder could not be created.");
    }
    if (!move_uploaded_file($upload["tmp_name"], posterPath($filename))) {
        throw new RuntimeException("Could not save the poster. Check folder permissions.");
    }

    return $filename;
}

function removePoster(?string $filename): void
{
    if ($filename !== null && $filename !== "") {
        $path = posterPath($filename);
        if (is_file($path) && !unlink($path)) {
            error_log("Could not remove event poster: " . $filename);
        }
    }
}

$message = (string) ($_SESSION["events_message"] ?? "");
$messageType = (string) ($_SESSION["events_message_type"] ?? "success");
unset($_SESSION["events_message"], $_SESSION["events_message_type"]);
$errors = [];
$editEvent = null;
$newPosterFile = null;
$formValues = [
    "id" => 0,
    "title" => "",
    "category" => "",
    "description" => "",
    "location" => "",
    "event_date" => "",
];

try {
    require_once __DIR__ . "/../PRACTICAL-9/db.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrf = $_POST["csrf_token"] ?? "";
        if (!is_string($csrf) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
            http_response_code(400);
            $errors[] = "Invalid form request. Reload the page and try again.";
        } else {
            $action = postValue("action");
            $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);

            if ($action === "delete") {
                if ($id === false || $id < 1) {
                    $errors[] = "Select a valid event to delete.";
                } else {
                    $stmt = $conn->prepare("SELECT poster_file FROM managed_events WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->bind_result($posterToRemove);
                    $found = $stmt->fetch();
                    $stmt->close();

                    if (!$found) {
                        $errors[] = "Event not found; no record was deleted.";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM managed_events WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $stmt->close();
                        removePoster(is_string($posterToRemove) ? $posterToRemove : null);
                        $_SESSION["events_message"] = "Event deleted successfully.";
                        $_SESSION["events_message_type"] = "success";
                        header("Location: events.php");
                        exit;
                    }
                }
            } elseif ($action === "save") {
                $formValues = [
                    "id" => $id !== false && $id > 0 ? $id : 0,
                    "title" => postValue("title"),
                    "category" => postValue("category"),
                    "description" => postValue("description"),
                    "location" => postValue("location"),
                    "event_date" => postValue("event_date"),
                ];
                $isEdit = $formValues["id"] > 0;
                $eventDate = DateTime::createFromFormat("Y-m-d\\TH:i", $formValues["event_date"]);
                $dateErrors = DateTime::getLastErrors();

                if (postValue("id") !== "" && $id === false) {
                    $errors[] = "Select a valid event to edit.";
                }
                if ($formValues["title"] === "" || strlen($formValues["title"]) > 150) {
                    $errors[] = "Enter an event title (up to 150 characters).";
                }
                if ($formValues["category"] === "" || strlen($formValues["category"]) > 60) {
                    $errors[] = "Enter an event category (up to 60 characters).";
                }
                if ($formValues["description"] === "" || strlen($formValues["description"]) > 5000) {
                    $errors[] = "Enter a description (up to 5000 characters).";
                }
                if ($formValues["location"] === "" || strlen($formValues["location"]) > 150) {
                    $errors[] = "Enter an event location (up to 150 characters).";
                }
                if (
                    $eventDate === false
                    || ($dateErrors !== false && ($dateErrors["warning_count"] > 0 || $dateErrors["error_count"] > 0))
                    || $eventDate->format("Y-m-d\\TH:i") !== $formValues["event_date"]
                ) {
                    $errors[] = "Choose a valid event date and time.";
                }

                if ($errors === []) {
                    $poster = savePoster($_FILES["poster"] ?? []);
                    $newPosterFile = $poster;
                    $dateForDatabase = $eventDate->format("Y-m-d H:i:s");

                    if ($isEdit) {
                        $stmt = $conn->prepare("SELECT poster_file FROM managed_events WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $stmt->bind_result($oldPoster);
                        $found = $stmt->fetch();
                        $stmt->close();

                        if (!$found) {
                            removePoster($poster);
                            $errors[] = "Event not found; no changes were saved.";
                        } else {
                            $posterFile = $poster ?? (is_string($oldPoster) ? $oldPoster : null);
                            $stmt = $conn->prepare(
                                "UPDATE managed_events
                                 SET title = ?, category = ?, description = ?, location = ?, event_date = ?, poster_file = ?
                                 WHERE id = ?"
                            );
                            $stmt->bind_param(
                                "ssssssi",
                                $formValues["title"],
                                $formValues["category"],
                                $formValues["description"],
                                $formValues["location"],
                                $dateForDatabase,
                                $posterFile,
                                $id
                            );
                            $stmt->execute();
                            $stmt->close();
                            if ($poster !== null && is_string($oldPoster)) {
                                removePoster($oldPoster);
                            }
                            $_SESSION["events_message"] = "Event updated successfully.";
                            $_SESSION["events_message_type"] = "success";
                            header("Location: events.php");
                            exit;
                        }
                    } else {
                        $stmt = $conn->prepare(
                            "INSERT INTO managed_events (title, category, description, location, event_date, poster_file)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->bind_param(
                            "ssssss",
                            $formValues["title"],
                            $formValues["category"],
                            $formValues["description"],
                            $formValues["location"],
                            $dateForDatabase,
                            $poster
                        );
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION["events_message"] = "Event added successfully.";
                        $_SESSION["events_message_type"] = "success";
                        header("Location: events.php");
                        exit;
                    }
                }
            } else {
                $errors[] = "Unknown action. Choose add, edit, or delete.";
            }
        }
    }

    $editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);
    if ($editId !== false && $editId > 0 && $_SERVER["REQUEST_METHOD"] !== "POST") {
        $stmt = $conn->prepare(
            "SELECT id, title, category, description, location, event_date, poster_file
             FROM managed_events WHERE id = ?"
        );
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $stmt->bind_result($eventId, $title, $category, $description, $location, $eventDate, $posterFile);
        if ($stmt->fetch()) {
            $editEvent = [
                "id" => (int) $eventId,
                "title" => (string) $title,
                "category" => (string) $category,
                "description" => (string) $description,
                "location" => (string) $location,
                "event_date" => date("Y-m-d\\TH:i", strtotime((string) $eventDate)),
                "poster_file" => is_string($posterFile) ? $posterFile : "",
            ];
            $formValues = $editEvent;
        } else {
            $message = "Event not found.";
            $messageType = "error";
        }
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT id, title, category, description, location, event_date, poster_file
         FROM managed_events ORDER BY event_date DESC"
    );
    $stmt->execute();
    $stmt->bind_result($eventId, $title, $category, $description, $location, $eventDate, $posterFile);
    $events = [];
    while ($stmt->fetch()) {
        $events[] = [
            "id" => (int) $eventId,
            "title" => (string) $title,
            "category" => (string) $category,
            "description" => (string) $description,
            "location" => (string) $location,
            "event_date" => (string) $eventDate,
            "poster_file" => is_string($posterFile) ? $posterFile : "",
        ];
    }
    $stmt->close();
    $conn->close();
} catch (RuntimeException $exception) {
    removePoster($newPosterFile);
    $errors[] = $exception->getMessage();
    $events = $events ?? [];
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
} catch (mysqli_sql_exception $exception) {
    removePoster($newPosterFile);
    error_log("Event management database error: " . $exception->getMessage());
    http_response_code(500);
    $errors[] = "Event operation failed. Import Practical 12 setup.sql and check the database.";
    $events = $events ?? [];
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

if ($errors !== [] && $message === "") {
    $message = "Please review the message below.";
    $messageType = "error";
}

$editMode = $editEvent !== null || (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && postValue("action") === "save"
    && (int) $formValues["id"] > 0
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Student Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; align-items: center; gap: 10px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
        .card { margin-bottom: 20px; padding: 24px; border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        h1, h2 { margin-bottom: 14px; }
        .message { margin-bottom: 18px; padding: 12px 14px; border-radius: 6px; background: #e8f5ed; color: #17653a; }
        .message.error { background: #fdecea; color: #a12622; }
        .message ul { margin: 8px 0 0 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #c8cdd3; border-radius: 5px; font: inherit; }
        textarea { min-height: 100px; resize: vertical; }
        .hint { display: block; margin-top: 5px; color: #5b6470; font-size: 13px; }
        .actions { display: flex; align-items: center; gap: 8px; }
        button, .button { display: inline-block; padding: 10px 14px; border: 0; border-radius: 5px; background: #4a4a4a; color: #fff; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #333; }
        .button.secondary { background: #68717c; }
        .button.danger { background: #a12622; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 18px; }
        .event { overflow: hidden; border: 1px solid #e0e0e0; border-radius: 10px; background: #fff; }
        .poster { display: block; width: 100%; height: 180px; object-fit: cover; background: #e9edf2; }
        .no-poster { display: grid; height: 180px; place-items: center; background: #e9edf2; color: #555; }
        .event-content { padding: 16px; }
        .event-content h3 { margin-bottom: 8px; }
        .event-content p { margin: 6px 0; line-height: 1.5; overflow-wrap: anywhere; }
        .event-actions { display: flex; align-items: center; gap: 8px; margin-top: 14px; }
        .event-actions form { margin-left: auto; }
        .event-actions button { padding: 8px 10px; }
        .empty { padding: 16px; color: #555; text-align: center; }
        @media (max-width: 700px) {
            header { flex-direction: column; gap: 12px; padding: 15px; }
            nav { flex-wrap: wrap; justify-content: center; }
        }
        @media (max-width: 520px) {
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            .card { padding: 18px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="../logo.jpg" alt="Student Hub Logo">Student Hub Portal</div>
        <nav aria-label="Main navigation">
            <a href="../home.html">Home</a>
            <a href="../PRACTICAL-10/admin_dashboard.php">Admin Dashboard</a>
            <a href="../Events.html">Events page</a>
            <a href="events.php" class="active" aria-current="page">Manage events</a>
            <form method="post" action="../PRACTICAL-10/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <main>
        <h1>Event Management</h1>
        <?php if ($message !== ""): ?>
            <div class="message <?= $messageType === "error" ? "error" : "" ?>" role="status">
                <?= e($message) ?>
                <?php if ($errors !== []): ?>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2><?= $editMode ? "Edit event" : "Add event" ?></h2>
            <form method="post" action="events.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $formValues["id"] ?>">
                <div class="form-grid">
                    <div><label for="title">Event title</label><input id="title" name="title" required maxlength="150" value="<?= e((string) $formValues["title"]) ?>"></div>
                    <div><label for="category">Category</label><input id="category" name="category" required maxlength="60" value="<?= e((string) $formValues["category"]) ?>"></div>
                    <div><label for="location">Location</label><input id="location" name="location" required maxlength="150" value="<?= e((string) $formValues["location"]) ?>"></div>
                    <div><label for="event_date">Date and time</label><input type="datetime-local" id="event_date" name="event_date" required value="<?= e((string) $formValues["event_date"]) ?>"></div>
                    <div class="full"><label for="description">Description</label><textarea id="description" name="description" required maxlength="5000"><?= e((string) $formValues["description"]) ?></textarea></div>
                    <div class="full">
                        <label for="poster">Event poster (JPG, PNG, or WebP; maximum 2 MB)</label>
                        <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <small class="hint">Optional. Leave empty when editing to keep the current poster.</small>
                        <?php if ($editEvent !== null && $editEvent["poster_file"] !== ""): ?>
                            <p class="hint">Current poster: <a href="posters/<?= e($editEvent["poster_file"]) ?>" target="_blank" rel="noopener">View poster</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="full actions">
                        <button type="submit"><?= $editMode ? "Save changes" : "Add event" ?></button>
                        <?php if ($editMode): ?><a class="button secondary" href="events.php">Cancel</a><?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Events (<?= count($events) ?>)</h2>
            <?php if ($events === []): ?>
                <p class="empty">No events have been added yet.</p>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <article class="event">
                            <?php if ($event["poster_file"] !== ""): ?>
                                <img class="poster" src="posters/<?= e($event["poster_file"]) ?>" alt="Poster for <?= e($event["title"]) ?>">
                            <?php else: ?>
                                <div class="no-poster">No poster uploaded</div>
                            <?php endif; ?>
                            <div class="event-content">
                                <h3><?= e($event["title"]) ?></h3>
                                <p><strong><?= e($event["category"]) ?></strong></p>
                                <p><?= nl2br(e($event["description"])) ?></p>
                                <p><strong>When:</strong> <?= e(date("M j, Y g:i A", strtotime($event["event_date"]))) ?></p>
                                <p><strong>Where:</strong> <?= e($event["location"]) ?></p>
                                <div class="event-actions">
                                    <a class="button secondary" href="events.php?edit=<?= $event["id"] ?>">Edit</a>
                                    <form method="post" action="events.php" onsubmit="return confirm('Delete this event and its poster?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $event["id"] ?>">
                                        <button class="danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
