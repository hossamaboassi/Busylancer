<?php

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($data) {
        $sql = "INSERT INTO users (email, password_hash, user_type, first_name, last_name, phone) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, [
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['user_type'],
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        return $this->db->fetch($sql, [$email]);
    }
    
    public function findById($id) {
        $sql = "SELECT * FROM users WHERE id = ? AND is_active = 1";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }
    
    public function updateProfile($userId, $data) {
        $fields = [];
        $values = [];
        
        $allowedFields = ['first_name', 'last_name', 'phone', 'profile_image'];
        
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
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->db->execute($sql, $values) > 0;
    }
    
    public function setEmailVerificationToken($userId, $token) {
        $sql = "UPDATE users SET email_verification_token = ? WHERE id = ?";
        return $this->db->execute($sql, [$token, $userId]) > 0;
    }
    
    public function verifyEmail($token) {
        $sql = "UPDATE users SET email_verified = 1, email_verification_token = NULL 
                WHERE email_verification_token = ?";
        return $this->db->execute($sql, [$token]) > 0;
    }
    
    public function setPasswordResetToken($email, $token, $expiry) {
        $sql = "UPDATE users SET password_reset_token = ?, password_reset_expires = ? 
                WHERE email = ?";
        return $this->db->execute($sql, [$token, $expiry, $email]) > 0;
    }
    
    public function resetPassword($token, $newPassword) {
        $sql = "SELECT id FROM users WHERE password_reset_token = ? 
                AND password_reset_expires > NOW()";
        $user = $this->db->fetch($sql, [$token]);
        
        if (!$user) {
            return false;
        }
        
        $sql = "UPDATE users SET password_hash = ?, password_reset_token = NULL, 
                password_reset_expires = NULL WHERE id = ?";
        return $this->db->execute($sql, [
            password_hash($newPassword, PASSWORD_DEFAULT),
            $user['id']
        ]) > 0;
    }
    
    public function deactivate($userId) {
        $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        return $this->db->execute($sql, [$userId]) > 0;
    }
}