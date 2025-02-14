<?php
require_once '../php/database_connection.php'; // Ensure correct path

if (!isset($_GET['event_name']) || empty($_GET['event_name'])) {
    die("<p style='text-align: center;'>No event selected.</p>");
}

$event_name = $_GET['event_name'];

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
    $robot_query = $pdo->query("SELECT * FROM temp_robot_categories ORDER BY robot ASC");
    $robots = $robot_query->fetchAll(PDO::FETCH_ASSOC);

    if (empty($robots)) {
        die("<p style='text-align: center;'>No data available for this event.</p>");
    }

    // Output Table
    echo "<table border='1' style='width:100%; border-collapse: collapse; text-align: center;'>
            <thead>
                <tr>
                    <th>Robot</th>
                    <th>Matches Played</th>
                    <th>Top Scoring Location</th>
                    <th>Offense Score</th>
                    <th>Defense Score</th>
                    <th>Auton Score</th>
                    <th>Cooperative Score</th>
                    <th>Level 1 Scores</th>
                    <th>Level 2 Scores</th>
                    <th>Level 3 Scores</th>
                    <th>Level 4 Scores</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($robots as $robot) {
        echo "<tr>
                <td>{$robot['robot']}</td>
                <td>{$robot['match_count']}</td>
                <td>{$robot['top_scoring_location']}</td>
                <td>{$robot['offense_score']}</td>
                <td>{$robot['defense_score']}</td>
                <td>{$robot['auton_score']}</td>
                <td>" . number_format($robot['cooperative_score'], 2) . "</td>
                <td>{$robot['count_level_1']}</td>
                <td>{$robot['count_level_2']}</td>
                <td>{$robot['count_level_3']}</td>
                <td>{$robot['count_level_4']}</td>
              </tr>";
    }

    echo "</tbody></table>";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
