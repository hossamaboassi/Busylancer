<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/JobApplication.php';
require_once __DIR__ . '/../models/CandidateProfile.php';
require_once __DIR__ . '/../models/EmployerProfile.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Router
switch ($method) {
    case 'GET':
        if (strpos($path, '/candidate') !== false) {
            getCandidateDashboard();
        } elseif (strpos($path, '/employer') !== false) {
            getEmployerDashboard();
        } elseif (strpos($path, '/stats') !== false) {
            getDashboardStats();
        } else {
            getDashboard();
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

function getDashboard() {
    try {
        $payload = AuthMiddleware::authenticate();
        
        if ($payload['user_type'] === 'candidate') {
            getCandidateDashboard();
        } else {
            getEmployerDashboard();
        }
        
    } catch (Exception $e) {
        Response::serverError('Failed to get dashboard data');
    }
}

function getCandidateDashboard() {
    try {
        $payload = AuthMiddleware::requireCandidate();
        
        $candidateModel = new CandidateProfile();
        $candidate = $candidateModel->findByUserId($payload['user_id']);
        
        if (!$candidate) {
            Response::error('Candidate profile not found', 404);
        }
        
        $applicationModel = new JobApplication();
        $jobModel = new Job();
        
        // Get application statistics
        $appStats = $applicationModel->getStatistics($candidate['id']);
        
        // Get recent applications
        $recentApplications = $applicationModel->getRecent(5, $candidate['id']);
        
        // Get recommended jobs (recent jobs matching candidate skills)
        $recommendedJobs = $jobModel->getRecent(5);
        
        // Get featured jobs
        $featuredJobs = $jobModel->getFeatured(5);
        
        $dashboardData = [
            'profile' => $candidate,
            'statistics' => $appStats,
            'recent_applications' => $recentApplications,
            'recommended_jobs' => $recommendedJobs,
            'featured_jobs' => $featuredJobs
        ];
        
        Response::success($dashboardData);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get candidate dashboard: ' . $e->getMessage());
    }
}

function getEmployerDashboard() {
    try {
        $payload = AuthMiddleware::requireEmployer();
        
        $employerModel = new EmployerProfile();
        $employer = $employerModel->findByUserId($payload['user_id']);
        
        if (!$employer) {
            Response::error('Employer profile not found', 404);
        }
        
        $jobModel = new Job();
        $applicationModel = new JobApplication();
        
        // Get job statistics for this employer
        $activeJobs = $jobModel->getByEmployer($employer['id'], 'active', 1, 10);
        
        // Get application statistics
        $appStats = $applicationModel->getStatistics(null, $employer['id']);
        
        // Get recent applications to employer's jobs
        $recentApplications = $applicationModel->getRecent(5, null, $employer['id']);
        
        // Get featured candidates
        $candidateModel = new CandidateProfile();
        $featuredCandidates = $candidateModel->getFeatured(5);
        
        $dashboardData = [
            'profile' => $employer,
            'active_jobs' => $activeJobs,
            'statistics' => $appStats,
            'recent_applications' => $recentApplications,
            'featured_candidates' => $featuredCandidates
        ];
        
        Response::success($dashboardData);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get employer dashboard: ' . $e->getMessage());
    }
}

function getDashboardStats() {
    try {
        $payload = AuthMiddleware::authenticate();
        
        $db = new Database();
        
        // Get platform-wide statistics
        $platformStats = [
            'total_jobs' => $db->fetch("SELECT COUNT(*) as count FROM jobs")['count'],
            'active_jobs' => $db->fetch("SELECT COUNT(*) as count FROM jobs WHERE status = 'active'")['count'],
            'total_candidates' => $db->fetch("SELECT COUNT(*) as count FROM candidate_profiles")['count'],
            'total_employers' => $db->fetch("SELECT COUNT(*) as count FROM employer_profiles")['count'],
            'total_applications' => $db->fetch("SELECT COUNT(*) as count FROM job_applications")['count']
        ];
        
        // Get user-specific stats
        if ($payload['user_type'] === 'candidate') {
            $candidateModel = new CandidateProfile();
            $candidate = $candidateModel->findByUserId($payload['user_id']);
            
            if ($candidate) {
                $applicationModel = new JobApplication();
                $userStats = $applicationModel->getStatistics($candidate['id']);
            } else {
                $userStats = [];
            }
        } else {
            $employerModel = new EmployerProfile();
            $employer = $employerModel->findByUserId($payload['user_id']);
            
            if ($employer) {
                $applicationModel = new JobApplication();
                $userStats = $applicationModel->getStatistics(null, $employer['id']);
                
                $jobModel = new Job();
                $jobStats = $jobModel->getStatistics();
                $userStats = array_merge($userStats, $jobStats);
            } else {
                $userStats = [];
            }
        }
        
        Response::success([
            'platform_stats' => $platformStats,
            'user_stats' => $userStats
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Failed to get dashboard statistics');
    }
}