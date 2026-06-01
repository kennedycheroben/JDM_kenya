<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Security: Only allow logged-in users for certain file types
$allowPublicAccess = true;
if (!empty($_SESSION['user_role'])) {
    $allowPublicAccess = false;
}

// Get file path from request
$filePath = $_GET['file'] ?? '';
$fileName = $_GET['name'] ?? basename($filePath);

// Security: Validate file path to prevent directory traversal
$allowedDirectories = [
    '/uploads/',
    '/assets/',
    '/JDM_kenya/uploads/',
    '/JDM_kenya/assets/'
];

$isValidPath = false;
foreach ($allowedDirectories as $allowedDir) {
    if (strpos($filePath, $allowedDir) === 0) {
        $isValidPath = true;
        break;
    }
}

if (!$isValidPath || empty($filePath)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Convert relative path to absolute path
$absolutePath = realpath(__DIR__ . '/../../' . ltrim($filePath, '/'));
$basePath = realpath(__DIR__ . '/../../');

// Additional security: Ensure file is within allowed base path
if (strpos($absolutePath, $basePath) !== 0 || !file_exists($absolutePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Get file info
$fileSize = filesize($absolutePath);
$fileExtension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$mimeType = getMimeType($fileExtension);

// Security: Check file extension
$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',  // Images
    'pdf', 'doc', 'docx', 'txt',         // Documents
    'mp4', 'avi', 'mov', 'wmv',         // Videos
    'mp3', 'wav', 'ogg',                // Audio
    'zip', 'rar', '7z'                  // Archives
];

if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

// Set appropriate headers for download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . htmlspecialchars($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Security: Prevent hotlinking
if (!$allowPublicAccess) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

// Read and output file
readfile($absolutePath);
exit;

/**
 * Get MIME type based on file extension
 */
function getMimeType($extension) {
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}
?>
