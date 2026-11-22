
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Protect the page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Get business ID from URL
$business_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$business_id || $business_id < 1) {
    header("Location: business.php");
    exit();
}

$error = "";

// Available business types
$business_types = [
    "Sole Proprietorship",
    "Partnership",
    "Limited Liability Company",
    "Public Limited Company",
    "Cooperative",
    "Other"
];

// Available registration statuses
$registration_statuses = [
    "Pending",
    "In Progress",
    "Registered"
];

// Retrieve the selected business
$sql = "SELECT business_id, business_name, business_type,
               registration_status, cac_registration_number,
               registration_date
        FROM businesses
        WHERE business_id = ? AND user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $business_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();

    header("Location: business.php");
    exit();
}

$business = $result->fetch_assoc();
$stmt->close();


// Process business update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $business_name = trim($_POST["business_name"] ?? "");
    $business_type = trim($_POST["business_type"] ?? "");
    $registration_status = trim($_POST["registration_status"] ?? "");
    $cac_number = trim($_POST["cac_registration_number"] ?? "");
    $registration_date = trim($_POST["registration_date"] ?? "");

    // Validate required fields
    if (
        empty($business_name) ||
        empty($business_type) ||
        empty($registration_status)
    ) {

        $error = "Please complete all required fields.";

    } elseif (!in_array($business_type, $business_types, true)) {

        $error = "Please select a valid business type.";

    } elseif (!in_array($registration_status, $registration_statuses, true)) {

        $error = "Please select a valid registration status.";

    } elseif (
        $registration_status === "Registered" &&
        (empty($cac_number) || empty($registration_date))
    ) {

        $error = "CAC registration number and registration date are required for registered businesses.";

    } elseif (
        !empty($registration_date) &&
        !DateTime::createFromFormat('!Y-m-d', $registration_date)
    ) {

        $error = "Please enter a valid registration date.";

    } else {

        // Update business information
        $sql = "UPDATE businesses
                SET business_name = ?,
                    business_type = ?,
                    registration_status = ?,
                    cac_registration_number = NULLIF(?, ''),
                    registration_date = NULLIF(?, '')
                WHERE business_id = ? AND user_id = ?";

        $update_stmt = $conn->prepare($sql);

        $update_stmt->bind_param(
            "sssssii",
            $business_name,
            $business_type,
            $registration_status,
            $cac_number,
            $registration_date,
            $business_id,
            $user_id
        );

        if ($update_stmt->execute()) {

            $update_stmt->close();

            header("Location: edit_business.php?id=" . $business_id . "&success=1");
            exit();

        } else {

            $error = "Unable to update business information. Please try again.";

        }

        $update_stmt->close();
    }

    // Preserve submitted values when validation fails
    $business["business_name"] = $business_name;
    $business["business_type"] = $business_type;
    $business["registration_status"] = $registration_status;
    $business["cac_registration_number"] = $cac_number;
    $business["registration_date"] = $registration_date;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Business Information</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/business.css">

</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">

            <div>

                <h1>Edit Business Information</h1>

                <p>
                    Update your business details and registration information.
                </p>

            </div>

            <a href="business.php" class="back-btn">
                Back to Businesses
            </a>

        </div>


        <!-- Success Message -->
        <?php if (isset($_GET["success"])): ?>

            <div class="success-message auto-dismiss">
                Business information updated successfully.
            </div>

        <?php endif; ?>


        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div class="error-message auto-dismiss">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>


        <!-- Edit Business Form -->
        <div class="content-card">

            <h2>Business Details</h2>

            <form method="POST" action="edit_business.php?id=<?php echo $business_id; ?>">

                <!-- Business Name -->
                <div class="form-group">

                    <label for="business_name">
                        Business Name
                    </label>

                    <input
                        type="text"
                        id="business_name"
                        name="business_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["business_name"], ENT_QUOTES, 'UTF-8'); ?>"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- Business Type -->
                <div class="form-group">

                    <label for="business_type">
                        Business Type
                    </label>

                    <select
                        id="business_type"
                        name="business_type"
                        class="form-control"
                        required
                    >

                        <?php foreach ($business_types as $type): ?>

                            <option
                                value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ($business["business_type"] === $type) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Registration Status -->
                <div class="form-group">

                    <label for="registration_status">
                        Registration Status
                    </label>

                    <select
                        id="registration_status"
                        name="registration_status"
                        class="form-control"
                        required
                    >

                        <?php foreach ($registration_statuses as $status): ?>

                            <option
                                value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo ($business["registration_status"] === $status) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CAC Registration Number -->
                <div class="form-group">

                    <label for="cac_registration_number">
                        CAC Registration Number
                    </label>

                    <input
                        type="text"
                        id="cac_registration_number"
                        name="cac_registration_number"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["cac_registration_number"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter CAC registration number"
                        maxlength="100"
                    >

                </div>


                <!-- Registration Date -->
                <div class="form-group">

                    <label for="registration_date">
                        CAC Registration Date
                    </label>

                    <input
                        type="date"
                        id="registration_date"
                        name="registration_date"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["registration_date"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                    >

                </div>


                <!-- Form Buttons -->
                <div class="form-actions">

                    <button type="submit" class="primary-btn">
                        Save Changes
                    </button>

                    <a href="business.php" class="cancel-btn">
                        Cancel
                    </a>

                </div>

            </form>

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

$conn->close();

?>
