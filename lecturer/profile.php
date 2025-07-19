<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'lecturer') {
    header("Location: /index.php");
    exit();
}

// Get lecturer profile data
$stmt = $pdo->prepare("SELECT u.*, l.staff_id, l.department 
                      FROM users u
                      JOIN lecturers l ON u.user_id = l.lecturer_id
                      WHERE u.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

// Handle profile update
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $errors[] = "Name and email fields are required.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Password change validation
    $password_changed = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password.";
        } elseif (!password_verify($current_password, $profile['password'])) {
            $errors[] = "Current password is incorrect.";
        } elseif (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        } else {
            $password_changed = true;
        }
    }
    
    // Handle file upload
    $profile_pic = $profile['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/profile_pics/';
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } elseif ($_FILES['profile_pic']['size'] > 2000000) {
            $errors[] = "File size must be less than 2MB.";
        } else {
            $new_filename = 'lecturer_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                // Delete old profile pic if it's not the default
                if ($profile_pic !== 'default_profile.png' && file_exists($upload_dir . $profile_pic)) {
                    unlink($upload_dir . $profile_pic);
                }
                $profile_pic = $new_filename;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update users table
            $update_user = "UPDATE users SET 
                           first_name = ?, last_name = ?, email = ?, profile_pic = ?";
            
            $params = [$first_name, $last_name, $email, $profile_pic];
            
            if ($password_changed) {
                $update_user .= ", password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $update_user .= " WHERE user_id = ?";
            $params[] = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare($update_user);
            $stmt->execute($params);
            
            // Update lecturers table
            $stmt = $pdo->prepare("UPDATE lecturers SET department = ? WHERE lecturer_id = ?");
            $stmt->execute([$department, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
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
                        <h1 class="h2 mb-0">Profile Settings</h1>
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
                    
                    <!-- Profile Form -->
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-4">
                                <!-- Profile Picture -->
                                <div class="card mb-4">
                                    <div class="card-body text-center">
                                        <img src="../assets/images/profile_pics/<?php echo htmlspecialchars($profile['profile_pic'] ?? 'default_profile.png'); ?>"
                                             class="rounded-circle mb-3"
                                             style="width: 200px; height: 200px; object-fit: cover;"
                                             onerror="this.onerror=null; this.src='../assets/images/default_profile.png';"
                                             alt="Profile Picture">
                                        <div class="mb-3">
                                            <label for="profile_pic" class="form-label">Change Profile Picture</label>
                                            <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <!-- Personal Information -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Personal Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="department" class="form-label">Department</label>
                                            <input type="text" class="form-control" id="department" name="department" 
                                                   value="<?php echo htmlspecialchars($profile['department']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Staff ID</label>
                                            <input type="text" class="form-control" 
                                                   value="<?php echo htmlspecialchars($profile['staff_id']); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Change Password -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Change Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                            <small class="text-muted">At least 8 characters</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>