
<?php

session_start();

// Check whether user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Redirect administrators to their dashboard
if (($_SESSION["role"] ?? "user") === "admin") {
    header("Location: admin.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Dashboard | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

    <!-- NAVIGATION BAR -->

    <header>
        <nav class="navbar">

            <div class="logo">
                Business<span>Portal</span>
            </div>

            <div class="dashboard-header-actions">

                <a href="profile.php" class="register-btn">
                    My Profile
                </a>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </nav>
    </header>


    <!-- WELCOME SECTION -->

    <section class="about">

        <h2>
            Welcome,
            <?php echo htmlspecialchars($_SESSION["full_name"], ENT_QUOTES, "UTF-8"); ?>!
        </h2>

        <p>
            Welcome to your Business Registration and Compliance Portal.
            You can manage your business information and access
            important business services here.
        </p>

    </section>


    <!-- USER DASHBOARD -->

    <main class="services">

        <h2>Your Dashboard</h2>

        <div class="service-container">

            <div class="service-card">
                <h3>My Business</h3>
                <p>Manage your business profile.</p>
                <a href="business.php">View Business</a>
            </div>

            <div class="service-card">
                <h3>Registration Guidance</h3>
                <p>Learn about business registration procedures.</p>
                <a href="guidance.php">View Guidance</a>
            </div>

            <div class="service-card">
                <h3>My Documents</h3>
                <p>Manage your business documents.</p>
                <a href="documents.php">View Documents</a>
            </div>

            <div class="service-card">
                <h3>Compliance</h3>
                <p>Monitor your compliance deadlines.</p>
                <a href="compliance.php">View Compliance</a>
            </div>

            <div class="service-card">
                <h3>Support Resources</h3>
                <p>Explore business support information.</p>
                <a href="resources.php">Explore Resources</a>
            </div>

            <div class="service-card">
                <h3>My Profile</h3>
                <p>Update your personal information and change your password.</p>
                <a href="profile.php">Manage Profile</a>
            </div>

        </div>

    </main>


    <!-- FOOTER -->

    <footer class="dashboard-footer">

        <p>
            &copy; <?php echo date("Y"); ?> BusinessPortal.
            All Rights Reserved.
        </p>

        <p>
            Business Registration and Compliance Portal
        </p>

    </footer>

</body>
</html>
