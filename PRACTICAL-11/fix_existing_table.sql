USE studenthub;

ALTER TABLE students
    MODIFY COLUMN username VARCHAR(50) NULL,
    MODIFY COLUMN password_hash VARCHAR(255) NULL;
