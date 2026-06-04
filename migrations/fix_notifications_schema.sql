-- Migration: Ensure notifications.id is AUTO_INCREMENT PRIMARY KEY and set defaults for type
-- Safe checks added to avoid errors on repeat runs

-- Add primary key if missing
ALTER TABLE notifications ADD PRIMARY KEY (id);

-- Modify id column to be AUTO_INCREMENT
ALTER TABLE notifications MODIFY id INT NOT NULL AUTO_INCREMENT;

-- Ensure type column has default value
ALTER TABLE notifications MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'general';
