-- Update Database for Demo Requests
USE drhrms_db;

CREATE TABLE IF NOT EXISTS demo_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    status ENUM('pending', 'contacted', 'converted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert dummy data for demo requests
INSERT INTO demo_requests (name, email, company_name, phone, status) VALUES 
('John Doe', 'john@example.com', 'Tech Innovators', '555-0192', 'pending'),
('Sarah Smith', 'sarah@designco.com', 'Design Co', '555-0193', 'contacted');
