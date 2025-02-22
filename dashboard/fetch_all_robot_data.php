<?php
// fetch_all_robot_data.php

require_once '../php/database_connection.php';
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_GET['event_name'])) {
    echo json_encode(["error" => "event_name parameter is required"]);
    exit;
}

$event_name = $_GET['event_name'];

// Query to fetch distinct robot data from the scouting_submissions table for the given event.
// Adjust the field list as needed.
$stmt = $pdo->prepare("
    SELECT 
        robot, 
        alliance, 
        MAX(match_no) AS match_count, 
        MAX(top_scoring_location) AS top_scoring_location,
        AVG(offense_score) AS offense_score,
        AVG(defense_score) AS defense_score,
        AVG(auton_score) AS auton_score,
        AVG(cooperative_score) AS cooperative_score,
        SUM(count_level_1) AS count_level_1,
        SUM(level1_attempts) AS level1_attempts,
        SUM(count_level_2) AS count_level_2,
        SUM(level2_attempts) AS level2_attempts,
        SUM(count_level_3) AS count_level_3,
        SUM(level3_attempts) AS level3_attempts,
        SUM(count_level_4) AS count_level_4,
        SUM(level4_attempts) AS level4_attempts,
        SUM(algae_net_success) AS algae_net_success,
        SUM(algae_net_attempts) AS algae_net_attempts,
        SUM(algae_processor_success) AS algae_processor_success,
        SUM(algae_processor_attempts) AS algae_processor_attempts
    FROM scouting_submissions
    WHERE event_name = ?
    GROUP BY robot, alliance
");
$stmt->execute([$event_name]);
$robots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each robot, get an array of match numbers they participated in.
foreach ($robots as &$robot) {
    $stmt2 = $pdo->prepare("SELECT match_no FROM scouting_submissions WHERE event_name = ? AND robot = ?");
    $stmt2->execute([$event_name, $robot['robot']]);
    $matches = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $matches_array = [];
    foreach ($matches as $match) {
        $matches_array[] = (int)$match['match_no'];
    }
    $robot['matches_played'] = $matches_array;
}

echo json_encode($robots);
?>
