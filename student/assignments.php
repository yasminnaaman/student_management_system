<?php
session_start();

require_once __DIR__ . '/../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header("Location: /index.php");
    exit();
}

// Handle file upload (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = $_POST['assignment_id'];
    
    // Verify student is enrolled in this assignment's course
    $stmt = $pdo->prepare("SELECT COUNT(*) 
                          FROM assignments a
                          JOIN student_courses sc ON a.course_id = sc.course_id
                          WHERE a.assignment_id = ? AND sc.student_id = ?");
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $valid_assignment = $stmt->fetchColumn();
    
    if (!$valid_assignment) {
        $_SESSION['error'] = "You are not enrolled in this assignment's course.";
        header("Location: assignments.php");
        exit();
    }
    
    // Check if assignment is still open
    $stmt = $pdo->prepare("SELECT due_date FROM assignments WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $due_date = $stmt->fetchColumn();
    
    if (strtotime($due_date) < time()) {
        $_SESSION['error'] = "The due date for this assignment has passed.";
        header("Location: assignments.php");
        exit();
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Please select a file to upload.";
        header("Location: assignments.php?action=submit&id=$assignment_id");
        exit();
    }
    
    // Validate file
    $file = $_FILES['submission_file'];
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Only PDF and Word documents are allowed.";
        header("Location: assignments.php?action=submit&id=$assignment_id");
        exit();
    }
    
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size must be less than 5MB.";
        header("Location: assignments.php?action=submit&id=$assignment_id");
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = "../../uploads/assignments/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "submission_" . $_SESSION['user_id'] . "_" . $assignment_id . "_" . time() . "." . $file_ext;
    $destination = $upload_dir . $filename;
    
    // Check if student has already submitted
    $stmt = $pdo->prepare("SELECT submission_id FROM assignment_submissions 
                          WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $existing_submission = $stmt->fetchColumn();
    
    try {
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            if ($existing_submission) {
                // Update existing submission
                $stmt = $pdo->prepare("UPDATE assignment_submissions 
                                      SET file_path = ?, submission_date = NOW() 
                                      WHERE submission_id = ?");
                $stmt->execute([$filename, $existing_submission]);
                $_SESSION['success'] = "Submission updated successfully!";
            } else {
                // Create new submission
                $stmt = $pdo->prepare("INSERT INTO assignment_submissions 
                                      (assignment_id, student_id, file_path) 
                                      VALUES (?, ?, ?)");
                $stmt->execute([$assignment_id, $_SESSION['user_id'], $filename]);
                $_SESSION['success'] = "Assignment submitted successfully!";
            }
            
            header("Location: assignments.php");
            exit();
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting assignment: " . $e->getMessage();
        header("Location: assignments.php?action=submit&id=$assignment_id");
        exit();
    }
}

// Get action and assignment ID if specified
$action = $_GET['action'] ?? '';
$assignment_id = $_GET['id'] ?? 0;

// View specific assignment
if ($action === 'view' && $assignment_id) {
    $stmt = $pdo->prepare("SELECT a.*, c.course_code, c.course_name
                          FROM assignments a
                          JOIN courses c ON a.course_id = c.course_id
                          JOIN student_courses sc ON c.course_id = sc.course_id
                          WHERE a.assignment_id = ? AND sc.student_id = ?");
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        $_SESSION['error'] = "Assignment not found or you are not enrolled in this course.";
        header("Location: assignments.php");
        exit();
    }
    
    // Check if student has submitted
    $stmt = $pdo->prepare("SELECT * FROM assignment_submissions 
                          WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $submission = $stmt->fetch();
}

// Get all assignments for the student
$stmt = $pdo->prepare("SELECT a.*, c.course_code, c.course_name,
                      (SELECT COUNT(*) FROM assignment_submissions s 
                       WHERE s.assignment_id = a.assignment_id AND s.student_id = ?) as submitted
                      FROM assignments a
                      JOIN courses c ON a.course_id = c.course_id
                      JOIN student_courses sc ON c.course_id = sc.course_id
                      WHERE sc.student_id = ?
                      ORDER BY a.due_date ASC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$assignments = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php';?>

<div class="container mt-4">
    <div class="row">
        <!-- Sidebar (same as dashboard) -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-body">
                    <h1 class="h2 mb-4">Assignments</h1>
                    
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
                    
                    <!-- View Single Assignment -->
                    <?php if ($action === 'view' && $assignment): ?>
                        <div class="mb-5">
                            <h2 class="h4 mb-3">Assignment Details</h2>
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h3 class="h5 card-title"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                    <p class="text-muted mb-2">Course: <?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name']); ?></p>
                                    <p class="text-muted mb-3">Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
                                    
                                    <div class="assignment-description">
                                        <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submission Status -->
                            <div class="mb-4">
                                <h3 class="h5 mb-3">Your Submission</h3>
                                
                                <?php if ($submission): ?>
                                    <div class="alert alert-success">
                                        <p class="mb-2"><strong>Submitted on <?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?></strong></p>
                                        <p class="mb-2">File: <?php echo htmlspecialchars($submission['file_path']); ?></p>
                                        <a href="../../uploads/assignments/<?php echo htmlspecialchars($submission['file_path']); ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           download>Download Submission</a>
                                    </div>
                                    
                                    <?php if (strtotime($assignment['due_date']) > time()): ?>
                                        <p class="text-muted mt-2">You can update your submission until the due date.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p class="mb-0">You haven't submitted this assignment yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Submission Form (if not past due date) -->
                            <?php if (strtotime($assignment['due_date']) > time()): ?>
                                <form method="post" action="assignments.php" enctype="multipart/form-data">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="submission_file" class="form-label">Upload Submission</label>
                                        <input type="file" class="form-control" name="submission_file" id="submission_file" required>
                                        <div class="form-text">Accepted formats: PDF, DOC, DOCX (Max 5MB)</div>
                                    </div>
                                    
                                    <button type="submit" name="submit_assignment" class="btn btn-primary">
                                        <?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <p class="mb-0">The due date for this assignment has passed. No further submissions are allowed.</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="assignments.php" class="btn btn-outline-secondary">&larr; Back to Assignments</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Assignment List -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course</th>
                                        <th>Assignment</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                                <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                    <span class="badge bg-danger ms-2">Closed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success ms-2">Open</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($assignment['submitted']): ?>
                                                    <span class="badge bg-success">Submitted</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
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
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
