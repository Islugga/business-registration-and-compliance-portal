
<?php

require_once __DIR__ . "/config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone_number = trim($_POST["phone_number"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone_number) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } elseif (
        strlen($phone_number) < 7 ||
        strlen($phone_number) > 20 ||
        !preg_match('/^[0-9+\-\s()]+$/', $phone_number)
    ) {

        $message = "Please enter a valid phone number.";

    } elseif (strlen($password) < 8) {

        $message = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

    } else {

        // Check if email already exists
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );

        if ($check) {

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "This email address is already registered.";

            } else {

                // Hash password
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert user into database
                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (full_name, email, phone_number, password_hash)
                    VALUES (?, ?, ?, ?)"
                );

                if ($stmt) {

                    $stmt->bind_param(
                        "ssss",
                        $full_name,
                        $email,
                        $phone_number,
                        $password_hash
                    );

                    if ($stmt->execute()) {

                        header("Location: login.php?registered=1");
                        exit();

                    } else {

                        $message = "Registration failed. Please try again.";

                    }

                    $stmt->close();

                } else {

                    $message = "Unable to process registration. Please try again.";

                }
            }

            $check->close();

        } else {

            $message = "Unable to process registration. Please try again.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="auth-container">

    <div class="auth-box">

        <h2>Create Your Account</h2>

        <p>Register to access the Business Portal.</p>

        <?php if (!empty($message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <label>Full Name</label>
            <input
                type="text"
                name="full_name"
                placeholder="Enter your full name"
                value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                required
            >

            <label>Email Address</label>
            <input
                type="email"
                name="email"
                placeholder="Enter your email"
                value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                required
            >

            <label>Phone Number</label>
            <input
                type="tel"
                name="phone_number"
                placeholder="e.g. +2348012345678"
                value="<?php echo htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                maxlength="20"
                required
            >

            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Create a password"
                minlength="8"
                required
            >

            <label>Confirm Password</label>
            <input
                type="password"
                name="confirm_password"
                placeholder="Confirm your password"
                minlength="8"
                required
            >

            <button type="submit" class="auth-btn">
                Create Account
            </button>

        </form>

        <p class="auth-link">
            Already have an account?
            <a href="login.php">Login here</a>
        </p>

        <p class="auth-link">
            <a href="index.php">Back to Home</a>
        </p>

    </div>

</div>

</body>
</html>
