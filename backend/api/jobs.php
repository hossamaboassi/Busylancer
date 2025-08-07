<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/EmployerProfile.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

// Extract job ID from URL if present
$pathParts = explode('/', trim($path, '/'));
$jobId = null;
if (count($pathParts) >= 3 && is_numeric(end($pathParts))) {
    $jobId = end($pathParts);
}

// Router
switch ($method) {
    case 'GET':
        if ($jobId) {
            getJob($jobId);
        } elseif (strpos($path, '/search') !== false) {
            searchJobs();
        } elseif (strpos($path, '/featured') !== false) {
            getFeaturedJobs();
        } elseif (strpos($path, '/recent') !== false) {
            getRecentJobs();
        } elseif (strpos($path, '/my-jobs') !== false) {
            getMyJobs();
        } elseif (strpos($path, '/stats') !== false) {
            getJobStatistics();
        } else {
            getAllJobs();
        }
        break;
        
    case 'POST':
        if (strpos($path, '/create') !== false) {
            createJob();
        } elseif ($jobId && strpos($path, '/skills') !== false) {
            addJobSkill($jobId);
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'PUT':
        if ($jobId) {
            updateJob($jobId);
        } else {
            Response::notFound('Job ID required');
        }
        break;
        
    case 'DELETE':
        if ($jobId && strpos($path, '/skills') !== false) {
            removeJobSkill($jobId);
        } elseif ($jobId) {
            deleteJob($jobId);
        } else {
            Response::notFound('Job ID required');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function createJob() {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        // Get employer profile
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer) {
            Response::error('Employer profile not found', 404);
        }
        
        // Validation
        $errors = [];
        if (empty($input['title'])) $errors['title'] = 'Title is required';
        if (empty($input['description'])) $errors['description'] = 'Description is required';
        if (empty($input['job_type'])) $errors['job_type'] = 'Job type is required';
        
        if (!in_array($input['job_type'], ['fixed_price', 'hourly'])) {
            $errors['job_type'] = 'Job type must be fixed_price or hourly';
        }
        
        if ($input['job_type'] === 'fixed_price') {
            if (empty($input['budget_min']) && empty($input['budget_max'])) {
                $errors['budget'] = 'Budget range is required for fixed price jobs';
            }
        } else {
            if (empty($input['hourly_rate_min']) && empty($input['hourly_rate_max'])) {
                $errors['hourly_rate'] = 'Hourly rate range is required for hourly jobs';
            }
        }
        
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        // Create job
        $input['employer_id'] = $employer['id'];
        $jobModel = new Job();
        $jobId = $jobModel->create($input);
        
        // Add skills if provided
        if (!empty($input['skills']) && is_array($input['skills'])) {
            foreach ($input['skills'] as $skillData) {
                if (isset($skillData['skill_id'])) {
                    $jobModel->addSkill($jobId, $skillData['skill_id'], $skillData['is_required'] ?? true);
                }
            }
        }
        
        // Update employer stats
        $employerModel->updateStats($employer['id'], 0, 1, 0);
        
        Response::success(['job_id' => $jobId], 'Job created successfully', 201);
        
    } catch (Exception $e) {
        Response::serverError('Failed to create job: ' . $e->getMessage());
    }
}

function getJob($jobId) {
    try {
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        // Increment view count
        $jobModel->incrementViews($jobId);
        
        // Get job skills
        $job['skills'] = $jobModel->getSkills($jobId);
        
        Response::success($job);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get job');
    }
}

function getAllJobs() {
    try {
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        $jobModel = new Job();
        $jobs = $jobModel->search([], $page, $limit);
        
        Response::success([
            'jobs' => $jobs,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($jobs)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get jobs');
    }
}

function searchJobs() {
    try {
        $filters = [];
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        // Build filters from query parameters
        $allowedFilters = [
            'category_id', 'job_type', 'experience_level', 'is_remote',
            'location', 'min_budget', 'max_budget', 'keywords'
        ];
        
        foreach ($allowedFilters as $filter) {
            if (isset($_GET[$filter]) && !empty($_GET[$filter])) {
                $filters[$filter] = $_GET[$filter];
            }
        }
        
        if (isset($_GET['skills'])) {
            $filters['skills'] = explode(',', $_GET['skills']);
        }
        
        $jobModel = new Job();
        $jobs = $jobModel->search($filters, $page, $limit);
        
        Response::success([
            'jobs' => $jobs,
            'filters' => $filters,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($jobs)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to search jobs');
    }
}

function getFeaturedJobs() {
    try {
        $limit = min($_GET['limit'] ?? 10, 50);
        
        $jobModel = new Job();
        $jobs = $jobModel->getFeatured($limit);
        
        Response::success($jobs);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get featured jobs');
    }
}

function getRecentJobs() {
    try {
        $limit = min($_GET['limit'] ?? 10, 50);
        
        $jobModel = new Job();
        $jobs = $jobModel->getRecent($limit);
        
        Response::success($jobs);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get recent jobs');
    }
}

function getMyJobs() {
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer) {
            Response::error('Employer profile not found', 404);
        }
        
        $status = $_GET['status'] ?? null;
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        $jobModel = new Job();
        $jobs = $jobModel->getByEmployer($employer['id'], $status, $page, $limit);
        
        Response::success([
            'jobs' => $jobs,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($jobs)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get jobs');
    }
}

function updateJob($jobId) {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        // Check if user owns this job
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only update your own jobs');
        }
        
        $success = $jobModel->update($jobId, $input);
        
        if ($success) {
            Response::success(null, 'Job updated successfully');
        } else {
            Response::error('Failed to update job');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to update job');
    }
}

function deleteJob($jobId) {
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        // Check if user owns this job
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only delete your own jobs');
        }
        
        $success = $jobModel->delete($jobId);
        
        if ($success) {
            Response::success(null, 'Job deleted successfully');
        } else {
            Response::error('Failed to delete job');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to delete job');
    }
}

function addJobSkill($jobId) {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        if (empty($input['skill_id'])) {
            Response::error('Skill ID is required');
        }
        
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        // Check if user owns this job
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only modify your own jobs');
        }
        
        $success = $jobModel->addSkill($jobId, $input['skill_id'], $input['is_required'] ?? true);
        
        if ($success) {
            Response::success(null, 'Skill added to job successfully');
        } else {
            Response::error('Failed to add skill to job');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to add job skill');
    }
}

function removeJobSkill($jobId) {
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        if (empty($_GET['skill_id'])) {
            Response::error('Skill ID is required');
        }
        
        $jobModel = new Job();
        $job = $jobModel->findById($jobId);
        
        if (!$job) {
            Response::notFound('Job not found');
        }
        
        // Check if user owns this job
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer || $job['employer_id'] != $employer['id']) {
            Response::forbidden('You can only modify your own jobs');
        }
        
        $success = $jobModel->removeSkill($jobId, $_GET['skill_id']);
        
        if ($success) {
            Response::success(null, 'Skill removed from job successfully');
        } else {
            Response::error('Failed to remove skill from job');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to remove job skill');
    }
}

function getJobStatistics() {
    try {
        $jobModel = new Job();
        $stats = $jobModel->getStatistics();
        
        Response::success($stats);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get job statistics');
    }
}