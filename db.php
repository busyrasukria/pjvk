<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

// Database configuration
$host = 'localhost';
$dbname = 'warehouse_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function checkAndAutoResetTripCounters($pdo) {
    // Set explicit Malaysian Time Zone for safety inside the function
    date_default_timezone_set('Asia/Kuala_Lumpur');
    $today = date('Y-m-d');

    try {
        // 1. Fetch last reset date
        $stmt_date = $pdo->prepare("SELECT last_reset_date FROM daily_reset_status WHERE id = 1");
        $stmt_date->execute();
        // Fallback to a very old date if the table/row doesn't exist yet
        $last_reset = $stmt_date->fetchColumn() ?? '2000-01-01'; 

        // 2. Check if the last reset was before today
        if ($last_reset < $today) {
            $pdo->beginTransaction();
            // Reset all ACTUAL_TRIP columns
            $pdo->exec("UPDATE master_trip SET ACTUAL_TRIP_1 = 0, ACTUAL_TRIP_2 = 0, ACTUAL_TRIP_3 = 0, ACTUAL_TRIP_4 = 0, ACTUAL_TRIP_5 = 0, ACTUAL_TRIP_6 = 0");
            // Update the last reset date (REPLACE handles insert or update)
            $pdo->prepare("REPLACE INTO daily_reset_status (id, last_reset_date) VALUES (1, ?)")->execute([$today]);
            $pdo->commit();
            error_log("Trip counters auto-reset completed for $today.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Trip Auto-Reset Failed in db.php: " . $e->getMessage());
        // The script continues without crashing the user experience
    }
}
?>