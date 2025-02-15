<?php
header('Content-Type: application/json');

require_once('../../php/database_connection.php');

try {
    $sqlQuery = "SELECT robot, time_sec, SUM(points) AS total_points 
              FROM scouting_submissions 
              GROUP BY robot, time_sec 
              ORDER BY robot, time_sec;";

    
    $stmt = $pdo->prepare($sqlQuery);
    $stmt->execute();
    
    // Fetch data as an associative array
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

?>