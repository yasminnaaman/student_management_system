<?php
session_start();

require_once __DIR__ . '/../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header("Location: /index.php");
    exit();
}

// Get student data
$stmt = $pdo->prepare("SELECT s.*, u.* FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    
    $errors = [];
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $errors[] = "Required fields are missing.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email is already in use by another account.";
    }
    
    // Handle profile picture upload
    $profile_pic = $student['profile_pic'];
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed.";
        }
        
        if ($file['size'] > $max_size) {
            $errors[] = "Image size must be less than 2MB.";
        }
        
        if (empty($errors)) {
            // Create upload directory if it doesn't exist
            $upload_dir = "../../uploads/profile_pics/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "profile_" . $_SESSION['user_id'] . "_" . time() . "." . $file_ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Delete old profile picture if it's not the default
                if ($profile_pic !== 'default_profile.jpg') {
                    @unlink($upload_dir . $profile_pic);
                }
                $profile_pic = $filename;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users 
                                  SET first_name = ?, last_name = ?, email = ?, profile_pic = ?
                                  WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $email, $profile_pic, $_SESSION['user_id']]);
            
            // Update students table
            $stmt = $pdo->prepare("UPDATE students 
                                  SET date_of_birth = ?, gender = ?
                                  WHERE student_id = ?");
            $stmt->execute([$date_of_birth, $gender, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['profile_pic'] = $profile_pic;
            
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors_password = [];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors_password[] = "All password fields are required.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors_password[] = "New passwords do not match.";
    }
    
    if (strlen($new_password) < 8) {
        $errors_password[] = "Password must be at least 8 characters long.";
    }
    
    if (empty($errors_password)) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
            
            $_SESSION['success_password'] = "Password changed successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $errors_password[] = "Current password is incorrect.";
        }
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
            <div class="card">
                <div class="card-body">
                    <h1 class="h2 mb-4">Profile Settings</h1>
                    
                    <!-- Profile Update Form -->
                    <div class="mb-5">
                        <h2 class="h4 mb-3">Personal Information</h2>
                        
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
                        
                        <form method="post" action="profile.php" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="first_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="registration_number" class="form-label">Registration Number</label>
                                    <input type="text" id="registration_number" 
                                           class="form-control bg-light" 
                                           value="<?php echo htmlspecialchars($student['registration_number']); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($student['date_of_birth']); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select name="gender" id="gender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="profile_picture" class="form-label">Profile Picture</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../../assets/images/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                                             alt="Profile Picture" 
                                             class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover; border: 2px solid #dee2e6;">
                                        <input type="file" name="profile_picture" id="profile_picture" 
                                               class="form-control" 
                                               accept="image/jpeg,image/png,image/gif">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Password Change Form -->
                    <div>
                        <h2 class="h4 mb-3">Change Password</h2>
                        
                        <?php if (isset($_SESSION['success_password'])): ?>
                            <div class="alert alert-success mb-4">
                                <?php echo $_SESSION['success_password']; unset($_SESSION['success_password']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors_password)): ?>
                            <div class="alert alert-danger mb-4">
                                <?php foreach ($errors_password as $error): ?>
                                    <p class="mb-0"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="profile.php">
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" name="current_password" id="current_password" 
                                           class="form-control" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" name="new_password" id="new_password" 
                                           class="form-control" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="change_password" class="btn btn-success">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
