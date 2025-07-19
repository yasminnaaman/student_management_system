<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header("Location: /index.php");
    exit();
}

// Handle course registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_courses'])) {
    $selected_courses = $_POST['courses'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // First remove all current registrations
        $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Add new registrations
        $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id) VALUES (?, ?)");
        
        foreach ($selected_courses as $course_id) {
            $stmt->execute([$_SESSION['user_id'], $course_id]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Course registration updated successfully!";
        header("Location: courses.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to update course registration: " . $e->getMessage();
    }
}

// Get student's current courses
$stmt = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$registered_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all available courses
$stmt = $pdo->query("SELECT c.*, u.first_name, u.last_name 
                     FROM courses c
                     LEFT JOIN lecturers l ON c.lecturer_id = l.lecturer_id
                     LEFT JOIN users u ON l.lecturer_id = u.user_id
                     ORDER BY c.course_code");
$available_courses = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php';?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar (same as dashboard) -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="h2 mb-4">Course Registration</h1>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success mb-4">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger mb-4">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="courses.php" method="post">
                        <div class="mb-5">
                            <h2 class="h4 mb-3">Available Courses</h2>
                            
                            <?php if (empty($available_courses)): ?>
                                <p class="text-muted">No courses available for registration.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Select</th>
                                                <th>Code</th>
                                                <th>Course Name</th>
                                                <th>Lecturer</th>
                                                <th>Credits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($available_courses as $course): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="courses[]" 
                                                               value="<?php echo $course['course_id']; ?>" 
                                                               <?php echo in_array($course['course_id'], $registered_courses) ? 'checked' : ''; ?>
                                                               class="form-check-input">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                    <td>
                                                        <?php if ($course['first_name']): ?>
                                                            <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                        <?php else: ?>
                                                            Not assigned
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($course['credit_hours']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="register_courses" class="btn btn-primary">
                                Update Registration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Currently Registered Courses -->
            <div class="card">
                <div class="card-body">
                    <h2 class="h4 mb-3">Your Registered Courses</h2>
                    
                    <?php if (empty($registered_courses)): ?>
                        <p class="text-muted">You haven't registered for any courses yet.</p>
                    <?php else: ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT c.*, u.first_name, u.last_name 
                                              FROM courses c
                                              JOIN student_courses sc ON c.course_id = sc.course_id
                                              LEFT JOIN lecturers l ON c.lecturer_id = l.lecturer_id
                                              LEFT JOIN users u ON l.lecturer_id = u.user_id
                                              WHERE sc.student_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $registered_courses_details = $stmt->fetchAll();
                        ?>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Course Name</th>
                                        <th>Lecturer</th>
                                        <th>Credits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registered_courses_details as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td>
                                                <?php if ($course['first_name']): ?>
                                                    <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                <?php else: ?>
                                                    Not assigned
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['credit_hours']); ?></td>
                                            
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
