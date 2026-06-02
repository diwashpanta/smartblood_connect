CREATE DATABASE IF NOT EXISTS smartblood_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartblood_connect;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS ml_predictions;
DROP TABLE IF EXISTS blood_issuance;
DROP TABLE IF EXISTS donation_appointments;
DROP TABLE IF EXISTS donor_notifications;
DROP TABLE IF EXISTS patient_notifications;
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS blood_inventory;
DROP TABLE IF EXISTS blood_requests;
DROP TABLE IF EXISTS donor_locations;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS donors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    name VARCHAR(120) NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'patient', 'donor') NOT NULL,
    phone VARCHAR(30) NULL,
    city VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    age TINYINT UNSIGNED NOT NULL,
    gender ENUM('male','female','other') NOT NULL DEFAULT 'other',
    date_of_birth DATE NULL,
    emergency_contact VARCHAR(30) NULL,
    hospital_preference VARCHAR(160) NULL,
    city VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    age TINYINT UNSIGNED NOT NULL,
    date_of_birth DATE NULL,
    weight DECIMAL(5,2) NOT NULL,
    medical_condition_status ENUM('healthy','temporary_deferral','chronic_issue') NOT NULL DEFAULT 'healthy',
    medical_condition ENUM('healthy','temporary_deferral','chronic_issue') NOT NULL DEFAULT 'healthy',
    availability_status ENUM('available','busy','inactive') NOT NULL DEFAULT 'available',
    available_status ENUM('available','busy','inactive') NOT NULL DEFAULT 'available',
    city VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    location_updated_at DATETIME NULL,
    last_donation_date DATE NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_eligible TINYINT(1) NOT NULL DEFAULT 1,
    past_donations INT NOT NULL DEFAULT 0,
    total_donations INT NOT NULL DEFAULT 0,
    response_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_donors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    designation VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE donor_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    label VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_at TIMESTAMP NULL,
    CONSTRAINT fk_donor_locations_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_needed INT NOT NULL,
    units_fulfilled INT NOT NULL DEFAULT 0,
    hospital_name VARCHAR(160) NOT NULL,
    hospital_address VARCHAR(255) NULL,
    hospital_city VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    hospital_latitude DECIMAL(10,7) NULL,
    hospital_longitude DECIMAL(10,7) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    urgency ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
    notes TEXT NULL,
    status ENUM('pending','matched','partially_fulfilled','fulfilled','rejected','cancelled') NOT NULL DEFAULT 'pending',
    request_status ENUM('pending','matched','partially_fulfilled','fulfilled','rejected','cancelled') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blood_requests_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_blood_requests_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE blood_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    donor_id INT NULL,
    request_id INT NULL,
    quantity_units INT NOT NULL DEFAULT 1,
    expiry_date DATE NOT NULL,
    status ENUM('available','reserved','issued','expired') NOT NULL DEFAULT 'available',
    collected_at DATE NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    action ENUM('add','reserve','issue','release','expire','adjust') NOT NULL,
    quantity_units INT NOT NULL DEFAULT 1,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes VARCHAR(255) NULL,
    performed_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_tx_inventory FOREIGN KEY (inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_tx_actor FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE donor_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    blood_request_id INT NOT NULL,
    donor_id INT NOT NULL,
    probability_score DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    predicted_probability DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
    matching_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    distance_km DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
    message VARCHAR(255) NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_donor (request_id, donor_id),
    CONSTRAINT fk_donor_notification_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_donor_notification_blood_request FOREIGN KEY (blood_request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_donor_notification_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE patient_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    request_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient_notification_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_patient_notification_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE donation_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    status ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointments_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE blood_issuance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    patient_id INT NOT NULL,
    inventory_id INT NOT NULL,
    units_issued INT NOT NULL DEFAULT 1,
    issued_by INT NULL,
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_issuance_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_issuance_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_issuance_inventory FOREIGN KEY (inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE,
    CONSTRAINT fk_issuance_actor FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ml_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    request_id INT NULL,
    model_name VARCHAR(80) NOT NULL,
    probability_score DECIMAL(6,4) NOT NULL,
    predicted_class ENUM('likely','unlikely') NOT NULL,
    confidence_label VARCHAR(20) NOT NULL,
    features_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ml_predictions_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    CONSTRAINT fk_ml_predictions_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT NULL,
    meta_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_lat_lng ON users(latitude, longitude);
CREATE INDEX idx_patients_lat_lng ON patients(latitude, longitude);
CREATE INDEX idx_donors_blood_group ON donors(blood_group);
CREATE INDEX idx_donors_available_status ON donors(availability_status);
CREATE INDEX idx_donors_lat_lng ON donors(latitude, longitude);
CREATE INDEX idx_requests_status ON blood_requests(status, request_status);
CREATE INDEX idx_requests_lat_lng ON blood_requests(latitude, longitude);
CREATE INDEX idx_inventory_status ON blood_inventory(status, expiry_date);
CREATE INDEX idx_notifications_status ON donor_notifications(status);
CREATE INDEX idx_notifications_donor_request ON donor_notifications(donor_id, request_id);
CREATE INDEX idx_appointments_status ON donation_appointments(status);

