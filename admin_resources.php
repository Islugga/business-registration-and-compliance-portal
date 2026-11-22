
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


// =================================
// CSRF PROTECTION
// =================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


// =================================
// INITIALIZE VARIABLES
// =================================

$success = "";
$error = "";

$title = "";
$description = "";
$resource_url = "";

$editing_id = isset($_GET["edit"])
    ? (int) $_GET["edit"]
    : 0;


// =================================
// HANDLE FORM SUBMISSIONS
// =================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($csrf_token, $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $action = $_POST["action"] ?? "";

        // =================================
        // ADD OR UPDATE RESOURCE
        // =================================

        if ($action === "save") {

            $title = trim($_POST["title"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $resource_url = trim($_POST["resource_url"] ?? "");

            $resource_id = isset($_POST["resource_id"])
                ? (int) $_POST["resource_id"]
                : 0;

            if ($title === "") {

                $error = "Resource title is required.";

            } elseif (strlen($title) > 150) {

                $error = "Resource title cannot exceed 150 characters.";

            } elseif (
                $resource_url !== "" &&
                (
                    !filter_var($resource_url, FILTER_VALIDATE_URL) ||
                    !in_array(
                        strtolower(parse_url($resource_url, PHP_URL_SCHEME) ?? ""),
                        ["http", "https"],
                        true
                    )
                )
            ) {

                $error = "Please enter a valid HTTP or HTTPS resource URL.";

            } else {

                if ($resource_id > 0) {

                    // Update existing resource
                    $stmt = $conn->prepare(
                        "UPDATE support_resources
                         SET title = ?, description = ?, resource_url = ?
                         WHERE resource_id = ?"
                    );

                    if ($stmt) {

                        $stmt->bind_param(
                            "sssi",
                            $title,
                            $description,
                            $resource_url,
                            $resource_id
                        );

                        if ($stmt->execute()) {

                            if ($stmt->affected_rows >= 0) {
                                header("Location: admin_resources.php?updated=1");
                                exit();
                            }

                        } else {

                            $error = "Unable to update the resource.";

                        }

                        $stmt->close();

                    } else {

                        $error = "Unable to prepare the update request.";

                    }

                } else {

                    // Add new resource
                    $stmt = $conn->prepare(
                        "INSERT INTO support_resources
                         (title, description, resource_url)
                         VALUES (?, ?, ?)"
                    );

                    if ($stmt) {

                        $stmt->bind_param(
                            "sss",
                            $title,
                            $description,
                            $resource_url
                        );

                        if ($stmt->execute()) {

                            header("Location: admin_resources.php?added=1");
                            exit();

                        } else {

                            $error = "Unable to add the resource.";

                        }

                        $stmt->close();

                    } else {

                        $error = "Unable to prepare the insert request.";

                    }

                }

            }

        }


        // =================================
        // DELETE RESOURCE
        // =================================

        elseif ($action === "delete") {

            $resource_id = isset($_POST["resource_id"])
                ? (int) $_POST["resource_id"]
                : 0;

            if ($resource_id <= 0) {

                $error = "Invalid resource selected.";

            } else {

                $stmt = $conn->prepare(
                    "DELETE FROM support_resources
                     WHERE resource_id = ?"
                );

                if ($stmt) {

                    $stmt->bind_param("i", $resource_id);

                    if ($stmt->execute()) {

                        if ($stmt->affected_rows > 0) {

                            header("Location: admin_resources.php?deleted=1");
                            exit();

                        } else {

                            $error = "The selected resource was not found.";

                        }

                    } else {

                        $error = "Unable to delete the resource.";

                    }

                    $stmt->close();

                } else {

                    $error = "Unable to prepare the delete request.";

                }

            }

        }

    }

}


// =================================
// SUCCESS MESSAGES
// =================================

if (isset($_GET["added"])) {
    $success = "Resource added successfully.";
}

if (isset($_GET["updated"])) {
    $success = "Resource updated successfully.";
}

if (isset($_GET["deleted"])) {
    $success = "Resource deleted successfully.";
}


// =================================
// LOAD RESOURCE FOR EDITING
// =================================

if ($editing_id > 0 && $error === "") {

    $stmt = $conn->prepare(
        "SELECT title, description, resource_url
         FROM support_resources
         WHERE resource_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param("i", $editing_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($resource = $result->fetch_assoc()) {

            $title = $resource["title"];
            $description = $resource["description"] ?? "";
            $resource_url = $resource["resource_url"] ?? "";

        } else {

            $error = "The selected resource could not be found.";
            $editing_id = 0;

        }

        $stmt->close();

    } else {

        $error = "Unable to load the selected resource.";
        $editing_id = 0;

    }

}


// =================================
// RETAIN FORM VALUES AFTER VALIDATION ERROR
// =================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "save" &&
    $error !== ""
) {
    $editing_id = isset($_POST["resource_id"])
        ? (int) $_POST["resource_id"]
        : 0;
}


