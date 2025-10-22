-- Remove the problematic trigger
DROP TRIGGER IF EXISTS cleanup_old_tokens;
 
-- Also clean up any old/used tokens
DELETE FROM password_reset_tokens WHERE expires_at < NOW() - INTERVAL 24 HOUR OR used = TRUE; 
DROP TRIGGER IF EXISTS cleanup_old_tokens;
 
-- Also clean up any old/used tokens
DELETE FROM password_reset_tokens WHERE expires_at < NOW() - INTERVAL 24 HOUR OR used = TRUE; 