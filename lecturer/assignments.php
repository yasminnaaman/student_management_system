<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Get lecturer's courses for dropdown
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.course_name 
                       FROM courses c
                       WHERE c.lecturer_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer_courses = $stmt->fetchAll();

// Handle new assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_score = $_POST['max_score'];
    
    $errors = [];
    
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
            $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, due_date, max_score) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $description, $due_date, $max_score]);
            
            $_SESSION['success'] = "Assignment created successfully!";
            header("Location: assignments.php");
            exit();
        } catch (Exception $e) {
            $errors[] = "Failed to create assignment: " . $e->getMessage();
        }
    }
}

// Get lecturer's assignments
$stmt = $pdo->prepare("SELECT a.*, c.course_code, c.course_name,
                      (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.assignment_id) as submissions_count
                      FROM assignments a
                      JOIN courses c ON a.course_id = c.course_id
                      WHERE c.lecturer_id = ?
                      ORDER BY a.due_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$assignments = $stmt->fetchAll();
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
                    <h1 class="h2 mb-4">Assignments</h1>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success mb-4">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Create Assignment Form -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Create New Assignment</h2>
                        
                        <form action="" method="post">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="course_id" class="form-label">Course</label>
                                    <select name="course_id" id="course_id" class="form-select" required>
                                        <option value="">Select Course</option>
                                        <?php foreach ($lecturer_courses as $course): ?>
                                            <option value="<?php echo $course['course_id']; ?>">
                                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Assignment Title</label>
                                    <input type="text" name="title" id="title" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" rows="3" class="form-control"></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="due_date" class="form-label">Due Date</label>
                                    <input type="datetime-local" name="due_date" id="due_date" class="form-control" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="max_score" class="form-label">Max Score</label>
                                    <input type="number" name="max_score" id="max_score" min="1" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="create_assignment" class="btn btn-primary">
                                    Create Assignment
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Assignment List -->
                    <div>
                        <h2 class="h4 mb-3">Your Assignments</h2>
                        
                        <?php if (empty($assignments)): ?>
                            <p class="text-muted">You haven't created any assignments yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Title</th>
                                            <th>Due Date</th>
                                            <th>Submissions</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                <td>
                                                    <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                                    <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                        <span class="badge bg-danger ms-2">Closed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success ms-2">Open</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $assignment['submissions_count']; ?></td>
                                                <td>
                                                    <a href="view_submissions.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary me-2">View</a>
                                                    <a href="edit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-success">Edit</a>
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
    
    // Set default to 1 week from now
    const oneWeekLater = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
    const oneWeekYear = oneWeekLater.getFullYear();
    const oneWeekMonth = String(oneWeekLater.getMonth() + 1).padStart(2, '0');
    const oneWeekDay = String(oneWeekLater.getDate()).padStart(2, '0');
    const oneWeekHours = String(oneWeekLater.getHours()).padStart(2, '0');
    const oneWeekMinutes = String(oneWeekLater.getMinutes()).padStart(2, '0');
    
    const defaultDateTime = `${oneWeekYear}-${oneWeekMonth}-${oneWeekDay}T${oneWeekHours}:${oneWeekMinutes}`;
    dueDateField.value = defaultDateTime;
});
</script>

<?php include __DIR__ . '/../includes/footer.php';?>
