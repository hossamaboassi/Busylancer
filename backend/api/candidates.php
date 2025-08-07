<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/CandidateProfile.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

// Extract candidate ID from URL if present
$pathParts = explode('/', trim($path, '/'));
$candidateId = null;
if (count($pathParts) >= 3 && is_numeric(end($pathParts))) {
    $candidateId = end($pathParts);
}

// Router
switch ($method) {
    case 'GET':
        if ($candidateId) {
            getCandidateProfile($candidateId);
        } elseif (strpos($path, '/search') !== false) {
            searchCandidates();
        } elseif (strpos($path, '/featured') !== false) {
            getFeaturedCandidates();
        } elseif (strpos($path, '/skills') !== false && $candidateId) {
            getCandidateSkills($candidateId);
        } else {
            getAllCandidates();
        }
        break;
        
    case 'POST':
        if ($candidateId && strpos($path, '/skills') !== false) {
            addCandidateSkill($candidateId);
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    case 'DELETE':
        if ($candidateId && strpos($path, '/skills') !== false) {
            removeCandidateSkill($candidateId);
        } else {
            Response::notFound('Endpoint not found');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function getCandidateProfile($candidateId) {
    try {
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findById($candidateId);
        
        if (!$candidate) {
            Response::notFound('Candidate not found');
        }
        
        // Get candidate skills
        $candidate['skills'] = $candidateModel->getSkills($candidateId);
        
        Response::success($candidate);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get candidate profile');
    }
}

function getAllCandidates() {
    try {
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        $candidateModel = new CandidateProfile();
        $candidates = $candidateModel->search([], $page, $limit);
        
        Response::success([
            'candidates' => $candidates,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($candidates)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get candidates');
    }
}

function searchCandidates() {
    try {
        $filters = [];
        $page = $_GET['page'] ?? 1;
        $limit = min($_GET['limit'] ?? DEFAULT_PAGE_SIZE, MAX_PAGE_SIZE);
        
        // Build filters from query parameters
        $allowedFilters = [
            'experience_level', 'availability', 'location', 'min_rate', 'max_rate'
        ];
        
        foreach ($allowedFilters as $filter) {
            if (isset($_GET[$filter]) && !empty($_GET[$filter])) {
                $filters[$filter] = $_GET[$filter];
            }
        }
        
        if (isset($_GET['skills'])) {
            $filters['skills'] = explode(',', $_GET['skills']);
        }
        
        $candidateModel = new CandidateProfile();
        $candidates = $candidateModel->search($filters, $page, $limit);
        
        Response::success([
            'candidates' => $candidates,
            'filters' => $filters,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => count($candidates)
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to search candidates');
    }
}

function getFeaturedCandidates() {
    try {
        $limit = min($_GET['limit'] ?? 10, 50);
        
        $candidateModel = new CandidateProfile();
        $candidates = $candidateModel->getFeatured($limit);
        
        Response::success($candidates);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get featured candidates');
    }
}

function getCandidateSkills($candidateId) {
    try {
        $candidateModel = new CandidateProfile();
        
        // Check if candidate exists
        $candidate = $candidateModel->findById($candidateId);
        if (!$candidate) {
            Response::notFound('Candidate not found');
        }
        
        $skills = $candidateModel->getSkills($candidateId);
        
        Response::success($skills);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get candidate skills');
    }
}

function addCandidateSkill($candidateId) {
    global $input;
    
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        if (empty($input['skill_id'])) {
            Response::error('Skill ID is required');
        }
        
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate || $candidate['id'] != $candidateId) {
            Response::forbidden('You can only modify your own skills');
        }
        
        $proficiency = $input['proficiency_level'] ?? 'intermediate';
        $success = $candidateModel->addSkill($candidateId, $input['skill_id'], $proficiency);
        
        if ($success) {
            Response::success(null, 'Skill added successfully');
        } else {
            Response::error('Failed to add skill');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to add candidate skill');
    }
}

function removeCandidateSkill($candidateId) {
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        if (empty($_GET['skill_id'])) {
            Response::error('Skill ID is required');
        }
        
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate || $candidate['id'] != $candidateId) {
            Response::forbidden('You can only modify your own skills');
        }
        
        $success = $candidateModel->removeSkill($candidateId, $_GET['skill_id']);
        
        if ($success) {
            Response::success(null, 'Skill removed successfully');
        } else {
            Response::error('Failed to remove skill');
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to remove candidate skill');
    }
}