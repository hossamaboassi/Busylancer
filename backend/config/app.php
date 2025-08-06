<?php

// Application Configuration
define('APP_NAME', 'Busylancer');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, production

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'busylancer_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT Configuration
define('JWT_SECRET', 'your_jwt_secret_key_here_make_it_long_and_secure');
define('JWT_EXPIRATION', 86400); // 24 hours

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// API Configuration
define('API_BASE_URL', '/api');
define('CORS_ORIGINS', ['http://localhost:3000', 'http://localhost:8080']);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@busylancer.com');
define('FROM_NAME', 'Busylancer Platform');

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');