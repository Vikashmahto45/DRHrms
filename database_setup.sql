-- Create Database
CREATE DATABASE IF NOT EXISTS drhrms_db;
USE drhrms_db;

-- Table: companies (Tenants)
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    plan_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: users (Super Admins, Admins, Employees)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT DEFAULT NULL, -- Null for Super Admin
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'manager', 'staff') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Table: permissions_map (Module Toggle)
CREATE TABLE IF NOT EXISTS permissions_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Table: leads (CRM)
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    source VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'New',
    assigned_to INT DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Table: attendance (HRMS)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in DATETIME DEFAULT NULL,
    clock_out DATETIME DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
-- Table: projects (Execution Tracker)
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    branch_id INT NULL,
    sales_person_id INT NULL,
    client_name VARCHAR(255) NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    commission_percent DECIMAL(5,2) DEFAULT NULL,
    source VARCHAR(100) DEFAULT 'Walk-in',
    total_value DECIMAL(15,2) DEFAULT 0.00,
    advance_paid DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Pending Approval', 'Active', 'On Hold', 'Completed', 'Cancelled', 'Pending HQ Review') DEFAULT 'Pending HQ Review',
    progress_pct INT DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    verified_by INT NULL,
    custom_sales_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Table: franchise_payments (Revenue Split)
CREATE TABLE IF NOT EXISTS franchise_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    project_id INT DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL,
    commission_percent DECIMAL(5,2) DEFAULT NULL,
    client_name VARCHAR(255) NOT NULL,
    product_id INT DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    payment_date DATE NOT NULL,
    proof_file VARCHAR(255) NOT NULL,
    admin_cut DECIMAL(15,2) DEFAULT NULL,
    franchise_share DECIMAL(15,2) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payout_status ENUM('pending', 'paid') DEFAULT 'pending',
    payout_date DATETIME DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- Seed Data: Create Default Super Admin (Password is 'password')
INSERT INTO users (company_id, name, email, password, role, status)
VALUES (NULL, 'Super Admin', 'superadmin@loom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active')
ON DUPLICATE KEY UPDATE email=email;

-- Seed Data: Create Demo Company
INSERT INTO companies (id, name, status) VALUES (1, 'Vast IT Agency', 'active') ON DUPLICATE KEY UPDATE name=name;

-- Seed Data: Create Company Admin for Demo Company
INSERT INTO users (company_id, name, email, password, role, status)
VALUES (1, 'Company Admin', 'admin@vastagency.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active')
ON DUPLICATE KEY UPDATE email=email;

-- Enable Modules for Demo Company
INSERT INTO permissions_map (company_id, module_name, is_enabled) VALUES (1, 'hrms', 1) ON DUPLICATE KEY UPDATE is_enabled=is_enabled;
INSERT INTO permissions_map (company_id, module_name, is_enabled) VALUES (1, 'leads', 1) ON DUPLICATE KEY UPDATE is_enabled=is_enabled;
