<?php
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST; // Fallback to POST data
}

// Validate required fields
$required_fields = ['email', 'name', 'user_type'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$name = filter_var($input['name'], FILTER_SANITIZE_STRING);
$user_type = $input['user_type'];
$phone = isset($input['phone']) ? filter_var($input['phone'], FILTER_SANITIZE_STRING) : '';
$location = isset($input['location']) ? filter_var($input['location'], FILTER_SANITIZE_STRING) : '';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

// Validate user type
$valid_types = ['busylancer', 'business', 'agent'];
if (!in_array($user_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user type']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (email, name, user_type, phone, location) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$email, $name, $user_type, $phone, $location]);
    $user_id = $pdo->lastInsertId();

    // Send welcome email (you can customize this)
    $to = $email;
    $subject = "Welcome to BusyLancer!";
    $message = "Hi $name,\n\nWelcome to BusyLancer! Your registration has been received and is being reviewed.\n\nWe'll notify you once your account is approved.\n\nBest regards,\nThe BusyLancer Team";
    $headers = "From: noreply@busy-lancer.com";

    mail($to, $subject, $message, $headers);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Please check your email for confirmation.',
        'user_id' => $user_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>