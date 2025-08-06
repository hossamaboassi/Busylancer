<?php
session_start();
require_once '../config/database.php';

// Simple admin check (in production, use proper authentication)
$admin_email = 'admin@busy-lancer.com'; // Change this to your admin email

// Check if user is logged in and is admin
if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] !== $admin_email) {
    // Redirect to login or show access denied
    header('Location: ../index.html');
    exit;
}

// Handle user approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Send approval email
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $to = $user['email'];
            $subject = "Your BusyLancer Account is Approved!";
            $message = "Hi " . $user['name'] . ",\n\nGreat news! Your BusyLancer account has been approved.\n\nYou can now login and start using the platform.\n\nBest regards,\nThe BusyLancer Team";
            $headers = "From: noreply@busy-lancer.com";
            
            mail($to, $subject, $message, $headers);
        }
        
        header('Location: index.php?message=User approved successfully');
        exit;
    }
    
    if ($_POST['action'] === 'reject') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: index.php?message=User rejected');
        exit;
    }
}

// Get pending users
$stmt = $pdo->prepare("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved users
$stmt = $pdo->prepare("SELECT * FROM users WHERE status = 'approved' ORDER BY created_at DESC");
$stmt->execute();
$approved_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get jobs
$stmt = $pdo->prepare("SELECT j.*, u.name as employer_name FROM jobs j LEFT JOIN users u ON j.employer_id = u.id ORDER BY j.created_at DESC");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get applications
$stmt = $pdo->prepare("SELECT a.*, j.title as job_title, u.name as applicant_name FROM applications a LEFT JOIN jobs j ON a.job_id = j.id LEFT JOIN users u ON a.user_id = u.id ORDER BY a.applied_at DESC");
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BusyLancer Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">BusyLancer Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.html">Back to Site</a>
                <a class="nav-link" href="../api/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2><?php echo count($approved_users) + count($pending_users); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending Approval</h5>
                        <h2><?php echo count($pending_users); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Active Jobs</h5>
                        <h2><?php echo count($jobs); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Applications</h5>
                        <h2><?php echo count($applications); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Users -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Pending User Approvals</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_users)): ?>
                    <p class="text-muted">No pending users to approve.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['user_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($user['location'] ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Jobs -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-briefcase"></i> Recent Jobs</h5>
            </div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <p class="text-muted">No jobs posted yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Employer</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Posted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($jobs, 0, 10) as $job): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                        <td><?php echo htmlspecialchars($job['employer_name'] ?: 'Anonymous'); ?></td>
                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($job['job_type']); ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                        <td><span class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($job['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-file-alt"></i> Recent Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <p class="text-muted">No applications submitted yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Job</th>
                                    <th>Applied</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($applications, 0, 10) as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></td>
                                        <td><span class="badge bg-<?php echo $app['status'] === 'pending' ? 'warning' : ($app['status'] === 'accepted' ? 'success' : 'danger'); ?>"><?php echo htmlspecialchars($app['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>