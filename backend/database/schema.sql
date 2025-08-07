-- Busylancer Platform Database Schema

-- Users table (both candidates and employers)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('candidate', 'employer') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Candidate profiles
CREATE TABLE candidate_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    bio TEXT,
    hourly_rate DECIMAL(10,2),
    experience_level ENUM('beginner', 'intermediate', 'expert'),
    availability ENUM('full_time', 'part_time', 'contract'),
    location VARCHAR(255),
    website VARCHAR(255),
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    portfolio_url VARCHAR(255),
    resume_file VARCHAR(255),
    total_earnings DECIMAL(12,2) DEFAULT 0,
    total_jobs_completed INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Employer profiles  
CREATE TABLE employer_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    company_description TEXT,
    company_size ENUM('1-10', '11-50', '51-200', '201-500', '500+'),
    industry VARCHAR(100),
    website VARCHAR(255),
    company_logo VARCHAR(255),
    location VARCHAR(255),
    total_spent DECIMAL(12,2) DEFAULT 0,
    total_jobs_posted INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Skills table
CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Candidate skills junction table
CREATE TABLE candidate_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'advanced', 'expert'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_candidate_skill (candidate_id, skill_id)
);

-- Job categories
CREATE TABLE job_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES job_categories(id) ON DELETE SET NULL
);

-- Jobs table
CREATE TABLE jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    job_type ENUM('fixed_price', 'hourly') NOT NULL,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    hourly_rate_min DECIMAL(10,2),
    hourly_rate_max DECIMAL(10,2),
    duration_estimate VARCHAR(50),
    experience_level ENUM('beginner', 'intermediate', 'expert'),
    location VARCHAR(255),
    is_remote BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'active', 'closed', 'completed', 'cancelled') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    applications_count INT DEFAULT 0,
    views_count INT DEFAULT 0,
    deadline DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL
);

-- Job skills junction table
CREATE TABLE job_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    skill_id INT NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job_skill (job_id, skill_id)
);

-- Job applications
CREATE TABLE job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    candidate_id INT NOT NULL,
    cover_letter TEXT,
    proposed_rate DECIMAL(10,2),
    estimated_duration VARCHAR(50),
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, candidate_id)
);

-- Contracts/Projects
CREATE TABLE contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    employer_id INT NOT NULL,
    candidate_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    contract_type ENUM('fixed_price', 'hourly') NOT NULL,
    total_amount DECIMAL(10,2),
    hourly_rate DECIMAL(10,2),
    estimated_hours INT,
    status ENUM('active', 'completed', 'cancelled', 'disputed') DEFAULT 'active',
    start_date DATETIME,
    end_date DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE
);

-- Messages
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    job_id INT NULL,
    contract_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
);

-- Reviews/Ratings
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (contract_id, reviewer_id)
);

-- Saved jobs (bookmarks)
CREATE TABLE saved_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved_job (candidate_id, job_id)
);

-- Saved candidates
CREATE TABLE saved_candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employer_id INT NOT NULL,
    candidate_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employer_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved_candidate (employer_id, candidate_id)
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Job alerts
CREATE TABLE job_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    keywords TEXT,
    category_id INT,
    location VARCHAR(255),
    job_type ENUM('fixed_price', 'hourly'),
    min_budget DECIMAL(10,2),
    max_budget DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE SET NULL
);

-- Insert some default data
INSERT INTO job_categories (name, description) VALUES
('Web Development', 'Frontend, Backend, and Full Stack Development'),
('Mobile Development', 'iOS, Android, and Cross-platform Apps'),
('Design', 'UI/UX, Graphic Design, and Web Design'),
('Writing & Translation', 'Content Writing, Copywriting, and Translation'),
('Digital Marketing', 'SEO, Social Media, and Online Marketing'),
('Data Science', 'Data Analysis, Machine Learning, and AI'),
('Photography', 'Product Photography, Event Photography'),
('Video & Animation', 'Video Editing, Motion Graphics, Animation');

INSERT INTO skills (name, category) VALUES
('JavaScript', 'Web Development'),
('React', 'Web Development'),
('Node.js', 'Web Development'),
('PHP', 'Web Development'),
('Python', 'Web Development'),
('Java', 'Web Development'),
('Swift', 'Mobile Development'),
('Kotlin', 'Mobile Development'),
('React Native', 'Mobile Development'),
('Flutter', 'Mobile Development'),
('Figma', 'Design'),
('Photoshop', 'Design'),
('Illustrator', 'Design'),
('UI/UX Design', 'Design'),
('SEO', 'Digital Marketing'),
('Google Ads', 'Digital Marketing'),
('Social Media Marketing', 'Digital Marketing'),
('Content Writing', 'Writing & Translation'),
('Copywriting', 'Writing & Translation'),
('Machine Learning', 'Data Science'),
('Data Analysis', 'Data Science'),
('SQL', 'Data Science');