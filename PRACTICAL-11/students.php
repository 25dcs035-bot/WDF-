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

$message = (string) ($_SESSION["students_message"] ?? "");
$messageType = (string) ($_SESSION["students_message_type"] ?? "success");
unset($_SESSION["students_message"], $_SESSION["students_message_type"]);
$errors = [];
$connectionError = false;
$editStudent = null;
$search = trim(is_string($_GET["search"] ?? null) ? $_GET["search"] : "");
$search = strlen($search) <= 100 ? $search : substr($search, 0, 100);
$courseFilter = trim(is_string($_GET["course"] ?? null) ? $_GET["course"] : "");
$courseFilter = strlen($courseFilter) <= 100 ? $courseFilter : "";
$filterYear = filter_var($_GET["year"] ?? "0", FILTER_VALIDATE_INT);
$filterYear = $filterYear !== false && $filterYear >= 1 && $filterYear <= 4 ? $filterYear : 0;
$editId = filter_var($_GET["edit"] ?? null, FILTER_VALIDATE_INT);

try {
    require_once __DIR__ . "/../PRACTICAL-9/db.php";
} catch (mysqli_sql_exception $exception) {
    error_log("Student management connection error: " . $exception->getMessage());
    http_response_code(500);
    $connectionError = true;
    $errors[] = "Could not connect to MySQL. Check that MySQL is running and db.php is configured.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$connectionError) {
    $csrf = $_POST["csrf_token"] ?? "";
    if (!is_string($csrf) || !hash_equals($_SESSION["csrf_token"], $csrf)) {
        http_response_code(400);
        $errors[] = "Invalid form request. Reload the page and try again.";
    } else {
        $action = postValue("action");
        $studentId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);

        try {
            if ($action === "delete") {
                if ($studentId === false || $studentId < 1) {
                    $errors[] = "Select a valid student record to delete.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
                    $stmt->bind_param("i", $studentId);
                    $stmt->execute();
                    $deleted = $stmt->affected_rows;
                    $stmt->close();
                    $_SESSION["students_message"] = $deleted
                        ? "Student record deleted successfully."
                        : "Student record was not found.";
                    $_SESSION["students_message_type"] = $deleted ? "success" : "error";
                    header("Location: students.php");
                    exit;
                }
            } elseif ($action === "save") {
                $fullName = postValue("full_name");
                $rollNumber = strtoupper(postValue("roll_number"));
                $email = strtolower(postValue("email"));
                $course = postValue("course");
                $year = filter_var($_POST["study_year"] ?? null, FILTER_VALIDATE_INT);
                $department = postValue("department");
                $isEdit = $studentId !== false && $studentId > 0;

                if (postValue("id") !== "" && $studentId === false) {
                    $errors[] = "Select a valid student record to edit.";
                }
                if (!preg_match("/^[\\p{L}\\p{M} .'-]{2,100}$/u", $fullName)) {
                    $errors[] = "Enter a valid full name (2–100 letters and common name punctuation).";
                }
                if (!preg_match("/^[A-Z0-9-]{3,30}$/", $rollNumber)) {
                    $errors[] = "Roll number must be 3–30 letters, numbers, or hyphens.";
                }
                if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = "Enter a valid email address.";
                }
                if ($course === "" || strlen($course) > 100) {
                    $errors[] = "Course is required and must be at most 100 characters.";
                }
                if ($year === false || $year < 1 || $year > 4) {
                    $errors[] = "Select a valid study year.";
                }
                if ($department === "" || strlen($department) > 100) {
                    $errors[] = "Department is required and must be at most 100 characters.";
                }

                if ($errors === []) {
                    if ($isEdit) {
                        $stmt = $conn->prepare(
                            "UPDATE students
                             SET full_name = ?, roll_number = ?, email = ?, course = ?, `year` = ?, department = ?
                             WHERE student_id = ?"
                        );
                        $stmt->bind_param(
                            "ssssisi",
                            $fullName,
                            $rollNumber,
                            $email,
                            $course,
                            $year,
                            $department,
                            $studentId
                        );
                        $stmt->execute();
                        $updated = $stmt->affected_rows;
                        $stmt->close();
                        $_SESSION["students_message"] = $updated
                            ? "Student record updated successfully."
                            : "No changes were made to the student record.";
                    } else {
                        $stmt = $conn->prepare(
                            "INSERT INTO students (full_name, roll_number, email, course, `year`, department)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->bind_param(
                            "ssssis",
                            $fullName,
                            $rollNumber,
                            $email,
                            $course,
                            $year,
                            $department
                        );
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION["students_message"] = "Student record added successfully.";
                    }

                    $_SESSION["students_message_type"] = "success";
                    header("Location: students.php");
                    exit;
                }
            } else {
                $errors[] = "Unknown action. Choose add, edit, or delete.";
            }
        } catch (mysqli_sql_exception $exception) {
            if ((int) $exception->getCode() === 1062) {
                $errors[] = "That roll number or email is already in use by another student.";
            } else {
                error_log("Student management database error: " . $exception->getMessage());
                http_response_code(500);
                $errors[] = "The database operation failed. Check the Practical 11 setup and try again.";
            }
        }
    }
}

