<?php
header('Content-Type: application/json');

require_once('../../php/database_connection.php');

try {
    $sqlQuery = "SELECT 
    a.robot, 
    AVG(a.total_points) AS avg_total_points,
    AVG(a.offensive_activities) as offense_activities,
    COALESCE(SUM(a.successful_offensive_activities) / NULLIF(SUM(a.offensive_activities), 0), 0) AS offensive_performance_rate

FROM (
    SELECT 
        robot, 
        match_no, 
        SUM(points) AS total_points,

        -- Count of offensive actions (excluding defensive ones)
        COUNT(CASE 
            WHEN action NOT IN ('tipped_over', 'disabled', 'plays_defense', 'attempts_to_steal')  
            THEN id 
        END) AS offensive_activities,

        -- Count of successful offensive actions
        COUNT(CASE 
            WHEN action NOT IN ('tipped_over', 'disabled', 'plays_defense', 'attempts_to_steal') 
              AND result = 'Success' 
            THEN id 
        END) AS successful_offensive_activities

    FROM scouting_submissions 
    GROUP BY robot, match_no 
) a

GROUP BY a.robot;";

    
    $stmt = $pdo->prepare($sqlQuery);
    $stmt->execute();
    
    // Fetch data as an associative array
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
}

?>