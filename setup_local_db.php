<?php
// setup_local_db.php
header('Content-Type: text/plain');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'xqtrqexj_jdm_kenya';

try {
    // 1. Connect without selecting database
    echo "Connecting to MySQL server...\n";
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ]);
    echo "Connected successfully!\n\n";

    // 2. Drop and recreate database
    echo "Dropping database '$dbName' if it exists...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "Creating database '$dbName'...\n";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully.\n\n";

    // 3. Select the database
    $pdo->exec("USE `$dbName`");
    echo "Selected database '$dbName'.\n\n";

    // 4. Disable foreign key checks
    echo "Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 5. Load combined_schema.sql
    $schemaFile = __DIR__ . '/combined_schema.sql';
    if (!file_exists($schemaFile)) {
        die("Error: schema file not found at $schemaFile\n");
    }

    echo "Reading schema file...\n";
    $sql = file_get_contents($schemaFile);

    // Remove comments and execute statements
    $queries = [];
    $accumulator = '';
    $in_string = false;
    $string_char = '';
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (!$in_string && (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0 || $trimmed === '')) {
            continue;
        }

        $accumulator .= $line . "\n";
        
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($in_string) {
                if ($char === $string_char && ($i === 0 || $line[$i-1] !== '\\')) {
                    $in_string = false;
                }
            } else {
                if ($char === "'" || $char === '"' || $char === '`') {
                    $in_string = true;
                    $string_char = $char;
                } elseif ($char === ';') {
                    $queries[] = trim($accumulator);
                    $accumulator = '';
                }
            }
        }
    }

    if (trim($accumulator) !== '') {
        $queries[] = trim($accumulator);
    }

    // Run in two passes to resolve forward references (ALTER before CREATE)
    echo "Executing " . count($queries) . " queries in 2 passes...\n";
    
    // Pass 1: Create all tables (suppress ALTER table failures)
    echo "--- Pass 1: Table Creation ---\n";
    $pass1Success = 0;
    $pass1Fail = 0;
    foreach ($queries as $q) {
        $q = trim($q);
        if ($q === '') continue;
        try {
            $stmt = $pdo->prepare($q);
            $stmt->execute();
            $stmt->closeCursor();
            $pass1Success++;
        } catch (PDOException $e) {
            $pass1Fail++;
        }
    }
    echo "Pass 1 Completed: $pass1Success succeeded, $pass1Fail failed.\n\n";

    // Pass 2: Apply Alterations (all should succeed now)
    echo "--- Pass 2: Applying Alterations & Re-running ---\n";
    $pass2Success = 0;
    $pass2Fail = 0;
    foreach ($queries as $q) {
        $q = trim($q);
        if ($q === '') continue;
        try {
            $stmt = $pdo->prepare($q);
            $stmt->execute();
            $stmt->closeCursor();
            $pass2Success++;
        } catch (PDOException $e) {
            echo "Failed query in Pass 2: " . substr($q, 0, 100) . "...\nError: " . $e->getMessage() . "\n\n";
            $pass2Fail++;
        }
    }
    echo "Pass 2 Completed: $pass2Success succeeded, $pass2Fail failed.\n\n";

    // 6. Re-enable foreign key checks
    echo "Re-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\nDatabase Setup Complete!\n";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
?>
