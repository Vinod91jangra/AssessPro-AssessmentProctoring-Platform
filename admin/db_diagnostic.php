<?php
require_once '../config.php';

echo "<!DOCTYPE html>
<html>
<head>
  <title>Database Diagnostic</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    table { border-collapse: collapse; }
    table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    table th { background: #e0e0e0; }
  </style>
</head>
<body>
<h1>📊 Database Diagnostic Report</h1>";

try {
    $db = getDB();
    echo "<p class='success'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if proctoring_violations table exists
echo "<h2>1. Table Schema Check</h2>";
try {
    $result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='proctoring_violations' AND TABLE_SCHEMA=DATABASE()");
    if ($result->rowCount() > 0) {
        echo "<p class='success'>✅ proctoring_violations table EXISTS</p>";
        
        // Show table structure
        $cols = $db->query("DESCRIBE proctoring_violations");
        echo "<h3>Table Structure:</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ proctoring_violations table DOES NOT EXIST</p>";
        echo "<p>You need to create it. Run this SQL:</p>";
        echo "<pre>CREATE TABLE IF NOT EXISTS proctoring_violations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id INT NOT NULL,
  violation_type VARCHAR(255),
  severity ENUM('low', 'medium', 'high') DEFAULT 'low',
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  frame_data LONGBLOB,
  FOREIGN KEY (session_id) REFERENCES test_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking table: " . $e->getMessage() . "</p>";
}

// Count records
echo "<h2>2. Data Count</h2>";
try {
    $count = $db->query("SELECT COUNT(*) as cnt FROM proctoring_violations")->fetch()['cnt'];
    echo "<p class='info'>Total violations in database: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<h3>Recent Violations:</h3>";
        $recent = $db->query("SELECT id, session_id, violation_type, severity, timestamp FROM proctoring_violations ORDER BY timestamp DESC LIMIT 10");
        echo "<table>";
        echo "<tr><th>ID</th><th>Session ID</th><th>Violation Type</th><th>Severity</th><th>Timestamp</th></tr>";
        while ($row = $recent->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['session_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['violation_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['severity']) . "</td>";
            echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error querying violations: " . $e->getMessage() . "</p>";
}

// Check test_sessions table
echo "<h2>3. Test Sessions Check</h2>";
try {
    $sessionCount = $db->query("SELECT COUNT(*) as cnt FROM test_sessions")->fetch()['cnt'];
    echo "<p class='info'>Total test sessions: <strong>$sessionCount</strong></p>";
    
    if ($sessionCount > 0) {
        echo "<h3>Recent Sessions:</h3>";
        $sessions = $db->query("SELECT id, cand_id, assessment_id, start_time, end_time FROM test_sessions ORDER BY id DESC LIMIT 5");
        echo "<table>";
        echo "<tr><th>ID</th><th>Candidate ID</th><th>Assessment ID</th><th>Start Time</th><th>End Time</th></tr>";
        while ($row = $sessions->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['cand_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['assessment_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['start_time']) . "</td>";
            echo "<td>" . htmlspecialchars($row['end_time']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No test sessions found. Create one first!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error querying sessions: " . $e->getMessage() . "</p>";
}

// Check log file
echo "<h2>4. API Log File</h2>";
$logFile = '../logs/violations.log';
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    $logContent = file_get_contents($logFile);
    echo "<p class='success'>✅ Log file exists (" . $logSize . " bytes)</p>";
    echo "<h3>Last 20 lines:</h3>";
    $lines = array_slice(explode("\n", $logContent), -20);
    echo "<pre>" . htmlspecialchars(implode("\n", $lines)) . "</pre>";
} else {
    echo "<p class='info'>ℹ️ Log file not created yet (will be created on first API call)</p>";
}

echo "</body>
</html>";
?>
