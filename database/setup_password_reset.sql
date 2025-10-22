-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES applicants(applicant_id)
);

-- Drop existing indexes if they exist
DROP INDEX IF EXISTS idx_token ON password_reset_tokens;
DROP INDEX IF EXISTS idx_expires_at ON password_reset_tokens;
DROP INDEX IF EXISTS idx_user_reset ON password_reset_tokens;

-- Create index for faster token lookups
CREATE INDEX idx_token ON password_reset_tokens(token);

-- Create index for expiry checks
CREATE INDEX idx_expires_at ON password_reset_tokens(expires_at);

-- Create index for user lookups
CREATE INDEX idx_user_reset ON password_reset_tokens(user_id);

-- Drop existing trigger if it exists
DROP TRIGGER IF EXISTS cleanup_old_tokens;

-- Add cleanup trigger to remove old tokens
DELIMITER //
CREATE TRIGGER cleanup_old_tokens BEFORE INSERT ON password_reset_tokens
FOR EACH ROW
BEGIN
    -- Delete tokens older than 24 hours or already used
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() - INTERVAL 24 HOUR 
    OR used = TRUE;
END;
//
DELIMITER ; 
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES applicants(applicant_id)
);

-- Drop existing indexes if they exist
DROP INDEX IF EXISTS idx_token ON password_reset_tokens;
DROP INDEX IF EXISTS idx_expires_at ON password_reset_tokens;
DROP INDEX IF EXISTS idx_user_reset ON password_reset_tokens;

-- Create index for faster token lookups
CREATE INDEX idx_token ON password_reset_tokens(token);

-- Create index for expiry checks
CREATE INDEX idx_expires_at ON password_reset_tokens(expires_at);

-- Create index for user lookups
CREATE INDEX idx_user_reset ON password_reset_tokens(user_id);

-- Drop existing trigger if it exists
DROP TRIGGER IF EXISTS cleanup_old_tokens;

-- Add cleanup trigger to remove old tokens
DELIMITER //
CREATE TRIGGER cleanup_old_tokens BEFORE INSERT ON password_reset_tokens
FOR EACH ROW
BEGIN
    -- Delete tokens older than 24 hours or already used
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() - INTERVAL 24 HOUR 
    OR used = TRUE;
END;
//
DELIMITER ; 