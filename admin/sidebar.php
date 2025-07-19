<div class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="card mb-4 shadow-sm sticky-top" style="top: 20px;">
        <div class="card-body text-center">
            <?php
            $profilePic = !empty($student['profile_pic']) ? $student['profile_pic'] : 'default_profile.png';
            ?>
            <img src="/student_management_system/assets/images/<?php echo htmlspecialchars($profilePic); ?>"
                 onerror="this.onerror=null; this.src='/student_management_system/assets/images/default_profile.png';"
                 alt="Profile Picture"
                 class="rounded-circle mb-3"
                 style="width: 120px; height: 120px; object-fit: cover;">

            <h5 class="card-title mb-1">
                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . htmlspecialchars($_SESSION['last_name'])); ?>
            </h5>
            <p class="text-muted mb-3">
                <?php echo ucfirst($_SESSION['role']); ?>
            </p>
        </div>

        <ul class="list-group list-group-flush">
            <li class="list-group-item">
                <a href="dashboard.php" class="text-decoration-none d-block">Dashboard</a>
            </li>
            <li class="list-group-item">
                <a href="courses.php" class="text-decoration-none d-block">My Courses</a>
            </li>
            <li class="list-group-item">
                <a href="profile.php" class="text-decoration-none d-block">Profile Settings</a>
            </li>
        </ul>
    </div>
</div>