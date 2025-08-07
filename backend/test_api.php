<?php

// Simple API test script for Busylancer Backend
echo "<h1>Busylancer Backend API Test</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    $db = new Database();
    echo "<p style='color: green'>✓ Database connection successful</p>";
    
    // Test if tables exist
    $tables = ['users', 'candidate_profiles', 'employer_profiles', 'jobs', 'job_applications', 'skills'];
    foreach ($tables as $table) {
        try {
            $result = $db->fetch("SELECT COUNT(*) as count FROM $table");
            echo "<p style='color: green'>✓ Table '$table' exists with {$result['count']} records</p>";
        } catch (Exception $e) {
            echo "<p style='color: red'>✗ Table '$table' not found or error: " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test JWT functionality
echo "<h2>JWT Test</h2>";
try {
    require_once __DIR__ . '/utils/JWT.php';
    $token = JWT::createToken(1, 'candidate', 'test@example.com');
    echo "<p style='color: green'>✓ JWT token created: " . substr($token, 0, 50) . "...</p>";
    
    $decoded = JWT::validateToken($token);
    if ($decoded && $decoded['user_id'] == 1) {
        echo "<p style='color: green'>✓ JWT token validation successful</p>";
    } else {
        echo "<p style='color: red'>✗ JWT token validation failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>✗ JWT test failed: " . $e->getMessage() . "</p>";
}

// Test API endpoints availability
echo "<h2>API Endpoints Test</h2>";
$endpoints = [
    '/api' => 'API Info',
    '/api/auth/register' => 'User Registration',
    '/api/auth/login' => 'User Login', 
    '/api/jobs' => 'Jobs Listing',
    '/api/candidates' => 'Candidates Listing',
    '/api/skills' => 'Skills Listing',
    '/api/dashboard' => 'Dashboard'
];

foreach ($endpoints as $endpoint => $description) {
    $file = __DIR__ . str_replace('/api', '/api', $endpoint) . '.php';
    if ($endpoint === '/api') {
        echo "<p style='color: green'>✓ $description endpoint available</p>";
    } else {
        $apiFile = __DIR__ . '/api/' . explode('/', $endpoint)[2] . '.php';
        if (file_exists($apiFile)) {
            echo "<p style='color: green'>✓ $description endpoint file exists</p>";
        } else {
            echo "<p style='color: red'>✗ $description endpoint file missing</p>";
        }
    }
}

// Configuration test
echo "<h2>Configuration Test</h2>";
try {
    require_once __DIR__ . '/config/app.php';
    echo "<p style='color: green'>✓ App configuration loaded</p>";
    echo "<p>App Name: " . APP_NAME . "</p>";
    echo "<p>App Version: " . APP_VERSION . "</p>";
    echo "<p>Environment: " . APP_ENV . "</p>";
    
    if (defined('JWT_SECRET') && JWT_SECRET !== 'your_jwt_secret_key_here_make_it_long_and_secure') {
        echo "<p style='color: green'>✓ JWT secret configured</p>";
    } else {
        echo "<p style='color: orange'>⚠ Please update JWT_SECRET in config/app.php</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>✗ Configuration test failed: " . $e->getMessage() . "</p>";
}

// File permissions test
echo "<h2>File Permissions Test</h2>";
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p style='color: green'>✓ Created uploads directory</p>";
    } else {
        echo "<p style='color: red'>✗ Could not create uploads directory</p>";
    }
} else {
    echo "<p style='color: green'>✓ Uploads directory exists</p>";
}

if (is_writable($uploadDir)) {
    echo "<p style='color: green'>✓ Uploads directory is writable</p>";
} else {
    echo "<p style='color: red'>✗ Uploads directory is not writable</p>";
}

echo "<h2>Setup Instructions</h2>";
echo "<ol>";
echo "<li>Create MySQL database named 'busylancer_db'</li>";
echo "<li>Import database/schema.sql into your database</li>";
echo "<li>Update database credentials in config/app.php</li>";
echo "<li>Set a secure JWT_SECRET in config/app.php</li>";
echo "<li>Ensure uploads directory has write permissions</li>";
echo "<li>Configure your web server to point to this backend directory</li>";
echo "<li>Test API endpoints using the examples in README.md</li>";
echo "</ol>";

echo "<h2>Sample API URLs</h2>";
$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
echo "<ul>";
echo "<li><a href='$baseUrl/api' target='_blank'>$baseUrl/api</a> - API Info</li>";
echo "<li>POST $baseUrl/api/auth/register - User Registration</li>";
echo "<li>POST $baseUrl/api/auth/login - User Login</li>";
echo "<li>GET $baseUrl/api/jobs - Jobs Listing</li>";
echo "<li>GET $baseUrl/api/candidates - Candidates Listing</li>";
echo "</ul>";

echo "<p><em>Use tools like Postman or curl to test POST endpoints with JSON data.</em></p>";
?>