<?php

require_once __DIR__ . '/../config/database.php';

class JobApplication {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO job_applications (job_id, candidate_id, cover_letter, proposed_rate, estimated_duration) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $data['job_id'],
            $data['candidate_id'],
            $data['cover_letter'] ?? null,
            $data['proposed_rate'] ?? null,
            $data['estimated_duration'] ?? null
        ]);
        
        // Update job applications count
        $this->db->execute("UPDATE jobs SET applications_count = applications_count + 1 WHERE id = ?", [$data['job_id']]);
        
        return $this->db->lastInsertId();
    }
    
    public function findById($id) {
        $sql = "SELECT ja.*, 
                       j.title as job_title, j.job_type, j.budget_min, j.budget_max,
                       cp.title as candidate_title, cp.hourly_rate as candidate_rate,
                       u.first_name, u.last_name, u.profile_image,
                       ep.company_name
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                JOIN candidate_profiles cp ON ja.candidate_id = cp.id
                JOIN users u ON cp.user_id = u.id
                JOIN employer_profiles ep ON j.employer_id = ep.id
                WHERE ja.id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function getByJob($jobId, $status = null, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = ["ja.job_id = ?"];
        $params = [$jobId];
        
        if ($status) {
            $conditions[] = "ja.status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT ja.*, 
                       cp.title as candidate_title, cp.hourly_rate as candidate_rate,
                       cp.experience_level, cp.total_jobs_completed, cp.average_rating,
                       u.first_name, u.last_name, u.profile_image
                FROM job_applications ja
                JOIN candidate_profiles cp ON ja.candidate_id = cp.id
                JOIN users u ON cp.user_id = u.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY ja.submitted_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getByCandidate($candidateId, $status = null, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $conditions = ["ja.candidate_id = ?"];
        $params = [$candidateId];
        
        if ($status) {
            $conditions[] = "ja.status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT ja.*, 
                       j.title as job_title, j.job_type, j.budget_min, j.budget_max,
                       j.location, j.is_remote, j.status as job_status,
                       ep.company_name, ep.company_logo
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                JOIN employer_profiles ep ON j.employer_id = ep.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY ja.submitted_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE job_applications SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$status, $id]) > 0;
    }
    
    public function withdraw($id) {
        $sql = "UPDATE job_applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }
    
    public function checkExisting($jobId, $candidateId) {
        $sql = "SELECT id FROM job_applications WHERE job_id = ? AND candidate_id = ?";
        return $this->db->fetch($sql, [$jobId, $candidateId]);
    }
    
    public function getStatistics($candidateId = null, $employerId = null) {
        $conditions = [];
        $params = [];
        
        if ($candidateId) {
            $conditions[] = "ja.candidate_id = ?";
            $params[] = $candidateId;
        }
        
        if ($employerId) {
            $conditions[] = "j.employer_id = ?";
            $params[] = $employerId;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
        
        $sql = "SELECT 
                    COUNT(*) as total_applications,
                    COUNT(CASE WHEN ja.status = 'pending' THEN 1 END) as pending_applications,
                    COUNT(CASE WHEN ja.status = 'accepted' THEN 1 END) as accepted_applications,
                    COUNT(CASE WHEN ja.status = 'rejected' THEN 1 END) as rejected_applications,
                    COUNT(CASE WHEN ja.status = 'withdrawn' THEN 1 END) as withdrawn_applications
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                $whereClause";
        
        return $this->db->fetch($sql, $params);
    }
    
    public function getRecent($limit = 10, $candidateId = null, $employerId = null) {
        $conditions = [];
        $params = [];
        
        if ($candidateId) {
            $conditions[] = "ja.candidate_id = ?";
            $params[] = $candidateId;
        }
        
        if ($employerId) {
            $conditions[] = "j.employer_id = ?";
            $params[] = $employerId;
        }
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
        
        $sql = "SELECT ja.*, 
                       j.title as job_title,
                       cp.title as candidate_title,
                       u.first_name, u.last_name,
                       ep.company_name
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                JOIN candidate_profiles cp ON ja.candidate_id = cp.id
                JOIN users u ON cp.user_id = u.id
                JOIN employer_profiles ep ON j.employer_id = ep.id
                $whereClause
                ORDER BY ja.submitted_at DESC 
                LIMIT ?";
        
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM job_applications WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }
}