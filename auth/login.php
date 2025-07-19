<?php
// Start output buffering at the very top
ob_start();
session_start();
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $errors = [];

    if (empty($username) || empty($password)) {
        $errors[] = "Both username and password are required.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_pic'] = $user['profile_pic'];

            // Clear buffer before redirect
            ob_end_clean();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'lecturer':
                    header("Location: ../lecturer/dashboard.php");
                    break;
                case 'student':
                    header("Location: ../student/dashboard.php");
                    break;
            }
            exit();
        } else {
            $errors[] = "Invalid username or password.";
        }
    }
}
?>

<!-- Include Bootstrap and custom header -->
<?php include '../includes/header.php'; ?>

<div class="container d-flex justify-content-center align-items-start pt-5" style="min-height: calc(80vh - 100px); overflow: hidden;">
    <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
        <h2 class="text-center mb-4">Login</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input 
                    type="text" 
                    name="username" 
                    id="username" 
                    class="form-control" 
                    value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    class="form-control" 
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary w-100">Login</button>

            <p class="text-center mt-3">
                Don't have an account? 
                <a href="register.php">Register here</a>
            </p>
        </form>
    </div>
</div>

<!-- Include footer -->
<?php include '../includes/footer.php'; ?>

<?php
// Flush output buffer at the end
ob_end_flush();
?>