
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Only administrators can access this page.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "";

// Handle form submissions.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token.
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";

    // ADD OR UPDATE GUIDANCE.
    if ($action === "save") {

        $guidance_id = filter_var(
            $_POST["guidance_id"] ?? 0,
            FILTER_VALIDATE_INT
        );

        $title = trim($_POST["title"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $content = trim($_POST["content"] ?? "");
        $official_url = trim($_POST["official_url"] ?? "");

        if ($title === "" || $category === "" || $content === "") {

            $message = "Please complete all required fields.";
            $message_type = "error";

        } elseif (strlen($title) > 150 || strlen($category) > 100) {

            $message = "The title or category is too long.";
            $message_type = "error";

        } else {

            // Validate optional official website URL.
            if ($official_url !== "") {

                $valid_url = filter_var(
                    $official_url,
                    FILTER_VALIDATE_URL
                );

                $scheme = strtolower(
                    parse_url($official_url, PHP_URL_SCHEME) ?? ""
                );

                if (!$valid_url || !in_array($scheme, ["http", "https"], true)) {
                    $message = "Please enter a valid HTTP or HTTPS website URL.";
                    $message_type = "error";
                }
            }

            if ($message === "") {

                if ($guidance_id && $guidance_id > 0) {

                    // UPDATE EXISTING GUIDANCE.
                    $stmt = $conn->prepare("
                        UPDATE registration_guidance
                        SET title = ?,
                            category = ?,
                            content = ?,
                            official_url = ?
                        WHERE guidance_id = ?
                    ");

                    $stmt->bind_param(
                        "ssssi",
                        $title,
                        $category,
                        $content,
                        $official_url,
                        $guidance_id
                    );

                    if ($stmt->execute()) {

                        header("Location: admin_guidance.php?updated=1");
                        exit();

                    } else {

                        $message = "Unable to update guidance.";
                        $message_type = "error";
                    }

                    $stmt->close();

                } else {

                    // ADD NEW GUIDANCE.
                    $stmt = $conn->prepare("
                        INSERT INTO registration_guidance
                        (title, category, content, official_url)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "ssss",
                        $title,
                        $category,
                        $content,
                        $official_url
                    );

                    if ($stmt->execute()) {

                        header("Location: admin_guidance.php?added=1");
                        exit();

                    } else {

                        $message = "Unable to add guidance.";
                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }

    // DELETE GUIDANCE.
    if ($action === "delete") {

        $guidance_id = filter_var(
            $_POST["guidance_id"] ?? 0,
            FILTER_VALIDATE_INT
        );

        if (!$guidance_id || $guidance_id < 1) {

            $message = "Invalid guidance record.";
            $message_type = "error";

        } else {

            $stmt = $conn->prepare("
                DELETE FROM registration_guidance
                WHERE guidance_id = ?
            ");

            $stmt->bind_param("i", $guidance_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {

                header("Location: admin_guidance.php?deleted=1");
                exit();

            } else {

                $message = "Guidance could not be deleted or was not found.";
                $message_type = "error";
            }

            $stmt->close();
        }
    }
}

// Success messages.
if (isset($_GET["added"])) {
    $message = "Guidance added successfully.";
    $message_type = "success";
}

if (isset($_GET["updated"])) {
    $message = "Guidance updated successfully.";
    $message_type = "success";
}

if (isset($_GET["deleted"])) {
    $message = "Guidance deleted successfully.";
    $message_type = "success";
}

// Load record for editing.
$edit_guidance = null;

if (isset($_GET["edit"])) {

    $edit_id = filter_var(
        $_GET["edit"],
        FILTER_VALIDATE_INT
    );

    if ($edit_id && $edit_id > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM registration_guidance
            WHERE guidance_id = ?
        ");

        $stmt->bind_param("i", $edit_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $edit_guidance = $result->fetch_assoc();

        $stmt->close();

        if (!$edit_guidance) {
            $message = "The selected guidance record was not found.";
            $message_type = "error";
        }
    }
}

// Fetch all guidance records.
$result = $conn->query("
    SELECT *
    FROM registration_guidance
    ORDER BY created_at DESC
");

if (!$result) {
    die("Unable to retrieve guidance records.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Registration Guidance | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin_guidance.css">

</head>

<body>

<div class="page-container">

    <!-- PAGE HEADER -->
    <header class="page-header">

        <div>
            <h1>Manage Registration Guidance</h1>

            <p>
                Add, update and manage business registration
                guidance for portal users.
            </p>
        </div>

        <a href="admin.php" class="back-btn">
            Admin Dashboard
        </a>

    </header>


    <!-- NOTIFICATION -->
    <?php if ($message !== ""): ?>

        <div class="alert <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <!-- ADD / EDIT FORM -->
    <section class="content-card">

        <h2>
            <?= $edit_guidance ? "Edit Guidance" : "Add New Guidance" ?>
        </h2>

        <form method="POST" action="admin_guidance.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
            >

            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="guidance_id"
                value="<?= htmlspecialchars($edit_guidance["guidance_id"] ?? "") ?>"
            >

            <div class="form-group">

                <label for="title">Guidance Title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    class="form-control"
                    maxlength="150"
                    value="<?= htmlspecialchars($edit_guidance["title"] ?? "") ?>"
                    placeholder="Enter guidance title"
                    required
                >

            </div>


            <div class="form-group">

                <label for="category">Category</label>

                <select
                    id="category"
                    name="category"
                    class="form-control"
                    required
                >

                    <option value="" selected disabled>Select Category</option>

                    <?php

                    $categories = [
                        "Business Registration",
                        "Taxation",
                        "Licensing and Permits",
                        "Regulatory Compliance",
                        "Other"
                    ];

                    foreach ($categories as $category_option):

                        $selected = (
                            ($edit_guidance["category"] ?? "") === $category_option
                        ) ? "selected" : "";

                    ?>

                        <option
                            value="<?= htmlspecialchars($category_option) ?>"
                            <?= $selected ?>
                        >
                            <?= htmlspecialchars($category_option) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="content">Guidance Content</label>

                <textarea
                    id="content"
                    name="content"
                    class="form-control"
                    rows="7"
                    placeholder="Enter detailed registration guidance..."
                    required
                ><?= htmlspecialchars($edit_guidance["content"] ?? "") ?></textarea>

            </div>


            <div class="form-group">

                <label for="official_url">
                    Official Website URL
                    <span class="optional">(Optional)</span>
                </label>

                <input
                    type="url"
                    id="official_url"
                    name="official_url"
                    class="form-control"
                    value="<?= htmlspecialchars($edit_guidance["official_url"] ?? "") ?>"
                    placeholder="https://example.gov.ng"
                >

            </div>


            <div class="form-actions">

                <button type="submit" class="primary-btn">
                    <?= $edit_guidance ? "Update Guidance" : "Add Guidance" ?>
                </button>

                <?php if ($edit_guidance): ?>

                    <a href="admin_guidance.php" class="cancel-btn">
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>


    <!-- GUIDANCE RECORDS -->
    <section class="content-card">

        <h2>Existing Guidance Topics</h2>

        <?php if ($result->num_rows > 0): ?>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Official Website</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($guidance = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($guidance["title"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($guidance["category"]) ?>
                                </td>

                                <td>

                                    <?php if (!empty($guidance["official_url"])): ?>

                                        <a
                                            href="<?= htmlspecialchars($guidance["official_url"]) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Visit Website
                                        </a>

                                    <?php else: ?>

                                        <span class="muted">Not provided</span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars(date(
                                        "d M Y",
                                        strtotime($guidance["created_at"])
                                    )) ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="admin_guidance.php?edit=<?= (int) $guidance["guidance_id"] ?>"
                                            class="update-btn"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="admin_guidance.php"
                                            onsubmit="return confirm('Are you sure you want to delete this guidance topic?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="guidance_id"
                                                value="<?= (int) $guidance["guidance_id"] ?>"
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

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <h3>No Guidance Topics Yet</h3>

                <p>
                    Use the form above to add your first
                    registration guidance topic.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>

// Automatically dismiss notification messages.
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
