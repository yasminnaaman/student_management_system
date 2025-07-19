<?php
require_once 'config/db_connection.php';

// Redirect logged in users to their dashboard
if (isset($_SESSION['user_id'])) {
    redirectBasedOnRole();
}
?>

<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="bg-primary text-white py-5">
  <div class="container text-center">
    <h1 class="display-4 fw-bold mb-3">Welcome to UniManage</h1>
    <p class="lead mb-4">Your comprehensive student management system</p>
    <div class="d-flex justify-content-center gap-3">
      <a href="auth/login.php" class="btn btn-light text-primary px-4 py-2 fw-semibold">Login</a>
      <a href="auth/register.php" class="btn btn-outline-light px-4 py-2 fw-semibold">Register</a>
    </div>
  </div>
</section>

<!-- Features Section -->
<section class="py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold mb-3">System Features</h2>
      <p class="text-muted mx-auto" style="max-width: 600px;">
        UniManage provides a complete solution for managing students, courses, assignments, and results.
      </p>
    </div>

    <div class="row g-4">
      <!-- Feature 1 -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body text-center">
            <div class="text-primary mb-3">
              <i class="bi bi-book fs-1"></i>
            </div>
            <h5 class="card-title fw-semibold">Course Management</h5>
            <p class="text-muted">Register for courses, view schedules, and track your academic progress.</p>
          </div>
        </div>
      </div>

      <!-- Feature 2 -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body text-center">
            <div class="text-primary mb-3">
              <i class="bi bi-journal-check fs-1"></i>
            </div>
            <h5 class="card-title fw-semibold">Assignment System</h5>
            <p class="text-muted">Submit assignments, receive feedback, and track your submission history.</p>
          </div>
        </div>
      </div>

      <!-- Feature 3 -->
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body text-center">
            <div class="text-primary mb-3">
              <i class="bi bi-graph-up-arrow fs-1"></i>
            </div>
            <h5 class="card-title fw-semibold">Results Tracking</h5>
            <p class="text-muted">View your grades and academic performance across all courses.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
