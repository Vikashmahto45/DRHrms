-- Advanced SaaS Management Updates
USE drhrms_db;

-- 1. Update Companies table with resource limits and subscription tracking
ALTER TABLE companies 
ADD COLUMN subscription_end_date DATETIME DEFAULT NULL,
ADD COLUMN user_limit INT DEFAULT 10,
ADD COLUMN lead_limit INT DEFAULT 100,
ADD COLUMN storage_limit_mb INT DEFAULT 500,
ADD COLUMN last_login_at DATETIME DEFAULT NULL;

-- 2. Update Users table with admin details and 2FA
ALTER TABLE users 
ADD COLUMN admin_type ENUM('full', 'limited') DEFAULT 'full',
ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0,
ADD COLUMN last_login DATETIME DEFAULT NULL;

-- 3. Update Plans table with default limits
ALTER TABLE plans
ADD COLUMN default_user_limit INT DEFAULT 10,
ADD COLUMN default_lead_limit INT DEFAULT 100,
ADD COLUMN default_storage_mb INT DEFAULT 500;

-- Update existing plans with defaults
UPDATE plans SET default_user_limit = 10, default_lead_limit = 100, default_storage_mb = 500 WHERE id = 1; -- Starter
UPDATE plans SET default_user_limit = 50, default_lead_limit = 1000, default_storage_mb = 2048 WHERE id = 2; -- Growth
UPDATE plans SET default_user_limit = 9999, default_lead_limit = 99999, default_storage_mb = 10240 WHERE id = 3; -- Enterprise

-- 4. Create Activity Logs table (Audit Trail)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL, -- NULL for superadmin actions
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Create System Settings table (Maintenance Mode, etc.)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed maintenance mode setting
INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- 6. Update existing company with a dummy expiry date for testing (30 days from now)
UPDATE companies SET subscription_end_date = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = 1;
