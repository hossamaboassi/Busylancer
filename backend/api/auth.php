<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

// Router
switch ($method) {
    case 'POST':
        if (strpos($path, '/register') !== false) {
            register();
        } elseif (strpos($path, '/login') !== false) {
            login();
        } elseif (strpos($path, '/forgot-password') !== false) {
            forgotPassword();
        } elseif (strpos($path, '/reset-password') !== false) {
            resetPassword();
        } elseif (strpos($path, '/verify-email') !== false) {
            verifyEmail();
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'GET':
        if (strpos($path, '/profile') !== false) {
            getProfile();
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'PUT':
        if (strpos($path, '/profile') !== false) {
            updateProfile();
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function register() {
    global $input;
    
    // Validation
    $errors = [];
    if (empty($input['email'])) $errors['email'] = 'Email is required';
    if (empty($input['password'])) $errors['password'] = 'Password is required';
    if (empty($input['user_type'])) $errors['user_type'] = 'User type is required';
    if (empty($input['first_name'])) $errors['first_name'] = 'First name is required';
    if (empty($input['last_name'])) $errors['last_name'] = 'Last name is required';
    
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (strlen($input['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if (!in_array($input['user_type'], ['candidate', 'employer'])) {
        $errors['user_type'] = 'User type must be candidate or employer';
    }
    
    if (!empty($errors)) {
        Response::validationError($errors);
    }
    
    try {
        $userModel = new User();
        
        // Check if email already exists
        if ($userModel->findByEmail($input['email'])) {
            Response::error('Email already exists', 409);
        }
        
        // Create user
        $userId = $userModel->create($input);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        $userModel->setEmailVerificationToken($userId, $verificationToken);
        
        // Create profile based on user type
        if ($input['user_type'] === 'candidate') {
            createCandidateProfile($userId);
        } else {
            createEmployerProfile($userId, $input);
        }
        
        // Generate JWT token
        $token = JWT::createToken($userId, $input['user_type'], $input['email']);
        
        Response::success([
            'user_id' => $userId,
            'token' => $token,
            'user_type' => $input['user_type'],
            'email_verification_token' => $verificationToken
        ], 'User registered successfully');
        
    } catch (Exception $e) {
        Response::serverError('Registration failed: ' . $e->getMessage());
    }
}

function login() {
    global $input;
    
    if (empty($input['email']) || empty($input['password'])) {
        Response::error('Email and password are required', 400);
    }
    
    try {
        $userModel = new User();
        $user = $userModel->verifyPassword($input['email'], $input['password']);
        
        if (!$user) {
            Response::error('Invalid credentials', 401);
        }
        
        $token = JWT::createToken($user['id'], $user['user_type'], $user['email']);
        
        Response::success([
            'user_id' => $user['id'],
            'token' => $token,
            'user_type' => $user['user_type'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email_verified' => $user['email_verified']
        ], 'Login successful');
        
    } catch (Exception $e) {
        Response::serverError('Login failed');
    }
}

function getProfile() {
    try {
        $payload = AuthMiddleware::authenticate();
        $userModel = new User();
        $user = $userModel->findById($payload['user_id']);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        // Get profile data based on user type
        $profileData = getUserProfileData($user);
        
        Response::success($profileData);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get profile');
    }
}

function updateProfile() {
    global $input;
    
    try {
        $payload = AuthMiddleware::authenticate();
        $userModel = new User();
        
        // Update user basic info
        $userUpdated = $userModel->updateProfile($payload['user_id'], $input);
        
        // Update specific profile based on user type
        if ($payload['user_type'] === 'candidate') {
            updateCandidateProfile($payload['user_id'], $input);
        } else {
            updateEmployerProfile($payload['user_id'], $input);
        }
        
        Response::success(null, 'Profile updated successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to update profile');
    }
}

function forgotPassword() {
    global $input;
    
    if (empty($input['email'])) {
        Response::error('Email is required');
    }
    
    try {
        $userModel = new User();
        $user = $userModel->findByEmail($input['email']);
        
        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $userModel->setPasswordResetToken($input['email'], $resetToken, $expiry);
            
            // Here you would send email with reset token
            // For now, we'll return it in the response (remove in production)
            Response::success(['reset_token' => $resetToken], 'Password reset email sent');
        } else {
            // Don't reveal if email exists or not
            Response::success(null, 'If email exists, reset instructions have been sent');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to process password reset');
    }
}

function resetPassword() {
    global $input;
    
    if (empty($input['token']) || empty($input['password'])) {
        Response::error('Token and new password are required');
    }
    
    if (strlen($input['password']) < 8) {
        Response::error('Password must be at least 8 characters');
    }
    
    try {
        $userModel = new User();
        $success = $userModel->resetPassword($input['token'], $input['password']);
        
        if ($success) {
            Response::success(null, 'Password reset successfully');
        } else {
            Response::error('Invalid or expired reset token');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to reset password');
    }
}

function verifyEmail() {
    global $input;
    
    if (empty($input['token'])) {
        Response::error('Verification token is required');
    }
    
    try {
        $userModel = new User();
        $success = $userModel->verifyEmail($input['token']);
        
        if ($success) {
            Response::success(null, 'Email verified successfully');
        } else {
            Response::error('Invalid verification token');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to verify email');
    }
}

// Helper functions
function createCandidateProfile($userId) {
    require_once __DIR__ . '/../models/CandidateProfile.php';
    $candidateModel = new CandidateProfile();
    $candidateModel->create(['user_id' => $userId]);
}

function createEmployerProfile($userId, $data) {
    require_once __DIR__ . '/../models/EmployerProfile.php';
    $employerModel = new EmployerProfile();
    $employerModel->create([
        'user_id' => $userId,
        'company_name' => $data['company_name'] ?? ''
    ]);
}

function getUserProfileData($user) {
    if ($user['user_type'] === 'candidate') {
        require_once __DIR__ . '/../models/CandidateProfile.php';
        $candidateModel = new CandidateProfile();
        $profile = $candidateModel->findByUserId($user['id']);
    } else {
        require_once __DIR__ . '/../models/EmployerProfile.php';
        $employerModel = new EmployerProfile();
        $profile = $employerModel->findByUserId($user['id']);
    }
    
    return array_merge($user, $profile ?: []);
}

function updateCandidateProfile($userId, $data) {
    require_once __DIR__ . '/../models/CandidateProfile.php';
    $candidateModel = new CandidateProfile();
    $candidateModel->updateByUserId($userId, $data);
}

function updateEmployerProfile($userId, $data) {
    require_once __DIR__ . '/../models/EmployerProfile.php';
    $employerModel = new EmployerProfile();
    $employerModel->updateByUserId($userId, $data);
}