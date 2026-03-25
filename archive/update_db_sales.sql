-- Add Plans & Subscription Tracking to DRHrms
USE drhrms_db;

-- Plans/Pricing Table
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) DEFAULT NULL
);

-- Seed Plans
INSERT INTO plans (id, name, price, description) VALUES
(1, 'Starter',   999.00,  'Up to 10 employees, Basic HRMS'),
(2, 'Growth',    2499.00, 'Up to 50 employees, HRMS + Leads CRM'),
(3, 'Enterprise',4999.00, 'Unlimited employees, All modules')
ON DUPLICATE KEY UPDATE name=name;

-- Add plan_id to companies if not already there
ALTER TABLE companies MODIFY COLUMN plan_id INT DEFAULT 1;
ALTER TABLE companies ADD CONSTRAINT fk_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL;

-- Update demo company to use plan 2
UPDATE companies SET plan_id = 2 WHERE id = 1;

-- Subscriptions log (every time a company pays / is billed)
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('paid','pending','failed') DEFAULT 'paid',
    billing_date DATE DEFAULT (CURDATE()),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

-- Seed some subscription history
INSERT INTO subscriptions (company_id, plan_id, amount, status, billing_date) VALUES
(1, 2, 2499.00, 'paid', '2026-01-01'),
(1, 2, 2499.00, 'paid', '2026-02-01'),
(1, 2, 2499.00, 'paid', '2026-03-01');
