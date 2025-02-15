<?php
require_once '../php/database_connection.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Example queries:
    $event_query = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name ASC");
    $events = $event_query->fetchAll(PDO::FETCH_ASSOC);

    $sub_query = $pdo->query("SELECT * FROM scouting_submissions");
    $submissions = $sub_query->fetchAll(PDO::FETCH_ASSOC);

    $active_query = $pdo->query("SELECT * FROM active_event");
    $activeEvents = $active_query->fetchAll(PDO::FETCH_ASSOC);

    // Build and output JSON
    $data = [
        "events" => $events,
        "scouting_submissions" => $submissions,
        "active_event" => $activeEvents
    ];
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit;
}
?>
