
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Only logged-in users can download documents
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// Validate document ID
$document_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$document_id || $document_id < 1) {
    http_response_code(400);
    exit("Invalid document request.");
}

// Retrieve document belonging to the logged-in user
$sql = "SELECT d.document_name, d.file_path
        FROM business_documents d
        INNER JOIN businesses b
        ON d.business_id = b.business_id
        WHERE d.document_id = ? AND b.user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to process download request.");
}

$stmt->bind_param("ii", $document_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();

    http_response_code(404);
    exit("Document not found or access denied.");
}

$document = $result->fetch_assoc();

$stmt->close();
$conn->close();


// Locate uploaded document
$upload_dir = __DIR__ . '/uploads/business_documents/';
$real_upload_dir = realpath($upload_dir);

$filename = basename($document["file_path"]);

$file_path = realpath($upload_dir . $filename);

// Verify file location and existence
if (
    $real_upload_dir === false ||
    $file_path === false ||
    !str_starts_with(
        $file_path,
        $real_upload_dir . DIRECTORY_SEPARATOR
    ) ||
    !is_file($file_path) ||
    !is_readable($file_path)
) {
    http_response_code(404);
    exit("The requested file could not be found.");
}


// Detect actual file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file_path);

// Allowed MIME types
$allowed_mime_types = [
    "application/pdf",
    "image/jpeg",
    "image/png"
];

if (
    $mime_type === false ||
    !in_array($mime_type, $allowed_mime_types, true)
) {
    http_response_code(415);
    exit("Unsupported document format.");
}


// Prepare safe download filename
$extension = strtolower(
    pathinfo($filename, PATHINFO_EXTENSION)
);

$safe_name = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $document["document_name"]
);

if ($safe_name === "") {
    $safe_name = "business_document";
}

$download_name = $safe_name . "." . $extension;


// Prevent accidental output from corrupting the download
if (headers_sent()) {
    exit("Unable to prepare document download.");
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

// Send download headers
header("Content-Type: " . $mime_type);
header(
    "Content-Disposition: attachment; filename=\"" .
    $download_name . "\""
);
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");

// Send original file contents
readfile($file_path);

exit();

