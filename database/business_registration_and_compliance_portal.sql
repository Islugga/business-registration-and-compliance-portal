CREATE DATABASE IF NOT EXISTS business_registration_and_compliance_portal
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE business_registration_and_compliance_portal;

-- 1. USERS TABLE
CREATE TABLE users (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2. BUSINESSES TABLE
CREATE TABLE businesses (
    business_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    business_name VARCHAR(150) NOT NULL,
    business_type VARCHAR(100) NOT NULL,
    registration_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    cac_registration_number VARCHAR(100) DEFAULT NULL,
    registration_date DATE DEFAULT NULL,
    tin VARCHAR(50) DEFAULT NULL,
    bank_account_status VARCHAR(30) NOT NULL DEFAULT 'Not Opened',
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_account_number VARCHAR(50) DEFAULT NULL,

    PRIMARY KEY (business_id),
    KEY user_id (user_id),

    CONSTRAINT businesses_ibfk_1
    FOREIGN KEY (user_id)
    REFERENCES users(user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 3. BUSINESS DOCUMENTS TABLE
CREATE TABLE business_documents (
    document_id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    document_name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,

    PRIMARY KEY (document_id),
    KEY business_id (business_id),

    CONSTRAINT business_documents_ibfk_1
    FOREIGN KEY (business_id)
    REFERENCES businesses(business_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 4. COMPLIANCE REMINDERS TABLE
CREATE TABLE compliance_reminders (
    reminder_id INT(11) NOT NULL AUTO_INCREMENT,
    business_id INT(11) NOT NULL,
    requirement_name VARCHAR(150) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',

    PRIMARY KEY (reminder_id),
    KEY business_id (business_id),

    CONSTRAINT compliance_reminders_ibfk_1
    FOREIGN KEY (business_id)
    REFERENCES businesses(business_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 5. REGISTRATION GUIDANCE TABLE
CREATE TABLE registration_guidance (
    guidance_id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    official_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (guidance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 6. SUPPORT RESOURCES TABLE
CREATE TABLE support_resources (
    resource_id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    resource_url VARCHAR(255) DEFAULT NULL,

    PRIMARY KEY (resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
