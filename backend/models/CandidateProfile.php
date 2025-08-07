<?php

require_once __DIR__ . '/../config/database.php';

class CandidateProfile {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO candidate_profiles (user_id, title, bio, hourly_rate, experience_level, availability, location) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $data['user_id'],
            $data['title'] ?? null,
            $data['bio'] ?? null,
            $data['hourly_rate'] ?? null,
            $data['experience_level'] ?? null,
            $data['availability'] ?? null,
            $data['location'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function findByUserId($userId) {
        $sql = "SELECT cp.*, u.first_name, u.last_name, u.email, u.profile_image 
                FROM candidate_profiles cp 
                JOIN users u ON cp.user_id = u.id 
                WHERE cp.user_id = ?";
        return $this->db->fetch($sql, [$userId]);
    }
    
    public function findById($id) {
        $sql = "SELECT cp.*, u.first_name, u.last_name, u.email, u.profile_image 
                FROM candidate_profiles cp 
                JOIN users u ON cp.user_id = u.id 
                WHERE cp.id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function updateByUserId($userId, $data) {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'title', 'bio', 'hourly_rate', 'experience_level', 'availability', 
            'location', 'website', 'linkedin_url', 'github_url', 'portfolio_url', 'resume_file'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $userId;
        $sql = "UPDATE candidate_profiles SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        return $this->db->execute($sql, $values) > 0;
    }
    
    public function search($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];
        
        $sql = "SELECT cp.*, u.first_name, u.last_name, u.profile_image,
                       GROUP_CONCAT(s.name) as skills
                FROM candidate_profiles cp 
                JOIN users u ON cp.user_id = u.id 
                LEFT JOIN candidate_skills cs ON cp.id = cs.candidate_id
                LEFT JOIN skills s ON cs.skill_id = s.id
                WHERE u.is_active = 1";
        
        // Apply filters
        if (!empty($filters['experience_level'])) {
            $conditions[] = "cp.experience_level = ?";
            $params[] = $filters['experience_level'];
        }
        
        if (!empty($filters['availability'])) {
            $conditions[] = "cp.availability = ?";
            $params[] = $filters['availability'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = "cp.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['min_rate'])) {
            $conditions[] = "cp.hourly_rate >= ?";
            $params[] = $filters['min_rate'];
        }
        
        if (!empty($filters['max_rate'])) {
            $conditions[] = "cp.hourly_rate <= ?";
            $params[] = $filters['max_rate'];
        }
        
        if (!empty($filters['skills'])) {
            $skillIds = is_array($filters['skills']) ? $filters['skills'] : [$filters['skills']];
            $placeholders = str_repeat('?,', count($skillIds) - 1) . '?';
            $conditions[] = "cs.skill_id IN ($placeholders)";
            $params = array_merge($params, $skillIds);
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY cp.id ORDER BY cp.average_rating DESC, cp.total_jobs_completed DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getSkills($candidateId) {
        $sql = "SELECT s.*, cs.proficiency_level 
                FROM candidate_skills cs 
                JOIN skills s ON cs.skill_id = s.id 
                WHERE cs.candidate_id = ?";
        return $this->db->fetchAll($sql, [$candidateId]);
    }
    
    public function addSkill($candidateId, $skillId, $proficiency = 'intermediate') {
        $sql = "INSERT INTO candidate_skills (candidate_id, skill_id, proficiency_level) 
                VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE proficiency_level = ?";
        return $this->db->execute($sql, [$candidateId, $skillId, $proficiency, $proficiency]) > 0;
    }
    
    public function removeSkill($candidateId, $skillId) {
        $sql = "DELETE FROM candidate_skills WHERE candidate_id = ? AND skill_id = ?";
        return $this->db->execute($sql, [$candidateId, $skillId]) > 0;
    }
    
    public function updateStats($candidateId, $earnings = 0, $jobsCompleted = 0, $rating = 0) {
        $sql = "UPDATE candidate_profiles SET 
                total_earnings = total_earnings + ?, 
                total_jobs_completed = total_jobs_completed + ?,
                average_rating = ? 
                WHERE id = ?";
        return $this->db->execute($sql, [$earnings, $jobsCompleted, $rating, $candidateId]) > 0;
    }
    
    public function getFeatured($limit = 10) {
        $sql = "SELECT cp.*, u.first_name, u.last_name, u.profile_image,
                       GROUP_CONCAT(s.name) as skills
                FROM candidate_profiles cp 
                JOIN users u ON cp.user_id = u.id 
                LEFT JOIN candidate_skills cs ON cp.id = cs.candidate_id
                LEFT JOIN skills s ON cs.skill_id = s.id
                WHERE u.is_active = 1 AND cp.average_rating >= 4.0
                GROUP BY cp.id 
                ORDER BY cp.average_rating DESC, cp.total_jobs_completed DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}