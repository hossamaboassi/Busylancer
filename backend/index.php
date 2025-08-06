<?php

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/utils/Response.php';

// Get the request path and clean it
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/backend', '', $path); // Remove /backend prefix if present

// Route to appropriate API file based on path
try {
    if (strpos($path, '/api/auth') === 0) {
        require_once __DIR__ . '/api/auth.php';
    } elseif (strpos($path, '/api/jobs') === 0) {
        require_once __DIR__ . '/api/jobs.php';
    } elseif (strpos($path, '/api/applications') === 0) {
        require_once __DIR__ . '/api/applications.php';
    } elseif (strpos($path, '/api/candidates') === 0) {
        require_once __DIR__ . '/api/candidates.php';
    } elseif (strpos($path, '/api/employers') === 0) {
        require_once __DIR__ . '/api/employers.php';
    } elseif (strpos($path, '/api/messages') === 0) {
        require_once __DIR__ . '/api/messages.php';
    } elseif (strpos($path, '/api/upload') === 0) {
        require_once __DIR__ . '/api/upload.php';
    } elseif (strpos($path, '/api/dashboard') === 0) {
        require_once __DIR__ . '/api/dashboard.php';
    } elseif (strpos($path, '/api/skills') === 0) {
        require_once __DIR__ . '/api/skills.php';
    } elseif (strpos($path, '/api/categories') === 0) {
        require_once __DIR__ . '/api/categories.php';
    } elseif ($path === '/api' || $path === '/api/') {
        // API info endpoint
        Response::success([
            'name' => APP_NAME,
            'version' => APP_VERSION,
            'environment' => APP_ENV,
            'endpoints' => [
                'auth' => '/api/auth',
                'jobs' => '/api/jobs',
                'applications' => '/api/applications',
                'candidates' => '/api/candidates',
                'employers' => '/api/employers',
                'messages' => '/api/messages',
                'upload' => '/api/upload',
                'dashboard' => '/api/dashboard',
                'skills' => '/api/skills',
                'categories' => '/api/categories'
            ]
        ], 'Busylancer API');
    } else {
        Response::notFound('API endpoint not found');
    }
} catch (Exception $e) {
    if (APP_ENV === 'development') {
        Response::serverError('API Error: ' . $e->getMessage());
    } else {
        Response::serverError('Internal server error');
    }
}