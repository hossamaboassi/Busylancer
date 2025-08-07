<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

// Router
switch ($method) {
    case 'GET':
        if (strpos($path, '/categories') !== false) {
            getSkillCategories();
        } elseif (strpos($path, '/search') !== false) {
            searchSkills();
        } else {
            getAllSkills();
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function getAllSkills() {
    try {
        $db = new Database();
        $category = $_GET['category'] ?? null;
        
        if ($category) {
            $sql = "SELECT * FROM skills WHERE category = ? ORDER BY name";
            $skills = $db->fetchAll($sql, [$category]);
        } else {
            $sql = "SELECT * FROM skills ORDER BY category, name";
            $skills = $db->fetchAll($sql);
        }
        
        Response::success($skills);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get skills');
    }
}

function getSkillCategories() {
    try {
        $db = new Database();
        $sql = "SELECT DISTINCT category FROM skills WHERE category IS NOT NULL ORDER BY category";
        $categories = $db->fetchAll($sql);
        
        Response::success($categories);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get skill categories');
    }
}

function searchSkills() {
    try {
        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? null;
        $limit = min($_GET['limit'] ?? 20, 100);
        
        if (empty($query)) {
            Response::error('Search query is required');
        }
        
        $db = new Database();
        $conditions = ["name LIKE ?"];
        $params = ['%' . $query . '%'];
        
        if ($category) {
            $conditions[] = "category = ?";
            $params[] = $category;
        }
        
        $sql = "SELECT * FROM skills WHERE " . implode(' AND ', $conditions) . " ORDER BY name LIMIT ?";
        $params[] = $limit;
        
        $skills = $db->fetchAll($sql, $params);
        
        Response::success([
            'skills' => $skills,
            'query' => $query,
            'category' => $category,
            'total' => count($skills)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to search skills');
    }
}