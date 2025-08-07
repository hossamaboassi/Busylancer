<?php

require_once __DIR__ . '/../config/database.php';

class EmployerProfile {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO employer_profiles (user_id, company_name, company_description, company_size, industry, website, location) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $data['user_id'],
            $data['company_name'],
            $data['company_description'] ?? null,
            $data['company_size'] ?? null,
            $data['industry'] ?? null,
            $data['website'] ?? null,
            $data['location'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function findByUserId($userId) {
        $sql = "SELECT ep.*, u.first_name, u.last_name, u.email, u.profile_image 
                FROM employer_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                WHERE ep.user_id = ?";
        return $this->db->fetch($sql, [$userId]);
    }
    
    public function findById($id) {
        $sql = "SELECT ep.*, u.first_name, u.last_name, u.email, u.profile_image 
                FROM employer_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                WHERE ep.id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function updateByUserId($userId, $data) {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'company_name', 'company_description', 'company_size', 'industry', 
            'website', 'company_logo', 'location'
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
        $sql = "UPDATE employer_profiles SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        return $this->db->execute($sql, $values) > 0;
    }
    
    public function search($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];
        
        $sql = "SELECT ep.*, u.first_name, u.last_name, u.profile_image,
                       COUNT(j.id) as active_jobs_count
                FROM employer_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                LEFT JOIN jobs j ON ep.id = j.employer_id AND j.status = 'active'
                WHERE u.is_active = 1";
        
        // Apply filters
        if (!empty($filters['company_size'])) {
            $conditions[] = "ep.company_size = ?";
            $params[] = $filters['company_size'];
        }
        
        if (!empty($filters['industry'])) {
            $conditions[] = "ep.industry = ?";
            $params[] = $filters['industry'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = "ep.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['company_name'])) {
            $conditions[] = "ep.company_name LIKE ?";
            $params[] = '%' . $filters['company_name'] . '%';
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY ep.id ORDER BY ep.average_rating DESC, ep.total_jobs_posted DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getActiveJobs($employerId, $limit = 10) {
        $sql = "SELECT j.*, jc.name as category_name,
                       COUNT(ja.id) as applications_count
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                WHERE j.employer_id = ? AND j.status = 'active'
                GROUP BY j.id 
                ORDER BY j.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$employerId, $limit]);
    }
    
    public function updateStats($employerId, $totalSpent = 0, $jobsPosted = 0, $rating = 0) {
        $sql = "UPDATE employer_profiles SET 
                total_spent = total_spent + ?, 
                total_jobs_posted = total_jobs_posted + ?,
                average_rating = ? 
                WHERE id = ?";
        return $this->db->execute($sql, [$totalSpent, $jobsPosted, $rating, $employerId]) > 0;
    }
    
    public function getFeatured($limit = 10) {
        $sql = "SELECT ep.*, u.first_name, u.last_name, u.profile_image,
                       COUNT(j.id) as active_jobs_count
                FROM employer_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                LEFT JOIN jobs j ON ep.id = j.employer_id AND j.status = 'active'
                WHERE u.is_active = 1 AND ep.average_rating >= 4.0
                GROUP BY ep.id 
                ORDER BY ep.average_rating DESC, ep.total_jobs_posted DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getRecentlyJoined($limit = 10) {
        $sql = "SELECT ep.*, u.first_name, u.last_name, u.profile_image
                FROM employer_profiles ep 
                JOIN users u ON ep.user_id = u.id 
                WHERE u.is_active = 1
                ORDER BY ep.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}