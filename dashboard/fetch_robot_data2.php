<?php
require_once '../php/database_connection.php';

$event_name = isset($_GET['event_name']) ? $_GET['event_name'] : null;
if (!$event_name) {
    echo json_encode(["error" => "No event specified"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    header('Content-Type: application/json');
    ob_clean();

    // Step 1: Create Temporary Table with additional columns for attempts
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
            count_level_4 INT DEFAULT 0,
            level1_attempts INT DEFAULT 0,
            level2_attempts INT DEFAULT 0,
            level3_attempts INT DEFAULT 0,
            level4_attempts INT DEFAULT 0,
            algae_net_attempts INT DEFAULT 0,
            algae_net_success INT DEFAULT 0,
            algae_processor_attempts INT DEFAULT 0,
            algae_processor_success INT DEFAULT 0
        )
    ");

    // Step 2: Insert All Robots for the Event
    $pdo->exec("
        INSERT INTO temp_robot_categories (robot)
        SELECT DISTINCT robot 
        FROM scouting_submissions
        WHERE event_name = '$event_name'
    ");

    // Step 3: Calculate Offense, Defense, and Autonomous Scores
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS offense_score
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
              AND result = 'success'
            GROUP BY robot
        ) AS offense_data ON rc.robot = offense_data.robot
        SET rc.offense_score = offense_data.offense_score
    ");

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

    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS auton_score
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND time_sec <= 18 AND result = 'success'
            GROUP BY robot
        ) AS auton_data ON rc.robot = auton_data.robot
        SET rc.auton_score = auton_data.auton_score
    ");

    // Step 4: Update Coral Scoring Counts (Successes) for Levels 1-4
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

    // Step 5: Update Coral Scoring Attempts for Levels 1-4
    for ($level = 1; $level <= 4; $level++) {
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level{$level}_attempts
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action = 'scores_coral_level_$level'
                GROUP BY robot
            ) AS attempts_data ON rc.robot = attempts_data.robot
            SET rc.level{$level}_attempts = attempts_data.level{$level}_attempts
        ");
    }

    // Step 6: Update Top Scoring Coral Location
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, action AS top_scoring_location
            FROM (
                SELECT robot, action, COUNT(*) AS score_count,
                       ROW_NUMBER() OVER (PARTITION BY robot ORDER BY COUNT(*) DESC) AS rn
                FROM scouting_submissions
                WHERE event_name = '$event_name'
                  AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4')
                  AND result = 'success'
                GROUP BY robot, action
            ) ranked
            WHERE rn = 1
        ) AS top_score_data ON rc.robot = top_score_data.robot
        SET rc.top_scoring_location = top_score_data.top_scoring_location
    ");

    // Step 7: Update Algae Scoring for Net and Processor Attempts/Successes
    // Algae Net Attempts
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_net_attempts
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_net'
            GROUP BY robot
        ) AS net_attempts ON rc.robot = net_attempts.robot
        SET rc.algae_net_attempts = net_attempts.algae_net_attempts
    ");

    // Algae Net Success
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_net_success
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_net'
              AND result = 'success'
            GROUP BY robot
        ) AS net_success ON rc.robot = net_success.robot
        SET rc.algae_net_success = net_success.algae_net_success
    ");

    // Algae Processor Attempts
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_processor_attempts
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_processor'
            GROUP BY robot
        ) AS proc_attempts ON rc.robot = proc_attempts.robot
        SET rc.algae_processor_attempts = proc_attempts.algae_processor_attempts
    ");

    // Algae Processor Success
    $pdo->exec("
        UPDATE temp_robot_categories rc
        JOIN (
            SELECT robot, COUNT(*) AS algae_processor_success
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action = 'scores_algae_processor'
              AND result = 'success'
            GROUP BY robot
        ) AS proc_success ON rc.robot = proc_success.robot
        SET rc.algae_processor_success = proc_success.algae_processor_success
    ");

    // Step 8: Compute Cooperative Score (existing logic)
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

    // Step 9: Update Match Count (for all matches)
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

    // Step 10: Fetch Final Data
    $robot_query = $pdo->query("
        SELECT rc.*, COALESCE(ae.alliance, 'N/A') AS alliance
        FROM temp_robot_categories rc
        LEFT JOIN active_event ae 
            ON rc.robot = ae.robot 
           AND ae.event_name = '$event_name'
        ORDER BY rc.robot ASC
    ");
    $robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);

    if (empty($robots)) {
        echo json_encode(["error" => "No robots available for this event"]);
        exit;
    }

    // Convert cooperative_score to float for proper JSON formatting
    foreach ($robots as &$robot) {
        $robot['cooperative_score'] = (float)$robot['cooperative_score'];
    }

    echo json_encode(["robots" => $robots]);
    exit;

} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit;
}
?>
