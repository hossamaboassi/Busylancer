<?php

require_once __DIR__ . '/../config/database.php';

class Job {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO jobs (employer_id, title, description, category_id, job_type, 
                budget_min, budget_max, hourly_rate_min, hourly_rate_max, duration_estimate, 
                experience_level, location, is_remote, deadline) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $data['employer_id'],
            $data['title'],
            $data['description'],
            $data['category_id'] ?? null,
            $data['job_type'],
            $data['budget_min'] ?? null,
            $data['budget_max'] ?? null,
            $data['hourly_rate_min'] ?? null,
            $data['hourly_rate_max'] ?? null,
            $data['duration_estimate'] ?? null,
            $data['experience_level'] ?? null,
            $data['location'] ?? null,
            $data['is_remote'] ?? false,
            $data['deadline'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function findById($id) {
        $sql = "SELECT j.*, jc.name as category_name, 
                       ep.company_name, ep.company_logo, ep.location as company_location,
                       u.first_name as employer_first_name, u.last_name as employer_last_name,
                       ep.average_rating as employer_rating,
                       COUNT(ja.id) as applications_count,
                       GROUP_CONCAT(s.name) as required_skills
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN employer_profiles ep ON j.employer_id = ep.id
                LEFT JOIN users u ON ep.user_id = u.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                LEFT JOIN job_skills js ON j.id = js.job_id
                LEFT JOIN skills s ON js.skill_id = s.id
                WHERE j.id = ?
                GROUP BY j.id";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function search($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];
        
        $sql = "SELECT j.*, jc.name as category_name, 
                       ep.company_name, ep.company_logo, ep.location as company_location,
                       ep.average_rating as employer_rating,
                       COUNT(ja.id) as applications_count,
                       GROUP_CONCAT(s.name) as required_skills
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN employer_profiles ep ON j.employer_id = ep.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                LEFT JOIN job_skills js ON j.id = js.job_id
                LEFT JOIN skills s ON js.skill_id = s.id
                WHERE j.status = 'active'";
        
        // Apply filters
        if (!empty($filters['category_id'])) {
            $conditions[] = "j.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['job_type'])) {
            $conditions[] = "j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        if (!empty($filters['experience_level'])) {
            $conditions[] = "j.experience_level = ?";
            $params[] = $filters['experience_level'];
        }
        
        if (!empty($filters['is_remote'])) {
            $conditions[] = "j.is_remote = ?";
            $params[] = $filters['is_remote'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = "j.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['min_budget'])) {
            $conditions[] = "(j.budget_min >= ? OR j.hourly_rate_min >= ?)";
            $params[] = $filters['min_budget'];
            $params[] = $filters['min_budget'];
        }
        
        if (!empty($filters['max_budget'])) {
            $conditions[] = "(j.budget_max <= ? OR j.hourly_rate_max <= ?)";
            $params[] = $filters['max_budget'];
            $params[] = $filters['max_budget'];
        }
        
        if (!empty($filters['keywords'])) {
            $conditions[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $searchTerm = '%' . $filters['keywords'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['skills'])) {
            $skillIds = is_array($filters['skills']) ? $filters['skills'] : [$filters['skills']];
            $placeholders = str_repeat('?,', count($skillIds) - 1) . '?';
            $conditions[] = "js.skill_id IN ($placeholders)";
            $params = array_merge($params, $skillIds);
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
        }
        
        $sql .= " GROUP BY j.id ORDER BY j.featured DESC, j.created_at DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getByEmployer($employerId, $status = null, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = ["j.employer_id = ?"];
        $params = [$employerId];
        
        if ($status) {
            $conditions[] = "j.status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT j.*, jc.name as category_name,
                       COUNT(ja.id) as applications_count
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY j.id 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'title', 'description', 'category_id', 'budget_min', 'budget_max',
            'hourly_rate_min', 'hourly_rate_max', 'duration_estimate',
            'experience_level', 'location', 'is_remote', 'deadline', 'status'
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
        
        $values[] = $id;
        $sql = "UPDATE jobs SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->db->execute($sql, $values) > 0;
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE jobs SET status = ? WHERE id = ?";
        return $this->db->execute($sql, [$status, $id]) > 0;
    }
    
    public function incrementViews($id) {
        $sql = "UPDATE jobs SET views_count = views_count + 1 WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }
    
    public function delete($id) {
        $sql = "DELETE FROM jobs WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }
    
    public function getFeatured($limit = 10) {
        $sql = "SELECT j.*, jc.name as category_name, 
                       ep.company_name, ep.company_logo,
                       COUNT(ja.id) as applications_count
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN employer_profiles ep ON j.employer_id = ep.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                WHERE j.status = 'active' AND j.featured = 1
                GROUP BY j.id 
                ORDER BY j.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function getRecent($limit = 10) {
        $sql = "SELECT j.*, jc.name as category_name, 
                       ep.company_name, ep.company_logo,
                       COUNT(ja.id) as applications_count
                FROM jobs j 
                LEFT JOIN job_categories jc ON j.category_id = jc.id
                LEFT JOIN employer_profiles ep ON j.employer_id = ep.id
                LEFT JOIN job_applications ja ON j.id = ja.job_id
                WHERE j.status = 'active'
                GROUP BY j.id 
                ORDER BY j.created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    public function addSkill($jobId, $skillId, $isRequired = true) {
        $sql = "INSERT INTO job_skills (job_id, skill_id, is_required) 
                VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_required = ?";
        return $this->db->execute($sql, [$jobId, $skillId, $isRequired, $isRequired]) > 0;
    }
    
    public function removeSkill($jobId, $skillId) {
        $sql = "DELETE FROM job_skills WHERE job_id = ? AND skill_id = ?";
        return $this->db->execute($sql, [$jobId, $skillId]) > 0;
    }
    
    public function getSkills($jobId) {
        $sql = "SELECT s.*, js.is_required 
                FROM job_skills js 
                JOIN skills s ON js.skill_id = s.id 
                WHERE js.job_id = ?";
        return $this->db->fetchAll($sql, [$jobId]);
    }
    
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_jobs,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_jobs,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_jobs,
                    COUNT(CASE WHEN job_type = 'fixed_price' THEN 1 END) as fixed_price_jobs,
                    COUNT(CASE WHEN job_type = 'hourly' THEN 1 END) as hourly_jobs
                FROM jobs";
        return $this->db->fetch($sql);
    }
}