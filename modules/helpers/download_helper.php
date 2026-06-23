<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

// Get file path from request
$filePath = $_GET['file'] ?? '';
$fileName = $_GET['name'] ?? basename($filePath);
$filePath = str_replace('\\', '/', $filePath);
$filePath = preg_replace('#^https?://[^/]+#i', '', $filePath);
// Strip the dynamic base path prefix so paths like /JDM_kenya/uploads/... → uploads/...
if (BASE_PATH !== '' && strpos($filePath, BASE_PATH . '/') === 0) {
    $filePath = substr($filePath, strlen(BASE_PATH) + 1);
} else {
    $filePath = ltrim($filePath, '/');
}
$filePath = ltrim($filePath, '/');

// Security: Validate file path to prevent directory traversal
$allowedDirectories = [
    'uploads/',
    'assets/',
    'images/',
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
$absolutePath = realpath(__DIR__ . '/../../' . $filePath);
$basePath = realpath(__DIR__ . '/../../');

// Additional security: Ensure file is within allowed base path
if ($absolutePath === false || $basePath === false || strpos($absolutePath, $basePath) !== 0 || !file_exists($absolutePath)) {
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
header('Content-Disposition: attachment; filename="' . safeDownloadName($fileName, $fileExtension) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

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

function safeDownloadName($fileName, $extension) {
    $baseName = basename((string)$fileName);
    $baseName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $baseName);
    $baseName = trim($baseName, " .\t\n\r\0\x0B");

    if ($baseName === '') {
        $baseName = 'download';
    }

    if (strtolower(pathinfo($baseName, PATHINFO_EXTENSION)) !== $extension) {
        $baseName .= '.' . $extension;
    }

    return str_replace('"', '', $baseName);
}
?>
