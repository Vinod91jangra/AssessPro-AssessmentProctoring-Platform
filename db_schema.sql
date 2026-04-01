-- Assessment Platform Database Schema

CREATE DATABASE IF NOT EXISTS assessment_platform;
USE assessment_platform;

-- Companies
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    logo VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admins (super admin + company admins)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','company_admin') DEFAULT 'company_admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

-- Assessments
CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT DEFAULT 60,
    pass_score INT DEFAULT 60,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Questions
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq','true_false','short_answer') DEFAULT 'mcq',
    options JSON,
    correct_answer TEXT,
    marks INT DEFAULT 1,
    order_index INT DEFAULT 0,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);

-- Candidates
CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    company_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Test Invitations / Sessions
CREATE TABLE test_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    candidate_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    status ENUM('pending','in_progress','completed','expired') DEFAULT 'pending',
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    score INT DEFAULT 0,
    total_marks INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    passed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)
);

-- Answers submitted
CREATE TABLE candidate_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT,
    is_correct TINYINT(1) DEFAULT 0,
    marks_obtained INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES test_sessions(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- Chat messages (candidate <-> admin)
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender_type ENUM('candidate','admin') NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES test_sessions(id)
);

-- Proctoring flags
CREATE TABLE proctoring_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    flag_type VARCHAR(100) NOT NULL,
    description TEXT,
    flagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES test_sessions(id)
);

-- Default super admin
INSERT INTO admins (name, email, password, role) VALUES 
('Super Admin', 'admin@assesspro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');
-- Default password: password

-- Sample Company
INSERT INTO companies (name, email, password) VALUES 
('TechCorp Solutions', 'techcorp@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO admins (company_id, name, email, password, role) VALUES 
(1, 'HR Manager', 'hr@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'company_admin');
