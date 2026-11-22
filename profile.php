
<?php

session_start();

require_once __DIR__ . "/config/database.php";

// Protect the page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$success = "";
$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Fetch current user details
$stmt = $conn->prepare("
    SELECT full_name, email, phone_number, password_hash
    FROM users
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Process profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $full_name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone_number = trim($_POST["phone_number"] ?? "");

        $current_password = $_POST["current_password"] ?? "";
        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        // Validate profile information
        if ($full_name === "" || $email === "") {
            $error = "Full name and email address are required.";

        } elseif (strlen($full_name) > 100) {
            $error = "Full name must not exceed 100 characters.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $error = "Please enter a valid email address.";

        } elseif ($phone_number !== "" && strlen($phone_number) > 20) {
            $error = "Phone number must not exceed 20 characters.";

        } elseif ($phone_number !== "" && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone_number)) {
            $error = "Please enter a valid phone number.";
        }

        // Check whether email belongs to another account
        if ($error === "") {

            $check_stmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE email = ? AND user_id != ?
            ");

            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "This email address is already registered.";
            }

            $check_stmt->close();
        }

        // Check whether password change was requested
        $changing_password =
            $current_password !== "" ||
            $new_password !== "" ||
            $confirm_password !== "";

        if ($error === "" && $changing_password) {

            if (
                $current_password === "" ||
                $new_password === "" ||
                $confirm_password === ""
            ) {
                $error = "Complete all password fields to change your password.";

            } elseif (!password_verify($current_password, $user["password_hash"])) {
                $error = "Your current password is incorrect.";

            } elseif (strlen($new_password) < 8) {
                $error = "Your new password must contain at least 8 characters.";

            } elseif ($new_password !== $confirm_password) {
                $error = "The new passwords do not match.";
            }
        }

        // Save changes
        if ($error === "") {

            if ($changing_password) {

                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?,
                        email = ?,
                        phone_number = ?,
                        password_hash = ?
                    WHERE user_id = ?
                ");

                $update_stmt->bind_param(
                    "ssssi",
                    $full_name,
                    $email,
                    $phone_number,
                    $password_hash,
                    $user_id
                );

            } else {

                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?,
                        email = ?,
                        phone_number = ?
                    WHERE user_id = ?
                ");

                $update_stmt->bind_param(
                    "sssi",
                    $full_name,
                    $email,
                    $phone_number,
                    $user_id
                );
            }

            if ($update_stmt->execute()) {

                // Refresh session information
                $_SESSION["full_name"] = $full_name;
                $_SESSION["email"] = $email;

                $success = "Your profile has been updated successfully.";

                // Refresh displayed user details
                $user["full_name"] = $full_name;
                $user["email"] = $email;
                $user["phone_number"] = $phone_number;

                if ($changing_password) {
                    $user["password_hash"] = $password_hash;
                }

            } else {
                $error = "Unable to update your profile. Please try again.";
            }

            $update_stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>My Profile | BusinessPortal</title>

        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/profile.css">
    </head>

    <body>

        <div class="page-container">

            <div class="page-header">

                <div>
                    <h1>My Profile</h1>
                    <p>Manage your personal account information and password.</p>
                </div>

                <div class="profile-header-actions">

                    <?php if ($_SESSION["role"] === "admin"): ?>
                        <a href="admin.php" class="back-btn">Back to Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
                    <?php endif; ?>

                    <a href="logout.php" class="logout-btn">Logout</a>

                </div>

            </div>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <div class="content-card">

                <h2>Personal Information</h2>

                <form method="POST" action="profile.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>"
                    >

                    <div class="form-group">
                        <label for="full_name">Full Name</label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="form-control"
                            value="<?= htmlspecialchars($user["full_name"], ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>

                        <input
                            type="tel"
                            id="phone_number"
                            name="phone_number"
                            class="form-control"
                            value="<?= htmlspecialchars($user["phone_number"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="20"
                            placeholder="Enter your phone number"
                        >
                    </div>

                    <div class="section-divider"></div>

                    <h2>Change Password</h2>

                    <p class="section-description">
                        Leave these fields empty if you do not want to change your password.
                    </p>

                    <div class="form-group">
                        <label for="current_password">Current Password</label>

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control"
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control"
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="primary-btn">
                            Save Changes
                        </button>
                    </div>

                </form>

            </div>

        </div>
            <script>
            // Automatically dismiss notification messages
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
