<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

$course_id = $_GET['course_id'] ?? null;

// Get lecturer's courses for dropdown
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name 
                       FROM courses c
                       WHERE c.lecturer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer_courses = $stmt->fetchAll();

// Handle marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_marks'])) {
    $course_id = $_POST['course_id'];
    $marks = $_POST['marks'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete existing marks for this course to avoid duplicates
        $stmt = $pdo->prepare("DELETE FROM results WHERE course_id = ?");
        $stmt->execute([$course_id]);
        
        // Insert new marks
        $stmt = $pdo->prepare("INSERT INTO results (student_id, course_id, marks, recorded_by) 
                              VALUES (?, ?, ?, ?)");
        
        foreach ($marks as $student_id => $mark) {
            if (!empty($mark)) {
                $stmt->execute([$student_id, $course_id, $mark, $_SESSION['user_id']]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Marks submitted successfully!";
        header("Location: marks.php?course_id=" . $course_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to submit marks: " . $e->getMessage();
    }
}

// Get students and marks for selected course
$students = [];
$existing_marks = [];

if ($course_id) {
    // Verify lecturer teaches this course
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $valid_course = $stmt->fetchColumn();
    
    if ($valid_course) {
        // Get students enrolled in this course
        $stmt = $pdo->prepare("SELECT s.student_id, u.first_name, u.last_name, u.profile_pic, sc.registration_date
                              FROM students s
                              JOIN users u ON s.student_id = u.user_id
                              JOIN student_courses sc ON s.student_id = sc.student_id
                              WHERE sc.course_id = ?
                              ORDER BY u.last_name, u.first_name");
        $stmt->execute([$course_id]);
        $students = $stmt->fetchAll();
        
        // Get existing marks for these students
        $stmt = $pdo->prepare("SELECT student_id, marks FROM results WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $existing_marks = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $_SESSION['error'] = "You are not authorized to enter marks for this course.";
        header("Location: marks.php");
        exit();
    }
}
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
                    <h1 class="h2 mb-4">Marks Entry</h1>
                    
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
                    
                    <!-- Course Selection -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Select Course</h2>
                        
                        <form method="get" action="marks.php" class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label for="course_id" class="form-label">Course</label>
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($lecturer_courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                            <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    Load Students
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Marks Entry Form -->
                    <?php if ($course_id && !empty($students)): ?>
                        <form method="post" action="marks.php">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            
                            <h2 class="h4 mb-3">Enter Marks for Students</h2>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Marks (0-100)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="../../assets/images/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                                                             alt="Profile" 
                                                             class="rounded-circle me-3" style="width: 40px; height: 40px;">
                                                        <div>
                                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                                            <small class="text-muted">Enrolled: <?php echo date('M j, Y', strtotime($student['registration_date'])); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           name="marks[<?php echo $student['student_id']; ?>]" 
                                                           value="<?php echo $existing_marks[$student['student_id']] ?? ''; ?>" 
                                                           min="0" 
                                                           max="100" 
                                                           class="form-control" style="width: 100px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4 text-end">
                                <button type="submit" name="submit_marks" class="btn btn-success">
                                    Submit Marks
                                </button>
                            </div>
                        </form>
                    <?php elseif ($course_id && empty($students)): ?>
                        <p class="text-muted">No students enrolled in this course yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';?>
