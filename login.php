
<?php

session_start();

require_once __DIR__ . "/config/database.php";

$message = "";
$password_message = "";

// =================================
// CSRF TOKEN
// =================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


// =================================
// HANDLE FORM SUBMISSIONS
// =================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // =================================
    // CHANGE TEMPORARY PASSWORD
    // =================================

    if (($_POST["action"] ?? "") === "change_password") {

        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($csrf_token, $_POST["csrf_token"])
        ) {

            $password_message = "Invalid request. Please refresh the page.";

        } elseif (
            !isset($_SESSION["user_id"]) ||
            ($_SESSION["must_change_password"] ?? 0) !== 1
        ) {

            $password_message = "You are not authorized to change this password.";

        } else {

            $new_password = $_POST["new_password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (
                strlen($new_password) < 8 ||
                strlen($new_password) > 4096
            ) {

                $password_message = "Your new password must contain at least 8 characters.";

            } elseif ($new_password !== $confirm_password) {

                $password_message = "The passwords do not match.";

            } else {

                $new_password_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $user_id = (int) $_SESSION["user_id"];

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET password_hash = ?, must_change_password = 0
                     WHERE user_id = ?"
                );

                if ($stmt) {

                    $stmt->bind_param(
                        "si",
                        $new_password_hash,
                        $user_id
                    );

                    if ($stmt->execute() && $stmt->affected_rows === 1) {

                        // Update session
                        $_SESSION["must_change_password"] = 0;

                        // Regenerate session ID
                        session_regenerate_id(true);

                        // Redirect based on role
                        if ($_SESSION["role"] === "admin") {
                            header("Location: admin.php");
                        } else {
                            header("Location: dashboard.php");
                        }

                        $stmt->close();
                        $conn->close();
                        exit();

                    } else {

                        $password_message = "Unable to update your password. Please try again.";

                    }

                    $stmt->close();

                } else {

                    $password_message = "Unable to process your request. Please try again.";

                }

            }

        }

    }


    // =================================
    // NORMAL LOGIN
    // =================================

    else {

        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if (empty($email) || empty($password)) {

            $message = "Please enter your email and password.";

        } else {

            // Find user by email
            $sql = "SELECT
                        user_id,
                        full_name,
                        email,
                        password_hash,
                        role,
                        must_change_password
                    FROM users
                    WHERE email = ?";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {

                $message = "Unable to process login. Please try again.";

            } else {

                $stmt->bind_param("s", $email);

                if ($stmt->execute()) {

                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {

                        $user = $result->fetch_assoc();

                        // Verify password
                        if (password_verify(
                            $password,
                            $user["password_hash"]
                        )) {

                            // Regenerate session ID
                            session_regenerate_id(true);

                            // Store user information
                            $_SESSION["user_id"] = (int) $user["user_id"];
                            $_SESSION["full_name"] = $user["full_name"];
                            $_SESSION["email"] = $user["email"];
                            $_SESSION["role"] = $user["role"];
                            $_SESSION["must_change_password"] =
                                (int) $user["must_change_password"];

                            // Check whether password change is required
                            if ($_SESSION["must_change_password"] === 1) {

                                // Stay on login page.
                                // The password-change modal will appear.

                            } else {

                                // Redirect based on user role
                                if ($user["role"] === "admin") {
                                    header("Location: admin.php");
                                } else {
                                    header("Location: dashboard.php");
                                }

                                $stmt->close();
                                $conn->close();
                                exit();

                            }

                        } else {

                            $message = "Invalid email or password.";

                        }

                    } else {

                        $message = "Invalid email or password.";

                    }

                } else {

                    $message = "Unable to process login. Please try again.";

                }

                $stmt->close();

            }

        }

    }

}


// =================================
// CHECK WHETHER MODAL SHOULD APPEAR
// =================================

$show_password_modal = (
    isset($_SESSION["user_id"]) &&
    ($_SESSION["must_change_password"] ?? 0) === 1
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Login | BusinessPortal</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">

</head>

<body>

    <div class="auth-container">

        <div class="auth-box">

            <h2>Welcome Back</h2>

            <p>Login to your business portal account.</p>


            <!-- Registration Success Message -->

            <?php if (isset($_GET["registered"])): ?>

                <div class="success-message">
                    Account created successfully. Please login.
                </div>

            <?php endif; ?>


            <!-- Login Error Message -->

            <?php if (!empty($message)): ?>

                <div class="error-message">

                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </div>

            <?php endif; ?>


            <!-- Login Form -->

            <form method="POST" action="login.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>"
                >

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="username"
                    required
                >


                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


                <button type="submit" class="auth-btn">
                    Login
                </button>

            </form>


            <p class="auth-link">

                Don't have an account?

                <a href="register.php">
                    Register here
                </a>

            </p>


            <p class="auth-link">

                <a href="index.php">
                    Back to Home
                </a>

            </p>

        </div>

    </div>


    <!-- PASSWORD CHANGE MODAL -->

    <?php if ($show_password_modal): ?>

        <div class="password-modal-overlay">

            <div
                class="password-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="password-modal-title"
            >

                <h2 id="password-modal-title">
                    Change Your Password
                </h2>

                <p>
                    Your account has been issued a temporary password.
                    Please create a new password before continuing.
                </p>


                <!-- Password Change Error -->

                <?php if (!empty($password_message)): ?>

                    <div class="error-message">

                        <?= htmlspecialchars(
                            $password_message,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>

                    </div>

                <?php endif; ?>


                <!-- Change Password Form -->

                <form method="POST" action="login.php">

                    <input
                        type="hidden"
                        name="action"
                        value="change_password"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>"
                    >


                    <label for="new_password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Enter your new password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >


                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your new password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >


                    <button type="submit" class="auth-btn">
                        Update Password
                    </button>

                </form>

            </div>

        </div>

    <?php endif; ?>

</body>
</html>

<?php

$conn->close();

?>
