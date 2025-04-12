<?php
// match_analysis.php

// Enable error reporting for debugging.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection settings.
require_once '../php/database_connection.php';

try {
    // Create a PDO connection.
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get selected event and robot from GET parameters.
$selected_event = isset($_GET['event']) ? $_GET['event'] : "";
$selected_robot = isset($_GET['robot']) ? $_GET['robot'] : "";

// Query distinct events from the active_event table.
$eventStmt = $pdo->query("SELECT DISTINCT event_name FROM active_event ORDER BY event_name ASC");
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

// If an event is selected, get distinct robot numbers.
$robots = [];
if (!empty($selected_event)) {
    $stmt = $pdo->prepare("SELECT DISTINCT robot FROM active_event WHERE event_name = ? ORDER BY robot ASC");
    $stmt->execute([$selected_event]);
    $robots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If both event and robot are selected, get all distinct match numbers (using match_number)
// in which that robot appears.
$matches = [];
if (!empty($selected_event) && !empty($selected_robot)) {
    $stmt = $pdo->prepare("SELECT DISTINCT match_number FROM active_event WHERE event_name = ? AND robot = ? ORDER BY match_number ASC");
    $stmt->execute([$selected_event, $selected_robot]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Match Analysis Dashboard</title>
  <style>
<style>
    /* --- Font Faces --- */
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
    }
    h1, h2, h3, h4 {
      text-align: center;
    }

  h1, h2 { color: #fff; }
    select, input[type="submit"] {
      background-color: #333;
      color: #fff;
      border: 1px solid #fff;
      padding: 8px;
      margin: 10px 0;
      width:220px;
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
      background-color: #222;
      cursor: pointer;
      user-select: none;
    }

    form {
      text-align: center;
      margin-bottom: 20px;
    }
    label {
      font-weight: bold;
      margin-right: 5px;
    }

    .match-section {
      margin: 20px auto;
      background: #333;
      border: 1px solid #ccc;
      border-radius: 5px;
      padding: 15px;
      max-width: 900px;
    }
    .match-section h3 {
      margin-top: 0;
    }
        .logo {
            width: 100%;
            max-width: 400px;
            display: block;
            margin: 0 auto 1rem auto;
        }

  </style>
   <link rel="stylesheet" href="../css/select.css">
  <script>
    // Function to fetch metrics for a given match.
    function fetchMatchMetrics(matchNumber) {
      var eventName = document.getElementById('eventDropdown').value;
      if (!eventName || !matchNumber) return;
      
      // Call your fetch_robot_data.php (which is used by other pages) using the expected parameters.
      var url = "fetch_robot_data.php?event_name=" + encodeURIComponent(eventName) + "&match_number=" + encodeURIComponent(matchNumber);
      
      var xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.onreadystatechange = function() {
          if (xhr.readyState === 4) {
              if (xhr.status === 200) {
                  try {
                      var response = JSON.parse(xhr.responseText);
                      if (response.error) {
                          console.error("Error for match " + matchNumber + ": " + response.error);
                          document.getElementById("match_" + matchNumber).innerHTML = "<p>Error: " + response.error + "</p>";
                      } else {
                          displayMatchMetrics(matchNumber, response);
                      }
                  } catch(e) {
                      console.error("JSON parse error for match " + matchNumber + ":", e);
                  }
              } else {
                  console.error("HTTP error (" + xhr.status + ") for match " + matchNumber);
              }
          }
      };
      xhr.send();
    }

    // Function to compute derived metrics for a robot.
    function computeDerivedMetrics(robot) {
      // Average score for a level is computed as count_level_X / levelX_attempts.
      // Success rate is that ratio expressed as a percentage.
      function calcAvg(count, attempts) {
        return (attempts > 0) ? (count / attempts).toFixed(2) : "0";
      }
      function calcRate(count, attempts) {
        return (attempts > 0) ? ((count / attempts) * 100).toFixed(1) + "%" : "0%";
      }
      return {
        level1_avg: calcAvg(robot.count_level_1, robot.level1_attempts),
        level1_rate: calcRate(robot.count_level_1, robot.level1_attempts),
        level2_avg: calcAvg(robot.count_level_2, robot.level2_attempts),
        level2_rate: calcRate(robot.count_level_2, robot.level2_attempts),
        level3_avg: calcAvg(robot.count_level_3, robot.level3_attempts),
        level3_rate: calcRate(robot.count_level_3, robot.level3_attempts),
        level4_avg: calcAvg(robot.count_level_4, robot.level4_attempts),
        level4_rate: calcRate(robot.count_level_4, robot.level4_attempts)
      };
    }

    // Function to display the fetched metrics for a specific match.
    function displayMatchMetrics(matchNumber, data) {
      // Expected JSON structure from fetch_robot_data.php: { "robots": [ {...}, {...} ] }.
      var container = document.getElementById("match_" + matchNumber);
      if (!container) {
          console.error("Container 'match_" + matchNumber + "' not found.");
          return;
      }
      
      // Separate the robots by alliance.
      var blueAlliance = [];
      var redAlliance = [];
      if (data.robots && Array.isArray(data.robots)) {
          data.robots.forEach(function(robot) {
              if (robot.alliance && typeof robot.alliance === "string") {
                  if (robot.alliance.toLowerCase() === "blue") {
                      blueAlliance.push(robot);
                  } else if (robot.alliance.toLowerCase() === "red") {
                      redAlliance.push(robot);
                  }
              }
          });
      }
      
      var html = "<h3>Match " + matchNumber + "</h3>";
      
      // Function to build a table for an alliance.
      function buildTable(robots) {
          var tableHtml = "<table><thead><tr>" +
              "<th>Robot</th>" +
              "<th>Level 1 </th>" +
          
              "<th>Level 2 </th>" +
             
              "<th>Level 3</th>" +
              
              "<th>Level 4</th>" +
            
              "<th>Algae (Processor)</th>" +
              "<th>Algae (Net)</th>" +
              "<th>Deep Climb Success/Attempts</th>" +
              "<th>Shallow Climb Success/Attempts</th>" +
              "<th>Auton Score</th>" +
              "</tr></thead><tbody>";
          robots.forEach(function(r) {
              // Compute derived values.
              var metrics = computeDerivedMetrics(r);
              tableHtml += "<tr>" +
                  "<td>" + r.robot + "</td>" +
                  "<td>" + (r.count_level_1 / r.match_count).toFixed(2) + "/" +
" " + metrics.level1_rate + "</td>" +
"<td>" + (r.count_level_2 / r.match_count).toFixed(2) + "/" +
" " + metrics.level2_rate + "</td>" +
"<td>" + (r.count_level_3 / r.match_count).toFixed(2) + "/" +
" " + metrics.level3_rate + "</td>" +
"<td>" + (r.count_level_4 / r.match_count).toFixed(2) + "/" +
" " + metrics.level4_rate + "</td>" +
                  "<td>" + (r.algae_processor_success !== undefined ? r.algae_processor_success : "0") + "</td>" +
                  "<td>" + (r.algae_net_success !== undefined ? r.algae_net_success : "0") + "</td>" +

                  "<td>" + (r.deep_climb_success !== undefined ? r.deep_climb_success : "0") + " / " +

(r.deep_climb_attempts !== undefined ? r.deep_climb_attempts : "0") + "</td>" +
                                    "<td>" + (r.shallow_climb_success !== undefined ? r.shallow_climb_success : "0") + " / " +

(r.shallow_climb_attempts !== undefined ? r.shallow_climb_attempts : "0") + "</td>" +
                  "<td>" + (r.auton_score !== undefined ? r.auton_score : "0") + "</td>" +
                  "</tr>";
          });
          tableHtml += "</tbody></table>";
          return tableHtml;
      }
      
      if (blueAlliance.length > 0) {
          html += "<h4>Blue Alliance</h4>" + buildTable(blueAlliance);
      } else {
          html += "<p>No Blue Alliance data available.</p>";
      }
      
      if (redAlliance.length > 0) {
          html += "<h4>Red Alliance</h4>" + buildTable(redAlliance);
      } else {
          html += "<p>No Red Alliance data available.</p>";
      }
      
      container.innerHTML = html;
    }

    // Function to load metrics for all match containers.
    function loadAllMatchMetrics() {
      var matchContainers = document.getElementsByClassName("match-container-item");
      for (var i = 0; i < matchContainers.length; i++) {
          var matchNumber = matchContainers[i].getAttribute("data-match");
          fetchMatchMetrics(matchNumber);
      }
    }

    window.onload = function() {
      // When the page loads, if there are match containers, load metrics for each match.
      loadAllMatchMetrics();
    };
  </script>
</head>
<body>
     <a href=".."><img src="../images/owlAnalytics.png" class="logo" alt="Logo"></a>
  <h1>Match Analysis Dashboard</h1>
  
  <!-- Form for selecting event and robot -->
  <form method="GET" action="">
    <label for="eventDropdown"></label>
    <select id="eventDropdown" name="event" onchange="this.form.submit()">
      <option value=""> Select Event </option>
      <?php foreach ($events as $e): ?>
        <option value="<?php echo htmlspecialchars($e['event_name']); ?>"
          <?php if ($selected_event === $e['event_name']) echo "selected"; ?>>
            <?php echo htmlspecialchars($e['event_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    
    <?php if (!empty($selected_event)): ?>
      <label for="robotDropdown"></label>
      <select id="robotDropdown" name="robot" onchange="this.form.submit()">
        <option value=""> Select Robot </option>
        <?php foreach ($robots as $r): ?>
          <option value="<?php echo htmlspecialchars($r['robot']); ?>"
            <?php if ($selected_robot === $r['robot']) echo "selected"; ?>>
              <?php echo htmlspecialchars($r['robot']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </form>
  
  <!-- Container for match sections -->
  <div id="matchesContainer">
    <?php if (!empty($selected_event) && !empty($selected_robot) && count($matches) > 0): ?>
      <h2>Matches for Robot <?php echo htmlspecialchars($selected_robot); ?> in "<?php echo htmlspecialchars($selected_event); ?>"</h2>
      <?php foreach ($matches as $m):
              $match_number = $m['match_number'];
      ?>
          <!-- Each match container has an ID formatted as match_<match_number> -->
          <div id="match_<?php echo htmlspecialchars($match_number); ?>" class="match-section match-container-item" data-match="<?php echo htmlspecialchars($match_number); ?>">
              <p>Loading metrics for match <?php echo htmlspecialchars($match_number); ?>...</p>
          </div>
      <?php endforeach; ?>
    <?php elseif (!empty($selected_event) && !empty($selected_robot)): ?>
      <p>No matches found for the selected robot in this event.</p>
    <?php endif; ?>
  </div>
</body>
</html>
