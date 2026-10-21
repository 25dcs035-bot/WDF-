USE studenthub;

ALTER TABLE users
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student';

CREATE TABLE remember_tokens (
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (selector),
    KEY idx_remember_tokens_expiry (expires_at),
    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
