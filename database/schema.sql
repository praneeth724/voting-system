-- ============================================================
-- Sri Lanka Online Voting System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS vote_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vote_system_db;

-- ============================================================
-- ADMINS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- LOCAL AUTHORITIES TABLE (Community Leaders / District Officers)
-- ============================================================
CREATE TABLE IF NOT EXISTS authorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    district VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- VOTERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS voters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nic VARCHAR(12) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('pending', 'authority_approved', 'approved', 'rejected') DEFAULT 'pending',
    authority_id INT NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (authority_id) REFERENCES authorities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ELECTIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    election_type ENUM('Presidential', 'Parliamentary', 'Provincial', 'Local') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id)
) ENGINE=InnoDB;

-- ============================================================
-- CANDIDATES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    party VARCHAR(100) NOT NULL,
    party_color VARCHAR(7) DEFAULT '#333333',
    symbol_text VARCHAR(50),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VOTES TABLE (anonymous - only stores counts per candidate)
-- ============================================================
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    ip_address VARCHAR(45),
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (election_id, voter_id),
    FOREIGN KEY (election_id) REFERENCES elections(id),
    FOREIGN KEY (voter_id) REFERENCES voters(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)
) ENGINE=InnoDB;

-- ============================================================
-- OTP TOKENS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('login', 'vote_confirm') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT DATA (run setup.php to insert with hashed passwords)
-- ============================================================
-- Default Admin   : username = admin         | password = Admin@123
-- Authority 1     : username = auth_colombo  | password = Auth@123
-- Authority 2     : username = auth_kandy    | password = Auth@123
-- Authority 3     : username = auth_galle    | password = Auth@123
-- NOTE: Use setup.php to create these accounts with proper bcrypt hashes
