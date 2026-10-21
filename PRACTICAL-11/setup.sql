USE studenthub;

CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    roll_number VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile VARCHAR(15) NULL,
    course VARCHAR(100) NULL,
    `year` INT NULL,
    department VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(50) NULL,
    password_hash VARCHAR(255) NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_roll_number (roll_number),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_course_year (course, `year`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
