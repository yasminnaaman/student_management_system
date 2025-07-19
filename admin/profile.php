<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username)) {
        $errors[] = "All required fields must be filled.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check for duplicate email or username
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = ? OR username = ?) AND user_id != ?");
    $stmt->execute([$email, $username, $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email or username already exists.";
    }

    // Password change validation
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password.";
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Handle profile picture upload
            $profile_pic = $user['profile_pic'];
            if (!empty($_FILES['profile_pic']['name'])) {
                $upload_dir = '../assets/images/';
                $file_name = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $upload_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if it's not the default
                    if ($profile_pic !== 'default_profile.png') {
                        @unlink($upload_dir . $profile_pic);
                    }
                    $profile_pic = $file_name;
                } else {
                    $errors[] = "Failed to upload profile picture.";
                    throw new Exception("File upload failed");
                }
            }

            // Update user data
            $update_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'username' => $username,
                'profile_pic' => $profile_pic,
                'user_id' => $_SESSION['user_id']
            ];

            // Add password update if changed
            if (!empty($new_password)) {
                $update_data['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, profile_pic = ?, password_hash = ? WHERE user_id = ?";
            } else {
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, profile_pic = ? WHERE user_id = ?";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($update_data));

            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;
            $_SESSION['profile_pic'] = $profile_pic;

            $pdo->commit();
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Profile Settings</h1>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-1"><?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="profile_pic" class="form-label">Profile Picture</label>
                                    <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept="image/*">
                                    <small class="text-muted">Max size: 2MB (JPEG, PNG)</small>
                                </div>

                                <div class="mb-4">
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>

                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Preview</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <img src="/student_management_system/assets/images/<?php echo htmlspecialchars($user['profile_pic'] ?? 'default_profile.png'); ?>" 
                                     class="rounded-circle" 
                                     style="width: 150px; height: 150px; object-fit: cover;"
                                     onerror="this.onerror=null; this.src='/student_management_system/assets/images/default_profile.png'"
                                     alt="Profile Picture">
                            </div>
                            <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></p>
                            <p class="text-muted">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>