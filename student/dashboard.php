<?php
// Start session at the very top
session_start();

require_once '../config/db_connection.php';

// Verify user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    // Redirect to login if not authenticated
    header("Location: ../login.php");
    exit();
}

// Get student data
$stmt = $pdo->prepare("SELECT s.*, u.* FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get registered courses
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name, c.credit_hours 
                       FROM student_courses sc 
                       JOIN courses c ON sc.course_id = c.course_id 
                       WHERE sc.student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Get recent assignments
$stmt = $pdo->prepare("SELECT a.assignment_id, a.title, a.due_date, c.course_code 
                       FROM assignments a
                       JOIN courses c ON a.course_id = c.course_id
                       JOIN student_courses sc ON c.course_id = sc.course_id
                       WHERE sc.student_id = ? AND a.due_date > NOW()
                       ORDER BY a.due_date ASC
                       LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$assignments = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                   <?php
$profilePic = !empty($student['profile_pic']) ? $student['profile_pic'] : 'default_profile.png';
?>
<img src="/student_management_system/assets/images/<?php echo htmlspecialchars($profilePic); ?>"
     onerror="this.onerror=null; this.src='/student_management_system/assets/images/default_profile.png';"
     alt="Profile Picture"
     style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%;">

                    <h2 class="h5"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars($student['registration_number']); ?></p>
                </div>
                
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="courses.php" class="list-group-item list-group-item-action">My Courses</a>
                    <a href="assignments.php" class="list-group-item list-group-item-action">Assignments</a>
                    <a href="results.php" class="list-group-item list-group-item-action">Results</a>
                    <a href="profile.php" class="list-group-item list-group-item-action">Profile Settings</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-body">
                    <h1 class="h2 mb-4">Student Dashboard</h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Registered Courses</h3>
                                    <p class="display-6"><?php echo count($courses); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Pending Assignments</h3>
                                    <p class="display-6"><?php echo count($assignments); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Assignments -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Upcoming Assignments</h2>
                        
                        <?php if (empty($assignments)): ?>
                            <p class="text-muted">No upcoming assignments.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Assignment</th>
                                            <th>Due Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></td>
                                                <td>
                                                    <a href="assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Registered Courses -->
                    <div>
                        <h2 class="h4 mb-3">Your Courses</h2>
                        
                        <?php if (empty($courses)): ?>
                            <p class="text-muted">You haven't registered for any courses yet.</p>
                            <a href="courses.php" class="btn btn-primary">Register for courses</a>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($courses as $course): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
<h3 class="h5 card-title">
    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
</h3>
                                                <p class="card-text text-muted">Credit Hours: <?php echo htmlspecialchars($course['credit_hours']); ?></p>
                                                <a href="courses.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>