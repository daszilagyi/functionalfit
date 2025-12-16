-- Initial Database Setup for FunctionalFit
-- Creates database with proper character set and collation

-- Ensure UTF-8 support for international characters
CREATE DATABASE IF NOT EXISTS functionalfit_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to application user
GRANT ALL PRIVILEGES ON functionalfit_db.* TO 'functionalfit'@'%';

-- Create test database for automated testing
CREATE DATABASE IF NOT EXISTS functionalfit_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON functionalfit_test.* TO 'functionalfit'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Set timezone to UTC
SET GLOBAL time_zone = '+00:00';

-- Verify database creation
SELECT 'Database setup completed successfully' AS status;
