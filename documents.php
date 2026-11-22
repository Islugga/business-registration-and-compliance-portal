
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
$user_id = $_SESSION["user_id"];

$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

$upload_dir = __DIR__ . '/uploads/business_documents/';

// Allowed file types
$allowed_types = [
    "pdf"  => "application/pdf",
    "jpg"  => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png"  => "image/png"
];


// =================================
// PROCESS DOCUMENT UPLOAD
// =================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrf_token, $submitted_token)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $action = $_POST["action"] ?? "";


        // =================================
        // UPLOAD DOCUMENT
        // =================================

        if ($action === "upload") {

            $business_id = filter_var(
                $_POST["business_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            $document_name = trim($_POST["document_name"] ?? "");

            if (!$business_id || $business_id < 1 || empty($document_name)) {

                $error = "Please complete all required fields.";

            } elseif (strlen($document_name) > 100) {

                $error = "Document name must not exceed 100 characters.";

            } else {

                // Confirm that the business belongs to the logged-in user
                $check_sql = "SELECT business_id
                              FROM businesses
                              WHERE business_id = ? AND user_id = ?";

                $check_stmt = $conn->prepare($check_sql);

                if (!$check_stmt) {
                    die("Database query failed: " . $conn->error);
                }

                $check_stmt->bind_param("ii", $business_id, $user_id);
                $check_stmt->execute();

                $business_result = $check_stmt->get_result();

                if ($business_result->num_rows === 0) {

                    $error = "Invalid business selection.";

                } elseif (!isset($_FILES["document_file"])) {

                    $error = "Please select a document to upload.";

                } else {

                    $file = $_FILES["document_file"];

                    if ($file["error"] !== UPLOAD_ERR_OK) {

                        $error = "Document upload failed. Please try again.";

                    } elseif ($file["size"] > 5 * 1024 * 1024) {

                        $error = "File size must not exceed 5 MB.";

                    } else {

                        $extension = strtolower(
                            pathinfo($file["name"], PATHINFO_EXTENSION)
                        );

                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo->file($file["tmp_name"]);

                        if (
                            !isset($allowed_types[$extension]) ||
                            $allowed_types[$extension] !== $mime_type
                        ) {

                            $error = "Only PDF, JPG and PNG documents are allowed.";

                        } else {

                            // Create upload directory if it does not exist
                            if (
                                !is_dir($upload_dir) &&
                                !mkdir($upload_dir, 0750, true)
                            ) {

                                $error = "Unable to prepare the upload directory.";

                            } else {

                                // Generate a unique filename
                                $new_filename = bin2hex(random_bytes(16))
                                    . "." . $extension;

                                $destination = $upload_dir . $new_filename;

                                $relative_path = "uploads/business_documents/"
                                    . $new_filename;

                                if (
                                    move_uploaded_file(
                                        $file["tmp_name"],
                                        $destination
                                    )
                                ) {

                                    // Save document information
                                    $insert_sql = "INSERT INTO business_documents
                                                   (business_id, document_name, file_path)
                                                   VALUES (?, ?, ?)";

                                    $insert_stmt = $conn->prepare($insert_sql);

                                    if (!$insert_stmt) {

                                        unlink($destination);

                                        $error = "Unable to prepare document record.";

                                    } else {

                                        $insert_stmt->bind_param(
                                            "iss",
                                            $business_id,
                                            $document_name,
                                            $relative_path
                                        );

                                        if ($insert_stmt->execute()) {

                                            $insert_stmt->close();
                                            $check_stmt->close();
                                            $conn->close();

                                            header("Location: documents.php?success=uploaded");
                                            exit();

                                        } else {

                                            unlink($destination);

                                            $error = "Unable to save document information.";

                                        }

                                        $insert_stmt->close();
                                    }

                                } else {

                                    $error = "Unable to upload the selected document.";

                                }
                            }
                        }
                    }
                }

                $check_stmt->close();
            }


        // =================================
        // DELETE DOCUMENT
        // =================================

        } elseif ($action === "delete") {

            $document_id = filter_var(
                $_POST["document_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            if (!$document_id || $document_id < 1) {

                $error = "Invalid document selected.";

            } else {

                // Retrieve document only if it belongs to the user's business
                $delete_check_sql = "SELECT d.file_path
                                     FROM business_documents d
                                     INNER JOIN businesses b
                                     ON d.business_id = b.business_id
                                     WHERE d.document_id = ?
                                     AND b.user_id = ?";

                $delete_check_stmt = $conn->prepare($delete_check_sql);

                if (!$delete_check_stmt) {
                    die("Database query failed: " . $conn->error);
                }

                $delete_check_stmt->bind_param(
                    "ii",
                    $document_id,
                    $user_id
                );

                $delete_check_stmt->execute();

                $delete_result = $delete_check_stmt->get_result();

                if ($delete_result->num_rows === 0) {

                    $error = "Document not found or access denied.";

                } else {

                    $document = $delete_result->fetch_assoc();

                    $delete_sql = "DELETE FROM business_documents
                                   WHERE document_id = ?";

                    $delete_stmt = $conn->prepare($delete_sql);

                    if (!$delete_stmt) {
                        die("Database query failed: " . $conn->error);
                    }

                    $delete_stmt->bind_param("i", $document_id);

                    if ($delete_stmt->execute()) {

                        // Remove the physical file from the upload folder
                        $stored_filename = basename($document["file_path"]);
                        $file_to_delete = $upload_dir . $stored_filename;

                        if (is_file($file_to_delete)) {
                            unlink($file_to_delete);
                        }

                        $delete_stmt->close();
                        $delete_check_stmt->close();
                        $conn->close();

                        header("Location: documents.php?success=deleted");
                        exit();

                    } else {

                        $error = "Unable to delete the document.";

                    }

                    $delete_stmt->close();
                }

                $delete_check_stmt->close();
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
    die("Database query failed: " . $conn->error);
}

$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();

$businesses = $business_stmt->get_result();


// =================================
// RETRIEVE USER'S DOCUMENTS
// =================================

$document_sql = "SELECT d.document_id,
                        d.document_name,
                        d.file_path,
                        b.business_name
                 FROM business_documents d
                 INNER JOIN businesses b
                 ON d.business_id = b.business_id
                 WHERE b.user_id = ?
                 ORDER BY d.document_id DESC";

$document_stmt = $conn->prepare($document_sql);

if (!$document_stmt) {
    die("Database query failed: " . $conn->error);
}

$document_stmt->bind_param("i", $user_id);
$document_stmt->execute();

$documents = $document_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document Management</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/documents.css">

</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">

            <div>

                <h1>Document Management</h1>

                <p>
                    Upload, download and manage your business documents.
                </p>

            </div>

            <a href="dashboard.php" class="back-btn">
                Back to Dashboard
            </a>

        </div>


        <!-- Success Messages -->
        <?php if (isset($_GET["success"])): ?>

            <div class="success-message auto-dismiss">

                <?php if ($_GET["success"] === "uploaded"): ?>

                    Document uploaded successfully.

                <?php elseif ($_GET["success"] === "deleted"): ?>

                    Document deleted successfully.

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div class="error-message auto-dismiss">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>


        <!-- Upload Document Form -->
        <div class="content-card">

            <h2>Upload New Document</h2>

            <?php if ($businesses->num_rows > 0): ?>

                <form
                    method="POST"
                    action="documents.php"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($csrf_token); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="upload"
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

                            <?php while ($business = $businesses->fetch_assoc()): ?>

                                <option value="<?php echo (int) $business["business_id"]; ?>">
                                    <?php echo htmlspecialchars($business["business_name"], ENT_QUOTES, 'UTF-8'); ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="document_name">
                            Document Name
                        </label>

                        <input
                            type="text"
                            id="document_name"
                            name="document_name"
                            class="form-control"
                            placeholder="e.g. CAC Certificate"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="document_file">
                            Select Document
                        </label>

                        <input
                            type="file"
                            id="document_file"
                            name="document_file"
                            class="form-control"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        >

                        <small class="file-help">
                            Allowed formats: PDF, JPG and PNG. Maximum size: 5 MB.
                        </small>

                    </div>


                    <button type="submit" class="primary-btn">
                        Upload Document
                    </button>

                </form>

            <?php else: ?>

                <p class="empty-state">
                    You need to add a business before uploading documents.
                </p>

                <a href="business.php" class="primary-link">
                    Add a Business
                </a>

            <?php endif; ?>

        </div>


        <!-- Uploaded Documents -->
        <div class="content-card">

            <h2>My Uploaded Documents</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>S/N</th>
                            <th>Document Name</th>
                            <th>Business</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($documents->num_rows > 0): ?>

                            <?php $count = 1; ?>

                            <?php while ($document = $documents->fetch_assoc()): ?>

                                <tr>

                                    <td>
                                        <?php echo $count++; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($document["document_name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($document["business_name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td class="document-actions">

                                        <a
                                            href="download_document.php?id=<?php echo (int) $document["document_id"]; ?>"
                                            class="download-btn"
                                        >
                                            Download
                                        </a>

                                        <form
                                            method="POST"
                                            action="documents.php"
                                            class="delete-form"
                                            onsubmit="return confirm('Are you sure you want to delete this document?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($csrf_token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="document_id"
                                                value="<?php echo (int) $document["document_id"]; ?>"
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

                                <td colspan="4" class="empty-state">
                                    You have not uploaded any documents yet.
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
$document_stmt->close();
$conn->close();

?>
