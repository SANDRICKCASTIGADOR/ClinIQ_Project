-- Hospital Task Management System Database
-- Run this SQL file to set up the database

CREATE DATABASE IF NOT EXISTS hospital_tms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_tms;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('main_admin', 'doctor_admin', 'nurse') NOT NULL,
    doctor_id INT DEFAULT NULL COMMENT 'For nurses: which doctor manages them',
    specialty VARCHAR(100) DEFAULT NULL COMMENT 'For doctors',
    department VARCHAR(100) DEFAULT NULL,
    avatar_initials VARCHAR(5) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nurse_id INT NOT NULL,
    doctor_id INT NOT NULL,
    task_title VARCHAR(200) NOT NULL,
    task_description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    task_date DATE NOT NULL,
    due_time TIME DEFAULT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    completed_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Nurse notes on completion',
    patient_ref VARCHAR(50) DEFAULT NULL COMMENT 'Patient # / room, for drug-interaction checks',
    medication_name VARCHAR(150) DEFAULT NULL COMMENT 'Generic drug name (openFDA lookup)',
    drug_warning TEXT DEFAULT NULL COMMENT 'Interaction flags found via openFDA',
    calendar_event_id VARCHAR(255) DEFAULT NULL,
    calendar_sync_status ENUM('none','synced','failed') NOT NULL DEFAULT 'none',
    reminder_sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (nurse_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Integration tables (Brevo email log, openFDA cache)
CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL COMMENT 'recipient',
    type ENUM('assigned','reminder','completed','cancelled') NOT NULL,
    recipient VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message_id VARCHAR(255) DEFAULT NULL COMMENT 'Brevo messageId',
    status ENUM('sent','delivered','failed','bounced') NOT NULL DEFAULT 'sent',
    last_event VARCHAR(50) DEFAULT NULL,
    error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (task_id), INDEX (status)
);

CREATE TABLE IF NOT EXISTS drug_cache (
    drug_key VARCHAR(150) PRIMARY KEY,
    payload MEDIUMTEXT NOT NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- SEED DATA - Default Accounts
-- =============================================

-- Main Admin (password: Admin@123)
INSERT INTO users (name, email, password, role, specialty, department, avatar_initials) VALUES
('Dr. System Admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWEIChW', 'main_admin', NULL, 'Administration', 'SA');

-- Doctor Admin 1 (password: Doctor1@123)
INSERT INTO users (name, email, password, role, specialty, department, avatar_initials) VALUES
('Dr. James Rivera', 'dr.rivera@hospital.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'doctor_admin', 'Cardiology', 'Cardiac Care Unit', 'JR');

-- Doctor Admin 2 (password: Doctor2@123)
INSERT INTO users (name, email, password, role, specialty, department, avatar_initials) VALUES
('Dr. Sofia Chen', 'dr.chen@hospital.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'doctor_admin', 'Neurology', 'Neurology Ward', 'SC');

-- Nurses for Doctor Rivera (id=2) (password: Nurse@123)
INSERT INTO users (name, email, password, role, doctor_id, department, avatar_initials) VALUES
('Nurse Maria Santos', 'maria.santos@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWEIChW', 'nurse', 2, 'Cardiac Care Unit', 'MS'),
('Nurse John Dela Cruz', 'john.delacruz@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWEIChW', 'nurse', 2, 'Cardiac Care Unit', 'JD');

-- Nurses for Doctor Chen (id=3) (password: Nurse@123)
INSERT INTO users (name, email, password, role, doctor_id, department, avatar_initials) VALUES
('Nurse Anna Reyes', 'anna.reyes@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWEIChW', 'nurse', 3, 'Neurology Ward', 'AR'),
('Nurse Carlos Mendoza', 'carlos.mendoza@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWEIChW', 'nurse', 3, 'Neurology Ward', 'CM');

-- Sample Tasks
INSERT INTO tasks (nurse_id, doctor_id, task_title, task_description, priority, task_date, due_time, status) VALUES
(4, 2, 'Morning Vital Signs', 'Check and record blood pressure, heart rate, temperature, and oxygen saturation for all patients in Room 101-105.', 'high', CURDATE(), '08:00:00', 'completed'),
(4, 2, 'Administer Medications', 'Administer prescribed morning medications: Metoprolol 25mg, Aspirin 81mg for Patient #1042.', 'urgent', CURDATE(), '09:00:00', 'in_progress'),
(5, 2, 'ECG Monitoring', 'Perform and document ECG for Patient #1087. Report any irregularities immediately.', 'high', CURDATE(), '10:00:00', 'pending'),
(6, 3, 'Neurological Assessment', 'Complete Glasgow Coma Scale assessment for patients in Neuro Ward B. Document findings.', 'high', CURDATE(), '08:30:00', 'completed'),
(7, 3, 'MRI Preparation', 'Prepare Patient #2031 for MRI scan. Ensure consent forms are signed and metal checklist completed.', 'medium', CURDATE(), '11:00:00', 'pending');

-- Activity Log Seed
INSERT INTO activity_log (user_id, action, details) VALUES
(1, 'System Initialized', 'Hospital TMS system initialized with default accounts'),
(2, 'Task Created', 'Created task: Morning Vital Signs for Nurse Maria Santos'),
(2, 'Task Created', 'Created task: Administer Medications for Nurse Maria Santos'),
(3, 'Task Created', 'Created task: Neurological Assessment for Nurse Anna Reyes');