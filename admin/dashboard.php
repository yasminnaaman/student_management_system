<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// Get counts for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students");
$total_students = $stmt->fetch()['total_students'];

$stmt = $pdo->query("SELECT COUNT(*) as total_lecturers FROM lecturers");
$total_lecturers = $stmt->fetch()['total_lecturers'];

$stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses");
$total_courses = $stmt->fetch()['total_courses'];

// Get recent users
$stmt = $pdo->query("SELECT user_id, username, first_name, last_name, role, created_at 
                     FROM users 
                     ORDER BY created_at DESC 
                     LIMIT 5");
$recent_users = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php';?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="/student_management_system/assets/images/<?php echo htmlspecialchars($profilePic); ?>"
     onerror="this.onerror=null; this.src='/student_management_system/assets/images/default_profile.png';"
     alt="Profile Picture"
     style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%;">
                    <h2 class="h5"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                    <p class="text-muted">Administrator</p>
                </div>
                
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="users.php" class="list-group-item list-group-item-action">Manage Users</a>
                    <a href="courses.php" class="list-group-item list-group-item-action">Manage Courses</a>
                    <a href="profile.php" class="list-group-item list-group-item-action">Profile Settings</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="h2 mb-4">Admin Dashboard</h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Total Users</h3>
                                    <p class="display-4"><?php echo $total_users; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Students</h3>
                                    <p class="display-4"><?php echo $total_students; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Lecturers</h3>
                                    <p class="display-4"><?php echo $total_lecturers; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Courses</h3>
                                    <p class="display-4"><?php echo $total_courses; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Users -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Recently Registered Users</h2>
                        
                        <?php if (empty($recent_users)): ?>
                            <p class="text-muted">No users found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="users.php?action=create" class="btn btn-primary">Add New User</a>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div>
                        <h2 class="h4 mb-3">Quick Actions</h2>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="users.php" class="card h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <div class="text-primary mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16">
                                                <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                                <path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216z"/>
                                                <path d="M4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>
                                            </svg>
                                        </div>
                                        <h3 class="h5">Manage Users</h3>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <a href="courses.php" class="card h-100 text-decoration-none">
                                    <div class="card-body text-center">
                                        <div class="text-success mb-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-book-fill" viewBox="0 0 16 16">
                                                <path d="M8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                                            </svg>
                                        </div>
                                        <h3 class="h5">Manage Courses</h3>
                                    </div>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';?>