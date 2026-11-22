
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// =================================
// ADMINISTRATOR AUTHENTICATION
// =================================

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION["full_name"] ?? "Administrator";


// =================================
// RETRIEVE DASHBOARD STATISTICS
// =================================

$statistics = [
    "users" => 0,
    "businesses" => 0,
    "resources" => 0,
    "guidance" => 0
];

$statements = [
    "users" => "SELECT COUNT(*) AS total FROM users",
    "businesses" => "SELECT COUNT(*) AS total FROM businesses",
    "resources" => "SELECT COUNT(*) AS total FROM support_resources",
    "guidance" => "SELECT COUNT(*) AS total FROM registration_guidance"
];

foreach ($statements as $key => $sql) {

    $result = $conn->query($sql);

    if ($result) {

        $row = $result->fetch_assoc();

        $statistics[$key] = (int) $row["total"];

        $result->free();

    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Dashboard | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">

</head>

<body>

    <!-- =================================
         NAVIGATION BAR
    ================================== -->

    <header>

        <nav class="navbar">

            <div class="logo">
                Business<span>Portal</span>
            </div>

            <div class="admin-header-actions">

                <a href="profile.php" class="profile-btn">
                    My Profile
                </a>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </nav>

    </header>


    <!-- =================================
         WELCOME SECTION
    ================================== -->

    <section class="admin-welcome">

        <h1>
            Welcome,

            <?= htmlspecialchars(
                $admin_name,
                ENT_QUOTES,
                'UTF-8'
            ); ?>!
        </h1>

        <p>
            Welcome to your Business Registration and Compliance Portal.

            Manage portal activities, monitor registered businesses
            and users, and maintain important business resources.
        </p>

    </section>


    <!-- =================================
         ADMINISTRATOR DASHBOARD
    ================================== -->

    <main class="admin-dashboard">


        <!-- DASHBOARD STATISTICS -->

        <h2>Dashboard Overview</h2>

        <section class="statistics-grid">

            <div class="stat-card">

                <h3>Registered Users</h3>

                <p>
                    <?= $statistics["users"]; ?>
                </p>

            </div>


            <div class="stat-card">

                <h3>Registered Businesses</h3>

                <p>
                    <?= $statistics["businesses"]; ?>
                </p>

            </div>


            <div class="stat-card">

                <h3>Support Resources</h3>

                <p>
                    <?= $statistics["resources"]; ?>
                </p>

            </div>


            <div class="stat-card">

                <h3>Registration Guidance</h3>

                <p>
                    <?= $statistics["guidance"]; ?>
                </p>

            </div>

        </section>


        <!-- MANAGEMENT LINKS -->

        <h2 class="management-heading">
            Portal Management
        </h2>

        <section class="admin-quick-links">


            <!-- REGISTRATION GUIDANCE -->

            <a href="admin_guidance.php" class="quick-link">

                <h3>Registration Guidance</h3>

                <p>
                    Add, edit and delete business registration
                    and regulatory guidance topics.
                </p>

                <span>Manage Guidance &rarr;</span>

            </a>


            <!-- SUPPORT RESOURCES -->

            <a href="admin_resources.php" class="quick-link">

                <h3>Support Resources</h3>

                <p>
                    Add, edit and delete useful business
                    support resources and official links.
                </p>

                <span>Manage Resources &rarr;</span>

            </a>


            <!-- REGISTERED USERS -->

            <a href="view_users.php" class="quick-link">

                <h3>Registered Users</h3>

                <p>
                    View the accounts of users registered
                    on the BusinessPortal platform.
                </p>

                <span>View Users &rarr;</span>

            </a>


        </section>

    </main>


    <!-- =================================
         FOOTER
    ================================== -->

    <footer class="admin-footer">

        <p>
            &copy; <?= date("Y"); ?> BusinessPortal.
            All Rights Reserved.
        </p>

        <p>
            Business Registration and Compliance Portal
        </p>

    </footer>

</body>

</html>

<?php

$conn->close();

?>
