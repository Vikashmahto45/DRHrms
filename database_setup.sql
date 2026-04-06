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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
