<?php
require_once '../php/database_connection.php'; // Ensure correct path

header('Content-Type: application/json'); // Force JSON output
ob_clean(); // Prevent unwanted output

if (!isset($_GET['event_name']) || empty($_GET['event_name']) || !isset($_GET['match_number']) || empty($_GET['match_number'])) {
    echo json_encode(["error" => "Missing event or match number."]);
    exit;
}

$event_name = $_GET['event_name'];
$match_number = (int)$_GET['match_number']; // Ensure it is properly set


try {
    // Create a PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Step 1: Create Temporary Table
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_robot_categories (
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

    // Step 2: Insert Unique Robots for Selected Event
    $stmt = $pdo->prepare("
        INSERT INTO temp_robot_categories (robot)
        SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = ?
    ");


    $stmt->execute([$event_name]);

    // Step 3: Update Scores (Filtered by Event)
    $queries = [
        "UPDATE temp_robot_categories rc
         JOIN (SELECT robot, COUNT(*) AS offense_score
               FROM scouting_submissions
               WHERE event_name = ? 
               AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
               AND result = 'success'
               GROUP BY robot) 
         AS offense_data ON rc.robot = offense_data.robot
         SET rc.offense_score = offense_data.offense_score",
        
        "UPDATE temp_robot_categories rc
         JOIN (SELECT robot, COUNT(*) AS defense_score
               FROM scouting_submissions
               WHERE event_name = ? 
               AND action IN ('plays_defense', 'attempts_to_descore')
               GROUP BY robot) 
         AS defense_data ON rc.robot = defense_data.robot
         SET rc.defense_score = defense_data.defense_score",
        
        "UPDATE temp_robot_categories rc
         JOIN (SELECT robot, COUNT(*) AS auton_score
               FROM scouting_submissions
               WHERE event_name = ? 
               AND time_sec <= 18 AND result = 'success'
               GROUP BY robot) 
         AS auton_data ON rc.robot = auton_data.robot
         SET rc.auton_score = auton_data.auton_score"
    ];
    
    foreach ($queries as $query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$event_name]);
    }

    // Step 4: Update Match Count
    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN (SELECT robot, COUNT(DISTINCT match_no) AS match_count
              FROM scouting_submissions
              WHERE event_name = ?
              GROUP BY robot) 
        AS match_data ON rc.robot = match_data.robot
        SET rc.match_count = match_data.match_count
    ");
    $stmt->execute([$event_name]);

    // Step 5: Update Scoring Counts for Each Level
    for ($level = 1; $level <= 4; $level++) {
        $stmt = $pdo->prepare("
            UPDATE temp_robot_categories rc
            JOIN (SELECT robot, COUNT(*) AS count_level_$level
                  FROM scouting_submissions
                  WHERE event_name = ? 
                  AND action = 'scores_coral_level_$level' AND result = 'success'
                  GROUP BY robot) 
            AS level_data ON rc.robot = level_data.robot
            SET rc.count_level_$level = level_data.count_level_$level
        ");
        $stmt->execute([$event_name]);
    }

$pdo->exec("
    UPDATE temp_robot_categories rc
    JOIN (
        SELECT robot, action AS top_scoring_location
        FROM (
            SELECT robot, action, COUNT(*) AS score_count,
                   ROW_NUMBER() OVER (PARTITION BY robot ORDER BY COUNT(*) DESC) AS rn
            FROM scouting_submissions
            WHERE event_name = '$event_name'
              AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 
                             'scores_coral_level_3', 'scores_coral_level_4')
              AND result = 'success'
            GROUP BY robot, action
        ) ranked
        WHERE rn = 1
    ) AS top_score_data ON rc.robot = top_score_data.robot
    SET rc.top_scoring_location = top_score_data.top_scoring_location
");






    // Step 6: Compute Cooperative Score
    $stmt = $pdo->prepare("
        CREATE TEMPORARY TABLE match_scores AS
        SELECT match_no, SUM(points) AS total_score
        FROM scouting_submissions
        WHERE event_name = ?
        GROUP BY match_no
    ");
    $stmt->execute([$event_name]);

    $stmt = $pdo->prepare("
        CREATE TEMPORARY TABLE robot_alliance_scores AS
        SELECT ss.robot, AVG(ms.total_score) AS avg_alliance_score
        FROM scouting_submissions ss
        JOIN match_scores ms ON ss.match_no = ms.match_no
        WHERE ss.event_name = ?
        GROUP BY ss.robot
    ");
    $stmt->execute([$event_name]);

    $stmt = $pdo->query("SELECT AVG(total_score) INTO @avg_alliance_score_overall FROM match_scores");

    $stmt = $pdo->prepare("
        UPDATE temp_robot_categories rc
        JOIN robot_alliance_scores ras ON rc.robot = ras.robot
        SET rc.cooperative_score = ras.avg_alliance_score - @avg_alliance_score_overall
    ");
    $stmt->execute();

    // Step 7: Fetch Final Data




/*
echo json_encode([
    "debug" => [
        "event_name" => $event_name,
        "match_number" => $match_number
    ]
]);
exit;
*/





if ($match_number === 'all') {
    // Show all robots for the event directly from scouting_submissions
    $robot_query = $pdo->prepare("
        SELECT DISTINCT robot 
        FROM scouting_submissions 
        WHERE event_name = ?
        ORDER BY robot ASC
    ");
    $robot_query->execute([$event_name]);
} else {
    // Use temp_robot_categories with specific match filter
    $robot_query = $pdo->prepare("
        SELECT rc.*, COALESCE(ae.alliance, 'Unknown') AS alliance
        FROM temp_robot_categories rc
        LEFT JOIN active_event ae 
            ON rc.robot = ae.robot 
           AND ae.event_name = ? 
           AND ae.match_number = ?
        WHERE rc.robot IN (
            SELECT DISTINCT robot 
            FROM scouting_submissions 
            WHERE event_name = ? 
              AND match_no = ?
        )
        ORDER BY rc.robot ASC
    ");
    $robot_query->execute([$event_name, $match_number, $event_name, $match_number]);
}

$robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);













/*

$robot_query = $pdo->prepare("
    SELECT rc.*, ae.alliance
    FROM temp_robot_categories rc
    JOIN active_event ae 
        ON rc.robot = ae.robot 
       AND CAST(ae.match_number AS CHAR) = CAST(? AS CHAR)
    WHERE ae.event_name = ? 
      AND rc.robot IN (
          SELECT DISTINCT robot FROM scouting_submissions 
          WHERE event_name = ? 
            AND CAST(match_no AS CHAR) = CAST(? AS CHAR)
      )
    ORDER BY rc.robot ASC
");
$robot_query->execute([$match_number, $event_name, $event_name, $match_number]);
$robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);
*/




    if (empty($robots)) {
        echo json_encode(["error" => "No data available for this event."]);
        exit;
    }

    // Convert cooperative_score to float for proper JSON formatting
    foreach ($robots as &$robot) {
        $robot['cooperative_score'] = (float) $robot['cooperative_score'];
    }

    // **Output JSON**
    echo json_encode($robots);
    exit;

} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit;
}
?>
