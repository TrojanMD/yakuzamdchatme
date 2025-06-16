-- For login attempt tracking
CREATE TABLE login_attempts (
    ip VARCHAR(45) PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt DATETIME NOT NULL
);

-- For remember me tokens
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add CSRF token to sessions table if using database sessions
ALTER TABLE sessions ADD csrf_token VARCHAR(64);
