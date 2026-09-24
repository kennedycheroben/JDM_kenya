<?php
// =====================================================
// JDM Kenya - DB Diagnostic (DELETE AFTER USE)
// =====================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configPath = __DIR__ . '/config.php';
echo "<h3>1. Config File</h3>";
echo "Expected path: <code>" . htmlspecialchars($configPath) . "</code><br>";
echo "File exists: <strong>" . (file_exists($configPath) ? '✅ YES' : '❌ NO') . "</strong><br>";
echo "Readable: <strong>" . (is_readable($configPath) ? '✅ YES' : '❌ NO') . "</strong><br>";

if (file_exists($configPath)) {
    require_once $configPath;
}

echo "<h3>2. DB Constants Loaded</h3>";
$constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SITE_URL'];
foreach ($constants as $c) {
    $val = defined($c) ? constant($c) : '❌ NOT DEFINED';
    if ($c === 'DB_PASS' && defined($c)) {
        $val = str_repeat('*', max(4, strlen($val) - 2)) . substr($val, -2);
    }
    echo "<strong>$c</strong>: <code>" . htmlspecialchars($val) . "</code><br>";
}

echo "<h3>3. DB Connection Test</h3>";
if (!defined('DB_NAME') || !defined('DB_USER')) {
    echo "❌ Cannot test — DB_NAME or DB_USER not defined.<br>";
} else {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "✅ <strong>Connected successfully!</strong><br>";
        $row = $pdo->query("SELECT VERSION() AS v")->fetch();
        echo "MySQL version: <code>" . htmlspecialchars($row['v']) . "</code><br>";
        $userRow = $pdo->query("SELECT USER() AS u")->fetch();
        echo "Logged in as: <code>" . htmlspecialchars($userRow['u']) . "</code><br>";
    } catch (PDOException $e) {
        echo "❌ <strong>Connection failed:</strong> <code>" . htmlspecialchars($e->getMessage()) . "</code><br>";
        echo "<br>Trying 127.0.0.1...<br>";
        try {
            $pdo2 = new PDO(
                'mysql:host=127.0.0.1;dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "✅ <strong>127.0.0.1 fallback worked!</strong><br>";
        } catch (PDOException $e2) {
            echo "❌ Fallback also failed: <code>" . htmlspecialchars($e2->getMessage()) . "</code><br>";
        }
    }
}

echo "<h3>4. Server Environment</h3>";
echo "PHP version: <code>" . PHP_VERSION . "</code><br>";
echo "DOCUMENT_ROOT: <code>" . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</code><br>";
echo "Script path: <code>" . htmlspecialchars(__FILE__) . "</code><br>";

echo "<br><hr><p style='color:red;font-weight:bold'>⚠️ DELETE dbcheck.php after debugging!</p>";
