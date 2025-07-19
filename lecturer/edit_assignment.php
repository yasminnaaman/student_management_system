<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Get assignment ID from URL
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    $_SESSION['error'] = "Assignment not specified.";
    header("Location: assignments.php");
    exit();
}

// Get assignment details and verify lecturer owns it
$stmt = $pdo->prepare("SELECT a.*, c.course_code, c.course_name 
                      FROM assignments a
                      JOIN courses c ON a.course_id = c.course_id
                      WHERE a.assignment_id = ? AND c.lecturer_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    $_SESSION['error'] = "Assignment not found or you don't have permission to edit it.";
    header("Location: assignments.php");
    exit();
}

// Get lecturer's courses for dropdown
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name 
                       FROM courses c
                       WHERE c.lecturer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer_courses = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_score = $_POST['max_score'];
    
    $errors = [];
    
    // Validation
    if (empty($course_id) || empty($title) || empty($due_date) || empty($max_score)) {
        $errors[] = "Required fields are missing.";
    }
    
    if (strtotime($due_date) < time()) {
        $errors[] = "Due date must be in the future.";
    }
    
    if (!is_numeric($max_score) || $max_score <= 0) {
        $errors[] = "Max score must be a positive number.";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE assignments 
                                  SET course_id = ?, title = ?, description = ?, 
                                  due_date = ?, max_score = ?
                                  WHERE assignment_id = ?");
            $stmt->execute([$course_id, $title, $description, $due_date, $max_score, $assignment_id]);
            
            $_SESSION['success'] = "Assignment updated successfully!";
            header("Location: view_submissions.php?assignment_id=" . $assignment_id);
            exit();
        } catch (Exception $e) {
            $errors[] = "Failed to update assignment: " . $e->getMessage();
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h2 mb-0">Edit Assignment</h1>
                        <a href="view_submissions.php?assignment_id=<?php echo $assignment_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Assignment
                        </a>
                    </div>
                    
                    <!-- Alerts -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo $error; ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Edit Assignment Form -->
                    <form method="post">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <?php foreach ($lecturer_courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>"
                                            <?php echo $course['course_id'] == $assignment['course_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Assignment Title</label>
                                <input type="text" name="title" id="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" rows="5" class="form-control"><?php echo htmlspecialchars($assignment['description']); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="datetime-local" name="due_date" id="due_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" 
                                       class="form-control" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="max_score" class="form-label">Max Score</label>
                                <input type="number" name="max_score" id="max_score" min="1" 
                                       value="<?php echo $assignment['max_score']; ?>" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="update_assignment" class="btn btn-primary me-2">
                                <i class="fas fa-save"></i> Update Assignment
                            </button>
                            <a href="view_submissions.php?assignment_id=<?php echo $assignment_id; ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set minimum datetime for due date (current time)
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const dueDateField = document.getElementById('due_date');
    
    // Format to YYYY-MM-DDTHH:MM
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    
    const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    dueDateField.min = minDateTime;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>