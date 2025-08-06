<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to apply for jobs']);
    exit;
}

// Check if user is a busylancer
if ($_SESSION['user_type'] !== 'busylancer') {
    http_response_code(403);
    echo json_encode(['error' => 'Only BusyLancers can apply for jobs']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

if (empty($input['job_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID is required']);
    exit;
}

$job_id = (int)$input['job_id'];
$user_id = $_SESSION['user_id'];

try {
    // Check if job exists and is active
    $stmt = $pdo->prepare("SELECT id, title, employer_id FROM jobs WHERE id = ? AND status = 'active'");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found or no longer available']);
        exit;
    }

    // Check if user already applied
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $user_id]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'You have already applied for this job']);
        exit;
    }

    // Create application
    $stmt = $pdo->prepare("INSERT INTO applications (job_id, user_id) VALUES (?, ?)");
    $stmt->execute([$job_id, $user_id]);
    
    // Send notification to employer (you can customize this)
    $employer_id = $job['employer_id'];
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$employer_id]);
    $employer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employer) {
        $to = $employer['email'];
        $subject = "New Job Application - " . $job['title'];
        $message = "Hi " . $employer['name'] . ",\n\nYou have received a new application for the job: " . $job['title'] . "\n\nPlease check your dashboard to review the application.\n\nBest regards,\nThe BusyLancer Team";
        $headers = "From: noreply@busy-lancer.com";
        
        mail($to, $subject, $message, $headers);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully!'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>