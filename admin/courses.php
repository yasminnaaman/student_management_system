<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$action = $_GET['action'] ?? '';
$course_id = $_GET['id'] ?? 0;

// Handle course creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = $_POST['credit_hours'];
    $description = trim($_POST['description']);
    $lecturer_id = $_POST['lecturer_id'] ?: null;
    
    $errors = [];
    
    // Validation
    if (empty($course_code) || empty($course_name) || empty($credit_hours)) {
        $errors[] = "Required fields are missing.";
    }
    
    if (!is_numeric($credit_hours) || $credit_hours <= 0) {
        $errors[] = "Credit hours must be a positive number.";
    }
    
    // Check for duplicate course code
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ? AND course_id != ?");
    $stmt->execute([$course_code, $course_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Course code already exists.";
    }
    
    if (empty($errors)) {
        try {
            if ($action === 'edit' && $course_id) {
                // Update existing course
                $stmt = $pdo->prepare("UPDATE courses 
                                      SET course_code = ?, course_name = ?, credit_hours = ?, 
                                          description = ?, lecturer_id = ?
                                      WHERE course_id = ?");
                $stmt->execute([$course_code, $course_name, $credit_hours, $description, $lecturer_id, $course_id]);
                
                $_SESSION['success'] = "Course updated successfully!";
            } else {
                // Create new course
                $stmt = $pdo->prepare("INSERT INTO courses 
                                      (course_code, course_name, credit_hours, description, lecturer_id) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$course_code, $course_name, $credit_hours, $description, $lecturer_id]);
                
                $_SESSION['success'] = "Course created successfully!";
            }
            
            header("Location: courses.php");
            exit();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle course deletion
if ($action === 'delete' && $course_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        
        $_SESSION['success'] = "Course deleted successfully!";
        header("Location: courses.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete course: " . $e->getMessage();
        header("Location: courses.php");
        exit();
    }
}

// Get course data for editing
$course = null;
if ($action === 'edit' && $course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error'] = "Course not found.";
        header("Location: courses.php");
        exit();
    }
}

// Get all lecturers for dropdown
$stmt = $pdo->query("SELECT l.lecturer_id, u.first_name, u.last_name 
                     FROM lecturers l
                     JOIN users u ON l.lecturer_id = u.user_id
                     ORDER BY u.last_name, u.first_name");
$lecturers = $stmt->fetchAll();

// Get all courses for listing
$stmt = $pdo->query("SELECT c.*, u.first_name, u.last_name 
                     FROM courses c
                     LEFT JOIN lecturers l ON c.lecturer_id = l.lecturer_id
                     LEFT JOIN users u ON l.lecturer_id = u.user_id
                     ORDER BY c.course_code");
$courses = $stmt->fetchAll();
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h2 mb-0">Course Management</h1>
                        <a href="courses.php?action=create" class="btn btn-primary">
                            Add New Course
                        </a>
                    </div>
                    
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
                    
                    <!-- Course Form for Create/Edit -->
                    <?php if ($action === 'create' || $action === 'edit'): ?>
                        <div class="mb-5">
                            <h2 class="h4 mb-3">
                                <?php echo $action === 'create' ? 'Create New Course' : 'Edit Course'; ?>
                            </h2>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-4">
                                    <?php foreach ($errors as $error): ?>
                                        <p class="mb-0"><?php echo $error; ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" action="courses.php?action=<?php echo $action; ?><?php echo $course_id ? "&id=$course_id" : ''; ?>">
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3">
                                        <label for="course_code" class="form-label">Course Code</label>
                                        <input type="text" name="course_code" id="course_code" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="course_name" class="form-label">Course Name</label>
                                        <input type="text" name="course_name" id="course_name" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($course['course_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="credit_hours" class="form-label">Credit Hours</label>
                                        <input type="number" name="credit_hours" id="credit_hours" min="1" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($course['credit_hours'] ?? '3'); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="lecturer_id" class="form-label">Lecturer (Optional)</label>
                                        <select name="lecturer_id" id="lecturer_id" class="form-select">
                                            <option value="">Select Lecturer</option>
                                            <?php foreach ($lecturers as $lecturer): ?>
                                                <option value="<?php echo $lecturer['lecturer_id']; ?>"
                                                    <?php echo ($course['lecturer_id'] ?? '') == $lecturer['lecturer_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea name="description" id="description" rows="3" 
                                                  class="form-control"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-3">
                                    <a href="courses.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $action === 'create' ? 'Create Course' : 'Update Course'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Courses List -->
                    <div>
                        <h2 class="h4 mb-3">All Courses</h2>
                        
                        <?php if (empty($courses)): ?>
                            <p class="text-muted">No courses found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Credits</th>
                                            <th>Lecturer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $c): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($c['credit_hours']); ?></td>
                                                <td>
                                                    <?php if ($c['first_name']): ?>
                                                        <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                                    <?php else: ?>
                                                        Not assigned
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="courses.php?action=edit&id=<?php echo $c['course_id']; ?>" class="btn btn-sm btn-outline-primary me-2">Edit</a>
                                                    <a href="courses.php?action=delete&id=<?php echo $c['course_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Are you sure you want to delete this course?')">Delete</a>
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
