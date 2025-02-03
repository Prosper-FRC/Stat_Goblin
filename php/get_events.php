<?php
// Database connection details
$host = 'localhost';
$dbname = 'frc_scouting';
$username = 'root';
$password = 'pw123456';

try {
    // Create a PDO instance to connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare the SQL query to fetch all event names from the frc_events table
    $sql = "SELECT event_name FROM frc_events WHERE active = 1";  // Assuming you only want active events

    // Execute the query
    $stmt = $pdo->query($sql);

    // Fetch the results as an associative array
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the events as JSON
    echo json_encode($events);
} catch (PDOException $e) {
    // Catch any database connection errors
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
?>
