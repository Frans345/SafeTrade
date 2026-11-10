<?php
require_once 'includes/db.php';

echo "<h3>Upgrading SafeTrade Database for Soft Deletes...</h3>";

try {
    // Add is_deleted to users
    $pdo->exec("ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    echo "<p style='color:green;'>✓ Added 'is_deleted' to users table.</p>";
} catch (Exception $e) {
    echo "<p style='color:orange;'>⚠ Column might already exist in users.</p>";
}

try {
    // Add is_deleted to listings
    $pdo->exec("ALTER TABLE listings ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    echo "<p style='color:green;'>✓ Added 'is_deleted' to listings table.</p>";
} catch (Exception $e) {
    echo "<p style='color:orange;'>⚠ Column might already exist in listings.</p>";
}

echo "<h4>Upgrade complete! You can now delete this file.</h4>";
?>