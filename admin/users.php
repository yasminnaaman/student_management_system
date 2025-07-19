<?php
session_start();
require_once '../config/db_connection.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $registration_number = $role === 'student' ? trim($_POST['registration_number']) : null;
    $staff_id = $role === 'lecturer' ? trim($_POST['staff_id']) : null;
    
    $errors = [];
    
    // Validation
    if (empty($username) || empty($email) || empty($first_name) || empty($last_name) || empty($role)) {
        $errors[] = "All required fields must be filled.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check for duplicate username or email
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->execute([$username, $email, $user_id]);
    if ($stmt->fetchColumn() > 0) {
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
        try {
            $pdo->beginTransaction();
            
            if ($action === 'edit' && $user_id) {
                // Update existing user
                $stmt = $pdo->prepare("UPDATE users 
                                      SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?
                                      WHERE user_id = ?");
                $stmt->execute([$username, $email, $first_name, $last_name, $role, $user_id]);
                
                // Update role-specific data
                if ($role === 'student') {
                    // Check if student record exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
                    $stmt->execute([$user_id]);
                    $student_exists = $stmt->fetchColumn();
                    
                    if ($student_exists) {
                        $stmt = $pdo->prepare("UPDATE students SET registration_number = ? WHERE student_id = ?");
                        $stmt->execute([$registration_number, $user_id]);
                    } else {
                        // Delete from other role tables
                        $stmt = $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Create student record
                        $stmt = $pdo->prepare("INSERT INTO students (student_id, registration_number) VALUES (?, ?)");
                        $stmt->execute([$user_id, $registration_number]);
                    }
                } elseif ($role === 'lecturer') {
                    // Check if lecturer record exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE lecturer_id = ?");
                    $stmt->execute([$user_id]);
                    $lecturer_exists = $stmt->fetchColumn();
                    
                    if ($lecturer_exists) {
                        $stmt = $pdo->prepare("UPDATE lecturers SET staff_id = ? WHERE lecturer_id = ?");
                        $stmt->execute([$staff_id, $user_id]);
                    } else {
                        // Delete from other role tables
                        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
                        $stmt->execute([$user_id]);
                        
                        // Create lecturer record
                        $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_id, staff_id) VALUES (?, ?)");
                        $stmt->execute([$user_id, $staff_id]);
                    }
                } else { // admin
                    // Remove from role-specific tables
                    $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
                    $stmt->execute([$user_id]);
                    $stmt = $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id = ?");
                    $stmt->execute([$user_id]);
                }
                
                $_SESSION['success'] = "User updated successfully!";
            } else {
                // Create new user (default password)
                $password = 'password123'; // Default password, should be changed by user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $profile_pic = 'default_profile.jpg';
                
                $stmt = $pdo->prepare("INSERT INTO users 
                                      (username, email, password_hash, first_name, last_name, role, profile_pic) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $role, $profile_pic]);
                $user_id = $pdo->lastInsertId();
                
                // Create role-specific record
                if ($role === 'student') {
                    $stmt = $pdo->prepare("INSERT INTO students (student_id, registration_number) VALUES (?, ?)");
                    $stmt->execute([$user_id, $registration_number]);
                } elseif ($role === 'lecturer') {
                    $stmt = $pdo->prepare("INSERT INTO lecturers (lecturer_id, staff_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $staff_id]);
                }
                
                $_SESSION['success'] = "User created successfully! Default password: password123";
            }
            
            $pdo->commit();
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle user deletion
if ($action === 'delete' && $user_id) {
    try {
        $pdo->beginTransaction();
        
        // Delete from users table (cascades to role-specific tables)
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "User deleted successfully!";
        header("Location: users.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
        header("Location: users.php");
        exit();
    }
}

// Get user data for editing
$user = null;
$student_data = null;
$lecturer_data = null;

if ($action === 'edit' && $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: users.php");
        exit();
    }
    
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->execute([$user_id]);
        $student_data = $stmt->fetch();
    } elseif ($user['role'] === 'lecturer') {
        $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE lecturer_id = ?");
        $stmt->execute([$user_id]);
        $lecturer_data = $stmt->fetch();
    }
}

// Get all users for listing
$stmt = $pdo->query("SELECT u.*, 
                     s.registration_number, 
                     l.staff_id 
                     FROM users u
                     LEFT JOIN students s ON u.user_id = s.student_id
                     LEFT JOIN lecturers l ON u.user_id = l.lecturer_id
                     ORDER BY u.role, u.last_name, u.first_name");
$users = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">User Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="users.php?action=create" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Add User
                    </a>
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

            <!-- User Form for Create/Edit -->
            <?php if ($action === 'create' || $action === 'edit'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $action === 'create' ? 'Create New User' : 'Edit User'; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-1"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="users.php?action=<?php echo $action; ?><?php echo $user_id ? "&id=$user_id" : ''; ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" name="username" id="username" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="role" class="form-label">Role</label>
                                    <select name="role" id="role" class="form-select" required>
                                        <option value="">Select Role</option>
                                        <option value="student" <?php echo ($user['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="lecturer" <?php echo ($user['role'] ?? '') === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Student fields -->
                            <div id="student-fields" class="row mb-3 role-fields <?php echo ($user['role'] ?? '') === 'student' ? '' : 'd-none'; ?>">
                                <div class="col-md-6">
                                    <label for="registration_number" class="form-label">Registration Number</label>
                                    <input type="text" name="registration_number" id="registration_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($student_data['registration_number'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Lecturer fields -->
                            <div id="lecturer-fields" class="row mb-3 role-fields <?php echo ($user['role'] ?? '') === 'lecturer' ? '' : 'd-none'; ?>">
                                <div class="col-md-6">
                                    <label for="staff_id" class="form-label">Staff ID</label>
                                    <input type="text" name="staff_id" id="staff_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($lecturer_data['staff_id'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="users.php" class="btn btn-secondary px-4">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <?php echo $action === 'create' ? 'Create User' : 'Update User'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Users List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Users</h5>
                    <input type="text" id="searchInput" class="form-control form-control-sm w-25" placeholder="Search users...">
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="alert alert-info">No users found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>ID Number</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="/student_management_system/assets/images/<?php echo htmlspecialchars($u['profile_pic'] ?? 'default_profile.png'); ?>" 
                                                         class="rounded-circle me-2" width="32" height="32"
                                                         onerror="this.onerror=null; this.src='/student_management_system/assets/images/default_profile.png'">
                                                    <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <span class="badge rounded-pill 
                                                    <?php echo $u['role'] === 'admin' ? 'bg-primary' : 
                                                        ($u['role'] === 'lecturer' ? 'bg-success' : 'bg-info'); ?>">
                                                    <?php echo ucfirst($u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $u['role'] === 'student' ? htmlspecialchars($u['registration_number']) : 
                                                     ($u['role'] === 'lecturer' ? htmlspecialchars($u['staff_id']) : 'N/A'); ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="users.php?action=edit&id=<?php echo $u['user_id']; ?>" 
                                                       class="btn btn-outline-primary"
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger delete-btn"
                                                            data-id="<?php echo $u['user_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($u['first_name']) . ' ' . htmlspecialchars($u['last_name']); ?>"
                                                            data-bs-toggle="tooltip" 
                                                            title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user: <strong><span id="deleteUserName"></span></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Delete confirmation modal
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteUserName = document.getElementById('deleteUserName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            deleteUserName.textContent = userName;
            confirmDeleteBtn.href = `users.php?action=delete&id=${userId}`;
            deleteModal.show();
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    // Role-specific fields toggle
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            const role = this.value;
            document.querySelectorAll('.role-fields').forEach(field => {
                field.classList.add('d-none');
            });
            if (role === 'student') {
                document.getElementById('student-fields').classList.remove('d-none');
            } else if (role === 'lecturer') {
                document.getElementById('lecturer-fields').classList.remove('d-none');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>