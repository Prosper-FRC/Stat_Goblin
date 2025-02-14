<?php
require_once '../php/database_connection.php'; // Ensure correct path

// Capture event name
$event_name = isset($_GET['event_name']) ? $_GET['event_name'] : null;
if (!$event_name) {
    echo json_encode(["error" => "No event specified"]);
    exit;
}

try {
    // Step 1: Create Temporary Table
    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS temp_robot_categories (
            robot INT PRIMARY KEY,
            offense_score INT DEFAULT 0,
            defense_score INT DEFAULT 0,
            auton_score INT DEFAULT 0,
            cooperative_score DECIMAL(5,2) DEFAULT 0,
            top_scoring_location VARCHAR(30),
            match_count INT DEFAULT 0,
            count_level_1 INT DEFAULT 0,
            count_level_2 INT DEFAULT 0,
            count_level_3 INT DEFAULT 0,
            count_level_4 INT DEFAULT 0
        )
    ");

    // Step 2: Insert All Robots
    $pdo->exec("
        INSERT INTO temp_robot_categories (robot)
        SELECT DISTINCT robot 
        FROM scouting_submissions
        WHERE event_name = '$event_name'
    ");

    // Step 3: Calculate Offense Score
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS offense_score
            FROM scouting_submissions
            WHERE event_name = '$event_name' 
              AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 
                             'scores_coral_level_3', 'scores_coral_level_4', 
                             'scores_algae_net', 'scores_algae_processor')
              AND result = 'success'
            GROUP BY robot
        ) AS offense_data ON rc.robot = offense_data.robot
        SET rc.offense_score = offense_data.offense_score
    ");

    // Step 4: Calculate Defense Score
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS defense_score
            FROM scouting_submissions
            WHERE event_name = '$event_name' 
              AND action IN ('plays_defense', 'attempts_to_descore')
            GROUP BY robot
        ) AS defense_data ON rc.robot = defense_data.robot
        SET rc.defense_score = defense_data.defense_score
    ");

    // Step 5: Calculate Autonomous Score
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS auton_score
            FROM scouting_submissions
            WHERE event_name = '$event_name' 
              AND time_sec <= 18 
              AND result = 'success'
            GROUP BY robot
        ) AS auton_data ON rc.robot = auton_data.robot
        SET rc.auton_score = auton_data.auton_score
    ");

    // Step 6: Calculate Cooperative Score
    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS match_scores AS
        SELECT match_no, SUM(points) AS total_score
        FROM scouting_submissions
        WHERE event_name = '$event_name'
        GROUP BY match_no
    ");

    $pdo->exec("
        CREATE TEMPORARY TABLE IF NOT EXISTS robot_alliance_scores AS
        SELECT ss.robot, AVG(ms.total_score) AS avg_alliance_score
        FROM scouting_submissions ss
        JOIN match_scores ms ON ss.match_no = ms.match_no
        WHERE ss.event_name = '$event_name'
        GROUP BY ss.robot
    ");

    $pdo->query("SELECT AVG(total_score) INTO @avg_alliance_score_overall FROM match_scores");

    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN robot_alliance_scores ras ON rc.robot = ras.robot
        SET rc.cooperative_score = ras.avg_alliance_score - @avg_alliance_score_overall
    ");

    // Step 7: Count Scoring Levels
    for ($level = 1; $level <= 4; $level++) {
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_$level
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action = 'scores_coral_level_$level' 
                  AND result = 'success'
                GROUP BY robot
            ) AS level_data ON rc.robot = level_data.robot
            SET rc.count_level_$level = level_data.count_level_$level
        ");
    }

// Step 7: Calculate Matches Played (for ALL matches)
$pdo->exec("
    UPDATE temp_robot_categories rc
    JOIN (
        SELECT robot, COUNT(DISTINCT match_no) AS match_count
        FROM scouting_submissions
        WHERE event_name = '$event_name'
        GROUP BY robot
    ) AS match_data ON rc.robot = match_data.robot
    SET rc.match_count = match_data.match_count
");


    // Step 8: Get Alliances from active_event
    $robot_query = $pdo->query("
        SELECT rc.*, 'N/A' AS alliance
        FROM temp_robot_categories rc
        LEFT JOIN active_event ae 
            ON rc.robot = ae.robot 
           AND ae.event_name = '$event_name'
        ORDER BY rc.robot ASC
    ");
    
    $robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);

    // If no robots found
    if (empty($robots)) {
        echo json_encode([
            "debug" => [
                "event_name" => $event_name
            ],
            "error" => "No robots available for this event"
        ]);
        exit;
    }

    // Step 9: Return JSON with robot data
    echo json_encode([
        "debug" => [
            "event_name" => $event_name
        ],
        "robots" => $robots
    ]);

} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
