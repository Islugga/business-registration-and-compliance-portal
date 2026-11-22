
<?php

session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Administrators cannot access business management.
if ($_SESSION["role"] === "admin") {
    header("Location: admin.php");
    exit();
}


// =================================
// RETRIEVE SUPPORT RESOURCES
// =================================

$sql = "SELECT resource_id, title, description, resource_url
        FROM support_resources
        ORDER BY title ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load support resources.");
}

if (!$stmt->execute()) {
    die("Unable to load support resources.");
}

$resources = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Business Support Resources</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/resources.css">

</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">

            <div>

                <h1>Business Support Resources</h1>

                <p>
                    Access useful information, guidance and services
                    to support your business.
                </p>

            </div>

            <a href="dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </div>


        <!-- Resources Section -->
        <div class="content-card">

            <h2>Available Resources</h2>

            <?php if ($resources->num_rows > 0): ?>

                <div class="resources-grid">

                    <?php while ($resource = $resources->fetch_assoc()): ?>

                        <?php

                        // Validate external resource URL
                        $resource_url = trim(
                            $resource["resource_url"] ?? ""
                        );

                        $valid_url = false;

                        if (filter_var($resource_url, FILTER_VALIDATE_URL)) {

                            $scheme = strtolower(
                                parse_url($resource_url, PHP_URL_SCHEME) ?? ""
                            );

                            $valid_url = in_array(
                                $scheme,
                                ["http", "https"],
                                true
                            );

                        }

                        ?>

                        <div class="resource-card">

                            <h3>

                                <?php echo htmlspecialchars(
                                    $resource["title"],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </h3>


                            <p>

                                <?php

                                $description = trim(
                                    $resource["description"] ?? ""
                                );

                                if ($description !== "") {

                                    echo nl2br(
                                        htmlspecialchars(
                                            $description,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    );

                                } else {

                                    echo "No description is currently available.";

                                }

                                ?>

                            </p>


                            <?php if ($valid_url): ?>

                                <a
                                    href="<?php echo htmlspecialchars(
                                        $resource_url,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    class="resource-link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Visit Resource
                                </a>

                            <?php else: ?>

                                <span class="resource-unavailable">
                                    Link currently unavailable
                                </span>

                            <?php endif; ?>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <h3>No Resources Available</h3>

                    <p>
                        Business support resources will appear here
                        when they are added to the portal.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>

<?php

$stmt->close();
$conn->close();
