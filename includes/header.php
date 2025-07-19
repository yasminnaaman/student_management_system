<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --indigo-dark: #4338CA;
            --indigo-light: #4F46E5;
            --gold-accent: #FACC15;
            --background-light: #F0FDF4;
            --text-dark: #1F2937;
            --success: #10B981;
            --danger: #DC2626;
        }

        body {
            background-color: var(--background-light);
            color: var(--text-dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background-color: var(--indigo-dark);
        }

        .navbar-brand {
            color: white !important;
        }

        .navbar .btn {
            color: white;
        }

        .btn-primary {
            background-color: var(--indigo-light);
            border-color: var(--indigo-light);
        }

        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }

        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .btn-primary:hover,
        .btn-success:hover,
        .btn-danger:hover {
            opacity: 0.9;
        }

        .navbar span {
            color: var(--gold-accent);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-expand-lg text-white p-3 shadow">
        <div class="container-fluid">
            <a href="../index.php" class="navbar-brand fs-3 fw-bold">UniManage</a>

            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                    <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-success me-2">Login</a>
                    <a href="../auth/register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-4">
