<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Get assignment details
$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    $_SESSION['error'] = "Assignment not specified.";
    header("Location: assignments.php");
    exit();
}

// Verify lecturer owns this assignment
$stmt = $pdo->prepare("SELECT a.*, c.course_code, c.course_name 
                      FROM assignments a
                      JOIN courses c ON a.course_id = c.course_id
                      WHERE a.assignment_id = ? AND c.lecturer_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    $_SESSION['error'] = "Assignment not found or you don't have permission to view it.";
    header("Location: assignments.php");
    exit();
}

// Get submissions for this assignment
$stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name, st.registration_number
                      FROM assignment_submissions s
                      JOIN students st ON s.student_id = st.student_id
                      JOIN users u ON st.student_id = u.user_id
                      WHERE s.assignment_id = ?
                      ORDER BY s.submission_date DESC");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];
    
    try {
        $stmt = $pdo->prepare("UPDATE assignment_submissions 
                              SET grade = ?, graded_at = NOW() 
                              WHERE submission_id = ?");
        $stmt->execute([$grade, $submission_id]);
        $_SESSION['success'] = "Grade updated successfully!";
        header("Location: view_submissions.php?assignment_id=" . $assignment_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update grade: " . $e->getMessage();
        header("Location: view_submissions.php?assignment_id=" . $assignment_id);
        exit();
    }
}

// Handle assignment deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['submission_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM assignment_submissions 
                              WHERE submission_id = ?");
        $stmt->execute([$_GET['submission_id']]);
        $_SESSION['success'] = "Submission deleted successfully!";
        header("Location: view_submissions.php?assignment_id=" . $assignment_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete submission: " . $e->getMessage();
        header("Location: view_submissions.php?assignment_id=" . $assignment_id);
        exit();
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
                        <h1 class="h2 mb-0">Assignment Submissions</h1>
                        <a href="assignment.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Assignments
                        </a>
                    </div>
                    
                    <!-- Assignment Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="h4"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name']); ?></p>
                            <p class="mb-1"><strong>Due Date:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
                            <p><strong>Max Score:</strong> <?php echo $assignment['max_score']; ?></p>
                            
                            <div class="mt-3">
                                <a href="edit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-edit"></i> Edit Assignment
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
                    
                    <!-- Submissions Table -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Student Submissions</h4>
                        </div>
                        <div class="card-body">
                            <?php if (empty($submissions)): ?>
                                <div class="alert alert-info">No submissions yet for this assignment.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Reg No.</th>
                                                <th>Submission Date</th>
                                                <th>File</th>
                                                <th>Grade</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $submission): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($submission['registration_number']); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?></td>
                                                    <td>
                                                        <?php if ($submission['file_path']): ?>
                                                            <a href="../uploads/<?php echo htmlspecialchars($submission['file_path']); ?>" 
                                                               class="btn btn-sm btn-outline-primary" download>
                                                               <i class="fas fa-download"></i> Download
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">No file</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-flex">
                                                            <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                                            <input type="number" name="grade" 
                                                                   value="<?php echo $submission['grade'] ?? ''; ?>" 
                                                                   min="0" max="<?php echo $assignment['max_score']; ?>"
                                                                   class="form-control form-control-sm" style="width: 80px;">
                                                            <button type="submit" name="update_grade" class="btn btn-sm btn-success ms-2">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <a href="view_submissions.php?action=delete&submission_id=<?php echo $submission['submission_id']; ?>&assignment_id=<?php echo $assignment_id; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this submission?')">
                                                           <i class="fas fa-trash-alt"></i>
                                                        </a>
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
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>