<?php
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $registration_number = $role === 'student' ? trim($_POST['registration_number']) : null;
    $staff_id = $role === 'lecturer' ? trim($_POST['staff_id']) : null;

    // Validation
    $errors = [];
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($role)) {
        $errors[] = "All fields are required.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Check if username or email exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Username or email already exists.";
    }

    // Role-specific validation
    if ($role === 'student' && empty($registration_number)) {
        $errors[] = "Registration number is required for students.";
    }

    if ($role === 'lecturer' && empty($staff_id)) {
        $errors[] = "Staff ID is required for lecturers.";
    }

    if (empty($errors)) {
        // Insert into users table
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $profile_pic = 'default_profile.jpg';

        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $role, $profile_pic]);
            $user_id = $pdo->lastInsertId();

            // Insert into role-specific table
            if ($role === 'student') {
                $stmt = $pdo->prepare("INSERT INTO students (student_id, registration_number) VALUES (?, ?)");
                $stmt->execute([$user_id, $registration_number]);
            } elseif ($role === 'lecturer') {
                $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_id, staff_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $staff_id]);
            }

            $pdo->commit();
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-5">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Register</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="post">
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select name="role" id="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="student" <?php echo isset($role) && $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="lecturer" <?php echo isset($role) && $role === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                <option value="admin" <?php echo isset($role) && $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required>
                        </div>
                        
                        <div id="student-fields" class="mb-3 <?php echo (isset($role) && $role === 'student') ? '' : 'd-none'; ?>">
                            <label for="registration_number" class="form-label">Registration Number</label>
                            <input type="text" name="registration_number" id="registration_number" class="form-control" value="<?php echo htmlspecialchars($registration_number ?? ''); ?>">
                        </div>
                        
                        <div id="lecturer-fields" class="mb-3 <?php echo (isset($role) && $role === 'lecturer') ? '' : 'd-none'; ?>">
                            <label for="staff_id" class="form-label">Staff ID</label>
                            <input type="text" name="staff_id" id="staff_id" class="form-control" value="<?php echo htmlspecialchars($staff_id ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                        
                        <p class="mt-3 text-center">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const studentFields = document.getElementById('student-fields');
    const lecturerFields = document.getElementById('lecturer-fields');
    
    studentFields.classList.toggle('d-none', role !== 'student');
    lecturerFields.classList.toggle('d-none', role !== 'lecturer');
    
    // Clear fields when hidden
    if (role !== 'student') document.getElementById('registration_number').value = '';
    if (role !== 'lecturer') document.getElementById('staff_id').value = '';
});
</script>

<?php include '../includes/footer.php'; ?>