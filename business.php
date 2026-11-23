
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Authentication
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Administrators cannot access business management
if (($_SESSION["role"] ?? "") === "admin") {
    header("Location: admin.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$error = "";

// Business types
$business_types = [
    "Sole Proprietorship",
    "Partnership",
    "Limited Liability Company",
    "Public Limited Company",
    "Cooperative",
    "Other"
];

// Process business registration form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $business_name = trim($_POST["business_name"] ?? "");
    $business_type = trim($_POST["business_type"] ?? "");

    if (empty($business_name) || empty($business_type)) {

        $error = "Please complete all fields.";

    } elseif (!in_array($business_type, $business_types, true)) {

        $error = "Please select a valid business type.";

    } else {

        // Insert business into database
        $sql = "INSERT INTO businesses
                (user_id, business_name, business_type)
                VALUES (?, ?, ?)";

        $insert_stmt = $conn->prepare($sql);

        $insert_stmt->bind_param(
            "iss",
            $user_id,
            $business_name,
            $business_type
        );

        if ($insert_stmt->execute()) {

            $insert_stmt->close();

            header("Location: business.php?success=1");
            exit();

        } else {

            $error = "Unable to save your business. Please try again.";
            $insert_stmt->close();

        }
    }
}

// Retrieve businesses belonging to the logged-in user
$sql = "SELECT business_id,
               business_name,
               business_type,
               registration_status,
               cac_registration_number,
               registration_date,
               tin,
               bank_account_status,
               bank_name,
               bank_account_number
        FROM businesses
        WHERE user_id = ?
        ORDER BY business_id DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Business Management</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/business.css">

</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">

            <div>

                <h1>Business Profile Management</h1>

                <p>
                    Add and manage your business information,
                    registration details and post-registration requirements.
                </p>

            </div>

            <a href="dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </div>


        <!-- Success Message -->
        <?php if (isset($_GET["success"])): ?>

            <div class="success-message auto-dismiss">
                Business added successfully.
            </div>

        <?php endif; ?>


        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div class="error-message auto-dismiss">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>


        <!-- Add Business Form -->
        <div class="content-card">

            <h2>Add New Business</h2>

            <form method="POST" action="business.php">

                <div class="form-group">

                    <label for="business_name">
                        Business Name
                    </label>

                    <input
                        type="text"
                        id="business_name"
                        name="business_name"
                        class="form-control"
                        placeholder="Enter your business name"
                        maxlength="150"
                        required
                    >

                </div>


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

                        <option value="" selected disabled>
                            Select business type
                        </option>

                        <?php foreach ($business_types as $type): ?>

                            <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <button type="submit" class="primary-btn">
                    Add Business
                </button>

            </form>

        </div>


        <!-- Business List -->
        <div class="content-card">

            <h2>My Businesses</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>S/N</th>
                            <th>Business Name</th>
                            <th>Business Type</th>
                            <th>CAC Status</th>
                            <th>CAC Number</th>
                            <th>Registration Date</th>
                            <th>TIN Status</th>
                            <th>Bank Account Status</th>
                            <th>Bank Name</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($result->num_rows > 0): ?>

                            <?php $count = 1; ?>

                            <?php while ($business = $result->fetch_assoc()): ?>

                                <tr>

                                    <!-- Serial Number -->
                                    <td>
                                        <?php echo $count++; ?>
                                    </td>


                                    <!-- Business Name -->
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars(
                                                $business["business_name"],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </strong>
                                    </td>


                                    <!-- Business Type -->
                                    <td>
                                        <?php echo htmlspecialchars(
                                            $business["business_type"],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>


                                    <!-- CAC Registration Status -->
                                    <td>

                                        <span class="status-pending">

                                            <?php echo htmlspecialchars(
                                                $business["registration_status"] ?? "Pending",
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- CAC Registration Number -->
                                    <td>

                                        <?php if (!empty($business["cac_registration_number"])): ?>

                                            <?php echo htmlspecialchars(
                                                $business["cac_registration_number"],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        <?php else: ?>

                                            <span>Not Available</span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Registration Date -->
                                    <td>

                                        <?php if (!empty($business["registration_date"])): ?>

                                            <?php echo htmlspecialchars(
                                                $business["registration_date"],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        <?php else: ?>

                                            <span>Not Available</span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- TIN Status -->
                                    <td>

                                        <?php if (!empty($business["tin"])): ?>

                                            <span class="status-pending">
                                                Provided
                                            </span>

                                        <?php else: ?>

                                            <span class="status-pending">
                                                Not Provided
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Bank Account Status -->
                                    <td>

                                        <span class="status-pending">

                                            <?php echo htmlspecialchars(
                                                $business["bank_account_status"] ?? "Not Opened",
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- Bank Name -->
                                    <td>

                                        <?php if (!empty($business["bank_name"])): ?>

                                            <?php echo htmlspecialchars(
                                                $business["bank_name"],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        <?php else: ?>

                                            <span>Not Available</span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Actions -->
                                    <td>

                                        <a
                                            href="edit_business.php?id=<?php echo (int) $business["business_id"]; ?>"
                                            class="update-btn"
                                        >
                                            Update
                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="10" class="empty-state">

                                    You have not added any business yet.

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

$stmt->close();
$conn->close();

?>
