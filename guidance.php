
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Only logged-in users can access this page.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Fetch all registration guidance topics.
$sql = "SELECT * FROM registration_guidance ORDER BY category ASC, title ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Unable to retrieve registration guidance.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registration Guidance | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/guidance.css">
</head>

<body>

<div class="page-container">

    <header class="page-header">

        <div>
            <h1>Registration Guidance</h1>

            <p>
                Learn about business registration and regulatory
                requirements in Nigeria.
            </p>
        </div>

        <div class="header-actions">

            <?php if ($_SESSION["role"] === "admin"): ?>

                <a href="admin_guidance.php" class="manage-btn">
                    Manage Guidance
                </a>

                <a href="admin.php" class="back-btn">
                    Dashboard
                </a>

            <?php else: ?>

                <a href="dashboard.php" class="back-btn">
                    Dashboard
                </a>

            <?php endif; ?>

        </div>

    </header>


    <main>

        <?php if ($result->num_rows > 0): ?>

            <div class="guidance-grid">

                <?php while ($guidance = $result->fetch_assoc()): ?>

                    <article class="guidance-card">

                        <span class="category-label">
                            <?= htmlspecialchars($guidance["category"]) ?>
                        </span>

                        <h2>
                            <?= htmlspecialchars($guidance["title"]) ?>
                        </h2>

                        <p class="guidance-content">
                            <?= nl2br(htmlspecialchars($guidance["content"])) ?>
                        </p>

                        <?php if (!empty($guidance["official_url"])): ?>

                            <a
                                href="<?= htmlspecialchars($guidance["official_url"]) ?>"
                                class="resource-link"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Visit Official Website
                            </a>

                        <?php endif; ?>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <h2>No Guidance Available Yet</h2>

                <p>
                    Registration guidance will appear here when
                    the administrator adds new topics.
                </p>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
