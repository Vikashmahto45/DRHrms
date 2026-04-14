-- DB Migration: Silk & Indigo DSR Evolution (Phase 10)
-- Adding human-readable location name and custom project name support to DSR

ALTER TABLE dsr ADD COLUMN IF NOT EXISTS custom_project_name VARCHAR(255) NULL AFTER client_name;
ALTER TABLE dsr ADD COLUMN IF NOT EXISTS location_name TEXT NULL AFTER latitude;

-- Adding indexes for faster client lookup
ALTER TABLE dsr ADD INDEX IF NOT EXISTS idx_client_name (client_name);
ALTER TABLE dsr ADD INDEX IF NOT EXISTS idx_visit_date (visit_date);

-- Refiniting DSR Items for Manual Product Names
ALTER TABLE dsr_items MODIFY product_id INT NULL;
ALTER TABLE dsr_items ADD COLUMN IF NOT EXISTS manual_product_name VARCHAR(255) NULL AFTER product_id;
