<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: Retrieve jobs
if ($method === 'GET') {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $location = isset($_GET['location']) ? $_GET['location'] : '';
        $job_type = isset($_GET['job_type']) ? $_GET['job_type'] : '';
        
        $where_conditions = ["j.status = 'active'"];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($location) {
            $where_conditions[] = "j.location LIKE ?";
            $params[] = "%$location%";
        }
        
        if ($job_type) {
            $where_conditions[] = "j.job_type = ?";
            $params[] = $job_type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs j WHERE $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get jobs
        $sql = "SELECT j.*, u.name as employer_name 
                FROM jobs j 
                LEFT JOIN users u ON j.employer_id = u.id 
                WHERE $where_clause 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'jobs' => $jobs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// POST: Create new job
elseif ($method === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Please login to post a job']);
        exit;
    }
    
    // Check if user is a business
    if ($_SESSION['user_type'] !== 'business') {
        http_response_code(403);
        echo json_encode(['error' => 'Only businesses can post jobs']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    $required_fields = ['title', 'description', 'location', 'job_type'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    $title = filter_var($input['title'], FILTER_SANITIZE_STRING);
    $description = filter_var($input['description'], FILTER_SANITIZE_STRING);
    $location = filter_var($input['location'], FILTER_SANITIZE_STRING);
    $job_type = $input['job_type'];
    $salary_min = isset($input['salary_min']) ? (float)$input['salary_min'] : null;
    $salary_max = isset($input['salary_max']) ? (float)$input['salary_max'] : null;
    
    // Validate job type
    $valid_types = ['full-time', 'part-time', 'temporary', 'gig'];
    if (!in_array($job_type, $valid_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid job type']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO jobs (title, description, location, salary_min, salary_max, job_type, employer_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$title, $description, $location, $salary_min, $salary_max, $job_type, $_SESSION['user_id']]);
        $job_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Job posted successfully!',
            'job_id' => $job_id
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>