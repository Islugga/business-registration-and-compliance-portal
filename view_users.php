
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// =================================
// ADMINISTRATOR AUTHENTICATION
// =================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$admin_id = (int) $_SESSION["user_id"];

$auth_stmt = $conn->prepare("
    SELECT role, must_change_password
    FROM users
    WHERE user_id = ?
");

$auth_stmt->bind_param("i", $admin_id);
$auth_stmt->execute();

$current_admin = $auth_stmt->get_result()->fetch_assoc();
$auth_stmt->close();

if (!$current_admin) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION["role"] = $current_admin["role"];
$_SESSION["must_change_password"] =
    (int) $current_admin["must_change_password"];

if ($_SESSION["must_change_password"] === 1) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit();
}


// =================================
// CSRF TOKEN
// =================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


// =================================
// FLASH MESSAGES
// =================================

$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";
$temporary_password = $_SESSION["temporary_password"] ?? "";

unset(
    $_SESSION["success"],
    $_SESSION["error"],
    $_SESSION["temporary_password"]
);


// =================================
// RESET USER PASSWORD
// =================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrf_token, $submitted_token)) {

        $_SESSION["error"] = "Invalid request. Please try again.";

    } else {

        $user_id = filter_var(
            $_POST["user_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        if (!$user_id || $user_id < 1) {

            $_SESSION["error"] = "Invalid user selected.";

        } else {

            // Confirm that the selected account is an ordinary user.
            $check_stmt = $conn->prepare("
                SELECT user_id, full_name, email
                FROM users
                WHERE user_id = ? AND role = 'user'
            ");

            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();

            $selected_user = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if (!$selected_user) {

                $_SESSION["error"] =
                    "User account not found or cannot be reset.";

            } else {

                // Generate a secure temporary password.
                $new_temporary_password = rtrim(
                    strtr(base64_encode(random_bytes(18)), '+/', '-_'),
                    '='
                );

                // Hash the temporary password before saving it.
                $password_hash = password_hash(
                    $new_temporary_password,
                    PASSWORD_DEFAULT
                );

                // Require the user to change the password at next login.
                $reset_stmt = $conn->prepare("
                    UPDATE users
                    SET password_hash = ?,
                        must_change_password = 1
                    WHERE user_id = ? AND role = 'user'
                ");

                $reset_stmt->bind_param(
                    "si",
                    $password_hash,
                    $user_id
                );

                if ($reset_stmt->execute() &&
                    $reset_stmt->affected_rows === 1) {

                    $_SESSION["success"] =
                        "Password reset successfully for " .
                        $selected_user["full_name"] . ".";

                    // Store temporarily for one-time display.
                    $_SESSION["temporary_password"] =
                        $new_temporary_password;

                } else {

                    $_SESSION["error"] =
                        "Unable to reset the password. Please try again.";
                }

                $reset_stmt->close();
            }
        }
    }

    // Prevent form resubmission.
    header("Location: view_users.php");
    exit();
}


// =================================
// FETCH REGISTERED USERS
// =================================

$users = [];

$result = $conn->query("
    SELECT user_id, full_name, email, phone_number
    FROM users
    WHERE role = 'user'
    ORDER BY full_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$admin_name = $_SESSION["full_name"] ?? "Administrator";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registered Users | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/view_users.css">
</head>

<body>

<!-- =================================
     NAVIGATION
================================= -->

<header class="navbar">

    <div class="logo">
        BusinessPortal
    </div>

    <div class="users-header-actions">

        <a href="admin.php" class="btn-secondary">
            Dashboard
        </a>

        <a href="profile.php" class="btn-secondary">
            My Profile
        </a>

        <a href="logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</header>


<!-- =================================
     PAGE HEADER
================================= -->

<section class="users-welcome">

    <h1>Registered Users</h1>

    <p>
        View registered accounts and manage user password resets.
    </p>

</section>


<!-- =================================
     USER MANAGEMENT
================================= -->

<main class="users-container">

    <?php if ($success): ?>
        <div class="success-message">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <!-- TEMPORARY PASSWORD DISPLAY -->

    <?php if ($temporary_password): ?>

        <div class="temporary-password-box">

            <h3>Temporary Password Generated</h3>

            <p>
                Provide this password securely to the selected user.
                It will not be displayed again after this page is refreshed.
            </p>

            <div class="temporary-password-display">

                <code id="temporaryPassword">
                    <?= htmlspecialchars($temporary_password) ?>
                </code>

                <button
                    type="button"
                    class="copy-btn"
                    onclick="copyTemporaryPassword()"
                >
                    Copy Password
                </button>

            </div>

            <p class="password-warning">
                The user must create a new password when logging in.
            </p>

        </div>

    <?php endif; ?>


    <div class="users-table-header">

        <div>
            <h2>User Accounts</h2>

            <p>
                Total registered users:
                <strong><?= count($users) ?></strong>
            </p>
        </div>

    </div>


    <div class="users-table-wrapper">

        <table class="users-table">

            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Phone Number</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($users) > 0): ?>

                    <?php foreach ($users as $index => $user): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($user["full_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($user["email"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $user["phone_number"] ?: "Not provided"
                                ) ?>
                            </td>

                            <td>

                                <form
                                    method="POST"
                                    action="view_users.php"
                                    onsubmit="return confirm(
                                        'Are you sure you want to reset this user password?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrf_token) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $user["user_id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="reset-password-btn"
                                    >
                                        Reset Password
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty-state">
                            No registered users found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>


<script>

// =================================
// COPY TEMPORARY PASSWORD
// =================================

function copyTemporaryPassword() {

    const password = document.getElementById("temporaryPassword").textContent.trim();

    navigator.clipboard.writeText(password)
        .then(function () {
            alert("Temporary password copied successfully.");
        })
        .catch(function () {
            alert("Unable to copy password. Please copy it manually.");
        });
}


// =================================
// AUTO-DISMISS ALERTS
// =================================

setTimeout(function () {

    document.querySelectorAll(
        ".success-message, .error-message"
    ).forEach(function (message) {

        message.style.opacity = "0";

        setTimeout(function () {
            message.remove();
        }, 400);

    });

}, 5000);

</script>

</body>
</html>

<?php
$conn->close();
?>
