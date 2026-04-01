<?php
session_start();
require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>DB Test</title><style>body{font-family:Arial;margin:20px} .ok{color:green;font-weight:bold} .err{color:red;font-weight:bold} pre{background:#f0f0f0;padding:10px;overflow-x:auto}</style></head><body>";

echo "<h1>🔧 Database Connection Test</h1>";

// Test 1: Connection
echo "<h2>1. Database Connection</h2>";
try {
    $db = getDB();
    echo "<p class='ok'>✅ Connected to: " . DB_NAME . " on 127.0.0.1:3307</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ Connection failed: " . $e->getMessage() . "</p>";
    die();
}

// Test 2: Tables exist
echo "<h2>2. Table Existence</h2>";
$tables = ['assessments', 'questions', 'test_sessions', 'candidate_answers', 'proctoring_violations'];
foreach ($tables as $table) {
    try {
        $result = $db->query("SELECT COUNT(*) FROM $table");
        $count = $result->fetchColumn();
        echo "<p class='ok'>✅ $table: $count rows</p>";
    } catch (Exception $e) {
        echo "<p class='err'>❌ $table: " . $e->getMessage() . "</p>";
    }
}

// Test 3: Sample SELECT
echo "<h2>3. Sample Data</h2>";
try {
    $assessments = $db->query("SELECT * FROM assessments LIMIT 1")->fetchAll();
    if ($assessments) {
        echo "<p>Found assessment:</p>";
        echo "<pre>" . json_encode($assessments[0], JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p class='err'>❌ No assessments. Create one first!</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 4: Test INSERT
echo "<h2>4. Test INSERT Operation</h2>";
try {
    $testValue = "test_" . time();
    $stmt = $db->prepare("INSERT INTO proctoring_violations (session_id, violation_type, severity, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->execute([1, $testValue, 'low']);
    $insertId = $db->lastInsertId();
    echo "<p class='ok'>✅ Insert successful! ID: $insertId</p>";
    
    // Verify it's there
    $check = $db->query("SELECT * FROM proctoring_violations WHERE id = $insertId")->fetch();
    if ($check) {
        echo "<p class='ok'>✅ Verified: Record found in database</p>";
        echo "<pre>" . json_encode($check, JSON_PRETTY_PRINT) . "</pre>";
    }
    
    // Clean up test record
    $db->query("DELETE FROM proctoring_violations WHERE id = $insertId");
    echo "<p class='ok'>✅ Cleanup complete</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ Insert failed: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>