if (!$connectionError) {
try {
    if ($editId !== false && $editId > 0) {
        $stmt = $conn->prepare(
            "SELECT student_id, full_name, roll_number, email, course, `year`, department
             FROM students WHERE student_id = ?"
        );
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $stmt->bind_result($id, $fullName, $rollNumber, $email, $course, $year, $department);
        if ($stmt->fetch()) {
            $editStudent = [
                "id" => (int) $id,
                "full_name" => (string) $fullName,
                "roll_number" => (string) $rollNumber,
                "email" => (string) $email,
                "course" => (string) $course,
                "study_year" => (int) $year,
                "department" => (string) $department,
            ];
        } else {
            $message = "Student record was not found.";
            $messageType = "error";
        }
        $stmt->close();
    }

    $like = "%" . $search . "%";
    $stmt = $conn->prepare(
        "SELECT student_id, full_name, roll_number, email, course, `year`, department
         FROM students
         WHERE (full_name LIKE ? OR roll_number LIKE ? OR email LIKE ? OR course LIKE ?)
           AND (? = 0 OR `year` = ?)
           AND (? = '' OR course = ?)
         ORDER BY full_name"
    );
    $stmt->bind_param(
        "ssssiiss",
        $like,
        $like,
        $like,
        $like,
        $filterYear,
        $filterYear,
        $courseFilter,
        $courseFilter
    );
    $stmt->execute();
    $stmt->bind_result($rowId, $rowName, $rowRoll, $rowEmail, $rowCourse, $rowYear, $rowDepartment);
    $students = [];
    while ($stmt->fetch()) {
        $students[] = [
            "id" => (int) $rowId,
            "full_name" => (string) $rowName,
            "roll_number" => (string) $rowRoll,
            "email" => (string) $rowEmail,
            "course" => (string) $rowCourse,
            "study_year" => (int) $rowYear,
            "department" => (string) $rowDepartment,
        ];
    }
    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log("Student management read error: " . $exception->getMessage());
    http_response_code(500);
    if ((int) $exception->getCode() === 1146) {
        $errors[] = "The students table is missing from studenthub. In phpMyAdmin, select studenthub, open the SQL tab, and run Practical 11 setup.sql.";
    } else {
        $errors[] = "Could not load student records (MySQL error " . (int) $exception->getCode() . "). Check the database and students table, then reload.";
    }
    $students = [];
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
} else {
    $students = [];
}

if ($errors !== [] && $message === "") {
    $messageType = "error";
    $message = "Please review the message below.";
}

$postedId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
$isEditingPost = $_SERVER["REQUEST_METHOD"] === "POST"
    && postValue("action") === "save"
    && $postedId !== false
    && $postedId > 0;
$editMode = $editStudent !== null || $isEditingPost;
$formValues = $editStudent ?? [
    "id" => $isEditingPost ? $postedId : 0,
    "full_name" => postValue("full_name"),
    "roll_number" => postValue("roll_number"),
    "email" => postValue("email"),
    "course" => postValue("course"),
    "study_year" => (int) (postValue("study_year") ?: 0),
    "department" => postValue("department"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Student Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; align-items: center; gap: 10px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { max-width: 1200px; margin: 32px auto; padding: 0 20px; }
        .card { margin-bottom: 20px; padding: 24px; border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        h1, h2 { margin-bottom: 14px; }
        .message { margin-bottom: 18px; padding: 12px 14px; border-radius: 6px; background: #e8f5ed; color: #17653a; }
        .message.error { background: #fdecea; color: #a12622; }
        .message ul { margin: 8px 0 0 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #c8cdd3; border-radius: 5px; font: inherit; }
        .actions { display: flex; align-items: end; gap: 8px; }
        button, .button { display: inline-block; padding: 10px 14px; border: 0; border-radius: 5px; background: #4a4a4a; color: #fff; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #333; }
        .button.secondary { background: #68717c; }
        .button.danger { background: #a12622; }
        .filter-row { display: grid; grid-template-columns: minmax(220px, 1fr) 160px minmax(160px, 1fr) auto; align-items: end; gap: 12px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 10px; border-bottom: 1px solid #e1e5e9; text-align: left; vertical-align: middle; }
        th { background: #f5f7f9; }
        .row-actions { display: flex; align-items: center; gap: 7px; }
        .row-actions form { display: inline; }
        .row-actions button { padding: 8px 10px; font-size: 13px; }
        .empty { padding: 18px; color: #555; text-align: center; }
        @media (max-width: 850px) {
            header { flex-direction: column; gap: 12px; padding: 15px; }
            nav { flex-wrap: wrap; justify-content: center; }
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-row { grid-template-columns: 1fr 150px; }
            .filter-row button { grid-column: 1 / -1; }
        }
        @media (max-width: 560px) {
            .form-grid, .filter-row { grid-template-columns: 1fr; }
            .actions { align-items: stretch; flex-direction: column; }
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
            <a href="students.php" class="active" aria-current="page">Students</a>
            <form method="post" action="../PRACTICAL-10/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <main>
        <h1>Student Management</h1>
        <?php if ($message !== ""): ?>
            <div class="message <?= $messageType === "error" ? "error" : "" ?>" role="status">
                <?= e($message) ?>
                <?php if ($errors !== []): ?>
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2><?= $editMode ? "Edit student" : "Add student" ?></h2>
            <form method="post" action="students.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $formValues["id"] ?>">
                <div class="form-grid">
                    <div><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="100" value="<?= e((string) $formValues["full_name"]) ?>"></div>
                    <div><label for="roll_number">Roll number</label><input id="roll_number" name="roll_number" required maxlength="30" value="<?= e((string) $formValues["roll_number"]) ?>"></div>
                    <div><label for="email">Email</label><input type="email" id="email" name="email" required maxlength="254" value="<?= e((string) $formValues["email"]) ?>"></div>
                    <div><label for="course">Course</label><input id="course" name="course" required maxlength="100" value="<?= e((string) $formValues["course"]) ?>"></div>
                    <div><label for="study_year">Year</label>
                        <select id="study_year" name="study_year" required>
                            <option value="">Select year</option>
                            <?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?>
                                <option value="<?= $yearOption ?>" <?= (int) $formValues["study_year"] === $yearOption ? "selected" : "" ?>>Year <?= $yearOption ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div><label for="department">Department</label><input id="department" name="department" required maxlength="100" value="<?= e((string) $formValues["department"]) ?>"></div>
                    <div class="actions">
                        <button type="submit"><?= $editMode ? "Save changes" : "Add student" ?></button>
                        <?php if ($editMode): ?><a class="button secondary" href="students.php">Cancel</a><?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Search and filter</h2>
            <form class="filter-row" method="get" action="students.php">
                <div><label for="search">Search name, roll number, email, or course</label><input id="search" name="search" maxlength="100" value="<?= e($search) ?>"></div>
                <div><label for="yearFilter">Filter by year</label>
                    <select id="yearFilter" name="year">
                        <option value="0">All years</option>
                        <?php for ($yearOption = 1; $yearOption <= 4; $yearOption++): ?>
                            <option value="<?= $yearOption ?>" <?= $filterYear === $yearOption ? "selected" : "" ?>>Year <?= $yearOption ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div><label for="courseFilter">Filter by course</label><input id="courseFilter" name="course" maxlength="100" value="<?= e($courseFilter) ?>" placeholder="Exact course name"></div>
                <button type="submit">Search</button>
            </form>
        </section>

        <section class="card">
            <h2>Student records (<?= count($students) ?>)</h2>
            <?php if ($students === []): ?>
                <p class="empty">No student records found.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Roll number</th><th>Email</th><th>Course</th><th>Year</th><th>Department</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= e($student["full_name"]) ?></td>
                                    <td><?= e($student["roll_number"]) ?></td>
                                    <td><?= e($student["email"]) ?></td>
                                    <td><?= e($student["course"]) ?></td>
                                    <td><?= $student["study_year"] ?></td>
                                    <td><?= e($student["department"]) ?></td>
                                    <td><div class="row-actions">
                                        <a class="button secondary" href="students.php?edit=<?= $student["id"] ?>">Edit</a>
                                        <form method="post" action="students.php" onsubmit="return confirm('Delete this student record?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $student["id"] ?>">
                                            <button class="danger" type="submit">Delete</button>
                                        </form>
                                    </div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
