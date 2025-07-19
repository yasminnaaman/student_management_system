<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Handle course deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['course_id'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete from courses table
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ? AND lecturer_id = ?");
        $stmt->execute([$_GET['course_id']], $_SESSION['user_id']);
        
        $pdo->commit();
        $_SESSION['success'] = "Course deleted successfully!";
        header("Location: courses.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to delete course: " . $e->getMessage();
        header("Location: courses.php");
        exit();
    }
}

// Get lecturer's courses
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name, 
                      COUNT(sc.student_id) as student_count,
                      COUNT(a.assignment_id) as assignment_count
                      FROM courses c
                      LEFT JOIN student_courses sc ON c.course_id = sc.course_id
                      LEFT JOIN assignments a ON c.course_id = a.course_id
                      WHERE c.lecturer_id = ?
                      GROUP BY c.course_id
                      ORDER BY c.course_code");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Get course details if specific course is selected
$selected_course = null;
$course_students = [];
$course_assignments = [];

if (isset($_GET['course_id']) && !isset($_GET['action'])) {
    $course_id = $_GET['course_id'];
    
    // Verify lecturer teaches this course
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ? AND lecturer_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $selected_course = $stmt->fetch();
    
    if ($selected_course) {
        // Get students enrolled in this course
        $stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email, 
                              s.registration_number, u.profile_pic
                              FROM users u
                              JOIN students s ON u.user_id = s.student_id
                              JOIN student_courses sc ON s.student_id = sc.student_id
                              WHERE sc.course_id = ?
                              ORDER BY u.last_name, u.first_name");
        $stmt->execute([$course_id]);
        $course_students = $stmt->fetchAll();
        
        // Get assignments for this course
        $stmt = $pdo->prepare("SELECT a.*, 
                              (SELECT COUNT(*) FROM assignment_submissions s 
                               WHERE s.assignment_id = a.assignment_id) as submissions_count
                              FROM assignments a
                              WHERE a.course_id = ?
                              ORDER BY a.due_date DESC");
        $stmt->execute([$course_id]);
        $course_assignments = $stmt->fetchAll();
    } else {
        $_SESSION['error'] = "You are not authorized to view this course.";
        header("Location: courses.php");
        exit();
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Courses</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group">
                        
                        <a href="assignments.php?action=create" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fas fa-tasks me-1"></i> New Assignment
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Course Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Courses</h6>
                                    <h3 class="mb-0"><?php echo count($courses); ?></h3>
                                </div>
                                <i class="fas fa-book fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Students</h6>
                                    <h3 class="mb-0"><?php echo array_sum(array_column($courses, 'student_count')); ?></h3>
                                </div>
                                <i class="fas fa-users fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Assignments</h6>
                                    <h3 class="mb-0"><?php echo array_sum(array_column($courses, 'assignment_count')); ?></h3>
                                </div>
                                <i class="fas fa-tasks fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Your Teaching Courses</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($courses)): ?>
                        <div class="alert alert-info">You are not assigned to any courses yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th class="text-center">Students</th>
                                        <th class="text-center">Assignments</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <a href="courses.php?course_id=<?php echo $course['course_id']; ?>" class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($course['course_code']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill"><?php echo $course['student_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info rounded-pill"><?php echo $course['assignment_count']; ?></span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group" role="group">
                                                    <a href="courses.php?course_id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View">
                                                       <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Edit">
                                                       <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="courses.php?action=delete&course_id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this course?')">
                                                       <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course Details Section -->
            <?php if ($selected_course): ?>
                <div class="row">
                    <!-- Course Information -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Course Information</h5>
                                <div>
                                    <a href="edit_course.php?id=<?php echo $selected_course['course_id']; ?>" 
                                       class="btn btn-sm btn-outline-success me-1" title="Edit Course">
                                       <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="courses.php?action=delete&course_id=<?php echo $selected_course['course_id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Delete Course"
                                       onclick="return confirm('Are you sure you want to delete this course?')">
                                       <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <h4 class="mb-3"><?php echo htmlspecialchars($selected_course['course_code'] . ' - ' . $selected_course['course_name']); ?></h4>
                                <div class="mb-3">
                                    <h6 class="text-muted">Description</h6>
                                    <p><?php echo htmlspecialchars($selected_course['description'] ?? 'No description available'); ?></p>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Students Enrolled</h6>
                                        <p class="h4"><?php echo count($course_students); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Active Assignments</h6>
                                        <p class="h4"><?php echo count($course_assignments); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Students Card -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Enrolled Students</h5>
                                <span class="badge bg-primary rounded-pill"><?php echo count($course_students); ?></span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($course_students)): ?>
                                    <div class="p-3 text-center text-muted">No students enrolled in this course yet.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($course_students as $student): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex align-items-center">
                                                    <img src="../assets/images/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                                                         class="rounded-circle me-3" 
                                                         width="45" height="45"
                                                         onerror="this.onerror=null; this.src='../assets/images/default_profile.png'"
                                                         alt="Student">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($student['registration_number']); ?></small>
                                                    </div>
                                                    <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Email">
                                                       <i class="fas fa-envelope"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Card -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Course Assignments</h5>
                                <a href="assignment.php?course_id=<?php echo $selected_course['course_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i> New Assignment
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($course_assignments)): ?>
                                    <div class="p-3 text-center text-muted">No assignments created for this course yet.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($course_assignments as $assignment): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                            <div>
                                                                <span class="badge bg-<?php echo strtotime($assignment['due_date']) < time() ? 'danger' : 'success'; ?>">
                                                                    <?php echo strtotime($assignment['due_date']) < time() ? 'Closed' : 'Open'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mb-2">
                                                            Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                                        </small>
                                                        <div class="d-flex">
                                                            <span class="badge bg-light text-dark me-2">
                                                                <i class="fas fa-users me-1"></i> <?php echo $assignment['submissions_count']; ?> submissions
                                                            </span>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="fas fa-star me-1"></i> Max: <?php echo $assignment['max_score']; ?> pts
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="btn-group ms-3">
                                                        <a href="assignment.php?action=view&id=<?php echo $assignment['assignment_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View">
                                                           <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="assignment.php?action=edit&id=<?php echo $assignment['assignment_id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" title="Edit">
                                                           <i class="fas fa-edit"></i>
                                                        </a>
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
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>