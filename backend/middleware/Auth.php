<?php

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    
    public static function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            Response::unauthorized('Authorization header missing');
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::unauthorized('Invalid authorization header format');
        }
        
        $token = $matches[1];
        $payload = JWT::validateToken($token);
        
        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }
        
        return $payload;
    }
    
    public static function requireUserType($userType) {
        $payload = self::authenticate();
        
        if ($payload['user_type'] !== $userType) {
            Response::forbidden('Access denied for this user type');
        }
        
        return $payload;
    }
    
    public static function requireCandidate() {
        return self::requireUserType('candidate');
    }
    
    public static function requireEmployer() {
        return self::requireUserType('employer');
    }
}