<?php
require_once 'includes/db.php';

echo "<h3>Running SafeTrade Admin Setup...</h3>";

// 1. create reports table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_user_id INT NOT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green;'>✓ Reports table verified/created.</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error creating reports table: " . $e->getMessage() . "</p>";
}

// 2. create admin acc
try {
    //check if admin account already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = 'admin'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        //hash the specific password you requested
        $adminPassword = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(8)); // set ADMIN_PASSWORD or note the generated one below
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        
        // insert the admin
        $insertStmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, is_admin, is_verified, town, gender, area) VALUES ('System Admin', 'admin', ?, 1, 1, 'Headquarters', 'other', 'gauteng')");
        $insertStmt->execute([$hashedPassword]);
        
        echo "<p style='color:green;'>✓ Admin account created successfully!</p>";
        echo "<ul><li><b>Username/Email:</b> admin</li><li><b>Password:</b> " . htmlspecialchars($adminPassword) . "</li></ul>";
    } else {
        echo "<p style='color:orange;'>⚠ Admin account already exists in the database.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error creating admin account: " . $e->getMessage() . "</p>";
}

echo "<h4>Setup complete. Please delete this file for security.</h4>";
?>