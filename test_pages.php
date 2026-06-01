<?php
// Diagnostic test for login and resources pages
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Page Loads</h2>";

echo "<h3>Testing login.php include...</h3>";
try {
    ob_start();
    include __DIR__ . '/modules/auth/login.php';
    $output = ob_get_clean();
    echo "<p>✓ Login module loaded successfully</p>";
    echo "<p>Output length: " . strlen($output) . " bytes</p>";
    if (strlen($output) < 100) {
        echo "<p style='color:red;'><strong>WARNING: Output is suspiciously small!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error loading login module: " . $e->getMessage() . "</p>";
}

echo "<h3>Testing resources.php include...</h3>";
try {
    ob_start();
    include __DIR__ . '/modules/portal/resources.php';
    $output = ob_get_clean();
    echo "<p>✓ Resources module loaded successfully</p>";
    echo "<p>Output length: " . strlen($output) . " bytes</p>";
    if (strlen($output) < 100) {
        echo "<p style='color:red;'><strong>WARNING: Output is suspiciously small!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error loading resources module: " . $e->getMessage() . "</p>";
}

echo "<h3>Testing core db_connect...</h3>";
try {
    $pdo_test = null;
    ob_start();
    include __DIR__ . '/core/db_connect.php';
    ob_end_clean();
    echo "<p>✓ Database connection successful</p>";
    echo "<p>PDO object: " . (isset($pdo) ? "yes" : "no") . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error with db_connect: " . $e->getMessage() . "</p>";
}
?>
