<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Get lecturer data
$stmt = $pdo->prepare("SELECT l.*, u.* FROM lecturers l JOIN users u ON l.lecturer_id = u.user_id WHERE l.lecturer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch();

// Get courses taught by this lecturer
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name, COUNT(sc.student_id) as student_count 
                       FROM courses c
                       LEFT JOIN student_courses sc ON c.course_id = sc.course_id
                       WHERE c.lecturer_id = ?
                       GROUP BY c.course_id");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Get recent assignments
$stmt = $pdo->prepare("SELECT a.assignment_id, a.title, a.due_date, c.course_code, 
                       COUNT(s.submission_id) as submissions_count
                       FROM assignments a
                       JOIN courses c ON a.course_id = c.course_id
                       LEFT JOIN assignment_submissions s ON a.assignment_id = s.assignment_id
                       WHERE c.lecturer_id = ?
                       GROUP BY a.assignment_id
                       ORDER BY a.due_date DESC
                       LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$assignments = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php';?>

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
                    <h2 class="h5"><?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars($lecturer['staff_id']); ?></p>
                </div>
                
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="courses.php" class="list-group-item list-group-item-action">My Courses</a>
                    <a href="assignments.php" class="list-group-item list-group-item-action">Assignments</a>
                    <a href="marks.php" class="list-group-item list-group-item-action">Marks Entry</a>
                    <a href="profile.php" class="list-group-item list-group-item-action">Profile Settings</a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="h2 mb-4">Lecturer Dashboard</h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Courses Teaching</h3>
                                    <p class="display-4"><?php echo count($courses); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Active Assignments</h3>
                                    <p class="display-4"><?php echo count($assignments); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h3 class="h5 card-title">Total Students</h3>
                                    <p class="display-4"><?php echo array_sum(array_column($courses, 'student_count')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Assignments -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Recent Assignments</h2>
                        
                        <?php if (empty($assignments)): ?>
                            <p class="text-muted">No assignments created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Assignment</th>
                                            <th>Due Date</th>
                                            <th>Submissions</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['submissions_count']); ?></td>
                                                <td>
                                                    <a href="assignments.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="assignments.php?action=create" class="btn btn-primary">Create New Assignment</a>
                        </div>
                    </div>
                    
                    <!-- Your Courses -->
                    <div>
                        <h2 class="h4 mb-3">Your Courses</h2>
                        
                        <?php if (empty($courses)): ?>
                            <p class="text-muted">You are not assigned to any courses yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Students</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($course['student_count']); ?></td>
                                                <td>
                                                    <a href="courses.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-primary me-2">View</a>
                                                    <a href="marks.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-success">Enter Marks</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';?>