// =================================
// FETCH ALL SUPPORT RESOURCES
// =================================

$resources = [];

$result = $conn->query(
    "SELECT resource_id, title, description, resource_url
     FROM support_resources
     ORDER BY title ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }

    $result->free();

} else {

    $error = "Unable to retrieve support resources.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Support Resources | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin_resources.css">

</head>

<body>

    <!-- NAVIGATION BAR -->

    <header>

        <nav class="navbar">

            <div class="logo">
                Business<span>Portal</span>
            </div>

            <div class="resources-header-actions">

                <a href="admin.php" class="back-btn">
                    Dashboard
                </a>

                <a href="profile.php" class="profile-btn">
                    My Profile
                </a>

                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </nav>

    </header>


    <!-- PAGE HEADER -->

    <section class="resources-welcome">

        <h1>Manage Support Resources</h1>

        <p>
            Add, update and manage useful business support
            information and external resources.
        </p>

    </section>


    <!-- MAIN CONTENT -->

    <main class="resources-admin-container">


        <!-- NOTIFICATIONS -->

        <?php if ($success !== ""): ?>

            <div class="alert success-alert">
                <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert error-alert">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </div>

        <?php endif; ?>


        <!-- ADD / EDIT RESOURCE FORM -->

        <section class="resource-form-card">

            <h2>
                <?= $editing_id > 0
                    ? "Edit Support Resource"
                    : "Add New Support Resource"; ?>
            </h2>

            <form method="POST" action="admin_resources.php<?= $editing_id > 0 ? '?edit=' . $editing_id : ''; ?>">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>"
                >

                <input type="hidden" name="action" value="save">

                <input
                    type="hidden"
                    name="resource_id"
                    value="<?= $editing_id; ?>"
                >

                <div class="form-group">

                    <label for="title">Resource Title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="150"
                        value="<?= htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="Enter resource title"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        placeholder="Enter a description of this resource"
                    ><?= htmlspecialchars($description, ENT_QUOTES, "UTF-8"); ?></textarea>

                </div>


                <div class="form-group">

                    <label for="resource_url">Resource URL</label>

                    <input
                        type="url"
                        id="resource_url"
                        name="resource_url"
                        value="<?= htmlspecialchars($resource_url, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="https://example.com"
                    >

                </div>


                <div class="form-actions">

                    <button type="submit" class="submit-btn">
                        <?= $editing_id > 0
                            ? "Update Resource"
                            : "Add Resource"; ?>
                    </button>

                    <?php if ($editing_id > 0): ?>

                        <a href="admin_resources.php" class="cancel-btn">
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- RESOURCE LIST -->

        <section class="resource-list-card">

            <h2>Available Support Resources</h2>

            <?php if (count($resources) > 0): ?>

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Resource URL</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($resources as $resource): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $resource["title"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $resource["description"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </td>


                                    <td>

                                        <?php
                                        $url = $resource["resource_url"] ?? "";

                                        $scheme = strtolower(
                                            parse_url($url, PHP_URL_SCHEME) ?? ""
                                        );

                                        $valid_url = filter_var(
                                            $url,
                                            FILTER_VALIDATE_URL
                                        ) && in_array(
                                            $scheme,
                                            ["http", "https"],
                                            true
                                        );
                                        ?>

                                        <?php if ($valid_url): ?>

                                            <a
                                                href="<?= htmlspecialchars($url, ENT_QUOTES, "UTF-8"); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Visit Resource
                                            </a>

                                        <?php else: ?>

                                            <span>Not provided</span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="table-actions">

                                            <a
                                                href="admin_resources.php?edit=<?= (int) $resource["resource_id"]; ?>"
                                                class="edit-btn"
                                            >
                                                Edit
                                            </a>


                                            <form
                                                method="POST"
                                                action="admin_resources.php"
                                                onsubmit="return confirm('Are you sure you want to delete this resource?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="resource_id"
                                                    value="<?= (int) $resource["resource_id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="delete-btn"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="empty-message">
                    No support resources have been added yet.
                </p>

            <?php endif; ?>

        </section>

    </main>


    <script>

        setTimeout(function () {

            const alerts = document.querySelectorAll(".alert");

            alerts.forEach(function (alert) {

                alert.style.transition = "opacity 0.5s ease";
                alert.style.opacity = "0";

                setTimeout(function () {
                    alert.remove();
                }, 500);

            });

        }, 4000);

    </script>

</body>
</html>

<?php

$conn->close();

?>
