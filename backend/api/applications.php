<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/JobApplication.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/CandidateProfile.php';
require_once __DIR__ . '/../models/EmployerProfile.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

// Extract application ID from URL if present
$pathParts = explode('/', trim($path, '/'));
$applicationId = null;
if (count($pathParts) >= 3 && is_numeric(end($pathParts))) {
    $applicationId = end($pathParts);
}

// Router
switch ($method) {
    case 'POST':
        if (strpos($path, '/apply') !== false) {
            applyToJob();
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'GET':
        if ($applicationId) {
            getApplication($applicationId);
        } elseif (strpos($path, '/my-applications') !== false) {
            getMyApplications();
        } elseif (strpos($path, '/job/') !== false) {
            getJobApplications();
        } elseif (strpos($path, '/stats') !== false) {
            getApplicationStatistics();
        } elseif (strpos($path, '/recent') !== false) {
            getRecentApplications();
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'PUT':
        if ($applicationId && strpos($path, '/status') !== false) {
            updateApplicationStatus($applicationId);
        } elseif ($applicationId && strpos($path, '/withdraw') !== false) {
            withdrawApplication($applicationId);
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'DELETE':
        if ($applicationId) {
            deleteApplication($applicationId);
        } else {
            Response::notFound('Application ID required');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function applyToJob() {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        // Get candidate profile
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate) {
            Response::error('Candidate profile not found', 404);
        }
        
        // Validation
        if (empty($input['job_id'])) {
            Response::error('Job ID is required');
        }
        
        // Check if job exists and is active
        $jobModel = new Job();
        $job = $jobModel->findById($input['job_id']);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        if ($job['status'] !== 'active') {
            Response::error('Job is not active for applications');
        }
        
        // Check if already applied
        $applicationModel = new JobApplication();
        $existing = $applicationModel->checkExisting($input['job_id'], $candidate['id']);
        
        if ($existing) {
            Response::error('You have already applied to this job', 409);
        }
        
        // Create application
        $applicationData = [
            'job_id' => $input['job_id'],
            'candidate_id' => $candidate['id'],
            'cover_letter' => $input['cover_letter'] ?? null,
            'proposed_rate' => $input['proposed_rate'] ?? null,
            'estimated_duration' => $input['estimated_duration'] ?? null
        ];
        
        $applicationId = $applicationModel->create($applicationData);
        
        Response::success(['application_id' => $applicationId], 'Application submitted successfully', 201);
        
    } catch (Exception $e) {
        Response::serverError('Failed to apply to job: ' . $e->getMessage());
    }
}

function getApplication($applicationId) {
    try {
        $payload = AuthMiddleware::authenticate();
        
        $applicationModel = new JobApplication();
        $application = $applicationModel->findById($applicationId);
        
        if (!$application) {
            Response::notFound('Application not found');
        }
        
        // Check if user has access to this application
        $hasAccess = false;
        
        if ($payload['user_type'] === 'candidate') {
            $candidateModel = new CandidateProfile();
            $candidate = $candidateModel->findByUserId($payload['user_id']);
            $hasAccess = $candidate && $application['candidate_id'] == $candidate['id'];
        } else {
            $employerModel = new EmployerProfile();
            $employer = $employerModel->findByUserId($payload['user_id']);
            $jobModel = new Job();
            $job = $jobModel->findById($application['job_id']);
            $hasAccess = $employer && $job && $job['employer_id'] == $employer['id'];
        }
        
        if (!$hasAccess) {
            Response::forbidden('Access denied');
        }
        
        Response::success($application);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get application');
    }
}

function getMyApplications() {
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate) {
            Response::error('Candidate profile not found', 404);
        }
        
        $status = $_GET['status'] ?? null;
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        $applicationModel = new JobApplication();
        $applications = $applicationModel->getByCandidate($candidate['id'], $status, $page, $limit);
        
        Response::success([
            'applications' => $applications,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($applications)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get applications');
    }
}

function getJobApplications() {
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        // Extract job ID from URL
        $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $jobIndex = array_search('job', $pathParts);
        
        if ($jobIndex === false || !isset($pathParts[$jobIndex + 1])) {
            Response::error('Job ID is required');
        }
        
        $jobId = $pathParts[$jobIndex + 1];
        
        // Check if job exists and belongs to employer
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only view applications for your own jobs');
        }
        
        $status = $_GET['status'] ?? null;
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        $applicationModel = new JobApplication();
        $applications = $applicationModel->getByJob($jobId, $status, $page, $limit);
        
        Response::success([
            'applications' => $applications,
            'job' => $job,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($applications)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get job applications');
    }
}

function updateApplicationStatus($applicationId) {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        if (empty($input['status'])) {
            Response::error('Status is required');
        }
        
        if (!in_array($input['status'], ['accepted', 'rejected'])) {
            Response::error('Status must be accepted or rejected');
        }
        
        $applicationModel = new JobApplication();
        $application = $applicationModel->findById($applicationId);
        
        if (!$application) {
            Response::notFound('Application not found');
        }
        
        // Check if employer owns the job
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        $jobModel = new Job();
        $job = $jobModel->findById($application['job_id']);
        
        if (!$employer || !$job || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only update applications for your own jobs');
        }
        
        $success = $applicationModel->updateStatus($applicationId, $input['status']);
        
        if ($success) {
            Response::success(null, 'Application status updated successfully');
        } else {
            Response::error('Failed to update application status');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to update application status');
    }
}

function withdrawApplication($applicationId) {
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        $applicationModel = new JobApplication();
        $application = $applicationModel->findById($applicationId);
        
        if (!$application) {
            Response::notFound('Application not found');
        }
        
        // Check if candidate owns this application
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate || $application['candidate_id'] != $candidate['id']) {
            Response::forbidden('You can only withdraw your own applications');
        }
        
        if ($application['status'] !== 'pending') {
            Response::error('You can only withdraw pending applications');
        }
        
        $success = $applicationModel->withdraw($applicationId);
        
        if ($success) {
            Response::success(null, 'Application withdrawn successfully');
        } else {
            Response::error('Failed to withdraw application');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to withdraw application');
    }
}

function getApplicationStatistics() {
    try {
        $payload = AuthMiddleware::authenticate();
        
        $candidateId = null;
        $employerId = null;
        
        if ($payload['user_type'] === 'candidate') {
            $candidateModel = new CandidateProfile();
            $candidate = $candidateModel->findByUserId($payload['user_id']);
            $candidateId = $candidate['id'] ?? null;
        } else {
            $employerModel = new EmployerProfile();
            $employer = $employerModel->findByUserId($payload['user_id']);
            $employerId = $employer['id'] ?? null;
        }
        
        $applicationModel = new JobApplication();
        $stats = $applicationModel->getStatistics($candidateId, $employerId);
        
        Response::success($stats);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get application statistics');
    }
}

function getRecentApplications() {
    try {
        $payload = AuthMiddleware::authenticate();
        $limit = min($_GET['limit'] ?? 10, 50);
        
        $candidateId = null;
        $employerId = null;
        
        if ($payload['user_type'] === 'candidate') {
            $candidateModel = new CandidateProfile();
            $candidate = $candidateModel->findByUserId($payload['user_id']);
            $candidateId = $candidate['id'] ?? null;
        } else {
            $employerModel = new EmployerProfile();
            $employer = $employerModel->findByUserId($payload['user_id']);
            $employerId = $employer['id'] ?? null;
        }
        
        $applicationModel = new JobApplication();
        $applications = $applicationModel->getRecent($limit, $candidateId, $employerId);
        
        Response::success($applications);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get recent applications');
    }
}

function deleteApplication($applicationId) {
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        $applicationModel = new JobApplication();
        $application = $applicationModel->findById($applicationId);
        
        if (!$application) {
            Response::notFound('Application not found');
        }
        
        // Check if candidate owns this application
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate || $application['candidate_id'] != $candidate['id']) {
            Response::forbidden('You can only delete your own applications');
        }
        
        $success = $applicationModel->delete($applicationId);
        
        if ($success) {
            Response::success(null, 'Application deleted successfully');
        } else {
            Response::error('Failed to delete application');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to delete application');
    }
}