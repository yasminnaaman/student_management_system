<?php
session_start();

require_once __DIR__ . '/../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header("Location: /index.php");
    exit();
}

// Get student's results
$stmt = $pdo->prepare("SELECT r.*, c.course_code, c.course_name, c.credit_hours
                      FROM results r
                      JOIN courses c ON r.course_id = c.course_id
                      WHERE r.student_id = ?
                      ORDER BY c.course_code");
$stmt->execute([$_SESSION['user_id']]);
$results = $stmt->fetchAll();

// Calculate GPA
$total_credits = 0;
$total_grade_points = 0;

foreach ($results as $result) {
    $grade_point = 0;
    
    // Simple grade point calculation (can be customized)
    if ($result['marks'] >= 80) {
        $grade_point = 4.0;
    } elseif ($result['marks'] >= 70) {
        $grade_point = 3.5;
    } elseif ($result['marks'] >= 60) {
        $grade_point = 3.0;
    } elseif ($result['marks'] >= 50) {
        $grade_point = 2.5;
    } elseif ($result['marks'] >= 40) {
        $grade_point = 2.0;
    } else {
        $grade_point = 0.0;
    }
    
    $total_grade_points += $grade_point * $result['credit_hours'];
    $total_credits += $result['credit_hours'];
}

$gpa = $total_credits > 0 ? $total_grade_points / $total_credits : 0;
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
                    <h1 class="h2 mb-4">Academic Results</h1>
                    
                    <!-- GPA Summary -->
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <h2 class="h4 mb-3">Academic Summary</h2>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="card bg-white text-dark">
                                        <div class="card-body text-center">
                                            <p class="text-muted mb-1">Courses Completed</p>
                                            <p class="h4 font-bold"><?php echo count($results); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="card bg-white text-dark">
                                        <div class="card-body text-center">
                                            <p class="text-muted mb-1">Total Credit Hours</p>
                                            <p class="h4 font-bold"><?php echo $total_credits; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card bg-white text-dark">
                                        <div class="card-body text-center">
                                            <p class="text-muted mb-1">Current GPA</p>
                                            <p class="h4 font-bold"><?php echo number_format($gpa, 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results Table -->
                    <div>
                        <h2 class="h4 mb-3">Course Results</h2>
                        
                        <?php if (empty($results)): ?>
                            <p class="text-muted">No results available yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Credits</th>
                                            <th>Marks</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($result['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($result['credit_hours']); ?></td>
                                                <td><?php echo htmlspecialchars($result['marks']); ?></td>
                                                <td>
                                                    <?php 
                                                    $grade = '';
                                                    $grade_class = '';
                                                    
                                                    if ($result['marks'] >= 80) {
                                                        $grade = 'A';
                                                        $grade_class = 'bg-success text-white';
                                                    } elseif ($result['marks'] >= 70) {
                                                        $grade = 'B';
                                                        $grade_class = 'bg-primary text-white';
                                                    } elseif ($result['marks'] >= 60) {
                                                        $grade = 'C';
                                                        $grade_class = 'bg-warning text-dark';
                                                    } elseif ($result['marks'] >= 50) {
                                                        $grade = 'D';
                                                        $grade_class = 'bg-orange text-white';
                                                    } elseif ($result['marks'] >= 40) {
                                                        $grade = 'E';
                                                        $grade_class = 'bg-danger text-white';
                                                    } else {
                                                        $grade = 'F';
                                                        $grade_class = 'bg-secondary text-white';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $grade_class; ?>">
                                                        <?php echo $grade; ?>
                                                    </span>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
