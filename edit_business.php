
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

// Registration statuses
$registration_statuses = [
    "Pending",
    "In Progress",
    "Registered"
];

// Bank account statuses
$bank_account_statuses = [
    "Not Opened",
    "In Progress",
    "Opened"
];

// Retrieve the selected business
$sql = "SELECT business_id, business_name, business_type,
               registration_status, cac_registration_number,
               registration_date, tin, bank_account_status,
               bank_name, bank_account_number
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

    $tin = trim($_POST["tin"] ?? "");

    $bank_account_status = trim($_POST["bank_account_status"] ?? "");
    $bank_name = trim($_POST["bank_name"] ?? "");
    $bank_account_number = trim($_POST["bank_account_number"] ?? "");

    // Validate required fields
    if (
        empty($business_name) ||
        empty($business_type) ||
        empty($registration_status) ||
        empty($bank_account_status)
    ) {

        $error = "Please complete all required fields.";

    } elseif (!in_array($business_type, $business_types, true)) {

        $error = "Please select a valid business type.";

    } elseif (!in_array($registration_status, $registration_statuses, true)) {

        $error = "Please select a valid registration status.";

    } elseif (!in_array($bank_account_status, $bank_account_statuses, true)) {

        $error = "Please select a valid bank account status.";

    } elseif (
        $registration_status === "Registered" &&
        (empty($cac_number) || empty($registration_date))
    ) {

        $error = "CAC registration number and registration date are required for registered businesses.";

    } elseif (
        !empty($registration_date) &&
        (
            !DateTime::createFromFormat('!Y-m-d', $registration_date) ||
            DateTime::createFromFormat('!Y-m-d', $registration_date)->format('Y-m-d') !== $registration_date
        )
    ) {

        $error = "Please enter a valid registration date.";

    } elseif (
        $bank_account_status === "Opened" &&
        (empty($bank_name) || empty($bank_account_number))
    ) {

        $error = "Bank name and account number are required when the business account is opened.";

    } else {

        // Update business information
        $sql = "UPDATE businesses
                SET business_name = ?,
                    business_type = ?,
                    registration_status = ?,
                    cac_registration_number = NULLIF(?, ''),
                    registration_date = NULLIF(?, ''),
                    tin = NULLIF(?, ''),
                    bank_account_status = ?,
                    bank_name = NULLIF(?, ''),
                    bank_account_number = NULLIF(?, '')
                WHERE business_id = ? AND user_id = ?";

        $update_stmt = $conn->prepare($sql);

        $update_stmt->bind_param(
            "sssssssssii",
            $business_name,
            $business_type,
            $registration_status,
            $cac_number,
            $registration_date,
            $tin,
            $bank_account_status,
            $bank_name,
            $bank_account_number,
            $business_id,
            $user_id
        );

        if ($update_stmt->execute()) {

            $update_stmt->close();

            header("Location: edit_business.php?id=" . $business_id . "&success=1");
            exit();

        } else {

            $error = "Unable to update business information. Please try again.";
            $update_stmt->close();

        }
    }

    // Preserve submitted values when validation fails
    $business["business_name"] = $business_name;
    $business["business_type"] = $business_type;
    $business["registration_status"] = $registration_status;
    $business["cac_registration_number"] = $cac_number;
    $business["registration_date"] = $registration_date;

    $business["tin"] = $tin;
    $business["bank_account_status"] = $bank_account_status;
    $business["bank_name"] = $bank_name;
    $business["bank_account_number"] = $bank_account_number;
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

                <!-- TIN -->
                <div class="form-group">

                    <label for="tin">
                        Tax Identification Number (TIN)
                    </label>

                    <input
                        type="text"
                        id="tin"
                        name="tin"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["tin"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter Tax Identification Number"
                        maxlength="50"
                    >

                    <small>
                        Enter your business Tax ID if it has been issued.
                    </small>

                </div>

                <!-- Bank Account Status -->
                <div class="form-group">

                    <label for="bank_account_status">
                        Business Bank Account Status
                    </label>

                    <select
                        id="bank_account_status"
                        name="bank_account_status"
                        class="form-control"
                        required
                    >

                        <?php foreach ($bank_account_statuses as $status): ?>

                            <option
                                value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo (($business["bank_account_status"] ?? "Not Opened") === $status) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Bank Name -->
                <div class="form-group">

                    <label for="bank_name">
                        Bank Name
                    </label>

                    <input
                        type="text"
                        id="bank_name"
                        name="bank_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["bank_name"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter bank name"
                        maxlength="100"
                    >

                </div>

                <!-- Business Account Number -->
                <div class="form-group">

                    <label for="bank_account_number">
                        Business Account Number
                    </label>

                    <input
                        type="text"
                        id="bank_account_number"
                        name="bank_account_number"
                        class="form-control"
                        value="<?php echo htmlspecialchars($business["bank_account_number"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter business account number"
                        maxlength="50"
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
