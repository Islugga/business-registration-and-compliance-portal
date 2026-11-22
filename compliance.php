
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

$user_id = (int) $_SESSION["user_id"];

$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


// =================================
// PROCESS FORM SUBMISSIONS
// =================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!is_string($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $action = $_POST["action"] ?? "";


        // =================================
        // ADD COMPLIANCE REQUIREMENT
        // =================================

        if ($action === "add") {

            $business_id = filter_var(
                $_POST["business_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            $requirement_name = trim(
                $_POST["requirement_name"] ?? ""
            );

            $due_date = trim($_POST["due_date"] ?? "");

            $date_object = DateTime::createFromFormat(
                '!Y-m-d',
                $due_date
            );

            $date_errors = DateTime::getLastErrors();

            $valid_date = $date_object !== false &&
                $date_object->format("Y-m-d") === $due_date &&
                ($date_errors === false ||
                    ($date_errors["warning_count"] === 0 &&
                     $date_errors["error_count"] === 0));


            if (
                !$business_id ||
                $business_id < 1 ||
                $requirement_name === "" ||
                $due_date === ""
            ) {

                $error = "Please complete all required fields.";

            } elseif (strlen($requirement_name) > 150) {

                $error = "Requirement name must not exceed 150 characters.";

            } elseif (!$valid_date) {

                $error = "Please provide a valid due date.";

            } else {

                // Confirm business ownership
                $check_sql = "SELECT business_id
                              FROM businesses
                              WHERE business_id = ?
                              AND user_id = ?";

                $check_stmt = $conn->prepare($check_sql);

                if (!$check_stmt) {

                    $error = "Unable to verify business details.";

                } else {

                    $check_stmt->bind_param(
                        "ii",
                        $business_id,
                        $user_id
                    );

                    if (!$check_stmt->execute()) {

                        $error = "Unable to verify business details.";

                    } else {

                        $business_result = $check_stmt->get_result();

                        if ($business_result->num_rows === 0) {

                            $error = "Invalid business selection.";

                        } else {

                            // Insert compliance requirement
                            $insert_sql = "INSERT INTO compliance_reminders
                                           (business_id, requirement_name, due_date)
                                           VALUES (?, ?, ?)";

                            $insert_stmt = $conn->prepare($insert_sql);

                            if (!$insert_stmt) {

                                $error = "Unable to prepare compliance record.";

                            } else {

                                $insert_stmt->bind_param(
                                    "iss",
                                    $business_id,
                                    $requirement_name,
                                    $due_date
                                );

                                if ($insert_stmt->execute()) {

                                    $insert_stmt->close();
                                    $check_stmt->close();

                                    header("Location: compliance.php?success=added");
                                    exit();

                                } else {

                                    $error = "Unable to save compliance requirement.";

                                }

                                $insert_stmt->close();
                            }
                        }
                    }

                    $check_stmt->close();
                }
            }


        // =================================
        // UPDATE COMPLIANCE STATUS
        // =================================

        } elseif ($action === "update_status") {

            $reminder_id = filter_var(
                $_POST["reminder_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            $status = $_POST["status"] ?? "";

            $allowed_statuses = [
                "Pending",
                "Completed"
            ];

            if (
                !$reminder_id ||
                $reminder_id < 1 ||
                !in_array($status, $allowed_statuses, true)
            ) {

                $error = "Invalid compliance update request.";

            } else {

                // Confirm that the reminder belongs to the user
                $check_sql = "SELECT r.reminder_id
                              FROM compliance_reminders r
                              INNER JOIN businesses b
                              ON r.business_id = b.business_id
                              WHERE r.reminder_id = ?
                              AND b.user_id = ?";

                $check_stmt = $conn->prepare($check_sql);

                if (!$check_stmt) {

                    $error = "Unable to verify compliance record.";

                } else {

                    $check_stmt->bind_param(
                        "ii",
                        $reminder_id,
                        $user_id
                    );

                    if (!$check_stmt->execute()) {

                        $error = "Unable to verify compliance record.";

                    } else {

                        $record_result = $check_stmt->get_result();

                        if ($record_result->num_rows === 0) {

                            $error = "Requirement not found or access denied.";

                        } else {

                            // Update status
                            $update_sql = "UPDATE compliance_reminders r
                                           INNER JOIN businesses b
                                           ON r.business_id = b.business_id
                                           SET r.status = ?
                                           WHERE r.reminder_id = ?
                                           AND b.user_id = ?";

                            $update_stmt = $conn->prepare($update_sql);

                            if (!$update_stmt) {

                                $error = "Unable to prepare status update.";

                            } else {

                                $update_stmt->bind_param(
                                    "sii",
                                    $status,
                                    $reminder_id,
                                    $user_id
                                );

                                if ($update_stmt->execute()) {

                                    header("Location: compliance.php?success=updated");
                                    exit();

                                } else {

                                    $error = "Unable to update compliance status.";

                                }

                                $update_stmt->close();
                            }
                        }
                    }

                    $check_stmt->close();
                }
            }


        // =================================
        // DELETE COMPLIANCE REQUIREMENT
        // =================================

        } elseif ($action === "delete") {

            $reminder_id = filter_var(
                $_POST["reminder_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            if (!$reminder_id || $reminder_id < 1) {

                $error = "Invalid compliance record selected.";

            } else {

                $delete_sql = "DELETE r
                               FROM compliance_reminders r
                               INNER JOIN businesses b
                               ON r.business_id = b.business_id
                               WHERE r.reminder_id = ?
                               AND b.user_id = ?";

                $delete_stmt = $conn->prepare($delete_sql);

                if (!$delete_stmt) {

                    $error = "Unable to prepare deletion request.";

                } else {

                    $delete_stmt->bind_param(
                        "ii",
                        $reminder_id,
                        $user_id
                    );

                    if ($delete_stmt->execute()) {

                        if ($delete_stmt->affected_rows > 0) {

                            header("Location: compliance.php?success=deleted");
                            exit();

                        } else {

                            $error = "Requirement not found or access denied.";

                        }

                    } else {

                        $error = "Unable to delete compliance requirement.";

                    }

                    $delete_stmt->close();
                }
            }

        } else {

            $error = "Invalid request.";

        }
    }
}


// =================================
// RETRIEVE USER'S BUSINESSES
// =================================

$business_sql = "SELECT business_id, business_name
                 FROM businesses
                 WHERE user_id = ?
                 ORDER BY business_name ASC";

$business_stmt = $conn->prepare($business_sql);

if (!$business_stmt) {
    die("Unable to load business information.");
}

$business_stmt->bind_param("i", $user_id);

if (!$business_stmt->execute()) {
    die("Unable to load business information.");
}

$businesses = $business_stmt->get_result();


// =================================
// RETRIEVE COMPLIANCE REQUIREMENTS
// =================================

$reminder_sql = "SELECT r.reminder_id,
                        r.requirement_name,
                        r.due_date,
                        r.status,
                        b.business_name
                 FROM compliance_reminders r
                 INNER JOIN businesses b
                 ON r.business_id = b.business_id
                 WHERE b.user_id = ?
                 ORDER BY r.due_date ASC";

$reminder_stmt = $conn->prepare($reminder_sql);

if (!$reminder_stmt) {
    die("Unable to load compliance information.");
}

$reminder_stmt->bind_param("i", $user_id);

if (!$reminder_stmt->execute()) {
    die("Unable to load compliance information.");
}

$reminders = $reminder_stmt->get_result();

$today = date("Y-m-d");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Compliance Management</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/compliance.css">

</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">

            <div>

                <h1>Compliance Management</h1>

                <p>
                    Monitor your business compliance requirements
                    and important deadlines.
                </p>

            </div>

            <a href="dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </div>


        <!-- Success Messages -->
        <?php if (isset($_GET["success"])): ?>

            <div class="success-message auto-dismiss">

                <?php if ($_GET["success"] === "added"): ?>

                    Compliance requirement added successfully.

                <?php elseif ($_GET["success"] === "updated"): ?>

                    Compliance status updated successfully.

                <?php elseif ($_GET["success"] === "deleted"): ?>

                    Compliance requirement deleted successfully.

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div class="error-message auto-dismiss">

                <?php echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

            </div>

        <?php endif; ?>


        <!-- Add Compliance Requirement -->
        <div class="content-card">

            <h2>Add Compliance Requirement</h2>

            <?php if ($businesses->num_rows > 0): ?>

                <form
                    method="POST"
                    action="compliance.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(
                            $csrf_token,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >


                    <div class="form-group">

                        <label for="business_id">
                            Select Business
                        </label>

                        <select
                            id="business_id"
                            name="business_id"
                            class="form-control"
                            required
                        >

                            <option value="" disabled selected>
                                Select a business
                            </option>

                            <?php while (
                                $business = $businesses->fetch_assoc()
                            ): ?>

                                <option value="<?php echo (int) $business["business_id"]; ?>">

                                    <?php echo htmlspecialchars(
                                        $business["business_name"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="requirement_name">
                            Compliance Requirement
                        </label>

                        <input
                            type="text"
                            id="requirement_name"
                            name="requirement_name"
                            class="form-control"
                            placeholder="e.g. Annual Returns Filing"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="due_date">
                            Due Date
                        </label>

                        <input
                            type="date"
                            id="due_date"
                            name="due_date"
                            class="form-control"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        Add Requirement
                    </button>

                </form>

            <?php else: ?>

                <p class="empty-state">
                    You need to add a business before creating
                    compliance reminders.
                </p>

                <a href="business.php" class="primary-link">
                    Add a Business
                </a>

            <?php endif; ?>

        </div>


        <!-- Compliance Requirements -->
        <div class="content-card">

            <h2>My Compliance Requirements</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>S/N</th>
                            <th>Requirement</th>
                            <th>Business</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($reminders->num_rows > 0): ?>

                            <?php $count = 1; ?>

                            <?php while (
                                $reminder = $reminders->fetch_assoc()
                            ): ?>

                                <?php

                                $display_status = $reminder["status"];

                                // Automatically display overdue requirements
                                if (
                                    $display_status !== "Completed" &&
                                    $reminder["due_date"] < $today
                                ) {

                                    $display_status = "Overdue";

                                }

                                $status_class = strtolower(
                                    $display_status
                                );

                                ?>

                                <tr>

                                    <td>
                                        <?php echo $count++; ?>
                                    </td>

                                    <td>

                                        <?php echo htmlspecialchars(
                                            $reminder["requirement_name"],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </td>

                                    <td>

                                        <?php echo htmlspecialchars(
                                            $reminder["business_name"],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </td>

                                    <td>

                                        <?php echo date(
                                            "d M Y",
                                            strtotime($reminder["due_date"])
                                        ); ?>

                                    </td>

                                    <td>

                                        <span class="status-badge status-<?php echo $status_class; ?>">

                                            <?php echo htmlspecialchars(
                                                $display_status,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    </td>

                                    <td class="compliance-actions">

                                        <?php if (
                                            $reminder["status"] !== "Completed"
                                        ): ?>

                                            <form
                                                method="POST"
                                                action="compliance.php"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo htmlspecialchars(
                                                        $csrf_token,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reminder_id"
                                                    value="<?php echo (int) $reminder["reminder_id"]; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="Completed"
                                                >

                                                <button
                                                    type="submit"
                                                    class="complete-btn"
                                                >
                                                    Complete
                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <form
                                                method="POST"
                                                action="compliance.php"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo htmlspecialchars(
                                                        $csrf_token,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reminder_id"
                                                    value="<?php echo (int) $reminder["reminder_id"]; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="Pending"
                                                >

                                                <button
                                                    type="submit"
                                                    class="pending-btn"
                                                >
                                                    Reopen
                                                </button>

                                            </form>

                                        <?php endif; ?>


                                        <!-- Delete Requirement -->
                                        <form
                                            method="POST"
                                            action="compliance.php"
                                            onsubmit="return confirm('Are you sure you want to delete this requirement?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars(
                                                    $csrf_token,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="reminder_id"
                                                value="<?php echo (int) $reminder["reminder_id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete-btn"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6" class="empty-state">

                                    You have not added any compliance
                                    requirements yet.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Automatically Dismiss Messages -->
    <script>

        const messages = document.querySelectorAll(".auto-dismiss");

        messages.forEach(function (message) {

            setTimeout(function () {

                message.style.opacity = "0";

                setTimeout(function () {

                    message.style.display = "none";

                }, 500);

            }, 4000);

        });

    </script>

</body>

</html>

<?php

$business_stmt->close();
$reminder_stmt->close();
$conn->close();
