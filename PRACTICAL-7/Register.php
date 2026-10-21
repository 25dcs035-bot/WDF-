<?php
echo "<h1>PHP is working!</h1>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<p>POST data received!</p>";
    print_r($_POST);
    exit;
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    
    $fullname = trim($_POST["fullname"] ?? "");
    $rollnumber = trim($_POST["rollnumber"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmpassword = $_POST["confirmpassword"] ?? "";
    $errors = [];

   
    if (!preg_match("/^[a-zA-Z ]{2,}$/", $fullname)) {
        $errors[] = "Invalid full name.";
    }

    if (!preg_match("/^[a-zA-Z0-9]{3,15}$/", $rollnumber)) {
        $errors[] = "Invalid roll number.";
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Mobile number must contain 10 digits.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if ($course == "") {
        $errors[] = "Course is required.";
    }

    if (!in_array($year, ["1", "2", "3", "4"])) {
        $errors[] = "Invalid year.";
    }

    if ($department == "") {
        $errors[] = "Department is required.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirmpassword) {
        $errors[] = "Passwords do not match.";
    }

    
    if (!empty($errors)) {

        echo "<h2>Registration Failed</h2>";

        foreach ($errors as $error) {
            echo "<p>" . htmlspecialchars($error) . "</p>";
        }

        echo '<a href="Register.html">Go Back</a>';

        exit;
    }


    $fullname = htmlspecialchars($fullname, ENT_QUOTES, "UTF-8");
    $rollnumber = htmlspecialchars($rollnumber, ENT_QUOTES, "UTF-8");
    $mobile = htmlspecialchars($mobile, ENT_QUOTES, "UTF-8");
    $email = htmlspecialchars($email, ENT_QUOTES, "UTF-8");
    $course = htmlspecialchars($course, ENT_QUOTES, "UTF-8");
    $year = htmlspecialchars($year, ENT_QUOTES, "UTF-8");
    $department = htmlspecialchars($department, ENT_QUOTES, "UTF-8");

    $file = "students.csv";

    $handle = fopen($file, "a");

    if ($handle === false) {
        die("Error: Unable to open CSV file.");
    }
    if (filesize($file) == 0) {
        fputcsv($handle, [
            "Full Name",
            "Roll Number",
            "Mobile",
            "Email",
            "Course",
            "Year",
            "Department"
        ]);
    }

    fputcsv($handle, [
        $fullname,
         $rollnumber,
        $mobile,
        $email,
        $course,
        $year,
        $department
    ]);

    fclose($handle);
    echo "<h2>Registration Successful!</h2>";
    echo "<p>Welcome, " . htmlspecialchars($fullname) . ".</p>";
    echo "<p>Your registration has been saved successfully.</p>";

    echo '<a href="Register.html">Register Another Student</a>';

} else {

    echo "Invalid request.";
}

?>
