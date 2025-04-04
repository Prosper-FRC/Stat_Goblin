<?php
require_once '../php/database_connection.php';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

    // For debugging, fetch distinct event names
    $stmt = $pdo->query("SELECT DISTINCT event_name FROM scouting_submissions ORDER BY event_name");
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $selected_event = isset($_GET['event_name']) ? $_GET['event_name'] : '';
    $results = [];
    
    if ($selected_event) {
        // Begin transaction so all queries run on the same connection
        $pdo->beginTransaction();
        
        // Set the event name in a user variable
        $stmt = $pdo->prepare("SET @event_name := ?");
        $stmt->execute([$selected_event]);
        
        // Step 1: Create Temporary Table
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_robot_categories");
        $pdo->exec("
            CREATE TEMPORARY TABLE temp_robot_categories (
                robot INT PRIMARY KEY,
                starting_position VARCHAR(30),
                auton_path VARCHAR(30),
                offense_score DECIMAL(5,2) DEFAULT 0,
                defense_score DECIMAL(5,2) DEFAULT 0,
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
                level1_avg_attempts DECIMAL(5,2) DEFAULT 0,
                level2_avg_attempts DECIMAL(5,2) DEFAULT 0,
                level3_avg_attempts DECIMAL(5,2) DEFAULT 0,
                level4_avg_attempts DECIMAL(5,2) DEFAULT 0,
                algae_net_attempts INT DEFAULT 0,
                algae_net_success INT DEFAULT 0,
                algae_net_avg_attempts DECIMAL(5,2) DEFAULT 0,
                algae_processor_attempts INT DEFAULT 0,
                algae_processor_success INT DEFAULT 0,
                algae_processor_avg_attempts DECIMAL(5,2) DEFAULT 0,
                high_score INT DEFAULT 0,
                high_score_match INT DEFAULT 0,
                seconds_per_score DECIMAL(5,2) DEFAULT 0
            )
        ");

        // Step 2: Insert unique robots for the event
        $stmt = $pdo->prepare("
            INSERT INTO temp_robot_categories (robot)
            SELECT DISTINCT robot FROM scouting_submissions WHERE event_name = @event_name
        ");
        $stmt->execute();

        // Steps 3-13: Run your update queries (same as before, using @event_name)
        // Offense Score
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, AVG(offense_score) AS offense_score
                FROM (
                    SELECT robot, match_no, SUM(points) AS offense_score
                    FROM scouting_submissions
                    WHERE event_name = @event_name
                    GROUP BY robot, match_no
                ) AS ff
                GROUP BY robot
            ) AS offense_data ON rc.robot = offense_data.robot
            SET rc.offense_score = offense_data.offense_score
        ");
        // Defense Score
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) / COUNT(DISTINCT match_no) AS defense_score
                FROM scouting_submissions
                WHERE event_name = @event_name
                  AND action IN ('plays_defense', 'block')
                GROUP BY robot
            ) AS defense_data ON rc.robot = defense_data.robot
            SET rc.defense_score = defense_data.defense_score
        ");
        // Autonomous Score
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS auton_score
                FROM scouting_submissions
                WHERE event_name = @event_name
                  AND time_sec <= 15 AND result = 'success'
                GROUP BY robot
            ) AS auton_data ON rc.robot = auton_data.robot
            SET rc.auton_score = auton_data.auton_score
        ");
        // Match Count
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(DISTINCT match_no) AS match_count
                FROM scouting_submissions
                WHERE event_name = @event_name
                GROUP BY robot
            ) AS match_data ON rc.robot = match_data.robot
            SET rc.match_count = match_data.match_count
        ");
        // Coral Scoring Counts for Levels 1-4
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_1
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_1' AND result = 'success'
                GROUP BY robot
            ) AS level1 ON rc.robot = level1.robot
            SET rc.count_level_1 = level1.count_level_1
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_2
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_2' AND result = 'success'
                GROUP BY robot
            ) AS level2 ON rc.robot = level2.robot
            SET rc.count_level_2 = level2.count_level_2
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_3
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_3' AND result = 'success'
                GROUP BY robot
            ) AS level3 ON rc.robot = level3.robot
            SET rc.count_level_3 = level3.count_level_3
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS count_level_4
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_4' AND result = 'success'
                GROUP BY robot
            ) AS level4 ON rc.robot = level4.robot
            SET rc.count_level_4 = level4.count_level_4
        ");
        // Coral Scoring Attempts for Levels 1-4
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level1_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_1'
                GROUP BY robot
            ) AS att1 ON rc.robot = att1.robot
            SET rc.level1_attempts = att1.level1_attempts
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level2_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_2'
                GROUP BY robot
            ) AS att2 ON rc.robot = att2.robot
            SET rc.level2_attempts = att2.level2_attempts
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level3_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_3'
                GROUP BY robot
            ) AS att3 ON rc.robot = att3.robot
            SET rc.level3_attempts = att3.level3_attempts
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS level4_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_coral_level_4'
                GROUP BY robot
            ) AS att4 ON rc.robot = att4.robot
            SET rc.level4_attempts = att4.level4_attempts
        ");
        // Top Scoring Location
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, top_scoring_location FROM (
                    SELECT robot, action AS top_scoring_location,
                           ROW_NUMBER() OVER (PARTITION BY robot ORDER BY COUNT(*) DESC) AS rn
                    FROM scouting_submissions
                    WHERE event_name = @event_name
                      AND action IN ('scores_coral_level_1', 'scores_coral_level_2', 'scores_coral_level_3', 'scores_coral_level_4', 'scores_algae_net', 'scores_algae_processor')
                      AND result = 'success'
                    GROUP BY robot, action
                ) ranked
                WHERE rn = 1
            ) AS top_score_data ON rc.robot = top_score_data.robot
            SET rc.top_scoring_location = top_score_data.top_scoring_location
        ");
        // Algae Scoring Attempts and Successes
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS algae_net_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_algae_net'
                GROUP BY robot
            ) AS net_attempts ON rc.robot = net_attempts.robot
            SET rc.algae_net_attempts = net_attempts.algae_net_attempts
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS algae_net_success
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_algae_net' AND result = 'success'
                GROUP BY robot
            ) AS net_success ON rc.robot = net_success.robot
            SET rc.algae_net_success = net_success.algae_net_success
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS algae_processor_attempts
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_algae_processor'
                GROUP BY robot
            ) AS proc_attempts ON rc.robot = proc_attempts.robot
            SET rc.algae_processor_attempts = proc_attempts.algae_processor_attempts
        ");
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, COUNT(*) AS algae_processor_success
                FROM scouting_submissions
                WHERE event_name = @event_name 
                  AND action = 'scores_algae_processor' AND result = 'success'
                GROUP BY robot
            ) AS proc_success ON rc.robot = proc_success.robot
            SET rc.algae_processor_success = proc_success.algae_processor_success
        ");
        // Cooperative Score
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS match_scores");
        $pdo->exec("
            CREATE TEMPORARY TABLE match_scores AS
            SELECT match_no, SUM(points) AS total_score
            FROM scouting_submissions
            WHERE event_name = @event_name
            GROUP BY match_no
        ");
        $pdo->exec("DROP TEMPORARY TABLE IF EXISTS robot_alliance_scores");
        $pdo->exec("
            CREATE TEMPORARY TABLE robot_alliance_scores AS
            SELECT ss.robot, AVG(ms.total_score) AS avg_alliance_score
            FROM scouting_submissions ss
            JOIN match_scores ms ON ss.match_no = ms.match_no
            WHERE ss.event_name = @event_name
            GROUP BY ss.robot
        ");
        $avg_alliance = $pdo->query("SELECT AVG(total_score) FROM match_scores")->fetchColumn();
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN robot_alliance_scores ras ON rc.robot = ras.robot
            SET rc.cooperative_score = ras.avg_alliance_score - {$avg_alliance}
        ");
        // Starting Position and Auton Path
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, starting_position, auton_path FROM (
                    SELECT 
                        robot,
                        MAX(CASE WHEN action LIKE 's%' THEN action END) AS starting_position,
                        MAX(CASE WHEN action LIKE 'a%' THEN action END) AS auton_path,
                        ROW_NUMBER() OVER (PARTITION BY robot ORDER BY COUNT(*) DESC) AS rn
                    FROM scouting_submissions
                    WHERE event_name = @event_name
                      AND action IN ('starting_position_1', 'starting_position_2', 'starting_position_3', 'auton_left', 'auton_center', 'auton_right')
                    GROUP BY robot, action
                ) sub
                WHERE rn = 1
            ) AS subquery ON rc.robot = subquery.robot
            SET rc.starting_position = subquery.starting_position,
                rc.auton_path = subquery.auton_path
        ");
        // High Score and Best Match
        $pdo->exec("
            UPDATE temp_robot_categories rc
            JOIN (
                SELECT robot, match_no, points
                FROM (
                    SELECT robot, match_no, SUM(points) AS points,
                           RANK() OVER (PARTITION BY robot ORDER BY SUM(points) DESC) AS rn
                    FROM scouting_submissions
                    WHERE event_name = @event_name
                    GROUP BY robot, match_no
                ) ranked
                WHERE rn = 1
            ) AS subquery ON rc.robot = subquery.robot
            SET rc.high_score = subquery.points,
                rc.high_score_match = subquery.match_no
        ");



// Update average attempts for scoring actions (make sure to adjust conditions as needed)
$pdo->exec("
    UPDATE temp_robot_categories rc
    JOIN (
        SELECT
           robot,
           COUNT(CASE WHEN action = 'scores_coral_level_1' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS level1_avg,
           COUNT(CASE WHEN action = 'scores_coral_level_2' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS level2_avg,
           COUNT(CASE WHEN action = 'scores_coral_level_3' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS level3_avg,
           COUNT(CASE WHEN action = 'scores_coral_level_4' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS level4_avg,
           COUNT(CASE WHEN action = 'scores_algae_net' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS algae_net_avg,
           COUNT(CASE WHEN action = 'scores_algae_processor' AND result = 'success' THEN 1 END) / COUNT(DISTINCT match_no) AS algae_processor_avg
        FROM scouting_submissions
        WHERE event_name = @event_name
        GROUP BY robot
    ) AS sub ON rc.robot = sub.robot
    SET 
        rc.level1_avg_attempts = sub.level1_avg,
        rc.level2_avg_attempts = sub.level2_avg,
        rc.level3_avg_attempts = sub.level3_avg,
        rc.level4_avg_attempts = sub.level4_avg,
        rc.algae_net_avg_attempts = sub.algae_net_avg,
        rc.algae_processor_avg_attempts = sub.algae_processor_avg
");





        // seconds_per_score using CTE

$pdo->exec("
DROP TEMPORARY TABLE IF EXISTS score_data;
CREATE TEMPORARY TABLE score_data AS
WITH time_differences AS (
    SELECT match_no, robot, time_sec,
           LAG(time_sec) OVER (PARTITION BY match_no, robot ORDER BY time_sec) AS prev_time
    FROM scouting_submissions 
    WHERE time_sec < 140
    ORDER BY match_no, robot, time_sec
),
time_differences2 AS (
    SELECT match_no, robot, AVG(time_sec - prev_time) AS avg_time_diff
    FROM time_differences
    WHERE prev_time IS NOT NULL
    GROUP BY match_no, robot
),
score_data_cte AS (
    SELECT robot, AVG(avg_time_diff) AS seconds_per_score
    FROM time_differences2
    GROUP BY robot
)
SELECT * FROM score_data_cte;
UPDATE temp_robot_categories rc
JOIN score_data sd ON rc.robot = sd.robot
SET rc.seconds_per_score = sd.seconds_per_score;
");

$pdo->commit();

// Retrieve final results
$stmt = $pdo->query("
    SELECT 
        robot,
        seconds_per_score,
        cooperative_score,
        auton_score,
        defense_score,
        offense_score,
        top_scoring_location,
        match_count,
        count_level_1 AS level_1_scores,
        count_level_2 AS level_2_scores,
        count_level_3 AS level_3_scores,
        count_level_4 AS level_4_scores,
        IF(level1_attempts > 0, ROUND((count_level_1 / level1_attempts) * 100, 2), 0) AS level_1_scoring_rate,
        IF(level2_attempts > 0, ROUND((count_level_2 / level2_attempts) * 100, 2), 0) AS level_2_scoring_rate,
        IF(level3_attempts > 0, ROUND((count_level_3 / level3_attempts) * 100, 2), 0) AS level_3_scoring_rate,
        IF(level4_attempts > 0, ROUND((count_level_4 / level4_attempts) * 100, 2), 0) AS level_4_scoring_rate,
        algae_net_success AS algae_net_scores,
        IF(algae_net_attempts > 0, ROUND((algae_net_success / algae_net_attempts) * 100, 2), 0) AS algae_net_scoring_rate,
        algae_net_avg_attempts,
        algae_processor_success,
        IF(algae_processor_attempts > 0, ROUND((algae_processor_success / algae_processor_attempts) * 100, 2), 0) AS algae_processor_scoring_rate,
        algae_processor_avg_attempts,
        high_score,
        high_score_match
    FROM temp_robot_categories
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
} catch (PDOException $e) {
    // Rollback if there was an error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database error: " . $e->getMessage());
}
?>



<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>The Stat Owl - Event Analysis</title>
  <link rel="stylesheet" href="../css/select.css">
  <style>
    body {
      background-color: #222;
      color: #fff;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
    }
    h1, h2 { color: #fff; }
    select, input[type="submit"] {
      background-color: #333;
      color: #fff;
      border: 1px solid #fff;
      padding: 8px;
      margin: 10px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    table, th, td { border: 1px solid #fff; }
    th, td {
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #333;
      cursor: pointer;
      user-select: none;
    }


.table-container {
    overflow-x: auto; /* Enable horizontal scrolling */
    margin-top: 20px;
}

#resultsTable {
    border-collapse: collapse;
    width: 100%;
}

#resultsTable th, #resultsTable td {
    padding: 8px;
    border: 1px solid #fff;
    text-align: left;
}

/* Lock the first column */
#resultsTable th:first-child, 
#resultsTable td:first-child {
    position: sticky;
    left: 0;
    background-color: #333;  /* Use a contrasting color so the fixed column stands out */
    z-index: 1;              /* Ensure it appears above other cells */
}

/* For header cell, add extra z-index so it stays on top */
#resultsTable th:first-child {
    z-index: 2;
}
/* Add this to your CSS */
#resultsTable tr.highlight {
    background-color: #555; /* Adjust to your desired highlight color */
}
      .icon {
      width: 80px;
      }
select{min-width: 300px}
a{color:#fff}


.modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5); /* Semi-transparent background */
  }
  .modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%; /* Adjust width as needed */
    max-width: 1000px;
    border-radius: 5px;
    position: relative;
  }
  .close {
    color: #aaa;
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
  }
  .close:hover,
  .close:focus {
    color: #000;
    text-decoration: none;
  }
         @font-face {
            font-family: 'Roboto';
            src: url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/roboto/Roboto-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Griffy';
            src: url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Griffy/Griffy-Regular.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            @font-face {
            font-family: 'Comfortaa';
            src: url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf'),
            url('/../stat_goblin/fonts/Comfortaa/Comfortaa-VariableFont_wght.ttf') format('ttf');
            font-weight: normal;
            font-style: normal;
            }
            /* Global Styles */
      
            body, html {
            font-family: 'Comfortaa', sans-serif;
      margin: 0;
      padding: 0;
      background: #222;
      color: #eee;
      line-height: 1.5;
      text-align: center;
    }
        .logo {
            width: 100%;
            max-width: 400px;
            display: block;
            margin: 0 auto 1rem auto;
        }


  </style>



</head>
<body>

      <a href="..">
        <img src="../images/theStatOwl.png" class="logo" alt="Logo">
    </a>

  <form method="get" action="">
    <label for="event_name"></label>
    <select name="event_name" id="event_name">
      <option value="">Choose an event</option>
      <?php foreach ($events as $event): ?>
        <option value="<?= htmlspecialchars($event) ?>" <?= ($event === $selected_event) ? 'selected' : '' ?>>
          <?= htmlspecialchars($event) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="submit" value="Analyze">



      <!-- Your grid item with the chart link -->
<div class="grid-item">
  <label for="eventDropdown"><strong></strong></label>


  <!-- Change the href to '#' and give the link an id -->
  <a href="#" id="openChart">
    <img class="icon" src="../icons/heyitsachart.png" alt="Admin Console">
  </a>
</div>

<!-- Modal Markup -->
<div id="chartModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <!-- Iframe will load charts.php -->
    <iframe src="" id="chartIframe" frameborder="0" style="width:100%; height:80vh;"></iframe>
  </div>
</div>





  </form>
  <?php $selected_event = isset($_GET['event_name']) ? $_GET['event_name'] : '';
if ($selected_event): ?>
    <h2>Results for event: <?= htmlspecialchars($selected_event) ?></h2>
    <?php if ($results): ?>
      <table id="resultsTable">
        <thead>
          <tr>
            <?php foreach (array_keys($results[0]) as $col): ?>
              <th data-sort="asc"><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
<tbody>
    <?php foreach ($results as $row): ?>
        <tr>
            <?php foreach ($row as $col => $cell): ?>
                <td>
                    <?php if ($col === 'robot'): ?>
                        <a href="https://www.thebluealliance.com/team/<?= htmlspecialchars($cell) ?>" target="_blank">
                            <?= htmlspecialchars($cell) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($cell) ?>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
</tbody>
      </table>
    <?php else: ?>
      <p>No data available for this event.</p>
    <?php endif; ?>
  <?php endif; ?>

  <script>
    // Vanilla JS table sorting:
    document.addEventListener("DOMContentLoaded", function() {
      const table = document.getElementById("resultsTable");
      if (!table) return;
      const headers = table.querySelectorAll("th");
      headers.forEach((header, columnIndex) => {
        header.addEventListener("click", function() {
          // Get current sort direction from the data-sort attribute
          const currentAsc = header.getAttribute("data-sort") === "asc";
          sortTableByColumn(table, columnIndex, currentAsc);
          // Toggle the sort direction for next click
          header.setAttribute("data-sort", currentAsc ? "desc" : "asc");
        });
      });
    });

    function sortTableByColumn(table, columnIndex, asc = true) {
      const tbody = table.tBodies[0];
      const rowsArray = Array.from(tbody.querySelectorAll("tr"));
      rowsArray.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        // Try to compare as numbers first
        const aNum = parseFloat(aText);
        const bNum = parseFloat(bText);
        if (!isNaN(aNum) && !isNaN(bNum)) {
          return asc ? aNum - bNum : bNum - aNum;
        }
        // Fallback to localeCompare for strings
        return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
      });
      // Append the sorted rows back to the tbody
      rowsArray.forEach(row => tbody.appendChild(row));
    }

document.addEventListener("DOMContentLoaded", function() {
    const rows = document.querySelectorAll("#resultsTable tbody tr");
    rows.forEach(row => {
        row.addEventListener("click", function() {
            // Remove highlight from all rows
            rows.forEach(r => r.classList.remove("highlight"));
            // Add highlight to the clicked row
            this.classList.add("highlight");
        });
    });
});




  </script>




  <script>
  // When the user clicks the chart link, open the modal and load charts.php into the iframe
  document.getElementById('openChart').addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('chartIframe').src = 'charts.php';
      document.getElementById('chartModal').style.display = 'block';
  });

  // Get the <span> element that closes the modal
  const closeBtn = document.querySelector('.close');
  closeBtn.addEventListener('click', function() {
      document.getElementById('chartModal').style.display = 'none';
      // Optionally clear the iframe src if you want to reset the page
      document.getElementById('chartIframe').src = '';
  });

  // When the user clicks outside of the modal content, close the modal
  window.addEventListener('click', function(e) {
      const modal = document.getElementById('chartModal');
      if (e.target === modal) {
          modal.style.display = 'none';
          document.getElementById('chartIframe').src = '';
      }
  });
</script>
</body>
</html>
